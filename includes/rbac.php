<?php
/**
 * EduScore RBAC (Role-Based Access Control) System
 * Production-ready permission management
 */

class RBAC {
    private static $instance = null;
    private $db;
    private $cache = [];
    
    private function __construct() {
        global $conn;
        $this->db = $conn;
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Check if current user has permission
     */
    public function hasPermission($permission_key) {
        if (empty($_SESSION['teacher_id']) || empty($_SESSION['school_id'])) {
            return false;
        }
        
        $teacher_id = $_SESSION['teacher_id'];
        $school_id = $_SESSION['school_id'];
        
        // Check cache first
        $cache_key = "perm_{$teacher_id}_{$permission_key}";
        if (isset($this->cache[$cache_key])) {
            return $this->cache[$cache_key];
        }
        
        // Check if user is Super Admin
        if ($this->isSuperAdmin($teacher_id, $school_id)) {
            $this->cache[$cache_key] = true;
            return true;
        }
        
        // Get user's roles and check permissions
        $roles = $this->getUserRoles($teacher_id, $school_id);
        if (empty($roles)) {
            $this->cache[$cache_key] = false;
            return false;
        }
        
        // Build roles list for IN clause
        $roleIds = array_column($roles, 'role_id');
        $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
        
        $query = "
            SELECT COUNT(*) as has_perm
            FROM tblrole_permissions rp
            JOIN tblpermissions p ON rp.permission_id = p.id
            WHERE rp.role_id IN ({$placeholders})
            AND rp.school_id = ?
            AND p.permission_key = ?
        ";
        
        $stmt = $this->db->prepare($query);
        $types = str_repeat('i', count($roleIds)) . 'is';
        $params = array_merge($roleIds, [$school_id, $permission_key]);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        $hasPermission = $row['has_perm'] > 0;
        $this->cache[$cache_key] = $hasPermission;
        
        return $hasPermission;
    }
    
    /**
     * Check if user has any of the given permissions
     */
    public function hasAnyPermission($permission_keys) {
        foreach ($permission_keys as $key) {
            if ($this->hasPermission($key)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Check if user has all of the given permissions
     */
    public function hasAllPermissions($permission_keys) {
        foreach ($permission_keys as $key) {
            if (!$this->hasPermission($key)) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * Get all permissions for current user
     */
    public function getUserPermissions() {
        if (empty($_SESSION['teacher_id']) || empty($_SESSION['school_id'])) {
            return [];
        }
        
        $teacher_id = $_SESSION['teacher_id'];
        $school_id = $_SESSION['school_id'];
        
        // Check if user is Super Admin
        if ($this->isSuperAdmin($teacher_id, $school_id)) {
            return $this->getAllPermissions();
        }
        
        $roles = $this->getUserRoles($teacher_id, $school_id);
        if (empty($roles)) {
            return [];
        }
        
        $roleIds = array_column($roles, 'role_id');
        $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
        
        $query = "
            SELECT DISTINCT p.permission_key, p.permission_name, p.category
            FROM tblrole_permissions rp
            JOIN tblpermissions p ON rp.permission_id = p.id
            WHERE rp.role_id IN ({$placeholders})
            AND rp.school_id = ?
            ORDER BY p.category, p.permission_name
        ";
        
        $stmt = $this->db->prepare($query);
        $types = str_repeat('i', count($roleIds)) . 'i';
        $params = array_merge($roleIds, [$school_id]);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $permissions = [];
        while ($row = $result->fetch_assoc()) {
            $permissions[$row['permission_key']] = $row;
        }
        $stmt->close();
        
        return $permissions;
    }
    
    /**
     * Check if user is Super Admin
     */
    public function isSuperAdmin($teacher_id = null, $school_id = null) {
        $teacher_id = $teacher_id ?? $_SESSION['teacher_id'] ?? null;
        $school_id = $school_id ?? $_SESSION['school_id'] ?? null;
        
        if (!$teacher_id || !$school_id) {
            return false;
        }
        
        $query = "
            SELECT COUNT(*) as is_super
            FROM tblteacher_roles tr
            JOIN tblroles r ON tr.role_id = r.id
            WHERE tr.teacher_id = ? 
            AND tr.school_id = ?
            AND r.role_name = 'Super Admin'
            AND r.is_protected = 1
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("ii", $teacher_id, $school_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        return $row['is_super'] > 0;
    }
    
    /**
     * Get all roles for a user
     */
    public function getUserRoles($teacher_id, $school_id) {
        $cache_key = "roles_{$teacher_id}_{$school_id}";
        if (isset($this->cache[$cache_key])) {
            return $this->cache[$cache_key];
        }
        
        $query = "
            SELECT tr.*, r.role_name, r.description, r.is_protected, r.is_system_role
            FROM tblteacher_roles tr
            JOIN tblroles r ON tr.role_id = r.id
            WHERE tr.teacher_id = ? AND tr.school_id = ?
            ORDER BY r.is_protected DESC, r.role_name
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("ii", $teacher_id, $school_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $roles = [];
        while ($row = $result->fetch_assoc()) {
            $roles[] = $row;
        }
        $stmt->close();
        
        $this->cache[$cache_key] = $roles;
        return $roles;
    }
    
    /**
     * Assign role to teacher
     */
    public function assignRoleToTeacher($teacher_id, $role_id, $school_id, $assigned_by = null) {
        // Check if role exists and belongs to school
        $role = $this->getRoleById($role_id, $school_id);
        if (!$role) {
            return ['success' => false, 'message' => 'Role not found'];
        }
        
        // Check if teacher exists
        $teacher = $this->getTeacher($teacher_id, $school_id);
        if (!$teacher) {
            return ['success' => false, 'message' => 'Teacher not found'];
        }
        
        // Check if assignment already exists
        $query = "SELECT id FROM tblteacher_roles WHERE teacher_id = ? AND role_id = ? AND school_id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("iii", $teacher_id, $role_id, $school_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $stmt->close();
            return ['success' => false, 'message' => 'Role already assigned to this teacher'];
        }
        $stmt->close();
        
        // Insert new assignment
        $assigned_by = $assigned_by ?? $_SESSION['teacher_id'] ?? null;
        
        $query = "INSERT INTO tblteacher_roles (teacher_id, role_id, school_id, assigned_by) VALUES (?, ?, ?, ?)";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("iiii", $teacher_id, $role_id, $school_id, $assigned_by);
        
        if ($stmt->execute()) {
            // Update teacher's primary role_id for backward compatibility
            $this->updateTeacherPrimaryRole($teacher_id, $school_id);
            
            $stmt->close();
            
            // Clear cache
            unset($this->cache["roles_{$teacher_id}_{$school_id}"]);
            
            return ['success' => true, 'message' => 'Role assigned successfully'];
        } else {
            $error = $stmt->error;
            $stmt->close();
            return ['success' => false, 'message' => 'Database error: ' . $error];
        }
    }
    
    /**
     * Remove role from teacher
     */
    public function removeRoleFromTeacher($teacher_id, $role_id, $school_id) {
        // Don't allow removing last Super Admin role
        $role = $this->getRoleById($role_id, $school_id);
        if ($role && $role['role_name'] === 'Super Admin') {
            $superAdminCount = $this->countSuperAdmins($school_id);
            if ($superAdminCount <= 1) {
                return ['success' => false, 'message' => 'Cannot remove the last Super Admin'];
            }
        }
        
        $query = "DELETE FROM tblteacher_roles WHERE teacher_id = ? AND role_id = ? AND school_id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("iii", $teacher_id, $role_id, $school_id);
        
        if ($stmt->execute()) {
            // Update teacher's primary role_id for backward compatibility
            $this->updateTeacherPrimaryRole($teacher_id, $school_id);
            
            $stmt->close();
            
            // Clear cache
            unset($this->cache["roles_{$teacher_id}_{$school_id}"]);
            
            return ['success' => true, 'message' => 'Role removed successfully'];
        } else {
            $error = $stmt->error;
            $stmt->close();
            return ['success' => false, 'message' => 'Database error: ' . $error];
        }
    }
    
    /**
     * Update teacher's primary role (for backward compatibility)
     */
    private function updateTeacherPrimaryRole($teacher_id, $school_id) {
        // Get the first role assigned to teacher
        $query = "
            SELECT role_id FROM tblteacher_roles 
            WHERE teacher_id = ? AND school_id = ? 
            ORDER BY id ASC LIMIT 1
        ";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("ii", $teacher_id, $school_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        $role_id = $row ? $row['role_id'] : null;
        
        $update = "UPDATE tblteachers SET role_id = ? WHERE id = ? AND school_id = ?";
        $stmt = $this->db->prepare($update);
        $stmt->bind_param("iii", $role_id, $teacher_id, $school_id);
        $stmt->execute();
        $stmt->close();
    }
    
    /**
     * Count Super Admins in a school
     */
    private function countSuperAdmins($school_id) {
        $query = "
            SELECT COUNT(*) as count
            FROM tblteacher_roles tr
            JOIN tblroles r ON tr.role_id = r.id
            WHERE tr.school_id = ? AND r.role_name = 'Super Admin'
        ";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $school_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        return $row['count'];
    }
    
    /**
     * Get role by ID
     */
    public function getRoleById($role_id, $school_id) {
        $query = "SELECT * FROM tblroles WHERE id = ? AND school_id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("ii", $role_id, $school_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $role = $result->fetch_assoc();
        $stmt->close();
        
        return $role;
    }
    
    /**
     * Get teacher by ID
     */
    private function getTeacher($teacher_id, $school_id) {
        $query = "SELECT id, firstname, lastname, email FROM tblteachers WHERE id = ? AND school_id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("ii", $teacher_id, $school_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $teacher = $result->fetch_assoc();
        $stmt->close();
        
        return $teacher;
    }
    
    /**
     * Get all permissions
     */
    public function getAllPermissions() {
        $query = "SELECT * FROM tblpermissions ORDER BY category, permission_name";
        $result = $this->db->query($query);
        
        $permissions = [];
        while ($row = $result->fetch_assoc()) {
            $permissions[$row['permission_key']] = $row;
        }
        
        return $permissions;
    }
    
    /**
     * Get permissions by category
     */
    public function getPermissionsByCategory() {
        $query = "SELECT * FROM tblpermissions ORDER BY category, permission_name";
        $result = $this->db->query($query);
        
        $categories = [];
        while ($row = $result->fetch_assoc()) {
            $categories[$row['category']][] = $row;
        }
        
        return $categories;
    }
    
    /**
     * Get role permissions
     */
    public function getRolePermissions($role_id, $school_id) {
        $query = "
            SELECT p.*
            FROM tblrole_permissions rp
            JOIN tblpermissions p ON rp.permission_id = p.id
            WHERE rp.role_id = ? AND rp.school_id = ?
            ORDER BY p.category, p.permission_name
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("ii", $role_id, $school_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $permissions = [];
        while ($row = $result->fetch_assoc()) {
            $permissions[$row['permission_key']] = $row;
        }
        $stmt->close();
        
        return $permissions;
    }
    
    /**
     * Save role permissions
     */
    public function saveRolePermissions($role_id, $school_id, $permission_ids) {
        // Start transaction
        $this->db->begin_transaction();
        
        try {
            // Delete existing permissions
            $delete = "DELETE FROM tblrole_permissions WHERE role_id = ? AND school_id = ?";
            $stmt = $this->db->prepare($delete);
            $stmt->bind_param("ii", $role_id, $school_id);
            $stmt->execute();
            $stmt->close();
            
            // Insert new permissions
            if (!empty($permission_ids)) {
                $insert = "INSERT INTO tblrole_permissions (role_id, permission_id, school_id) VALUES (?, ?, ?)";
                $stmt = $this->db->prepare($insert);
                
                foreach ($permission_ids as $perm_id) {
                    $stmt->bind_param("iii", $role_id, $perm_id, $school_id);
                    $stmt->execute();
                }
                $stmt->close();
            }
            
            $this->db->commit();
            
            // Clear permission cache for all teachers with this role
            $this->clearRolePermissionCache($role_id, $school_id);
            
            return ['success' => true, 'message' => 'Permissions saved successfully'];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Clear permission cache for all teachers with a role
     */
    private function clearRolePermissionCache($role_id, $school_id) {
        $query = "SELECT teacher_id FROM tblteacher_roles WHERE role_id = ? AND school_id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("ii", $role_id, $school_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            // Clear all permission cache keys for this teacher
            $pattern = "perm_{$row['teacher_id']}_";
            foreach ($this->cache as $key => $value) {
                if (strpos($key, $pattern) === 0) {
                    unset($this->cache[$key]);
                }
            }
            unset($this->cache["roles_{$row['teacher_id']}_{$school_id}"]);
        }
        $stmt->close();
    }
    
    /**
     * Create new role
     */
    public function createRole($school_id, $role_name, $description = '', $permission_ids = []) {
        // Check if role already exists
        $check = "SELECT id FROM tblroles WHERE school_id = ? AND role_name = ?";
        $stmt = $this->db->prepare($check);
        $stmt->bind_param("is", $school_id, $role_name);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $stmt->close();
            return ['success' => false, 'message' => 'Role already exists'];
        }
        $stmt->close();
        
        // Create role
        $insert = "INSERT INTO tblroles (school_id, role_name, description, created_by) VALUES (?, ?, ?, ?)";
        $stmt = $this->db->prepare($insert);
        $created_by = $_SESSION['teacher_id'] ?? null;
        $stmt->bind_param("issi", $school_id, $role_name, $description, $created_by);
        
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            return ['success' => false, 'message' => 'Database error: ' . $error];
        }
        
        $role_id = $stmt->insert_id;
        $stmt->close();
        
        // Assign permissions if provided
        if (!empty($permission_ids)) {
            $result = $this->saveRolePermissions($role_id, $school_id, $permission_ids);
            if (!$result['success']) {
                return $result;
            }
        }
        
        return ['success' => true, 'message' => 'Role created successfully', 'role_id' => $role_id];
    }
    
    /**
     * Update role
     */
    public function updateRole($role_id, $school_id, $role_name, $description = '') {
        $role = $this->getRoleById($role_id, $school_id);
        if (!$role) {
            return ['success' => false, 'message' => 'Role not found'];
        }
        
        if ($role['is_protected']) {
            return ['success' => false, 'message' => 'Cannot edit protected role'];
        }
        
        $update = "UPDATE tblroles SET role_name = ?, description = ? WHERE id = ? AND school_id = ?";
        $stmt = $this->db->prepare($update);
        $stmt->bind_param("ssii", $role_name, $description, $role_id, $school_id);
        
        if ($stmt->execute()) {
            $stmt->close();
            return ['success' => true, 'message' => 'Role updated successfully'];
        } else {
            $error = $stmt->error;
            $stmt->close();
            return ['success' => false, 'message' => 'Database error: ' . $error];
        }
    }
    
    /**
     * Delete role
     */
    public function deleteRole($role_id, $school_id) {
        $role = $this->getRoleById($role_id, $school_id);
        if (!$role) {
            return ['success' => false, 'message' => 'Role not found'];
        }
        
        if ($role['is_protected']) {
            return ['success' => false, 'message' => 'Cannot delete protected role'];
        }
        
        // Check if role is assigned to any teachers
        $check = "SELECT COUNT(*) as count FROM tblteacher_roles WHERE role_id = ? AND school_id = ?";
        $stmt = $this->db->prepare($check);
        $stmt->bind_param("ii", $role_id, $school_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        if ($row['count'] > 0) {
            return ['success' => false, 'message' => 'Cannot delete role that is assigned to teachers'];
        }
        
        // Delete role (permissions will cascade delete)
        $delete = "DELETE FROM tblroles WHERE id = ? AND school_id = ?";
        $stmt = $this->db->prepare($delete);
        $stmt->bind_param("ii", $role_id, $school_id);
        
        if ($stmt->execute()) {
            $stmt->close();
            return ['success' => true, 'message' => 'Role deleted successfully'];
        } else {
            $error = $stmt->error;
            $stmt->close();
            return ['success' => false, 'message' => 'Database error: ' . $error];
        }
    }
    
    /**
     * Get all roles for a school
     */
    public function getSchoolRoles($school_id) {
        $query = "
            SELECT r.*, 
                   COUNT(DISTINCT tr.teacher_id) as teacher_count,
                   (SELECT COUNT(*) FROM tblrole_permissions rp WHERE rp.role_id = r.id) as permission_count
            FROM tblroles r
            LEFT JOIN tblteacher_roles tr ON r.id = tr.role_id AND tr.school_id = r.school_id
            WHERE r.school_id = ?
            GROUP BY r.id
            ORDER BY r.is_protected DESC, r.role_name
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $school_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $roles = [];
        while ($row = $result->fetch_assoc()) {
            $roles[] = $row;
        }
        $stmt->close();
        
        return $roles;
    }
    
    /**
     * Initialize default roles for a new school
     */
    public function initializeSchoolRoles($school_id) {
        $defaultRoles = [
            ['Super Admin', 'Full system access with all permissions', 1],
            ['Administrator', 'School administrator with most permissions', 1],
            ['Teacher', 'Standard teacher role', 0],
            ['Class Teacher', 'Teacher with class management permissions', 0],
            ['ICT Teacher', 'Technical support role', 0],
            ['Parent', 'Parent view-only access', 0]
        ];
        
        foreach ($defaultRoles as $role) {
            $insert = "INSERT INTO tblroles (school_id, role_name, description, is_protected, is_system_role) VALUES (?, ?, ?, ?, 1)";
            $stmt = $this->db->prepare($insert);
            $stmt->bind_param("issi", $school_id, $role[0], $role[1], $role[2]);
            $stmt->execute();
            $role_id = $stmt->insert_id;
            $stmt->close();
            
            // Assign all permissions to Super Admin
            if ($role[0] === 'Super Admin') {
                $this->assignAllPermissionsToRole($role_id, $school_id);
            }
        }
        
        return true;
    }
    
    /**
     * Assign all permissions to a role
     */
    private function assignAllPermissionsToRole($role_id, $school_id) {
        $query = "SELECT id FROM tblpermissions";
        $result = $this->db->query($query);
        
        $insert = "INSERT INTO tblrole_permissions (role_id, permission_id, school_id) VALUES (?, ?, ?)";
        $stmt = $this->db->prepare($insert);
        
        while ($row = $result->fetch_assoc()) {
            $stmt->bind_param("iii", $role_id, $row['id'], $school_id);
            $stmt->execute();
        }
        
        $stmt->close();
    }
    
    /**
     * Clear all caches
     */
    public function clearCache() {
        $this->cache = [];
    }
}

// Global helper functions
function hasPermission($permission_key) {
    return RBAC::getInstance()->hasPermission($permission_key);
}

function hasAnyPermission($permission_keys) {
    return RBAC::getInstance()->hasAnyPermission($permission_keys);
}

function hasAllPermissions($permission_keys) {
    return RBAC::getInstance()->hasAllPermissions($permission_keys);
}

function isSuperAdmin() {
    return RBAC::getInstance()->isSuperAdmin();
}