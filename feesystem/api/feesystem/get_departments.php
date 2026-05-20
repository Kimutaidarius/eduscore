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
    $school_id = $input['school_id'] ?? $_SESSION['school_id'] ?? null;
    
    if (!$school_id) {
        echo json_encode(['success' => false, 'message' => 'School ID is required']);
        exit;
    }
    
    $stmt = $db->prepare("SELECT id, name, description FROM departments WHERE school_id = ? ORDER BY name ASC");
    $stmt->execute([$school_id]);
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'departments' => $departments]);
} catch (PDOException $e) {
    error_log("Database error in get_departments.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("General error in get_departments.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>