<?php
require_once 'SubscriptionManager.php';

class AccessControl {
    private $subscriptionManager;
    
    public function __construct($dbh, $school_id) {
        $this->subscriptionManager = new SubscriptionManager($dbh, $school_id);
    }
    
    /**
     * Check if user has access to a specific module
     */
    public function checkAccess($module_type = null) {
        if (!$this->subscriptionManager->hasAccess($module_type)) {
            $this->denyAccess();
            return false;
        }
        return true;
    }
    
    /**
     * Block access and show appropriate message
     */
    private function denyAccess() {
        $state = $this->subscriptionManager->getCurrentState();
        
        if ($state === SubscriptionManager::STATE_AWAITING_ONBOARDING) {
            header('Location: subscription.php?error=onboarding_required');
        } elseif ($state === SubscriptionManager::STATE_EXPIRED) {
            header('Location: subscription.php?error=subscription_expired');
        } else {
            header('Location: subscription.php?error=access_denied');
        }
        exit();
    }
}

// Usage in other pages:
// $accessControl = new AccessControl($dbh, $_SESSION['school_id']);
// $accessControl->checkAccess('reports');
?>