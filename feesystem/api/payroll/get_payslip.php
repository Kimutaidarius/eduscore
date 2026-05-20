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
    // Get payroll details
    $stmt = $db->prepare("
        SELECT 
            p.*,
            CONCAT(s.first_name, ' ', s.last_name) as staff_name,
            s.staff_number as staff_id,
            s.id_number as kra_pin,
            d.name as department,
            sch.school_name,
            DATE_FORMAT(p.created_at, '%d %M %Y') as created_at,
            CASE p.month
                WHEN 1 THEN 'January'
                WHEN 2 THEN 'February'
                WHEN 3 THEN 'March'
                WHEN 4 THEN 'April'
                WHEN 5 THEN 'May'
                WHEN 6 THEN 'June'
                WHEN 7 THEN 'July'
                WHEN 8 THEN 'August'
                WHEN 9 THEN 'September'
                WHEN 10 THEN 'October'
                WHEN 11 THEN 'November'
                WHEN 12 THEN 'December'
            END as month_name
        FROM payroll_transactions p
        JOIN staff s ON p.staff_id = s.id
        LEFT JOIN departments d ON s.department_id = d.id
        JOIN tblschoolinfo sch ON sch.id = p.school_id
        WHERE p.id = ? AND p.school_id = ?
    ");
    $stmt->execute([$input['id'], $school_id]);
    $payslip = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($payslip) {
        // Get allowances
        $allowStmt = $db->prepare("SELECT name, amount FROM payroll_allowances WHERE payroll_id = ?");
        $allowStmt->execute([$input['id']]);
        $allowances = $allowStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get deductions
        $dedStmt = $db->prepare("SELECT name, amount FROM payroll_deductions WHERE payroll_id = ?");
        $dedStmt->execute([$input['id']]);
        $deductions = $dedStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $payslip['allowances'] = $allowances;
        $payslip['deductions'] = $deductions;
        
        sendResponse(['payslip' => $payslip], 'success');
    } else {
        sendResponse(null, 'error', 'Payslip not found');
    }
} catch (PDOException $e) {
    error_log("Error in get_payslip.php: " . $e->getMessage());
    sendResponse(null, 'error', 'Database error occurred');
}
?>