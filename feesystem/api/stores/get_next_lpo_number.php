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
    $year = date('Y');
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM lpos WHERE school_id = ? AND YEAR(created_at) = ?");
    $stmt->execute([$school_id, $year]);
    $count = $stmt->fetch()['count'] + 1;
    $lpo_number = "LPO-" . $year . "-" . str_pad($count, 3, '0', STR_PAD_LEFT);
    
    echo json_encode(['success' => true, 'lpo_number' => $lpo_number]);
} catch (PDOException $e) {
    error_log("Error in get_next_lpo_number: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>