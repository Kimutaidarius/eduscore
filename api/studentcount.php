<?php
header('Content-Type: application/json');

// Include the configuration file for database connection and sendResponse function
require_once '../includes/config.php';

// Check if the request method is GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(null, 'error', 'Invalid request method.');
}

// Check for required school_id parameter
if (!isset($_GET['school_id'])) {
    sendResponse(null, 'error', 'Missing school ID.');
}

$school_id = $_GET['school_id'];
$class_id = $_GET['class_id'] ?? null;   // Optional class_id filter
$stream_id = $_GET['stream_id'] ?? null; // Optional stream_id filter

// Get the PDO connection object
global $dbh;

// Base SQL query to count students
$sql = "SELECT COUNT(id) AS student_count FROM tblstudents WHERE school_id = ?";
$params = [$school_id];

// Add filters based on provided parameters
if ($class_id !== null && $stream_id !== null) {
    // If both class_id and stream_id are provided
    $sql .= " AND class_id = ? AND StreamId = ?"; // Assuming StreamId is the column name for stream ID in tblstudents
    $params[] = $class_id;
    $params[] = $stream_id;
} elseif ($class_id !== null) {
    // If only class_id is provided
    $sql .= " AND class_id = ?";
    $params[] = $class_id;
} elseif ($stream_id !== null) {
    // If only stream_id is provided
    $sql .= " AND StreamId = ?"; // Assuming StreamId is the column name for stream ID in tblstudents
    $params[] = $stream_id;
} else {
    // If neither class_id nor stream_id is provided, return an error or total students for the school
    // For this specific request (individual class/stream counts), we'll return 0 and a message.
    sendResponse(['student_count' => 0], 'error', 'Please provide a class ID or stream ID.');
}

try {
    $query = $dbh->prepare($sql);
    $query->execute($params);
    $result = $query->fetch(PDO::FETCH_ASSOC);
    $student_count = $result['student_count'];

    sendResponse(['student_count' => $student_count], 'success', 'Student count fetched successfully.');

} catch (PDOException $e) {
    // Log the error for internal debugging
    error_log("Error fetching student count: " . $e->getMessage());
    sendResponse(null, 'error', 'Technical error. Please try again later.');
}

?>