<?php
session_start(); // Start the session at the very beginning
error_reporting(E_ALL); // Enable all error reporting for debugging
ini_set('display_errors', 1); // Display errors directly for debugging (turn off in production)

require_once('../includes/config.php'); // Corrected path

header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => ''
];

$data = json_decode(file_get_contents('php://input'), true);

try {
    // --- CRITICAL: Validate and get school_id from session ---
    if (!isset($_SESSION['school_id']) || empty($_SESSION['school_id']) || !is_numeric($_SESSION['school_id'])) {
        throw new Exception("Security Error: School ID is missing or invalid in session. Please log in.");
    }
    $schoolId = intval($_SESSION['school_id']); // Get school_id from session

    $classId = isset($data['class_id']) ? intval($data['class_id']) : 0;
    $subjectId = isset($data['subject_id']) ? intval($data['subject_id']) : 0;
    $examId = isset($data['exam_id']) ? intval($data['exam_id']) : 0;
    $totalScore = isset($data['total_score']) ? floatval($data['total_score']) : 0;

    // From JS, stream_id will be 0 for "All Streams", or an actual ID.
    $streamId = isset($data['stream_id']) ? intval($data['stream_id']) : 0;

    if ($classId <= 0 || $subjectId <= 0 || $examId <= 0 || $totalScore <= 0) {
        throw new Exception("Invalid input: Class, Subject, Exam IDs or Total Score are missing or invalid.");
    }

    if (!isset($dbh)) {
        throw new Exception("Database connection not established. Check config.php.");
    }

    // --- DEBUGGING LOGS START ---
    error_log("--- set_exam_subject_total_score.php Debug ---");
    error_log("School ID from Session: " . $schoolId);
    error_log("Received class_id: " . $classId);
    error_log("Received subject_id: " . $subjectId);
    error_log("Received exam_id: " . $examId);
    error_log("Received total_score: " . $totalScore);
    error_log("Received stream_id: " . $streamId);
    // --- DEBUGGING LOGS END ---


    // Use INSERT ... ON DUPLICATE KEY UPDATE for tbl_exam_subject_total_scores
    // This ensures only one unique entry for each combination (exam, subject, class, stream, school)
    $sql = "INSERT INTO tbl_exam_subject_total_scores
            (exam_id, subject_id, class_id, stream_id, school_id, total_score_value) -- Added school_id
            VALUES (:exam_id, :subject_id, :class_id, :stream_id, :school_id, :total_score_value)
            ON DUPLICATE KEY UPDATE
            total_score_value = VALUES(total_score_value),
            last_updated = CURRENT_TIMESTAMP";

    $stmt = $dbh->prepare($sql);
    $stmt->bindParam(':exam_id', $examId, PDO::PARAM_INT);
    $stmt->bindParam(':subject_id', $subjectId, PDO::PARAM_INT);
    $stmt->bindParam(':class_id', $classId, PDO::PARAM_INT);
    $stmt->bindParam(':stream_id', $streamId, PDO::PARAM_INT); // Bind 0 or actual ID
    $stmt->bindParam(':school_id', $schoolId, PDO::PARAM_INT); // NEW: Bind school_id from session
    $stmt->bindParam(':total_score_value', $totalScore, PDO::PARAM_STR); // Use STR for float values to prevent precision issues

    $stmt->execute();

    $response['success'] = true;
    $response['message'] = "Total score for exam/subject updated successfully.";

} catch (PDOException $e) {
    $response['message'] = "Database Error: " . $e->getMessage();
    error_log("Database Error in set_exam_subject_total_score.php: " . $e->getMessage());
} catch (Exception $e) {
    $response['message'] = "Error: " . $e->getMessage();
    error_log("Application Error in set_exam_subject_total_score.php: " . $e->getMessage());
}

echo json_encode($response);
?><?php
session_start(); // Start the session at the very beginning
error_reporting(E_ALL); // Enable all error reporting for debugging
ini_set('display_errors', 1); // Display errors directly for debugging (turn off in production)

require_once('../includes/config.php'); // Corrected path

header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => ''
];

$data = json_decode(file_get_contents('php://input'), true);

try {
    // --- CRITICAL: Validate and get school_id from session ---
    if (!isset($_SESSION['school_id']) || empty($_SESSION['school_id']) || !is_numeric($_SESSION['school_id'])) {
        throw new Exception("Security Error: School ID is missing or invalid in session. Please log in.");
    }
    $schoolId = intval($_SESSION['school_id']); // Get school_id from session

    $classId = isset($data['class_id']) ? intval($data['class_id']) : 0;
    $subjectId = isset($data['subject_id']) ? intval($data['subject_id']) : 0;
    $examId = isset($data['exam_id']) ? intval($data['exam_id']) : 0;
    $totalScore = isset($data['total_score']) ? floatval($data['total_score']) : 0;

    // From JS, stream_id will be 0 for "All Streams", or an actual ID.
    $streamId = isset($data['stream_id']) ? intval($data['stream_id']) : 0;

    if ($classId <= 0 || $subjectId <= 0 || $examId <= 0 || $totalScore <= 0) {
        throw new Exception("Invalid input: Class, Subject, Exam IDs or Total Score are missing or invalid.");
    }

    if (!isset($dbh)) {
        throw new Exception("Database connection not established. Check config.php.");
    }

    // --- DEBUGGING LOGS START ---
    error_log("--- set_exam_subject_total_score.php Debug ---");
    error_log("School ID from Session: " . $schoolId);
    error_log("Received class_id: " . $classId);
    error_log("Received subject_id: " . $subjectId);
    error_log("Received exam_id: " . $examId);
    error_log("Received total_score: " . $totalScore);
    error_log("Received stream_id: " . $streamId);
    // --- DEBUGGING LOGS END ---


    // Use INSERT ... ON DUPLICATE KEY UPDATE for tbl_exam_subject_total_scores
    // This ensures only one unique entry for each combination (exam, subject, class, stream, school)
    $sql = "INSERT INTO tbl_exam_subject_total_scores
            (exam_id, subject_id, class_id, stream_id, school_id, total_score_value) -- Added school_id
            VALUES (:exam_id, :subject_id, :class_id, :stream_id, :school_id, :total_score_value)
            ON DUPLICATE KEY UPDATE
            total_score_value = VALUES(total_score_value),
            last_updated = CURRENT_TIMESTAMP";

    $stmt = $dbh->prepare($sql);
    $stmt->bindParam(':exam_id', $examId, PDO::PARAM_INT);
    $stmt->bindParam(':subject_id', $subjectId, PDO::PARAM_INT);
    $stmt->bindParam(':class_id', $classId, PDO::PARAM_INT);
    $stmt->bindParam(':stream_id', $streamId, PDO::PARAM_INT); // Bind 0 or actual ID
    $stmt->bindParam(':school_id', $schoolId, PDO::PARAM_INT); // NEW: Bind school_id from session
    $stmt->bindParam(':total_score_value', $totalScore, PDO::PARAM_STR); // Use STR for float values to prevent precision issues

    $stmt->execute();

    $response['success'] = true;
    $response['message'] = "Total score for exam/subject updated successfully.";

} catch (PDOException $e) {
    $response['message'] = "Database Error: " . $e->getMessage();
    error_log("Database Error in set_exam_subject_total_score.php: " . $e->getMessage());
} catch (Exception $e) {
    $response['message'] = "Error: " . $e->getMessage();
    error_log("Application Error in set_exam_subject_total_score.php: " . $e->getMessage());
}

echo json_encode($response);
?>