<?php
header('Content-Type: application/json');
require_once('../../includes/config.php');

$data = json_decode(file_get_contents('php://input'), true);

$school_id = $data['school_id'] ?? 0;
$user_id = $data['user_id'] ?? 0;
$issuance_date = $data['issuance_date'] ?? date('Y-m-d');
$department_id = $data['department_id'] ?? null;
$requested_by = $data['requested_by'] ?? '';
$items = $data['items'] ?? [];
$remarks = $data['remarks'] ?? '';

if (!$school_id || empty($items)) {
    echo json_encode(['success' => false, 'message' => 'Items are required']);
    exit;
}

try {
    // Start transaction
    $db->beginTransaction();
    
    // Validate stock availability for all items
    foreach ($items as $item) {
        $stmt = $db->prepare("SELECT current_stock, item_name, unit_of_measure, unit_price FROM store_items WHERE id = ? AND school_id = ? AND status = 'active'");
        $stmt->execute([$item['item_id'], $school_id]);
        $stock = $stmt->fetch();
        
        if (!$stock) {
            throw new Exception("Item not found");
        }
        
        if ($stock['current_stock'] < $item['quantity']) {
            throw new Exception("Insufficient stock for {$stock['item_name']}. Available: {$stock['current_stock']} {$stock['unit_of_measure']}");
        }
    }
    
    // Generate issuance number - sequential per school (starts from 001)
    $year = date('Y');
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM issuances WHERE school_id = ? AND YEAR(created_at) = ?");
    $stmt->execute([$school_id, $year]);
    $count = $stmt->fetch()['count'] + 1;
    $issuance_number = "ISS-" . $year . "-" . str_pad($count, 3, '0', STR_PAD_LEFT);
    
    // Insert issuance record
    $stmt = $db->prepare("
        INSERT INTO issuances 
        (school_id, issuance_number, issuance_date, department_id, requested_by, remarks, status, created_by) 
        VALUES (?, ?, ?, ?, ?, ?, 'issued', ?)
    ");
    $stmt->execute([$school_id, $issuance_number, $issuance_date, $department_id, $requested_by, $remarks, $user_id]);
    $issuance_id = $db->lastInsertId();
    
    // Process each item
    foreach ($items as $item) {
        // Get current stock and price
        $stmt = $db->prepare("SELECT unit_price, current_stock FROM store_items WHERE id = ?");
        $stmt->execute([$item['item_id']]);
        $item_data = $stmt->fetch();
        
        $old_stock = $item_data['current_stock'];
        $new_stock = $old_stock - $item['quantity'];
        $total = $item['quantity'] * $item_data['unit_price'];
        
        // Insert issuance item
        $stmt = $db->prepare("
            INSERT INTO issuance_items (issuance_id, item_id, quantity, unit_price, total) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$issuance_id, $item['item_id'], $item['quantity'], $item_data['unit_price'], $total]);
        
        // Update stock
        $stmt = $db->prepare("UPDATE store_items SET current_stock = current_stock - ? WHERE id = ?");
        $stmt->execute([$item['quantity'], $item['item_id']]);
        
        // Log stock movement (if stock_movements table exists)
        $stmt = $db->prepare("
            INSERT INTO stock_movements 
            (school_id, item_id, movement_type, reference_id, reference_type, 
             quantity, previous_stock, new_stock, unit_price, created_by) 
            VALUES (?, ?, 'issuance', ?, 'issuance', ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $school_id, $item['item_id'], $issuance_id, 
            $item['quantity'], $old_stock, $new_stock, 
            $item_data['unit_price'], $user_id
        ]);
    }
    
    // Commit transaction
    $db->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Issuance recorded successfully', 
        'issuance_number' => $issuance_number,
        'issuance_id' => $issuance_id
    ]);
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Error in save_issuance: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>