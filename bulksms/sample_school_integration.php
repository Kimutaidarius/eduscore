<?php
// sample_school_integration.php
// Sample code for schools to integrate with EduScore SMS API

class EduScoreSMS {
    private $api_key;
    private $api_secret;
    private $base_url = 'https://api.eduscore.com/sms/';
    
    public function __construct($api_key, $api_secret) {
        $this->api_key = $api_key;
        $this->api_secret = $api_secret;
    }
    
    /**
     * Send a single SMS
     */
    public function sendSMS($phone, $message) {
        $endpoint = $this->base_url . 'send';
        
        $data = [
            'phone' => $phone,
            'message' => $message
        ];
        
        $response = $this->makeRequest('POST', $endpoint, $data);
        
        if ($response['success']) {
            return [
                'success' => true,
                'message_id' => $response['message_id'],
                'credits_used' => $response['credits_used']
            ];
        }
        
        return [
            'success' => false,
            'error' => $response['error'] ?? 'Unknown error'
        ];
    }
    
    /**
     * Send bulk SMS
     */
    public function sendBulkSMS($recipients, $message, $batch_name = null) {
        $endpoint = $this->base_url . 'send-bulk';
        
        // Format recipients
        $formatted_recipients = [];
        foreach ($recipients as $recipient) {
            if (is_array($recipient)) {
                $formatted_recipients[] = $recipient;
            } else {
                $formatted_recipients[] = ['phone' => $recipient];
            }
        }
        
        $data = [
            'recipients' => $formatted_recipients,
            'message' => $message,
            'batch_name' => $batch_name
        ];
        
        $response = $this->makeRequest('POST', $endpoint, $data);
        
        if ($response['success']) {
            return [
                'success' => true,
                'batch_id' => $response['batch_id'],
                'recipients_count' => $response['recipients_count'],
                'total_credits' => $response['total_credits']
            ];
        }
        
        return [
            'success' => false,
            'error' => $response['error'] ?? 'Unknown error'
        ];
    }
    
    /**
     * Check SMS balance
     */
    public function checkBalance() {
        $endpoint = $this->base_url . 'balance';
        
        $response = $this->makeRequest('GET', $endpoint);
        
        if (isset($response['balance'])) {
            return [
                'success' => true,
                'balance' => $response['balance']
            ];
        }
        
        return [
            'success' => false,
            'error' => $response['error'] ?? 'Unknown error'
        ];
    }
    
    /**
     * Get message status
     */
    public function getMessageStatus($message_id) {
        $endpoint = $this->base_url . 'status?message_id=' . urlencode($message_id);
        
        $response = $this->makeRequest('GET', $endpoint);
        
        if (!isset($response['error'])) {
            return [
                'success' => true,
                'status' => $response
            ];
        }
        
        return [
            'success' => false,
            'error' => $response['error']
        ];
    }
    
    /**
     * Get message history
     */
    public function getHistory($page = 1, $limit = 50) {
        $endpoint = $this->base_url . 'history?page=' . $page . '&limit=' . $limit;
        
        $response = $this->makeRequest('GET', $endpoint);
        
        if (!isset($response['error'])) {
            return [
                'success' => true,
                'messages' => $response['messages'],
                'pagination' => $response['pagination']
            ];
        }
        
        return [
            'success' => false,
            'error' => $response['error']
        ];
    }
    
    /**
     * Make HTTP request
     */
    private function makeRequest($method, $url, $data = null) {
        $ch = curl_init();
        
        $headers = [
            'Authorization: Bearer ' . $this->api_key,
            'X-API-Secret: ' . $this->api_secret,
            'Content-Type: application/json'
        ];
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        if ($method == 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code == 200 || $http_code == 201) {
            return json_decode($response, true);
        } elseif ($http_code == 401) {
            return ['error' => 'Authentication failed. Check API credentials.'];
        } elseif ($http_code == 429) {
            return ['error' => 'Rate limit exceeded. Please try again later.'];
        } else {
            return ['error' => 'HTTP Error: ' . $http_code];
        }
    }
}

// ============================================
// USAGE EXAMPLES
// ============================================

// Initialize with your API credentials
$api_key = 'ES_your_generated_api_key_here';
$api_secret = 'your_api_secret_here';

$sms = new EduScoreSMS($api_key, $api_secret);

// Example 1: Check balance
$balance = $sms->checkBalance();
if ($balance['success']) {
    echo "Your SMS balance: " . $balance['balance'] . " credits\n";
} else {
    echo "Error: " . $balance['error'] . "\n";
}

// Example 2: Send single SMS
$result = $sms->sendSMS('0712345678', 'Hello from EduScore! Your child\'s exam results are now available.');
if ($result['success']) {
    echo "SMS sent! Message ID: " . $result['message_id'] . "\n";
    echo "Credits used: " . $result['credits_used'] . "\n";
} else {
    echo "Failed to send SMS: " . $result['error'] . "\n";
}

// Example 3: Send bulk SMS to multiple recipients
$recipients = [
    ['phone' => '0712345678', 'name' => 'John Doe'],
    ['phone' => '0723456789', 'name' => 'Jane Smith'],
    ['phone' => '0734567890', 'name' => 'Bob Johnson']
];

$bulk_result = $sms->sendBulkSMS($recipients, 'School will be closed on Monday for staff training.', 'School Closure Announcement');
if ($bulk_result['success']) {
    echo "Bulk SMS queued!\n";
    echo "Batch ID: " . $bulk_result['batch_id'] . "\n";
    echo "Recipients: " . $bulk_result['recipients_count'] . "\n";
    echo "Total credits: " . $bulk_result['total_credits'] . "\n";
}

// Example 4: Check message status
$status = $sms->getMessageStatus('MSG_1234567890');
if ($status['success']) {
    echo "Message status: " . $status['status']['status'] . "\n";
    if ($status['status']['status'] == 'delivered') {
        echo "Delivered at: " . $status['status']['delivered_at'] . "\n";
    }
}

// Example 5: Get message history
$history = $sms->getHistory(1, 10);
if ($history['success']) {
    echo "Recent messages:\n";
    foreach ($history['messages'] as $msg) {
        echo "- To: " . $msg['recipient_phone'] . ", Status: " . $msg['status'] . "\n";
    }
    echo "Page " . $history['pagination']['page'] . " of " . $history['pagination']['pages'] . "\n";
}