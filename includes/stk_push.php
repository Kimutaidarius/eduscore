<?php
session_start();
require_once 'config.php';
require_once 'SubscriptionManager.php';
require_once 'PricingEngine.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['school_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// ============================================
// STEP 1: GET DB CONNECTION FIRST
// ============================================
$dbh = getDbConnection();
if (!$dbh) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// ============================================
// STEP 2: GET INPUTS
// ============================================
$phone = $_POST['phone'] ?? '';
$school_id = intval($_POST['school_id'] ?? 0);
$payment_type = $_POST['payment_type'] ?? 'subscription';
$invoice_id = intval($_POST['invoice_id'] ?? 0);

// Validate required fields
if (!$phone || !$school_id) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

// ============================================
// STEP 3: RETRY PROTECTION - PREVENT DUPLICATE PENDING
// ============================================
$stmt = $dbh->prepare("
    SELECT id FROM billing_transactions 
    WHERE school_id = ? AND status = 'pending' 
    ORDER BY created_at DESC LIMIT 1
");
$stmt->execute([$school_id]);

if ($stmt->fetch()) {
    echo json_encode([
        'success' => false,
        'message' => 'You already have a pending payment. Check your phone for the STK prompt.'
    ]);
    exit();
}

// ============================================
// STEP 4: CALCULATE AMOUNT USING PRICING ENGINE
// ============================================
try {
    $pricing = new PricingEngine($dbh, $school_id);
    $amount = 0;
    
    // Calculate amount based on payment type
    if ($payment_type === 'onboarding') {
        $amount = $pricing->getOnboardingFee();
    } else {
        $amount = $pricing->calculateSubscriptionAmount();
    }
    
    // If amount is 0, check if there's an invoice with amount
    if ($amount == 0 && $invoice_id > 0) {
        $stmt = $dbh->prepare("SELECT amount FROM billing_invoices WHERE id = ? AND school_id = ?");
        $stmt->execute([$invoice_id, $school_id]);
        $invoice = $stmt->fetch();
        if ($invoice) {
            $amount = floatval($invoice['amount']);
        }
    }
    
    if ($amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid payment amount']);
        exit();
    }
} catch (Exception $e) {
    error_log("Pricing Engine Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error calculating payment amount']);
    exit();
}

// ============================================
// STEP 5: FORMAT PHONE NUMBER (SAFARICOM ONLY)
// ============================================
$phone = preg_replace('/[^0-9]/', '', $phone);
if (substr($phone, 0, 1) === '0') {
    $phone = '254' . substr($phone, 1);
}
if (substr($phone, 0, 4) !== '254') {
    $phone = '254' . $phone;
}
// Only allow Safaricom numbers (2547XXXXXXXX)
if (!preg_match('/^2547\d{8}$/', $phone)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid Safaricom phone number (07XXXXXXXX)']);
    exit();
}

// ============================================
// STEP 6: GET SCHOOL NAME
// ============================================
$stmt = $dbh->prepare("SELECT school_name FROM tblschoolinfo WHERE id = ?");
$stmt->execute([$school_id]);
$school = $stmt->fetch();
$customer_name = $school['school_name'] ?? 'School Customer';

// ============================================
// STEP 7: GENERATE UNIQUE TRANSACTION REFERENCE
// ============================================
$transaction_ref = 'TXN-' . date('YmdHis') . '-' . rand(1000, 9999);

// ============================================
// STEP 8: SAVE TRANSACTION AS PENDING (WITH CORRECT COLUMN NAMES)
// ============================================
try {
    // IMPORTANT: Using transaction_ref and payment_type (not reference and type)
    $stmt = $dbh->prepare("
        INSERT INTO billing_transactions 
        (school_id, transaction_ref, amount, payment_type, status, phone, invoice_id, provider)
        VALUES (?, ?, ?, ?, 'pending', ?, ?, 'mpesa')
    ");
    $stmt->execute([
        $school_id, 
        $transaction_ref, 
        $amount, 
        $payment_type,
        $phone,
        $invoice_id
    ]);
    $transaction_id = $dbh->lastInsertId();
    
    // Also link to invoice if exists
    if ($invoice_id > 0) {
        $stmt = $dbh->prepare("UPDATE billing_invoices SET status = 'UNPAID' WHERE id = ? AND school_id = ?");
        $stmt->execute([$invoice_id, $school_id]);
    }
} catch (Exception $e) {
    error_log("DB Insert Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit();
}

// ============================================
// STEP 9: PREPARE PAYHERO API REQUEST
// ============================================
$callback_url = defined('PAYHERO_CALLBACK_URL') ? PAYHERO_CALLBACK_URL : 'https://eduscore.co.ke/includes/mpesa_callback.php?token=' . PAYHERO_CALLBACK_SECRET;
$channel_id = defined('PAYHERO_CHANNEL_ID') ? PAYHERO_CHANNEL_ID : 133;

$payload = [
    'amount' => (int)$amount,
    'phone_number' => $phone,
    'channel_id' => $channel_id,
    'provider' => 'm-pesa',
    'external_reference' => $transaction_ref,
    'customer_name' => $customer_name,
    'callback_url' => $callback_url
];

// Optional: Add credential_id if you have your own API keys
if (defined('PAYHERO_CREDENTIAL_ID') && PAYHERO_CREDENTIAL_ID) {
    $payload['credential_id'] = PAYHERO_CREDENTIAL_ID;
}

// ============================================
// STEP 10: MAKE CURL REQUEST TO PAYHERO
// ============================================
$ch = curl_init('https://backend.payhero.co.ke/api/v2/payments');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);

// IMPORTANT: Fix Authorization header format
// If PAYHERO_BASIC_AUTH_TOKEN already contains "Basic ", use as is
// Otherwise, prepend "Basic "
$auth_header = PAYHERO_BASIC_AUTH_TOKEN;
if (!preg_match('/^Basic\s/i', $auth_header)) {
    $auth_header = 'Basic ' . $auth_header;
}

curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: ' . $auth_header
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);

// Log for debugging
error_log("==== PAYHERO REQUEST ====");
error_log("URL: https://backend.payhero.co.ke/api/v2/payments");
error_log("Payload: " . json_encode($payload));
error_log("Auth Header: " . substr($auth_header, 0, 20) . '...');
error_log("==== PAYHERO RESPONSE ====");
error_log("HTTP Code: " . $http_code);
error_log("Response: " . $response);
if ($curl_error) {
    error_log("CURL Error: " . $curl_error);
}

// ============================================
// STEP 11: ENHANCED TIMEOUT FAIL-SAFE
// ============================================
// Detect curl-level failure
if ($response === false) {
    $stmt = $dbh->prepare("UPDATE billing_transactions SET status = 'failed' WHERE id = ?");
    $stmt->execute([$transaction_id]);
    
    error_log("CURL FAILURE: " . $curl_error);
    
    echo json_encode([
        'success' => false,
        'message' => 'Connection error. Please try again.'
    ]);
    curl_close($ch);
    exit();
}

// Detect empty response
if (empty($response)) {
    $stmt = $dbh->prepare("UPDATE billing_transactions SET status = 'failed' WHERE id = ?");
    $stmt->execute([$transaction_id]);
    
    error_log("EMPTY RESPONSE FROM PAYHERO");
    
    echo json_encode([
        'success' => false,
        'message' => 'No response from payment gateway. Please try again.'
    ]);
    curl_close($ch);
    exit();
}

// ============================================
// STEP 12: JSON PARSE VALIDATION
// ============================================
$result = json_decode($response, true);
if (!$result) {
    $stmt = $dbh->prepare("UPDATE billing_transactions SET status = 'failed' WHERE id = ?");
    $stmt->execute([$transaction_id]);
    
    error_log("INVALID JSON RESPONSE: " . $response);
    
    echo json_encode([
        'success' => false,
        'message' => 'Invalid response from payment gateway.'
    ]);
    curl_close($ch);
    exit();
}

curl_close($ch);

// ============================================
// STEP 13: HANDLE API RESPONSE
// ============================================
if ($http_code === 200 || $http_code === 201) {
    if (isset($result['success']) && $result['success'] === true) {
        // Payment initiated successfully
        $checkout_request_id = $result['CheckoutRequestID'] ?? null;
        $payhero_reference = $result['reference'] ?? null;
        
        // Update transaction with checkout request ID
        if ($checkout_request_id) {
            $stmt = $dbh->prepare("UPDATE billing_transactions SET checkout_request_id = ? WHERE id = ?");
            $stmt->execute([$checkout_request_id, $transaction_id]);
        }
        
        echo json_encode([
            'success' => true,
            'reference' => $transaction_ref,
            'checkout_request_id' => $checkout_request_id,
            'payhero_reference' => $payhero_reference,
            'message' => 'STK push sent successfully. Check your phone to complete payment.'
        ]);
        
    } else {
        // API responded but failed
        $stmt = $dbh->prepare("UPDATE billing_transactions SET status = 'failed' WHERE id = ?");
        $stmt->execute([$transaction_id]);
        
        $error_msg = $result['message'] ?? $result['error'] ?? 'STK push failed';
        error_log("PayHero API Error: " . $error_msg);
        
        echo json_encode(['success' => false, 'message' => $error_msg]);
    }
    
} else {
    // HTTP error - mark transaction as failed
    $stmt = $dbh->prepare("UPDATE billing_transactions SET status = 'failed' WHERE id = ?");
    $stmt->execute([$transaction_id]);
    
    error_log("HTTP Error: " . $http_code . " - Response: " . $response);
    
    echo json_encode([
        'success' => false,
        'message' => 'Payment gateway error. Please try again later.',
        'debug' => $http_code . ': ' . substr($response, 0, 200)
    ]);
}
?>