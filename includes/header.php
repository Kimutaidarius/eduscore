<?php
// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once __DIR__ . '/../includes/config.php';

// Safe defaults
$school_name    = $_SESSION['school_name'] ?? 'School';
$user_name      = $_SESSION['user_fullname'] ?? $_SESSION['user_name'] ?? 'User';
$user_role      = $_SESSION['user_role'] ?? 'Administrator';
$academic_level = $_SESSION['academic_level'] ?? 'primary';
$user_id        = $_SESSION['user_id'] ?? null;
$school_id      = $_SESSION['school_id'] ?? null;

// Current page
$current_page = basename($_SERVER['PHP_SELF']);

// Academic levels
$academic_levels = [
    'primary'          => 'Primary School',
    'junior_secondary' => 'Junior Secondary',
    'senior_secondary' => 'Senior Secondary',
    'college'          => 'College'
];
$current_academic_level = $academic_levels[$academic_level] ?? 'Primary School';

// =============================
// NOTIFICATION HELPER CLASS (defined inline to avoid file not found)
// =============================
class NotificationHelper {
    private $db;
    private $school_id;
    
    public function __construct($db, $school_id) {
        $this->db = $db;
        $this->school_id = $school_id;
    }
    
    public function getUnreadCount($user_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count 
                FROM notifications 
                WHERE user_id = ? AND is_read = 0
            ");
            $stmt->execute([$user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['count'] ?? 0;
        } catch (Exception $e) {
            error_log("Error getting unread count: " . $e->getMessage());
            return 0;
        }
    }
    
    public function getNotifications($user_id, $limit = 5) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM notifications 
                WHERE user_id = ? 
                ORDER BY created_at DESC 
                LIMIT ?
            ");
            $stmt->execute([$user_id, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting notifications: " . $e->getMessage());
            return [];
        }
    }
    
    // Get maintenance notifications
    public function getMaintenanceNotifications() {
        try {
            // First check if the table exists
            $tableCheck = $this->db->query("SHOW TABLES LIKE 'maintenance_schedule'");
            if ($tableCheck->rowCount() == 0) {
                return [];
            }
            
            $stmt = $this->db->prepare("
                SELECT 
                    'maintenance' as notification_type,
                    CONCAT('🔧 ', title) as message,
                    start_date as created_at,
                    CASE 
                        WHEN status = 'scheduled' AND start_date <= NOW() AND end_date >= NOW() THEN 'in_progress'
                        ELSE status 
                    END as maintenance_status,
                    start_date,
                    end_date,
                    impact
                FROM maintenance_schedule 
                WHERE status IN ('scheduled', 'in_progress')
                AND start_date <= DATE_ADD(NOW(), INTERVAL 7 DAY)
                ORDER BY start_date ASC
                LIMIT 5
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting maintenance notifications: " . $e->getMessage());
            return [];
        }
    }
    
    // Get active maintenance
    public function getActiveMaintenance() {
        try {
            // First check if the table exists
            $tableCheck = $this->db->query("SHOW TABLES LIKE 'maintenance_schedule'");
            if ($tableCheck->rowCount() == 0) {
                return null;
            }
            
            $stmt = $this->db->prepare("
                SELECT * FROM maintenance_schedule 
                WHERE status = 'scheduled' 
                AND start_date <= NOW() 
                AND end_date >= NOW()
                LIMIT 1
            ");
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting active maintenance: " . $e->getMessage());
            return null;
        }
    }
}

// Notifications
$unread_count = 0;
$notifications = [];
$maintenance_notifications = [];
$active_maintenance = null;
$total_unread = 0;

// Initialize notification helper if database and user_id exist
if (isset($db) && $user_id && $school_id) {
    try {
        $notificationHelper = new NotificationHelper($db, $school_id);
        $unread_count = $notificationHelper->getUnreadCount($user_id);
        $notifications = $notificationHelper->getNotifications($user_id, 5);
        $maintenance_notifications = $notificationHelper->getMaintenanceNotifications();
        $active_maintenance = $notificationHelper->getActiveMaintenance();
        
        // Calculate total unread including maintenance
        $total_unread = $unread_count;
        if ($maintenance_notifications) {
            $total_unread += count($maintenance_notifications);
        }
    } catch (Exception $e) {
        error_log("Notification system error: " . $e->getMessage());
    }
}

// Helper functions
function getNotificationIcon($type) {
    $icons = [
        'class_created'    => 'fas fa-chalkboard-teacher text-primary',
        'subject_created'  => 'fas fa-book text-info',
        'student_added'    => 'fas fa-user-plus text-success',
        'teacher_added'    => 'fas fa-user-tie text-warning',
        'exam_created'     => 'fas fa-file-alt text-danger',
        'exam_deadline'    => 'fas fa-clock text-warning',
        'report_generated' => 'fas fa-chart-line text-info',
        'scores_submitted' => 'fas fa-check-circle text-success',
        'maintenance'      => 'fas fa-tools text-warning'
    ];
    return $icons[$type] ?? 'fas fa-bell text-muted';
}

function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;

    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff/60) . ' minute' . (floor($diff/60) > 1 ? 's' : '') . ' ago';
    if ($diff < 86400) return floor($diff/3600) . ' hour' . (floor($diff/3600) > 1 ? 's' : '') . ' ago';
    return date('M j, g:i A', $time);
}

function getAcademicLevelIcon($level) {
    $icons = [
        'primary'          => 'school',
        'junior_secondary' => 'graduation-cap',
        'senior_secondary' => 'graduation-cap',
        'college'          => 'university'
    ];
    return $icons[$level] ?? 'graduation-cap';
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
    'settings.php' => 'System Settings'
];
?>

<style>
/* =============================
   Modern Blue Gradient Header - Improved Color (Less Dark)
   ============================= */

:root {
    --header-primary-blue: #2b4c9e;      /* Brighter, less dark blue - more vibrant */
    --header-primary-blue-dark: #1e3a8a;  /* Original blue kept for gradient depth */
    --header-accent-blue: #3b82f6;        /* Vibrant accent blue */
    --header-text-light: #ffffff;
    --header-text-muted: rgba(255, 255, 255, 0.85);
    --header-shadow-light: 0 4px 12px rgba(0, 0, 0, 0.08);
    --header-shadow-medium: 0 6px 20px rgba(0, 0, 0, 0.12);
    --header-border-radius: 12px;
    --header-transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    --header-height: 70px;
    --mobile-header-height: 60px;
}

body {
    margin: 0;
    padding: 0;
    overflow-x: hidden;
    min-height: 100vh;
}

/* Loading Overlay */
.global-loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 9999;
    display: none;
    justify-content: center;
    align-items: center;
    backdrop-filter: blur(3px);
}

.global-loading-overlay.active {
    display: flex;
}

.loading-spinner-global {
    background: white;
    padding: 20px 30px;
    border-radius: 12px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 15px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
}

.loading-spinner-global i {
    font-size: 40px;
    color: var(--header-primary-blue);
}

.loading-spinner-global span {
    color: #374151;
    font-size: 14px;
    font-weight: 500;
}

.header {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    height: var(--header-height);
    /* IMPROVED: Brighter gradient with less dark blue */
    background: linear-gradient(135deg, var(--header-primary-blue) 0%, #2563eb 100%);
    box-shadow: var(--header-shadow-medium);
    z-index: 1000;
    transition: var(--header-transition);
    margin: 0;
    padding: 0 24px;
    box-sizing: border-box;
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    color: var(--header-text-light);
    font-family: 'Inter', sans-serif;
}

/* Optional: Add a subtle animation on header load */
@keyframes headerGlow {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
}

/* Apply animation only if preferred */
.header {
    background-size: 200% 200%;
    animation: headerGlow 8s ease infinite;
}

.mobile-header {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    height: var(--mobile-header-height);
    background: linear-gradient(135deg, var(--header-primary-blue) 0%, #2563eb 100%);
    box-shadow: var(--header-shadow-medium);
    z-index: 10001;
    margin: 0;
    padding: 0 16px;
    box-sizing: border-box;
    align-items: center;
    justify-content: space-between;
    color: var(--header-text-light);
}

.header-gradient {
    position: absolute;
    top: 0;
    right: 0;
    width: 50%;
    height: 100%;
    background: linear-gradient(90deg, transparent 0%, rgba(59, 130, 246, 0.25) 100%);
    opacity: 0.4;
    pointer-events: none;
}

.header a, .mobile-header a {
    color: var(--header-text-light);
    text-decoration: none;
    transition: color 0.3s;
}

.header a:hover, .mobile-header a:hover {
    color: rgba(255, 255, 255, 0.9);
}

.header-content {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 100%;
    padding: 0 25px;
    z-index: 2;
    gap: 20px;
    box-sizing: border-box;
    width: 100%;
}

/* Left Section */
.header-left {
    display: flex;
    align-items: center;
    gap: 20px;
    flex: 1;
    min-width: 0;
}

.menu-toggle {
    background: rgba(255, 255, 255, 0.15);
    border: none;
    border-radius: 10px;
    width: 45px;
    height: 45px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--header-text-light);
    font-size: 18px;
    cursor: pointer;
    transition: var(--header-transition);
    backdrop-filter: blur(10px);
    flex-shrink: 0;
}

.menu-toggle:hover {
    background: rgba(255, 255, 255, 0.25);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
}

@media (min-width: 993px) {
    .menu-toggle {
        display: none;
    }
}

/* Search Container */
.search-container {
    position: relative;
    flex: 1;
    max-width: 400px;
    min-width: 150px;
}

.search-box {
    position: relative;
    background: rgba(255, 255, 255, 0.12);
    border-radius: 25px;
    padding: 8px 16px;
    display: flex;
    align-items: center;
    transition: var(--header-transition);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    min-width: 0;
    box-sizing: border-box;
}

.search-box:focus-within {
    background: rgba(255, 255, 255, 0.18);
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
}

.search-icon {
    color: var(--header-text-muted);
    margin-right: 10px;
    font-size: 14px;
    flex-shrink: 0;
}

.search-input {
    background: transparent;
    border: none;
    color: var(--header-text-light);
    font-size: 14px;
    flex: 1;
    outline: none;
    min-width: 0;
    width: 100%;
}

.search-input::placeholder {
    color: var(--header-text-muted);
}

.search-clear {
    background: none;
    border: none;
    color: var(--header-text-muted);
    cursor: pointer;
    padding: 4px;
    opacity: 0;
    visibility: hidden;
    transition: var(--header-transition);
    flex-shrink: 0;
    margin-left: 8px;
}

.search-clear:hover {
    color: var(--header-text-light);
}

/* Center Section */
.header-center {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 20px;
    min-width: 0;
    flex: 1;
}

.center-academic-level-wrapper {
    position: relative;
}

.center-academic-level-btn {
    background: rgba(255, 255, 255, 0.15);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 25px;
    padding: 8px 16px;
    color: var(--header-text-light);
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    transition: var(--header-transition);
    font-size: 14px;
    font-weight: 500;
    backdrop-filter: blur(10px);
    white-space: nowrap;
    max-width: 200px;
    box-sizing: border-box;
}

.center-academic-level-btn:hover {
    background: rgba(255, 255, 255, 0.25);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
    border-color: rgba(255, 255, 255, 0.3);
}

.center-level-text {
    font-weight: 500;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 120px;
}

.center-dropdown-arrow {
    font-size: 12px;
    transition: var(--header-transition);
    flex-shrink: 0;
}

.center-academic-level-dropdown {
    position: absolute;
    top: 100%;
    left: 50%;
    transform: translateX(-50%) translateY(-10px);
    background: white;
    border-radius: var(--header-border-radius);
    box-shadow: var(--header-shadow-medium);
    margin-top: 10px;
    min-width: 280px;
    max-width: 320px;
    opacity: 0;
    visibility: hidden;
    transition: var(--header-transition);
    z-index: 10000;
    overflow: hidden;
}

.center-academic-level-dropdown.show {
    opacity: 1;
    visibility: visible;
    transform: translateX(-50%) translateY(0);
}

.center-academic-level-dropdown .dropdown-header {
    padding: 15px 20px;
    background: linear-gradient(135deg, var(--header-primary-blue), #2563eb);
    color: white;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.center-academic-level-dropdown .dropdown-header h4 {
    margin: 0 0 5px 0;
    font-size: 14px;
    font-weight: 600;
}

.center-academic-level-dropdown .dropdown-header .current-level {
    font-size: 12px;
    opacity: 0.9;
    display: block;
}

.center-academic-level-dropdown .dropdown-menu {
    padding: 10px 0;
    max-height: 300px;
    overflow-y: auto;
}

.center-academic-level-dropdown .level-option {
    display: flex;
    align-items: center;
    gap: 12px;
    width: 100%;
    padding: 12px 20px;
    background: none;
    border: none;
    text-align: left;
    cursor: pointer;
    transition: var(--header-transition);
    color: #374151;
    font-size: 14px;
    border-left: 3px solid transparent;
}

.center-academic-level-dropdown .level-option:hover {
    background: #f0f9ff;
    color: var(--header-primary-blue);
    border-left-color: var(--header-primary-blue);
}

.center-academic-level-dropdown .level-option.active {
    background: #eff6ff;
    color: var(--header-primary-blue);
    font-weight: 500;
    border-left-color: var(--header-primary-blue);
}

.center-academic-level-dropdown .level-option i:first-child {
    width: 18px;
    text-align: center;
    color: var(--header-primary-blue);
}

.center-academic-level-dropdown .level-option span {
    flex: 1;
}

.center-academic-level-dropdown .level-option .active-indicator {
    color: #10b981;
    font-size: 12px;
}

/* Page Title */
.page-title {
    color: var(--header-text-light);
    font-size: 22px;
    font-weight: 700;
    margin: 0;
    text-align: center;
    letter-spacing: -0.5px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
}

/* Right Section */
.header-right {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
    justify-content: flex-end;
    min-width: 0;
}

/* Notification Styles */
.notification-wrapper {
    position: relative;
}

.notification-btn {
    background: rgba(255, 255, 255, 0.12);
    border: none;
    border-radius: 50%;
    width: 45px;
    height: 45px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--header-text-light);
    cursor: pointer;
    transition: var(--header-transition);
    position: relative;
    backdrop-filter: blur(10px);
    flex-shrink: 0;
}

.notification-btn:hover {
    background: rgba(255, 255, 255, 0.18);
    transform: translateY(-2px);
}

.notification-count {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #ef4444;
    color: white;
    border-radius: 10px;
    padding: 2px 6px;
    font-size: 10px;
    font-weight: 600;
    min-width: 18px;
    text-align: center;
    line-height: 1;
}

.notifications-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    background: white;
    border-radius: var(--header-border-radius);
    box-shadow: var(--header-shadow-medium);
    margin-top: 8px;
    width: 350px;
    max-width: 90vw;
    max-height: 500px;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: var(--header-transition);
    z-index: 10000;
    display: flex;
    flex-direction: column;
}

.notification-wrapper:hover .notifications-dropdown {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.notifications-header {
    padding: 15px 20px;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}

.notifications-header h4 {
    margin: 0;
    color: #374151;
    font-size: 14px;
    font-weight: 600;
}

.notification-header-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

.notification-badge {
    background: #ef4444;
    color: white;
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
}

.mark-all-read {
    background: none;
    border: none;
    color: #6b7280;
    cursor: pointer;
    padding: 4px;
    border-radius: 4px;
    transition: var(--header-transition);
    flex-shrink: 0;
}

.mark-all-read:hover {
    color: #374151;
    background: #f3f4f6;
}

.notifications-list {
    max-height: 300px;
    overflow-y: auto;
    flex: 1;
}

.notification-item {
    display: flex;
    align-items: flex-start;
    padding: 12px 20px;
    border-bottom: 1px solid #f3f4f6;
    transition: var(--header-transition);
    gap: 12px;
}

.notification-item:hover {
    background: #f8fafc;
}

.notification-item.unread {
    background: #f0f9ff;
}

.notification-item.unread:hover {
    background: #e6f0fa;
}

.notification-item:last-child {
    border-bottom: none;
}

.notification-icon {
    width: 32px;
    height: 32px;
    background: #eff6ff;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--header-primary-blue);
    font-size: 14px;
    flex-shrink: 0;
}

.notification-content {
    flex: 1;
    min-width: 0;
}

.notification-content p {
    margin: 0 0 4px 0;
    color: #000000;
    font-size: 13px;
    line-height: 1.4;
    word-wrap: break-word;
    font-weight: 500;
}

.notification-time {
    color: #4b5563;
    font-size: 11px;
    white-space: nowrap;
}

.notification-item.maintenance {
    background: linear-gradient(135deg, #f97316, #dc2626);
    border-left: 4px solid #ffffff;
}

.notification-item.maintenance .notification-icon {
    background: rgba(255, 255, 255, 0.2) !important;
    color: #ffffff !important;
}

.notification-item.maintenance .notification-content p {
    color: #ffffff !important;
    font-weight: 600;
}

.notification-item.maintenance .notification-time {
    color: rgba(255, 255, 255, 0.9) !important;
}

.notification-item.maintenance .impact-badge {
    display: inline-block;
    background: rgba(255, 255, 255, 0.2);
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 600;
    color: white;
    margin-top: 4px;
    text-transform: capitalize;
}

.maintenance-urgent {
    background: #ef4444 !important;
    animation: shake 0.82s cubic-bezier(0.36, 0.07, 0.19, 0.97) infinite;
}

.countdown-timer {
    font-size: 11px;
    font-weight: 500;
    color: rgba(255, 255, 255, 0.9);
    margin-left: 4px;
}

@keyframes shake {
    10%, 90% { transform: translate3d(-1px, 0, 0); }
    20%, 80% { transform: translate3d(2px, 0, 0); }
    30%, 50%, 70% { transform: translate3d(-2px, 0, 0); }
    40%, 60% { transform: translate3d(2px, 0, 0); }
}

.notification-badge.maintenance {
    background: #ef4444;
    color: white;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

.notifications-footer {
    padding: 12px 20px;
    border-top: 1px solid #e5e7eb;
    text-align: center;
}

.view-all-notification-btn {
    color: black;
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
    transition: var(--header-transition);
    white-space: nowrap;
}

/* User Profile Styles */
.user-profile-wrapper {
    position: relative;
}

.user-profile-btn {
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 25px;
    padding: 6px 12px 6px 6px;
    color: var(--header-text-light);
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    transition: var(--header-transition);
    backdrop-filter: blur(10px);
    max-width: 220px;
    box-sizing: border-box;
    font-family: 'Inter', sans-serif;
}

.user-profile-btn:hover {
    background: rgba(255, 255, 255, 0.18);
    transform: translateY(-1px);
}

.user-avatar {
    width: 36px;
    height: 36px;
    background: linear-gradient(135deg, #facc15, var(--header-accent-blue));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 14px;
    color: #1e3a8a;
    flex-shrink: 0;
    text-transform: uppercase;
}

.header-user-info {
    display: flex;
    flex-direction: column;
    min-width: 0;
    flex: 1;
    gap: 2px;
}

.header-user-name {
    font-weight: 600;
    font-size: 14px;
    color: #ffffff;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.header-user-role {
    font-size: 12px;
    color: #fcd34d;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.user-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    background: #ffffff;
    border-radius: var(--header-border-radius);
    box-shadow: var(--header-shadow-medium);
    margin-top: 8px;
    width: 280px;
    max-width: 90vw;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: var(--header-transition);
    z-index: 10000;
    color: #374151;
}

.user-profile-wrapper:hover .user-dropdown {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.user-dropdown-header {
    padding: 20px;
    background: linear-gradient(135deg, var(--header-primary-blue), #2563eb);
    border-radius: var(--header-border-radius) var(--header-border-radius) 0 0;
    border-left: 4px solid #fcd34d;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    color: white;
}

.user-avatar.large {
    width: 60px;
    height: 60px;
    font-size: 20px;
    margin-bottom: 12px;
    background: linear-gradient(135deg, #fcd34d, var(--header-accent-blue));
    color: #1e3a8a;
}

.user-details h4 {
    margin: 0 0 4px 0;
    font-size: 16px;
    font-weight: 600;
    color: #ffffff;
}

.user-details p {
    margin: 0 0 8px 0;
    font-size: 14px;
    opacity: 0.85;
    color: #fcd34d;
}

.user-school {
    font-size: 12px;
    opacity: 0.75;
    color: #ffffffaa;
}

/* =============================
   PROFILE DROPDOWN TEXT COLORS - FIXED
   ============================= */

/* User Dropdown Menu Items - Dark Text */
.user-dropdown-menu .dropdown-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    color: #1f2937 !important;  /* Dark gray text */
    text-decoration: none;
    border-radius: 8px;
    transition: all 0.3s ease;
    font-size: 14px;
    font-weight: 500;
}

.user-dropdown-menu .dropdown-item i {
    color: #4b5563 !important;  /* Medium-dark icon color */
    font-size: 15px;
    width: 20px;
    text-align: center;
}

.user-dropdown-menu .dropdown-item:hover {
    background: #f0f9ff;
    color: #1e3a8a !important;  /* Blue on hover */
}

.user-dropdown-menu .dropdown-item:hover i {
    color: #1e3a8a !important;  /* Blue icon on hover */
}

/* Logout Button - Red Text */
.user-dropdown-menu .dropdown-item.logout-btn {
    color: #dc2626 !important;  /* Red text for logout */
    font-weight: 600;
}

.user-dropdown-menu .dropdown-item.logout-btn i {
    color: #dc2626 !important;  /* Red icon for logout */
}

.user-dropdown-menu .dropdown-item.logout-btn:hover {
    background: #fef2f2 !important;  /* Light red background on hover */
    color: #b91c1c !important;  /* Darker red on hover */
}

.user-dropdown-menu .dropdown-item.logout-btn:hover i {
    color: #b91c1c !important;  /* Darker red icon on hover */
}

/* Dropdown Divider */
.user-dropdown-menu .dropdown-divider {
    height: 1px;
    background: #e5e7eb;
    margin: 8px 0;
}

/* =============================
   MOBILE USER DROPDOWN TEXT COLORS - FIXED
   ============================= */

/* Mobile User Menu Items - Dark Text */
.mobile-user-menu .mobile-dropdown-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 15px;
    color: #1f2937 !important;  /* Dark gray text */
    text-decoration: none;
    border-radius: 8px;
    transition: all 0.3s ease;
    font-size: 14px;
    font-weight: 500;
}

.mobile-user-menu .mobile-dropdown-item i {
    color: #4b5563 !important;  /* Medium-dark icon color */
    font-size: 15px;
    width: 20px;
    text-align: center;
}

.mobile-user-menu .mobile-dropdown-item:hover {
    background: #f0f9ff;
    color: #1e3a8a !important;  /* Blue on hover */
}

.mobile-user-menu .mobile-dropdown-item:hover i {
    color: #1e3a8a !important;  /* Blue icon on hover */
}

/* Mobile Logout Button - Red Text */
.mobile-user-menu .mobile-dropdown-item.mobile-logout-btn {
    color: #dc2626 !important;  /* Red text for logout */
    font-weight: 600;
}

.mobile-user-menu .mobile-dropdown-item.mobile-logout-btn i {
    color: #dc2626 !important;  /* Red icon for logout */
}

.mobile-user-menu .mobile-dropdown-item.mobile-logout-btn:hover {
    background: #fef2f2 !important;  /* Light red background on hover */
    color: #b91c1c !important;  /* Darker red on hover */
}

.mobile-user-menu .mobile-dropdown-item.mobile-logout-btn:hover i {
    color: #b91c1c !important;  /* Darker red icon on hover */
}

/* =============================
   USER DROPDOWN HEADER - Already good, just ensuring
   ============================= */
.user-dropdown-header {
    padding: 20px;
    background: linear-gradient(135deg, var(--header-primary-blue), #2563eb);
    border-radius: var(--header-border-radius) var(--header-border-radius) 0 0;
    border-left: 4px solid #fcd34d;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    color: white;
}

.user-details h4 {
    margin: 0 0 4px 0;
    font-size: 16px;
    font-weight: 600;
    color: #ffffff;
}

.user-details p {
    margin: 0 0 8px 0;
    font-size: 14px;
    opacity: 0.85;
    color: #fcd34d;
}

.user-school {
    font-size: 12px;
    opacity: 0.75;
    color: rgba(255, 255, 255, 0.7);
}
/* Mobile Header Styles */
@media (max-width: 992px) {
    .header {
        display: none;
    }
    
    .mobile-header {
        display: flex;
    }
}

.mobile-header-gradient {
    position: absolute;
    top: 0;
    right: 0;
    width: 60%;
    height: 100%;
    background: linear-gradient(90deg, transparent 0%, rgba(59, 130, 246, 0.3) 100%);
    opacity: 0.6;
}

.mobile-header-content {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 100%;
    padding: 0 15px;
    z-index: 2;
    gap: 10px;
    box-sizing: border-box;
    width: 100%;
}

.mobile-left {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}

.mobile-menu-toggle {
    background: rgba(255, 255, 255, 0.15);
    border: none;
    border-radius: 10px;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--header-text-light);
    font-size: 16px;
    cursor: pointer;
    transition: var(--header-transition);
    backdrop-filter: blur(10px);
    flex-shrink: 0;
}

.mobile-menu-toggle:hover {
    background: rgba(255, 255, 255, 0.25);
    transform: translateY(-2px);
}

.mobile-center {
    display: flex;
    flex-direction: column;
    align-items: center;
    flex: 1;
    min-width: 0;
    margin: 0 10px;
    overflow: hidden;
}

.mobile-title {
    display: flex;
    align-items: center;
    gap: 8px;
}

.mobile-title h2 {
    color: var(--header-text-light);
    font-size: 16px;
    font-weight: 700;
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
}

.mobile-academic-toggle {
    background: rgba(255, 255, 255, 0.12);
    border: none;
    border-radius: 50%;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--header-text-light);
    cursor: pointer;
    transition: var(--header-transition);
    backdrop-filter: blur(10px);
    flex-shrink: 0;
    position: relative;
}

.mobile-academic-toggle:hover {
    background: rgba(255, 255, 255, 0.18);
}

.mobile-right {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
}

.mobile-search-toggle,
.mobile-notification-btn,
.mobile-profile-btn {
    background: rgba(255, 255, 255, 0.12);
    border: none;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--header-text-light);
    cursor: pointer;
    transition: var(--header-transition);
    backdrop-filter: blur(10px);
    flex-shrink: 0;
    position: relative;
}

.mobile-notification-count {
    position: absolute;
    top: -4px;
    right: -4px;
    background: #ef4444;
    color: white;
    border-radius: 8px;
    padding: 1px 4px;
    font-size: 9px;
    font-weight: 600;
    min-width: 16px;
    text-align: center;
    line-height: 1;
}

.mobile-user-avatar {
    width: 32px;
    height: 32px;
    background: linear-gradient(135deg, var(--header-accent-blue), var(--header-primary-blue-dark));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 12px;
    color: white;
}

.mobile-search-container {
    position: fixed;
    top: var(--mobile-header-height);
    left: 0;
    right: 0;
    background: white;
    padding: 15px;
    box-shadow: var(--header-shadow-medium);
    display: none;
    z-index: 10001;
}

.mobile-search-container.active {
    display: block;
    animation: slideDown 0.3s ease;
}

.mobile-search-box {
    position: relative;
    background: #f8fafc;
    border-radius: 20px;
    padding: 10px 16px;
    display: flex;
    align-items: center;
    border: 1px solid #e5e7eb;
    box-sizing: border-box;
}

.mobile-search-icon {
    color: #6b7280;
    margin-right: 10px;
    font-size: 14px;
    flex-shrink: 0;
}

.mobile-search-input {
    background: transparent;
    border: none;
    color: #374151;
    font-size: 14px;
    flex: 1;
    outline: none;
    min-width: 0;
}

.mobile-search-input::placeholder {
    color: #9ca3af;
}

.mobile-search-close {
    background: none;
    border: none;
    color: #6b7280;
    cursor: pointer;
    padding: 4px;
    margin-left: 8px;
    flex-shrink: 0;
}

.mobile-dropdown {
    position: fixed;
    top: var(--mobile-header-height);
    background: white;
    border-radius: var(--header-border-radius);
    box-shadow: var(--header-shadow-medium);
    z-index: 10002;
    max-height: 70vh;
    overflow-y: auto;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: var(--header-transition);
    width: 300px;
    max-width: 90vw;
}

.mobile-dropdown.active {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.mobile-academic-dropdown {
    left: 50%;
    transform: translateX(-50%) translateY(-10px);
    z-index: 10003;
}

.mobile-academic-dropdown.active {
    transform: translateX(-50%) translateY(0);
}

.mobile-academic-header {
    padding: 15px 20px;
    border-bottom: 1px solid #e5e7eb;
    background: linear-gradient(135deg, var(--header-primary-blue), #2563eb);
    color: white;
    border-radius: var(--header-border-radius) var(--header-border-radius) 0 0;
}

.mobile-academic-header h4 {
    margin: 0 0 5px 0;
    font-size: 14px;
    font-weight: 600;
}

.mobile-current-level {
    font-size: 12px;
    opacity: 0.9;
}

.mobile-level-option {
    width: 100%;
    background: none;
    border: none;
    padding: 12px 20px;
    text-align: left;
    cursor: pointer;
    transition: var(--header-transition);
    display: flex;
    align-items: center;
    gap: 12px;
    border-bottom: 1px solid #f3f4f6;
    color: #374151;
    font-size: 14px;
    border-left: 3px solid transparent;
}

.mobile-level-option:last-child {
    border-bottom: none;
}

.mobile-level-option:hover {
    background: #f8fafc;
    color: var(--header-primary-blue);
    border-left-color: var(--header-primary-blue);
}

.mobile-level-option.active {
    background: #eff6ff;
    color: var(--header-primary-blue);
    font-weight: 500;
    border-left-color: var(--header-primary-blue);
}

.mobile-level-option i:first-child {
    width: 18px;
    text-align: center;
    color: var(--header-primary-blue);
}

.mobile-level-option span {
    flex: 1;
}

.mobile-notifications-dropdown {
    right: 10px;
    z-index: 10002;
}

.mobile-notifications-header {
    padding: 15px 20px;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.mobile-notifications-header h4 {
    margin: 0;
    color: #374151;
    font-size: 14px;
    font-weight: 600;
}

.mobile-notifications-list {
    max-height: 300px;
    overflow-y: auto;
}

.mobile-notification-item {
    display: flex;
    align-items: flex-start;
    padding: 12px 20px;
    border-bottom: 1px solid #f3f4f6;
    gap: 12px;
}

.mobile-notification-item:last-child {
    border-bottom: none;
}

.mobile-notification-icon {
    width: 32px;
    height: 32px;
    background: #eff6ff;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--header-primary-blue);
    font-size: 14px;
    flex-shrink: 0;
}

.mobile-notification-content {
    flex: 1;
    min-width: 0;
}

.mobile-notification-content p {
    margin: 0 0 4px 0;
    color: #000000;
    font-size: 13px;
    line-height: 1.4;
    font-weight: 500;
}

.mobile-notification-time {
    color: #4b5563;
    font-size: 11px;
}

.mobile-notification-item.maintenance {
    background: linear-gradient(135deg, #f97316, #dc2626);
    border-left: 4px solid #ffffff;
}

.mobile-notification-item.maintenance .mobile-notification-icon {
    background: rgba(255, 255, 255, 0.2) !important;
    color: #ffffff !important;
}

.mobile-notification-item.maintenance .mobile-notification-content p {
    color: #ffffff !important;
    font-weight: 600;
}

.mobile-notification-item.maintenance .mobile-notification-time {
    color: rgba(255, 255, 255, 0.9) !important;
}

.mobile-user-dropdown {
    right: 10px;
    z-index: 10002;
}

.mobile-user-header {
    padding: 20px;
    background: linear-gradient(135deg, var(--header-primary-blue), #2563eb);
    color: white;
    text-align: center;
    border-radius: var(--header-border-radius) var(--header-border-radius) 0 0;
}

.mobile-user-avatar-large {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, var(--header-accent-blue), var(--header-primary-blue-dark));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 20px;
    color: white;
    margin: 0 auto 10px;
}

.mobile-user-details h4 {
    margin: 0 0 4px 0;
    font-size: 16px;
}

.mobile-user-details p {
    margin: 0 0 8px 0;
    opacity: 0.9;
    font-size: 14px;
}

.mobile-user-menu {
    padding: 8px;
}

.mobile-dropdown-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 15px;
    color: #374151;
    text-decoration: none;
    border-radius: 8px;
    transition: var(--header-transition);
    font-size: 14px;
}

.mobile-dropdown-item:hover {
    background: #f8fafc;
    color: var(--header-primary-blue);
}

.mobile-logout-btn {
    color: #ef4444;
}

.mobile-logout-btn:hover {
    background: #fef2f2;
    color: #dc2626;
}

.mobile-overlay {
    display: none;
    position: fixed;
    top: var(--mobile-header-height);
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 10001;
    backdrop-filter: blur(2px);
}

.mobile-overlay.active {
    display: block;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (max-width: 768px) {
    .mobile-header-content {
        padding: 0 12px;
    }
    
    .mobile-title h2 {
        font-size: 15px;
        max-width: 120px;
    }
    
    .mobile-menu-toggle,
    .mobile-search-toggle,
    .mobile-notification-btn,
    .mobile-profile-btn,
    .mobile-academic-toggle {
        width: 36px;
        height: 36px;
        font-size: 14px;
    }
    
    .mobile-academic-toggle {
        width: 32px;
        height: 32px;
    }
    
    .mobile-user-avatar {
        width: 28px;
        height: 28px;
        font-size: 11px;
    }
    
    .mobile-right {
        gap: 6px;
    }
}

@media (max-width: 576px) {
    .mobile-header-content {
        padding: 0 10px;
    }
    
    .mobile-center {
        margin: 0 5px;
    }
    
    .mobile-title h2 {
        font-size: 14px;
        max-width: 100px;
    }
    
    .mobile-right {
        gap: 4px;
    }
    
    .mobile-menu-toggle,
    .mobile-search-toggle,
    .mobile-notification-btn,
    .mobile-profile-btn,
    .mobile-academic-toggle {
        width: 34px;
        height: 34px;
        font-size: 13px;
    }
    
    .mobile-academic-toggle {
        width: 30px;
        height: 30px;
    }
    
    .mobile-user-avatar {
        width: 26px;
        height: 26px;
        font-size: 10px;
    }
    
    .mobile-dropdown {
        width: 280px;
    }
}

@media (max-width: 480px) {
    .mobile-header-content {
        padding: 0 8px;
    }
    
    .mobile-title h2 {
        font-size: 13px;
        max-width: 80px;
    }
    
    .mobile-search-toggle {
        display: none;
    }
}
</style>

<!-- Global Loading Overlay -->
<div class="global-loading-overlay" id="globalLoadingOverlay">
    <div class="loading-spinner-global">
        <i class="fas fa-spinner fa-spin"></i>
        <span>Updating dashboard data...</span>
    </div>
</div>

<!-- Desktop Header -->
<header class="header">
    <div class="header-gradient"></div>
    <div class="header-content">
        <!-- Left Section: Menu Toggle and Search -->
        <div class="header-left">
            <button class="menu-toggle" id="menuToggle" aria-label="Toggle sidebar">
                <i class="fas fa-bars"></i>
            </button>
            
            <!-- Search -->
            <div class="search-container">
                <div class="search-box">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" class="search-input" id="globalSearchInput" placeholder="Search students, classes, reports...">
                    <button class="search-clear" id="searchClear"><i class="fas fa-times"></i></button>
                </div>
                <div class="search-suggestions" id="searchSuggestions">
                    <div class="suggestions-header"></div>
                    <div class="suggestions-list" id="suggestionsList"></div>
                </div>
            </div>
        </div>

        <!-- Center Section: Academic Level + Title -->
        <div class="header-center">
            <!-- Academic Level Dropdown -->
            <div class="center-academic-level-wrapper" id="centerAcademicLevelWrapper">
                <button class="center-academic-level-btn" id="centerAcademicLevelBtn" aria-label="Change academic level">
                    <i class="fas fa-graduation-cap"></i>
                    <span class="center-level-text"><?php echo htmlspecialchars($current_academic_level); ?></span>
                    <i class="fas fa-chevron-down center-dropdown-arrow"></i>
                </button>

                <div class="center-academic-level-dropdown" id="centerAcademicLevelDropdown">
                    <div class="dropdown-header">
                        <h4>Academic Level</h4>
                        <span class="current-level">Current: <?php echo htmlspecialchars($current_academic_level); ?></span>
                    </div>
                    <div class="dropdown-menu">
                        <?php foreach($academic_levels as $key => $level): ?>
                            <button class="level-option <?php echo $key == $academic_level ? 'active' : ''; ?>" 
                                data-level="<?php echo htmlspecialchars($key); ?>"
                                data-display="<?php echo htmlspecialchars($level); ?>">
                                <i class="fas fa-<?php echo getAcademicLevelIcon($key); ?>"></i>
                                <span><?php echo htmlspecialchars($level); ?></span>
                                <?php if($key == $academic_level): ?>
                                    <i class="fas fa-check active-indicator"></i>
                                <?php endif; ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Page Title -->
            <h1 class="page-title">
                <?php echo $page_titles[$current_page] ?? 'EduScore Management System'; ?>
            </h1>
        </div>

        <!-- Right Section: Notifications and Profile -->
        <div class="header-right">
            <!-- Notifications -->
            <div class="notification-wrapper">
                <button class="notification-btn <?php echo $active_maintenance ? 'maintenance-urgent' : ''; ?>" id="notificationBtn" aria-label="Notifications">
                    <?php if ($active_maintenance): ?>
                        <i class="fas fa-exclamation-triangle"></i>
                    <?php else: ?>
                        <i class="fas fa-bell"></i>
                    <?php endif; ?>
                    
                    <?php if ($total_unread > 0): ?>
                    <span class="notification-count <?php echo $active_maintenance ? 'maintenance' : ''; ?>"><?php echo $total_unread; ?></span>
                    <?php endif; ?>
                </button>

                <div class="notifications-dropdown" id="notificationsDropdown">
                    <div class="notifications-header">
                        <h4>
                            <?php if ($active_maintenance): ?>
                                <i class="fas fa-exclamation-triangle text-red-500 mr-2"></i>
                            <?php endif; ?>
                            Notifications
                        </h4>
                        <?php if ($total_unread > 0): ?>
                            <div class="notification-header-actions">
                                <span class="notification-badge <?php echo $active_maintenance ? 'maintenance' : ''; ?>">
                                    <?php echo $total_unread; ?> New
                                    <?php if ($active_maintenance): ?>
                                        (Maintenance)
                                    <?php endif; ?>
                                </span>
                                <?php if ($unread_count > 0): ?>
                                    <button class="mark-all-read" id="markAllRead"><i class="fas fa-check-double"></i></button>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="notifications-list" id="notificationsList">
                        <?php 
                        // Show maintenance notifications first (priority)
                        if ($maintenance_notifications): 
                            foreach ($maintenance_notifications as $maintenance): 
                                $is_active = $maintenance['maintenance_status'] == 'in_progress';
                                $start = new DateTime($maintenance['start_date']);
                                $now = new DateTime();
                                $time_to_start = $start->getTimestamp() - $now->getTimestamp();
                                
                                // Calculate countdown if upcoming
                                if ($time_to_start > 0 && $time_to_start < 86400) {
                                    $hours = floor($time_to_start / 3600);
                                    $minutes = floor(($time_to_start % 3600) / 60);
                                    $countdown_text = "Starts in {$hours}h {$minutes}m";
                                } elseif ($is_active) {
                                    $countdown_text = "🔴 IN PROGRESS NOW";
                                } else {
                                    $countdown_text = "Scheduled: " . $start->format('M j, g:i A');
                                }
                        ?>
                            <div class="notification-item maintenance <?php echo $is_active ? 'active-maintenance' : ''; ?>">
                                <div class="notification-icon">
                                    <?php if ($is_active): ?>
                                        <i class="fas fa-exclamation-triangle"></i>
                                    <?php else: ?>
                                        <i class="fas fa-tools"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="notification-content">
                                    <p>
                                        <?php echo htmlspecialchars($maintenance['message']); ?>
                                        <?php if ($is_active): ?>
                                            <span class="countdown-timer">(Now)</span>
                                        <?php endif; ?>
                                    </p>
                                    <div class="notification-time">
                                        <?php echo $countdown_text; ?>
                                    </div>
                                    <span class="impact-badge"><?php echo $maintenance['impact']; ?> impact</span>
                                </div>
                            </div>
                        <?php 
                            endforeach; 
                        endif; 
                        ?>
                        
                        <?php if ($notifications): ?>
                            <?php foreach ($notifications as $notification): ?>
                                <div class="notification-item <?php echo !$notification['is_read'] ? 'unread' : ''; ?>">
                                    <div class="notification-icon">
                                        <i class="<?php echo getNotificationIcon($notification['notification_type']); ?>"></i>
                                    </div>
                                    <div class="notification-content">
                                        <p><?php echo htmlspecialchars($notification['message']); ?></p>
                                        <span class="notification-time"><?php echo timeAgo($notification['created_at']); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php elseif (!$maintenance_notifications): ?>
                            <div class="notification-item empty">
                                <div class="notification-content">
                                    <p>No notifications</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="notifications-footer">
                        <a href="notifications.php" class="view-all-notification-btn">View All Notifications</a>
                    </div>
                </div>
            </div>

            <!-- User Profile -->
            <div class="user-profile-wrapper">
                <button class="user-profile-btn" id="userProfileBtn" aria-label="User profile">
                    <div class="user-avatar"><?php echo strtoupper(substr($user_name, 0, 1)); ?></div>
                    <div class="header-user-info">
                        <span class="header-user-name"><?php echo htmlspecialchars($user_name); ?></span>
                        <span class="header-user-role"><?php echo htmlspecialchars($user_role); ?></span>
                    </div>
                    <i class="fas fa-chevron-down dropdown-arrow"></i>
                </button>

                <div class="user-dropdown" id="userDropdown">
                    <div class="user-dropdown-header">
                        <div class="user-avatar large">
                            <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                        </div>
                        <div class="user-details">
                            <h4><?php echo htmlspecialchars($user_name); ?></h4>
                            <p><?php echo htmlspecialchars($user_role); ?></p>
                            <div class="user-school"><?php echo htmlspecialchars($school_name); ?></div>
                        </div>
                    </div>

                    <div class="user-dropdown-menu">
                        <a href="profile.php" class="dropdown-item"><i class="fas fa-user"></i> My Profile</a>
                        <a href="settings.php" class="dropdown-item"><i class="fas fa-cog"></i> Settings</a>
                        <a href="help.php" class="dropdown-item"><i class="fas fa-question-circle"></i> Help</a>
                        <div class="dropdown-divider"></div>
                        <a href="logout.php" class="dropdown-item logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

<!-- Mobile Header -->
<div class="mobile-header">
    <div class="mobile-header-gradient"></div>
    <div class="mobile-header-content">
        <div class="mobile-left">
            <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle sidebar">
                <i class="fas fa-bars"></i>
            </button>
        </div>

        <div class="mobile-center">
            <div class="mobile-title">
                <h2><?php echo $page_titles[$current_page] ?? 'EduScore'; ?></h2>
                <button class="mobile-academic-toggle" id="mobileAcademicToggle" aria-label="Change academic level">
                    <i class="fas fa-graduation-cap"></i>
                </button>
            </div>
        </div>

        <div class="mobile-right">
            <button class="mobile-search-toggle" id="mobileSearchToggle" aria-label="Search">
                <i class="fas fa-search"></i>
            </button>

            <button class="mobile-notification-btn" id="mobileNotificationBtn" aria-label="Notifications">
                <i class="fas fa-bell"></i>
                <?php if ($total_unread > 0): ?>
                <span class="mobile-notification-count"><?php echo $total_unread; ?></span>
                <?php endif; ?>
            </button>

            <button class="mobile-profile-btn" id="mobileProfileBtn" aria-label="User profile">
                <div class="mobile-user-avatar"><?php echo strtoupper(substr($user_name, 0, 1)); ?></div>
            </button>
        </div>
    </div>
</div>

<!-- Mobile Search Container -->
<div class="mobile-search-container" id="mobileSearchContainer">
    <div class="mobile-search-box">
        <i class="fas fa-search mobile-search-icon"></i>
        <input type="text" class="mobile-search-input" id="mobileSearchInput" placeholder="Search...">
        <button class="mobile-search-close" id="mobileSearchClose"><i class="fas fa-times"></i></button>
    </div>
    <div class="mobile-search-suggestions" id="mobileSearchSuggestions"></div>
</div>

<!-- Mobile Dropdowns -->
<div class="mobile-dropdown mobile-academic-dropdown" id="mobileAcademicDropdown">
    <div class="mobile-academic-header">
        <h4>Academic Level</h4>
        <div class="mobile-current-level">Current: <?php echo htmlspecialchars($current_academic_level); ?></div>
    </div>
    <div class="mobile-level-list">
        <?php foreach($academic_levels as $key => $level): ?>
            <button class="mobile-level-option <?php echo $key == $academic_level ? 'active' : ''; ?>" 
                data-level="<?php echo htmlspecialchars($key); ?>"
                data-display="<?php echo htmlspecialchars($level); ?>">
                <i class="fas fa-<?php echo getAcademicLevelIcon($key); ?>"></i>
                <span><?php echo htmlspecialchars($level); ?></span>
                <?php if($key == $academic_level): ?>
                    <i class="fas fa-check active-indicator"></i>
                <?php endif; ?>
            </button>
        <?php endforeach; ?>
    </div>
</div>

<!-- Mobile Notifications Dropdown -->
<div class="mobile-dropdown mobile-notifications-dropdown" id="mobileNotificationsDropdown">
    <div class="mobile-notifications-header">
        <h4>
            <?php if ($active_maintenance): ?>
                <i class="fas fa-exclamation-triangle text-red-500 mr-2"></i>
            <?php endif; ?>
            Notifications
        </h4>
        <?php if ($total_unread > 0): ?>
            <div class="notification-header-actions">
                <span class="notification-badge <?php echo $active_maintenance ? 'maintenance' : ''; ?>">
                    <?php echo $total_unread; ?>
                </span>
            </div>
        <?php endif; ?>
    </div>
    <div class="mobile-notifications-list">
        <?php 
        // Show maintenance notifications first (priority)
        if ($maintenance_notifications): 
            foreach ($maintenance_notifications as $maintenance): 
                $is_active = $maintenance['maintenance_status'] == 'in_progress';
                $start = new DateTime($maintenance['start_date']);
                $now = new DateTime();
                $time_to_start = $start->getTimestamp() - $now->getTimestamp();
                
                if ($time_to_start > 0 && $time_to_start < 86400) {
                    $hours = floor($time_to_start / 3600);
                    $minutes = floor(($time_to_start % 3600) / 60);
                    $countdown_text = "Starts in {$hours}h {$minutes}m";
                } elseif ($is_active) {
                    $countdown_text = "🔴 IN PROGRESS NOW";
                } else {
                    $countdown_text = "Scheduled: " . $start->format('M j, g:i A');
                }
        ?>
            <div class="mobile-notification-item maintenance <?php echo $is_active ? 'active-maintenance' : ''; ?>">
                <div class="mobile-notification-icon">
                    <?php if ($is_active): ?>
                        <i class="fas fa-exclamation-triangle"></i>
                    <?php else: ?>
                        <i class="fas fa-tools"></i>
                    <?php endif; ?>
                </div>
                <div class="mobile-notification-content">
                    <p>
                        <?php echo htmlspecialchars($maintenance['message']); ?>
                        <?php if ($is_active): ?>
                            <span class="countdown-timer">(Now)</span>
                        <?php endif; ?>
                    </p>
                    <div class="mobile-notification-time">
                        <?php echo $countdown_text; ?>
                    </div>
                    <span class="impact-badge"><?php echo $maintenance['impact']; ?> impact</span>
                </div>
            </div>
        <?php 
            endforeach; 
        endif; 
        ?>
        
        <?php if ($notifications): ?>
            <?php foreach ($notifications as $notification): ?>
                <div class="mobile-notification-item">
                    <div class="mobile-notification-icon">
                        <i class="<?php echo getNotificationIcon($notification['notification_type']); ?>"></i>
                    </div>
                    <div class="mobile-notification-content">
                        <p><?php echo htmlspecialchars($notification['message']); ?></p>
                        <div class="mobile-notification-time"><?php echo timeAgo($notification['created_at']); ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php elseif (!$maintenance_notifications): ?>
            <div class="mobile-notification-item">
                <div class="mobile-notification-content">
                    <p>No notifications</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Mobile User Dropdown -->
<div class="mobile-dropdown mobile-user-dropdown" id="mobileUserDropdown">
    <div class="mobile-user-header">
        <div class="mobile-user-avatar-large"><?php echo strtoupper(substr($user_name, 0, 1)); ?></div>
        <div class="mobile-user-details">
            <h4><?php echo htmlspecialchars($user_name); ?></h4>
            <p><?php echo htmlspecialchars($user_role); ?></p>
            <div class="user-school"><?php echo htmlspecialchars($school_name); ?></div>
        </div>
    </div>
    <div class="mobile-user-menu">
        <a href="profile.php" class="mobile-dropdown-item"><i class="fas fa-user"></i> My Profile</a>
        <a href="settings.php" class="mobile-dropdown-item"><i class="fas fa-cog"></i> Settings</a>
        <a href="help.php" class="mobile-dropdown-item"><i class="fas fa-question-circle"></i> Help</a>
        <div class="dropdown-divider"></div>
        <a href="logout.php" class="mobile-dropdown-item mobile-logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<!-- Mobile Overlay -->
<div class="mobile-overlay" id="mobileOverlay"></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // =============================
    // AJAX Academic Level Handler
    // =============================
    
    /**
     * Updates all page data based on selected academic level via AJAX
     * This function should be called whenever academic level changes
     * It will fetch new data and update the page without reloading
     */
function updateDataByAcademicLevel(level, displayName) {
    // Show loading overlay
    const loadingOverlay = document.getElementById('globalLoadingOverlay');
    if (loadingOverlay) {
        loadingOverlay.classList.add('active');
    }
    
    // Get the current page to know which data to update
    const currentPage = '<?php echo basename($_SERVER['PHP_SELF'], '.php'); ?>';
    
    // Build the request data
    const requestData = new URLSearchParams();
    requestData.append('academic_level', level);
    requestData.append('page', currentPage);
    
    // FIX: Use absolute path from root (start with /)
    fetch('/api/get_page_data_ajax.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: requestData.toString()
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Update the page content based on current page
            updatePageContent(data, currentPage);
            
            // Update academic level in session (background)
            updateSessionAcademicLevel(level);
            
            // Show success message
            showToast('Academic Level Updated', `Switched to ${displayName} view`, 'success');
        } else {
            showToast('Error', data.message || 'Failed to load page data', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error', 'Network error while updating page', 'error');
    })
    .finally(() => {
        // Hide loading overlay
        if (loadingOverlay) {
            loadingOverlay.classList.remove('active');
        }
    });
}
    
    /**
     * Updates the page content based on the current page and received data
     */
    function updatePageContent(data, currentPage) {
        // Update data attributes on page elements for reference
        document.body.setAttribute('data-academic-level', data.academic_level);
        
        // Page-specific updates
        switch(currentPage) {
            case 'dashboard':
                updateDashboardContent(data);
                break;
            case 'students':
                updateStudentsContent(data);
                break;
            case 'classes':
                updateClassesContent(data);
                break;
            case 'subjects':
                updateSubjectsContent(data);
                break;
            case 'teachers':
                updateTeachersContent(data);
                break;
            case 'scores':
                updateScoresContent(data);
                break;
            case 'attendance':
                updateAttendanceContent(data);
                break;
            default:
                // For other pages, trigger a page-specific refresh event
                const refreshEvent = new CustomEvent('academicLevelChanged', {
                    detail: { academic_level: data.academic_level, data: data }
                });
                window.dispatchEvent(refreshEvent);
                break;
        }
        
        // Update any dynamic elements that should reflect academic level
        updateDynamicElements(data);
    }
    
    /**
     * Updates dashboard-specific content
     */
    function updateDashboardContent(data) {
        // Update KPI cards if they exist
        if (data.stats) {
            // Update student count
            const studentCard = document.querySelector('.kpi-card.student h3, .stat-card.students .stat-value');
            if (studentCard) studentCard.textContent = data.stats.total_students?.toLocaleString() || '0';
            
            // Update teacher count
            const teacherCard = document.querySelector('.kpi-card.teacher h3, .stat-card.teachers .stat-value');
            if (teacherCard) teacherCard.textContent = data.stats.total_teachers?.toLocaleString() || '0';
            
            // Update class count
            const classCard = document.querySelector('.kpi-card.class h3, .stat-card.classes .stat-value');
            if (classCard) classCard.textContent = data.stats.total_classes?.toLocaleString() || '0';
            
            // Update subject count
            const subjectCard = document.querySelector('.kpi-card.subject h3, .stat-card.subjects .stat-value');
            if (subjectCard) subjectCard.textContent = data.stats.total_subjects?.toLocaleString() || '0';
        }
        
        // Update charts if they exist
        if (data.performance_data && typeof updatePerformanceChart === 'function') {
            updatePerformanceChart(data.performance_data);
        }
        
        // Update student list if it exists
        if (data.recent_students && typeof updateStudentList === 'function') {
            updateStudentList(data.recent_students);
        }
        
        // Dispatch event for any dashboard-specific handlers
        const dashboardEvent = new CustomEvent('dashboardDataUpdated', {
            detail: { data: data }
        });
        window.dispatchEvent(dashboardEvent);
    }
    
    /**
     * Updates students page content
     */
    function updateStudentsContent(data) {
        if (data.students) {
            // Update student table if it exists
            const studentTable = document.querySelector('#students-table tbody');
            if (studentTable) {
                studentTable.innerHTML = renderStudentRows(data.students);
            }
            
            // Update student count
            const studentCountEl = document.querySelector('.students-count, .total-students');
            if (studentCountEl && data.stats) {
                studentCountEl.textContent = data.stats.total_students?.toLocaleString() || '0';
            }
        }
        
        // Dispatch event for student page handlers
        const studentsEvent = new CustomEvent('studentsDataUpdated', {
            detail: { students: data.students, stats: data.stats }
        });
        window.dispatchEvent(studentsEvent);
    }
    
    /**
     * Updates classes page content
     */
    function updateClassesContent(data) {
        if (data.classes) {
            const classTable = document.querySelector('#classes-table tbody');
            if (classTable) {
                classTable.innerHTML = renderClassRows(data.classes);
            }
            
            // Update class count
            const classCountEl = document.querySelector('.classes-count, .total-classes');
            if (classCountEl && data.stats) {
                classCountEl.textContent = data.stats.total_classes?.toLocaleString() || '0';
            }
        }
        
        const classesEvent = new CustomEvent('classesDataUpdated', {
            detail: { classes: data.classes, stats: data.stats }
        });
        window.dispatchEvent(classesEvent);
    }
    
    /**
     * Updates subjects page content
     */
    function updateSubjectsContent(data) {
        if (data.subjects) {
            const subjectTable = document.querySelector('#subjects-table tbody');
            if (subjectTable) {
                subjectTable.innerHTML = renderSubjectRows(data.subjects);
            }
            
            // Update subject count
            const subjectCountEl = document.querySelector('.subjects-count, .total-subjects');
            if (subjectCountEl && data.stats) {
                subjectCountEl.textContent = data.stats.total_subjects?.toLocaleString() || '0';
            }
        }
        
        const subjectsEvent = new CustomEvent('subjectsDataUpdated', {
            detail: { subjects: data.subjects, stats: data.stats }
        });
        window.dispatchEvent(subjectsEvent);
    }
    
    /**
     * Updates teachers page content
     */
    function updateTeachersContent(data) {
        if (data.teachers) {
            const teacherTable = document.querySelector('#teachers-table tbody');
            if (teacherTable) {
                teacherTable.innerHTML = renderTeacherRows(data.teachers);
            }
            
            // Update teacher count
            const teacherCountEl = document.querySelector('.teachers-count, .total-teachers');
            if (teacherCountEl && data.stats) {
                teacherCountEl.textContent = data.stats.total_teachers?.toLocaleString() || '0';
            }
        }
        
        const teachersEvent = new CustomEvent('teachersDataUpdated', {
            detail: { teachers: data.teachers, stats: data.stats }
        });
        window.dispatchEvent(teachersEvent);
    }
    
    /**
     * Updates scores page content
     */
    function updateScoresContent(data) {
        if (data.scores) {
            const scoresTable = document.querySelector('#scores-table tbody');
            if (scoresTable) {
                scoresTable.innerHTML = renderScoreRows(data.scores);
            }
        }
        
        const scoresEvent = new CustomEvent('scoresDataUpdated', {
            detail: { scores: data.scores, stats: data.stats }
        });
        window.dispatchEvent(scoresEvent);
    }
    
    /**
     * Updates attendance page content
     */
    function updateAttendanceContent(data) {
        if (data.attendance) {
            const attendanceTable = document.querySelector('#attendance-table tbody');
            if (attendanceTable) {
                attendanceTable.innerHTML = renderAttendanceRows(data.attendance);
            }
        }
        
        const attendanceEvent = new CustomEvent('attendanceDataUpdated', {
            detail: { attendance: data.attendance, stats: data.stats }
        });
        window.dispatchEvent(attendanceEvent);
    }
    
    /**
     * Updates dynamic elements like select dropdowns and filters
     */
    function updateDynamicElements(data) {
        // Update class select dropdowns
        if (data.classes && data.classes.length) {
            document.querySelectorAll('select[name="class_id"], .class-select').forEach(select => {
                const currentValue = select.value;
                select.innerHTML = '<option value="">Select Class</option>' +
                    data.classes.map(c => `<option value="${c.id}" ${currentValue == c.id ? 'selected' : ''}>${c.name}</option>`).join('');
            });
        }
        
        // Update subject select dropdowns
        if (data.subjects && data.subjects.length) {
            document.querySelectorAll('select[name="subject_id"], .subject-select').forEach(select => {
                const currentValue = select.value;
                select.innerHTML = '<option value="">Select Subject</option>' +
                    data.subjects.map(s => `<option value="${s.id}" ${currentValue == s.id ? 'selected' : ''}>${s.name}</option>`).join('');
            });
        }
        
        // Update stream select dropdowns
        if (data.streams && data.streams.length) {
            document.querySelectorAll('select[name="stream_id"], .stream-select').forEach(select => {
                const currentValue = select.value;
                select.innerHTML = '<option value="">Select Stream</option>' +
                    data.streams.map(s => `<option value="${s.id}" ${currentValue == s.id ? 'selected' : ''}>${s.name}</option>`).join('');
            });
        }
        
        // Trigger custom event for other elements
        const dynamicEvent = new CustomEvent('dynamicElementsUpdated', {
            detail: { classes: data.classes, subjects: data.subjects, streams: data.streams }
        });
        window.dispatchEvent(dynamicEvent);
    }

/**
 * Updates academic level in session via AJAX
 */
function updateSessionAcademicLevel(level) {
    // FIX: Use absolute path from root (start with /)
    fetch('/api/update_academic_level_ajax.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'academic_level=' + encodeURIComponent(level)
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            console.log('Academic level updated in session');
        } else {
            console.error('Failed to update academic level:', data.message);
        }
    })
    .catch(error => console.error('Error updating session:', error));
}
    /**
     * Updates UI to show selected academic level
     */
    function updateAcademicLevelUI(level, displayName) {
        // Update desktop academic level text
        document.querySelectorAll('.center-level-text').forEach(el => {
            el.textContent = displayName;
        });
        
        // Update current level display in dropdowns
        document.querySelectorAll('.current-level, .mobile-current-level').forEach(el => {
            el.textContent = `Current: ${displayName}`;
        });
        
        // Update active state in level options
        document.querySelectorAll('.level-option, .mobile-level-option').forEach(opt => {
            opt.classList.remove('active');
            if (opt.getAttribute('data-level') === level) {
                opt.classList.add('active');
            }
        });
    }
    
    /**
     * Shows toast notification
     */
    function showToast(title, message, type = 'info') {
        // Check if toast container exists, create if not
        let toastContainer = document.getElementById('toast-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'toast-container';
            toastContainer.style.cssText = `
                position: fixed;
                top: 80px;
                right: 20px;
                z-index: 10001;
                display: flex;
                flex-direction: column;
                gap: 10px;
            `;
            document.body.appendChild(toastContainer);
        }
        
        const toast = document.createElement('div');
        const bgColors = {
            success: '#10b981',
            error: '#ef4444',
            warning: '#f59e0b',
            info: '#3b82f6'
        };
        
        toast.style.cssText = `
            background: ${bgColors[type] || '#3b82f6'};
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            min-width: 250px;
            max-width: 350px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            animation: slideInRight 0.3s ease;
            font-size: 14px;
        `;
        
        toast.innerHTML = `
            <strong>${title}</strong><br>
            <span>${message}</span>
        `;
        
        toastContainer.appendChild(toast);
        
        // Remove after 3 seconds
        setTimeout(() => {
            toast.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => {
                if (toastContainer.contains(toast)) {
                    toastContainer.removeChild(toast);
                }
            }, 300);
        }, 3000);
    }
    
    // Add animation styles
    const style = document.createElement('style');
    style.textContent = `
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
        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);
    
    // Helper function to render student rows
    function renderStudentRows(students) {
        if (!students || students.length === 0) {
            return '<tr><td colspan="5" class="text-center">No students found</td></tr>';
        }
        return students.map(student => `
            <tr>
                <td>${escapeHtml(student.admission_no || '')}</td>
                <td>${escapeHtml(student.first_name)} ${escapeHtml(student.last_name)}</td>
                <td>${escapeHtml(student.class_name || '')}</td>
                <td>${escapeHtml(student.stream_name || '')}</td>
                <td>${student.gender === 'Male' ? '<span class="badge male">Male</span>' : '<span class="badge female">Female</span>'}</td>
            </tr>
        `).join('');
    }
    
    // Helper function to render class rows
    function renderClassRows(classes) {
        if (!classes || classes.length === 0) {
            return '<tr><td colspan="4" class="text-center">No classes found</td></tr>';
        }
        return classes.map(cls => `
            <tr>
                <td>${escapeHtml(cls.name)}</td>
                <td>${escapeHtml(cls.stream_name || 'N/A')}</td>
                <td>${cls.student_count || 0}</td>
                <td>${cls.class_teacher_name || 'Not Assigned'}</td>
            </tr>
        `).join('');
    }
    
    // Helper function to render subject rows
    function renderSubjectRows(subjects) {
        if (!subjects || subjects.length === 0) {
            return '<tr><td colspan="4" class="text-center">No subjects found</td></tr>';
        }
        return subjects.map(subject => `
            <tr>
                <td>${escapeHtml(subject.code || '')}</td>
                <td>${escapeHtml(subject.name)}</td>
                <td>${escapeHtml(subject.class_name || 'All')}</td>
                <td>${escapeHtml(subject.teacher_name || 'Not Assigned')}</td>
            </tr>
        `).join('');
    }
    
    // Helper function to render teacher rows
    function renderTeacherRows(teachers) {
        if (!teachers || teachers.length === 0) {
            return '<tr><td colspan="5" class="text-center">No teachers found</td></tr>';
        }
        return teachers.map(teacher => `
            <tr>
                <td>${escapeHtml(teacher.employee_id || '')}</td>
                <td>${escapeHtml(teacher.first_name)} ${escapeHtml(teacher.last_name)}</td>
                <td>${escapeHtml(teacher.subject_name || 'N/A')}</td>
                <td>${escapeHtml(teacher.class_name || 'N/A')}</td>
                <td>${teacher.gender === 'Male' ? '<span class="badge male">Male</span>' : '<span class="badge female">Female</span>'}</td>
            </tr>
        `).join('');
    }
    
    // Helper function to render score rows
    function renderScoreRows(scores) {
        if (!scores || scores.length === 0) {
            return '<tr><td colspan="5" class="text-center">No scores found</td></tr>';
        }
        return scores.map(score => `
            <tr>
                <td>${escapeHtml(score.student_name)}</td>
                <td>${escapeHtml(score.admission_no)}</td>
                <td>${escapeHtml(score.subject_name)}</td>
                <td>${score.score_value}%</td>
                <td>${escapeHtml(score.grade || '-')}</td>
            </tr>
        `).join('');
    }
    
    // Helper function to render attendance rows
    function renderAttendanceRows(attendance) {
        if (!attendance || attendance.length === 0) {
            return '<tr><td colspan="5" class="text-center">No attendance records found</td></tr>';
        }
        return attendance.map(record => `
            <tr>
                <td>${escapeHtml(record.student_name)}</td>
                <td>${escapeHtml(record.admission_no)}</td>
                <td>${escapeHtml(record.date)}</td>
                <td>
                    <span class="badge ${record.status === 'present' ? 'present' : (record.status === 'absent' ? 'absent' : 'late')}">
                        ${record.status.charAt(0).toUpperCase() + record.status.slice(1)}
                    </span>
                </td>
                <td>${escapeHtml(record.remarks || '-')}</td>
            </tr>
        `).join('');
    }
    
    // Helper function to escape HTML
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // =============================
    // Academic Level Click Handlers
    // =============================
    
    // Handle desktop level option clicks
    document.querySelectorAll('.level-option').forEach(option => {
        option.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const level = this.getAttribute('data-level');
            const displayName = this.getAttribute('data-display') || this.querySelector('span')?.textContent || level;
            
            if (level) {
                // Update UI immediately for better UX
                updateAcademicLevelUI(level, displayName);
                
                // Close desktop dropdown
                const centerDropdown = document.getElementById('centerAcademicLevelDropdown');
                if (centerDropdown) centerDropdown.classList.remove('show');
                
                // Fetch and update page data via AJAX
                updateDataByAcademicLevel(level, displayName);
            }
        });
    });
    
    // Handle mobile level option clicks
    document.querySelectorAll('.mobile-level-option').forEach(option => {
        option.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const level = this.getAttribute('data-level');
            const displayName = this.getAttribute('data-display') || this.querySelector('span')?.textContent || level;
            
            if (level) {
                // Update UI immediately
                updateAcademicLevelUI(level, displayName);
                
                // Close mobile dropdown
                const mobileDropdown = document.getElementById('mobileAcademicDropdown');
                if (mobileDropdown) mobileDropdown.classList.remove('active');
                
                // Close overlay
                const mobileOverlay = document.getElementById('mobileOverlay');
                if (mobileOverlay) mobileOverlay.classList.remove('active');
                
                // Fetch and update page data via AJAX
                updateDataByAcademicLevel(level, displayName);
            }
        });
    });
    
    // =============================
    // Desktop Dropdown Toggle
    // =============================
    const centerAcademicLevelWrapper = document.getElementById('centerAcademicLevelWrapper');
    const centerAcademicLevelBtn = document.getElementById('centerAcademicLevelBtn');
    const centerAcademicLevelDropdown = document.getElementById('centerAcademicLevelDropdown');
    
    if (centerAcademicLevelBtn && centerAcademicLevelDropdown) {
        centerAcademicLevelBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            closeOtherDesktopDropdowns();
            centerAcademicLevelDropdown.classList.toggle('show');
        });
    }
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (centerAcademicLevelWrapper && !centerAcademicLevelWrapper.contains(e.target)) {
            centerAcademicLevelDropdown?.classList.remove('show');
        }
    });
    
    // =============================
    // Desktop Dropdown Functionality
    // =============================
    const desktopDropdowns = {
        notifications: {
            btn: document.getElementById('notificationBtn'),
            dropdown: document.getElementById('notificationsDropdown')
        },
        user: {
            btn: document.getElementById('userProfileBtn'),
            dropdown: document.getElementById('userDropdown')
        }
    };
    
    function closeOtherDesktopDropdowns() {
        Object.values(desktopDropdowns).forEach(({dropdown}) => {
            if (dropdown) {
                dropdown.style.opacity = '0';
                dropdown.style.visibility = 'hidden';
                dropdown.style.transform = 'translateY(-10px)';
            }
        });
    }
    
    Object.values(desktopDropdowns).forEach(({btn, dropdown}) => {
        if (btn && dropdown) {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                centerAcademicLevelDropdown?.classList.remove('show');
                
                const isVisible = dropdown.style.opacity === '1';
                closeOtherDesktopDropdowns();
                
                if (!isVisible) {
                    dropdown.style.opacity = '1';
                    dropdown.style.visibility = 'visible';
                    dropdown.style.transform = 'translateY(0)';
                }
            });
        }
    });
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth > 992) {
            let isClickInside = false;
            if (centerAcademicLevelWrapper?.contains(e.target)) isClickInside = true;
            Object.values(desktopDropdowns).forEach(({btn, dropdown}) => {
                if (btn?.contains(e.target)) isClickInside = true;
                if (dropdown?.contains(e.target)) isClickInside = true;
            });
            
            if (!isClickInside) {
                centerAcademicLevelDropdown?.classList.remove('show');
                Object.values(desktopDropdowns).forEach(({dropdown}) => {
                    if (dropdown) {
                        dropdown.style.opacity = '0';
                        dropdown.style.visibility = 'hidden';
                        dropdown.style.transform = 'translateY(-10px)';
                    }
                });
            }
        }
    });
    
    // =============================
    // Sidebar Toggle Functionality
    // =============================
    const menuToggle = document.getElementById('menuToggle');
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    
    function toggleSidebar() {
        const sidebar = document.querySelector('.sidebar');
        const sidebarOverlay = document.querySelector('.sidebar-overlay');
        
        if (!sidebar || !sidebarOverlay) return;
        
        const isMobile = window.innerWidth <= 992;
        
        if (isMobile) {
            const isShowing = sidebar.classList.contains('show');
            if (isShowing) {
                sidebar.classList.remove('show');
                sidebarOverlay.classList.remove('show');
                document.body.style.overflow = '';
            } else {
                sidebar.classList.add('show');
                sidebarOverlay.classList.add('show');
                document.body.style.overflow = 'hidden';
                closeAllMobileDropdowns();
            }
        } else {
            sidebar.classList.toggle('collapsed');
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
            // Dispatch event for any components that need to know sidebar state
            window.dispatchEvent(new CustomEvent('sidebarStateChanged'));
        }
    }
    
    if (menuToggle) menuToggle.addEventListener('click', toggleSidebar);
    if (mobileMenuToggle) mobileMenuToggle.addEventListener('click', toggleSidebar);
    
    // =============================
    // Mobile Dropdown Functionality
    // =============================
    const mobileDropdowns = {
        academic: {
            btn: document.getElementById('mobileAcademicToggle'),
            dropdown: document.getElementById('mobileAcademicDropdown')
        },
        notifications: {
            btn: document.getElementById('mobileNotificationBtn'),
            dropdown: document.getElementById('mobileNotificationsDropdown')
        },
        user: {
            btn: document.getElementById('mobileProfileBtn'),
            dropdown: document.getElementById('mobileUserDropdown')
        },
        search: {
            btn: document.getElementById('mobileSearchToggle'),
            container: document.getElementById('mobileSearchContainer')
        }
    };
    
    const mobileOverlay = document.getElementById('mobileOverlay');
    
    function closeAllMobileDropdowns() {
        Object.values(mobileDropdowns).forEach(({dropdown, container}) => {
            if (dropdown) dropdown.classList.remove('active');
            if (container) container.classList.remove('active');
        });
        if (mobileOverlay) mobileOverlay.classList.remove('active');
    }
    
    Object.entries(mobileDropdowns).forEach(([key, {btn, dropdown, container}]) => {
        if (btn) {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                const target = dropdown || container;
                if (!target) return;
                
                const isActive = target.classList.contains('active');
                closeAllMobileDropdowns();
                
                if (!isActive) {
                    target.classList.add('active');
                    if (mobileOverlay) mobileOverlay.classList.add('active');
                }
            });
        }
    });
    
    if (mobileOverlay) {
        mobileOverlay.addEventListener('click', closeAllMobileDropdowns);
    }
    
    // Mobile search close
    const mobileSearchClose = document.getElementById('mobileSearchClose');
    if (mobileSearchClose) {
        mobileSearchClose.addEventListener('click', closeAllMobileDropdowns);
    }
    
    // =============================
    // Search Functionality
    // =============================
    const searchInput = document.getElementById('globalSearchInput');
    const searchClear = document.getElementById('searchClear');
    
    if (searchInput && searchClear) {
        searchInput.addEventListener('input', function() {
            searchClear.style.opacity = this.value.length > 0 ? '1' : '0';
            searchClear.style.visibility = this.value.length > 0 ? 'visible' : 'hidden';
        });
        
        searchClear.addEventListener('click', function() {
            searchInput.value = '';
            searchInput.focus();
            this.style.opacity = '0';
            this.style.visibility = 'hidden';
        });
    }
    
    // =============================
    // Notification Mark as Read
    // =============================
    const markAllRead = document.getElementById('markAllRead');
    if (markAllRead) {
        markAllRead.addEventListener('click', function() {
            fetch('mark_notifications_read.php', { method: 'POST' })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.querySelectorAll('.notification-item.unread').forEach(item => {
                        item.classList.remove('unread');
                    });
                    document.querySelectorAll('.notification-count, .mobile-notification-count').forEach(badge => {
                        badge.style.display = 'none';
                    });
                }
            });
        });
    }
    
    // =============================
    // Maintenance Status Check
    // =============================
// =============================
// Maintenance Status Check
// =============================
setInterval(() => {
    // FIX: Use absolute path from root (start with /)
    fetch('/api/maintenance_api.php?action=get_maintenance_status')
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.active) {
                const notificationBtn = document.getElementById('notificationBtn');
                if (notificationBtn) {
                    notificationBtn.classList.add('maintenance-urgent');
                    const icon = notificationBtn.querySelector('i');
                    if (icon) icon.className = 'fas fa-exclamation-triangle';
                }
            }
        })
        .catch(error => console.error('Error checking maintenance:', error));
}, 300000); // 5 minutes
    
    // =============================
    // Window resize handler
    // =============================
    function handleResize() {
        if (window.innerWidth > 992) {
            // Desktop - ensure mobile dropdowns are closed
            closeAllMobileDropdowns();
            const sidebar = document.querySelector('.sidebar');
            if (sidebar && sidebar.classList.contains('show')) {
                sidebar.classList.remove('show');
                const sidebarOverlay = document.querySelector('.sidebar-overlay');
                if (sidebarOverlay) sidebarOverlay.classList.remove('show');
                document.body.style.overflow = '';
            }
        }
    }
    
    window.addEventListener('resize', handleResize);
    
    // Make functions available globally for other scripts
    window.updateDataByAcademicLevel = updateDataByAcademicLevel;
    window.showToast = showToast;
    window.toggleSidebar = toggleSidebar;
});
</script>