<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['school_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$teacher_id = $_SESSION['teacher_id'];
$school_id = $_SESSION['school_id'];

require_once '../includes/config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

$class_level = isset($_POST['class_level']) ? trim($_POST['class_level']) : '';

if (empty($class_level)) {
    echo json_encode(['success' => false, 'message' => 'Class level required']);
    exit();
}

// Get streams for this class level
$sql = "SELECT DISTINCT 
            stream as stream_name,
            COUNT(*) as class_count
        FROM tblclasses 
        WHERE school_id = ? 
        AND class_level = ?
        AND stream IS NOT NULL 
        AND stream != ''
        GROUP BY stream
        ORDER BY stream";

$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $school_id, $class_level);
$stmt->execute();
$result = $stmt->get_result();

$streams = [];
while ($row = $result->fetch_assoc()) {
    $streams[] = [
        'name' => $row['stream_name'],
        'count' => $row['class_count']
    ];
}

$stmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'streams' => $streams,
    'count' => count($streams)
]);