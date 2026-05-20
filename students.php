<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database/config
require_once 'includes/config.php';
require_once 'includes/PermissionHelper.php';
require_once 'includes/session_timeout.php'; 

// ✅ Authentication check
if (empty($_SESSION['authenticated']) || 
    empty($_SESSION['school_id']) || 
    empty($_SESSION['teacher_id'])) {
    header('Location: login.php');
    exit;
}

// Get school ID from session
$school_id = $_SESSION['school_id'] ?? null;
if (!$school_id) {
    die("School ID not found in session.");
}

// Initialize Permission Helper
$permissionHelper = new PermissionHelper($db, $school_id, $_SESSION['teacher_id']);

// Check if user has permission to view students page
$permissionHelper->requireAnyPermission(['studentsView', 'studentsViewAll'], 'dashboard.php');

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
    $school_id = $_SESSION['school_id'];

    try {
        // Fetch streams for the class
        $streams_query = "SELECT id, stream_name FROM tblstreams WHERE class_id = :class_id ORDER BY stream_name";
        $stmt = $db->prepare($streams_query);
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
try {
    $classes_query = "SELECT id, class_level 
                      FROM tblclasses 
                      WHERE school_id = :school_id AND academic_level = :academic_level 
                      ORDER BY class_level";
    $stmt = $db->prepare($classes_query);
    $stmt->bindParam(':school_id', $_SESSION['school_id'], PDO::PARAM_INT);
    $stmt->bindParam(':academic_level', $current_level);
    $stmt->execute();
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Classes fetch error: " . $e->getMessage());
    $classes = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EduScore - Modern School Management System</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="icon" type="image/png" href="images/logo.png" />
<link rel="apple-touch-icon" href="images/logo.png">
<style>
    /* Your existing CSS styles remain exactly the same */
    :root {
        --primary-blue: #1A73E8;
        --secondary-blue: #1976D2;
        --dark-blue: #0D47A1;
        --light-blue: #E8F0FE;
        --success-green: #10b981;
        --warning-orange: #f59e0b;
        --error-red: #ef4444;
        --text-dark: #000000;
        --text-light: #000000;
        --text-muted: #000000;
        --bg-light: #f9fafb;
        --bg-white: #ffffff;
        --border-color: #e5e7eb;
        --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        --shadow-xl: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        --gradient-primary: linear-gradient(135deg, #1A73E8 0%, #0D47A1 100%);
        --sidebar-width: 280px;
        --header-height: 70px;
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'Inter', sans-serif;
    }

    body {
        background: var(--bg-light);
        min-height: 100vh;
        display: flex;
        overflow-x: hidden;
    }

    .main-content {
        flex: 1;
        margin-left: var(--sidebar-width);
        min-height: 100vh;
        transition: margin-left 0.3s ease;
        position: relative;
        padding-top: var(--header-height);
        color: #000000;
    }

    .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 2rem;
        width: 100%;
        color: #000000;
    }

    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid var(--border-color);
        flex-wrap: wrap;
        gap: 1rem;
        color: #000000;
    }

    .students-page-title {
        font-size: clamp(1.5rem, 4vw, 2rem);
        font-weight: 700;
        color: black;
        display: flex;
        align-items: center;
        gap: 1rem;
        flex-shrink: 0;
    }

    .students-page-title i {
        color: black;
        font-size: clamp(1.8rem, 4vw, 2.2rem);
    }

    .header-actions {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
    }

    .btn {
        padding: 0.75rem 1.5rem;
        border: none;
        border-radius: 12px;
        font-size: 0.95rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        text-decoration: none;
        white-space: nowrap;
    }

    .btn-primary {
        background: var(--gradient-primary);
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

    .btn-outline:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .btn-secondary {
        background: var(--bg-light);
        color: var(--text-dark);
        border: 1px solid var(--border-color);
    }

    .btn-secondary:hover {
        background: var(--border-color);
    }

    .btn-danger {
        background: var(--error-red);
        color: white;
    }

    .btn-danger:hover:not(:disabled) {
        background: #dc2626;
        transform: translateY(-2px);
    }

    .btn-danger:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .btn-sm {
        padding: 0.25rem 0.75rem;
        font-size: 0.8rem;
    }

    .btn-lg {
        padding: 1rem 2rem;
        font-size: 1.1rem;
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
        margin-left: 1rem;
    }

    .role-badge i {
        font-size: 0.9rem;
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
        box-shadow: var(--shadow);
    }

    .permission-denied i {
        font-size: 3rem;
        margin-bottom: 1rem;
    }

    .permission-denied h3 {
        font-size: 1.25rem;
        margin-bottom: 0.5rem;
        color: var(--text-dark);
    }

    .permission-denied p {
        margin-bottom: 0.5rem;
    }

    /* Form Card */
    .form-card {
        background: var(--bg-white);
        border-radius: 20px;
        box-shadow: var(--shadow-xl);
        padding: 2.5rem;
        margin-bottom: 2rem;
        border: 1px solid var(--border-color);
        color: #000000;
    }

    .form-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        padding-bottom: 1.5rem;
        border-bottom: 2px solid var(--light-blue);
        color: #000000;
    }

    .form-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: #000000;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .form-title i {
        color: var(--primary-blue);
    }

    .mode-switcher {
        display: flex;
        background: var(--light-blue);
        border-radius: 12px;
        padding: 0.25rem;
        gap: 0.25rem;
    }

    .mode-btn {
        padding: 0.75rem 1.5rem;
        border: none;
        border-radius: 8px;
        background: transparent;
        color: #000000;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .mode-btn.active {
        background: var(--primary-blue);
        color: white;
        box-shadow: var(--shadow);
    }

    .mode-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .form-content {
        display: grid;
        gap: 2rem;
        color: #000000;
    }

    .form-section {
        background: var(--light-blue);
        border-radius: 16px;
        padding: 2rem;
        border-left: 4px solid var(--primary-blue);
        color: #000000;
    }

    .section-header {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 1.5rem;
        color: #000000;
    }

    .section-icon {
        width: 40px;
        height: 40px;
        background: var(--primary-blue);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.2rem;
    }

    .section-title {
        font-size: 1.2rem;
        font-weight: 700;
        color: #000000;
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1.5rem;
        color: #000000;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        color: #000000;
    }

    .form-label {
        font-weight: 600;
        color: #000000;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .required::after {
        content: '*';
        color: var(--error-red);
        margin-left: 0.25rem;
    }

    .form-control {
        padding: 0.875rem 1rem;
        border: 2px solid var(--border-color);
        border-radius: 12px;
        font-size: 1rem;
        transition: all 0.3s ease;
        background: var(--bg-white);
        color: #000000;
    }

    .form-control::placeholder {
        color: #666666;
    }

    .form-control:focus {
        outline: none;
        border-color: var(--primary-blue);
        box-shadow: 0 0 0 3px rgba(26, 115, 232, 0.1);
    }

    .form-control:read-only {
        background-color: #f8f9fa;
        border-color: #e9ecef;
        color: #000000;
        cursor: not-allowed;
    }

    .form-control:disabled {
        background: var(--bg-light);
        cursor: not-allowed;
        opacity: 0.6;
    }

    select.form-control {
        color: #000000;
    }

    select.form-control option {
        color: #000000;
        background: var(--bg-white);
    }

    .text-muted {
        color: #000000 !important;
    }

    small {
        color: #000000;
    }

    /* Photo Upload */
    .photo-upload-area {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 1rem;
        padding: 2rem;
        border: 2px dashed var(--border-color);
        border-radius: 16px;
        background: var(--bg-white);
        transition: all 0.3s ease;
        cursor: pointer;
        color: #000000;
    }

    .photo-upload-area:hover {
        border-color: var(--primary-blue);
        background: var(--light-blue);
    }

    .photo-upload-area.disabled {
        opacity: 0.5;
        cursor: not-allowed;
        pointer-events: none;
    }

    .photo-preview {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        background: var(--light-blue);
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        border: 3px solid var(--primary-blue);
        position: relative;
    }

    .photo-preview img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .photo-preview i {
        font-size: 3rem;
        color: var(--primary-blue);
    }

    .upload-text {
        text-align: center;
        color: #000000;
    }

    .upload-text h4 {
        font-weight: 600;
        margin-bottom: 0.5rem;
        color: #000000;
    }

    .upload-text p {
        color: #000000;
        font-size: 0.9rem;
    }

    /* Form Actions */
    .form-actions {
        display: flex;
        justify-content: flex-end;
        gap: 1rem;
        margin-top: 2rem;
        padding-top: 2rem;
        border-top: 2px solid var(--light-blue);
        color: #000000;
    }

    /* Toast */
    .toast-container {
        position: fixed;
        top: calc(var(--header-height) + 1rem);
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
        max-width: min(400px, 90vw);
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

    /* Loading States */
    .loading {
        opacity: 0.6;
        pointer-events: none;
    }

    .spinner {
        width: 20px;
        height: 20px;
        border: 2px solid transparent;
        border-top: 2px solid currentColor;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        display: inline-block;
    }

    .spinner-small {
        width: 16px;
        height: 16px;
        border: 2px solid transparent;
        border-top: 2px solid currentColor;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        display: inline-block;
        margin-right: 0.5rem;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }

    /* Search by name link */
    .search-by-name-link {
        display: inline-block;
        margin-top: 0.5rem;
        color: var(--primary-blue);
        text-decoration: none;
        font-size: 0.9rem;
        cursor: pointer;
        transition: color 0.3s ease;
    }

    .search-by-name-link:hover {
        color: var(--dark-blue);
        text-decoration: underline;
    }

    .search-by-name-link:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        pointer-events: none;
    }

    /* Modal styles */
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
    }

    .modal-overlay.show {
        opacity: 1;
        visibility: visible;
    }

    .modal-content {
        background: var(--bg-white);
        border-radius: 20px;
        box-shadow: var(--shadow-xl);
        width: min(90vw, 600px);
        max-height: 80vh;
        overflow: hidden;
        transform: scale(0.9);
        transition: transform 0.3s ease;
    }

    .modal-overlay.show .modal-content {
        transform: scale(1);
    }

    .modal-header {
        padding: 1.5rem 2rem;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: var(--gradient-primary);
        color: white;
    }

    .modal-title {
        font-size: 1.3rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .modal-close {
        background: none;
        border: none;
        color: white;
        font-size: 1.5rem;
        cursor: pointer;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background 0.3s ease;
    }

    .modal-close:hover {
        background: rgba(255, 255, 255, 0.2);
    }

    .modal-body {
        padding: 2rem;
        color: #000000 !important;
        overflow-y: auto;
    }

    .modal-footer {
        padding: 1.5rem 2rem;
        border-top: 1px solid var(--border-color);
        display: flex;
        gap: 1rem;
        justify-content: flex-end;
    }

    /* Search Results */
    .students-search-input-group {
        position: relative;
        margin-bottom: 1.5rem;
    }

    .students-search-input {
        width: 100%;
        padding: 1rem 1rem 1rem 3rem;
        border: 2px solid var(--border-color);
        border-radius: 12px;
        font-size: 1rem;
        transition: all 0.3s ease;
        background: var(--bg-white);
        color: #000000 !important;
    }

    .students-search-input::placeholder {
        color: #666666 !important;
    }

    .students-search-input:focus {
        outline: none;
        border-color: var(--primary-blue);
        box-shadow: 0 0 0 3px rgba(26, 115, 232, 0.1);
    }

    .search-icon {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-muted);
    }

    .search-results {
        max-height: 300px;
        overflow-y: auto;
        border: 1px solid var(--border-color);
        border-radius: 12px;
        background: var(--bg-white);
    }

    .search-result-item {
        padding: 1rem 1.5rem;
        border-bottom: 1px solid var(--border-color);
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 1rem;
        color: #000000 !important;
    }

    .search-result-item:last-child {
        border-bottom: none;
    }

    .search-result-item:hover {
        background: var(--light-blue);
    }

    .student-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: var(--light-blue);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--primary-blue);
        font-weight: 600;
        flex-shrink: 0;
    }

    .student-avatar img {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        object-fit: cover;
    }

    .student-info {
        flex: 1;
    }

    .student-name {
        font-weight: 600;
        color: #000000 !important;
        margin-bottom: 0.25rem;
    }

    .student-details {
        display: flex;
        gap: 1rem;
        font-size: 0.85rem;
        color: #666666 !important;
    }

    .student-admission {
        font-family: monospace;
        background: var(--light-blue);
        padding: 0.25rem 0.5rem;
        border-radius: 6px;
        color: #000000 !important;
    }

    .no-results, .loading-results {
        padding: 2rem;
        text-align: center;
        color: #666666 !important;
    }

    /* Progress Bar */
    .progress-container {
        margin: 1rem 0 1.5rem 0;
    }

    .progress-stats {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.75rem;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .progress-bar-container {
        height: 10px;
        background: var(--border-color);
        border-radius: 5px;
        overflow: hidden;
    }

    .progress-fill {
        height: 100%;
        background: linear-gradient(90deg, var(--primary-blue), var(--secondary-blue));
        width: 0%;
        transition: width 0.3s ease;
        border-radius: 5px;
    }

    .progress-details {
        background: var(--light-blue);
        border-radius: 12px;
        padding: 1.25rem;
        margin: 1rem 0;
    }

    .detail-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.75rem;
        padding-bottom: 0.75rem;
        border-bottom: 1px solid rgba(0, 0, 0, 0.1);
    }

    .detail-item:last-child {
        margin-bottom: 0;
        padding-bottom: 0;
        border-bottom: none;
    }

    .detail-label {
        color: #666666;
        font-size: 0.9rem;
    }

    .detail-value {
        color: #000000;
        font-weight: 600;
        font-size: 0.95rem;
    }

    .detail-value.success { color: var(--success-green); }
    .detail-value.error { color: var(--error-red); }
    .detail-value.warning { color: var(--warning-orange); }

    /* Upload Area for Import */
    .upload-area {
        border: 2px dashed var(--border-color);
        border-radius: 12px;
        padding: 2rem 1.5rem;
        background: var(--bg-white);
        transition: all 0.3s ease;
        text-align: center;
        cursor: pointer;
        margin-bottom: 1rem;
    }

    .upload-area:hover:not(.disabled) {
        border-color: var(--primary-blue);
        background: var(--light-blue);
    }

    .upload-area.dragover {
        border-color: var(--success-green);
        background: rgba(16, 185, 129, 0.05);
    }

    .upload-content {
        pointer-events: none;
    }

    .upload-content i {
        font-size: 2.5rem;
        color: var(--success-green);
        margin-bottom: 1rem;
    }

    .file-info {
        margin-top: 1rem;
        animation: fadeIn 0.3s ease;
    }

    /* Responsive */
    @media (max-width: 1024px) {
        .container {
            padding: 1.5rem;
        }
        .form-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {
        .main-content {
            margin-left: 0;
            padding-top: calc(var(--header-height) + 1rem);
        }
        .container {
            padding: 1rem;
        }
        .page-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 1rem;
        }
        .header-actions {
            width: 100%;
            justify-content: space-between;
        }
        .form-card {
            padding: 1.5rem;
        }
        .form-header {
            flex-direction: column;
            gap: 1rem;
            align-items: flex-start;
        }
        .mode-switcher {
            width: 100%;
            justify-content: center;
        }
        .form-section {
            padding: 1.5rem;
        }
        .form-actions {
            flex-direction: column;
        }
        .btn {
            width: 100%;
            justify-content: center;
        }
        .modal-content {
            width: 95vw;
            margin: 1rem;
        }
        .modal-header {
            padding: 1rem 1.5rem;
        }
        .modal-body {
            padding: 1.5rem;
        }
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>
</head>
<body>

<?php 
// Include the trial banner if not activated
if (!isset($school)) {
    $stmt = $db->prepare("SELECT * FROM tblschoolinfo WHERE id = :school_id");
    $stmt->bindParam(":school_id", $_SESSION['school_id'], PDO::PARAM_INT);
    $stmt->execute();
    $school = $stmt->fetch(PDO::FETCH_ASSOC);
}
include 'trial_banner.php'; 
?>

<!-- Include Sidebar -->
<?php include 'includes/sidebar.php'; ?>

<!-- Main Content -->
<div class="main-content">
    <!-- Include Header -->
    <?php include 'includes/header.php'; ?>

    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <h1 class="students-page-title">
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
                        Import Students
                    </button>
                <?php endif; ?>
                <?php if ($canCreate || $canEdit): ?>
                    <button class="btn btn-outline" id="clearFormBtn">
                        <i class="fas fa-broom"></i>
                        Clear Form
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!$permissionHelper->hasAnyPermission(['studentsView', 'studentsViewAll'])): ?>
            <div class="permission-denied">
                <i class="fas fa-lock"></i>
                <h3>Access Denied</h3>
                <p>You do not have permission to view students.</p>
                <p style="font-size: 0.9rem; margin-top: 0.5rem;">Please contact your system administrator if you need access.</p>
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
                                Add Student
                            </button>
                            <button class="mode-btn" id="editModeBtn" <?php echo !$canEdit ? 'disabled' : ''; ?>>
                                <i class="fas fa-edit"></i>
                                Edit Student
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
                                    <label class="form-label required" for="admissionNo">
                                        <i class="fas fa-id-card"></i>
                                        Admission Number
                                    </label>
                                    <input type="text" class="form-control" id="admissionNo" name="admission_no" required 
                                           placeholder="Auto-generated" 
                                           <?php echo $canCreate ? 'readonly' : ''; ?>>
                                    <small class="text-muted">Automatically generated</small>
                                    
                                    <?php if ($canEdit): ?>
                                        <div style="display: flex; gap: 0.5rem; align-items: center; margin-top: 0.5rem; flex-wrap: wrap;">
                                            <button type="button" class="btn btn-sm btn-outline" id="searchAdmissionBtn" style="padding: 0.25rem 0.75rem; font-size: 0.8rem;">
                                                <i class="fas fa-search"></i> Search
                                            </button>
                                            <a class="search-by-name-link" id="searchByNameLink" style="display: inline-block;">
                                                <i class="fas fa-search"></i> Search by name
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="form-group">
                                    <label class="form-label required" for="firstName">
                                        <i class="fas fa-user"></i>
                                        First Name
                                    </label>
                                    <input type="text" class="form-control" id="firstName" name="first_name" required 
                                           placeholder="Enter first name" <?php echo (!$canCreate && !$canEdit) ? 'disabled' : ''; ?>>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="middleName">
                                        <i class="fas fa-user"></i>
                                        Middle Name
                                    </label>
                                    <input type="text" class="form-control" id="middleName" name="middle_name" 
                                           placeholder="Enter middle name" <?php echo (!$canCreate && !$canEdit) ? 'disabled' : ''; ?>>
                                </div>

                                <div class="form-group">
                                    <label class="form-label required" for="lastName">
                                        <i class="fas fa-user"></i>
                                        Last Name
                                    </label>
                                    <input type="text" class="form-control" id="lastName" name="last_name" required 
                                           placeholder="Enter last name" <?php echo (!$canCreate && !$canEdit) ? 'disabled' : ''; ?>>
                                </div>

                                <div class="form-group">
                                    <label class="form-label required" for="admissionDate">
                                        <i class="fas fa-calendar-check"></i>
                                        Date of Admission
                                    </label>
                                    <input type="date" class="form-control" id="admissionDate" name="admission_date" required 
                                           value="<?php echo date('Y-m-d'); ?>" <?php echo $canCreate ? 'readonly' : ''; ?> <?php echo (!$canCreate && !$canEdit) ? 'disabled' : ''; ?>>
                                    <small class="text-muted">Today's date (auto-filled)</small>
                                </div>

                                <div class="form-group">
                                    <label class="form-label required" for="gender">
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
                                    <label class="form-label required" for="classSelect">
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
                                    <label class="form-label" for="streamSelect">
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
                                    <label class="form-label" for="guardianName">
                                        <i class="fas fa-user-friends"></i>
                                        Guardian Name
                                    </label>
                                    <input type="text" class="form-control" id="guardianName" name="guardian_name" 
                                           placeholder="Enter guardian's full name" <?php echo (!$canCreate && !$canEdit) ? 'disabled' : ''; ?>>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="guardianRelation">
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
                                    <label class="form-label" for="guardianContact">
                                        <i class="fas fa-phone-alt"></i>
                                        Guardian Contact
                                    </label>
                                    <input type="tel" class="form-control" id="guardianContact" name="guardian_contact" 
                                           placeholder="Enter guardian's contact number" pattern="[0-9+\-\s()]{10,}" <?php echo (!$canCreate && !$canEdit) ? 'disabled' : ''; ?>>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="guardianEmail">
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
                            <button type="button" class="btn btn-outline" id="cancelBtn" <?php echo (!$canCreate && !$canEdit) ? 'disabled' : ''; ?>>
                                <i class="fas fa-times"></i>
                                Cancel
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
    </div>
</div>

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
            <div class="students-search-input-group">
                <i class="fas fa-search search-icon"></i>
                <input type="text" class="students-search-input" id="studentSearchInput" 
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

<?php if ($canImport): ?>
<!-- Import Students Modal -->
<div class="modal-overlay" id="importStudentsModal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fas fa-file-import"></i>
                Import Students from Excel
            </h3>
            <button class="modal-close" id="closeImportModal">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="form-section" style="margin-bottom: 1.5rem;">
                <div class="section-header">
                    <div class="section-icon" style="background: var(--primary-blue);">
                        <i class="fas fa-users-class"></i>
                    </div>
                    <h3 class="section-title">Select Class & Stream</h3>
                </div>
                <div class="form-grid" style="grid-template-columns: 1fr; gap: 1.25rem;">
                    <div class="form-group">
                        <label class="form-label required">
                            <i class="fas fa-school"></i>
                            Class
                        </label>
                        <select class="form-control" id="importClassSelect" required>
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
                        <select class="form-control" id="importStreamSelect">
                            <option value="">Select Stream</option>
                        </select>
                        <small>Note: Leave empty if the class has no streams</small>
                    </div>
                </div>
            </div>

            <div class="form-section" style="margin-bottom: 1.5rem;">
                <div class="section-header">
                    <div class="section-icon" style="background: var(--success-green);">
                        <i class="fas fa-upload"></i>
                    </div>
                    <h3 class="section-title">Upload Excel File</h3>
                </div>
                <div class="upload-area" id="excelUploadArea">
                    <div class="upload-content">
                        <i class="fas fa-file-excel"></i>
                        <h4>Upload Excel File</h4>
                        <p>Drag & drop your Excel file here or click to browse</p>
                        <p>Supported formats: .xls, .xlsx, .csv<br>Maximum file size: 10MB</p>
                    </div>
                    <input type="file" id="excelFileInput" accept=".xls,.xlsx,.csv" style="display: none;">
                </div>
                <div class="file-info" id="fileInfo" style="display: none;">
                    <div>
                        <i class="fas fa-file-excel"></i>
                        <div>
                            <h5 id="fileName"></h5>
                            <p id="fileSize"></p>
                        </div>
                        <button type="button" class="btn btn-danger" id="removeFileBtn">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="section-header">
                    <div class="section-icon" style="background: var(--warning-orange);">
                        <i class="fas fa-download"></i>
                    </div>
                    <h3 class="section-title">Download Sample File</h3>
                </div>
                <div style="background: #fff8e1; border-radius: 12px; padding: 1.5rem;">
                    <p><i class="fas fa-info-circle"></i> Download our sample Excel template to ensure your file has the correct format.</p>
                    <button type="button" class="btn btn-primary" id="downloadSampleBtn">
                        <i class="fas fa-download"></i>
                        Download Sample Template
                    </button>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" id="cancelImportBtn">Cancel</button>
            <button type="button" class="btn btn-primary" id="startImportBtn" disabled>Start Import</button>
        </div>
    </div>
</div>

<!-- Import Progress Modal -->
<div class="modal-overlay" id="importProgressModal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fas fa-sync-alt fa-spin"></i>
                Importing Students
            </h3>
        </div>
        <div class="modal-body">
            <div class="progress-container">
                <div class="progress-stats">
                    <span id="progressText">Processing...</span>
                    <span id="progressPercent">0%</span>
                </div>
                <div class="progress-bar-container">
                    <div class="progress-fill" id="progressBar"></div>
                </div>
            </div>
            <div class="progress-details">
                <div class="detail-item">
                    <span class="detail-label">Total Records:</span>
                    <span class="detail-value" id="totalRecords">0</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Processed:</span>
                    <span class="detail-value" id="processedRecords">0</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Successful:</span>
                    <span class="detail-value success" id="successfulRecords">0</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Failed:</span>
                    <span class="detail-value error" id="failedRecords">0</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Skipped:</span>
                    <span class="detail-value warning" id="skippedRecords">0</span>
                </div>
            </div>
            <div id="importResult" style="display: none;"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" id="closeProgressModal" style="display: none;">Close</button>
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
    let isSearching = false;
    let currentAcademicLevel = '<?php echo $current_level; ?>';
    let selectedFile = null;

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

    // Import elements
    const importStudentsBtn = document.getElementById('importStudentsBtn');
    const importStudentsModal = document.getElementById('importStudentsModal');
    const closeImportModal = document.getElementById('closeImportModal');
    const cancelImportBtn = document.getElementById('cancelImportBtn');
    const excelUploadArea = document.getElementById('excelUploadArea');
    const excelFileInput = document.getElementById('excelFileInput');
    const fileInfo = document.getElementById('fileInfo');
    const fileName = document.getElementById('fileName');
    const fileSize = document.getElementById('fileSize');
    const removeFileBtn = document.getElementById('removeFileBtn');
    const downloadSampleBtn = document.getElementById('downloadSampleBtn');
    const startImportBtn = document.getElementById('startImportBtn');
    const importProgressModal = document.getElementById('importProgressModal');
    const closeProgressModal = document.getElementById('closeProgressModal');
    const importClassSelect = document.getElementById('importClassSelect');
    const importStreamSelect = document.getElementById('importStreamSelect');

    // Permissions from PHP
    const PERMISSIONS = {
        canCreate: <?php echo $canCreate ? 'true' : 'false'; ?>,
        canEdit: <?php echo $canEdit ? 'true' : 'false'; ?>,
        canDelete: <?php echo $canDelete ? 'true' : 'false'; ?>,
        canImport: <?php echo $canImport ? 'true' : 'false'; ?>,
        canExport: <?php echo $canExport ? 'true' : 'false'; ?>,
        canViewAll: <?php echo $canViewAll ? 'true' : 'false'; ?>,
        isSuperAdmin: <?php echo $isSuperAdmin ? 'true' : 'false'; ?>
    };

    // Helper function
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i><span>${message}</span>`;
        toastContainer.appendChild(toast);
        setTimeout(() => toast.classList.add('show'), 10);
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    }

    // Generate admission number
    function generateAdmissionNumber() {
        if (currentMode === 'add' && PERMISSIONS.canCreate) {
            fetch('api_handlers/get_last_admission.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success && admissionNoInput) {
                        admissionNoInput.value = data.next_admission;
                    }
                })
                .catch(error => console.error('Error fetching admission number:', error));
        }
    }

    function setAdmissionDate() {
        if (currentMode === 'add' && admissionDateInput && PERMISSIONS.canCreate) {
            admissionDateInput.value = new Date().toISOString().split('T')[0];
        }
    }

    // Load streams
    function loadStreams(classId, selectedStreamId = null) {
        if (!classId) {
            if (streamSelect) {
                streamSelect.innerHTML = '<option value="">Select Stream</option>';
                streamSelect.disabled = true;
            }
            return;
        }
        
        if (streamSelect) {
            streamSelect.innerHTML = '<option value="">Loading streams...</option>';
            streamSelect.disabled = true;
        }
        
        const formData = new FormData();
        formData.append('action', 'get_streams');
        formData.append('class_id', classId);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (streamSelect) {
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
            }
        })
        .catch(error => {
            console.error('Error loading streams:', error);
            if (streamSelect) {
                streamSelect.innerHTML = '<option value="">Error loading streams</option>';
                streamSelect.disabled = false;
            }
        });
    }

    // Search student by admission
    function searchStudentByAdmission(admissionNo) {
        if (!PERMISSIONS.canEdit || isSearching) return;
        
        admissionNo = admissionNo.trim();
        if (admissionNo.length < 3) {
            showToast('Please enter a valid admission number (minimum 3 characters)', 'warning');
            return;
        }
        
        isSearching = true;
        const admissionInput = document.getElementById('admissionNo');
        if (admissionInput) {
            admissionInput.style.borderColor = '#f59e0b';
            admissionInput.style.backgroundColor = '#fffbeb';
        }
        
        fetch(`api_handlers/get_student_by_admission.php?admission_no=${encodeURIComponent(admissionNo)}`)
            .then(response => response.json())
            .then(data => {
                if (admissionInput) {
                    admissionInput.style.borderColor = '';
                    admissionInput.style.backgroundColor = '';
                }
                
                if (data.success && data.data) {
                    populateFormWithStudentData(data.data);
                    showToast('Student data loaded successfully', 'success');
                    if (admissionInput) {
                        admissionInput.style.borderColor = '#10b981';
                        admissionInput.style.backgroundColor = '#f0fdf4';
                        setTimeout(() => {
                            if (admissionInput) {
                                admissionInput.style.borderColor = '';
                                admissionInput.style.backgroundColor = '';
                            }
                        }, 2000);
                    }
                } else {
                    showToast(data.message || 'Student not found', 'error');
                    if (admissionInput) {
                        admissionInput.style.borderColor = '#ef4444';
                        admissionInput.style.backgroundColor = '#fef2f2';
                        setTimeout(() => {
                            if (admissionInput) {
                                admissionInput.style.borderColor = '';
                                admissionInput.style.backgroundColor = '';
                            }
                        }, 3000);
                    }
                }
            })
            .catch(error => {
                console.error('Error searching student:', error);
                showToast('Error searching for student', 'error');
            })
            .finally(() => {
                isSearching = false;
            });
    }

    function populateFormWithStudentData(student) {
        document.getElementById('studentId').value = student.id || '';
        document.getElementById('firstName').value = student.FirstName || '';
        document.getElementById('middleName').value = student.SecondName || '';
        document.getElementById('lastName').value = student.LastName || '';
        
        if (admissionNoInput) admissionNoInput.value = student.AdmNo || '';
        document.getElementById('gender').value = student.Gender || '';
        document.getElementById('classSelect').value = student.class_id || '';
        
        if (student.class_id) {
            loadStreams(student.class_id, student.StreamId);
        }
        
        document.getElementById('guardianName').value = student.guardian_name || student.GuardianName || '';
        document.getElementById('guardianRelation').value = student.guardian_relationship || student.GuardianRelationship || '';
        document.getElementById('guardianContact').value = student.guardian_phone || student.GuardianPhone || '';
        document.getElementById('guardianEmail').value = student.guardian_email || student.GuardianEmail || '';
        document.getElementById('admissionDate').value = student.admission_date || new Date().toISOString().split('T')[0];
        
        if (photoPreview) {
            if (student.ProfilePic && student.ProfilePic !== 'default.png' && student.ProfilePic !== 'null') {
                photoPreview.innerHTML = `<img src="uploads/students/${student.ProfilePic}" alt="Preview" style="width:100%;height:100%;object-fit:cover;">`;
            } else {
                photoPreview.innerHTML = '<i class="fas fa-user"></i>';
            }
        }
        
        currentStudentId = student.id;
    }

    function clearForm() {
        if ((!PERMISSIONS.canCreate && !PERMISSIONS.canEdit) || isClearingForm) return;
        isClearingForm = true;
        
        studentForm.reset();
        if (photoPreview) photoPreview.innerHTML = '<i class="fas fa-user"></i>';
        if (streamSelect) {
            streamSelect.innerHTML = '<option value="">Select Stream</option>';
            streamSelect.disabled = false;
        }
        document.getElementById('studentId').value = '';
        currentStudentId = null;
        
        if (currentMode === 'add' && PERMISSIONS.canCreate) {
            generateAdmissionNumber();
            setAdmissionDate();
            if (admissionNoInput) admissionNoInput.placeholder = "Auto-generated";
        } else if (currentMode === 'edit' && PERMISSIONS.canEdit && admissionNoInput) {
            admissionNoInput.value = '';
            admissionNoInput.placeholder = "Enter admission number (e.g., NK/001/2026 or STU26063)";
        }
        
        isClearingForm = false;
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
        if (formTitle) formTitle.textContent = 'Add New Student';
        if (saveBtnText) saveBtnText.textContent = 'Add Student';
        if (admissionNoInput) {
            admissionNoInput.readOnly = true;
            admissionNoInput.placeholder = "Auto-generated";
        }
        if (admissionDateInput) admissionDateInput.readOnly = true;
        if (searchByNameLink) searchByNameLink.style.display = 'none';
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
        if (formTitle) formTitle.textContent = 'Edit Student';
        if (saveBtnText) saveBtnText.textContent = 'Update Student';
        if (admissionNoInput) {
            admissionNoInput.readOnly = false;
            admissionNoInput.placeholder = "Enter admission number (e.g., NK/001/2026 or STU26063)";
            admissionNoInput.value = '';
        }
        if (admissionDateInput) admissionDateInput.readOnly = false;
        if (searchByNameLink) searchByNameLink.style.display = 'inline-block';
        showToast('Switched to Edit Student mode', 'success');
    }

    function cancelForm() {
        clearForm();
        showToast('Form cleared', 'info');
    }

    function handlePhotoUpload(e) {
        const file = e.target.files[0];
        if (file && photoPreview) {
            if (file.size > 2 * 1024 * 1024) {
                showToast('Image size must be less than 2MB', 'error');
                return;
            }
            const reader = new FileReader();
            reader.onload = function(e) {
                photoPreview.innerHTML = `<img src="${e.target.result}" alt="Preview" style="width:100%;height:100%;object-fit:cover;">`;
            };
            reader.readAsDataURL(file);
        }
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
        
        const apiEndpoint = isEdit ? 'api_handlers/update_student.php' : 'api_handlers/add_student.php';
        if (isEdit) formData.append('student_id', studentId);
        
        const requiredFields = ['first_name', 'last_name', 'admission_no', 'class_id', 'admission_date', 'gender'];
        for (const field of requiredFields) {
            if (!formData.get(field)) {
                showToast(`Please fill in ${field.replace('_', ' ')}`, 'warning');
                return;
            }
        }
        
        const originalBtnText = saveStudentBtn.innerHTML;
        saveStudentBtn.innerHTML = '<div class="spinner"></div> ' + (isEdit ? 'Updating...' : 'Saving...');
        saveStudentBtn.disabled = true;
        
        fetch(apiEndpoint, { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showToast(data.message || (isEdit ? 'Student updated successfully' : 'Student added successfully'), 'success');
                    if (!isEdit) setTimeout(() => { clearForm(); generateAdmissionNumber(); setAdmissionDate(); }, 2000);
                } else {
                    showToast(data.message || 'Operation failed', 'error');
                }
            })
            .catch(error => {
                console.error('Save error:', error);
                showToast('Failed to save student: ' + error.message, 'error');
            })
            .finally(() => {
                saveStudentBtn.innerHTML = originalBtnText;
                saveStudentBtn.disabled = false;
            });
    }

    // Search by name modal functions
    function openSearchModal() {
        if (!PERMISSIONS.canEdit || !searchByNameModal) return;
        searchByNameModal.classList.add('show');
        if (studentSearchInput) {
            studentSearchInput.value = '';
            studentSearchInput.focus();
        }
        if (searchResults) {
            searchResults.innerHTML = '<div class="no-results"><i class="fas fa-search"></i><p>Start typing to search for students</p></div>';
        }
    }

    function closeSearchModalHandler() {
        if (searchByNameModal) searchByNameModal.classList.remove('show');
    }

    function handleSearchInput(e) {
        const searchTerm = e.target.value.trim();
        if (searchTimeout) clearTimeout(searchTimeout);
        
        if (searchTerm.length >= 2) {
            if (searchResults) {
                searchResults.innerHTML = '<div class="loading-results"><div class="spinner-small"></div> Searching students...</div>';
            }
            searchTimeout = setTimeout(() => searchStudents(searchTerm), 500);
        } else if (searchTerm.length === 0 && searchResults) {
            searchResults.innerHTML = '<div class="no-results"><i class="fas fa-search"></i><p>Start typing to search for students</p></div>';
        } else if (searchResults) {
            searchResults.innerHTML = '<div class="no-results"><i class="fas fa-info-circle"></i><p>Type at least 2 characters to search</p></div>';
        }
    }

    function searchStudents(searchTerm) {
        fetch(`api_handlers/search_students_by_name.php?search_term=${encodeURIComponent(searchTerm)}`)
            .then(response => response.json())
            .then(data => {
                if (searchResults) {
                    if (data.success && data.students && data.students.length > 0) {
                        searchResults.innerHTML = data.students.map(student => `
                            <div class="search-result-item" data-admission="${escapeHtml(student.admission_no)}">
                                <div class="student-avatar">${student.profile_pic && student.profile_pic !== 'default.png' && student.profile_pic !== 'null' ? `<img src="uploads/students/${student.profile_pic}" alt="${student.name}">` : '<i class="fas fa-user"></i>'}</div>
                                <div class="student-info">
                                    <div class="student-name">${escapeHtml(student.name)}</div>
                                    <div class="student-details"><span class="student-admission">${escapeHtml(student.admission_no)}</span><span>${escapeHtml(student.class_name || 'N/A')}</span><span>${escapeHtml(student.gender || 'N/A')}</span></div>
                                </div>
                            </div>
                        `).join('');
                        document.querySelectorAll('.search-result-item').forEach(item => {
                            item.addEventListener('click', function() {
                                const admissionNo = this.getAttribute('data-admission');
                                if (admissionNo && admissionNoInput) {
                                    admissionNoInput.value = admissionNo;
                                    searchStudentByAdmission(admissionNo);
                                    closeSearchModalHandler();
                                }
                            });
                        });
                    } else {
                        searchResults.innerHTML = '<div class="no-results"><i class="fas fa-user-times"></i><p>No students found</p></div>';
                    }
                }
            })
            .catch(error => {
                console.error('Search error:', error);
                if (searchResults) searchResults.innerHTML = '<div class="no-results"><i class="fas fa-exclamation-triangle"></i><p>Search failed. Please try again.</p></div>';
            });
    }

    // Import functions
    function loadImportStreams(classId) {
        if (!classId || !importStreamSelect) {
            if (importStreamSelect) {
                importStreamSelect.innerHTML = '<option value="">Select Stream</option>';
                importStreamSelect.disabled = true;
            }
            return;
        }
        
        importStreamSelect.innerHTML = '<option value="">Loading streams...</option>';
        importStreamSelect.disabled = true;
        
        const formData = new FormData();
        formData.append('action', 'get_streams');
        formData.append('class_id', classId);
        
        fetch(window.location.href, { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (importStreamSelect) {
                    if (data.success && data.streams && data.streams.length > 0) {
                        let options = '<option value="">Select Stream (Optional)</option>';
                        data.streams.forEach(stream => { options += `<option value="${stream.id}">${escapeHtml(stream.stream_name)}</option>`; });
                        importStreamSelect.innerHTML = options;
                    } else {
                        importStreamSelect.innerHTML = '<option value="">No streams available</option>';
                    }
                    importStreamSelect.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error loading import streams:', error);
                if (importStreamSelect) {
                    importStreamSelect.innerHTML = '<option value="">Error loading streams</option>';
                    importStreamSelect.disabled = false;
                }
            });
    }

    function preventDefaults(e) { e.preventDefault(); e.stopPropagation(); }
    function highlight() { if (excelUploadArea) excelUploadArea.classList.add('dragover'); }
    function unhighlight() { if (excelUploadArea) excelUploadArea.classList.remove('dragover'); }
    function handleDrop(e) { const dt = e.dataTransfer; const files = dt.files; if (files.length > 0) handleFiles(files[0]); }
    function handleFileSelect(e) { if (e.target.files.length > 0) handleFiles(e.target.files[0]); }

    function handleFiles(file) {
        if (!file) return;
        const validExtensions = ['.xls', '.xlsx', '.csv'];
        const fileExtension = '.' + file.name.split('.').pop().toLowerCase();
        if (!validExtensions.includes(fileExtension)) { showToast('Please upload only Excel files (.xls, .xlsx, .csv)', 'error'); return; }
        if (file.size > 10 * 1024 * 1024) { showToast('File size must be less than 10MB', 'error'); return; }
        selectedFile = file;
        if (fileName) fileName.textContent = file.name;
        if (fileSize) fileSize.textContent = formatFileSize(file.size);
        if (fileInfo) fileInfo.style.display = 'block';
        if (excelUploadArea) {
            excelUploadArea.style.borderStyle = 'solid';
            excelUploadArea.style.borderColor = 'var(--success-green)';
            excelUploadArea.style.background = 'rgba(16, 185, 129, 0.05)';
        }
        if (startImportBtn) startImportBtn.disabled = false;
    }

    function resetFileSelection() {
        selectedFile = null;
        if (excelFileInput) excelFileInput.value = '';
        if (fileInfo) fileInfo.style.display = 'none';
        if (excelUploadArea) {
            excelUploadArea.style.borderStyle = 'dashed';
            excelUploadArea.style.borderColor = 'var(--border-color)';
            excelUploadArea.style.background = 'var(--bg-white)';
        }
        if (startImportBtn) startImportBtn.disabled = true;
    }

    function resetImportForm() { resetFileSelection(); }

    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024, sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    function downloadSampleTemplate() {
        const sampleData = [['first_name', 'last_name', 'middle_name', 'gender', 'admission_no'], ['John', 'Doe', 'Michael', 'Male', ''], ['Sarah', 'Smith', 'Elizabeth', 'Female', ''], ['Michael', 'Johnson', 'David', 'Male', '']];
        const csvContent = sampleData.map(row => row.map(cell => `"${cell}"`).join(',')).join('\n');
        const blob = new Blob([csvContent], { type: 'text/csv' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'students_import_template.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        showToast('Sample template downloaded successfully', 'success');
    }

    function startImportProcess() {
        if (!PERMISSIONS.canImport) { showToast('You do not have permission to import students', 'error'); return; }
        if (!selectedFile) { showToast('Please select a file to import', 'error'); return; }
        const classId = importClassSelect ? importClassSelect.value : '';
        const streamId = importStreamSelect ? importStreamSelect.value : '';
        if (!classId) { showToast('Please select a class for import', 'error'); return; }
        
        if (importStudentsModal) importStudentsModal.classList.remove('show');
        if (importProgressModal) importProgressModal.classList.add('show');
        if (closeProgressModal) closeProgressModal.style.display = 'none';
        
        updateProgress(0, 'Preparing import...');
        const totalRecordsEl = document.getElementById('totalRecords');
        const processedRecordsEl = document.getElementById('processedRecords');
        const successfulRecordsEl = document.getElementById('successfulRecords');
        const failedRecordsEl = document.getElementById('failedRecords');
        const skippedRecordsEl = document.getElementById('skippedRecords');
        const importResultEl = document.getElementById('importResult');
        if (totalRecordsEl) totalRecordsEl.textContent = '0';
        if (processedRecordsEl) processedRecordsEl.textContent = '0';
        if (successfulRecordsEl) successfulRecordsEl.textContent = '0';
        if (failedRecordsEl) failedRecordsEl.textContent = '0';
        if (skippedRecordsEl) skippedRecordsEl.textContent = '0';
        if (importResultEl) importResultEl.style.display = 'none';
        
        const formData = new FormData();
        formData.append('file', selectedFile);
        formData.append('action', 'import_students');
        formData.append('class_id', classId);
        formData.append('stream_id', streamId || '');
        
        fetch('api_handlers/import_students.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (totalRecordsEl) totalRecordsEl.textContent = data.total_records || 0;
                    if (processedRecordsEl) processedRecordsEl.textContent = data.processed_records || 0;
                    if (successfulRecordsEl) successfulRecordsEl.textContent = data.successful_records || 0;
                    if (failedRecordsEl) failedRecordsEl.textContent = data.failed_records || 0;
                    if (skippedRecordsEl) skippedRecordsEl.textContent = data.skipped_records || 0;
                    updateProgress(100, 'Import completed!');
                    setTimeout(() => { if (closeProgressModal) closeProgressModal.style.display = 'block'; }, 1000);
                    showToast(`Import completed: ${data.successful_records || 0} successful, ${data.failed_records || 0} failed`, 'success');
                } else {
                    throw new Error(data.message || 'Import failed');
                }
            })
            .catch(error => {
                console.error('Import error:', error);
                showToast(`Import failed: ${error.message}`, 'error');
                if (closeProgressModal) closeProgressModal.style.display = 'block';
            });
    }

    function updateProgress(percent, message) {
        const progressBar = document.getElementById('progressBar');
        const progressPercent = document.getElementById('progressPercent');
        const progressText = document.getElementById('progressText');
        if (progressBar) progressBar.style.width = `${percent}%`;
        if (progressPercent) progressPercent.textContent = `${percent}%`;
        if (progressText) progressText.textContent = message;
    }

    // Event listeners
    document.addEventListener('DOMContentLoaded', function() {
        if (PERMISSIONS.canCreate) {
            currentMode = 'add';
            if (addModeBtn) addModeBtn.classList.add('active');
            if (editModeBtn) editModeBtn.classList.remove('active');
            if (formTitle) formTitle.textContent = 'Add New Student';
            if (saveBtnText) saveBtnText.textContent = 'Add Student';
            if (admissionNoInput) {
                admissionNoInput.readOnly = true;
                admissionNoInput.placeholder = "Auto-generated";
            }
            if (admissionDateInput) admissionDateInput.readOnly = true;
            if (searchByNameLink) searchByNameLink.style.display = 'none';
            generateAdmissionNumber();
            setAdmissionDate();
        } else if (PERMISSIONS.canEdit) {
            currentMode = 'edit';
            if (addModeBtn) addModeBtn.disabled = true;
            if (editModeBtn) editModeBtn.classList.add('active');
            if (formTitle) formTitle.textContent = 'Edit Student';
            if (saveBtnText) saveBtnText.textContent = 'Update Student';
            if (admissionNoInput) {
                admissionNoInput.readOnly = false;
                admissionNoInput.placeholder = "Enter admission number to search...";
            }
            if (admissionDateInput) admissionDateInput.readOnly = false;
            if (searchByNameLink) searchByNameLink.style.display = 'inline-block';
        }
        
        // Setup event listeners
        if (addModeBtn) addModeBtn.addEventListener('click', switchToAddMode);
        if (editModeBtn) editModeBtn.addEventListener('click', switchToEditMode);
        if (clearFormBtn) clearFormBtn.addEventListener('click', clearForm);
        if (cancelBtn) cancelBtn.addEventListener('click', cancelForm);
        if (saveStudentBtn) saveStudentBtn.addEventListener('click', saveStudent);
        if (photoInput) photoInput.addEventListener('change', handlePhotoUpload);
        
        const searchAdmissionBtn = document.getElementById('searchAdmissionBtn');
        if (searchAdmissionBtn) {
            searchAdmissionBtn.addEventListener('click', function() {
                const admissionNo = admissionNoInput ? admissionNoInput.value.trim() : '';
                if (admissionNo.length >= 3) searchStudentByAdmission(admissionNo);
                else showToast('Please enter a valid admission number', 'warning');
            });
        }
        
        if (admissionNoInput) {
            admissionNoInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter' && currentMode === 'edit' && this.value.length >= 3 && !isSearching) {
                    e.preventDefault();
                    searchStudentByAdmission(this.value);
                }
            });
        }
        
        if (classSelect) classSelect.addEventListener('change', function() { loadStreams(this.value); });
        if (searchByNameLink) searchByNameLink.addEventListener('click', openSearchModal);
        if (closeSearchModal) closeSearchModal.addEventListener('click', closeSearchModalHandler);
        if (studentSearchInput) studentSearchInput.addEventListener('input', handleSearchInput);
        
        // Import event listeners
        if (importStudentsBtn && importStudentsModal) {
            importStudentsBtn.addEventListener('click', () => importStudentsModal.classList.add('show'));
        }
        if (closeImportModal && importStudentsModal) {
            closeImportModal.addEventListener('click', () => importStudentsModal.classList.remove('show'));
        }
        if (cancelImportBtn && importStudentsModal) {
            cancelImportBtn.addEventListener('click', () => importStudentsModal.classList.remove('show'));
        }
        if (excelUploadArea) {
            excelUploadArea.addEventListener('click', () => { if (excelFileInput) excelFileInput.click(); });
        }
        if (excelFileInput) excelFileInput.addEventListener('change', handleFileSelect);
        if (excelUploadArea) {
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => { excelUploadArea.addEventListener(eventName, preventDefaults, false); });
            ['dragenter', 'dragover'].forEach(eventName => { excelUploadArea.addEventListener(eventName, highlight, false); });
            ['dragleave', 'drop'].forEach(eventName => { excelUploadArea.addEventListener(eventName, unhighlight, false); });
            excelUploadArea.addEventListener('drop', handleDrop, false);
        }
        if (removeFileBtn) removeFileBtn.addEventListener('click', resetFileSelection);
        if (downloadSampleBtn) downloadSampleBtn.addEventListener('click', downloadSampleTemplate);
        if (startImportBtn) startImportBtn.addEventListener('click', startImportProcess);
        if (closeProgressModal && importProgressModal) {
            closeProgressModal.addEventListener('click', () => importProgressModal.classList.remove('show'));
        }
        if (importClassSelect) importClassSelect.addEventListener('change', function() { loadImportStreams(this.value); });
        
        // Close modals on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                if (searchByNameModal && searchByNameModal.classList.contains('show')) closeSearchModalHandler();
                if (importStudentsModal && importStudentsModal.classList.contains('show')) importStudentsModal.classList.remove('show');
                if (importProgressModal && importProgressModal.classList.contains('show')) importProgressModal.classList.remove('show');
            }
        });
        
        // Click outside to close modals
        if (searchByNameModal) {
            searchByNameModal.addEventListener('click', (e) => { if (e.target === searchByNameModal) closeSearchModalHandler(); });
        }
        if (importStudentsModal) {
            importStudentsModal.addEventListener('click', (e) => { if (e.target === importStudentsModal) importStudentsModal.classList.remove('show'); });
        }
        if (importProgressModal) {
            importProgressModal.addEventListener('click', (e) => { if (e.target === importProgressModal) importProgressModal.classList.remove('show'); });
        }
    });
</script>
</body>
</html>