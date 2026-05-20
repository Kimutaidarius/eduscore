<?php
// htdocs/api/maintenance_api.php

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/SystemNotificationHelper.php';
require_once __DIR__ . '/../includes/EmailHelper.php';

header('Content-Type: application/json');

// Check if user is logged in as superadmin
if (!isset($_SESSION['superadmin_loggedin']) || $_SESSION['superadmin_loggedin'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$notificationHelper = new SystemNotificationHelper($db);
$emailHelper = new EmailHelper();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'schedule_maintenance':
        scheduleMaintenance();
        break;
    case 'get_maintenance_status':
        getMaintenanceStatus();
        break;
    case 'get_upcoming_maintenance':
        getUpcomingMaintenance();
        break;
    case 'get_maintenance_history':
        getMaintenanceHistory();
        break;
    case 'cancel_maintenance':
        cancelMaintenance();
        break;
    case 'complete_maintenance':
        completeMaintenance();
        break;
    case 'send_test_notification':
        sendTestNotification();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function scheduleMaintenance() {
    global $notificationHelper, $emailHelper;
    
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $startDate = $_POST['start_date'] ?? '';
    $duration = intval($_POST['duration'] ?? 2);
    $impact = $_POST['impact'] ?? 'medium';
    $notifyUsers = isset($_POST['notify_users']) && $_POST['notify_users'] == '1';
    
    if (empty($title) || empty($startDate)) {
        echo json_encode(['success' => false, 'message' => 'Title and start date are required']);
        return;
    }
    
    // Calculate end date based on duration
    $endDate = date('Y-m-d H:i:s', strtotime($startDate . " + $duration hours"));
    
    // Map impact to priority
    $priority = $impact === 'high' ? 'urgent' : ($impact === 'medium' ? 'high' : 'medium');
    
    // Create maintenance notification
    $result = $notificationHelper->createMaintenanceNotification(
        $title,
        $description,
        $startDate,
        $endDate,
        $priority
    );
    
    if ($result) {
        // Send email notifications if requested
        if ($notifyUsers) {
            sendMaintenanceEmails($title, $description, $startDate, $endDate);
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'Maintenance scheduled successfully',
            'data' => [
                'title' => $title,
                'start_date' => $startDate,
                'end_date' => $endDate
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to schedule maintenance']);
    }
}

function sendMaintenanceEmails($title, $description, $startDate, $endDate) {
    global $db, $emailHelper;
    
    // Get all active teachers with email
    $sql = "SELECT t.id, t.email, t.firstname, t.lastname, s.school_name 
            FROM tblteachers t
            JOIN tblschoolinfo s ON t.school_id = s.id
            WHERE t.status = 'Active' AND t.email IS NOT NULL";
    
    $stmt = $db->query($sql);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $subject = "🔧 System Maintenance Scheduled";
    
    foreach ($users as $user) {
        $htmlMessage = getMaintenanceEmailHTML(
            $user['firstname'] . ' ' . $user['lastname'],
            $title,
            $description,
            $startDate,
            $endDate
        );
        
        try {
            $emailHelper->sendEmailSMTP($user['email'], $subject, $htmlMessage);
            logEmail($user['email'], 'maintenance', $subject, $htmlMessage, true, $user['id']);
        } catch (Exception $e) {
            logEmail($user['email'], 'maintenance', $subject, $htmlMessage, false, $user['id'], $e->getMessage());
        }
        
        // Small delay to avoid rate limiting
        usleep(100000);
    }
}

function getMaintenanceEmailHTML($name, $title, $description, $startDate, $endDate) {
    $start = date('F j, Y g:i A', strtotime($startDate));
    $end = date('F j, Y g:i A', strtotime($endDate));
    
    return '
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9; }
            .header { background: linear-gradient(90deg, #dc2626 0%, #ef4444 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: white; padding: 30px; border-radius: 0 0 10px 10px; }
            .maintenance-box { background: #fee2e2; border: 2px solid #ef4444; padding: 20px; border-radius: 8px; margin: 20px 0; }
            .footer { text-align: center; padding: 20px; color: #6b7280; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🔧 Scheduled Maintenance</h1>
            </div>
            <div class="content">
                <h2>Hello ' . htmlspecialchars($name) . ',</h2>
                
                <div class="maintenance-box">
                    <h3>' . htmlspecialchars($title) . '</h3>
                    <p>' . nl2br(htmlspecialchars($description)) . '</p>
                    <p><strong>Start:</strong> ' . $start . '</p>
                    <p><strong>End:</strong> ' . $end . '</p>
                </div>
                
                <p>During this time, some features may be temporarily unavailable. We apologize for any inconvenience.</p>
                
                <p style="text-align: center; margin-top: 20px;">
                    <a href="https://edu-score.app" style="color: #2563eb;">Visit EDUSCORE</a>
                </p>
            </div>
            <div class="footer">
                <p>&copy; ' . date('Y') . ' EDUSCORE. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ';
}

function logEmail($email, $type, $subject, $message, $status, $userId = null, $error = null) {
    global $db;
    
    try {
        $sql = "INSERT INTO email_notification_logs 
                (user_id, email, notification_type, subject, message, status, error_message, sent_at) 
                VALUES (:user_id, :email, :type, :subject, :message, :status, :error, NOW())";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':email' => $email,
            ':type' => $type,
            ':subject' => $subject,
            ':message' => $message,
            ':status' => $status ? 'sent' : 'failed',
            ':error' => $error
        ]);
    } catch (Exception $e) {
        error_log("Failed to log email: " . $e->getMessage());
    }
}

function getMaintenanceStatus() {
    global $notificationHelper;
    
    $active = $notificationHelper->isMaintenanceModeActive();
    
    if ($active) {
        echo json_encode([
            'success' => true,
            'active' => true,
            'maintenance' => $active
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'active' => false
        ]);
    }
}

function getUpcomingMaintenance() {
    global $notificationHelper;
    
    $upcoming = $notificationHelper->getUpcomingMaintenance();
    echo json_encode(['success' => true, 'upcoming' => $upcoming]);
}

function getMaintenanceHistory() {
    global $notificationHelper;
    
    $history = $notificationHelper->getMaintenanceHistory();
    echo json_encode(['success' => true, 'history' => $history]);
}

function cancelMaintenance() {
    global $notificationHelper;
    
    $id = $_POST['id'] ?? 0;
    
    // Deactivate the notification
    $sql = "UPDATE system_notifications SET is_active = 0 WHERE id = :id AND notification_type = 'maintenance'";
    $stmt = $notificationHelper->conn->prepare($sql);
    $result = $stmt->execute([':id' => $id]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Maintenance cancelled']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to cancel maintenance']);
    }
}

function completeMaintenance() {
    global $notificationHelper;
    
    $id = $_POST['id'] ?? 0;
    
    // Mark as completed
    $sql = "UPDATE system_notifications SET is_active = 0 WHERE id = :id";
    $stmt = $notificationHelper->conn->prepare($sql);
    $result = $stmt->execute([':id' => $id]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Maintenance marked as completed']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to complete maintenance']);
    }
}

function sendTestNotification() {
    global $notificationHelper;
    
    $email = $_POST['email'] ?? '';
    if (empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Email required']);
        return;
    }
    
    $subject = "🔧 Test Maintenance Notification";
    $message = getMaintenanceEmailHTML(
        'Test User',
        'Test Maintenance',
        'This is a test notification to verify email delivery.',
        date('Y-m-d H:i:s'),
        date('Y-m-d H:i:s', strtotime('+2 hours'))
    );
    
    try {
        $emailHelper = new EmailHelper();
        $result = $emailHelper->sendEmailSMTP($email, $subject, $message);
        logEmail($email, 'test', $subject, $message, $result);
        echo json_encode(['success' => true, 'message' => 'Test email sent']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to send test email: ' . $e->getMessage()]);
    }
}