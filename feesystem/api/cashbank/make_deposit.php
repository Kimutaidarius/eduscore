<?php
header('Content-Type: application/json');
require_once('../../includes/config.php');

$data = json_decode(file_get_contents('php://input'), true);

$school_id = $data['school_id'] ?? 0;
$user_id = $data['user_id'] ?? 0;
$bank_account_id = $data['bank_account_id'] ?? 0;
$amount = $data['amount'] ?? 0;
$transaction_date = $data['transaction_date'] ?? date('Y-m-d');
$reference = $data['reference'] ?? '';
$description = $data['description'] ?? '';

// Validation
if (!$school_id || !$bank_account_id || !$amount || $amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit;
}

try {
    // Start transaction
    $db->beginTransaction();
    
    // Update bank account balance
    $updateStmt = $db->prepare("UPDATE bank_accounts 
                                SET current_balance = current_balance + ? 
                                WHERE id = ? AND school_id = ?");
    $updateStmt->execute([$amount, $bank_account_id, $school_id]);
    
    if ($updateStmt->rowCount() === 0) {
        throw new Exception('Bank account not found');
    }
    
    // Record transaction
    $insertStmt = $db->prepare("INSERT INTO bank_transactions 
                                (school_id, bank_account_id, transaction_type, amount, transaction_date, reference, description, created_by) 
                                VALUES (?, ?, 'deposit', ?, ?, ?, ?, ?)");
    $insertStmt->execute([$school_id, $bank_account_id, $amount, $transaction_date, $reference, $description, $user_id]);
    
    $db->commit();
    echo json_encode(['success' => true, 'message' => 'Deposit recorded successfully']);
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Error in make_deposit: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>