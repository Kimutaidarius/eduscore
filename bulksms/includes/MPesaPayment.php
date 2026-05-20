<?php
// includes/MPesaPayment.php
class MPesaPayment {
    private $conn;
    private $business_shortcode;
    private $passkey;
    private $consumer_key;
    private $consumer_secret;
    private $environment;
    
    public function __construct($db) {
        $this->conn = $db;
        $this->business_shortcode = MPESA_BUSINESS_SHORTCODE;
        $this->passkey = MPESA_PASSKEY;
        $this->consumer_key = MPESA_CONSUMER_KEY;
        $this->consumer_secret = MPESA_CONSUMER_SECRET;
        $this->environment = MPESA_ENVIRONMENT;
    }
    
    public function stkPush($school_id, $phone, $package_id) {
        // Get package details
        $package = $this->getPackageDetails($package_id);
        if (!$package) {
            return ['success' => false, 'message' => 'Invalid package'];
        }
        
        $amount = $package['price'];
        $credits = $package['credits'] + $package['bonus_credits'];
        
        // Generate transaction reference
        $transaction_ref = 'ES' . time() . rand(1000, 9999);
        
        // Get access token
        $token = $this->getAccessToken();
        if (!$token) {
            return ['success' => false, 'message' => 'Failed to get access token'];
        }
        
        // Format phone number (remove 0 or +254)
        $phone = $this->formatPhoneNumber($phone);
        
        // Prepare STK Push request
        $timestamp = date('YmdHis');
        $password = base64_encode($this->business_shortcode . $this->passkey . $timestamp);
        
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $this->getApiUrl('stkpush/v1/processrequest'));
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ]);
        
        $curl_post_data = [
            'BusinessShortCode' => $this->business_shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => $amount,
            'PartyA' => $phone,
            'PartyB' => $this->business_shortcode,
            'PhoneNumber' => $phone,
            'CallBackURL' => $this->getCallbackUrl(),
            'AccountReference' => $transaction_ref,
            'TransactionDesc' => 'EduScore SMS Credits'
        ];
        
        $data_string = json_encode($curl_post_data);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
        
        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        if ($http_code == 200) {
            $result = json_decode($response, true);
            
            if (isset($result['CheckoutRequestID'])) {
                // Save transaction
                $this->saveTransaction($school_id, $package_id, $phone, $amount, $credits, 
                                       $result['CheckoutRequestID'], $transaction_ref);
                
                return [
                    'success' => true,
                    'checkout_request_id' => $result['CheckoutRequestID'],
                    'message' => 'STK Push sent. Please check your phone and enter PIN.'
                ];
            }
        }
        
        return ['success' => false, 'message' => 'STK Push failed'];
    }
    
    private function getAccessToken() {
        $url = $this->getApiUrl('oauth/v1/generate?grant_type=client_credentials');
        
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        $credentials = base64_encode($this->consumer_key . ':' . $this->consumer_secret);
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . $credentials]);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        
        $result = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        if ($http_code == 200) {
            $token_data = json_decode($result);
            return $token_data->access_token;
        }
        
        return false;
    }
    
    private function getApiUrl($endpoint) {
        if ($this->environment == 'sandbox') {
            return 'https://sandbox.safaricom.co.ke/mpesa/' . $endpoint;
        }
        return 'https://api.safaricom.co.ke/mpesa/' . $endpoint;
    }
    
    private function getCallbackUrl() {
        return SMS_API_BASE_URL . 'mpesa_callback.php';
    }
    
    private function formatPhoneNumber($phone) {
        // Remove any non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // If starts with 0, replace with 254
        if (substr($phone, 0, 1) == '0') {
            $phone = '254' . substr($phone, 1);
        }
        // If starts with 7, add 254
        elseif (substr($phone, 0, 1) == '7') {
            $phone = '254' . $phone;
        }
        
        return $phone;
    }
    
    private function getPackageDetails($package_id) {
        $query = "SELECT * FROM sms_packages WHERE id = ? AND is_active = 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $package_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        return null;
    }
    
    private function saveTransaction($school_id, $package_id, $phone, $amount, $credits, $checkout_id, $ref) {
        $query = "INSERT INTO sms_mpesa_transactions 
                  (school_id, package_id, phone_number, amount, credits_purchased, 
                   checkout_request_id, transaction_id, status) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("iisdiss", $school_id, $package_id, $phone, $amount, $credits, 
                         $checkout_id, $ref);
        $stmt->execute();
    }
    
    public function processCallback($data) {
        // Verify the callback data
        if (isset($data['Body']['stkCallback'])) {
            $callback = $data['Body']['stkCallback'];
            $checkout_id = $callback['CheckoutRequestID'];
            $result_code = $callback['ResultCode'];
            $result_desc = $callback['ResultDesc'];
            
            // Find transaction
            $query = "SELECT * FROM sms_mpesa_transactions WHERE checkout_request_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("s", $checkout_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $transaction = $result->fetch_assoc();
                
                if ($result_code == 0) {
                    // Success
                    $mpesa_receipt = $callback['CallbackMetadata']['Item'][1]['Value'];
                    
                    // Update transaction
                    $update = "UPDATE sms_mpesa_transactions 
                               SET status = 'completed', result_code = ?, result_desc = ?, 
                                   mpesa_receipt = ?, completed_at = NOW(), callback_data = ?
                               WHERE id = ?";
                    
                    $callback_json = json_encode($data);
                    $stmt = $this->conn->prepare($update);
                    $stmt->bind_param("isssi", $result_code, $result_desc, $mpesa_receipt, 
                                     $callback_json, $transaction['id']);
                    $stmt->execute();
                    
                    // Add credits to school
                    $this->addCredits($transaction['school_id'], $transaction['credits_purchased']);
                    
                    return ['success' => true];
                } else {
                    // Failed
                    $update = "UPDATE sms_mpesa_transactions 
                               SET status = 'failed', result_code = ?, result_desc = ?, 
                                   callback_data = ? WHERE id = ?";
                    
                    $callback_json = json_encode($data);
                    $stmt = $this->conn->prepare($update);
                    $stmt->bind_param("issi", $result_code, $result_desc, $callback_json, 
                                     $transaction['id']);
                    $stmt->execute();
                    
                    return ['success' => false, 'message' => $result_desc];
                }
            }
        }
        
        return ['success' => false, 'message' => 'Invalid callback data'];
    }
    
    private function addCredits($school_id, $credits) {
        // Update credits balance
        $query = "UPDATE sms_credits 
                  SET credits_balance = credits_balance + ?, 
                      total_credits_purchased = total_credits_purchased + ?,
                      last_recharged_at = NOW()
                  WHERE school_id = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("iii", $credits, $credits, $school_id);
        $stmt->execute();
        
        // If no rows updated, insert new record
        if ($stmt->affected_rows == 0) {
            $insert = "INSERT INTO sms_credits (school_id, credits_balance, total_credits_purchased) 
                       VALUES (?, ?, ?)";
            $stmt = $this->conn->prepare($insert);
            $stmt->bind_param("iii", $school_id, $credits, $credits);
            $stmt->execute();
        }
    }
}