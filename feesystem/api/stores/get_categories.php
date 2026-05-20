<?php
header('Content-Type: application/json');
require_once('../../includes/config.php');

$data = json_decode(file_get_contents('php://input'), true);
$school_id = $data['school_id'] ?? 0;

if (!$school_id) {
    echo json_encode(['success' => false, 'message' => 'School ID required']);
    exit;
}

try {
    $stmt = $db->prepare("SELECT id, name, description FROM store_categories WHERE school_id = ? ORDER BY name");
    $stmt->execute([$school_id]);
    $categories = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'categories' => $categories]);
} catch (PDOException $e) {
    error_log("Error in get_categories: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>