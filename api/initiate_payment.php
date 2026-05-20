<?php
session_start();
require_once('../includes/config.php');
require_once('../includes/mpesa_config.php'); // Make sure you have this config file
error_log("=== DEBUG: Checking M-Pesa Configuration ===");
error_log("MPESA_ENVIRONMENT: " . (defined('MPESA_ENVIRONMENT') ? MPESA_ENVIRONMENT : 'NOT DEFINED'));
error_log("MPESA_CONSUMER_KEY: " . (defined('MPESA_CONSUMER_KEY') ? substr(MPESA_CONSUMER_KEY, 0, 10) . '...' : 'NOT DEFINED'));
error_log("MPESA_CONSUMER_SECRET: " . (defined('MPESA_CONSUMER_SECRET') ? substr(MPESA_CONSUMER_SECRET, 0, 10) . '...' : 'NOT DEFINED'));
error_log("MPESA_SHORTCODE: " . (defined('MPESA_SHORTCODE') ? MPESA_SHORTCODE : 'NOT DEFINED'));

header('Content-Type: application/json');

// DEBUG: Log the start of the request
error_log("=== INITIATE PAYMENT REQUEST START ===");

// Check if user is logged in
if (!isset($_SESSION['school_id'])) {
    error_log("ERROR: User not logged in");
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get POST data
$json_input = file_get_contents('php://input');
error_log("Raw JSON input: " . $json_input);

$data = json_decode($json_input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("JSON decode error: " . json_last_error_msg());
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
    exit;
}

$phone_number = $data['phone_number'] ?? '';
$tier = $data['tier'] ?? '';
$amount = $data['amount'] ?? 0;
$school_id = $data['school_id'] ?? $_SESSION['school_id'];

error_log("Parsed data: phone=$phone_number, tier=$tier, amount=$amount, school_id=$school_id");

// Validate input
if (empty($phone_number) || empty($tier) || $amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid payment details']);
    exit;
}

// Clean phone number (remove spaces, plus signs, ensure 254 format)
$phone_number = preg_replace('/[^0-9]/', '', $phone_number);
if (strlen($phone_number) === 10 && $phone_number[0] === '0') {
    $phone_number = '254' . substr($phone_number, 1);
} elseif (strlen($phone_number) === 9) {
    $phone_number = '254' . $phone_number;
}

// Check if phone number is valid for M-Pesa
if (strlen($phone_number) !== 12 || substr($phone_number, 0, 3) !== '254') {
    echo json_encode(['success' => false, 'message' => 'Invalid phone number format. Use format: 2547XXXXXXXX']);
    exit;
}

// Get school details
$stmt = $dbh->prepare("SELECT school_name FROM tblschoolinfo WHERE id = :id");
$stmt->execute([':id' => $school_id]);
$school = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$school) {
    echo json_encode(['success' => false, 'message' => 'School not found']);
    exit;
}

// M-Pesa API Function: Generate access token
function getAccessToken($consumer_key, $consumer_secret) {
    $url = MPESA_ENVIRONMENT === 'sandbox' 
        ? 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials'
        : 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
    
    $credentials = base64_encode($consumer_key . ':' . $consumer_secret);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . $credentials]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    error_log("M-Pesa Access Token Response - HTTP: $http_code, Body: $response");
    
    $result = json_decode($response, true);
    
    return $result['access_token'] ?? null;
}

// Generate timestamp and password
$timestamp = date('YmdHis');
$password = base64_encode(MPESA_SHORTCODE . MPESA_PASSKEY . $timestamp);

// Get access token
$access_token = getAccessToken(MPESA_CONSUMER_KEY, MPESA_CONSUMER_SECRET);

if (!$access_token) {
    error_log("ERROR: Failed to get access token");
    echo json_encode(['success' => false, 'message' => 'Failed to authenticate with M-Pesa API']);
    exit;
}

// Prepare STK Push request
$stk_url = MPESA_ENVIRONMENT === 'sandbox'
    ? 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest'
    : 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest';

$request_data = [
    'BusinessShortCode' => MPESA_SHORTCODE,
    'Password' => $password,
    'Timestamp' => $timestamp,
    'TransactionType' => 'CustomerPayBillOnline',
    'Amount' => $amount,
    'PartyA' => $phone_number,
    'PartyB' => MPESA_SHORTCODE,
    'PhoneNumber' => $phone_number,
    'CallBackURL' => MPESA_CALLBACK_URL,
    'AccountReference' => 'EDUSCORE-' . $school_id,
    'TransactionDesc' => 'Eduscore ' . $tier . ' Subscription - ' . $school['school_name']
];

error_log("STK Push Request Data: " . json_encode($request_data));

// Save payment record to database FIRST (before making API call)
try {
    $stmt = $dbh->prepare("
        INSERT INTO payments (
            school_id, 
            phone_number, 
            tier, 
            amount, 
            status, 
            transaction_desc,
            created_at
        ) VALUES (
            :school_id, 
            :phone_number, 
            :tier, 
            :amount, 
            'pending', 
            :transaction_desc,
            NOW()
        )
    ");
    
    $stmt->execute([
        ':school_id' => $school_id,
        ':phone_number' => $phone_number,
        ':tier' => $tier,
        ':amount' => $amount,
        ':transaction_desc' => 'Eduscore ' . $tier . ' Subscription'
    ]);
    
    $payment_id = $dbh->lastInsertId();
    error_log("Payment record created with ID: $payment_id");
    
} catch (PDOException $e) {
    error_log("Payment record error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}

// Send STK Push request to M-Pesa
$ch = curl_init($stk_url);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $access_token,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request_data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

error_log("M-Pesa STK Push Response - HTTP: $http_code, Body: $response, Curl Error: $curl_error");

$result = json_decode($response, true);

if ($http_code === 200 && isset($result['ResponseCode']) && $result['ResponseCode'] === "0") {
    // Update payment record with CheckoutRequestID
    if (isset($payment_id) && isset($result['CheckoutRequestID'])) {
        $stmt = $dbh->prepare("UPDATE payments SET checkout_request_id = :checkout_id WHERE id = :id");
        $stmt->execute([
            ':checkout_id' => $result['CheckoutRequestID'],
            ':id' => $payment_id
        ]);
        error_log("Updated payment $payment_id with checkout ID: " . $result['CheckoutRequestID']);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Payment request sent successfully! Please check your phone and enter your M-Pesa PIN.',
        'checkout_request_id' => $result['CheckoutRequestID'] ?? '',
        'merchant_request_id' => $result['MerchantRequestID'] ?? ''
    ]);
} else {
    $error_message = $result['errorMessage'] ?? 'Failed to initiate payment';
    error_log("M-Pesa API Error: $error_message");
    
    // Update payment status to failed
    if (isset($payment_id)) {
        $stmt = $dbh->prepare("UPDATE payments SET status = 'failed', error_message = :error WHERE id = :id");
        $stmt->execute([':error' => $error_message, ':id' => $payment_id]);
    }
    
    echo json_encode([
        'success' => false,
        'message' => $error_message,
        'details' => $result
    ]);
}
?>