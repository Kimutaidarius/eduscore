<?php
// F:\xampp\htdocs\school result PHP\srms\api_handlers\getStreamsByClass.php

// Include the config file to get $dbh (database connection) and sendResponse() function
require_once __DIR__ . '/../includes/config.php';

// Assuming schoolId is set in the session after a successful login
if (!isset($_SESSION['school_id'])) {
    sendResponse([], 'error', 'Authentication required. School ID not found in session.');
}
$schoolId = $_SESSION['school_id'];

// Your existing logic for fetching streams will go here, for example:
if (!isset($_GET['class_id']) || empty($_GET['class_id'])) {
    sendResponse([], 'error', 'Invalid class ID specified.');
}
$classId = $_GET['class_id'];

try {
    if (!isset($dbh) || !$dbh instanceof PDO) {
        error_log("Database handle (dbh) not properly established in getStreamsByClass.php");
        sendResponse([], 'error', 'Server configuration error: Database connection not available.');
    }

    // Adjust your query based on your actual database schema for streams
    // Assuming your streams table is 'tblstreams'
    // and it has columns 'id', 'stream_name', and a foreign key 'class_id'
    $stmt = $dbh->prepare("SELECT id, stream_name AS name FROM tblstreams WHERE class_id = :class_id AND school_id = :school_id ORDER BY stream_name ASC");
    $stmt->bindParam(':class_id', $classId, PDO::PARAM_INT);
    $stmt->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
    $stmt->execute();
    $streams = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse($streams, 'success', 'Streams fetched successfully.');

} catch (PDOException $e) {
    error_log("PDO Error fetching streams by class: " . $e->getMessage());
    sendResponse([], 'error', 'Database error: ' . $e->getMessage());
} catch (Exception $e) {
    error_log("General Error fetching streams by class: " . $e->getMessage());
    sendResponse([], 'error', 'An unexpected server error occurred: ' . $e->getMessage());
}
// `exit;` is typically not needed here if sendResponse() calls exit().
?>