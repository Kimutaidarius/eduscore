<?php
session_start();
header('Content-Type: application/json');
require_once('../../includes/config.php');

if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    sendResponse(null, 'error', 'Unauthorized access');
}

$school_id = $_SESSION['school_id'] ?? 0;

try {
    $stmt = $db->prepare("SELECT id, name, description FROM departments WHERE school_id = ? ORDER BY name");
    $stmt->execute([$school_id]);
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    sendResponse(['departments' => $departments], 'success');
} catch (PDOException $e) {
    error_log("Error in get_departments.php: " . $e->getMessage());
    sendResponse(null, 'error', 'Database error occurred');
}
?>