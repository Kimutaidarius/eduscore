<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once('../../includes/config.php');

$data = json_decode(file_get_contents('php://input'), true);
$school_id = $data['school_id'] ?? 0;
$from_date = $data['from_date'] ?? date('Y-m-01');
$to_date = $data['to_date'] ?? date('Y-m-d');
$account_filter = $data['account'] ?? '';

if (!$school_id) {
    echo json_encode(['success' => false, 'message' => 'School ID required']);
    exit;
}

try {
    $entries = [];
    $total_debit = 0;
    $total_credit = 0;
    
    // 1. GET FEE COLLECTIONS (Receipts)
    if ($account_filter == '' || $account_filter == 'fee_collections') {
        // Fee transactions (payments from students)
        $stmt = $db->prepare("
            SELECT 
                ft.created_at as transaction_date,
                ft.receipt_no as transaction_no,
                'Fee Collections' as account_name,
                CONCAT('Fee payment via ', UPPER(ft.payment_mode), ' - Receipt: ', ft.receipt_no) as description,
                ft.amount as debit,
                0 as credit
            FROM fee_transactions ft
            WHERE ft.school_id = ? 
                AND ft.transaction_type = 'payment'
                AND DATE(ft.created_at) BETWEEN ? AND ?
        ");
        $stmt->execute([$school_id, $from_date, $to_date]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['debit'] = floatval($row['debit']);
            $total_debit += $row['debit'];
            $entries[] = $row;
        }
        
        // Other income receipts
        $stmt = $db->prepare("
            SELECT 
                oir.created_at as transaction_date,
                oir.receipt_number as transaction_no,
                'Other Income' as account_name,
                CONCAT(oir.payer_name, ' - ', oir.notes) as description,
                oir.total_amount as debit,
                0 as credit
            FROM other_income_receipts oir
            WHERE oir.school_id = ? 
                AND oir.status = 'active'
                AND DATE(oir.created_at) BETWEEN ? AND ?
        ");
        $stmt->execute([$school_id, $from_date, $to_date]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['debit'] = floatval($row['debit']);
            $total_debit += $row['debit'];
            $entries[] = $row;
        }
    }
    
    // 2. GET PAYMENT VOUCHERS (Expenditure)
    if ($account_filter == '' || $account_filter == 'payment_vouchers') {
        $stmt = $db->prepare("
            SELECT 
                pv.created_at as transaction_date,
                pv.voucher_no as transaction_no,
                'Payment Voucher' as account_name,
                CONCAT('Payment to: ', pv.payee_name, ' - ', pv.notes) as description,
                0 as debit,
                pv.total_amount as credit
            FROM payment_vouchers pv
            WHERE pv.school_id = ? 
                AND pv.status != 'cancelled'
                AND DATE(pv.created_at) BETWEEN ? AND ?
        ");
        $stmt->execute([$school_id, $from_date, $to_date]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['credit'] = floatval($row['credit']);
            $total_credit += $row['credit'];
            $entries[] = $row;
        }
    }
    
    // 3. GET PAYROLL TRANSACTIONS
    if ($account_filter == '' || $account_filter == 'payroll') {
        $stmt = $db->prepare("
            SELECT 
                pt.created_at as transaction_date,
                pt.payroll_no as transaction_no,
                'Payroll' as account_name,
                CONCAT('Salary payment for ', s.first_name, ' ', s.last_name) as description,
                0 as debit,
                pt.net_pay as credit
            FROM payroll_transactions pt
            LEFT JOIN staff s ON pt.staff_id = s.id
            WHERE pt.school_id = ? 
                AND pt.status = 'paid'
                AND DATE(pt.created_at) BETWEEN ? AND ?
        ");
        $stmt->execute([$school_id, $from_date, $to_date]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['credit'] = floatval($row['credit']);
            $total_credit += $row['credit'];
            $entries[] = $row;
        }
    }
    
    // 4. GET BANK TRANSACTIONS
    if ($account_filter == '' || $account_filter == 'bank_transactions') {
        // Bank deposits (debits to bank account)
        $stmt = $db->prepare("
            SELECT 
                bt.created_at as transaction_date,
                bt.reference as transaction_no,
                'Bank Account' as account_name,
                CONCAT('Bank ', bt.transaction_type, ': ', bt.description) as description,
                CASE WHEN bt.transaction_type = 'deposit' THEN bt.amount ELSE 0 END as debit,
                CASE WHEN bt.transaction_type = 'withdrawal' THEN bt.amount ELSE 0 END as credit
            FROM bank_transactions bt
            WHERE bt.school_id = ? 
                AND DATE(bt.created_at) BETWEEN ? AND ?
        ");
        $stmt->execute([$school_id, $from_date, $to_date]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['debit'] = floatval($row['debit']);
            $row['credit'] = floatval($row['credit']);
            $total_debit += $row['debit'];
            $total_credit += $row['credit'];
            $entries[] = $row;
        }
    }
    
    // 5. GET POCKET MONEY TRANSACTIONS (WITH STUDENT DETAILS)
    if ($account_filter == '' || $account_filter == 'pocket_money') {
        $stmt = $db->prepare("
            SELECT 
                pm.created_at as transaction_date,
                pm.transaction_no as transaction_no,
                'Pocket Money' as account_name,
                CONCAT(
                    'Pocket money ', pm.type, ' for ',
                    TRIM(CONCAT(COALESCE(s.FirstName, ''), ' ', COALESCE(s.SecondName, ''), ' ', COALESCE(s.LastName, ''))),
                    ' (Adm: ', COALESCE(s.AdmNo, 'N/A'), ')',
                    CASE WHEN pm.description IS NOT NULL AND pm.description != '' THEN CONCAT(' - ', pm.description) ELSE '' END
                ) as description,
                CASE WHEN pm.type = 'deposit' THEN pm.amount ELSE 0 END as debit,
                CASE WHEN pm.type = 'withdrawal' THEN pm.amount ELSE 0 END as credit
            FROM pocket_money_transactions pm
            LEFT JOIN tblstudents s ON pm.student_id = s.id AND s.school_id = pm.school_id
            WHERE pm.school_id = ? 
                AND pm.status = 'completed'
                AND DATE(pm.created_at) BETWEEN ? AND ?
        ");
        $stmt->execute([$school_id, $from_date, $to_date]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['debit'] = floatval($row['debit']);
            $row['credit'] = floatval($row['credit']);
            $total_debit += $row['debit'];
            $total_credit += $row['credit'];
            $entries[] = $row;
        }
    }
    
    // Sort entries by date
    usort($entries, function($a, $b) {
        return strtotime($a['transaction_date']) - strtotime($b['transaction_date']);
    });
    
    echo json_encode([
        'success' => true,
        'entries' => $entries,
        'total_debit' => $total_debit,
        'total_credit' => $total_credit
    ]);
    
} catch (PDOException $e) {
    error_log("PDO Error in get_ledger: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage(), 
        'entries' => []
    ]);
}
?>