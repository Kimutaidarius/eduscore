<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database/config
require_once 'includes/config.php';
require_once 'includes/PermissionHelper.php';
require_once 'includes/notification_helper.php';
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

// Check if user has permission to view notifications
$permissionHelper->requireAnyPermission(['notificationsView', 'notificationsViewAll'], 'dashboard.php');

// Initialize Notification Helper
$notificationHelper = new NotificationHelper($db, $school_id);

// Current academic level
$current_level = $_SESSION['academic_level'] ?? 'Primary';

// Determine which actions are allowed based on permissions
$canView = $permissionHelper->hasPermission('notificationsView');
$canViewAll = $permissionHelper->hasPermission('notificationsViewAll');
$canCreate = $permissionHelper->hasPermission('notificationsCreate');
$canEdit = $permissionHelper->hasPermission('notificationsEdit');
$canDelete = $permissionHelper->hasPermission('notificationsDelete');
$isSuperAdmin = $permissionHelper->isSuperAdmin();

// Get notification counts
$unread_count = $notificationHelper->getUnreadCount($_SESSION['teacher_id']);

// Get all notifications for display
$all_notifications = $notificationHelper->getAllNotifications($_SESSION['teacher_id']);

// Get maintenance notifications
$maintenance_notifications = $notificationHelper->getMaintenanceNotifications();
$active_maintenance = $notificationHelper->getActiveMaintenance();

// Handle AJAX request for marking notifications as read
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'mark_as_read') {
        $notification_id = $_POST['notification_id'] ?? 0;
        
        if ($notification_id) {
            $result = $notificationHelper->markAsRead($notification_id, $_SESSION['teacher_id']);
            echo json_encode(['success' => $result]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Notification ID required']);
        }
        exit;
    }
    
    if ($action === 'mark_all_read') {
        $result = $notificationHelper->markAllAsRead($_SESSION['teacher_id']);
        echo json_encode(['success' => $result]);
        exit;
    }
    
    if ($action === 'delete_notification') {
        if (!$canDelete) {
            echo json_encode(['success' => false, 'message' => 'Permission denied']);
            exit;
        }
        
        $notification_id = $_POST['notification_id'] ?? 0;
        if ($notification_id) {
            $result = $notificationHelper->deleteNotification($notification_id, $_SESSION['teacher_id']);
            echo json_encode(['success' => $result]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Notification ID required']);
        }
        exit;
    }
    
    if ($action === 'get_notifications') {
        $page = $_POST['page'] ?? 1;
        $per_page = $_POST['per_page'] ?? 20;
        $filter = $_POST['filter'] ?? 'all';
        
        $notifications = $notificationHelper->getPaginatedNotifications(
            $_SESSION['teacher_id'], 
            $page, 
            $per_page, 
            $filter
        );
        
        echo json_encode([
            'success' => true,
            'notifications' => $notifications
        ]);
        exit;
    }
}

// Page titles
$page_titles = [
    'dashboard.php' => 'Dashboard',
    'students.php' => 'Student Management',
    'teachers.php' => 'Staff Management',
    'classes.php' => 'Class Management',
    'subjects.php' => 'Subject Management',
    'scores.php' => 'Grade Management',
    'attendance.php' => 'Attendance Tracking',
    'reports.php' => 'Reports & Analytics',
    'profile.php' => 'User Profile',
    'settings.php' => 'System Settings',
    'notifications.php' => 'Notifications'
];

function getNotificationIcon($type) {
    $icons = [
        'class_created'    => 'fas fa-chalkboard-teacher',
        'subject_created'  => 'fas fa-book',
        'student_added'    => 'fas fa-user-plus',
        'teacher_added'    => 'fas fa-user-tie',
        'exam_created'     => 'fas fa-file-alt',
        'exam_deadline'    => 'fas fa-clock',
        'report_generated' => 'fas fa-chart-line',
        'scores_submitted' => 'fas fa-check-circle',
        'maintenance'      => 'fas fa-tools',
        'system'           => 'fas fa-cog',
        'reminder'         => 'fas fa-bell',
        'alert'            => 'fas fa-exclamation-triangle'
    ];
    return $icons[$type] ?? 'fas fa-bell';
}

function getNotificationIconColor($type) {
    $colors = [
        'class_created'    => '#3b82f6',
        'subject_created'  => '#8b5cf6',
        'student_added'    => '#10b981',
        'teacher_added'    => '#f59e0b',
        'exam_created'     => '#ef4444',
        'exam_deadline'    => '#f97316',
        'report_generated' => '#6366f1',
        'scores_submitted' => '#14b8a6',
        'maintenance'      => '#dc2626',
        'system'           => '#6b7280',
        'reminder'         => '#f59e0b',
        'alert'            => '#ef4444'
    ];
    return $colors[$type] ?? '#6b7280';
}

function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;

    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff/60) . ' minute' . (floor($diff/60) > 1 ? 's' : '') . ' ago';
    if ($diff < 86400) return floor($diff/3600) . ' hour' . (floor($diff/3600) > 1 ? 's' : '') . ' ago';
    if ($diff < 604800) return floor($diff/86400) . ' day' . (floor($diff/86400) > 1 ? 's' : '') . ' ago';
    return date('M j, Y', $time);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EduScore - Notification Center</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="icon" type="image/png" href="images/logo.png" />
<link rel="apple-touch-icon" href="images/logo.png">
    <style>
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
            --text-muted: #666666;
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

        .page-title {
            font-size: clamp(1.5rem, 4vw, 2rem);
            font-weight: 700;
            color: black;
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-shrink: 0;
        }

        .page-title i {
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

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--bg-white);
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            transition: var(--header-transition);
            cursor: pointer;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-blue);
        }

        .stat-card.active {
            border: 2px solid var(--primary-blue);
            background: var(--light-blue);
        }

        .stat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .stat-icon.total { background: var(--light-blue); color: var(--primary-blue); }
        .stat-icon.unread { background: #fee2e2; color: var(--error-red); }
        .stat-icon.maintenance { background: #fff7ed; color: var(--warning-orange); }
        .stat-icon.system { background: #e0f2fe; color: #0284c7; }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #000000;
        }

        .stat-label {
            color: #666666;
            font-size: 0.9rem;
            font-weight: 500;
        }

        /* Filter Section */
        .filter-section {
            background: var(--bg-white);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .filter-label {
            font-weight: 600;
            color: #000000;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }

        .filter-select {
            padding: 0.75rem 1rem;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 0.95rem;
            color: #000000;
            background: var(--bg-white);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .filter-select:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(26, 115, 232, 0.1);
        }

        .search-input-group {
            position: relative;
            flex: 1;
        }

        .search-input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 0.95rem;
            color: #000000;
            background: var(--bg-white);
            transition: all 0.3s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(26, 115, 232, 0.1);
        }

        .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
        }

        /* Notifications List */
        .notifications-container {
            background: var(--bg-white);
            border-radius: 20px;
            box-shadow: var(--shadow-xl);
            overflow: hidden;
            border: 1px solid var(--border-color);
        }

        .notifications-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(to right, var(--light-blue), transparent);
            flex-wrap: wrap;
            gap: 1rem;
        }

        .notifications-header h2 {
            font-size: 1.3rem;
            font-weight: 600;
            color: #000000;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .notifications-header-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .notifications-list {
            max-height: 600px;
            overflow-y: auto;
        }

        .notification-item {
            display: flex;
            align-items: flex-start;
            gap: 1.5rem;
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--border-color);
            transition: all 0.3s ease;
            position: relative;
        }

        .notification-item:hover {
            background: #f8fafc;
        }

        .notification-item.unread {
            background: var(--light-blue);
            border-left: 4px solid var(--primary-blue);
        }

        .notification-item.unread:hover {
            background: #d9e9ff;
        }

        .notification-item.maintenance {
            background: linear-gradient(135deg, #f97316, #dc2626);
            border-left: 4px solid #ffffff;
        }

        .notification-item.maintenance .notification-content p {
            color: #ffffff !important;
        }

        .notification-item.maintenance .notification-time {
            color: rgba(255, 255, 255, 0.9) !important;
        }

        .notification-item.maintenance .notification-icon {
            background: rgba(255, 255, 255, 0.2) !important;
            color: #ffffff !important;
        }

        .notification-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
            background: var(--light-blue);
            color: var(--primary-blue);
        }

        .notification-content {
            flex: 1;
            min-width: 0;
        }

        .notification-content p {
            margin: 0 0 0.5rem 0;
            color: #000000;
            font-size: 1rem;
            line-height: 1.5;
            font-weight: 500;
        }

        .notification-meta {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
            color: #666666;
            font-size: 0.9rem;
        }

        .notification-time i {
            margin-right: 0.25rem;
            font-size: 0.8rem;
        }

        .notification-type {
            background: var(--bg-light);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            color: #000000;
            border: 1px solid var(--border-color);
        }

        .notification-actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .action-btn {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: transparent;
            color: #666666;
        }

        .action-btn:hover {
            background: var(--bg-light);
            color: #000000;
            transform: translateY(-2px);
        }

        .action-btn.mark-read:hover {
            color: var(--success-green);
        }

        .action-btn.delete:hover {
            color: var(--error-red);
        }

        .unread-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--primary-blue);
            margin-left: 0.5rem;
            flex-shrink: 0;
        }

        /* Empty State */
        .empty-state {
            padding: 4rem 2rem;
            text-align: center;
            background: var(--bg-white);
            border-radius: 20px;
        }

        .empty-state i {
            font-size: 4rem;
            color: #d1d5db;
            margin-bottom: 1.5rem;
        }

        .empty-state h3 {
            color: #000000;
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: #666666;
            font-size: 1rem;
        }

        /* Pagination */
        .pagination {
            padding: 1.5rem 2rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            background: var(--bg-white);
        }

        .pagination-info {
            color: #666666;
            font-size: 0.9rem;
        }

        .pagination-controls {
            display: flex;
            gap: 0.5rem;
        }

        .pagination-btn {
            padding: 0.5rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: var(--bg-white);
            color: #000000;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .pagination-btn:hover:not(:disabled) {
            background: var(--primary-blue);
            color: white;
            border-color: var(--primary-blue);
        }

        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .pagination-numbers {
            display: flex;
            gap: 0.25rem;
        }

        .page-number {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: var(--bg-white);
            color: #000000;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .page-number:hover {
            background: var(--light-blue);
            border-color: var(--primary-blue);
        }

        .page-number.active {
            background: var(--primary-blue);
            color: white;
            border-color: var(--primary-blue);
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
            color: white;
        }

        .toast.show {
            transform: translateX(0);
            opacity: 1;
        }

        .toast-success { background: var(--success-green); }
        .toast-error { background: var(--error-red); }
        .toast-warning { background: var(--warning-orange); }
        .toast-info { background: var(--primary-blue); }

        .spinner {
            width: 20px;
            height: 20px;
            border: 2px solid transparent;
            border-top: 2px solid currentColor;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Loading Skeleton */
        .skeleton-item {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            gap: 1.5rem;
        }

        .skeleton-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: #e5e7eb;
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }

        .skeleton-content {
            flex: 1;
        }

        .skeleton-line {
            height: 1rem;
            background: #e5e7eb;
            border-radius: 4px;
            margin-bottom: 0.75rem;
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }

        .skeleton-line.short {
            width: 60%;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .container {
                padding: 1.5rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
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
                flex-wrap: wrap;
            }
            
            .btn {
                flex: 1;
                justify-content: center;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
            
            .stat-card {
                padding: 1rem;
            }
            
            .stat-icon {
                width: 40px;
                height: 40px;
                font-size: 1.2rem;
            }
            
            .stat-value {
                font-size: 1.5rem;
            }
            
            .notification-item {
                flex-direction: column;
                padding: 1rem 1.5rem;
                gap: 1rem;
            }
            
            .notification-actions {
                align-self: flex-end;
            }
            
            .filter-grid {
                grid-template-columns: 1fr;
            }
            
            .pagination {
                flex-direction: column;
                align-items: stretch;
            }
            
            .pagination-controls {
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .notifications-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .notifications-header-actions {
                width: 100%;
                flex-direction: column;
            }
            
            .notification-meta {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
            
            .pagination-numbers {
                display: none;
            }
        }
    </style>
</head>
<body>
    <?php 
    // Fetch school data for the banner
    $stmt = $db->prepare("SELECT * FROM tblschoolinfo WHERE id = :school_id");
    $stmt->bindParam(":school_id", $_SESSION['school_id'], PDO::PARAM_INT);
    $stmt->execute();
    $school = $stmt->fetch(PDO::FETCH_ASSOC);
    include 'trial_banner.php'; 
    ?>
    
    <!-- Include Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Include Topbar -->
        <?php include 'includes/header.php'; ?>

        <div class="container">
            <!-- Page Header -->
            <div class="page-header">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <h1 class="page-title">
                        <i class="fas fa-bell"></i>
                        Notification Center
                    </h1>
                    <span class="role-badge">
                        <i class="fas fa-<?php echo $isSuperAdmin ? 'crown' : 'user-tag'; ?>"></i>
                        <?php echo htmlspecialchars($permissionHelper->getRole() ?? 'User'); ?>
                    </span>
                </div>
                <div class="header-actions">
                    <?php if ($canView): ?>
                        <button class="btn btn-outline" id="refreshBtn">
                            <i class="fas fa-sync-alt"></i>
                            Refresh
                        </button>
                        <button class="btn btn-primary" id="markAllReadBtn" <?php echo $unread_count === 0 ? 'disabled' : ''; ?>>
                            <i class="fas fa-check-double"></i>
                            Mark All as Read
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!$canView && !$canViewAll): ?>
                <!-- Permission Denied -->
                <div class="empty-state">
                    <i class="fas fa-lock"></i>
                    <h3>Access Denied</h3>
                    <p>You do not have permission to view notifications.</p>
                    <p style="font-size: 0.9rem; margin-top: 0.5rem;">Please contact your system administrator if you need access.</p>
                </div>
            <?php else: ?>
                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card" data-filter="all">
                        <div class="stat-header">
                            <div class="stat-icon total">
                                <i class="fas fa-bell"></i>
                            </div>
                            <div class="stat-value" id="totalCount">0</div>
                        </div>
                        <div class="stat-label">Total Notifications</div>
                    </div>
                    
                    <div class="stat-card" data-filter="unread">
                        <div class="stat-header">
                            <div class="stat-icon unread">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div class="stat-value" id="unreadCount"><?php echo $unread_count; ?></div>
                        </div>
                        <div class="stat-label">Unread</div>
                    </div>
                    
                    <div class="stat-card" data-filter="maintenance">
                        <div class="stat-header">
                            <div class="stat-icon maintenance">
                                <i class="fas fa-tools"></i>
                            </div>
                            <div class="stat-value" id="maintenanceCount"><?php echo count($maintenance_notifications); ?></div>
                        </div>
                        <div class="stat-label">Maintenance</div>
                    </div>
                    
                    <div class="stat-card" data-filter="system">
                        <div class="stat-header">
                            <div class="stat-icon system">
                                <i class="fas fa-cog"></i>
                            </div>
                            <div class="stat-value" id="systemCount">0</div>
                        </div>
                        <div class="stat-label">System Alerts</div>
                    </div>
                </div>

                <!-- Filter Section -->
                <div class="filter-section">
                    <div class="filter-grid">
                        <div class="filter-group">
                            <label class="filter-label">
                                <i class="fas fa-filter"></i>
                                Filter by Type
                            </label>
                            <select class="filter-select" id="filterType">
                                <option value="all">All Notifications</option>
                                <option value="unread">Unread Only</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="system">System Alerts</option>
                                <option value="reminder">Reminders</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label class="filter-label">
                                <i class="fas fa-calendar-alt"></i>
                                Time Period
                            </label>
                            <select class="filter-select" id="filterTime">
                                <option value="all">All Time</option>
                                <option value="today">Today</option>
                                <option value="week">This Week</option>
                                <option value="month">This Month</option>
                            </select>
                        </div>
                        
                        <div class="filter-group" style="grid-column: span 2;">
                            <div class="search-input-group">
                                <i class="fas fa-search search-icon"></i>
                                <input type="text" class="search-input" id="searchInput" placeholder="Search notifications...">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notifications List -->
                <div class="notifications-container">
                    <div class="notifications-header">
                        <h2>
                            <i class="fas fa-list-ul"></i>
                            Recent Notifications
                        </h2>
                        <div class="notifications-header-actions">
                            <button class="btn btn-outline" id="exportBtn" <?php echo !$canExport ? 'disabled' : ''; ?>>
                                <i class="fas fa-download"></i>
                                Export
                            </button>
                        </div>
                    </div>

                    <div class="notifications-list" id="notificationsList">
                        <!-- Notifications will be loaded here via JavaScript -->
                        <div class="loading-state" id="loadingState">
                            <?php for ($i = 0; $i < 5; $i++): ?>
                                <div class="skeleton-item">
                                    <div class="skeleton-icon"></div>
                                    <div class="skeleton-content">
                                        <div class="skeleton-line"></div>
                                        <div class="skeleton-line short"></div>
                                    </div>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <!-- Pagination -->
                    <div class="pagination" id="pagination">
                        <div class="pagination-info" id="paginationInfo">
                            Showing 0 to 0 of 0 notifications
                        </div>
                        <div class="pagination-controls">
                            <button class="pagination-btn" id="prevPage" disabled>
                                <i class="fas fa-chevron-left"></i>
                                Previous
                            </button>
                            <div class="pagination-numbers" id="paginationNumbers"></div>
                            <button class="pagination-btn" id="nextPage" disabled>
                                Next
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <script>
        // Global variables
        let currentPage = 1;
        let totalPages = 1;
        let totalNotifications = 0;
        let currentFilter = 'all';
        let currentTimeFilter = 'all';
        let searchTerm = '';
        
        // DOM Elements
        const notificationsList = document.getElementById('notificationsList');
        const loadingState = document.getElementById('loadingState');
        const paginationInfo = document.getElementById('paginationInfo');
        const prevPageBtn = document.getElementById('prevPage');
        const nextPageBtn = document.getElementById('nextPage');
        const paginationNumbers = document.getElementById('paginationNumbers');
        const filterType = document.getElementById('filterType');
        const filterTime = document.getElementById('filterTime');
        const searchInput = document.getElementById('searchInput');
        const refreshBtn = document.getElementById('refreshBtn');
        const markAllReadBtn = document.getElementById('markAllReadBtn');
        const exportBtn = document.getElementById('exportBtn');
        const statCards = document.querySelectorAll('.stat-card');
        const totalCountEl = document.getElementById('totalCount');
        const unreadCountEl = document.getElementById('unreadCount');
        const maintenanceCountEl = document.getElementById('maintenanceCount');
        const systemCountEl = document.getElementById('systemCount');
        const toastContainer = document.getElementById('toastContainer');

        // Permissions from PHP
        const PERMISSIONS = {
            canView: <?php echo $canView ? 'true' : 'false'; ?>,
            canViewAll: <?php echo $canViewAll ? 'true' : 'false'; ?>,
            canCreate: <?php echo $canCreate ? 'true' : 'false'; ?>,
            canEdit: <?php echo $canEdit ? 'true' : 'false'; ?>,
            canDelete: <?php echo $canDelete ? 'true' : 'false'; ?>,
            isSuperAdmin: <?php echo $isSuperAdmin ? 'true' : 'false'; ?>
        };

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            loadNotifications();
            setupEventListeners();
            updateStats();
        });

        function setupEventListeners() {
            // Filter changes
            if (filterType) {
                filterType.addEventListener('change', function() {
                    currentFilter = this.value;
                    currentPage = 1;
                    loadNotifications();
                });
            }
            
            if (filterTime) {
                filterTime.addEventListener('change', function() {
                    currentTimeFilter = this.value;
                    currentPage = 1;
                    loadNotifications();
                });
            }
            
            // Search with debounce
            if (searchInput) {
                let searchTimeout;
                searchInput.addEventListener('input', function() {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        searchTerm = this.value;
                        currentPage = 1;
                        loadNotifications();
                    }, 500);
                });
            }
            
            // Refresh button
            if (refreshBtn) {
                refreshBtn.addEventListener('click', function() {
                    loadNotifications();
                    showToast('Notifications refreshed', 'success');
                });
            }
            
            // Mark all as read
            if (markAllReadBtn) {
                markAllReadBtn.addEventListener('click', function() {
                    markAllAsRead();
                });
            }
            
            // Export button
            if (exportBtn) {
                exportBtn.addEventListener('click', function() {
                    exportNotifications();
                });
            }
            
            // Stat cards filter
            statCards.forEach(card => {
                card.addEventListener('click', function() {
                    const filter = this.getAttribute('data-filter');
                    if (filterType) {
                        filterType.value = filter;
                        currentFilter = filter;
                        currentPage = 1;
                        loadNotifications();
                        
                        // Update active state
                        statCards.forEach(c => c.classList.remove('active'));
                        this.classList.add('active');
                    }
                });
            });
            
            // Pagination buttons
            if (prevPageBtn) {
                prevPageBtn.addEventListener('click', function() {
                    if (currentPage > 1) {
                        currentPage--;
                        loadNotifications();
                    }
                });
            }
            
            if (nextPageBtn) {
                nextPageBtn.addEventListener('click', function() {
                    if (currentPage < totalPages) {
                        currentPage++;
                        loadNotifications();
                    }
                });
            }
        }

        function loadNotifications() {
            // Show loading state
            if (loadingState) {
                loadingState.style.display = 'block';
            }
            
            if (notificationsList) {
                notificationsList.innerHTML = '';
                for (let i = 0; i < 5; i++) {
                    const skeleton = document.createElement('div');
                    skeleton.className = 'skeleton-item';
                    skeleton.innerHTML = `
                        <div class="skeleton-icon"></div>
                        <div class="skeleton-content">
                            <div class="skeleton-line"></div>
                            <div class="skeleton-line short"></div>
                        </div>
                    `;
                    notificationsList.appendChild(skeleton);
                }
            }
            
            const formData = new FormData();
            formData.append('action', 'get_notifications');
            formData.append('page', currentPage);
            formData.append('per_page', 20);
            formData.append('filter', currentFilter);
            formData.append('time_filter', currentTimeFilter);
            formData.append('search', searchTerm);
            
            fetch('notifications.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    renderNotifications(data.notifications);
                    updatePagination(data.notifications);
                } else {
                    showToast('Failed to load notifications', 'error');
                }
            })
            .catch(error => {
                console.error('Error loading notifications:', error);
                showToast('Error loading notifications', 'error');
            })
            .finally(() => {
                if (loadingState) {
                    loadingState.style.display = 'none';
                }
            });
        }

        function renderNotifications(notifications) {
            if (!notificationsList) return;
            
            notificationsList.innerHTML = '';
            
            if (!notifications.data || notifications.data.length === 0) {
                notificationsList.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-bell-slash"></i>
                        <h3>No Notifications</h3>
                        <p>You're all caught up! No notifications to display.</p>
                    </div>
                `;
                return;
            }
            
            notifications.data.forEach(notification => {
                const item = document.createElement('div');
                item.className = `notification-item ${!notification.is_read ? 'unread' : ''} ${notification.type === 'maintenance' ? 'maintenance' : ''}`;
                item.setAttribute('data-id', notification.id);
                
                const iconColor = getNotificationIconColor(notification.type);
                
                item.innerHTML = `
                    <div class="notification-icon" style="background: ${iconColor}20; color: ${iconColor};">
                        <i class="${getNotificationIcon(notification.type)}"></i>
                    </div>
                    <div class="notification-content">
                        <p>${notification.message}</p>
                        <div class="notification-meta">
                            <span class="notification-time">
                                <i class="far fa-clock"></i>
                                ${timeAgo(notification.created_at)}
                            </span>
                            <span class="notification-type">
                                <i class="fas fa-tag"></i>
                                ${formatNotificationType(notification.type)}
                            </span>
                        </div>
                    </div>
                    <div class="notification-actions">
                        ${!notification.is_read ? `
                            <button class="action-btn mark-read" onclick="markAsRead(${notification.id})" title="Mark as read">
                                <i class="fas fa-check"></i>
                            </button>
                        ` : ''}
                        ${PERMISSIONS.canDelete ? `
                            <button class="action-btn delete" onclick="deleteNotification(${notification.id})" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        ` : ''}
                        ${!notification.is_read ? '<span class="unread-dot"></span>' : ''}
                    </div>
                `;
                
                notificationsList.appendChild(item);
            });
            
            // Update total count
            totalNotifications = notifications.total || 0;
            if (totalCountEl) totalCountEl.textContent = totalNotifications;
        }

        function updatePagination(notifications) {
            if (!notifications) return;
            
            totalPages = Math.ceil((notifications.total || 0) / 20);
            
            // Update info text
            const start = ((currentPage - 1) * 20) + 1;
            const end = Math.min(currentPage * 20, notifications.total || 0);
            
            if (paginationInfo) {
                if (notifications.total > 0) {
                    paginationInfo.textContent = `Showing ${start} to ${end} of ${notifications.total} notifications`;
                } else {
                    paginationInfo.textContent = 'No notifications to display';
                }
            }
            
            // Update pagination buttons
            if (prevPageBtn) {
                prevPageBtn.disabled = currentPage <= 1;
            }
            
            if (nextPageBtn) {
                nextPageBtn.disabled = currentPage >= totalPages;
            }
            
            // Render page numbers
            if (paginationNumbers) {
                paginationNumbers.innerHTML = '';
                
                const maxVisible = 5;
                let startPage = Math.max(1, currentPage - Math.floor(maxVisible / 2));
                let endPage = Math.min(totalPages, startPage + maxVisible - 1);
                
                if (endPage - startPage + 1 < maxVisible) {
                    startPage = Math.max(1, endPage - maxVisible + 1);
                }
                
                for (let i = startPage; i <= endPage; i++) {
                    const pageBtn = document.createElement('button');
                    pageBtn.className = `page-number ${i === currentPage ? 'active' : ''}`;
                    pageBtn.textContent = i;
                    pageBtn.onclick = () => {
                        currentPage = i;
                        loadNotifications();
                    };
                    paginationNumbers.appendChild(pageBtn);
                }
            }
        }

        function markAsRead(notificationId) {
            const formData = new FormData();
            formData.append('action', 'mark_as_read');
            formData.append('notification_id', notificationId);
            
            fetch('notifications.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const notification = document.querySelector(`.notification-item[data-id="${notificationId}"]`);
                    if (notification) {
                        notification.classList.remove('unread');
                        const markReadBtn = notification.querySelector('.mark-read');
                        if (markReadBtn) markReadBtn.remove();
                        const unreadDot = notification.querySelector('.unread-dot');
                        if (unreadDot) unreadDot.remove();
                    }
                    
                    // Update unread count
                    const unreadCount = parseInt(unreadCountEl.textContent) - 1;
                    unreadCountEl.textContent = Math.max(0, unreadCount);
                    
                    // Update mark all read button
                    if (markAllReadBtn) {
                        markAllReadBtn.disabled = unreadCount <= 0;
                    }
                    
                    showToast('Notification marked as read', 'success');
                } else {
                    showToast('Failed to mark as read', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error marking as read', 'error');
            });
        }

        function markAllAsRead() {
            const formData = new FormData();
            formData.append('action', 'mark_all_read');
            
            fetch('notifications.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.querySelectorAll('.notification-item').forEach(item => {
                        item.classList.remove('unread');
                        const markReadBtn = item.querySelector('.mark-read');
                        if (markReadBtn) markReadBtn.remove();
                        const unreadDot = item.querySelector('.unread-dot');
                        if (unreadDot) unreadDot.remove();
                    });
                    
                    // Update counts
                    unreadCountEl.textContent = '0';
                    if (markAllReadBtn) markAllReadBtn.disabled = true;
                    
                    showToast('All notifications marked as read', 'success');
                } else {
                    showToast('Failed to mark all as read', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error marking all as read', 'error');
            });
        }

        function deleteNotification(notificationId) {
            if (!confirm('Are you sure you want to delete this notification?')) return;
            
            const formData = new FormData();
            formData.append('action', 'delete_notification');
            formData.append('notification_id', notificationId);
            
            fetch('notifications.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const notification = document.querySelector(`.notification-item[data-id="${notificationId}"]`);
                    if (notification) {
                        notification.remove();
                    }
                    showToast('Notification deleted', 'success');
                    
                    // Reload notifications to update counts
                    setTimeout(loadNotifications, 500);
                } else {
                    showToast('Failed to delete notification', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error deleting notification', 'error');
            });
        }

        function exportNotifications() {
            showToast('Preparing export...', 'info');
            
            // Build export URL with filters
            let url = 'export_notifications.php?';
            url += `filter=${currentFilter}`;
            url += `&time_filter=${currentTimeFilter}`;
            url += `&search=${encodeURIComponent(searchTerm)}`;
            
            window.open(url, '_blank');
        }

        function updateStats() {
            // Update stats from PHP values
            if (totalCountEl) totalCountEl.textContent = '<?php echo count($all_notifications); ?>';
            if (maintenanceCountEl) maintenanceCountEl.textContent = '<?php echo count($maintenance_notifications); ?>';
            
            // Calculate system alerts count (can be customized based on your data)
            const systemCount = document.querySelectorAll('.notification-item .notification-type:contains("System")').length;
            if (systemCountEl) systemCountEl.textContent = systemCount;
        }

        function getNotificationIcon(type) {
            const icons = {
                'class_created': 'fas fa-chalkboard-teacher',
                'subject_created': 'fas fa-book',
                'student_added': 'fas fa-user-plus',
                'teacher_added': 'fas fa-user-tie',
                'exam_created': 'fas fa-file-alt',
                'exam_deadline': 'fas fa-clock',
                'report_generated': 'fas fa-chart-line',
                'scores_submitted': 'fas fa-check-circle',
                'maintenance': 'fas fa-tools',
                'system': 'fas fa-cog',
                'reminder': 'fas fa-bell',
                'alert': 'fas fa-exclamation-triangle'
            };
            return icons[type] || 'fas fa-bell';
        }

        function getNotificationIconColor(type) {
            const colors = {
                'class_created': '#3b82f6',
                'subject_created': '#8b5cf6',
                'student_added': '#10b981',
                'teacher_added': '#f59e0b',
                'exam_created': '#ef4444',
                'exam_deadline': '#f97316',
                'report_generated': '#6366f1',
                'scores_submitted': '#14b8a6',
                'maintenance': '#dc2626',
                'system': '#6b7280',
                'reminder': '#f59e0b',
                'alert': '#ef4444'
            };
            return colors[type] || '#6b7280';
        }

        function formatNotificationType(type) {
            return type.split('_').map(word => 
                word.charAt(0).toUpperCase() + word.slice(1)
            ).join(' ');
        }

        function timeAgo(datetime) {
            const time = new Date(datetime).getTime();
            const now = Date.now();
            const diff = Math.floor((now - time) / 1000);
            
            if (diff < 60) return 'Just now';
            if (diff < 3600) return Math.floor(diff/60) + ' minute' + (Math.floor(diff/60) > 1 ? 's' : '') + ' ago';
            if (diff < 86400) return Math.floor(diff/3600) + ' hour' + (Math.floor(diff/3600) > 1 ? 's' : '') + ' ago';
            if (diff < 604800) return Math.floor(diff/86400) + ' day' + (Math.floor(diff/86400) > 1 ? 's' : '') + ' ago';
            
            return new Date(datetime).toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric'
            });
        }

        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            
            let icon = 'info-circle';
            if (type === 'success') icon = 'check-circle';
            if (type === 'error') icon = 'exclamation-circle';
            if (type === 'warning') icon = 'exclamation-triangle';
            
            toast.innerHTML = `
                <i class="fas fa-${icon}"></i>
                <span>${message}</span>
            `;
            
            toastContainer.appendChild(toast);
            
            setTimeout(() => toast.classList.add('show'), 10);
            
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.parentNode.removeChild(toast);
                    }
                }, 300);
            }, 5000);
        }
    </script>
</body>
</html>