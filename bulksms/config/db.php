<?php
// config/db.php
define('DB_HOST', 'sql302.infinityfree.com');
define('DB_USER', 'if0_39232734');
define('DB_PASS', '8dnzKnJrXA3');
define('DB_NAME', 'if0_39232734_srms');

// Create MySQLi connection (for register.php and login.php)
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check MySQLi connection
if ($conn->connect_error) {
    error_log("MySQLi Connection error: " . $conn->connect_error);
    die("Database connection failed. Please try again later.");
}

// Set charset for MySQLi
$conn->set_charset("utf8mb4");

// Create PDO connection (for other parts of your application)
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS,
        [
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8mb4'",
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    error_log("PDO Connection error: " . $e->getMessage());
    // Don't die here, as we might be using MySQLi only
}

/**
 * Sends a JSON response and exits the script.
 *
 * @param mixed $data The data to be sent in the 'data' field of the response.
 * @param string $status The status of the response ('success' or 'error').
 * @param string $message An optional message to include in the response.
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

// --- M-PESA API CREDENTIALS ---
define('MPESA_CONSUMER_KEY', '9GglDu8k5HkLJWycgkrRToeYZQr4ZBkA9ec3lUQLD6iAU0G0');
define('MPESA_CONSUMER_SECRET', '2wfQM0jN0rrtTfEGLIi93KIFLuIn1weaqA7FuHdNrYzZtkHy1msmTGlsWJGt50lQ');
define('MPESA_SHORTCODE', '174379'); // Sandbox shortcode
define('MPESA_PASSKEY', 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919'); // Sandbox passkey
define('MPESA_CALLBACK_URL', 'https://eduscore.ct.ws/api/mpesa_callback.php'); // Update with your domain
define('MPESA_ENVIRONMENT', 'sandbox'); // sandbox or production
?>