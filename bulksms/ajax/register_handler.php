<?php
// ajax/register_handler.php
session_start();
header('Content-Type: application/json');

// Include database configuration
require_once '../config/config.php';

// Function to send JSON response
function sendJsonResponse($status, $message, $redirect = '') {
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'redirect' => $redirect
    ]);
    exit();
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse('error', 'Invalid request method');
}

// Get and sanitize input
$username = isset($_POST['username']) ? sanitize($_POST['username']) : '';
$email = isset($_POST['email']) ? sanitize($_POST['email']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';
$confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
$full_name = isset($_POST['full_name']) ? sanitize($_POST['full_name']) : '';
$company_name = isset($_POST['company_name']) ? sanitize($_POST['company_name']) : '';
$phone = isset($_POST['phone']) ? sanitize($_POST['phone']) : '';
$terms = isset($_POST['terms']) && $_POST['terms'] === '1';
$csrf_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

// Validate CSRF token
if (!isset($_SESSION['csrf_token']) || $csrf_token !== $_SESSION['csrf_token']) {
    sendJsonResponse('error', 'Invalid security token');
}

// Validation
if (empty($username) || empty($email) || empty($password) || empty($full_name)) {
    sendJsonResponse('error', 'Please fill in all required fields');
}

if ($password !== $confirm_password) {
    sendJsonResponse('error', 'Passwords do not match');
}

if (strlen($password) < 8) {
    sendJsonResponse('error', 'Password must be at least 8 characters');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendJsonResponse('error', 'Invalid email format');
}

if (!$terms) {
    sendJsonResponse('error', 'You must accept the Terms of Service');
}

// Validate username format
if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
    sendJsonResponse('error', 'Username must be 3-20 characters and can only contain letters, numbers, and underscores');
}

// Validate phone number if provided (optional)
if (!empty($phone)) {
    $phone = validatePhone($phone);
    if (!$phone) {
        sendJsonResponse('error', 'Invalid phone number format. Use international format (e.g., 254712345678)');
    }
}

try {
    // Check if username or email exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    
    if ($stmt->fetch()) {
        sendJsonResponse('error', 'Username or email already exists');
    }
    
    // Begin transaction
    $pdo->beginTransaction();
    
    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert user with 0 SMS balance
    $stmt = $pdo->prepare("
        INSERT INTO users (
            username, 
            email, 
            password, 
            full_name, 
            company_name, 
            phone, 
            sms_balance, 
            sms_balance_kes,
            status,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, 0, 0.00, 'active', NOW())
    ");
    
    if (!$stmt->execute([$username, $email, $hashed_password, $full_name, $company_name, $phone])) {
        $pdo->rollBack();
        sendJsonResponse('error', 'Registration failed. Please try again.');
    }
    
    $user_id = $pdo->lastInsertId();
    
    // Generate API key for user
    $api_key = generateApiKey();
    $api_secret = generateApiSecret();
    
    $stmt = $pdo->prepare("
        INSERT INTO api_keys (user_id, api_key, api_secret, name, status, created_at) 
        VALUES (?, ?, ?, 'Default API Key', 'active', NOW())
    ");
    
    if (!$stmt->execute([$user_id, $api_key, $api_secret])) {
        $pdo->rollBack();
        sendJsonResponse('error', 'Failed to generate API key. Please try again.');
    }
    
    // Create default sender ID (EDUSCORE) for the user
    $stmt = $pdo->prepare("
        INSERT INTO sender_ids (user_id, sender_id, status, is_default, created_at) 
        VALUES (?, 'EDUSCORE', 'approved', 1, NOW())
    ");
    
    if (!$stmt->execute([$user_id])) {
        // Non-critical, just log error
        error_log("Failed to create default sender ID for user {$user_id}");
    }
    
    // Log registration (optional)
    try {
        $logStmt = $pdo->prepare("
            INSERT INTO api_requests (
                user_id, 
                endpoint, 
                method, 
                ip_address, 
                user_agent, 
                response_code,
                created_at
            ) VALUES (?, 'register', 'POST', ?, ?, 200, NOW())
        ");
        $logStmt->execute([
            $user_id,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    } catch (PDOException $logError) {
        // Just log the error but don't fail the registration
        error_log("Failed to log registration: " . $logError->getMessage());
    }
    
    // Commit transaction
    $pdo->commit();
    
    // Send success response
    sendJsonResponse('success', 'Registration successful! You can now login.', 'login.php');
    
} catch (PDOException $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Log error but don't expose details to user
    error_log("Registration error: " . $e->getMessage());
    
    // Check for specific error conditions
    if (strpos($e->getMessage(), 'connection') !== false) {
        sendJsonResponse('error', 'Database connection error. Please try again later.');
    } elseif (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        sendJsonResponse('error', 'Username or email already exists');
    } else {
        sendJsonResponse('error', 'An error occurred. Please try again later.');
    }
}
?>