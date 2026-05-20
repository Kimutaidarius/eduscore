<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session and include config
require_once 'includes/config.php';
require_once 'includes/PermissionHelper.php';
require_once 'includes/session_timeout.php'; 

// Security check - ensure user is logged in
if (empty($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
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

// Check if user has permission to view teachers page
$permissionHelper->requireAnyPermission(['teachersView', 'teachersViewAll'], 'dashboard.php');

// Determine which actions are allowed based on permissions
$canCreate = $permissionHelper->hasPermission('teachersCreate');
$canEdit = $permissionHelper->hasPermission('teachersEdit');
$canDelete = $permissionHelper->hasPermission('teachersDelete');
$canAssignSubjects = $permissionHelper->hasPermission('teachersAssignSubjects');
$canImport = $permissionHelper->hasPermission('teachersImport');
$canExport = $permissionHelper->hasPermission('teachersExport');
$canViewAll = $permissionHelper->hasPermission('teachersViewAll');
$isSuperAdmin = $permissionHelper->isSuperAdmin();
$currentUserRole = $permissionHelper->getRole();
$currentUserId = $_SESSION['teacher_id'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EduScore - Teacher Management</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="icon" type="image/png" href="images/logo.png" />
<link rel="apple-touch-icon" href="images/logo.png">
<link rel="stylesheet" href="assets/banner/banner.css">
<style>
/* Your existing CSS styles remain the same */
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

/* Page Header */
.page-header {
    background: var(--bg-white);
    border-radius: var(--border-radius);
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: var(--shadow);
    border-left: 4px solid var(--primary-blue);
    position: relative;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.page-header::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    height: 2px;
    background: linear-gradient(90deg, var(--accent-yellow), transparent);
}

.header-left {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.teachers-page-title {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--text-dark);
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.teachers-page-title i {
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

/* Stats Cards */
.stats-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: var(--bg-white);
    border-radius: var(--border-radius);
    padding: 1.5rem;
    box-shadow: var(--shadow);
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: var(--transition);
    border-top: 3px solid var(--primary-blue);
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}

.stat-icon.total { background: var(--light-blue); color: var(--primary-blue); }
.stat-icon.active { background: #d1fae5; color: var(--success-green); }
.stat-icon.inactive { background: #fef3c7; color: var(--warning-orange); }
.stat-icon.leave { background: #f3f4f6; color: var(--text-light); }

.stat-content {
    flex: 1;
}

.stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-dark);
    line-height: 1.2;
}

.stat-label {
    font-size: 0.875rem;
    color: var(--text-light);
}

/* Action Bar */
.action-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    gap: 1rem;
    flex-wrap: wrap;
    background: var(--bg-white);
    padding: 1rem 1.5rem;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow);
}

.teachers-search-box {
    position: relative;
    flex: 1;
    max-width: 400px;
    min-width: 250px;
}

.teachers-search-input {
    width: 100%;
    padding: 0.75rem 1rem 0.75rem 2.5rem;
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    font-size: 0.9rem;
    transition: var(--transition);
    background: var(--bg-white);
}

.teachers-search-input:focus {
    outline: none;
    border-color: var(--primary-blue);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.teachers-search-icon {
    position: absolute;
    left: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-light);
}

.action-buttons {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.action-btn {
    padding: 0.75rem 1.25rem;
    border: none;
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

.action-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
    color: white;
}

.btn-primary:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.btn-secondary {
    background: var(--light-blue);
    color: var(--primary-blue);
}

.btn-secondary:hover:not(:disabled) {
    background: var(--secondary-blue);
    color: white;
}

.btn-success {
    background: linear-gradient(135deg, var(--success-green), #059669);
    color: white;
}

.btn-success:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

/* Teachers Table */
.teachers-table-container {
    background: var(--bg-white);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow);
    overflow: hidden;
    min-height: 400px;
}

.table-responsive {
    overflow-x: auto;
}

.teachers-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1000px;
}

.teachers-table th {
    background: var(--primary-blue);
    padding: 1rem 1.5rem;
    text-align: left;
    font-weight: 600;
    color: white;
    border-bottom: 2px solid var(--accent-yellow);
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.teachers-table th i {
    margin-right: 0.5rem;
}

.teachers-table td {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--border-color);
    font-size: 0.9rem;
}

.teachers-table tr:last-child td {
    border-bottom: none;
}

.teachers-table tr:hover {
    background: var(--bg-light);
}

/* Teacher Info */
.teacher-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.teacher-avatar {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, var(--secondary-blue), var(--primary-blue));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 0.9rem;
    flex-shrink: 0;
}

.teacher-details {
    flex: 1;
    min-width: 0;
}

.teacher-name {
    font-weight: 600;
    color: var(--text-dark);
    margin-bottom: 0.125rem;
    cursor: pointer;
    transition: var(--transition);
    text-decoration: none;
}

.teacher-name:hover {
    color: var(--primary-blue);
    text-decoration: underline;
}

.teacher-id {
    font-size: 0.8rem;
    color: var(--text-light);
    font-family: monospace;
}

.teacher-contact {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.contact-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
}

.contact-item i {
    width: 16px;
    color: var(--text-light);
}

/* Status Badge */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.375rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
    white-space: nowrap;
}

.status-active {
    background: #d1fae5;
    color: var(--success-green);
    border: 1px solid #a7f3d0;
}

.status-inactive {
    background: #fef3c7;
    color: var(--warning-orange);
    border: 1px solid #fde68a;
}

.status-leave {
    background: #f3f4f6;
    color: var(--text-light);
    border: 1px solid #e5e7eb;
}

/* Subjects Display */
.subjects-container {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    max-width: 300px;
}

.subject-badge {
    background: var(--light-blue);
    color: var(--primary-blue);
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
    border: 1px solid rgba(37, 99, 235, 0.2);
    white-space: nowrap;
}

/* Actions */
.actions {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.action-btn-small {
    width: 32px;
    height: 32px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    transition: var(--transition);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
}

.action-btn-small:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}

.edit-btn {
    background: var(--light-blue);
    color: var(--primary-blue);
}

.edit-btn:hover:not(:disabled) {
    background: var(--secondary-blue);
    color: white;
}

.delete-btn {
    background: #fef2f2;
    color: var(--error-red);
}

.delete-btn:hover:not(:disabled) {
    background: var(--error-red);
    color: white;
}

.assign-btn {
    background: #f0f9ff;
    color: #0ea5e9;
}

.assign-btn:hover:not(:disabled) {
    background: #0ea5e9;
    color: white;
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
    color: var(--primary-blue);
}

.empty-state h3 {
    font-size: 1.25rem;
    margin-bottom: 0.5rem;
    color: var(--text-dark);
}

.empty-state p {
    margin-bottom: 1.5rem;
}

/* Protected Teacher Badge */
.protected-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: linear-gradient(135deg, #ef4444, #b91c1c);
    color: white;
    border-radius: 30px;
    font-size: 0.85rem;
    font-weight: 600;
    box-shadow: 0 4px 10px rgba(239, 68, 68, 0.3);
    border: 1px solid rgba(255, 255, 255, 0.2);
    white-space: nowrap;
    transition: var(--transition);
}

.protected-badge i {
    font-size: 0.9rem;
    filter: drop-shadow(0 2px 2px rgba(0, 0, 0, 0.2));
}

.protected-badge:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(239, 68, 68, 0.4);
    background: linear-gradient(135deg, #dc2626, #991b1b);
}

/* Highlight protected row */
.protected-row {
    background: rgba(239, 68, 68, 0.02) !important;
    border-left: 3px solid #ef4444;
}

.protected-row:hover {
    background: rgba(239, 68, 68, 0.05) !important;
}

/* Super Admin badge styling */
.super-admin-badge {
    background: linear-gradient(135deg, #7c3aed, #5b21b6);
    color: white;
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
    padding: 1rem;
    backdrop-filter: blur(4px);
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
    max-width: 700px;
    max-height: 90vh;
    overflow-y: auto;
    animation: slideUp 0.3s ease;
    border-top: 4px solid var(--accent-yellow);
}

.modal-header {
    padding: 1.5rem 2rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: var(--primary-blue);
    color: white;
    border-radius: var(--border-radius) var(--border-radius) 0 0;
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
    transition: var(--transition);
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.close-modal:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: rotate(90deg);
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
}

/* Form Styles */
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

.form-label.required::after {
    content: '*';
    color: var(--error-red);
    margin-left: 0.25rem;
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
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.form-control.readonly-input {
    background: var(--bg-light);
    color: var(--text-light);
    cursor: not-allowed;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.btn {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: var(--border-radius);
    font-weight: 600;
    font-size: 0.9rem;
    cursor: pointer;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
    color: white;
}

.btn-primary:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.btn-secondary {
    background: var(--bg-light);
    color: var(--text-dark);
    border: 1px solid var(--border-color);
}

.btn-secondary:hover:not(:disabled) {
    background: var(--border-color);
}

.btn-danger {
    background: linear-gradient(135deg, var(--error-red), #b91c1c);
    color: white;
}

.btn-danger:hover:not(:disabled) {
    background: linear-gradient(135deg, #dc2626, #991b1b);
}

/* Loading States */
.loading-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 4rem 2rem;
    text-align: center;
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
    border-top-color: var(--accent-yellow);
    animation-delay: 0.2s;
    animation-duration: 1.6s;
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
    box-shadow: 0 4px 15px rgba(30, 58, 138, 0.2);
}

.loading-content {
    text-align: center;
}

.loading-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--text-dark);
    margin-bottom: 0.75rem;
}

.loading-text {
    color: var(--text-light);
    font-size: 0.95rem;
}

/* Subject Checkbox Container */
.subjects-select-container {
    max-height: 300px;
    overflow-y: auto;
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    padding: 1rem;
    background: var(--bg-light);
}

.subject-checkbox {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    border-radius: 6px;
    transition: var(--transition);
    cursor: pointer;
}

.subject-checkbox:hover {
    background: white;
}

.subject-checkbox input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.subject-checkbox label {
    flex: 1;
    cursor: pointer;
    font-size: 0.9rem;
}

/* Import Section */
.import-section {
    background: var(--bg-light);
    border-radius: var(--border-radius);
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.import-section h4 {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 1rem;
    color: var(--text-dark);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.file-upload-area {
    border: 2px dashed var(--border-color);
    border-radius: var(--border-radius);
    padding: 2rem;
    text-align: center;
    cursor: pointer;
    transition: var(--transition);
    background: white;
}

.file-upload-area:hover {
    border-color: var(--primary-blue);
    background: var(--light-blue);
}

.file-upload-area.active {
    border-color: var(--success-green);
    background: #d1fae5;
}

.upload-icon {
    font-size: 3rem;
    color: var(--text-light);
    margin-bottom: 1rem;
}

.file-input {
    display: none;
}

.download-template {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--primary-blue);
    text-decoration: none;
    font-weight: 500;
    margin-top: 1rem;
    padding: 0.5rem 1rem;
    border-radius: var(--border-radius);
    background: var(--light-blue);
    transition: var(--transition);
}

.download-template:hover {
    background: var(--secondary-blue);
    color: white;
}

/* Delete Warning */
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

.delete-modal .modal {
    max-width: 400px;
}

/* Toast Notifications */
.toast-container {
    position: fixed;
    top: 24px;
    right: 24px;
    z-index: 999999;
    display: flex;
    flex-direction: column;
    gap: 12px;
    max-width: 380px;
    width: 100%;
    pointer-events: none;
}

.toast {
    background: white;
    border-radius: 16px;
    padding: 16px 20px;
    box-shadow: 0 20px 40px -12px rgba(0, 0, 0, 0.25), 0 8px 24px -6px rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: flex-start;
    gap: 14px;
    position: relative;
    width: 100%;
    pointer-events: auto;
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    animation: toastSlideIn 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55) forwards;
}

.toast.success {
    background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
    border-left: 4px solid #22c55e;
}

.toast.error {
    background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
    border-left: 4px solid #ef4444;
}

.toast.warning {
    background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
    border-left: 4px solid #f59e0b;
}

.toast.info {
    background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
    border-left: 4px solid #3b82f6;
}

.toast-icon {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 18px;
    flex-shrink: 0;
    box-shadow: 0 8px 16px -4px rgba(0, 0, 0, 0.15);
}

.toast.success .toast-icon {
    background: linear-gradient(135deg, #22c55e, #16a34a);
}

.toast.error .toast-icon {
    background: linear-gradient(135deg, #ef4444, #dc2626);
}

.toast.warning .toast-icon {
    background: linear-gradient(135deg, #f59e0b, #d97706);
}

.toast.info .toast-icon {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
}

.toast-content {
    flex: 1;
}

.toast-title {
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 4px;
    font-size: 16px;
    letter-spacing: -0.02em;
}

.toast-message {
    color: #4b5563;
    font-size: 14px;
    line-height: 1.5;
    word-break: break-word;
}

.toast-close {
    background: rgba(255, 255, 255, 0.5);
    border: none;
    color: #9ca3af;
    cursor: pointer;
    font-size: 20px;
    padding: 0;
    transition: all 0.2s ease;
    flex-shrink: 0;
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    line-height: 1;
}

.toast-close:hover {
    background: rgba(255, 255, 255, 0.9);
    color: #4b5563;
    transform: rotate(90deg);
}

.toast-progress {
    position: absolute;
    bottom: 0;
    left: 0;
    height: 3px;
    background: rgba(0, 0, 0, 0.1);
    border-radius: 0 0 0 16px;
    animation: progressShrink 5s linear forwards;
    width: 100%;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
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

@keyframes toastSlideIn {
    0% {
        opacity: 0;
        transform: translateX(100%) scale(0.8);
    }
    50% {
        transform: translateX(-10px) scale(1.02);
    }
    100% {
        opacity: 1;
        transform: translateX(0) scale(1);
    }
}

@keyframes progressShrink {
    from { width: 100%; }
    to { width: 0%; }
}

@keyframes rotate {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Responsive */
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
    
    .teachers-search-box {
        max-width: none;
    }
    
    .action-buttons {
        width: 100%;
    }
    
    .action-btn {
        flex: 1;
        justify-content: center;
    }
    
    .modal {
        max-width: 95%;
        margin: 1rem;
    }
    
    .modal-header,
    .modal-body,
    .modal-footer {
        padding: 1.25rem;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .toast-container {
        top: 16px;
        right: 16px;
        left: 16px;
        max-width: none;
    }
    
    .toast {
        padding: 14px 16px;
    }
    
    .toast-icon {
        width: 36px;
        height: 36px;
        font-size: 16px;
    }
    
    .toast-title {
        font-size: 15px;
    }
    
    .toast-message {
        font-size: 13px;
    }
}

@media (max-width: 480px) {
    .teachers-table td,
    .teachers-table th {
        padding: 0.75rem 1rem;
    }

    .teacher-info {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
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
        <div class="header-left">
            <h1 class="teachers-page-title">
                <i class="fas fa-chalkboard-teacher"></i>
                Teacher Management
            </h1>
            <span class="role-badge">
                <i class="fas fa-<?php echo $isSuperAdmin ? 'crown' : 'user-tag'; ?>"></i>
                <?php echo htmlspecialchars($currentUserRole ?? 'User'); ?>
            </span>
        </div>
        <p class="page-description">Manage teacher accounts, roles, and subject assignments</p>
    </div>

    <?php if (!$permissionHelper->hasAnyPermission(['teachersView', 'teachersViewAll'])): ?>
        <div class="permission-denied">
            <i class="fas fa-lock"></i>
            <h3>Access Denied</h3>
            <p>You do not have permission to view teachers.</p>
            <p style="font-size: 0.9rem; margin-top: 0.5rem;">Please contact your system administrator if you need access.</p>
        </div>
    <?php else: ?>

    <!-- Stats Cards -->
    <div class="stats-container" id="statsContainer">
        <div class="stat-card">
            <div class="stat-icon total">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value" id="totalTeachers">0</div>
                <div class="stat-label">Total Teachers</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon active">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value" id="activeTeachers">0</div>
                <div class="stat-label">Active</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon inactive">
                <i class="fas fa-user-slash"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value" id="inactiveTeachers">0</div>
                <div class="stat-label">Inactive</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon leave">
                <i class="fas fa-umbrella-beach"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value" id="onLeaveTeachers">0</div>
                <div class="stat-label">On Leave</div>
            </div>
        </div>
    </div>

    <!-- Action Bar -->
    <div class="action-bar">
        <div class="teachers-search-box">
            <i class="fas fa-search teachers-search-icon"></i>
            <input type="text" class="teachers-search-input" id="searchInput" placeholder="Search teachers by name, ID, or email...">
        </div>
        <div class="action-buttons">
            <?php if ($canCreate): ?>
                <button class="action-btn btn-primary" id="addTeacherBtn">
                    <i class="fas fa-user-plus"></i>
                    Add Teacher
                </button>
            <?php endif; ?>
            <?php if ($canImport): ?>
                <button class="action-btn btn-secondary" id="importBtn">
                    <i class="fas fa-file-import"></i>
                    Import Excel
                </button>
            <?php endif; ?>
            <?php if ($canExport): ?>
                <button class="action-btn btn-success" id="exportBtn">
                    <i class="fas fa-file-pdf"></i>
                    Export PDF
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Teachers Table -->
    <div class="teachers-table-container">
        <div class="table-responsive">
            <table class="teachers-table">
                <thead>
                    <tr>
                        <th><i class="fas fa-id-card"></i> Teacher</th>
                        <th><i class="fas fa-phone"></i> Contact</th>
                        <th><i class="fas fa-book"></i> Subjects</th>
                        <th><i class="fas fa-user-tag"></i> Role</th>
                        <th><i class="fas fa-info-circle"></i> Status</th>
                        <th><i class="fas fa-cogs"></i> Actions</th>
                    </tr>
                </thead>
                <tbody id="teachersTableBody">
                    <tr>
                        <td colspan="6">
                            <div class="loading-state">
                                <div class="modern-spinner">
                                    <div class="spinner-container">
                                        <div class="spinner-ring"></div>
                                        <div class="spinner-ring"></div>
                                        <div class="spinner-ring"></div>
                                        <div class="spinner-center">
                                            <i class="fas fa-chalkboard-teacher"></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="loading-content">
                                    <h3 class="loading-title">Loading Teacher Data</h3>
                                    <p class="loading-text">Please wait while we fetch teacher information...</p>
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
<!-- Add/Edit Teacher Modal -->
<div class="modal-overlay" id="teacherModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fas fa-user-plus"></i>
                <span id="modalTitle">Add New Teacher</span>
            </h3>
            <button class="close-modal" id="closeModal">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="teacherForm">
            <div class="modal-body">
                <input type="hidden" id="teacherId" name="teacher_id">
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required" for="title">Title</label>
                        <select class="form-control form-select" id="title" name="title">
                            <option value="">Select Title</option>
                            <option value="Mr.">Mr.</option>
                            <option value="Mrs.">Mrs.</option>
                            <option value="Miss">Miss</option>
                            <option value="Dr.">Dr.</option>
                            <option value="Prof.">Prof.</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label required" for="gender">Gender</label>
                        <select class="form-control form-select" id="gender" name="gender" required>
                            <option value="">Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required" for="firstname">First Name</label>
                        <input type="text" class="form-control" id="firstname" name="firstname" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="middlename">Middle Name</label>
                        <input type="text" class="form-control" id="middlename" name="middlename">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label required" for="lastname">Last Name</label>
                    <input type="text" class="form-control" id="lastname" name="lastname" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required" for="email">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label required" for="phonenumber">Phone Number</label>
                        <input type="tel" class="form-control" id="phonenumber" name="phonenumber" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required" for="role">Role</label>
                        <select class="form-control form-select" id="role" name="role" required>
                            <option value="">Select Role</option>
                            <option value="Teacher">Teacher</option>
                            <option value="ICT Teacher">ICT Teacher</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label required" for="status">Status</label>
                        <select class="form-control form-select" id="status" name="status" required>
                            <option value="">Select Status</option>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                            <option value="On Leave">On Leave</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="teacher_number">Teacher ID</label>
                    <input type="text" class="form-control readonly-input" id="teacher_number" name="teacher_number" readonly>
                    <small class="form-text">Auto-generated teacher ID</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="cancelBtn">Cancel</button>
                <button type="submit" class="btn btn-primary" id="saveBtn">
                    <i class="fas fa-save"></i>
                    <span id="saveBtnText">Save Teacher</span>
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($canImport): ?>
<!-- Import Teachers Modal -->
<div class="modal-overlay" id="importModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fas fa-file-import"></i>
                Import Teachers from Excel
            </h3>
            <button class="close-modal" id="closeImportModal">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="importForm">
            <div class="modal-body">
                <div class="import-section">
                    <h4><i class="fas fa-download"></i> Download Template</h4>
                    <p>Download the Excel template to ensure proper formatting:</p>
                    <a href="javascript:void(0)" class="download-template" id="downloadTemplateBtn">
                        <i class="fas fa-file-excel"></i>
                        Download Excel Template
                    </a>
                </div>

                <div class="import-section">
                    <h4><i class="fas fa-upload"></i> Upload Excel File</h4>
                    <div class="file-upload-area" id="fileUploadArea">
                        <div class="upload-icon">
                            <i class="fas fa-cloud-upload-alt"></i>
                        </div>
                        <p>Drag & drop your Excel file here or click to browse</p>
                        <p class="file-info">Supported formats: .xlsx, .xls, .csv</p>
                        <input type="file" class="file-input" id="excelFile" accept=".xlsx,.xls,.csv" required>
                    </div>
                    <div id="fileInfo"></div>
                </div>

                <div class="import-section">
                    <h4><i class="fas fa-info-circle"></i> Import Instructions</h4>
                    <ul style="padding-left: 1.5rem; color: var(--text-light); font-size: 0.9rem;">
                        <li>Use the provided template for correct column format</li>
                        <li>Ensure email addresses are unique</li>
                        <li>Required columns: First Name, Last Name, Email, Phone</li>
                        <li>Phone numbers must be in international format</li>
                        <li>Maximum file size: 10MB</li>
                        <li>Role column is optional. Leave empty for default role</li>
                    </ul>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="cancelImportBtn">Cancel</button>
                <button type="submit" class="btn btn-primary" id="importSubmitBtn">
                    <i class="fas fa-upload"></i>
                    <span>Import Teachers</span>
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($canAssignSubjects): ?>
<!-- Assign Subjects Modal -->
<div class="modal-overlay" id="assignModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fas fa-book"></i>
                <span id="assignModalTitle">Assign Subjects</span>
            </h3>
            <button class="close-modal" id="closeAssignModal">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="assignForm">
            <div class="modal-body">
                <input type="hidden" id="assignTeacherId" name="teacher_id">
                
                <div class="form-group">
                    <label class="form-label" for="teacherNameDisplay">Teacher</label>
                    <input type="text" class="form-control readonly-input" id="teacherNameDisplay" readonly>
                </div>

                <div class="form-group">
                    <label class="form-label required">Select Subjects</label>
                    <div class="subjects-select-container" id="subjectsContainer">
                        <div class="loading-text">Loading subjects...</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="cancelAssignBtn">Cancel</button>
                <button type="submit" class="btn btn-primary" id="saveAssignBtn">
                    <i class="fas fa-save"></i>
                    <span>Save Assignments</span>
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($canDelete): ?>
<!-- Delete Confirmation Modal -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal delete-modal">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fas fa-exclamation-triangle"></i>
                Confirm Delete
            </h3>
            <button class="close-modal" id="closeDeleteModal">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <p>Are you sure you want to <strong>permanently delete</strong> teacher <strong id="deleteTeacherName"></strong>?</p>
            <div class="delete-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Warning:</strong> This action cannot be undone! All data associated with this teacher (including subject assignments) will be permanently deleted.
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" id="cancelDeleteBtn">Cancel</button>
            <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                <i class="fas fa-trash"></i> Permanently Delete
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Toast Container -->
<div class="toast-container" id="toastContainer"></div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<!-- Include banner JavaScript -->
<script src="assets/banner/banner.js"></script>

<script>
// DOM Elements
const teacherModal = document.getElementById('teacherModal');
const teacherForm = document.getElementById('teacherForm');
const modalTitle = document.getElementById('modalTitle');
const teacherIdInput = document.getElementById('teacherId');
const teacherNumberInput = document.getElementById('teacher_number');
const addTeacherBtn = document.getElementById('addTeacherBtn');
const closeModal = document.getElementById('closeModal');
const cancelBtn = document.getElementById('cancelBtn');
const saveBtn = document.getElementById('saveBtn');
const saveBtnText = document.getElementById('saveBtnText');
const searchInput = document.getElementById('searchInput');
const teachersTableBody = document.getElementById('teachersTableBody');

// Import Modal Elements
const importModal = document.getElementById('importModal');
const importForm = document.getElementById('importForm');
const closeImportModal = document.getElementById('closeImportModal');
const cancelImportBtn = document.getElementById('cancelImportBtn');
const importSubmitBtn = document.getElementById('importSubmitBtn');
const fileUploadArea = document.getElementById('fileUploadArea');
const excelFileInput = document.getElementById('excelFile');
const fileInfo = document.getElementById('fileInfo');
const downloadTemplateBtn = document.getElementById('downloadTemplateBtn');

// Assign Modal Elements
const assignModal = document.getElementById('assignModal');
const assignForm = document.getElementById('assignForm');
const assignModalTitle = document.getElementById('assignModalTitle');
const assignTeacherIdInput = document.getElementById('assignTeacherId');
const teacherNameDisplay = document.getElementById('teacherNameDisplay');
const subjectsContainer = document.getElementById('subjectsContainer');
const closeAssignModal = document.getElementById('closeAssignModal');
const cancelAssignBtn = document.getElementById('cancelAssignBtn');
const saveAssignBtn = document.getElementById('saveAssignBtn');

// Delete Modal Elements
const deleteModal = document.getElementById('deleteModal');
const deleteTeacherName = document.getElementById('deleteTeacherName');
const closeDeleteModal = document.getElementById('closeDeleteModal');
const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');

// Stats Elements
const totalTeachers = document.getElementById('totalTeachers');
const activeTeachers = document.getElementById('activeTeachers');
const inactiveTeachers = document.getElementById('inactiveTeachers');
const onLeaveTeachers = document.getElementById('onLeaveTeachers');

// State
let teachersData = [];
let subjectsData = [];
let deleteTeacherId = null;

// Permissions from PHP
const PERMISSIONS = {
    canCreate: <?php echo $canCreate ? 'true' : 'false'; ?>,
    canEdit: <?php echo $canEdit ? 'true' : 'false'; ?>,
    canDelete: <?php echo $canDelete ? 'true' : 'false'; ?>,
    canAssignSubjects: <?php echo $canAssignSubjects ? 'true' : 'false'; ?>,
    canImport: <?php echo $canImport ? 'true' : 'false'; ?>,
    canExport: <?php echo $canExport ? 'true' : 'false'; ?>,
    canViewAll: <?php echo $canViewAll ? 'true' : 'false'; ?>,
    isSuperAdmin: <?php echo $isSuperAdmin ? 'true' : 'false'; ?>,
    currentUserId: <?php echo $currentUserId; ?>
};

// Function to check if a teacher is Super Admin (protected)
function isTeacherProtected(teacher) {
    if (!teacher) return false;
    return teacher.role === 'Super Admin';
}

// Function to check if current user can edit a specific teacher
function canEditTeacher(teacher) {
    if (PERMISSIONS.isSuperAdmin) return true;
    if (isTeacherProtected(teacher)) return false;
    if (PERMISSIONS.canEdit && PERMISSIONS.canViewAll) return true;
    if (PERMISSIONS.canEdit && teacher.id == PERMISSIONS.currentUserId) return true;
    return false;
}

// Function to check if current user can delete a specific teacher
function canDeleteTeacher(teacher) {
    if (PERMISSIONS.isSuperAdmin) return true;
    if (isTeacherProtected(teacher)) return false;
    if (PERMISSIONS.canDelete && PERMISSIONS.canViewAll) return true;
    if (PERMISSIONS.canDelete && teacher.id == PERMISSIONS.currentUserId) return false;
    return false;
}

// Function to check if current user can assign subjects to a teacher
function canAssignSubjectsToTeacher(teacher) {
    if (PERMISSIONS.isSuperAdmin) return true;
    if (isTeacherProtected(teacher)) return false;
    return PERMISSIONS.canAssignSubjects;
}

// Function to sort teachers with Super Admins first
function sortTeachersWithProtectedFirst(teachers) {
    return [...teachers].sort((a, b) => {
        const aProtected = isTeacherProtected(a);
        const bProtected = isTeacherProtected(b);
        
        if (aProtected && !bProtected) return -1;
        if (!aProtected && bProtected) return 1;
        return 0;
    });
}

// Toast Notification Function
window.showToast = function(title, message, type = 'success', duration = 5000) {
    let container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        container.id = 'toastContainer';
        document.body.appendChild(container);
    }
    
    const toastId = 'toast-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
    let icon = 'check-circle';
    if (type === 'error') icon = 'exclamation-circle';
    else if (type === 'warning') icon = 'exclamation-triangle';
    else if (type === 'info') icon = 'info-circle';
    
    const toast = document.createElement('div');
    toast.id = toastId;
    toast.className = `toast ${type}`;
    
    toast.innerHTML = `
        <div class="toast-icon">
            <i class="fas fa-${icon}"></i>
        </div>
        <div class="toast-content">
            <div class="toast-title">${escapeHtml(title)}</div>
            <div class="toast-message">${escapeHtml(message)}</div>
        </div>
        <button class="toast-close" onclick="removeToast('${toastId}')">
            <i class="fas fa-times"></i>
        </button>
        <div class="toast-progress"></div>
    `;
    
    container.appendChild(toast);
    
    const timeoutId = setTimeout(() => removeToast(toastId), duration);
    toast.dataset.timeoutId = timeoutId;
};

window.removeToast = function(id) {
    const toast = document.getElementById(id);
    if (toast) {
        if (toast.dataset.timeoutId) {
            clearTimeout(parseInt(toast.dataset.timeoutId));
        }
        setTimeout(() => {
            if (toast.parentNode) toast.parentNode.removeChild(toast);
        }, 300);
        toast.remove();
    }
};

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// API Functions
async function fetchTeachers() {
    try {
        const response = await fetch('ajax/teacher.php?action=fetch_teachers');
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        const result = await response.json();
        
        if (result.success) {
            teachersData = result.data;
            updateStats(teachersData);
            renderTeachersTable(teachersData);
        } else {
            showToast('Error', result.message || 'Failed to load teachers', 'error');
        }
    } catch (error) {
        teachersTableBody.innerHTML = `
            <tr>
                <td colspan="6">
                    <div class="empty-state">
                        <i class="fas fa-exclamation-triangle" style="color: var(--error-red);"></i>
                        <h3>Error Loading Teachers</h3>
                        <p>${escapeHtml(error.message)}</p>
                        <button onclick="location.reload()" class="btn btn-primary">
                            <i class="fas fa-redo"></i> Retry
                        </button>
                    </div>
                </td>
            </tr>
        `;
        showToast('Connection Error', error.message, 'error');
    }
}

async function fetchSubjects() {
    try {
        const response = await fetch('ajax/teacher.php?action=fetch_subjects');
        const result = await response.json();
        
        if (result.success) {
            subjectsData = result.data;
        }
    } catch (error) {
        console.error('Error loading subjects:', error);
    }
}

async function fetchTeacherSubjects(teacherId) {
    try {
        const response = await fetch(`ajax/teacher.php?action=fetch_teacher_subjects&teacher_id=${teacherId}`);
        const result = await response.json();
        
        if (result.success) {
            return result.data;
        }
        return [];
    } catch (error) {
        console.error('Error loading teacher subjects:', error);
        return [];
    }
}

function updateStats(teachers) {
    const total = teachers.length;
    const active = teachers.filter(t => t.status === 'Active').length;
    const inactive = teachers.filter(t => t.status === 'Inactive').length;
    const onLeave = teachers.filter(t => t.status === 'On Leave').length;
    
    if (totalTeachers) totalTeachers.textContent = total;
    if (activeTeachers) activeTeachers.textContent = active;
    if (inactiveTeachers) inactiveTeachers.textContent = inactive;
    if (onLeaveTeachers) onLeaveTeachers.textContent = onLeave;
}

// Teacher Table Functions
function renderTeacherRow(teacher) {
    const teacherName = `${teacher.firstname} ${teacher.middlename ? teacher.middlename + ' ' : ''}${teacher.lastname}`.trim();
    const isSuperAdmin = isTeacherProtected(teacher);
    const canEdit = canEditTeacher(teacher);
    const canDelete = canDeleteTeacher(teacher);
    const canAssign = canAssignSubjectsToTeacher(teacher);
    const isCurrentUser = teacher.id == PERMISSIONS.currentUserId;
    
    let roleDisplay = teacher.role || 'Not assigned';
    const escapedTeacherName = escapeHtml(teacherName).replace(/'/g, "&#39;");
    
    let actionsColumn = '';
    if (isSuperAdmin) {
        actionsColumn = `
            <div class="protected-badge">
                <i class="fas fa-shield-alt"></i>
                Super Admin
            </div>
        `;
    } else {
        let buttons = '<div class="actions">';
        
        if (canAssign && PERMISSIONS.canAssignSubjects) {
            buttons += `<button class="action-btn-small assign-btn" onclick="openAssignModal(${teacher.id}, '${escapedTeacherName}')" title="Assign Subjects">
                <i class="fas fa-book"></i>
            </button>`;
        }
        
        if (canEdit) {
            buttons += `<button class="action-btn-small edit-btn" onclick="editTeacher(${teacher.id})" title="Edit Teacher">
                <i class="fas fa-edit"></i>
            </button>`;
        }
        
        if (canDelete) {
            buttons += `<button class="action-btn-small delete-btn" onclick="showDeleteModal(${teacher.id}, '${escapedTeacherName}')" title="Delete Teacher">
                <i class="fas fa-trash"></i>
            </button>`;
        }
        
        if (!canAssign && !canEdit && !canDelete) {
            buttons += `<span class="protected-badge" style="background: var(--text-light);">
                <i class="fas fa-lock"></i> No Access
            </span>`;
        }
        
        buttons += '</div>';
        actionsColumn = buttons;
    }
    
    const rowClass = isSuperAdmin ? 'protected-row' : '';
    
    return `
        <tr data-teacher-id="${teacher.id}" class="${rowClass}">
            <td>
                <div class="teacher-info">
                    <div class="teacher-avatar" style="${isSuperAdmin ? 'background: linear-gradient(135deg, #ef4444, #b91c1c);' : ''}">
                        ${teacher.firstname ? teacher.firstname.charAt(0).toUpperCase() : '?'}
                    </div>
                    <div class="teacher-details">
                        <a href="teacher_profile.php?teacher_id=${teacher.id}" class="teacher-name">
                            ${escapeHtml(teacherName)}
                            ${isSuperAdmin ? ' <i class="fas fa-shield-alt" style="color: #ef4444; font-size: 0.8rem; margin-left: 0.25rem;" title="Super Admin - Protected Account"></i>' : ''}
                            ${isCurrentUser ? ' <span style="color: var(--success-green); font-size: 0.7rem; font-weight: normal;">(You)</span>' : ''}
                        </a>
                        <div class="teacher-id">${escapeHtml(teacher.teacher_number || 'N/A')}</div>
                    </div>
                </div>
            </td>
            <td>
                <div class="teacher-contact">
                    <div class="contact-item">
                        <i class="fas fa-envelope"></i>
                        <span>${escapeHtml(teacher.email || 'N/A')}</span>
                    </div>
                    <div class="contact-item">
                        <i class="fas fa-phone"></i>
                        <span>${escapeHtml(teacher.phonenumber || 'N/A')}</span>
                    </div>
                </div>
            </td>
            <td>
                <div class="subjects-container" id="subjects-${teacher.id}">
                    Loading subjects...
                </div>
            </td>
            <td>
                <span class="subject-badge ${isSuperAdmin ? 'super-admin-badge' : ''}">
                    ${escapeHtml(roleDisplay)}
                </span>
            </td>
            <td>
                <span class="status-badge status-${teacher.status ? teacher.status.toLowerCase().replace(' ', '-') : 'active'}">
                    <i class="fas fa-circle" style="font-size: 8px;"></i>
                    ${escapeHtml(teacher.status || 'Active')}
                </span>
            </td>
            <td>
                ${actionsColumn}
            </td>
        </tr>
    `;
}

function renderTeachersTable(teachers) {
    if (teachers.length === 0) {
        teachersTableBody.innerHTML = `
            <tr>
                <td colspan="6">
                    <div class="empty-state">
                        <i class="fas fa-chalkboard-teacher"></i>
                        <h3>No Teachers Found</h3>
                        <p>Get started by adding your first teacher</p>
                        ${PERMISSIONS.canCreate ? `
                            <button class="btn btn-primary" onclick="openAddModal()">
                                <i class="fas fa-user-plus"></i> Add Teacher
                            </button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `;
        return;
    }
    
    const sortedTeachers = sortTeachersWithProtectedFirst(teachers);
    teachersTableBody.innerHTML = sortedTeachers.map(teacher => renderTeacherRow(teacher)).join('');
    
    sortedTeachers.forEach(teacher => {
        loadTeacherSubjects(teacher.id);
    });
}

async function loadTeacherSubjects(teacherId) {
    const subjects = await fetchTeacherSubjects(teacherId);
    const container = document.getElementById(`subjects-${teacherId}`);
    
    if (container) {
        if (subjects.length > 0) {
            container.innerHTML = subjects.map(subject => 
                `<div class="subject-badge">${escapeHtml(subject.subject_name)}</div>`
            ).join('');
        } else {
            container.innerHTML = '<span style="color: var(--text-light); font-style: italic;">No subjects</span>';
        }
    }
}

// Modal Functions
function openAddModal() {
    if (!PERMISSIONS.canCreate) {
        showToast('Access Denied', 'You do not have permission to add teachers', 'error');
        return;
    }
    
    teacherForm.reset();
    teacherIdInput.value = '';
    teacherNumberInput.value = 'Auto-generated';
    modalTitle.textContent = 'Add New Teacher';
    saveBtnText.textContent = 'Save Teacher';
    
    teacherModal.classList.add('active');
    document.body.style.overflow = 'hidden';
    generateTeacherNumber();
}

function closeModalFunc() {
    teacherModal.classList.remove('active');
    document.body.style.overflow = '';
}

async function editTeacher(teacherId) {
    const teacher = teachersData.find(t => t.id == teacherId);
    if (!teacher) return;
    
    if (isTeacherProtected(teacher)) {
        showToast('Protected Account', 'Super Admin accounts cannot be edited.', 'warning');
        return;
    }
    
    if (!canEditTeacher(teacher)) {
        showToast('Access Denied', 'You do not have permission to edit this teacher', 'error');
        return;
    }
    
    teacherForm.reset();
    teacherIdInput.value = teacher.id;
    
    setTimeout(() => {
        $('#title').val(teacher.title || '').trigger('change');
        document.getElementById('firstname').value = teacher.firstname || '';
        document.getElementById('middlename').value = teacher.middle_name || teacher.middlename || '';
        document.getElementById('lastname').value = teacher.lastname || '';
        document.getElementById('email').value = teacher.email || '';
        document.getElementById('phonenumber').value = teacher.phonenumber || '';
        $('#gender').val(teacher.gender || '').trigger('change');
        $('#status').val(teacher.status || '').trigger('change');
        $('#role').val(teacher.role || '').trigger('change');
        teacherNumberInput.value = teacher.teacher_number || 'N/A';
    }, 100);
    
    modalTitle.textContent = 'Edit Teacher';
    saveBtnText.textContent = 'Update Teacher';
    teacherModal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function generateTeacherNumber() {
    const year = new Date().getFullYear();
    const random = Math.floor(Math.random() * 1000).toString().padStart(3, '0');
    teacherNumberInput.value = `TCH-${year}-${random}`;
}

// Form Submission
if (teacherForm) {
    teacherForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const teacherId = teacherIdInput.value;
        const isEdit = teacherId !== '';
        
        if (isEdit) {
            const teacher = teachersData.find(t => t.id == teacherId);
            if (!teacher) return;
            
            if (isTeacherProtected(teacher)) {
                showToast('Protected Account', 'Super Admin accounts cannot be edited.', 'warning');
                closeModalFunc();
                return;
            }
            
            if (!canEditTeacher(teacher)) {
                showToast('Access Denied', 'You do not have permission to edit this teacher', 'error');
                closeModalFunc();
                return;
            }
        } else {
            if (!PERMISSIONS.canCreate) {
                showToast('Access Denied', 'You do not have permission to add teachers', 'error');
                return;
            }
        }
        
        const requiredFields = ['firstname', 'lastname', 'email', 'phonenumber'];
        for (const field of requiredFields) {
            const element = document.getElementById(field);
            if (element && !element.value) {
                showToast('Validation Error', `${field.charAt(0).toUpperCase() + field.slice(1)} is required`, 'error');
                return;
            }
        }
        
        const action = isEdit ? 'update_teacher' : 'add_teacher';
        
        const formData = new FormData();
        formData.append('action', action);
        formData.append('teacher_id', teacherId);
        formData.append('title', $('#title').val());
        formData.append('firstname', document.getElementById('firstname').value);
        formData.append('middlename', document.getElementById('middlename').value);
        formData.append('lastname', document.getElementById('lastname').value);
        formData.append('email', document.getElementById('email').value);
        formData.append('phonenumber', document.getElementById('phonenumber').value);
        formData.append('gender', $('#gender').val());
        formData.append('role', $('#role').val());
        formData.append('status', $('#status').val());
        
        if (!isEdit) {
            formData.append('teacher_number', teacherNumberInput.value);
        }
        
        formData.append('secondname', document.getElementById('middlename').value || '');
        
        const originalBtnText = saveBtn.innerHTML;
        saveBtn.innerHTML = '<div class="spinner"></div> ' + (isEdit ? 'Updating...' : 'Saving...');
        saveBtn.disabled = true;
        
        try {
            const response = await fetch('ajax/teacher.php', {
                method: 'POST',
                body: formData
            });
            
            const responseText = await response.text();
            let result;
            try {
                result = JSON.parse(responseText);
            } catch (jsonError) {
                throw new Error(`Server returned invalid JSON: ${responseText.substring(0, 200)}`);
            }
            
            if (result.success) {
                showToast('Success', result.message || (isEdit ? 'Teacher updated successfully!' : 'Teacher added successfully!'), 'success');
                closeModalFunc();
                await fetchTeachers();
            } else {
                showToast('Error', result.message || 'Operation failed', 'error');
            }
        } catch (error) {
            showToast('Error', error.message, 'error');
        } finally {
            saveBtn.innerHTML = originalBtnText;
            saveBtn.disabled = false;
        }
    });
}

// Import Modal Functions
function openImportModal() {
    if (!PERMISSIONS.canImport) {
        showToast('Access Denied', 'You do not have permission to import teachers', 'error');
        return;
    }
    
    importModal.classList.add('active');
    document.body.style.overflow = 'hidden';
    importForm.reset();
    if (fileInfo) fileInfo.innerHTML = '';
    if (fileUploadArea) fileUploadArea.classList.remove('active');
}

function closeImportModalFunc() {
    importModal.classList.remove('active');
    document.body.style.overflow = '';
}

if (fileUploadArea) {
    fileUploadArea.addEventListener('click', () => {
        if (excelFileInput) excelFileInput.click();
    });
}

if (excelFileInput) {
    excelFileInput.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (file && fileUploadArea && fileInfo) {
            fileUploadArea.classList.add('active');
            fileInfo.innerHTML = `
                <div style="margin-top: 1rem; padding: 0.75rem; background: var(--light-blue); border-radius: var(--border-radius);">
                    <strong>Selected file:</strong> ${escapeHtml(file.name)}<br>
                    <strong>Size:</strong> ${(file.size / 1024 / 1024).toFixed(2)} MB
                </div>
            `;
        }
    });
}

if (fileUploadArea) {
    fileUploadArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        fileUploadArea.style.borderColor = 'var(--primary-blue)';
        fileUploadArea.style.background = 'var(--light-blue)';
    });
    
    fileUploadArea.addEventListener('dragleave', () => {
        fileUploadArea.style.borderColor = '';
        fileUploadArea.style.background = '';
    });
    
    fileUploadArea.addEventListener('drop', (e) => {
        e.preventDefault();
        fileUploadArea.style.borderColor = '';
        fileUploadArea.style.background = '';
        
        const file = e.dataTransfer.files[0];
        if (file && (file.name.endsWith('.xlsx') || file.name.endsWith('.xls') || file.name.endsWith('.csv'))) {
            if (excelFileInput) excelFileInput.files = e.dataTransfer.files;
            if (fileUploadArea) fileUploadArea.classList.add('active');
            if (fileInfo) {
                fileInfo.innerHTML = `
                    <div style="margin-top: 1rem; padding: 0.75rem; background: var(--light-blue); border-radius: var(--border-radius);">
                        <strong>Selected file:</strong> ${escapeHtml(file.name)}<br>
                        <strong>Size:</strong> ${(file.size / 1024 / 1024).toFixed(2)} MB
                    </div>
                `;
            }
        } else {
            showToast('Error', 'Please upload a valid Excel or CSV file', 'error');
        }
    });
}

if (importForm) {
    importForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        if (!PERMISSIONS.canImport) {
            showToast('Access Denied', 'You do not have permission to import teachers', 'error');
            return;
        }
        
        const file = excelFileInput ? excelFileInput.files[0] : null;
        if (!file) {
            showToast('Error', 'Please select an Excel file to import', 'error');
            return;
        }
        
        if (file.size > 10 * 1024 * 1024) {
            showToast('Error', 'File size must be less than 10MB', 'error');
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'import_teachers');
        formData.append('excel_file', file);
        
        const originalBtnText = importSubmitBtn.innerHTML;
        importSubmitBtn.innerHTML = '<div class="spinner"></div> Importing...';
        importSubmitBtn.disabled = true;
        
        try {
            const response = await fetch('ajax/teacher.php', {
                method: 'POST',
                body: formData
            });
            
            const responseText = await response.text();
            let result;
            try {
                result = JSON.parse(responseText);
            } catch (jsonError) {
                throw new Error(`Server returned invalid JSON: ${responseText.substring(0, 200)}`);
            }
            
            if (result.success) {
                let message = result.message || 'Teachers imported successfully!';
                
                if (result.errors && result.errors.length > 0) {
                    message += `\n\nErrors: ${result.errors.slice(0, 3).join('\n')}`;
                    showToast('Partial Success', message, 'warning');
                } else {
                    showToast('Success', message, 'success');
                }
                
                closeImportModalFunc();
                await fetchTeachers();
            } else {
                showToast('Import Failed', result.message || 'Import failed', 'error');
            }
        } catch (error) {
            showToast('Error', error.message, 'error');
        } finally {
            importSubmitBtn.innerHTML = originalBtnText;
            importSubmitBtn.disabled = false;
        }
    });
}

if (downloadTemplateBtn) {
    downloadTemplateBtn.addEventListener('click', () => {
        const templateData = [
            ['Title', 'First Name', 'Middle Name', 'Last Name', 'Email', 'Phone Number', 'Gender', 'Role'],
            ['Mr.', 'John', '', 'Doe', 'john.doe@example.com', '+254712345678', 'Male', 'Teacher'],
            ['Mrs.', 'Jane', 'Marie', 'Smith', 'jane.smith@example.com', '+254798765432', 'Female', 'Teacher']
        ];
        
        let csvContent = "data:text/csv;charset=utf-8,";
        templateData.forEach(row => {
            csvContent += row.map(cell => `"${cell}"`).join(",") + "\r\n";
        });
        
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "teacher_import_template.csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    });
}

// Assign Subjects Modal Functions
async function openAssignModal(teacherId, teacherName) {
    const teacher = teachersData.find(t => t.id == teacherId);
    
    if (!PERMISSIONS.canAssignSubjects) {
        showToast('Access Denied', 'You do not have permission to assign subjects', 'error');
        return;
    }
    
    if (teacher && isTeacherProtected(teacher)) {
        showToast('Protected Account', 'Super Admin accounts cannot have subjects assigned.', 'warning');
        return;
    }
    
    if (!canAssignSubjectsToTeacher(teacher)) {
        showToast('Access Denied', 'You do not have permission to assign subjects to this teacher', 'error');
        return;
    }
    
    assignTeacherIdInput.value = teacherId;
    teacherNameDisplay.value = teacherName;
    assignModalTitle.textContent = `Assign Subjects to ${teacherName}`;
    
    await fetchSubjects();
    const teacherSubjects = await fetchTeacherSubjects(teacherId);
    
    if (subjectsContainer) {
        subjectsContainer.innerHTML = '';
        
        if (subjectsData.length === 0) {
            subjectsContainer.innerHTML = '<div class="empty-state">No subjects available</div>';
        } else {
            subjectsData.forEach(subject => {
                const isChecked = teacherSubjects.some(ts => ts.id == subject.id);
                const checkbox = document.createElement('div');
                checkbox.className = 'subject-checkbox';
                checkbox.innerHTML = `
                    <input type="checkbox" id="subject-${subject.id}" name="subjects[]" value="${subject.id}" ${isChecked ? 'checked' : ''}>
                    <label for="subject-${subject.id}">${escapeHtml(subject.subject_name)}</label>
                `;
                subjectsContainer.appendChild(checkbox);
            });
        }
    }
    
    assignModal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeAssignModalFunc() {
    assignModal.classList.remove('active');
    document.body.style.overflow = '';
}

if (assignForm) {
    assignForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const teacherId = assignTeacherIdInput.value;
        const teacher = teachersData.find(t => t.id == teacherId);
        
        if (!PERMISSIONS.canAssignSubjects) {
            showToast('Access Denied', 'You do not have permission to assign subjects', 'error');
            closeAssignModalFunc();
            return;
        }
        
        if (teacher && isTeacherProtected(teacher)) {
            showToast('Protected Account', 'Super Admin accounts cannot have subjects assigned.', 'warning');
            closeAssignModalFunc();
            return;
        }
        
        if (!canAssignSubjectsToTeacher(teacher)) {
            showToast('Access Denied', 'You do not have permission to assign subjects to this teacher', 'error');
            closeAssignModalFunc();
            return;
        }
        
        const checkboxes = subjectsContainer.querySelectorAll('input[type="checkbox"]:checked');
        const subjects = Array.from(checkboxes).map(cb => cb.value);
        
        const formData = new FormData();
        formData.append('action', 'assign_subjects');
        formData.append('teacher_id', teacherId);
        formData.append('subjects', JSON.stringify(subjects));
        
        const originalBtnText = saveAssignBtn.innerHTML;
        saveAssignBtn.innerHTML = '<div class="spinner"></div> Saving...';
        saveAssignBtn.disabled = true;
        
        try {
            const response = await fetch('ajax/teacher.php', {
                method: 'POST',
                body: formData
            });
            
            const responseText = await response.text();
            let result;
            try {
                result = JSON.parse(responseText);
            } catch (jsonError) {
                throw new Error(`Server returned invalid JSON: ${responseText.substring(0, 200)}`);
            }
            
            if (result.success) {
                showToast('Success', result.message || 'Subjects assigned successfully!', 'success');
                closeAssignModalFunc();
                await loadTeacherSubjects(teacherId);
            } else {
                showToast('Assignment Failed', result.message || 'Failed to assign subjects', 'error');
            }
        } catch (error) {
            showToast('Error', error.message, 'error');
        } finally {
            saveAssignBtn.innerHTML = originalBtnText;
            saveAssignBtn.disabled = false;
        }
    });
}

// Delete Modal Functions
function showDeleteModal(teacherId, teacherName) {
    const teacher = teachersData.find(t => t.id == teacherId);
    
    if (!PERMISSIONS.canDelete) {
        showToast('Access Denied', 'You do not have permission to delete teachers', 'error');
        return;
    }
    
    if (teacher && isTeacherProtected(teacher)) {
        showToast('Protected Account', 'Super Admin accounts cannot be deleted.', 'warning');
        return;
    }
    
    if (!canDeleteTeacher(teacher)) {
        showToast('Access Denied', 'You do not have permission to delete this teacher', 'error');
        return;
    }
    
    deleteTeacherId = teacherId;
    deleteTeacherName.textContent = teacherName;
    deleteModal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeDeleteModalFunc() {
    deleteModal.classList.remove('active');
    document.body.style.overflow = '';
    deleteTeacherId = null;
}

async function confirmDelete() {
    if (!deleteTeacherId) return;
    
    const teacher = teachersData.find(t => t.id == deleteTeacherId);
    
    if (!PERMISSIONS.canDelete) {
        showToast('Access Denied', 'You do not have permission to delete teachers', 'error');
        closeDeleteModalFunc();
        return;
    }
    
    if (teacher && isTeacherProtected(teacher)) {
        showToast('Protected Account', 'Super Admin accounts cannot be deleted.', 'warning');
        closeDeleteModalFunc();
        return;
    }
    
    if (!canDeleteTeacher(teacher)) {
        showToast('Access Denied', 'You do not have permission to delete this teacher', 'error');
        closeDeleteModalFunc();
        return;
    }
    
    const originalBtnText = confirmDeleteBtn.innerHTML;
    confirmDeleteBtn.innerHTML = '<div class="spinner"></div> Deleting...';
    confirmDeleteBtn.disabled = true;
    
    const formData = new FormData();
    formData.append('action', 'delete_teacher');
    formData.append('teacher_id', deleteTeacherId);
    
    try {
        const response = await fetch('ajax/teacher.php', {
            method: 'POST',
            body: formData
        });
        
        const responseText = await response.text();
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (jsonError) {
            throw new Error(`Server returned invalid JSON: ${responseText.substring(0, 200)}`);
        }
        
        if (result.success) {
            showToast('Success', result.message || 'Teacher permanently deleted successfully!', 'success');
            closeDeleteModalFunc();
            await fetchTeachers();
        } else {
            showToast('Delete Failed', result.message || 'Failed to delete teacher', 'error');
        }
    } catch (error) {
        showToast('Error', error.message, 'error');
    } finally {
        confirmDeleteBtn.innerHTML = originalBtnText;
        confirmDeleteBtn.disabled = false;
    }
}

// Search Functionality
function setupSearch() {
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = teachersTableBody.querySelectorAll('tr[data-teacher-id]');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
    }
}

// Export to PDF
const exportBtn = document.getElementById('exportBtn');
if (exportBtn) {
    exportBtn.addEventListener('click', async () => {
        if (!PERMISSIONS.canExport) {
            showToast('Access Denied', 'You do not have permission to export teachers', 'error');
            return;
        }
        
        const originalText = exportBtn.innerHTML;
        exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating PDF...';
        exportBtn.disabled = true;
        
        try {
            window.open('exports/teachers.php', '_blank');
            showToast('Success', 'PDF export started!', 'success');
        } catch (error) {
            console.error('Export error:', error);
            showToast('Error', error.message, 'error');
        } finally {
            exportBtn.innerHTML = originalText;
            exportBtn.disabled = false;
        }
    });
}

// Initialize
document.addEventListener('DOMContentLoaded', async () => {
    await Promise.all([
        fetchTeachers(),
        fetchSubjects()
    ]);
    
    if (addTeacherBtn) addTeacherBtn.addEventListener('click', openAddModal);
    if (closeModal) closeModal.addEventListener('click', closeModalFunc);
    if (cancelBtn) cancelBtn.addEventListener('click', closeModalFunc);
    if (teacherModal) {
        teacherModal.addEventListener('click', (e) => {
            if (e.target === teacherModal) closeModalFunc();
        });
    }
    
    const importBtn = document.getElementById('importBtn');
    if (importBtn) importBtn.addEventListener('click', openImportModal);
    if (closeImportModal) closeImportModal.addEventListener('click', closeImportModalFunc);
    if (cancelImportBtn) cancelImportBtn.addEventListener('click', closeImportModalFunc);
    if (importModal) {
        importModal.addEventListener('click', (e) => {
            if (e.target === importModal) closeImportModalFunc();
        });
    }
    
    if (closeAssignModal) closeAssignModal.addEventListener('click', closeAssignModalFunc);
    if (cancelAssignBtn) cancelAssignBtn.addEventListener('click', closeAssignModalFunc);
    if (assignModal) {
        assignModal.addEventListener('click', (e) => {
            if (e.target === assignModal) closeAssignModalFunc();
        });
    }
    
    if (closeDeleteModal) closeDeleteModal.addEventListener('click', closeDeleteModalFunc);
    if (cancelDeleteBtn) cancelDeleteBtn.addEventListener('click', closeDeleteModalFunc);
    if (confirmDeleteBtn) confirmDeleteBtn.addEventListener('click', confirmDelete);
    if (deleteModal) {
        deleteModal.addEventListener('click', (e) => {
            if (e.target === deleteModal) closeDeleteModalFunc();
        });
    }
    
    setupSearch();
    
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeModalFunc();
            closeImportModalFunc();
            closeAssignModalFunc();
            closeDeleteModalFunc();
        }
    });
});

// Make functions global for onclick handlers
window.openAddModal = openAddModal;
window.editTeacher = editTeacher;
window.openAssignModal = openAssignModal;
window.showDeleteModal = showDeleteModal;
window.removeToast = removeToast;
</script>
</body>
</html>