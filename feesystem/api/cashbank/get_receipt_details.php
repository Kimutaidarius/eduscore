<?php
header('Content-Type: application/json');
require_once('../../includes/config.php');

$data = json_decode(file_get_contents('php://input'), true);
$transaction_id = $data['transaction_id'] ?? 0;

if (!$transaction_id) {
    echo json_encode(['success' => false, 'message' => 'Transaction ID required']);
    exit;
}

try {
    $stmt = $db->prepare("
        SELECT 
            bt.*, 
            ba.bank_name,
            ba.account_name
        FROM bank_transactions bt
        JOIN bank_accounts ba ON bt.bank_account_id = ba.id
        WHERE bt.id = ?
    ");
    $stmt->execute([$transaction_id]);
    $row = $stmt->fetch();
    
    if ($row) {
        echo json_encode([
            'success' => true,
            'receipt' => [
                'id' => $row['id'],
                'transaction_date' => $row['transaction_date'],
                'type' => $row['transaction_type'],
                'amount' => $row['amount'],
                'reference' => $row['reference'],
                'description' => $row['description'],
                'bank_name' => $row['bank_name'],
                'account_name' => $row['account_name']
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Receipt not found']);
    }
} catch (PDOException $e) {
    error_log("Error in get_receipt_details: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>