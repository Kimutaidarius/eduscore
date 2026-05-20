<?php
// ajax/bulk_sms_handler.php
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

// Get common parameters
$method = isset($_POST['method']) ? $_POST['method'] : '';
$sender_id = isset($_POST['sender_id']) ? sanitize($_POST['sender_id']) : 'OPENSMS';
$message = isset($_POST['message']) ? trim($_POST['message']) : '';
$schedule_time = isset($_POST['schedule_time']) && !empty($_POST['schedule_time']) ? $_POST['schedule_time'] : null;
$campaign_name = isset($_POST['campaign_name']) ? sanitize($_POST['campaign_name']) : 'Bulk Campaign';

// Validate common fields
if (empty($message)) {
    sendJsonResponse('error', 'Please enter a message');
}

if (strlen($message) > 1600) {
    sendJsonResponse('error', 'Message too long. Maximum 1600 characters allowed.');
}

// Get user balance
$stmt = $pdo->prepare("SELECT sms_balance FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    sendJsonResponse('error', 'User not found');
}

$user_sms_balance = (int)$user['sms_balance'];

try {
    $pdo->beginTransaction();
    
    $recipients = [];
    
    // Process based on method
    switch ($method) {
        case 'manual':
            // Get phone numbers from textarea
            $phoneNumbers = isset($_POST['phone_numbers']) ? $_POST['phone_numbers'] : '';
            $lines = explode("\n", $phoneNumbers);
            foreach ($lines as $line) {
                $phone = trim($line);
                if (!empty($phone)) {
                    $validated = validatePhone($phone);
                    if ($validated) {
                        $recipients[] = $validated;
                    }
                }
            }
            break;
            
        case 'csv':
            // Handle CSV file upload
            if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
                sendJsonResponse('error', 'Please upload a valid CSV file');
            }
            
            $csvFile = $_FILES['csv_file']['tmp_name'];
            $handle = fopen($csvFile, 'r');
            while (($row = fgetcsv($handle)) !== false) {
                $phone = trim($row[0]);
                if (!empty($phone)) {
                    $validated = validatePhone($phone);
                    if ($validated) {
                        $recipients[] = $validated;
                    }
                }
            }
            fclose($handle);
            break;
            
        case 'group':
            // Get selected groups
            $group_ids = isset($_POST['groups']) ? $_POST['groups'] : [];
            if (empty($group_ids)) {
                sendJsonResponse('error', 'Please select at least one contact group');
            }
            
            // Get all contacts from selected groups
            $placeholders = implode(',', array_fill(0, count($group_ids), '?'));
            $stmt = $pdo->prepare("SELECT phone FROM contacts WHERE group_id IN ($placeholders) AND user_id = ?");
            $params = array_merge($group_ids, [$user_id]);
            $stmt->execute($params);
            $contacts = $stmt->fetchAll();
            
            foreach ($contacts as $contact) {
                $validated = validatePhone($contact['phone']);
                if ($validated) {
                    $recipients[] = $validated;
                }
            }
            break;
            
        default:
            sendJsonResponse('error', 'Invalid method');
    }
    
    if (empty($recipients)) {
        sendJsonResponse('error', 'No valid phone numbers found');
    }
    
    // Calculate SMS parts and cost
    $sms_parts = calculateSmsParts($message);
    $total_recipients = count($recipients);
    $total_cost = $sms_parts * $total_recipients;
    
    // Check if user has enough balance
    if ($user_sms_balance < $total_cost) {
        sendJsonResponse('error', "Insufficient balance. Need $total_cost credits, but have $user_sms_balance. Please top up.");
    }
    
    // Generate unique campaign ID
    $campaign_id = 'CAMP_' . time() . '_' . rand(1000, 9999);
    $message_id = 'MSG_' . time() . '_' . rand(1000, 9999);
    
    // Prepare recipient string for database (store first 10 for preview)
    $recipient_preview = implode(',', array_slice($recipients, 0, 10));
    if (count($recipients) > 10) {
        $recipient_preview .= "... and " . (count($recipients) - 10) . " more";
    }
    
    // Insert campaign record
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
    
    $stmt->execute([
        $user_id,
        $message_id,
        $sender_id,
        $recipient_preview,
        $message,
        $sms_parts * $total_recipients,
        $total_cost,
        $schedule_time ? 'scheduled' : 'pending',
        $schedule_time
    ]);
    
    $sms_db_id = $pdo->lastInsertId();
    
    // If not scheduled, send immediately via OpenSMS API
    if (!$schedule_time) {
        // Get OpenSMS gateway instance
        $gateway = getSmsGateway();
        
        if (!$gateway instanceof OpenSMSGateway) {
            throw new Exception('SMS gateway not properly configured');
        }
        
        // Send bulk SMS via API
        $gateway_response = $gateway->sendBulkSMS($recipients, $message, $sender_id);
        
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
                $gateway_response['batch_id'] ?? null,
                json_encode($gateway_response['responses'] ?? []),
                $sms_db_id
            ]);
            
            // Deduct SMS credits from user balance
            $stmt = $pdo->prepare("
                UPDATE users 
                SET sms_balance = sms_balance - ? 
                WHERE id = ?
            ");
            $stmt->execute([$total_cost, $user_id]);
            
            // Log successful API request
            try {
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
                    ) VALUES (?, 'bulk_sms', 'POST', ?, ?, 200, ?, ?)
                ");
                $logStmt->execute([
                    $user_id,
                    $_SERVER['REMOTE_ADDR'] ?? '',
                    $_SERVER['HTTP_USER_AGENT'] ?? '',
                    json_encode(['recipients' => count($recipients), 'sms_parts' => $sms_parts]),
                    json_encode($gateway_response)
                ]);
            } catch (Exception $logError) {
                error_log("Failed to log API request: " . $logError->getMessage());
            }
            
            // Commit transaction
            $pdo->commit();
            
            // Get updated balance
            $stmt = $pdo->prepare("SELECT sms_balance FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $new_balance = $stmt->fetchColumn();
            
            sendJsonResponse('success', "Bulk SMS sent successfully to $total_recipients recipients!", [
                'campaign_id' => $campaign_id,
                'message_id' => $message_id,
                'recipients' => $total_recipients,
                'sms_parts' => $sms_parts,
                'total_cost' => $total_cost,
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
            
            sendJsonResponse('error', 'Failed to send bulk SMS: ' . $error_msg);
        }
    } else {
        // Message is scheduled
        $pdo->commit();
        sendJsonResponse('success', "Bulk SMS scheduled successfully to $total_recipients recipients!", [
            'campaign_id' => $campaign_id,
            'message_id' => $message_id,
            'recipients' => $total_recipients,
            'sms_parts' => $sms_parts,
            'total_cost' => $total_cost,
            'schedule_time' => $schedule_time
        ]);
    }
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Bulk SMS error: " . $e->getMessage());
    sendJsonResponse('error', 'Failed to send bulk SMS: ' . $e->getMessage());
}
?>