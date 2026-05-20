<?php
session_start();
require_once('../includes/config.php');
header('Content-Type: application/json');

$response = ['success' => false, 'message' => '', 'data' => []];

try {
    // Check school session
    if (!isset($_SESSION['school_id']) || empty($_SESSION['school_id'])) {
        throw new Exception("Unauthorized access. School ID not found in session.");
    }

    $school_id = intval($_SESSION['school_id']);

    // Validate class_id
    if (!isset($_GET['class_id']) || empty($_GET['class_id'])) {
        throw new Exception("Missing or invalid class_id");
    }

    $class_id = intval($_GET['class_id']);
    $stream_id = isset($_GET['stream_id']) && !empty($_GET['stream_id'])
        ? intval($_GET['stream_id'])
        : null;

    // ============================
    // Query Students by Class & Stream
    // ============================
    $query = "
        SELECT id, FirstName, SecondName, LastName 
        FROM tblstudents 
        WHERE class_id = :class_id 
        AND school_id = :school_id 
        AND Status = 'Active'
    ";

    if ($stream_id) {
        $query .= " AND StreamId = :stream_id";
    }

    $query .= " ORDER BY FirstName ASC";

    $stmt = $dbh->prepare($query);
    $stmt->bindParam(':class_id', $class_id, PDO::PARAM_INT);
    $stmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
    if ($stream_id) $stmt->bindParam(':stream_id', $stream_id, PDO::PARAM_INT);

    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ============================
    // Response
    // ============================
    $response['success'] = true;
    $response['data'] = $students;

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>

