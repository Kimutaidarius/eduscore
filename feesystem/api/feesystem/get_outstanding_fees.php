<?php
// /feesystem/api/feesystem/get_outstanding_fees.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../../includes/config.php';

$database = Database::getInstance();
$db = $database->getConnection();

$data = json_decode(file_get_contents('php://input'), true);
$school_id = $data['school_id'] ?? $_SESSION['school_id'];
$student_id = $data['student_id'] ?? 0;

if ($student_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Student ID is required']);
    exit;
}

try {
    // Get all debits and calculate outstanding balance
    $sql = "SELECT 
                ft.id,
                ft.amount,
                ft.description,
                ft.term,
                ft.academic_year,
                ft.created_at,
                vh.name as vote_head_name,
                vh.alias as vote_head_alias,
                COALESCE((
                    SELECT SUM(p.amount) 
                    FROM fee_transactions p 
                    WHERE p.student_id = ft.student_id 
                        AND p.transaction_type = 'payment'
                        AND p.group_id = ft.group_id
                ), 0) as paid_amount
            FROM fee_transactions ft
            LEFT JOIN vote_heads vh ON vh.id = ft.group_id
            WHERE ft.student_id = :student_id 
                AND ft.school_id = :school_id
                AND ft.transaction_type = 'debit'
            ORDER BY ft.created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':student_id' => $student_id,
        ':school_id' => $school_id
    ]);
    $fees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate balance for each fee
    foreach ($fees as &$fee) {
        $fee['balance'] = $fee['amount'] - $fee['paid_amount'];
    }
    
    echo json_encode([
        'success' => true,
        'fees' => $fees
    ]);
    
} catch (PDOException $e) {
    error_log("Get outstanding fees error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>