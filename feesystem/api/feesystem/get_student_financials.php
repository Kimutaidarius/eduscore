<?php
// /feesystem/api/feesystem/get_student_financials.php
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
$year = $data['year'] ?? date('Y');

if ($student_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Student ID is required']);
    exit;
}

try {
    // Get debits (fee_transactions with type 'debit')
    $debit_sql = "SELECT 
                    ft.id,
                    ft.amount,
                    ft.description,
                    ft.term,
                    ft.academic_year as year,
                    ft.created_at,
                    vh.name as vote_head_name,
                    vh.alias as vote_head_alias
                  FROM fee_transactions ft
                  LEFT JOIN vote_heads vh ON vh.id = ft.group_id
                  WHERE ft.student_id = :student_id 
                    AND ft.school_id = :school_id
                    AND ft.transaction_type = 'debit'
                    AND ft.academic_year = :year
                  ORDER BY ft.created_at DESC";
    
    $debit_stmt = $db->prepare($debit_sql);
    $debit_stmt->execute([
        ':student_id' => $student_id,
        ':school_id' => $school_id,
        ':year' => $year
    ]);
    $debits = $debit_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get payments (fee_transactions with type 'payment')
    $payment_sql = "SELECT 
                      ft.id,
                      ft.amount,
                      ft.description,
                      ft.created_at as payment_date,
                      ft.payment_mode,
                      ft.receipt_no,
                      ft.payment_code,
                      ft.academic_year as year
                    FROM fee_transactions ft
                    WHERE ft.student_id = :student_id 
                      AND ft.school_id = :school_id
                      AND ft.transaction_type = 'payment'
                      AND ft.academic_year = :year
                    ORDER BY ft.created_at DESC";
    
    $payment_stmt = $db->prepare($payment_sql);
    $payment_stmt->execute([
        ':student_id' => $student_id,
        ':school_id' => $school_id,
        ':year' => $year
    ]);
    $payments = $payment_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'debits' => $debits,
        'payments' => $payments
    ]);
    
} catch (PDOException $e) {
    error_log("Get student financials error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>