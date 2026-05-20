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
    $query = "SELECT 
                pv.id,
                pv.voucher_no,
                pv.payee_name,
                pv.payment_date,
                pv.total_amount as amount,
                pv.payment_mode,
                pv.status,
                pv.notes,
                s.name as supplier_name
              FROM payment_vouchers pv
              LEFT JOIN suppliers s ON pv.supplier_id = s.id
              WHERE pv.school_id = ? 
              AND pv.payment_mode = 'cash'
              AND pv.payment_date BETWEEN ? AND ?
              ORDER BY pv.payment_date DESC, pv.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$school_id, $from_date, $to_date]);
    $results = $stmt->fetchAll();
    
    $payments = [];
    foreach ($results as $row) {
        $payments[] = [
            'id' => $row['id'],
            'payment_date' => $row['payment_date'],
            'voucher_no' => $row['voucher_no'],
            'payee_name' => $row['payee_name'],
            'amount' => $row['amount'],
            'payment_mode' => $row['payment_mode'],
            'status' => $row['status'],
            'notes' => $row['notes'],
            'supplier_name' => $row['supplier_name']
        ];
    }
    
    echo json_encode(['success' => true, 'payments' => $payments]);
    
} catch (PDOException $e) {
    error_log("Error in get_cash_payments: " . $e->getMessage());
    echo json_encode(['success' => true, 'payments' => []]);
}
?>