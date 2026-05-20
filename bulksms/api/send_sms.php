<?php
header('Content-Type: application/json');
require_once '../config/config.php';

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    $input = $_POST;
}

// Validate API key
$api_key = isset($input['api_key']) ? $input['api_key'] : '';

if (empty($api_key)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'API key is required'
    ]);
    exit();
}

// Get API key from database
$stmt = $pdo->prepare("
    SELECT k.*, u.id as user_id, u.sms_balance, u.status as user_status 
    FROM api_keys k 
    JOIN users u ON k.user_id = u.id 
    WHERE k.api_key = ? AND k.status = 'active'
");
$stmt->execute([$api_key]);
$key_data = $stmt->fetch();

if (!$key_data) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid or inactive API key'
    ]);
    exit();
}

// Check if user is active
if ($key_data['user_status'] != 'active') {
    http_response_code(403);
    echo json_encode([
        'status' => 'error',
        'message' => 'User account is not active'
    ]);
    exit();
}

$user_id = $key_data['user_id'];

// Validate required parameters
$phone = isset($input['phone']) ? $input['phone'] : '';
$message = isset($input['message']) ? $input['message'] : '';
$sender_id = isset($input['sender_id']) ? $input['sender_id'] : 'EduScore';

if (empty($phone) || empty($message)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Phone and message are required'
    ]);
    exit();
}

// Validate phone
$phone = validatePhone($phone);
if (!$phone) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid phone number format'
    ]);
    exit();
}

// Calculate SMS parts and cost
$sms_parts = calculateSmsParts($message);
$cost = $sms_parts;

// Check balance
if ($key_data['sms_balance'] < $cost) {
    http_response_code(402);
    echo json_encode([
        'status' => 'error',
        'message' => 'Insufficient SMS balance'
    ]);
    exit();
}

// Rate limiting check
$client_ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
$minute_ago = date('Y-m-d H:i:s', strtotime('-1 minute'));

$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM api_requests 
    WHERE user_id = ? AND created_at > ? 
    AND endpoint = '/api/send_sms.php'
");
$stmt->execute([$user_id, $minute_ago]);
$requests_last_minute = $stmt->fetchColumn();

if ($requests_last_minute >= API_RATE_LIMIT) {
    http_response_code(429);
    echo json_encode([
        'status' => 'error',
        'message' => 'Rate limit exceeded. Maximum ' . API_RATE_LIMIT . ' requests per minute.'
    ]);
    exit();
}

// Generate message ID
$message_id = generateMessageId();

// Save to database
$stmt = $pdo->prepare("
    INSERT INTO sms_messages (user_id, api_key_id, message_id, sender_id, recipient, message, sms_count, cost, status) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
");
$stmt->execute([$user_id, $key_data['id'], $message_id, $sender_id, $phone, $message, $sms_parts, $cost]);
$msg_db_id = $pdo->lastInsertId();

// Log API request
logApiRequest(
    $pdo, 
    $user_id, 
    $key_data['id'], 
    '/api/send_sms.php', 
    $_SERVER['REQUEST_METHOD'], 
    $client_ip, 
    $_SERVER['HTTP_USER_AGENT'] ?? '', 
    json_encode($input), 
    200
);

// Send SMS via gateway
$gateway_response = sendSmsViaGateway($phone, $message, $sender_id);

if ($gateway_response && isset($gateway_response['success']) && $gateway_response['success']) {
    // Update status
    $stmt = $pdo->prepare("UPDATE sms_messages SET status = 'sent', sent_at = NOW() WHERE id = ?");
    $stmt->execute([$msg_db_id]);
    
    // Deduct balance
    $stmt = $pdo->prepare("UPDATE users SET sms_balance = sms_balance - ? WHERE id = ?");
    $stmt->execute([$cost, $user_id]);
    
    // Update API key last used
    $stmt = $pdo->prepare("UPDATE api_keys SET last_used = NOW() WHERE id = ?");
    $stmt->execute([$key_data['id']]);
    
    echo json_encode([
        'status' => 'success',
        'message' => 'SMS sent successfully',
        'data' => [
            'message_id' => $message_id,
            'recipient' => $phone,
            'sms_parts' => $sms_parts,
            'cost' => $cost,
            'balance_remaining' => $key_data['sms_balance'] - $cost
        ]
    ]);
} else {
    // Update status to failed
    $error_msg = $gateway_response['error'] ?? 'Gateway error';
    $stmt = $pdo->prepare("UPDATE sms_messages SET status = 'failed', error_message = ? WHERE id = ?");
    $stmt->execute([$error_msg, $msg_db_id]);
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to send SMS: ' . $error_msg
    ]);
}