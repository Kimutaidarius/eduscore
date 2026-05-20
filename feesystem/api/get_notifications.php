<?php
session_start();
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Check authentication
if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check if user_id exists in session
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User ID not found in session']);
    exit;
}

require_once '../includes/config.php';

$user_id = $_SESSION['user_id'];

try {
    // Check if notifications table exists - create if it doesn't
    $table_check = $db->query("SHOW TABLES LIKE 'notifications'");
    if ($table_check->rowCount() == 0) {
        // Create the notifications table
        $create_table = "
        CREATE TABLE IF NOT EXISTS `notifications` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `title` varchar(255) NOT NULL,
            `message` text NOT NULL,
            `type` varchar(50) DEFAULT 'info',
            `icon` varchar(50) DEFAULT NULL,
            `is_read` tinyint(1) DEFAULT 0,
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            `read_at` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_user_read` (`user_id`, `is_read`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        $db->exec($create_table);
        
        // Insert sample welcome notification for the user
        $welcome = $db->prepare("
            INSERT INTO notifications (user_id, title, message, type, icon, created_at) 
            VALUES (?, 'Welcome to EduScore', 'Welcome to the Fee Management System! You can now manage student fees, generate invoices, and track payments.', 'welcome', 'fa-graduation-cap', NOW())
        ");
        $welcome->execute([$user_id]);
    }
    
    // Get notifications for the user
    $stmt = $db->prepare("
        SELECT 
            id, 
            title, 
            message, 
            type, 
            icon, 
            is_read, 
            created_at,
            CASE 
                WHEN DATE(created_at) = CURDATE() THEN TIME_FORMAT(created_at, '%h:%i %p')
                WHEN DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) THEN 'Yesterday'
                ELSE DATE_FORMAT(created_at, '%b %d, %Y')
            END as time_ago
        FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 20
    ");
    
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate unread count
    $unread_count = 0;
    foreach ($notifications as $notif) {
        if (!$notif['is_read']) {
            $unread_count++;
        }
    }
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'unread_count' => $unread_count,
        'count' => $unread_count
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in get_notifications.php: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Database error occurred'
    ]);
} catch (Exception $e) {
    error_log("General error in get_notifications.php: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred'
    ]);
}
?>