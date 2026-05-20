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
    $input = json_decode(file_get_contents('php://input'), true);
    
    $id = $input['id'] ?? 0;
    $school_id = $input['school_id'] ?? $_SESSION['school_id'] ?? null;
    $department_id = $input['department_id'] ?? null;
    $staff_number = trim($input['staff_number'] ?? '');
    $title = trim($input['title'] ?? '');
    $first_name = trim($input['first_name'] ?? '');
    $middle_name = trim($input['middle_name'] ?? '');
    $last_name = trim($input['last_name'] ?? '');
    $gender = $input['gender'] ?? '';
    $phone_number = trim($input['phone_number'] ?? '');
    $id_number = trim($input['id_number'] ?? '');
    $bank = trim($input['bank'] ?? '');
    $account_number = trim($input['account_number'] ?? '');
    
    // Validate required fields
    if (!$id || !$school_id || !$department_id || !$staff_number || !$first_name || !$last_name || !$gender || !$phone_number || !$id_number || !$bank || !$account_number) {
        echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
        exit;
    }
    
    // Check if staff number already exists (excluding current)
    $checkStmt = $db->prepare("SELECT id FROM staff WHERE staff_number = ? AND school_id = ? AND id != ?");
    $checkStmt->execute([$staff_number, $school_id, $id]);
    if ($checkStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Staff number already exists']);
        exit;
    }
    
    // Check if ID number already exists (excluding current)
    $checkStmt = $db->prepare("SELECT id FROM staff WHERE id_number = ? AND school_id = ? AND id != ?");
    $checkStmt->execute([$id_number, $school_id, $id]);
    if ($checkStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'ID number already exists']);
        exit;
    }
    
    // Update staff
    $stmt = $db->prepare("UPDATE staff 
                           SET department_id = ?, staff_number = ?, title = ?, first_name = ?, 
                               middle_name = ?, last_name = ?, gender = ?, phone_number = ?, 
                               id_number = ?, bank = ?, account_number = ?, updated_at = NOW()
                           WHERE id = ? AND school_id = ?");
    
    $result = $stmt->execute([
        $department_id, $staff_number, $title, $first_name, $middle_name, $last_name,
        $gender, $phone_number, $id_number, $bank, $account_number, $id, $school_id
    ]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Staff member updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update staff member']);
    }
} catch (PDOException $e) {
    error_log("Database error in update_staff.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("General error in update_staff.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>