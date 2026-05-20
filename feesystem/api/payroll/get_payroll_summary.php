<?php
session_start();
header('Content-Type: application/json');
require_once('../../includes/config.php');

if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    sendResponse(null, 'error', 'Unauthorized access');
}

$school_id = $_SESSION['school_id'] ?? 0;
$input = json_decode(file_get_contents('php://input'), true);
$year = $input['year'] ?? date('Y');

try {
    // Monthly summary
    $monthlyStmt = $db->prepare("
        SELECT 
            month,
            CASE month
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
            END as month_name,
            COUNT(*) as staff_count,
            SUM(gross_salary) as total_gross,
            SUM(total_deductions) as total_deductions,
            SUM(net_pay) as total_net
        FROM payroll_transactions
        WHERE school_id = ? AND year = ?
        GROUP BY month
        ORDER BY month
    ");
    $monthlyStmt->execute([$school_id, $year]);
    $monthly_summary = $monthlyStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Department summary
    $deptStmt = $db->prepare("
        SELECT 
            COALESCE(d.name, 'Unassigned') as department_name,
            COUNT(DISTINCT p.staff_id) as staff_count,
            SUM(p.gross_salary) as total_gross,
            SUM(p.total_deductions) as total_deductions,
            SUM(p.net_pay) as total_net_pay
        FROM payroll_transactions p
        JOIN staff s ON p.staff_id = s.id
        LEFT JOIN departments d ON s.department_id = d.id
        WHERE p.school_id = ? AND p.year = ?
        GROUP BY s.department_id
        ORDER BY total_net_pay DESC
    ");
    $deptStmt->execute([$school_id, $year]);
    $department_summary = $deptStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Overall totals
    $totalStmt = $db->prepare("
        SELECT 
            COUNT(*) as total_transactions,
            SUM(gross_salary) as total_gross,
            SUM(total_deductions) as total_deductions,
            SUM(net_pay) as total_net_pay
        FROM payroll_transactions
        WHERE school_id = ? AND year = ?
    ");
    $totalStmt->execute([$school_id, $year]);
    $totals = $totalStmt->fetch(PDO::FETCH_ASSOC);
    
    sendResponse([
        'monthly_summary' => $monthly_summary,
        'department_summary' => $department_summary,
        'totals' => $totals,
        'year' => $year
    ], 'success');
} catch (PDOException $e) {
    error_log("Error in get_payroll_summary.php: " . $e->getMessage());
    sendResponse(null, 'error', 'Database error occurred');
}
?>