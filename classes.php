<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/tmp/php-error.log');

// Start session and include config
require_once 'includes/config.php';
require_once 'includes/PermissionHelper.php';
require_once 'includes/session_timeout.php'; 


// Security check - ensure user is logged in
if (empty($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(403);
    header('Location: login.php');
    exit;
}

// Enhanced security: Validate session variables
if (
    empty($_SESSION['authenticated']) ||
    empty($_SESSION['school_id']) ||
    empty($_SESSION['teacher_id'])
) {
    session_destroy();
    header("Location: login.php");
    exit;
}

// Get school ID from session
$school_id = $_SESSION['school_id'] ?? null;
if (!$school_id) {
    die("School ID not found in session.");
}

// Initialize Permission Helper
$permissionHelper = new PermissionHelper($db, $school_id, $_SESSION['teacher_id']);

// Check if user has permission to view classes page
$permissionHelper->requireAnyPermission(['classesView', 'classesViewAll'], 'dashboard.php');

// Fetch teachers for dropdown (only if user has permission to view teachers)
$teachers = [];
if ($permissionHelper->hasAnyPermission(['teachersView', 'teachersViewAll'])) {
    try {
        if (!isset($db)) {
            throw new Exception("Database connection not established");
        }

        $teacher_query = "SELECT id, firstname, secondname, lastname 
                         FROM tblteachers 
                         WHERE school_id = :school_id 
                         ORDER BY firstname, secondname";
        $teacher_stmt = $db->prepare($teacher_query);
        $teacher_stmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
        $teacher_stmt->execute();
        $teachers = $teacher_stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        // Error will be handled by JavaScript
    }
}

// Determine which actions are allowed based on permissions
$canCreate = $permissionHelper->hasPermission('classesCreate');
$canEdit = $permissionHelper->hasPermission('classesEdit');
$canDelete = $permissionHelper->hasPermission('classesDelete');
$canManageStreams = $permissionHelper->hasPermission('classesManageStreams');
$isSuperAdmin = $permissionHelper->isSuperAdmin();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EduScore - Class Management</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="icon" type="image/png" href="/images/logo.png" />
<link rel="apple-touch-icon" href="images/logo.png">
<link rel="stylesheet" href="assets/banner/banner.css">
<style>
/* Your existing CSS - keep as is */
:root {
    --primary-blue: #1e40af;
    --secondary-blue: #3b82f6;
    --light-blue: #dbeafe;
    --accent-blue: #60a5fa;
    --dark-blue: #1e3a8a;
    --success-green: #10b981;
    --warning-orange: #f59e0b;
    --error-red: #ef4444;
    --text-dark: #1f2937;
    --text-light: #6b7280;
    --bg-light: #f9fafb;
    --bg-white: #ffffff;
    --border-color: #e5e7eb;
    --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    --shadow-xl: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    --border-radius: 12px;
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Inter', sans-serif;
}

body {
    background: var(--bg-light);
    color: var(--text-dark);
    min-height: 100vh;
}

.main-content {
    margin-left: 280px;
    min-height: 100vh;
    padding: 100px 2rem 2rem;
    transition: margin-left 0.3s ease;
}

@media (max-width: 992px) {
    .main-content {
        margin-left: 0;
        padding: 100px 1rem 1rem;
    }
}

/* Error Message */
.error-message {
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: var(--error-red);
    padding: 1rem;
    border-radius: var(--border-radius);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
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
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
}

.page-header-left {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.role-badge {
    background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 50px;
    font-size: 0.85rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    white-space: nowrap;
    box-shadow: var(--shadow);
}

.role-badge i {
    font-size: 0.9rem;
}

.classes-page-title {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--text-dark);
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.classes-page-title i {
    color: var(--primary-blue);
}

.page-description {
    color: var(--text-light);
    font-size: 1rem;
}

/* Action Bar */
.action-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    gap: 1rem;
    flex-wrap: wrap;
}

.classes-search-box {
    position: relative;
    flex: 1;
    max-width: 400px;
    min-width: 250px;
}

.classes-search-input {
    width: 100%;
    padding: 0.75rem 1rem 0.75rem 2.5rem;
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    font-size: 0.9rem;
    transition: var(--transition);
    background: var(--bg-white);
}

.classes-search-input:focus {
    outline: none;
    border-color: var(--primary-blue);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.classes-search-icon {
    position: absolute;
    left: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-light);
}

.add-class-btn {
    background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: var(--border-radius);
    font-weight: 600;
    font-size: 0.9rem;
    cursor: pointer;
    transition: var(--transition);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    box-shadow: var(--shadow);
}

.add-class-btn:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.add-class-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    background: linear-gradient(135deg, #9ca3af, #6b7280);
}

/* Permission Denied Message */
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

/* Classes Table */
.classes-table-container {
    background: var(--bg-white);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow);
    overflow: hidden;
    min-height: 400px;
}

.table-responsive {
    overflow-x: auto;
}

.classes-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 900px;
}

.classes-table th {
    background: var(--light-blue);
    padding: 1rem 1.5rem;
    text-align: left;
    font-weight: 600;
    color: var(--text-dark);
    border-bottom: 1px solid var(--border-color);
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.classes-table td {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--border-color);
    font-size: 0.9rem;
}

.classes-table tr:last-child td {
    border-bottom: none;
}

.classes-table tr:hover {
    background: var(--bg-light);
}

.class-name {
    font-weight: 600;
    color: var(--text-dark);
}

.academic-level {
    background: var(--light-blue);
    color: var(--primary-blue);
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
}

.student-count {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    color: var(--text-dark);
}

.student-count i {
    color: var(--success-green);
}

.teacher-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.teacher-avatar {
    width: 32px;
    height: 32px;
    background: linear-gradient(135deg, var(--accent-blue), var(--secondary-blue));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 0.8rem;
}

.teacher-details {
    display: flex;
    flex-direction: column;
}

.teacher-name {
    font-weight: 500;
    color: var(--text-dark);
}

/* Streams Display */
.streams-container {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 0.5rem;
}

.stream-badge {
    background: var(--light-blue);
    color: var(--primary-blue);
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    cursor: <?php echo $canManageStreams ? 'pointer' : 'default'; ?>;
    transition: var(--transition);
}

.stream-badge:hover {
    background: var(--accent-blue);
    color: white;
}

.stream-badge .remove-stream {
    opacity: 0;
    transition: var(--transition);
}

.stream-badge:hover .remove-stream {
    opacity: <?php echo $canManageStreams ? '1' : '0'; ?>;
}

.add-stream-btn {
    background: none;
    border: 1px dashed var(--border-color);
    color: var(--text-light);
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    cursor: <?php echo $canManageStreams ? 'pointer' : 'not-allowed'; ?>;
    transition: var(--transition);
    opacity: <?php echo $canManageStreams ? '1' : '0.5'; ?>;
}

.add-stream-btn:hover:not(:disabled) {
    background: var(--light-blue);
    color: var(--primary-blue);
    border-color: var(--primary-blue);
}

.add-stream-btn:disabled {
    cursor: not-allowed;
}

.no-streams {
    color: var(--text-light);
    font-style: italic;
    font-size: 0.9rem;
}

/* Actions */
.actions {
    display: flex;
    gap: 0.5rem;
}

.action-btn {
    background: none;
    border: none;
    padding: 0.5rem;
    border-radius: 6px;
    cursor: pointer;
    transition: var(--transition);
    color: var(--text-light);
}

.edit-btn:hover:not(:disabled) {
    background: var(--light-blue);
    color: var(--primary-blue);
}

.delete-btn:hover:not(:disabled) {
    background: #fef2f2;
    color: var(--error-red);
}

.stream-btn:hover:not(:disabled) {
    background: #f0f9ff;
    color: var(--secondary-blue);
}

.action-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
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

.empty-state h3 {
    font-size: 1.25rem;
    margin-bottom: 0.5rem;
    color: var(--text-dark);
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
    z-index: 1000;
    padding: 1rem;
}

.modal-overlay.active {
    display: flex;
    animation: fadeIn 0.3s ease;
}

.modal {
    background: var(--bg-white);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-xl);
    width: 100%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
    animation: slideUp 0.3s ease;
}

.modal-header {
    padding: 1.5rem 2rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.modal-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-dark);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.close-modal {
    background: none;
    border: none;
    font-size: 1.25rem;
    color: var(--text-light);
    cursor: pointer;
    padding: 0.25rem;
    border-radius: 4px;
    transition: var(--transition);
}

.close-modal:hover {
    background: var(--bg-light);
    color: var(--text-dark);
}

.modal-body {
    padding: 2rem;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: var(--text-dark);
    font-size: 0.9rem;
}

.form-control {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    font-size: 0.9rem;
    transition: var(--transition);
    background: var(--bg-white);
}

.form-control:focus {
    outline: none;
    border-color: var(--primary-blue);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-control:disabled {
    background: var(--bg-light);
    cursor: not-allowed;
}

.form-select {
    appearance: none;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
    background-position: right 0.75rem center;
    background-repeat: no-repeat;
    background-size: 16px 12px;
    padding-right: 2.5rem;
}

.modal-footer {
    padding: 1.5rem 2rem;
    border-top: 1px solid var(--border-color);
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
}

.btn {
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

.btn-primary {
    background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
    color: white;
}

.btn-primary:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: var(--shadow);
}

.btn-primary:disabled {
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
    background: linear-gradient(135deg, var(--error-red), #b91c1c);
    color: white;
}

.btn-danger:hover:not(:disabled) {
    background: linear-gradient(135deg, #dc2626, #991b1b);
}

.btn-danger:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Current Level Display */
.current-level-display {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    background-color: #f8fafc;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    color: #374151;
}

.current-level-display i {
    color: #3b82f6;
}

#levelDisplayText {
    font-weight: 500;
}

.form-text {
    display: block;
    margin-top: 6px;
    font-size: 12px;
    color: #6b7280;
}

/* Stream Modal Styles */
.stream-modal .modal {
    max-width: 450px;
}

.existing-streams {
    background: var(--bg-light);
    border-radius: var(--border-radius);
    padding: 1rem;
    margin-top: 1rem;
}

.existing-streams h4 {
    font-size: 0.9rem;
    font-weight: 600;
    margin-bottom: 0.75rem;
    color: var(--text-dark);
}

.stream-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

/* Toast Notifications */
.toast-container {
    position: fixed;
    top: 100px;
    right: 2rem;
    z-index: 1100;
    max-width: 400px;
}

.toast {
    background: var(--bg-white);
    border-radius: var(--border-radius);
    padding: 1rem 1.5rem;
    margin-bottom: 1rem;
    box-shadow: var(--shadow-lg);
    border-left: 4px solid var(--success-green);
    display: flex;
    align-items: center;
    gap: 1rem;
    animation: slideInRight 0.3s ease;
}

.toast.error {
    border-left-color: var(--error-red);
}

.toast.warning {
    border-left-color: var(--warning-orange);
}

.toast.info {
    border-left-color: var(--primary-blue);
}

.toast-icon {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.8rem;
}

.toast.success .toast-icon {
    background: var(--success-green);
}

.toast.error .toast-icon {
    background: var(--error-red);
}

.toast.warning .toast-icon {
    background: var(--warning-orange);
}

.toast.info .toast-icon {
    background: var(--primary-blue);
}

.toast-content {
    flex: 1;
}

.toast-title {
    font-weight: 600;
    margin-bottom: 0.25rem;
}

.toast-message {
    font-size: 0.9rem;
    color: var(--text-light);
}

/* Loading States */
.loading-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 4rem 2rem;
    text-align: center;
    animation: fadeIn 0.5s ease;
}

.modern-spinner {
    position: relative;
    width: 120px;
    height: 120px;
    margin-bottom: 2rem;
}

.spinner-container {
    position: relative;
    width: 100%;
    height: 100%;
}

.spinner-ring {
    position: absolute;
    border-radius: 50%;
    border: 3px solid transparent;
    animation: rotate 2s linear infinite;
}

.spinner-ring:nth-child(1) {
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    border-top-color: var(--primary-blue);
    animation-delay: 0s;
    animation-duration: 2s;
}

.spinner-ring:nth-child(2) {
    top: 10px;
    left: 10px;
    right: 10px;
    bottom: 10px;
    border-top-color: var(--secondary-blue);
    animation-delay: 0.1s;
    animation-duration: 1.8s;
}

.spinner-ring:nth-child(3) {
    top: 20px;
    left: 20px;
    right: 20px;
    bottom: 20px;
    border-top-color: var(--accent-blue);
    animation-delay: 0.2s;
    animation-duration: 1.6s;
}

.spinner-ring:nth-child(4) {
    top: 30px;
    left: 30px;
    right: 30px;
    bottom: 30px;
    border-top-color: rgba(59, 130, 246, 0.3);
    animation-delay: 0.3s;
    animation-duration: 1.4s;
}

.spinner-center {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.2rem;
    box-shadow: 0 4px 15px rgba(30, 64, 175, 0.2);
    animation: pulse 2s ease-in-out infinite;
}

.loading-content {
    max-width: 400px;
}

.loading-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--text-dark);
    margin-bottom: 0.75rem;
    background: linear-gradient(90deg, var(--text-dark), var(--primary-blue), var(--text-dark));
    background-size: 200% 100%;
    background-clip: text;
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    animation: shimmer 3s ease-in-out infinite;
}

.loading-text {
    color: var(--text-light);
    font-size: 0.95rem;
    margin-bottom: 1.5rem;
    line-height: 1.5;
}

.loading-progress {
    width: 100%;
    max-width: 300px;
    margin: 0 auto;
}

.progress-bar {
    width: 100%;
    height: 6px;
    background: var(--border-color);
    border-radius: 3px;
    overflow: hidden;
    position: relative;
}

.progress-fill {
    position: absolute;
    top: 0;
    left: 0;
    height: 100%;
    width: 30%;
    background: linear-gradient(90deg, var(--primary-blue), var(--secondary-blue), var(--accent-blue));
    border-radius: 3px;
    animation: progressMove 2s ease-in-out infinite, progressColor 3s ease-in-out infinite;
}

/* Table Row Animation */
@keyframes fadeInRow {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.new-row {
    animation: fadeInRow 0.5s ease;
    background-color: rgba(239, 246, 255, 0.5);
}

/* Delete Modal Specific */
.delete-modal .modal {
    max-width: 400px;
}

.delete-warning {
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: var(--error-red);
    padding: 0.75rem;
    border-radius: 8px;
    margin-top: 1rem;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* Animations */
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes slideInRight {
    from {
        opacity: 0;
        transform: translateX(100%);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes rotate {
    0% {
        transform: rotate(0deg);
    }
    100% {
        transform: rotate(360deg);
    }
}

@keyframes pulse {
    0%, 100% {
        transform: translate(-50%, -50%) scale(1);
        box-shadow: 0 4px 15px rgba(30, 64, 175, 0.2);
    }
    50% {
        transform: translate(-50%, -50%) scale(1.05);
        box-shadow: 0 6px 20px rgba(30, 64, 175, 0.3);
    }
}

@keyframes progressMove {
    0%, 100% {
        left: -30%;
        width: 30%;
    }
    50% {
        left: 100%;
        width: 30%;
    }
}

@keyframes progressColor {
    0%, 100% {
        background: linear-gradient(90deg, var(--primary-blue), var(--secondary-blue), var(--accent-blue));
    }
    50% {
        background: linear-gradient(90deg, var(--accent-blue), var(--secondary-blue), var(--primary-blue));
    }
}

@keyframes shimmer {
    0%, 100% {
        background-position: -200% 0;
    }
    50% {
        background-position: 200% 0;
    }
}

/* Responsive Design */
@media (max-width: 768px) {
    .main-content {
        padding: 100px 1rem 1rem;
    }

    .page-header {
        padding: 1.5rem;
    }

    .action-bar {
        flex-direction: column;
        align-items: stretch;
    }

    .classes-search-box {
        max-width: none;
    }

    .add-class-btn {
        width: 100%;
        justify-content: center;
    }

    .modal {
        margin: 1rem;
    }

    .modal-header,
    .modal-body,
    .modal-footer {
        padding: 1.25rem;
    }

    .toast-container {
        right: 1rem;
        left: 1rem;
        max-width: none;
    }
}

@media (max-width: 480px) {
    .classes-table td,
    .classes-table th {
        padding: 0.75rem 1rem;
    }

    .teacher-info {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.25rem;
    }

    .actions {
        flex-direction: column;
        gap: 0.25rem;
    }
}
</style>
</head>
<body>

    <?php 
    // Include the trial banner if not activated
    if (!isset($school)) {
        // Fetch school data for the banner
        $stmt = $db->prepare("SELECT * FROM tblschoolinfo WHERE id = :school_id");
        $stmt->bindParam(":school_id", $_SESSION['school_id'], PDO::PARAM_INT);
        $stmt->execute();
        $school = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    include 'trial_banner.php'; 
    ?>
    
    <!-- Include Header -->
    <?php include 'includes/header.php'; ?>

    <!-- Include Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <div class="page-header-left">
                <div>
                    <h1 class="classes-page-title">
                        <i class="fas fa-school"></i>
                        Class Management
                    </h1>
                    <p class="page-description">
                        Manage classes, streams, and class teachers
                    </p>
                </div>
            </div>
            <div class="role-badge">
                <i class="fas fa-<?php echo $isSuperAdmin ? 'crown' : 'user-tag'; ?>"></i>
                <?php echo htmlspecialchars($permissionHelper->getRole() ?? 'User'); ?>
            </div>
        </div>

        <?php if (!$permissionHelper->hasAnyPermission(['classesView', 'classesViewAll'])): ?>
            <!-- Permission Denied -->
            <div class="permission-denied">
                <i class="fas fa-lock"></i>
                <h3>Access Denied</h3>
                <p>You do not have permission to view classes.</p>
                <p style="font-size: 0.9rem; margin-top: 0.5rem;">Please contact your system administrator if you need access.</p>
            </div>
        <?php else: ?>
            <!-- Action Bar -->
            <div class="action-bar">
                <div class="classes-search-box">
                    <i class="fas fa-search classes-search-icon"></i>
                    <input type="text" class="classes-search-input" id="searchInput" placeholder="Search classes...">
                </div>
                <?php if ($canCreate): ?>
                    <button class="add-class-btn" id="addClassBtn">
                        <i class="fas fa-plus"></i>
                        Add Class
                    </button>
                <?php endif; ?>
            </div>

            <!-- Classes Table -->
            <div class="classes-table-container">
                <div class="table-responsive">
                    <table class="classes-table">
                        <thead>
                            <tr>
                                <th>Class</th>
                                <th>Streams</th>
                                <th>Students</th>
                                <th>Class Teacher</th>
                                <th>Academic Level</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="classesTableBody">
                            <!-- Classes will be loaded dynamically -->
                            <tr>
                                <td colspan="6">
                                    <div class="loading-state">
                                        <div class="modern-spinner">
                                            <div class="spinner-container">
                                                <div class="spinner-ring"></div>
                                                <div class="spinner-ring"></div>
                                                <div class="spinner-ring"></div>
                                                <div class="spinner-ring"></div>
                                                <div class="spinner-center">
                                                    <i class="fas fa-school"></i>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="loading-content">
                                            <h3 class="loading-title">Loading Class Information</h3>
                                            <p class="loading-text">Please wait while we fetch your class data...</p>
                                            <div class="loading-progress">
                                                <div class="progress-bar">
                                                    <div class="progress-fill"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($canCreate || $canEdit): ?>
    <!-- Add/Edit Class Modal -->
    <div class="modal-overlay" id="classModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-school"></i>
                    <span id="modalTitle"><?php echo $canCreate ? 'Add New Class' : 'Edit Class'; ?></span>
                </h3>
                <button class="close-modal" id="closeModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="classForm">
                <div class="modal-body">
                    <input type="hidden" id="classId" name="class_id">
                    <input type="hidden" id="academicLevel" name="academic_level">
                    
                    <div class="form-group">
                        <label class="form-label">Current Academic Level</label>
                        <div class="current-level-display" id="currentLevelDisplay">
                            <i class="fas fa-graduation-cap"></i>
                            <span id="levelDisplayText">Primary School</span>
                        </div>
                        <small class="form-text">Change academic level from the header dropdown</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="className">Class *</label>
                        <select class="form-control form-select" id="className" name="class_name" required <?php echo !$canCreate && !$canEdit ? 'disabled' : ''; ?>>
                            <option value="">Select Class</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="teacherId">Class Teacher</label>
                        <select class="form-control form-select" id="teacherId" name="teacher_id" <?php echo !$canCreate && !$canEdit ? 'disabled' : ''; ?>>
                            <option value="">Select Class Teacher</option>
                            <?php foreach ($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>">
                                    <?php echo htmlspecialchars($teacher['firstname'] . ' ' . $teacher['secondname'] . ' ' . $teacher['lastname']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" id="cancelBtn">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary" id="saveBtn" <?php echo !$canCreate && !$canEdit ? 'disabled' : ''; ?>>
                        <i class="fas fa-save"></i>
                        <span><?php echo $canCreate ? 'Save Class' : 'Update Class'; ?></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($canManageStreams): ?>
    <!-- Add Stream Modal -->
    <div class="modal-overlay" id="streamModal">
        <div class="modal stream-modal">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-layer-group"></i>
                    <span id="streamModalTitle">Add Streams to Class</span>
                </h3>
                <button class="close-modal" id="closeStreamModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="streamForm">
                <div class="modal-body">
                    <input type="hidden" id="streamClassId" name="class_id">
                    <input type="hidden" id="streamClassName" name="class_name">
                    
                    <div class="form-group">
                        <label class="form-label" for="streamName">Stream Name *</label>
                        <input type="text" class="form-control" id="streamName" name="stream_name" 
                               placeholder="e.g., A, B, Red, Blue, Science, Arts, etc." required>
                        <small class="form-text">Enter stream name (max 50 characters)</small>
                    </div>

                    <div class="existing-streams" id="existingStreamsContainer" style="display: none;">
                        <h4>Existing Streams</h4>
                        <div class="stream-list" id="existingStreamsList"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" id="cancelStreamBtn">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary" id="saveStreamBtn">
                        <i class="fas fa-plus"></i>
                        <span>Add Stream</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- Include banner JavaScript -->
    <script src="assets/banner/banner.js"></script>
    
    <script>
    // DOM Elements
    const classModal = document.getElementById('classModal');
    const classForm = document.getElementById('classForm');
    const modalTitle = document.getElementById('modalTitle');
    const classIdInput = document.getElementById('classId');
    const academicLevelInput = document.getElementById('academicLevel');
    const levelDisplayText = document.getElementById('levelDisplayText');
    const classNameSelect = document.getElementById('className');
    const teacherIdSelect = document.getElementById('teacherId');
    const addClassBtn = document.getElementById('addClassBtn');
    const closeModal = document.getElementById('closeModal');
    const cancelBtn = document.getElementById('cancelBtn');
    const saveBtn = document.getElementById('saveBtn');
    const saveBtnText = saveBtn ? saveBtn.querySelector('span') : null;
    const searchInput = document.getElementById('searchInput');
    const classesTableBody = document.getElementById('classesTableBody');
    const toastContainer = document.getElementById('toastContainer');

    // Stream Modal Elements
    const streamModal = document.getElementById('streamModal');
    const streamForm = document.getElementById('streamForm');
    const streamModalTitle = document.getElementById('streamModalTitle');
    const streamClassIdInput = document.getElementById('streamClassId');
    const streamClassNameInput = document.getElementById('streamClassName');
    const streamNameInput = document.getElementById('streamName');
    const closeStreamModal = document.getElementById('closeStreamModal');
    const cancelStreamBtn = document.getElementById('cancelStreamBtn');
    const saveStreamBtn = document.getElementById('saveStreamBtn');
    const saveStreamBtnText = saveStreamBtn ? saveStreamBtn.querySelector('span') : null;
    const existingStreamsContainer = document.getElementById('existingStreamsContainer');
    const existingStreamsList = document.getElementById('existingStreamsList');

    // Permissions from PHP
    const PERMISSIONS = {
        canCreate: <?php echo $canCreate ? 'true' : 'false'; ?>,
        canEdit: <?php echo $canEdit ? 'true' : 'false'; ?>,
        canDelete: <?php echo $canDelete ? 'true' : 'false'; ?>,
        canManageStreams: <?php echo $canManageStreams ? 'true' : 'false'; ?>,
        isSuperAdmin: <?php echo $isSuperAdmin ? 'true' : 'false'; ?>
    };

    // Configuration
    const classLevels = {
        'primary': [
            { value: 'PP1', label: 'Pre-Primary 1 (PP1)' },
            { value: 'PP2', label: 'Pre-Primary 2 (PP2)' },
            { value: 'Grade 1', label: 'Grade 1' },
            { value: 'Grade 2', label: 'Grade 2' },
            { value: 'Grade 3', label: 'Grade 3' },
            { value: 'Grade 4', label: 'Grade 4' },
            { value: 'Grade 5', label: 'Grade 5' },
            { value: 'Grade 6', label: 'Grade 6' }
        ],
        'junior_secondary': [
            { value: 'Grade 7', label: 'Grade 7' },
            { value: 'Grade 8', label: 'Grade 8' },
            { value: 'Grade 9', label: 'Grade 9' }
        ],
        'senior_secondary': [
            { value: 'Grade 10', label: 'Grade 10' },
            { value: 'Grade 11', label: 'Grade 11' },
            { value: 'Grade 12', label: 'Grade 12' }
        ],
        'college': [
            { value: 'Year 1', label: 'Year 1' },
            { value: 'Year 2', label: 'Year 2' },
            { value: 'Year 3', label: 'Year 3' },
            { value: 'Year 4', label: 'Year 4' }
        ]
    };

    const academicLevelDisplay = {
        'primary': 'Primary School',
        'junior_secondary': 'Junior Secondary',
        'senior_secondary': 'Senior Secondary',
        'college': 'College'
    };

    let currentAcademicLevel = 'primary';
    let teachersCache = <?php echo json_encode($teachers); ?>;
    let classesData = [];
    let streamsCache = {};

    // Enhanced Toast Notification Function
    function showToast(title, message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        
        let icon = 'check';
        if (type === 'error') icon = 'exclamation-triangle';
        if (type === 'warning') icon = 'exclamation-circle';
        if (type === 'info') icon = 'info-circle';
        
        toast.innerHTML = `
            <div class="toast-icon">
                <i class="fas fa-${icon}"></i>
            </div>
            <div class="toast-content">
                <div class="toast-title">${title}</div>
                <div class="toast-message">${message}</div>
            </div>
        `;
        
        toastContainer.appendChild(toast);
        
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(100%)';
            toast.style.transition = 'all 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    }

    // Enhanced Loading States
    function showLoading(button, text) {
        if (!button) return;
        const originalHTML = button.innerHTML;
        button.dataset.originalHTML = originalHTML;
        button.innerHTML = `
            <div class="loading-spinner" style="display: inline-block; width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-radius: 50%; border-top-color: white; animation: spin 1s ease-in-out infinite; margin-right: 8px;"></div>
            ${text}
        `;
        button.disabled = true;
    }

    function hideLoading(button) {
        if (!button) return;
        if (button.dataset.originalHTML) {
            button.innerHTML = button.dataset.originalHTML;
        }
        button.disabled = false;
    }

    // Add CSS for spinner animation
    const style = document.createElement('style');
    style.textContent = `
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    `;
    document.head.appendChild(style);

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Check permission for action
    function checkPermission(action) {
        if (!PERMISSIONS[action] && !PERMISSIONS.isSuperAdmin) {
            showToast('Access Denied', 'You do not have permission to perform this action', 'error');
            return false;
        }
        return true;
    }

    // Stream Functions
    function renderStreamBadge(stream, showRemove = false) {
        const canRemove = showRemove && (PERMISSIONS.canManageStreams || PERMISSIONS.isSuperAdmin);
        return `
            <div class="stream-badge" data-stream-id="${stream.id}">
                ${escapeHtml(stream.stream_name)}
                ${canRemove ? 
                    `<span class="remove-stream" onclick="removeStream(${stream.id}, '${escapeHtml(stream.stream_name).replace(/'/g, "\\'")}')">
                        <i class="fas fa-times" style="font-size: 10px;"></i>
                    </span>` 
                    : ''}
            </div>
        `;
    }

    function renderStreamsCell(classId, streams) {
        const canAdd = PERMISSIONS.canManageStreams || PERMISSIONS.isSuperAdmin;
        
        if (!streams || streams.length === 0) {
            return `
                <div class="no-streams">No streams</div>
                ${canAdd ? `
                    <button class="add-stream-btn" onclick="openAddStreamModal(${classId})">
                        <i class="fas fa-plus"></i> Add Stream
                    </button>
                ` : ''}
            `;
        }

        return `
            <div class="streams-container">
                ${streams.map(stream => renderStreamBadge(stream, canAdd)).join('')}
                ${canAdd ? `
                    <button class="add-stream-btn" onclick="openAddStreamModal(${classId})">
                        <i class="fas fa-plus"></i> Add More
                    </button>
                ` : ''}
            </div>
        `;
    }

    // Class Table Functions
    function renderClassRow(classData, isNew = false) {
        const teacher = teachersCache.find(t => t.id == classData.teacher_id);
        const teacherName = teacher ? 
            `${teacher.firstname} ${teacher.secondname} ${teacher.lastname}` : '';
        
        const levelMap = {
            'primary': 'Primary',
            'junior_secondary': 'Junior Secondary',
            'senior_secondary': 'Senior Secondary',
            'college': 'College'
        };

        const streams = streamsCache[classData.id] || [];
        
        const canEditClass = PERMISSIONS.canEdit || PERMISSIONS.isSuperAdmin;
        const canDeleteClass = PERMISSIONS.canDelete || PERMISSIONS.isSuperAdmin;
        const canManageStreams = PERMISSIONS.canManageStreams || PERMISSIONS.isSuperAdmin;
        
        return `
            <tr data-class-id="${classData.id}" ${isNew ? 'class="new-row"' : ''}>
                <td>
                    <div class="class-name">${escapeHtml(classData.class_level)}</div>
                </td>
                <td>
                    ${renderStreamsCell(classData.id, streams)}
                </td>
                <td>
                    <div class="student-count">
                        <i class="fas fa-users"></i>
                        ${classData.student_count || 0} Students
                    </div>
                </td>
                <td>
                    ${teacherName ? `
                        <div class="teacher-info">
                            <div class="teacher-avatar">
                                ${teacherName.charAt(0).toUpperCase()}
                            </div>
                            <div class="teacher-details">
                                <span class="teacher-name">${escapeHtml(teacherName)}</span>
                            </div>
                        </div>
                    ` : '<span style="color: var(--text-light); font-style: italic;">Not assigned</span>'}
                </div>
                <td>
                    <span class="academic-level">
                        ${levelMap[classData.academic_level] || escapeHtml(classData.academic_level)}
                    </span>
                </div>
                <td>
                    <div class="actions">
                        ${canManageStreams ? `
                            <button class="action-btn stream-btn" onclick="openAddStreamModal(${classData.id})" title="Add Stream">
                                <i class="fas fa-layer-group"></i>
                            </button>
                        ` : ''}
                        ${canEditClass ? `
                            <button class="action-btn edit-btn" onclick="editClass(${classData.id})" title="Edit Class">
                                <i class="fas fa-edit"></i>
                            </button>
                        ` : ''}
                        ${canDeleteClass ? `
                            <button class="action-btn delete-btn" onclick="showDeleteModal(${classData.id}, '${escapeHtml(classData.class_level).replace(/'/g, "\\'")}')" title="Delete Class">
                                <i class="fas fa-trash"></i>
                            </button>
                        ` : ''}
                    </div>
                </div>
            </tr>
        `;
    }

    async function loadClasses() {
        try {
            const response = await fetch('/api_handlers/class_handler.php?action=get_classes');
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const result = await response.json();
            
            if (result.success) {
                classesData = result.data;
                await loadAllStreams();
                renderClassesTable(classesData);
            } else {
                showToast('Error', result.message || 'Failed to load classes', 'error');
            }
        } catch (error) {
            classesTableBody.innerHTML = `
                <tr>
                    <td colspan="6">
                        <div class="empty-state">
                            <i class="fas fa-exclamation-triangle" style="color: var(--error-red);"></i>
                            <h3>Error Loading Classes</h3>
                            <p>${escapeHtml(error.message)}</p>
                            <button onclick="loadClasses()" class="retry-btn">
                                <i class="fas fa-redo"></i> Retry
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }
    }

    async function loadAllStreams() {
        try {
            const response = await fetch('/api_handlers/stream_handler.php?action=get_all_streams');
            const result = await response.json();
            
            if (result.success) {
                streamsCache = {};
                result.data.forEach(stream => {
                    if (!streamsCache[stream.class_id]) {
                        streamsCache[stream.class_id] = [];
                    }
                    streamsCache[stream.class_id].push(stream);
                });
            }
        } catch (error) {
            console.error('Error loading streams:', error);
        }
    }

    async function loadClassStreams(classId) {
        try {
            const response = await fetch(`/api_handlers/stream_handler.php?action=get_streams&class_id=${classId}`);
            const result = await response.json();
            
            if (result.success) {
                streamsCache[classId] = result.data;
                updateStreamsDisplay(classId);
            }
        } catch (error) {
            console.error('Error loading streams:', error);
        }
    }

    function updateStreamsDisplay(classId) {
        const row = document.querySelector(`tr[data-class-id="${classId}"]`);
        if (row) {
            const streams = streamsCache[classId] || [];
            const streamsCell = row.cells[1];
            if (streamsCell) {
                streamsCell.innerHTML = renderStreamsCell(classId, streams);
            }
        }
    }

    function renderClassesTable(classes) {
        if (classes.length === 0) {
            classesTableBody.innerHTML = `
                <tr>
                    <td colspan="6">
                        <div class="empty-state">
                            <i class="fas fa-school"></i>
                            <h3>No Classes Found</h3>
                            <p>Get started by creating your first class</p>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        classesTableBody.innerHTML = classes.map(classData => renderClassRow(classData)).join('');
    }

    function addClassToTable(classData) {
        if (classesTableBody.querySelector('.empty-state')) {
            classesTableBody.innerHTML = '';
        }
        
        const newRow = renderClassRow(classData, true);
        classesTableBody.insertAdjacentHTML('afterbegin', newRow);
        
        setTimeout(() => {
            const row = classesTableBody.querySelector('.new-row');
            if (row) row.classList.remove('new-row');
        }, 500);
    }

    function updateClassInTable(classData) {
        const row = document.querySelector(`tr[data-class-id="${classData.id}"]`);
        if (row) {
            row.outerHTML = renderClassRow(classData);
        }
    }

    function removeClassFromTable(classId) {
        const row = document.querySelector(`tr[data-class-id="${classId}"]`);
        if (row) {
            row.style.opacity = '0';
            row.style.transform = 'translateX(-20px)';
            row.style.transition = 'all 0.3s ease';
            
            setTimeout(() => {
                row.remove();
                
                if (classesTableBody.children.length === 0) {
                    classesTableBody.innerHTML = `
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <i class="fas fa-school"></i>
                                    <h3>No Classes Found</h3>
                                    <p>Get started by creating your first class</p>
                                </div>
                            </td>
                        </tr>
                    `;
                }
            }, 300);
        }
    }

    // Modal Functions
    function populateClassDropdown(academicLevel) {
        if (!classNameSelect) return;
        classNameSelect.innerHTML = '<option value="">Select Class</option>';
        const classes = classLevels[academicLevel] || [];
        
        classes.forEach(cls => {
            const option = document.createElement('option');
            option.value = cls.value;
            option.textContent = cls.label;
            classNameSelect.appendChild(option);
        });
    }

    function getCurrentAcademicLevelFromHeader() {
        const centerLevelText = document.querySelector('.center-level-text');
        if (centerLevelText) {
            const levelText = centerLevelText.textContent.trim();
            const levelMap = {
                'Primary School': 'primary',
                'Junior Secondary': 'junior_secondary',
                'Senior Secondary': 'senior_secondary',
                'College': 'college'
            };
            return levelMap[levelText] || 'primary';
        }
        return 'primary';
    }

    function updateModalAcademicLevel() {
        if (!academicLevelInput || !levelDisplayText) return;
        currentAcademicLevel = getCurrentAcademicLevelFromHeader();
        academicLevelInput.value = currentAcademicLevel;
        levelDisplayText.textContent = academicLevelDisplay[currentAcademicLevel] || 'Primary School';
        populateClassDropdown(currentAcademicLevel);
    }

    function openModal() {
        if (!classModal) return;
        classModal.classList.add('active');
        document.body.style.overflow = 'hidden';
        updateModalAcademicLevel();
    }

    function closeModalFunc() {
        if (!classModal) return;
        classModal.classList.remove('active');
        document.body.style.overflow = '';
        resetForm();
    }

    function resetForm() {
        if (!classForm) return;
        classForm.reset();
        if (classIdInput) classIdInput.value = '';
        if (modalTitle) modalTitle.textContent = PERMISSIONS.canCreate ? 'Add New Class' : 'Edit Class';
        if (saveBtnText) saveBtnText.textContent = PERMISSIONS.canCreate ? 'Save Class' : 'Update Class';
        updateModalAcademicLevel();
    }

    // Stream Modal Functions
    async function openAddStreamModal(classId) {
        if (!PERMISSIONS.canManageStreams && !PERMISSIONS.isSuperAdmin) {
            showToast('Access Denied', 'You do not have permission to manage streams', 'error');
            return;
        }
        
        const classData = classesData.find(c => c.id == classId);
        if (!classData) return;

        if (!streamClassIdInput || !streamClassNameInput || !streamModalTitle || !streamForm || !streamNameInput) return;

        streamClassIdInput.value = classId;
        streamClassNameInput.value = classData.class_level;
        streamModalTitle.textContent = `Add Streams to ${escapeHtml(classData.class_level)}`;
        streamForm.reset();

        await loadClassStreams(classId);
        const streams = streamsCache[classId] || [];
        
        if (existingStreamsContainer && existingStreamsList) {
            if (streams.length > 0) {
                existingStreamsContainer.style.display = 'block';
                existingStreamsList.innerHTML = streams.map(stream => 
                    `<div class="stream-badge">${escapeHtml(stream.stream_name)}</div>`
                ).join('');
            } else {
                existingStreamsContainer.style.display = 'none';
            }
        }

        if (streamModal) {
            streamModal.classList.add('active');
            document.body.style.overflow = 'hidden';
            setTimeout(() => streamNameInput.focus(), 100);
        }
    }

    function closeStreamModalFunc() {
        if (!streamModal) return;
        streamModal.classList.remove('active');
        document.body.style.overflow = '';
        if (streamForm) streamForm.reset();
        if (existingStreamsContainer) existingStreamsContainer.style.display = 'none';
    }

    // Search Functionality
    function setupSearch() {
        if (!searchInput) return;
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = classesTableBody.querySelectorAll('tr[data-class-id]');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
    }

    // API Functions
    async function submitClassForm(formData) {
        try {
            const response = await fetch('/api_handlers/class_handler.php', {
                method: 'POST',
                body: formData
            });
            return await response.json();
        } catch (error) {
            return {
                success: false,
                message: 'Network error: ' + error.message
            };
        }
    }

    async function fetchClassData(classId) {
        try {
            const response = await fetch(`/api_handlers/class_handler.php?action=get_class&class_id=${classId}`);
            return await response.json();
        } catch (error) {
            return {
                success: false,
                message: 'Failed to load class data: ' + error.message
            };
        }
    }

    async function submitStreamForm(formData) {
        try {
            const response = await fetch('/api_handlers/stream_handler.php', {
                method: 'POST',
                body: formData
            });
            return await response.json();
        } catch (error) {
            return {
                success: false,
                message: 'Network error: ' + error.message
            };
        }
    }

    async function deleteClassApi(classId) {
        const formData = new FormData();
        formData.append('action', 'delete_class');
        formData.append('class_id', classId);
        
        try {
            const response = await fetch('/api_handlers/class_handler.php', {
                method: 'POST',
                body: formData
            });
            return await response.json();
        } catch (error) {
            return {
                success: false,
                message: 'Network error: ' + error.message
            };
        }
    }

    async function deleteStreamApi(streamId) {
        const formData = new FormData();
        formData.append('action', 'delete_stream');
        formData.append('stream_id', streamId);
        
        try {
            const response = await fetch('/api_handlers/stream_handler.php', {
                method: 'POST',
                body: formData
            });
            return await response.json();
        } catch (error) {
            return {
                success: false,
                message: 'Network error: ' + error.message
            };
        }
    }

    // Enhanced Event Handlers with permission checks
    if (classForm) {
        classForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            if (!PERMISSIONS.canCreate && !PERMISSIONS.canEdit && !PERMISSIONS.isSuperAdmin) {
                showToast('Access Denied', 'You do not have permission to add/edit classes', 'error');
                return;
            }
            
            const isEdit = classIdInput && classIdInput.value !== '';
            const formData = new FormData(classForm);
            formData.append('action', isEdit ? 'edit_class' : 'add_class');
            
            const className = formData.get('class_name');
            formData.set('class_level', className);
            
            if (saveBtn) showLoading(saveBtn, isEdit ? 'Updating...' : 'Saving...');
            
            try {
                const result = await submitClassForm(formData);
                
                if (result.success) {
                    showToast(
                        'Success', 
                        result.message || (isEdit ? 'Class updated successfully!' : 'Class added successfully!'), 
                        'success'
                    );
                    
                    closeModalFunc();
                    
                    if (isEdit && classIdInput) {
                        const classData = await fetchClassData(classIdInput.value);
                        if (classData.success) {
                            updateClassInTable(classData.data);
                        }
                    } else {
                        const newClassData = {
                            id: result.class_id,
                            class_level: className,
                            academic_level: formData.get('academic_level'),
                            teacher_id: formData.get('teacher_id'),
                            student_count: 0
                        };
                        addClassToTable(newClassData);
                    }
                    
                    setTimeout(loadClasses, 500);
                } else {
                    showToast('Error', result.message || 'Operation failed', 'error');
                }
            } catch (error) {
                showToast('Error', error.message, 'error');
            } finally {
                if (saveBtn) hideLoading(saveBtn);
            }
        });
    }

    if (streamForm) {
        streamForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            if (!PERMISSIONS.canManageStreams && !PERMISSIONS.isSuperAdmin) {
                showToast('Access Denied', 'You do not have permission to manage streams', 'error');
                return;
            }
            
            const formData = new FormData(streamForm);
            formData.append('action', 'add_stream');
            
            if (saveStreamBtn) showLoading(saveStreamBtn, 'Adding...');
            
            try {
                const result = await submitStreamForm(formData);
                
                if (result.success) {
                    showToast('Success', result.message || 'Stream added successfully!', 'success');
                    streamForm.reset();
                    await loadClassStreams(streamClassIdInput.value);
                    
                    const streams = streamsCache[streamClassIdInput.value] || [];
                    if (existingStreamsContainer && existingStreamsList) {
                        if (streams.length > 0) {
                            existingStreamsContainer.style.display = 'block';
                            existingStreamsList.innerHTML = streams.map(stream => 
                                `<div class="stream-badge">${escapeHtml(stream.stream_name)}</div>`
                            ).join('');
                        }
                    }
                    
                    if (streamNameInput) setTimeout(() => streamNameInput.focus(), 100);
                } else {
                    showToast('Error', result.message || 'Failed to add stream', 'error');
                }
            } catch (error) {
                showToast('Error', error.message, 'error');
            } finally {
                if (saveStreamBtn) hideLoading(saveStreamBtn);
            }
        });
    }

    // Delete Modal Functions
    function showDeleteModal(classId, className) {
        if (!PERMISSIONS.canDelete && !PERMISSIONS.isSuperAdmin) {
            showToast('Access Denied', 'You do not have permission to delete classes', 'error');
            return;
        }
        
        const modalHTML = `
            <div class="modal-overlay active delete-modal" id="deleteModal">
                <div class="modal">
                    <div class="modal-header">
                        <h3 class="modal-title">
                            <i class="fas fa-exclamation-triangle" style="color: var(--error-red);"></i>
                            Confirm Delete
                        </h3>
                        <button class="close-modal" onclick="closeDeleteModal()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete the class <strong>"${className}"</strong>?</p>
                        <div class="delete-warning">
                            <i class="fas fa-info-circle"></i>
                            This action will also delete all associated streams and cannot be undone.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">
                            Cancel
                        </button>
                        <button type="button" class="btn btn-danger" onclick="confirmDeleteClass(${classId})">
                            <i class="fas fa-trash"></i> Delete Class
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }

    window.closeDeleteModal = function() {
        const deleteModal = document.getElementById('deleteModal');
        if (deleteModal) deleteModal.remove();
    };

    window.confirmDeleteClass = async function(classId) {
        if (!PERMISSIONS.canDelete && !PERMISSIONS.isSuperAdmin) {
            showToast('Access Denied', 'You do not have permission to delete classes', 'error');
            closeDeleteModal();
            return;
        }
        
        const deleteBtn = document.querySelector('#deleteModal .btn-danger');
        if (deleteBtn) showLoading(deleteBtn, 'Deleting...');
        
        try {
            const result = await deleteClassApi(classId);
            
            if (result.success) {
                showToast('Success', result.message || 'Class deleted successfully!', 'success');
                closeDeleteModal();
                removeClassFromTable(classId);
                delete streamsCache[classId];
            } else {
                showToast('Error', result.message || 'Failed to delete class', 'error');
            }
        } catch (error) {
            showToast('Error', error.message, 'error');
        } finally {
            if (deleteBtn) hideLoading(deleteBtn);
        }
    };

    // Stream Removal
    window.removeStream = async function(streamId, streamName) {
        if (!PERMISSIONS.canManageStreams && !PERMISSIONS.isSuperAdmin) {
            showToast('Access Denied', 'You do not have permission to remove streams', 'error');
            return;
        }
        
        if (!confirm(`Are you sure you want to remove stream "${streamName}"?`)) {
            return;
        }

        try {
            const result = await deleteStreamApi(streamId);
            
            if (result.success) {
                showToast('Success', result.message || 'Stream removed successfully!', 'success');
                
                for (const classId in streamsCache) {
                    streamsCache[classId] = streamsCache[classId].filter(s => s.id != streamId);
                }
                
                let foundClassId = null;
                for (const classId in streamsCache) {
                    if (streamsCache[classId].some(s => s.id == streamId)) {
                        foundClassId = classId;
                        break;
                    }
                }
                
                if (foundClassId) {
                    updateStreamsDisplay(foundClassId);
                }
            } else {
                showToast('Error', result.message || 'Failed to remove stream', 'error');
            }
        } catch (error) {
            showToast('Error', error.message, 'error');
        }
    };

    window.editClass = async function(classId) {
        if (!PERMISSIONS.canEdit && !PERMISSIONS.isSuperAdmin) {
            showToast('Access Denied', 'You do not have permission to edit classes', 'error');
            return;
        }
        
        try {
            if (saveBtn) showLoading(saveBtn, 'Loading...');
            openModal();
            
            const result = await fetchClassData(classId);
            
            if (result.success) {
                const classData = result.data;
                
                if (classIdInput) classIdInput.value = classData.id;
                if (academicLevelInput) academicLevelInput.value = classData.academic_level;
                
                currentAcademicLevel = classData.academic_level;
                if (levelDisplayText) levelDisplayText.textContent = academicLevelDisplay[classData.academic_level] || 'Primary School';
                populateClassDropdown(classData.academic_level);
                
                setTimeout(() => {
                    if (classNameSelect) classNameSelect.value = classData.class_level;
                    if (teacherIdSelect) teacherIdSelect.value = classData.teacher_id || '';
                }, 100);
                
                if (modalTitle) modalTitle.textContent = 'Edit Class';
                if (saveBtnText) saveBtnText.textContent = 'Update Class';
            } else {
                showToast('Error', result.message || 'Failed to load class data', 'error');
                closeModalFunc();
            }
        } catch (error) {
            showToast('Error', error.message, 'error');
            closeModalFunc();
        } finally {
            if (saveBtn) hideLoading(saveBtn);
        }
    };

    // Initialize
    document.addEventListener('DOMContentLoaded', () => {
        loadClasses();
        
        if (addClassBtn) {
            addClassBtn.addEventListener('click', () => {
                if (!PERMISSIONS.canCreate && !PERMISSIONS.isSuperAdmin) {
                    showToast('Access Denied', 'You do not have permission to add classes', 'error');
                    return;
                }
                resetForm();
                openModal();
            });
        }

        if (closeModal) closeModal.addEventListener('click', closeModalFunc);
        if (cancelBtn) cancelBtn.addEventListener('click', closeModalFunc);
        
        if (classModal) {
            classModal.addEventListener('click', (e) => {
                if (e.target === classModal) closeModalFunc();
            });
        }

        if (closeStreamModal) closeStreamModal.addEventListener('click', closeStreamModalFunc);
        if (cancelStreamBtn) cancelStreamBtn.addEventListener('click', closeStreamModalFunc);
        
        if (streamModal) {
            streamModal.addEventListener('click', (e) => {
                if (e.target === streamModal) closeStreamModalFunc();
            });
        }

        setupSearch();

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeModalFunc();
                closeStreamModalFunc();
                closeDeleteModal();
            }
        });
    });
    </script>
</body>
</html>