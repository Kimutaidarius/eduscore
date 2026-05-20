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

$class_id = $input['class_id'] ?? null;
$stream_id = $input['stream_id'] ?? 0;
$exam_id = $input['exam_id'] ?? null;
$school_id = $_SESSION['school_id'];

if (!$class_id || !$exam_id) {
    echo json_encode(['success' => false, 'message' => 'Class ID and Exam ID are required']);
    exit;
}

try {
    // Get students for this class/stream
    $query = "SELECT s.id, CONCAT(s.FirstName, ' ', s.LastName) as full_name, 
                     s.AdmNo as admission_no, s.Gender as gender
              FROM tblstudents s
              WHERE s.class_id = :class_id 
              AND s.school_id = :school_id 
              AND s.Status = 'Active'";
    
    if ($stream_id > 0) {
        $query .= " AND s.StreamId = :stream_id";
    }
    
    $query .= " ORDER BY s.FirstName, s.LastName";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':class_id', $class_id, PDO::PARAM_INT);
    $stmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
    
    if ($stream_id > 0) {
        $stmt->bindParam(':stream_id', $stream_id, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'students' => $students,
        'total' => count($students)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to get students: ' . $e->getMessage()
    ]);
}
?>