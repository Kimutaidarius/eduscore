<?php
session_start();
require_once '../includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['school_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$school_id = $_SESSION['school_id'];

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

$query = "SELECT id, term_name, term_number, academic_year 
          FROM tblterms 
          WHERE school_id = ? 
          ORDER BY academic_year DESC, term_number";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $school_id);
$stmt->execute();
$result = $stmt->get_result();

$terms = [];
while ($row = $result->fetch_assoc()) {
    $terms[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode(['success' => true, 'data' => $terms]);
?>