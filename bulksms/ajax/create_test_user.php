<?php
// ajax/create_test_user.php
session_start();
require_once '../config/config.php';
require_once '../includes/sms_gateway.php';

// Only allow this in development
$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] == 'POST' || isset($_GET['create'])) {
    
    $username = 'testuser';
    $email = 'test@example.com';
    $password = 'Test@123456';
    $full_name = 'Test User';
    $phone = '0712345678'; // Added phone number for real SMS testing
    
    try {
        // Check if user exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        
        if ($stmt->fetch()) {
            $response['message'] = 'Test user already exists!';
            $response['success'] = true;
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert user with 1000 credits (KES 1000) and phone number
            $stmt = $pdo->prepare("
                INSERT INTO users (username, email, password, full_name, phone, sms_balance, status) 
                VALUES (?, ?, ?, ?, ?, 1000, 'active')
            ");
            
            if ($stmt->execute([$username, $email, $hashed_password, $full_name, $phone])) {
                $user_id = $pdo->lastInsertId();
                
                // Generate API key
                $api_key = 'esk_' . bin2hex(random_bytes(24));
                $api_secret = bin2hex(random_bytes(32));
                
                $stmt = $pdo->prepare("
                    INSERT INTO api_keys (user_id, api_key, api_secret, name, status) 
                    VALUES (?, ?, ?, 'Default API Key', 'active')
                ");
                $stmt->execute([$user_id, $api_key, $api_secret]);
                
                // Create EDUSCORE sender ID
                $stmt = $pdo->prepare("
                    INSERT INTO sender_ids (user_id, sender_id, status, is_default) 
                    VALUES (?, 'EDUSCORE', 'approved', 1)
                ");
                $stmt->execute([$user_id]);
                
                // Add some sample contacts for real SMS testing
                $contacts = [
                    ['John Doe', '0711111111', 'john@example.com'],
                    ['Jane Smith', '0722222222', 'jane@example.com'],
                    ['Peter Kimani', '0733333333', 'peter@example.com'],
                    ['Mary Wanjiku', '0744444444', 'mary@example.com'],
                    ['James Omondi', '0755555555', 'james@example.com']
                ];
                
                $contact_stmt = $pdo->prepare("
                    INSERT INTO contacts (user_id, name, phone, email) 
                    VALUES (?, ?, ?, ?)
                ");
                
                foreach ($contacts as $contact) {
                    $contact_stmt->execute([$user_id, $contact[0], $contact[1], $contact[2]]);
                }
                
                // Add a sample message template
                $template_stmt = $pdo->prepare("
                    INSERT INTO message_templates (user_id, name, message, category) 
                    VALUES (?, 'Welcome Message', 'Hello {name}, welcome to EduScore SMS platform! Your account has been activated.', 'general')
                ");
                $template_stmt->execute([$user_id]);
                
                // Send a welcome SMS to test user's phone (if TextBelt is configured)
                $welcome_message = "Welcome to EduScore SMS! Your test account has been created with 1000 SMS credits. Sender ID: EDUSCORE";
                
                $sms_result = sendSMS($phone, $welcome_message, 'EDUSCORE');
                
                if ($sms_result['success']) {
                    // Log the SMS in database
                    $log_stmt = $pdo->prepare("
                        INSERT INTO sms_messages (user_id, message_id, sender_id, recipient, message, status, sent_at) 
                        VALUES (?, ?, 'EDUSCORE', ?, ?, 'sent', NOW())
                    ");
                    $log_stmt->execute([$user_id, $sms_result['message_id'], $phone, $welcome_message]);
                }
                
                $response['success'] = true;
                $response['message'] = "Test user created successfully!\n" .
                                      "Username: $username\n" .
                                      "Password: $password\n" .
                                      "Balance: 1000 KES\n" .
                                      "Phone: $phone\n" .
                                      "Contacts Added: 5 sample contacts\n" .
                                      "Template Added: 1 sample template\n" .
                                      "Welcome SMS: " . ($sms_result['success'] ? "Sent successfully" : "Failed to send");
            }
        }
    } catch (PDOException $e) {
        $response['message'] = 'Error: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Test User - EduScore SMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: #f8f9fa; padding: 50px; }
        .container { max-width: 700px; margin: 0 auto; }
        .card { border: none; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .card-header { 
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
            color: white; 
            border-radius: 15px 15px 0 0; 
            padding: 25px; 
        }
        .btn-primary { 
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
            border: none; 
            padding: 12px 30px;
            font-weight: 600;
        }
        .btn-primary:hover { 
            background: linear-gradient(135deg, #152b63 0%, #1d4ed8 100%);
        }
        .feature-list {
            background: #f8fafc;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
        }
        .feature-item {
            display: flex;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #e2e8f0;
        }
        .feature-item:last-child {
            border-bottom: none;
        }
        .feature-icon {
            width: 40px;
            height: 40px;
            background: #1e3a8a;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
        }
        .alert-success {
            background: #d1fae5;
            border: none;
            color: #065f46;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0"><i class="fas fa-user-plus me-2"></i>Create Test User - EduScore SMS</h4>
            </div>
            <div class="card-body">
                <?php if (isset($response['message'])): ?>
                    <div class="alert <?php echo $response['success'] ? 'alert-success' : 'alert-danger'; ?>">
                        <i class="fas <?php echo $response['success'] ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> me-2"></i>
                        <?php echo nl2br($response['message']); ?>
                    </div>
                <?php endif; ?>
                
                <div class="feature-list">
                    <h5 class="mb-3"><i class="fas fa-gift me-2 text-primary"></i>What you'll get:</h5>
                    
                    <div class="feature-item">
                        <div class="feature-icon"><i class="fas fa-user"></i></div>
                        <div>
                            <strong>Test Account</strong><br>
                            Username: <code>testuser</code> | Password: <code>Test@123456</code>
                        </div>
                    </div>
                    
                    <div class="feature-item">
                        <div class="feature-icon"><i class="fas fa-coins"></i></div>
                        <div>
                            <strong>SMS Credits</strong><br>
                            1000 KES (1000 SMS messages)
                        </div>
                    </div>
                    
                    <div class="feature-item">
                        <div class="feature-icon"><i class="fas fa-tag"></i></div>
                        <div>
                            <strong>Sender ID</strong><br>
                            EDUSCORE (Pre-approved)
                        </div>
                    </div>
                    
                    <div class="feature-item">
                        <div class="feature-icon"><i class="fas fa-phone"></i></div>
                        <div>
                            <strong>Test Phone Number</strong><br>
                            0712345678 (Welcome SMS will be sent here)
                        </div>
                    </div>
                    
                    <div class="feature-item">
                        <div class="feature-icon"><i class="fas fa-address-book"></i></div>
                        <div>
                            <strong>Sample Contacts</strong><br>
                            5 contacts with Kenyan phone numbers
                        </div>
                    </div>
                    
                    <div class="feature-item">
                        <div class="feature-icon"><i class="fas fa-template"></i></div>
                        <div>
                            <strong>Message Template</strong><br>
                            Welcome message template included
                        </div>
                    </div>
                    
                    <div class="feature-item">
                        <div class="feature-icon"><i class="fas fa-paper-plane"></i></div>
                        <div>
                            <strong>Real SMS Test</strong><br>
                            Welcome SMS sent to your phone number
                        </div>
                    </div>
                </div>
                
                <form method="POST" class="mt-4">
                    <button type="submit" name="create" class="btn btn-primary w-100">
                        <i class="fas fa-magic me-2"></i>Create Test User & Send Welcome SMS
                    </button>
                </form>
                
                <hr>
                
                <div class="d-flex justify-content-between align-items-center">
                    <a href="../login.php" class="btn btn-outline-primary">
                        <i class="fas fa-sign-in-alt me-2"></i>Go to Login
                    </a>
                    <small class="text-muted">
                        <i class="fas fa-info-circle me-1"></i>
                        Using TextBelt gateway (1 free SMS/day)
                    </small>
                </div>
            </div>
        </div>
        
        <!-- SMS Gateway Status -->
        <div class="card mt-4">
            <div class="card-body">
                <h6 class="mb-3"><i class="fas fa-plug me-2"></i>SMS Gateway Status</h6>
                <?php
                // Check gateway status
                $stmt = $pdo->query("SELECT * FROM sms_gateway_settings WHERE is_active = 1");
                $active_gateway = $stmt->fetch();
                ?>
                <?php if ($active_gateway): ?>
                    <div class="alert alert-success mb-0">
                        <i class="fas fa-check-circle me-2"></i>
                        Active Gateway: <strong><?php echo $active_gateway['gateway_name']; ?></strong>
                        <br>
                        <small>API URL: <?php echo $active_gateway['api_url']; ?></small>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning mb-0">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        No active SMS gateway configured. SMS sending will fail.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>