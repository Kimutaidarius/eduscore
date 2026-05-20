<?php
// api/test_callback.php - Test endpoint to manually complete a transaction
// WARNING: Remove this file after testing!

require_once __DIR__ . '/../config/config.php';

if (!isset($_GET['transaction_id']) && !isset($_GET['reference'])) {
    die("Please provide transaction_id or reference");
}

$transaction_id = $_GET['transaction_id'] ?? null;
$reference = $_GET['reference'] ?? null;

try {
    $pdo->beginTransaction();
    
    if ($transaction_id) {
        $stmt = $pdo->prepare("SELECT * FROM mpesa_transactions WHERE id = ?");
        $stmt->execute([$transaction_id]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM mpesa_transactions WHERE reference = ?");
        $stmt->execute([$reference]);
    }
    
    $transaction = $stmt->fetch();
    
    if (!$transaction) {
        die("Transaction not found");
    }
    
    echo "<h2>Transaction Found</h2>";
    echo "<pre>";
    print_r($transaction);
    echo "</pre>";
    
    if ($transaction['status'] === 'completed') {
        echo "<p style='color:orange'>Transaction already completed</p>";
        exit;
    }
    
    // Update transaction
    $update = $pdo->prepare("UPDATE mpesa_transactions SET status = 'completed', completed_at = NOW() WHERE id = ?");
    $update->execute([$transaction['id']]);
    
    // Update user balance
    $userUpdate = $pdo->prepare("UPDATE users SET sms_balance = sms_balance + ? WHERE id = ?");
    $userUpdate->execute([$transaction['sms_units'], $transaction['user_id']]);
    
    // Insert into sms_topups
    $topupInsert = $pdo->prepare("
        INSERT INTO sms_topups (user_id, amount, sms_units, payment_method, reference, status, created_at, completed_at)
        VALUES (?, ?, ?, 'mpesa', ?, 'completed', NOW(), NOW())
    ");
    $topupInsert->execute([
        $transaction['user_id'],
        $transaction['amount'],
        $transaction['sms_units'],
        $transaction['reference']
    ]);
    
    $pdo->commit();
    
    echo "<p style='color:green'>✅ Transaction completed successfully!</p>";
    echo "<p>Added {$transaction['sms_units']} SMS to user {$transaction['user_id']}</p>";
    
    // Get updated user balance
    $userStmt = $pdo->prepare("SELECT sms_balance FROM users WHERE id = ?");
    $userStmt->execute([$transaction['user_id']]);
    $newBalance = $userStmt->fetchColumn();
    
    echo "<p>New SMS balance: {$newBalance}</p>";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}