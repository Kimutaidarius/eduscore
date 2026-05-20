<?php
// classes/ReportGenerator.php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/report_config.php';

use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;

class ReportGenerator {
    private $db;
    private $school_id;
    private $teacher_id;
    private $gradingCache = [];
    
    public function __construct($db, $school_id, $teacher_id) {
        $this->db = $db;
        $this->school_id = $school_id;
        $this->teacher_id = $teacher_id;
        $this->loadGradingScale();
    }
    
    /**
     * Load and cache grading scale for the school
     */
    private function loadGradingScale() {
        if (!isset($this->gradingCache[$this->school_id])) {
            $stmt = $this->db->prepare("
                SELECT grade, min_score, max_score, points, remark 
                FROM grading_scales 
                WHERE school_id = :school_id OR is_default = 1
                ORDER BY min_score DESC
            ");
            $stmt->execute([':school_id' => $this->school_id]);
            $this->gradingCache[$this->school_id] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        return $this->gradingCache[$this->school_id];
    }
    
    /**
     * Calculate grade based on percentage score
     */
    private function calculateGrade($percentage) {
        $scales = $this->gradingCache[$this->school_id];
        foreach ($scales as $scale) {
            if ($percentage >= $scale['min_score'] && $percentage <= $scale['max_score']) {
                return [
                    'grade' => $scale['grade'],
                    'points' => $scale['points'],
                    'remark' => $scale['remark']
                ];
            }
        }
        return ['grade' => 'N/A', 'points' => 0, 'remark' => 'Not Graded'];
    }
    
    /**
     * Generate directory path for school
     */
    private function getSchoolDirectory($type = 'merged') {
        $basePath = ($type === 'merged') ? REPORTS_BASE_PATH : STUDENT_REPORTS_BASE_PATH;
        $schoolPath = $basePath . '/' . $this->school_id;
        return ensureDirectoryExists($schoolPath);
    }
    
    /**
     * Generate single student report PDF
     */
    public function generateSingleStudentReport($student_id, $exam_id, $term_id, $academic_year) {
        try {
            // Fetch student data with scores in bulk query
            $studentData = $this->fetchStudentData($student_id, $exam_id, $term_id, $academic_year);
            
            if (!$studentData) {
                throw new Exception("No data found for student ID: $student_id");
            }
            
            // Calculate mean score and grade
            $meanScore = $this->calculateMeanScore($studentData['scores']);
            $gradeInfo = $this->calculateGrade($meanScore);
            
            // Generate PDF
            $pdf = new PersonalReportCardPDF($this->getSchoolInfo(), $studentData);
            $pdf->AddPage();
            $pdf->StudentDetailsBox();
            $pdf->SubjectsTable();
            $pdf->PerformanceTrend();
            $pdf->RemarksAndSignatures();
            
            // Save file
            $filename = 'report_' . time() . '_' . $student_id . '.pdf';
            $schoolPath = $this->getSchoolDirectory('student');
            $filePath = $schoolPath . '/' . $filename;
            $relativePath = 'student_reports/' . $this->school_id . '/' . $filename;
            
            $pdf->Output('F', $filePath);
            
            // Verify file was saved
            if (!file_exists($filePath)) {
                throw new Exception("Failed to save PDF file: $filePath");
            }
            
            // Log debugging info
            error_log("PDF generated - Absolute: $filePath, Relative: $relativePath");
            
            // Save to database
            $this->saveStudentReport($student_id, $exam_id, $term_id, $academic_year, $meanScore, $gradeInfo['grade'], $relativePath);
            
            return [
                'success' => true,
                'pdf_url' => BASE_URL . '/' . $relativePath,
                'absolute_path' => $filePath
            ];
            
        } catch (Exception $e) {
            error_log("Single report generation failed: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Generate merged class report
     */
    public function generateMergedReport($class_id, $stream_id, $exam_id, $term_id, $academic_year, $student_ids) {
        try {
            // Fetch all students data in bulk
            $studentsData = $this->fetchMultipleStudentsData($student_ids, $exam_id, $term_id, $academic_year);
            
            if (empty($studentsData)) {
                throw new Exception("No student data found for the selected criteria");
            }
            
            // Initialize FPDI for merging
            $pdf = new FPDI();
            $totalPages = 0;
            $subjectList = [];
            
            // Generate individual PDFs for each student
            foreach ($studentsData as $index => $student) {
                // Generate student PDF in memory
                $studentPdf = $this->generateStudentPdfInMemory($student);
                
                // Import pages from the generated PDF
                $pageCount = $pdf->setSourceFile($studentPdf);
                for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                    $templateId = $pdf->importPage($pageNo);
                    $pdf->AddPage();
                    $pdf->useTemplate($templateId);
                    $totalPages++;
                }
                
                // Clean up temp file
                if (file_exists($studentPdf)) {
                    unlink($studentPdf);
                }
            }
            
            // Add summary page
            $this->addSummaryPage($pdf, $studentsData, $exam_id, $term_id);
            $totalPages++;
            
            // Save merged PDF
            $filename = 'merged_report_' . time() . '.pdf';
            $schoolPath = $this->getSchoolDirectory('merged');
            $filePath = $schoolPath . '/' . $filename;
            $relativePath = 'merged_reports/' . $this->school_id . '/' . $filename;
            
            $pdf->Output('F', $filePath);
            
            // Verify file was saved
            if (!file_exists($filePath)) {
                throw new Exception("Failed to save merged PDF file: $filePath");
            }
            
            // Calculate class statistics
            $classStats = $this->calculateClassStatistics($studentsData);
            $subjects = $this->getSubjectsForClass($class_id, $stream_id);
            
            // Save to database
            $reportId = $this->saveMergedReport(
                $class_id, $stream_id, $exam_id, $term_id, $academic_year,
                $classStats['mean_score'], $classStats['grade'],
                $subjects, $relativePath, count($studentsData), $totalPages
            );
            
            // Log debugging info
            error_log("Merged PDF generated - Absolute: $filePath, Relative: $relativePath, Pages: $totalPages");
            
            return [
                'success' => true,
                'pdf_url' => BASE_URL . '/' . $relativePath,
                'merged_report_id' => $reportId,
                'students_processed' => count($studentsData),
                'total_pages' => $totalPages,
                'absolute_path' => $filePath
            ];
            
        } catch (Exception $e) {
            error_log("Merged report generation failed: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Generate student PDF in memory and return file path
     */
    private function generateStudentPdfInMemory($studentData) {
        $pdf = new PersonalReportCardPDF($this->getSchoolInfo(), $studentData);
        $pdf->AddPage();
        $pdf->StudentDetailsBox();
        $pdf->SubjectsTable();
        $pdf->PerformanceTrend();
        $pdf->RemarksAndSignatures();
        
        $tempFile = sys_get_temp_dir() . '/student_' . uniqid() . '.pdf';
        $pdf->Output('F', $tempFile);
        
        return $tempFile;
    }
    
    /**
     * Add summary page to merged PDF
     */
    private function addSummaryPage($pdf, $studentsData, $exam_id, $term_id) {
        $pdf->AddPage();
        
        // Set font
        $pdf->SetFont('Helvetica', 'B', 16);
        $pdf->Cell(0, 10, 'CLASS SUMMARY REPORT', 0, 1, 'C');
        $pdf->Ln(10);
        
        // Class statistics
        $stats = $this->calculateClassStatistics($studentsData);
        
        $pdf->SetFont('Helvetica', 'B', 12);
        $pdf->Cell(0, 8, 'Overall Class Performance', 0, 1, 'L');
        $pdf->SetFont('Helvetica', '', 11);
        
        $pdf->Cell(80, 8, 'Class Mean Score:', 0, 0);
        $pdf->Cell(0, 8, number_format($stats['mean_score'], 2) . '%', 0, 1);
        
        $pdf->Cell(80, 8, 'Class Mean Grade:', 0, 0);
        $pdf->Cell(0, 8, $stats['grade'], 0, 1);
        
        $pdf->Cell(80, 8, 'Total Students:', 0, 0);
        $pdf->Cell(0, 8, $stats['total_students'], 0, 1);
        
        $pdf->Ln(10);
        
        // Best student
        $pdf->SetFont('Helvetica', 'B', 12);
        $pdf->Cell(0, 8, 'Best Performing Student', 0, 1, 'L');
        $pdf->SetFont('Helvetica', '', 11);
        
        $bestStudent = $stats['best_student'];
        $pdf->Cell(80, 8, 'Name:', 0, 0);
        $pdf->Cell(0, 8, $bestStudent['name'], 0, 1);
        
        $pdf->Cell(80, 8, 'Mean Score:', 0, 0);
        $pdf->Cell(0, 8, number_format($bestStudent['mean_score'], 2) . '%', 0, 1);
        
        $pdf->Cell(80, 8, 'Grade:', 0, 0);
        $pdf->Cell(0, 8, $bestStudent['grade'], 0, 1);
        
        $pdf->Ln(10);
        
        // Subject performance
        $pdf->SetFont('Helvetica', 'B', 12);
        $pdf->Cell(0, 8, 'Subject Performance Summary', 0, 1, 'L');
        
        $subjectStats = $this->calculateSubjectStatistics($studentsData);
        
        $pdf->SetFont('Helvetica', 'B', 10);
        $headers = ['Subject', 'Class Mean', 'Highest', 'Lowest', 'Grade'];
        $widths = [60, 30, 30, 30, 30];
        
        foreach ($headers as $i => $header) {
            $pdf->Cell($widths[$i], 8, $header, 1, 0, 'C');
        }
        $pdf->Ln();
        
        $pdf->SetFont('Helvetica', '', 9);
        foreach ($subjectStats as $stat) {
            $pdf->Cell($widths[0], 7, $stat['subject_name'], 1);
            $pdf->Cell($widths[1], 7, number_format($stat['mean'], 2), 1, 0, 'C');
            $pdf->Cell($widths[2], 7, number_format($stat['highest'], 2), 1, 0, 'C');
            $pdf->Cell($widths[3], 7, number_format($stat['lowest'], 2), 1, 0, 'C');
            $pdf->Cell($widths[4], 7, $stat['grade'], 1, 0, 'C');
            $pdf->Ln();
        }
        
        $pdf->Ln(10);
        
        // Grade distribution
        $pdf->SetFont('Helvetica', 'B', 12);
        $pdf->Cell(0, 8, 'Grade Distribution', 0, 1, 'L');
        
        $gradeDistribution = $stats['grade_distribution'];
        $pdf->SetFont('Helvetica', '', 10);
        
        foreach ($gradeDistribution as $grade => $count) {
            $percentage = ($count / $stats['total_students']) * 100;
            $pdf->Cell(30, 7, $grade . ':', 0, 0);
            $pdf->Cell(50, 7, $count . ' student(s)', 0, 0);
            $pdf->Cell(0, 7, number_format($percentage, 1) . '%', 0, 1);
        }
    }
    
    /**
     * Fetch single student data with scores
     */
    private function fetchStudentData($student_id, $exam_id, $term_id, $academic_year) {
        $stmt = $this->db->prepare("
            SELECT 
                s.id as student_id,
                s.AdmNo as admission_no,
                CONCAT(s.FirstName, ' ', COALESCE(s.SecondName, ''), ' ', COALESCE(s.LastName, '')) as student_name,
                s.Gender,
                s.ProfilePic as profile_pic,
                c.class_level as class_name,
                st.stream_name,
                t.term_name,
                t.academic_year,
                e.examname as exam_name,
                sub.id as subject_id,
                sub.subject_name,
                sub.alias,
                sc.score_value,
                sc.total_score,
                sc.percentage,
                sc.grade,
                tch.firstname as teacher_firstname,
                tch.secondname as teacher_secondname
            FROM tblstudents s
            INNER JOIN tblclasses c ON s.class_id = c.id
            LEFT JOIN tblstreams st ON s.StreamId = st.id
            INNER JOIN tblexam e ON e.class_id = c.id
            INNER JOIN tblterms t ON t.id = :term_id
            LEFT JOIN tblscores sc ON sc.student_id = s.id AND sc.exam_id = e.id AND sc.school_id = s.school_id
            LEFT JOIN tblsubjects sub ON sub.id = sc.subject_id
            LEFT JOIN tbllessons l ON l.subject_id = sub.id AND l.class_id = c.id
            LEFT JOIN tblteachers tch ON tch.id = l.teacher_id
            WHERE s.id = :student_id 
                AND e.id = :exam_id 
                AND s.school_id = :school_id
                AND t.academic_year = :academic_year
        ");
        
        $stmt->execute([
            ':student_id' => $student_id,
            ':exam_id' => $exam_id,
            ':term_id' => $term_id,
            ':academic_year' => $academic_year,
            ':school_id' => $this->school_id
        ]);
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($results)) {
            return null;
        }
        
        // Organize data
        $studentData = [
            'student_id' => $results[0]['student_id'],
            'student_adm' => $results[0]['admission_no'],
            'student_name' => $results[0]['student_name'],
            'gender' => $results[0]['Gender'],
            'profile_pic' => $results[0]['profile_pic'],
            'class_name' => $results[0]['class_name'],
            'stream_name' => $results[0]['stream_name'] ?? 'N/A',
            'term_name' => $results[0]['term_name'],
            'academic_year' => $results[0]['academic_year'],
            'exam_name' => $results[0]['exam_name'],
            'scores' => []
        ];
        
        // Build scores array
        foreach ($results as $row) {
            if ($row['subject_id']) {
                $gradeInfo = $this->calculateGrade($row['percentage'] ?? 0);
                $studentData['scores'][] = [
                    'subject_id' => $row['subject_id'],
                    'subject_name' => $row['subject_name'],
                    'alias' => $row['alias'],
                    'score' => $row['score_value'],
                    'total' => $row['total_score'],
                    'percentage' => round($row['percentage'] ?? 0, 2),
                    'grade' => $row['grade'] ?: $gradeInfo['grade'],
                    'points' => $gradeInfo['points'],
                    'teacher' => trim(($row['teacher_firstname'] ?? '') . ' ' . ($row['teacher_secondname'] ?? ''))
                ];
            }
        }
        
        // Calculate rank
        $studentData['class_position'] = $this->calculateStudentRank($student_id, $exam_id);
        $studentData['class_total'] = $this->getClassStudentCount($results[0]['class_name']);
        
        return $studentData;
    }
    
    /**
     * Fetch multiple students data in bulk
     */
    private function fetchMultipleStudentsData($student_ids, $exam_id, $term_id, $academic_year) {
        if (empty($student_ids)) {
            return [];
        }
        
        $placeholders = implode(',', array_fill(0, count($student_ids), '?'));
        
        $stmt = $this->db->prepare("
            SELECT 
                s.id as student_id,
                s.AdmNo as admission_no,
                CONCAT(s.FirstName, ' ', COALESCE(s.SecondName, ''), ' ', COALESCE(s.LastName, '')) as student_name,
                s.Gender,
                c.class_level as class_name,
                st.stream_name,
                t.term_name,
                t.academic_year,
                e.examname as exam_name,
                sub.id as subject_id,
                sub.subject_name,
                sub.alias,
                sc.score_value,
                sc.total_score,
                sc.percentage,
                sc.grade,
                tch.firstname as teacher_firstname,
                tch.secondname as teacher_secondname
            FROM tblstudents s
            INNER JOIN tblclasses c ON s.class_id = c.id
            LEFT JOIN tblstreams st ON s.StreamId = st.id
            INNER JOIN tblexam e ON e.class_id = c.id
            INNER JOIN tblterms t ON t.id = ?
            LEFT JOIN tblscores sc ON sc.student_id = s.id AND sc.exam_id = e.id AND sc.school_id = s.school_id
            LEFT JOIN tblsubjects sub ON sub.id = sc.subject_id
            LEFT JOIN tbllessons l ON l.subject_id = sub.id AND l.class_id = c.id
            LEFT JOIN tblteachers tch ON tch.id = l.teacher_id
            WHERE s.id IN ($placeholders)
                AND e.id = ?
                AND s.school_id = ?
                AND t.academic_year = ?
            ORDER BY s.id
        ");
        
        $params = array_merge([$term_id], $student_ids, [$exam_id, $this->school_id, $academic_year]);
        $stmt->execute($params);
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Group by student
        $studentsData = [];
        foreach ($results as $row) {
            if (!isset($studentsData[$row['student_id']])) {
                $studentsData[$row['student_id']] = [
                    'student_id' => $row['student_id'],
                    'student_adm' => $row['admission_no'],
                    'student_name' => $row['student_name'],
                    'gender' => $row['Gender'],
                    'class_name' => $row['class_name'],
                    'stream_name' => $row['stream_name'] ?? 'N/A',
                    'term_name' => $row['term_name'],
                    'academic_year' => $row['academic_year'],
                    'exam_name' => $row['exam_name'],
                    'scores' => []
                ];
            }
            
            if ($row['subject_id']) {
                $gradeInfo = $this->calculateGrade($row['percentage'] ?? 0);
                $studentsData[$row['student_id']]['scores'][] = [
                    'subject_id' => $row['subject_id'],
                    'subject_name' => $row['subject_name'],
                    'alias' => $row['alias'],
                    'score' => $row['score_value'],
                    'total' => $row['total_score'],
                    'percentage' => round($row['percentage'] ?? 0, 2),
                    'grade' => $row['grade'] ?: $gradeInfo['grade'],
                    'points' => $gradeInfo['points'],
                    'teacher' => trim(($row['teacher_firstname'] ?? '') . ' ' . ($row['teacher_secondname'] ?? ''))
                ];
            }
        }
        
        // Calculate ranks for all students
        $ranks = $this->calculateAllStudentRanks(array_keys($studentsData), $exam_id);
        foreach ($studentsData as $student_id => &$data) {
            $data['class_position'] = $ranks[$student_id] ?? null;
        }
        
        return array_values($studentsData);
    }
    
    /**
     * Calculate mean score from subject scores
     */
    private function calculateMeanScore($scores) {
        if (empty($scores)) {
            return 0;
        }
        
        $total = 0;
        $count = 0;
        foreach ($scores as $score) {
            if (isset($score['percentage']) && $score['percentage'] > 0) {
                $total += $score['percentage'];
                $count++;
            }
        }
        
        return $count > 0 ? $total / $count : 0;
    }
    
    /**
     * Calculate student's rank in class
     */
    private function calculateStudentRank($student_id, $exam_id) {
        $stmt = $this->db->prepare("
            SELECT 
                s.id,
                AVG(sc.percentage) as mean_score
            FROM tblstudents s
            INNER JOIN tblscores sc ON sc.student_id = s.id
            WHERE sc.exam_id = :exam_id 
                AND s.school_id = :school_id
            GROUP BY s.id
            ORDER BY mean_score DESC
        ");
        
        $stmt->execute([
            ':exam_id' => $exam_id,
            ':school_id' => $this->school_id
        ]);
        
        $rank = 1;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($row['id'] == $student_id) {
                return $rank;
            }
            $rank++;
        }
        
        return null;
    }
    
    /**
     * Calculate ranks for multiple students
     */
    private function calculateAllStudentRanks($student_ids, $exam_id) {
        if (empty($student_ids)) {
            return [];
        }
        
        $stmt = $this->db->prepare("
            SELECT 
                s.id,
                AVG(sc.percentage) as mean_score
            FROM tblstudents s
            INNER JOIN tblscores sc ON sc.student_id = s.id
            WHERE sc.exam_id = :exam_id 
                AND s.school_id = :school_id
                AND s.id IN (" . implode(',', array_fill(0, count($student_ids), '?')) . ")
            GROUP BY s.id
            ORDER BY mean_score DESC
        ");
        
        $params = array_merge([':exam_id' => $exam_id, ':school_id' => $this->school_id], $student_ids);
        $stmt->execute($params);
        
        $ranks = [];
        $position = 1;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $ranks[$row['id']] = $position++;
        }
        
        return $ranks;
    }
    
    /**
     * Calculate class statistics
     */
    private function calculateClassStatistics($studentsData) {
        $totalMean = 0;
        $bestStudent = null;
        $gradeDistribution = [];
        
        foreach ($studentsData as $student) {
            $meanScore = $this->calculateMeanScore($student['scores']);
            $gradeInfo = $this->calculateGrade($meanScore);
            
            $totalMean += $meanScore;
            
            if ($bestStudent === null || $meanScore > $bestStudent['mean_score']) {
                $bestStudent = [
                    'name' => $student['student_name'],
                    'mean_score' => $meanScore,
                    'grade' => $gradeInfo['grade']
                ];
            }
            
            $grade = $gradeInfo['grade'];
            if (!isset($gradeDistribution[$grade])) {
                $gradeDistribution[$grade] = 0;
            }
            $gradeDistribution[$grade]++;
        }
        
        $totalStudents = count($studentsData);
        $classMean = $totalStudents > 0 ? $totalMean / $totalStudents : 0;
        $classGradeInfo = $this->calculateGrade($classMean);
        
        return [
            'mean_score' => $classMean,
            'grade' => $classGradeInfo['grade'],
            'total_students' => $totalStudents,
            'best_student' => $bestStudent,
            'grade_distribution' => $gradeDistribution
        ];
    }
    
    /**
     * Calculate subject statistics
     */
    private function calculateSubjectStatistics($studentsData) {
        $subjectStats = [];
        
        foreach ($studentsData as $student) {
            foreach ($student['scores'] as $score) {
                $subjectId = $score['subject_id'];
                $subjectName = $score['subject_name'];
                $percentage = $score['percentage'];
                
                if (!isset($subjectStats[$subjectId])) {
                    $subjectStats[$subjectId] = [
                        'subject_name' => $subjectName,
                        'scores' => [],
                        'total' => 0,
                        'count' => 0,
                        'highest' => 0,
                        'lowest' => 100
                    ];
                }
                
                if ($percentage > 0) {
                    $subjectStats[$subjectId]['scores'][] = $percentage;
                    $subjectStats[$subjectId]['total'] += $percentage;
                    $subjectStats[$subjectId]['count']++;
                    $subjectStats[$subjectId]['highest'] = max($subjectStats[$subjectId]['highest'], $percentage);
                    $subjectStats[$subjectId]['lowest'] = min($subjectStats[$subjectId]['lowest'], $percentage);
                }
            }
        }
        
        $result = [];
        foreach ($subjectStats as $id => $stat) {
            $mean = $stat['count'] > 0 ? $stat['total'] / $stat['count'] : 0;
            $gradeInfo = $this->calculateGrade($mean);
            
            $result[] = [
                'subject_id' => $id,
                'subject_name' => $stat['subject_name'],
                'mean' => $mean,
                'highest' => $stat['highest'],
                'lowest' => $stat['lowest'],
                'grade' => $gradeInfo['grade']
            ];
        }
        
        return $result;
    }
    
    /**
     * Get subjects for a class
     */
    private function getSubjectsForClass($class_id, $stream_id) {
        $stmt = $this->db->prepare("
            SELECT id, subject_name, alias 
            FROM tblsubjects 
            WHERE class_id = :class_id 
                AND (stream_id = :stream_id OR stream_id IS NULL)
                AND school_id = :school_id
            ORDER BY subject_name
        ");
        
        $stmt->execute([
            ':class_id' => $class_id,
            ':stream_id' => $stream_id ?: 0,
            ':school_id' => $this->school_id
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get class student count
     */
    private function getClassStudentCount($class_name) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count 
            FROM tblstudents s
            INNER JOIN tblclasses c ON s.class_id = c.id
            WHERE c.class_level = :class_name AND s.school_id = :school_id
        ");
        
        $stmt->execute([
            ':class_name' => $class_name,
            ':school_id' => $this->school_id
        ]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] ?? 0;
    }
    
    /**
     * Get school information
     */
    private function getSchoolInfo() {
        $stmt = $this->db->prepare("
            SELECT * FROM tblschoolinfo WHERE id = :school_id
        ");
        $stmt->execute([':school_id' => $this->school_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Save student report to database
     */
    private function saveStudentReport($student_id, $exam_id, $term_id, $academic_year, $mean_score, $grade, $pdf_url) {
        $stmt = $this->db->prepare("
            INSERT INTO student_report_cards 
            (school_id, student_id, exam_id, term_id, academic_year, mean_score, grade, pdf_url, generated_at)
            VALUES (:school_id, :student_id, :exam_id, :term_id, :academic_year, :mean_score, :grade, :pdf_url, NOW())
            ON DUPLICATE KEY UPDATE
            mean_score = VALUES(mean_score),
            grade = VALUES(grade),
            pdf_url = VALUES(pdf_url),
            generated_at = NOW()
        ");
        
        return $stmt->execute([
            ':school_id' => $this->school_id,
            ':student_id' => $student_id,
            ':exam_id' => $exam_id,
            ':term_id' => $term_id,
            ':academic_year' => $academic_year,
            ':mean_score' => $mean_score,
            ':grade' => $grade,
            ':pdf_url' => $pdf_url
        ]);
    }
    
    /**
     * Save merged report to database
     */
    private function saveMergedReport($class_id, $stream_id, $exam_id, $term_id, $academic_year, $mean_score, $grade, $subjects, $pdf_url, $total_students, $total_pages) {
        $stmt = $this->db->prepare("
            INSERT INTO merged_reports 
            (school_id, class_id, stream_id, exam_id, term_id, academic_year, mean_score, grade, subjects, pdf_url, total_students, total_pages, status, created_by, created_at)
            VALUES (:school_id, :class_id, :stream_id, :exam_id, :term_id, :academic_year, :mean_score, :grade, :subjects, :pdf_url, :total_students, :total_pages, 'completed', :created_by, NOW())
        ");
        
        $subjectsJson = json_encode($subjects);
        
        $stmt->execute([
            ':school_id' => $this->school_id,
            ':class_id' => $class_id,
            ':stream_id' => $stream_id ?: null,
            ':exam_id' => $exam_id,
            ':term_id' => $term_id,
            ':academic_year' => $academic_year,
            ':mean_score' => $mean_score,
            ':grade' => $grade,
            ':subjects' => $subjectsJson,
            ':pdf_url' => $pdf_url,
            ':total_students' => $total_students,
            ':total_pages' => $total_pages,
            ':created_by' => $this->teacher_id
        ]);
        
        return $this->db->lastInsertId();
    }
}