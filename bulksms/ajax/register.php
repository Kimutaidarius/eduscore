<?php
// ajax/register.php
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

// Debug: Log received data
error_log("Received registration data: " . print_r($data, true));

// Validate required fields
$errors = [];
$requiredFields = [
    'fullname', 'email', 'phone', 'sender_id', 
    'password', 'confirm_password', 'terms'
];

foreach ($requiredFields as $field) {
    if (empty($data[$field])) {
        $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
    }
}

// Validate email format
if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Please enter a valid email address';
}

// Validate phone format
if (!empty($data['phone'])) {
    $clean_phone = preg_replace('/[^0-9+]/', '', $data['phone']);
    if (!preg_match('/^\+?[0-9]{10,13}$/', $clean_phone)) {
        $errors['phone'] = 'Invalid phone number format. Use format: +254712345678 or 0712345678';
    }
}

// Validate sender ID
if (!empty($data['sender_id'])) {
    if (strlen($data['sender_id']) < 3 || strlen($data['sender_id']) > 11) {
        $errors['sender_id'] = 'Sender ID must be between 3 and 11 characters';
    } elseif (!preg_match('/^[a-zA-Z0-9]+$/', $data['sender_id'])) {
        $errors['sender_id'] = 'Sender ID can only contain letters and numbers';
    }
}

// Validate password
if (!empty($data['password'])) {
    if (strlen($data['password']) < 8) {
        $errors['password'] = 'Password must be at least 8 characters';
    } else {
        if (!preg_match('/[A-Z]/', $data['password'])) {
            $errors['password'] = 'Password must contain at least one uppercase letter';
        }
        if (!preg_match('/[a-z]/', $data['password'])) {
            $errors['password'] = 'Password must contain at least one lowercase letter';
        }
        if (!preg_match('/[0-9]/', $data['password'])) {
            $errors['password'] = 'Password must contain at least one number';
        }
    }
}

// Validate password confirmation
if (!empty($data['password']) && !empty($data['confirm_password']) && $data['password'] !== $data['confirm_password']) {
    $errors['confirm_password'] = 'Passwords do not match';
}

// Validate terms
if (empty($data['terms']) || $data['terms'] !== 'on' && $data['terms'] !== true && $data['terms'] !== 1 && $data['terms'] !== '1') {
    $errors['terms'] = 'You must agree to the Terms of Service and Privacy Policy';
}

// Check if email already exists
if (!empty($data['email'])) {
    $checkEmail = $conn->prepare("SELECT id FROM superadmins WHERE email = ?");
    if ($checkEmail) {
        $checkEmail->bind_param("s", $data['email']);
        $checkEmail->execute();
        $result = $checkEmail->get_result();
        
        if ($result->num_rows > 0) {
            $errors['email'] = 'Email address is already registered';
        }
        $checkEmail->close();
    }
}

// Check if phone already exists
if (!empty($data['phone'])) {
    $clean_phone = preg_replace('/[^0-9+]/', '', $data['phone']);
    $checkPhone = $conn->prepare("SELECT id FROM superadmins WHERE phone = ?");
    if ($checkPhone) {
        $checkPhone->bind_param("s", $clean_phone);
        $checkPhone->execute();
        $result = $checkPhone->get_result();
        
        if ($result->num_rows > 0) {
            $errors['phone'] = 'Phone number is already registered';
        }
        $checkPhone->close();
    }
}

// Check if sender ID already exists
if (!empty($data['sender_id'])) {
    $checkSender = $conn->prepare("SELECT id FROM superadmins WHERE sender_id = ?");
    if ($checkSender) {
        $checkSender->bind_param("s", $data['sender_id']);
        $checkSender->execute();
        $result = $checkSender->get_result();
        
        if ($result->num_rows > 0) {
            $errors['sender_id'] = 'Sender ID is already taken';
        }
        $checkSender->close();
    }
}

// Return errors if any
if (!empty($errors)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Please fix the validation errors',
        'errors' => $errors
    ]);
    exit;
}

// Generate unique username from email
$base_username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', explode('@', $data['email'])[0]));
$username = $base_username;
$counter = 1;

while (true) {
    $checkUsername = $conn->prepare("SELECT id FROM superadmins WHERE username = ?");
    if ($checkUsername) {
        $checkUsername->bind_param("s", $username);
        $checkUsername->execute();
        $result = $checkUsername->get_result();
        
        if ($result->num_rows == 0) {
            $checkUsername->close();
            break;
        }
        $checkUsername->close();
    }
    $username = $base_username . $counter;
    $counter++;
}

// Hash password
$password_hash = password_hash($data['password'], PASSWORD_DEFAULT);
$clean_phone = preg_replace('/[^0-9+]/', '', $data['phone']);

// Insert superadmin
$insert = $conn->prepare("INSERT INTO superadmins (username, email, password_hash, fullname, phone, sender_id, role, created_at) VALUES (?, ?, ?, ?, ?, ?, 'user', NOW())");

if ($insert) {
    $insert->bind_param("ssssss", $username, $data['email'], $password_hash, $data['fullname'], $clean_phone, $data['sender_id']);
    
    if ($insert->execute()) {
        $superadmin_id = $conn->insert_id;
        
        // Clear rate limiting file if it exists
        $ip = $_SERVER['REMOTE_ADDR'];
        $rate_limit_file = sys_get_temp_dir() . '/register_attempts_' . md5($ip) . '.txt';
        if (file_exists($rate_limit_file)) {
            unlink($rate_limit_file);
        }
        
        // Send welcome email
        $emailSent = false;
        
        $to = $data['email'];
        $subject = "Welcome to EduScore SMS - Registration Successful!";
        
        $message = "
        <html>
        <head>
            <title>Welcome to EduScore SMS</title>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e5e7eb; border-radius: 12px;'>
                <div style='text-align: center; margin-bottom: 20px;'>
                    <h1 style='color: #3b82f6;'>Welcome to EduScore SMS!</h1>
                </div>
                
                <p>Dear <strong>" . htmlspecialchars($data['fullname']) . "</strong>,</p>
                
                <p>Congratulations! Your EduScore SMS account has been successfully created.</p>
                
                <div style='background: #f3f4f6; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                    <h3 style='margin-top: 0; color: #374151;'>Your Account Details:</h3>
                    <p><strong>Username:</strong> " . htmlspecialchars($username) . "</p>
                    <p><strong>Email:</strong> " . htmlspecialchars($data['email']) . "</p>
                    <p><strong>Full Name:</strong> " . htmlspecialchars($data['fullname']) . "</p>
                    <p><strong>Phone:</strong> " . htmlspecialchars($clean_phone) . "</p>
                    <p><strong>Sender ID:</strong> " . htmlspecialchars($data['sender_id']) . "</p>
                </div>
                
                <div style='background: #f0f9ff; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                    <h3 style='margin-top: 0; color: #2563eb;'>🎉 50 Free SMS Credits!</h3>
                    <p>As a welcome gift, we've added <strong>50 free SMS credits</strong> to your account. Start sending messages today!</p>
                </div>
                
                <h3>Getting Started:</h3>
                <ol>
                    <li><strong>Login to your dashboard</strong> using your email and password</li>
                    <li><strong>Generate API credentials</strong> in the SMS API section</li>
                    <li><strong>Start sending messages</strong> using our API or dashboard</li>
                    <li><strong>Monitor your usage</strong> and top up credits when needed</li>
                </ol>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='https://eduscore.ct.ws/login.php' 
                       style='background: linear-gradient(135deg, #3b82f6, #2563eb); 
                              color: white; 
                              padding: 12px 24px; 
                              text-decoration: none; 
                              border-radius: 8px; 
                              font-weight: bold;'>
                        Access Your Dashboard
                    </a>
                </div>
                
                <hr style='border: 1px solid #e5e7eb; margin: 20px 0;'>
                
                <p style='font-size: 14px; color: #6b7280; text-align: center;'>
                    Need help? Contact us at <a href='mailto:support@eduscore.ct.ws'>support@eduscore.ct.ws</a><br>
                    &copy; 2026 EduScore SMS. All rights reserved.
                </p>
            </div>
        </body>
        </html>
        ";
        
        // Plain text version
        $plain_message = "Welcome to EduScore SMS!\n\n";
        $plain_message .= "Dear " . $data['fullname'] . ",\n\n";
        $plain_message .= "Congratulations! Your EduScore SMS account has been successfully created.\n\n";
        $plain_message .= "Your Account Details:\n";
        $plain_message .= "Username: " . $username . "\n";
        $plain_message .= "Email: " . $data['email'] . "\n";
        $plain_message .= "Full Name: " . $data['fullname'] . "\n";
        $plain_message .= "Phone: " . $clean_phone . "\n";
        $plain_message .= "Sender ID: " . $data['sender_id'] . "\n\n";
        $plain_message .= "🎉 50 Free SMS Credits!\n";
        $plain_message .= "As a welcome gift, we've added 50 free SMS credits to your account.\n\n";
        $plain_message .= "Login at: https://eduscore.ct.ws/login.php\n\n";
        $plain_message .= "Need help? Contact support@eduscore.ct.ws\n";
        
        $headers = "From: EduScore SMS <no-reply@eduscore.ct.ws>\r\n";
        $headers .= "Reply-To: support@eduscore.ct.ws\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        
        if (mail($to, $subject, $message, $headers)) {
            $emailSent = true;
            error_log("Welcome email sent successfully to: " . $data['email']);
        } else {
            error_log("Failed to send welcome email to: " . $data['email']);
        }
        
        // Send notification to admin
        $adminEmail = "kymtechnologiesltd@gmail.com";
        $adminSubject = "New Super Admin Registration";
        $adminMessage = "
        A new super admin has registered on EduScore SMS.
        
        Full Name: " . $data['fullname'] . "
        Email: " . $data['email'] . "
        Phone: " . $clean_phone . "
        Sender ID: " . $data['sender_id'] . "
        Username: " . $username . "
        
        Please review this registration.
        ";
        
        @mail($adminEmail, $adminSubject, $adminMessage, "From: no-reply@eduscore.ct.ws");
        
        // Send success response
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Registration successful! A welcome email has been sent to your email address.',
            'superadmin_id' => $superadmin_id,
            'username' => $username,
            'email' => $data['email'],
            'fullname' => $data['fullname'],
            'email_sent' => $emailSent,
            'redirect_url' => 'login.php'
        ]);
        
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Registration failed: ' . $insert->error
        ]);
    }
    $insert->close();
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $conn->error
    ]);
}

$conn->close();
?>