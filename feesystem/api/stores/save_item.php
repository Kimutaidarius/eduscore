<?php
header('Content-Type: application/json');
require_once('../../includes/config.php');

$data = json_decode(file_get_contents('php://input'), true);

$school_id = $data['school_id'] ?? 0;
$user_id = $data['user_id'] ?? 0;
$item_id = $data['item_id'] ?? 0;
$item_code = $data['item_code'] ?? '';
$item_name = $data['item_name'] ?? '';
$category_id = $data['category_id'] ?? null;
$unit_of_measure = $data['unit_of_measure'] ?? 'pcs';
$unit_price = $data['unit_price'] ?? 0;
$initial_stock = $data['initial_stock'] ?? 0;
$reorder_level = $data['reorder_level'] ?? 0;

if (!$school_id || !$item_code || !$item_name) {
    echo json_encode(['success' => false, 'message' => 'Item code and name are required']);
    exit;
}

try {
    if ($item_id) {
        // Update existing item
        $stmt = $db->prepare("UPDATE store_items 
                              SET item_code = ?, item_name = ?, category_id = ?, 
                                  unit_of_measure = ?, unit_price = ?, reorder_level = ?
                              WHERE id = ? AND school_id = ?");
        $stmt->execute([$item_code, $item_name, $category_id, $unit_of_measure, $unit_price, $reorder_level, $item_id, $school_id]);
        
        echo json_encode(['success' => true, 'message' => 'Item updated successfully']);
    } else {
        // Insert new item
        $stmt = $db->prepare("INSERT INTO store_items 
                              (school_id, item_code, item_name, category_id, unit_of_measure, 
                               current_stock, unit_price, reorder_level, created_by) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$school_id, $item_code, $item_name, $category_id, $unit_of_measure, 
                        $initial_stock, $unit_price, $reorder_level, $user_id]);
        
        echo json_encode(['success' => true, 'message' => 'Item added successfully']);
    }
} catch (PDOException $e) {
    error_log("Error in save_item: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>