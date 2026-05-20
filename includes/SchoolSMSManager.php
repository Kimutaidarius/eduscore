<?php
// includes/SchoolSMSManager.php

// Fix the config path - use absolute path based on document root
$configPath = dirname(__DIR__) . '/includes/config.php';
if (file_exists($configPath)) {
    require_once $configPath;
} else {
    // If config.php is not found, try alternative path
    $configPath = dirname(__DIR__) . '/config.php';
    if (file_exists($configPath)) {
        require_once $configPath;
    } else {
        die("Configuration file not found");
    }
}

class SchoolSMSManager {
    private $pdo;
    private $school_id;
    private $school_data;
    private $bulk_sms_api_key;
    private $bulk_sms_api_url;
    
    // Your main bulk SMS API credentials
    const MAIN_API_KEY = 'esk_4a9565f2f2831158'; // Your main API key
    const BASE_API_URL = 'https://edu-score.app/bulksms/user/api-docs.php/api/';
    
    public function __construct($school_id = null, $pdo = null) {
        // Try to get PDO from global if not passed
        if ($pdo === null) {
            global $dbh, $pdo, $conn;
            
            // Try different possible variable names for the database connection
            if (isset($dbh) && $dbh instanceof PDO) {
                $this->pdo = $dbh;
            } elseif (isset($pdo) && $pdo instanceof PDO) {
                $this->pdo = $pdo;
            } elseif (isset($conn) && $conn instanceof PDO) {
                $this->pdo = $conn;
            } elseif (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
                $this->pdo = $GLOBALS['pdo'];
            } elseif (isset($GLOBALS['dbh']) && $GLOBALS['dbh'] instanceof PDO) {
                $this->pdo = $GLOBALS['dbh'];
            } else {
                // Last resort - try to create a new connection
                try {
                    $this->pdo = new PDO(
                        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                        DB_USER,
                        DB_PASS,
                        [
                            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                            PDO::ATTR_EMULATE_PREPARES => false
                        ]
                    );
                } catch (Exception $e) {
                    error_log("Failed to create database connection in SchoolSMSManager: " . $e->getMessage());
                    throw new Exception("Database connection failed: " . $e->getMessage());
                }
            }
        } else {
            $this->pdo = $pdo;
        }
        
        // Verify we have a valid PDO connection
        if (!$this->pdo instanceof PDO) {
            error_log("SchoolSMSManager: No valid PDO connection available");
            throw new Exception("Database connection not available");
        }
        
        if ($school_id) {
            $this->setSchool($school_id);
        }
        
        $this->bulk_sms_api_url = self::BASE_API_URL;
    }
    
    /**
     * Set the current school
     */
    public function setSchool($school_id) {
        try {
            if (!$this->pdo instanceof PDO) {
                error_log("setSchool: PDO is not valid");
                return false;
            }
            
            $stmt = $this->pdo->prepare("
                SELECT * FROM tblschoolinfo 
                WHERE id = ? AND status = 'approved' AND is_activated = 1
            ");
            $stmt->execute([$school_id]);
            $this->school_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($this->school_data) {
                $this->school_id = $school_id;
                $this->bulk_sms_api_key = $this->school_data['bulk_sms_api_key'] ?? null;
                
                // Check and reset monthly usage if needed
                $this->checkAndResetMonthlyUsage();
                return true;
            }
            
            return false;
        } catch (PDOException $e) {
            error_log("SchoolSMSManager setSchool error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate API key for a school to use your bulk SMS system
     */
    public function generateSchoolAPIKey($school_id) {
        try {
            if (!$this->pdo instanceof PDO) {
                error_log("generateSchoolAPIKey: PDO is not valid");
                return false;
            }
            
            // Generate unique API key for the school
            $api_key = 'SCH_' . $school_id . '_' . bin2hex(random_bytes(16));
            $api_secret = bin2hex(random_bytes(32));
            
            // Check if school_bulk_sms_keys table exists, if not create it
            try {
                $this->pdo->exec("
                    CREATE TABLE IF NOT EXISTS school_bulk_sms_keys (
                        id INT(11) AUTO_INCREMENT PRIMARY KEY,
                        school_id INT(11) NOT NULL,
                        bulk_sms_api_key VARCHAR(100) NOT NULL,
                        bulk_sms_api_secret VARCHAR(100),
                        status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
                        monthly_limit INT(11) DEFAULT 1000,
                        current_month_usage INT(11) DEFAULT 0,
                        last_reset_date DATE,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        UNIQUE KEY unique_bulk_api_key (bulk_sms_api_key),
                        INDEX idx_school (school_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                ");
            } catch (Exception $e) {
                error_log("Table creation warning: " . $e->getMessage());
            }
            
            // Check if already exists
            $checkStmt = $this->pdo->prepare("
                SELECT id FROM school_bulk_sms_keys 
                WHERE school_id = ?
            ");
            $checkStmt->execute([$school_id]);
            
            if ($checkStmt->rowCount() > 0) {
                // Update existing
                $updateStmt = $this->pdo->prepare("
                    UPDATE school_bulk_sms_keys 
                    SET bulk_sms_api_key = ?, bulk_sms_api_secret = ?, 
                        updated_at = NOW()
                    WHERE school_id = ?
                ");
                $updateStmt->execute([$api_key, $api_secret, $school_id]);
            } else {
                // Insert new
                $insertStmt = $this->pdo->prepare("
                    INSERT INTO school_bulk_sms_keys 
                    (school_id, bulk_sms_api_key, bulk_sms_api_secret) 
                    VALUES (?, ?, ?)
                ");
                $insertStmt->execute([$school_id, $api_key, $api_secret]);
            }
            
            // Update main school table (check if column exists first)
            try {
                $updateSchool = $this->pdo->prepare("
                    UPDATE tblschoolinfo 
                    SET bulk_sms_api_key = ? 
                    WHERE id = ?
                ");
                $updateSchool->execute([$api_key, $school_id]);
            } catch (Exception $e) {
                error_log("Failed to update school table (column might not exist): " . $e->getMessage());
            }
            
            return [
                'api_key' => $api_key,
                'api_secret' => $api_secret
            ];
            
        } catch (PDOException $e) {
            error_log("Generate School API Key Error: " . $e->getMessage());
            return false;
        } catch (Exception $e) {
            error_log("Generate School API Key Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get school's SMS balance from your main system
     */
    public function getSchoolBalance() {
        if (!$this->bulk_sms_api_key) {
            return ['success' => false, 'message' => 'School API key not configured', 'balance' => 0];
        }
        
        // Call your main API to check balance
        $url = $this->bulk_sms_api_url . 'check_balance.php?api_key=' . $this->bulk_sms_api_key;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code == 200) {
            $data = json_decode($response, true);
            if ($data && isset($data['status']) && $data['status'] == 'success') {
                // Update local balance
                try {
                    if ($this->pdo instanceof PDO) {
                        $updateStmt = $this->pdo->prepare("
                            UPDATE tblschoolinfo 
                            SET sms_balance = ? 
                            WHERE id = ?
                        ");
                        $updateStmt->execute([$data['data']['balance'], $this->school_id]);
                    }
                } catch (Exception $e) {
                    error_log("Failed to update local balance: " . $e->getMessage());
                }
                
                return [
                    'success' => true,
                    'balance' => $data['data']['balance']
                ];
            }
        }
        
        // Return local balance as fallback
        return [
            'success' => true,
            'balance' => $this->school_data['sms_balance'] ?? 0
        ];
    }
    
    // ... (rest of the methods remain the same)
    
    // Make sure all methods that use $this->pdo check if it's valid
    private function checkAndResetMonthlyUsage() {
        if (!$this->school_data || !$this->pdo instanceof PDO) return;
        
        $today = date('Y-m-d');
        $last_reset = $this->school_data['sms_last_reset'] ?? null;
        
        if (!$last_reset || $last_reset < date('Y-m-01')) {
            // Reset monthly usage
            try {
                $updateStmt = $this->pdo->prepare("
                    UPDATE tblschoolinfo 
                    SET sms_usage_monthly = 0, 
                        sms_last_reset = ?
                    WHERE id = ?
                ");
                $updateStmt->execute([$today, $this->school_id]);
                
                $this->school_data['sms_usage_monthly'] = 0;
                $this->school_data['sms_last_reset'] = $today;
            } catch (Exception $e) {
                error_log("Failed to reset monthly usage: " . $e->getMessage());
            }
        }
    }
    
    private function checkMonthlyLimit() {
        $monthly_limit = $this->school_data['sms_monthly_limit'] ?? 1000;
        $current_usage = $this->school_data['sms_usage_monthly'] ?? 0;
        
        return $current_usage < $monthly_limit;
    }
    
    private function updateMonthlyUsage($sms_count) {
        if (!$this->pdo instanceof PDO) return;
        
        try {
            $updateStmt = $this->pdo->prepare("
                UPDATE tblschoolinfo 
                SET sms_usage_monthly = sms_usage_monthly + ? 
                WHERE id = ?
            ");
            $updateStmt->execute([$sms_count, $this->school_id]);
        } catch (Exception $e) {
            error_log("Failed to update monthly usage: " . $e->getMessage());
        }
    }
    
    private function logSMSAttempt($phone, $message, $response, $http_code) {
        if (!$this->pdo instanceof PDO) return;
        
        try {
            $logStmt = $this->pdo->prepare("
                INSERT INTO sms_logs (
                    school_id, recipient_phone, message_content, gateway_response, 
                    status, created_at
                ) VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            $status = ($http_code == 200) ? 'sent' : 'failed';
            
            $logStmt->execute([
                $this->school_id,
                $phone,
                $message,
                json_encode(['http_code' => $http_code, 'response' => $response]),
                $status
            ]);
        } catch (Exception $e) {
            error_log("Failed to log SMS attempt: " . $e->getMessage());
        }
    }
    
    private function formatPhoneNumber($phone) {
        // Remove any non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Check if it's a Kenyan number
        if (strlen($phone) == 9 && ($phone[0] == '7' || $phone[0] == '1')) {
            $phone = '254' . $phone;
        } elseif (strlen($phone) == 10 && $phone[0] == '0') {
            $phone = '254' . substr($phone, 1);
        } elseif (strlen($phone) == 12 && substr($phone, 0, 3) == '254') {
            // Already in correct format
        }
        
        return '+' . $phone;
    }
    
    public function getStatistics($period = 'month') {
        if (!$this->pdo instanceof PDO) {
            return [
                'daily_stats' => [],
                'purchase_stats' => ['total_purchases' => 0, 'total_credits' => 0, 'total_amount' => 0],
                'current_balance' => 0,
                'monthly_usage' => 0,
                'monthly_limit' => 1000,
                'remaining_this_month' => 1000
            ];
        }
        
        $date_condition = '';
        
        switch ($period) {
            case 'week':
                $date_condition = "AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                break;
            case 'month':
                $date_condition = "AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                break;
            case 'year':
                $date_condition = "AND created_at >= DATE_SUB(NOW(), INTERVAL 365 DAY)";
                break;
        }
        
        try {
            // Get total SMS sent
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_sms,
                    COUNT(CASE WHEN status = 'sent' THEN 1 END) as successful,
                    COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed,
                    DATE(created_at) as date
                FROM sms_logs
                WHERE school_id = ? $date_condition
                GROUP BY DATE(created_at)
                ORDER BY date DESC
            ");
            $stmt->execute([$this->school_id]);
            $daily_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get purchase history
            $stmt2 = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_purchases,
                    SUM(credits_purchased) as total_credits,
                    SUM(amount_paid) as total_amount
                FROM school_sms_purchases
                WHERE school_id = ? AND payment_status = 'completed'
                $date_condition
            ");
            $stmt2->execute([$this->school_id]);
            $purchase_stats = $stmt2->fetch(PDO::FETCH_ASSOC);
            
            return [
                'daily_stats' => $daily_stats,
                'purchase_stats' => $purchase_stats,
                'current_balance' => $this->school_data['sms_balance'] ?? 0,
                'monthly_usage' => $this->school_data['sms_usage_monthly'] ?? 0,
                'monthly_limit' => $this->school_data['sms_monthly_limit'] ?? 1000,
                'remaining_this_month' => ($this->school_data['sms_monthly_limit'] ?? 1000) - ($this->school_data['sms_usage_monthly'] ?? 0)
            ];
        } catch (Exception $e) {
            error_log("Get statistics error: " . $e->getMessage());
            return [
                'daily_stats' => [],
                'purchase_stats' => ['total_purchases' => 0, 'total_credits' => 0, 'total_amount' => 0],
                'current_balance' => 0,
                'monthly_usage' => 0,
                'monthly_limit' => 1000,
                'remaining_this_month' => 1000
            ];
        }
    }
}
?>