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
    
    if (empty($input['id']) || empty($input['school_id'])) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }
    
    // Use 'vote_heads' instead of 'tbl_vote_heads'
    $stmt = $db->prepare("DELETE FROM vote_heads WHERE id = ? AND school_id = ?");
    $stmt->execute([$input['id'], $input['school_id']]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Vote head deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Vote head not found']);
    }
} catch (PDOException $e) {
    error_log("Database error in delete_vote_head.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("General error in delete_vote_head.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>