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

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

$query = "SELECT id, class_level as display_name 
          FROM tblclasses 
          WHERE school_id = ? 
          ORDER BY class_level";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $school_id);
$stmt->execute();
$result = $stmt->get_result();

$classes = [];
while ($row = $result->fetch_assoc()) {
    $classes[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode(['success' => true, 'data' => $classes]);
?>