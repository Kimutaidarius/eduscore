<?php
// api/mpesa_callback.php
require_once __DIR__ . '/../config/config.php';

// Get the callback data
$callbackData = file_get_contents('php://input');
$data = json_decode($callbackData, true);

// Log the callback for debugging
error_log("M-Pesa Callback: " . $callbackData);

// Process the callback
if (isset($data['Body']['stkCallback'])) {
    $callback = $data['Body']['stkCallback'];
    $resultCode = $callback['ResultCode'];
    $checkoutRequestID = $callback['CheckoutRequestID'];
    
    if ($resultCode == 0) {
        // Successful transaction
        $amount = $callback['CallbackMetadata']['Item'][0]['Value'];
        $mpesaReceipt = $callback['CallbackMetadata']['Item'][1]['Value'];
        $phone = $callback['CallbackMetadata']['Item'][4]['Value'];
        
        // Update transaction in database
        $stmt = $pdo->prepare("
            UPDATE mpesa_transactions 
            SET status = 'completed', 
                mpesa_receipt = ?, 
                updated_at = NOW() 
            WHERE checkout_request_id = ?
        ");
        $stmt->execute([$mpesaReceipt, $checkoutRequestID]);
        
        // Get user_id from transaction
        $stmt = $pdo->prepare("SELECT user_id, amount FROM mpesa_transactions WHERE checkout_request_id = ?");
        $stmt->execute([$checkoutRequestID]);
        $transaction = $stmt->fetch();
        
        if ($transaction) {
            // Calculate SMS units (at KES 0.70 per SMS)
            $smsUnits = floor($transaction['amount'] / OPENSMS_PRICE_PER_SMS);
            
            // Update user's SMS balance
            $stmt = $pdo->prepare("UPDATE users SET sms_balance = sms_balance + ? WHERE id = ?");
            $stmt->execute([$smsUnits, $transaction['user_id']]);
            
            // Log the topup
            $stmt = $pdo->prepare("
                INSERT INTO sms_topups (user_id, amount, sms_units, mpesa_receipt, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$transaction['user_id'], $transaction['amount'], $smsUnits, $mpesaReceipt]);
        }
    } else {
        // Failed transaction
        $stmt = $pdo->prepare("
            UPDATE mpesa_transactions 
            SET status = 'failed', 
                result_desc = ?, 
                updated_at = NOW() 
            WHERE checkout_request_id = ?
        ");
        $stmt->execute([$callback['ResultDesc'], $checkoutRequestID]);
    }
}

// Respond to Safaricom
header('Content-Type: application/json');
echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Success']);
?>