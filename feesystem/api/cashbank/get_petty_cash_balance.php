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
    $stmt = $db->prepare("SELECT balance FROM petty_cash WHERE school_id = ?");
    $stmt->execute([$school_id]);
    $row = $stmt->fetch();
    
    $balance = $row ? $row['balance'] : 0;
    echo json_encode(['success' => true, 'balance' => $balance]);
} catch (PDOException $e) {
    error_log("Error in get_petty_cash_balance: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred', 'balance' => 0]);
}
?>