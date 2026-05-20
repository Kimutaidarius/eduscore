<?php
// /feesystem/api/mpesa_callback.php
header('Content-Type: application/json');

require_once('../includes/config.php');

// Log incoming callback for debugging
file_put_contents(__DIR__ . '/mpesa_callback_log.txt', 
    date('Y-m-d H:i:s') . " - " . file_get_contents('php://input') . "\n", 
    FILE_APPEND);

$data = json_decode(file_get_contents('php://input'), true);

try {
    // Check if it's a STK Push callback
    if (isset($data['Body']['stkCallback'])) {
        $callback = $data['Body']['stkCallback'];
        $checkout_request_id = $callback['CheckoutRequestID'];
        $result_code = $callback['ResultCode'];
        $result_desc = $callback['ResultDesc'];
        
        if ($result_code == 0) {
            // Payment successful
            $mpesa_receipt = $callback['CallbackMetadata']['Item'][1]['Value'];
            $amount = $callback['CallbackMetadata']['Item'][0]['Value'];
            
            // Find the pending payment
            $stmt = $db->prepare("
                SELECT p.*, i.school_id 
                FROM saas_payments p
                JOIN saas_invoices i ON p.invoice_id = i.id
                WHERE p.mpesa_code = ? AND p.status = 'pending'
            ");
            $stmt->execute([$checkout_request_id]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($payment) {
                $db->beginTransaction();
                
                // Update payment record
                $stmt = $db->prepare("
                    UPDATE saas_payments 
                    SET status = 'completed', 
                        payment_date = NOW(),
                        transaction_id = ?
                    WHERE id = ?
                ");
                $stmt->execute([$mpesa_receipt, $payment['id']]);
                
                // Update invoice status
                $stmt = $db->prepare("
                    UPDATE saas_invoices 
                    SET status = 'paid', 
                        paid_at = NOW(),
                        payment_method = 'mpesa',
                        mpesa_code = ?
                    WHERE id = ?
                ");
                $stmt->execute([$mpesa_receipt, $payment['invoice_id']]);
                
                // Activate subscription if it was inactive
                $stmt = $db->prepare("
                    UPDATE saas_subscriptions s
                    JOIN saas_invoices i ON s.id = i.subscription_id
                    SET s.status = 'active', 
                        s.current_period_start = NOW()
                    WHERE i.id = ? AND s.status IN ('trial', 'expired', 'pending_payment')
                ");
                $stmt->execute([$payment['invoice_id']]);
                
                $db->commit();
                
                // Send confirmation email
                sendPaymentConfirmation($payment['school_id'], $payment['invoice_id'], $amount, $mpesa_receipt, $db);
            }
        } else {
            // Payment failed
            $stmt = $db->prepare("
                UPDATE saas_payments 
                SET status = 'failed', notes = ? 
                WHERE mpesa_code = ?
            ");
            $stmt->execute([$result_desc, $checkout_request_id]);
        }
    }
    
    echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Success']);
    
} catch (Exception $e) {
    error_log("M-Pesa callback error: " . $e->getMessage());
    echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Failed']);
}

function sendPaymentConfirmation($school_id, $invoice_id, $amount, $receipt, $db) {
    $stmt = $db->prepare("
        SELECT sc.school_name, sc.school_email, i.invoice_number
        FROM tblschoolinfo sc
        JOIN saas_invoices i ON sc.id = i.school_id
        WHERE sc.id = ? AND i.id = ?
    ");
    $stmt->execute([$school_id, $invoice_id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($data) {
        $subject = "Payment Confirmation - Invoice #{$data['invoice_number']}";
        $message = "
            <h2>Payment Received</h2>
            <p>Dear {$data['school_name']},</p>
            <p>We have received your payment of <strong>KES " . number_format($amount, 2) . "</strong>.</p>
            <p><strong>M-Pesa Receipt:</strong> {$receipt}<br>
            <strong>Invoice #:</strong> {$data['invoice_number']}</p>
            <p>Your subscription has been updated. Thank you for choosing EduScore!</p>
        ";
        // mail($data['school_email'], $subject, $message, "Content-Type: text/html\r\n");
    }
}
?>