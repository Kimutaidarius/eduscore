<?php
// /feesystem/includes/subscription_middleware.php
// Include this in all protected pages

function checkSubscriptionAccess($db, $school_id) {
    // Get subscription status
    $stmt = $db->prepare("
        SELECT status, trial_ends_at, current_period_end 
        FROM saas_subscriptions 
        WHERE school_id = ?
    ");
    $stmt->execute([$school_id]);
    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$subscription) {
        // Create trial subscription
        createTrialSubscription($db, $school_id);
        return true; // Allow access during trial creation
    }
    
    $now = new DateTime();
    $expiry = new DateTime($subscription['current_period_end'] ?? $subscription['trial_ends_at']);
    
    // Check if subscription is active
    if ($subscription['status'] == 'expired' || $subscription['status'] == 'cancelled') {
        redirectToBilling('Your subscription has expired. Please renew to continue.');
        return false;
    }
    
    // Check if trial has ended
    if ($subscription['status'] == 'trial' && $now > $expiry) {
        $stmt = $db->prepare("UPDATE saas_subscriptions SET status = 'expired' WHERE school_id = ?");
        $stmt->execute([$school_id]);
        redirectToBilling('Your free trial has ended. Please subscribe to continue.');
        return false;
    }
    
    // Check for unpaid invoices
    $stmt = $db->prepare("
        SELECT COUNT(*) as unpaid 
        FROM saas_invoices 
        WHERE school_id = ? AND status IN ('pending', 'overdue')
    ");
    $stmt->execute([$school_id]);
    $unpaid = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($unpaid['unpaid'] > 0 && $subscription['status'] != 'trial') {
        // Allow access but show warning
        $_SESSION['subscription_warning'] = "You have unpaid invoices. Please settle them to avoid service interruption.";
    }
    
    return true;
}

function createTrialSubscription($db, $school_id) {
    $trial_ends_at = date('Y-m-d H:i:s', strtotime('+14 days'));
    $stmt = $db->prepare("
        INSERT INTO saas_subscriptions 
        (school_id, module_type, student_count, status, trial_ends_at, current_period_end, next_billing_date) 
        VALUES (?, 'single', 0, 'trial', ?, ?, ?)
    ");
    $stmt->execute([$school_id, $trial_ends_at, $trial_ends_at, $trial_ends_at]);
}

function redirectToBilling($message) {
    $_SESSION['subscription_error'] = $message;
    header('Location: /feesystem/finance/billing.php');
    exit;
}

// Usage in any protected page:
// require_once('../../includes/subscription_middleware.php');
// checkSubscriptionAccess($db, $_SESSION['school_id']);
?>