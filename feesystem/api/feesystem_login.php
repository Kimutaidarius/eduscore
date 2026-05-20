<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Fix the path - go up two levels to reach the root, then into includes
require_once('../../includes/config.php');
header('Content-Type: application/json');

// Define constants for encryption (should match register_school.php)
define('ENCRYPTION_KEY', 'your-32-char-encryption-key-here!123456');
define('ENCRYPTION_IV', '1234567890123456');

try {
    $input = json_decode(file_get_contents('php://input'), true);

    $identifier = trim($input['username'] ?? '');
    $password   = $input['password'] ?? '';

    if ($identifier === '' || $password === '') {
        throw new Exception("Please enter your email/phone and password.");
    }

    // Fetch user from tblteachers with school information
    // Use positional placeholders instead of named to avoid parameter issues
    $stmt = $dbh->prepare("
        SELECT 
            t.id AS teacher_id,
            t.email,
            t.phonenumber,
            t.password,
            t.plain_password,
            t.school_id,
            t.role AS user_role,
            t.status AS teacher_status,
            s.is_activated,
            s.product_type,
            s.school_name,
            sub.id AS subscription_id,
            sub.plan_name,
            sub.is_trial,
            sub.status AS subscription_status,
            sub.expires_at,
            sub.suspended,
            DATEDIFF(sub.expires_at, NOW()) AS days_remaining,
            CASE 
                WHEN sub.expires_at < NOW() THEN 'expired'
                WHEN sub.suspended = 1 THEN 'suspended'
                WHEN sub.expires_at IS NULL THEN 'no_subscription'
                ELSE 'active'
            END AS current_subscription_status
        FROM tblteachers t
        INNER JOIN tblschoolinfo s ON s.id = t.school_id
        LEFT JOIN subscriptions sub ON s.id = sub.school_id AND sub.status = 'active'
        WHERE (t.email = ? OR t.phonenumber = ?)
          AND t.is_deleted = 0
        LIMIT 1
    ");

    $stmt->execute([$identifier, $identifier]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception("Invalid email/phone or password.");
    }

    // Check if teacher is active
    if ($user['teacher_status'] !== 'Active') {
        throw new Exception("Your account is not active. Please contact your school administrator.");
    }

    // Check if user has finance system access
    // Finance users should have role = 'Super Admin' or product_type includes 'Fee Management System'
    $hasFinanceAccess = false;
    
    // Check role - Super Admin or Admin should have access
    if (isset($user['user_role'])) {
        $hasFinanceAccess = ($user['user_role'] === 'Super Admin' || $user['user_role'] === 'admin');
    }
    
    // Also check product_type for Fee Management System
    if (!$hasFinanceAccess && isset($user['product_type'])) {
        $productTypes = explode(',', $user['product_type']);
        $hasFinanceAccess = in_array('Fee Management System', $productTypes);
    }

    if (!$hasFinanceAccess) {
        error_log("Finance access denied for user: " . $identifier . " - Role: " . ($user['user_role'] ?? 'none') . " - Product: " . ($user['product_type'] ?? 'none'));
        throw new Exception("You do not have permission to access the Finance Management System. Please contact your school administrator.");
    }

    $dbPassword = $user['password'];
    $dbPlainPassword = $user['plain_password'];
    $passwordOk = false;

    // DECRYPTION FUNCTION for plain_password
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

    // 1️⃣ Check hashed password (bcrypt)
    if (password_verify($password, $dbPassword)) {
        $passwordOk = true;
        error_log("Password verified via bcrypt for finance user: " . $identifier);
    }
    // 2️⃣ Check decrypted plain_password
    elseif (!empty($dbPlainPassword)) {
        $decryptedPassword = decryptPassword($dbPlainPassword);
        
        if ($decryptedPassword !== null && $decryptedPassword === $password) {
            $passwordOk = true;
            error_log("Password verified via plain_password for finance user: " . $identifier);
            
            // Auto-upgrade to hashed password
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $update = $dbh->prepare("
                UPDATE tblteachers 
                SET password = :hash 
                WHERE id = :id
            ");
            $update->execute([
                ':hash' => $newHash,
                ':id'   => $user['teacher_id']
            ]);
            error_log("Upgraded to bcrypt hash for finance user ID: " . $user['teacher_id']);
        }
    }
    // 3️⃣ Fallback: plain-text password (legacy accounts)
    elseif (hash_equals($dbPassword, $password) || md5($password) === $dbPassword) {
        $passwordOk = true;
        error_log("Password verified via legacy method for finance user: " . $identifier);

        // Auto-upgrade to hashed password
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $update = $dbh->prepare("
            UPDATE tblteachers 
            SET password = :hash 
            WHERE id = :id
        ");
        $update->execute([
            ':hash' => $newHash,
            ':id'   => $user['teacher_id']
        ]);
        error_log("Upgraded to bcrypt hash for finance user ID: " . $user['teacher_id']);
    }

    if (!$passwordOk) {
        error_log("Password verification failed for finance user: " . $identifier);
        throw new Exception("Invalid email/phone or password.");
    }

    // 🔒 CHECK SCHOOL ACTIVATION
    if ((int)$user['is_activated'] !== 1) {
        session_regenerate_id(true);
        $_SESSION = [
            'activation_only' => true,
            'school_id'  => (int)$user['school_id'],
            'teacher_id' => (int)$user['teacher_id'],
            'locked'     => true,
            'lock_time'  => time(),
            'user_type'  => 'finance'
        ];

        echo json_encode([
            "success"  => false,
            "locked"   => true,
            "message"  => "Your school account is pending activation.",
            "redirect" => "../../activation-module.php"
        ]);
        exit;
    }

    // 🔒 CHECK SUBSCRIPTION STATUS
    $subscription_status = $user['current_subscription_status'] ?? 'no_subscription';
    $needs_subscription_redirect = false;
    $subscription_message = '';
    $subscription_redirect = '';

    // Check if product includes Fee Management System
    $productTypes = explode(',', $user['product_type'] ?? '');
    $hasFeeManagement = in_array('Fee Management System', $productTypes);
    
    // Only check subscription if they have fee management product
    if ($hasFeeManagement) {
        if ($subscription_status === 'expired') {
            $needs_subscription_redirect = true;
            $subscription_message = "Your subscription has expired. Please renew to continue using the Finance Management System.";
            $subscription_redirect = "../../subscription-module.php?status=expired&product=fee";
        } elseif ($subscription_status === 'suspended') {
            $needs_subscription_redirect = true;
            $subscription_message = "Your subscription has been suspended. Please contact support.";
            $subscription_redirect = "../../subscription-module.php?status=suspended&product=fee";
        } elseif ($subscription_status === 'no_subscription') {
            $needs_subscription_redirect = true;
            $subscription_message = "No active subscription found for Finance Management System. Please set up your subscription.";
            $subscription_redirect = "../../subscription-module.php?status=no_subscription&product=fee";
        } elseif ($user['days_remaining'] !== null && $user['days_remaining'] <= 0) {
            $needs_subscription_redirect = true;
            $subscription_message = "Your subscription has expired. Please renew to continue.";
            $subscription_redirect = "../../subscription-module.php?status=expired&product=fee";
        }
    }

    if ($needs_subscription_redirect) {
        // Store subscription info in session for the subscription module
        session_regenerate_id(true);
        $_SESSION = [
            'subscription_redirect' => true,
            'subscription_redirect_time' => time(),
            'school_id'             => (int)$user['school_id'],
            'teacher_id'            => (int)$user['teacher_id'],
            'subscription_status'   => $subscription_status,
            'subscription_id'       => $user['subscription_id'],
            'expires_at'            => $user['expires_at'],
            'days_remaining'        => $user['days_remaining'] ?? 0,
            'plan_name'             => $user['plan_name'] ?? 'No Plan',
            'is_trial'              => $user['is_trial'] ?? 0,
            'user_type'             => 'finance',
            'product'               => 'fee'
        ];

        echo json_encode([
            "success"  => false,
            "subscription_issue" => true,
            "message"  => $subscription_message,
            "redirect" => $subscription_redirect,
            "status"   => $subscription_status
        ]);
        exit;
    }

    // ✅ Full authenticated session - all checks passed
    session_regenerate_id(true);

    $_SESSION = [
        'authenticated' => true,
        'is_logged_in'  => true,
        'user_id'       => (int)$user['teacher_id'],
        'teacher_id'    => (int)$user['teacher_id'],
        'school_id'     => (int)$user['school_id'],
        'email'         => $user['email'],
        'phone'         => $user['phonenumber'],
        'school_name'   => $user['school_name'] ?? '',
        'subscription_id' => $user['subscription_id'],
        'subscription_status' => $user['subscription_status'] ?? 'active',
        'plan_name'     => $user['plan_name'] ?? 'Free Trial',
        'expires_at'    => $user['expires_at'],
        'login_time'    => time(),
        'user_type'     => 'finance',
        'product_type'  => 'Fee Management System',
        'role'          => $user['user_role'] ?? 'User'
    ];

    error_log("Finance login successful for: " . $identifier . " (School: " . $user['school_name'] . ", Role: " . ($user['user_role'] ?? 'none') . ")");

    echo json_encode([
        "success"  => true,
        "message"  => "Login successful. Welcome to Finance Management System.",
        "redirect" => "dashboard.php"
    ]);
    exit;

} catch (Exception $e) {
    error_log("Finance login error: " . $e->getMessage());
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
    exit;
}
?>