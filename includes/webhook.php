<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/db_sms.php';

// Get callback data from M-Pesa
$callbackData = json_decode(file_get_contents('php://input'), true);

if (!$callbackData) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid callback data']);
    exit;
}

// Log the callback for debugging
file_put_contents('../logs/mpesa_callback_' . date('Y-m-d') . '.log', 
    date('Y-m-d H:i:s') . ' - ' . json_encode($callbackData) . "\n", 
    FILE_APPEND);

$checkoutRequestID = $callbackData['Body']['stkCallback']['CheckoutRequestID'] ?? '';
$resultCode = $callbackData['Body']['stkCallback']['ResultCode'] ?? 1;
$resultDesc = $callbackData['Body']['stkCallback']['ResultDesc'] ?? '';

try {
    // Find transaction
    $stmt = $pdo->prepare("SELECT * FROM mpesa_transactions WHERE checkout_request_id = ? OR reference = ?");
    $stmt->execute([$checkoutRequestID, $checkoutRequestID]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$transaction) {
        echo json_encode(['status' => 'error', 'message' => 'Transaction not found']);
        exit;
    }
    
    if ($resultCode == 0) { // Success
        $mpesaReceipt = $callbackData['Body']['stkCallback']['CallbackMetadata']['Item'][1]['Value'] ?? '';
        
        // Update transaction status
        $stmt = $pdo->prepare("UPDATE mpesa_transactions SET status = 'completed', mpesa_receipt = ?, result_desc = ?, completed_at = NOW(), updated_at = NOW() WHERE id = ?");
        $stmt->execute([$mpesaReceipt, $resultDesc, $transaction['id']]);
        
        // Add SMS credits to user's balance
        $stmt = $pdo->prepare("UPDATE users SET sms_balance = sms_balance + ? WHERE id = ?");
        $stmt->execute([$transaction['sms_units'], $transaction['user_id']]);
        
        // Also update tblschoolinfo if it exists
        $stmt = $pdo->prepare("UPDATE tblschoolinfo SET sms_balance = sms_balance + ? WHERE id = ?");
        $stmt->execute([$transaction['sms_units'], $transaction['user_id']]);
        
        echo json_encode(['status' => 'success', 'message' => 'Payment processed successfully']);
        
    } else { // Failed
        $stmt = $pdo->prepare("UPDATE mpesa_transactions SET status = 'failed', error_message = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$resultDesc, $transaction['id']]);
        
        echo json_encode(['status' => 'failed', 'message' => $resultDesc]);
    }
    
} catch (Exception $e) {
    error_log("Webhook error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>