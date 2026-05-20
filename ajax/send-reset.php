<?php
// ajax/send-reset.php

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

require_once '../includes/EmailHelper.php';

header('Content-Type: application/json');

try {
    // Validate email
    if (!isset($_POST['email']) || empty($_POST['email'])) {
        throw new Exception('Email is required');
    }
    
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }
    
    // First, check if there's a users table (adjust table name if different)
    // From your database, it looks like users might be in tblteachers or superadmins
    // Let's check both common tables
    
    $user = null;
    $userId = null;
    
    // Check in tblteachers first
    $stmt = $dbh->prepare("SELECT id, email FROM tblteachers WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user) {
        $userId = $user['id'];
    } else {
        // Check in superadmins
        $stmt = $dbh->prepare("SELECT id, email FROM superadmins WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            $userId = $user['id'];
        }
    }
    
    if (!$user) {
        // Don't reveal if email exists or not for security
        echo json_encode([
            'success' => true,
            'message' => 'If the email exists in our system, you will receive an OTP'
        ]);
        exit;
    }
    
    // Initialize Email Helper
    $emailHelper = new EmailHelper();
    
    // Generate OTP
    $otp = $emailHelper->generateOTP();
    
    // Hash the OTP for security (as shown in your existing data)
    $hashedOtp = password_hash($otp, PASSWORD_DEFAULT);
    
    // Calculate expiry (10 minutes from now)
    $expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    
    // Store OTP in database - matching your actual table structure
    $stmt = $dbh->prepare("INSERT INTO password_resets (user_id, otp, expires_at) VALUES (?, ?, ?)");
    if (!$stmt->execute([$userId, $hashedOtp, $expiry])) {
        throw new Exception('Failed to store OTP');
    }
    
    // Send OTP via email
    $emailResult = $emailHelper->sendPasswordResetOTP($email, $otp);
    
    if ($emailResult['success']) {
        $_SESSION['reset_email'] = $email;
        
        echo json_encode([
            'success' => true,
            'message' => 'OTP sent successfully to your email'
        ]);
    } else {
        throw new Exception($emailResult['message']);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>