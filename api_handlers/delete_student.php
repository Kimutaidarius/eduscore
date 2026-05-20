<?php
// Set headers to ensure the browser understands it's a JSON response
header('Content-Type: application/json');
// Enable error logging for debugging (errors will go to php_errors.log)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Crucial: Do NOT display errors directly in API response in production
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log'); // Adjust path to your error log file

// Include your database configuration/connection file
// Adjust the path to your config.php as necessary (e.g., if it's in a parent directory)
include('../includes/config.php'); 

// Check if the request method is POST (as your JavaScript sends it)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method. Only POST is allowed.']);
    exit();
}

// Get the raw POST data from the request body
$json_data = file_get_contents('php://input');

// Decode the JSON data into a PHP associative array
$data = json_decode($json_data, true); 

// Check for JSON decoding errors
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload received.']);
    exit();
}

// Extract the student_id from the decoded JSON data
// Use the null coalescing operator (??) for safety if 'student_id' might be missing
$studentId = $data['student_id'] ?? null; 

// Validate if studentId is received and is not empty
if (empty($studentId)) {
    echo json_encode(['success' => false, 'message' => 'Student ID is required for deletion.']);
    exit();
}

// --- If studentId is valid, proceed with your database deletion logic ---
try {
    // Prepare your SQL DELETE statement
    // IMPORTANT: Replace 'tblstudents' and 'student_pk_id' with your actual table and primary key column names
    $sql = "DELETE FROM tblstudents WHERE id = :student_id"; // Changed 'student_pk_id' to 'id'
    $query = $dbh->prepare($sql);
    
    // Bind the studentId parameter to prevent SQL injection
    // Assuming student_pk_id is an integer in your database
    $query->bindParam(':student_id', $studentId, PDO::PARAM_INT); 
    
    // Execute the query
    $query->execute();

    // Check if any rows were affected (i.e., a student was deleted)
    if ($query->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Student record deleted successfully.']);
    } else {
        // If rowCount is 0, it means no student matched the ID or no deletion occurred
        echo json_encode(['success' => false, 'message' => 'Student not found or could not be deleted.']);
    }

} catch (PDOException $e) {
    // Catch any database-related errors
    // Log the error for internal debugging, but do NOT expose sensitive database error messages to the frontend
    error_log("Database error deleting student (ID: {$studentId}): " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An internal server error occurred. Please try again later.']);
}

?>