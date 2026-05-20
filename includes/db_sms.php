<?php
// config/db_sms.php - Database configuration for SMS System with PayHero Integration

// Database configuration for SMS system
define('DB_HOST', 'sql302.infinityfree.com');
define('DB_USER', 'if0_39232734');
define('DB_PASS', '8dnzKnJrXA3');
define('DB_NAME', 'if0_39232734_sms');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    error_log("SMS Database connection error: " . $e->getMessage());
    die("Database connection failed: " . $e->getMessage());
}

// Also create a global $db variable for consistency
$db = $pdo;

// ============================================
// PAYHERO SMS PURCHASE INTEGRATION
// ============================================

// PayHero API Credentials
if (!defined('PAYHERO_API_USERNAME')) define('PAYHERO_API_USERNAME', '8WCSpTWg94k88j7AH2Ww');
if (!defined('PAYHERO_API_PASSWORD')) define('PAYHERO_API_PASSWORD', 'fMIoN81DKBzamCJvvaXcHd8fVYQYsTlxtASkVNBG');
if (!defined('PAYHERO_BASIC_AUTH_TOKEN'))
define('PAYHERO_BASIC_AUTH_TOKEN', 'OFdDU3BUV2c5NGs4OGo3QUgyV3c6Zk1Jb044MURLQnphbUNKdnZhWGNIZDhmVllRWXNUbHh0QVNrVk5CRw==');



// API Endpoints
if (!defined('PAYHERO_BASE_URL')) define('PAYHERO_BASE_URL', 'https://backend.payhero.co.ke/api/v2');

// Account Configuration
if (!defined('PAYHERO_ACCOUNT_ID')) define('PAYHERO_ACCOUNT_ID', '7950');
if (!defined('PAYHERO_CHANNEL_ID')) define('PAYHERO_CHANNEL_ID', 7550);
if (!defined('PAYHERO_TILL_NUMBER')) define('PAYHERO_TILL_NUMBER', '6876258');

// Callback URLs - UPDATE YOUR DOMAIN HERE
$callback_domain = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'eduscore.gt.tc';
$callback_protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';

if (!defined('PAYHERO_CALLBACK_SECRET')) define('PAYHERO_CALLBACK_SECRET', 'eduscore_secure_2024');
// SMS Purchase Callback (separate from main billing)
if (!defined('PAYHERO_CALLBACK_URL')) {
    define(
        'PAYHERO_CALLBACK_URL',
        'https://eduscore.co.ke/includes/mpesa_callback_sms.php?token=' . PAYHERO_CALLBACK_SECRET
    );
}
if (!defined('PAYHERO_TIMEOUT_URL')) define('PAYHERO_TIMEOUT_URL', $callback_protocol . '://' . $callback_domain . '/admin/mpesa_timeout.php');

// Environment (production or sandbox)
if (!defined('PAYHERO_ENVIRONMENT')) define('PAYHERO_ENVIRONMENT', 'production');

// For backward compatibility with older code
if (!defined('PAYHERO_API_KEY')) define('PAYHERO_API_KEY', PAYHERO_API_USERNAME);
if (!defined('PAYHERO_API_SECRET')) define('PAYHERO_API_SECRET', PAYHERO_API_PASSWORD);
if (!defined('PAYHERO_PAYBILL_NUMBER')) define('PAYHERO_PAYBILL_NUMBER', '');

// ============================================
// SMS PRICING CONFIGURATION
// ============================================

// SMS price per unit (KES)
define('SMS_PRICE_PER_UNIT', 0.85);

// Minimum SMS purchase for custom amount
define('MIN_SMS_PURCHASE', 10);

// SMS Packages with volume discounts (SMS => Price in KES)
$sms_packages = [
    10 => 8.50,      // 10 SMS @ KES 0.85 each
    50 => 42.50,     // 50 SMS @ KES 0.85 each
    100 => 85.00,    // 100 SMS @ KES 0.85 each
    500 => 425.00,   // 500 SMS @ KES 0.85 each
    1000 => 850.00,  // 1000 SMS @ KES 0.85 each
    5000 => 4250.00, // 5000 SMS @ KES 0.85 each
];

// ============================================
// SMS GATEWAY CONFIGURATION (OpenSMS)
// ============================================

// OpenSMS API Configuration (if using external gateway)
if (!defined('OPENSMS_API_URL')) define('OPENSMS_API_URL', 'https://api.opensms.co.ke/v1/send');
if (!defined('OPENSMS_API_KEY')) define('OPENSMS_API_KEY', 'your_opensms_api_key');
if (!defined('OPENSMS_SENDER_ID')) define('OPENSMS_SENDER_ID', 'EDUSCORE');

// ============================================
// HELPER FUNCTIONS
// ============================================

/**
 * Get SMS package pricing
 * @param int $sms_count Number of SMS
 * @return array ['quantity' => int, 'price' => float, 'price_per_sms' => float]
 */
function getSmsPackagePrice($sms_count) {
    global $sms_packages;
    
    // Check if exact package exists
    if (isset($sms_packages[$sms_count])) {
        return [
            'quantity' => $sms_count,
            'price' => $sms_packages[$sms_count],
            'price_per_sms' => $sms_packages[$sms_count] / $sms_count
        ];
    }
    
    // Custom package (minimum MIN_SMS_PURCHASE)
    if ($sms_count >= MIN_SMS_PURCHASE) {
        return [
            'quantity' => $sms_count,
            'price' => $sms_count * SMS_PRICE_PER_UNIT,
            'price_per_sms' => SMS_PRICE_PER_UNIT
        ];
    }
    
    return null;
}

/**
 * Format phone number for M-Pesa
 * @param string $phone Phone number
 * @return string Formatted phone number (2547XXXXXXXX)
 */
function formatMpesaPhone($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Remove leading 0 or +254
    if (substr($phone, 0, 1) === '0') {
        $phone = '254' . substr($phone, 1);
    } elseif (substr($phone, 0, 3) === '254') {
        // Already in correct format
    } elseif (substr($phone, 0, 4) === '+254') {
        $phone = substr($phone, 1);
    } else {
        $phone = '254' . $phone;
    }
    
    return $phone;
}

/**
 * Validate Safaricom phone number
 * @param string $phone Phone number
 * @return bool True if valid Safaricom number
 */
function isValidSafaricomNumber($phone) {
    $phone = formatMpesaPhone($phone);
    return preg_match('/^2547[0-9]{8}$/', $phone);
}

/**
 * Generate unique transaction reference
 * @param string $prefix Prefix for reference
 * @return string Unique reference
 */
function generateTransactionRef($prefix = 'SMS') {
    return $prefix . '-' . date('YmdHis') . '-' . rand(1000, 9999);
}

/**
 * Update admin SMS balance after successful purchase
 * @param PDO $pdo Database connection
 * @param int $admin_id Admin ID
 * @param int $quantity SMS quantity to add
 * @return bool Success status
 */
function updateAdminSmsBalance($pdo, $admin_id, $quantity) {
    try {
        // Check if sms_balance column exists in admins table
        $stmt = $pdo->query("SHOW COLUMNS FROM admins LIKE 'sms_balance'");
        if ($stmt->rowCount() == 0) {
            // Add sms_balance column if it doesn't exist
            $pdo->exec("ALTER TABLE admins ADD COLUMN sms_balance INT DEFAULT 0 AFTER role");
        }
        
        // Update admin's SMS balance
        $stmt = $pdo->prepare("UPDATE admins SET sms_balance = sms_balance + ? WHERE id = ?");
        return $stmt->execute([$quantity, $admin_id]);
    } catch (Exception $e) {
        error_log("Error updating admin SMS balance: " . $e->getMessage());
        return false;
    }
}

/**
 * Log SMS purchase
 * @param PDO $pdo Database connection
 * @param array $data Purchase data
 * @return int|false Insert ID or false on failure
 */
function logSmsPurchase($pdo, $data) {
    try {
        // Check if sms_purchases table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'sms_purchases'");
        if ($stmt->rowCount() == 0) {
            // Create sms_purchases table
            $sql = "CREATE TABLE IF NOT EXISTS `sms_purchases` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `admin_id` int(11) NOT NULL,
                `transaction_id` varchar(50) NOT NULL,
                `quantity` int(11) NOT NULL,
                `price_per_sms` decimal(10,2) NOT NULL,
                `total_cost` decimal(10,2) NOT NULL,
                `payment_method` enum('mpesa','bank_transfer','card') DEFAULT 'mpesa',
                `phone_number` varchar(20) DEFAULT NULL,
                `checkout_request_id` varchar(100) DEFAULT NULL,
                `mpesa_receipt` varchar(50) DEFAULT NULL,
                `status` enum('pending','completed','failed','cancelled') DEFAULT 'pending',
                `notes` text,
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `transaction_id` (`transaction_id`),
                KEY `admin_id` (`admin_id`),
                KEY `status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            $pdo->exec($sql);
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO sms_purchases 
            (admin_id, transaction_id, quantity, price_per_sms, total_cost, payment_method, phone_number, notes, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        
        $stmt->execute([
            $data['admin_id'],
            $data['transaction_id'],
            $data['quantity'],
            $data['price_per_sms'],
            $data['total_cost'],
            $data['payment_method'],
            $data['phone_number'] ?? null,
            $data['notes'] ?? null
        ]);
        
        return $pdo->lastInsertId();
    } catch (Exception $e) {
        error_log("Error logging SMS purchase: " . $e->getMessage());
        return false;
    }
}

// ============================================
// INITIALIZE DATABASE TABLES IF NOT EXISTS
// ============================================

// Ensure required tables exist
try {
    // Check if sms_balance column exists in admins table
    $stmt = $pdo->query("SHOW COLUMNS FROM admins LIKE 'sms_balance'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE admins ADD COLUMN sms_balance INT DEFAULT 0 AFTER role");
    }
    
    // Ensure sms_purchases table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'sms_purchases'");
    if ($stmt->rowCount() == 0) {
        $sql = "CREATE TABLE IF NOT EXISTS `sms_purchases` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `admin_id` int(11) NOT NULL,
            `transaction_id` varchar(50) NOT NULL,
            `quantity` int(11) NOT NULL,
            `price_per_sms` decimal(10,2) NOT NULL,
            `total_cost` decimal(10,2) NOT NULL,
            `payment_method` enum('mpesa','bank_transfer','card') DEFAULT 'mpesa',
            `phone_number` varchar(20) DEFAULT NULL,
            `checkout_request_id` varchar(100) DEFAULT NULL,
            `mpesa_receipt` varchar(50) DEFAULT NULL,
            `status` enum('pending','completed','failed','cancelled') DEFAULT 'pending',
            `notes` text,
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
            `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `transaction_id` (`transaction_id`),
            KEY `admin_id` (`admin_id`),
            KEY `status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $pdo->exec($sql);
    }
    
    // Add sms_balance to admins if not exists
    $stmt = $pdo->query("SHOW COLUMNS FROM admins LIKE 'sms_balance'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE admins ADD COLUMN sms_balance INT DEFAULT 0 AFTER role");
    }
} catch (Exception $e) {
    error_log("Database initialization error: " . $e->getMessage());
}
?>