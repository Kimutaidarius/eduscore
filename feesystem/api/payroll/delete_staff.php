<?php
session_start();
header('Content-Type: application/json');
require_once('../../includes/config.php');

if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    sendResponse(null, 'error', 'Unauthorized access');
}

$school_id = $_SESSION['school_id'] ?? 0;
$input = json_decode(file_get_contents('php://input'), true);

try {
    // Check if the staff exists
    $checkStmt = $db->prepare("SELECT id, staff_number, first_name, last_name FROM staff WHERE id = ? AND school_id = ?");
    $checkStmt->execute([$input['id'], $school_id]);
    $staff = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$staff) {
        sendResponse(null, 'error', 'Staff not found');
        exit;
    }
    
    // Start transaction for data integrity
    $db->beginTransaction();
    
    // Delete related payroll transactions first (if any)
    $deletePayrollStmt = $db->prepare("DELETE FROM payroll_transactions WHERE staff_id = ?");
    $deletePayrollStmt->execute([$input['id']]);
    $payrollDeleted = $deletePayrollStmt->rowCount();
    
    // Delete related payroll allowances
    // First get payroll IDs for this staff
    $payrollIdsStmt = $db->prepare("SELECT id FROM payroll_transactions WHERE staff_id = ?");
    $payrollIdsStmt->execute([$input['id']]);
    $payrollIds = $payrollIdsStmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($payrollIds)) {
        $placeholders = implode(',', array_fill(0, count($payrollIds), '?'));
        $deleteAllowancesStmt = $db->prepare("DELETE FROM payroll_allowances WHERE payroll_id IN ($placeholders)");
        $deleteAllowancesStmt->execute($payrollIds);
        
        $deleteDeductionsStmt = $db->prepare("DELETE FROM payroll_deductions WHERE payroll_id IN ($placeholders)");
        $deleteDeductionsStmt->execute($payrollIds);
    }
    
    // PERMANENT DELETE - remove the staff record
    $stmt = $db->prepare("DELETE FROM staff WHERE id = ? AND school_id = ?");
    $result = $stmt->execute([$input['id'], $school_id]);
    
    if ($result && $stmt->rowCount() > 0) {
        // Commit transaction
        $db->commit();
        
        $message = "Staff deleted permanently";
        if ($payrollDeleted > 0) {
            $message .= " along with $payrollDeleted associated payroll record(s)";
        }
        
        sendResponse(null, 'success', $message);
    } else {
        // Rollback on failure
        $db->rollBack();
        sendResponse(null, 'error', 'Failed to delete staff');
    }
} catch (PDOException $e) {
    // Rollback on error
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Error in delete_staff.php: " . $e->getMessage());
    sendResponse(null, 'error', 'Database error: ' . $e->getMessage());
}
?>