<?php
// /feesystem/api/billing/get_dashboard_data.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once('../../includes/config.php');
require_once('../../includes/billing_functions.php');

session_start();

if (!isset($db)) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

if (!isset($_SESSION['school_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$school_id = $_SESSION['school_id'];

try {
    // Create subscription if it doesn't exist
    $subscription = getOrCreateSubscription($school_id, $db);
    
    // Get school info
    $stmt = $db->prepare("SELECT school_name, institution_level FROM tblschoolinfo WHERE id = ?");
    $stmt->execute([$school_id]);
    $school = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get student count
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM tblstudents WHERE school_id = ? AND Status = 'Active'");
    $stmt->execute([$school_id]);
    $studentCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Get plan details if subscribed
    $plan = null;
    if (!empty($subscription['plan_id'])) {
        $stmt = $db->prepare("SELECT * FROM saas_plans WHERE id = ?");
        $stmt->execute([$subscription['plan_id']]);
        $plan = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Get pending invoices
    $stmt = $db->prepare("
        SELECT * FROM saas_invoices 
        WHERE school_id = ? AND status IN ('pending', 'overdue')
        ORDER BY due_date ASC
    ");
    $stmt->execute([$school_id]);
    $pendingInvoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get payment history
    $stmt = $db->prepare("
        SELECT p.*, i.invoice_number 
        FROM saas_payments p
        LEFT JOIN saas_invoices i ON p.invoice_id = i.id
        WHERE p.school_id = ?
        ORDER BY p.payment_date DESC
        LIMIT 10
    ");
    $stmt->execute([$school_id]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get invoice history
    $stmt = $db->prepare("
        SELECT * FROM saas_invoices 
        WHERE school_id = ?
        ORDER BY invoice_date DESC
        LIMIT 10
    ");
    $stmt->execute([$school_id]);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $is_trial = $subscription['status'] == 'trial';
    $trial_ends_at = $subscription['trial_ends_at'];
    $days_left = $is_trial ? max(0, ceil((strtotime($trial_ends_at) - time()) / 86400)) : 0;
    $total_outstanding = array_sum(array_column($pendingInvoices, 'total_amount'));
    
    // Get SMS credits from school_bulk_sms_keys
    $stmt = $db->prepare("SELECT monthly_limit, current_month_usage FROM school_bulk_sms_keys WHERE school_id = ?");
    $stmt->execute([$school_id]);
    $sms = $stmt->fetch(PDO::FETCH_ASSOC);
    $sms_credits = ($sms['monthly_limit'] ?? 1000) - ($sms['current_month_usage'] ?? 0);
    
    $response = [
        'success' => true,
        'data' => [
            'subscription' => $subscription,
            'current_plan' => $plan['name'] ?? ($is_trial ? 'Free Trial' : 'Basic Plan'),
            'plan_price' => $plan['price_per_student'] ?? 0,
            'billing_cycle' => $plan['billing_cycle'] ?? 'monthly',
            'is_trial' => $is_trial,
            'trial_days_left' => $days_left,
            'trial_ends_at' => $trial_ends_at,
            'student_count' => $studentCount,
            'total_outstanding' => $total_outstanding,
            'total_paid' => array_sum(array_column($payments, 'amount')),
            'this_month' => 0,
            'last_month' => 0,
            'monthly_change' => 0,
            'overdue' => array_sum(array_filter($pendingInvoices, function($inv) { return $inv['status'] == 'overdue'; })),
            'sms_credits' => max(0, $sms_credits),
            'pending_invoices' => $pendingInvoices,
            'payment_history' => $payments,
            'invoices' => $invoices,
            'status' => $subscription['status'],
            'status_text' => getStatusText($subscription['status']),
            'status_color' => getStatusColor($subscription['status']),
            'renewal_date' => $subscription['current_period_end'] ?? $subscription['trial_ends_at'],
            'onboarding_paid' => $subscription['onboarding_paid'] ?? false,
            'product_edition' => $plan['module_type'] == 'both' ? 'Professional' : 'Standard',
            'student_limit' => 999999,
            'student_excess' => 0,
            'extra_student_fee' => 0,
            'current_students' => $studentCount
        ]
    ];
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    error_log("PDO Error in get_dashboard_data.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("Error in get_dashboard_data.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>