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

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get JSON data
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
    exit();
}

$teacher_id = $_SESSION['teacher_id'];
$school_id = $_SESSION['school_id'];

// Extract data
$class_id = isset($data['class_id']) ? intval($data['class_id']) : 0;
$stream_id = isset($data['stream_id']) ? intval($data['stream_id']) : 0;
$subject_id = isset($data['subject_id']) ? intval($data['subject_id']) : 0;
$exam_id = isset($data['exam_id']) ? intval($data['exam_id']) : 0;
$term_id = isset($data['term_id']) ? intval($data['term_id']) : 0;
$year = isset($data['year']) ? intval($data['year']) : date('Y');
$total_score = isset($data['total_score']) ? floatval($data['total_score']) : 0;

// Validate required fields
if (!$class_id || !$subject_id || !$exam_id) {
    echo json_encode([
        'success' => false, 
        'message' => 'Missing required fields: class_id, subject_id, exam_id'
    ]);
    exit();
}

if ($total_score <= 0) {
    echo json_encode([
        'success' => false, 
        'message' => 'Total score must be greater than 0'
    ]);
    exit();
}

if ($total_score > 500) {
    echo json_encode([
        'success' => false, 
        'message' => 'Total score cannot exceed 500'
    ]);
    exit();
}

// Database connection
require_once dirname(__DIR__) . '/includes/config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

try {
    // Begin transaction
    $conn->begin_transaction();

    // First, get all students for this class/stream
    $student_query = "SELECT id FROM tblstudents 
                      WHERE school_id = ? AND CAST(class_id AS UNSIGNED) = ? AND Status = 'Active'";
    $student_params = [$school_id, $class_id];
    $student_types = "ii";
    
    if ($stream_id > 0) {
        $student_query .= " AND StreamId = ?";
        $student_params[] = $stream_id;
        $student_types .= "i";
    }
    
    $student_stmt = $conn->prepare($student_query);
    $student_stmt->bind_param($student_types, ...$student_params);
    $student_stmt->execute();
    $student_result = $student_stmt->get_result();
    
    $student_ids = [];
    while ($row = $student_result->fetch_assoc()) {
        $student_ids[] = $row['id'];
    }
    $student_stmt->close();
    
    if (empty($student_ids)) {
        throw new Exception("No active students found for this class");
    }
    
    // Update existing scores with the new total
    $update_sql = "UPDATE tblscores 
                   SET total_score = ? 
                   WHERE school_id = ? AND exam_id = ? AND subject_id = ? AND class_id = ?";
    $update_params = [$total_score, $school_id, $exam_id, $subject_id, $class_id];
    $update_types = "diiii";
    
    if ($stream_id > 0) {
        $update_sql .= " AND StreamId = ?";
        $update_params[] = $stream_id;
        $update_types .= "i";
    }
    
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param($update_types, ...$update_params);
    $update_stmt->execute();
    $updated_count = $update_stmt->affected_rows;
    $update_stmt->close();
    
    // Now check which students don't have score records yet
    $placeholders = implode(',', array_fill(0, count($student_ids), '?'));
    
    $check_sql = "SELECT DISTINCT student_id FROM tblscores 
                  WHERE school_id = ? AND exam_id = ? AND subject_id = ? 
                    AND class_id = ? AND student_id IN ($placeholders)";
    
    $check_params = array_merge([$school_id, $exam_id, $subject_id, $class_id], $student_ids);
    $check_types = "iiii" . str_repeat("i", count($student_ids));
    
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param($check_types, ...$check_params);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    $students_with_scores = [];
    while ($row = $check_result->fetch_assoc()) {
        $students_with_scores[] = $row['student_id'];
    }
    $check_stmt->close();
    
    // Find students without scores
    $students_without_scores = array_diff($student_ids, $students_with_scores);
    
    // Create placeholder score records for students without scores
    $inserted_count = 0;
    if (!empty($students_without_scores)) {
        $insert_sql = "INSERT INTO tblscores 
                      (school_id, student_id, subject_id, exam_id, class_id, StreamId, 
                       total_score, recorded_by_teacher_id, recorded_at, rubric, grade) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), '', '')";
        
        $insert_stmt = $conn->prepare($insert_sql);
        
        foreach ($students_without_scores as $student_id) {
            $insert_stmt->bind_param("iiiiiiid", 
                $school_id, 
                $student_id, 
                $subject_id, 
                $exam_id, 
                $class_id, 
                $stream_id, 
                $total_score, 
                $teacher_id
            );
            
            if ($insert_stmt->execute()) {
                $inserted_count++;
            }
        }
        $insert_stmt->close();
    }
    
    // Also update any scores that might have stream_id = 0 when stream_id > 0
    if ($stream_id > 0) {
        $update_zero_sql = "UPDATE tblscores 
                           SET total_score = ?, StreamId = ? 
                           WHERE school_id = ? AND exam_id = ? AND subject_id = ? 
                             AND class_id = ? AND (StreamId = 0 OR StreamId IS NULL)";
        $update_zero_stmt = $conn->prepare($update_zero_sql);
        $update_zero_stmt->bind_param("diiiii", $total_score, $stream_id, $school_id, $exam_id, $subject_id, $class_id);
        $update_zero_stmt->execute();
        $update_zero_count = $update_zero_stmt->affected_rows;
        $update_zero_stmt->close();
    } else {
        $update_zero_count = 0;
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "Exam total set to {$total_score} points",
        'total_score' => $total_score,
        'updated_existing' => $updated_count,
        'created_new' => $inserted_count,
        'updated_zero_stream' => $update_zero_count,
        'total_students' => count($student_ids),
        'students_with_scores' => count($students_with_scores),
        'students_without_scores' => count($students_without_scores)
    ]);

} catch (Exception $e) {
    $conn->rollback();
    error_log("Error in save_exam_total.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} finally {
    $conn->close();
}
?>