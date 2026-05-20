<?php
header('Content-Type: application/json');
require_once('../../includes/config.php');

$data = json_decode(file_get_contents('php://input'), true);
$school_id = $data['school_id'] ?? 0;
$search = $data['search'] ?? '';
$category_id = $data['category_id'] ?? '';
$stock_status = $data['stock_status'] ?? '';

if (!$school_id) {
    echo json_encode(['success' => false, 'message' => 'School ID required']);
    exit;
}

try {
    $query = "SELECT si.*, sc.name as category_name 
              FROM store_items si
              LEFT JOIN store_categories sc ON si.category_id = sc.id
              WHERE si.school_id = ? AND si.status = 'active'";
    $params = [$school_id];
    
    if ($search) {
        $query .= " AND (si.item_code LIKE ? OR si.item_name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if ($category_id) {
        $query .= " AND si.category_id = ?";
        $params[] = $category_id;
    }
    
    if ($stock_status === 'low') {
        $query .= " AND si.current_stock <= si.reorder_level AND si.current_stock > 0";
    } elseif ($stock_status === 'out') {
        $query .= " AND si.current_stock = 0";
    }
    
    $query .= " ORDER BY si.item_name";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $items = $stmt->fetchAll();
    
    foreach ($items as &$item) {
        $item['stock_status'] = $item['current_stock'] <= 0 ? 'out' : 
                                ($item['current_stock'] <= $item['reorder_level'] ? 'low' : 'in');
    }
    
    echo json_encode(['success' => true, 'items' => $items]);
} catch (PDOException $e) {
    error_log("Error in get_items: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred', 'items' => []]);
}
?>