<?php
header('Content-Type: application/json');
require_once('../../includes/config.php');

$data = json_decode(file_get_contents('php://input'), true);
$school_id = $data['school_id'] ?? 0;

if (!$school_id) {
    echo json_encode(['success' => false, 'message' => 'School ID required']);
    exit;
}

try {
    // Get all banks for the school
    $bankStmt = $db->prepare("
        SELECT 
            b.id as bank_id,
            b.name as bank_name,
            NULL as account_id,
            NULL as account_name,
            NULL as account_number,
            NULL as branch,
            NULL as current_balance
        FROM banks b
        WHERE b.school_id = ?
        ORDER BY b.name
    ");
    $bankStmt->execute([$school_id]);
    $banks = $bankStmt->fetchAll();
    
    // Get all bank accounts for the school
    $accountStmt = $db->prepare("
        SELECT 
            ba.id as account_id,
            ba.bank_id,
            ba.account_name,
            ba.account_number,
            ba.branch,
            ba.current_balance,
            ba.is_active
        FROM bank_accounts ba
        WHERE ba.school_id = ? AND ba.is_active = 1
        ORDER BY ba.account_name
    ");
    $accountStmt->execute([$school_id]);
    $accounts = $accountStmt->fetchAll();
    
    // Create a map of bank_id to accounts
    $accountsByBank = [];
    foreach ($accounts as $account) {
        $bankId = $account['bank_id'];
        if (!isset($accountsByBank[$bankId])) {
            $accountsByBank[$bankId] = [];
        }
        $accountsByBank[$bankId][] = $account;
    }
    
    // Build the response - combine banks with their accounts
    $result = [];
    foreach ($banks as $bank) {
        $bankData = [
            'bank_id' => $bank['bank_id'],
            'bank_name' => $bank['bank_name'],
            'accounts' => $accountsByBank[$bank['bank_id']] ?? []
        ];
        
        // If there are no accounts, still include the bank
        if (empty($bankData['accounts'])) {
            $bankData['has_accounts'] = false;
        } else {
            $bankData['has_accounts'] = true;
        }
        
        $result[] = $bankData;
    }
    
    echo json_encode(['success' => true, 'banks' => $result]);
    
} catch (PDOException $e) {
    error_log("Error in get_bank_accounts: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred: ' . $e->getMessage()]);
}
?>