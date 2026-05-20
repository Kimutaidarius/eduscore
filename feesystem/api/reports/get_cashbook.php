<?php
header('Content-Type: application/json');
require_once('../../includes/config.php');

$data = json_decode(file_get_contents('php://input'), true);
$school_id = $data['school_id'] ?? 0;
$from_date = $data['from_date'] ?? date('Y-m-01');
$to_date = $data['to_date'] ?? date('Y-m-d');
$filter = $data['filter'] ?? '';

if (!$school_id) {
    echo json_encode(['success' => false, 'message' => 'School ID required']);
    exit;
}

try {
    $transactions = [];
    $total_receipts = 0;
    $total_payments = 0;
    
    // Get fee collections from fee_transactions (receipts)
    $sql = "SELECT 
                ft.created_at as transaction_date,
                ft.receipt_no as transaction_no,
                'receipt' as type,
                ft.description,
                ft.amount,
                ft.payment_mode
            FROM fee_transactions ft
            WHERE ft.school_id = ? AND ft.transaction_type = 'payment' 
            AND DATE(ft.created_at) BETWEEN ? AND ?";
    
    $params = [$school_id, $from_date, $to_date];
    
    if ($filter == 'cash') {
        $sql .= " AND ft.payment_mode = 'cash'";
    } elseif ($filter == 'mpesa') {
        $sql .= " AND ft.payment_mode = 'mpesa'";
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $receipts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($receipts as $receipt) {
        $transactions[] = $receipt;
        $total_receipts += $receipt['amount'];
    }
    
    // Get other income receipts
    $sql = "SELECT 
                oir.created_at as transaction_date,
                oir.receipt_number as transaction_no,
                'receipt' as type,
                oir.payer_name as description,
                oir.total_amount as amount,
                oir.payment_mode
            FROM other_income_receipts oir
            WHERE oir.school_id = ? AND oir.status = 'active'
            AND DATE(oir.created_at) BETWEEN ? AND ?";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$school_id, $from_date, $to_date]);
    $other_receipts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($other_receipts as $receipt) {
        $transactions[] = $receipt;
        $total_receipts += $receipt['amount'];
    }
    
    // Get pocket money deposits (receipts)
    $sql = "SELECT 
                pmt.created_at as transaction_date,
                pmt.transaction_no,
                'receipt' as type,
                CONCAT('Pocket money deposit for student ID: ', pmt.student_id) as description,
                pmt.amount,
                'cash' as payment_mode
            FROM pocket_money_transactions pmt
            WHERE pmt.school_id = ? AND pmt.type = 'deposit' AND pmt.status = 'completed'
            AND DATE(pmt.created_at) BETWEEN ? AND ?";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$school_id, $from_date, $to_date]);
    $pocket_deposits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($pocket_deposits as $deposit) {
        $transactions[] = $deposit;
        $total_receipts += $deposit['amount'];
    }
    
    // Get payment vouchers (payments)
    $sql = "SELECT 
                pv.created_at as transaction_date,
                pv.voucher_no as transaction_no,
                'payment' as type,
                pv.payee_name as description,
                pv.total_amount as amount,
                pv.payment_mode
            FROM payment_vouchers pv
            WHERE pv.school_id = ? AND pv.status = 'approved'
            AND DATE(pv.created_at) BETWEEN ? AND ?";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$school_id, $from_date, $to_date]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($payments as $payment) {
        $transactions[] = $payment;
        $total_payments += $payment['amount'];
    }
    
    // Get pocket money withdrawals (payments)
    $sql = "SELECT 
                pmt.created_at as transaction_date,
                pmt.transaction_no,
                'payment' as type,
                CONCAT('Pocket money withdrawal for student ID: ', pmt.student_id) as description,
                pmt.amount,
                'cash' as payment_mode
            FROM pocket_money_transactions pmt
            WHERE pmt.school_id = ? AND pmt.type = 'withdrawal' AND pmt.status = 'completed'
            AND DATE(pmt.created_at) BETWEEN ? AND ?";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$school_id, $from_date, $to_date]);
    $pocket_withdrawals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($pocket_withdrawals as $withdrawal) {
        $transactions[] = $withdrawal;
        $total_payments += $withdrawal['amount'];
    }
    
    // Sort transactions by date
    usort($transactions, function($a, $b) {
        return strtotime($a['transaction_date']) - strtotime($b['transaction_date']);
    });
    
    // Calculate opening balance (sum of all transactions before from_date)
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(CASE WHEN type IN ('receipt', 'deposit') THEN amount ELSE -amount END), 0) as opening_balance
        FROM (
            SELECT 'receipt' as type, amount, created_at as txn_date FROM fee_transactions WHERE school_id = ? AND transaction_type = 'payment' AND DATE(created_at) < ?
            UNION ALL
            SELECT 'receipt' as type, total_amount as amount, created_at FROM other_income_receipts WHERE school_id = ? AND status = 'active' AND DATE(created_at) < ?
            UNION ALL
            SELECT 'deposit' as type, amount, created_at FROM pocket_money_transactions WHERE school_id = ? AND type = 'deposit' AND status = 'completed' AND DATE(created_at) < ?
            UNION ALL
            SELECT 'payment' as type, total_amount as amount, created_at FROM payment_vouchers WHERE school_id = ? AND status = 'approved' AND DATE(created_at) < ?
            UNION ALL
            SELECT 'withdrawal' as type, amount, created_at FROM pocket_money_transactions WHERE school_id = ? AND type = 'withdrawal' AND status = 'completed' AND DATE(created_at) < ?
        ) as all_transactions
    ");
    $stmt->execute([$school_id, $from_date, $school_id, $from_date, $school_id, $from_date, $school_id, $from_date, $school_id, $from_date]);
    $opening_balance = $stmt->fetch(PDO::FETCH_ASSOC)['opening_balance'] ?? 0;
    
    echo json_encode([
        'success' => true,
        'transactions' => $transactions,
        'total_receipts' => $total_receipts,
        'total_payments' => $total_payments,
        'opening_balance' => (float)$opening_balance
    ]);
    
} catch (PDOException $e) {
    error_log("Error in get_cashbook: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred: ' . $e->getMessage(), 'transactions' => []]);
}
?>