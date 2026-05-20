<?php
// includes/SubscriptionAccess.php

require_once __DIR__ . '/config.php';

class SubscriptionAccess {
    private $dbh;
    
    public function __construct($dbh) {
        $this->dbh = $dbh;
    }
    
    /**
     * Check if school has active subscription access
     */
    public function checkAccess($school_id) {
        // First check if subscription exists
        $stmt = $this->dbh->prepare("
            SELECT subscription_state, expiry_date 
            FROM school_subscriptions 
            WHERE school_id = ? 
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$school_id]);
        $subscription = $stmt->fetch();
        
        if (!$subscription) {
            return false;
        }
        
        // Check if expired
        if ($subscription['expiry_date'] && strtotime($subscription['expiry_date']) < time()) {
            return false;
        }
        
        // Valid states for access
        $valid_states = ['trial', 'active_free_term', 'active_paid_term'];
        
        return in_array($subscription['subscription_state'], $valid_states);
    }
    
    /**
     * Get days remaining until expiry
     */
    public function getDaysRemaining($school_id) {
        $stmt = $this->dbh->prepare("
            SELECT expiry_date 
            FROM school_subscriptions 
            WHERE school_id = ? 
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$school_id]);
        $subscription = $stmt->fetch();
        
        if (!$subscription || !$subscription['expiry_date']) {
            return null;
        }
        
        $days_left = floor((strtotime($subscription['expiry_date']) - time()) / 86400);
        return max(0, $days_left);
    }
    
    /**
     * Redirect if access denied
     */
    public function requireAccess($school_id, $redirect_url = '/subscription.php') {
        if (!$this->checkAccess($school_id)) {
            header("Location: {$redirect_url}");
            exit();
        }
        return true;
    }
}
?>