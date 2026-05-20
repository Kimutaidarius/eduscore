<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include('../includes/config.php'); // PDO connection

header('Content-Type: application/json');

$response = ["success" => false, "exams" => [], "message" => ""];

// Check PDO connection
if (!isset($dbh) || !($dbh instanceof PDO)) {
    $response["message"] = "Database connection failed.";
    echo json_encode($response);
    exit();
}

// Get school_id from session
$school_id = $_SESSION['school_id'] ?? null;
if (!$school_id) {
    $response["message"] = "School not identified in session.";
    echo json_encode($response);
    exit();
}

// Validate class_id
$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
if ($class_id <= 0) {
    $response["message"] = "Invalid or missing class ID.";
    echo json_encode($response);
    exit();
}

try {
    $sql = "SELECT id, examname, DateAdded, status, class_id 
            FROM tblexam 
            WHERE class_id = :class_id 
              AND school_id = :school_id
            ORDER BY DateAdded DESC";
    $stmt = $dbh->prepare($sql);
    $stmt->bindValue(':class_id', $class_id, PDO::PARAM_INT);
    $stmt->bindValue(':school_id', $school_id, PDO::PARAM_INT);
    $stmt->execute();

    $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response["success"] = true;
    $response["exams"] = $exams;
    $response["message"] = count($exams) ? "Exams fetched successfully." : "No exams found for this class.";

} catch (PDOException $e) {
    $response["message"] = "Database error: " . $e->getMessage();
    error_log("PDO Error in get_exams_by_class.php: " . $e->getMessage());
}

echo json_encode($response);
exit();
?>
