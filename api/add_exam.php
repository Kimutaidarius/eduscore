<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

include('../includes/config.php'); // Ensure this file defines $dbh (PDO connection)

$response = ["success" => false, "message" => ""];

// -------------------- AUTHENTICATION --------------------
if (
    !isset($_SESSION['id']) || empty($_SESSION['id']) ||
    !isset($_SESSION['login']) || empty($_SESSION['login']) ||
    !isset($_SESSION['school_id']) || empty($_SESSION['school_id'])
) {
    $response['message'] = 'Authentication required. Please log in.';
    http_response_code(401);
    echo json_encode($response);
    exit();
}

$school_id = (int) $_SESSION['school_id'];

// -------------------- HANDLE JSON INPUT --------------------
// Support both JSON and regular form POST
$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() === JSON_ERROR_NONE && is_array($input)) {
    $_POST = array_merge($_POST, $input);
}

// -------------------- SANITIZE INPUT --------------------
$examname = trim($_POST['exam_name'] ?? $_POST['examname'] ?? '');
$class_id = isset($_POST['class_id']) ? (int) $_POST['class_id'] : 0;
$stream_id = !empty($_POST['stream_id']) ? (int) $_POST['stream_id'] : null;

// -------------------- VALIDATION --------------------
if (empty($examname)) {
    $response['message'] = "Exam Name cannot be empty.";
    http_response_code(400);
    echo json_encode($response);
    exit();
}

if ($class_id === 0) {
    $response['message'] = "Class must be selected.";
    http_response_code(400);
    echo json_encode($response);
    exit();
}

// -------------------- DATABASE LOGIC --------------------
try {
    if (!isset($dbh) || !$dbh instanceof PDO) {
        throw new Exception("Database connection not available. Check config.php.");
    }

    // --- Check for duplicates ---
    $checkStmt = $dbh->prepare("
        SELECT COUNT(*) 
        FROM tblexam 
        WHERE examname = :examname 
        AND class_id = :class_id 
        AND school_id = :school_id 
        AND (stream_id = :stream_id_check OR (stream_id IS NULL AND :stream_id_check IS NULL))
    ");

    $checkStmt->bindParam(':examname', $examname, PDO::PARAM_STR);
    $checkStmt->bindParam(':class_id', $class_id, PDO::PARAM_INT);
    $checkStmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
    $checkStmt->bindValue(':stream_id_check', $stream_id, $stream_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $checkStmt->execute();

    if ($checkStmt->fetchColumn() > 0) {
        $response['message'] = "An exam with this name already exists for the selected class and stream (or no stream).";
        http_response_code(409);
        echo json_encode($response);
        exit();
    }

    // --- Insert the exam record ---
    $insertStmt = $dbh->prepare("
        INSERT INTO tblexam (school_id, examname, class_id, stream_id, DateAdded, status, last_updated)
        VALUES (:school_id, :examname, :class_id, :stream_id, CURDATE(), 'Active', CURRENT_TIMESTAMP())
    ");

    $insertStmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
    $insertStmt->bindParam(':examname', $examname, PDO::PARAM_STR);
    $insertStmt->bindParam(':class_id', $class_id, PDO::PARAM_INT);
    $insertStmt->bindValue(':stream_id', $stream_id, $stream_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $insertStmt->execute();

    $response['success'] = true;
    $response['message'] = "Exam added successfully!";
    http_response_code(201);
    echo json_encode($response);

} catch (PDOException $e) {
    error_log("Database Error in add_exam.php: " . $e->getMessage());
    $response['message'] = "Database error: " . $e->getMessage();
    http_response_code(500);
    echo json_encode($response);

} catch (Exception $e) {
    error_log("Application Error in add_exam.php: " . $e->getMessage());
    $response['message'] = "Error: " . $e->getMessage();
    http_response_code(500);
    echo json_encode($response);
}
?>
