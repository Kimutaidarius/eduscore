<?php
session_start();
header('Content-Type: application/json');

require_once '../config/db_sms.php';
require_once '../includes/PaymentRouter.php';

// Debug logging
error_log("=== SMS Purchase Request ===");

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to continue']);
    exit;
}

// Get admin user
try {
    $stmt = $db->prepare("SELECT id, username, full_name, sms_balance FROM admins WHERE id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $admin = $stmt->fetch();
    
    if (!$admin) {
        echo json_encode(['success' => false, 'message' => 'Admin account not found']);
        exit;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}

// Get POST data
$quantity = (int)($_POST['quantity'] ?? 0);
$total_amount = (float)($_POST['total_amount'] ?? 0);
$payment_method = $_POST['payment_method'] ?? 'mpesa';
$phone_number = $_POST['phone_number'] ?? '';
$notes = $_POST['notes'] ?? '';
$csrf_token = $_POST['csrf_token'] ?? '';

// Verify CSRF token
if (!isset($_SESSION['csrf_token']) || $csrf_token !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit;
}

// Validate inputs
if ($quantity <= 0 || $total_amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid quantity or amount']);
    exit;
}

// Clean phone number format
if ($payment_method === 'mpesa') {
    $phone_number = preg_replace('/\D/', '', $phone_number);
    
    if (empty($phone_number)) {
        echo json_encode(['success' => false, 'message' => 'Please enter your phone number']);
        exit;
    }
    
    // Convert to 07XXXXXXXX format for PayHero (not 254 format)
    if (strpos($phone_number, '254') === 0) {
        $phone_number = '0' . substr($phone_number, 3);
    }
    
    // Validate format (must be 10 digits starting with 07)
    if (strlen($phone_number) !== 10 || substr($phone_number, 0, 2) !== '07') {
        echo json_encode([
            'success' => false, 
            'message' => 'Please enter a valid Safaricom number (e.g., 0712345678)'
        ]);
        exit;
    }
}

// Generate unique transaction reference
$transaction_ref = 'SMS-' . date('YmdHis') . '-' . rand(1000, 9999);
$price_per_sms = $total_amount / $quantity;

// Get database connection for School Management (for router)
// Since SMS system uses its own DB, we need to pass both
$smsDb = $db; // SMS database
$srmsDb = getSrmsDbConnection(); // School Management DB (for ledger/jobs)

// Initialize PaymentRouter
$router = new PaymentRouter($smsDb, $srmsDb, getenv('PAYHERO_WEBHOOK_SECRET'));

try {
    // ============================================
    // STEP 1: CREATE PENDING TRANSACTION
    // ============================================
    $stmt = $smsDb->prepare("
        INSERT INTO sms_purchases 
        (admin_id, transaction_id, quantity, price_per_sms, total_cost, payment_method, phone_number, notes, status, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");
    
    $stmt->execute([
        $admin['id'], 
        $transaction_ref, 
        $quantity, 
        $price_per_sms, 
        $total_amount, 
        $payment_method, 
        $phone_number, 
        $notes
    ]);
    
    $purchase_id = $smsDb->lastInsertId();
    
    // ============================================
    // STEP 2: BUILD TRANSACTION INFO FOR ROUTER
    // ============================================
    $transactionInfo = [
        'type' => 'sms',
        'data' => [
            'id' => $purchase_id,
            'admin_id' => $admin['id'],
            'quantity' => $quantity,
            'total_cost' => $total_amount,
            'transaction_id' => $transaction_ref,
            'phone_number' => $phone_number
        ],
        'table' => 'sms_purchases',
        'id_field' => 'id',
        'database' => 'sms'
    ];
    
    // ============================================
    // STEP 3: INITIATE PAYHERO PAYMENT
    // ============================================
    if ($payment_method === 'mpesa') {
        // Ensure minimum amount of 10 KES (PayHero requirement)
        if ($total_amount < 10) {
            echo json_encode(['success' => false, 'message' => 'Minimum payment amount is KES 10']);
            exit;
        }
        
        // Initiate PayHero STK Push
        $payhero_response = initiatePayHeroPayment($phone_number, (int)$total_amount, $transaction_ref, $admin['full_name']);
        
        if ($payhero_response['success']) {
            // Update checkout_request_id if received
            if (isset($payhero_response['checkout_request_id'])) {
                $stmt = $smsDb->prepare("UPDATE sms_purchases SET checkout_request_id = ? WHERE id = ?");
                $stmt->execute([$payhero_response['checkout_request_id'], $purchase_id]);
                
                // Also update in transactionInfo for router
                $transactionInfo['data']['checkout_request_id'] = $payhero_response['checkout_request_id'];
            }
            
            // ============================================
            // STEP 4: LET ROUTER CLAIM TRANSACTION (Atomic)
            // ============================================
            // This ensures no double processing when callback arrives
            try {
                $router->executePayment($srmsDb, function($db) use ($router, $transactionInfo) {
                    return $router->atomicClaimTransaction($transactionInfo, $db);
                });
            } catch (Exception $e) {
                // Transaction already claimed or processing - that's fine
                error_log("Router claim notice: " . $e->getMessage());
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'STK Push sent to your phone. Please enter your PIN to complete payment.',
                'transaction_id' => $transaction_ref,
                'checkout_request_id' => $payhero_response['checkout_request_id'] ?? null,
                'stk_push' => true
            ]);
        } else {
            // Mark as failed
            $stmt = $smsDb->prepare("UPDATE sms_purchases SET status = 'failed' WHERE id = ?");
            $stmt->execute([$purchase_id]);
            
            echo json_encode([
                'success' => false,
                'message' => $payhero_response['message'] ?? 'Failed to initiate M-Pesa payment. Please try again.'
            ]);
        }
    } else {
        // Bank transfer - pending approval
        echo json_encode([
            'success' => true,
            'message' => 'Purchase request created. Please complete the bank transfer to activate your SMS credits.',
            'transaction_id' => $transaction_ref,
            'payment_instructions' => true
        ]);
    }
    
} catch (Exception $e) {
    error_log("SMS Purchase Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error processing purchase: ' . $e->getMessage()]);
}

/**
 * Get School Management Database connection
 */
function getSrmsDbConnection() {
    static $connection = null;
    if ($connection === null) {
        try {
            $connection = new PDO(
                "mysql:host=sql107.infinityfree.com;dbname=if0_41566747_srms;charset=utf8mb4",
                "if0_41566747",
                "Bit06882020",
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
        } catch (PDOException $e) {
            error_log("Failed to connect to SRMS DB: " . $e->getMessage());
            return null;
        }
    }
    return $connection;
}

/**
 * Initiate PayHero STK Push
 */
function initiatePayHeroPayment($phone, $amount, $reference, $customer_name) {
    error_log("=== PAYHERO DEBUG INFO ===");
    error_log("CHANNEL ID: " . PAYHERO_CHANNEL_ID);
    error_log("CALLBACK URL: " . PAYHERO_CALLBACK_URL);
    error_log("PHONE NUMBER: " . $phone);
    error_log("AMOUNT: " . $amount);
    error_log("REFERENCE: " . $reference);
    
    $endpoint = 'https://backend.payhero.co.ke/api/v2/payments';
    
    $payload = [
        'amount' => (int)$amount,
        'phone_number' => $phone,
        'channel_id' => (int)PAYHERO_CHANNEL_ID,
        'provider' => 'm-pesa',
        'external_reference' => $reference,
        'customer_name' => $customer_name,
        'callback_url' => PAYHERO_CALLBACK_URL
    ];
    
    error_log("=== PAYHERO REQUEST ===");
    error_log(json_encode($payload));
    
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Basic ' . trim(PAYHERO_BASIC_AUTH_TOKEN)
    ];
    
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $endpoint,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    error_log("=== PAYHERO RESPONSE ===");
    error_log("HTTP CODE: $http_code");
    error_log("RESPONSE: $response");
    
    if ($curl_error) {
        error_log("CURL ERROR: " . $curl_error);
        return ['success' => false, 'message' => 'Connection error: ' . $curl_error];
    }
    
    $result = json_decode($response, true);
    
    if (!$result) {
        error_log("Invalid JSON response: " . $response);
        return ['success' => false, 'message' => 'Invalid response from payment gateway'];
    }
    
    // Check for success
    if (($http_code == 201 || $http_code == 200) && isset($result['success']) && $result['success'] == true) {
        return [
            'success' => true,
            'checkout_request_id' => $result['CheckoutRequestID'] ?? null,
            'reference' => $result['reference'] ?? null
        ];
    }
    
    // Handle error
    $error_msg = $result['message'] ?? $result['error'] ?? 'Payment initiation failed';
    error_log("PAYHERO ERROR: " . $error_msg);
    
    return ['success' => false, 'message' => $error_msg];
}
?>