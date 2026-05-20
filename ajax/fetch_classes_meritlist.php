<?php
session_start();
require_once '../includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['school_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$school_id = $_SESSION['school_id'];
$class_id  = $_POST['class_id'] ?? 0;

if (!$class_id) {
    echo json_encode(['success' => false, 'message' => 'Class ID is required']);
    exit();
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

/*
   Fetch exams assigned to selected class
*/
$query = "
    SELECT 
        id,
        examname,
        DateAdded,
        status,
        deadline_date
    FROM tblexam
    WHERE school_id = ?
      AND class_id = ?
      AND status = 'Active'
    ORDER BY DateAdded DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $school_id, $class_id);
$stmt->execute();
$result = $stmt->get_result();

$exams = [];
while ($row = $result->fetch_assoc()) {
    $exams[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'data' => $exams,
    'count' => count($exams)
]);
?>