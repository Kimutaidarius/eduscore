<?php
header('Content-Type: application/json');
require_once('../../includes/config.php');

$data = json_decode(file_get_contents('php://input'), true);
$school_id = $data['school_id'] ?? 0;
$from_date = $data['from_date'] ?? date('Y-m-01');
$to_date = $data['to_date'] ?? date('Y-m-d');

if (!$school_id) {
    echo json_encode(['success' => false, 'message' => 'School ID required']);
    exit;
}

try {
    // Get payment vouchers
    $stmt = $db->prepare("
        SELECT 
            pv.payment_date,
            pv.voucher_no,
            pv.payee_name,
            pvi.particulars as description,
            pvi.amount,
            pv.payment_mode,
            pv.created_at
        FROM payment_vouchers pv
        LEFT JOIN payment_voucher_items pvi ON pv.id = pvi.voucher_id
        WHERE pv.school_id = ? AND pv.status = 'approved'
        AND DATE(pv.payment_date) BETWEEN ? AND ?
        ORDER BY pv.payment_date DESC
    ");
    $stmt->execute([$school_id, $from_date, $to_date]);
    $expenditures = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Also get pocket money withdrawals as expenditure
    $stmt2 = $db->prepare("
        SELECT 
            pmt.transaction_date as payment_date,
            pmt.transaction_no as voucher_no,
            CONCAT('Student ID: ', pmt.student_id) as payee_name,
            pmt.description,
            pmt.amount,
            'cash' as payment_mode,
            pmt.created_at
        FROM pocket_money_transactions pmt
        WHERE pmt.school_id = ? AND pmt.type = 'withdrawal' AND pmt.status = 'completed'
        AND DATE(pmt.transaction_date) BETWEEN ? AND ?
        ORDER BY pmt.transaction_date DESC
    ");
    $stmt2->execute([$school_id, $from_date, $to_date]);
    $pocket_expenditures = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    // Merge both arrays
    $all_expenditures = array_merge($expenditures, $pocket_expenditures);
    
    // Sort by payment date
    usort($all_expenditures, function($a, $b) {
        return strtotime($b['payment_date']) - strtotime($a['payment_date']);
    });
    
    echo json_encode(['success' => true, 'expenditures' => $all_expenditures]);
    
} catch (PDOException $e) {
    error_log("Error in get_expenditure: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred', 'expenditures' => []]);
}
?>