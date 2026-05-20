<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database/config
require_once '../../includes/config.php';

// Include PermissionHelper with fallback
if (file_exists('../../includes/PermissionHelper.php')) {
    require_once '../../includes/PermissionHelper.php';
} else {
    // Fallback PermissionHelper class
    class PermissionHelper {
        private $db;
        private $school_id;
        private $teacher_id;
        private $role;
        
        public function __construct($db, $school_id, $teacher_id) {
            $this->db = $db;
            $this->school_id = $school_id;
            $this->teacher_id = $teacher_id;
            $this->loadUserRole();
        }
        
        private function loadUserRole() {
            try {
                $stmt = $this->db->prepare("
                    SELECT r.role_name 
                    FROM tblteachers t
                    LEFT JOIN roles r ON t.role_id = r.id
                    WHERE t.id = :teacher_id AND t.school_id = :school_id
                ");
                $stmt->execute([
                    ':teacher_id' => $this->teacher_id,
                    ':school_id' => $this->school_id
                ]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $this->role = $result['role_name'] ?? 'teacher';
            } catch (Exception $e) {
                $this->role = 'teacher';
            }
        }
        
        public function getRole() { return $this->role; }
        public function isSuperAdmin() { return $this->role === 'super_admin'; }
        
        public function hasPermission($permission) {
            if ($this->isSuperAdmin()) return true;
            try {
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) as has_permission
                    FROM role_permissions rp
                    JOIN permissions p ON rp.permission_id = p.id
                    JOIN roles r ON rp.role_id = r.id
                    WHERE r.role_name = :role_name AND p.permission_key = :permission
                ");
                $stmt->execute([':role_name' => $this->role, ':permission' => $permission]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                return $result['has_permission'] > 0;
            } catch (Exception $e) {
                return true;
            }
        }
        
        public function hasAnyPermission($permissions) {
            foreach ($permissions as $permission) {
                if ($this->hasPermission($permission)) return true;
            }
            return false;
        }
        
        public function requireAnyPermission($permissions, $redirect = null) {
            if (!$this->hasAnyPermission($permissions)) {
                if ($redirect) { header("Location: $redirect"); exit; }
                return false;
            }
            return true;
        }
    }
}

// Include session timeout if exists
if (file_exists('../../includes/session_timeout.php')) {
    require_once '../../includes/session_timeout.php';
}

// Authentication check
if (empty($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('Location: ../../login.php');
    exit;
}

if (empty($_SESSION['school_id']) || empty($_SESSION['teacher_id'])) {
    session_destroy();
    header("Location: ../../login.php");
    exit;
}

// Get school ID from session
$school_id = $_SESSION['school_id'];
if (!$school_id) {
    die("School ID not found in session.");
}

// Initialize Permission Helper
$permissionHelper = new PermissionHelper($db, $school_id, $_SESSION['teacher_id']);

// Check if user has permission to view roles page
$permissionHelper->requireAnyPermission(['rolesView', 'rolesViewAll'], '../../dashboard.php');

// Determine which actions are allowed based on permissions
$canCreate = $permissionHelper->hasPermission('rolesCreate');
$canEdit = $permissionHelper->hasPermission('rolesEdit');
$canDelete = $permissionHelper->hasPermission('rolesDelete');
$canViewAll = $permissionHelper->hasPermission('rolesViewAll');
$isSuperAdmin = $permissionHelper->isSuperAdmin();
$currentUserRole = $permissionHelper->getRole();

// School info for banner
$school = null;
try {
    $stmt = $db->prepare("SELECT * FROM tblschoolinfo WHERE id = :school_id");
    $stmt->bindParam(":school_id", $school_id, PDO::PARAM_INT);
    $stmt->execute();
    $school = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) { 
    $school = null; 
}

// Include trial banner if exists
if (file_exists('../../trial_banner.php')) {
    include '../../trial_banner.php';
}

// Include header and sidebar
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduScore - Role Management</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <style>
        :root {
            --primary-blue: #1e3a8a;
            --secondary-blue: #2563eb;
            --light-blue: #dbeafe;
            --accent-yellow: #fbbf24;
            --dark-blue: #1e3a8a;
            --success-green: #10b981;
            --warning-orange: #f59e0b;
            --error-red: #ef4444;
            --text-dark: #1f2937;
            --text-light: #6b7280;
            --bg-light: #f9fafb;
            --bg-white: #ffffff;
            --border-color: #e5e7eb;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            --border-radius: 12px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Page Header */
        .page-header {
            background: var(--bg-white);
            border-radius: var(--border-radius);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
            border-left: 4px solid var(--primary-blue);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .page-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .page-title i {
            color: var(--primary-blue);
        }

        .page-description {
            color: var(--text-light);
            font-size: 1rem;
        }

        /* Role Badge */
        .role-badge {
            background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            white-space: nowrap;
            box-shadow: var(--shadow);
        }

        /* Permission Denied */
        .permission-denied {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: var(--error-red);
            padding: 2rem;
            border-radius: var(--border-radius);
            text-align: center;
            margin: 2rem 0;
        }

        .permission-denied i {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        /* Toggle Container */
        .toggle-container {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin: 2rem auto;
            background: var(--bg-white);
            padding: 0.5rem;
            border-radius: 50px;
            box-shadow: var(--shadow);
            width: fit-content;
        }

        .toggle-btn {
            padding: 0.75rem 2rem;
            border: none;
            background: transparent;
            color: var(--text-light);
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            border-radius: 50px;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .toggle-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .toggle-btn.active {
            background: var(--primary-blue);
            color: white;
            box-shadow: var(--shadow);
        }

        /* Content Sections */
        .content-section {
            display: none;
        }

        .content-section.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Filter Bar */
        .filter-bar {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 2rem;
            background: var(--bg-white);
            padding: 1.5rem;
            border-radius: 16px;
            box-shadow: var(--shadow);
            flex-wrap: wrap;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }

        .filter-label i {
            color: var(--primary-blue);
        }

        .filter-select {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            font-size: 0.95rem;
            background: var(--bg-white);
            cursor: pointer;
        }

        .filter-select:focus {
            outline: none;
            border-color: var(--primary-blue);
        }

        /* Table Styles */
        .table-container {
            background: var(--bg-white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .table-responsive {
            overflow-x: auto;
        }

        .users-table {
            width: 100%;
            border-collapse: collapse;
        }

        .users-table th {
            background: var(--primary-blue);
            padding: 1rem 1.5rem;
            text-align: left;
            font-weight: 600;
            color: white;
            font-size: 0.9rem;
        }

        .users-table th i {
            margin-right: 0.5rem;
        }

        .users-table td {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.9rem;
        }

        .users-table tr:hover {
            background: var(--bg-light);
        }

        /* User Info */
        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        .user-details {
            flex: 1;
        }

        .user-name {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.125rem;
        }

        .user-email {
            font-size: 0.8rem;
            color: var(--text-light);
        }

        .role-badge-small {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            background: var(--light-blue);
            color: var(--primary-blue);
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        /* Action Buttons */
        .action-btns {
            display: flex;
            gap: 0.5rem;
        }

        .action-btn {
            width: 32px;
            height: 32px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-light);
            color: var(--text-light);
        }

        .action-btn.edit-btn:hover {
            background: var(--primary-blue);
            color: white;
        }

        .action-btn.delete-btn:hover {
            background: var(--error-red);
            color: white;
        }

        /* Cards Grid for Roles */
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
        }

        .role-card {
            background: var(--bg-white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            transition: var(--transition);
            border: 1px solid var(--border-color);
        }

        .role-card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }

        .role-card.protected {
            border-left: 4px solid var(--error-red);
        }

        .role-card-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .role-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .role-title i {
            font-size: 1.2rem;
            color: var(--primary-blue);
        }

        .role-title h3 {
            font-size: 1.1rem;
            font-weight: 600;
        }

        .permission-count {
            padding: 0.25rem 0.75rem;
            background: var(--light-blue);
            color: var(--primary-blue);
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .protected-badge {
            display: flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.25rem 0.75rem;
            background: #fee2e2;
            color: var(--error-red);
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .role-card-body {
            padding: 1.5rem;
        }

        .role-description {
            color: var(--text-light);
            font-size: 0.9rem;
            margin-bottom: 1rem;
            line-height: 1.5;
        }

        .teacher-count {
            color: var(--text-light);
            font-size: 0.85rem;
            margin-bottom: 1rem;
        }

        .teacher-count i {
            color: var(--primary-blue);
        }

        .permissions-list {
            list-style: none;
            margin: 0;
            padding: 0;
            max-height: 150px;
            overflow-y: auto;
        }

        .permissions-list li {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0;
            font-size: 0.85rem;
            border-bottom: 1px dashed var(--border-color);
        }

        .permissions-list li i {
            width: 20px;
            color: var(--success-green);
        }

        .role-card-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
            background: var(--bg-light);
        }

        .role-action-btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .role-action-btn.edit-btn {
            background: var(--light-blue);
            color: var(--primary-blue);
        }

        .role-action-btn.edit-btn:hover {
            background: var(--primary-blue);
            color: white;
        }

        .role-action-btn.permissions-btn {
            background: linear-gradient(135deg, #8b5cf6, #6d28d9);
            color: white;
        }

        .role-action-btn.permissions-btn:hover {
            background: linear-gradient(135deg, #7c3aed, #5b21b6);
        }

        .role-action-btn.delete-btn {
            background: #fee2e2;
            color: var(--error-red);
        }

        .role-action-btn.delete-btn:hover {
            background: var(--error-red);
            color: white;
        }

        .role-action-btn.protected-btn {
            background: #9ca3af;
            color: white;
            cursor: not-allowed;
        }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            backdrop-filter: blur(4px);
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal {
            background: var(--bg-white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-xl);
            width: 100%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--border-color);
            background: var(--primary-blue);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .close-modal {
            background: rgba(255, 255, 255, 0.1);
            border: none;
            font-size: 1.25rem;
            color: white;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 6px;
            width: 36px;
            height: 36px;
        }

        .close-modal:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .modal-body {
            padding: 2rem;
        }

        .modal-footer {
            padding: 1.5rem 2rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            background: var(--bg-light);
        }

        .modal-btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .modal-btn-primary {
            background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
            color: white;
        }

        .modal-btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow);
        }

        .modal-btn-secondary {
            background: var(--bg-light);
            color: var(--text-dark);
            border: 1px solid var(--border-color);
        }

        .modal-btn-secondary:hover {
            background: var(--border-color);
        }

        /* Permissions Grid */
        .permissions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1rem;
        }

        .permission-item {
            background: var(--bg-light);
            border-radius: 8px;
            padding: 1rem;
            border: 1px solid var(--border-color);
        }

        .permission-category {
            font-weight: 600;
            color: var(--primary-blue);
            margin-bottom: 0.75rem;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .permission-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .permission-action {
            display: flex;
            align-items: center;
            gap: 0.375rem;
            font-size: 0.85rem;
        }

        .permission-action input[type="checkbox"] {
            width: 16px;
            height: 16px;
            cursor: pointer;
            accent-color: var(--primary-blue);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-light);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .loading-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-light);
        }

        .loading-state i {
            font-size: 3rem;
            color: var(--primary-blue);
            margin-bottom: 1rem;
        }

        /* Toast */
        .toast-container {
            position: fixed;
            top: 80px;
            right: 1rem;
            z-index: 1100;
        }

        .toast {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            box-shadow: var(--shadow-lg);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            transform: translateX(400px);
            opacity: 0;
            transition: all 0.3s ease;
            max-width: 400px;
        }

        .toast.show {
            transform: translateX(0);
            opacity: 1;
        }

        .toast-success {
            background: var(--success-green);
            color: white;
        }

        .toast-error {
            background: var(--error-red);
            color: white;
        }

        .toast-warning {
            background: var(--warning-orange);
            color: white;
        }

        .toast-info {
            background: var(--primary-blue);
            color: white;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .cards-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-bar {
                flex-direction: column;
            }
            
            .filter-group {
                width: 100%;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Main Content Area -->
    <main class="flex-1 p-4 md:p-6">
        <!-- Page Header -->
        <div class="page-header">
            <div class="header-left">
                <h1 class="page-title">
                    <i class="fas fa-user-tag"></i>
                    Role Management
                </h1>
                <span class="role-badge">
                    <i class="fas fa-<?php echo $isSuperAdmin ? 'crown' : 'user-tag'; ?>"></i>
                    <?php echo htmlspecialchars($currentUserRole ?? 'User'); ?>
                </span>
            </div>
            <p class="page-description">Manage user roles and permissions</p>
        </div>

        <?php if (!$permissionHelper->hasAnyPermission(['rolesView', 'rolesViewAll'])): ?>
            <div class="permission-denied">
                <i class="fas fa-lock"></i>
                <h3>Access Denied</h3>
                <p>You do not have permission to view roles.</p>
            </div>
        <?php else: ?>
<div style="display: flex; justify-content: flex-end; margin-bottom: 1.5rem;">
    <?php if ($canCreate): ?>
        <button class="btn btn-primary" id="createRoleBtn">
            <i class="fas fa-plus"></i>
            Create New Role
        </button>
    <?php endif; ?>
</div>
        <div class="toggle-container">
            <button class="toggle-btn active" id="toggleUsers">
                <i class="fas fa-users"></i>
                Users
            </button>
            <button class="toggle-btn" id="toggleRoles">
                <i class="fas fa-tags"></i>
                Roles
            </button>
        </div>

        <!-- Users Section -->
        <div class="content-section active" id="usersSection">
            <div class="filter-bar">
                <div class="filter-group">
                    <label class="filter-label">
                        <i class="fas fa-chalkboard-teacher"></i>
                        Select Teacher
                    </label>
                    <select class="filter-select" id="teacherFilter">
                        <option value="">All Teachers</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label">
                        <i class="fas fa-user-tag"></i>
                        Select Role
                    </label>
                    <select class="filter-select" id="roleFilter">
                        <option value="">All Roles</option>
                    </select>
                </div>
            </div>

            <div class="table-container">
                <div class="table-responsive">
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th><i class="fas fa-user"></i> Name</th>
                                <th><i class="fas fa-envelope"></i> Email</th>
                                <th><i class="fas fa-user-tag"></i> Role</th>
                                <th><i class="fas fa-cogs"></i> Actions</th>
                            </tr>
                        </thead>
                        <tbody id="usersTableBody">
                            <tr><td colspan="4"><div class="loading-state"><i class="fas fa-spinner fa-spin"></i><p>Loading users...</p></div></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Roles Section -->
        <div class="content-section" id="rolesSection">
            <div class="cards-grid" id="rolesCardsContainer">
                <div class="loading-state"><i class="fas fa-spinner fa-spin"></i><p>Loading roles...</p></div>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <!-- Permissions Modal -->
    <div class="modal-overlay" id="permissionsModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-cog"></i>
                    <span id="modalRoleName">Configure Permissions</span>
                </h3>
                <button class="close-modal" id="closePermissionsModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="permissionsForm">
                <div class="modal-body">
                    <div class="permissions-grid" id="permissionsGridContainer">
                        <!-- Permissions will be loaded dynamically -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="modal-btn modal-btn-secondary" id="cancelPermissionsBtn">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="modal-btn modal-btn-primary">
                        <i class="fas fa-save"></i> Save Permissions
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>
<?php if ($canCreate || $canEdit): ?>
    <?php include '../../ajax/create_role_modal.php'; ?>
<?php endif; ?>
<script>
    // Permissions from PHP
    const PERMISSIONS = {
        canCreate: <?php echo $canCreate ? 'true' : 'false'; ?>,
        canEdit: <?php echo $canEdit ? 'true' : 'false'; ?>,
        canDelete: <?php echo $canDelete ? 'true' : 'false'; ?>,
        canViewAll: <?php echo $canViewAll ? 'true' : 'false'; ?>,
        isSuperAdmin: <?php echo $isSuperAdmin ? 'true' : 'false'; ?>
    };

    // DOM Elements
    const toggleUsers = document.getElementById('toggleUsers');
    const toggleRoles = document.getElementById('toggleRoles');
    const usersSection = document.getElementById('usersSection');
    const rolesSection = document.getElementById('rolesSection');
    const permissionsModal = document.getElementById('permissionsModal');
    const closePermissionsModal = document.getElementById('closePermissionsModal');
    const cancelPermissionsBtn = document.getElementById('cancelPermissionsBtn');
    const teacherFilter = document.getElementById('teacherFilter');
    const roleFilter = document.getElementById('roleFilter');
    const usersTableBody = document.getElementById('usersTableBody');
    const toastContainer = document.getElementById('toastContainer');
    const permissionsForm = document.getElementById('permissionsForm');

    let usersData = [];

    // Toast Notification
    function showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'}"></i>
            <span>${escapeHtml(message)}</span>
        `;
        
        toastContainer.appendChild(toast);
        setTimeout(() => toast.classList.add('show'), 10);
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Toggle between sections
    toggleUsers.addEventListener('click', () => {
        toggleUsers.classList.add('active');
        toggleRoles.classList.remove('active');
        usersSection.classList.add('active');
        rolesSection.classList.remove('active');
    });

    toggleRoles.addEventListener('click', () => {
        toggleRoles.classList.add('active');
        toggleUsers.classList.remove('active');
        rolesSection.classList.add('active');
        usersSection.classList.remove('active');
        fetchRoles();
    });

    const createRoleBtn = document.getElementById('createRoleBtn');
    if (createRoleBtn) {
        createRoleBtn.addEventListener('click', () => {
            if (typeof openCreateRoleModal === 'function') {
                openCreateRoleModal();
            }
        });
    }

    // Close modal
    function closePermissionsModalFunc() {
        permissionsModal.classList.remove('active');
        document.body.style.overflow = '';
    }

    closePermissionsModal.addEventListener('click', closePermissionsModalFunc);
    cancelPermissionsBtn.addEventListener('click', closePermissionsModalFunc);
    permissionsModal.addEventListener('click', (e) => {
        if (e.target === permissionsModal) closePermissionsModalFunc();
    });

    // Fetch users
    async function fetchUsers() {
        try {
            const response = await fetch('../../ajax/teacher.php?action=fetch_teachers');
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const result = await response.json();
            
            if (result.success) {
                usersData = result.data;
                renderUsersTable(usersData);
                populateFilters(usersData);
            } else {
                showToast(result.message || 'Failed to load users', 'error');
            }
        } catch (error) {
            usersTableBody.innerHTML = `<tr><td colspan="4"><div class="empty-state"><i class="fas fa-exclamation-triangle"></i><p>Error loading users</p></div></td></tr>`;
        }
    }

    function renderUsersTable(users) {
        if (users.length === 0) {
            usersTableBody.innerHTML = `<tr><td colspan="4"><div class="empty-state"><i class="fas fa-users"></i><p>No users found</p></div></td></tr>`;
            return;
        }

        usersTableBody.innerHTML = users.map(user => {
            const fullName = `${user.firstname || ''} ${user.middlename ? user.middlename + ' ' : ''}${user.lastname || ''}`.trim();
            const isProtected = user.role === 'Super Admin';
            
            return `
                <tr>
                    <td>
                        <div class="user-info">
                            <div class="user-avatar">${fullName.charAt(0) || 'U'}</div>
                            <div class="user-details">
                                <div class="user-name">${escapeHtml(fullName)}</div>
                                <div class="user-email">${escapeHtml(user.email)}</div>
                            </div>
                        </div>
                    </td>
                    <td>${escapeHtml(user.email)}</td>
                    <td><span class="role-badge-small">${escapeHtml(user.role || 'Not Assigned')}</span></td>
                    <td>
                        <div class="action-btns">
                            ${!isProtected && PERMISSIONS.canEdit ? `
                                <button class="action-btn edit-btn" onclick="editUser(${user.id})" title="Edit"><i class="fas fa-edit"></i></button>
                            ` : ''}
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    }

    function populateFilters(users) {
        teacherFilter.innerHTML = '<option value="">All Teachers</option>';
        users.forEach(user => {
            const name = `${user.firstname || ''} ${user.lastname || ''}`.trim();
            if (name) {
                teacherFilter.innerHTML += `<option value="${user.id}">${escapeHtml(name)}</option>`;
            }
        });

        roleFilter.innerHTML = '<option value="">All Roles</option>';
        const roles = [...new Set(users.map(u => u.role).filter(r => r))];
        roles.forEach(role => {
            roleFilter.innerHTML += `<option value="${escapeHtml(role)}">${escapeHtml(role)}</option>`;
        });
    }

    function filterUsers() {
        const selectedTeacher = teacherFilter.value;
        const selectedRole = roleFilter.value;
        
        let filtered = [...usersData];
        if (selectedTeacher) filtered = filtered.filter(u => u.id == selectedTeacher);
        if (selectedRole) filtered = filtered.filter(u => u.role === selectedRole);
        
        renderUsersTable(filtered);
    }

    teacherFilter.addEventListener('change', filterUsers);
    roleFilter.addEventListener('change', filterUsers);

    window.editUser = function(userId) {
        showToast(`Edit user ID: ${userId}`, 'info');
    };

    // Fetch roles
    async function fetchRoles() {
        const rolesContainer = document.getElementById('rolesCardsContainer');
        try {
            rolesContainer.innerHTML = '<div class="loading-state"><i class="fas fa-spinner fa-spin"></i><p>Loading roles...</p></div>';
            
            const response = await fetch('../../ajax/roles.php?action=fetch_roles');
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const result = await response.json();
            
            if (result.success) {
                renderRolesCards(result.data);
            } else {
                showToast(result.message || 'Failed to load roles', 'error');
            }
        } catch (error) {
            rolesContainer.innerHTML = `<div class="empty-state"><i class="fas fa-exclamation-triangle"></i><p>Error loading roles</p></div>`;
        }
    }

    function renderRolesCards(roles) {
        const rolesContainer = document.getElementById('rolesCardsContainer');
        
        if (!roles || roles.length === 0) {
            rolesContainer.innerHTML = `<div class="empty-state"><i class="fas fa-tags"></i><p>No roles found</p></div>`;
            return;
        }

        rolesContainer.innerHTML = roles.map(role => {
            const roleName = role.role_name || 'Unknown';
            const isProtected = role.is_protected === 1 || roleName === 'Super Admin';
            const permissionCount = role.permissions ? role.permissions.length : 0;
            
            let permissionsHtml = '';
            if (role.has_all_permissions) {
                permissionsHtml = '<li><i class="fas fa-check-circle"></i> Full system access - all permissions granted</li>';
            } else if (role.permissions && role.permissions.length > 0) {
                permissionsHtml = role.permissions.slice(0, 5).map(p => 
                    `<li><i class="fas fa-check-circle"></i> ${escapeHtml(p.description || p.permission_name || p)}</li>`
                ).join('');
                if (role.permissions.length > 5) {
                    permissionsHtml += `<li><i class="fas fa-ellipsis-h"></i> +${role.permissions.length - 5} more permissions</li>`;
                }
            } else {
                permissionsHtml = '<li><i class="fas fa-minus-circle"></i> No permissions assigned</li>';
            }

            return `
                <div class="role-card ${isProtected ? 'protected' : ''}">
                    <div class="role-card-header">
                        <div class="role-title">
                            <i class="fas fa-user-tag"></i>
                            <h3>${escapeHtml(roleName)}</h3>
                        </div>
                        <div class="permission-count">
                            <i class="fas fa-shield-alt"></i> ${role.has_all_permissions ? '∞' : permissionCount} Permissions
                        </div>
                        ${isProtected ? '<div class="protected-badge"><i class="fas fa-lock"></i> Protected</div>' : ''}
                    </div>
                    <div class="role-card-body">
                        <p class="role-description">${escapeHtml(role.description || `Role: ${roleName}`)}</p>
                        <p class="teacher-count"><i class="fas fa-users"></i> ${role.teacher_count || 0} Teacher(s)</p>
                        <ul class="permissions-list">
                            ${permissionsHtml}
                        </ul>
                    </div>
                    <div class="role-card-footer">
                        ${isProtected ? `
                            <button class="role-action-btn protected-btn" disabled><i class="fas fa-lock"></i> Protected</button>
                        ` : `
                            ${PERMISSIONS.canEdit ? `<button class="role-action-btn permissions-btn" onclick="openPermissionsModal('${escapeHtml(roleName)}', ${role.id || 0})"><i class="fas fa-cog"></i> Permissions</button>` : ''}
                            ${PERMISSIONS.canDelete ? `<button class="role-action-btn delete-btn" onclick="deleteRole(${role.id || 0}, '${escapeHtml(roleName)}')"><i class="fas fa-trash"></i> Delete</button>` : ''}
                        `}
                    </div>
                </div>
            `;
        }).join('');
    }

    window.openPermissionsModal = function(roleName, roleId) {
        if (!PERMISSIONS.canEdit && !PERMISSIONS.isSuperAdmin) {
            showToast('Access Denied', 'You do not have permission to configure permissions', 'error');
            return;
        }
        
        document.getElementById('modalRoleName').textContent = `Configure Permissions - ${roleName}`;
        
        let roleNameInput = document.getElementById('currentRoleName');
        if (!roleNameInput) {
            roleNameInput = document.createElement('input');
            roleNameInput.type = 'hidden';
            roleNameInput.name = 'role_name';
            roleNameInput.id = 'currentRoleName';
            permissionsForm.appendChild(roleNameInput);
        }
        roleNameInput.value = roleName;
        
        loadRolePermissions(roleName);
        permissionsModal.classList.add('active');
        document.body.style.overflow = 'hidden';
    };

    async function loadRolePermissions(roleName) {
        const permissionsGrid = document.getElementById('permissionsGridContainer');
        if (!permissionsGrid) {
            console.error('permissionsGridContainer not found');
            return;
        }
        
        permissionsGrid.innerHTML = '<div class="loading-state"><i class="fas fa-spinner fa-spin"></i><p>Loading permissions for ' + escapeHtml(roleName) + '...</p></div>';
        
        try {
            const response = await fetch(`../../ajax/roles.php?action=get_role_permissions&role_name=${encodeURIComponent(roleName)}`);
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const result = await response.json();
            
            console.log('Permissions loaded:', result);
            
            if (result.success) {
                rebuildPermissionsGrid(result.data);
            } else {
                permissionsGrid.innerHTML = `
                    <div style="text-align: center; padding: 2rem; grid-column: 1/-1;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 2rem; color: var(--error-red); margin-bottom: 1rem;"></i>
                        <p style="color: var(--error-red);">${escapeHtml(result.message || 'Failed to load permissions')}</p>
                    </div>
                `;
                showToast(result.message || 'Failed to load permissions', 'error');
            }
        } catch (error) {
            console.error('Load permissions error:', error);
            permissionsGrid.innerHTML = `
                <div style="text-align: center; padding: 2rem; grid-column: 1/-1;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 2rem; color: var(--error-red); margin-bottom: 1rem;"></i>
                    <p style="color: var(--error-red);">Error loading permissions: ${escapeHtml(error.message)}</p>
                    <button onclick="loadRolePermissions('${escapeHtml(roleName)}')" style="margin-top: 1rem; padding: 0.5rem 1rem; background: var(--primary-blue); color: white; border: none; border-radius: 8px; cursor: pointer;">
                        <i class="fas fa-redo"></i> Retry
                    </button>
                </div>
            `;
            showToast('Failed to load permissions: ' + error.message, 'error');
        }
    }

    function rebuildPermissionsGrid(permissionsData) {
        const permissionsGrid = document.getElementById('permissionsGridContainer');
        if (!permissionsGrid) {
            console.error('permissionsGridContainer not found');
            return;
        }
        
        const permissionCategories = {
            'classes': ['View', 'Create', 'Edit', 'Delete'],
            'teachers': ['View', 'Create', 'Edit', 'Delete'],
            'students': ['View', 'Create', 'Edit', 'Delete'],
            'subjects': ['View', 'Create', 'Edit', 'Delete'],
            'exam': ['View', 'Create', 'Edit', 'Delete'],
            'scores': ['View', 'Edit'],
            'reports': ['View', 'Generate', 'Print'],
            'roles': ['View', 'Create', 'Edit', 'Delete'],
            'lessons': ['View', 'Create', 'Edit', 'Delete'],
            'grading': ['View', 'Create', 'Edit', 'Delete'],
            'meritlist': ['View', 'Generate'],
            'analytics': ['View', 'Export'],
            'messaging': ['Send', 'View', 'Groups'],
            'promotions': ['View', 'Process'],
            'timetable': ['View', 'Create', 'Edit', 'Delete'],
            'attendance': ['View', 'Mark', 'Reports'],
            'utility': ['Settings', 'Backup', 'Logs']
        };

        let html = '<div class="permissions-grid">';
        
        for (const [category, actions] of Object.entries(permissionCategories)) {
            const hasAnyPermission = actions.some(action => {
                const permId = `${category}${action}`;
                return permissionsData && permissionsData[permId] === true;
            });
            
            const categoryDisplay = category.charAt(0).toUpperCase() + category.slice(1);
            
            html += `
                <div class="permission-item">
                    <div class="permission-category">
                        <i class="fas ${getCategoryIcon(category)}"></i> ${categoryDisplay}
                        ${hasAnyPermission ? '<span style="margin-left: auto; font-size: 0.7rem; background: var(--success-green); color: white; padding: 0.2rem 0.5rem; border-radius: 20px;">Enabled</span>' : ''}
                    </div>
                    <div class="permission-actions">
            `;
            
            actions.forEach(action => {
                const permId = `${category}${action}`;
                const isChecked = permissionsData && permissionsData[permId] === true ? 'checked' : '';
                
                html += `
                    <div class="permission-action">
                        <input type="checkbox" id="${permId}" name="permissions[]" value="${permId}" ${isChecked}>
                        <label for="${permId}">${action}</label>
                    </div>
                `;
            });
            
            html += `
                    </div>
                </div>
            `;
        }
        
        html += '</div>';
        
        if (Object.keys(permissionsData || {}).length === 0) {
            html = `
                <div style="text-align: center; padding: 2rem; grid-column: 1/-1;">
                    <i class="fas fa-info-circle" style="font-size: 2rem; color: var(--text-light); margin-bottom: 1rem;"></i>
                    <p style="color: var(--text-light);">No permissions configured for this role.</p>
                    <p style="color: var(--text-light); font-size: 0.85rem;">Check the boxes above to grant permissions.</p>
                </div>
            `;
        }
        
        permissionsGrid.innerHTML = html;
    }

    function getCategoryIcon(category) {
        const icons = {
            'classes': 'fa-school',
            'teachers': 'fa-chalkboard-teacher',
            'students': 'fa-user-graduate',
            'subjects': 'fa-book',
            'exam': 'fa-pencil-alt',
            'scores': 'fa-chart-line',
            'reports': 'fa-file-alt',
            'roles': 'fa-user-tag',
            'lessons': 'fa-video',
            'grading': 'fa-star',
            'meritlist': 'fa-trophy',
            'analytics': 'fa-chart-pie',
            'messaging': 'fa-comments',
            'promotions': 'fa-arrow-up',
            'timetable': 'fa-calendar-alt',
            'attendance': 'fa-calendar-check',
            'utility': 'fa-tools'
        };
        return icons[category] || 'fa-folder';
    }

    permissionsForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        if (!PERMISSIONS.canEdit && !PERMISSIONS.isSuperAdmin) {
            showToast('Access Denied', 'You do not have permission to save permissions', 'error');
            return;
        }
        
        const roleName = document.getElementById('currentRoleName')?.value;
        if (!roleName) {
            showToast('Error', 'Role name not found', 'error');
            return;
        }
        
        const permissions = {};
        document.querySelectorAll('#permissionsForm input[type="checkbox"]').forEach(cb => {
            permissions[cb.id] = cb.checked;
        });
        
        const submitBtn = permissionsForm.querySelector('button[type="submit"]');
        const originalHtml = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        submitBtn.disabled = true;
        
        try {
            const response = await fetch('../../ajax/roles.php?action=save_permissions', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ role_name: roleName, permissions: permissions })
            });
            
            const result = await response.json();
            if (result.success) {
                showToast('Permissions saved successfully!', 'success');
                closePermissionsModalFunc();
                fetchRoles();
            } else {
                showToast(result.message || 'Failed to save permissions', 'error');
            }
        } catch (error) {
            showToast('Failed to save permissions', 'error');
        } finally {
            submitBtn.innerHTML = originalHtml;
            submitBtn.disabled = false;
        }
    });

    window.deleteRole = function(roleId, roleName) {
        if (!PERMISSIONS.canDelete) {
            showToast('Access Denied', 'You do not have permission to delete roles', 'error');
            return;
        }
        
        if (confirm(`Are you sure you want to delete the role "${roleName}"? This action cannot be undone.`)) {
            fetch('ajax/roles.php?action=delete_role', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ role_id: roleId, role_name: roleName })
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    showToast('Role deleted successfully!', 'success');
                    fetchRoles();
                } else {
                    showToast(result.message || 'Failed to delete role', 'error');
                }
            })
            .catch(() => showToast('Failed to delete role', 'error'));
        }
    };

    // Escape key handler
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closePermissionsModalFunc();
        }
    });

    // Initialize
    document.addEventListener('DOMContentLoaded', () => {
        fetchUsers();
        fetchRoles();
    });
</script>
</body>
</html>