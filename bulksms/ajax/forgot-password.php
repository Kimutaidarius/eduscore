<?php
// ajax/forgot-password.php
require_once '../config/db.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set header to JSON
header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method. Use POST.']);
    exit;
}

// Get the JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// If JSON decode fails, try form data
if (!$data && $input && json_last_error() !== JSON_ERROR_NONE) {
    $data = $_POST;
}

// If no data received
if (empty($data)) {
    $data = $_POST;
}

// Rate limiting
$ip = $_SERVER['REMOTE_ADDR'];
$rate_limit_file = sys_get_temp_dir() . '/reset_attempts_' . md5($ip) . '.txt';
$max_attempts = 3;
$lockout_time = 1800; // 30 minutes

// Check rate limit
if (file_exists($rate_limit_file)) {
    $rate_data = json_decode(file_get_contents($rate_limit_file), true);
    $attempts = $rate_data['attempts'] ?? 0;
    $first_attempt = $rate_data['first_attempt'] ?? time();
    
    if ($attempts >= $max_attempts && (time() - $first_attempt) < $lockout_time) {
        $minutes = ceil(($lockout_time - (time() - $first_attempt)) / 60);
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'message' => "Too many reset attempts. Please try again in {$minutes} minutes."
        ]);
        exit;
    } elseif ((time() - $first_attempt) > $lockout_time) {
        unlink($rate_limit_file);
    }
}

// Validate email
$errors = [];
$email = trim($data['email'] ?? '');

if (empty($email)) {
    $errors['email'] = 'Email address is required';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Invalid email address format';
}

if (!empty($errors)) {
    // Update rate limiting
    if (!file_exists($rate_limit_file)) {
        $rate_data = ['attempts' => 1, 'first_attempt' => time()];
    } else {
        $rate_data = json_decode(file_get_contents($rate_limit_file), true);
        $rate_data['attempts']++;
    }
    file_put_contents($rate_limit_file, json_encode($rate_data));
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Please fix the validation errors',
        'errors' => $errors
    ]);
    exit;
}

// Check if email exists in superadmins table
$check_email = $conn->prepare("SELECT id, username, fullname FROM superadmins WHERE email = ?");
if (!$check_email) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

$check_email->bind_param("s", $email);
$check_email->execute();
$result = $check_email->get_result();

if ($result->num_rows === 0) {
    // Don't reveal that email doesn't exist (security)
    echo json_encode([
        'success' => true,
        'message' => 'If the email exists in our system, reset instructions will be sent.'
    ]);
    exit;
}

$user = $result->fetch_assoc();
$check_email->close();

// Generate reset token and OTP
$reset_token = bin2hex(random_bytes(32));
$otp = sprintf("%06d", mt_rand(100000, 999999));
$hashed_otp = password_hash($otp, PASSWORD_DEFAULT);
$expires_at = date('Y-m-d H:i:s', strtotime('+30 minutes'));

// Store reset request
$store_reset = $conn->prepare("INSERT INTO password_resets (user_id, otp, token, expires_at, used) VALUES (?, ?, ?, ?, 0)");
if (!$store_reset) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

$store_reset->bind_param("isss", $user['id'], $hashed_otp, $reset_token, $expires_at);
$store_reset->execute();
$reset_id = $conn->insert_id;
$store_reset->close();

// Send email
$to = $email;
$subject = "Password Reset Request - EduScore SMS";

$reset_link = "https://" . $_SERVER['HTTP_HOST'] . "/reset-password.php?token=" . $reset_token;

$message = "
<html>
<head>
    <title>Password Reset Request</title>
</head>
<body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
    <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e5e7eb; border-radius: 12px;'>
        <div style='text-align: center; margin-bottom: 20px;'>
            <h1 style='color: #3b82f6;'>Password Reset Request</h1>
        </div>
        
        <p>Dear <strong>" . htmlspecialchars($user['fullname'] ?: $user['username']) . "</strong>,</p>
        
        <p>We received a request to reset your password for your EduScore SMS account.</p>
        
        <div style='background: #f3f4f6; padding: 15px; border-radius: 8px; margin: 20px 0; text-align: center;'>
            <h3 style='margin-top: 0; color: #374151;'>Your OTP Code:</h3>
            <p style='font-size: 32px; font-weight: bold; letter-spacing: 5px; color: #3b82f6; margin: 10px 0;'>" . $otp . "</p>
            <p style='font-size: 14px; color: #6b7280;'>This code expires in 30 minutes</p>
        </div>
        
        <p>You can also reset your password by clicking the button below:</p>
        
        <div style='text-align: center; margin: 30px 0;'>
            <a href='" . $reset_link . "' 
               style='background: linear-gradient(135deg, #3b82f6, #2563eb); 
                      color: white; 
                      padding: 12px 24px; 
                      text-decoration: none; 
                      border-radius: 8px; 
                      font-weight: bold;'>
                Reset Password
            </a>
        </div>
        
        <p style='font-size: 14px; color: #6b7280;'>If you didn't request this, please ignore this email or contact support if you have concerns.</p>
        
        <hr style='border: 1px solid #e5e7eb; margin: 20px 0;'>
        
        <p style='font-size: 14px; color: #6b7280; text-align: center;'>
            Need help? Contact us at <a href='mailto:support@eduscore.ct.ws'>support@eduscore.ct.ws</a><br>
            &copy; 2026 EduScore SMS. All rights reserved.
        </p>
    </div>
</body>
</html>
";

$headers = "From: EduScore SMS <no-reply@eduscore.ct.ws>\r\n";
$headers .= "Reply-To: support@eduscore.ct.ws\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/html; charset=UTF-8\r\n";
$headers .= "X-Mailer: PHP/" . phpversion();

$emailSent = mail($to, $subject, $message, $headers);

if ($emailSent) {
    // Clear rate limiting on success
    if (file_exists($rate_limit_file)) {
        unlink($rate_limit_file);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Password reset instructions have been sent to your email.'
    ]);
} else {
    // Delete the reset record if email failed
    $delete_reset = $conn->prepare("DELETE FROM password_resets WHERE id = ?");
    $delete_reset->bind_param("i", $reset_id);
    $delete_reset->execute();
    $delete_reset->close();
    
    error_log("Failed to send password reset email to: " . $email);
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to send email. Please try again later.'
    ]);
}

$conn->close();
?>