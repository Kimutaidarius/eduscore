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

// Check if user has permission to view roles page
$permissionHelper->requireAnyPermission(['rolesView', 'rolesViewAll'], 'dashboard.php');

// Determine which actions are allowed based on permissions
$canCreate = $permissionHelper->hasPermission('rolesCreate');
$canEdit = $permissionHelper->hasPermission('rolesEdit');
$canDelete = $permissionHelper->hasPermission('rolesDelete');
$canViewAll = $permissionHelper->hasPermission('rolesViewAll');
$isSuperAdmin = $permissionHelper->isSuperAdmin();
$currentUserRole = $permissionHelper->getRole();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EduScore - Role Management</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="icon" type="image/png" href="images/logo.png" />
<link rel="apple-touch-icon" href="images/logo.png">
<link rel="stylesheet" href="assets/banner/banner.css">
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
    overflow-x: hidden;
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

.roles-page-title {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--text-dark);
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.roles-page-title i {
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

/* Toggle Container - Centered */
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

.toggle-btn i {
    font-size: 1rem;
}

.toggle-btn.active {
    background: var(--primary-blue);
    color: white;
    box-shadow: var(--shadow);
}

.toggle-btn:hover:not(.active):not(:disabled) {
    background: var(--bg-light);
    color: var(--primary-blue);
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

/* Enhanced Filter Bar */
.filter-bar {
    display: flex;
    gap: 1.5rem;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    background: linear-gradient(135deg, var(--bg-white), #f8faff);
    padding: 2rem;
    border-radius: 24px;
    box-shadow: 0 8px 20px -6px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.8);
    backdrop-filter: blur(10px);
    position: relative;
    overflow: hidden;
}

.filter-bar::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--primary-blue), var(--success-green), var(--accent-yellow));
    opacity: 0.5;
}

/* Filter Group */
.roles-filter-group {
    flex: 1;
    min-width: 250px;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

/* Filter Label Styling */
.filter-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-dark);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-left: 0.5rem;
    transition: var(--transition);
}

.filter-label i {
    color: var(--primary-blue);
    font-size: 1rem;
    transition: var(--transition);
}

.roles-filter-group:hover .filter-label {
    color: var(--primary-blue);
}

.roles-filter-group:hover .filter-label i {
    transform: scale(1.1);
}

/* Modern Select Buttons Styling */
.roles-filter-select {
    position: relative;
    width: 100%;
}

.roles-filter-select select {
    width: 100%;
    padding: 1rem 2.5rem 1rem 1.25rem;
    border: 2px solid var(--border-color);
    border-radius: 16px;
    font-size: 1rem;
    font-weight: 500;
    background: var(--bg-white);
    cursor: pointer;
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    transition: all 0.3s ease;
    color: var(--text-dark);
    letter-spacing: 0.3px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
    line-height: 1.5;
}

.roles-filter-select select:hover:not(:disabled) {
    border-color: var(--primary-blue);
    background: linear-gradient(to bottom, var(--bg-white), #f8faff);
    box-shadow: 0 8px 20px -8px rgba(30, 58, 138, 0.15);
    transform: translateY(-2px);
}

.roles-filter-select select:focus {
    outline: none;
    border-color: var(--primary-blue);
    box-shadow: 0 0 0 4px rgba(30, 58, 138, 0.15);
    background: var(--bg-white);
}

.roles-filter-select select:active {
    transform: translateY(0);
    box-shadow: 0 4px 12px rgba(30, 58, 138, 0.1);
}

.roles-filter-select select:disabled {
    background: var(--bg-light);
    cursor: not-allowed;
    opacity: 0.6;
}

.roles-filter-select select:disabled:hover {
    border-color: var(--border-color);
    transform: none;
    box-shadow: none;
}

.roles-filter-select::after {
    content: '\f078';
    font-family: 'Font Awesome 6 Free';
    font-weight: 900;
    position: absolute;
    right: 1.25rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--primary-blue);
    pointer-events: none;
    font-size: 0.9rem;
    transition: all 0.3s ease;
    opacity: 0.7;
    z-index: 1;
}

.roles-filter-select:hover::after {
    opacity: 1;
    transform: translateY(-50%) rotate(180deg);
    color: var(--dark-blue);
}

/* Save Button Container */
.save-button-container {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    min-width: 200px;
}

.save-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-dark);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-left: 0.5rem;
    opacity: 0;
    animation: fadeInLabel 0.3s ease forwards 0.2s;
}

@keyframes fadeInLabel {
    to {
        opacity: 1;
    }
}

.save-label i {
    color: var(--success-green);
    font-size: 1rem;
    transition: var(--transition);
}

.save-button-container:hover .save-label i {
    transform: scale(1.1);
}

#saveUserRoleBtn {
    background: linear-gradient(135deg, #10b981, #059669, #047857);
    color: white;
    border: none;
    font-weight: 600;
    transition: all 0.3s ease;
    box-shadow: 0 8px 20px -8px rgba(16, 185, 129, 0.4);
    white-space: nowrap;
    position: relative;
    overflow: hidden;
    border-radius: 16px;
    padding: 1rem 2rem;
    font-size: 1rem;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    border: 1px solid rgba(255, 255, 255, 0.1);
    width: 100%;
    animation: slideInButton 0.4s ease;
}

#saveUserRoleBtn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
    background: linear-gradient(135deg, #9ca3af, #6b7280);
}

#saveUserRoleBtn:disabled:hover {
    transform: none;
    box-shadow: none;
}

@keyframes slideInButton {
    from {
        opacity: 0;
        transform: translateX(20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

#saveUserRoleBtn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transition: left 0.5s ease;
    z-index: 1;
}

#saveUserRoleBtn:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 15px 30px -10px rgba(16, 185, 129, 0.5);
    background: linear-gradient(135deg, #059669, #047857, #065f46);
}

#saveUserRoleBtn:hover:not(:disabled)::before {
    left: 100%;
}

#saveUserRoleBtn:active:not(:disabled) {
    transform: translateY(0);
    box-shadow: 0 5px 15px -5px rgba(16, 185, 129, 0.4);
}

#saveUserRoleBtn i {
    font-size: 1.2rem;
    transition: all 0.3s ease;
    position: relative;
    z-index: 2;
}

#saveUserRoleBtn:hover:not(:disabled) i {
    transform: scale(1.1) rotate(-5deg);
}

#saveUserRoleBtn span {
    position: relative;
    z-index: 2;
}

#saveUserRoleBtn.saving {
    pointer-events: none;
    opacity: 0.9;
    background: linear-gradient(135deg, #9ca3af, #6b7280, #4b5563);
    cursor: wait;
}

#saveUserRoleBtn.saving i {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

#saveUserRoleBtn.success {
    background: linear-gradient(135deg, var(--success-green), #047857);
    pointer-events: none;
    cursor: default;
}

#saveUserRoleBtn.success i {
    animation: successPop 0.3s ease;
}

@keyframes successPop {
    0% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.2);
    }
    100% {
        transform: scale(1);
    }
}

/* Table Styles */
.table-container {
    background: var(--bg-white);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow);
    overflow: hidden;
    animation: slideUp 0.4s ease;
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
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.users-table th i {
    margin-right: 0.5rem;
}

.users-table td {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--border-color);
    font-size: 0.9rem;
}

.users-table tr:last-child td {
    border-bottom: none;
}

.users-table tr:hover {
    background: var(--bg-light);
}

/* Role Card Styles */
.cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 1.5rem;
    margin-top: 1rem;
}

.role-card {
    background: var(--bg-white);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow);
    overflow: visible !important;
    transition: var(--transition);
    border: 1px solid var(--border-color);
    position: relative;
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
    background: var(--bg-light);
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
    color: var(--text-dark);
}

.permission-count {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.35rem 1rem;
    background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
    color: white;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 600;
    letter-spacing: 0.3px;
    box-shadow: 0 2px 8px rgba(30, 58, 138, 0.15);
    transition: var(--transition);
    width: fit-content;
}

.role-card:hover .permission-count {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(30, 58, 138, 0.25);
}

.permission-count i {
    font-size: 0.75rem;
    opacity: 0.9;
}

.role-card-body {
    padding: 1.5rem;
    overflow: hidden;
}

.role-description {
    color: var(--text-light);
    font-size: 0.9rem;
    margin-bottom: 1rem;
    line-height: 1.5;
    padding: 0.5rem 0;
    border-bottom: 1px solid var(--border-color);
}

.teacher-count {
    color: var(--text-light);
    font-size: 0.85rem;
    margin-bottom: 1rem;
}

.teacher-count i {
    color: var(--primary-blue);
}

.permissions-container {
    position: relative;
    margin: 1rem 0;
    overflow: hidden;
}

.permissions-list {
    list-style: none;
    margin: 0;
    padding: 0;
    max-height: 200px;
    overflow: hidden;
    transition: max-height 0.5s cubic-bezier(0.4, 0, 0.2, 1);
}

.permissions-list.expanded {
    max-height: 1000px;
}

.permissions-list li {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.6rem 0;
    font-size: 0.9rem;
    color: var(--text-dark);
    border-bottom: 1px dashed var(--border-color);
    animation: slideInPermission 0.3s ease forwards;
    transform-origin: top;
}

@keyframes slideInPermission {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.permissions-list li:last-child {
    border-bottom: none;
}

.permissions-list li i {
    width: 20px;
    font-size: 0.85rem;
}

.permissions-list li i.fa-check-circle {
    color: var(--success-green);
}

.permissions-list li i.fa-lock {
    color: var(--text-light);
}

.permissions-container::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 40px;
    background: linear-gradient(to bottom, transparent, var(--bg-white));
    pointer-events: none;
    opacity: 1;
    transition: opacity 0.3s ease;
    border-radius: 0 0 12px 12px;
}

.permissions-container.expanded::after {
    opacity: 0;
}

.show-more-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    width: 100%;
    padding: 0.75rem;
    margin-top: 0.5rem;
    background: linear-gradient(to bottom, transparent, var(--bg-light));
    border: 1px solid var(--border-color);
    border-radius: 8px;
    color: var(--primary-blue);
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition);
    border: none;
    background: var(--bg-light);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
}

.show-more-btn:hover {
    background: var(--light-blue);
    color: var(--dark-blue);
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(30, 58, 138, 0.1);
}

.show-more-btn:active {
    transform: translateY(0);
}

.show-more-btn i {
    font-size: 0.75rem;
    transition: transform 0.3s ease;
}

.show-more-btn:hover i {
    transform: translateY(2px);
}

.show-more-btn.expanded i {
    transform: rotate(180deg);
}

.show-more-btn.expanded:hover i {
    transform: rotate(180deg) translateY(-2px);
}

/* Action Buttons (Replacing Ellipsis) */
.role-card-footer {
    padding: 1.5rem;
    border-top: 1px solid var(--border-color);
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    background: var(--bg-light);
    position: relative;
    z-index: 10;
    flex-wrap: wrap;
}

.role-action-btn {
    padding: 0.6rem 1rem;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.role-action-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}

.role-action-btn i {
    font-size: 0.9rem;
}

.role-action-btn.edit-btn {
    background: var(--light-blue);
    color: var(--primary-blue);
}

.role-action-btn.edit-btn:hover:not(:disabled) {
    background: var(--secondary-blue);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(37, 99, 235, 0.2);
}

.role-action-btn.permissions-btn {
    background: linear-gradient(135deg, #8b5cf6, #6d28d9);
    color: white;
}

.role-action-btn.permissions-btn:hover:not(:disabled) {
    background: linear-gradient(135deg, #7c3aed, #5b21b6);
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(109, 40, 217, 0.3);
}

.role-action-btn.delete-btn {
    background: #fee2e2;
    color: var(--error-red);
}

.role-action-btn.delete-btn:hover:not(:disabled) {
    background: var(--error-red);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(239, 68, 68, 0.3);
}

.role-action-btn.protected-btn {
    background: linear-gradient(135deg, #9ca3af, #6b7280);
    color: white;
    cursor: not-allowed;
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
    font-size: 0.9rem;
}

.user-details {
    flex: 1;
}

.roles-user-name {
    font-weight: 600;
    color: var(--text-dark);
    margin-bottom: 0.125rem;
}

.user-email {
    font-size: 0.8rem;
    color: var(--text-light);
}

.role-badge {
    display: inline-block;
    padding: 0.25rem 1rem;
    background: var(--light-blue);
    color: var(--primary-blue);
    border-radius: 50px;
    font-size: 0.85rem;
    font-weight: 500;
}

.protected-badge {
    display: flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.35rem 1rem;
    background: #fee2e2;
    color: var(--error-red);
    border-radius: 50px;
    font-size: 0.75rem;
    font-weight: 600;
    box-shadow: 0 2px 4px rgba(239, 68, 68, 0.1);
    width: fit-content;
}

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
    font-size: 0.9rem;
    background: var(--bg-light);
    color: var(--text-light);
}

.action-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}

.action-btn.edit-btn:hover:not(:disabled) {
    background: var(--primary-blue);
    color: white;
}

.action-btn.delete-btn:hover:not(:disabled) {
    background: var(--error-red);
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
    max-width: 800px;
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

.close-modal:hover:not(:disabled) {
    background: rgba(255, 255, 255, 0.2);
}

.close-modal:disabled {
    opacity: 0.5;
    cursor: not-allowed;
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

.modal-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}

.modal-btn-primary {
    background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
    color: white;
    box-shadow: 0 4px 6px -1px rgba(30, 58, 138, 0.2);
}

.modal-btn-primary:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 6px 10px -1px rgba(30, 58, 138, 0.3);
}

.modal-btn-secondary {
    background: var(--bg-light);
    color: var(--text-dark);
    border: 1px solid var(--border-color);
}

.modal-btn-secondary:hover:not(:disabled) {
    background: var(--border-color);
    border-color: var(--text-light);
}

/* Permissions Grid */
.permissions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 1rem;
    margin-top: 1.5rem;
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

.permission-action input[type="checkbox"]:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.permission-action label {
    cursor: pointer;
    color: var(--text-dark);
}

.permission-action label.disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Delete Confirmation Modal */
.delete-modal {
    max-width: 400px !important;
}

.delete-modal .modal-header {
    background: var(--error-red);
}

.delete-modal .modal-body {
    text-align: center;
    padding: 2rem;
}

.delete-modal .warning-icon {
    font-size: 4rem;
    color: var(--error-red);
    margin-bottom: 1rem;
}

.delete-modal h3 {
    font-size: 1.25rem;
    margin-bottom: 0.5rem;
    color: var(--text-dark);
}

.delete-modal p {
    color: var(--text-light);
    margin-bottom: 1.5rem;
}

.delete-modal .role-name {
    font-weight: 600;
    color: var(--error-red);
}

.delete-modal .modal-footer {
    justify-content: center;
    gap: 1rem;
}

.delete-btn {
    background: var(--error-red) !important;
    color: white !important;
}

.delete-btn:hover:not(:disabled) {
    background: #dc2626 !important;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
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

/* Toast Notifications */
.toast-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
}

.toast {
    background: white;
    border-radius: 12px;
    padding: 1rem 1.5rem;
    margin-bottom: 1rem;
    box-shadow: var(--shadow-lg);
    display: flex;
    align-items: center;
    gap: 1rem;
    min-width: 300px;
    max-width: 400px;
    animation: slideInRight 0.3s ease;
    border-left: 4px solid;
}

.toast.success {
    border-left-color: var(--success-green);
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

.toast-icon i {
    font-size: 1.5rem;
}

.toast.success .toast-icon i {
    color: var(--success-green);
}

.toast.error .toast-icon i {
    color: var(--error-red);
}

.toast.warning .toast-icon i {
    color: var(--warning-orange);
}

.toast.info .toast-icon i {
    color: var(--primary-blue);
}

.toast-content {
    flex: 1;
}

.toast-title {
    font-weight: 600;
    color: var(--text-dark);
    margin-bottom: 0.25rem;
}

.toast-message {
    font-size: 0.9rem;
    color: var(--text-light);
}

@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
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

/* Loading State */
.loading-state {
    text-align: center;
    padding: 4rem 2rem;
    color: var(--text-light);
    background: var(--bg-white);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow);
}

.loading-state i {
    font-size: 3rem;
    color: var(--primary-blue);
    margin-bottom: 1rem;
}

.loading-state h3 {
    font-size: 1.25rem;
    margin-bottom: 0.5rem;
    color: var(--text-dark);
}

.loading-state p {
    margin-bottom: 1.5rem;
    color: var(--text-light);
}

/* Responsive Design */
@media (max-width: 1200px) {
    .filter-bar {
        padding: 1.5rem;
        gap: 1.25rem;
    }
    
    .filter-group {
        min-width: 200px;
    }
    
    .filter-select select,
    #saveUserRoleBtn {
        padding: 0.875rem 2rem 0.875rem 1rem;
        font-size: 0.95rem;
    }
}

@media (max-width: 992px) {
    .filter-bar {
        flex-wrap: wrap;
    }
    
    .filter-group {
        flex: 1 1 calc(50% - 0.75rem);
        min-width: 0;
    }
    
    .save-button-container {
        flex: 1 1 100%;
    }
    
    #saveUserRoleBtn {
        width: 100%;
    }
    
    .cards-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .filter-bar {
        padding: 1.25rem;
        border-radius: 20px;
        gap: 1rem;
    }
    
    .filter-group {
        flex: 1 1 100%;
    }
    
    .filter-select select,
    #saveUserRoleBtn {
        padding: 0.875rem 2.5rem 0.875rem 1rem;
        font-size: 0.95rem;
    }
    
    .filter-select::after {
        right: 1rem;
    }
    
    .filter-label,
    .save-label {
        font-size: 0.85rem;
        margin-left: 0.25rem;
    }
    
    .role-card-footer {
        flex-direction: column;
        align-items: stretch;
    }
    
    .role-action-btn {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .filter-bar {
        padding: 1rem;
        border-radius: 16px;
        gap: 0.875rem;
    }
    
    .filter-select select,
    #saveUserRoleBtn {
        padding: 0.75rem 2rem 0.75rem 0.875rem;
        font-size: 0.9rem;
        border-radius: 12px;
    }
    
    .filter-label,
    .save-label {
        font-size: 0.8rem;
        margin-left: 0.25rem;
    }
    
    .filter-label i,
    .save-label i {
        font-size: 0.85rem;
    }
    
    #saveUserRoleBtn i {
        font-size: 1rem;
    }
    
    #saveUserRoleBtn {
        padding: 0.75rem 1rem;
    }
    
    .toast-container {
        right: 1rem;
        left: 1rem;
        max-width: none;
    }
}
</style>
</head>
<body>

<?php include 'includes/sidebar.php'; ?>
<div class="main-content">
<?php include 'includes/header.php'; ?>
<?php include 'trial_banner.php'; ?>

<!-- ========================================== -->
<!-- MAIN PAGE CONTENT -->
<!-- ========================================== -->

<div class="page-header">
    <div class="header-left">
        <h1 class="roles-page-title">
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
    <!-- Permission Denied -->
    <div class="permission-denied">
        <i class="fas fa-lock"></i>
        <h3>Access Denied</h3>
        <p>You do not have permission to view roles.</p>
        <p style="font-size: 0.9rem; margin-top: 0.5rem;">Please contact your system administrator if you need access.</p>
    </div>
<?php else: ?>

<div class="toggle-container">
    <button class="toggle-btn active" id="toggleUsers" <?php echo !$permissionHelper->hasAnyPermission(['usersView', 'usersViewAll']) ? 'disabled' : ''; ?>>
        <i class="fas fa-users"></i>
        Users
    </button>
    <button class="toggle-btn" id="toggleRoles">
        <i class="fas fa-tags"></i>
        Roles
    </button>
</div>

<div class="content-section active" id="usersSection">
    <div class="filter-bar">
        <div class="roles-filter-group">
            <label for="teacherFilter" class="filter-label">
                <i class="fas fa-chalkboard-teacher"></i>
                Select Teacher
            </label>
            <div class="roles-filter-select">
                <select id="teacherFilter">
                    <option value="">All Teachers</option>
                </select>
            </div>
        </div>

        <div class="roles-filter-group">
            <label for="roleFilter" class="filter-label">
                <i class="fas fa-user-tag"></i>
                Select Role
            </label>
            <div class="roles-filter-select">
                <select id="roleFilter">
                    <option value="">All Roles</option>
                </select>
            </div>
        </div>

        <?php if ($permissionHelper->hasPermission('usersAssignRole')): ?>
        <div class="save-button-container">
            <span class="save-label">
                <i class="fas fa-save"></i>
                Save Changes
            </span>
            <button class="action-btn btn-primary" id="saveUserRoleBtn">
                <i class="fas fa-save"></i>
                <span>Save</span>
            </button>
        </div>
        <?php endif; ?>
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
                    <tr>
                        <td colspan="4">
                            <div class="empty-state">
                                <i class="fas fa-spinner fa-spin"></i>
                                <h3>Loading Users</h3>
                                <p>Please wait while we fetch user data...</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="content-section" id="rolesSection">
    <div class="cards-grid" id="rolesCardsContainer">
        <div class="loading-state">
            <i class="fas fa-spinner fa-spin"></i>
            <h3>Loading Roles</h3>
            <p>Please wait while we fetch role data...</p>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modals -->
<?php if ($permissionHelper->hasAnyPermission(['rolesEdit', 'rolesCreate'])): ?>
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
                    <i class="fas fa-times"></i>
                    Cancel
                </button>
                <button type="submit" class="modal-btn modal-btn-primary">
                    <i class="fas fa-save"></i>
                    Save Permissions
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($canDelete): ?>
<div class="modal-overlay" id="deleteRoleModal">
    <div class="modal delete-modal">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fas fa-exclamation-triangle"></i>
                Delete Role
            </h3>
            <button class="close-modal" id="closeDeleteModal">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <i class="fas fa-exclamation-circle warning-icon"></i>
            <h3>Are you sure?</h3>
            <p>
                You are about to delete the role <span class="role-name" id="deleteRoleName"></span>.
                This action cannot be undone and will affect all teachers with this role.
            </p>
            <p style="color: var(--error-red); font-size: 0.9rem;">
                <i class="fas fa-exclamation-triangle"></i>
                Teachers with this role will become unassigned.
            </p>
        </div>
        <div class="modal-footer">
            <button class="modal-btn modal-btn-secondary cancel-btn" id="cancelDeleteBtn">
                <i class="fas fa-times"></i>
                Cancel
            </button>
            <button class="modal-btn modal-btn-primary delete-btn" id="confirmDeleteBtn">
                <i class="fas fa-trash-alt"></i>
                Delete Role
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="toast-container" id="toastContainer"></div>

<script src="assets/banner/banner.js"></script>
<script>
// All your existing JavaScript code remains exactly the same
const PERMISSIONS = {
    canCreate: <?php echo $canCreate ? 'true' : 'false'; ?>,
    canEdit: <?php echo $canEdit ? 'true' : 'false'; ?>,
    canDelete: <?php echo $canDelete ? 'true' : 'false'; ?>,
    canViewAll: <?php echo $canViewAll ? 'true' : 'false'; ?>,
    canAssignRole: <?php echo $permissionHelper->hasPermission('usersAssignRole') ? 'true' : 'false'; ?>,
    isSuperAdmin: <?php echo $isSuperAdmin ? 'true' : 'false'; ?>
};

// DOM Elements
const rolesToggleUsers = document.getElementById('toggleUsers');
const rolesToggleRoles = document.getElementById('toggleRoles');
const rolesUsersSection = document.getElementById('usersSection');
const rolesRolesSection = document.getElementById('rolesSection');
const rolesPermissionsModal = document.getElementById('permissionsModal');
const rolesModalRoleName = document.getElementById('modalRoleName');
const rolesClosePermissionsModal = document.getElementById('closePermissionsModal');
const rolesCancelPermissionsBtn = document.getElementById('cancelPermissionsBtn');
const rolesTeacherFilter = document.getElementById('teacherFilter');
const rolesRoleFilter = document.getElementById('roleFilter');
const rolesUsersTableBody = document.getElementById('usersTableBody');
const rolesToastContainer = document.getElementById('toastContainer');
const rolesPermissionsForm = document.getElementById('permissionsForm');
const saveUserRoleBtn = document.getElementById('saveUserRoleBtn');
const deleteRoleModal = document.getElementById('deleteRoleModal');
const closeDeleteModal = document.getElementById('closeDeleteModal');
const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
const deleteRoleNameSpan = document.getElementById('deleteRoleName');

let rolesUsersData = [];
let roleToDelete = { id: null, name: null };

function showToast(title, message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    let icon = 'check-circle';
    if (type === 'error') icon = 'exclamation-circle';
    if (type === 'warning') icon = 'exclamation-triangle';
    if (type === 'info') icon = 'info-circle';
    toast.innerHTML = `
        <div class="toast-icon"><i class="fas fa-${icon}"></i></div>
        <div class="toast-content"><div class="toast-title">${title}</div><div class="toast-message">${message}</div></div>
    `;
    rolesToastContainer.appendChild(toast);
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100%)';
        toast.style.transition = 'all 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 5000);
}

rolesToggleUsers.addEventListener('click', () => {
    rolesToggleUsers.classList.add('active');
    rolesToggleRoles.classList.remove('active');
    rolesUsersSection.classList.add('active');
    rolesRolesSection.classList.remove('active');
});

rolesToggleRoles.addEventListener('click', () => {
    rolesToggleRoles.classList.add('active');
    rolesToggleUsers.classList.remove('active');
    rolesRolesSection.classList.add('active');
    rolesUsersSection.classList.remove('active');
    fetchRoles();
});

function closePermissionsModalFunc() {
    rolesPermissionsModal.classList.remove('active');
    document.body.style.overflow = '';
}

rolesClosePermissionsModal.addEventListener('click', closePermissionsModalFunc);
rolesCancelPermissionsBtn.addEventListener('click', closePermissionsModalFunc);
rolesPermissionsModal.addEventListener('click', (e) => {
    if (e.target === rolesPermissionsModal) closePermissionsModalFunc();
});

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closePermissionsModalFunc();
        closeDeleteModalFunc();
    }
});

async function fetchUsers() {
    try {
        const response = await fetch('ajax/teacher.php?action=fetch_teachers');
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        const result = await response.json();
        if (result.success) {
            rolesUsersData = result.data;
            renderUsersTable(rolesUsersData);
            populateFilters(rolesUsersData);
        } else {
            showToast('Error', result.message || 'Failed to load users', 'error');
        }
    } catch (error) {
        rolesUsersTableBody.innerHTML = `<table><td colspan="4"><div class="empty-state"><i class="fas fa-exclamation-triangle"></i><h3>Error Loading Users</h3><p>${escapeHtml(error.message)}</p><button onclick="fetchUsers()" class="set-roles-btn">Retry</button></div></td></tr>`;
    }
}

function renderUsersTable(users) {
    if (users.length === 0) {
        rolesUsersTableBody.innerHTML = `<tr><td colspan="4"><div class="empty-state"><i class="fas fa-users"></i><h3>No Users Found</h3><p>No users match your filter criteria</p></div></td></tr>`;
        return;
    }
    rolesUsersTableBody.innerHTML = users.map(user => {
        const isProtected = user.role === 'Super Admin' || (user.firstname === 'School' && user.lastname === 'Admin');
        const fullName = `${user.firstname} ${user.middlename ? user.middlename + ' ' : ''}${user.lastname}`.trim();
        return `<tr><td><div class="user-info"><div class="user-avatar">${user.firstname ? user.firstname.charAt(0).toUpperCase() : 'U'}</div><div class="user-details"><div class="roles-user-name">${escapeHtml(fullName)}${isProtected ? '<i class="fas fa-shield-alt" style="color: var(--error-red); margin-left: 0.25rem;" title="Protected"></i>' : ''}</div><div class="user-email">${escapeHtml(user.email)}</div></div></div></td><td>${escapeHtml(user.email)}</td><td><span class="role-badge">${escapeHtml(user.role || 'Not Assigned')}</span></td><td><div class="action-btns">${isProtected ? '<span class="protected-badge"><i class="fas fa-lock"></i> Protected</span>' : `${PERMISSIONS.canEdit ? '<button class="action-btn edit-btn" onclick="editUser(' + user.id + ')"><i class="fas fa-edit"></i></button>' : ''}${PERMISSIONS.canDelete ? '<button class="action-btn delete-btn" onclick="deleteUser(' + user.id + ')"><i class="fas fa-trash"></i></button>' : ''}`}</div></td></tr>`;
    }).join('');
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

async function populateFilters(users) {
    const uniqueTeachers = [...new Map(users.map(user => [user.id, { id: user.id, name: `${user.firstname || ''} ${user.lastname || ''}`.trim() }])).values()];
    rolesTeacherFilter.innerHTML = '<option value="">All Teachers</option>';
    uniqueTeachers.forEach(teacher => {
        if (teacher.id && teacher.name) {
            const option = document.createElement('option');
            option.value = teacher.id;
            option.textContent = teacher.name;
            rolesTeacherFilter.appendChild(option);
        }
    });
    try {
        const response = await fetch('ajax/roles.php?action=get_teacher_roles');
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        const result = await response.json();
        if (result.success && result.data && result.data.length > 0) {
            rolesRoleFilter.innerHTML = '<option value="">All Roles</option>';
            result.data.forEach(roleName => {
                if (roleName && roleName !== '0' && roleName.trim() !== '') {
                    const option = document.createElement('option');
                    option.value = roleName;
                    option.textContent = roleName;
                    rolesRoleFilter.appendChild(option);
                }
            });
        } else {
            loadDefaultRoles();
        }
    } catch (error) {
        console.error('Failed to load roles:', error);
        loadDefaultRoles();
    }
}

function loadDefaultRoles() {
    rolesRoleFilter.innerHTML = '<option value="">All Roles</option>';
    const defaultRoles = ['ICT Teacher', 'Teacher'];
    defaultRoles.forEach(role => {
        const option = document.createElement('option');
        option.value = role;
        option.textContent = role;
        rolesRoleFilter.appendChild(option);
    });
}

function filterUsers() {
    const selectedTeacher = rolesTeacherFilter.value;
    const selectedRole = rolesRoleFilter.value;
    let filtered = rolesUsersData;
    if (selectedTeacher) filtered = filtered.filter(u => u.id == selectedTeacher);
    if (selectedRole) filtered = filtered.filter(u => u.role === selectedRole);
    renderUsersTable(filtered);
}

rolesTeacherFilter.addEventListener('change', filterUsers);
rolesRoleFilter.addEventListener('change', filterUsers);

window.editUser = function(userId) {
    if (!PERMISSIONS.canEdit) { showToast('Access Denied', 'You do not have permission to edit users', 'error'); return; }
    const user = rolesUsersData.find(u => u.id === userId);
    showToast('Edit User', `Editing user: ${user?.firstname || ''} ${user?.lastname || ''}`, 'info');
};

window.deleteUser = function(userId) {
    if (!PERMISSIONS.canDelete) { showToast('Access Denied', 'You do not have permission to delete users', 'error'); return; }
    const user = rolesUsersData.find(u => u.id === userId);
    if (confirm(`Are you sure you want to delete user: ${user?.firstname || ''} ${user?.lastname || ''}?`)) {
        showToast('Success', 'User deleted successfully!', 'success');
    }
};

async function assignRoleToTeacher(teacherId, roleName) {
    if (!PERMISSIONS.canAssignRole) { showToast('Access Denied', 'You do not have permission to assign roles', 'error'); return false; }
    try {
        const response = await fetch('ajax/teacher.php?action=update_teacher_role', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ teacher_id: teacherId, role: roleName })
        });
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        const result = await response.json();
        if (result.success) {
            showToast('Success', 'Role assigned successfully!', 'success');
            fetchUsers();
            return true;
        } else {
            showToast('Error', result.message || 'Failed to assign role', 'error');
            return false;
        }
    } catch (error) {
        showToast('Error', 'Failed to assign role: ' + error.message, 'error');
        return false;
    }
}

if (saveUserRoleBtn) {
    saveUserRoleBtn.addEventListener('click', async function(e) {
        e.preventDefault();
        if (!PERMISSIONS.canAssignRole) { showToast('Access Denied', 'You do not have permission to assign roles', 'error'); return; }
        const teacherId = rolesTeacherFilter.value;
        const roleName = rolesRoleFilter.value;
        if (!teacherId) { showToast('Warning', 'Please select a teacher', 'warning'); return; }
        if (!roleName) { showToast('Warning', 'Please select a role', 'warning'); return; }
        const btn = this;
        const originalHtml = btn.innerHTML;
        btn.classList.add('saving');
        btn.innerHTML = '<i class="fas fa-spinner"></i> Assigning...';
        const success = await assignRoleToTeacher(teacherId, roleName);
        if (success) {
            btn.classList.remove('saving');
            btn.classList.add('success');
            btn.innerHTML = '<i class="fas fa-check"></i> Assigned!';
            setTimeout(() => { btn.classList.remove('success'); btn.innerHTML = originalHtml; }, 2000);
        } else {
            btn.classList.remove('saving');
            btn.innerHTML = originalHtml;
        }
    });
}

async function fetchRoles() {
    const rolesContainer = document.getElementById('rolesCardsContainer');
    try {
        rolesContainer.innerHTML = `<div class="loading-state"><i class="fas fa-spinner fa-spin"></i><h3>Loading Roles</h3><p>Please wait while we fetch role data...</p></div>`;
        const response = await fetch('ajax/roles.php?action=fetch_roles');
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        const result = await response.json();
        if (result.success) {
            renderRolesCards(result.data);
        } else {
            showToast('Error', result.message || 'Failed to load roles', 'error');
            rolesContainer.innerHTML = `<div class="empty-state"><i class="fas fa-exclamation-triangle"></i><h3>Error Loading Roles</h3><p>${escapeHtml(result.message || 'Unknown error')}</p><button onclick="fetchRoles()" class="set-roles-btn">Retry</button></div>`;
        }
    } catch (error) {
        console.error('Fetch roles error:', error);
        rolesContainer.innerHTML = `<div class="empty-state"><i class="fas fa-exclamation-triangle"></i><h3>Error Loading Roles</h3><p>${escapeHtml(error.message)}</p><button onclick="fetchRoles()" class="set-roles-btn">Retry</button></div>`;
    }
}

function renderRolesCards(roles) {
    const rolesContainer = document.getElementById('rolesCardsContainer');
    if (!roles || roles.length === 0) {
        rolesContainer.innerHTML = `<div class="empty-state"><i class="fas fa-tags"></i><h3>No Roles Found</h3><p>No roles have been created yet.</p>${PERMISSIONS.canCreate ? '<button onclick="openCreateRoleModal()" class="set-roles-btn">Create Role</button>' : ''}</div>`;
        return;
    }
    const validRoles = roles.filter(role => role && role.role_name && role.role_name !== '0' && role.role_name.trim() !== '');
    if (validRoles.length === 0) {
        rolesContainer.innerHTML = `<div class="empty-state"><i class="fas fa-tags"></i><h3>No Valid Roles Found</h3><p>Please assign proper roles to teachers first.</p></div>`;
        return;
    }
    rolesContainer.innerHTML = validRoles.map(role => {
        const roleName = role.role_name || 'Unknown Role';
        const isProtected = role.is_protected === 1 || roleName === 'Super Admin';
        const permissions = Array.isArray(role.permissions) ? role.permissions : [];
        const permissionCount = permissions.length || parseInt(role.permission_count) || 0;
        const hasAllPermissions = role.has_all_permissions || false;
        const canEditRole = PERMISSIONS.canEdit && !isProtected;
        const canDeleteRole = PERMISSIONS.canDelete && !isProtected;
        const canConfigurePermissions = (PERMISSIONS.canEdit || PERMISSIONS.isSuperAdmin) && !isProtected;
        let permissionsListHtml = '';
        if (hasAllPermissions) {
            permissionsListHtml = `<li><i class="fas fa-check-circle"></i> Full system access - All permissions granted</li><li><i class="fas fa-shield-alt"></i> This role has every permission in the system</li>`;
        } else if (permissions.length > 0) {
            permissionsListHtml = permissions.map(permission => `<li><i class="fas fa-check-circle"></i> ${escapeHtml(permission?.description || permission?.permission_name || permission || 'Unknown Permission')}</li>`).join('');
        } else {
            permissionsListHtml = `<li><i class="fas fa-minus-circle"></i> No permissions assigned</li>`;
        }
        const containerId = `perms-${role.id || roleName.replace(/[^a-zA-Z0-9]/g, '-')}-${Date.now()}`;
        return `<div class="role-card ${isProtected ? 'protected' : ''}"><div class="role-card-header"><div class="role-title"><i class="${getRoleIcon(roleName)}"></i><h3>${escapeHtml(roleName)}</h3></div><div class="permission-count"><i class="fas fa-shield-alt"></i><span class="count">${hasAllPermissions ? '∞' : permissionCount}</span> ${hasAllPermissions ? 'All Permissions' : (permissionCount !== 1 ? 'Permissions' : 'Permission')}</div>${isProtected ? '<div class="protected-badge"><i class="fas fa-lock"></i> Protected</div>' : ''}</div><div class="role-card-body"><p class="role-description">${escapeHtml(role.description || `Role: ${roleName}`)}${hasAllPermissions ? '<br><span style="color: var(--success-green);">✓ Full system access</span>' : ''}</p><p class="teacher-count"><i class="fas fa-users"></i> ${role.teacher_count || 0} Teacher${(role.teacher_count || 0) !== 1 ? 's' : ''}</p><div class="permissions-container" id="${containerId}"><ul class="permissions-list">${permissionsListHtml}</ul></div>${!hasAllPermissions && permissions.length > 5 ? `<button class="show-more-btn" onclick="togglePermissions('${containerId}', this)"><span>Show More</span> <i class="fas fa-chevron-down"></i></button>` : ''}</div><div class="role-card-footer">${isProtected ? '<button class="role-action-btn protected-btn" disabled><i class="fas fa-lock"></i> Protected</button>' : `${canEditRole ? '<button class="role-action-btn edit-btn" onclick="openEditRoleModal(\'' + escapeHtml(roleName) + '\', ' + (role.id || 0) + ')"><i class="fas fa-pencil-alt"></i> Edit</button>' : ''}${canConfigurePermissions ? '<button class="role-action-btn permissions-btn" onclick="openPermissionsModal(\'' + escapeHtml(roleName) + '\', ' + (role.id || 0) + ')"><i class="fas fa-cog"></i> Permissions</button>' : ''}${canDeleteRole ? '<button class="role-action-btn delete-btn" onclick="openDeleteRoleModal(\'' + escapeHtml(roleName) + '\', ' + (role.id || 0) + ')"><i class="fas fa-trash-alt"></i> Delete</button>' : ''}`}</div></div>`;
    }).join('');
}

function openDeleteRoleModal(roleName, roleId) {
    if (!PERMISSIONS.canDelete) { showToast('Access Denied', 'You do not have permission to delete roles', 'error'); return; }
    roleToDelete = { id: roleId, name: roleName };
    deleteRoleNameSpan.textContent = roleName;
    deleteRoleModal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeDeleteModalFunc() {
    deleteRoleModal.classList.remove('active');
    document.body.style.overflow = '';
    roleToDelete = { id: null, name: null };
}

closeDeleteModal.addEventListener('click', closeDeleteModalFunc);
cancelDeleteBtn.addEventListener('click', closeDeleteModalFunc);
deleteRoleModal.addEventListener('click', (e) => { if (e.target === deleteRoleModal) closeDeleteModalFunc(); });

confirmDeleteBtn.addEventListener('click', async function() {
    if (!PERMISSIONS.canDelete) { showToast('Access Denied', 'You do not have permission to delete roles', 'error'); closeDeleteModalFunc(); return; }
    if (!roleToDelete.id) { showToast('Error', 'No role selected', 'error'); closeDeleteModalFunc(); return; }
    const originalHtml = this.innerHTML;
    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
    this.disabled = true;
    try {
        const response = await fetch('ajax/roles.php?action=delete_role', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ role_id: roleToDelete.id, role_name: roleToDelete.name })
        });
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        const result = await response.json();
        if (result.success) { showToast('Success', 'Role deleted successfully!', 'success'); closeDeleteModalFunc(); fetchRoles(); }
        else { showToast('Error', result.message || 'Failed to delete role', 'error'); }
    } catch (error) { showToast('Error', 'Failed to delete role: ' + error.message, 'error'); }
    finally { this.innerHTML = originalHtml; this.disabled = false; }
});

function getRoleIcon(roleName) {
    if (!roleName || typeof roleName !== 'string') return 'fas fa-user-tag';
    const name = roleName.toLowerCase();
    if (name.includes('super') || name.includes('admin')) return 'fas fa-crown';
    if (name.includes('ict') || name.includes('tech')) return 'fas fa-laptop-code';
    if (name.includes('teacher')) return 'fas fa-chalkboard-teacher';
    if (name.includes('student')) return 'fas fa-user-graduate';
    if (name.includes('parent')) return 'fas fa-users';
    return 'fas fa-user-tag';
}

window.togglePermissions = function(containerId, btn) {
    const container = document.getElementById(containerId);
    if (!container) return;
    const permissionsList = container.querySelector('.permissions-list');
    if (!permissionsList) return;
    const isExpanded = permissionsList.classList.contains('expanded');
    if (!isExpanded) {
        permissionsList.classList.add('expanded');
        container.classList.add('expanded');
        btn.classList.add('expanded');
        btn.innerHTML = '<span>Show Less</span> <i class="fas fa-chevron-up"></i>';
    } else {
        permissionsList.classList.remove('expanded');
        container.classList.remove('expanded');
        btn.classList.remove('expanded');
        btn.innerHTML = '<span>Show More</span> <i class="fas fa-chevron-down"></i>';
    }
};

window.openPermissionsModal = function(roleName, roleId) {
    if (!PERMISSIONS.canEdit && !PERMISSIONS.isSuperAdmin) { showToast('Access Denied', 'You do not have permission to configure permissions', 'error'); return; }
    rolesModalRoleName.textContent = `Configure Permissions - ${roleName}`;
    const roleNameInput = document.createElement('input');
    roleNameInput.type = 'hidden';
    roleNameInput.name = 'role_name';
    roleNameInput.id = 'currentRoleName';
    roleNameInput.value = roleName;
    const roleIdInput = document.createElement('input');
    roleIdInput.type = 'hidden';
    roleIdInput.name = 'role_id';
    roleIdInput.id = 'currentRoleId';
    roleIdInput.value = roleId;
    const existingRoleName = document.getElementById('currentRoleName');
    if (existingRoleName) existingRoleName.remove();
    const existingRoleId = document.getElementById('currentRoleId');
    if (existingRoleId) existingRoleId.remove();
    rolesPermissionsForm.appendChild(roleNameInput);
    rolesPermissionsForm.appendChild(roleIdInput);
    loadRolePermissions(roleName);
    rolesPermissionsModal.classList.add('active');
    document.body.style.overflow = 'hidden';
};

window.openEditRoleModal = function(roleName, roleId) {
    if (!PERMISSIONS.canEdit) { showToast('Access Denied', 'You do not have permission to edit roles', 'error'); return; }
    rolesModalRoleName.textContent = `Edit Role - ${roleName}`;
    const roleNameInput = document.createElement('input');
    roleNameInput.type = 'hidden';
    roleNameInput.name = 'role_name';
    roleNameInput.id = 'currentRoleName';
    roleNameInput.value = roleName;
    const roleIdInput = document.createElement('input');
    roleIdInput.type = 'hidden';
    roleIdInput.name = 'role_id';
    roleIdInput.id = 'currentRoleId';
    roleIdInput.value = roleId;
    const editModeInput = document.createElement('input');
    editModeInput.type = 'hidden';
    editModeInput.name = 'edit_mode';
    editModeInput.id = 'editMode';
    editModeInput.value = 'true';
    const existingRoleName = document.getElementById('currentRoleName');
    if (existingRoleName) existingRoleName.remove();
    const existingRoleId = document.getElementById('currentRoleId');
    if (existingRoleId) existingRoleId.remove();
    const existingEditMode = document.getElementById('editMode');
    if (existingEditMode) existingEditMode.remove();
    rolesPermissionsForm.appendChild(roleNameInput);
    rolesPermissionsForm.appendChild(roleIdInput);
    rolesPermissionsForm.appendChild(editModeInput);
    loadRolePermissions(roleName);
    document.querySelector('#permissionsModal .modal-title span').textContent = `Edit Role - ${roleName}`;
    const submitBtn = rolesPermissionsForm.querySelector('button[type="submit"]');
    submitBtn.innerHTML = '<i class="fas fa-save"></i> Update Role';
    rolesPermissionsModal.classList.add('active');
    document.body.style.overflow = 'hidden';
};

async function loadRolePermissions(roleName) {
    try {
        const permissionsGrid = document.getElementById('permissionsGridContainer');
        permissionsGrid.innerHTML = `<div style="text-align: center; padding: 3rem;"><i class="fas fa-spinner fa-spin"></i><p>Loading permissions for ${escapeHtml(roleName)}...</p></div>`;
        const response = await fetch(`ajax/roles.php?action=get_role_permissions&role_name=${encodeURIComponent(roleName)}`);
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        const result = await response.json();
        if (result.success) { rebuildPermissionsGrid(result.data, result.is_super_admin || roleName === 'Super Admin'); }
        else { showToast('Error', result.message || 'Failed to load permissions', 'error'); rebuildPermissionsGrid({}, roleName === 'Super Admin'); }
    } catch (error) { showToast('Error', 'Failed to load permissions: ' + error.message, 'error'); rebuildPermissionsGrid({}, roleName === 'Super Admin'); }
}

function rebuildPermissionsGrid(permissionsData, isSuperAdmin = false) {
    const permissionsGrid = document.getElementById('permissionsGridContainer');
    if (isSuperAdmin) {
        permissionsGrid.innerHTML = `<div style="text-align: center; padding: 2rem;"><i class="fas fa-crown"></i><h3>Super Admin Access</h3><p>Super Admin has full system access with all permissions. This role cannot be modified.</p><div><i class="fas fa-check-circle"></i> All permissions are granted to Super Admin</div></div>`;
        return;
    }
    const permissionCategories = {
        'classes': ['View', 'Create', 'Edit', 'Delete'], 'teachers': ['View', 'Create', 'Edit', 'Delete'], 'subjects': ['View', 'Create', 'Edit', 'Delete'],
        'students': ['View', 'Create', 'Edit', 'Delete'], 'roles': ['View', 'Create', 'Edit', 'Delete'], 'lessons': ['View', 'Create', 'Edit', 'Delete'],
        'exam': ['View', 'Create', 'Edit', 'Delete'], 'grading': ['View', 'Create', 'Edit', 'Delete'], 'scores': ['View', 'Edit'],
        'meritlist': ['View', 'Generate'], 'reports': ['View', 'Generate', 'Print'], 'analytics': ['View', 'Export'],
        'messaging': ['Send', 'View', 'Groups'], 'promotions': ['View', 'Process'], 'utility': ['Settings', 'Backup', 'Logs'],
        'timetable': ['View', 'Create', 'Edit', 'Delete'], 'attendance': ['View', 'Mark', 'Reports']
    };
    let html = '<div class="permissions-grid">';
    for (const [category, actions] of Object.entries(permissionCategories)) {
        const categoryDisplay = category.charAt(0).toUpperCase() + category.slice(1);
        html += `<div class="permission-item"><div class="permission-category"><i class="fas ${getCategoryIcon(category)}"></i> ${categoryDisplay}</div><div class="permission-actions">`;
        actions.forEach(action => {
            const permissionId = `${category}${action}`;
            const isChecked = permissionsData && permissionsData[permissionId] ? 'checked' : '';
            const canEdit = PERMISSIONS.canEdit || PERMISSIONS.isSuperAdmin;
            html += `<div class="permission-action"><input type="checkbox" id="${permissionId}" name="permissions[]" value="${permissionId}" ${isChecked} ${!canEdit ? 'disabled' : ''}><label for="${permissionId}" ${!canEdit ? 'class="disabled"' : ''}>${action}</label></div>`;
        });
        html += `</div></div>`;
    }
    html += '</div>';
    permissionsGrid.innerHTML = html;
}

function getCategoryIcon(category) {
    const icons = { 'classes': 'fa-school', 'teachers': 'fa-chalkboard-teacher', 'subjects': 'fa-book', 'students': 'fa-user-graduate', 'roles': 'fa-user-tag', 'lessons': 'fa-video', 'exam': 'fa-pencil-alt', 'grading': 'fa-star', 'scores': 'fa-chart-line', 'meritlist': 'fa-trophy', 'reports': 'fa-file-alt', 'analytics': 'fa-chart-pie', 'messaging': 'fa-comments', 'promotions': 'fa-arrow-up', 'utility': 'fa-tools', 'timetable': 'fa-calendar-alt', 'attendance': 'fa-calendar-check' };
    return icons[category] || 'fa-circle';
}

if (rolesPermissionsForm) {
    rolesPermissionsForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!PERMISSIONS.canEdit && !PERMISSIONS.isSuperAdmin) { showToast('Access Denied', 'You do not have permission to save permissions', 'error'); return; }
        const roleName = document.getElementById('currentRoleName')?.value;
        if (!roleName) { showToast('Error', 'Role name not found', 'error'); return; }
        const permissions = {};
        const checkboxes = rolesPermissionsForm.querySelectorAll('input[type="checkbox"]');
        checkboxes.forEach(cb => { permissions[cb.id] = cb.checked; });
        const submitBtn = e.target.querySelector('button[type="submit"]');
        const originalBtnHtml = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        submitBtn.disabled = true;
        try {
            const response = await fetch('ajax/roles.php?action=save_permissions', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ role_name: roleName, permissions: permissions })
            });
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const result = await response.json();
            if (result.success) { showToast('Success', 'Permissions saved successfully!', 'success'); closePermissionsModalFunc(); fetchRoles(); }
            else { showToast('Error', result.message || 'Failed to save permissions', 'error'); }
        } catch (error) { showToast('Error', 'Failed to save permissions: ' + error.message, 'error'); }
        finally { submitBtn.innerHTML = originalBtnHtml; submitBtn.disabled = false; }
    });
}

window.editRole = function(roleId) {
    if (!PERMISSIONS.canEdit) { showToast('Access Denied', 'You do not have permission to edit roles', 'error'); return; }
    showToast('Edit Role', `Editing role ID: ${roleId}`, 'info');
};

window.openCreateRoleModal = function() {
    if (!PERMISSIONS.canCreate) { showToast('Access Denied', 'You do not have permission to create roles', 'error'); return; }
    showToast('Create Role', 'Open create role modal', 'info');
};

function initializeSelect2() {
    if (typeof $ !== 'undefined' && $.fn.select2) {
        $('#teacherFilter, #roleFilter').select2({ width: '100%', minimumResultsForSearch: 5, placeholder: 'Select option', allowClear: true, theme: 'default' });
    }
}

document.addEventListener('DOMContentLoaded', () => {
    fetchUsers();
    initializeSelect2();
    fetchRoles();
});
</script>
</body>
</html>