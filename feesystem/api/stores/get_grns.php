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
        SELECT g.*, l.lpo_number, s.name as supplier_name 
        FROM grns g
        JOIN lpos l ON g.lpo_id = l.id
        JOIN suppliers s ON g.supplier_id = s.id
        WHERE g.school_id = ?
        ORDER BY g.created_at DESC
    ");
    $stmt->execute([$school_id]);
    $grns = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'grns' => $grns]);
} catch (PDOException $e) {
    error_log("Error in get_grns: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred', 'grns' => []]);
}
?>