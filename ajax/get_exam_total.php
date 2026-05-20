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

$teacher_id = $_SESSION['teacher_id'];
$school_id = $_SESSION['school_id'];

// Get POST data
$class_id = isset($_POST['class_id']) ? intval($_POST['class_id']) : 0;
$stream_id = isset($_POST['stream_id']) ? intval($_POST['stream_id']) : 0;
$subject_id = isset($_POST['subject_id']) ? intval($_POST['subject_id']) : 0;
$exam_id = isset($_POST['exam_id']) ? intval($_POST['exam_id']) : 0;

// Validate required fields
if (!$class_id || !$subject_id || !$exam_id) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
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
    $total_score = 0;
    
    // Get total_score from tblscores - prefer stream-specific records first
    $query = "SELECT total_score FROM tblscores 
              WHERE school_id = ? AND exam_id = ? AND subject_id = ? AND class_id = ?";
    $params = [$school_id, $exam_id, $subject_id, $class_id];
    $types = "iiii";
    
    if ($stream_id > 0) {
        // Try to get with exact stream match first
        $query .= " AND StreamId = ? LIMIT 1";
        $params[] = $stream_id;
        $types .= "i";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $total_score = floatval($row['total_score']);
        }
        $stmt->close();
        
        // If not found, try with stream_id = 0 or NULL
        if ($total_score == 0) {
            $fallback_query = "SELECT total_score FROM tblscores 
                              WHERE school_id = ? AND exam_id = ? AND subject_id = ? 
                                AND class_id = ? AND (StreamId = 0 OR StreamId IS NULL)
                              LIMIT 1";
            $fallback_stmt = $conn->prepare($fallback_query);
            $fallback_stmt->bind_param("iiii", $school_id, $exam_id, $subject_id, $class_id);
            $fallback_stmt->execute();
            $fallback_result = $fallback_stmt->get_result();
            
            if ($fallback_row = $fallback_result->fetch_assoc()) {
                $total_score = floatval($fallback_row['total_score']);
            }
            $fallback_stmt->close();
        }
    } else {
        // No stream specified, get any record
        $query .= " LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $total_score = floatval($row['total_score']);
        }
        $stmt->close();
    }
    
    echo json_encode([
        'success' => true,
        'total_score' => $total_score,
        'class_id' => $class_id,
        'stream_id' => $stream_id,
        'subject_id' => $subject_id,
        'exam_id' => $exam_id
    ]);

} catch (Exception $e) {
    error_log("Error in get_exam_total.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} finally {
    $conn->close();
}
?>