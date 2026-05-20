<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once('../includes/config.php');
header('Content-Type: application/json');

// Define constants for encryption (should match register_school.php)
define('ENCRYPTION_KEY', 'your-32-char-encryption-key-here!123456');
define('ENCRYPTION_IV', '1234567890123456');

try {
    $input = json_decode(file_get_contents('php://input'), true);

    $identifier = trim($input['email'] ?? '');
    $password   = $input['password'] ?? '';

    if ($identifier === '' || $password === '') {
        throw new Exception("Please enter your email/phone and password.");
    }

    // FIXED: Use separate parameters for email and phone to avoid binding issues
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
            sub.suspended,
            DATEDIFF(NOW(), sub.expires_at) AS days_since_expiry,
            CASE 
                WHEN sub.expires_at IS NULL THEN 'no_subscription'
                WHEN sub.expires_at < NOW() THEN 'expired'
                WHEN sub.suspended = 1 THEN 'suspended'
                ELSE 'active'
            END AS current_subscription_status
        FROM tblteachers t
        INNER JOIN tblschoolinfo s ON s.id = t.school_id
        LEFT JOIN subscriptions sub ON s.id = sub.school_id AND sub.status = 'active'
        WHERE t.email = :email OR t.phonenumber = :phone
        LIMIT 1
    ");

    // Bind parameters separately to avoid parameter count mismatch
    $stmt->bindParam(':email', $identifier, PDO::PARAM_STR);
    $stmt->bindParam(':phone', $identifier, PDO::PARAM_STR);
    $stmt->execute();
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception("Invalid email/phone or password.");
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
        error_log("Password verified via bcrypt");
    }
    // 2️⃣ Check decrypted plain_password
    elseif (!empty($dbPlainPassword)) {
        $decryptedPassword = decryptPassword($dbPlainPassword);
        
        if ($decryptedPassword !== null && $decryptedPassword === $password) {
            $passwordOk = true;
            error_log("Password verified via plain_password");
            
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
            error_log("Upgraded to bcrypt hash for user ID: " . $user['teacher_id']);
        }
    }
    // 3️⃣ Fallback: plain-text password (legacy accounts)
    elseif (hash_equals($dbPassword, $password) || md5($password) === $dbPassword) {
        $passwordOk = true;
        error_log("Password verified via legacy method");

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
        error_log("Upgraded to bcrypt hash for user ID: " . $user['teacher_id']);
    }

    if (!$passwordOk) {
        error_log("Password verification failed for user: " . $identifier);
        throw new Exception("Invalid email/phone or password.");
    }

    // Calculate days remaining correctly
    if ($user['expires_at']) {
        $expiry_date = new DateTime($user['expires_at']);
        $now = new DateTime();
        $user['days_remaining'] = $now->diff($expiry_date)->days;
        if ($expiry_date < $now) {
            $user['days_remaining'] = -$user['days_remaining'];
        }
    } else {
        $user['days_remaining'] = null;
    }

    // 🔒 CHECK SCHOOL ACTIVATION
    if ((int)$user['is_activated'] !== 1) {
        session_regenerate_id(true);
        $_SESSION = [
            'activation_only' => true,
            'school_id'  => (int)$user['school_id'],
            'teacher_id' => (int)$user['teacher_id'],
            'locked'     => true,
            'lock_time'  => time()
        ];

        echo json_encode([
            "success"  => false,
            "locked"   => true,
            "message"  => "Your school account is pending activation.",
            "redirect" => "../activation-module.php"
        ]);
        exit;
    }

    // 🔒 CHECK SUBSCRIPTION STATUS
    $subscription_status = $user['current_subscription_status'] ?? 'no_subscription';
    $needs_subscription_redirect = false;
    $subscription_message = '';
    $subscription_redirect = '';

    if ($subscription_status === 'expired') {
        $needs_subscription_redirect = true;
        $subscription_message = "Your subscription has expired. Please renew to continue.";
        $subscription_redirect = "../subscription-module.php?status=expired";
    } elseif ($subscription_status === 'suspended') {
        $needs_subscription_redirect = true;
        $subscription_message = "Your subscription has been suspended. Please contact support.";
        $subscription_redirect = "../subscription-module.php?status=suspended";
    } elseif ($subscription_status === 'no_subscription') {
        $needs_subscription_redirect = true;
        $subscription_message = "No active subscription found. Please set up your subscription.";
        $subscription_redirect = "../subscription-module.php?status=no_subscription";
    } elseif ($user['days_remaining'] !== null && $user['days_remaining'] <= 0) {
        $needs_subscription_redirect = true;
        $subscription_message = "Your subscription has expired. Please renew to continue.";
        $subscription_redirect = "../subscription-module.php?status=expired";
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
            'is_trial'              => $user['is_trial'] ?? 0
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
        'school_name'   => $user['school_name'] ?? '',
        'subscription_id' => $user['subscription_id'],
        'subscription_status' => $user['subscription_status'] ?? 'active',
        'plan_name'     => $user['plan_name'] ?? 'Free Trial',
        'expires_at'    => $user['expires_at'],
        'login_time'    => time()
    ];

    error_log("Login successful for: " . $identifier);

    echo json_encode([
        "success"  => true,
        "message"  => "Login successful.",
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