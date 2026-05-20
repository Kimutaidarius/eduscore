<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

if (!isset($_SESSION['school_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$school_id = $_SESSION['school_id'];

// Include config
require_once dirname(__DIR__) . '/includes/config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Fetch school information - UPDATED: Use correct column names from database
// From database: school_name, school_motto, school_address, school_logo, school_logo_url, school_phone, school_email
$query = "SELECT school_name, school_motto, school_address, school_logo, school_logo_url, school_phone, school_email FROM tblschoolinfo WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $school_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    // Determine the logo path
    $logo_path = 'uploads/logos/default.png'; // Default fallback
    
    // Check school_logo column first
    if (!empty($row['school_logo']) && $row['school_logo'] !== 'default.png' && file_exists('../uploads/logos/' . $row['school_logo'])) {
        $logo_path = 'uploads/logos/' . $row['school_logo'];
    } 
    // Check school_logo_url if school_logo is empty or default
    elseif (!empty($row['school_logo_url'])) {
        $logo_path = $row['school_logo_url'];
    }
    // If no valid logo, use default
    elseif (!empty($row['school_logo']) && $row['school_logo'] === 'default.png') {
        $logo_path = 'uploads/logos/default.png';
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'schoolname' => $row['school_name'] ?? 'School Name',
            'schoolmotto' => $row['school_motto'] ?? 'Excellence in Education',
            'address' => $row['school_address'] ?? '',
            'logo' => $logo_path,
            'phone_number' => $row['school_phone'] ?? '',  // Changed from 'phone_number' to 'school_phone'
            'email' => $row['school_email'] ?? ''  // Changed from 'email' to 'school_email'
        ]
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'School information not found',
        'data' => [
            'schoolname' => 'School Name',
            'schoolmotto' => 'Excellence in Education',
            'address' => '',
            'logo' => 'uploads/logos/default.png',
            'phone_number' => '',
            'email' => ''
        ]
    ]);
}

$stmt->close();
$conn->close();
exit();
?>