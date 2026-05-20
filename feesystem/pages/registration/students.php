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

// Check if user has permission to view students page
$permissionHelper->requireAnyPermission(['studentsView', 'studentsViewAll'], '../../dashboard.php');

// Current academic level
$current_level = $_SESSION['academic_level'] ?? 'Primary';

// Determine which actions are allowed based on permissions
$canCreate = $permissionHelper->hasPermission('studentsCreate');
$canEdit = $permissionHelper->hasPermission('studentsEdit');
$canDelete = $permissionHelper->hasPermission('studentsDelete');
$canImport = $permissionHelper->hasPermission('studentsImport');
$canExport = $permissionHelper->hasPermission('studentsExport');
$canViewAll = $permissionHelper->hasPermission('studentsViewAll');
$isSuperAdmin = $permissionHelper->isSuperAdmin();

// ---------- Handle AJAX request for streams ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'get_streams') {
    header('Content-Type: application/json');

    if (empty($_POST['class_id'])) {
        echo json_encode(['success' => false, 'message' => 'Class ID is required']);
        exit;
    }

    $class_id = (int)$_POST['class_id'];

    try {
        $streams_query = "SELECT id, stream_name 
                          FROM tblstreams 
                          WHERE school_id = :school_id AND class_id = :class_id 
                          ORDER BY stream_name";
        $stmt = $db->prepare($streams_query);
        $stmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
        $stmt->bindParam(':class_id', $class_id, PDO::PARAM_INT);
        $stmt->execute();
        $streams = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'streams' => $streams]);
    } catch (PDOException $e) {
        error_log("Streams fetch error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error fetching streams']);
    }
    exit;
}

// ---------- Fetch classes for page rendering ----------
$classes = [];
try {
    // Map the academic level to match database values
    $db_academic_level = '';
    switch (strtolower($current_level)) {
        case 'primary':
        case 'primary school':
            $db_academic_level = 'primary';
            break;
        case 'junior_secondary':
        case 'junior secondary':
            $db_academic_level = 'junior_secondary';
            break;
        case 'senior_secondary':
        case 'senior secondary':
        case 'secondary':
            $db_academic_level = 'secondary';
            break;
        default:
            $db_academic_level = 'primary';
    }
    
    $classes_query = "SELECT id, class_level 
                      FROM tblclasses 
                      WHERE school_id = :school_id AND academic_level = :academic_level 
                      ORDER BY class_level";
    $stmt = $db->prepare($classes_query);
    $stmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
    $stmt->bindParam(':academic_level', $db_academic_level);
    $stmt->execute();
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($classes)) {
        error_log("No classes found for school_id: $school_id, academic_level: $db_academic_level");
    }
} catch (PDOException $e) {
    error_log("Classes fetch error: " . $e->getMessage());
}

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>EduScore - Students Management</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-blue: #1A73E8;
            --secondary-blue: #1976D2;
            --dark-blue: #0D47A1;
            --light-blue: #E8F0FE;
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
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: #f3f4f6;
            overflow-x: hidden;
        }

        /* Main Content Area - Responsive */
        .main-content {
            flex: 1;
            padding: 1rem;
            max-width: 100%;
            overflow-x: hidden;
        }

        @media (min-width: 640px) {
            .main-content {
                padding: 1.25rem;
            }
        }

        @media (min-width: 768px) {
            .main-content {
                padding: 1.5rem;
            }
        }

        @media (min-width: 1024px) {
            .main-content {
                padding: 2rem;
            }
        }

        /* Page Header - Responsive */
        .page-header {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
            gap: 1rem;
        }

        @media (min-width: 640px) {
            .page-header {
                flex-direction: row;
                align-items: center;
                margin-bottom: 2rem;
            }
        }

        .page-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        @media (min-width: 640px) {
            .page-title {
                font-size: 1.6rem;
            }
        }

        @media (min-width: 768px) {
            .page-title {
                font-size: 1.8rem;
            }
        }

        .page-title i {
            color: var(--primary-blue);
            font-size: 1.3rem;
        }

        @media (min-width: 768px) {
            .page-title i {
                font-size: 1.5rem;
            }
        }

        .role-badge {
            background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
            color: white;
            padding: 0.4rem 0.8rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }

        @media (min-width: 640px) {
            .role-badge {
                padding: 0.5rem 1rem;
                font-size: 0.85rem;
            }
        }

        .header-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            width: 100%;
        }

        @media (min-width: 640px) {
            .header-actions {
                width: auto;
                gap: 1rem;
            }
        }

        /* Buttons - Touch Friendly */
        .btn {
            padding: 0.6rem 1rem;
            border: none;
            border-radius: 12px;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            text-decoration: none;
            min-height: 44px;
        }

        @media (min-width: 640px) {
            .btn {
                padding: 0.75rem 1.5rem;
                font-size: 0.95rem;
            }
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
            color: white;
        }

        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-outline {
            background: transparent;
            border: 2px solid var(--primary-blue);
            color: var(--primary-blue);
        }

        .btn-outline:hover:not(:disabled) {
            background: var(--primary-blue);
            color: white;
        }

        .btn-secondary {
            background: var(--bg-light);
            border: 1px solid var(--border-color);
            color: var(--text-dark);
        }

        /* Form Card - Responsive */
        .form-card {
            background: var(--bg-white);
            border-radius: 20px;
            box-shadow: var(--shadow-xl);
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            border: 1px solid var(--border-color);
        }

        @media (min-width: 640px) {
            .form-card {
                padding: 1.5rem;
                margin-bottom: 2rem;
            }
        }

        @media (min-width: 768px) {
            .form-card {
                padding: 2rem;
            }
        }

        .form-header {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--light-blue);
            gap: 1rem;
        }

        @media (min-width: 640px) {
            .form-header {
                flex-direction: row;
                align-items: center;
                margin-bottom: 2rem;
                padding-bottom: 1.5rem;
            }
        }

        .form-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        @media (min-width: 640px) {
            .form-title {
                font-size: 1.35rem;
            }
        }

        @media (min-width: 768px) {
            .form-title {
                font-size: 1.5rem;
            }
        }

        .mode-switcher {
            display: flex;
            background: var(--light-blue);
            border-radius: 12px;
            padding: 0.25rem;
            gap: 0.25rem;
            width: 100%;
        }

        @media (min-width: 480px) {
            .mode-switcher {
                width: auto;
            }
        }

        .mode-btn {
            flex: 1;
            padding: 0.6rem 1rem;
            border: none;
            border-radius: 8px;
            background: transparent;
            color: var(--text-dark);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-size: 0.8rem;
            min-height: 40px;
        }

        @media (min-width: 640px) {
            .mode-btn {
                padding: 0.75rem 1.5rem;
                font-size: 0.9rem;
            }
        }

        .mode-btn.active {
            background: var(--primary-blue);
            color: white;
            box-shadow: var(--shadow);
        }

        /* Form Sections - Responsive */
        .form-section {
            background: var(--light-blue);
            border-radius: 16px;
            padding: 1rem;
            border-left: 4px solid var(--primary-blue);
        }

        @media (min-width: 640px) {
            .form-section {
                padding: 1.25rem;
            }
        }

        @media (min-width: 768px) {
            .form-section {
                padding: 1.5rem;
            }
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        @media (min-width: 640px) {
            .section-header {
                margin-bottom: 1.5rem;
            }
        }

        .section-icon {
            width: 36px;
            height: 36px;
            background: var(--primary-blue);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1rem;
        }

        @media (min-width: 640px) {
            .section-icon {
                width: 40px;
                height: 40px;
                font-size: 1.2rem;
            }
        }

        .section-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-dark);
        }

        @media (min-width: 640px) {
            .section-title {
                font-size: 1.1rem;
            }
        }

        @media (min-width: 768px) {
            .section-title {
                font-size: 1.2rem;
            }
        }

        /* Form Grid - Fully Responsive */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
        }

        @media (min-width: 480px) {
            .form-grid {
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                gap: 1.25rem;
            }
        }

        @media (min-width: 768px) {
            .form-grid {
                gap: 1.5rem;
            }
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }

        .form-label {
            font-weight: 600;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.85rem;
        }

        @media (min-width: 640px) {
            .form-label {
                font-size: 0.9rem;
            }
        }

        .required::after {
            content: '*';
            color: var(--error-red);
            margin-left: 0.25rem;
        }

        .form-control {
            padding: 0.6rem 0.8rem;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            background: var(--bg-white);
            color: var(--text-dark);
            width: 100%;
            min-height: 42px;
        }

        @media (min-width: 640px) {
            .form-control {
                padding: 0.75rem 1rem;
                font-size: 1rem;
            }
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(26, 115, 232, 0.1);
        }

        /* Photo Upload - Responsive */
        .photo-upload-area {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem;
            border: 2px dashed var(--border-color);
            border-radius: 16px;
            background: var(--bg-white);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        @media (min-width: 640px) {
            .photo-upload-area {
                padding: 1.5rem;
                gap: 1rem;
            }
        }

        @media (min-width: 768px) {
            .photo-upload-area {
                padding: 2rem;
            }
        }

        .photo-preview {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--light-blue);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border: 3px solid var(--primary-blue);
        }

        @media (min-width: 640px) {
            .photo-preview {
                width: 100px;
                height: 100px;
            }
        }

        @media (min-width: 768px) {
            .photo-preview {
                width: 120px;
                height: 120px;
            }
        }

        .photo-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .photo-preview i {
            font-size: 2rem;
            color: var(--primary-blue);
        }

        @media (min-width: 640px) {
            .photo-preview i {
                font-size: 2.5rem;
            }
        }

        @media (min-width: 768px) {
            .photo-preview i {
                font-size: 3rem;
            }
        }

        .upload-text h4 {
            font-weight: 600;
            margin-bottom: 0.25rem;
            color: var(--text-dark);
            font-size: 0.9rem;
        }

        @media (min-width: 640px) {
            .upload-text h4 {
                font-size: 1rem;
            }
        }

        .upload-text p {
            color: var(--text-light);
            font-size: 0.75rem;
        }

        @media (min-width: 640px) {
            .upload-text p {
                font-size: 0.85rem;
            }
        }

        /* Form Actions - Responsive */
        .form-actions {
            display: flex;
            flex-direction: column-reverse;
            justify-content: center;
            gap: 0.75rem;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 2px solid var(--light-blue);
        }

        @media (min-width: 480px) {
            .form-actions {
                flex-direction: row;
                justify-content: flex-end;
            }
        }

        @media (min-width: 640px) {
            .form-actions {
                gap: 1rem;
                margin-top: 2rem;
                padding-top: 2rem;
            }
        }

        .btn-lg {
            padding: 0.6rem 1.2rem;
            font-size: 0.9rem;
        }

        @media (min-width: 640px) {
            .btn-lg {
                padding: 0.8rem 1.6rem;
                font-size: 1rem;
            }
        }

        @media (min-width: 768px) {
            .btn-lg {
                padding: 1rem 2rem;
                font-size: 1.1rem;
            }
        }

        /* Modal - Responsive */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(5px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            padding: 1rem;
        }

        .modal-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        .modal-content {
            background: var(--bg-white);
            border-radius: 20px;
            box-shadow: var(--shadow-xl);
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            overflow: hidden;
            transform: scale(0.9);
            transition: transform 0.3s ease;
        }

        @media (min-width: 640px) {
            .modal-content {
                width: 500px;
            }
        }

        .modal-overlay.show .modal-content {
            transform: scale(1);
        }

        .modal-header {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        @media (min-width: 640px) {
            .modal-header {
                padding: 1.25rem;
            }
        }

        @media (min-width: 768px) {
            .modal-header {
                padding: 1.5rem;
            }
        }

        .modal-title {
            font-size: 1.1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        @media (min-width: 640px) {
            .modal-title {
                font-size: 1.2rem;
            }
        }

        @media (min-width: 768px) {
            .modal-title {
                font-size: 1.3rem;
            }
        }

        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 1.3rem;
            cursor: pointer;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.3s ease;
        }

        @media (min-width: 640px) {
            .modal-close {
                font-size: 1.5rem;
                width: 40px;
                height: 40px;
            }
        }

        .modal-body {
            padding: 1rem;
        }

        @media (min-width: 640px) {
            .modal-body {
                padding: 1.25rem;
            }
        }

        @media (min-width: 768px) {
            .modal-body {
                padding: 1.5rem;
            }
        }

        /* Search Input */
        .search-input-group {
            position: relative;
            margin-bottom: 1rem;
        }

        .search-input {
            width: 100%;
            padding: 0.6rem 1rem 0.6rem 2.3rem;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            font-size: 0.9rem;
            min-height: 42px;
        }

        @media (min-width: 640px) {
            .search-input {
                padding: 0.75rem 1rem 0.75rem 2.5rem;
                font-size: 1rem;
            }
        }

        .search-icon {
            position: absolute;
            left: 0.8rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
            font-size: 0.9rem;
        }

        @media (min-width: 640px) {
            .search-icon {
                left: 1rem;
                font-size: 1rem;
            }
        }

        /* Search Results */
        .search-results {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid var(--border-color);
            border-radius: 12px;
        }

        .search-result-item {
            padding: 0.75rem;
            border-bottom: 1px solid var(--border-color);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            transition: background 0.2s;
        }

        @media (min-width: 640px) {
            .search-result-item {
                padding: 1rem;
                gap: 1rem;
            }
        }

        .student-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--light-blue);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-blue);
            font-weight: 600;
            flex-shrink: 0;
        }

        @media (min-width: 640px) {
            .student-avatar {
                width: 40px;
                height: 40px;
            }
        }

        .student-info {
            flex: 1;
            min-width: 0;
        }

        .student-name {
            font-weight: 600;
            margin-bottom: 0.25rem;
            font-size: 0.85rem;
            word-break: break-word;
        }

        @media (min-width: 640px) {
            .student-name {
                font-size: 0.9rem;
            }
        }

        .student-details {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            font-size: 0.7rem;
            color: var(--text-light);
        }

        @media (min-width: 640px) {
            .student-details {
                font-size: 0.8rem;
                gap: 1rem;
            }
        }

        /* Toast Container - Responsive */
        .toast-container {
            position: fixed;
            top: 70px;
            right: 0.5rem;
            left: 0.5rem;
            z-index: 1100;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
        }

        @media (min-width: 640px) {
            .toast-container {
                top: 80px;
                right: 1rem;
                left: auto;
            }
        }

        .toast {
            padding: 0.75rem 1rem;
            border-radius: 12px;
            box-shadow: var(--shadow-lg);
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            transform: translateX(400px);
            opacity: 0;
            transition: all 0.3s ease;
            max-width: calc(100vw - 1rem);
            font-size: 0.85rem;
        }

        @media (min-width: 640px) {
            .toast {
                padding: 1rem 1.5rem;
                max-width: 400px;
                font-size: 0.9rem;
            }
        }

        /* Permission Denied */
        .permission-denied {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: var(--error-red);
            padding: 1.5rem;
            border-radius: 16px;
            text-align: center;
        }

        @media (min-width: 640px) {
            .permission-denied {
                padding: 2rem;
            }
        }

        .permission-denied i {
            font-size: 2rem;
            margin-bottom: 0.75rem;
        }

        @media (min-width: 640px) {
            .permission-denied i {
                font-size: 3rem;
                margin-bottom: 1rem;
            }
        }

        /* Touch-friendly adjustments */
        @media (hover: none) and (pointer: coarse) {
            .btn, .mode-btn, .modal-close, .search-result-item {
                -webkit-tap-highlight-color: transparent;
            }
            
            .btn:active {
                transform: scale(0.97);
            }
        }

        /* Landscape mode adjustments */
        @media (max-width: 768px) and (orientation: landscape) {
            .form-card {
                margin-bottom: 1rem;
            }
            
            .form-section {
                padding: 0.75rem;
            }
            
            .section-header {
                margin-bottom: 0.75rem;
            }
        }

        /* Small devices */
        @media (max-width: 360px) {
            .page-title {
                font-size: 1.2rem;
            }
            
            .form-title {
                font-size: 1.1rem;
            }
            
            .mode-btn {
                font-size: 0.7rem;
                padding: 0.5rem 0.75rem;
            }
        }

        /* Spinner */
        .spinner-small {
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 2px solid var(--border-color);
            border-top-color: var(--primary-blue);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 0.5rem;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        @keyframes popIn {
            0% { opacity: 0; transform: scale(0.8) translateY(20px); }
            100% { opacity: 1; transform: scale(1) translateY(0); }
        }

        .text-muted {
            color: var(--text-light);
            font-size: 0.7rem;
        }

        @media (min-width: 640px) {
            .text-muted {
                font-size: 0.75rem;
            }
        }

        /* Utility */
        .hidden {
            display: none !important;
        }
        
        .flex-1 {
            flex: 1;
        }
    </style>
</head>
<body>
    <!-- Main Content Area -->
    <main class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                <h1 class="page-title">
                    <i class="fas fa-user-graduate"></i>
                    Students Management
                </h1>
                <span class="role-badge">
                    <i class="fas fa-<?php echo $isSuperAdmin ? 'crown' : 'user-tag'; ?>"></i>
                    <?php echo htmlspecialchars($permissionHelper->getRole() ?? 'User'); ?>
                </span>
            </div>
            <div class="header-actions">
                <?php if ($canImport): ?>
                    <button class="btn btn-primary" id="importStudentsBtn">
                        <i class="fas fa-file-import"></i>
                        <span>Import Students</span>
                    </button>
                <?php endif; ?>
                <?php if ($canCreate || $canEdit): ?>
                    <button class="btn btn-outline" id="clearFormBtn">
                        <i class="fas fa-broom"></i>
                        <span>Clear Form</span>
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!$permissionHelper->hasAnyPermission(['studentsView', 'studentsViewAll'])): ?>
            <!-- Permission Denied -->
            <div class="permission-denied">
                <i class="fas fa-lock"></i>
                <h3>Access Denied</h3>
                <p>You do not have permission to view students.</p>
                <p style="font-size: 0.85rem; margin-top: 0.5rem;">Please contact your system administrator if you need access.</p>
            </div>
        <?php else: ?>
            <!-- Student Form Card -->
            <div class="form-card">
                <div class="form-header">
                    <h2 class="form-title">
                        <i class="fas fa-user-plus"></i>
                        <span id="formTitle">Add New Student</span>
                    </h2>
                    <?php if ($canCreate || $canEdit): ?>
                        <div class="mode-switcher">
                            <button class="mode-btn <?php echo $canCreate ? 'active' : ''; ?>" id="addModeBtn" <?php echo !$canCreate ? 'disabled' : ''; ?>>
                                <i class="fas fa-plus"></i>
                                <span>Add Student</span>
                            </button>
                            <button class="mode-btn" id="editModeBtn" <?php echo !$canEdit ? 'disabled' : ''; ?>>
                                <i class="fas fa-edit"></i>
                                <span>Edit Student</span>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

                <form id="studentForm" enctype="multipart/form-data">
                    <input type="hidden" id="studentId" name="student_id">
                    
                    <div class="form-content">
                        <!-- Photo Upload Section -->
                        <div class="form-section">
                            <div class="section-header">
                                <div class="section-icon">
                                    <i class="fas fa-camera"></i>
                                </div>
                                <h3 class="section-title">Student Photo</h3>
                            </div>
                            <div class="photo-upload-area <?php echo (!$canCreate && !$canEdit) ? 'disabled' : ''; ?>" onclick="<?php echo ($canCreate || $canEdit) ? "document.getElementById('photoInput').click()" : ''; ?>">
                                <div class="photo-preview" id="photoPreview">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div class="upload-text">
                                    <h4>Upload Student Photo</h4>
                                    <p>Click to browse or drag and drop</p>
                                    <p class="text-muted">JPG, PNG, GIF - Max 2MB</p>
                                </div>
                                <input type="file" id="photoInput" name="photo" accept="image/*" style="display: none;" <?php echo (!$canCreate && !$canEdit) ? 'disabled' : ''; ?>>
                            </div>
                        </div>

                        <!-- Student Information -->
                        <div class="form-section">
                            <div class="section-header">
                                <div class="section-icon">
                                    <i class="fas fa-info-circle"></i>
                                </div>
                                <h3 class="section-title">Student Information</h3>
                            </div>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label required">
                                        <i class="fas fa-id-card"></i>
                                        Admission Number
                                    </label>
                                    <input type="text" class="form-control" id="admissionNo" name="admission_no" required 
                                           placeholder="Auto-generated" 
                                           <?php echo $canCreate ? 'readonly' : ''; ?>>
                                    <small class="text-muted">Automatically generated</small>
                                    <?php if ($canEdit): ?>
                                        <a class="search-by-name-link" id="searchByNameLink" style="display: none;">
                                            <i class="fas fa-search"></i>
                                            Search by name
                                        </a>
                                    <?php endif; ?>
                                </div>

                                <div class="form-group">
                                    <label class="form-label required">
                                        <i class="fas fa-user"></i>
                                        First Name
                                    </label>
                                    <input type="text" class="form-control" id="firstName" name="first_name" required 
                                           placeholder="Enter first name" <?php echo (!$canCreate && !$canEdit) ? 'disabled' : ''; ?>>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-user"></i>
                                        Middle Name
                                    </label>
                                    <input type="text" class="form-control" id="middleName" name="middle_name" 
                                           placeholder="Enter middle name" <?php echo (!$canCreate && !$canEdit) ? 'disabled' : ''; ?>>
                                </div>

                                <div class="form-group">
                                    <label class="form-label required">
                                        <i class="fas fa-user"></i>
                                        Last Name
                                    </label>
                                    <input type="text" class="form-control" id="lastName" name="last_name" required 
                                           placeholder="Enter last name" <?php echo (!$canCreate && !$canEdit) ? 'disabled' : ''; ?>>
                                </div>

                                <div class="form-group">
                                    <label class="form-label required">
                                        <i class="fas fa-calendar-check"></i>
                                        Date of Admission
                                    </label>
                                    <input type="date" class="form-control" id="admissionDate" name="admission_date" required 
                                           value="<?php echo date('Y-m-d'); ?>" <?php echo $canCreate ? 'readonly' : ''; ?> <?php echo (!$canCreate && !$canEdit) ? 'disabled' : ''; ?>>
                                    <small class="text-muted">Today's date (auto-filled)</small>
                                </div>

                                <div class="form-group">
                                    <label class="form-label required">
                                        <i class="fas fa-venus-mars"></i>
                                        Gender
                                    </label>
                                    <select class="form-control" id="gender" name="gender" required <?php echo (!$canCreate && !$canEdit) ? 'disabled' : ''; ?>>
                                        <option value="">Select Gender</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Class Information -->
                        <div class="form-section">
                            <div class="section-header">
                                <div class="section-icon">
                                    <i class="fas fa-school"></i>
                                </div>
                                <h3 class="section-title">Class Information</h3>
                            </div>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label required">
                                        <i class="fas fa-users-class"></i>
                                        Class
                                    </label>
                                    <select class="form-control" id="classSelect" name="class_id" required <?php echo (!$canCreate && !$canEdit) ? 'disabled' : ''; ?>>
                                        <option value="">Select Class</option>
                                        <?php foreach ($classes as $class): ?>
                                            <option value="<?php echo $class['id']; ?>">
                                                <?php echo htmlspecialchars($class['class_level']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-stream"></i>
                                        Stream
                                    </label>
                                    <select class="form-control" id="streamSelect" name="stream_id" <?php echo (!$canCreate && !$canEdit) ? 'disabled' : ''; ?>>
                                        <option value="">Select Stream</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Guardian Information -->
                        <div class="form-section">
                            <div class="section-header">
                                <div class="section-icon">
                                    <i class="fas fa-users"></i>
                                </div>
                                <h3 class="section-title">Guardian Information</h3>
                            </div>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-user-friends"></i>
                                        Guardian Name
                                    </label>
                                    <input type="text" class="form-control" id="guardianName" name="guardian_name" 
                                           placeholder="Enter guardian's full name" <?php echo (!$canCreate && !$canEdit) ? 'disabled' : ''; ?>>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-link"></i>
                                        Relation
                                    </label>
                                    <select class="form-control" id="guardianRelation" name="guardian_relation" <?php echo (!$canCreate && !$canEdit) ? 'disabled' : ''; ?>>
                                        <option value="">Select Relation</option>
                                        <option value="Father">Father</option>
                                        <option value="Mother">Mother</option>
                                        <option value="Guardian">Guardian</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-phone-alt"></i>
                                        Guardian Contact
                                    </label>
                                    <input type="tel" class="form-control" id="guardianContact" name="guardian_contact" 
                                           placeholder="Enter guardian's contact number" <?php echo (!$canCreate && !$canEdit) ? 'disabled' : ''; ?>>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-envelope-open"></i>
                                        Guardian Email
                                    </label>
                                    <input type="email" class="form-control" id="guardianEmail" name="guardian_email" 
                                           placeholder="Enter guardian's email address" <?php echo (!$canCreate && !$canEdit) ? 'disabled' : ''; ?>>
                                </div>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="form-actions">
                            <button type="button" class="btn btn-secondary" id="cancelBtn" <?php echo (!$canCreate && !$canEdit) ? 'disabled' : ''; ?>>
                                <i class="fas fa-times"></i>
                                <span>Cancel</span>
                            </button>
                            <button type="button" class="btn btn-primary btn-lg" id="saveStudentBtn" <?php echo (!$canCreate && !$canEdit) ? 'disabled' : ''; ?>>
                                <i class="fas fa-save"></i>
                                <span id="saveBtnText">Add Student</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </main>

    <?php if ($canEdit): ?>
    <!-- Search by Name Modal -->
    <div class="modal-overlay" id="searchByNameModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-search"></i>
                    Search Student by Name
                </h3>
                <button class="modal-close" id="closeSearchModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="search-input-group">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" class="search-input" id="studentSearchInput" 
                           placeholder="Type student name to search...">
                </div>
                <div class="search-results" id="searchResults">
                    <div class="no-results">
                        <i class="fas fa-search"></i>
                        <p>Start typing to search for students</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <script>
    // Global variables
    let currentMode = 'add';
    let currentStudentId = null;
    let searchTimeout = null;
    let isClearingForm = false;

    // DOM Elements
    const studentForm = document.getElementById('studentForm');
    const admissionNoInput = document.getElementById('admissionNo');
    const admissionDateInput = document.getElementById('admissionDate');
    const classSelect = document.getElementById('classSelect');
    const streamSelect = document.getElementById('streamSelect');
    const photoInput = document.getElementById('photoInput');
    const photoPreview = document.getElementById('photoPreview');
    const addModeBtn = document.getElementById('addModeBtn');
    const editModeBtn = document.getElementById('editModeBtn');
    const clearFormBtn = document.getElementById('clearFormBtn');
    const cancelBtn = document.getElementById('cancelBtn');
    const saveStudentBtn = document.getElementById('saveStudentBtn');
    const saveBtnText = document.getElementById('saveBtnText');
    const formTitle = document.getElementById('formTitle');
    const toastContainer = document.getElementById('toastContainer');
    const searchByNameLink = document.getElementById('searchByNameLink');
    const searchByNameModal = document.getElementById('searchByNameModal');
    const closeSearchModal = document.getElementById('closeSearchModal');
    const studentSearchInput = document.getElementById('studentSearchInput');
    const searchResults = document.getElementById('searchResults');
    const importStudentsBtn = document.getElementById('importStudentsBtn');

    // Permissions
    const PERMISSIONS = {
        canCreate: <?php echo $canCreate ? 'true' : 'false'; ?>,
        canEdit: <?php echo $canEdit ? 'true' : 'false'; ?>,
        canDelete: <?php echo $canDelete ? 'true' : 'false'; ?>,
        canImport: <?php echo $canImport ? 'true' : 'false'; ?>,
        canExport: <?php echo $canExport ? 'true' : 'false'; ?>,
        canViewAll: <?php echo $canViewAll ? 'true' : 'false'; ?>,
        isSuperAdmin: <?php echo $isSuperAdmin ? 'true' : 'false'; ?>
    };

    // Initialize page
    document.addEventListener('DOMContentLoaded', function() {
        if (PERMISSIONS.canCreate) {
            currentMode = 'add';
            if (addModeBtn) addModeBtn.classList.add('active');
            if (editModeBtn) editModeBtn.classList.remove('active');
            formTitle.textContent = 'Add New Student';
            saveBtnText.textContent = 'Add Student';
            admissionNoInput.readOnly = true;
            admissionDateInput.readOnly = true;
            admissionNoInput.placeholder = "Auto-generated";
            if (searchByNameLink) searchByNameLink.style.display = 'none';
            generateAdmissionNumber();
            setAdmissionDate();
        } else if (PERMISSIONS.canEdit) {
            currentMode = 'edit';
            if (addModeBtn) addModeBtn.disabled = true;
            if (editModeBtn) editModeBtn.classList.add('active');
            formTitle.textContent = 'Edit Student';
            saveBtnText.textContent = 'Update Student';
            admissionNoInput.readOnly = false;
            admissionDateInput.readOnly = false;
            admissionNoInput.placeholder = "Enter admission number to search...";
            if (searchByNameLink) searchByNameLink.style.display = 'inline-block';
        }
        
        setupEventListeners();
    });

    function setupEventListeners() {
        if (addModeBtn) addModeBtn.addEventListener('click', switchToAddMode);
        if (editModeBtn) editModeBtn.addEventListener('click', switchToEditMode);
        if (clearFormBtn) clearFormBtn.addEventListener('click', clearForm);
        if (cancelBtn) cancelBtn.addEventListener('click', cancelForm);
        if (saveStudentBtn) saveStudentBtn.addEventListener('click', saveStudent);
        if (photoInput) photoInput.addEventListener('change', handlePhotoUpload);
        if (classSelect) classSelect.addEventListener('change', function() { loadStreams(this.value); });
        if (admissionNoInput) admissionNoInput.addEventListener('input', function() {
            if (currentMode === 'edit' && this.value.length >= 3) {
                searchStudentByAdmission(this.value);
            }
        });
        if (searchByNameLink) searchByNameLink.addEventListener('click', openSearchModal);
        if (closeSearchModal) closeSearchModal.addEventListener('click', closeSearchModalHandler);
        if (studentSearchInput) studentSearchInput.addEventListener('input', handleSearchInput);
        if (importStudentsBtn) importStudentsBtn.addEventListener('click', function() {
            window.location.href = 'import_students.php';
        });
        
        // Close modal on outside click
        searchByNameModal.addEventListener('click', function(e) {
            if (e.target === searchByNameModal) {
                closeSearchModalHandler();
            }
        });
    }

    function switchToAddMode() {
        if (!PERMISSIONS.canCreate) {
            showToast('You do not have permission to add students', 'error');
            return;
        }
        clearForm();
        currentMode = 'add';
        if (addModeBtn) addModeBtn.classList.add('active');
        if (editModeBtn) editModeBtn.classList.remove('active');
        formTitle.textContent = 'Add New Student';
        saveBtnText.textContent = 'Add Student';
        admissionNoInput.readOnly = true;
        admissionDateInput.readOnly = true;
        admissionNoInput.placeholder = "Auto-generated";
        if (searchByNameLink) searchByNameLink.style.display = 'none';
        document.getElementById('studentId').value = '';
        currentStudentId = null;
        generateAdmissionNumber();
        setAdmissionDate();
        showToast('Switched to Add Student mode', 'success');
    }

    function switchToEditMode() {
        if (!PERMISSIONS.canEdit) {
            showToast('You do not have permission to edit students', 'error');
            return;
        }
        clearForm();
        currentMode = 'edit';
        if (editModeBtn) editModeBtn.classList.add('active');
        if (addModeBtn) addModeBtn.classList.remove('active');
        formTitle.textContent = 'Edit Student';
        saveBtnText.textContent = 'Update Student';
        admissionNoInput.readOnly = false;
        admissionDateInput.readOnly = false;
        admissionNoInput.placeholder = "Enter admission number to search...";
        if (searchByNameLink) searchByNameLink.style.display = 'inline-block';
        document.getElementById('studentId').value = '';
        currentStudentId = null;
        admissionNoInput.value = '';
        admissionNoInput.focus();
        showToast('Switched to Edit Student mode', 'info');
    }

    function generateAdmissionNumber() {
        if (currentMode === 'add' && PERMISSIONS.canCreate) {
            fetch('../../api_handlers/get_last_admission.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        admissionNoInput.value = data.next_admission;
                    } else {
                        admissionNoInput.value = '001';
                    }
                })
                .catch(error => {
                    admissionNoInput.value = '001';
                });
        }
    }

    function setAdmissionDate() {
        if (currentMode === 'add' && PERMISSIONS.canCreate) {
            const today = new Date().toISOString().split('T')[0];
            admissionDateInput.value = today;
        }
    }

    function searchStudentByAdmission(admissionNo) {
        if (!PERMISSIONS.canEdit) return;
        if (admissionNo.length < 3) return;
        
        fetch('../../api_handlers/get_student_by_admission.php?admission_no=' + encodeURIComponent(admissionNo))
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    populateFormWithStudentData(data.data);
                    showToast('Student data loaded successfully', 'success');
                } else {
                    clearForm();
                    showToast('Student not found', 'warning');
                }
            })
            .catch(error => {
                showToast('Error searching for student', 'error');
            });
    }

    function populateFormWithStudentData(student) {
        document.getElementById('studentId').value = student.id;
        document.getElementById('firstName').value = student.FirstName || '';
        document.getElementById('middleName').value = student.SecondName || '';
        document.getElementById('lastName').value = student.LastName || '';
        document.getElementById('admissionNo').value = student.AdmNo || '';
        document.getElementById('admissionDate').value = student.admission_date || new Date().toISOString().split('T')[0];
        document.getElementById('gender').value = student.Gender || '';
        document.getElementById('classSelect').value = student.class_id || '';
        document.getElementById('guardianName').value = student.GuardianName || '';
        document.getElementById('guardianRelation').value = student.GuardianRelationship || '';
        document.getElementById('guardianContact').value = student.GuardianPhone || '';
        document.getElementById('guardianEmail').value = student.GuardianEmail || '';
        
        if (student.ProfilePic && student.ProfilePic !== 'default.png') {
            photoPreview.innerHTML = `<img src="uploads/students/${student.ProfilePic}" alt="${student.FirstName}">`;
        } else {
            photoPreview.innerHTML = '<i class="fas fa-user"></i>';
        }
        
        if (student.class_id) {
            loadStreams(student.class_id, student.StreamId);
        }
        
        currentStudentId = student.id;
    }

    function clearForm() {
        if (!PERMISSIONS.canCreate && !PERMISSIONS.canEdit) return;
        if (isClearingForm) return;
        isClearingForm = true;
        
        studentForm.reset();
        photoPreview.innerHTML = '<i class="fas fa-user"></i>';
        streamSelect.innerHTML = '<option value="">Select Stream</option>';
        streamSelect.disabled = false;
        document.getElementById('studentId').value = '';
        currentStudentId = null;
        
        if (currentMode === 'add' && PERMISSIONS.canCreate) {
            generateAdmissionNumber();
            setAdmissionDate();
        } else if (currentMode === 'edit' && PERMISSIONS.canEdit) {
            admissionNoInput.value = '';
            admissionNoInput.placeholder = "Enter admission number to search...";
            admissionNoInput.readOnly = false;
            admissionDateInput.readOnly = false;
        }
        
        isClearingForm = false;
    }

    function cancelForm() {
        clearForm();
        showToast('Form cleared', 'info');
    }

    function handlePhotoUpload(e) {
        const file = e.target.files[0];
        if (file) {
            if (file.size > 2 * 1024 * 1024) {
                showToast('Image size must be less than 2MB', 'error');
                return;
            }
            const reader = new FileReader();
            reader.onload = function(e) {
                photoPreview.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
            };
            reader.readAsDataURL(file);
        }
    }

    function loadStreams(classId, selectedStreamId = null) {
        if (!classId) {
            streamSelect.innerHTML = '<option value="">Select Stream</option>';
            streamSelect.disabled = true;
            return;
        }
        
        streamSelect.innerHTML = '<option value="">Loading streams...</option>';
        streamSelect.disabled = true;
        
        const formData = new FormData();
        formData.append('action', 'get_streams');
        formData.append('class_id', classId);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.streams && data.streams.length > 0) {
                let options = '<option value="">Select Stream</option>';
                data.streams.forEach(stream => {
                    const selected = selectedStreamId && selectedStreamId == stream.id ? 'selected' : '';
                    options += `<option value="${stream.id}" ${selected}>${escapeHtml(stream.stream_name)}</option>`;
                });
                streamSelect.innerHTML = options;
            } else {
                streamSelect.innerHTML = '<option value="">No streams available</option>';
            }
            streamSelect.disabled = false;
        })
        .catch(error => {
            streamSelect.innerHTML = '<option value="">Error loading streams</option>';
            streamSelect.disabled = false;
        });
    }

    function saveStudent() {
        if (!PERMISSIONS.canCreate && !PERMISSIONS.canEdit) {
            showToast('You do not have permission to save students', 'error');
            return;
        }
        
        const formData = new FormData(studentForm);
        const studentId = document.getElementById('studentId').value;
        const isEdit = studentId !== '';
        
        if (isEdit && !PERMISSIONS.canEdit) {
            showToast('You do not have permission to edit students', 'error');
            return;
        }
        
        const apiEndpoint = isEdit ? '../../api_handlers/update_student.php' : '../../api_handlers/add_student.php';
        
        if (isEdit) formData.append('student_id', studentId);
        
        const requiredFields = ['first_name', 'last_name', 'admission_no', 'class_id', 'admission_date', 'gender'];
        let isValid = true;
        
        for (const field of requiredFields) {
            if (!formData.get(field)) {
                isValid = false;
                showToast(`Please fill in ${field.replace('_', ' ')}`, 'warning');
                break;
            }
        }
        
        if (!isValid) return;
        
        const originalBtnText = saveStudentBtn.innerHTML;
        saveStudentBtn.innerHTML = '<div class="spinner-small"></div> ' + (isEdit ? 'Updating...' : 'Saving...');
        saveStudentBtn.disabled = true;
        
        fetch(apiEndpoint, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                showSuccessPopup(data);
                if (!isEdit) {
                    setTimeout(() => {
                        clearForm();
                        generateAdmissionNumber();
                        setAdmissionDate();
                    }, 2000);
                }
            } else {
                showToast(data.message || 'Operation failed', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Failed to save student: ' + error.message, 'error');
        })
        .finally(() => {
            saveStudentBtn.innerHTML = originalBtnText;
            saveStudentBtn.disabled = false;
        });
    }

    // Search by name functionality
    function openSearchModal() {
        if (!PERMISSIONS.canEdit) return;
        searchByNameModal.classList.add('show');
        studentSearchInput.value = '';
        searchResults.innerHTML = `
            <div class="no-results">
                <i class="fas fa-search"></i>
                <p>Start typing to search for students</p>
            </div>
        `;
        setTimeout(() => studentSearchInput.focus(), 300);
    }

    function closeSearchModalHandler() {
        searchByNameModal.classList.remove('show');
    }

    function handleSearchInput(e) {
        const searchTerm = e.target.value.trim();
        
        if (searchTimeout) clearTimeout(searchTimeout);
        
        if (searchTerm.length >= 2) {
            searchResults.innerHTML = `
                <div class="loading-results">
                    <div class="spinner-small"></div>
                    Searching students...
                </div>
            `;
            searchTimeout = setTimeout(() => searchStudents(searchTerm), 500);
        } else if (searchTerm.length === 0) {
            searchResults.innerHTML = `
                <div class="no-results">
                    <i class="fas fa-search"></i>
                    <p>Start typing to search for students</p>
                </div>
            `;
        } else {
            searchResults.innerHTML = `
                <div class="no-results">
                    <i class="fas fa-info-circle"></i>
                    <p>Type at least 2 characters to search</p>
                </div>
            `;
        }
    }

    function searchStudents(searchTerm) {
        fetch(`../../api_handlers/search_students_by_name.php?search_term=${encodeURIComponent(searchTerm)}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displaySearchResults(data.students);
                } else {
                    searchResults.innerHTML = `
                        <div class="no-results">
                            <i class="fas fa-user-times"></i>
                            <p>${escapeHtml(data.message || 'No students found')}</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                searchResults.innerHTML = `
                    <div class="no-results">
                        <i class="fas fa-exclamation-triangle"></i>
                        <p>Search failed. Please try again.</p>
                    </div>
                `;
            });
    }

    function displaySearchResults(students) {
        if (students.length === 0) {
            searchResults.innerHTML = `
                <div class="no-results">
                    <i class="fas fa-user-times"></i>
                    <p>No students found matching your search</p>
                </div>
            `;
            return;
        }
        
        const resultsHTML = students.map(student => `
            <div class="search-result-item" data-student-id="${student.id}">
                <div class="student-avatar">
                    ${student.profile_pic && student.profile_pic !== 'default.png' 
                        ? `<img src="uploads/students/${student.profile_pic}" alt="${escapeHtml(student.name)}">`
                        : `<i class="fas fa-user"></i>`
                    }
                </div>
                <div class="student-info">
                    <div class="student-name">${escapeHtml(student.name)}</div>
                    <div class="student-details">
                        <span class="student-admission">${escapeHtml(student.admission_no)}</span>
                        <span>${escapeHtml(student.class)}</span>
                        <span>${escapeHtml(student.gender)}</span>
                    </div>
                </div>
            </div>
        `).join('');
        
        searchResults.innerHTML = resultsHTML;
        
        document.querySelectorAll('.search-result-item').forEach(item => {
            item.addEventListener('click', function() {
                const studentId = this.getAttribute('data-student-id');
                const student = students.find(s => s.id == studentId);
                if (student) selectStudentFromSearch(student);
            });
        });
    }

    function selectStudentFromSearch(student) {
        if (currentMode !== 'edit' && PERMISSIONS.canEdit) switchToEditMode();
        admissionNoInput.value = student.admission_no;
        searchStudentByAdmission(student.admission_no);
        searchByNameModal.classList.remove('show');
        showToast(`Selected student: ${student.name}`, 'success');
    }

    function showSuccessPopup(data) {
        const popupOverlay = document.createElement('div');
        popupOverlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            backdrop-filter: blur(5px);
        `;

        const popupContent = document.createElement('div');
        popupContent.style.cssText = `
            background: white;
            padding: 1.5rem;
            border-radius: 20px;
            text-align: center;
            max-width: 90%;
            width: 350px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: popIn 0.5s ease-out;
        `;

        popupContent.innerHTML = `
            <div style="width: 60px; height: 60px; border-radius: 50%; background: #10b981; margin: 0 auto 1rem; display: flex; align-items: center; justify-content: center;">
                <i class="fas fa-check" style="color: white; font-size: 2rem;"></i>
            </div>
            <h3 style="color: #10b981; margin-bottom: 0.5rem; font-size: 1.3rem;">Success!</h3>
            <p style="color: #374151; margin-bottom: 1rem; font-size: 0.9rem;">${escapeHtml(data.message || 'Operation completed successfully')}</p>
            <button class="btn btn-primary" style="padding: 0.6rem 1.2rem; font-size: 0.9rem;" onclick="this.closest('div').parentElement.remove()">Continue</button>
        `;
        
        popupOverlay.appendChild(popupContent);
        document.body.appendChild(popupOverlay);
        
        setTimeout(() => {
            if (document.body.contains(popupOverlay)) popupOverlay.remove();
        }, 3000);
    }

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
            setTimeout(() => { if (toast.parentNode) toast.parentNode.removeChild(toast); }, 300);
        }, 5000);
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    </script>
</body>
</html>