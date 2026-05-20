<?php
session_start();
header('Content-Type: application/json');
require_once('../../includes/config.php');

if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    sendResponse(null, 'error', 'Unauthorized access');
}

$school_id = $_SESSION['school_id'] ?? 0;
$user_id = $_SESSION['user_id'] ?? 0;
$input = json_decode(file_get_contents('php://input'), true);

// Generate unique payroll number
$payroll_no = 'PYR-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));

try {
    $db->beginTransaction();
    
    // Insert payroll record
    $stmt = $db->prepare("
        INSERT INTO payroll_transactions (
            payroll_no, school_id, staff_id, user_id, month, year, period,
            basic_salary, total_allowances, total_deductions, gross_salary,
            net_pay, payment_mode, notes, status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'paid', NOW())
    ");
    
    $stmt->execute([
        $payroll_no,
        $school_id,
        $input['staff_id'],
        $user_id,
        $input['month'],
        $input['year'],
        $input['period'],
        $input['basic_salary'],
        $input['total_allowances'],
        $input['total_deductions'],
        $input['gross_salary'],
        $input['net_pay'],
        $input['payment_mode'],
        $input['notes']
    ]);
    
    $payroll_id = $db->lastInsertId();
    
    // Insert allowances
    if (!empty($input['allowances'])) {
        $allowanceStmt = $db->prepare("
            INSERT INTO payroll_allowances (
                payroll_id, school_id, name, amount, created_at
            ) VALUES (?, ?, ?, ?, NOW())
        ");
        foreach ($input['allowances'] as $allowance) {
            $allowanceStmt->execute([$payroll_id, $school_id, $allowance['name'], $allowance['amount']]);
        }
    }
    
    // Insert deductions
    if (!empty($input['deductions'])) {
        $deductionStmt = $db->prepare("
            INSERT INTO payroll_deductions (
                payroll_id, school_id, name, amount, created_at
            ) VALUES (?, ?, ?, ?, NOW())
        ");
        foreach ($input['deductions'] as $deduction) {
            $deductionStmt->execute([$payroll_id, $school_id, $deduction['name'], $deduction['amount']]);
        }
    }
    
    $db->commit();
    sendResponse(['payroll_id' => $payroll_id, 'payroll_no' => $payroll_no], 'success', 'Payroll processed successfully');
    
} catch (PDOException $e) {
    $db->rollBack();
    error_log("Error in process_payroll.php: " . $e->getMessage());
    sendResponse(null, 'error', 'Failed to process payroll: ' . $e->getMessage());
}
?>