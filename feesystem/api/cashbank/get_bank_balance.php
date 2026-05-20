<?php
header('Content-Type: application/json');
require_once('../../includes/config.php');

$data = json_decode(file_get_contents('php://input'), true);
$school_id = $data['school_id'] ?? 0;
$bank_account_id = $data['bank_account_id'] ?? null;

if (!$school_id) {
    echo json_encode(['success' => false, 'message' => 'School ID required']);
    exit;
}

try {
    if ($bank_account_id) {
        $stmt = $db->prepare("SELECT current_balance FROM bank_accounts WHERE id = ? AND school_id = ?");
        $stmt->execute([$bank_account_id, $school_id]);
        $row = $stmt->fetch();
        $balance = $row ? $row['current_balance'] : 0;
    } else {
        $stmt = $db->prepare("SELECT SUM(current_balance) as total_balance FROM bank_accounts WHERE school_id = ? AND is_active = 1");
        $stmt->execute([$school_id]);
        $row = $stmt->fetch();
        $balance = $row ? $row['total_balance'] : 0;
    }
    
    echo json_encode(['success' => true, 'balance' => $balance ?: 0]);
} catch (PDOException $e) {
    error_log("Error in get_bank_balance: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred', 'balance' => 0]);
}
?>