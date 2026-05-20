<?php
session_start();
header("Content-Type: application/json");
include('../includes/config.php');

$data = json_decode(file_get_contents("php://input"), true);
$assignmentId = $data['lesson_id'] ?? null;
$newTeacherId = $data['new_teacher_id'] ?? null;

if (!$assignmentId || !$newTeacherId) {
    echo json_encode(["success" => false, "message" => "Missing data."]);
    exit;
}

try {
    $stmt = $dbh->prepare("UPDATE tblsubjectassignments SET teacher_id = :teacher_id WHERE id = :assignment_id");
    $stmt->execute([
        ':teacher_id' => $newTeacherId,
        ':assignment_id' => $assignmentId
    ]);

    echo json_encode(["success" => true, "message" => "Assignment updated successfully."]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
}
