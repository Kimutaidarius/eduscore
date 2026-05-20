<?php
// ajax/send_sms_handler.php
session_start();
header('Content-Type: application/json');

// Include database configuration
require_once '../config/config.php';

// Function to send JSON response
function sendJsonResponse($status, $message, $data = []) {
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data
    ]);
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    sendJsonResponse('error', 'Please login to continue');
}

$user_id = $_SESSION['user_id'];

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse('error', 'Invalid request method');
}

// CSRF check
$csrf_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
if (!isset($_SESSION['csrf_token']) || $csrf_token !== $_SESSION['csrf_token']) {
    sendJsonResponse('error', 'Invalid security token');
}

// Get and sanitize input
$recipient = isset($_POST['recipient']) ? sanitize($_POST['recipient']) : '';
$message = isset($_POST['message']) ? trim($_POST['message']) : '';
$schedule_time = isset($_POST['schedule_time']) && !empty($_POST['schedule_time']) ? $_POST['schedule_time'] : null;

// Fixed sender ID as EDUSCORE
$sender_id = 'EDUSCORE';

// Validate input
if (empty($recipient) || empty($message)) {
    sendJsonResponse('error', 'Please fill in all required fields');
}

// Validate phone number
$phone = validatePhone($recipient);
if (!$phone) {
    sendJsonResponse('error', 'Invalid phone number format. Use international format (e.g., 254712345678)');
}

// Format phone number for OpenSMS (ensure 254 format)
$formatted_phone = $phone;
if (substr($phone, 0, 1) === '0') {
    $formatted_phone = '254' . substr($phone, 1);
} elseif (substr($phone, 0, 3) !== '254' && strlen($phone) == 9) {
    $formatted_phone = '254' . $phone;
}

// Check message length
if (strlen($message) > 1600) {
    sendJsonResponse('error', 'Message too long. Maximum 1600 characters allowed.');
}

// Calculate SMS parts needed
$sms_parts = calculateSmsParts($message); // Returns number of SMS credits needed

// Get user balance (in SMS credits)
$stmt = $pdo->prepare("SELECT sms_balance FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    sendJsonResponse('error', 'User not found');
}

// Check if user has enough SMS credits
$user_sms_balance = (int)$user['sms_balance'];
if ($user_sms_balance < $sms_parts) {
    sendJsonResponse('error', 'Insufficient SMS credits. You need ' . $sms_parts . ' credits but have ' . $user_sms_balance . '. Please top up your account.');
}

try {
    // Begin transaction
    $pdo->beginTransaction();
    
    // Generate unique message ID
    $message_id = 'MSG' . time() . rand(1000, 9999);
    
    // Determine status (scheduled or pending)
    $status = $schedule_time ? 'scheduled' : 'pending';
    
    // Insert into sms_messages table
    $stmt = $pdo->prepare("
        INSERT INTO sms_messages (
            user_id, 
            message_id, 
            sender_id, 
            recipient, 
            message, 
            sms_count, 
            cost,
            status, 
            schedule_time,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    // cost is same as sms_count since 1 credit = 1 SMS
    $stmt->execute([
        $user_id,
        $message_id,
        $sender_id,
        $formatted_phone,
        $message,
        $sms_parts,
        $sms_parts, // Cost in SMS credits
        $status,
        $schedule_time
    ]);
    
    $sms_db_id = $pdo->lastInsertId();
    
    // If not scheduled, send immediately via OpenSMS gateway
    if (!$schedule_time) {
        // Get OpenSMS gateway instance
        $gateway = getSmsGateway();
        
        if (!$gateway instanceof OpenSMSGateway) {
            throw new Exception('SMS gateway not properly configured');
        }
        
        // Send via OpenSMS API
        $gateway_response = $gateway->sendSMS($formatted_phone, $message, $sender_id);
        
        if ($gateway_response['success']) {
            // Update message status to sent
            $stmt = $pdo->prepare("
                UPDATE sms_messages 
                SET status = 'sent', 
                    sent_at = NOW(),
                    gateway_message_id = ?,
                    gateway_response = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $gateway_response['message_id'] ?? null,
                json_encode($gateway_response['response'] ?? []),
                $sms_db_id
            ]);
            
            // Deduct SMS credits from user balance
            $stmt = $pdo->prepare("
                UPDATE users 
                SET sms_balance = sms_balance - ? 
                WHERE id = ?
            ");
            $stmt->execute([$sms_parts, $user_id]);
            
            // Log successful API request
            $logStmt = $pdo->prepare("
                INSERT INTO api_requests (
                    user_id, 
                    endpoint, 
                    method, 
                    ip_address, 
                    user_agent, 
                    response_code,
                    request_data,
                    response_data
                ) VALUES (?, 'send_sms', 'POST', ?, ?, 200, ?, ?)
            ");
            $logStmt->execute([
                $user_id,
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                json_encode(['recipient' => $formatted_phone, 'sms_parts' => $sms_parts]),
                json_encode($gateway_response)
            ]);
            
            // Commit transaction
            $pdo->commit();
            
            // Get updated balance
            $stmt = $pdo->prepare("SELECT sms_balance FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $new_balance = $stmt->fetchColumn();
            
            sendJsonResponse('success', 'SMS sent successfully!', [
                'message_id' => $message_id,
                'gateway_message_id' => $gateway_response['message_id'] ?? null,
                'sms_parts' => $sms_parts,
                'new_balance' => $new_balance
            ]);
        } else {
            // Update message status to failed
            $error_msg = $gateway_response['error'] ?? 'Gateway error';
            $stmt = $pdo->prepare("
                UPDATE sms_messages 
                SET status = 'failed', 
                    error_message = ?,
                    gateway_response = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $error_msg,
                json_encode($gateway_response),
                $sms_db_id
            ]);
            
            // Rollback transaction
            $pdo->rollBack();
            
            sendJsonResponse('error', 'Failed to send SMS: ' . $error_msg);
        }
    } else {
        // Message is scheduled
        $pdo->commit();
        sendJsonResponse('success', 'SMS scheduled successfully!', [
            'message_id' => $message_id,
            'schedule_time' => $schedule_time,
            'sms_parts' => $sms_parts
        ]);
    }
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Send SMS error: " . $e->getMessage());
    sendJsonResponse('error', 'Failed to send SMS: ' . $e->getMessage());
}
?>