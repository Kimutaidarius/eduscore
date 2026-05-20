<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

// CORRECTED PATH - use includes/config.php
require_once __DIR__ . '/../includes/config.php';

// Set header for JSON response
header('Content-Type: application/json');

// Security check - ensure user is logged in
if (empty($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get school ID from session
$school_id = $_SESSION['school_id'] ?? null;
if (!$school_id) {
    echo json_encode(['success' => false, 'message' => 'School ID not found']);
    exit;
}

// Get action from request
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'fetch_roles':
        fetchRoles($db, $school_id);
        break;
        
    case 'get_role_permissions':
        getRolePermissions($db, $school_id);
        break;
        
    case 'save_permissions':
        savePermissions($db, $school_id);
        break;
        
    case 'sync_roles':
        syncRolesFromTeachers($db, $school_id);
        break;
        
    case 'get_teacher_roles':
        getTeacherRoles($db, $school_id);
        break;
        
    case 'get_role_stats':
        getRoleStats($db, $school_id);
        break;
        
    case 'delete_role':
        deleteRole($db, $school_id);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

/**
 * Fetch all roles with their permissions and teacher counts
 */
function fetchRoles($db, $school_id) {
    try {
        // First, sync roles from teachers
        syncRolesFromTeachers($db, $school_id);
        
        $query = "
            SELECT 
                r.*,
                COUNT(DISTINCT rp.permission_id) as permission_count,
                COUNT(DISTINCT t.id) as teacher_count
            FROM tblroles r
            LEFT JOIN tblrole_permissions rp ON r.role_name = rp.role_name AND rp.school_id = r.school_id
            LEFT JOIN tblteachers t ON t.role = r.role_name AND t.school_id = r.school_id AND t.is_deleted = 0
            WHERE r.school_id = :school_id
            GROUP BY r.id, r.role_name
            ORDER BY 
                CASE 
                    WHEN r.role_name = 'Super Admin' THEN 1
                    WHEN r.role_name LIKE '%ICT%' THEN 2
                    WHEN r.role_name = 'Teacher' THEN 3
                    ELSE 4
                END,
                r.role_name ASC
        ";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
        $stmt->execute();
        
        $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get total permissions count for reference
        $totalPermissionsStmt = $db->prepare("SELECT COUNT(*) as total FROM tblpermissions");
        $totalPermissionsStmt->execute();
        $totalPermissions = $totalPermissionsStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Get permissions for each role
        foreach ($roles as &$role) {
            if ($role['role_name'] === 'Super Admin') {
                // Super Admin has all permissions
                $role['permissions'] = getAllPermissionsList($db);
                $role['permission_count'] = $totalPermissions;
                $role['has_all_permissions'] = true;
            } else {
                $role['permissions'] = getRolePermissionsList($db, $role['role_name'], $school_id);
            }
        }
        
        echo json_encode([
            'success' => true,
            'data' => $roles
        ]);
        
    } catch (Exception $e) {
        error_log("Fetch roles error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
}

/**
 * Get permissions for a specific role by role name or role id
 */
function getRolePermissions($db, $school_id) {
    $role_name = $_GET['role_name'] ?? '';
    $role_id = $_GET['role_id'] ?? '';
    
    if (empty($role_name) && empty($role_id)) {
        echo json_encode(['success' => false, 'message' => 'Role name or ID required']);
        return;
    }
    
    try {
        // If role_id is provided but role_name is not, get role_name from tblroles
        if (empty($role_name) && !empty($role_id)) {
            $stmt = $db->prepare("SELECT role_name FROM tblroles WHERE id = :id AND school_id = :school_id");
            $stmt->bindParam(':id', $role_id, PDO::PARAM_INT);
            $stmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
            $stmt->execute();
            $role = $stmt->fetch(PDO::FETCH_ASSOC);
            $role_name = $role['role_name'] ?? '';
            
            if (empty($role_name)) {
                echo json_encode(['success' => false, 'message' => 'Role not found']);
                return;
            }
        }
        
        // Check if this is Super Admin
        $isSuperAdmin = ($role_name === 'Super Admin');
        
        if ($isSuperAdmin) {
            // For Super Admin, return ALL permissions as true
            $allPermissions = getAllPermissionsList($db);
            $formattedPermissions = [];
            foreach ($allPermissions as $perm) {
                $formattedPermissions[$perm['permission_key']] = true;
            }
            
            echo json_encode([
                'success' => true,
                'data' => $formattedPermissions,
                'is_super_admin' => true,
                'message' => 'Super Admin has all permissions'
            ]);
        } else {
            // For other roles, get only their assigned permissions
            $permissions = getRolePermissionsList($db, $role_name, $school_id);
            
            // Format permissions as key-value pairs
            $formattedPermissions = [];
            foreach ($permissions as $perm) {
                $formattedPermissions[$perm['permission_key']] = true;
            }
            
            echo json_encode([
                'success' => true,
                'data' => $formattedPermissions
            ]);
        }
        
    } catch (Exception $e) {
        error_log("Get role permissions error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
}

/**
 * Helper function to get all permissions
 */
function getAllPermissionsList($db) {
    $query = "
        SELECT * FROM tblpermissions 
        ORDER BY category, permission_name
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Helper function to get role permissions list by role name
 */
function getRolePermissionsList($db, $role_name, $school_id) {
    $query = "
        SELECT 
            p.*
        FROM tblpermissions p
        INNER JOIN tblrole_permissions rp ON p.id = rp.permission_id
        WHERE rp.role_name = :role_name 
        AND rp.school_id = :school_id
        ORDER BY p.category, p.permission_name
    ";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':role_name', $role_name, PDO::PARAM_STR);
    $stmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Save permissions for a role (using role name)
 */
function savePermissions($db, $school_id) {
    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    
    $role_name = $input['role_name'] ?? '';
    $role_id = $input['role_id'] ?? '';
    $permissions = $input['permissions'] ?? [];
    
    // If role_id is provided but role_name is not, get role_name from tblroles
    if (empty($role_name) && !empty($role_id)) {
        $stmt = $db->prepare("SELECT role_name FROM tblroles WHERE id = :id AND school_id = :school_id");
        $stmt->bindParam(':id', $role_id, PDO::PARAM_INT);
        $stmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
        $stmt->execute();
        $role = $stmt->fetch(PDO::FETCH_ASSOC);
        $role_name = $role['role_name'] ?? '';
    }
    
    if (empty($role_name)) {
        echo json_encode(['success' => false, 'message' => 'Role name required']);
        return;
    }
    
    // Check if role is Super Admin - prevent modifications
    if ($role_name === 'Super Admin') {
        echo json_encode(['success' => false, 'message' => 'Super Admin permissions cannot be modified']);
        return;
    }
    
    // Check if role is protected
    $stmt = $db->prepare("SELECT is_protected FROM tblroles WHERE role_name = :role_name AND school_id = :school_id");
    $stmt->bindParam(':role_name', $role_name, PDO::PARAM_STR);
    $stmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
    $stmt->execute();
    $role = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($role && $role['is_protected'] == 1) {
        echo json_encode(['success' => false, 'message' => 'Cannot modify protected role']);
        return;
    }
    
    try {
        // Begin transaction
        $db->beginTransaction();
        
        // Delete existing permissions for this role
        $deleteStmt = $db->prepare("DELETE FROM tblrole_permissions WHERE role_name = :role_name AND school_id = :school_id");
        $deleteStmt->bindParam(':role_name', $role_name, PDO::PARAM_STR);
        $deleteStmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
        $deleteStmt->execute();
        
        // Insert new permissions
        $insertStmt = $db->prepare("
            INSERT INTO tblrole_permissions (role_name, permission_id, school_id) 
            VALUES (:role_name, (SELECT id FROM tblpermissions WHERE permission_key = :permission_key), :school_id)
        ");
        
        foreach ($permissions as $permission_key => $enabled) {
            if ($enabled) {
                $insertStmt->bindParam(':role_name', $role_name, PDO::PARAM_STR);
                $insertStmt->bindParam(':permission_key', $permission_key, PDO::PARAM_STR);
                $insertStmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
                $insertStmt->execute();
            }
        }
        
        // Commit transaction
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Permissions saved successfully'
        ]);
        
    } catch (Exception $e) {
        // Rollback on error
        $db->rollBack();
        error_log("Save permissions error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
}

/**
 * Sync roles from tblteachers to tblroles
 */
function syncRolesFromTeachers($db, $school_id) {
    try {
        // Direct INSERT IGNORE query without stored procedure
        $query = "
            INSERT IGNORE INTO tblroles (school_id, role_name, description, is_protected)
            SELECT DISTINCT 
                :school_id,
                t.role,
                CONCAT('Role: ', t.role),
                CASE 
                    WHEN t.role = 'Super Admin' THEN 1
                    ELSE 0
                END
            FROM tblteachers t
            WHERE t.school_id = :school_id2 
            AND t.role IS NOT NULL 
            AND t.role != ''
            AND t.role != '0'
            AND t.role NOT LIKE '0%'
        ";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
        $stmt->bindParam(':school_id2', $school_id, PDO::PARAM_INT);
        $stmt->execute();
        
        // Delete any roles with invalid names (like '0')
        $deleteStmt = $db->prepare("
            DELETE FROM tblroles 
            WHERE school_id = :school_id 
            AND (role_name = '0' OR role_name = '' OR role_name IS NULL)
        ");
        $deleteStmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
        $deleteStmt->execute();
        
        // Also ensure Super Admin exists
        $superAdminStmt = $db->prepare("
            INSERT IGNORE INTO tblroles (school_id, role_name, description, is_protected)
            VALUES (:school_id, 'Super Admin', 'Full system access with all permissions', 1)
        ");
        $superAdminStmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
        $superAdminStmt->execute();
        
        return true;
        
    } catch (Exception $e) {
        error_log('Sync roles error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get roles for dropdown (excluding Super Admin)
 */
function getTeacherRoles($db, $school_id) {
    try {
        // Sync first to ensure all roles are present
        syncRolesFromTeachers($db, $school_id);
        
        $stmt = $db->prepare("
            SELECT role_name 
            FROM tblroles 
            WHERE school_id = :school_id 
            AND role_name != 'Super Admin'
            ORDER BY 
                CASE 
                    WHEN role_name LIKE '%ICT%' THEN 1
                    WHEN role_name = 'Teacher' THEN 2
                    ELSE 3
                END,
                role_name ASC
        ");
        $stmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
        $stmt->execute();
        
        $roles = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo json_encode([
            'success' => true,
            'data' => $roles
        ]);
        
    } catch (Exception $e) {
        error_log("Get teacher roles error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
}

/**
 * Delete a role
 */
function deleteRole($db, $school_id) {
    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    
    $role_id = $input['role_id'] ?? 0;
    $role_name = $input['role_name'] ?? '';
    
    if (empty($role_id) && empty($role_name)) {
        echo json_encode(['success' => false, 'message' => 'Role ID or name required']);
        return;
    }
    
    // Check if role is protected
    if (!empty($role_name) && $role_name === 'Super Admin') {
        echo json_encode(['success' => false, 'message' => 'Super Admin cannot be deleted']);
        return;
    }
    
    // Check if role is protected by is_protected flag
    if (!empty($role_id)) {
        $stmt = $db->prepare("SELECT is_protected, role_name FROM tblroles WHERE id = :id AND school_id = :school_id");
        $stmt->bindParam(':id', $role_id, PDO::PARAM_INT);
        $stmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
        $stmt->execute();
        $role = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($role && $role['is_protected'] == 1) {
            echo json_encode(['success' => false, 'message' => 'Cannot delete protected role']);
            return;
        }
        
        $role_name = $role['role_name'] ?? $role_name;
    }
    
    try {
        // Begin transaction
        $db->beginTransaction();
        
        // First, set all teachers with this role to NULL or empty
        $updateTeachersStmt = $db->prepare("
            UPDATE tblteachers 
            SET role = NULL 
            WHERE role = :role_name 
            AND school_id = :school_id
        ");
        $updateTeachersStmt->bindParam(':role_name', $role_name, PDO::PARAM_STR);
        $updateTeachersStmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
        $updateTeachersStmt->execute();
        
        // Delete role permissions
        $deletePermissionsStmt = $db->prepare("
            DELETE FROM tblrole_permissions 
            WHERE role_name = :role_name 
            AND school_id = :school_id
        ");
        $deletePermissionsStmt->bindParam(':role_name', $role_name, PDO::PARAM_STR);
        $deletePermissionsStmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
        $deletePermissionsStmt->execute();
        
        // Delete the role
        if (!empty($role_id)) {
            $deleteRoleStmt = $db->prepare("
                DELETE FROM tblroles 
                WHERE id = :id 
                AND school_id = :school_id
            ");
            $deleteRoleStmt->bindParam(':id', $role_id, PDO::PARAM_INT);
            $deleteRoleStmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
            $deleteRoleStmt->execute();
        } else {
            $deleteRoleStmt = $db->prepare("
                DELETE FROM tblroles 
                WHERE role_name = :role_name 
                AND school_id = :school_id
            ");
            $deleteRoleStmt->bindParam(':role_name', $role_name, PDO::PARAM_STR);
            $deleteRoleStmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
            $deleteRoleStmt->execute();
        }
        
        // Commit transaction
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Role deleted successfully'
        ]);
        
    } catch (Exception $e) {
        // Rollback on error
        $db->rollBack();
        error_log("Delete role error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
}

/**
 * Get role statistics
 */
function getRoleStats($db, $school_id) {
    try {
        $query = "
            SELECT 
                r.role_name,
                COUNT(DISTINCT rp.permission_id) as permission_count,
                COUNT(DISTINCT t.id) as teacher_count,
                r.is_protected
            FROM tblroles r
            LEFT JOIN tblrole_permissions rp ON r.role_name = rp.role_name AND rp.school_id = r.school_id
            LEFT JOIN tblteachers t ON t.role = r.role_name AND t.school_id = r.school_id AND t.is_deleted = 0
            WHERE r.school_id = :school_id
            GROUP BY r.role_name
            ORDER BY r.role_name
        ";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
        $stmt->execute();
        
        $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $stats
        ]);
        
    } catch (Exception $e) {
        error_log("Get role stats error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
}
?>