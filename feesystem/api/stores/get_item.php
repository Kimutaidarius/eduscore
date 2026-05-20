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
    $stmt = $db->prepare("
        SELECT si.*, sc.name as category_name 
        FROM store_items si
        LEFT JOIN store_categories sc ON si.category_id = sc.id
        WHERE si.id = ? AND si.school_id = ?
    ");
    $stmt->execute([$item_id, $school_id]);
    $item = $stmt->fetch();
    
    if ($item) {
        echo json_encode(['success' => true, 'item' => $item]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Item not found']);
    }
} catch (PDOException $e) {
    error_log("Error in get_item: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>