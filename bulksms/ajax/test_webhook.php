<?php
// api/test_webhook.php - Test Lipana webhook format
// This file helps you test if your callback is working

require_once __DIR__ . '/../config/config.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html>
<head>
    <title>Test Webhook</title>
    <style>
        body { font-family: Arial; padding: 20px; }
        .success { color: green; }
        .error { color: red; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>Lipana Webhook Tester</h1>";

// Get reference from URL or use default
$reference = $_GET['ref'] ?? 'SMS-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
$amount = $_GET['amount'] ?? 10;
$phone = $_GET['phone'] ?? '+254746614238';

// First, check if there's a pending transaction with this reference
$stmt = $pdo->prepare("SELECT * FROM mpesa_transactions WHERE reference = ?");
$stmt->execute([$reference]);
$transaction = $stmt->fetch();

if (!$transaction) {
    // Create a test transaction if none exists
    echo "<p class='error'>No transaction found with reference: $reference</p>";
    
    // You can create one manually
    echo "<p>To create a test transaction, <a href='../user/topup.php'>make a payment request first</a></p>";
} else {
    echo "<h2>Found Transaction</h2>";
    echo "<pre>";
    print_r($transaction);
    echo "</pre>";
    
    // Simulate Lipana webhook
    $testPayload = [
        'event' => 'transaction.success',
        'transaction_id' => 'TXN' . time(),
        'amount' => (int)$transaction['amount'],
        'phone' => '+254' . ltrim($transaction['phone'], '254'),
        'reference' => $transaction['reference'],
        'timestamp' => date('c')
    ];
    
    $jsonPayload = json_encode($testPayload);
    
    // Generate signature (if you have secret)
    $secret = defined('LIPANA_WEBHOOK_SECRET') ? LIPANA_WEBHOOK_SECRET : 'test_secret';
    $signature = hash_hmac('sha256', $jsonPayload, $secret);
    
    echo "<h2>Sending Test Webhook</h2>";
    echo "<h3>Payload:</h3>";
    echo "<pre>" . json_encode($testPayload, JSON_PRETTY_PRINT) . "</pre>";
    
    // Send to your callback
    $ch = curl_init('https://edu-score.app/api/lipana_callback.php');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $jsonPayload,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-Lipana-Signature: ' . $signature
        ],
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    echo "<h3>Response (HTTP $httpCode):</h3>";
    if ($error) {
        echo "<p class='error'>CURL Error: $error</p>";
    }
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
    
    // Check if transaction was updated
    $stmt = $pdo->prepare("SELECT * FROM mpesa_transactions WHERE id = ?");
    $stmt->execute([$transaction['id']]);
    $updated = $stmt->fetch();
    
    echo "<h3>Updated Transaction:</h3>";
    echo "<pre>";
    print_r($updated);
    echo "</pre>";
    
    // Check user balance
    if ($updated && $updated['status'] === 'completed') {
        $userStmt = $pdo->prepare("SELECT sms_balance FROM users WHERE id = ?");
        $userStmt->execute([$updated['user_id']]);
        $balance = $userStmt->fetchColumn();
        
        echo "<p class='success'>✅ User {$updated['user_id']} new balance: {$balance} SMS</p>";
    }
}

echo "</body></html>";