<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include('../includes/config.php');

header('Content-Type: application/json');

$response = ["success" => false, "message" => ""];

if (!isset($dbh) || !($dbh instanceof PDO)) {
    $response["message"] = "Database connection failed. Ensure PDO connection is established.";
    echo json_encode($response);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

$class_id = isset($input['class_id']) ? intval($input['class_id']) : 0;
$stream_id = isset($input['stream_id']) ? intval($input['stream_id']) : null; // Can be null
$subject_id = isset($input['subject_id']) ? intval($input['subject_id']) : 0;
$exam_id = isset($input['exam_id']) ? intval($input['exam_id']) : 0;
$teacher_id = isset($input['teacher_id']) ? intval($input['teacher_id']) : 0;
$total_score_exam = isset($input['total_score']) ? intval($input['total_score']) : 0;
$scores_to_save = isset($input['scores']) ? $input['scores'] : [];

if ($class_id === 0 || $subject_id === 0 || $exam_id === 0 || $teacher_id === 0 || $total_score_exam <= 0) {
    $response["message"] = "Missing required parameters (Class, Subject, Exam, Teacher, or Total Score).";
    echo json_encode($response);
    exit();
}

if (empty($scores_to_save)) {
    $response["message"] = "No scores provided to save.";
    $response["success"] = true; // Technically a success if nothing needed saving
    echo json_encode($response);
    exit();
}

try {
    // Prepare for batch insert/update using ON DUPLICATE KEY UPDATE
    $sql = "INSERT INTO tblresults (student_id, class_id, stream_id, subject_id, exam_id, teacher_id, score, total_score_exam)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                score = VALUES(score),
                total_score_exam = VALUES(total_score_exam),
                date_recorded = CURRENT_TIMESTAMP"; // Update timestamp on score change

    $stmt = $dbh->prepare($sql);
    $dbh->beginTransaction(); // Start transaction for atomicity

    foreach ($scores_to_save as $score_entry) {
        $student_id = intval($score_entry['student_id']);
        // Ensure score is int or null. Convert empty string to null if needed from JS.
        $score = ($score_entry['score'] === null || $score_entry['score'] === '') ? null : intval($score_entry['score']);
        
        // Validate score against total_score_exam from frontend (and potentially re-validate with backend's true max score if needed)
        if ($score !== null && ($score < 0 || $score > $total_score_exam)) {
            throw new Exception("Invalid score for student ID {$student_id}. Score must be between 0 and {$total_score_exam}.");
        }

        // Bind parameters
        $stmt->bindValue(1, $student_id, PDO::PARAM_INT);
        $stmt->bindValue(2, $class_id, PDO::PARAM_INT);
        $stmt->bindValue(3, $stream_id, PDO::PARAM_INT); // PDO handles null for PARAM_INT
        $stmt->bindValue(4, $subject_id, PDO::PARAM_INT);
        $stmt->bindValue(5, $exam_id, PDO::PARAM_INT);
        $stmt->bindValue(6, $teacher_id, PDO::PARAM_INT);
        $stmt->bindValue(7, $score, ($score === null ? PDO::PARAM_NULL : PDO::PARAM_INT));
        $stmt->bindValue(8, $total_score_exam, PDO::PARAM_INT);
        
        $stmt->execute();
    }

    $dbh->commit(); // Commit transaction
    $response["success"] = true;
    $response["message"] = "All scores saved successfully!";

} catch (PDOException $e) {
    $dbh->rollBack(); // Rollback on error
    $response["message"] = "Database error: " . $e->getMessage();
    error_log("PDO Error in api/save_student_scores.php: " . $e->getMessage());
} catch (Exception $e) {
    $dbh->rollBack(); // Rollback on error
    $response["message"] = "An unexpected error occurred: " . $e->getMessage();
    error_log("General Error in api/save_student_scores.php: " . $e->getMessage());
} finally {
    $stmt = null;
}

echo json_encode($response);
exit();