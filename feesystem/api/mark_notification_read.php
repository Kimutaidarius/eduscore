<?php
session_start();
header('Content-Type: application/json');

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../includes/config.php';

$notification_id = isset($_POST['notification_id']) ? (int)$_POST['notification_id'] : 0;
$user_id = $_SESSION['user_id'];

if ($notification_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
    exit;
}

try {
    $stmt = $db->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?");
    $stmt->execute([$notification_id, $user_id]);
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log("Error in mark_notification_read.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>