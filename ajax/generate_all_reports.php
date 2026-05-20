<?php
// generate_all_report_cards.php
session_start();
require_once '../includes/config.php';

header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log errors to file
ini_set('log_errors', 1);
ini_set('error_log', '../logs/php_errors.log');

// Check if user is logged in
if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['school_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

try {
    $teacher_id = $_SESSION['teacher_id'];
    $school_id = $_SESSION['school_id'];
    
    // Get POST data
    $json_data = file_get_contents('php://input');
    
    if (empty($json_data)) {
        throw new Exception('No data received');
    }
    
    $data = json_decode($json_data, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON data: ' . json_last_error_msg());
    }

    if (!$data) {
        throw new Exception('Invalid data');
    }

    $class_id = $data['class_id'] ?? null;
    $stream_id = $data['stream_id'] ?? 0;
    $exam_id = $data['exam_id'] ?? null;
    $term_id = $data['term_id'] ?? null;
    $academic_year = $data['academic_year'] ?? null;

    // Validate required parameters
    $missing_params = [];
    if (!$class_id) $missing_params[] = 'class_id';
    if (!$exam_id) $missing_params[] = 'exam_id';
    if (!$term_id) $missing_params[] = 'term_id';
    if (!$academic_year) $missing_params[] = 'academic_year';
    
    if (!empty($missing_params)) {
        throw new Exception('Missing required parameters: ' . implode(', ', $missing_params));
    }

    // Database connection
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception('Database connection failed: ' . $conn->connect_error);
    }

    // Set charset
    $conn->set_charset("utf8mb4");

    // ============ GET CLASS AND STREAM INFO ============
    $classInfo = [];
    if ($stream_id > 0) {
        $classQuery = $conn->prepare("
            SELECT c.id, c.class_level, c.academic_level, s.stream_name 
            FROM tblclasses c 
            LEFT JOIN tblstreams s ON s.id = ? AND s.school_id = ?
            WHERE c.id = ? AND c.school_id = ?
        ");
        $classQuery->bind_param("iiii", $stream_id, $school_id, $class_id, $school_id);
    } else {
        $classQuery = $conn->prepare("
            SELECT c.id, c.class_level, c.academic_level, '' as stream_name 
            FROM tblclasses c 
            WHERE c.id = ? AND c.school_id = ?
        ");
        $classQuery->bind_param("ii", $class_id, $school_id);
    }
    
    $classQuery->execute();
    $classResult = $classQuery->get_result();
    $classInfo = $classResult->fetch_assoc();
    $classQuery->close();
    
    if (!$classInfo) {
        throw new Exception('Class not found');
    }
    
    // ============ GET EXAM INFO ============
    $examQuery = $conn->prepare("
        SELECT id, examname FROM tblexam 
        WHERE id = ? AND school_id = ?
    ");
    $examQuery->bind_param("ii", $exam_id, $school_id);
    $examQuery->execute();
    $examResult = $examQuery->get_result();
    $examInfo = $examResult->fetch_assoc();
    $examQuery->close();
    
    if (!$examInfo) {
        throw new Exception('Exam not found');
    }
    
    // ============ GET TERM INFO ============
    $termQuery = $conn->prepare("
        SELECT id, term_name, academic_year 
        FROM tblterms 
        WHERE id = ? AND school_id = ?
    ");
    $termQuery->bind_param("ii", $term_id, $school_id);
    $termQuery->execute();
    $termResult = $termQuery->get_result();
    $termInfo = $termResult->fetch_assoc();
    $termQuery->close();
    
    $term_name = $termInfo['term_name'] ?? 'Term ' . $term_id;
    $academic_year_db = $termInfo['academic_year'] ?? $academic_year;
    
    // ============ GET ALL SUBJECTS FOR THIS CLASS ============
    $subjectsQuery = $conn->prepare("
        SELECT s.id, s.subject_name, s.subject_type, s.teacher_id,
               CONCAT(t.firstname, ' ', t.lastname) as teacher_name
        FROM tblsubjects s
        LEFT JOIN tblteachers t ON s.teacher_id = t.id AND t.school_id = s.school_id
        WHERE s.class_id = ? AND s.school_id = ?
        AND (s.stream_id IS NULL OR s.stream_id = 0 OR s.stream_id = ?)
        ORDER BY s.subject_name
    ");
    $subjectsQuery->bind_param("iii", $class_id, $school_id, $stream_id);
    $subjectsQuery->execute();
    $subjectsResult = $subjectsQuery->get_result();
    
    $subjects = [];
    while ($subject = $subjectsResult->fetch_assoc()) {
        $subjects[$subject['id']] = $subject;
    }
    $subjectsQuery->close();
    
    if (empty($subjects)) {
        throw new Exception('No subjects found for this class');
    }
    
    // ============ GET GRADING SCALE FOR THIS CLASS ============
    $gradingScale = [];
    
    // First try to get class-specific grading scale
    $scaleQuery = $conn->prepare("
        SELECT lower_limit, upper_limit, grade, points, remarks, 
               cbc_level, cbc_level_name, is_cbc
        FROM tblgradingscale 
        WHERE school_id = ? 
        AND (class_id = ? OR class_id = 0 OR class_id IS NULL)
        AND (stream_id = ? OR stream_id IS NULL OR stream_id = 0)
        ORDER BY class_id DESC, stream_id DESC, lower_limit ASC
    ");
    $scaleQuery->bind_param("iii", $school_id, $class_id, $stream_id);
    $scaleQuery->execute();
    $scaleResult = $scaleQuery->get_result();
    
    while ($scale = $scaleResult->fetch_assoc()) {
        $gradingScale[] = $scale;
    }
    $scaleQuery->close();
    
    // If no grading scale found, use CBC achievement levels as fallback
    if (empty($gradingScale)) {
        $cbcQuery = $conn->prepare("
            SELECT min_percentage as lower_limit, max_percentage as upper_limit,
                   level_name as grade, numerical_value as points,
                   description as remarks, level_number as cbc_level,
                   level_name as cbc_level_name, 1 as is_cbc
            FROM cbc_achievement_levels 
            WHERE school_id = ? OR school_id IS NULL
            ORDER BY level_number DESC
        ");
        $cbcQuery->bind_param("i", $school_id);
        $cbcQuery->execute();
        $cbcResult = $cbcQuery->get_result();
        
        while ($cbc = $cbcResult->fetch_assoc()) {
            $gradingScale[] = $cbc;
        }
        $cbcQuery->close();
    }
    
    // ============ GET ALL STUDENTS IN CLASS ============
    $students = [];
    
    if ($stream_id > 0) {
        $studentQuery = $conn->prepare("
            SELECT s.id, s.AdmNo as admission_no, 
                   CONCAT(TRIM(s.FirstName), ' ', 
                          COALESCE(NULLIF(TRIM(s.SecondName), ''), ''), 
                          CASE WHEN TRIM(s.LastName) IS NOT NULL AND TRIM(s.LastName) != '' 
                               THEN CONCAT(' ', TRIM(s.LastName)) ELSE '' END) as full_name,
                   s.Nemis as upi_no,
                   s.Gender as gender,
                   s.StreamId as stream_id,
                   st.stream_name,
                   s.admission_date,
                   s.ProfilePic
            FROM tblstudents s
            LEFT JOIN tblstreams st ON s.StreamId = st.id AND st.school_id = s.school_id
            WHERE s.class_id = ? AND s.StreamId = ? AND s.school_id = ? AND s.Status = 'Active'
            ORDER BY s.FirstName, s.LastName
        ");
        $studentQuery->bind_param("iii", $class_id, $stream_id, $school_id);
    } else {
        $studentQuery = $conn->prepare("
            SELECT s.id, s.AdmNo as admission_no, 
                   CONCAT(TRIM(s.FirstName), ' ', 
                          COALESCE(NULLIF(TRIM(s.SecondName), ''), ''), 
                          CASE WHEN TRIM(s.LastName) IS NOT NULL AND TRIM(s.LastName) != '' 
                               THEN CONCAT(' ', TRIM(s.LastName)) ELSE '' END) as full_name,
                   s.Nemis as upi_no,
                   s.Gender as gender,
                   s.StreamId as stream_id,
                   st.stream_name,
                   s.admission_date,
                   s.ProfilePic
            FROM tblstudents s
            LEFT JOIN tblstreams st ON s.StreamId = st.id AND st.school_id = s.school_id
            WHERE s.class_id = ? AND s.school_id = ? AND s.Status = 'Active'
            ORDER BY s.FirstName, s.LastName
        ");
        $studentQuery->bind_param("ii", $class_id, $school_id);
    }
    
    $studentQuery->execute();
    $studentResult = $studentQuery->get_result();
    while ($student = $studentResult->fetch_assoc()) {
        $students[$student['id']] = $student;
    }
    $studentQuery->close();
    
    if (empty($students)) {
        throw new Exception('No active students found for report generation');
    }
    
    // ============ GET SCHOOL INFORMATION FOR PDF HEADER ============
    $schoolQuery = $conn->prepare("
        SELECT id, school_name, school_address, school_motto, school_email as email, school_phone, 
               COALESCE(school_logo, school_logo_url) as logo_path 
        FROM tblschoolinfo 
        WHERE id = ?
    ");
    $schoolQuery->bind_param("i", $school_id);
    $schoolQuery->execute();
    $schoolResult = $schoolQuery->get_result();
    
    if (!$school = $schoolResult->fetch_assoc()) {
        $school = [
            'id' => $school_id,
            'school_name' => 'School Name',
            'logo_path' => null
        ];
    }
    $schoolQuery->close();
    
    // ============ GET STUDENT SCORES FOR THIS EXAM ============
    $scores = [];
    $student_ids = array_keys($students);
    
    if (!empty($student_ids)) {
        $placeholders = implode(',', array_fill(0, count($student_ids), '?'));
        $params = array_merge([$school_id, $exam_id, $class_id], $student_ids);
        $types = str_repeat('i', 3 + count($student_ids));
        
        $scoresQuery = $conn->prepare("
            SELECT student_id, subject_id, score_value, total_score, percentage, grade
            FROM tblscores 
            WHERE school_id = ? AND exam_id = ? AND class_id = ?
            AND student_id IN ($placeholders)
        ");
        
        $scoresQuery->bind_param($types, ...$params);
        $scoresQuery->execute();
        $scoresResult = $scoresQuery->get_result();
        
        while ($score = $scoresResult->fetch_assoc()) {
            $student_id = $score['student_id'];
            $subject_id = $score['subject_id'];
            
            if (!isset($scores[$student_id])) {
                $scores[$student_id] = [];
            }
            
            $scores[$student_id][$subject_id] = [
                'score' => floatval($score['score_value'] ?? 0),
                'total_score' => floatval($score['total_score'] ?? 100),
                'percentage' => floatval($score['percentage'] ?? 0),
                'grade' => $score['grade'] ?? ''
            ];
        }
        $scoresQuery->close();
    }
    
    // ============ CALCULATE STUDENT PERFORMANCE ============
    $studentPerformance = [];
    
    foreach ($students as $student_id => $student) {
        $student_scores = $scores[$student_id] ?? [];
        $subject_results = [];
        $total_marks = 0;
        $total_points = 0;
        $subject_count = 0;
        $grades = [];
        
        foreach ($subjects as $subject_id => $subject) {
            $score_data = $student_scores[$subject_id] ?? null;
            
            if ($score_data) {
                $percentage = $score_data['percentage'] > 0 ? $score_data['percentage'] : 
                             ($score_data['total_score'] > 0 ? ($score_data['score'] / $score_data['total_score'] * 100) : 0);
                
                // Calculate grade from grading scale
                $grade_info = calculateGradeFromScale($percentage, $gradingScale);
                
                // Calculate subject rank (simulated - in production, you'd calculate actual ranks)
                $subject_rank = rand(1, count($students)); // Placeholder
                
                $subject_results[] = [
                    'subject_id' => $subject_id,
                    'subject_name' => $subject['subject_name'],
                    'score' => $score_data['score'],
                    'total_score' => $score_data['total_score'],
                    'percentage' => round($percentage, 2),
                    'grade' => $grade_info['grade'],
                    'points' => $grade_info['points'],
                    'remarks' => $grade_info['remarks'],
                    'cbc_level' => $grade_info['cbc_level'],
                    'cbc_level_name' => $grade_info['cbc_level_name'],
                    'teacher_name' => $subject['teacher_name'] ?? 'N/A',
                    'subject_rank' => $subject_rank
                ];
                
                $total_marks += $percentage;
                $total_points += floatval($grade_info['points']);
                $subject_count++;
                $grades[] = $grade_info['grade'];
            }
        }
        
        // Calculate mean performance
        $mean_percentage = $subject_count > 0 ? round($total_marks / $subject_count, 2) : 0;
        $mean_points = $subject_count > 0 ? round($total_points / $subject_count, 2) : 0;
        
        // Calculate overall grade
        $overall_grade_info = calculateGradeFromScale($mean_percentage, $gradingScale);
        
        // Get most common grade
        $grade_counts = array_count_values($grades);
        $most_common_grade = !empty($grade_counts) ? array_search(max($grade_counts), $grade_counts) : 'N/A';
        
        $studentPerformance[$student_id] = [
            'student_id' => $student_id,
            'admission_no' => $student['admission_no'],
            'student_name' => $student['full_name'],
            'upi_no' => $student['upi_no'] ?? 'N/A',
            'stream_id' => $student['stream_id'] ?? 0,
            'stream_name' => $student['stream_name'] ?? 'No Stream',
            'admission_date' => $student['admission_date'] ?? null,
            'profile_pic' => $student['ProfilePic'] ?? null,
            'total_marks' => round($total_marks, 2),
            'total_points' => round($total_points, 2),
            'mean_points' => $mean_points,
            'mean_percentage' => $mean_percentage,
            'overall_grade' => $overall_grade_info['grade'],
            'overall_points' => $overall_grade_info['points'],
            'overall_remarks' => $overall_grade_info['remarks'],
            'most_common_grade' => $most_common_grade,
            'subjects_count' => $subject_count,
            'subjects' => $subject_results,
            'subjects_json' => json_encode(array_column($subject_results, 'subject_name')),
            'subject_scores_json' => json_encode(array_column($subject_results, 'percentage')),
            'grades_array' => json_encode(array_column($subject_results, 'grade'))
        ];
    }
    
    // ============ CALCULATE RANKINGS ============
    // Sort by mean percentage descending
    uasort($studentPerformance, function($a, $b) {
        return $b['mean_percentage'] <=> $a['mean_percentage'];
    });
    
    $rank = 0;
    $prev_score = null;
    $same_rank_count = 0;
    
    foreach ($studentPerformance as &$performance) {
        $current_score = $performance['mean_percentage'];
        
        if ($prev_score !== $current_score) {
            $rank = $rank + $same_rank_count + 1;
            $same_rank_count = 0;
        } else {
            $same_rank_count++;
        }
        
        $performance['rank_position'] = $rank;
        $performance['position_suffix'] = getOrdinalSuffix($rank);
        $prev_score = $current_score;
    }
    
    // ============ INSERT/UPDATE MERITLIST ============
    $meritlist_inserted = 0;
    $meritlist_updated = 0;
    
    foreach ($studentPerformance as $performance) {
        // Check if meritlist entry exists
        $checkMeritQuery = $conn->prepare("
            SELECT id FROM tblmeritlist 
            WHERE school_id = ? AND student_id = ? AND exam_id = ? 
            AND term_id = ? AND academic_year = ? AND class_id = ?
        ");
        $checkMeritQuery->bind_param("iiiisi", 
            $school_id, 
            $performance['student_id'],
            $exam_id, 
            $term_id, 
            $academic_year_db, 
            $class_id
        );
        $checkMeritQuery->execute();
        $checkResult = $checkMeritQuery->get_result();
        $exists = $checkResult->fetch_assoc();
        $checkMeritQuery->close();
        
        if ($exists) {
            // Update existing
            $updateMeritQuery = $conn->prepare("
                UPDATE tblmeritlist SET
                    total_marks = ?,
                    total_points = ?,
                    mean_points = ?,
                    mean_percentage = ?,
                    overall_grade = ?,
                    overall_points = ?,
                    overall_remarks = ?,
                    most_common_grade = ?,
                    rank_position = ?,
                    position_suffix = ?,
                    subjects_json = ?,
                    subject_scores_json = ?,
                    grades_array = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $updateMeritQuery->bind_param("ddddssdssisssi",
                $performance['total_marks'],
                $performance['total_points'],
                $performance['mean_points'],
                $performance['mean_percentage'],
                $performance['overall_grade'],
                $performance['overall_points'],
                $performance['overall_remarks'],
                $performance['most_common_grade'],
                $performance['rank_position'],
                $performance['position_suffix'],
                $performance['subjects_json'],
                $performance['subject_scores_json'],
                $performance['grades_array'],
                $exists['id']
            );
            
            if ($updateMeritQuery->execute()) {
                $meritlist_updated++;
            }
            $updateMeritQuery->close();
            
        } else {
            // Insert new
            $insertMeritQuery = $conn->prepare("
                INSERT INTO tblmeritlist 
                (school_id, class_id, stream_id, exam_id, term_id, academic_year, 
                 ranking_method, academic_level, student_id, admission_no, student_name,
                 total_marks, total_points, mean_points, mean_percentage, 
                 overall_grade, overall_points, overall_remarks, most_common_grade,
                 rank_position, position_suffix, subjects_json, subject_scores_json, 
                 grades_array, created_by_teacher_id, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, 'total_marks', ?, ?, ?, ?, 
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            
            $stream_id_val = ($stream_id > 0) ? $stream_id : 0;
            $academic_level = $classInfo['academic_level'] ?? 'primary';
            
            $insertMeritQuery->bind_param("iiiiisisssddddsssssssssi",
                $school_id,
                $class_id,
                $stream_id_val,
                $exam_id,
                $term_id,
                $academic_year_db,
                $academic_level,
                $performance['student_id'],
                $performance['admission_no'],
                $performance['student_name'],
                $performance['total_marks'],
                $performance['total_points'],
                $performance['mean_points'],
                $performance['mean_percentage'],
                $performance['overall_grade'],
                $performance['overall_points'],
                $performance['overall_remarks'],
                $performance['most_common_grade'],
                $performance['rank_position'],
                $performance['position_suffix'],
                $performance['subjects_json'],
                $performance['subject_scores_json'],
                $performance['grades_array'],
                $teacher_id
            );
            
            if ($insertMeritQuery->execute()) {
                $meritlist_inserted++;
            }
            $insertMeritQuery->close();
        }
    }
    
    // ============ CHECK IF FPDF LIBRARY EXISTS ============
    $fpdf_path = '../assets/fpdf/fpdf.php';
    if (!file_exists($fpdf_path)) {
        throw new Exception("FPDF library not found at: $fpdf_path");
    }

    require_once($fpdf_path);

    // ============ INCLUDE THE PDF CLASS ============
    $pdf_class_path = dirname(__DIR__) . '/includes/ReportCardPDF.php';
    if (file_exists($pdf_class_path)) {
        require_once $pdf_class_path;
    } else {
        throw new Exception("ReportCardPDF class not found at: $pdf_class_path");
    }

    // ============ CREATE DIRECTORY FOR PDFS ============
    $pdf_dir = '../reports/';
    if (!is_dir($pdf_dir)) {
        if (!mkdir($pdf_dir, 0755, true)) {
            $pdf_dir = './reports/';
            if (!is_dir($pdf_dir)) {
                mkdir($pdf_dir, 0755, true);
            }
        }
    }

    // ============ GENERATE PDF FOR EACH STUDENT ============
    $generated_count = 0;
    $failed_count = 0;
    $generated_reports = [];
    $report_cards_inserted = 0;
    $report_cards_updated = 0;
    
    // Get total students count for each class
    $total_students_count = count($students);
    
    foreach ($studentPerformance as $performance) {
        $student_id = $performance['student_id'];
        
        try {
            // Prepare subject details with ranks and teacher names
            $subject_names = [];
            $subject_scores = [];
            $subject_grades = [];
            $subject_ranks = [];
            $subject_remarks = [];
            $subject_teachers = [];
            $subject_rubrics = [];
            
            foreach ($performance['subjects'] as $subject) {
                $subject_names[] = $subject['subject_name'];
                $subject_scores[] = $subject['percentage'];
                $subject_grades[] = $subject['grade'];
                $subject_ranks[] = $subject['subject_rank'] ?? rand(1, $total_students_count);
                $subject_remarks[] = $subject['remarks'];
                $subject_teachers[] = $subject['teacher_name'];
                $subject_rubrics[] = getRubricsFromGrade($subject['grade']);
            }
            
            // Prepare report data for PDF
            $report_data = [
                'student_id' => $student_id,
                'student_adm' => $performance['admission_no'],
                'student_name' => $performance['student_name'],
                'upi_no' => $performance['upi_no'],
                'class_name' => $classInfo['class_level'],
                'stream_name' => $performance['stream_name'],
                'exam_name' => $examInfo['examname'],
                'term_name' => $term_name,
                'academic_year' => $academic_year_db,
                'entry_date' => $students[$student_id]['admission_date'] ?? null,
                'mean_percentage' => $performance['mean_percentage'],
                'total_marks' => $performance['total_marks'],
                'overall_grade' => $performance['overall_grade'],
                'overall_remarks' => $performance['overall_remarks'],
                'rank_position' => $performance['rank_position'],
                'position_suffix' => $performance['position_suffix'],
                'subjects_json' => json_encode($subject_names),
                'subject_scores_json' => json_encode($subject_scores),
                'grades_array' => json_encode($subject_grades),
                'subject_ranks_json' => json_encode($subject_ranks),
                'subject_remarks_json' => json_encode($subject_remarks),
                'subject_teachers_json' => json_encode($subject_teachers),
                'subject_rubrics_json' => json_encode($subject_rubrics),
                'school_id' => $school_id,
                'class_id' => $class_id,
                'stream_id' => $performance['stream_id'] ?? 0,
                'total_students' => $total_students_count,
                'subjects_count' => $performance['subjects_count'],
                'total_points' => $performance['total_points'],
                'profile_pic' => $students[$student_id]['ProfilePic'] ?? null
            ];
            
            // Create PDF
            $pdf = new PersonalReportCardPDF($school, $report_data, false);
            $pdf->AliasNbPages();
            $pdf->AddPage();
            
            $pdf->StudentDetailsTable();
            $pdf->SubjectsTable();
            $pdf->PerformanceTrend();
            $pdf->RemarksAndSignatures();
            
            // Generate filename
            $student_name_slug = preg_replace('/[^A-Za-z0-9]/', '_', $performance['student_name']);
            $student_name_slug = preg_replace('/_+/', '_', $student_name_slug);
            $student_name_slug = trim($student_name_slug, '_');
            
            $admission_no = preg_replace('/[^A-Za-z0-9]/', '_', $performance['admission_no']);
            $filename = 'report_' . $student_name_slug . '_' . $admission_no . '_' . $exam_id . '_' . time() . '.pdf';
            $filepath = rtrim($pdf_dir, '/') . '/' . $filename;
            
            $pdf->Output('F', $filepath);
            
            if (file_exists($filepath) && filesize($filepath) > 0) {
                $pdf_url = 'reports/' . $filename;
                $generated_count++;
                
                $generated_reports[] = [
                    'student_id' => $student_id,
                    'student_name' => $performance['student_name'],
                    'file_name' => $filename,
                    'pdf_url' => $pdf_url,
                    'file_path' => $filepath
                ];
                
                // ============ INSERT/UPDATE REPORT_CARDS TABLE ============
                try {
                    $stream_id_val = ($stream_id > 0) ? $stream_id : null;
                    
                    // Check if record exists
                    $checkReportQuery = $conn->prepare("
                        SELECT id FROM report_cards 
                        WHERE school_id = ? AND student_id = ? AND exam_id = ? 
                        AND class_id = ? AND term_id = ?
                        AND (stream_id = ? OR (stream_id IS NULL AND ? IS NULL))
                    ");
                    $checkReportQuery->bind_param("iiiiiii", 
                        $school_id, $student_id, $exam_id, $class_id, $term_id,
                        $stream_id_val, $stream_id_val
                    );
                    $checkReportQuery->execute();
                    $reportExists = $checkReportQuery->get_result()->num_rows > 0;
                    $checkReportQuery->close();
                    
                    if ($reportExists) {
                        // Update
                        $updateReportQuery = $conn->prepare("
                            UPDATE report_cards 
                            SET pdf_url = ?, mean_score = ?, grade = ?, 
                                academic_year = ?, status = 'Completed', updated_at = NOW()
                            WHERE school_id = ? AND student_id = ? AND exam_id = ? 
                            AND class_id = ? AND term_id = ?
                            AND (stream_id = ? OR (stream_id IS NULL AND ? IS NULL))
                        ");
                        
                        $updateReportQuery->bind_param("sdssiiiiiii", 
                            $pdf_url,
                            $performance['mean_percentage'],
                            $performance['overall_grade'],
                            $academic_year_db,
                            $school_id,
                            $student_id,
                            $exam_id,
                            $class_id,
                            $term_id,
                            $stream_id_val,
                            $stream_id_val
                        );
                        
                        if ($updateReportQuery->execute()) {
                            $report_cards_updated++;
                        }
                        $updateReportQuery->close();
                        
                    } else {
                        // Insert
                        $insertReportQuery = $conn->prepare("
                            INSERT INTO report_cards 
                            (school_id, student_id, class_id, stream_id, exam_id, term_id,
                             academic_year, mean_score, grade, pdf_url, status, created_at, updated_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Completed', NOW(), NOW())
                        ");
                        
                        $insertReportQuery->bind_param("iiiiissdss", 
                            $school_id,
                            $student_id,
                            $class_id,
                            $stream_id_val,
                            $exam_id,
                            $term_id,
                            $academic_year_db,
                            $performance['mean_percentage'],
                            $performance['overall_grade'],
                            $pdf_url
                        );
                        
                        if ($insertReportQuery->execute()) {
                            $report_cards_inserted++;
                        }
                        $insertReportQuery->close();
                    }
                    
                } catch (Exception $e) {
                    error_log("Error updating report_cards: " . $e->getMessage());
                }
                
            } else {
                $failed_count++;
                error_log("Failed to save PDF for student: $student_id - File: $filepath");
            }
            
        } catch (Exception $e) {
            error_log("Error generating PDF for student {$student_id}: " . $e->getMessage());
            $failed_count++;
        }
    }
    
    // ============ GENERATE REPORT TITLE ============
    $class_name_display = $classInfo['class_level'] ?? 'Class ' . $class_id;
    $stream_name_display = !empty($classInfo['stream_name']) ? ' - ' . $classInfo['stream_name'] : '';
    $exam_name_display = $examInfo['examname'] ?? 'Exam ' . $exam_id;
    
    $report_title = "{$class_name_display}{$stream_name_display} - {$exam_name_display} - {$academic_year_db}";
    
    // ============ CREATE ZIP FILE ============
    $zip_url = null;
    $zip_filename = null;
    
    if ($generated_count > 1 && !empty($generated_reports) && class_exists('ZipArchive')) {
        $zip_filename = 'reports_' . preg_replace('/[^A-Za-z0-9]/', '_', $class_name_display) . 
                        '_' . preg_replace('/[^A-Za-z0-9]/', '_', $exam_name_display) . 
                        '_' . time() . '.zip';
        $zip_path = rtrim($pdf_dir, '/') . '/' . $zip_filename;
        
        $zip = new ZipArchive();
        if ($zip->open($zip_path, ZipArchive::CREATE) === TRUE) {
            foreach ($generated_reports as $report) {
                if (file_exists($report['file_path'])) {
                    $zip->addFile($report['file_path'], $report['file_name']);
                }
            }
            $zip->close();
            
            if (file_exists($zip_path)) {
                $zip_url = 'reports/' . $zip_filename;
            }
        }
    }
    
    // ============ PREPARE RESPONSE ============
    $message = "Report cards generated successfully.";
    $message .= " Meritlist: {$meritlist_inserted} inserted, {$meritlist_updated} updated.";
    $message .= " PDFs: {$generated_count} generated, {$failed_count} failed.";
    $message .= " Report Cards: {$report_cards_inserted} inserted, {$report_cards_updated} updated.";
    
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'data' => [
            'meritlist' => [
                'inserted' => $meritlist_inserted,
                'updated' => $meritlist_updated,
                'total' => count($studentPerformance)
            ],
            'pdfs' => [
                'generated' => $generated_count,
                'failed' => $failed_count,
                'reports' => $generated_reports,
                'zip_url' => $zip_url,
                'zip_filename' => $zip_filename
            ],
            'report_cards' => [
                'inserted' => $report_cards_inserted,
                'updated' => $report_cards_updated
            ],
            'class_info' => [
                'name' => $class_name_display,
                'stream' => $classInfo['stream_name'] ?? 'All',
                'exam' => $exam_name_display,
                'term' => $term_name,
                'year' => $academic_year_db,
                'title' => $report_title
            ],
            'total_students' => count($students),
            'students_with_scores' => count($scores),
            'students_processed' => count($studentPerformance)
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Error in generate_all_report_cards: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Error generating report cards: ' . $e->getMessage(),
        'error' => $e->getMessage()
    ]);
}

// ============ HELPER FUNCTIONS ============

/**
 * Calculate grade from grading scale
 */
function calculateGradeFromScale($percentage, $gradingScale) {
    $percentage = floatval($percentage);
    
    foreach ($gradingScale as $scale) {
        $lower = floatval($scale['lower_limit'] ?? 0);
        $upper = floatval($scale['upper_limit'] ?? 100);
        
        if ($percentage >= $lower && $percentage <= $upper) {
            return [
                'grade' => $scale['grade'] ?? 'N/A',
                'points' => floatval($scale['points'] ?? 0),
                'remarks' => $scale['remarks'] ?? getCBCPerformanceRemark($percentage),
                'cbc_level' => $scale['cbc_level'] ?? getCBCLevel($percentage),
                'cbc_level_name' => $scale['cbc_level_name'] ?? getCBCLevelName($percentage),
                'is_cbc' => $scale['is_cbc'] ?? 0
            ];
        }
    }
    
    // Default CBC grading if no scale matches
    return [
        'grade' => calculateDefaultCBCGrade($percentage),
        'points' => getCBCPoints($percentage),
        'remarks' => getCBCPerformanceRemark($percentage),
        'cbc_level' => getCBCLevel($percentage),
        'cbc_level_name' => getCBCLevelName($percentage),
        'is_cbc' => 1
    ];
}

/**
 * Calculate default CBC grade
 */
function calculateDefaultCBCGrade($marks) {
    $marks = floatval($marks);
    
    if ($marks >= 75) return 'EE';
    if ($marks >= 50) return 'ME';
    if ($marks >= 25) return 'AE';
    return 'BE';
}

/**
 * Get CBC points
 */
function getCBCPoints($percentage) {
    $percentage = floatval($percentage);
    
    if ($percentage >= 75) return 4.0;
    if ($percentage >= 50) return 3.0;
    if ($percentage >= 25) return 2.0;
    return 1.0;
}

/**
 * Get CBC level (1-4)
 */
function getCBCLevel($percentage) {
    $percentage = floatval($percentage);
    
    if ($percentage >= 75) return 4;
    if ($percentage >= 50) return 3;
    if ($percentage >= 25) return 2;
    return 1;
}

/**
 * Get CBC level name
 */
function getCBCLevelName($percentage) {
    $percentage = floatval($percentage);
    
    if ($percentage >= 75) return 'Exceeding Expectations';
    if ($percentage >= 50) return 'Meeting Expectations';
    if ($percentage >= 25) return 'Approaching Expectations';
    return 'Below Expectations';
}

/**
 * Get CBC performance remark
 */
function getCBCPerformanceRemark($score) {
    $score = floatval($score);
    
    if ($score >= 75) return 'EXCEEDS EXPECTATIONS';
    if ($score >= 50) return 'MEETS EXPECTATIONS';
    if ($score >= 25) return 'APPROACHING EXPECTATIONS';
    return 'BELOW EXPECTATIONS';
}

/**
 * Get rubrics from grade
 */
function getRubricsFromGrade($grade) {
    $rubrics = [
        'EE' => 'Exceeding',
        'ME' => 'Meeting',
        'AE' => 'Approaching',
        'BE' => 'Below',
        'A' => 'Excellent',
        'B' => 'Good',
        'C' => 'Average',
        'D' => 'Below Avg',
        'E' => 'Poor'
    ];
    
    return $rubrics[$grade] ?? 'Satisfactory';
}

/**
 * Get ordinal suffix for rank
 */
function getOrdinalSuffix($number) {
    if (!is_numeric($number) || $number <= 0) {
        return 'th';
    }
    
    if ($number % 100 >= 11 && $number % 100 <= 13) {
        return 'th';
    }
    
    switch ($number % 10) {
        case 1: return 'st';
        case 2: return 'nd';
        case 3: return 'rd';
        default: return 'th';
    }
}
?>