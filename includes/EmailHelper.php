<?php
// includes/EmailHelper.php

require_once __DIR__ . '/phpmailer/PHPMailer.php';
require_once __DIR__ . '/phpmailer/SMTP.php';
require_once __DIR__ . '/phpmailer/Exception.php';

// Define constants if they don't exist (fallback)
if (!defined('SMTP_HOST')) {
    define('SMTP_HOST', 'smtp.gmail.com');
}
if (!defined('SMTP_PORT')) {
    define('SMTP_PORT', 587);
}
if (!defined('SMTP_USERNAME')) {
    define('SMTP_USERNAME', 'kymtechnologiesltd@gmail.com');
}
if (!defined('SMTP_PASSWORD')) {
    define('SMTP_PASSWORD', 'cwev xgwb wksp clbt');
}
if (!defined('SMTP_FROM_EMAIL')) {
    define('SMTP_FROM_EMAIL', 'noreply@eduscore.com');
}
if (!defined('SMTP_FROM_NAME')) {
    define('SMTP_FROM_NAME', 'EduScore System');
}
if (!defined('SMTP_ENCRYPTION')) {
    define('SMTP_ENCRYPTION', 'tls');
}

// Now try to load the config file if it exists (it will override if needed)
$smtpConfigPath = __DIR__ . '/../config/smtp_config.php';
if (file_exists($smtpConfigPath)) {
    require_once $smtpConfigPath;
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailHelper {
    private $mail;
    
    public function __construct() {
        $this->mail = new PHPMailer(true);
        
        try {
            // Server settings
            $this->mail->isSMTP();
            $this->mail->Host       = SMTP_HOST;
            $this->mail->SMTPAuth   = true;
            $this->mail->Username   = SMTP_USERNAME;
            $this->mail->Password   = SMTP_PASSWORD;
            $this->mail->SMTPSecure = SMTP_ENCRYPTION;
            $this->mail->Port       = SMTP_PORT;
            
            // Disable SSL verification for testing (remove in production)
            $this->mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
            
            // Default settings
            $this->mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $this->mail->isHTML(true);
            $this->mail->CharSet = 'UTF-8';
            
            // Enable debug logging (remove in production)
            // $this->mail->SMTPDebug = SMTP::DEBUG_SERVER;
            
        } catch (Exception $e) {
            error_log("EmailHelper constructor error: " . $e->getMessage());
        }
    }
    
    /**
     * Send Password Reset OTP
     */
    public function sendPasswordResetOTP($email, $otp) {
        try {
            $this->mail->clearAddresses();
            $this->mail->clearAttachments();
            $this->mail->addAddress($email);
            $this->mail->Subject = 'Password Reset Request - EduScore System';
            $htmlBody = $this->getResetEmailHTML($otp);
            $this->mail->Body = $htmlBody;
            $this->mail->AltBody = $this->getResetEmailText($otp);
            $this->mail->send();
            return ['success' => true, 'message' => 'OTP sent successfully'];
        } catch (Exception $e) {
            error_log("Password Reset OTP Error: " . $this->mail->ErrorInfo);
            return ['success' => false, 'message' => 'Failed to send email: ' . $e->getMessage()];
        }
    }
    
    /**
     * Send Welcome Email to New Teacher
     */
    public function sendWelcomeEmail($to_email, $teacher_name, $teacher_number, $password) {
        try {
            $this->mail->clearAddresses();
            $this->mail->clearAttachments();
            $this->mail->addAddress($to_email, $teacher_name);
            $this->mail->Subject = 'Welcome to EduScore - Your Login Credentials';
            $htmlBody = $this->getWelcomeEmailHTML($teacher_name, $teacher_number, $password, $to_email);
            $this->mail->Body = $htmlBody;
            $this->mail->AltBody = $this->getWelcomeEmailText($teacher_name, $teacher_number, $password, $to_email);
            $this->mail->send();
            error_log("Welcome email sent successfully to: " . $to_email);
            return true;
        } catch (Exception $e) {
            error_log("Welcome email error to {$to_email}: " . $this->mail->ErrorInfo);
            return false;
        }
    }
    
    /**
     * Send School Registration Confirmation Email
     */
    public function sendSchoolConfirmationEmail($school_email, $school_name, $admin_name, $school_id, $activation_code) {
        try {
            $this->mail->clearAddresses();
            $this->mail->clearAttachments();
            $this->mail->addAddress($school_email, $school_name);
            $this->mail->Subject = 'School Registration Confirmation - EduScore';
            $trialExpires = date('F d, Y', strtotime('+30 days'));
            $htmlBody = $this->getSchoolConfirmationHTML($school_name, $admin_name, $school_id, $activation_code, $trialExpires);
            $plainText = $this->getSchoolConfirmationText($school_name, $admin_name, $school_id, $activation_code, $trialExpires);
            $this->mail->Body = $htmlBody;
            $this->mail->AltBody = $plainText;
            $this->mail->send();
            error_log("School confirmation email sent successfully to: " . $school_email);
            return true;
        } catch (Exception $e) {
            error_log("School confirmation email error: " . $this->mail->ErrorInfo);
            return false;
        }
    }
    
    /**
     * Generate OTP
     */
    public function generateOTP() {
        return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
    
    /**
     * HTML Email Template for Password Reset
     */
    private function getResetEmailHTML($otp) {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9; }
                .header { background: linear-gradient(135deg, #0b2c4d, #143a63); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .header h1 { margin: 0; font-size: 24px; }
                .content { background: white; padding: 30px; border-radius: 0 0 10px 10px; }
                .otp-box { background: #f0f0f0; padding: 20px; text-align: center; font-size: 36px; letter-spacing: 5px; font-weight: bold; color: #0b2c4d; margin: 20px 0; border-radius: 5px; }
                .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; font-size: 14px; }
                .footer { text-align: center; color: #666; font-size: 12px; margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>🔐 Password Reset Request</h1>
                </div>
                <div class="content">
                    <h2>Hello,</h2>
                    <p>We received a request to reset your password for your EduScore System account. Use the OTP code below to complete the process:</p>
                    <div class="otp-box">' . $otp . '</div>
                    <p>This OTP will expire in <strong>10 minutes</strong>.</p>
                    <div class="warning">
                        <strong>⚠️ Security Alert:</strong> If you didn\'t request this password reset, please ignore this email or contact support immediately.
                    </div>
                    <p>For security reasons, never share this OTP with anyone. Our team will never ask for your password or OTP.</p>
                </div>
                <div class="footer">
                    <p>© ' . date('Y') . ' EduScore System. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>';
    }
    
    /**
     * Plain Text Email Template for Password Reset
     */
    private function getResetEmailText($otp) {
        return "Password Reset Request\n\nWe received a request to reset your password.\n\nYour OTP Code: " . $otp . "\n\nThis OTP will expire in 10 minutes.\n\nIf you didn't request this, please ignore this email.\n\n© " . date('Y') . " EduScore System";
    }
    
    /**
     * HTML Email Template for Welcome Email
     */
    private function getWelcomeEmailHTML($teacher_name, $teacher_number, $password, $email) {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9; }
                .header { background: linear-gradient(135deg, #0b2c4d, #143a63); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .header h1 { margin: 0; font-size: 24px; }
                .content { background: white; padding: 30px; border-radius: 0 0 10px 10px; }
                .credentials { background: #f0f0f0; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #ffc107; }
                .password-box { background: #e8f4fd; padding: 10px; font-family: monospace; font-size: 18px; text-align: center; border-radius: 5px; }
                .footer { text-align: center; color: #666; font-size: 12px; margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>🎓 Welcome to EduScore!</h1>
                </div>
                <div class="content">
                    <h2>Hello ' . htmlspecialchars($teacher_name) . ',</h2>
                    <p>Your teacher account has been created successfully in the EduScore system. Below are your login credentials:</p>
                    <div class="credentials">
                        <p><strong>Teacher ID:</strong> ' . htmlspecialchars($teacher_number) . '</p>
                        <p><strong>Email:</strong> ' . htmlspecialchars($email) . '</p>
                        <p><strong>Default Password:</strong></p>
                        <div class="password-box">' . htmlspecialchars($password) . '</div>
                    </div>
                    <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; font-size: 14px;">
                        <strong>⚠️ Security Alert:</strong> For security reasons, please change your password after your first login.
                    </div>
                    <p>You can access the system at: <a href="https://eduscore.co.ke/login.php">https://eduscore.co.ke/login.php</a></p>
                    <p>Best regards,<br><strong>EduScore Team</strong></p>
                </div>
                <div class="footer">
                    <p>© ' . date('Y') . ' EduScore. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>';
    }
    
    /**
     * Plain Text Email Template for Welcome Email
     */
    private function getWelcomeEmailText($teacher_name, $teacher_number, $password, $email) {
        return "Welcome to EduScore!\n\nHello " . $teacher_name . ",\n\nYour teacher account has been created successfully. Below are your login credentials:\n\nTeacher ID: " . $teacher_number . "\nEmail: " . $email . "\nDefault Password: " . $password . "\n\nFor security reasons, please change your password after your first login.\n\nAccess the system at: https://eduscore.co.ke/login.php\n\nBest regards,\nEduScore Team";
    }
    
    /**
     * HTML Email Template for School Confirmation
     */
    private function getSchoolConfirmationHTML($school_name, $admin_name, $school_id, $activation_code, $trialExpires) {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9; }
                .header { background: linear-gradient(135deg, #0b2c4d, #143a63); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .header h1 { margin: 0; font-size: 24px; }
                .content { background: white; padding: 30px; border-radius: 0 0 10px 10px; }
                .info-box { background: #e8f4fd; padding: 20px; border-radius: 8px; margin: 20px 0; }
                .activation-code { background: #f0f0f0; padding: 15px; text-align: center; font-size: 24px; letter-spacing: 3px; font-weight: bold; font-family: monospace; border-radius: 5px; }
                .footer { text-align: center; color: #666; font-size: 12px; margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>🏫 Registration Successful!</h1>
                </div>
                <div class="content">
                    <h2>Dear ' . htmlspecialchars($admin_name) . ',</h2>
                    <p>Congratulations! Your school <strong>' . htmlspecialchars($school_name) . '</strong> has been successfully registered on the EduScore system.</p>
                    <div class="info-box">
                        <h3>Registration Details:</h3>
                        <p><strong>School ID:</strong> ' . htmlspecialchars($school_id) . '</p>
                        <p><strong>License Tier:</strong> Basic (Trial)</p>
                        <p><strong>Trial Period:</strong> 30 days (Expires: ' . htmlspecialchars($trialExpires) . ')</p>
                        <p><strong>Activation Code:</strong></p>
                        <div class="activation-code">' . htmlspecialchars($activation_code) . '</div>
                    </div>
                    <h3>Next Steps:</h3>
                    <ol>
                        <li>Login to your account using the credentials sent to your email</li>
                        <li>Complete your school profile setup</li>
                        <li>Add classrooms and subjects</li>
                        <li>Register students and teachers</li>
                        <li>Start using the system!</li>
                    </ol>
                    <p>Best regards,<br><strong>EduScore Team</strong></p>
                </div>
                <div class="footer">
                    <p>© ' . date('Y') . ' EduScore. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>';
    }
    
    /**
     * Plain Text Email Template for School Confirmation
     */
    private function getSchoolConfirmationText($school_name, $admin_name, $school_id, $activation_code, $trialExpires) {
        return "Registration Successful!\n\nDear " . $admin_name . ",\n\nCongratulations! Your school " . $school_name . " has been successfully registered.\n\nRegistration Details:\nSchool ID: " . $school_id . "\nLicense Tier: Basic (Trial)\nTrial Period: 30 days (Expires: " . $trialExpires . ")\nActivation Code: " . $activation_code . "\n\nNext Steps:\n1. Login to your account\n2. Complete your school profile\n3. Add classrooms and subjects\n4. Register students and teachers\n5. Start using the system!\n\nBest regards,\nEduScore Team";
    }
}
?>