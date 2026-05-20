<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once('../../includes/config.php');

$data = json_decode(file_get_contents('php://input'), true);
$school_id = $data['school_id'] ?? 0;

try {
    $payments = [];
    
    // Get payments from tblpayments
    $stmt = $db->prepare("
        SELECT 
            reference as transaction_id, 
            amount, 
            status, 
            created_at, 
            'M-PESA' as payment_method,
            'Payment' as description
        FROM tblpayments 
        WHERE school_id = ? AND status = 'completed'
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$school_id]);
    $tbl_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $payments = array_merge($payments, $tbl_payments);
    
    // Get payments from tbltransactions
    $stmt = $db->prepare("
        SELECT 
            transaction_code as transaction_id, 
            amount, 
            status, 
            created_at, 
            'M-PESA' as payment_method,
            'Transaction' as description
        FROM tbltransactions 
        WHERE school_id = ? AND status = 'success'
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$school_id]);
    $tbl_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $payments = array_merge($payments, $tbl_transactions);
    
    // Sort by created_at desc
    usort($payments, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    // Limit to 10 most recent
    $payments = array_slice($payments, 0, 10);
    
    echo json_encode(['success' => true, 'payments' => $payments]);
    
} catch (PDOException $e) {
    error_log("Error in get_payment_history: " . $e->getMessage());
    echo json_encode(['success' => true, 'payments' => []]);
}
?>