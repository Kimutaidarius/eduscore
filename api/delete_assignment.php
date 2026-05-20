<?php
session_start();
header("Content-Type: application/json");
include('../includes/config.php');

$data = json_decode(file_get_contents("php://input"), true);
$assignmentId = $data['lesson_id'] ?? null;

if (!$assignmentId) {
    echo json_encode(["success" => false, "message" => "Assignment ID is required."]);
    exit;
}

try {
    $stmt = $dbh->prepare("DELETE FROM tblsubjectassignments WHERE id = :id");
    $stmt->execute([':id' => $assignmentId]);

    echo json_encode(["success" => true, "message" => "Assignment deleted successfully."]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
}
