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

$user_id = $_SESSION['user_id'];

try {
    $stmt = $db->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log("Error in mark_all_notifications_read.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>