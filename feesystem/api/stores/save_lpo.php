<?php
header('Content-Type: application/json');
require_once('../../includes/config.php');

$data = json_decode(file_get_contents('php://input'), true);

$school_id = $data['school_id'] ?? 0;
$user_id = $data['user_id'] ?? 0;
$supplier_id = $data['supplier_id'] ?? 0;
$lpo_date = $data['lpo_date'] ?? date('Y-m-d');
$delivery_date = $data['delivery_date'] ?? null;
$items = $data['items'] ?? [];
$notes = $data['notes'] ?? '';

if (!$school_id || !$supplier_id || empty($items)) {
    echo json_encode(['success' => false, 'message' => 'Supplier and items are required']);
    exit;
}

try {
    // Generate LPO number
    $year = date('Y');
    $month = date('m');
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM lpos WHERE school_id = ? AND YEAR(created_at) = ?");
    $stmt->execute([$school_id, $year]);
    $count = $stmt->fetch()['count'] + 1;
    $lpo_number = "LPO-{$year}{$month}-" . str_pad($count, 4, '0', STR_PAD_LEFT);
    
    // Calculate total amount
    $total_amount = 0;
    foreach ($items as $item) {
        $total_amount += $item['quantity'] * $item['unit_price'];
    }
    
    $db->beginTransaction();
    
    // Insert LPO
    $stmt = $db->prepare("INSERT INTO lpos 
                          (school_id, lpo_number, supplier_id, lpo_date, delivery_date, total_amount, notes, created_by) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$school_id, $lpo_number, $supplier_id, $lpo_date, $delivery_date, $total_amount, $notes, $user_id]);
    $lpo_id = $db->lastInsertId();
    
    // Insert LPO items
    foreach ($items as $item) {
        $stmt = $db->prepare("INSERT INTO lpo_items (lpo_id, item_id, quantity, unit_price, total) 
                              VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$lpo_id, $item['item_id'], $item['quantity'], $item['unit_price'], 
                        $item['quantity'] * $item['unit_price']]);
    }
    
    $db->commit();
    
    echo json_encode(['success' => true, 'message' => 'LPO created successfully', 'lpo_number' => $lpo_number]);
} catch (PDOException $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log("Error in save_lpo: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>