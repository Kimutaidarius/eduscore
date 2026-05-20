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
$class_id = $_GET['class_id'] ?? null; // Optional class_id filter
$academic_level = $_GET['academic_level'] ?? null; // Optional academic_level filter

// Get the PDO connection object
global $dbh;

// Base SQL query to join tblstreams with tblclasses to get class_level and academic_level
$sql = "SELECT s.id, s.stream_name, s.class_id, c.class_level, c.academic_level 
        FROM tblstreams s
        JOIN tblclasses c ON s.class_id = c.id
        WHERE s.school_id = ?";
$params = [$school_id];

// Add class_id filter if provided
if ($class_id) {
    $sql .= " AND s.class_id = ?";
    $params[] = $class_id;
}

// Add academic_level filter if provided
if ($academic_level) {
    $sql .= " AND c.academic_level = ?";
    $params[] = $academic_level;
}

$sql .= " ORDER BY c.academic_level, c.class_level, s.stream_name";

try {
    $query = $dbh->prepare($sql);
    $query->execute($params);
    $streams = $query->fetchAll(PDO::FETCH_ASSOC);

    if ($query->rowCount() > 0) {
        sendResponse(['streams' => $streams], 'success', 'Streams fetched successfully.');
    } else {
        // If no streams are found, still send a success status but with an empty array
        sendResponse(['streams' => []], 'success', 'No streams found for the selected criteria.');
    }

} catch (PDOException $e) {
    // Log the error for internal debugging
    error_log("Error fetching streams: " . $e->getMessage());
    sendResponse(null, 'error', 'Technical error. Please try again later.');
}

?>