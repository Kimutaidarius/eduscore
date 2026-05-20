<?php
// config/opensms_gateway.php

class OpenSMSGateway {
    private $apiToken;
    private $senderId;
    private $baseUrl;
    private $mockMode;
    
    public function __construct($apiToken, $senderId = 'EDUSCORE') {
        $this->apiToken = $apiToken;
        $this->senderId = $senderId;
        $this->baseUrl = 'https://opensms.co.ke/api/v3'; // Correct base URL
        // Check if token is valid (not the placeholder)
        $this->mockMode = (strpos($apiToken, 'YOUR_') !== false || strlen($apiToken) < 10);
        
        if ($this->mockMode) {
            error_log("OpenSMS Gateway running in MOCK mode - no real SMS will be sent");
        }
    }

    /**
     * Send a single SMS via OpenSMS API v3
     */
    public function sendSMS($phone, $message, $scheduleTime = null) {
        // Format phone number
        $phone = $this->formatPhoneNumber($phone);
        
        // If in mock mode, simulate successful send
        if ($this->mockMode) {
            return $this->mockSendSMS($phone, $message);
        }
        
        // Prepare request data according to OpenSMS API v3 documentation
        $data = [
            'recipient' => $phone,  // Can be single number or comma-separated for multiple
            'sender_id' => $this->senderId,
            'type' => 'plain',       // Required: must be 'plain' for text messages
            'message' => $message
        ];
        
        // Add scheduling if specified (format: Y-m-d H:i)
        if ($scheduleTime) {
            // Convert schedule_time to proper format if needed
            $data['schedule_time'] = date('Y-m-d H:i', strtotime($scheduleTime));
        }
        
        // Make API request with Bearer token authentication
        $response = $this->makeRequest('/sms/send', $data);
        
        // Parse response based on documentation
        $success = isset($response['status']) && $response['status'] === 'success';
        
        return [
            'success' => $success,
            'message_id' => $response['data']['uid'] ?? $response['uid'] ?? null,
            'cost' => 0.70, // Cost per SMS (you may get this from response)
            'response' => $response,
            'error' => !$success ? ($response['message'] ?? 'Unknown error') : null
        ];
    }
    
    /**
     * Send bulk SMS to multiple recipients
     */
    public function sendBulkSMS($phones, $message, $scheduleTime = null) {
        // Format all phone numbers and join with comma
        $formattedPhones = array_map([$this, 'formatPhoneNumber'], $phones);
        $recipients = implode(',', $formattedPhones);
        
        if ($this->mockMode) {
            return [
                'success' => true,
                'batch_id' => 'BATCH_' . time(),
                'total_cost' => count($phones) * 0.70,
                'responses' => [
                    'status' => 'success',
                    'count' => count($phones)
                ]
            ];
        }
        
        // Prepare request data
        $data = [
            'recipient' => $recipients,  // Comma-separated numbers
            'sender_id' => $this->senderId,
            'type' => 'plain',
            'message' => $message
        ];
        
        if ($scheduleTime) {
            $data['schedule_time'] = date('Y-m-d H:i', strtotime($scheduleTime));
        }
        
        $response = $this->makeRequest('/sms/send', $data);
        
        $success = isset($response['status']) && $response['status'] === 'success';
        
        return [
            'success' => $success,
            'batch_id' => $response['data']['batch_id'] ?? time(),
            'total_cost' => count($phones) * 0.70,
            'responses' => $response,
            'error' => !$success ? ($response['message'] ?? 'Unknown error') : null
        ];
    }
    
    /**
     * Send campaign using contact list
     */
    public function sendCampaign($contactListId, $message, $senderId = null, $scheduleTime = null) {
        if ($this->mockMode) {
            return [
                'success' => true,
                'campaign_id' => 'CAMP_' . time()
            ];
        }
        
        $data = [
            'contact_list_id' => $contactListId,
            'sender_id' => $senderId ?? $this->senderId,
            'type' => 'plain',
            'message' => $message
        ];
        
        if ($scheduleTime) {
            $data['schedule_time'] = date('Y-m-d H:i', strtotime($scheduleTime));
        }
        
        $response = $this->makeRequest('/sms/campaign', $data);
        
        $success = isset($response['status']) && $response['status'] === 'success';
        
        return [
            'success' => $success,
            'campaign_id' => $response['data']['uid'] ?? null,
            'response' => $response,
            'error' => !$success ? ($response['message'] ?? 'Unknown error') : null
        ];
    }
    
    /**
     * Check SMS balance
     */
    public function checkBalance() {
        if ($this->mockMode) {
            return [
                'success' => true,
                'balance' => 10000,
                'currency' => 'KES'
            ];
        }
        
        // Note: According to docs, there's no specific balance endpoint
        // You may need to check your account dashboard or contact support
        return [
            'success' => true,
            'balance' => 'Check dashboard',
            'currency' => 'KES',
            'note' => 'Balance checking not available via API. Please check your OpenSMS dashboard.'
        ];
    }
    
    /**
     * Get message status by UID
     */
    public function getMessageStatus($uid) {
        if ($this->mockMode) {
            return [
                'success' => true,
                'message_status' => 'delivered',
                'delivery_status' => 'success'
            ];
        }
        
        $response = $this->makeRequest('/sms/' . $uid, [], 'GET');
        
        $success = isset($response['status']) && $response['status'] === 'success';
        
        return [
            'success' => $success,
            'message_status' => $response['data']['status'] ?? 'unknown',
            'delivery_status' => $response['data']['delivery_status'] ?? 'unknown',
            'response' => $response,
            'error' => !$success ? ($response['message'] ?? 'Unknown error') : null
        ];
    }
    
    /**
     * Get all messages
     */
    public function getAllMessages() {
        if ($this->mockMode) {
            return [
                'success' => true,
                'data' => []
            ];
        }
        
        $response = $this->makeRequest('/sms/', [], 'GET');
        return $response;
    }
    
    /**
     * Get campaign status by UID
     */
    public function getCampaignStatus($uid) {
        if ($this->mockMode) {
            return [
                'success' => true,
                'campaign_status' => 'completed'
            ];
        }
        
        $response = $this->makeRequest('/campaign/' . $uid, [], 'GET');
        return $response;
    }
    
    /**
     * Mock send for testing
     */
    private function mockSendSMS($phone, $message) {
        // Simulate API delay
        usleep(500000); // 0.5 seconds
        
        $uid = uniqid() . rand(1000, 9999);
        
        // Log the mock send
        error_log("MOCK SMS [{$uid}] To: {$phone}, Message: " . substr($message, 0, 50) . "...");
        
        return [
            'success' => true,
            'message_id' => $uid,
            'cost' => 0.70,
            'response' => [
                'status' => 'success',
                'data' => [
                    'uid' => $uid,
                    'recipient' => $phone,
                    'message' => $message,
                    'status' => 'sent'
                ]
            ],
            'error' => null
        ];
    }
    
    /**
     * Format phone number to international format
     */
    private function formatPhoneNumber($phone) {
        // Remove any non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Convert to 254 format
        if (substr($phone, 0, 1) === '0') {
            $phone = '254' . substr($phone, 1);
        } elseif (substr($phone, 0, 3) !== '254' && strlen($phone) === 9) {
            $phone = '254' . $phone;
        }
        
        // Ensure it's exactly 12 digits (254 + 9 digits)
        if (strlen($phone) > 12) {
            $phone = substr($phone, 0, 12);
        }
        
        return $phone;
    }
    
    /**
     * Make HTTP request to OpenSMS API
     */
    private function makeRequest($endpoint, $data = [], $method = 'POST') {
        $url = $this->baseUrl . $endpoint;
        
        $ch = curl_init();
        
        $headers = [
            'Authorization: Bearer ' . $this->apiToken,
            'Content-Type: application/json',
            'Accept: application/json'
        ];
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } else {
            // GET request
            if (!empty($data)) {
                $url .= '?' . http_build_query($data);
            }
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        }
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false, // Set to true in production
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        // Log API call for debugging
        error_log("OpenSMS API Call: {$method} {$url} - HTTP {$httpCode}");
        
        if ($curlError) {
            error_log("OpenSMS API CURL Error: " . $curlError);
            return [
                'status' => 'error',
                'message' => 'Connection error: ' . $curlError
            ];
        }
        
        if ($httpCode !== 200) {
            error_log("OpenSMS API HTTP Error {$httpCode}: " . $response);
            
            // Try to parse error response
            $errorResponse = json_decode($response, true);
            if ($errorResponse && isset($errorResponse['message'])) {
                return $errorResponse;
            }
            
            return [
                'status' => 'error',
                'message' => 'API returned error code: ' . $httpCode,
                'response' => $response
            ];
        }
        
        $decodedResponse = json_decode($response, true);
        
        if ($decodedResponse === null) {
            error_log("OpenSMS API Invalid JSON response: " . $response);
            return [
                'status' => 'error',
                'message' => 'Invalid JSON response'
            ];
        }
        
        return $decodedResponse;
    }
}

/**
 * Factory function to get SMS gateway instance
 */
function getSmsGateway() {
    static $gateway = null;
    
    if ($gateway === null) {
        // Use the API token you provided
        $apiToken = '302|zVi4PHf2NYSlVhivKIaTPQuyGiaIguouaPmQMuGB1e15a0e2';
        
        $gateway = new OpenSMSGateway(
            $apiToken,
            'OPENSMS' // Your sender ID
        );
    }
    
    return $gateway;
}
?>