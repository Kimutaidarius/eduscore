<?php
session_start();
require_once('../includes/config.php');
header('Content-Type: application/json');

$response = ['success' => false, 'message' => '', 'data' => []];

if (!isset($_SESSION['school_id']) || empty($_SESSION['school_id'])) {
    $response['message'] = "Authentication required.";
    echo json_encode($response);
    exit();
}
$schoolId = intval($_SESSION['school_id']);
$classId = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;

if ($classId <= 0) {
    $response['message'] = "Invalid Class ID provided.";
    echo json_encode($response);
    exit();
}

try {
    $sql = "SELECT id, stream_name FROM tblstreams WHERE school_id = :school_id AND class_id = :class_id ORDER BY stream_name ASC";
    $query = $dbh->prepare($sql);
    $query->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
    $query->bindParam(':class_id', $classId, PDO::PARAM_INT);
    $query->execute();
    $streams = $query->fetchAll(PDO::FETCH_ASSOC);

    $response['success'] = true;
    $response['data'] = $streams;
} catch (PDOException $e) {
    $response['message'] = "Database Error: " . $e->getMessage();
    error_log("Error in get_streams_by_class.php: " . $e->getMessage());
} catch (Exception $e) {
    $response['message'] = "Error: " . $e->getMessage();
    error_log("Error in get_streams_by_class.php: " . $e->getMessage());
}
echo json_encode($response);
?>