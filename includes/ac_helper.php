<?php
/**
 * Role-Based Access Control (RBAC) Helper
 * Manages permissions for different user roles
 */

class RBACHelper {
    private $conn;
    private $user_id;
    private $user_role;
    private $school_id;
    
    // Define role constants
    const ROLE_SUPER_ADMIN = 'super_admin';
    const ROLE_SCHOOL_ADMIN = 'school_admin';
    const ROLE_TEACHER = 'teacher';
    const ROLE_STUDENT = 'student';
    
    // Define permission constants
    const PERM_MANAGE_ALL_SCORES = 'manage_all_scores';
    const PERM_EDIT_ALL_SCORES = 'edit_all_scores';
    const PERM_DELETE_SCORES = 'delete_scores';
    const PERM_VIEW_ALL_SUBJECTS = 'view_all_subjects';
    const PERM_MANAGE_OWN_SUBJECTS = 'manage_own_subjects';
    const PERM_VIEW_REPORTS = 'view_reports';
    const PERM_EXPORT_DATA = 'export_data';
    
    public function __construct($conn, $user_id, $school_id) {
        $this->conn = $conn;
        $this->user_id = $user_id;
        $this->school_id = $school_id;
        $this->user_role = $this->getUserRole();
    }
    
    /**
     * Get user role from database
     */
    private function getUserRole() {
        $stmt = $this->conn->prepare("
            SELECT role, is_admin 
            FROM tblteacher 
            WHERE Id = ? AND school_id = ?
        ");
        $stmt->bind_param("ii", $this->user_id, $this->school_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            // Check if user is admin
            if ($row['is_admin'] == 1) {
                return self::ROLE_SCHOOL_ADMIN;
            }
            
            // Return role from database or default to teacher
            return $row['role'] ?? self::ROLE_TEACHER;
        }
        
        $stmt->close();
        return self::ROLE_TEACHER; // Default role
    }
    
    /**
     * Check if user has a specific permission
     */
    public function hasPermission($permission) {
        $permissions = $this->getRolePermissions($this->user_role);
        return in_array($permission, $permissions);
    }
    
    /**
     * Get all permissions for a role
     */
    private function getRolePermissions($role) {
        $permissions = [
            self::ROLE_SUPER_ADMIN => [
                self::PERM_MANAGE_ALL_SCORES,
                self::PERM_EDIT_ALL_SCORES,
                self::PERM_DELETE_SCORES,
                self::PERM_VIEW_ALL_SUBJECTS,
                self::PERM_MANAGE_OWN_SUBJECTS,
                self::PERM_VIEW_REPORTS,
                self::PERM_EXPORT_DATA,
            ],
            self::ROLE_SCHOOL_ADMIN => [
                self::PERM_MANAGE_ALL_SCORES,
                self::PERM_EDIT_ALL_SCORES,
                self::PERM_DELETE_SCORES,
                self::PERM_VIEW_ALL_SUBJECTS,
                self::PERM_MANAGE_OWN_SUBJECTS,
                self::PERM_VIEW_REPORTS,
                self::PERM_EXPORT_DATA,
            ],
            self::ROLE_TEACHER => [
                self::PERM_MANAGE_OWN_SUBJECTS,
                self::PERM_VIEW_REPORTS,
            ],
        ];
        
        return $permissions[$role] ?? [];
    }
    
    /**
     * Check if user is school admin
     */
    public function isSchoolAdmin() {
        return $this->user_role === self::ROLE_SCHOOL_ADMIN || 
               $this->user_role === self::ROLE_SUPER_ADMIN;
    }
    
    /**
     * Check if user is regular teacher
     */
    public function isTeacher() {
        return $this->user_role === self::ROLE_TEACHER;
    }
    
    /**
     * Check if teacher can access a specific subject
     */
    public function canAccessSubject($subject_id) {
        // Admins can access all subjects
        if ($this->isSchoolAdmin()) {
            return true;
        }
        
        // Teachers can only access their assigned subjects
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as count 
            FROM tbllessons 
            WHERE teacher_id = ? 
            AND subject_id = ? 
            AND school_id = ?
        ");
        $stmt->bind_param("iii", $this->user_id, $subject_id, $this->school_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        return $row['count'] > 0;
    }
    
    /**
     * Check if user can edit a specific score
     */
    public function canEditScore($score_id) {
        // Admins can edit all scores
        if ($this->isSchoolAdmin()) {
            return true;
        }
        
        // Teachers can only edit scores they recorded
        $stmt = $this->conn->prepare("
            SELECT recorded_by_teacher_id, subject_id 
            FROM tblscores 
            WHERE id = ? AND school_id = ?
        ");
        $stmt->bind_param("ii", $score_id, $this->school_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $stmt->close();
            // Check if teacher recorded this score OR if it's their subject
            return $row['recorded_by_teacher_id'] == $this->user_id || 
                   $this->canAccessSubject($row['subject_id']);
        }
        
        $stmt->close();
        return false;
    }
    
    /**
     * Get user role name for display
     */
    public function getRoleName() {
        $roleNames = [
            self::ROLE_SUPER_ADMIN => 'Super Administrator',
            self::ROLE_SCHOOL_ADMIN => 'School Administrator',
            self::ROLE_TEACHER => 'Teacher',
            self::ROLE_STUDENT => 'Student',
        ];
        
        return $roleNames[$this->user_role] ?? 'User';
    }
    
    /**
     * Get current user role
     */
    public function getUserRole() {
        return $this->user_role;
    }
    
    /**
     * Log permission check for audit trail
     */
    private function logPermissionCheck($permission, $granted) {
        $stmt = $this->conn->prepare("
            INSERT INTO tbl_audit_log 
            (user_id, school_id, action, details, created_at) 
            VALUES (?, ?, 'permission_check', ?, NOW())
        ");
        
        $details = json_encode([
            'permission' => $permission,
            'granted' => $granted,
            'role' => $this->user_role
        ]);
        
        $stmt->bind_param("iis", $this->user_id, $this->school_id, $details);
        $stmt->execute();
        $stmt->close();
    }
}