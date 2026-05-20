<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['school_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Session variables
$teacher_id = $_SESSION['teacher_id'];
$school_id = $_SESSION['school_id'];

// Database connection
require_once '../includes/config.php';

// Database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Get POST data
$action = $_POST['action'] ?? '';

if ($action !== 'update_details') {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit();
}

$school_name = $_POST['school_name'] ?? '';
$address = $_POST['address'] ?? '';
$email = $_POST['email'] ?? '';
$phone = $_POST['phone'] ?? '';
$motto = $_POST['motto'] ?? '';

// Validate required fields
if (empty($school_name)) {
    echo json_encode(['success' => false, 'message' => 'School name is required']);
    exit();
}

// Validate email if provided
if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit();
}

// Prepare update query - only update the fields that exist in the table
$query = "UPDATE tblschoolinfo SET 
          school_name = ?,
          school_address = ?,
          school_email = ?,
          school_phone = ?,
          school_motto = ?,
          updated_at = CURRENT_TIMESTAMP
          WHERE id = ?";

$stmt = $conn->prepare($query);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit();
}

$stmt->bind_param("sssssi", 
    $school_name, 
    $address, 
    $email, 
    $phone, 
    $motto, 
    $school_id
);

if ($stmt->execute()) {
    // Check if any rows were affected
    if ($stmt->affected_rows > 0 || $stmt->affected_rows == 0) {
        // affected_rows == 0 means no changes, but query was successful
        echo json_encode([
            'success' => true, 
            'message' => 'School details updated successfully',
            'data' => [
                'school_name' => $school_name,
                'address' => $address,
                'email' => $email,
                'phone' => $phone,
                'motto' => $motto
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No changes were made']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update school details: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>