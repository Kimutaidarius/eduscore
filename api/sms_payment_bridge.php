<?php
// /api/sms_payment_bridge.php
// OPEN BRIDGE - No authentication (internal API)

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://eduscore.gt.tc');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // Find database config file
    $possible_paths = [
        __DIR__ . '/../config/db_sms.php',
        __DIR__ . '/../includes/db_sms.php',
        __DIR__ . '/../../admin/config/db_sms.php'
    ];
    
    $config_path = null;
    foreach ($possible_paths as $path) {
        if (file_exists($path)) {
            $config_path = $path;
            break;
        }
    }
    
    if (!$config_path) {
        throw new Exception("Config file not found");
    }
    
    require_once $config_path;
    
    // Define PayHero constants if not defined
    if (!defined('PAYHERO_BASE_URL')) {
        define('PAYHERO_BASE_URL', 'https://backend.payhero.co.ke/api/v2');
    }
    if (!defined('PAYHERO_CHANNEL_ID')) {
        define('PAYHERO_CHANNEL_ID', 7550);
    }
    if (!defined('PAYHERO_BASIC_AUTH_TOKEN')) {
        define('PAYHERO_BASIC_AUTH_TOKEN', 'Basic OFdDU3BUV2c5NGs4OGo3QUgyV3c6Zk1Jb044MURLQnphbUNKdnZhWGNIZDhmVllRWXNUbHh0QVNrVk5CRw==');
    }
    if (!defined('PAYHERO_CALLBACK_URL')) {
        define('PAYHERO_CALLBACK_URL', 'https://eduscore.co.ke/includes/mpesa_callback_sms.php?token=eduscore_secure_2024');
    }
    
    $action = $_GET['action'] ?? '';
    
    if ($action === 'initiate_payment') {
        handleInitiatePayment($db);
    } elseif ($action === 'check_status') {
        handleCheckStatus($db);
    } elseif ($action === 'test') {
        echo json_encode(['success' => true, 'message' => 'Bridge API is working']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Throwable $e) {
    error_log("Bridge FATAL: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Bridge error: ' . $e->getMessage()
    ]);
}

function handleInitiatePayment($db) {
    $raw_input = file_get_contents('php://input');
    $input = json_decode($raw_input, true);
    
    if (!$input) {
        echo json_encode(['success' => false, 'message' => 'Invalid request data']);
        return;
    }
    
    $required = ['admin_id', 'quantity', 'total_amount', 'phone_number', 'customer_name'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            echo json_encode(['success' => false, 'message' => "Missing field: $field"]);
            return;
        }
    }
    
    $transaction_ref = 'SMS-' . date('YmdHis') . '-' . rand(1000, 9999);
    $price_per_sms = $input['total_amount'] / $input['quantity'];
    
    try {
        $stmt = $db->prepare("
            INSERT INTO sms_purchases 
            (admin_id, transaction_id, quantity, price_per_sms, total_cost, payment_method, phone_number, status, created_at) 
            VALUES (?, ?, ?, ?, ?, 'mpesa', ?, 'pending', NOW())
        ");
        
        $stmt->execute([
            $input['admin_id'],
            $transaction_ref,
            $input['quantity'],
            $price_per_sms,
            $input['total_amount'],
            $input['phone_number']
        ]);
        
        $purchase_id = $db->lastInsertId();
        
        $payhero_response = initiatePayHeroPayment(
            $input['phone_number'],
            (int)$input['total_amount'],
            $transaction_ref,
            $input['customer_name']
        );
        
        if ($payhero_response['success']) {
            if (isset($payhero_response['checkout_request_id'])) {
                $stmt = $db->prepare("UPDATE sms_purchases SET checkout_request_id = ? WHERE id = ?");
                $stmt->execute([$payhero_response['checkout_request_id'], $purchase_id]);
            }
            
            echo json_encode([
                'success' => true,
                'transaction_id' => $transaction_ref,
                'checkout_request_id' => $payhero_response['checkout_request_id'] ?? null
            ]);
        } else {
            $stmt = $db->prepare("UPDATE sms_purchases SET status = 'failed' WHERE id = ?");
            $stmt->execute([$purchase_id]);
            
            echo json_encode([
                'success' => false,
                'message' => $payhero_response['message']
            ]);
        }
        
    } catch (Exception $e) {
        error_log("Initiate payment error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function handleCheckStatus($db) {
    $transaction_id = $_GET['transaction_id'] ?? '';
    
    if (empty($transaction_id)) {
        echo json_encode(['success' => false, 'message' => 'Transaction ID required']);
        return;
    }
    
    try {
        $stmt = $db->prepare("SELECT status, quantity FROM sms_purchases WHERE transaction_id = ?");
        $stmt->execute([$transaction_id]);
        $purchase = $stmt->fetch();
        
        if ($purchase) {
            echo json_encode([
                'success' => true,
                'status' => $purchase['status'],
                'quantity' => $purchase['quantity']
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Transaction not found']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function initiatePayHeroPayment($phone, $amount, $reference, $customer_name) {
    $endpoint = PAYHERO_BASE_URL . '/payments';
    
    $phone = preg_replace('/\D/', '', $phone);
    if (substr($phone, 0, 3) === '254') {
        $phone = '0' . substr($phone, 3);
    }
    
    $payload = [
        'amount' => (int)$amount,
        'phone_number' => $phone,
        'channel_id' => (int)PAYHERO_CHANNEL_ID,
        'provider' => 'm-pesa',
        'external_reference' => $reference,
        'customer_name' => $customer_name,
        'callback_url' => PAYHERO_CALLBACK_URL
    ];
    
    error_log("PayHero Request: " . json_encode($payload));
    
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: ' . PAYHERO_BASIC_AUTH_TOKEN
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    error_log("PayHero Response: HTTP $http_code - " . substr($response, 0, 500));
    
    if ($curl_error) {
        return ['success' => false, 'message' => 'Connection error: ' . $curl_error];
    }
    
    $result = json_decode($response, true);
    
    if (!$result) {
        return ['success' => false, 'message' => 'Invalid response from payment gateway'];
    }
    
    if ($http_code == 201 || $http_code == 200) {
        if (isset($result['success']) && $result['success'] === true) {
            return [
                'success' => true,
                'checkout_request_id' => $result['CheckoutRequestID'] ?? null
            ];
        }
        if (isset($result['CheckoutRequestID'])) {
            return [
                'success' => true,
                'checkout_request_id' => $result['CheckoutRequestID']
            ];
        }
    }
    
    $error_msg = $result['message'] ?? $result['error'] ?? 'Payment initiation failed';
    return ['success' => false, 'message' => $error_msg];
}
?>