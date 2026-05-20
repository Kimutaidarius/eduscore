<?php
session_start();
require_once('../../includes/config.php');
header('Content-Type: application/json');

// Check authentication
if (empty($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $id = $input['id'] ?? 0;
    $school_id = $input['school_id'] ?? $_SESSION['school_id'] ?? null;
    
    if (!$id || !$school_id) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }
    
    $stmt = $db->prepare("DELETE FROM staff WHERE id = ? AND school_id = ?");
    $stmt->execute([$id, $school_id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Staff member deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Staff member not found']);
    }
} catch (PDOException $e) {
    error_log("Database error in delete_staff.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("General error in delete_staff.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>