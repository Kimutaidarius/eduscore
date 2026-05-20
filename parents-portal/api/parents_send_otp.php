<?php
header('Content-Type: application/json');
session_start();
require_once '../includes/config.php';

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$phone = isset($input['phone']) ? trim($input['phone']) : '';

if (empty($phone)) {
    echo json_encode(['success' => false, 'message' => 'Phone number is required']);
    exit;
}

// Generate 6-digit OTP
$otp = sprintf("%06d", mt_rand(1, 999999));

// Store OTP in session
$_SESSION['parent_otp'] = $otp;
$_SESSION['parent_otp_phone'] = $phone;
$_SESSION['parent_otp_expiry'] = time() + 300; // 5 minutes expiry

// Log OTP for debugging
error_log("OTP for $phone: $otp");

// For testing, return OTP in response (remove in production)
echo json_encode([
    'success' => true, 
    'message' => 'Verification code sent successfully',
    'debug_otp' => $otp
]);
?>