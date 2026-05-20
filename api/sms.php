<?php
// api/sms.php - Main SMS API endpoint

require_once __DIR__ . '/../includes/SMSAPI.php';

// Enable CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key, X-API-Secret');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Get API keys from headers
$headers = getallheaders();
$api_key = isset($headers['X-API-Key']) ? $headers['X-API-Key'] : null;
$api_secret = isset($headers['X-API-Secret']) ? $headers['X-API-Secret'] : null;

if (!$api_key || !$api_secret) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'API key and secret are required'
    ]);
    exit();
}

// Initialize SMS API
$sms_api = new SMSAPI($api_key, $api_secret);

if (!$sms_api->authenticate($api_key, $api_secret)) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid API credentials or school not active'
    ]);
    exit();
}

// Route based on request method and endpoint
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);
$endpoint = basename($path);

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        if ($endpoint === 'balance') {
            // Get SMS balance
            $balance = $sms_api->getBalance();
            echo json_encode([
                'success' => true,
                'balance' => $balance,
                'price_per_sms' => SMSAPI::PRICE_PER_SMS
            ]);
        } elseif ($endpoint === 'logs') {
            // Get SMS logs
            $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;
            $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
            $logs = $sms_api->getSMSLogs($limit, $offset);
            echo json_encode([
                'success' => true,
                'logs' => $logs
            ]);
        } elseif ($endpoint === 'transactions') {
            // Get transaction history
            $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;
            $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
            $transactions = $sms_api->getTransactionHistory($limit, $offset);
            echo json_encode([
                'success' => true,
                'transactions' => $transactions
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Endpoint not found']);
        }
        break;
        
    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true);
        
        if ($endpoint === 'send') {
            // Send SMS
            if (!isset($input['recipients']) || !isset($input['message'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Recipients and message are required'
                ]);
                break;
            }
            
            $recipients = $input['recipients'];
            $message = $input['message'];
            $sender_id = isset($input['sender_id']) ? $input['sender_id'] : null;
            
            $result = $sms_api->sendSMS($recipients, $message, $sender_id);
            
            if ($result['success']) {
                http_response_code(200);
            } else {
                http_response_code(400);
            }
            
            echo json_encode($result);
            
        } elseif ($endpoint === 'purchase') {
            // Purchase SMS credits
            if (!isset($input['amount'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Amount is required'
                ]);
                break;
            }
            
            $amount = floatval($input['amount']);
            $payment_method = isset($input['payment_method']) ? $input['payment_method'] : 'mpesa';
            $mpesa_receipt = isset($input['mpesa_receipt']) ? $input['mpesa_receipt'] : null;
            
            $result = $sms_api->purchaseCredits($amount, $payment_method, $mpesa_receipt);
            
            if ($result['success']) {
                http_response_code(200);
            } else {
                http_response_code(400);
            }
            
            echo json_encode($result);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Endpoint not found']);
        }
        break;
        
    case 'PUT':
        if ($endpoint === 'complete-purchase') {
            // Complete a purchase (for M-PESA callback)
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['transaction_id']) || !isset($input['mpesa_receipt'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Transaction ID and M-PESA receipt are required'
                ]);
                break;
            }
            
            $result = $sms_api->completePurchase($input['transaction_id'], $input['mpesa_receipt']);
            
            if ($result['success']) {
                http_response_code(200);
            } else {
                http_response_code(400);
            }
            
            echo json_encode($result);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Endpoint not found']);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        break;
}
?>