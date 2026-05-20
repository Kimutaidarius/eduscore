<?php
class SubscriptionManager {
    private $dbh;
    private $school_id;
    
    // Subscription states
    const STATE_TRIAL = 'trial';
    const STATE_AWAITING_ONBOARDING = 'awaiting_onboarding';
    const STATE_ACTIVE_FREE_TERM = 'active_free_term';
    const STATE_ACTIVE_PAID_TERM = 'active_paid_term';
    const STATE_EXPIRED = 'expired';
    
    // Term dates (Kenyan school calendar)
    const TERM_1_START = [1 => '01-01', 2 => '01-04', 3 => '01-09']; // Month-Day
    const TERM_1_END = [1 => '31-03', 2 => '31-07', 3 => '30-11'];
    
    public function __construct($dbh, $school_id) {
        $this->dbh = $dbh;
        $this->school_id = $school_id;
    }
    
    /**
     * Get current subscription state
     */
    public function getCurrentState() {
        $stmt = $this->dbh->prepare("
            SELECT * FROM school_subscriptions 
            WHERE school_id = ? 
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$this->school_id]);
        $subscription = $stmt->fetch();
        
        if (!$subscription) {
            $this->initializeTrial();
            return $this->getCurrentState();
        }
        
        // Auto-update state based on dates
        $current_state = $subscription['subscription_state'];
        $now = new DateTime();
        
        if ($current_state === self::STATE_TRIAL) {
            $trial_ends = new DateTime($subscription['trial_ends_at']);
            if ($now > $trial_ends) {
                $this->updateState(self::STATE_AWAITING_ONBOARDING);
                return self::STATE_AWAITING_ONBOARDING;
            }
        }
        
        if (in_array($current_state, [self::STATE_ACTIVE_FREE_TERM, self::STATE_ACTIVE_PAID_TERM])) {
            $expiry = new DateTime($subscription['expiry_date']);
            if ($now > $expiry) {
                $this->updateState(self::STATE_EXPIRED);
                return self::STATE_EXPIRED;
            }
        }
        
        return $current_state;
    }
    
    /**
     * Initialize trial for new school
     */
    private function initializeTrial() {
        $trial_start = new DateTime();
        $trial_end = (clone $trial_start)->modify('+14 days');
        
        $stmt = $this->dbh->prepare("
            INSERT INTO school_subscriptions 
            (school_id, subscription_state, trial_started_at, trial_ends_at, last_state_change)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $this->school_id, 
            self::STATE_TRIAL,
            $trial_start->format('Y-m-d H:i:s'),
            $trial_end->format('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Update subscription state
     */
    public function updateState($new_state) {
        $stmt = $this->dbh->prepare("
            UPDATE school_subscriptions 
            SET subscription_state = ?, last_state_change = NOW()
            WHERE school_id = ? 
            ORDER BY id DESC LIMIT 1
        ");
        return $stmt->execute([$new_state, $this->school_id]);
    }
    
    /**
     * Activate after onboarding payment (Free Term)
     */
    public function activateFreeTerm() {
        $term_dates = $this->getCurrentTermDates();
        $expiry = $term_dates['end']->format('Y-m-d');
        
        $stmt = $this->dbh->prepare("
            UPDATE school_subscriptions 
            SET subscription_state = ?, 
                start_date = ?,
                expiry_date = ?,
                term_year = ?,
                term_number = ?,
                last_state_change = NOW()
            WHERE school_id = ? 
            ORDER BY id DESC LIMIT 1
        ");
        
        return $stmt->execute([
            self::STATE_ACTIVE_FREE_TERM,
            $term_dates['start']->format('Y-m-d'),
            $expiry,
            $term_dates['year'],
            $term_dates['term'],
            $this->school_id
        ]);
    }
    
    /**
     * Activate after paid subscription (Paid Term)
     */
    public function activatePaidTerm() {
        $term_dates = $this->getNextTermDates();
        $expiry = $term_dates['end']->format('Y-m-d');
        
        $stmt = $this->dbh->prepare("
            UPDATE school_subscriptions 
            SET subscription_state = ?,
                start_date = ?,
                expiry_date = ?,
                term_year = ?,
                term_number = ?,
                last_state_change = NOW()
            WHERE school_id = ? 
            ORDER BY id DESC LIMIT 1
        ");
        
        return $stmt->execute([
            self::STATE_ACTIVE_PAID_TERM,
            $term_dates['start']->format('Y-m-d'),
            $expiry,
            $term_dates['year'],
            $term_dates['term'],
            $this->school_id
        ]);
    }
    
    /**
     * Get current term dates from database or calculate
     */
    public function getCurrentTermDates() {
        // First try to get from tblterms table
        try {
            $stmt = $this->dbh->prepare("
                SELECT * FROM tblterms 
                WHERE school_id = ? AND is_current = 1 
                LIMIT 1
            ");
            $stmt->execute([$this->school_id]);
            $term = $stmt->fetch();
            
            if ($term) {
                return [
                    'start' => new DateTime($term['start_date']),
                    'end' => new DateTime($term['end_date']),
                    'term' => $term['term_number'],
                    'year' => $term['academic_year'],
                    'name' => $term['term_name']
                ];
            }
        } catch (Exception $e) {
            // Fall back to calculated dates
        }
        
        // Calculate based on current date
        $now = new DateTime();
        $year = (int)$now->format('Y');
        $month = (int)$now->format('n');
        
        if ($month >= 1 && $month <= 3) $term = 1;
        elseif ($month >= 4 && $month <= 7) $term = 2;
        else $term = 3;
        
        $start = DateTime::createFromFormat('Y-m-d', $year . '-' . self::TERM_1_START[$term]);
        $end = DateTime::createFromFormat('Y-m-d', $year . '-' . self::TERM_1_END[$term]);
        
        return ['start' => $start, 'end' => $end, 'term' => $term, 'year' => $year];
    }
    
    /**
     * Get next term dates
     */
    public function getNextTermDates() {
        $current = $this->getCurrentTermDates();
        $next_term = $current['term'] + 1;
        $year = $current['year'];
        
        if ($next_term > 3) {
            $next_term = 1;
            $year++;
        }
        
        $start = DateTime::createFromFormat('Y-m-d', $year . '-' . self::TERM_1_START[$next_term]);
        $end = DateTime::createFromFormat('Y-m-d', $year . '-' . self::TERM_1_END[$next_term]);
        
        return ['start' => $start, 'end' => $end, 'term' => $next_term, 'year' => $year];
    }
    
    /**
     * Get term name
     */
    public function getCurrentTermName() {
        $term_info = $this->getCurrentTermDates();
        return "Term {$term_info['term']}";
    }
    
    /**
     * Check if school has access to a module
     */
    public function hasAccess($module_type = null) {
        $state = $this->getCurrentState();
        
        // Block access for awaiting_onboarding
        if ($state === self::STATE_AWAITING_ONBOARDING) {
            return false;
        }
        
        // Block report generation for expired
        if ($state === self::STATE_EXPIRED && $module_type === 'reports') {
            return false;
        }
        
        // Allow access for active states and trial
        return in_array($state, [
            self::STATE_TRIAL,
            self::STATE_ACTIVE_FREE_TERM,
            self::STATE_ACTIVE_PAID_TERM
        ]);
    }
    
    /**
     * Run daily cron jobs
     */
    public static function runDailyCron($dbh) {
        $manager = new self($dbh, 0);
        
        // Move expired trials to awaiting_onboarding
        $stmt = $dbh->prepare("
            UPDATE school_subscriptions 
            SET subscription_state = ?
            WHERE subscription_state = ? 
            AND trial_ends_at < NOW()
        ");
        $stmt->execute([self::STATE_AWAITING_ONBOARDING, self::STATE_TRIAL]);
        $trial_updated = $stmt->rowCount();
        
        // Move expired paid terms to expired
        $stmt = $dbh->prepare("
            UPDATE school_subscriptions 
            SET subscription_state = ?
            WHERE subscription_state IN (?, ?) 
            AND expiry_date < CURDATE()
        ");
        $stmt->execute([
            self::STATE_EXPIRED,
            self::STATE_ACTIVE_FREE_TERM,
            self::STATE_ACTIVE_PAID_TERM
        ]);
        $expired_updated = $stmt->rowCount();
        
        // Log cron execution (if table exists)
        try {
            $stmt = $dbh->prepare("
                INSERT INTO cron_logs (job_name, status, affected_count) 
                VALUES ('daily_subscription_check', 'success', ?)
            ");
            $stmt->execute([$trial_updated + $expired_updated]);
        } catch (Exception $e) {
            // Table might not exist, ignore
        }
        
        return true;
    }
}
?>