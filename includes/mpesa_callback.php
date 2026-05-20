<?php
// /includes/mpesa_callback.php
// ENTERPRISE-GRADE: Full security, transaction locking, and deduplication

// ============================================
// STEP 1: LOAD CONFIGURATIONS WITH EXPLICIT FUNCTIONS
// ============================================

/**
 * Get SMS Database connection (explicit, not fragile)
 */
function getSmsDbConnection() {
    static $connection = null;
    if ($connection === null) {
        require_once __DIR__ . '/db_sms.php';
        global $db;
        $connection = $db;
    }
    return $connection;
}

/**
 * Get School Management Database connection (explicit, not fragile)
 */
function getSrmsDbConnection() {
    static $connection = null;
    if ($connection === null) {
        require_once __DIR__ . '/config.php';
        global $dbh;
        $connection = $dbh;
    }
    return $connection;
}

// ============================================
// STEP 2: SECURITY VALIDATION
// ============================================
$expected_token = defined('PAYHERO_CALLBACK_SECRET') ? PAYHERO_CALLBACK_SECRET : 'eduscore_secure_2024';

if (!isset($_GET['token']) || $_GET['token'] !== $expected_token) {
    error_log("UNAUTHORIZED callback attempt - Invalid token. IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

// ============================================
// STEP 3: GET AND VALIDATE CALLBACK DATA
// ============================================
$callback_data = file_get_contents('php://input');
$data = json_decode($callback_data, true);

if (!$data || !isset($data['response']) || !isset($data['response']['ResultCode'])) {
    error_log("Invalid callback data structure");
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
    exit();
}

// Log callback for audit
$log_entry = date('Y-m-d H:i:s') . " - Callback received\n";
$log_entry .= "Raw: " . $callback_data . "\n";
file_put_contents(__DIR__ . '/callback_logs.txt', $log_entry, FILE_APPEND);

// ============================================
// STEP 4: EXTRACT PAYMENT DETAILS
// ============================================
$response = $data['response'];
$external_reference = $response['ExternalReference'] ?? null;
$checkout_request_id = $response['CheckoutRequestID'] ?? null;
$mpesa_receipt = $response['MpesaReceiptNumber'] ?? null;
$result_code = (int)($response['ResultCode'] ?? 1);
$result_desc = $response['ResultDesc'] ?? 'Unknown';
$amount_received = floatval($response['Amount'] ?? 0);

error_log("Processing: Ref=$external_reference, CheckoutID=$checkout_request_id, Result=$result_code, Amount=$amount_received");

// ============================================
// STEP 5: GET DATABASE CONNECTIONS
// ============================================
$smsDb = getSmsDbConnection();
$srmsDb = getSrmsDbConnection();

if (!$smsDb || !$srmsDb) {
    error_log("Database connection failed");
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
    exit();
}

// ============================================
// STEP 6: DEDUPLICATION CHECK (CRITICAL)
// ============================================
// Check if this callback was already processed
$stmt = $srmsDb->prepare("SELECT id FROM callback_audit WHERE checkout_request_id = ? OR mpesa_receipt = ? LIMIT 1");
$stmt->execute([$checkout_request_id, $mpesa_receipt]);
if ($stmt->fetch()) {
    error_log("DUPLICATE CALLBACK: CheckoutID=$checkout_request_id, Receipt=$mpesa_receipt");
    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => 'Already processed']);
    exit();
}

// Store raw callback in audit table
$stmt = $srmsDb->prepare("
    INSERT INTO callback_audit (checkout_request_id, mpesa_receipt, external_reference, raw_payload, received_at) 
    VALUES (?, ?, ?, ?, NOW())
");
$stmt->execute([$checkout_request_id, $mpesa_receipt, $external_reference, $callback_data]);

// ============================================
// STEP 7: LOAD PAYMENT ROUTER
// ============================================
require_once __DIR__ . '/PaymentRouter.php';
$router = new PaymentRouter($smsDb, $srmsDb);

// ============================================
// STEP 8: HANDLE FAILED PAYMENTS
// ============================================
if ($result_code !== 0) {
    error_log("PAYMENT FAILED: $result_desc");
    
    $transactionInfo = $router->detectTransactionType($external_reference, $checkout_request_id);
    if ($transactionInfo) {
        $router->markTransactionFailed($transactionInfo, $result_desc);
    }
    
    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => 'Payment failed recorded']);
    exit();
}

// ============================================
// STEP 9: DETECT TRANSACTION TYPE
// ============================================
$transactionInfo = $router->detectTransactionType($external_reference, $checkout_request_id);

if (!$transactionInfo) {
    error_log("Transaction not found: $external_reference");
    http_response_code(200);
    echo json_encode(['status' => 'error', 'message' => 'Transaction not found']);
    exit();
}

$transaction = $transactionInfo['data'];
error_log("ROUTING: Type={$transactionInfo['type']}, ID=" . ($transaction['transaction_id'] ?? $transaction['reference']));

// ============================================
// STEP 10: AMOUNT VALIDATION (FAIL ON MISMATCH)
// ============================================
$expected_amount = floatval($transaction['total_cost'] ?? $transaction['amount'] ?? 0);

if ($amount_received > 0 && abs($amount_received - $expected_amount) > 0.01) {
    error_log("AMOUNT MISMATCH ALERT: Expected $expected_amount, Got $amount_received");
    $router->markTransactionFailed($transactionInfo, "Amount mismatch: Expected $expected_amount, Got $amount_received");
    http_response_code(200);
    echo json_encode(['status' => 'error', 'message' => 'Amount mismatch']);
    exit();
}

// ============================================
// STEP 11: PROCESS PAYMENT IN DATABASE TRANSACTION
// ============================================
$db = $router->getDb($transactionInfo['database']);
$db->beginTransaction();

try {
    // Atomic claim with transaction lock
    $atomic_updated = $router->atomicClaimTransaction($transactionInfo, $db);
    
    if (!$atomic_updated) {
        $db->rollBack();
        error_log("Transaction already claimed: " . ($transaction['transaction_id'] ?? $transaction['reference']));
        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => 'Already processed']);
        exit();
    }
    
    // Route payment within transaction
    $success = $router->routePaymentInTransaction(
        $transactionInfo,
        $callback_data,
        $mpesa_receipt,
        $result_code,
        $result_desc,
        $amount_received,
        $db
    );
    
    if ($success) {
        $db->commit();
        error_log("Payment processed successfully: " . ($transaction['transaction_id'] ?? $transaction['reference']));
    } else {
        $db->rollBack();
        error_log("Payment processing failed");
    }
    
} catch (Exception $e) {
    $db->rollBack();
    error_log("CRITICAL ERROR: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Processing error']);
    exit();
}

// ============================================
// STEP 12: RESPOND TO PAYHERO
// ============================================
http_response_code(200);
echo json_encode(['status' => 'success', 'message' => 'Callback processed']);