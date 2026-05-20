<?php
// api/sms.php - Main SMS API endpoint
header('Content-Type: application/json');
require_once '../config/db.php';
require_once '../includes/SMSApiGenerator.php';
require_once '../includes/SMSSender.php';

// Get Authorization header
$headers = getallheaders();
$auth = isset($headers['Authorization']) ? $headers['Authorization'] : '';

if (empty($auth) || !preg_match('/Bearer\s+(.*)$/i', $auth, $matches)) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized - Missing API key']);
    exit;
}

$api_key = $matches[1];
$api_secret = isset($headers['X-API-Secret']) ? $headers['X-API-Secret'] : '';

// Validate API credentials
$apiGenerator = new SMSApiGenerator($conn);
$validation = $apiGenerator->validateApiKey($api_key, $api_secret);

if (!$validation['valid']) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid API credentials']);
    exit;
}

$school_id = $validation['school_id'];
$api_key_id = $validation['api_key_id'];

// Rate limiting
require_once '../includes/RateLimiter.php';
$rateLimiter = new RateLimiter($conn);
if (!$rateLimiter->checkLimit($school_id, $api_key_id)) {
    http_response_code(429);
    echo json_encode(['error' => 'Rate limit exceeded. Please try again later.']);
    exit;
}

// Process request
$method = $_SERVER['REQUEST_METHOD'];
$request_uri = $_SERVER['REQUEST_URI'];
$endpoint = basename(parse_url($request_uri, PHP_URL_PATH));

$smsSender = new SMSSender($conn);

switch ($method) {
    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true);
        
        if ($endpoint == 'send') {
            // Send single SMS
            $result = $smsSender->sendSingle($school_id, $api_key_id, $input);
            echo json_encode($result);
        } 
        elseif ($endpoint == 'send-bulk') {
            // Send bulk SMS
            $result = $smsSender->sendBulk($school_id, $api_key_id, $input);
            echo json_encode($result);
        }
        elseif ($endpoint == 'schedule') {
            // Schedule SMS
            $result = $smsSender->scheduleSMS($school_id, $api_key_id, $input);
            echo json_encode($result);
        }
        else {
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
        }
        break;
        
    case 'GET':
        if ($endpoint == 'balance') {
            // Check SMS balance
            $balance = $smsSender->getBalance($school_id);
            echo json_encode(['balance' => $balance]);
        }
        elseif ($endpoint == 'status') {
            // Check message status
            $message_id = isset($_GET['message_id']) ? $_GET['message_id'] : '';
            $status = $smsSender->getMessageStatus($school_id, $message_id);
            echo json_encode($status);
        }
        elseif ($endpoint == 'history') {
            // Get message history
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            $history = $smsSender->getHistory($school_id, $page, $limit);
            echo json_encode($history);
        }
        else {
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

// Log API usage
require_once '../includes/APILogger.php';
$logger = new APILogger($conn);
$logger->logRequest($school_id, $api_key_id, $endpoint, $method, $input ?? []);