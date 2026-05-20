<?php
// ajax/reset-password.php

session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../includes/config.php';
require_once '../includes/EmailHelper.php'; // Include EmailHelper for encryption function

// Verify database connection
if (!isset($dbh) || $dbh === null) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection error'
    ]);
    exit;
}

header('Content-Type: application/json');

try {
    // Check if OTP was verified
    if (!isset($_SESSION['otp_verified']) || !$_SESSION['otp_verified']) {
        throw new Exception('Please verify OTP first');
    }
    
    if (!isset($_SESSION['verified_email']) || !isset($_SESSION['verified_user_id'])) {
        throw new Exception('Session expired. Please start over.');
    }
    
    if (!isset($_POST['newPassword'])) {
        throw new Exception('New password is required');
    }
    
    $email = $_SESSION['verified_email'];
    $userId = $_SESSION['verified_user_id'];
    $newPassword = $_POST['newPassword'];
    
    // Validate password strength
    if (strlen($newPassword) < 8) {
        throw new Exception('Password must be at least 8 characters long');
    }
    
    if (!preg_match('/[A-Z]/', $newPassword)) {
        throw new Exception('Password must contain at least one uppercase letter');
    }
    
    if (!preg_match('/[a-z]/', $newPassword)) {
        throw new Exception('Password must contain at least one lowercase letter');
    }
    
    if (!preg_match('/[0-9]/', $newPassword)) {
        throw new Exception('Password must contain at least one number');
    }
    
    // Hash the new password for login
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Encrypt the plain password for admin viewing
    function encryptPassword($password) {
        $encryption_key = 'your-32-char-encryption-key-here!123456';
        $iv = '1234567890123456';
        $encrypted = openssl_encrypt($password, 'AES-256-CBC', $encryption_key, 0, $iv);
        return $encrypted;
    }
    
    $encryptedPassword = encryptPassword($newPassword);
    
    // Determine which table the user belongs to and update password
    $updated = false;
    
    // Check if user is in tblteachers
    $stmt = $dbh->prepare("SELECT id, school_id FROM tblteachers WHERE id = ? AND email = ?");
    $stmt->execute([$userId, $email]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($teacher) {
        // Update teacher password - update both password and plain_password columns
        $stmt = $dbh->prepare("UPDATE tblteachers SET password = ?, plain_password = ? WHERE id = ?");
        if ($stmt->execute([$hashedPassword, $encryptedPassword, $userId])) {
            $updated = true;
            
            // Log the password reset in audit log
            $logStmt = $dbh->prepare("
                INSERT INTO password_audit_log 
                (teacher_id, school_id, action, password_revealed, revealed_by, revealed_at, ip_address)
                VALUES (?, ?, 'password_reset_by_user', ?, 'system', NOW(), ?)
            ");
            $logStmt->execute([
                $userId,
                $teacher['school_id'],
                $encryptedPassword,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        }
    } else {
        // Check if user is in superadmins
        $stmt = $dbh->prepare("SELECT id FROM superadmins WHERE id = ? AND email = ?");
        $stmt->execute([$userId, $email]);
        if ($stmt->fetch()) {
            // Update superadmin password - superadmins don't have plain_password column
            $stmt = $dbh->prepare("UPDATE superadmins SET password_hash = ? WHERE id = ?");
            if ($stmt->execute([$hashedPassword, $userId])) {
                $updated = true;
            }
        }
    }
    
    if (!$updated) {
        throw new Exception('Failed to update password. User not found.');
    }
    
    // Clean up all OTPs for this user
    $stmt = $dbh->prepare("DELETE FROM password_resets WHERE user_id = ?");
    $stmt->execute([$userId]);
    
    // Clear session
    unset($_SESSION['otp_verified']);
    unset($_SESSION['verified_email']);
    unset($_SESSION['verified_user_id']);
    unset($_SESSION['reset_email']);
    
    echo json_encode([
        'success' => true,
        'message' => 'Password reset successfully. You can now login with your new password.'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>