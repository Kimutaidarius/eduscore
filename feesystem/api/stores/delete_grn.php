<?php
header('Content-Type: application/json');
require_once('../../includes/config.php');

$data = json_decode(file_get_contents('php://input'), true);

$school_id = $data['school_id'] ?? 0;
$grn_id = $data['grn_id'] ?? 0;
$user_id = $data['user_id'] ?? 0;

if (!$school_id || !$grn_id) {
    echo json_encode(['success' => false, 'message' => 'GRN ID required']);
    exit;
}

try {
    $db->beginTransaction();
    
    // Get GRN items to reverse stock
    $stmt = $db->prepare("SELECT gi.*, si.current_stock 
                          FROM grn_items gi
                          JOIN store_items si ON gi.item_id = si.id
                          WHERE gi.grn_id = ?");
    $stmt->execute([$grn_id]);
    $grn_items = $stmt->fetchAll();
    
    // Reverse stock for each item
    foreach ($grn_items as $item) {
        $new_stock = $item['current_stock'] - $item['received_quantity'];
        
        $stmt = $db->prepare("UPDATE store_items SET current_stock = current_stock - ? WHERE id = ?");
        $stmt->execute([$item['received_quantity'], $item['item_id']]);
        
        // Log reversal
        $stmt = $db->prepare("INSERT INTO stock_movements 
                              (school_id, item_id, movement_type, reference_id, reference_type, 
                               quantity, previous_stock, new_stock, unit_price, created_by, notes) 
                              VALUES (?, ?, 'adjustment', ?, 'grn_deletion', ?, ?, ?, ?, ?, 'GRN deleted')");
        $stmt->execute([$school_id, $item['item_id'], $grn_id, -$item['received_quantity'], 
                        $item['current_stock'], $new_stock, $item['unit_price'], $user_id]);
    }
    
    // Delete GRN (cascades to grn_items)
    $stmt = $db->prepare("DELETE FROM grns WHERE id = ? AND school_id = ?");
    $stmt->execute([$grn_id, $school_id]);
    
    $db->commit();
    
    echo json_encode(['success' => true, 'message' => 'GRN deleted successfully']);
} catch (PDOException $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log("Error in delete_grn: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>