<?php
session_start();
include('../includes/config.php'); // Adjusted path to config.php
header('Content-Type: application/json');

$response = ['status' => 'error', 'message' => 'Invalid request.', 'classes' => []];

$schoolId = $_SESSION['school_id'] ?? null;

if (empty($schoolId)) {
    $response['message'] = 'School ID not found in session.';
    echo json_encode($response);
    exit();
}

try {
    $sql = "SELECT id, class_level, academic_level FROM tblclasses WHERE school_id = :school_id ORDER BY academic_level ASC, class_level ASC";
    $query = $dbh->prepare($sql);
    $query->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
    $query->execute();
    $classes = $query->fetchAll(PDO::FETCH_ASSOC);

    if ($classes) {
        $response['status'] = 'success';
        $response['message'] = 'Classes fetched successfully.';
        $response['classes'] = $classes;
    } else {
        $response['status'] = 'success'; // Indicate success but no classes found
        $response['message'] = 'No classes found.';
    }
} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
    error_log("API Fetch Classes for Dropdown Error: " . $e->getMessage());
}

echo json_encode($response);
?>
