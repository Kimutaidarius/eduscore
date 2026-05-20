<?php
session_start();
require_once 'db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
$school_id = $_SESSION['school_id'] ?? null;

if (!$user_id || !$school_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

try {
    $sql = "UPDATE tblnotifications SET is_read = 1 
            WHERE school_id = ? AND user_id = ? AND is_read = 0";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $school_id, $user_id);
    $success = $stmt->execute();
    
    echo json_encode(['success' => $success]);
} catch (Exception $e) {
    error_log("Mark all notifications read error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>