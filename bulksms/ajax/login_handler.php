<?php
// ajax/login_handler.php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Include database configuration
require_once '../config/config.php';

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

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
    sendJsonResponse('error', 'Invalid request method. Please use POST.');
}

// Get raw POST data (in case of JSON payload)
$input = json_decode(file_get_contents('php://input'), true);
if ($input) {
    $_POST = array_merge($_POST, $input);
}

// Get and sanitize input
$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';
$remember = isset($_POST['remember']) && ($_POST['remember'] === '1' || $_POST['remember'] === true);
$csrf_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

// Debug log
error_log("Login attempt - Username: $username, Method: " . $_SERVER['REQUEST_METHOD']);

// Validate CSRF token
if (!isset($_SESSION['csrf_token']) || $csrf_token !== $_SESSION['csrf_token']) {
    error_log("CSRF token mismatch - Expected: " . $_SESSION['csrf_token'] . ", Received: " . $csrf_token);
    sendJsonResponse('error', 'Invalid security token. Please refresh the page.');
}

// Validate input
if (empty($username) || empty($password)) {
    sendJsonResponse('error', 'Please enter username and password');
}

try {
    // Prepare SQL statement to prevent SQL injection
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        // Check if account is active
        if ($user['status'] != 'active') {
            sendJsonResponse('error', 'Your account is not active. Please contact support.');
        }
        
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['login_time'] = time();
        
        // Regenerate session ID for security
        session_regenerate_id(true);
        
        // Update last login time
        $updateStmt = $pdo->prepare("UPDATE users SET updated_at = NOW() WHERE id = ?");
        $updateStmt->execute([$user['id']]);
        
        // Handle "Remember me" functionality
        if ($remember) {
            $token = bin2hex(random_bytes(32));
            $expires = time() + (86400 * 30);
            
            setcookie(
                'remember_token', 
                $token, 
                [
                    'expires' => $expires,
                    'path' => '/',
                    'secure' => false,
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]
            );
        }
        
        // Log successful login (optional)
        try {
            $tableCheck = $pdo->query("SHOW TABLES LIKE 'api_requests'");
            if ($tableCheck->rowCount() > 0) {
                $logStmt = $pdo->prepare("
                    INSERT INTO api_requests (user_id, endpoint, method, ip_address, user_agent, response_code) 
                    VALUES (?, 'login', 'POST', ?, ?, 200)
                ");
                $logStmt->execute([
                    $user['id'],
                    $_SERVER['REMOTE_ADDR'] ?? '',
                    substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)
                ]);
            }
        } catch (PDOException $logError) {
            error_log("Failed to log login attempt: " . $logError->getMessage());
        }
        
        // Construct redirect path
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'];
        $base_path = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/');
        $redirect_path = $protocol . $host . $base_path . '/user/dashboard.php';
        
        error_log("Login successful for user: " . $user['username'] . " - Redirecting to: " . $redirect_path);
        
        sendJsonResponse('success', 'Login successful! Redirecting...', $redirect_path);
        
    } else {
        error_log("Failed login attempt for username: " . $username . " from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        sendJsonResponse('error', 'Invalid username or password');
    }
    
} catch (PDOException $e) {
    error_log("Login error: " . $e->getMessage());
    
    if (strpos($e->getMessage(), 'connection') !== false) {
        sendJsonResponse('error', 'Database connection error. Please try again later.');
    } else {
        sendJsonResponse('error', 'An error occurred. Please try again later.');
    }
}
?>