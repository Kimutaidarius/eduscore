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
    // Using your existing suppliers table structure (no email column)
    $stmt = $db->prepare("SELECT id, name, phone, address, kra_pin 
                          FROM suppliers 
                          WHERE school_id = ? AND deleted_at IS NULL
                          ORDER BY name");
    $stmt->execute([$school_id]);
    $suppliers = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'suppliers' => $suppliers]);
} catch (PDOException $e) {
    error_log("Error in get_suppliers: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred: ' . $e->getMessage(), 'suppliers' => []]);
}
?>