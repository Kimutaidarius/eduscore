<?php
session_start();
require_once 'db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$notification_id = $_POST['notification_id'] ?? null;
$user_id = $_SESSION['user_id'] ?? null;

if (!$notification_id || !$user_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

try {
    $sql = "UPDATE tblnotifications SET is_read = 1 
            WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $notification_id, $user_id);
    $success = $stmt->execute();
    
    echo json_encode(['success' => $success]);
} catch (Exception $e) {
    error_log("Mark notification read error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>