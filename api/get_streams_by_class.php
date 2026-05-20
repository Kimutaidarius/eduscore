<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include('../includes/config.php');

header('Content-Type: application/json');

$response = ["success" => false, "streams" => [], "message" => ""];

if (!isset($dbh) || !($dbh instanceof PDO)) {
    $response["message"] = "Database connection failed. Ensure PDO connection is established.";
    echo json_encode($response);
    exit();
}

$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;

if ($class_id === 0) {
    $response["message"] = "Invalid or missing class ID.";
    echo json_encode($response);
    exit();
}

try {
    $sql = "SELECT id, stream_name FROM tblstreams WHERE class_id = ? ORDER BY stream_name ASC";
    $stmt = $dbh->prepare($sql);
    $stmt->bindValue(1, $class_id, PDO::PARAM_INT);
    $stmt->execute();

    $streams = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($stmt->rowCount() > 0) {
        $response["success"] = true;
        $response["streams"] = $streams;
        $response["message"] = "Streams fetched successfully.";
    } else {
        $response["success"] = true;
        $response["message"] = "No streams found for this class.";
        $response["streams"] = [];
    }

} catch (PDOException $e) {
    $response["message"] = "Database error: " . $e->getMessage();
    error_log("PDO Error in api/get_streams_by_class.php: " . $e->getMessage());
} catch (Exception $e) {
    $response["message"] = "An unexpected error occurred: " . $e->getMessage();
    error_log("General Error in api/get_streams_by_class.php: " . $e->getMessage());
} finally {
    $stmt = null;
}

echo json_encode($response);
exit();