<?php
// api/lipana_callback.php
// Webhook endpoint for Lipana to notify about payment events

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Get the raw input (required for signature verification)
$raw_input = file_get_contents('php://input');
$headers = getallheaders();

// Log incoming request for debugging
$log_entry = [
    'time' => date('Y-m-d H:i:s'),
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'method' => $_SERVER['REQUEST_METHOD'],
    'headers' => $headers,
    'raw_input' => $raw_input,
    'input_decoded' => json_decode($raw_input, true)
];

file_put_contents(__DIR__ . '/lipana_callback.log', 
    json_encode($log_entry, JSON_PRETTY_PRINT) . "\n" . str_repeat('=', 80) . "\n\n", 
    FILE_APPEND
);

// -----------------------------------------------------------------------------
// VERIFY WEBHOOK SIGNATURE (if you have a webhook secret)
// -----------------------------------------------------------------------------
$signature = $headers['X-Lipana-Signature'] ?? $headers['x-lipana-signature'] ?? '';

// You need to set this in your config file
// define('LIPANA_WEBHOOK_SECRET', 'wh_sec_your_webhook_secret_here');

if (defined('LIPANA_WEBHOOK_SECRET') && LIPANA_WEBHOOK_SECRET && LIPANA_WEBHOOK_SECRET !== 'wh_sec_your_webhook_secret_here') {
    $expected_signature = hash_hmac('sha256', $raw_input, LIPANA_WEBHOOK_SECRET);
    
    if (!hash_equals($expected_signature, $signature)) {
        error_log("Lipana webhook signature verification failed");
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Invalid signature']);
        exit;
    }
    error_log("Signature verification successful");
}

// Parse the webhook data
$data = json_decode($raw_input, true);

if (!$data) {
    error_log("Invalid JSON received: " . $raw_input);
    http_response_code(200);
    echo "OK";
    exit;
}

$event = $data['event'] ?? '';
$event_data = $data['data'] ?? [];

// Extract transaction details
$transaction_id = $event_data['transactionId'] ?? '';
$amount = $event_data['amount'] ?? 0;
$currency = $event_data['currency'] ?? 'KES';
$status = $event_data['status'] ?? '';
$phone = $event_data['phone'] ?? '';
$timestamp = $event_data['timestamp'] ?? '';

error_log("Processing Lipana webhook - Event: $event, Transaction ID: $transaction_id, Amount: $amount, Phone: $phone");

// -----------------------------------------------------------------------------
// HANDLE DIFFERENT EVENT TYPES
// -----------------------------------------------------------------------------
if ($event === 'payment.success') {
    // Payment was successful
    try {
        require_once __DIR__ . '/../config/config.php';
        
        $pdo->beginTransaction();
        
        // First, try to find by transaction ID in our records
        $stmt = $pdo->prepare("
            SELECT * FROM admin_sms_purchases 
            WHERE transaction_ref = ? AND status = 'pending'
        ");
        $stmt->execute([$transaction_id]);
        $admin_purchase = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If not found by transaction_ref, try to find by amount and recent pending
        if (!$admin_purchase) {
            $stmt = $pdo->prepare("
                SELECT * FROM admin_sms_purchases 
                WHERE amount = ? AND status = 'pending'
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $stmt->execute([$amount]);
            $admin_purchase = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        if ($admin_purchase) {
            error_log("Found admin purchase: " . $admin_purchase['reference']);
            
            // Update admin purchase status
            $update = $pdo->prepare("
                UPDATE admin_sms_purchases 
                SET status = 'completed', 
                    completed_at = NOW(),
                    transaction_ref = ?,
                    api_response = ?
                WHERE id = ?
            ");
            $update->execute([
                $transaction_id,
                json_encode($data),
                $admin_purchase['id']
            ]);
            
            // Add credits to admin inventory
            $inventory = $pdo->prepare("
                UPDATE system_settings 
                SET setting_value = setting_value + ? 
                WHERE setting_name = 'sms_inventory_balance'
            ");
            $inventory->execute([$admin_purchase['sms_units']]);
            
            error_log("Admin purchase completed: " . $admin_purchase['reference'] . 
                      " - Added " . $admin_purchase['sms_units'] . " SMS credits to inventory");
            
            $pdo->commit();
            
            http_response_code(200);
            echo json_encode([
                'status' => 'success', 
                'message' => 'Admin purchase processed successfully',
                'sms_added' => $admin_purchase['sms_units']
            ]);
            exit;
        }
        
        // Check if this is a regular school payment
        $stmt = $pdo->prepare("
            SELECT * FROM tblpayments 
            WHERE amount = ? AND status = 'pending'
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$amount]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($payment) {
            error_log("Found school payment: " . $payment['reference']);
            
            // Calculate SMS units (1 SMS = KES 1.00)
            $sms_units = floor($payment['amount']);
            $your_cost = $sms_units * 0.70;
            $your_profit = $payment['amount'] - $your_cost;
            
            // Update payment status
            $update = $pdo->prepare("
                UPDATE tblpayments 
                SET status = 'completed', 
                    transaction_id = ?,
                    paid_at = NOW()
                WHERE id = ?
            ");
            $update->execute([$transaction_id, $payment['id']]);
            
            // Record in mpesa_transactions
            $insert = $pdo->prepare("
                INSERT INTO mpesa_transactions 
                (user_id, reference, phone, amount, sms_units, your_cost, your_profit, status, payment_method, transaction_ref, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'completed', 'M-Pesa', ?, NOW())
            ");
            $insert->execute([
                $payment['school_id'],
                $payment['reference'],
                $phone,
                $payment['amount'],
                $sms_units,
                $your_cost,
                $your_profit,
                $transaction_id
            ]);
            
            // Add SMS credits to school balance
            $school = $pdo->prepare("
                UPDATE tblschoolinfo 
                SET sms_balance = sms_balance + ? 
                WHERE id = ?
            ");
            $school->execute([$sms_units, $payment['school_id']]);
            
            $pdo->commit();
            
            error_log("School purchase completed: " . $payment['reference'] . 
                      " - Added " . $sms_units . " SMS credits to school " . $payment['school_id']);
            
            http_response_code(200);
            echo json_encode([
                'status' => 'success', 
                'message' => 'School purchase processed successfully',
                'sms_added' => $sms_units
            ]);
            exit;
        }
        
        // No matching transaction found
        error_log("No matching transaction found for payment: Transaction ID: $transaction_id, Amount: $amount");
        $pdo->rollBack();
        
        http_response_code(200);
        echo json_encode(['status' => 'ignored', 'message' => 'Transaction not found']);
        
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Lipana callback error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        
        http_response_code(200);
        echo json_encode(['status' => 'error', 'message' => 'Internal server error']);
    }
    
} elseif ($event === 'payment.failed') {
    // Payment failed
    error_log("Payment failed for transaction: $transaction_id");
    
    try {
        require_once __DIR__ . '/../config/config.php';
        
        $pdo->beginTransaction();
        
        // Update admin purchase if exists
        $stmt = $pdo->prepare("
            UPDATE admin_sms_purchases 
            SET status = 'failed', api_response = ? 
            WHERE transaction_ref = ? AND status = 'pending'
        ");
        $stmt->execute([json_encode($data), $transaction_id]);
        
        // Update payment if exists
        $stmt = $pdo->prepare("
            UPDATE tblpayments 
            SET status = 'failed' 
            WHERE transaction_id = ? AND status = 'pending'
        ");
        $stmt->execute([$transaction_id]);
        
        $pdo->commit();
        
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error updating failed transaction: " . $e->getMessage());
    }
    
    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => 'Failure recorded']);
    
} elseif ($event === 'payment.pending') {
    // Payment initiated - just log it
    error_log("Payment pending for transaction: $transaction_id");
    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => 'Pending recorded']);
    
} else {
    error_log("Unknown webhook event: $event");
    http_response_code(200);
    echo json_encode(['status' => 'ignored', 'message' => 'Unknown event']);
}