<?php
session_start();
require_once '../includes/config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['school_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$teacher_id = $_SESSION['teacher_id'];
$school_id = $_SESSION['school_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$class_id = isset($_POST['class_id']) ? intval($_POST['class_id']) : 0;

if ($class_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid class ID']);
    exit();
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Fetch streams for the selected class
$query = $conn->prepare("
    SELECT id, stream_name 
    FROM tblstream 
    WHERE class_id = ? AND school_id = ?
    ORDER BY stream_name
");
$query->bind_param("ii", $class_id, $school_id);
$query->execute();
$result = $query->get_result();

$streams = [];
while ($row = $result->fetch_assoc()) {
    $streams[] = $row;
}

$query->close();
$conn->close();

echo json_encode([
    'success' => true,
    'data' => $streams
]);
?>