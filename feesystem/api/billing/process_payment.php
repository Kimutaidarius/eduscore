<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once('../../includes/config.php');
session_start();

$data = json_decode(file_get_contents('php://input'), true);
$school_id = $data['school_id'] ?? $_SESSION['school_id'] ?? 0;
$plan_id = $data['plan_id'] ?? 0;
$plan_name = $data['plan_name'] ?? '';
$amount = $data['amount'] ?? 0;
$payment_method = $data['payment_method'] ?? 'mpesa';
$phone_number = $data['phone_number'] ?? '';

if (!$school_id) {
    echo json_encode(['success' => false, 'message' => 'School ID required']);
    exit;
}

try {
    // For M-Pesa payments, initiate STK push
    if ($payment_method === 'mpesa' && $phone_number) {
        // Format phone number
        $phone = preg_replace('/[^0-9]/', '', $phone_number);
        if (strlen($phone) == 9) {
            $phone = '254' . $phone;
        }
        
        // Here you would integrate with M-Pesa API
        // For now, simulate successful payment
        // In production, call stk_push.php here
        
        // Record payment
        $reference = 'UPG-' . strtoupper(uniqid());
        $stmt = $db->prepare("
            INSERT INTO tblpayments (school_id, phone, amount, reference, status, created_at) 
            VALUES (?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([$school_id, $phone, $amount, $reference]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'STK push sent. Please check your phone to complete payment.',
            'reference' => $reference
        ]);
        exit;
    }
    
    // For bank transfer or card, record as pending
    $reference = 'INV-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    $stmt = $db->prepare("
        INSERT INTO invoices (school_id, invoice_number, total_amount, status, due_date, created_at) 
        VALUES (?, ?, ?, 'PENDING', DATE_ADD(NOW(), INTERVAL 7 DAY), NOW())
    ");
    $stmt->execute([$school_id, $reference, $amount]);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Payment initiated. Please complete the payment using the provided instructions.',
        'reference' => $reference
    ]);
    
} catch (PDOException $e) {
    error_log("Error in process_payment: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>