<?php
header('Content-Type: application/json');
require_once('../../includes/config.php');

$data = json_decode(file_get_contents('php://input'), true);
$school_id = $data['school_id'] ?? 0;
$class_id = $data['class_id'] ?? '';

if (!$school_id) {
    echo json_encode(['success' => false, 'message' => 'School ID required']);
    exit;
}

try {
    // Get total students
    $sql = "SELECT COUNT(*) as total FROM tblstudents WHERE school_id = ? AND Status = 'Active'";
    $params = [$school_id];
    if ($class_id) {
        $sql .= " AND class_id = ?";
        $params[] = $class_id;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $total_students = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get total deposits (all time)
    $sql = "SELECT COALESCE(SUM(amount), 0) as total FROM pocket_money_transactions 
            WHERE school_id = ? AND type = 'deposit' AND status = 'completed'";
    $params = [$school_id];
    if ($class_id) {
        $sql .= " AND student_id IN (SELECT id FROM tblstudents WHERE class_id = ?)";
        $params[] = $class_id;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $total_deposits = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get total withdrawals (all time)
    $sql = "SELECT COALESCE(SUM(amount), 0) as total FROM pocket_money_transactions 
            WHERE school_id = ? AND type = 'withdrawal' AND status = 'completed'";
    $params = [$school_id];
    if ($class_id) {
        $sql .= " AND student_id IN (SELECT id FROM tblstudents WHERE class_id = ?)";
        $params[] = $class_id;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $total_withdrawals = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get outstanding balance (sum of all deposits minus withdrawals)
    $sql = "SELECT COALESCE(SUM(CASE WHEN type = 'deposit' THEN amount ELSE -amount END), 0) as balance 
            FROM pocket_money_transactions WHERE school_id = ? AND status = 'completed'";
    $params = [$school_id];
    if ($class_id) {
        $sql .= " AND student_id IN (SELECT id FROM tblstudents WHERE class_id = ?)";
        $params[] = $class_id;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $outstanding_balance = $stmt->fetch(PDO::FETCH_ASSOC)['balance'];
    
    echo json_encode([
        'success' => true,
        'total_students' => (int)$total_students,
        'total_deposits' => (float)$total_deposits,
        'total_withdrawals' => (float)$total_withdrawals,
        'outstanding_balance' => (float)$outstanding_balance
    ]);
    
} catch (PDOException $e) {
    error_log("Error in get_stats: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>