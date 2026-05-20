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

// Get current logo path
$query = "SELECT school_logo FROM tblschoolinfo WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $school_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$old_logo = $row['school_logo'] ?? '';
$stmt->close();

// Delete file if it exists
if (!empty($old_logo) && file_exists('../' . $old_logo)) {
    unlink('../' . $old_logo);
}

// Update database to remove logo
$query = "UPDATE tblschoolinfo SET school_logo = NULL, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $school_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Logo deleted successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to delete logo from database']);
}

$stmt->close();
$conn->close();
?>