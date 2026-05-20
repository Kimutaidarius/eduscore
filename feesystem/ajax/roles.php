<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../includes/config.php';

// Check authentication
if (empty($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$school_id = $_SESSION['school_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Default roles that should exist for every school
$DEFAULT_ROLES = ['Super Admin', 'Teacher', 'ICT Teacher'];

try {
    switch ($action) {
        case 'fetch_roles':
            // First, ensure default roles exist for this school
            ensureDefaultRolesExist($db, $school_id);
            
            // Get all roles from tblroles table for this school
            $sql = "SELECT r.id, r.role_name, r.description, r.is_protected,
                           (SELECT COUNT(*) FROM tblteachers WHERE role_id = r.id AND school_id = r.school_id AND is_deleted = 0) as teacher_count
                    FROM tblroles r
                    WHERE r.school_id = :school_id
                    ORDER BY FIELD(r.role_name, 'Super Admin', 'ICT Teacher', 'Teacher'), r.id ASC";
            $stmt = $db->prepare($sql);
            $stmt->execute([':school_id' => $school_id]);
            $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get permissions for each role from tblrole_permissions
            $permStmt = $db->prepare("SELECT p.permission_key, p.description 
                                       FROM tblrole_permissions rp 
                                       JOIN tblpermissions p ON rp.permission_id = p.id
                                       WHERE rp.role_name = :role_name AND rp.school_id = :school_id");
            
            foreach ($roles as &$role) {
                $permStmt->execute([
                    ':role_name' => $role['role_name'],
                    ':school_id' => $school_id
                ]);
                $permissions = $permStmt->fetchAll(PDO::FETCH_ASSOC);
                $role['permissions'] = $permissions;
                $role['permission_count'] = count($permissions);
                $role['has_all_permissions'] = ($role['role_name'] === 'Super Admin');
            }
            
            echo json_encode(['success' => true, 'data' => $roles]);
            break;
            
        case 'get_teacher_roles':
            // Get distinct roles for teacher filter
            $sql = "SELECT DISTINCT role_name FROM tblroles WHERE school_id = :school_id AND role_name IS NOT NULL AND role_name != ''";
            $stmt = $db->prepare($sql);
            $stmt->execute([':school_id' => $school_id]);
            $roles = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo json_encode(['success' => true, 'data' => $roles]);
            break;
            
        case 'get_role_permissions':
            $role_name = $_GET['role_name'] ?? '';
            if (!$role_name) {
                echo json_encode(['success' => false, 'message' => 'Role name required']);
                break;
            }
            
            // Check if Super Admin
            $isSuperAdmin = ($role_name === 'Super Admin');
            
            // If Super Admin, return all permissions as true
            if ($isSuperAdmin) {
                // Get all permission keys
                $allPermStmt = $db->prepare("SELECT permission_key FROM tblpermissions");
                $allPermStmt->execute();
                $allPermissionKeys = $allPermStmt->fetchAll(PDO::FETCH_COLUMN);
                
                $permissions = [];
                foreach ($allPermissionKeys as $key) {
                    $permissions[$key] = true;
                }
                
                echo json_encode(['success' => true, 'data' => $permissions, 'is_super_admin' => true]);
                break;
            }
            
            // Get current permissions from tblrole_permissions for non-Super Admin
            $permStmt = $db->prepare("SELECT p.permission_key 
                                       FROM tblrole_permissions rp
                                       JOIN tblpermissions p ON rp.permission_id = p.id
                                       WHERE rp.role_name = :role_name AND rp.school_id = :school_id");
            $permStmt->execute([':role_name' => $role_name, ':school_id' => $school_id]);
            $currentPerms = $permStmt->fetchAll(PDO::FETCH_COLUMN);
            
            $permissions = [];
            foreach ($currentPerms as $perm) {
                $permissions[$perm] = true;
            }
            
            echo json_encode(['success' => true, 'data' => $permissions, 'is_super_admin' => false]);
            break;
            
        case 'save_permissions':
            $input = json_decode(file_get_contents('php://input'), true);
            $role_name = $input['role_name'] ?? '';
            $permissions = $input['permissions'] ?? [];
            
            if (!$role_name) {
                echo json_encode(['success' => false, 'message' => 'Role name required']);
                break;
            }
            
            // Check if role is protected
            $stmt = $db->prepare("SELECT is_protected FROM tblroles WHERE role_name = :role_name AND school_id = :school_id");
            $stmt->execute([':role_name' => $role_name, ':school_id' => $school_id]);
            $isProtected = $stmt->fetchColumn();
            
            if ($isProtected || $role_name === 'Super Admin') {
                echo json_encode(['success' => false, 'message' => 'Cannot modify protected role permissions']);
                break;
            }
            
            // Start transaction
            $db->beginTransaction();
            
            // Get all permission IDs from tblpermissions
            $allPermStmt = $db->prepare("SELECT id, permission_key FROM tblpermissions");
            $allPermStmt->execute();
            $allPermissions = $allPermStmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            // Delete existing permissions for this role
            $deleteStmt = $db->prepare("DELETE FROM tblrole_permissions WHERE role_name = :role_name AND school_id = :school_id");
            $deleteStmt->execute([':role_name' => $role_name, ':school_id' => $school_id]);
            
            // Insert new permissions
            $insertStmt = $db->prepare("INSERT INTO tblrole_permissions (role_name, permission_id, school_id) 
                                        VALUES (:role_name, :permission_id, :school_id)");
            
            foreach ($permissions as $key => $enabled) {
                if ($enabled && isset($allPermissions[$key])) {
                    $insertStmt->execute([
                        ':role_name' => $role_name,
                        ':permission_id' => $allPermissions[$key],
                        ':school_id' => $school_id
                    ]);
                }
            }
            
            $db->commit();
            echo json_encode(['success' => true, 'message' => 'Permissions saved successfully']);
            break;
            
        case 'create_role':
            $input = json_decode(file_get_contents('php://input'), true);
            $role_name = trim($input['role_name'] ?? '');
            $description = trim($input['description'] ?? '');
            
            if (empty($role_name)) {
                echo json_encode(['success' => false, 'message' => 'Role name is required']);
                break;
            }
            
            // Check if role exists
            $stmt = $db->prepare("SELECT id FROM tblroles WHERE role_name = :role_name AND school_id = :school_id");
            $stmt->execute([':role_name' => $role_name, ':school_id' => $school_id]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Role already exists']);
                break;
            }
            
            $stmt = $db->prepare("INSERT INTO tblroles (school_id, role_name, description, is_protected, created_at) 
                                   VALUES (:school_id, :role_name, :description, 0, NOW())");
            $stmt->execute([
                ':school_id' => $school_id,
                ':role_name' => $role_name,
                ':description' => $description
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Role created successfully']);
            break;
            
        case 'delete_role':
            $input = json_decode(file_get_contents('php://input'), true);
            $role_id = $input['role_id'] ?? 0;
            $role_name = $input['role_name'] ?? '';
            
            if (!$role_id) {
                echo json_encode(['success' => false, 'message' => 'Role ID required']);
                break;
            }
            
            // Check if role is protected
            $stmt = $db->prepare("SELECT is_protected FROM tblroles WHERE id = :role_id AND school_id = :school_id");
            $stmt->execute([':role_id' => $role_id, ':school_id' => $school_id]);
            $isProtected = $stmt->fetchColumn();
            
            if ($isProtected || $role_name === 'Super Admin') {
                echo json_encode(['success' => false, 'message' => 'Cannot delete protected role']);
                break;
            }
            
            // Check if role has teachers assigned
            $stmt = $db->prepare("SELECT COUNT(*) FROM tblteachers WHERE role_id = :role_id AND school_id = :school_id AND is_deleted = 0");
            $stmt->execute([':role_id' => $role_id, ':school_id' => $school_id]);
            $teacherCount = $stmt->fetchColumn();
            
            if ($teacherCount > 0) {
                echo json_encode(['success' => false, 'message' => "Cannot delete role. $teacherCount teacher(s) are assigned to this role."]);
                break;
            }
            
            // Delete role permissions first
            $stmt = $db->prepare("DELETE FROM tblrole_permissions WHERE role_name = :role_name AND school_id = :school_id");
            $stmt->execute([':role_name' => $role_name, ':school_id' => $school_id]);
            
            // Delete role
            $stmt = $db->prepare("DELETE FROM tblroles WHERE id = :role_id AND school_id = :school_id");
            $stmt->execute([':role_id' => $role_id, ':school_id' => $school_id]);
            
            echo json_encode(['success' => true, 'message' => 'Role deleted successfully']);
            break;
            
        case 'update_role':
            $input = json_decode(file_get_contents('php://input'), true);
            $role_id = $input['role_id'] ?? 0;
            $role_name = trim($input['role_name'] ?? '');
            $description = trim($input['description'] ?? '');
            
            if (!$role_id) {
                echo json_encode(['success' => false, 'message' => 'Role ID required']);
                break;
            }
            
            if (empty($role_name)) {
                echo json_encode(['success' => false, 'message' => 'Role name is required']);
                break;
            }
            
            // Check if role is protected
            $stmt = $db->prepare("SELECT is_protected FROM tblroles WHERE id = :role_id AND school_id = :school_id");
            $stmt->execute([':role_id' => $role_id, ':school_id' => $school_id]);
            $isProtected = $stmt->fetchColumn();
            
            if ($isProtected) {
                echo json_encode(['success' => false, 'message' => 'Cannot modify protected role']);
                break;
            }
            
            // Check for duplicate name
            $stmt = $db->prepare("SELECT id FROM tblroles WHERE role_name = :role_name AND school_id = :school_id AND id != :role_id");
            $stmt->execute([':role_name' => $role_name, ':school_id' => $school_id, ':role_id' => $role_id]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Role name already exists']);
                break;
            }
            
            $stmt = $db->prepare("UPDATE tblroles SET role_name = :role_name, description = :description 
                                   WHERE id = :role_id AND school_id = :school_id");
            $stmt->execute([
                ':role_name' => $role_name,
                ':description' => $description,
                ':role_id' => $role_id,
                ':school_id' => $school_id
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Role updated successfully']);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
    }
} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Roles API error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

/**
 * Ensure default roles exist for a school
 */
function ensureDefaultRolesExist($db, $school_id) {
    global $DEFAULT_ROLES;
    
    foreach ($DEFAULT_ROLES as $role_name) {
        // Check if role exists
        $stmt = $db->prepare("SELECT id FROM tblroles WHERE role_name = :role_name AND school_id = :school_id");
        $stmt->execute([':role_name' => $role_name, ':school_id' => $school_id]);
        $exists = $stmt->fetch();
        
        if (!$exists) {
            // Create the role
            $is_protected = ($role_name === 'Super Admin') ? 1 : 0;
            $description = "Role: $role_name";
            
            $insertStmt = $db->prepare("INSERT INTO tblroles (school_id, role_name, description, is_protected, created_at) 
                                        VALUES (:school_id, :role_name, :description, :is_protected, NOW())");
            $insertStmt->execute([
                ':school_id' => $school_id,
                ':role_name' => $role_name,
                ':description' => $description,
                ':is_protected' => $is_protected
            ]);
            
            $role_id = $db->lastInsertId();
            
            // If this is Super Admin, assign all permissions
            if ($role_name === 'Super Admin') {
                // Get all permission IDs
                $permStmt = $db->prepare("SELECT id FROM tblpermissions");
                $permStmt->execute();
                $allPermissions = $permStmt->fetchAll(PDO::FETCH_COLUMN);
                
                // Assign all permissions to Super Admin
                $assignStmt = $db->prepare("INSERT INTO tblrole_permissions (role_name, permission_id, school_id) 
                                           VALUES (:role_name, :permission_id, :school_id)");
                foreach ($allPermissions as $perm_id) {
                    $assignStmt->execute([
                        ':role_name' => $role_name,
                        ':permission_id' => $perm_id,
                        ':school_id' => $school_id
                    ]);
                }
            }
        }
    }
}
?>