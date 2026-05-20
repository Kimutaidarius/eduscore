<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/db_sms.php';

$checkoutRequestID = $_GET['checkoutRequestID'] ?? '';

if (empty($checkoutRequestID)) {
    echo json_encode(['status' => 'error', 'message' => 'No checkout request ID provided']);
    exit;
}

try {
    // Get transaction by checkout request ID
    $stmt = $pdo->prepare("SELECT * FROM mpesa_transactions WHERE checkout_request_id = ? OR reference = ?");
    $stmt->execute([$checkoutRequestID, $checkoutRequestID]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$transaction) {
        echo json_encode(['status' => 'error', 'message' => 'Transaction not found']);
        exit;
    }
    
    if ($transaction['status'] == 'completed') {
        echo json_encode(['status' => 'completed', 'message' => 'Payment completed successfully']);
    } elseif ($transaction['status'] == 'failed') {
        echo json_encode(['status' => 'failed', 'message' => 'Payment failed']);
    } else {
        echo json_encode(['status' => 'pending', 'message' => 'Payment pending']);
    }
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>