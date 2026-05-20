<?php
// ajax/request_activation.php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['school_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// DEBUG: Check current directory and paths
$current_dir = __DIR__;
$root_dir = dirname(__DIR__);
$debug_info = [
    'current_dir' => $current_dir,
    'root_dir' => $root_dir,
    'config_exists' => file_exists($root_dir . '/includes/config.php') ? 'Yes' : 'No',
    'email_helper_exists' => file_exists($root_dir . '/includes/EmailHelper.php') ? 'Yes' : 'No',
    'phpmailer_exists' => file_exists($root_dir . '/includes/phpmailer/PHPMailer.php') ? 'Yes' : 'No',
    'smtp_config_exists' => file_exists($root_dir . '/config/smtp_config.php') ? 'Yes' : 'No'
];
error_log("Request Activation Debug: " . print_r($debug_info, true));

// Try to include files with absolute paths
try {
    require_once $root_dir . '/includes/config.php';
    require_once $root_dir . '/includes/EmailHelper.php';
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'File inclusion error: ' . $e->getMessage()]);
    exit();
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit();
}

$school_id = $input['school_id'] ?? $_SESSION['school_id'];
$teacher_id = $input['teacher_id'] ?? $_SESSION['teacher_id'];
$notes = $input['notes'] ?? '';
$payment_amount = $input['payment_amount'] ?? 0;
$payment_type = $input['payment_type'] ?? '';

if (!$school_id || !$teacher_id) {
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit();
}

try {
    // Get school details - FIXED: Changed 'address' to 'school_address'
    $stmt = $dbh->prepare("
        SELECT school_name, school_email, school_phone, county, school_address, principal_name 
        FROM tblschoolinfo 
        WHERE id = ?
    ");
    $stmt->execute([$school_id]);
    $school_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$school_info) {
        throw new Exception('School not found');
    }
    
    // Get teacher details
    $stmt = $dbh->prepare("
        SELECT firstname, secondname, lastname, email, phonenumber 
        FROM tblteachers 
        WHERE id = ? AND school_id = ?
    ");
    $stmt->execute([$teacher_id, $school_id]);
    $teacher_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$teacher_info) {
        throw new Exception('Teacher not found');
    }
    
    $teacher_name = trim($teacher_info['firstname'] . ' ' . $teacher_info['secondname'] . ' ' . $teacher_info['lastname']);
    if (empty($teacher_name)) {
        $teacher_name = $teacher_info['email'];
    }
    
    // Prepare email content
    $to_email = 'kymtechnologiesltd@gmail.com';
    $subject = 'New School Activation Request - ' . $school_info['school_name'];
    
    // Build HTML email - FIXED: Changed 'address' to 'school_address'
    $html_message = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
            .container { max-width: 600px; margin: 20px auto; background: #f9f9f9; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(135deg, #0b2c4d, #143a63); color: white; padding: 30px; text-align: center; }
            .header h1 { margin: 0; font-size: 24px; }
            .header p { margin: 5px 0 0; opacity: 0.9; font-size: 14px; }
            .content { background: white; padding: 30px; }
            .section { background: #f5f5f5; padding: 20px; margin: 20px 0; border-radius: 8px; border-left: 4px solid #0b2c4d; }
            .section h3 { margin: 0 0 15px; color: #0b2c4d; display: flex; align-items: center; gap: 10px; }
            .info-row { display: flex; margin-bottom: 10px; border-bottom: 1px solid #e0e0e0; padding-bottom: 8px; }
            .info-row:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
            .label { font-weight: 600; color: #666; width: 120px; }
            .value { color: #333; flex: 1; }
            .payment-info { background: #fff3cd; border-left: 4px solid #ffc107; padding: 20px; margin: 20px 0; border-radius: 8px; }
            .payment-info h3 { margin: 0 0 15px; color: #856404; }
            .amount-box { background: white; padding: 15px; border-radius: 6px; text-align: center; margin-top: 15px; }
            .amount-box .amount { font-size: 28px; font-weight: bold; color: #0b2c4d; }
            .amount-box .type { font-size: 14px; color: #666; margin-top: 5px; }
            .notes-box { background: #e8f4fd; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #0b2c4d; }
            .footer { text-align: center; padding: 20px; background: #f0f0f0; font-size: 12px; color: #666; }
            .btn { display: inline-block; padding: 12px 30px; background: #0b2c4d; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; }
            .btn:hover { background: #143a63; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🏫 New School Activation Request</h1>
                <p>' . htmlspecialchars($school_info['school_name']) . '</p>
            </div>
            
            <div class="content">
                <p>A new activation request has been submitted. Please review the details below:</p>
                
                <div class="section">
                    <h3>🏛️ School Information</h3>
                    <div class="info-row">
                        <span class="label">School Name:</span>
                        <span class="value">' . htmlspecialchars($school_info['school_name'] ?? 'N/A') . '</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Email:</span>
                        <span class="value">' . htmlspecialchars($school_info['school_email'] ?? 'N/A') . '</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Phone:</span>
                        <span class="value">' . htmlspecialchars($school_info['school_phone'] ?? 'N/A') . '</span>
                    </div>
                    <div class="info-row">
                        <span class="label">County:</span>
                        <span class="value">' . htmlspecialchars($school_info['county'] ?? 'N/A') . '</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Address:</span>
                        <span class="value">' . htmlspecialchars($school_info['school_address'] ?? 'N/A') . '</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Principal:</span>
                        <span class="value">' . htmlspecialchars($school_info['principal_name'] ?? 'N/A') . '</span>
                    </div>
                </div>
                
                <div class="section">
                    <h3>👤 Requester Information</h3>
                    <div class="info-row">
                        <span class="label">Name:</span>
                        <span class="value">' . htmlspecialchars($teacher_name) . '</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Email:</span>
                        <span class="value">' . htmlspecialchars($teacher_info['email'] ?? 'N/A') . '</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Phone:</span>
                        <span class="value">' . htmlspecialchars($teacher_info['phonenumber'] ?? 'N/A') . '</span>
                    </div>
                </div>
                
                <div class="payment-info">
                    <h3>💰 Payment Details</h3>
                    <div class="info-row">
                        <span class="label">Payment Type:</span>
                        <span class="value">' . htmlspecialchars($payment_type) . '</span>
                    </div>
                    <div class="amount-box">
                        <div class="amount">KES ' . number_format($payment_amount, 2) . '</div>
                        <div class="type">Amount Due</div>
                    </div>
                </div>';
    
    if (!empty($notes)) {
        $html_message .= '
                <div class="notes-box">
                    <h3 style="margin: 0 0 10px; color: #0b2c4d;">📝 Additional Notes</h3>
                    <p style="margin: 0;">' . nl2br(htmlspecialchars($notes)) . '</p>
                </div>';
    }
    
    $html_message .= '
                <div style="text-align: center;">
                    <a href="https://eduscore.ct.ws/admin/activations.php" class="btn">View in Admin Panel</a>
                </div>
            </div>
            
            <div class="footer">
                <p>This is an automated message from the EduScore System.</p>
                <p>© ' . date('Y') . ' EduScore. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>';
    
    // Plain text version - FIXED: Changed 'address' to 'school_address'
    $text_message = "NEW SCHOOL ACTIVATION REQUEST\n";
    $text_message .= str_repeat("=", 50) . "\n\n";
    $text_message .= "School: " . $school_info['school_name'] . "\n\n";
    
    $text_message .= "SCHOOL INFORMATION:\n";
    $text_message .= "------------------\n";
    $text_message .= "School Name: " . ($school_info['school_name'] ?? 'N/A') . "\n";
    $text_message .= "Email: " . ($school_info['school_email'] ?? 'N/A') . "\n";
    $text_message .= "Phone: " . ($school_info['school_phone'] ?? 'N/A') . "\n";
    $text_message .= "County: " . ($school_info['county'] ?? 'N/A') . "\n";
    $text_message .= "Address: " . ($school_info['school_address'] ?? 'N/A') . "\n";
    $text_message .= "Principal: " . ($school_info['principal_name'] ?? 'N/A') . "\n\n";
    
    $text_message .= "REQUESTER INFORMATION:\n";
    $text_message .= "---------------------\n";
    $text_message .= "Name: " . $teacher_name . "\n";
    $text_message .= "Email: " . ($teacher_info['email'] ?? 'N/A') . "\n";
    $text_message .= "Phone: " . ($teacher_info['phonenumber'] ?? 'N/A') . "\n\n";
    
    $text_message .= "PAYMENT DETAILS:\n";
    $text_message .= "---------------\n";
    $text_message .= "Type: " . $payment_type . "\n";
    $text_message .= "Amount: KES " . number_format($payment_amount, 2) . "\n\n";
    
    if (!empty($notes)) {
        $text_message .= "ADDITIONAL NOTES:\n";
        $text_message .= "-----------------\n";
        $text_message .= $notes . "\n\n";
    }
    
    $text_message .= "View in Admin Panel: https://eduscore.ct.ws/admin/activations.php\n";
    
    // Send email using PHPMailer
    try {
        // Check if smtp_config exists, if not, define constants
        if (!defined('SMTP_HOST') && file_exists($root_dir . '/config/smtp_config.php')) {
            require_once $root_dir . '/config/smtp_config.php';
        } elseif (!defined('SMTP_HOST')) {
            // Define SMTP constants if file doesn't exist
            define('SMTP_HOST', 'smtp.gmail.com');
            define('SMTP_USERNAME', 'your-email@gmail.com');
            define('SMTP_PASSWORD', 'your-password');
            define('SMTP_ENCRYPTION', 'tls');
            define('SMTP_PORT', 587);
            define('SMTP_FROM_EMAIL', 'your-email@gmail.com');
            define('SMTP_FROM_NAME', 'EduScore System');
        }
        
        // Create a new PHPMailer instance
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION;
        $mail->Port       = SMTP_PORT;
        $mail->SMTPDebug  = 0; // Set to 0 in production
        
        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to_email);
        $mail->addReplyTo($teacher_info['email'] ?? SMTP_FROM_EMAIL, $teacher_name);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html_message;
        $mail->AltBody = $text_message;
        
        $mail->send();
        
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $e->getMessage());
        throw new Exception("Failed to send email: " . $e->getMessage());
    }
    
    // Store the request in database
    try {
        // Create table if not exists
        $dbh->exec("
            CREATE TABLE IF NOT EXISTS activation_requests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                school_id INT NOT NULL,
                teacher_id INT NOT NULL,
                payment_amount DECIMAL(10,2),
                payment_type VARCHAR(100),
                notes TEXT,
                status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
                INDEX (school_id),
                INDEX (status)
            )
        ");
        
        $stmt = $dbh->prepare("
            INSERT INTO activation_requests (school_id, teacher_id, payment_amount, payment_type, notes)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$school_id, $teacher_id, $payment_amount, $payment_type, $notes]);
        
        $request_id = $dbh->lastInsertId();
        
        // Log the request
        error_log("Activation request #$request_id saved for school ID: $school_id");
        
    } catch (PDOException $e) {
        // Log but don't fail the request
        error_log("Failed to save activation request to database: " . $e->getMessage());
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Activation request sent successfully. Admin will review your request.'
    ]);
    
} catch (Exception $e) {
    error_log("Activation request error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to send activation request: ' . $e->getMessage()
    ]);
}
?>