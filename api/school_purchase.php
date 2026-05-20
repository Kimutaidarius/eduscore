<?php
// api/school_purchase.php - Handle SMS credit purchases

require_once __DIR__ . '/../includes/SchoolSMSManager.php';
require_once __DIR__ . '/../includes/MpesaSTKPush.php'; // Your M-PESA integration

header('Content-Type: application/json');

// Check admin/school authentication
session_start();
if (!isset($_SESSION['school_id']) && !isset($_SESSION['admin_logged_in'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required'
    ]);
    exit();
}

$school_id = isset($_SESSION['school_id']) ? $_SESSION['school_id'] : null;

if (!$school_id && isset($_POST['school_id'])) {
    $school_id = intval($_POST['school_id']);
}

if (!$school_id) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'School ID is required'
    ]);
    exit();
}

$sms_manager = new SchoolSMSManager($school_id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    if ($action === 'initiate_purchase') {
        $credits = intval($_POST['credits']);
        $amount = floatval($_POST['amount']);
        $payment_method = $_POST['payment_method'] ?? 'mpesa';
        
        if ($credits <= 0 || $amount <= 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid credits or amount'
            ]);
            exit();
        }
        
        $result = $sms_manager->purchaseCredits($school_id, $credits, $amount, $payment_method);
        
        if ($result['success'] && $payment_method === 'mpesa') {
            // Initiate M-PESA STK Push
            $phone = $_POST['phone'] ?? null;
            if ($phone) {
                $mpesa = new MpesaSTKPush();
                $stk_result = $mpesa->stkPush($phone, $amount, $result['transaction_id']);
                
                if ($stk_result['success']) {
                    $result['mpesa_request'] = $stk_result;
                }
            }
        }
        
        echo json_encode($result);
        
    } elseif ($action === 'verify_payment') {
        $transaction_id = $_POST['transaction_id'];
        $mpesa_receipt = $_POST['mpesa_receipt'] ?? null;
        
        $result = $sms_manager->completePurchase($transaction_id, $mpesa_receipt);
        echo json_encode($result);
    }
}
?>