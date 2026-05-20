<?php
header('Content-Type: application/json');
require_once('../../includes/config.php');

$data = json_decode(file_get_contents('php://input'), true);
$school_id = $data['school_id'] ?? 0;
$from_date = $data['from_date'] ?? date('Y-m-01');
$to_date = $data['to_date'] ?? date('Y-m-t');
$bank_account_id = $data['bank_account_id'] ?? null;

if (!$school_id) {
    echo json_encode(['success' => false, 'message' => 'School ID required']);
    exit;
}

try {
    $query = "
        SELECT 
            bt.*, 
            ba.bank_name,
            ba.account_name
        FROM bank_transactions bt
        JOIN bank_accounts ba ON bt.bank_account_id = ba.id
        WHERE bt.school_id = ? 
        AND bt.transaction_date BETWEEN ? AND ?
    ";
    
    $params = [$school_id, $from_date, $to_date];
    
    if ($bank_account_id) {
        $query .= " AND bt.bank_account_id = ?";
        $params[] = $bank_account_id;
    }
    
    $query .= " ORDER BY bt.transaction_date DESC, bt.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll();
    
    $transactions = [];
    foreach ($results as $row) {
        $transactions[] = [
            'id' => $row['id'],
            'transaction_date' => $row['transaction_date'],
            'type' => $row['transaction_type'],
            'amount' => $row['amount'],
            'description' => $row['description'],
            'reference' => $row['reference'],
            'bank_name' => $row['bank_name'],
            'account_name' => $row['account_name']
        ];
    }
    
    echo json_encode(['success' => true, 'transactions' => $transactions]);
} catch (PDOException $e) {
    error_log("Error in get_transaction_history: " . $e->getMessage());
    echo json_encode(['success' => true, 'transactions' => []]);
}
?>