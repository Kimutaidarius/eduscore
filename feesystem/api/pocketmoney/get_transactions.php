<?php
header('Content-Type: application/json');
require_once('../../includes/config.php');

$data = json_decode(file_get_contents('php://input'), true);
$school_id = $data['school_id'] ?? 0;
$student_id = $data['student_id'] ?? 0;

if (!$school_id) {
    echo json_encode(['success' => false, 'message' => 'School ID required']);
    exit;
}

try {
    // If no student selected, fetch ALL transactions for the school (no date filter)
    if (empty($student_id)) {
        $sql = "
            SELECT 
                t.id,
                t.transaction_no,
                t.type,
                t.amount,
                t.transaction_date,
                t.reference,
                t.description,
                t.status,
                t.created_at,
                CONCAT(s.FirstName, ' ', COALESCE(s.SecondName, ''), ' ', COALESCE(s.LastName, '')) as student_name,
                s.AdmNo as admission_no,
                u.username as processed_by_name
            FROM pocket_money_transactions t
            JOIN tblstudents s ON t.student_id = s.id
            LEFT JOIN users u ON t.processed_by = u.id
            WHERE t.school_id = ?
            ORDER BY t.id ASC
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([$school_id]);
    } else {
        // Fetch all transactions for specific student (no date filter)
        $sql = "
            SELECT 
                t.id,
                t.transaction_no,
                t.type,
                t.amount,
                t.transaction_date,
                t.reference,
                t.description,
                t.status,
                t.created_at,
                CONCAT(s.FirstName, ' ', COALESCE(s.SecondName, ''), ' ', COALESCE(s.LastName, '')) as student_name,
                s.AdmNo as admission_no,
                u.username as processed_by_name
            FROM pocket_money_transactions t
            JOIN tblstudents s ON t.student_id = s.id
            LEFT JOIN users u ON t.processed_by = u.id
            WHERE t.school_id = ? AND t.student_id = ?
            ORDER BY t.id ASC
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([$school_id, $student_id]);
    }
    
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'transactions' => $transactions]);
    
} catch (PDOException $e) {
    error_log("Error in get_transactions: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>