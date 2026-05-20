<?php
session_start();
require_once '../includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['school_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$school_id = $_SESSION['school_id'];
$data = json_decode(file_get_contents('php://input'), true);

$class_id = isset($data['class_id']) ? intval($data['class_id']) : 0;
$stream_id = isset($data['stream_id']) ? intval($data['stream_id']) : 0;

if ($class_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Class ID is required']);
    exit();
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Build query based on stream selection
if ($stream_id > 0) {
    $query = "SELECT id, AdmNo as admission_no, 
                     CONCAT(FirstName, ' ', LastName) as full_name,
                     StreamId as stream_id
              FROM tblstudents 
              WHERE school_id = ? AND class_id = ? AND StreamId = ? 
              AND Status = 'Active'
              ORDER BY AdmNo";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $school_id, $class_id, $stream_id);
} else {
    $query = "SELECT id, AdmNo as admission_no, 
                     CONCAT(FirstName, ' ', LastName) as full_name,
                     StreamId as stream_id
              FROM tblstudents 
              WHERE school_id = ? AND class_id = ? 
              AND Status = 'Active'
              ORDER BY AdmNo";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $school_id, $class_id);
}

$stmt->execute();
$result = $stmt->get_result();

$students = [];
while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}

$stmt->close();
$conn->close();

if (empty($students)) {
    echo json_encode(['success' => false, 'message' => 'No students found']);
} else {
    echo json_encode(['success' => true, 'data' => $students]);
}
?>