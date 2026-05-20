<?php
header('Content-Type: application/json');
require_once('../../includes/config.php');

$data = json_decode(file_get_contents('php://input'), true);
$school_id = $data['school_id'] ?? 0;
$user_id = $data['user_id'] ?? 0;
$student_id = $data['student_id'] ?? 0;
$amount = $data['amount'] ?? 0;
$transaction_date = $data['transaction_date'] ?? date('Y-m-d');
$reference = $data['reference'] ?? '';
$description = $data['description'] ?? '';

if (!$school_id || !$user_id || !$student_id || $amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid request parameters']);
    exit;
}

try {
    $db->beginTransaction();
    
    // Verify student belongs to school and is active
    $stmt = $db->prepare("SELECT id FROM tblstudents WHERE id = ? AND school_id = ? AND Status = 'Active'");
    $stmt->execute([$student_id, $school_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Student not found or inactive']);
        exit;
    }
    
    // Check current balance
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(CASE WHEN type = 'deposit' THEN amount ELSE -amount END), 0) as balance 
        FROM pocket_money_transactions 
        WHERE student_id = ?
    ");
    $stmt->execute([$student_id]);
    $balance = $stmt->fetch(PDO::FETCH_ASSOC)['balance'];
    
    if ($balance < $amount) {
        echo json_encode(['success' => false, 'message' => 'Insufficient balance. Available: KES ' . number_format($balance, 2)]);
        exit;
    }
    
    // Generate transaction number
    $year = date('Y');
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM pocket_money_transactions WHERE school_id = ? AND YEAR(created_at) = ?");
    $stmt->execute([$school_id, $year]);
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] + 1;
    $transaction_no = "PMW-" . $year . "-" . str_pad($count, 5, '0', STR_PAD_LEFT);
    
    // Insert withdrawal
    $stmt = $db->prepare("
        INSERT INTO pocket_money_transactions 
        (school_id, student_id, transaction_no, type, amount, transaction_date, reference, description, processed_by, status, created_at)
        VALUES (?, ?, ?, 'withdrawal', ?, ?, ?, ?, ?, 'completed', NOW())
    ");
    $stmt->execute([
        $school_id, 
        $student_id, 
        $transaction_no, 
        $amount, 
        $transaction_date, 
        $reference, 
        $description, 
        $user_id
    ]);
    
    $db->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Withdrawal recorded successfully', 
        'transaction_no' => $transaction_no,
        'new_balance' => $balance - $amount
    ]);
    
} catch (PDOException $e) {
    $db->rollBack();
    error_log("Error in make_withdrawal: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>