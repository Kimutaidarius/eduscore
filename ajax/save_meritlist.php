<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');

// Set custom error handler
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error [$errno]: $errstr in $errfile on line $errline");
    return true;
}, E_ALL);

if (!isset($_SESSION['school_id']) || !isset($_SESSION['teacher_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$school_id = $_SESSION['school_id'];
$teacher_id = $_SESSION['teacher_id'];
$data = json_decode(file_get_contents('php://input'), true);

// Debug: Log what we received
error_log("=== SAVE MERIT LIST DEBUG ===");
error_log("Session - Teacher: $teacher_id, School: $school_id");
error_log("Raw POST data: " . file_get_contents('php://input'));

if (empty($data)) {
    error_log("No JSON data received");
    echo json_encode(['success' => false, 'message' => 'No data received']);
    exit();
}

// Validate required fields
$required_fields = ['class_id', 'exam_id', 'academic_year', 'merit_list', 'ranking_method'];
foreach ($required_fields as $field) {
    if (!isset($data[$field])) {
        error_log("Missing field: $field");
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit();
    }
}

$class_id = intval($data['class_id']);
$stream_id = isset($data['stream_id']) ? intval($data['stream_id']) : 0;
$exam_id = intval($data['exam_id']);
$term_id = isset($data['term_id']) && $data['term_id'] !== null ? intval($data['term_id']) : null;
$academic_year = intval($data['academic_year']);
$ranking_method = $data['ranking_method'];
$academic_level = isset($data['academic_level']) ? $data['academic_level'] : ($_SESSION['academic_level'] ?? 'primary');
$merit_list = $data['merit_list'];

error_log("Parsed: class=$class_id, stream=$stream_id, exam=$exam_id, year=$academic_year, term=" . ($term_id ?? 'null'));
error_log("Merit list count: " . count($merit_list));

if ($class_id <= 0 || $exam_id <= 0 || empty($merit_list)) {
    error_log("Invalid: class=$class_id, exam=$exam_id, list_empty=" . empty($merit_list));
    echo json_encode(['success' => false, 'message' => 'Invalid data provided']);
    exit();
}

// Include config - this gives us PDO connection
$config_file = dirname(__DIR__) . '/includes/config.php';
if (!file_exists($config_file)) {
    error_log("Config file not found: $config_file");
    echo json_encode(['success' => false, 'message' => 'Configuration error']);
    exit();
}

require_once $config_file;

// Use the PDO connection from config.php
global $dbh;
$conn = $dbh; // $dbh is the PDO connection from config.php

$response = [
    'success' => false,
    'message' => '',
    'saved_count' => 0,
    'failed_count' => 0,
    'details' => []
];

try {
    // Begin transaction
    $conn->beginTransaction();
    
    $saved = 0;
    $failed = 0;

    // First, delete any existing merit list for this class, exam, term, and year
    $delete_sql = "DELETE FROM tblmeritlist 
                   WHERE school_id = ? AND class_id = ? AND exam_id = ? 
                   AND academic_year = ?";
    
    if ($term_id !== null) {
        $delete_sql .= " AND term_id = ?";
        $params = [$school_id, $class_id, $exam_id, $academic_year, $term_id];
    } else {
        $delete_sql .= " AND term_id IS NULL";
        $params = [$school_id, $class_id, $exam_id, $academic_year];
    }
    
    error_log("Delete SQL: $delete_sql");
    
    $delete_stmt = $conn->prepare($delete_sql);
    if (!$delete_stmt->execute($params)) {
        error_log("Delete execute failed");
        throw new Exception("Delete execute failed");
    }
    
    $deleted_rows = $delete_stmt->rowCount();
    error_log("Deleted $deleted_rows existing merit list entries");
    
    // Prepare insert statement with PDO
    $insert_sql = "INSERT INTO tblmeritlist (
        school_id, class_id, stream_id, exam_id, term_id, academic_year,
        ranking_method, academic_level, student_id, admission_no, student_name,
        total_marks, total_points, total_rubric, mean_points, mean_percentage,
        overall_grade, overall_points, overall_remarks, most_common_grade,
        rank_position, position_suffix, subjects_json, subject_scores_json,
        grades_array, created_by_teacher_id
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $insert_stmt = $conn->prepare($insert_sql);
    
    // Insert new merit list entries
    foreach ($merit_list as $index => $student_data) {
        error_log("Processing student $index");
        
        // Validate student data
        if (!isset($student_data['student_id']) || !isset($student_data['admission_no']) || 
            !isset($student_data['full_name']) || !isset($student_data['rank'])) {
            $failed++;
            $response['details'][] = [
                'student_id' => $student_data['student_id'] ?? 'unknown',
                'status' => 'failed',
                'message' => 'Missing required student data'
            ];
            error_log("Missing student data for index $index");
            continue;
        }
        
        $student_id = intval($student_data['student_id']);
        $admission_no = $student_data['admission_no'];
        $student_name = $student_data['full_name'];
        $rank_position = intval($student_data['rank']);
        
        // Calculate position suffix
        $position_suffix = '';
        if ($rank_position > 0) {
            $last_digit = $rank_position % 10;
            $last_two_digits = $rank_position % 100;
            
            if ($last_digit == 1 && $last_two_digits != 11) {
                $position_suffix = 'st';
            } elseif ($last_digit == 2 && $last_two_digits != 12) {
                $position_suffix = 'nd';
            } elseif ($last_digit == 3 && $last_two_digits != 13) {
                $position_suffix = 'rd';
            } else {
                $position_suffix = 'th';
            }
        }
        
        // Prepare JSON data
        $subjects_json = isset($student_data['subjects']) ? 
            json_encode($student_data['subjects'], JSON_UNESCAPED_UNICODE) : '[]';
        
        $subject_scores_json = isset($student_data['subject_scores']) ? 
            json_encode($student_data['subject_scores'], JSON_UNESCAPED_UNICODE) : '[]';
        
        $grades_array_json = isset($student_data['grades']) ? 
            json_encode($student_data['grades'], JSON_UNESCAPED_UNICODE) : '[]';
        
        // Get values with defaults
        $total_marks = floatval($student_data['total_marks'] ?? 0);
        $total_points = floatval($student_data['total_points'] ?? 0);
        $total_rubric = floatval($student_data['total_rubric'] ?? 0);
        $mean_points = floatval($student_data['mean_points'] ?? 0);
        $mean_percentage = floatval($student_data['mean_percentage'] ?? 0);
        $overall_grade = $student_data['overall_grade'] ?? '';
        $overall_points = floatval($student_data['overall_points'] ?? 0);
        $overall_remarks = $student_data['overall_remarks'] ?? '';
        $most_common_grade = $student_data['most_common_grade'] ?? '';
        
        // Handle NULL term_id for PDO
        $bind_term_id = $term_id !== null ? $term_id : null;
        
        // Prepare parameters
        $params = [
            $school_id,
            $class_id,
            $stream_id,
            $exam_id,
            $bind_term_id,
            $academic_year,
            $ranking_method,
            $academic_level,
            $student_id,
            $admission_no,
            $student_name,
            $total_marks,
            $total_points,
            $total_rubric,
            $mean_points,
            $mean_percentage,
            $overall_grade,
            $overall_points,
            $overall_remarks,
            $most_common_grade,
            $rank_position,
            $position_suffix,
            $subjects_json,
            $subject_scores_json,
            $grades_array_json,
            $teacher_id
        ];
        
        // Execute with PDO
        try {
            if ($insert_stmt->execute($params)) {
                $saved++;
                $response['details'][] = [
                    'student_id' => $student_id,
                    'status' => 'saved',
                    'message' => 'Merit list entry saved successfully'
                ];
                error_log("Saved student $student_id successfully");
            } else {
                $failed++;
                $errorInfo = $insert_stmt->errorInfo();
                $response['details'][] = [
                    'student_id' => $student_id,
                    'status' => 'failed',
                    'message' => 'Save failed: ' . ($errorInfo[2] ?? 'Unknown error')
                ];
                error_log("Save failed for student $student_id: " . ($errorInfo[2] ?? 'Unknown error'));
            }
        } catch (PDOException $e) {
            $failed++;
            $response['details'][] = [
                'student_id' => $student_id,
                'status' => 'failed',
                'message' => 'Save failed: ' . $e->getMessage()
            ];
            error_log("PDO Exception for student $student_id: " . $e->getMessage());
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    $response['success'] = true;
    $response['message'] = "Merit list saved successfully";
    $response['saved_count'] = $saved;
    $response['failed_count'] = $failed;
    
    error_log("Successfully saved $saved students, failed: $failed");
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($conn) && $conn) {
        $conn->rollback();
    }
    
    $response['success'] = false;
    $response['message'] = "Error saving merit list: " . $e->getMessage();
    error_log("Exception in save_meritlist.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
}

// Send response
echo json_encode($response, JSON_UNESCAPED_UNICODE);
error_log("Response sent: " . json_encode($response));
exit();
?>