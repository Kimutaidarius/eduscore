<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include('../includes/config.php');

header('Content-Type: application/json');

$response = ["success" => false, "subjects" => [], "message" => ""];

if (!isset($dbh) || !($dbh instanceof PDO)) {
    $response["message"] = "Database connection failed. Ensure PDO connection is established.";
    echo json_encode($response);
    exit();
}

$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$teacher_id = isset($_GET['teacher_id']) ? intval($_GET['teacher_id']) : 0; // Assuming teacher_id is integer

if ($class_id === 0 || $teacher_id === 0) {
    $response["message"] = "Class ID and Teacher ID are required.";
    echo json_encode($response);
    exit();
}

try {
    // Query tblsubjects directly, filtering by class_id and teacher_id
    $sql = "SELECT id, subject_name FROM tblsubjects WHERE class_id = ? AND teacher_id = ? ORDER BY subject_name ASC";
    $stmt = $dbh->prepare($sql);
    $stmt->bindValue(1, $class_id, PDO::PARAM_INT);
    $stmt->bindValue(2, $teacher_id, PDO::PARAM_INT);
    $stmt->execute();

    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($stmt->rowCount() > 0) {
        $response["success"] = true;
        $response["subjects"] = $subjects;
        $response["message"] = "Subjects fetched successfully.";
    } else {
        $response["success"] = true; // Still a success, just no data
        $response["message"] = "No subjects assigned to this teacher for this class.";
        $response["subjects"] = [];
    }

} catch (PDOException $e) {
    $response["message"] = "Database error: " . $e->getMessage();
    error_log("PDO Error in api/get_teacher_subjects.php: " . $e->getMessage());
} catch (Exception $e) {
    $response["message"] = "An unexpected error occurred: " . $e->getMessage();
    error_log("General Error in api/get_teacher_subjects.php: " . $e->getMessage());
} finally {
    $stmt = null;
}

echo json_encode($response);
exit();