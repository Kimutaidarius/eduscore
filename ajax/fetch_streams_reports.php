<?php
session_start();
require_once '../includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['school_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$school_id = $_SESSION['school_id'];
$class_id = isset($_POST['class_id']) ? intval($_POST['class_id']) : 0;

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

if ($class_id > 0) {
    $query = "SELECT id, stream_name 
              FROM tblstreams 
              WHERE school_id = ? AND class_id = ? 
              ORDER BY stream_name";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $school_id, $class_id);
} else {
    $query = "SELECT id, stream_name 
              FROM tblstreams 
              WHERE school_id = ? 
              ORDER BY stream_name";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $school_id);
}

$stmt->execute();
$result = $stmt->get_result();

$streams = [];
while ($row = $result->fetch_assoc()) {
    $streams[] = $row;
}

$stmt->close();
$conn->close();

if (empty($streams)) {
    echo json_encode(['success' => false, 'message' => 'No streams found']);
} else {
    echo json_encode(['success' => true, 'data' => $streams]);
}
?>