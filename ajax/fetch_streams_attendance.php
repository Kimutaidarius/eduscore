<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['school_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

require_once '../includes/config.php';

$school_id = $_SESSION['school_id'];
$class_id = $_POST['class_id'] ?? 0;

if (!$class_id) {
    echo json_encode(['success' => false, 'message' => 'Class ID required']);
    exit();
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Fetch streams for the selected class - using stream column
$query = "SELECT id, stream as stream_name FROM tblclasses WHERE school_id = ? AND id = ? AND stream IS NOT NULL AND stream != ''";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $school_id, $class_id);
$stmt->execute();
$result = $stmt->get_result();

$streams = [];
while ($row = $result->fetch_assoc()) {
    $streams[] = [
        'id' => $row['id'],
        'stream_name' => $row['stream_name']
    ];
}

// If no stream found, return empty array
echo json_encode(['success' => true, 'data' => $streams]);

$stmt->close();
$conn->close();
?>