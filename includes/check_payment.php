<?php
// includes/check_payment.php
session_start();
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['school_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$reference = $_GET['reference'] ?? '';
if (!$reference) {
    echo json_encode(['status' => 'error', 'message' => 'Reference required']);
    exit;
}

try {
    $stmt = $dbh->prepare("
        SELECT status, amount, credits_purchased 
        FROM school_sms_purchases 
        WHERE transaction_id = ? AND school_id = ?
    ");
    $stmt->execute([$reference, $_SESSION['school_id']]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($payment) {
        echo json_encode([
            'status' => $payment['status'],
            'credits_added' => $payment['credits_purchased'] ?? 0,
            'amount' => $payment['amount']
        ]);
    } else {
        echo json_encode(['status' => 'pending', 'message' => 'Transaction not found']);
    }
} catch (PDOException $e) {
    error_log("Check payment error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}
?>