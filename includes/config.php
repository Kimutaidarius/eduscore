<?php
// Prevent multiple inclusions
if (defined('CONFIG_LOADED')) {
    return;
}
define('CONFIG_LOADED', true);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- DATABASE CLASS ---
if (!class_exists('Database')) {
    class Database {
        private static $instance = null;
        private $pdo;

        private function __construct() {
            try {

                // XAMPP LOCALHOST DATABASE
                $dsn = "mysql:host=localhost;dbname=srms;charset=utf8mb4";
                $username = "root";
                $password = "";

                $this->pdo = new PDO(
                    $dsn,
                    $username,
                    $password,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'"
                    ]
                );

            } catch (PDOException $e) {
                error_log("Database connection error: " . $e->getMessage());
                die("Database connection failed: " . $e->getMessage());
            }
        }

        public static function getInstance() {
            if (self::$instance === null) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        public function getConnection() {
            return $this->pdo;
        }
    }
}

// Create database connection
if (!isset($db)) {
    $database = Database::getInstance();
    $db = $database->getConnection();
    $dbh = $db;
}

// --- DATABASE CONSTANTS ---
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('DB_NAME')) define('DB_NAME', 'srms');

// ============================================
// PAYHERO CONFIGURATION
// ============================================

// API Credentials
if (!defined('PAYHERO_API_USERNAME')) define('PAYHERO_API_USERNAME', 'YOUR_API_USERNAME');
if (!defined('PAYHERO_API_PASSWORD')) define('PAYHERO_API_PASSWORD', 'YOUR_API_PASSWORD');
if (!defined('PAYHERO_BASIC_AUTH_TOKEN')) define('PAYHERO_BASIC_AUTH_TOKEN', 'YOUR_BASIC_AUTH_TOKEN');

// API Endpoints
if (!defined('PAYHERO_BASE_URL')) define('PAYHERO_BASE_URL', 'https://backend.payhero.co.ke/api/v2');

// Account Configuration
if (!defined('PAYHERO_ACCOUNT_ID')) define('PAYHERO_ACCOUNT_ID', '7950');
if (!defined('PAYHERO_CHANNEL_ID')) define('PAYHERO_CHANNEL_ID', 7550);
if (!defined('PAYHERO_TILL_NUMBER')) define('PAYHERO_TILL_NUMBER', '6876258');

// CALLBACK URLs FOR LOCALHOST
if (!defined('PAYHERO_CALLBACK_SECRET')) define('PAYHERO_CALLBACK_SECRET', 'eduscore_secure_2026');

if (!defined('PAYHERO_CALLBACK_URL')) {
    define(
        'PAYHERO_CALLBACK_URL',
        'http://localhost/eduscore/includes/mpesa_callback.php?token=' . PAYHERO_CALLBACK_SECRET
    );
}

if (!defined('PAYHERO_TIMEOUT_URL')) {
    define(
        'PAYHERO_TIMEOUT_URL',
        'http://localhost/eduscore/includes/mpesa_timeout.php'
    );
}

// Environment
if (!defined('PAYHERO_ENVIRONMENT')) define('PAYHERO_ENVIRONMENT', 'sandbox');

// Legacy Compatibility
if (!defined('PAYHERO_API_KEY')) define('PAYHERO_API_KEY', PAYHERO_API_USERNAME);
if (!defined('PAYHERO_API_SECRET')) define('PAYHERO_API_SECRET', PAYHERO_API_PASSWORD);
if (!defined('PAYHERO_PAYBILL_NUMBER')) define('PAYHERO_PAYBILL_NUMBER', '');

// --- MPESA LEGACY ---
if (!defined('MPESA_CONSUMER_KEY')) define('MPESA_CONSUMER_KEY', '');
if (!defined('MPESA_CONSUMER_SECRET')) define('MPESA_CONSUMER_SECRET', '');
if (!defined('MPESA_SHORTCODE')) define('MPESA_SHORTCODE', '');
if (!defined('MPESA_PASSKEY')) define('MPESA_PASSKEY', '');

if (!defined('MPESA_CALLBACK_URL')) {
    define(
        'MPESA_CALLBACK_URL',
        'http://localhost/eduscore/api/mpesa_callback.php'
    );
}

// --- SYSTEM CONFIGURATION ---
if (!defined('SITE_NAME')) define('SITE_NAME', 'EduScore');

if (!defined('SITE_URL')) {
    define('SITE_URL', 'http://localhost/eduscore');
}

if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://localhost/eduscore');
}

if (!defined('TIMEZONE')) define('TIMEZONE', 'Africa/Nairobi');

date_default_timezone_set(TIMEZONE);

// --- BILLING CONFIGURATION ---
if (!defined('CURRENCY')) define('CURRENCY', 'KES');
if (!defined('CURRENCY_SYMBOL')) define('CURRENCY_SYMBOL', 'KES');
if (!defined('TRIAL_DAYS')) define('TRIAL_DAYS', 14);

// --- DIRECTORY PATHS ---
if (!defined('BILLING_DIR')) define('BILLING_DIR', __DIR__ . '/../billing/');
if (!defined('INVOICE_DIR')) define('INVOICE_DIR', __DIR__ . '/../billing/invoices/');
if (!defined('RECEIPT_DIR')) define('RECEIPT_DIR', __DIR__ . '/../billing/receipts/');

// Create directories
if (!file_exists(BILLING_DIR)) mkdir(BILLING_DIR, 0777, true);
if (!file_exists(INVOICE_DIR)) mkdir(INVOICE_DIR, 0777, true);
if (!file_exists(RECEIPT_DIR)) mkdir(RECEIPT_DIR, 0777, true);

// --- LOGGING ---
if (!defined('LOG_DIR')) define('LOG_DIR', __DIR__ . '/../logs/');
if (!defined('LOG_LEVEL')) define('LOG_LEVEL', 'debug');

if (!file_exists(LOG_DIR)) mkdir(LOG_DIR, 0777, true);

// --- EMAIL ---
if (!defined('SMTP_HOST')) define('SMTP_HOST', '');
if (!defined('SMTP_PORT')) define('SMTP_PORT', 587);
if (!defined('SMTP_USER')) define('SMTP_USER', '');
if (!defined('SMTP_PASS')) define('SMTP_PASS', '');
if (!defined('SMTP_FROM_EMAIL')) define('SMTP_FROM_EMAIL', 'billing@localhost');
if (!defined('SMTP_FROM_NAME')) define('SMTP_FROM_NAME', 'EduScore Billing');

// --- LOG FUNCTION ---
if (!function_exists('logTransaction')) {

    function logTransaction($message, $type = 'info', $data = null) {

        $log_file = LOG_DIR . 'payments_' . date('Y-m-d') . '.log';

        $timestamp = date('Y-m-d H:i:s');

        $log_entry = "[$timestamp] [$type] $message";

        if ($data) {
            $log_entry .= "\nData: " . json_encode($data, JSON_PRETTY_PRINT);
        }

        $log_entry .= "\n" . str_repeat('-', 80) . "\n";

        file_put_contents($log_file, $log_entry, FILE_APPEND);
    }
}

// --- RESPONSE FUNCTION ---
if (!function_exists('sendResponse')) {

    function sendResponse($data = null, $status = 'success', $message = '') {

        header('Content-Type: application/json');

        echo json_encode([
            'status' => $status,
            'message' => $message,
            'data' => $data,
            'success' => ($status === 'success')
        ]);

        exit();
    }
}

// --- DB CONNECTION FUNCTION ---
if (!function_exists('getDbConnection')) {

    function getDbConnection() {

        global $dbh;

        return $dbh;
    }
}

// --- CONFIG LOADED LOG ---
logTransaction("Localhost config loaded", "info", [
    'database' => DB_NAME,
    'environment' => PAYHERO_ENVIRONMENT,
    'base_url' => BASE_URL
]);

?>