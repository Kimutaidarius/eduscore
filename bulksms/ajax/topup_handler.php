<?php
// ajax/topup_handler.php
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

// Define sanitize function if not already defined
if (!function_exists('sanitize')) {
    function sanitize($data) {
        if ($data === null || $data === '') {
            return '';
        }
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
}

// Define validatePhone function if not already defined
if (!function_exists('validatePhone')) {
    function validatePhone($phone) {
        // Remove any non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Check if it's a valid phone number (basic check)
        if (strlen($phone) >= 10 && strlen($phone) <= 15) {
            return $phone;
        }
        return false;
    }
}

$action = isset($_POST['action']) ? $_POST['action'] : '';

try {
    switch ($action) {
        case 'get_packages':
            // Get available SMS packages
            $stmt = $pdo->prepare("SELECT * FROM sms_packages WHERE is_active = 1 ORDER BY price ASC");
            $stmt->execute();
            $packages = $stmt->fetchAll();
            
            sendJsonResponse('success', 'Packages retrieved', ['packages' => $packages]);
            break;
            
        case 'initiate_payment':
            $phone = isset($_POST['phone']) ? sanitize($_POST['phone']) : '';
            $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
            $package_id = isset($_POST['package_id']) ? (int)$_POST['package_id'] : 0;
            
            if (empty($phone)) {
                sendJsonResponse('error', 'Phone number is required');
            }
            
            // Validate phone
            $phone = validatePhone($phone);
            if (!$phone) {
                sendJsonResponse('error', 'Invalid phone number format. Use 254712345678');
            }
            
            // Ensure phone starts with 254
            if (substr($phone, 0, 3) !== '254') {
                $phone = '254' . ltrim($phone, '0');
            }
            
            if ($amount < 10) {
                sendJsonResponse('error', 'Minimum amount is KES 10');
            }
            
            // Get package details if selected
            $package_name = 'Custom Amount';
            $credits = $amount; // 1:1 conversion for custom amount
            
            if ($package_id > 0) {
                $stmt = $pdo->prepare("SELECT * FROM sms_packages WHERE id = ? AND is_active = 1");
                $stmt->execute([$package_id]);
                $package = $stmt->fetch();
                
                if ($package) {
                    $package_name = $package['package_name'];
                    $credits = $package['credits'] + ($package['bonus_credits'] ?? 0);
                }
            }
            
            // Generate unique transaction reference
            $reference = 'TXN' . time() . rand(1000, 9999);
            
            // Save transaction to database
            $stmt = $pdo->prepare("
                INSERT INTO sms_transactions 
                (user_id, package_id, phone, amount, credits, reference, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([$user_id, $package_id, $phone, $amount, $credits, $reference]);
            $transaction_id = $pdo->lastInsertId();
            
            // Here you would integrate with actual M-Pesa API
            // For demo purposes, we'll simulate a successful payment
            
            // Simulate M-Pesa STK Push
            $mpesa_response = initiateMpesaStkPush($phone, $amount, $reference, $package_name);
            
            if ($mpesa_response && isset($mpesa_response['ResponseCode']) && $mpesa_response['ResponseCode'] == '0') {
                // Update transaction with checkout request ID
                $stmt = $pdo->prepare("
                    UPDATE sms_transactions 
                    SET checkout_request_id = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$mpesa_response['CheckoutRequestID'], $transaction_id]);
                
                sendJsonResponse('success', 'Please check your phone to complete the payment', [
                    'transaction_id' => $transaction_id,
                    'checkout_request_id' => $mpesa_response['CheckoutRequestID'],
                    'reference' => $reference
                ]);
            } else {
                // For demo without actual M-Pesa, simulate success
                sendJsonResponse('success', 'Payment initiated successfully (Demo Mode)', [
                    'transaction_id' => $transaction_id,
                    'reference' => $reference,
                    'demo' => true
                ]);
            }
            break;
            
        case 'check_payment_status':
            $transaction_id = isset($_POST['transaction_id']) ? (int)$_POST['transaction_id'] : 0;
            
            if (!$transaction_id) {
                sendJsonResponse('error', 'Transaction ID is required');
            }
            
            // Check transaction status
            $stmt = $pdo->prepare("SELECT * FROM sms_transactions WHERE id = ? AND user_id = ?");
            $stmt->execute([$transaction_id, $user_id]);
            $transaction = $stmt->fetch();
            
            if (!$transaction) {
                sendJsonResponse('error', 'Transaction not found');
            }
            
            // For demo, we'll simulate successful payment after 5 seconds
            // In production, you'd check with M-Pesa API
            
            sendJsonResponse('success', 'Transaction status retrieved', [
                'status' => $transaction['status'],
                'transaction' => $transaction
            ]);
            break;
            
        default:
            sendJsonResponse('error', 'Invalid action');
    }
} catch (PDOException $e) {
    error_log("Topup handler error: " . $e->getMessage());
    sendJsonResponse('error', 'Database error occurred');
} catch (Exception $e) {
    error_log("Topup handler error: " . $e->getMessage());
    sendJsonResponse('error', $e->getMessage());
}
?>