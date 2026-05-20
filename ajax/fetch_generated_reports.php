<?php
session_start();
require_once '../config/config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['school_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to continue']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Debug: Log the input
error_log("Received input in fetch_generated_reports.php: " . print_r($input, true));

$class_id = $input['class_id'] ?? null;
$stream_id = isset($input['stream_id']) ? (int)$input['stream_id'] : 0;
$exam_id = $input['exam_id'] ?? null;
$term_id = $input['term_id'] ?? null;
$school_id = $_SESSION['school_id'];

if (!$class_id || !$exam_id) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required parameters: class_id and exam_id are required'
    ]);
    exit;
}

try {
    // ============ GET CLASS, EXAM, STREAM, TERM INFORMATION ============
    $class_info = getClassInfo($db, $class_id, $school_id);
    $exam_info = getExamInfo($db, $exam_id, $school_id);
    $stream_info = ($stream_id > 0) ? getStreamInfo($db, $stream_id, $school_id) : null;
    $term_info = ($term_id && $term_id > 0) ? getTermInfo($db, $term_id, $school_id) : null;
    
    $class_name = $class_info['class_level'] ?? 'Unknown Class';
    $exam_name = $exam_info['examname'] ?? 'Unknown Exam';
    $stream_name = $stream_info['stream_name'] ?? ($stream_id > 0 ? 'Unknown Stream' : 'All Streams');
    $term_name = $term_info['term_name'] ?? 'N/A';
    $academic_year = $term_info['academic_year'] ?? $input['academic_year'] ?? date('Y');
    
    // ============ FIXED: FETCH ALL REPORTS DIRECTLY FROM tblmeritlist ============
    $query = "SELECT 
                m.*,
                c.class_level as class_name,
                s.stream_name,
                e.examname as exam_name,
                t.term_name,
                t.academic_year as term_academic_year,
                stu.AdmNo as admission_no,
                stu.FirstName,
                stu.SecondName,
                stu.LastName,
                stu.StreamId as student_stream_id,
                CONCAT(TRIM(stu.FirstName), ' ', 
                       COALESCE(NULLIF(TRIM(stu.SecondName), ''), ''), 
                       CASE 
                           WHEN TRIM(stu.LastName) IS NOT NULL AND TRIM(stu.LastName) != '' 
                           THEN CONCAT(' ', TRIM(stu.LastName)) 
                           ELSE '' 
                       END) as student_full_name,
                rc.pdf_url,
                rc.id as report_card_id,
                rc.status as report_status,
                rc.created_at as report_created_at
                
              FROM tblmeritlist m
              
              INNER JOIN tblclasses c ON m.class_id = c.id AND c.school_id = m.school_id
              INNER JOIN tblexam e ON m.exam_id = e.id AND e.school_id = m.school_id
              INNER JOIN tblstudents stu ON m.student_id = stu.id AND stu.school_id = m.school_id
              LEFT JOIN tblstreams s ON m.stream_id = s.id AND s.school_id = m.school_id
              LEFT JOIN tblterms t ON m.term_id = t.id AND t.school_id = m.school_id
              LEFT JOIN report_cards rc ON m.student_id = rc.student_id 
                                         AND m.exam_id = rc.exam_id 
                                         AND m.class_id = rc.class_id
                                         AND (m.stream_id = rc.stream_id OR (m.stream_id = 0 AND rc.stream_id IS NULL))
                                         AND (m.term_id = rc.term_id OR rc.term_id IS NULL)
                                         AND m.school_id = rc.school_id
              
              WHERE m.school_id = :school_id 
              AND m.class_id = :class_id 
              AND m.exam_id = :exam_id";
    
    $params = [
        ':school_id' => $school_id,
        ':class_id' => $class_id,
        ':exam_id' => $exam_id
    ];
    
    // Add term filter if provided and valid
    if ($term_id && $term_id > 0) {
        $query .= " AND m.term_id = :term_id";
        $params[':term_id'] = $term_id;
    }
    
    // ============ FIXED: Stream filtering logic ============
    if ($stream_id > 0) {
        // For specific stream: match either stream_id = 0 (no stream) OR stream_id = selected stream
        $query .= " AND (m.stream_id = 0 OR m.stream_id = :stream_id)";
        $params[':stream_id'] = $stream_id;
    } else {
        // For "All Streams": only match stream_id = 0 (no specific stream)
        $query .= " AND m.stream_id = 0";
    }
    
    // Order by performance
    $query .= " ORDER BY m.mean_percentage DESC, m.total_marks DESC";
    
    error_log("Executing query: " . $query);
    error_log("Params: " . print_r($params, true));
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Found " . count($reports) . " reports from database");
    
    // If no reports found, try to scan filesystem for PDFs
    if (empty($reports)) {
        $reports = scanFilesystemForReports($db, $class_id, $stream_id, $exam_id, $term_id, $school_id, $input);
    }
    
    // Process reports if any found
    $processed_reports = [];
    $streams_data = [];
    $grade_distribution = [];
    $has_pdfs = false;
    $total_mean_score = 0;
    $total_total_marks = 0;
    $total_total_points = 0;
    $total_mean_points = 0;
    
    if (!empty($reports)) {
        foreach ($reports as $report) {
            // ============ FIXED: Properly decode JSON fields with error handling ============
            $subjects = [];
            $scores = [];
            $grades = [];
            
            // Decode subjects_json
            if (!empty($report['subjects_json'])) {
                $decoded_subjects = json_decode($report['subjects_json'], true);
                if (is_array($decoded_subjects)) {
                    $subjects = $decoded_subjects;
                }
            }
            
            // Decode subject_scores_json
            if (!empty($report['subject_scores_json'])) {
                $decoded_scores = json_decode($report['subject_scores_json'], true);
                if (is_array($decoded_scores)) {
                    $scores = $decoded_scores;
                }
            }
            
            // Decode grades_array
            if (!empty($report['grades_array'])) {
                $decoded_grades = json_decode($report['grades_array'], true);
                if (is_array($decoded_grades)) {
                    $grades = $decoded_grades;
                }
            }
            
            // Build subject scores array
            $subject_scores = [];
            $subject_count = max(count($subjects), count($scores), count($grades));
            
            for ($i = 0; $i < $subject_count; $i++) {
                $subject_scores[] = [
                    'subject_name' => $subjects[$i] ?? 'Unknown Subject',
                    'score' => isset($scores[$i]) ? floatval($scores[$i]) : 0,
                    'grade' => $grades[$i] ?? 'N/A',
                    'percentage' => isset($scores[$i]) ? floatval($scores[$i]) : 0
                ];
            }
            
            // ============ FIXED: Stream handling ============
            $report_stream_id = 0; // Default to 0 for no stream
            $report_stream_name = 'No Stream';
            
            // Check if student has a stream
            if (!empty($report['student_stream_id']) && $report['student_stream_id'] > 0) {
                $report_stream_id = $report['student_stream_id'];
                $report_stream_name = getStreamName($db, $report_stream_id, $school_id);
            }
            
            // Check if PDF exists
// ============ FIXED: PDF URL handling with proper file existence check ============
$pdf_url = $report['pdf_url'] ?? null;

// If no PDF URL in the join, try to find it by constructing from report_cards table
if (empty($pdf_url) && !empty($report['student_id']) && !empty($report['exam_id'])) {
    // Try to find the PDF in the report_cards table using student_id and exam_id
    $pdf_query = $db->prepare("
        SELECT pdf_url FROM report_cards 
        WHERE student_id = ? AND exam_id = ? AND class_id = ? AND school_id = ?
        ORDER BY id DESC LIMIT 1
    ");
    $pdf_query->execute([
        $report['student_id'], 
        $report['exam_id'], 
        $report['class_id'], 
        $school_id
    ]);
    $pdf_result = $pdf_query->fetch(PDO::FETCH_ASSOC);
    if ($pdf_result && !empty($pdf_result['pdf_url'])) {
        $pdf_url = $pdf_result['pdf_url'];
        error_log("Found PDF URL from direct query: " . $pdf_url);
    }
}

// If still no PDF URL, check the filesystem directly for matching PDF files
if (empty($pdf_url) && !empty($report['admission_no']) && !empty($exam_id)) {
    $reports_dir = '../reports/';
    $admission_no = $report['admission_no'] ?? $report['AdmNo'] ?? '';
    $student_name_slug = preg_replace('/[^A-Za-z0-9]/', '_', $student_name);
    
    // Try to find PDF by admission number
    $pattern = $reports_dir . 'report_*_' . $admission_no . '_' . $exam_id . '_*.pdf';
    $pdf_files = glob($pattern);
    
    if (empty($pdf_files)) {
        // Try by student name slug
        $pattern = $reports_dir . 'report_' . $student_name_slug . '_*_' . $exam_id . '_*.pdf';
        $pdf_files = glob($pattern);
    }
    
    if (!empty($pdf_files)) {
        // Use the most recent PDF
        usort($pdf_files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        $pdf_path = $pdf_files[0];
        $pdf_url = 'reports/' . basename($pdf_path);
        error_log("Found PDF URL from filesystem: " . $pdf_url);
    }
}

// Verify the PDF file exists
if (!empty($pdf_url)) {
    $full_path = '../' . ltrim($pdf_url, '/');
    if (file_exists($full_path)) {
        $has_pdfs = true;
        error_log("PDF verified at: " . $full_path);
    } else {
        error_log("PDF file not found at: " . $full_path);
        $pdf_url = null;
    }
}
            
            // Get numeric values with proper defaults
            $total_marks = isset($report['total_marks']) ? floatval($report['total_marks']) : 0;
            $total_points = isset($report['total_points']) ? floatval($report['total_points']) : 0;
            $mean_points = isset($report['mean_points']) ? floatval($report['mean_points']) : 0;
            $mean_percentage = isset($report['mean_percentage']) ? floatval($report['mean_percentage']) : 0;
            $total_rubric = isset($report['total_rubric']) ? floatval($report['total_rubric']) : 0;
            $overall_points = isset($report['overall_points']) ? floatval($report['overall_points']) : 0;
            $rank_position = isset($report['rank_position']) ? intval($report['rank_position']) : 0;
            
            // Build student name properly
            $student_name = trim($report['FirstName'] ?? '');
            if (!empty($report['SecondName'])) {
                $student_name .= ' ' . trim($report['SecondName']);
            }
            if (!empty($report['LastName'])) {
                $student_name .= ' ' . trim($report['LastName']);
            }
            if (empty($student_name)) {
                $student_name = $report['student_name'] ?? 'Unknown Student';
            }
            
            // Build processed report with ALL fields from meritlist
// Build processed report with ALL fields from meritlist
// IMPORTANT: Use meritlist_id as the primary ID for PDF lookup if report_card_id is 0
$report_id_for_pdf = $report['report_card_id'] ?? 0;
if ($report_id_for_pdf == 0 && !empty($report['id'])) {
    $report_id_for_pdf = $report['id']; // Fallback to meritlist ID
}

$processed_report = [
    // Report identifiers - use meritlist ID as fallback if no report_card_id
    'id' => $report_id_for_pdf,
    'meritlist_id' => $report['id'] ?? 0,
                'student_id' => $report['student_id'] ?? 0,
                'student_name' => $student_name,
                'admission_no' => $report['admission_no'] ?? $report['AdmNo'] ?? 'N/A',
                
                // Class/Stream/Exam/Term details
                'class_id' => $report['class_id'] ?? $class_id,
                'class_name' => $report['class_name'] ?? $class_name,
                'stream_id' => $report_stream_id,
                'stream_name' => $report_stream_name,
                'exam_id' => $report['exam_id'] ?? $exam_id,
                'exam_name' => $report['exam_name'] ?? $exam_name,
                'term_id' => $report['term_id'] ?? $term_id,
                'term_name' => $report['term_name'] ?? $term_name,
                'academic_year' => $report['term_academic_year'] ?? $report['academic_year'] ?? $academic_year,
                
                // Meritlist academic fields - ALL OF THEM
                'total_marks' => $total_marks,
                'total_points' => $total_points,
                'mean_points' => $mean_points,
                'mean_percentage' => $mean_percentage,
                'overall_grade' => $report['overall_grade'] ?? 'N/A',
                'overall_remarks' => $report['overall_remarks'] ?? '',
                'rank_position' => $rank_position,
                'position_suffix' => $report['position_suffix'] ?? getOrdinalSuffix($rank_position),
                'total_rubric' => $total_rubric,
                'overall_points' => $overall_points,
                'most_common_grade' => $report['most_common_grade'] ?? 'N/A',
                'ranking_method' => $report['ranking_method'] ?? 'total_marks',
                'academic_level' => $report['academic_level'] ?? 'primary',
                'created_by_teacher_id' => $report['created_by_teacher_id'] ?? 0,
                
                // Subject data - properly populated
                'subjects_count' => count($subject_scores),
                'subject_scores' => $subject_scores,
                'subjects_json' => $report['subjects_json'] ?? '[]',
                'subject_scores_json' => $report['subject_scores_json'] ?? '[]',
                'grades_array' => $report['grades_array'] ?? '[]',
                
                // Timestamps
                'generated_at' => $report['created_at'] ?? date('Y-m-d H:i:s'),
                'updated_at' => $report['updated_at'] ?? date('Y-m-d H:i:s'),
                
                // PDF related fields
                'has_pdf' => !empty($pdf_url),
                'pdf_url' => $pdf_url,
                'pdf_filename' => $pdf_url ? basename($pdf_url) : null,
                'report_status' => $report['report_status'] ?? 'Completed'
            ];
            
            $processed_reports[] = $processed_report;
            
            // Organize by stream
            if (!isset($streams_data[$report_stream_name])) {
                $streams_data[$report_stream_name] = [
                    'stream_name' => $report_stream_name,
                    'stream_id' => $report_stream_id,
                    'reports' => [],
                    'total_students' => 0,
                    'total_mean_score' => 0,
                    'total_total_marks' => 0,
                    'total_total_points' => 0,
                    'total_mean_points' => 0,
                    'grade_distribution' => []
                ];
            }
            
            $streams_data[$report_stream_name]['reports'][] = $processed_report;
            $streams_data[$report_stream_name]['total_students']++;
            $streams_data[$report_stream_name]['total_mean_score'] += $mean_percentage;
            $streams_data[$report_stream_name]['total_total_marks'] += $total_marks;
            $streams_data[$report_stream_name]['total_total_points'] += $total_points;
            $streams_data[$report_stream_name]['total_mean_points'] += $mean_points;
            
            // Grade distribution for stream
            $grade = $processed_report['overall_grade'];
            if (!empty($grade) && $grade !== 'N/A') {
                $streams_data[$report_stream_name]['grade_distribution'][$grade] = 
                    ($streams_data[$report_stream_name]['grade_distribution'][$grade] ?? 0) + 1;
            }
            
            // Calculate totals for overall summary
            $total_mean_score += $mean_percentage;
            $total_total_marks += $total_marks;
            $total_total_points += $total_points;
            $total_mean_points += $mean_points;
            
            if (!empty($grade) && $grade !== 'N/A') {
                $grade_distribution[$grade] = ($grade_distribution[$grade] ?? 0) + 1;
            }
        }
        
        // Calculate averages for streams
        foreach ($streams_data as &$stream) {
            if ($stream['total_students'] > 0) {
                $stream['average_score'] = round($stream['total_mean_score'] / $stream['total_students'], 2);
                $stream['average_total_marks'] = round($stream['total_total_marks'] / $stream['total_students'], 2);
                $stream['average_total_points'] = round($stream['total_total_points'] / $stream['total_students'], 2);
                $stream['average_mean_points'] = round($stream['total_mean_points'] / $stream['total_students'], 2);
            }
        }
    }
    
    $total_reports = count($processed_reports);
    $average_mean_score = $total_reports > 0 ? round($total_mean_score / $total_reports, 2) : 0;
    $average_total_marks = $total_reports > 0 ? round($total_total_marks / $total_reports, 2) : 0;
    $average_total_points = $total_reports > 0 ? round($total_total_points / $total_reports, 2) : 0;
    $average_mean_points = $total_reports > 0 ? round($total_mean_points / $total_reports, 2) : 0;
    
    // Calculate class rankings
    if (!empty($processed_reports)) {
        usort($processed_reports, function($a, $b) {
            return $b['mean_percentage'] <=> $a['mean_percentage'];
        });
        
        $rank = 0;
        $prev_score = null;
        $same_rank_count = 0;
        
        foreach ($processed_reports as &$report) {
            $current_score = $report['mean_percentage'];
            
            if ($prev_score !== $current_score) {
                $rank = $rank + $same_rank_count + 1;
                $same_rank_count = 0;
            } else {
                $same_rank_count++;
            }
            
            $report['class_rank'] = $rank;
            $report['class_rank_suffix'] = getOrdinalSuffix($rank);
            $prev_score = $current_score;
        }
    }
    
    // Calculate stream rankings
    foreach ($streams_data as &$stream) {
        if (!empty($stream['reports'])) {
            usort($stream['reports'], function($a, $b) {
                return $b['mean_percentage'] <=> $a['mean_percentage'];
            });
            
            $stream_rank = 0;
            $stream_prev_score = null;
            $stream_same_rank_count = 0;
            
            foreach ($stream['reports'] as &$stream_report) {
                $current_score = $stream_report['mean_percentage'];
                
                if ($stream_prev_score !== $current_score) {
                    $stream_rank = $stream_rank + $stream_same_rank_count + 1;
                    $stream_same_rank_count = 0;
                } else {
                    $stream_same_rank_count++;
                }
                
                $stream_report['stream_rank'] = $stream_rank;
                $stream_report['stream_rank_suffix'] = getOrdinalSuffix($stream_rank);
                $stream_prev_score = $current_score;
            }
        }
    }
    
    // Get top student
    $top_student = null;
    if (!empty($processed_reports)) {
        $top = $processed_reports[0];
        $top_student = [
            'student_id' => $top['student_id'],
            'student_name' => $top['student_name'],
            'admission_no' => $top['admission_no'],
            'stream_name' => $top['stream_name'],
            'mean_percentage' => $top['mean_percentage'],
            'total_marks' => $top['total_marks'],
            'total_points' => $top['total_points'],
            'mean_points' => $top['mean_points'],
            'overall_grade' => $top['overall_grade'],
            'rank_position' => $top['rank_position'],
            'class_rank' => $top['class_rank'] ?? 1,
            'pdf_url' => $top['pdf_url']
        ];
    }
    
    // Check for ZIP file
    $zip_url = null;
    $zip_filename = null;
    $reports_dir = '../reports/';
    
    if (!empty($class_name) && !empty($exam_name)) {
        $class_name_for_zip = preg_replace('/[^A-Za-z0-9]/', '_', $class_name);
        $exam_name_for_zip = preg_replace('/[^A-Za-z0-9]/', '_', $exam_name);
        
        $zip_pattern = $reports_dir . 'reports_' . $class_name_for_zip . '_' . $exam_name_for_zip . '_*.zip';
        $zip_matches = glob($zip_pattern);
        
        if (!empty($zip_matches)) {
            usort($zip_matches, function($a, $b) {
                return filemtime($b) - filemtime($a);
            });
            $zip_path = $zip_matches[0];
            $zip_filename = basename($zip_path);
            $zip_url = 'reports/' . $zip_filename;
        }
    }
    
    // Create consolidated report
    $consolidated_report = [
        'class_id' => $class_id,
        'class_name' => $class_name,
        'class_info' => $class_info,
        'stream_id' => $stream_id,
        'stream_name' => $stream_name,
        'stream_info' => $stream_info,
        'exam_id' => $exam_id,
        'exam_name' => $exam_name,
        'exam_info' => $exam_info,
        'term_id' => $term_id,
        'term_name' => $term_name,
        'term_info' => $term_info,
        'academic_year' => $academic_year,
        'total_students' => $total_reports,
        'average_score' => $average_mean_score,
        'average_total_marks' => $average_total_marks,
        'average_total_points' => $average_total_points,
        'average_mean_points' => $average_mean_points,
        'total_total_marks' => $total_total_marks,
        'total_total_points' => $total_total_points,
        'total_mean_points' => $total_mean_points,
        'grade_distribution' => $grade_distribution,
        'streams_count' => count($streams_data),
        'generated_at' => date('Y-m-d H:i:s'),
        'has_zip' => !empty($zip_url),
        'zip_url' => $zip_url,
        'zip_filename' => $zip_filename
    ];
    
    // ============ RETURN CONSOLIDATED RESPONSE ============
    echo json_encode([
        'success' => true,
        'message' => $total_reports > 0 ? 'Report cards fetched successfully' : 'No report cards found for the selected criteria',
        'data' => [
            'selection_details' => [
                'class_id' => $class_id,
                'class_name' => $class_name,
                'class_info' => $class_info,
                'stream_id' => $stream_id,
                'stream_name' => $stream_name,
                'stream_info' => $stream_info,
                'exam_id' => $exam_id,
                'exam_name' => $exam_name,
                'exam_info' => $exam_info,
                'term_id' => $term_id,
                'term_name' => $term_name,
                'term_info' => $term_info,
                'academic_year' => $academic_year,
                'school_id' => $school_id
            ],
            'all_reports' => $processed_reports,
            'streams' => array_values($streams_data),
            'consolidated_view' => $consolidated_report,
            'summary' => [
                'total_reports' => $total_reports,
                'total_students' => $total_reports,
                'average_mean_score' => $average_mean_score,
                'average_total_marks' => $average_total_marks,
                'average_total_points' => $average_total_points,
                'average_mean_points' => $average_mean_points,
                'total_total_marks' => $total_total_marks,
                'total_total_points' => $total_total_points,
                'total_mean_points' => $total_mean_points,
                'grade_distribution' => $grade_distribution,
                'top_student' => $top_student,
                'class_name' => $class_name,
                'stream_name' => $stream_name,
                'exam_name' => $exam_name,
                'term_name' => $term_name,
                'academic_year' => $academic_year
            ],
            'has_pdfs' => $has_pdfs,
            'zip_url' => $zip_url,
            'zip_filename' => $zip_filename
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Database Error in fetch_generated_reports.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred while fetching reports',
        'error_details' => $e->getMessage(),
        'data' => [
            'selection_details' => [
                'class_id' => $class_id ?? null,
                'stream_id' => $stream_id ?? 0,
                'exam_id' => $exam_id ?? null,
                'term_id' => $term_id ?? null
            ],
            'all_reports' => [],
            'streams' => [],
            'consolidated_view' => null,
            'summary' => []
        ]
    ]);
} catch (Exception $e) {
    error_log("Error in fetch_generated_reports.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching reports: ' . $e->getMessage(),
        'data' => [
            'selection_details' => [
                'class_id' => $class_id ?? null,
                'stream_id' => $stream_id ?? 0,
                'exam_id' => $exam_id ?? null,
                'term_id' => $term_id ?? null
            ],
            'all_reports' => [],
            'streams' => [],
            'consolidated_view' => null,
            'summary' => []
        ]
    ]);
}

// ============ HELPER FUNCTIONS ============

/**
 * Get ordinal suffix for a number (1st, 2nd, 3rd, etc.)
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

/**
 * Get class information
 */
function getClassInfo($db, $class_id, $school_id) {
    try {
        $stmt = $db->prepare("SELECT * FROM tblclasses WHERE id = ? AND school_id = ?");
        $stmt->execute([$class_id, $school_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: ['class_level' => 'Unknown Class', 'id' => $class_id];
    } catch (Exception $e) {
        error_log("Error in getClassInfo: " . $e->getMessage());
        return ['class_level' => 'Unknown Class', 'id' => $class_id];
    }
}

/**
 * Get exam information
 */
function getExamInfo($db, $exam_id, $school_id) {
    try {
        $stmt = $db->prepare("SELECT * FROM tblexam WHERE id = ? AND school_id = ?");
        $stmt->execute([$exam_id, $school_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: ['examname' => 'Unknown Exam', 'id' => $exam_id];
    } catch (Exception $e) {
        error_log("Error in getExamInfo: " . $e->getMessage());
        return ['examname' => 'Unknown Exam', 'id' => $exam_id];
    }
}

/**
 * Get stream information
 */
function getStreamInfo($db, $stream_id, $school_id) {
    if ($stream_id <= 0) return null;
    try {
        $stmt = $db->prepare("SELECT * FROM tblstreams WHERE id = ? AND school_id = ?");
        $stmt->execute([$stream_id, $school_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error in getStreamInfo: " . $e->getMessage());
        return null;
    }
}

/**
 * Get stream name
 */
function getStreamName($db, $stream_id, $school_id) {
    if ($stream_id <= 0) return 'No Stream';
    try {
        $stmt = $db->prepare("SELECT stream_name FROM tblstreams WHERE id = ? AND school_id = ?");
        $stmt->execute([$stream_id, $school_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['stream_name'] : 'No Stream';
    } catch (Exception $e) {
        error_log("Error in getStreamName: " . $e->getMessage());
        return 'No Stream';
    }
}

/**
 * Get term information
 */
function getTermInfo($db, $term_id, $school_id) {
    if (!$term_id || $term_id <= 0) return null;
    try {
        $stmt = $db->prepare("SELECT * FROM tblterms WHERE id = ? AND school_id = ?");
        $stmt->execute([$term_id, $school_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error in getTermInfo: " . $e->getMessage());
        return null;
    }
}

/**
 * Get term name
 */
function getTermName($db, $term_id, $school_id) {
    if (!$term_id || $term_id <= 0) return null;
    try {
        $stmt = $db->prepare("SELECT term_name, academic_year FROM tblterms WHERE id = ? AND school_id = ?");
        $stmt->execute([$term_id, $school_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['term_name'] : null;
    } catch (Exception $e) {
        error_log("Error in getTermName: " . $e->getMessage());
        return null;
    }
}

/**
 * Scan filesystem for PDF reports when database records are missing
 */
function scanFilesystemForReports($db, $class_id, $stream_id, $exam_id, $term_id, $school_id, $input) {
    $reports = [];
    $reports_dir = '../reports/';
    
    if (!is_dir($reports_dir)) {
        return $reports;
    }
    
    try {
        $class_info = getClassInfo($db, $class_id, $school_id);
        $exam_info = getExamInfo($db, $exam_id, $school_id);
        $term_info = getTermInfo($db, $term_id, $school_id);
        
        $pattern = $reports_dir . 'report_*_' . $exam_id . '_*.pdf';
        $pdf_files = glob($pattern);
        
        if (!empty($pdf_files)) {
            foreach ($pdf_files as $pdf_path) {
                $pdf_filename = basename($pdf_path);
                $pdf_url = 'reports/' . $pdf_filename;
                
                if (preg_match('/report_(.+?)_(.+?)_' . $exam_id . '_(\d+)\.pdf/', $pdf_filename, $matches)) {
                    $student_name_slug = $matches[1] ?? '';
                    $admission_no = $matches[2] ?? '';
                    $timestamp = $matches[3] ?? '';
                    
                    $student_name = str_replace('_', ' ', $student_name_slug);
                    
                    // Try to find student in database
                    $stmt = $db->prepare("
                        SELECT * FROM tblstudents 
                        WHERE AdmNo = ? AND school_id = ? AND class_id = ?
                        LIMIT 1
                    ");
                    $stmt->execute([$admission_no, $school_id, $class_id]);
                    $student = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $student_stream_id = $student ? ($student['StreamId'] ?? 0) : 0;
                    $student_stream_name = $student_stream_id > 0 ? 
                        getStreamName($db, $student_stream_id, $school_id) : 'No Stream';
                    
                    // ============ FIXED: Stream filtering for filesystem scan ============
                    if ($stream_id > 0) {
                        // If specific stream selected, match either no stream OR matching stream
                        if ($student_stream_id > 0 && $student_stream_id != $stream_id) {
                            continue;
                        }
                    }
                    
                    $reports[] = [
                        'id' => 0,
                        'student_id' => $student['id'] ?? 0,
                        'student_full_name' => $student_name,
                        'admission_no' => $admission_no,
                        'class_id' => $class_id,
                        'class_name' => $class_info['class_level'] ?? 'Unknown',
                        'stream_id' => $student_stream_id,
                        'stream_name' => $student_stream_name,
                        'exam_id' => $exam_id,
                        'exam_name' => $exam_info['examname'] ?? 'Unknown',
                        'term_id' => $term_id,
                        'term_name' => $term_info['term_name'] ?? 'N/A',
                        'term_academic_year' => $term_info['academic_year'] ?? null,
                        'academic_year' => $input['academic_year'] ?? ($term_info['academic_year'] ?? date('Y')),
                        'total_marks' => 0,
                        'total_points' => 0,
                        'mean_points' => 0,
                        'mean_percentage' => 0,
                        'overall_grade' => 'N/A',
                        'overall_remarks' => '',
                        'rank_position' => 0,
                        'position_suffix' => '',
                        'total_rubric' => 0,
                        'overall_points' => 0,
                        'most_common_grade' => 'N/A',
                        'ranking_method' => 'total_marks',
                        'academic_level' => 'primary',
                        'created_by_teacher_id' => 0,
                        'subjects_json' => '[]',
                        'subject_scores_json' => '[]',
                        'grades_array' => '[]',
                        'subjects_count' => 0,
                        'subject_scores' => [],
                        'pdf_url' => $pdf_url,
                        'has_pdf' => true,
                        'created_at' => date('Y-m-d H:i:s', filemtime($pdf_path)),
                        'updated_at' => date('Y-m-d H:i:s', filemtime($pdf_path)),
                        'report_status' => 'Completed'
                    ];
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error in scanFilesystemForReports: " . $e->getMessage());
    }
    
    return $reports;
}
?>