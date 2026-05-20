<?php
header('Content-Type: application/json');
require_once('../../includes/config.php');

$data = json_decode(file_get_contents('php://input'), true);

$school_id = $data['school_id'] ?? 0;
$item_id = $data['item_id'] ?? 0;

if (!$school_id || !$item_id) {
    echo json_encode(['success' => false, 'message' => 'Item ID required']);
    exit;
}

try {
    // Check if item exists
    $stmt = $db->prepare("SELECT id, item_name FROM store_items WHERE id = ? AND school_id = ?");
    $stmt->execute([$item_id, $school_id]);
    $item = $stmt->fetch();
    
    if (!$item) {
        echo json_encode(['success' => false, 'message' => 'Item not found']);
        exit;
    }
    
    // Soft delete - set status to inactive
    $stmt = $db->prepare("UPDATE store_items SET status = 'inactive' WHERE id = ? AND school_id = ?");
    $stmt->execute([$item_id, $school_id]);
    
    echo json_encode(['success' => true, 'message' => 'Item deleted successfully']);
} catch (PDOException $e) {
    error_log("Error in delete_item: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>