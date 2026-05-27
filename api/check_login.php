<?php
// api/check_login.php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't expose errors in production

require_once('../includes/config.php');
require_once('../includes/auth.php');

header('Content-Type: application/json');

// Define constants for encryption (should match register_school.php)
define('ENCRYPTION_KEY', 'your-32-char-encryption-key-here!123456');
define('ENCRYPTION_IV', '1234567890123456');

// Helper function for decryption
function decryptPassword($encrypted_password) {
    if (!$encrypted_password) return null;
    $decrypted = openssl_decrypt(
        $encrypted_password, 
        'AES-256-CBC', 
        ENCRYPTION_KEY, 
        0, 
        ENCRYPTION_IV
    );
    return $decrypted ?: null;
}

// Helper function for subscription checking
function checkSubscriptionStatus($user) {
    // Check if subscription exists
    if (!isset($user['subscription_status']) || $user['subscription_status'] === 'no_subscription') {
        return [
            'status' => 'no_subscription',
            'message' => "No active subscription found. Please set up your subscription.",
            'redirect' => "../subscription-module.php?status=no_subscription"
        ];
    }
    
    if ($user['subscription_status'] === 'expired') {
        return [
            'status' => 'expired',
            'message' => "Your subscription has expired. Please renew to continue.",
            'redirect' => "../subscription-module.php?status=expired"
        ];
    }
    
    if ($user['subscription_status'] === 'suspended' || $user['suspended'] == 1) {
        return [
            'status' => 'suspended',
            'message' => "Your subscription has been suspended. Please contact support.",
            'redirect' => "../subscription-module.php?status=suspended"
        ];
    }
    
    // Check expiry date if exists
    if (!empty($user['expires_at']) && $user['expires_at'] < date('Y-m-d H:i:s')) {
        return [
            'status' => 'expired',
            'message' => "Your subscription has expired. Please renew to continue.",
            'redirect' => "../subscription-module.php?status=expired"
        ];
    }
    
    return ['status' => 'active'];
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $identifier = trim($input['email'] ?? '');
    $password   = $input['password'] ?? '';
    $remember   = $input['remember'] ?? false;
    
    if ($identifier === '' || $password === '') {
        throw new Exception("Please enter your email/phone and password.");
    }
    
    // Get user from database
    $stmt = $dbh->prepare("
        SELECT 
            t.id AS teacher_id,
            t.email,
            t.phonenumber,
            t.password,
            t.plain_password,
            t.school_id,
            s.is_activated,
            s.product_type,
            s.school_name,
            sub.id AS subscription_id,
            sub.plan_name,
            sub.is_trial,
            sub.status AS subscription_status,
            sub.expires_at,
            sub.suspended
        FROM tblteachers t
        INNER JOIN tblschoolinfo s ON s.id = t.school_id
        LEFT JOIN subscriptions sub ON s.id = sub.school_id AND sub.status = 'active'
        WHERE t.email = :email OR t.phonenumber = :phone
        LIMIT 1
    ");
    
    $stmt->bindParam(':email', $identifier, PDO::PARAM_STR);
    $stmt->bindParam(':phone', $identifier, PDO::PARAM_STR);
    $stmt->execute();
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception("Invalid email/phone or password.");
    }
    
    // Verify password
    $passwordOk = false;
    
    // Check bcrypt hash
    if (password_verify($password, $user['password'])) {
        $passwordOk = true;
        error_log("Password verified via bcrypt for: " . $identifier);
    }
    // Check plain_password (legacy)
    elseif (!empty($user['plain_password'])) {
        $decryptedPassword = decryptPassword($user['plain_password']);
        if ($decryptedPassword !== null && $decryptedPassword === $password) {
            $passwordOk = true;
            error_log("Password verified via plain_password for: " . $identifier);
            
            // Auto-upgrade to hashed password
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $update = $dbh->prepare("UPDATE tblteachers SET password = :hash WHERE id = :id");
            $update->execute([':hash' => $newHash, ':id' => $user['teacher_id']]);
            error_log("Upgraded to bcrypt hash for user ID: " . $user['teacher_id']);
        }
    }
    
    if (!$passwordOk) {
        error_log("Password verification failed for user: " . $identifier);
        throw new Exception("Invalid email/phone or password.");
    }
    
    // Check school activation
    if ((int)$user['is_activated'] !== 1) {
        echo json_encode([
            "success" => false,
            "locked" => true,
            "message" => "Your school account is pending activation.",
            "redirect" => "../activation-module.php"
        ]);
        exit;
    }
    
    // Check subscription
    $subscription_check = checkSubscriptionStatus($user);
    if ($subscription_check['status'] !== 'active') {
        echo json_encode([
            "success" => false,
            "subscription_issue" => true,
            "message" => $subscription_check['message'],
            "redirect" => $subscription_check['redirect'],
            "status" => $subscription_check['status']
        ]);
        exit;
    }
    
    // ============================================
    // TOKEN-BASED AUTHENTICATION - SUCCESS!
    // ============================================
    $auth = new Auth($dbh);
    
    // Create new token
    $token = $auth->createToken(
        (int)$user['teacher_id'],
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null
    );
    
    // Set secure cookie with remember me option
    $auth->setAuthCookie($token, $remember);
    
    // Clear old session data for backward compatibility
    $_SESSION = [];
    
    error_log("Token login successful for: " . $identifier . " (User ID: " . $user['teacher_id'] . ")");
    
    echo json_encode([
        "success" => true,
        "message" => "Login successful.",
        "redirect" => "../dashboard.php"
    ]);
    exit;
    
} catch (Exception $e) {
    error_log("Login error: " . $e->getMessage());
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
    exit;
}
?>