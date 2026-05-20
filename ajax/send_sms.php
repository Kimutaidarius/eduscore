<?php
// ajax/send_sms.php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['school_id']) || !isset($_SESSION['teacher_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$school_id = $_SESSION['school_id'];
$teacher_id = $_SESSION['teacher_id'];

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit();
}

// Database connection
require_once '../includes/config.php';
require_once '../includes/SchoolSMSManager.php';

try {
    // Initialize SMS Manager
    $sms_manager = new SchoolSMSManager($school_id);
    
    // Get recipient numbers based on selection
    $phoneNumbers = [];
    
    switch ($input['recipientType']) {
        case 'all-students':
            $stmt = $pdo->prepare("
                SELECT GuardianPhone as phone 
                FROM tblstudents 
                WHERE school_id = ? AND Status = 'Active' 
                AND GuardianPhone IS NOT NULL AND GuardianPhone != ''
                AND GuardianPhone != '0'
            ");
            $stmt->execute([$school_id]);
            $phoneNumbers = $stmt->fetchAll(PDO::FETCH_COLUMN);
            break;
            
        case 'all-parents':
            $stmt = $pdo->prepare("
                SELECT DISTINCT GuardianPhone as phone 
                FROM tblstudents 
                WHERE school_id = ? AND Status = 'Active' 
                AND GuardianPhone IS NOT NULL AND GuardianPhone != ''
                AND GuardianPhone != '0'
            ");
            $stmt->execute([$school_id]);
            $phoneNumbers = $stmt->fetchAll(PDO::FETCH_COLUMN);
            break;
            
        case 'all-teachers':
            $stmt = $pdo->prepare("
                SELECT phonenumber as phone 
                FROM tblteachers 
                WHERE school_id = ? AND status = 'Active' 
                AND is_deleted = 0
                AND phonenumber IS NOT NULL AND phonenumber != ''
            ");
            $stmt->execute([$school_id]);
            $phoneNumbers = $stmt->fetchAll(PDO::FETCH_COLUMN);
            break;
            
        case 'specific-class':
            $class_id = intval($input['classId']);
            if ($class_id) {
                $stmt = $pdo->prepare("
                    SELECT GuardianPhone as phone 
                    FROM tblstudents 
                    WHERE school_id = ? AND class_id = ? AND Status = 'Active'
                    AND GuardianPhone IS NOT NULL AND GuardianPhone != ''
                ");
                $stmt->execute([$school_id, $class_id]);
                $phoneNumbers = $stmt->fetchAll(PDO::FETCH_COLUMN);
            }
            break;
            
        case 'specific-stream':
            $stream_id = intval($input['streamId']);
            if ($stream_id) {
                $stmt = $pdo->prepare("
                    SELECT GuardianPhone as phone 
                    FROM tblstudents 
                    WHERE school_id = ? AND StreamId = ? AND Status = 'Active'
                    AND GuardianPhone IS NOT NULL AND GuardianPhone != ''
                ");
                $stmt->execute([$school_id, $stream_id]);
                $phoneNumbers = $stmt->fetchAll(PDO::FETCH_COLUMN);
            }
            break;
            
        case 'custom-numbers':
            $numbersText = $input['customNumbers'];
            $phoneNumbers = preg_split('/[\n,]+/', $numbersText);
            $phoneNumbers = array_map('trim', $phoneNumbers);
            $phoneNumbers = array_filter($phoneNumbers);
            break;
    }
    
    // Clean and validate phone numbers
    $validNumbers = [];
    foreach ($phoneNumbers as $num) {
        $clean_num = preg_replace('/[^0-9]/', '', $num);
        
        if (strlen($clean_num) == 9 && ($clean_num[0] == '7' || $clean_num[0] == '1')) {
            $validNumbers[] = '+254' . $clean_num;
        } elseif (strlen($clean_num) == 10 && $clean_num[0] == '0') {
            $validNumbers[] = '+254' . substr($clean_num, 1);
        } elseif (strlen($clean_num) == 12 && substr($clean_num, 0, 3) == '254') {
            $validNumbers[] = '+' . $clean_num;
        } elseif (strlen($clean_num) == 13 && $clean_num[0] == '+') {
            $validNumbers[] = $clean_num;
        }
    }
    
    if (empty($validNumbers)) {
        echo json_encode(['success' => false, 'message' => 'No valid phone numbers found']);
        exit();
    }
    
    // Send SMS using the SMS Manager
    $message = $input['message'];
    $segments = ceil(strlen($message) / 160);
    $totalCost = $segments * count($validNumbers);
    
    // Check balance
    $currentBalance = $sms_manager->getSchoolBalance()['balance'] ?? 0;
    if ($totalCost > $currentBalance) {
        echo json_encode([
            'success' => false, 
            'message' => "Insufficient balance. Required: $totalCost credits, Available: $currentBalance credits"
        ]);
        exit();
    }
    
    // Send SMS (bulk)
    $result = $sms_manager->sendBulkSMS($validNumbers, $message);
    
    if ($result['success']) {
        // Log the SMS in your database
        $message_id = 'MSG_' . date('Ymd') . '_' . uniqid();
        
        foreach ($validNumbers as $index => $number) {
            $stmt = $pdo->prepare("
                INSERT INTO sms_logs (
                    school_id, message_id, recipient_phone, message_content, 
                    status, cost, created_at
                ) VALUES (?, ?, ?, ?, 'sent', ?, NOW())
            ");
            $stmt->execute([
                $school_id,
                $message_id,
                $number,
                $message,
                $segments * 0.70 // Cost per SMS
            ]);
        }
        
        // Get updated balance
        $newBalance = $sms_manager->getSchoolBalance()['balance'] ?? ($currentBalance - $totalCost);
        
        echo json_encode([
            'success' => true,
            'message' => "SMS sent successfully to {$result['successful']} recipients",
            'total_recipients' => $result['total'],
            'successful' => $result['successful'],
            'failed' => $result['failed'],
            'total_cost' => $totalCost,
            'new_balance' => $newBalance
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => $result['message'] ?? 'Failed to send SMS'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Error in send_sms.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage()
    ]);
}
?>