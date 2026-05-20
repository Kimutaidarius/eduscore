<?php
// includes/PermissionHelper.php

/**
 * PermissionHelper class to handle role-based access control
 */
class PermissionHelper {
    private $db;
    private $school_id;
    private $teacher_id;
    private $teacher_role;
    private $permissions = [];
    
    /**
     * Constructor - initializes the permission helper
     * 
     * @param PDO $db Database connection
     * @param int $school_id School ID from session
     * @param int $teacher_id Teacher ID from session
     */
    public function __construct($db, $school_id, $teacher_id) {
        $this->db = $db;
        $this->school_id = $school_id;
        $this->teacher_id = $teacher_id;
        $this->loadTeacherRole();
        $this->loadPermissions();
    }
    
    /**
     * Load the current teacher's role
     */
    private function loadTeacherRole() {
        try {
            $stmt = $this->db->prepare("
                SELECT role 
                FROM tblteachers 
                WHERE id = :teacher_id 
                AND school_id = :school_id 
                AND is_deleted = 0
            ");
            $stmt->execute([
                ':teacher_id' => $this->teacher_id,
                ':school_id' => $this->school_id
            ]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->teacher_role = $result ? $result['role'] : null;
        } catch (PDOException $e) {
            error_log("Error loading teacher role: " . $e->getMessage());
            $this->teacher_role = null;
        }
    }
    
    /**
     * Load permissions for the current teacher's role
     */
    private function loadPermissions() {
        // Super Admin has all permissions - no need to load from database
        if ($this->isSuperAdmin()) {
            $this->permissions = ['*']; // Wildcard for all permissions
            return;
        }
        
        try {
            $stmt = $this->db->prepare("
                SELECT p.permission_key
                FROM tblrole_permissions rp
                JOIN tblpermissions p ON rp.permission_id = p.id
                WHERE rp.role_name = :role_name 
                AND rp.school_id = :school_id
            ");
            $stmt->execute([
                ':role_name' => $this->teacher_role,
                ':school_id' => $this->school_id
            ]);
            
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $this->permissions = array_column($results, 'permission_key');
            
        } catch (PDOException $e) {
            error_log("Error loading permissions: " . $e->getMessage());
            $this->permissions = [];
        }
    }
    
    /**
     * Check if current user is Super Admin
     * 
     * @return bool
     */
    public function isSuperAdmin() {
        return $this->teacher_role === 'Super Admin';
    }
    
    /**
     * Check if user has a specific permission
     * 
     * @param string $permission The permission key to check (e.g., 'classesView', 'classesCreate')
     * @return bool
     */
    public function hasPermission($permission) {
        // Super Admin has all permissions
        if ($this->isSuperAdmin()) {
            return true;
        }
        
        return in_array($permission, $this->permissions);
    }
    
    /**
     * Check if user has any of the given permissions
     * 
     * @param array $permissions Array of permission keys
     * @return bool
     */
    public function hasAnyPermission($permissions) {
        // Super Admin has all permissions
        if ($this->isSuperAdmin()) {
            return true;
        }
        
        foreach ($permissions as $permission) {
            if (in_array($permission, $this->permissions)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Check if user has all of the given permissions
     * 
     * @param array $permissions Array of permission keys
     * @return bool
     */
    public function hasAllPermissions($permissions) {
        // Super Admin has all permissions
        if ($this->isSuperAdmin()) {
            return true;
        }
        
        foreach ($permissions as $permission) {
            if (!in_array($permission, $this->permissions)) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * Get the current user's role
     * 
     * @return string|null
     */
    public function getRole() {
        return $this->teacher_role;
    }
    
    /**
     * Get all permissions for the current user
     * 
     * @return array
     */
    public function getAllPermissions() {
        return $this->permissions;
    }
    
    /**
     * Require a specific permission or redirect
     * 
     * @param string $permission Required permission
     * @param string $redirect URL to redirect to if permission denied
     */
    public function requirePermission($permission, $redirect = 'dashboard.php') {
        if (!$this->hasPermission($permission)) {
            $_SESSION['error_message'] = 'You do not have permission to access this page.';
            header("Location: $redirect");
            exit;
        }
    }
    
    /**
     * Require any of the given permissions or redirect
     * 
     * @param array $permissions Array of permission keys
     * @param string $redirect URL to redirect to if permission denied
     */
    public function requireAnyPermission($permissions, $redirect = 'dashboard.php') {
        if (!$this->hasAnyPermission($permissions)) {
            $_SESSION['error_message'] = 'You do not have permission to access this page.';
            header("Location: $redirect");
            exit;
        }
    }
    
    /**
     * Get permission denied message
     * 
     * @return string
     */
    public function getPermissionDeniedMessage() {
        return '<div class="error-message" style="background: #fef2f2; border: 1px solid #fecaca; color: #ef4444; padding: 2rem; border-radius: 12px; text-align: center; margin: 2rem;">
            <i class="fas fa-lock" style="font-size: 3rem; margin-bottom: 1rem;"></i>
            <h3>Access Denied</h3>
            <p>You do not have permission to perform this action.</p>
            <p style="font-size: 0.9rem; margin-top: 0.5rem;">Please contact your system administrator if you need access.</p>
        </div>';
    }
    
    /**
     * Filter data based on permissions (for API responses)
     * 
     * @param array $data Data to filter
     * @param array $permissionMap Map of fields to permissions
     * @return array Filtered data
     */
    public function filterDataByPermissions($data, $permissionMap) {
        if ($this->isSuperAdmin()) {
            return $data; // Super Admin sees everything
        }
        
        foreach ($permissionMap as $field => $permission) {
            if (!$this->hasPermission($permission) && isset($data[$field])) {
                $data[$field] = '[Hidden]';
            }
        }
        
        return $data;
    }
    
    /**
     * Check if user can access a specific teacher's data
     * 
     * @param int $teacher_id Teacher ID to check access for
     * @return bool
     */
    public function canAccessTeacherData($teacher_id) {
        // Super Admin can access all teachers
        if ($this->isSuperAdmin()) {
            return true;
        }
        
        // Teachers can access their own data
        if ($this->teacher_id == $teacher_id) {
            return true;
        }
        
        // Check if user has permission to view all teachers
        if ($this->hasPermission('teachersViewAll')) {
            return true;
        }
        
        return false;
    }
}