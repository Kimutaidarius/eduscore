<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once('../../includes/config.php');

$data = json_decode(file_get_contents('php://input'), true);
$school_id = $data['school_id'] ?? 0;

try {
    // Get total outstanding (unpaid invoices)
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(total_amount), 0) as total_outstanding
        FROM invoices 
        WHERE school_id = ? AND status = 'UNPAID'
    ");
    $stmt->execute([$school_id]);
    $outstanding = $stmt->fetch();
    
    // Get this month's billing
    $firstDayOfMonth = date('Y-m-01');
    $lastDayOfMonth = date('Y-m-t');
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(amount), 0) as this_month
        FROM tblpayments 
        WHERE school_id = ? AND status = 'completed' 
        AND DATE(created_at) BETWEEN ? AND ?
    ");
    $stmt->execute([$school_id, $firstDayOfMonth, $lastDayOfMonth]);
    $this_month = $stmt->fetch();
    
    // Get last month's billing
    $lastMonthStart = date('Y-m-01', strtotime('first day of previous month'));
    $lastMonthEnd = date('Y-m-t', strtotime('last day of previous month'));
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(amount), 0) as last_month
        FROM tblpayments 
        WHERE school_id = ? AND status = 'completed' 
        AND DATE(created_at) BETWEEN ? AND ?
    ");
    $stmt->execute([$school_id, $lastMonthStart, $lastMonthEnd]);
    $last_month = $stmt->fetch();
    
    // Get overdue amount
    $thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(amount), 0) as overdue
        FROM tblpayments 
        WHERE school_id = ? AND status = 'pending' 
        AND DATE(created_at) <= ?
    ");
    $stmt->execute([$school_id, $thirtyDaysAgo]);
    $overdue = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'total_outstanding' => floatval($outstanding['total_outstanding']),
        'this_month' => floatval($this_month['this_month']),
        'last_month' => floatval($last_month['last_month']),
        'overdue' => floatval($overdue['overdue'])
    ]);
    
} catch (PDOException $e) {
    error_log("Error in get_summary: " . $e->getMessage());
    echo json_encode([
        'success' => true,
        'total_outstanding' => 0,
        'this_month' => 0,
        'last_month' => 0,
        'overdue' => 0
    ]);
}
?>