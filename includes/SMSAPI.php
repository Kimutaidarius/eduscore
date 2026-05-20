<?php
// includes/SMSAPI.php

require_once __DIR__ . '/../config.php';

class SMSAPI {
    private $pdo;
    private $school_id;
    private $api_key;
    private $api_secret;
    private $rate_limit;
    private $sms_balance;
    
    // SMS pricing (KES per SMS)
    const PRICE_PER_SMS = 0.70;
    
    public function __construct($api_key = null, $api_secret = null) {
        global $pdo;
        $this->pdo = $pdo;
        
        if ($api_key && $api_secret) {
            $this->authenticate($api_key, $api_secret);
        }
    }
    
    /**
     * Authenticate school using API key and secret
     */
    public function authenticate($api_key, $api_secret) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT sak.*, ts.sms_balance, ts.school_name, ts.school_email
                FROM school_api_keys sak
                INNER JOIN tblschoolinfo ts ON sak.school_id = ts.id
                WHERE sak.api_key = ? AND sak.api_secret = ? 
                AND sak.status = 'active' AND ts.status = 'approved' AND ts.is_activated = 1
            ");
            $stmt->execute([$api_key, $api_secret]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($data) {
                $this->school_id = $data['school_id'];
                $this->api_key = $data['api_key'];
                $this->api_secret = $data['api_secret'];
                $this->rate_limit = $data['rate_limit'];
                $this->sms_balance = $data['sms_balance'];
                $this->school_name = $data['school_name'];
                $this->school_email = $data['school_email'];
                
                // Update last used timestamp
                $updateStmt = $this->pdo->prepare("
                    UPDATE school_api_keys 
                    SET last_used_at = NOW() 
                    WHERE api_key = ?
                ");
                $updateStmt->execute([$api_key]);
                
                return true;
            }
            
            return false;
        } catch (PDOException $e) {
            error_log("SMS API Authentication Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate API key for a school
     */
    public static function generateAPIKey($school_id, $pdo) {
        try {
            // Generate unique API key and secret
            $api_key = 'EDU_' . bin2hex(random_bytes(24));
            $api_secret = bin2hex(random_bytes(32));
            
            // Check if key already exists
            $checkStmt = $pdo->prepare("SELECT id FROM school_api_keys WHERE api_key = ?");
            $checkStmt->execute([$api_key]);
            
            if ($checkStmt->rowCount() > 0) {
                // Regenerate if duplicate (unlikely but possible)
                return self::generateAPIKey($school_id, $pdo);
            }
            
            // Insert new API key
            $insertStmt = $pdo->prepare("
                INSERT INTO school_api_keys (school_id, api_key, api_secret) 
                VALUES (?, ?, ?)
            ");
            $insertStmt->execute([$school_id, $api_key, $api_secret]);
            
            return [
                'api_key' => $api_key,
                'api_secret' => $api_secret
            ];
        } catch (PDOException $e) {
            error_log("Generate API Key Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get school SMS balance
     */
    public function getBalance() {
        $stmt = $this->pdo->prepare("
            SELECT sms_balance 
            FROM tblschoolinfo 
            WHERE id = ?
        ");
        $stmt->execute([$this->school_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['sms_balance'] : 0;
    }
    
    /**
     * Purchase SMS credits
     */
    public function purchaseCredits($amount, $payment_method = 'mpesa', $mpesa_receipt = null) {
        try {
            $this->pdo->beginTransaction();
            
            // Calculate SMS credits based on amount (1 SMS = 0.70 KES)
            $sms_count = floor($amount / self::PRICE_PER_SMS);
            $actual_amount = $sms_count * self::PRICE_PER_SMS;
            
            if ($sms_count <= 0) {
                throw new Exception("Invalid amount. Minimum purchase is " . self::PRICE_PER_SMS . " KES");
            }
            
            // Generate transaction ID
            $transaction_id = 'TXN_' . date('Ymd') . '_' . uniqid() . '_' . $this->school_id;
            
            // Create transaction record
            $stmt = $this->pdo->prepare("
                INSERT INTO sms_transactions (
                    school_id, transaction_id, sms_count, amount, 
                    payment_method, payment_status, status
                ) VALUES (?, ?, ?, ?, ?, 'pending', 'pending')
            ");
            $stmt->execute([
                $this->school_id,
                $transaction_id,
                $sms_count,
                $actual_amount,
                $payment_method
            ]);
            
            $transaction_id_db = $this->pdo->lastInsertId();
            
            // If M-PESA payment, we'll update after callback
            if ($payment_method === 'mpesa' && $mpesa_receipt) {
                // Process as completed if M-PESA receipt is provided
                $this->completePurchase($transaction_id_db, $mpesa_receipt);
            }
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'transaction_id' => $transaction_id,
                'sms_count' => $sms_count,
                'amount' => $actual_amount,
                'payment_method' => $payment_method,
                'message' => 'Payment initiated successfully'
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Purchase Credits Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Complete purchase after payment confirmation
     */
    public function completePurchase($transaction_id, $mpesa_receipt = null) {
        try {
            $this->pdo->beginTransaction();
            
            // Get transaction details
            $stmt = $this->pdo->prepare("
                SELECT * FROM sms_transactions 
                WHERE id = ? AND school_id = ?
            ");
            $stmt->execute([$transaction_id, $this->school_id]);
            $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$transaction) {
                throw new Exception("Transaction not found");
            }
            
            if ($transaction['payment_status'] === 'completed') {
                throw new Exception("Transaction already completed");
            }
            
            // Update transaction
            $updateStmt = $this->pdo->prepare("
                UPDATE sms_transactions 
                SET payment_status = 'completed', 
                    mpesa_receipt = COALESCE(?, mpesa_receipt),
                    completed_at = NOW(),
                    status = 'processed'
                WHERE id = ?
            ");
            $updateStmt->execute([$mpesa_receipt, $transaction_id]);
            
            // Add SMS credits to school balance
            $updateBalanceStmt = $this->pdo->prepare("
                UPDATE tblschoolinfo 
                SET sms_balance = sms_balance + ? 
                WHERE id = ?
            ");
            $updateBalanceStmt->execute([$transaction['sms_count'], $this->school_id]);
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'message' => 'Payment completed successfully',
                'sms_added' => $transaction['sms_count'],
                'new_balance' => $this->getBalance() + $transaction['sms_count']
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Complete Purchase Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Send SMS
     */
    public function sendSMS($recipients, $message, $sender_id = null) {
        try {
            // Validate balance
            $current_balance = $this->getBalance();
            
            // Convert single recipient to array
            if (!is_array($recipients)) {
                $recipients = [$recipients];
            }
            
            // Clean and validate phone numbers
            $valid_recipients = [];
            foreach ($recipients as $recipient) {
                $clean_number = $this->cleanPhoneNumber($recipient);
                if ($this->validatePhoneNumber($clean_number)) {
                    $valid_recipients[] = $clean_number;
                }
            }
            
            if (empty($valid_recipients)) {
                throw new Exception("No valid phone numbers provided");
            }
            
            // Calculate SMS count (1 SMS per 160 characters)
            $sms_count_per_message = ceil(strlen($message) / 160);
            $total_sms = count($valid_recipients) * $sms_count_per_message;
            
            if ($current_balance < $total_sms) {
                throw new Exception("Insufficient SMS balance. Required: $total_sms, Available: $current_balance");
            }
            
            // Start transaction
            $this->pdo->beginTransaction();
            
            // Prepare SMS log entries
            $logStmt = $this->pdo->prepare("
                INSERT INTO sms_logs (
                    school_id, api_key, recipient, message, 
                    sms_count, status, created_at
                ) VALUES (?, ?, ?, ?, ?, 'queued', NOW())
            ");
            
            $log_ids = [];
            foreach ($valid_recipients as $recipient) {
                $logStmt->execute([
                    $this->school_id,
                    $this->api_key,
                    $recipient,
                    $message,
                    $sms_count_per_message
                ]);
                $log_ids[] = $this->pdo->lastInsertId();
            }
            
            // Deduct from balance
            $updateBalanceStmt = $this->pdo->prepare("
                UPDATE tblschoolinfo 
                SET sms_balance = sms_balance - ? 
                WHERE id = ?
            ");
            $updateBalanceStmt->execute([$total_sms, $this->school_id]);
            
            $this->pdo->commit();
            
            // Send via gateway (async)
            $this->sendViaGateway($log_ids, $valid_recipients, $message, $sender_id);
            
            return [
                'success' => true,
                'message' => 'SMS queued successfully',
                'total_recipients' => count($valid_recipients),
                'total_sms' => $total_sms,
                'cost' => $total_sms * self::PRICE_PER_SMS,
                'balance_after' => $this->getBalance() - $total_sms,
                'message_id' => implode(',', $log_ids)
            ];
            
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log("Send SMS Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Send SMS via gateway
     */
    private function sendViaGateway($log_ids, $recipients, $message, $sender_id = null) {
        // This is where you integrate with your actual SMS gateway (OpenSMS)
        // For now, we'll simulate sending
        
        $sender = $sender_id ?: OPENSMS_SENDER_ID;
        
        foreach ($recipients as $index => $recipient) {
            $log_id = $log_ids[$index];
            
            // Simulate gateway call - replace with actual API call
            $gateway_response = $this->callOpenSMSGateway($recipient, $message, $sender);
            
            // Update log status
            $updateStmt = $this->pdo->prepare("
                UPDATE sms_logs 
                SET status = ?, 
                    gateway_response = ?,
                    sent_at = NOW(),
                    cost = ?
                WHERE id = ?
            ");
            
            $status = $gateway_response['success'] ? 'sent' : 'failed';
            $cost = $gateway_response['success'] ? self::PRICE_PER_SMS : 0;
            
            $updateStmt->execute([
                $status,
                json_encode($gateway_response),
                $cost,
                $log_id
            ]);
        }
    }
    
    /**
     * Call OpenSMS Gateway
     */
    private function callOpenSMSGateway($phone, $message, $sender_id) {
        // Replace with actual OpenSMS API implementation
        $api_url = OPENSMS_API_URL . '/send';
        $api_key = OPENSMS_API_KEY;
        
        $postData = [
            'api_key' => $api_key,
            'to' => $phone,
            'from' => $sender_id,
            'sms' => $message,
            'type' => 'text'
        ];
        
        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code == 200) {
            return [
                'success' => true,
                'response' => json_decode($response, true)
            ];
        } else {
            return [
                'success' => false,
                'error' => "Gateway error: HTTP $http_code",
                'response' => $response
            ];
        }
    }
    
    /**
     * Clean phone number to international format
     */
    private function cleanPhoneNumber($phone) {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Remove leading 0 and add 254 for Kenya
        if (strlen($phone) == 9 && substr($phone, 0, 1) == '7') {
            $phone = '254' . $phone;
        } elseif (strlen($phone) == 10 && substr($phone, 0, 1) == '0') {
            $phone = '254' . substr($phone, 1);
        } elseif (strlen($phone) == 12 && substr($phone, 0, 3) == '254') {
            // Already in correct format
        } else {
            // Assume it's already in correct format
        }
        
        return '+' . $phone;
    }
    
    /**
     * Validate phone number
     */
    private function validatePhoneNumber($phone) {
        // Basic validation for Kenyan phone numbers
        return preg_match('/^\+254[17]\d{8}$/', $phone);
    }
    
    /**
     * Get SMS logs
     */
    public function getSMSLogs($limit = 100, $offset = 0) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM sms_logs 
            WHERE school_id = ? 
            ORDER BY created_at DESC 
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$this->school_id, $limit, $offset]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get transaction history
     */
    public function getTransactionHistory($limit = 100, $offset = 0) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM sms_transactions 
            WHERE school_id = ? 
            ORDER BY created_at DESC 
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$this->school_id, $limit, $offset]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>