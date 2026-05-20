<?php
// config/config.php
session_start();

// Database configuration - Using your InfinityFree credentials
define('DB_HOST', 'sql107.infinityfree.com');
define('DB_USER', 'if0_41566747');
define('DB_PASS', 'Bit06882020');
define('DB_NAME', 'if0_41566747_sms');

// Detect base URL automatically
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$script_name = $_SERVER['SCRIPT_NAME'];
$base_path = str_replace('/user/sms-logs.php', '', $script_name);
$base_path = str_replace('/admin/', '', $base_path);
$base_path = str_replace('/api/', '', $base_path);

// Application configuration
define('APP_NAME', 'EduScore SMS');
define('APP_URL', $protocol . $host . $base_path);
define('APP_TIMEZONE', 'Africa/Nairobi');

// Set timezone
date_default_timezone_set(APP_TIMEZONE);

// SMS Gateway configuration - OpenSMS Kenya
define('SMS_GATEWAY_PROVIDER', 'opensms'); // 'textbelt' or 'opensms'
define('OPENSMS_API_URL', 'https://opensms.kenya.com/api/v1'); // Replace with actual OpenSMS API URL
define('OPENSMS_API_KEY', 'YOUR_OPENSMS_API_KEY'); // Get this after signup
define('OPENSMS_SENDER_ID', 'EduScore'); // Your approved sender ID
define('OPENSMS_PRICE_PER_SMS', 0.70); // KES per SMS

// Keep TextBelt as fallback or for testing
define('SMS_GATEWAY_URL', 'https://textbelt.com/text');
define('SMS_GATEWAY_KEY', 'textbelt');

// API configuration
define('API_RATE_LIMIT', 100);

// --- M-PESA API CREDENTIALS ---
define('MPESA_CONSUMER_KEY', '9GglDu8k5HkLJWycgkrRToeYZQr4ZBkA9ec3lUQLD6iAU0G0');
define('MPESA_CONSUMER_SECRET', '2wfQM0jN0rrtTfEGLIi93KIFLuIn1weaqA7FuHdNrYzZtkHy1msmTGlsWJGt50lQ');
define('MPESA_SHORTCODE', 'YOUR_SHORTCODE');
define('MPESA_PASSKEY', 'YOUR_PASSKEY');
define('MPESA_CALLBACK_URL', APP_URL . '/api/mpesa_callback.php');

// CSRF Protection
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Establish database connection using PDO
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]
    );
} catch (PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
    die("An internal server error occurred. Please try again later.");
}

// Include helper functions
require_once __DIR__ . '/functions.php';

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Sends a JSON response and exits the script.
 */
function sendResponse($data, $status, $message) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data,
        'success' => ($status === 'success')
    ]);
    exit();
}

// Add sanitize function if not already defined
if (!function_exists('sanitize')) {
    function sanitize($data) {
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
}
?>