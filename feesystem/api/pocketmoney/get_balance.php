<?php
header('Content-Type: application/json');
require_once('../../includes/config.php');

$data = json_decode(file_get_contents('php://input'), true);
$school_id = $data['school_id'] ?? 0;
$student_id = $data['student_id'] ?? 0;

if (!$school_id || !$student_id) {
    echo json_encode(['success' => false, 'message' => 'School ID and Student ID required']);
    exit;
}

try {
    // Verify student belongs to school
    $stmt = $db->prepare("SELECT id FROM tblstudents WHERE id = ? AND school_id = ? AND Status = 'Active'");
    $stmt->execute([$student_id, $school_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        exit;
    }
    
    // Calculate balance
    $stmt = $db->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN type = 'deposit' THEN amount ELSE 0 END), 0) as total_deposits,
            COALESCE(SUM(CASE WHEN type = 'withdrawal' THEN amount ELSE 0 END), 0) as total_withdrawals,
            COALESCE(SUM(CASE WHEN type = 'deposit' THEN amount ELSE -amount END), 0) as balance
        FROM pocket_money_transactions 
        WHERE student_id = ? AND status = 'completed'
    ");
    $stmt->execute([$student_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true, 
        'balance' => (float)$result['balance'],
        'total_deposits' => (float)$result['total_deposits'],
        'total_withdrawals' => (float)$result['total_withdrawals']
    ]);
    
} catch (PDOException $e) {
    error_log("Error in get_balance: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>