<?php
header('Content-Type: application/json');
require_once('../../includes/config.php');

$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? 0;
$school_id = $data['school_id'] ?? 0;

if (!$id || !$school_id) {
    echo json_encode(['success' => false, 'message' => 'Transaction ID and School ID required']);
    exit;
}

try {
    // First get the transaction details
    $stmt = $db->prepare("
        SELECT 
            t.*,
            CONCAT(s.FirstName, ' ', COALESCE(s.SecondName, ''), ' ', COALESCE(s.LastName, '')) as student_name,
            s.AdmNo as admission_no,
            u.username as processed_by_name
        FROM pocket_money_transactions t
        JOIN tblstudents s ON t.student_id = s.id
        LEFT JOIN users u ON t.processed_by = u.id
        WHERE t.id = ? AND t.school_id = ?
    ");
    $stmt->execute([$id, $school_id]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($transaction) {
        // Calculate running balance up to this transaction for the same student
        $stmt2 = $db->prepare("
            SELECT 
                SUM(CASE WHEN type = 'deposit' THEN amount ELSE -amount END) as balance
            FROM pocket_money_transactions 
            WHERE student_id = ? AND id <= ? AND status = 'completed'
        ");
        $stmt2->execute([$transaction['student_id'], $id]);
        $balance_result = $stmt2->fetch(PDO::FETCH_ASSOC);
        $balance_after = $balance_result['balance'] ?? 0;
        
        $transaction['balance_after'] = $balance_after;
        
        echo json_encode(['success' => true, 'transaction' => $transaction]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Transaction not found']);
    }
    
} catch (PDOException $e) {
    error_log("Error in get_transaction: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>