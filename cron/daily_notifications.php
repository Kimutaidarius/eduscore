<?php
// cron/daily_notifications.php
// This file should be set up as a daily cron job

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/system_notification_helper.php';
require_once __DIR__ . '/../includes/EmailHelper.php'; // Using your existing EmailHelper

class DailyNotificationScheduler {
    private $conn;
    private $notificationHelper;
    private $emailHelper;
    
    public function __construct($conn) {
        $this->conn = $conn;
        $this->notificationHelper = new SystemNotificationHelper($conn);
        $this->emailHelper = new EmailHelper(); // Using your existing EmailHelper
    }
    
    /**
     * Send welcome email to new users (should be called when user registers)
     */
    public function sendWelcomeEmail($userId, $email, $name, $teacherNumber = null, $password = null) {
        // If password is provided, use the existing welcome email method
        if ($password && $teacherNumber) {
            return $this->emailHelper->sendWelcomeEmail($email, $name, $teacherNumber, $password);
        }
        
        // Otherwise, send a custom welcome email
        $subject = "Welcome to EDUSCORE - Your Learning Journey Begins!";
        
        $message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9; }
                .header { background: linear-gradient(90deg, #1e3a8a 0%, #2563eb 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: white; padding: 30px; border-radius: 0 0 10px 10px; }
                .button { display: inline-block; padding: 12px 30px; background: #2563eb; color: white; text-decoration: none; border-radius: 5px; }
                .footer { text-align: center; padding: 20px; color: #6b7280; font-size: 12px; }
                .features { background: #f0f9ff; padding: 20px; border-radius: 8px; margin: 20px 0; }
                .feature-item { margin: 10px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🎉 Welcome to EDUSCORE!</h1>
                </div>
                <div class='content'>
                    <h2>Hello {$name},</h2>
                    <p>Welcome to EDUSCORE - your comprehensive school management system!</p>
                    
                    <div class='features'>
                        <h3>What you can do with EDUSCORE:</h3>
                        <div class='feature-item'>✓ Manage students, teachers, and classes efficiently</div>
                        <div class='feature-item'>✓ Track attendance and generate reports</div>
                        <div class='feature-item'>✓ Analyze exam results with detailed analytics</div>
                        <div class='feature-item'>✓ Send SMS notifications to parents</div>
                        <div class='feature-item'>✓ Generate professional report cards</div>
                        <div class='feature-item'>✓ Track fee payments and manage invoices</div>
                    </div>
                    
                    <p><strong>Your 14-day free trial has started today!</strong></p>
                    <p><strong>Trial Expiry Date:</strong> " . date('F j, Y', strtotime('+14 days')) . "</p>
                    
                    <p>To get started, click the button below:</p>
                    <p style='text-align: center;'>
                        <a href='https://edu-score.app/login' class='button'>🚀 Login to EDUSCORE</a>
                    </p>
                    
                    <p>Need help getting started? Check out our <a href='https://edu-score.app/guides'>Quick Start Guides</a>.</p>
                </div>
                <div class='footer'>
                    <p>&copy; 2026 EDUSCORE. All rights reserved.</p>
                    <p>If you didn't create an account, please ignore this email.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        // Use PHPMailer through your EmailHelper
        try {
            // Since your EmailHelper doesn't have a generic send method,
            // we'll create a custom method or use PHPMailer directly
            return $this->sendCustomEmail($email, $subject, $message);
        } catch (Exception $e) {
            error_log("Welcome email error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send custom email using PHPMailer
     */
    private function sendCustomEmail($to, $subject, $htmlMessage) {
        try {
            // Access PHPMailer instance through reflection or create new one
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            
            // Server settings
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USERNAME;
            $mail->Password   = SMTP_PASSWORD;
            $mail->SMTPSecure = SMTP_ENCRYPTION;
            $mail->Port       = SMTP_PORT;
            
            // Sender and recipient
            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $mail->addAddress($to);
            
            // Content
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = $subject;
            $mail->Body    = $htmlMessage;
            $mail->AltBody = strip_tags($htmlMessage);
            
            $mail->send();
            return true;
            
        } catch (Exception $e) {
            error_log("Custom email error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send trial expiration reminders to users whose trial is expiring soon
     */
    public function sendTrialExpirationReminders() {
        // Get schools where trial expires in 3 days
        $sql = "SELECT s.id as school_id, s.school_name, s.school_email, 
                       sub.expires_at, t.email, t.firstname, t.lastname, t.id as user_id
                FROM subscriptions sub
                JOIN tblschoolinfo s ON sub.school_id = s.id
                LEFT JOIN tblteachers t ON s.id = t.school_id AND (t.role = 'Super Admin' OR t.role_id IN (SELECT id FROM roles WHERE role_name = 'Super Admin'))
                WHERE sub.is_trial = 1 
                AND sub.status = 'active'
                AND sub.expires_at BETWEEN DATE_ADD(NOW(), INTERVAL 3 DAY) AND DATE_ADD(NOW(), INTERVAL 4 DAY)";
        
        $stmt = $this->conn->query($sql);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $count = 0;
        foreach ($users as $user) {
            $expiryDate = date('F j, Y', strtotime($user['expires_at']));
            $name = trim($user['firstname'] . ' ' . $user['lastname']);
            if (empty($name)) $name = 'School Administrator';
            
            $subject = "⏰ Your EDUSCORE Free Trial Expires in 3 Days";
            
            $message = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9; }
                    .header { background: linear-gradient(90deg, #1e3a8a 0%, #2563eb 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                    .content { background: white; padding: 30px; border-radius: 0 0 10px 10px; }
                    .warning { background: #fff3cd; border: 2px solid #ffeeba; color: #856404; padding: 20px; border-radius: 8px; margin: 20px 0; }
                    .warning h3 { margin-top: 0; color: #856404; }
                    .button { display: inline-block; padding: 14px 35px; background: #2563eb; color: white; text-decoration: none; border-radius: 5px; font-weight: bold; }
                    .button:hover { background: #1e3a8a; }
                    .pricing-box { background: #f8fafc; padding: 20px; border-radius: 8px; margin: 20px 0; border: 1px solid #e2e8f0; }
                    .plan { border-bottom: 1px solid #e2e8f0; padding: 15px 0; }
                    .plan:last-child { border-bottom: none; }
                    .plan-name { font-weight: bold; color: #1e3a8a; font-size: 18px; }
                    .plan-price { color: #059669; font-weight: bold; }
                    .footer { text-align: center; padding: 20px; color: #6b7280; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>⏰ Trial Expiration Notice</h1>
                    </div>
                    <div class='content'>
                        <h2>Hello {$name},</h2>
                        
                        <div class='warning'>
                            <h3>⚠️ Your free trial is ending soon!</h3>
                            <p><strong>School:</strong> {$user['school_name']}</p>
                            <p><strong>Trial Expiry Date:</strong> {$expiryDate}</p>
                            <p><strong>Days Remaining:</strong> 3 days</p>
                        </div>
                        
                        <p>To continue enjoying all EDUSCORE features without interruption, please subscribe to one of our plans:</p>
                        
                        <div class='pricing-box'>
                            <div class='plan'>
                                <div class='plan-name'>📘 Starter Plan</div>
                                <div class='plan-price'>KES 3,000/month</div>
                                <div>✓ Up to 200 students</div>
                                <div>✓ Basic analytics & reports</div>
                                <div>✓ Email support</div>
                            </div>
                            
                            <div class='plan'>
                                <div class='plan-name'>📊 Standard Plan</div>
                                <div class='plan-price'>KES 6,000/month</div>
                                <div>✓ Up to 500 students</div>
                                <div>✓ Advanced analytics</div>
                                <div>✓ SMS credits included</div>
                                <div>✓ Priority support</div>
                            </div>
                            
                            <div class='plan'>
                                <div class='plan-name'>🏫 Enterprise Plan</div>
                                <div class='plan-price'>KES 12,000/month</div>
                                <div>✓ Up to 2000 students</div>
                                <div>✓ Full feature access</div>
                                <div>✓ API access</div>
                                <div>✓ Dedicated account manager</div>
                            </div>
                        </div>
                        
                        <p style='text-align: center; margin: 30px 0;'>
                            <a href='https://edu-score.app/subscription?school={$user['school_id']}' class='button'>💳 Choose Your Plan</a>
                        </p>
                        
                        <p style='text-align: center;'>
                            <a href='https://edu-score.app/login' style='color: #2563eb;'>Login to EDUSCORE</a> | 
                            <a href='https://edu-score.app/pricing' style='color: #2563eb;'>View Detailed Pricing</a>
                        </p>
                        
                        <p><strong>Special Offer:</strong> Upgrade to an annual plan and get <strong>2 months FREE</strong>!</p>
                    </div>
                    <div class='footer'>
                        <p>&copy; 2026 EDUSCORE. All rights reserved.</p>
                        <p>Need help? Contact us at support@edu-score.app</p>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            $emailTo = !empty($user['email']) ? $user['email'] : $user['school_email'];
            if (!empty($emailTo)) {
                $result = $this->sendCustomEmail($emailTo, $subject, $message);
                
                // Log the email
                $this->logEmail($emailTo, 'trial_expiration_reminder', $subject, $message, $result, $user['user_id']);
                
                if ($result) $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Send daily reminders to inactive users
     */
    public function sendInactiveUserReminders() {
        // Get users who haven't logged in for 7+ days
        $sql = "SELECT DISTINCT t.id, t.email, t.firstname, t.lastname, t.school_id, s.school_name,
                       MAX(l.login_time) as last_login
                FROM tblteachers t
                JOIN tblschoolinfo s ON t.school_id = s.id
                LEFT JOIN login_logs l ON t.id = l.user_id AND l.status = 'success'
                WHERE t.status = 'Active'
                AND t.email IS NOT NULL 
                AND t.email != ''
                GROUP BY t.id
                HAVING last_login IS NULL OR last_login < DATE_SUB(NOW(), INTERVAL 7 DAY)
                ORDER BY last_login ASC
                LIMIT 100"; // Limit to 100 per day to avoid overwhelming
        
        $stmt = $this->conn->query($sql);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $count = 0;
        foreach ($users as $user) {
            $lastLoginText = $user['last_login'] ? date('F j, Y', strtotime($user['last_login'])) : 'never';
            $daysInactive = $user['last_login'] ? floor((time() - strtotime($user['last_login'])) / 86400) : 'several';
            
            $subject = "👋 We Miss You at EDUSCORE!";
            
            $message = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9; }
                    .header { background: linear-gradient(90deg, #1e3a8a 0%, #2563eb 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                    .content { background: white; padding: 30px; border-radius: 0 0 10px 10px; }
                    .button { display: inline-block; padding: 14px 35px; background: #2563eb; color: white; text-decoration: none; border-radius: 5px; font-weight: bold; }
                    .button:hover { background: #1e3a8a; }
                    .feature-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin: 25px 0; }
                    .feature-item { background: #f8fafc; padding: 20px; border-radius: 8px; border-left: 4px solid #2563eb; }
                    .feature-item strong { color: #1e3a8a; display: block; margin-bottom: 8px; font-size: 16px; }
                    .feature-item p { margin: 0; color: #4b5563; font-size: 14px; }
                    .stats-box { background: #e0f2fe; padding: 15px; border-radius: 8px; margin: 20px 0; text-align: center; }
                    .stats-number { font-size: 24px; font-weight: bold; color: #0369a1; }
                    .footer { text-align: center; padding: 20px; color: #6b7280; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>👋 We Miss You at EDUSCORE!</h1>
                    </div>
                    <div class='content'>
                        <h2>Hello " . htmlspecialchars($user['firstname'] . ' ' . $user['lastname']) . ",</h2>
                        
                        <div class='stats-box'>
                            <p>It's been <span class='stats-number'>{$daysInactive} days</span> since your last login</p>
                            <p><strong>Last Login:</strong> {$lastLoginText}</p>
                            <p><strong>School:</strong> " . htmlspecialchars($user['school_name']) . "</p>
                        </div>
                        
                        <p>Here's what you've been missing:</p>
                        
                        <div class='feature-grid'>
                            <div class='feature-item'>
                                <strong>📊 Exam Analysis</strong>
                                <p>Track student performance with detailed analytics, identify weak areas, and improve results</p>
                            </div>
                            <div class='feature-item'>
                                <strong>📱 SMS Notifications</strong>
                                <p>Send instant updates to parents about attendance, fees, and exam results</p>
                            </div>
                            <div class='feature-item'>
                                <strong>📈 Report Cards</strong>
                                <p>Generate professional, CBC-compliant report cards automatically with just one click</p>
                            </div>
                            <div class='feature-item'>
                                <strong>👥 Student Management</strong>
                                <p>Manage all student data, track progress, and maintain digital records</p>
                            </div>
                            <div class='feature-item'>
                                <strong>💰 Fee Management</strong>
                                <p>Track payments, generate invoices, and send fee reminders automatically</p>
                            </div>
                            <div class='feature-item'>
                                <strong>📅 Attendance Tracking</strong>
                                <p>Easy attendance marking with daily, weekly, and monthly reports</p>
                            </div>
                        </div>
                        
                        <p><strong>🎁 Special Offer:</strong> Upgrade to our annual plan before March 31st and get <strong>2 months absolutely FREE</strong>!</p>
                        
                        <p style='text-align: center; margin: 30px 0;'>
                            <a href='https://edu-score.app/login' class='button'>🚀 Login Now</a>
                        </p>
                        
                        <p style='text-align: center; margin-top: 20px;'>
                            <a href='https://edu-score.app/features' style='color: #2563eb;'>Explore All Features</a> | 
                            <a href='https://edu-score.app/pricing' style='color: #2563eb;'>View Pricing</a> |
                            <a href='https://edu-score.app/tutorials' style='color: #2563eb;'>Watch Tutorials</a>
                        </p>
                    </div>
                    <div class='footer'>
                        <p>&copy; 2026 EDUSCORE. All rights reserved.</p>
                        <p>Your school: " . htmlspecialchars($user['school_name']) . "</p>
                        <p>Questions? Contact us at support@edu-score.app</p>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            if (!empty($user['email'])) {
                $result = $this->sendCustomEmail($user['email'], $subject, $message);
                
                // Log the email
                $this->logEmail($user['email'], 'inactive_reminder', $subject, $message, $result, $user['id']);
                
                if ($result) $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Send promotional campaign to all active users
     */
    public function sendPromotionalCampaign($campaignType = 'general') {
        // Get all active users
        $sql = "SELECT t.id, t.email, t.firstname, t.lastname, t.school_id, s.school_name
                FROM tblteachers t
                JOIN tblschoolinfo s ON t.school_id = s.id
                WHERE t.status = 'Active'
                AND t.email IS NOT NULL 
                AND t.email != ''
                ORDER BY t.id";
        
        $stmt = $this->conn->query($sql);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $subject = "🚀 Exciting Updates from EDUSCORE!";
        
        $message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9; }
                .header { background: linear-gradient(90deg, #1e3a8a 0%, #2563eb 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: white; padding: 30px; border-radius: 0 0 10px 10px; }
                .button { display: inline-block; padding: 14px 35px; background: #2563eb; color: white; text-decoration: none; border-radius: 5px; font-weight: bold; }
                .highlight { background: #fef3c7; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #f59e0b; }
                .footer { text-align: center; padding: 20px; color: #6b7280; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🚀 Exciting Updates from EDUSCORE!</h1>
                </div>
                <div class='content'>
                    <h2>Hello " . htmlspecialchars($user['firstname'] . ' ' . $user['lastname']) . ",</h2>
                    
                    <p>We're constantly working to make EDUSCORE better for you. Here are our latest updates:</p>
                    
                    <div class='highlight'>
                        <h3>✨ New Features Added:</h3>
                        <ul>
                            <li><strong>Automated Report Cards:</strong> Generate CBC-compliant report cards with one click</li>
                            <li><strong>Bulk SMS:</strong> Send updates to hundreds of parents instantly</li>
                            <li><strong>Fee Management:</strong> Track payments and send automatic reminders</li>
                            <li><strong>Advanced Analytics:</strong> Deeper insights into student performance</li>
                        </ul>
                    </div>
                    
                    <p><strong>🎯 Limited Time Offer:</strong> Get 20% off on annual subscriptions! Use code: <strong>EDUSCORE20</strong></p>
                    
                    <p style='text-align: center; margin: 30px 0;'>
                        <a href='https://edu-score.app/login' class='button'>Login to Explore</a>
                    </p>
                    
                    <p style='text-align: center;'>
                        <a href='https://edu-score.app/blog' style='color: #2563eb;'>Read Our Blog</a> | 
                        <a href='https://edu-score.app/updates' style='color: #2563eb;'>See All Updates</a>
                    </p>
                </div>
                <div class='footer'>
                    <p>&copy; 2026 EDUSCORE. All rights reserved.</p>
                    <p>Follow us on social media for more updates!</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $count = 0;
        foreach ($users as $user) {
            // Personalize the message for each user
            $personalizedMessage = str_replace(
                htmlspecialchars($user['firstname'] . ' ' . $user['lastname']),
                htmlspecialchars($user['firstname'] . ' ' . $user['lastname']),
                $message
            );
            
            $result = $this->sendCustomEmail($user['email'], $subject, $personalizedMessage);
            
            if ($result) $count++;
            
            // Small delay to avoid rate limiting
            usleep(200000); // 0.2 seconds
        }
        
        return $count;
    }
    
    /**
     * Log email notifications
     */
    private function logEmail($email, $type, $subject, $message, $status, $userId = null) {
        try {
            $sql = "INSERT INTO email_notification_logs 
                    (user_id, email, notification_type, subject, message, status, sent_at) 
                    VALUES (:user_id, :email, :type, :subject, :message, :status, NOW())";
            
            $stmt = $this->conn->prepare($sql);
            return $stmt->execute([
                ':user_id' => $userId,
                ':email' => $email,
                ':type' => $type,
                ':subject' => $subject,
                ':message' => $message,
                ':status' => $status ? 'sent' : 'failed'
            ]);
        } catch (Exception $e) {
            error_log("Failed to log email: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Initialize system notifications
     */
    public function initializeSystemNotifications() {
        // Welcome notification for all users
        $this->notificationHelper->createSystemNotification(
            '🎉 Welcome to EDUSCORE!',
            'Welcome to EDUSCORE - Your comprehensive school management system. Get started by exploring our features! Check out our quick start guide in the Help section.',
            'system',
            'all',
            'high',
            date('Y-m-d H:i:s', strtotime('+30 days'))
        );
        
        // Trial reminder notification
        $this->notificationHelper->createSystemNotification(
            '⏰ 14-Day Free Trial',
            'You have 14 days of free trial remaining. Upgrade to continue enjoying all features and get 2 months FREE on annual plans!',
            'trial_reminder',
            'all',
            'medium',
            date('Y-m-d H:i:s', strtotime('+30 days'))
        );
        
        // Feature announcement
        $this->notificationHelper->createSystemNotification(
            '✨ New Feature: Automated Report Cards',
            'We\'ve added automated CBC-compliant report card generation! Generate professional report cards for your students with just one click.',
            'feature',
            'all',
            'medium',
            date('Y-m-d H:i:s', strtotime('+60 days'))
        );
    }
    
    /**
     * Send 1-day before trial expiry reminder
     */
    public function sendLastDayTrialReminders() {
        // Get schools where trial expires tomorrow
        $sql = "SELECT s.id as school_id, s.school_name, s.school_email, 
                       sub.expires_at, t.email, t.firstname, t.lastname, t.id as user_id
                FROM subscriptions sub
                JOIN tblschoolinfo s ON sub.school_id = s.id
                LEFT JOIN tblteachers t ON s.id = t.school_id AND (t.role = 'Super Admin' OR t.role_id IN (SELECT id FROM roles WHERE role_name = 'Super Admin'))
                WHERE sub.is_trial = 1 
                AND sub.status = 'active'
                AND sub.expires_at BETWEEN DATE_ADD(NOW(), INTERVAL 1 DAY) AND DATE_ADD(NOW(), INTERVAL 2 DAY)";
        
        $stmt = $this->conn->query($sql);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $count = 0;
        foreach ($users as $user) {
            $expiryDate = date('F j, Y', strtotime($user['expires_at']));
            $name = trim($user['firstname'] . ' ' . $user['lastname']);
            
            $subject = "⚠️ LAST DAY: Your EDUSCORE Trial Expires Tomorrow!";
            
            $message = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9; }
                    .header { background: linear-gradient(90deg, #dc2626 0%, #ef4444 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                    .content { background: white; padding: 30px; border-radius: 0 0 10px 10px; }
                    .urgent { background: #fee2e2; border: 2px solid #ef4444; color: #991b1b; padding: 20px; border-radius: 8px; margin: 20px 0; text-align: center; }
                    .button { display: inline-block; padding: 14px 35px; background: #2563eb; color: white; text-decoration: none; border-radius: 5px; font-weight: bold; }
                    .footer { text-align: center; padding: 20px; color: #6b7280; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>⚠️ URGENT: Trial Expires Tomorrow!</h1>
                    </div>
                    <div class='content'>
                        <h2>Hello {$name},</h2>
                        
                        <div class='urgent'>
                            <h3>Your free trial ends TOMORROW!</h3>
                            <p><strong>Expiry Date:</strong> {$expiryDate}</p>
                            <p style='font-size: 18px;'>Don't lose access to your data and features!</p>
                        </div>
                        
                        <p style='text-align: center; margin: 30px 0;'>
                            <a href='https://edu-score.app/subscription?school={$user['school_id']}&urgent=1' class='button'>⚡ RENEW NOW ⚡</a>
                        </p>
                        
                        <p style='text-align: center;'>
                            <a href='https://edu-score.app/login'>Login to continue using the system</a>
                        </p>
                    </div>
                    <div class='footer'>
                        <p>&copy; 2026 EDUSCORE. All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            $emailTo = !empty($user['email']) ? $user['email'] : $user['school_email'];
            if (!empty($emailTo)) {
                $result = $this->sendCustomEmail($emailTo, $subject, $message);
                $this->logEmail($emailTo, 'trial_last_day', $subject, $message, $result, $user['user_id']);
                if ($result) $count++;
            }
        }
        
        return $count;
    }
}

// Run the scheduler if this file is executed directly
if (basename($_SERVER['PHP_SELF']) == 'daily_notifications.php') {
    echo "[" . date('Y-m-d H:i:s') . "] Starting daily notification tasks...\n";
    
    try {
        $scheduler = new DailyNotificationScheduler($db);
        
        // Send trial expiration reminders (3 days before)
        $trialReminders = $scheduler->sendTrialExpirationReminders();
        echo "✓ Sent {$trialReminders} trial expiration reminders (3-day)\n";
        
        // Send last day reminders (1 day before)
        $lastDayReminders = $scheduler->sendLastDayTrialReminders();
        echo "✓ Sent {$lastDayReminders} last day trial reminders\n";
        
        // Send inactive user reminders
        $inactiveReminders = $scheduler->sendInactiveUserReminders();
        echo "✓ Sent {$inactiveReminders} inactive user reminders\n";
        
        // Send promotional campaign (once a week - you can control this via cron schedule)
        // Uncomment if you want to run weekly
        // if (date('N') == 1) { // Monday
        //     $promotional = $scheduler->sendPromotionalCampaign();
        //     echo "✓ Sent {$promotional} promotional emails\n";
        // }
        
        echo "[" . date('Y-m-d H:i:s') . "] Daily notification tasks completed successfully.\n";
        
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
        error_log("Daily notification cron error: " . $e->getMessage());
    }
}
?>