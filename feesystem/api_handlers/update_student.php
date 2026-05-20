<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database config
require_once '../../includes/config.php';

// Set JSON header
header('Content-Type: application/json');

// Check authentication
if (empty($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if (empty($_SESSION['school_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'School ID not found']);
    exit;
}

$school_id = $_SESSION['school_id'];

if (empty($_POST['student_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Student ID is required']);
    exit;
}

try {
    $db->beginTransaction();
    
    $student_id = $_POST['student_id'];
    
    // Check if student exists
    $stmt = $db->prepare("SELECT id, ProfilePic FROM tblstudents WHERE id = ? AND school_id = ?");
    $stmt->execute([$student_id, $school_id]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$existing) {
        echo json_encode(['status' => 'error', 'message' => 'Student not found']);
        exit;
    }
    
    // Handle photo upload
    $profile_pic = $existing['ProfilePic'];
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../../uploads/students/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($file_extension, $allowed_extensions)) {
            $new_filename = time() . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $_POST['admission_no']) . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_path)) {
                // Delete old photo if not default
                if ($profile_pic != 'default.png' && file_exists($upload_dir . $profile_pic)) {
                    unlink($upload_dir . $profile_pic);
                }
                $profile_pic = $new_filename;
            }
        }
    }
    
    // Fix: Use correct column name 'guardian_email'
    $sql = "UPDATE tblstudents SET 
                class_id = ?,
                FirstName = ?,
                SecondName = ?,
                LastName = ?,
                AdmNo = ?,
                admission_date = ?,
                Gender = ?,
                GuardianName = ?,
                GuardianRelationship = ?,
                GuardianPhone = ?,
                guardian_email = ?,
                ProfilePic = ?,
                StreamId = ?
            WHERE id = ? AND school_id = ?";
    
    $stmt = $db->prepare($sql);
    $result = $stmt->execute([
        $_POST['class_id'],
        $_POST['first_name'],
        $_POST['middle_name'] ?? '',
        $_POST['last_name'],
        $_POST['admission_no'],
        $_POST['admission_date'],
        $_POST['gender'],
        $_POST['guardian_name'] ?? '',
        $_POST['guardian_relation'] ?? '',
        $_POST['guardian_contact'] ?? '',
        $_POST['guardian_email'] ?? '',  // Fix: Use guardian_email column
        $profile_pic,
        !empty($_POST['stream_id']) ? $_POST['stream_id'] : null,
        $student_id,
        $school_id
    ]);
    
    if ($result) {
        $db->commit();
        echo json_encode([
            'status' => 'success',
            'message' => 'Student updated successfully'
        ]);
    } else {
        throw new Exception('Failed to update student');
    }
    
} catch (Exception $e) {
    $db->rollBack();
    error_log("Error updating student: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to update student: ' . $e->getMessage()
    ]);
}
?>