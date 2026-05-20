<?php
session_start();
require_once '../includes/config.php';

header('Content-Type: application/json');

// Check if user is logged in and has school_id
if (!isset($_SESSION['school_id']) || empty($_SESSION['school_id'])) {
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

$school_id = $_SESSION['school_id'];

// Get parameters from POST or GET
$exam_id = isset($_POST['exam_id']) ? intval($_POST['exam_id']) : (isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0);
$class_id = isset($_POST['class_id']) ? intval($_POST['class_id']) : (isset($_GET['class_id']) ? intval($_GET['class_id']) : 0);
$stream_id = isset($_POST['stream_id']) ? intval($_POST['stream_id']) : (isset($_GET['stream_id']) ? intval($_GET['stream_id']) : 0);
$teacher_id = isset($_POST['teacher_id']) ? intval($_POST['teacher_id']) : (isset($_SESSION['teacher_id']) ? $_SESSION['teacher_id'] : 0);

// Log received parameters for debugging (remove in production)
error_log("Student Count Request: exam_id=$exam_id, class_id=$class_id, stream_id=$stream_id, school_id=$school_id");

// Validate required parameters
if ($class_id <= 0) {
    echo json_encode(['error' => 'Invalid class ID', 'received_class_id' => $class_id]);
    exit;
}

try {
    // Initialize response array
    $response = [
        'success' => false,
        'total_students' => 0,
        'students_with_scores' => 0,
        'students_without_scores' => 0,
        'exam_name' => 'Unknown Exam',
        'class_name' => 'Unknown Class',
        'stream_name' => 'No Stream',
        'has_stream' => false,
        'exam_exists' => false
    ];
    
    // First, get total students in the class/stream
    $sql = "SELECT COUNT(*) as total_students 
            FROM tblstudents 
            WHERE school_id = ? 
            AND class_id = ? 
            AND Status = 'Active'";
    
    $params = [$school_id, $class_id];
    
    if ($stream_id > 0) {
        $sql .= " AND StreamId = ?";
        $params[] = $stream_id;
    }
    
    $stmt = $dbh->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        $response['total_students'] = intval($result['total_students']);
    }
    
    // Get students who already have scores for this exam (if exam_id is provided)
    if ($exam_id > 0) {
        $sql_scores = "SELECT COUNT(DISTINCT s.student_id) as students_with_scores
                       FROM tblscores s
                       WHERE s.school_id = ? 
                       AND s.exam_id = ? 
                       AND s.class_id = ?";
        
        $score_params = [$school_id, $exam_id, $class_id];
        
        if ($stream_id > 0) {
            $sql_scores .= " AND s.StreamId = ?";
            $score_params[] = $stream_id;
        }
        
        $stmt_scores = $dbh->prepare($sql_scores);
        $stmt_scores->execute($score_params);
        $score_result = $stmt_scores->fetch(PDO::FETCH_ASSOC);
        
        if ($score_result) {
            $response['students_with_scores'] = intval($score_result['students_with_scores']);
            $response['students_without_scores'] = $response['total_students'] - $response['students_with_scores'];
        }
        
        // Check if exam exists
        $sql_exam_check = "SELECT COUNT(*) as exam_count FROM tblexam WHERE id = ? AND school_id = ?";
        $stmt_exam_check = $dbh->prepare($sql_exam_check);
        $stmt_exam_check->execute([$exam_id, $school_id]);
        $exam_check = $stmt_exam_check->fetch(PDO::FETCH_ASSOC);
        
        $response['exam_exists'] = ($exam_check && $exam_check['exam_count'] > 0);
    }
    
    // Get exam details if exam exists
    if ($response['exam_exists'] || $exam_id > 0) {
        $sql_exam = "SELECT examname FROM tblexam WHERE id = ? AND school_id = ?";
        $stmt_exam = $dbh->prepare($sql_exam);
        $stmt_exam->execute([$exam_id, $school_id]);
        $exam = $stmt_exam->fetch(PDO::FETCH_ASSOC);
        
        if ($exam) {
            $response['exam_name'] = $exam['examname'];
        }
    }
    
    // Get class details
    $sql_class = "SELECT academic_level, class_level FROM tblclasses WHERE id = ? AND school_id = ?";
    $stmt_class = $dbh->prepare($sql_class);
    $stmt_class->execute([$class_id, $school_id]);
    $class = $stmt_class->fetch(PDO::FETCH_ASSOC);
    
    if ($class) {
        $response['class_name'] = $class['class_level'] . ' (' . $class['academic_level'] . ')';
    }
    
    // Get stream name if stream_id is provided
    if ($stream_id > 0) {
        $sql_stream = "SELECT stream_name FROM tblstreams WHERE id = ? AND school_id = ?";
        $stmt_stream = $dbh->prepare($sql_stream);
        $stmt_stream->execute([$stream_id, $school_id]);
        $stream = $stmt_stream->fetch(PDO::FETCH_ASSOC);
        
        if ($stream) {
            $response['stream_name'] = $stream['stream_name'];
            $response['has_stream'] = true;
        }
    }
    
    $response['success'] = true;
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    error_log("Database error in get_student_count.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred',
        'details' => $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("General error in get_student_count.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred',
        'details' => $e->getMessage()
    ]);
}
?>