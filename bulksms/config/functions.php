<?php
// config/functions.php

require_once __DIR__ . '/opensms_gateway.php';

/**
 * Send SMS via gateway (supports multiple providers)
 */
function sendSmsViaGateway($phone, $message, $sender_id = 'EduScore') {
    $gateway = getSmsGateway();
    
    // Check if using OpenSMS
    if ($gateway instanceof OpenSMSGateway) {
        $result = $gateway->sendSMS($phone, $message);
        return [
            'success' => $result['success'],
            'message_id' => $result['message_id'] ?? null,
            'cost' => $result['cost'] ?? OPENSMS_PRICE_PER_SMS
        ];
    } else {
        // Fallback to TextBelt
        $url = SMS_GATEWAY_URL;
        
        $data = [
            'phone' => $phone,
            'message' => $message,
            'key' => SMS_GATEWAY_KEY,
            'sender' => $sender_id
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code == 200) {
            return json_decode($response, true);
        }
        
        return ['success' => false, 'error' => 'Gateway error'];
    }
}

/**
 * Get SMS gateway balance
 */
function getSmsGatewayBalance() {
    $gateway = getSmsGateway();
    
    if ($gateway instanceof OpenSMSGateway) {
        return $gateway->checkBalance();
    }
    
    return ['success' => true, 'balance' => 'N/A'];
}

/**
 * Process M-Pesa payment for SMS credit
 */
function processMpesaTopUp($phone, $amount, $userId, $pdo) {
    $gateway = getSmsGateway();
    
    if ($gateway instanceof OpenSMSGateway) {
        // Use OpenSMS built-in M-Pesa
        $result = $gateway->initiateMpesaPayment($phone, $amount, $userId);
        
        if ($result['success']) {
            // Log the transaction
            $stmt = $pdo->prepare("
                INSERT INTO mpesa_transactions 
                (user_id, phone, amount, transaction_id, checkout_request_id, status, created_at) 
                VALUES (?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([
                $userId, 
                $phone, 
                $amount, 
                $result['transaction_id'] ?? null,
                $result['checkout_request_id'] ?? null
            ]);
        }
        
        return $result;
    } else {
        // Use your existing M-Pesa integration
        return initiateMpesaStkPush($phone, $amount, 'SMS Topup', 'SMS credit purchase');
    }
}

/**
 * Generate a unique API key
 */
function generateApiKey() {
    return 'esk_' . bin2hex(random_bytes(24));
}

/**
 * Generate API secret
 */
function generateApiSecret() {
    return bin2hex(random_bytes(32));
}

/**
 * Generate unique message ID
 */
function generateMessageId() {
    return 'MSG' . time() . rand(1000, 9999);
}

/**
 * Validate phone number (basic)
 */
function validatePhone($phone) {
    // Remove any non-numeric characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Check if it's a valid phone number (basic check)
    if (strlen($phone) >= 10 && strlen($phone) <= 15) {
        return $phone;
    }
    return false;
}

/**
 * Get user by ID
 */
function getUserById($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Redirect if not logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/login.php');
        exit();
    }
}

/**
 * Check if admin is logged in
 */
function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']);
}

/**
 * Redirect if not admin
 */
function requireAdmin() {
    if (!isAdminLoggedIn()) {
        header('Location: ' . APP_URL . '/admin/login.php');
        exit();
    }
}

/**
 * Get user's API key
 */
function getUserApiKey($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT * FROM api_keys WHERE user_id = ? AND status = 'active' ORDER BY id DESC LIMIT 1");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

/**
 * Update SMS balance
 */
function updateSmsBalance($pdo, $user_id, $cost) {
    $stmt = $pdo->prepare("UPDATE users SET sms_balance = sms_balance - ? WHERE id = ?");
    return $stmt->execute([$cost, $user_id]);
}

/**
 * Check if user has enough balance
 */
function hasEnoughBalance($pdo, $user_id, $required) {
    $stmt = $pdo->prepare("SELECT sms_balance FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    return $user && $user['sms_balance'] >= $required;
}

/**
 * Log API request
 */
function logApiRequest($pdo, $user_id, $api_key_id, $endpoint, $method, $ip, $user_agent, $request_data, $response_code) {
    $stmt = $pdo->prepare("
        INSERT INTO api_requests (user_id, api_key_id, endpoint, method, ip_address, user_agent, request_data, response_code) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    return $stmt->execute([$user_id, $api_key_id, $endpoint, $method, $ip, $user_agent, $request_data, $response_code]);
}

/**
 * Calculate SMS parts
 */
function calculateSmsParts($message) {
    $length = strlen($message);
    if ($length <= 160) return 1;
    if ($length <= 306) return 2;
    if ($length <= 459) return 3;
    return ceil($length / 153);
}

/**
 * Format date for display
 */
function formatDate($date, $format = 'M d, Y H:i') {
    if (!$date) return 'N/A';
    return date($format, strtotime($date));
}

/**
 * Get status badge HTML
 */
function getStatusBadge($status) {
    $badges = [
        'active' => 'success',
        'inactive' => 'secondary',
        'suspended' => 'danger',
        'pending' => 'warning',
        'approved' => 'success',
        'rejected' => 'danger',
        'sent' => 'info',
        'delivered' => 'success',
        'failed' => 'danger',
        'scheduled' => 'primary'
    ];
    
    $class = isset($badges[$status]) ? $badges[$status] : 'secondary';
    return '<span class="badge bg-' . $class . '">' . ucfirst($status) . '</span>';
}

/**
 * Generate pagination
 */
function generatePagination($current_page, $total_pages, $url) {
    $html = '<nav><ul class="pagination justify-content-center">';
    
    // Previous button
    if ($current_page > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $url . '?page=' . ($current_page - 1) . '">Previous</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">Previous</span></li>';
    }
    
    // Page numbers
    for ($i = 1; $i <= $total_pages; $i++) {
        if ($i == $current_page) {
            $html .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
        } else {
            $html .= '<li class="page-item"><a class="page-link" href="' . $url . '?page=' . $i . '">' . $i . '</a></li>';
        }
    }
    
    // Next button
    if ($current_page < $total_pages) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $url . '?page=' . ($current_page + 1) . '">Next</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">Next</span></li>';
    }
    
    $html .= '</ul></nav>';
    return $html;
}

/**
 * Initiate M-Pesa STK Push
 */
function initiateMpesaStkPush($phone, $amount, $account_reference, $transaction_desc) {
    $url = 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest'; // Use sandbox for testing
    
    // Format phone number (remove 0 or +254, ensure 254 format)
    $phone = preg_replace('/^0/', '254', $phone);
    $phone = preg_replace('/^\+/', '', $phone);
    
    $timestamp = date('YmdHis');
    $password = base64_encode(MPESA_SHORTCODE . MPESA_PASSKEY . $timestamp);
    
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . getMpesaAccessToken()
    ]);
    
    $curl_post_data = [
        'BusinessShortCode' => MPESA_SHORTCODE,
        'Password' => $password,
        'Timestamp' => $timestamp,
        'TransactionType' => 'CustomerPayBillOnline',
        'Amount' => $amount,
        'PartyA' => $phone,
        'PartyB' => MPESA_SHORTCODE,
        'PhoneNumber' => $phone,
        'CallBackURL' => MPESA_CALLBACK_URL,
        'AccountReference' => $account_reference,
        'TransactionDesc' => $transaction_desc
    ];
    
    $data_string = json_encode($curl_post_data);
    
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
    
    $response = curl_exec($curl);
    curl_close($curl);
    
    return json_decode($response, true);
}

/**
 * Get M-Pesa Access Token
 */
function getMpesaAccessToken() {
    $url = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
    
    $credentials = base64_encode(MPESA_CONSUMER_KEY . ':' . MPESA_CONSUMER_SECRET);
    
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . $credentials]);
    curl_setopt($curl, CURLOPT_HEADER, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($curl);
    curl_close($curl);
    
    $result = json_decode($response, true);
    
    if (isset($result['access_token'])) {
        return $result['access_token'];
    }
    
    return null;
}