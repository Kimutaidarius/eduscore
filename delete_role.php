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
    
    // Check if role is assigned to any users
    $user_check_query = "SELECT id FROM user_roles WHERE role_id = :role_id";
    $user_check_stmt = $db->prepare($user_check_query);
    $user_check_stmt->bindParam(":role_id", $role_id);
    $user_check_stmt->execute();
    
    if ($user_check_stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete role that is assigned to users. Please unassign users first.']);
        exit;
    }
    
    // Start transaction
    $db->beginTransaction();
    
    try {
        // Delete role permissions
        $delete_perm_query = "DELETE FROM role_permissions WHERE role_id = :role_id AND school_id = :school_id";
        $delete_perm_stmt = $db->prepare($delete_perm_query);
        $delete_perm_stmt->bindParam(":role_id", $role_id);
        $delete_perm_stmt->bindParam(":school_id", $_SESSION['school_id']);
        $delete_perm_stmt->execute();
        
        // Delete role
        $delete_role_query = "DELETE FROM roles WHERE id = :role_id AND school_id = :school_id";
        $delete_role_stmt = $db->prepare($delete_role_query);
        $delete_role_stmt->bindParam(":role_id", $role_id);
        $delete_role_stmt->bindParam(":school_id", $_SESSION['school_id']);
        $delete_role_stmt->execute();
        
        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Role deleted successfully']);
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
    
} catch (PDOException $e) {
    error_log("Delete role error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>