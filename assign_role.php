<?php
session_start();
require_once 'config/config.php';
require_once 'config/database.php';

header('Content-Type: application/json');

// Check if user is authenticated
if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if (!isset($_SESSION['school_id'])) {
    echo json_encode(['success' => false, 'message' => 'School ID not found']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get POST data
    $role_id = $_POST['role_id'] ?? '';
    $user_ids = $_POST['user_ids'] ?? [];
    
    if (empty($role_id)) {
        echo json_encode(['success' => false, 'message' => 'Role ID is required']);
        exit;
    }
    
    // Check if role exists and belongs to this school
    $check_query = "SELECT id FROM roles WHERE id = :role_id AND school_id = :school_id";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(":role_id", $role_id);
    $check_stmt->bindParam(":school_id", $_SESSION['school_id']);
    $check_stmt->execute();
    
    if (!$check_stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Role not found']);
        exit;
    }
    
    // Start transaction
    $db->beginTransaction();
    
    try {
        // Remove all current assignments for this role
        $delete_query = "DELETE FROM user_roles WHERE role_id = :role_id";
        $delete_stmt = $db->prepare($delete_query);
        $delete_stmt->bindParam(":role_id", $role_id);
        $delete_stmt->execute();
        
        // Assign role to selected users
        if (!empty($user_ids) && is_array($user_ids)) {
            foreach ($user_ids as $user_id) {
                // Check if user exists and belongs to this school
                $user_check_query = "SELECT id FROM tblteachers WHERE id = :user_id AND school_id = :school_id";
                $user_check_stmt = $db->prepare($user_check_query);
                $user_check_stmt->bindParam(":user_id", $user_id);
                $user_check_stmt->bindParam(":school_id", $_SESSION['school_id']);
                $user_check_stmt->execute();
                
                if ($user_check_stmt->fetch()) {
                    $assign_query = "INSERT INTO user_roles (teacher_id, role_id, assigned_at) VALUES (:user_id, :role_id, NOW())";
                    $assign_stmt = $db->prepare($assign_query);
                    $assign_stmt->bindParam(":user_id", $user_id);
                    $assign_stmt->bindParam(":role_id", $role_id);
                    $assign_stmt->execute();
                }
            }
        }
        
        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Role assigned successfully']);
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
    
} catch (PDOException $e) {
    error_log("Assign role error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>