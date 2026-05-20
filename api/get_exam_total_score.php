<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include('../includes/config.php');

header('Content-Type: application/json');

$response = ["success" => false, "total_score" => null, "message" => ""];

if (!isset($dbh) || !($dbh instanceof PDO)) {
    $response["message"] = "Database connection failed. Ensure PDO connection is established.";
    echo json_encode($response);
    exit();
}

$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;
$exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;

if ($class_id === 0 || $subject_id === 0 || $exam_id === 0) {
    $response["message"] = "Class ID, Subject ID, and Exam ID are required to fetch total score.";
    echo json_encode($response);
    exit();
}

try {
    // Queries the new tblexamsubject table for the total score
    $sql = "SELECT total_score FROM tblexamsubject WHERE class_id = ? AND subject_id = ? AND exam_id = ?";
    $stmt = $dbh->prepare($sql);
    $stmt->bindValue(1, $class_id, PDO::PARAM_INT);
    $stmt->bindValue(2, $subject_id, PDO::PARAM_INT);
    $stmt->bindValue(3, $exam_id, PDO::PARAM_INT);
    $stmt->execute();

    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        $response["success"] = true;
        $response["total_score"] = intval($result['total_score']);
        $response["message"] = "Total score fetched successfully.";
    } else {
        $response["success"] = true; // Still a success, but score not found
        $response["total_score"] = null;
        $response["message"] = "Total score not set for this combination.";
    }

} catch (PDOException $e) {
    $response["message"] = "Database error: " . $e->getMessage();
    error_log("PDO Error in api/get_exam_total_score.php: " . $e->getMessage());
} catch (Exception $e) {
    $response["message"] = "An unexpected error occurred: " . $e->getMessage();
    error_log("General Error in api/get_exam_total_score.php: " . $e->getMessage());
} finally {
    $stmt = null;
}

echo json_encode($response);
exit();<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include('../includes/config.php');

header('Content-Type: application/json');

$response = ["success" => false, "total_score" => null, "message" => ""];

if (!isset($dbh) || !($dbh instanceof PDO)) {
    $response["message"] = "Database connection failed. Ensure PDO connection is established.";
    echo json_encode($response);
    exit();
}

$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;
$exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;

if ($class_id === 0 || $subject_id === 0 || $exam_id === 0) {
    $response["message"] = "Class ID, Subject ID, and Exam ID are required to fetch total score.";
    echo json_encode($response);
    exit();
}

try {
    // Queries the new tblexamsubject table for the total score
    $sql = "SELECT total_score FROM tblexamsubject WHERE class_id = ? AND subject_id = ? AND exam_id = ?";
    $stmt = $dbh->prepare($sql);
    $stmt->bindValue(1, $class_id, PDO::PARAM_INT);
    $stmt->bindValue(2, $subject_id, PDO::PARAM_INT);
    $stmt->bindValue(3, $exam_id, PDO::PARAM_INT);
    $stmt->execute();

    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        $response["success"] = true;
        $response["total_score"] = intval($result['total_score']);
        $response["message"] = "Total score fetched successfully.";
    } else {
        $response["success"] = true; // Still a success, but score not found
        $response["total_score"] = null;
        $response["message"] = "Total score not set for this combination.";
    }

} catch (PDOException $e) {
    $response["message"] = "Database error: " . $e->getMessage();
    error_log("PDO Error in api/get_exam_total_score.php: " . $e->getMessage());
} catch (Exception $e) {
    $response["message"] = "An unexpected error occurred: " . $e->getMessage();
    error_log("General Error in api/get_exam_total_score.php: " . $e->getMessage());
} finally {
    $stmt = null;
}

echo json_encode($response);
exit();