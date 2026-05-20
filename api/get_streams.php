<?php
// api/get_streams.php
session_start();
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

include('../includes/config.php'); // adjust path

$response = ['success'=>false, 'message'=>'', 'streams'=>[]];

// Check login
if (empty($_SESSION['id']) || empty($_SESSION['school_id'])) {
    $response['message'] = 'Authentication required. Please log in.';
    echo json_encode($response);
    exit();
}

$schoolId = $_SESSION['school_id'];
$classId = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;

if ($classId <= 0) {
    $response['message'] = 'Invalid Class ID provided.';
    echo json_encode($response);
    exit();
}

try {
    // Fetch streams for the class
    $sql = "SELECT id, stream_name 
            FROM tblstreams 
            WHERE class_id=:classId AND school_id=:schoolId 
            ORDER BY stream_name ASC";
    $stmt = $dbh->prepare($sql);
    $stmt->bindParam(':classId', $classId, PDO::PARAM_INT);
    $stmt->bindParam(':schoolId', $schoolId, PDO::PARAM_INT);
    $stmt->execute();
    $streams = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($streams) {
        $response['success'] = true;
        $response['streams'] = $streams;
    } else {
        $response['message'] = 'No streams found for this class.';
    }

} catch(PDOException $e) {
    error_log("get_streams.php error: ".$e->getMessage());
    $response['message'] = 'Database error during stream retrieval. Please try again later.';
}

echo json_encode($response);
