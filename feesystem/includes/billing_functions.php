<?php
// /feesystem/includes/billing_functions.php

/**
 * Get or create a subscription for a school
 */
function getOrCreateSubscription($school_id, $db) {
    // Check if subscription exists
    $stmt = $db->prepare("SELECT * FROM saas_subscriptions WHERE school_id = ?");
    $stmt->execute([$school_id]);
    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($subscription) {
        return $subscription;
    }
    
    // Create new trial subscription
    $trial_ends_at = date('Y-m-d H:i:s', strtotime('+14 days'));
    $module_type = 'single';
    $student_count = getSchoolStudentCount($school_id, $db);
    
    $stmt = $db->prepare("
        INSERT INTO saas_subscriptions 
        (school_id, module_type, student_count, status, trial_ends_at, current_period_end, next_billing_date) 
        VALUES (?, ?, ?, 'trial', ?, ?, ?)
    ");
    
    $current_period_end = $trial_ends_at;
    $next_billing_date = $trial_ends_at;
    
    $stmt->execute([$school_id, $module_type, $student_count, $trial_ends_at, $current_period_end, $next_billing_date]);
    
    return getOrCreateSubscription($school_id, $db);
}

/**
 * Get school's student count
 */
function getSchoolStudentCount($school_id, $db) {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM tblstudents WHERE school_id = ? AND Status = 'Active'");
    $stmt->execute([$school_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'] ?? 0;
}

/**
 * Get applicable plan based on school type and module selection
 */
function getApplicablePlan($school_id, $module_type, $db) {
    $stmt = $db->prepare("SELECT institution_level as school_level, school_type FROM tblschoolinfo WHERE id = ?");
    $stmt->execute([$school_id]);
    $school = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$school) return null;
    
    $school_level = ($school['school_level'] == 'secondary') ? 'secondary' : 'primary';
    $school_type = $school['school_type'] ?? 'public';
    
    $stmt = $db->prepare("
        SELECT * FROM saas_plans 
        WHERE school_level = ? AND school_type = ? AND module_type = ? AND status = 'active'
    ");
    $stmt->execute([$school_level, $school_type, $module_type]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Get complete billing dashboard data
 */
function getBillingDashboardData($school_id, $db) {
    $subscription = getOrCreateSubscription($school_id, $db);
    
    // Get current plan details
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
    $pending_invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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
    
    // Get SMS credits
    $stmt = $db->prepare("SELECT current_month_usage, monthly_limit FROM school_bulk_sms_keys WHERE school_id = ?");
    $stmt->execute([$school_id]);
    $sms = $stmt->fetch(PDO::FETCH_ASSOC);
    $sms_credits = ($sms['monthly_limit'] ?? 1000) - ($sms['current_month_usage'] ?? 0);
    
    $student_count = getSchoolStudentCount($school_id, $db);
    $is_trial = $subscription['status'] == 'trial';
    $trial_ends_at = $subscription['trial_ends_at'];
    $days_left = $is_trial ? max(0, ceil((strtotime($trial_ends_at) - time()) / 86400)) : 0;
    
    // Calculate totals
    $total_outstanding = array_sum(array_column($pending_invoices, 'total_amount'));
    $total_paid = array_sum(array_column($payments, 'amount'));
    $overdue = array_sum(array_filter($pending_invoices, function($inv) { 
        return $inv['status'] == 'overdue'; 
    }));
    
    // Get this month's billing
    $this_month_start = date('Y-m-01 00:00:00');
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM saas_payments WHERE school_id = ? AND payment_date >= ?");
    $stmt->execute([$school_id, $this_month_start]);
    $this_month = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get last month's billing
    $last_month_start = date('Y-m-01 00:00:00', strtotime('-1 month'));
    $last_month_end = date('Y-m-t 23:59:59', strtotime('-1 month'));
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM saas_payments WHERE school_id = ? AND payment_date BETWEEN ? AND ?");
    $stmt->execute([$school_id, $last_month_start, $last_month_end]);
    $last_month = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Calculate monthly change percentage
    $monthly_change = 0;
    if (($last_month['total'] ?? 0) > 0) {
        $monthly_change = (($this_month['total'] - $last_month['total']) / $last_month['total']) * 100;
    }
    
    return [
        'subscription' => $subscription,
        'current_plan' => $plan['name'] ?? ($is_trial ? 'Free Trial' : 'Basic'),
        'plan_price' => $plan['price_per_student'] ?? 0,
        'billing_cycle' => $plan['billing_cycle'] ?? 'monthly',
        'is_trial' => $is_trial,
        'trial_days_left' => $days_left,
        'trial_ends_at' => $trial_ends_at,
        'student_count' => $student_count,
        'total_outstanding' => $total_outstanding,
        'total_paid' => $total_paid,
        'this_month' => $this_month['total'] ?? 0,
        'last_month' => $last_month['total'] ?? 0,
        'monthly_change' => round($monthly_change, 1),
        'overdue' => $overdue,
        'sms_credits' => max(0, $sms_credits),
        'pending_invoices' => $pending_invoices,
        'payment_history' => $payments,
        'invoices' => $invoices,
        'status' => $subscription['status'],
        'status_text' => getStatusText($subscription['status']),
        'status_color' => getStatusColor($subscription['status']),
        'renewal_date' => $subscription['current_period_end'] ?? $subscription['trial_ends_at'],
        'onboarding_paid' => $subscription['onboarding_paid'] ?? false,
        'overdue_count' => count(array_filter($pending_invoices, function($inv) { return $inv['status'] == 'overdue'; }))
    ];
}

function getStatusText($status) {
    $map = [
        'trial' => 'Free Trial',
        'active' => 'Active',
        'expired' => 'Expired',
        'pending_payment' => 'Payment Required',
        'cancelled' => 'Cancelled'
    ];
    return $map[$status] ?? $status;
}

function getStatusColor($status) {
    $map = [
        'trial' => '#f59e0b',
        'active' => '#10b981',
        'expired' => '#ef4444',
        'pending_payment' => '#f59e0b',
        'cancelled' => '#6b7280'
    ];
    return $map[$status] ?? '#6b7280';
}

function getPlanFeatures($plan) {
    $features = [];
    if ($plan['module_type'] == 'single') {
        $features[] = 'Single Module Access';
        $features[] = 'Basic Analytics';
        $features[] = 'Email Support';
        $features[] = '24/7 Customer Support';
    } else {
        $features[] = 'Both Modules Access';
        $features[] = 'Advanced Analytics';
        $features[] = 'Priority Support';
        $features[] = 'API Access';
        $features[] = 'Bulk SMS Credits';
        $features[] = 'Custom Reports';
    }
    $features[] = 'Unlimited Students';
    return $features;
}

function getDefaultPlans($school_level, $school_type) {
    $plans = [];
    
    if ($school_level == 'primary') {
        if ($school_type == 'public') {
            $plans[] = [
                'id' => 1,
                'name' => 'Primary Starter',
                'module_type' => 'single',
                'price_per_student' => 15,
                'billing_cycle' => 'monthly',
                'onboarding_fee' => 2000,
                'status' => 'active',
                'description' => 'Perfect for small primary schools starting with exam analysis'
            ];
            $plans[] = [
                'id' => 2,
                'name' => 'Primary Professional',
                'module_type' => 'both',
                'price_per_student' => 25,
                'billing_cycle' => 'monthly',
                'onboarding_fee' => 3500,
                'status' => 'active',
                'description' => 'Complete solution with exam and fee management'
            ];
        } else {
            $plans[] = [
                'id' => 3,
                'name' => 'Primary Starter',
                'module_type' => 'single',
                'price_per_student' => 20,
                'billing_cycle' => 'monthly',
                'onboarding_fee' => 3000,
                'status' => 'active',
                'description' => 'Perfect for small private primary schools'
            ];
            $plans[] = [
                'id' => 4,
                'name' => 'Primary Professional',
                'module_type' => 'both',
                'price_per_student' => 50,
                'billing_cycle' => 'monthly',
                'onboarding_fee' => 8000,
                'status' => 'active',
                'description' => 'Complete solution for private primary schools'
            ];
        }
    } else {
        if ($school_type == 'public') {
            $plans[] = [
                'id' => 5,
                'name' => 'Secondary Starter',
                'module_type' => 'single',
                'price_per_student' => 20,
                'billing_cycle' => 'term',
                'onboarding_fee' => 2500,
                'status' => 'active',
                'description' => 'Perfect for secondary schools starting with exam analysis'
            ];
            $plans[] = [
                'id' => 6,
                'name' => 'Secondary Professional',
                'module_type' => 'both',
                'price_per_student' => 35,
                'billing_cycle' => 'term',
                'onboarding_fee' => 4500,
                'status' => 'active',
                'description' => 'Complete solution for public secondary schools'
            ];
        } else {
            $plans[] = [
                'id' => 7,
                'name' => 'Secondary Starter',
                'module_type' => 'single',
                'price_per_student' => 40,
                'billing_cycle' => 'term',
                'onboarding_fee' => 6000,
                'status' => 'active',
                'description' => 'Perfect for private secondary schools'
            ];
            $plans[] = [
                'id' => 8,
                'name' => 'Secondary Professional',
                'module_type' => 'both',
                'price_per_student' => 70,
                'billing_cycle' => 'term',
                'onboarding_fee' => 10000,
                'status' => 'active',
                'description' => 'Complete solution for private secondary schools'
            ];
        }
    }
    
    return $plans;
}
?>