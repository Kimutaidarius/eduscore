<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

include('../includes/config.php'); // Ensure $dbh (PDO) is available

$response = ["success" => false, "message" => ""];

// --- AUTH CHECK ---
if (
    !isset($_SESSION['id']) || empty($_SESSION['id']) ||
    !isset($_SESSION['login']) || empty($_SESSION['login']) ||
    !isset($_SESSION['school_id']) || empty($_SESSION['school_id'])
) {
    http_response_code(401);
    $response['message'] = 'Authentication required. Please log in.';
    echo json_encode($response);
    exit;
}

$school_id = (int) $_SESSION['school_id'];
$exam_id = isset($_POST['exam_id']) ? (int) $_POST['exam_id'] : 0;
$deadline_date = isset($_POST['deadline_date']) ? trim($_POST['deadline_date']) : '';

if ($exam_id === 0 || empty($deadline_date)) {
    http_response_code(400);
    $response['message'] = 'Exam ID and deadline date are required.';
    echo json_encode($response);
    exit;
}

try {
    if (!isset($dbh)) {
        throw new Exception("Database connection not available.");
    }

    // --- Check exam ownership ---
    $checkSql = "SELECT id FROM tblexam WHERE id = :exam_id AND school_id = :school_id";
    $checkStmt = $dbh->prepare($checkSql);
    $checkStmt->bindParam(':exam_id', $exam_id, PDO::PARAM_INT);
    $checkStmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
    $checkStmt->execute();

    if ($checkStmt->rowCount() === 0) {
        http_response_code(403);
        $response['message'] = 'You are not authorized to modify this exam.';
        echo json_encode($response);
        exit;
    }

    // --- Update exam deadline ---
    $updateSql = "
        UPDATE tblexam 
        SET deadline_date = :deadline_date,
            last_updated = CURRENT_TIMESTAMP 
        WHERE id = :exam_id AND school_id = :school_id
    ";
    $updateStmt = $dbh->prepare($updateSql);
    $updateStmt->bindParam(':deadline_date', $deadline_date, PDO::PARAM_STR);
    $updateStmt->bindParam(':exam_id', $exam_id, PDO::PARAM_INT);
    $updateStmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);

    if ($updateStmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Deadline set successfully.';
    } else {
        http_response_code(500);
        $response['message'] = 'Failed to update exam deadline.';
    }

    echo json_encode($response);

} catch (PDOException $e) {
    error_log("DB Error (set_deadline.php): " . $e->getMessage());
    http_response_code(500);
    $response['message'] = 'Database error occurred while setting the deadline.';
    echo json_encode($response);
} catch (Exception $e) {
    error_log("General Error (set_deadline.php): " . $e->getMessage());
    http_response_code(500);
    $response['message'] = 'Unexpected application error occurred.';
    echo json_encode($response);
}
?>
