<?php
header('Content-Type: application/json');
require_once('../../includes/config.php');

$data = json_decode(file_get_contents('php://input'), true);
$school_id = $data['school_id'] ?? 0;
$as_at_date = $data['as_at_date'] ?? date('Y-m-d');

if (!$school_id) {
    echo json_encode(['success' => false, 'message' => 'School ID required']);
    exit;
}

try {
    $accounts = [];
    $total_debits = 0;
    $total_credits = 0;
    
    // Helper function to add account
    function addAccount(&$accounts, &$total_debits, &$total_credits, $code, $name, $debit, $credit) {
        if ($debit != 0 || $credit != 0) {
            $accounts[] = [
                'account_code' => $code,
                'account_name' => $name,
                'debit_balance' => round($debit, 2),
                'credit_balance' => round($credit, 2)
            ];
            $total_debits += $debit;
            $total_credits += $credit;
        }
    }
    
    // ============ ASSET ACCOUNTS (Debit balances) ============
    
    // 1. CASH ACCOUNT - All cash transactions
    $stmt = $db->prepare("
        SELECT 
            COALESCE((
                -- Total Receipts (Inflows)
                (SELECT COALESCE(SUM(amount), 0) FROM fee_transactions 
                 WHERE school_id = ? AND transaction_type = 'payment' AND DATE(created_at) <= ?)
                +
                (SELECT COALESCE(SUM(total_amount), 0) FROM other_income_receipts 
                 WHERE school_id = ? AND status = 'active' AND DATE(created_at) <= ?)
                +
                (SELECT COALESCE(SUM(amount), 0) FROM pocket_money_transactions 
                 WHERE school_id = ? AND type = 'deposit' AND status = 'completed' AND DATE(created_at) <= ?)
                -
                -- Total Payments (Outflows)
                (SELECT COALESCE(SUM(total_amount), 0) FROM payment_vouchers 
                 WHERE school_id = ? AND status IN ('approved', 'pending') AND DATE(created_at) <= ?)
                -
                (SELECT COALESCE(SUM(amount), 0) FROM pocket_money_transactions 
                 WHERE school_id = ? AND type = 'withdrawal' AND status = 'completed' AND DATE(created_at) <= ?)
                -
                (SELECT COALESCE(SUM(net_pay), 0) FROM payroll_transactions 
                 WHERE school_id = ? AND status = 'paid' AND DATE(created_at) <= ?)
            ), 0) as balance
    ");
    $stmt->execute([
        $school_id, $as_at_date,
        $school_id, $as_at_date,
        $school_id, $as_at_date,
        $school_id, $as_at_date,
        $school_id, $as_at_date,
        $school_id, $as_at_date
    ]);
    $cash = $stmt->fetch(PDO::FETCH_ASSOC);
    $cash_balance = floatval($cash['balance']);
    addAccount($accounts, $total_debits, $total_credits, 'CASH', 'Cash at Bank', max($cash_balance, 0), 0);
    if ($cash_balance < 0) {
        addAccount($accounts, $total_debits, $total_credits, 'CASH_OD', 'Bank Overdraft', 0, abs($cash_balance));
    }
    
    // 2. ACCOUNTS RECEIVABLE - Student fee balances (What students owe)
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(
            COALESCE((
                SELECT SUM(fs2.amount) FROM fee_structures fs2 
                WHERE fs2.school_id = s.school_id 
                AND fs2.status = 'active'
                AND (fs2.class_level = c.class_level OR fs2.class_level = '')
            ), 0)
            - 
            COALESCE((
                SELECT SUM(ft2.amount) FROM fee_transactions ft2 
                WHERE ft2.student_id = s.id 
                AND ft2.transaction_type = 'payment'
                AND DATE(ft2.created_at) <= ?
            ), 0)
        ), 0) as balance
        FROM tblstudents s
        LEFT JOIN tblclasses c ON s.class_id = c.id
        WHERE s.school_id = ? AND s.Status = 'Active'
        HAVING balance > 0
    ");
    $stmt->execute([$as_at_date, $school_id]);
    $receivables = $stmt->fetch(PDO::FETCH_ASSOC);
    $ar_balance = floatval($receivables['balance']);
    addAccount($accounts, $total_debits, $total_credits, 'AR', 'Accounts Receivable - Student Fees', $ar_balance, 0);
    
    // ============ LIABILITY ACCOUNTS (Credit balances) ============
    
    // 3. UNEARNED REVENUE - Fees collected but not yet earned (if you have advance payments)
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(ft.amount), 0) as balance
        FROM fee_transactions ft
        WHERE ft.school_id = ? 
            AND ft.transaction_type = 'payment'
            AND DATE(ft.created_at) <= ?
            AND ft.academic_year > YEAR(?)
    ");
    $stmt->execute([$school_id, $as_at_date, $as_at_date]);
    $unearned = $stmt->fetch(PDO::FETCH_ASSOC);
    $unearned_balance = floatval($unearned['balance']);
    addAccount($accounts, $total_debits, $total_credits, 'UNEARNED', 'Unearned Revenue', 0, $unearned_balance);
    
    // ============ INCOME ACCOUNTS (Credit balances) ============
    
    // 4. FEE INCOME by Vote Head
    $stmt = $db->prepare("
        SELECT 
            vh.id as account_code,
            vh.name as account_name,
            COALESCE(SUM(ft.amount), 0) as balance
        FROM vote_heads vh
        LEFT JOIN fee_transactions ft ON vh.id = ft.vote_head_id 
            AND ft.school_id = vh.school_id
            AND ft.transaction_type = 'payment'
            AND DATE(ft.created_at) <= ?
        WHERE vh.school_id = ? AND vh.type IN ('income', 'both')
        GROUP BY vh.id, vh.name
        HAVING balance > 0
    ");
    $stmt->execute([$as_at_date, $school_id]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        addAccount($accounts, $total_debits, $total_credits, $row['account_code'], $row['account_name'], 0, floatval($row['balance']));
    }
    
    // 5. OTHER INCOME
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(total_amount), 0) as balance
        FROM other_income_receipts
        WHERE school_id = ? AND status = 'active' AND DATE(created_at) <= ?
    ");
    $stmt->execute([$school_id, $as_at_date]);
    $other_inc = $stmt->fetch(PDO::FETCH_ASSOC);
    $other_balance = floatval($other_inc['balance']);
    addAccount($accounts, $total_debits, $total_credits, 'OTHER_INC', 'Other Income', 0, $other_balance);
    
    // ============ EXPENSE ACCOUNTS (Debit balances) ============
    
    // 6. OPERATING EXPENSES (Payment Vouchers by Vote Head)
    $stmt = $db->prepare("
        SELECT 
            COALESCE(vh.name, 'General Expenses') as account_name,
            COALESCE(vh.id, 'GEN_EXP') as account_code,
            COALESCE(SUM(pvi.amount), 0) as balance
        FROM payment_vouchers pv
        LEFT JOIN payment_voucher_items pvi ON pv.id = pvi.voucher_id
        LEFT JOIN vote_heads vh ON pvi.vote_head_id = vh.id
        WHERE pv.school_id = ? 
            AND pv.status IN ('approved', 'pending')
            AND DATE(pv.created_at) <= ?
        GROUP BY vh.id, vh.name
        HAVING balance > 0
    ");
    $stmt->execute([$school_id, $as_at_date]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        addAccount($accounts, $total_debits, $total_credits, $row['account_code'], $row['account_name'], floatval($row['balance']), 0);
    }
    
    // 7. PAYROLL EXPENSE
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(net_pay), 0) as balance
        FROM payroll_transactions
        WHERE school_id = ? AND status = 'paid' AND DATE(created_at) <= ?
    ");
    $stmt->execute([$school_id, $as_at_date]);
    $payroll = $stmt->fetch(PDO::FETCH_ASSOC);
    $payroll_balance = floatval($payroll['balance']);
    addAccount($accounts, $total_debits, $total_credits, 'PAYROLL', 'Payroll Expense', $payroll_balance, 0);
    
    // ============ EQUITY ACCOUNTS (Credit balances) ============
    
    // 8. RETAINED EARNINGS / OPENING BALANCE (Balancing figure)
    $difference = $total_debits - $total_credits;
    if (abs($difference) > 0.01) {
        if ($difference > 0) {
            // More debits than credits - needs credit to balance (equity)
            addAccount($accounts, $total_debits, $total_credits, 'RE', 'Retained Earnings', 0, $difference);
        } else {
            // More credits than debits - needs debit to balance (drawings/equity reduction)
            addAccount($accounts, $total_debits, $total_credits, 'RE', 'Retained Earnings', abs($difference), 0);
        }
    }
    
    // Sort accounts: Assets (Debit), Expenses (Debit), Liabilities (Credit), Income (Credit), Equity (Credit)
    usort($accounts, function($a, $b) {
        // Debit balances come first
        if ($a['debit_balance'] > 0 && $b['debit_balance'] == 0) return -1;
        if ($a['debit_balance'] == 0 && $b['debit_balance'] > 0) return 1;
        
        // Then sort alphabetically within same balance type
        return strcmp($a['account_name'], $b['account_name']);
    });
    
    echo json_encode([
        'success' => true, 
        'accounts' => $accounts,
        'total_debits' => round($total_debits, 2),
        'total_credits' => round($total_credits, 2)
    ]);
    
} catch (PDOException $e) {
    error_log("Error in get_trial_balance: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred: ' . $e->getMessage(), 'accounts' => []]);
}
?>