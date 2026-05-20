<?php
// includes/SMSSender.php
class SMSSender {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function sendSingle($school_id, $api_key_id, $data) {
        // Validate required fields
        if (!isset($data['phone']) || !isset($data['message'])) {
            return ['success' => false, 'error' => 'Phone and message are required'];
        }
        
        $phone = $this->formatPhone($data['phone']);
        $message = trim($data['message']);
        $message_id = $this->generateMessageId();
        
        // Calculate SMS credits
        $sms_count = $this->calculateSMSCount($message);
        $credits_needed = $sms_count;
        
        // Check balance
        if (!$this->hasEnoughCredits($school_id, $credits_needed)) {
            return ['success' => false, 'error' => 'Insufficient SMS credits'];
        }
        
        // Send SMS (integrate with your SMS gateway)
        $sent = $this->sendViaGateway($phone, $message, $message_id);
        
        if ($sent['success']) {
            // Deduct credits
            $this->deductCredits($school_id, $credits_needed);
            
            // Save message record
            $this->saveMessage([
                'school_id' => $school_id,
                'api_key_id' => $api_key_id,
                'message_id' => $message_id,
                'recipient_phone' => $phone,
                'message_content' => $message,
                'sms_count' => $sms_count,
                'credits_used' => $credits_needed,
                'status' => 'sent',
                'sent_at' => date('Y-m-d H:i:s')
            ]);
            
            return [
                'success' => true,
                'message_id' => $message_id,
                'credits_used' => $credits_needed,
                'status' => 'sent'
            ];
        }
        
        return ['success' => false, 'error' => $sent['error']];
    }
    
    public function sendBulk($school_id, $api_key_id, $data) {
        if (!isset($data['recipients']) || !is_array($data['recipients']) || !isset($data['message'])) {
            return ['success' => false, 'error' => 'Recipients array and message are required'];
        }
        
        $recipients = $data['recipients'];
        $message = trim($data['message']);
        $batch_name = isset($data['batch_name']) ? $data['batch_name'] : 'Bulk SMS ' . date('Y-m-d H:i');
        
        // Validate recipients
        $valid_recipients = [];
        foreach ($recipients as $recipient) {
            if (isset($recipient['phone'])) {
                $phone = $this->formatPhone($recipient['phone']);
                if ($phone) {
                    $valid_recipients[] = [
                        'phone' => $phone,
                        'name' => $recipient['name'] ?? ''
                    ];
                }
            }
        }
        
        if (empty($valid_recipients)) {
            return ['success' => false, 'error' => 'No valid recipients'];
        }
        
        $recipient_count = count($valid_recipients);
        $sms_count = $this->calculateSMSCount($message);
        $total_credits_needed = $recipient_count * $