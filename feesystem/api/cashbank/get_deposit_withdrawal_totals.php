<?php
header('Content-Type: application/json');
require_once('../../includes/config.php');

$data = json_decode(file_get_contents('php://input'), true);
$school_id = $data['school_id'] ?? 0;
$from_date = $data['from_date'] ?? date('Y-m-01');
$to_date = $data['to_date'] ?? date('Y-m-t');

if (!$school_id) {
    echo json_encode(['success' => false, 'message' => 'School ID required']);
    exit;
}

try {
    // Get total deposits
    $depositStmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total 
                                 FROM bank_transactions 
                                 WHERE school_id = ? 
                                 AND transaction_type = 'deposit'
                                 AND transaction_date BETWEEN ? AND ?");
    $depositStmt->execute([$school_id, $from_date, $to_date]);
    $depositTotal = $depositStmt->fetch()['total'];
    
    // Get total withdrawals
    $withdrawalStmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total 
                                    FROM bank_transactions 
                                    WHERE school_id = ? 
                                    AND transaction_type = 'withdrawal'
                                    AND transaction_date BETWEEN ? AND ?");
    $withdrawalStmt->execute([$school_id, $from_date, $to_date]);
    $withdrawalTotal = $withdrawalStmt->fetch()['total'];
    
    echo json_encode([
        'success' => true,
        'total_deposits' => $depositTotal ?: 0,
        'total_withdrawals' => $withdrawalTotal ?: 0
    ]);
} catch (PDOException $e) {
    error_log("Error in get_deposit_withdrawal_totals: " . $e->getMessage());
    echo json_encode(['success' => true, 'total_deposits' => 0, 'total_withdrawals' => 0]);
}
?>