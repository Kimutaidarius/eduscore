<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include('../includes/config.php');
header('Content-Type: application/json');

$response = ["success" => false, "message" => ""];

if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['school_id'])) {
    $response["message"] = "Unauthorized access.";
    echo json_encode($response);
    exit;
}

$class_id = intval($_POST['class_id'] ?? 0);
$subject_id = intval($_POST['subject_id'] ?? 0);
$exam_id = intval($_POST['exam_id'] ?? 0);
$stream_id = intval($_POST['stream_id'] ?? 0);
$total_score = floatval($_POST['total_score'] ?? 0);

if ($class_id === 0 || $subject_id === 0 || $exam_id === 0) {
    $response["message"] = "Class, subject, and exam are required.";
    echo json_encode($response);
    exit;
}

try {
    $teacher_id = $_SESSION['teacher_id'];
    $school_id = $_SESSION['school_id'];
    
    // First, update existing scores for this combination
    $updateStmt = $dbh->prepare("
        UPDATE tblscores 
        SET total_score = :total_score 
        WHERE school_id = :school_id 
        AND class_id = :class_id 
        AND subject_id = :subject_id 
        AND exam_id = :exam_id 
        AND (StreamId = :stream_id OR StreamId = 0)
    ");
    
    $updateStmt->execute([
        ':total_score' => $total_score,
        ':school_id' => $school_id,
        ':class_id' => $class_id,
        ':subject_id' => $subject_id,
        ':exam_id' => $exam_id,
        ':stream_id' => $stream_id
    ]);
    
    $response["success"] = true;
    $response["message"] = "Exam total score updated to " . $total_score . " for all students.";
    
} catch (PDOException $e) {
    $response["message"] = "Database error: " . $e->getMessage();
    error_log("PDO Error update_exam_total.php: " . $e->getMessage());
}

echo json_encode($response);