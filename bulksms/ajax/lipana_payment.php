<?php
/**
 * Lipana.dev STK Push for SMS Topup
 * When user pays via M-Pesa:
 * - Buys SMS from OpenSMS at KES 0.70 per SMS
 * - User receives SMS at KES 1.00 per SMS
 * - Profit (KES 0.30 per SMS) goes to your till
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/mpesa_config.php';

header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Auth check
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Please login to continue']);
    exit;
}

$user_id = (int) $_SESSION['user_id'];

// CSRF check
$csrf_token = $_POST['csrf_token'] ?? '';
if (!isset($_SESSION['csrf_token']) || $csrf_token !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit;
}

// Input validation
$rawPhone = $_POST['phone'] ?? '';
$amount   = (float) ($_POST['amount'] ?? 0);
$payment_method = $_POST['payment_method'] ?? 'mpesa';

// Minimum amount enforcement (Lipana requires KES 10 minimum)
if ($amount < 10) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Minimum topup amount is KES 10 (M-Pesa requirement)']);
    exit;
}

// Pricing configuration
$customer_price_per_sms = 1.00;      // Customer pays KES 1 per SMS
$opensms_cost_per_sms = OPENSMS_PRICE_PER_SMS; // KES 0.70 per SMS (from config)
$profit_per_sms = $customer_price_per_sms - $opensms_cost_per_sms; // KES 0.30 profit per SMS

// Calculate SMS units
$sms_units = floor($amount / $customer_price_per_sms); // e.g., KES 10 = 10 SMS
$your_cost = $sms_units * $opensms_cost_per_sms;       // e.g., 10 SMS = KES 7.00
$your_profit = $amount - $your_cost;                    // e.g., KES 10 - KES 7 = KES 3 profit

// Normalize phone
$phone = preg_replace('/\D/', '', $rawPhone);
if (substr($phone, 0, 1) === '0') {
    $phone = '254' . substr($phone, 1);
} elseif (substr($phone, 0, 3) !== '254' && strlen($phone) === 9) {
    $phone = '254' . $phone;
}

if (!preg_match('/^2547\d{8}$/', $phone)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid phone number format']);
    exit;
}

$lipana_phone = '+' . $phone;
$reference = 'SMS-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

try {
    $pdo->beginTransaction();
    
    // Insert into mpesa_transactions - store profit details
    $stmt = $pdo->prepare("
        INSERT INTO mpesa_transactions 
            (user_id, phone, amount, sms_units, your_cost, your_profit, reference, status, payment_method, created_at)
        VALUES 
            (?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW())
    ");
    
    $stmt->execute([
        $user_id,
        $phone,
        $amount,
        $sms_units,
        $your_cost,
        $your_profit,
        $reference,
        $payment_method
    ]);
    
    $transaction_id = $pdo->lastInsertId();
    $pdo->commit();
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Transaction creation error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to create transaction record']);
    exit;
}

// Lipana STK Push Request
$payload = [
    'phone'  => $lipana_phone,
    'amount' => (int) $amount,
    'reference' => $reference
];

$ch = curl_init(LIPANA_STK_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => [
        'x-api-key: ' . LIPANA_API_KEY,
        'Content-Type: application/json',
        'Accept: application/json'
    ],
    CURLOPT_TIMEOUT        => 30
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Connection to payment gateway failed']);
    exit;
}

$responseData = json_decode($response, true);

if ($httpCode >= 400 || !isset($responseData['success']) || $responseData['success'] !== true) {
    $errorMsg = $responseData['message'] ?? 'STK Push rejected';
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'STK Push failed: ' . $errorMsg]);
    exit;
}

// Extract checkout ID if present
$checkout_request_id = $responseData['data']['CheckoutRequestID'] ?? $responseData['CheckoutRequestID'] ?? null;

if ($checkout_request_id) {
    $updateStmt = $pdo->prepare("UPDATE mpesa_transactions SET checkout_request_id = ? WHERE id = ?");
    $updateStmt->execute([$checkout_request_id, $transaction_id]);
}

echo json_encode([
    'success'    => true,
    'message'    => 'STK push sent successfully. Complete payment on your phone.',
    'reference'  => $reference,
    'amount'     => $amount,
    'sms_units'  => $sms_units,
    'your_cost'  => $your_cost,
    'your_profit' => $your_profit,
    'transaction_id' => $transaction_id,
    'checkout_request_id' => $checkout_request_id
]);