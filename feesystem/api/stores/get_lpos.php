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
    $stmt = $db->prepare("
        SELECT l.*, s.name as supplier_name 
        FROM lpos l
        JOIN suppliers s ON l.supplier_id = s.id
        WHERE l.school_id = ?
        ORDER BY l.created_at DESC
    ");
    $stmt->execute([$school_id]);
    $lpos = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'lpos' => $lpos]);
} catch (PDOException $e) {
    error_log("Error in get_lpos: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred', 'lpos' => []]);
}
?>