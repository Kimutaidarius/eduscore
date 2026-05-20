<?php
// ajax/verify-otp.php

session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../includes/config.php';

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
    if (!isset($_POST['email']) || !isset($_POST['otp'])) {
        throw new Exception('Email and OTP are required');
    }
    
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    
    // Replace deprecated FILTER_SANITIZE_STRING with htmlspecialchars
    $otp = htmlspecialchars(strip_tags(trim($_POST['otp'])), ENT_QUOTES, 'UTF-8');
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }
    
    if (empty($otp) || strlen($otp) > 10) {
        throw new Exception('Invalid OTP format');
    }
    
    // First, get the user ID from email
    $userId = null;
    
    // Check in tblteachers
    $stmt = $dbh->prepare("SELECT id FROM tblteachers WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user) {
        $userId = $user['id'];
    } else {
        // Check in superadmins
        $stmt = $dbh->prepare("SELECT id FROM superadmins WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            $userId = $user['id'];
        }
    }
    
    if (!$userId) {
        throw new Exception('User not found');
    }
    
    // Get the most recent OTP for this user that hasn't expired
    $stmt = $dbh->prepare("
        SELECT id, otp FROM password_resets 
        WHERE user_id = ? 
        AND expires_at > NOW() 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    
    $stmt->execute([$userId]);
    $reset = $stmt->fetch();
    
    if (!$reset) {
        throw new Exception('No valid OTP found. Please request a new one.');
    }
    
    // Verify the OTP (since it's stored hashed in your database)
    if (!password_verify($otp, $reset['otp'])) {
        throw new Exception('Invalid OTP');
    }
    
    // Delete the used OTP
    $stmt = $dbh->prepare("DELETE FROM password_resets WHERE id = ?");
    $stmt->execute([$reset['id']]);
    
    // Store verification in session
    $_SESSION['otp_verified'] = true;
    $_SESSION['verified_email'] = $email;
    $_SESSION['verified_user_id'] = $userId;
    
    echo json_encode([
        'success' => true,
        'message' => 'OTP verified successfully'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>