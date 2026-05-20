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
    $name = trim($input['name'] ?? '');
    
    if (!$school_id || empty($name)) {
        echo json_encode(['success' => false, 'message' => 'School ID and bank name are required']);
        exit;
    }
    
    // Check if bank already exists
    $checkStmt = $db->prepare("SELECT id FROM banks WHERE name = ? AND school_id = ?");
    $checkStmt->execute([$name, $school_id]);
    if ($checkStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Bank already exists']);
        exit;
    }
    
    $stmt = $db->prepare("INSERT INTO banks (school_id, name, created_at) VALUES (?, ?, NOW())");
    $stmt->execute([$school_id, $name]);
    
    echo json_encode(['success' => true, 'message' => 'Bank added successfully', 'id' => $db->lastInsertId()]);
} catch (PDOException $e) {
    error_log("Database error in save_bank.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("General error in save_bank.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>