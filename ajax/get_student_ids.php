<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set CORS headers
header('Access-Control-Allow-Origin: https://eduscore.ct.ws');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['school_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get POST data
$class_id = $_POST['class_id'] ?? 0;
$stream_id = $_POST['stream_id'] ?? 0;
$exam_id = $_POST['exam_id'] ?? 0;
$school_id = $_POST['school_id'] ?? $_SESSION['school_id'];

if (!$class_id || !$exam_id) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

// Database connection
require_once '../includes/config.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Get students who have scores for this exam
$query = "
    SELECT DISTINCT s.id
    FROM tblstudents s
    JOIN tblscores sc ON s.id = sc.student_id
    WHERE s.class_id = ? 
        AND sc.exam_id = ?
        AND s.school_id = ?
";

if ($stream_id > 0) {
    $query .= " AND s.StreamId = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iiii", $class_id, $exam_id, $school_id, $stream_id);
} else {
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $class_id, $exam_id, $school_id);
}

$stmt->execute();
$result = $stmt->get_result();

$student_ids = [];
while ($row = $result->fetch_assoc()) {
    $student_ids[] = (int)$row['id'];
}

$stmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'student_ids' => $student_ids,
    'total' => count($student_ids)
]);
?>