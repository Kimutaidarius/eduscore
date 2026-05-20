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
    
    // Validate input
    if (empty($input['id']) || empty($input['name']) || empty($input['alias']) || empty($input['type'])) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }
    
    // Use 'vote_heads' instead of 'tbl_vote_heads'
    $stmt = $db->prepare("
        UPDATE vote_heads 
        SET name = :name, 
            alias = :alias, 
            type = :type, 
            priority = :priority, 
            applies_to = :applies_to, 
            status = :status, 
            description = :description,
            updated_at = NOW()
        WHERE id = :id AND school_id = :school_id
    ");
    
    $stmt->execute([
        ':name' => $input['name'],
        ':alias' => strtoupper($input['alias']),
        ':type' => $input['type'],
        ':priority' => $input['priority'],
        ':applies_to' => $input['applies_to'],
        ':status' => $input['status'],
        ':description' => $input['description'] ?? '',
        ':id' => $input['id'],
        ':school_id' => $input['school_id']
    ]);
    
    echo json_encode(['success' => true, 'message' => 'Vote head updated successfully']);
} catch (PDOException $e) {
    error_log("Database error in update_vote_head.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("General error in update_vote_head.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>