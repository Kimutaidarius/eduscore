<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once('../../includes/config.php');

$input = json_decode(file_get_contents('php://input'), true);
$school_id = $input['school_id'] ?? $_SESSION['school_id'];
$from_date = $input['from_date'] ?? date('Y-m-01');
$to_date = $input['to_date'] ?? date('Y-m-d');

try {
    $database = Database::getInstance();
    $db = $database->getConnection();
    
    // Receipts by payment mode
    $receipts_query = "SELECT 
                        payment_mode as mode,
                        COUNT(*) as count,
                        SUM(amount) as total
                      FROM fee_transactions
                      WHERE school_id = :school_id 
                      AND transaction_type = 'payment'
                      AND DATE(created_at) BETWEEN :from_date AND :to_date
                      GROUP BY payment_mode";
    
    $stmt = $db->prepare($receipts_query);
    $stmt->execute([
        ':school_id' => $school_id,
        ':from_date' => $from_date,
        ':to_date' => $to_date
    ]);
    $receipts_by_mode = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Payment vouchers by mode (debits)
    $vouchers_query = "SELECT 
                        payment_mode as mode,
                        COUNT(*) as count,
                        SUM(amount) as total
                      FROM fee_transactions
                      WHERE school_id = :school_id 
                      AND transaction_type = 'debit'
                      AND DATE(created_at) BETWEEN :from_date AND :to_date
                      GROUP BY payment_mode";
    
    $stmt = $db->prepare($vouchers_query);
    $stmt->execute([
        ':school_id' => $school_id,
        ':from_date' => $from_date,
        ':to_date' => $to_date
    ]);
    $vouchers_by_mode = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Votehead summary
    $votehead_query = "SELECT 
                        vh.name as votehead_name,
                        COALESCE(SUM(CASE WHEN ft.transaction_type = 'payment' THEN ft.amount ELSE 0 END), 0) as credit,
                        COALESCE(SUM(CASE WHEN ft.transaction_type = 'debit' THEN ft.amount ELSE 0 END), 0) as debit
                      FROM vote_heads vh
                      LEFT JOIN fee_transactions ft ON ft.vote_head_id = vh.id 
                        AND ft.school_id = :school_id
                        AND DATE(ft.created_at) BETWEEN :from_date AND :to_date
                      WHERE vh.school_id = :school_id
                      GROUP BY vh.id, vh.name
                      HAVING credit > 0 OR debit > 0
                      ORDER BY vh.name";
    
    $stmt = $db->prepare($votehead_query);
    $stmt->execute([
        ':school_id' => $school_id,
        ':from_date' => $from_date,
        ':to_date' => $to_date
    ]);
    $votehead_summary = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'receipts_by_mode' => $receipts_by_mode,
        'vouchers_by_mode' => $vouchers_by_mode,
        'votehead_summary' => $votehead_summary
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_daily_summary: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch daily summary: ' . $e->getMessage()
    ]);
}
?>