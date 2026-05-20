<?php
// includes/AlertHelper.php

require_once __DIR__ . '/config.php';

class AlertHelper {
    private $dbh;
    
    public function __construct($dbh) {
        $this->dbh = $dbh;
    }
    
    /**
     * Send WhatsApp message using Africa's Talking
     */
    public function sendWhatsApp($phone, $message, $school_id = null) {
        // Format phone number to international format (2547XXXXXXXX)
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (substr($phone, 0, 1) === '0') {
            $phone = '254' . substr($phone, 1);
        }
        if (substr($phone, 0, 4) !== '254') {
            $phone = '254' . $phone;
        }
        
        // Shorten message for WhatsApp (they have limits)
        $message = substr($message, 0, 1600);
        
        $username = defined('AFRICASTALKING_USERNAME') ? AFRICASTALKING_USERNAME : 'sandbox';
        $apiKey = defined('AFRICASTALKING_API_KEY') ? AFRICASTALKING_API_KEY : '';
        
        if (!$apiKey) {
            error_log("WhatsApp: No API key configured");
            $this->logWhatsAppMessage($phone, $message, 'failed', 'no_api_key', $school_id);
            return false;
        }
        
        $url = "https://api.africastalking.com/version1/messaging";
        
        $data = [
            'username' => $username,
            'to' => $phone,
            'message' => $message,
            'from' => 'WHATSAPP' // Must be enabled on your Africa's Talking account
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apiKey: {$apiKey}",
            "Content-Type: application/x-www-form-urlencoded",
            "Accept: application/json"
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        $result = json_decode($response, true);
        $status = ($http_code === 201 || $http_code === 200) ? 'sent' : 'failed';
        
        // Log the message
        $this->logWhatsAppMessage($phone, $message, $status, $response, $school_id);
        
        if ($curl_error || $status === 'failed') {
            error_log("WhatsApp Error: " . ($curl_error ?: $response));
            return false;
        }
        
        error_log("WhatsApp sent to {$phone}: " . substr($message, 0, 100) . "...");
        return true;
    }
    
    /**
     * Send SMS as fallback when WhatsApp fails
     */
    public function sendWithFallback($phone, $message, $school_id = null, $priority = 'whatsapp_first') {
        $sent = false;
        
        if ($priority === 'whatsapp_first') {
            // Try WhatsApp first
            $sent = $this->sendWhatsApp($phone, $message, $school_id);
            
            // Fallback to SMS if WhatsApp fails
            if (!$sent) {
                $sent = $this->sendSMS($phone, $message, $school_id);
                error_log("WhatsApp failed, sent SMS fallback to {$phone}");
            }
        } else {
            // Try SMS first
            $sent = $this->sendSMS($phone, $message, $school_id);
            
            // Try WhatsApp as secondary if SMS fails
            if (!$sent) {
                $sent = $this->sendWhatsApp($phone, $message, $school_id);
            }
        }
        
        return $sent;
    }
    
    /**
     * Send SMS using Africa's Talking
     */
    private function sendSMS($phone, $message, $school_id = null) {
        // Format phone number
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (substr($phone, 0, 1) === '0') {
            $phone = '254' . substr($phone, 1);
        }
        if (substr($phone, 0, 4) !== '254') {
            $phone = '254' . $phone;
        }
        
        // Shorten message for SMS (160 chars limit, but we'll handle multi-part)
        $message = substr($message, 0, 480);
        
        $username = defined('AFRICASTALKING_USERNAME') ? AFRICASTALKING_USERNAME : 'sandbox';
        $apiKey = defined('AFRICASTALKING_API_KEY') ? AFRICASTALKING_API_KEY : '';
        
        if (!$apiKey) {
            error_log("SMS: No API key configured");
            return false;
        }
        
        $url = "https://api.africastalking.com/version1/messaging";
        
        $data = [
            'username' => $username,
            'to' => $phone,
            'message' => $message,
            'bulkSMSMode' => 0
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apiKey: {$apiKey}",
            "Content-Type: application/x-www-form-urlencoded",
            "Accept: application/json"
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $status = ($http_code === 201 || $http_code === 200) ? 'sent' : 'failed';
        
        if ($status === 'sent') {
            error_log("SMS sent to {$phone}");
            return true;
        }
        
        error_log("SMS failed for {$phone}: " . $response);
        return false;
    }
    
    /**
     * Send payment failure alert
     */
    public function sendPaymentFailedAlert($school_id, $amount, $reason = '', $payment_type = '', $reference = '') {
        // Get school contact info
        $stmt = $this->dbh->prepare("SELECT school_name, school_phone, school_email FROM tblschoolinfo WHERE id = ?");
        $stmt->execute([$school_id]);
        $school = $stmt->fetch();
        
        if (!$school) {
            error_log("Alert: School not found for ID: {$school_id}");
            return false;
        }
        
        $school_name = $school['school_name'];
        $phone = $school['school_phone'];
        $email = $school['school_email'];
        
        // Build WhatsApp message (more engaging format)
        $whatsapp_message = "📢 *EduScore Payment Alert*\n\n";
        $whatsapp_message .= "Hello {$school_name},\n\n";
        $whatsapp_message .= "❌ Your payment of *KES " . number_format($amount, 2) . "* was not completed.\n\n";
        $whatsapp_message .= "📋 *Details:*\n";
        $whatsapp_message .= "• Type: " . ucfirst($payment_type) . "\n";
        if ($reference) {
            $whatsapp_message .= "• Reference: {$reference}\n";
        }
        $whatsapp_message .= "• Reason: {$reason}\n\n";
        $whatsapp_message .= "🔁 *What to do:*\n";
        $whatsapp_message .= "1. Retry payment now\n";
        $whatsapp_message .= "2. Check your M-PESA balance\n";
        $whatsapp_message .= "3. Contact support if issue persists\n\n";
        $whatsapp_message .= "👉 *Pay now:* https://eduscore.co.ke/billing/pay.php\n\n";
        $whatsapp_message .= "_This is an automated message. Need help? Reply to this message._";
        
        // Build SMS message (shorter)
        $sms_message = "EduScore: Payment of KES " . number_format($amount, 2) . " failed. Reason: {$reason}. Retry now: https://eduscore.co.ke/billing/pay.php";
        
        // Send via WhatsApp with SMS fallback
        $sent = $this->sendWithFallback($phone, $whatsapp_message, $school_id, 'whatsapp_first');
        
        // Also send email
        if (!empty($email)) {
            $this->sendEmail($email, $school_name, "Payment Failed - EduScore", $sms_message);
        }
        
        // Save to database
        $this->logAlert($school_id, $sms_message, 'payment_failed', $reference);
        
        error_log("Payment failure alert sent for school {$school_id}");
        return $sent;
    }
    
    /**
     * Send subscription expiry reminder
     */
    public function sendExpiryReminder($school_id, $days_left, $expiry_date, $amount = null) {
        // Get school contact info
        $stmt = $this->dbh->prepare("SELECT school_name, school_phone, school_email FROM tblschoolinfo WHERE id = ?");
        $stmt->execute([$school_id]);
        $school = $stmt->fetch();
        
        if (!$school) {
            return false;
        }
        
        $school_name = $school['school_name'];
        $phone = $school['school_phone'];
        $email = $school['school_email'];
        
        if ($days_left > 0) {
            // WhatsApp message for upcoming expiry
            $whatsapp_message = "📢 *EduScore Subscription Reminder*\n\n";
            $whatsapp_message .= "Hello {$school_name},\n\n";
            $whatsapp_message .= "Your subscription expires in *{$days_left} day(s)*.\n\n";
            $whatsapp_message .= "📅 *Expiry Date:* " . date('M d, Y', strtotime($expiry_date)) . "\n";
            if ($amount) {
                $whatsapp_message .= "💰 *Renewal Amount:* KES " . number_format($amount, 2) . "\n\n";
            }
            $whatsapp_message .= "⚠️ Renew now to avoid service interruption.\n\n";
            $whatsapp_message .= "👉 *Renew Now:* https://eduscore.co.ke/billing/renew.php\n\n";
            $whatsapp_message .= "_Need help? Reply to this message._";
            
            $sms_message = "EduScore: Your subscription expires in {$days_left} day(s). Renew now: https://eduscore.co.ke/billing/renew.php";
            
        } else {
            // WhatsApp message for expired
            $whatsapp_message = "⚠️ *EduScore Subscription Expired*\n\n";
            $whatsapp_message .= "Hello {$school_name},\n\n";
            $whatsapp_message .= "Your subscription expired on " . date('M d, Y', strtotime($expiry_date)) . ".\n\n";
            $whatsapp_message .= "Your access has been limited.\n\n";
            $whatsapp_message .= "👉 *Renew Now:* https://eduscore.co.ke/billing/renew.php\n\n";
            $whatsapp_message .= "_Renew today to restore full access._";
            
            $sms_message = "EduScore: Your subscription has expired. Renew now: https://eduscore.co.ke/billing/renew.php";
        }
        
        // Send via WhatsApp with SMS fallback
        $sent = $this->sendWithFallback($phone, $whatsapp_message, $school_id, 'whatsapp_first');
        
        // Send email as well
        if (!empty($email)) {
            $subject = $days_left > 0 ? "Subscription Expires in {$days_left} Days - EduScore" : "Subscription Expired - EduScore";
            $this->sendEmail($email, $school_name, $subject, $sms_message);
        }
        
        // Save to database
        $this->logAlert($school_id, $sms_message, 'expiry_reminder', null, $days_left);
        
        error_log("Expiry reminder sent for school {$school_id}: {$days_left} days left");
        return $sent;
    }
    
    /**
     * Send payment success confirmation (optional)
     */
    public function sendPaymentSuccessAlert($school_id, $amount, $receipt_code, $payment_type = '') {
        $stmt = $this->dbh->prepare("SELECT school_name, school_phone, school_email FROM tblschoolinfo WHERE id = ?");
        $stmt->execute([$school_id]);
        $school = $stmt->fetch();
        
        if (!$school) {
            return false;
        }
        
        $school_name = $school['school_name'];
        $phone = $school['school_phone'];
        
        $whatsapp_message = "✅ *Payment Received - EduScore*\n\n";
        $whatsapp_message .= "Hello {$school_name},\n\n";
        $whatsapp_message .= "We have received your payment of *KES " . number_format($amount, 2) . "*.\n\n";
        $whatsapp_message .= "📋 *Details:*\n";
        $whatsapp_message .= "• Type: " . ucfirst($payment_type) . "\n";
        $whatsapp_message .= "• Receipt: {$receipt_code}\n\n";
        $whatsapp_message .= "Your subscription is now active.\n\n";
        $whatsapp_message .= "📄 *Download Receipt:* https://eduscore.co.ke/billing/receipt.php?ref={$receipt_code}\n\n";
        $whatsapp_message .= "_Thank you for choosing EduScore!_";
        
        $sms_message = "EduScore: Payment of KES " . number_format($amount, 2) . " received. Receipt: {$receipt_code}";
        
        return $this->sendWithFallback($phone, $whatsapp_message, $school_id, 'whatsapp_first');
    }
    
    /**
     * Log WhatsApp message to database
     */
    private function logWhatsAppMessage($phone, $message, $status, $response = null, $school_id = null) {
        try {
            // Create table if not exists
            $this->dbh->exec("
                CREATE TABLE IF NOT EXISTS whatsapp_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    school_id INT NULL,
                    phone VARCHAR(20) NOT NULL,
                    message TEXT NOT NULL,
                    status VARCHAR(20) DEFAULT 'pending',
                    response TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_phone (phone),
                    INDEX idx_status (status),
                    INDEX idx_created (created_at)
                )
            ");
            
            $stmt = $this->dbh->prepare("
                INSERT INTO whatsapp_logs (school_id, phone, message, status, response)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$school_id, $phone, $message, $status, $response]);
        } catch (Exception $e) {
            error_log("Failed to log WhatsApp message: " . $e->getMessage());
        }
    }
    
    /**
     * Log alert to database
     */
    private function logAlert($school_id, $message, $type, $reference = null, $days_left = null) {
        try {
            $this->dbh->exec("
                CREATE TABLE IF NOT EXISTS payment_alerts (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    school_id INT NOT NULL,
                    alert_type VARCHAR(50) NOT NULL,
                    message TEXT NOT NULL,
                    reference VARCHAR(100),
                    days_left INT,
                    status VARCHAR(20) DEFAULT 'sent',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_school (school_id),
                    INDEX idx_type (alert_type)
                )
            ");
            
            $stmt = $this->dbh->prepare("
                INSERT INTO payment_alerts (school_id, alert_type, message, reference, days_left, status)
                VALUES (?, ?, ?, ?, ?, 'sent')
            ");
            $stmt->execute([$school_id, $type, $message, $reference, $days_left]);
        } catch (Exception $e) {
            error_log("Failed to log alert: " . $e->getMessage());
        }
    }
    
    /**
     * Send Email (fallback)
     */
    private function sendEmail($to, $school_name, $subject, $message) {
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8" . "\r\n";
        $headers .= "From: " . (defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'alerts@eduscore.co.ke') . "\r\n";
        
        $full_message = "Dear {$school_name},\n\n" . $message . "\n\n--\nEduScore System\nhttps://eduscore.co.ke";
        
        return mail($to, $subject, $full_message, $headers);
    }
}
?>