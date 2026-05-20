<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once('../../includes/config.php');
session_start();

$data = json_decode(file_get_contents('php://input'), true);
$school_id = $data['school_id'] ?? $_SESSION['school_id'] ?? 0;

if (!$school_id) {
    echo json_encode(['success' => false, 'message' => 'School ID required']);
    exit;
}

try {
    // Check if subscriptions table exists
    $stmt = $db->query("SHOW TABLES LIKE 'subscriptions'");
    if ($stmt->rowCount() == 0) {
        echo json_encode(['success' => true, 'message' => 'No active subscription found']);
        exit;
    }
    
    // Update subscription to cancelled
    $stmt = $db->prepare("
        UPDATE subscriptions 
        SET status = 'cancelled', auto_renew = 0 
        WHERE school_id = ? AND status = 'active'
    ");
    $stmt->execute([$school_id]);
    
    echo json_encode(['success' => true, 'message' => 'Subscription cancelled successfully']);
    
} catch (PDOException $e) {
    error_log("Error in cancel_subscription: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>