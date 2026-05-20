<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['school_id'])) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit();
}

$teacher_id = $_SESSION['teacher_id'];
$school_id = $_SESSION['school_id'];

// Get POST data
$class_id = isset($_POST['class_id']) ? intval($_POST['class_id']) : 0;
$stream_id = isset($_POST['stream_id']) ? intval($_POST['stream_id']) : 0;
$subject_id = isset($_POST['subject_id']) ? intval($_POST['subject_id']) : 0;
$exam_id = isset($_POST['exam_id']) ? intval($_POST['exam_id']) : 0;
$term_id = isset($_POST['term_id']) ? intval($_POST['term_id']) : 0;
$year = isset($_POST['year']) ? intval($_POST['year']) : date('Y');

// Validate required fields
if (!$class_id) {
    echo json_encode(['success' => false, 'message' => 'Class ID is required']);
    exit();
}

if (!$subject_id) {
    echo json_encode(['success' => false, 'message' => 'Subject ID is required']);
    exit();
}

if (!$exam_id) {
    echo json_encode(['success' => false, 'message' => 'Exam ID is required']);
    exit();
}

// Database connection
require_once dirname(__DIR__) . '/includes/config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Initialize statement variables
$stmt = null;
$score_stmt = null;
$total_from_scores_stmt = null;
$total_stmt = null;
$grading_stmt = null;

try {
    // Build the query to fetch students from tblstudents
    $query = "SELECT 
                s.id,
                s.AdmNo as admission_no,
                s.FirstName,
                s.SecondName,
                s.LastName,
                CONCAT(TRIM(s.FirstName), ' ', TRIM(s.SecondName), ' ', COALESCE(TRIM(s.LastName), '')) as full_name,
                s.Gender,
                s.StreamId,
                st.stream_name,
                s.Status
              FROM tblstudents s
              LEFT JOIN tblstreams st ON s.StreamId = st.id
              WHERE s.school_id = ? 
                AND CAST(s.class_id AS UNSIGNED) = ?
                AND s.Status = 'Active'";
    
    $params = [$school_id, $class_id];
    $types = "ii";
    
    // Add stream filter if provided
    if ($stream_id > 0) {
        $query .= " AND s.StreamId = ?";
        $params[] = $stream_id;
        $types .= "i";
    }
    
    $query .= " ORDER BY s.FirstName ASC, s.SecondName ASC";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Failed to prepare student statement: " . $conn->error);
    }
    
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $students = [];
    $student_ids = [];
    
    while ($row = $result->fetch_assoc()) {
        // Clean up full name (remove extra spaces)
        $full_name = trim(preg_replace('/\s+/', ' ', $row['full_name']));
        
        $students[$row['id']] = [
            'id' => $row['id'],
            'admission_no' => $row['admission_no'],
            'first_name' => $row['FirstName'],
            'second_name' => $row['SecondName'],
            'last_name' => $row['LastName'],
            'full_name' => $full_name ?: 'Unknown',
            'name' => $full_name ?: 'Unknown', // Alias for compatibility
            'gender' => $row['Gender'],
            'stream_id' => $row['StreamId'],
            'stream_name' => $row['stream_name'] ?? '',
            'status' => $row['Status'],
            // Initialize score fields with defaults
            'score' => '',
            'total_score' => 0,
            'percentage' => 0,
            'rubric' => '',
            'grade' => '',
            'remarks' => ''
        ];
        
        $student_ids[] = $row['id'];
    }
    
    // If no students found, return empty array
    if (empty($students)) {
        echo json_encode([
            'success' => true,
            'data' => [],
            'message' => 'No active students found for this class'
        ]);
        exit();
    }
    
    // Fetch existing scores for these students for this exam/subject
    $placeholders = implode(',', array_fill(0, count($student_ids), '?'));
    $scores_query = "SELECT 
                        s.student_id,
                        s.score_value as score,
                        s.total_score,
                        s.percentage,
                        s.rubric,
                        s.grade,
                        s.recorded_at
                     FROM tblscores s
                     WHERE s.school_id = ? 
                       AND s.subject_id = ? 
                       AND s.exam_id = ? 
                       AND s.class_id = ?
                       AND s.student_id IN ($placeholders)";
    
    $score_params = array_merge([$school_id, $subject_id, $exam_id, $class_id], $student_ids);
    $score_types = "iiii" . str_repeat("i", count($student_ids));
    
    $score_stmt = $conn->prepare($scores_query);
    if (!$score_stmt) {
        throw new Exception("Failed to prepare score statement: " . $conn->error);
    }
    
    $score_stmt->bind_param($score_types, ...$score_params);
    $score_stmt->execute();
    $score_result = $score_stmt->get_result();
    
    // Merge scores with student data
    while ($score_row = $score_result->fetch_assoc()) {
        $student_id = $score_row['student_id'];
        if (isset($students[$student_id])) {
            $students[$student_id]['score'] = $score_row['score'];
            $students[$student_id]['total_score'] = $score_row['total_score'];
            $students[$student_id]['percentage'] = $score_row['percentage'];
            $students[$student_id]['rubric'] = $score_row['rubric'];
            $students[$student_id]['grade'] = $score_row['grade'];
            
            // Map rubric points to remarks if needed
            $rubric_value = intval($score_row['rubric']);
            if ($rubric_value == 4) {
                $students[$student_id]['remarks'] = 'Exceeding Expectations';
            } elseif ($rubric_value == 3) {
                $students[$student_id]['remarks'] = 'Meeting Expectations';
            } elseif ($rubric_value == 2) {
                $students[$student_id]['remarks'] = 'Approaching Expectations';
            } elseif ($rubric_value == 1) {
                $students[$student_id]['remarks'] = 'Below Expectations';
            } else {
                $students[$student_id]['remarks'] = '';
            }
        }
    }
    
    // Get exam total score - first try to get from tblscores
    $exam_total = 0;
    
    // Try to get from any existing score record in tblscores
    $total_from_scores_query = "SELECT total_score FROM tblscores 
                                WHERE school_id = ? AND exam_id = ? AND subject_id = ? AND class_id = ?";
    $total_from_scores_params = [$school_id, $exam_id, $subject_id, $class_id];
    $total_from_scores_types = "iiii";
    
    if ($stream_id > 0) {
        $total_from_scores_query .= " AND StreamId = ?";
        $total_from_scores_params[] = $stream_id;
        $total_from_scores_types .= "i";
    }
    
    $total_from_scores_query .= " LIMIT 1";
    
    $total_from_scores_stmt = $conn->prepare($total_from_scores_query);
    if (!$total_from_scores_stmt) {
        throw new Exception("Failed to prepare total from scores statement: " . $conn->error);
    }
    
    $total_from_scores_stmt->bind_param($total_from_scores_types, ...$total_from_scores_params);
    $total_from_scores_stmt->execute();
    $total_from_scores_result = $total_from_scores_stmt->get_result();
    
    if ($total_from_scores_row = $total_from_scores_result->fetch_assoc()) {
        $exam_total = floatval($total_from_scores_row['total_score']);
    }
    $total_from_scores_stmt->close();
    $total_from_scores_stmt = null; // Set to null after closing
    
    // If not found in tblscores, check if tblexam_subject_totals exists
    if ($exam_total == 0) {
        $check_table = $conn->query("SHOW TABLES LIKE 'tblexam_subject_totals'");
        if ($check_table && $check_table->num_rows > 0) {
            // Build query for tblexam_subject_totals
            $total_query = "SELECT total_score FROM tblexam_subject_totals 
                            WHERE school_id = ? AND exam_id = ? AND subject_id = ? AND class_id = ?";
            $total_params = [$school_id, $exam_id, $subject_id, $class_id];
            $total_types = "iiii";
            
            if ($stream_id > 0) {
                $total_query .= " AND stream_id = ?";
                $total_params[] = $stream_id;
                $total_types .= "i";
            } else {
                $total_query .= " AND (stream_id = 0 OR stream_id IS NULL)";
            }
            
            $total_query .= " LIMIT 1";
            
            $total_stmt = $conn->prepare($total_query);
            if (!$total_stmt) {
                throw new Exception("Failed to prepare total statement: " . $conn->error);
            }
            
            $total_stmt->bind_param($total_types, ...$total_params);
            $total_stmt->execute();
            $total_result = $total_stmt->get_result();
            
            if ($total_row = $total_result->fetch_assoc()) {
                $exam_total = floatval($total_row['total_score']);
            }
            $total_stmt->close();
            $total_stmt = null; // Set to null after closing
        }
    }
    
    // Get grading scale for this subject/class
$grading_query = "SELECT 
                    lower_limit, 
                    upper_limit, 
                    grade_alias,
                    points,
                    remarks,
                    principal_remarks
                  FROM tblsubjectgrading 
                  WHERE school_id = ? 
                  AND subject_id = ? 
                  AND class_id = ?";

$grading_params = [$school_id, $subject_id, $class_id];
$grading_types = "iii";

if ($stream_id > 0) {
    $grading_query .= " AND (stream_id = ? OR stream_id IS NULL)";
    $grading_params[] = $stream_id;
    $grading_types .= "i";
} else {
    $grading_query .= " AND stream_id IS NULL";
}

/* IMPORTANT: Always bind stream_id for ORDER BY CASE */
$grading_query .= " 
    ORDER BY 
    CASE 
        WHEN stream_id = ? THEN 0
        WHEN stream_id IS NULL THEN 1
        ELSE 2
    END
    LIMIT 1";

$grading_params[] = $stream_id;   // ALWAYS add this
$grading_types .= "i";            // ALWAYS add this

$grading_stmt = $conn->prepare($grading_query);
if (!$grading_stmt) {
    throw new Exception("Failed to prepare grading statement: " . $conn->error);
}

$grading_stmt->bind_param($grading_types, ...$grading_params);
    $grading_stmt->execute();
    $grading_result = $grading_stmt->get_result();
    
    $grading_scale = [];
    while ($grade_row = $grading_result->fetch_assoc()) {
        $grading_scale[] = $grade_row;
    }
    
    // If no custom grading scale found, use default CBE scale
    if (empty($grading_scale)) {
        $grading_scale = [
            ['lower_limit' => 80, 'upper_limit' => 100, 'grade_alias' => 'EE', 'points' => 4, 'remarks' => 'Exceeding Expectations'],
            ['lower_limit' => 65, 'upper_limit' => 79, 'grade_alias' => 'ME', 'points' => 3, 'remarks' => 'Meeting Expectations'],
            ['lower_limit' => 50, 'upper_limit' => 64, 'grade_alias' => 'AE', 'points' => 2, 'remarks' => 'Approaching Expectations'],
            ['lower_limit' => 30, 'upper_limit' => 49, 'grade_alias' => 'BE', 'points' => 1, 'remarks' => 'Below Expectations'],
            ['lower_limit' => 0, 'upper_limit' => 29, 'grade_alias' => 'BE', 'points' => 1, 'remarks' => 'Below Expectations']
        ];
    }
    
    // Re-index students array to 0-based for JSON response
    $students_array = array_values($students);
    
    // Return response
    echo json_encode([
        'success' => true,
        'data' => $students_array,
        'count' => count($students_array),
        'exam_total' => $exam_total,
        'grading_scale' => $grading_scale,
        'class_id' => $class_id,
        'stream_id' => $stream_id,
        'subject_id' => $subject_id,
        'exam_id' => $exam_id
    ]);

} catch (Exception $e) {
    error_log("Error in get_students.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} finally {
    // Only close statements that were successfully created
    if ($stmt !== null) {
        $stmt->close();
    }
    if ($score_stmt !== null) {
        $score_stmt->close();
    }
    if ($total_from_scores_stmt !== null) {
        $total_from_scores_stmt->close();
    }
    if ($total_stmt !== null) {
        $total_stmt->close();
    }
    if ($grading_stmt !== null) {
        $grading_stmt->close();
    }
    if ($conn) {
        $conn->close();
    }
}
?>