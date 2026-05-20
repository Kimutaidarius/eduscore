<?php
// api/school_sms.php - Endpoint for schools to send SMS using their own API key

require_once __DIR__ . '/../includes/SchoolSMSManager.php';

// Enable CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Get API key from header or request
$headers = getallheaders();
$api_key = isset($headers['X-API-Key']) ? $headers['X-API-Key'] : null;

if (!$api_key && isset($_REQUEST['api_key'])) {
    $api_key = $_REQUEST['api_key'];
}

if (!$api_key) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'API key is required'
    ]);
    exit();
}

// Find school by API key
global $pdo;
$stmt = $pdo->prepare("
    SELECT school_id FROM school_bulk_sms_keys 
    WHERE bulk_sms_api_key = ? AND status = 'active'
");
$stmt->execute([$api_key]);
$school = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$school) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid API key'
    ]);
    exit();
}

// Initialize SMS manager for this school
$sms_manager = new SchoolSMSManager($school['school_id']);

// Route based on endpoint
$endpoint = basename($_SERVER['REQUEST_URI'], '?' . $_SERVER['QUERY_STRING']);

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        if (strpos($endpoint, 'balance') !== false) {
            // Check balance
            $balance = $sms_manager->getSchoolBalance();
            echo json_encode($balance);
        } elseif (strpos($endpoint, 'stats') !== false) {
            // Get statistics
            $period = isset($_GET['period']) ? $_GET['period'] : 'month';
            $stats = $sms_manager->getStatistics($period);
            echo json_encode([
                'status' => 'success',
                'data' => $stats
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'message' => 'Endpoint not found'
            ]);
        }
        break;
        
    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (strpos($endpoint, 'send') !== false) {
            // Send SMS
            if (!isset($input['phone']) || !isset($input['message'])) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Phone and message are required'
                ]);
                break;
            }
            
            $phone = $input['phone'];
            $message = $input['message'];
            $sender_id = isset($input['sender_id']) ? $input['sender_id'] : null;
            
            $result = $sms_manager->sendSMS($phone, $message, $sender_id);
            
            if ($result['success']) {
                http_response_code(200);
            } else {
                http_response_code(400);
            }
            
            echo json_encode($result);
            
        } elseif (strpos($endpoint, 'bulk') !== false) {
            // Send bulk SMS
            if (!isset($input['recipients']) || !isset($input['message'])) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Recipients and message are required'
                ]);
                break;
            }
            
            $recipients = $input['recipients'];
            $message = $input['message'];
            $sender_id = isset($input['sender_id']) ? $input['sender_id'] : null;
            
            $result = $sms_manager->sendBulkSMS($recipients, $message, $sender_id);
            
            http_response_code(200);
            echo json_encode($result);
            
        } else {
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'message' => 'Endpoint not found'
            ]);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode([
            'status' => 'error',
            'message' => 'Method not allowed'
        ]);
        break;
}
?>