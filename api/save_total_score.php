<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include('../includes/config.php');
header('Content-Type: application/json');

$response = ["success" => false, "message" => ""];

$input = json_decode(file_get_contents('php://input'), true);

$class_id   = intval($input['class_id'] ?? 0);
$subject_id = intval($input['subject_id'] ?? 0);
$exam_id    = intval($input['exam_id'] ?? 0);
$total_score = floatval($input['total_score'] ?? 0);
$student_ids = $input['student_ids'] ?? []; // optional: specific students

if ($class_id === 0 || $subject_id === 0 || $exam_id === 0) {
    $response["message"] = "Class, subject, and exam are required.";
    echo json_encode($response);
    exit;
}

if ($total_score < 0) $total_score = 0; // default to 0 if negative


try {
    // Fetch students if not provided
    if (empty($student_ids)) {
        $stmt = $dbh->prepare("SELECT student_id FROM tblscores WHERE class_id = ? AND subject_id = ? AND exam_id = ?");
        $stmt->execute([$class_id, $subject_id, $exam_id]);
        $student_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

$stmt = $dbh->prepare("
    INSERT INTO tblscores 
        (school_id, student_id, subject_id, exam_id, class_id, StreamId, total_score, recorded_by_teacher_id)
    VALUES 
        (:school_id, :student_id, :subject_id, :exam_id, :class_id, :stream_id, :total_score, :teacher_id)
    ON DUPLICATE KEY UPDATE total_score = VALUES(total_score)
");

    $teacher_id = $_SESSION['teacher_id'] ?? 0;
    $school_id = $_SESSION['school_id'] ?? 0;
    $stream_id = $input['stream_id'] ?? 0;

    foreach ($student_ids as $student_id) {
        $stmt->execute([
            ':school_id' => $school_id,
            ':student_id' => $student_id,
            ':subject_id' => $subject_id,
            ':exam_id' => $exam_id,
            ':class_id' => $class_id,
            ':stream_id' => $stream_id,
            ':total_score' => $total_score,
            ':teacher_id' => $teacher_id
        ]);
    }

    $response["success"] = true;
    $response["message"] = "Total score updated for " . count($student_ids) . " students.";

} catch (PDOException $e) {
    $response["message"] = "Database error: " . $e->getMessage();
    error_log("PDO Error save_total_score.php: " . $e->getMessage());
}

echo json_encode($response);
