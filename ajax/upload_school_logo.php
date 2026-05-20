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

// Check if file was uploaded
if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
    $upload_errors = [
        UPLOAD_ERR_INI_SIZE => 'File exceeds maximum size (2MB)',
        UPLOAD_ERR_FORM_SIZE => 'File exceeds maximum size',
        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
    ];
    $error_code = $_FILES['logo']['error'] ?? UPLOAD_ERR_NO_FILE;
    $error_message = $upload_errors[$error_code] ?? 'Unknown upload error';
    echo json_encode(['success' => false, 'message' => 'Upload error: ' . $error_message]);
    exit();
}

$file = $_FILES['logo'];

// Validate file type
$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime_type, $allowed_types)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPEG, PNG, GIF, and WEBP are allowed.']);
    exit();
}

// Validate file size (max 2MB)
if ($file['size'] > 2 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'File too large. Maximum size is 2MB.']);
    exit();
}

// Create upload directory if it doesn't exist
$upload_dir = '../uploads/school_logos/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Generate unique filename
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'school_' . $school_id . '_' . time() . '.' . $extension;
$upload_path = $upload_dir . $filename;

// Move uploaded file
if (move_uploaded_file($file['tmp_name'], $upload_path)) {
    // Update database with logo path
    $logo_url = 'uploads/school_logos/' . $filename;
    
    // First, get the current logo to delete old file if exists
    $query = "SELECT school_logo FROM tblschoolinfo WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $school_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $old_logo = $result->fetch_assoc()['school_logo'];
    $stmt->close();
    
    // Delete old logo file if it exists and is not the default
    if (!empty($old_logo) && file_exists('../' . $old_logo)) {
        unlink('../' . $old_logo);
    }
    
    // Update database
    $query = "UPDATE tblschoolinfo SET school_logo = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $logo_url, $school_id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Logo uploaded successfully',
            'logo_path' => $logo_url
        ]);
    } else {
        // Delete uploaded file if database update fails
        unlink($upload_path);
        echo json_encode(['success' => false, 'message' => 'Failed to update database']);
    }
    
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file']);
}

$conn->close();
?>