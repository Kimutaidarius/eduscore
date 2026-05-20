<?php
header('Content-Type: application/json');
require_once('../../includes/config.php');

$data = json_decode(file_get_contents('php://input'), true);

$school_id = $data['school_id'] ?? 0;
$user_id = $data['user_id'] ?? 0;
$lpo_id = $data['lpo_id'] ?? 0;
$grn_date = $data['grn_date'] ?? date('Y-m-d');
$delivery_note = $data['delivery_note'] ?? '';
$received_by = $data['received_by'] ?? '';
$items = $data['items'] ?? [];
$notes = $data['notes'] ?? '';

if (!$school_id || !$lpo_id || empty($items)) {
    echo json_encode(['success' => false, 'message' => 'LPO and items are required']);
    exit;
}

try {
    // Get LPO details
    $stmt = $db->prepare("SELECT supplier_id FROM lpos WHERE id = ? AND school_id = ?");
    $stmt->execute([$lpo_id, $school_id]);
    $lpo = $stmt->fetch();
    if (!$lpo) {
        throw new Exception('LPO not found');
    }
    
    // Generate GRN number
    $year = date('Y');
    $month = date('m');
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM grns WHERE school_id = ? AND YEAR(created_at) = ?");
    $stmt->execute([$school_id, $year]);
    $count = $stmt->fetch()['count'] + 1;
    $grn_number = "GRN-{$year}{$month}-" . str_pad($count, 4, '0', STR_PAD_LEFT);
    
    $db->beginTransaction();
    
    // Insert GRN
    $stmt = $db->prepare("INSERT INTO grns 
                          (school_id, grn_number, lpo_id, supplier_id, grn_date, delivery_note_no, received_by, notes, created_by) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$school_id, $grn_number, $lpo_id, $lpo['supplier_id'], $grn_date, $delivery_note, $received_by, $notes, $user_id]);
    $grn_id = $db->lastInsertId();
    
    // Process each item
    foreach ($items as $item) {
        // Get LPO item details
        $stmt = $db->prepare("SELECT li.*, si.current_stock, si.item_name 
                              FROM lpo_items li
                              JOIN store_items si ON li.item_id = si.id
                              WHERE li.id = ? AND li.lpo_id = ?");
        $stmt->execute([$item['lpo_item_id'], $lpo_id]);
        $lpo_item = $stmt->fetch();
        
        if (!$lpo_item) continue;
        
        $received_qty = $item['received_quantity'];
        $old_stock = $lpo_item['current_stock'];
        $new_stock = $old_stock + $received_qty;
        
        // Insert GRN item
        $stmt = $db->prepare("INSERT INTO grn_items 
                              (grn_id, lpo_item_id, item_id, ordered_quantity, received_quantity, unit_price, total) 
                              VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$grn_id, $item['lpo_item_id'], $lpo_item['item_id'], 
                        $lpo_item['quantity'], $received_qty, $lpo_item['unit_price'], 
                        $received_qty * $lpo_item['unit_price']]);
        
        // Update LPO item received quantity
        $stmt = $db->prepare("UPDATE lpo_items SET received_quantity = received_quantity + ? WHERE id = ?");
        $stmt->execute([$received_qty, $item['lpo_item_id']]);
        
        // Update store item stock
        $stmt = $db->prepare("UPDATE store_items SET current_stock = current_stock + ? WHERE id = ?");
        $stmt->execute([$received_qty, $lpo_item['item_id']]);
        
        // Log stock movement
        $stmt = $db->prepare("INSERT INTO stock_movements 
                              (school_id, item_id, movement_type, reference_id, reference_type, 
                               quantity, previous_stock, new_stock, unit_price, created_by) 
                              VALUES (?, ?, 'purchase', ?, 'grn', ?, ?, ?, ?, ?)");
        $stmt->execute([$school_id, $lpo_item['item_id'], $grn_id, $received_qty, 
                        $old_stock, $new_stock, $lpo_item['unit_price'], $user_id]);
    }
    
    // Check if LPO is fully received
    $stmt = $db->prepare("SELECT SUM(quantity) as total_qty, SUM(received_quantity) as total_received 
                          FROM lpo_items WHERE lpo_id = ?");
    $stmt->execute([$lpo_id]);
    $totals = $stmt->fetch();
    
    if ($totals['total_qty'] <= $totals['total_received']) {
        $stmt = $db->prepare("UPDATE lpos SET status = 'completed' WHERE id = ?");
        $stmt->execute([$lpo_id]);
    }
    
    $db->commit();
    
    echo json_encode(['success' => true, 'message' => 'Goods received successfully', 'grn_number' => $grn_number]);
} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log("Error in save_grn: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>