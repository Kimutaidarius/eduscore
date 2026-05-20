<?php
// ajax/reports_handler.php
session_start();
header('Content-Type: application/json');

// Include database configuration
require_once '../config/config.php';

// Function to send JSON response
function sendJsonResponse($status, $message, $data = []) {
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data
    ]);
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    sendJsonResponse('error', 'Please login to continue');
}

$user_id = $_SESSION['user_id'];

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse('error', 'Invalid request method');
}

// CSRF check
$csrf_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
if (!isset($_SESSION['csrf_token']) || $csrf_token !== $_SESSION['csrf_token']) {
    sendJsonResponse('error', 'Invalid security token');
}

$action = isset($_POST['action']) ? $_POST['action'] : '';

try {
    // Test database connection first
    $pdo->query("SELECT 1");
    
    switch ($action) {
        case 'get_summary':
            $date_from = isset($_POST['date_from']) ? $_POST['date_from'] : date('Y-m-01');
            $date_to = isset($_POST['date_to']) ? $_POST['date_to'] : date('Y-m-d');
            
            // Get summary statistics
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_messages,
                    COALESCE(SUM(sms_count), 0) as total_sms,
                    COALESCE(SUM(cost), 0) as total_cost,
                    COUNT(DISTINCT DATE(created_at)) as active_days,
                    COUNT(DISTINCT recipient) as unique_recipients
                FROM sms_messages 
                WHERE user_id = ? AND DATE(created_at) BETWEEN ? AND ?
            ");
            $stmt->execute([$user_id, $date_from, $date_to]);
            $summary = $stmt->fetch();
            
            sendJsonResponse('success', 'Summary retrieved', ['summary' => $summary]);
            break;
            
        case 'get_status_breakdown':
            $date_from = isset($_POST['date_from']) ? $_POST['date_from'] : date('Y-m-01');
            $date_to = isset($_POST['date_to']) ? $_POST['date_to'] : date('Y-m-d');
            
            $stmt = $pdo->prepare("
                SELECT 
                    status, 
                    COUNT(*) as count,
                    COALESCE(SUM(sms_count), 0) as total_sms,
                    COALESCE(SUM(cost), 0) as total_cost
                FROM sms_messages 
                WHERE user_id = ? AND DATE(created_at) BETWEEN ? AND ?
                GROUP BY status
            ");
            $stmt->execute([$user_id, $date_from, $date_to]);
            $status_breakdown = $stmt->fetchAll();
            
            sendJsonResponse('success', 'Status breakdown retrieved', ['status_breakdown' => $status_breakdown]);
            break;
            
        case 'get_daily_stats':
            $date_from = isset($_POST['date_from']) ? $_POST['date_from'] : date('Y-m-01');
            $date_to = isset($_POST['date_to']) ? $_POST['date_to'] : date('Y-m-d');
            
            $stmt = $pdo->prepare("
                SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as messages,
                    COALESCE(SUM(sms_count), 0) as sms_count,
                    COALESCE(SUM(cost), 0) as cost
                FROM sms_messages 
                WHERE user_id = ? AND DATE(created_at) BETWEEN ? AND ?
                GROUP BY DATE(created_at)
                ORDER BY date
            ");
            $stmt->execute([$user_id, $date_from, $date_to]);
            $daily_stats = $stmt->fetchAll();
            
            sendJsonResponse('success', 'Daily stats retrieved', ['daily_stats' => $daily_stats]);
            break;
            
        case 'get_top_recipients':
            $date_from = isset($_POST['date_from']) ? $_POST['date_from'] : date('Y-m-01');
            $date_to = isset($_POST['date_to']) ? $_POST['date_to'] : date('Y-m-d');
            $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 10;
            
            $stmt = $pdo->prepare("
                SELECT 
                    recipient,
                    COUNT(*) as message_count,
                    COALESCE(SUM(sms_count), 0) as total_sms,
                    COALESCE(SUM(cost), 0) as total_cost
                FROM sms_messages 
                WHERE user_id = ? AND DATE(created_at) BETWEEN ? AND ?
                GROUP BY recipient
                ORDER BY total_sms DESC
                LIMIT ?
            ");
            $stmt->execute([$user_id, $date_from, $date_to, $limit]);
            $top_recipients = $stmt->fetchAll();
            
            sendJsonResponse('success', 'Top recipients retrieved', ['top_recipients' => $top_recipients]);
            break;
            
        case 'get_hourly_stats':
            $date_from = isset($_POST['date_from']) ? $_POST['date_from'] : date('Y-m-01');
            $date_to = isset($_POST['date_to']) ? $_POST['date_to'] : date('Y-m-d');
            
            $stmt = $pdo->prepare("
                SELECT 
                    HOUR(created_at) as hour,
                    COUNT(*) as count
                FROM sms_messages 
                WHERE user_id = ? AND DATE(created_at) BETWEEN ? AND ?
                GROUP BY HOUR(created_at)
                ORDER BY hour
            ");
            $stmt->execute([$user_id, $date_from, $date_to]);
            $hourly_stats = $stmt->fetchAll();
            
            // Fill missing hours with zero
            $full_hourly = array_fill(0, 24, 0);
            foreach ($hourly_stats as $stat) {
                $full_hourly[$stat['hour']] = (int)$stat['count'];
            }
            
            sendJsonResponse('success', 'Hourly stats retrieved', ['hourly_stats' => $full_hourly]);
            break;
            
        case 'get_all_reports':
            $date_from = isset($_POST['date_from']) ? $_POST['date_from'] : date('Y-m-01');
            $date_to = isset($_POST['date_to']) ? $_POST['date_to'] : date('Y-m-d');
            
            // Get summary
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_messages,
                    COALESCE(SUM(sms_count), 0) as total_sms,
                    COALESCE(SUM(cost), 0) as total_cost,
                    COUNT(DISTINCT DATE(created_at)) as active_days,
                    COUNT(DISTINCT recipient) as unique_recipients
                FROM sms_messages 
                WHERE user_id = ? AND DATE(created_at) BETWEEN ? AND ?
            ");
            $stmt->execute([$user_id, $date_from, $date_to]);
            $summary = $stmt->fetch();
            
            // Get status breakdown
            $stmt = $pdo->prepare("
                SELECT 
                    status, 
                    COUNT(*) as count,
                    COALESCE(SUM(sms_count), 0) as total_sms,
                    COALESCE(SUM(cost), 0) as total_cost
                FROM sms_messages 
                WHERE user_id = ? AND DATE(created_at) BETWEEN ? AND ?
                GROUP BY status
            ");
            $stmt->execute([$user_id, $date_from, $date_to]);
            $status_breakdown = $stmt->fetchAll();
            
            // Get daily stats
            $stmt = $pdo->prepare("
                SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as messages,
                    COALESCE(SUM(sms_count), 0) as sms_count,
                    COALESCE(SUM(cost), 0) as cost
                FROM sms_messages 
                WHERE user_id = ? AND DATE(created_at) BETWEEN ? AND ?
                GROUP BY DATE(created_at)
                ORDER BY date
            ");
            $stmt->execute([$user_id, $date_from, $date_to]);
            $daily_stats = $stmt->fetchAll();
            
            // Get top recipients
            $stmt = $pdo->prepare("
                SELECT 
                    recipient,
                    COUNT(*) as message_count,
                    COALESCE(SUM(sms_count), 0) as total_sms,
                    COALESCE(SUM(cost), 0) as total_cost
                FROM sms_messages 
                WHERE user_id = ? AND DATE(created_at) BETWEEN ? AND ?
                GROUP BY recipient
                ORDER BY total_sms DESC
                LIMIT 10
            ");
            $stmt->execute([$user_id, $date_from, $date_to]);
            $top_recipients = $stmt->fetchAll();
            
            // Get hourly stats
            $stmt = $pdo->prepare("
                SELECT 
                    HOUR(created_at) as hour,
                    COUNT(*) as count
                FROM sms_messages 
                WHERE user_id = ? AND DATE(created_at) BETWEEN ? AND ?
                GROUP BY HOUR(created_at)
                ORDER BY hour
            ");
            $stmt->execute([$user_id, $date_from, $date_to]);
            $hourly_stats = $stmt->fetchAll();
            
            $full_hourly = array_fill(0, 24, 0);
            foreach ($hourly_stats as $stat) {
                $full_hourly[$stat['hour']] = (int)$stat['count'];
            }
            
            sendJsonResponse('success', 'All reports retrieved', [
                'summary' => $summary,
                'status_breakdown' => $status_breakdown,
                'daily_stats' => $daily_stats,
                'top_recipients' => $top_recipients,
                'hourly_stats' => $full_hourly
            ]);
            break;
            
        default:
            sendJsonResponse('error', 'Invalid action');
    }
} catch (PDOException $e) {
    error_log("Reports handler error: " . $e->getMessage());
    sendJsonResponse('error', 'Database error occurred');
} catch (Exception $e) {
    error_log("Reports handler error: " . $e->getMessage());
    sendJsonResponse('error', $e->getMessage());
}
?>