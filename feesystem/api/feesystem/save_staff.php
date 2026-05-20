<?php
session_start();
require_once('../../includes/config.php');
header('Content-Type: application/json');

// Check authentication
if (empty($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $school_id = $_POST['school_id'] ?? $_SESSION['school_id'] ?? null;
    $department_id = $_POST['department_id'] ?? null;
    $staff_number = trim($_POST['staff_number'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $phone_number = trim($_POST['phone_number'] ?? '');
    $id_number = trim($_POST['id_number'] ?? '');
    $bank = trim($_POST['bank'] ?? '');
    $account_number = trim($_POST['account_number'] ?? '');
    
    // Validate required fields
    if (!$school_id || !$department_id || !$staff_number || !$first_name || !$last_name || !$gender || !$phone_number || !$id_number || !$bank || !$account_number) {
        echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
        exit;
    }
    
    // Handle signature upload
    $signature_path = null;
    if (isset($_FILES['signature']) && $_FILES['signature']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../../uploads/signatures/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_ext = pathinfo($_FILES['signature']['name'], PATHINFO_EXTENSION);
        $signature_path = 'signature_' . time() . '_' . rand(1000, 9999) . '.' . $file_ext;
        $target_file = $upload_dir . $signature_path;
        
        if (!move_uploaded_file($_FILES['signature']['tmp_name'], $target_file)) {
            echo json_encode(['success' => false, 'message' => 'Failed to upload signature']);
            exit;
        }
    }
    
    // Check if staff number already exists
    $checkStmt = $db->prepare("SELECT id FROM staff WHERE staff_number = ? AND school_id = ?");
    $checkStmt->execute([$staff_number, $school_id]);
    if ($checkStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Staff number already exists']);
        exit;
    }
    
    // Check if ID number already exists
    $checkStmt = $db->prepare("SELECT id FROM staff WHERE id_number = ? AND school_id = ?");
    $checkStmt->execute([$id_number, $school_id]);
    if ($checkStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'ID number already exists']);
        exit;
    }
    
    // Insert staff
    $stmt = $db->prepare("INSERT INTO staff (school_id, department_id, staff_number, title, first_name, middle_name, last_name, gender, phone_number, id_number, bank, account_number, signature, created_at) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    
    $result = $stmt->execute([
        $school_id, $department_id, $staff_number, $title, $first_name, $middle_name, 
        $last_name, $gender, $phone_number, $id_number, $bank, $account_number, $signature_path
    ]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Staff member saved successfully', 'id' => $db->lastInsertId()]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save staff member']);
    }
} catch (PDOException $e) {
    error_log("Database error in save_staff.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("General error in save_staff.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>