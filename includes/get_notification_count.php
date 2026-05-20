<?php
session_start();
require_once 'db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    echo json_encode(['count' => 0]);
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
$school_id = $_SESSION['school_id'] ?? null;

if (!$user_id || !$school_id) {
    echo json_encode(['count' => 0]);
    exit;
}

try {
    // Count unread notifications
    $sql = "SELECT COUNT(*) as count FROM tblnotifications 
            WHERE school_id = ? AND user_id = ? AND is_read = 0";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $school_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    echo json_encode(['count' => (int)$row['count']]);
} catch (Exception $e) {
    error_log("Notification count error: " . $e->getMessage());
    echo json_encode(['count' => 0]);
}
?>