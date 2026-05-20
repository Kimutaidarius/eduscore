<?php
// cron/DailyNotificationScheduler.php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/SystemNotificationHelper.php';
require_once __DIR__ . '/../includes/EmailHelper.php';

class DailyNotificationScheduler {
    private $conn;
    private $notificationHelper;
    private $emailHelper;
    
    public function __construct($conn) {
        $this->conn = $conn;
        $this->notificationHelper = new SystemNotificationHelper($conn);
        $this->emailHelper = new EmailHelper();
    }
    
    /**
     * Send welcome email to new users
     */
    public function sendWelcomeEmail($userId, $email, $name, $teacherNumber = null, $password = null) {
        if ($password && $teacherNumber) {
            return $this->emailHelper->sendWelcomeEmail($email, $name, $teacherNumber, $password);
        }
        
        $subject = "🎉 Welcome to EDUSCORE - Your Learning Journey Begins!";
        
        $message = $this->getWelcomeEmailHTML($name);
        
        return $this->sendCustomEmail($email, $subject, $message, $userId, 'welcome');
    }
    
    /**
     * Send trial expiration reminders
     */
    public function sendTrialExpirationReminders() {
        // Get schools where trial expires in 3 days
        $sql = "SELECT s.id as school_id, s.school_name, s.school_email, 
                       sub.expires_at, t.id as user_id, t.email, t.firstname, t.lastname
                FROM subscriptions sub
                JOIN tblschoolinfo s ON sub.school_id = s.id
                LEFT JOIN tblteachers t ON s.id = t.school_id AND (t.role = 'Super Admin' OR t.role LIKE '%Admin%')
                WHERE sub.is_trial = 1 
                AND sub.status = 'active'
                AND sub.expires_at BETWEEN DATE_ADD(NOW(), INTERVAL 3 DAY) AND DATE_ADD(NOW(), INTERVAL 4 DAY)
                GROUP BY s.id";
        
        $stmt = $this->conn->query($sql);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $count = 0;
        foreach ($users as $user) {
            $expiryDate = date('F j, Y', strtotime($user['expires_at']));
            $name = trim($user['firstname'] . ' ' . $user['lastname']);
            if (empty($name)) $name = 'School Administrator';
            
            $subject = "⏰ Your EDUSCORE Free Trial Expires in 3 Days";
            $message = $this->getTrialExpiryEmailHTML($name, $user['school_name'], $expiryDate, 3);
            
            $emailTo = !empty($user['email']) ? $user['email'] : $user['school_email'];
            if (!empty($emailTo)) {
                $result = $this->sendCustomEmail($emailTo, $subject, $message, $user['user_id'], 'trial_3day', $user['school_id']);
                if ($result) $count++;
            }
        }
        
        // Get schools where trial expires tomorrow
        $sql = "SELECT s.id as school_id, s.school_name, s.school_email, 
                       sub.expires_at, t.id as user_id, t.email, t.firstname, t.lastname
                FROM subscriptions sub
                JOIN tblschoolinfo s ON sub.school_id = s.id
                LEFT JOIN tblteachers t ON s.id = t.school_id AND (t.role = 'Super Admin' OR t.role LIKE '%Admin%')
                WHERE sub.is_trial = 1 
                AND sub.status = 'active'
                AND sub.expires_at BETWEEN DATE_ADD(NOW(), INTERVAL 1 DAY) AND DATE_ADD(NOW(), INTERVAL 2 DAY)
                GROUP BY s.id";
        
        $stmt = $this->conn->query($sql);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($users as $user) {
            $expiryDate = date('F j, Y', strtotime($user['expires_at']));
            $name = trim($user['firstname'] . ' ' . $user['lastname']);
            
            $subject = "⚠️ LAST DAY: Your EDUSCORE Trial Expires Tomorrow!";
            $message = $this->getTrialExpiryEmailHTML($name, $user['school_name'], $expiryDate, 1, true);
            
            $emailTo = !empty($user['email']) ? $user['email'] : $user['school_email'];
            if (!empty($emailTo)) {
                $result = $this->sendCustomEmail($emailTo, $subject, $message, $user['user_id'], 'trial_1day', $user['school_id']);
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
                LIMIT 100";
        
        $stmt = $this->conn->query($sql);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $count = 0;
        foreach ($users as $user) {
            $lastLoginText = $user['last_login'] ? date('F j, Y', strtotime($user['last_login'])) : 'never';
            $daysInactive = $user['last_login'] ? floor((time() - strtotime($user['last_login'])) / 86400) : 'several';
            
            $subject = "👋 We Miss You at EDUSCORE!";
            $message = $this->getInactiveUserEmailHTML($user, $lastLoginText, $daysInactive);
            
            if (!empty($user['email'])) {
                $result = $this->sendCustomEmail($user['email'], $subject, $message, $user['id'], 'inactive_reminder', $user['school_id']);
                if ($result) $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Check and send maintenance notifications
     */
    public function checkMaintenanceMode() {
        $maintenance = $this->notificationHelper->isMaintenanceModeActive();
        
        if ($maintenance) {
            // Get all active users to notify about maintenance
            $sql = "SELECT t.id, t.email, t.firstname, t.lastname
                    FROM tblteachers t
                    WHERE t.status = 'Active'
                    AND t.email IS NOT NULL";
            
            $stmt = $this->conn->query($sql);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $subject = "🔧 System Maintenance Notification";
            $maintenanceEnd = date('F j, Y g:i A', strtotime($maintenance['end_date']));
            
            foreach ($users as $user) {
                $message = $this->getMaintenanceEmailHTML($user['firstname'], $maintenance['message'], $maintenanceEnd);
                $this->sendCustomEmail($user['email'], $subject, $message, $user['id'], 'maintenance');
                
                // Small delay to avoid rate limiting
                usleep(100000);
            }
        }
    }
    
    /**
     * Send promotional campaign
     */
    public function sendPromotionalCampaign($campaignType = 'general') {
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
        $count = 0;
        
        foreach ($users as $user) {
            $message = $this->getPromotionalEmailHTML($user);
            $result = $this->sendCustomEmail($user['email'], $subject, $message, $user['id'], 'promotional', $user['school_id']);
            if ($result) $count++;
            usleep(200000);
        }
        
        return $count;
    }
    
    /**
     * Send custom email using EmailHelper
     */
    private function sendCustomEmail($to, $subject, $htmlMessage, $userId = null, $type = 'general', $schoolId = null) {
        try {
            $result = $this->emailHelper->sendEmailSMTP($to, $subject, $htmlMessage);
            $this->logEmail($to, $type, $subject, $htmlMessage, true, $userId, $schoolId);
            return true;
        } catch (Exception $e) {
            error_log("Email error: " . $e->getMessage());
            $this->logEmail($to, $type, $subject, $htmlMessage, false, $userId, $schoolId, $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log email notifications
     */
    private function logEmail($email, $type, $subject, $message, $status, $userId = null, $schoolId = null, $error = null) {
        try {
            $sql = "INSERT INTO email_notification_logs 
                    (user_id, school_id, email, notification_type, subject, message, status, error_message, sent_at) 
                    VALUES (:user_id, :school_id, :email, :type, :subject, :message, :status, :error, NOW())";
            
            $stmt = $this->conn->prepare($sql);
            return $stmt->execute([
                ':user_id' => $userId,
                ':school_id' => $schoolId,
                ':email' => $email,
                ':type' => $type,
                ':subject' => $subject,
                ':message' => $message,
                ':status' => $status ? 'sent' : 'failed',
                ':error' => $error
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
        // Welcome notification
        $this->notificationHelper->createNotification(
            '🎉 Welcome to EDUSCORE!',
            'Welcome to EDUSCORE - Your comprehensive school management system. Get started by exploring our features! Check out our quick start guide in the Help section.',
            'system',
            'all',
            'high',
            date('Y-m-d H:i:s', strtotime('+30 days'))
        );
        
        // Trial reminder
        $this->notificationHelper->createNotification(
            '⏰ 14-Day Free Trial',
            'You have 14 days of free trial remaining. Upgrade to continue enjoying all features and get 2 months FREE on annual plans!',
            'trial_reminder',
            'trial_users',
            'medium',
            date('Y-m-d H:i:s', strtotime('+30 days'))
        );
        
        // Feature announcement
        $this->notificationHelper->createNotification(
            '✨ New Feature: Automated Report Cards',
            'We\'ve added automated CBC-compliant report card generation! Generate professional report cards for your students with just one click.',
            'feature',
            'all',
            'medium',
            date('Y-m-d H:i:s', strtotime('+60 days'))
        );
    }
    
    // Email template methods
    private function getWelcomeEmailHTML($name) {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9; }
                .header { background: linear-gradient(90deg, #1e3a8a 0%, #2563eb 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: white; padding: 30px; border-radius: 0 0 10px 10px; }
                .button { display: inline-block; padding: 12px 30px; background: #2563eb; color: white; text-decoration: none; border-radius: 5px; font-weight: bold; }
                .features { background: #f0f9ff; padding: 20px; border-radius: 8px; margin: 20px 0; }
                .footer { text-align: center; padding: 20px; color: #6b7280; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>🎉 Welcome to EDUSCORE!</h1>
                </div>
                <div class="content">
                    <h2>Hello ' . htmlspecialchars($name) . ',</h2>
                    <p>Welcome to EDUSCORE - your comprehensive school management system!</p>
                    
                    <div class="features">
                        <h3>What you can do with EDUSCORE:</h3>
                        <p>✓ Manage students, teachers, and classes efficiently</p>
                        <p>✓ Track attendance and generate reports</p>
                        <p>✓ Analyze exam results with detailed analytics</p>
                        <p>✓ Send SMS notifications to parents</p>
                        <p>✓ Generate professional report cards</p>
                        <p>✓ Track fee payments and manage invoices</p>
                    </div>
                    
                    <p><strong>Your 14-day free trial has started today!</strong></p>
                    <p><strong>Trial Expiry Date:</strong> ' . date('F j, Y', strtotime('+14 days')) . '</p>
                    
                    <p style="text-align: center; margin: 30px 0;">
                        <a href="https://edu-score.app/login" class="button">🚀 Login to EDUSCORE</a>
                    </p>
                    
                    <p style="text-align: center;">
                        <a href="https://edu-score.app/guides" style="color: #2563eb;">Quick Start Guides</a>
                    </p>
                </div>
                <div class="footer">
                    <p>&copy; ' . date('Y') . ' EDUSCORE. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ';
    }
    
    private function getTrialExpiryEmailHTML($name, $schoolName, $expiryDate, $daysLeft, $urgent = false) {
        $urgentClass = $urgent ? 'urgent' : 'warning';
        $buttonText = $urgent ? '⚡ RENEW NOW ⚡' : 'Choose Your Plan';
        
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9; }
                .header { background: linear-gradient(90deg, #1e3a8a 0%, #2563eb 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .urgent { background: #fee2e2; border: 2px solid #ef4444; color: #991b1b; padding: 20px; border-radius: 8px; margin: 20px 0; }
                .warning { background: #fff3cd; border: 2px solid #ffeeba; color: #856404; padding: 20px; border-radius: 8px; margin: 20px 0; }
                .content { background: white; padding: 30px; border-radius: 0 0 10px 10px; }
                .button { display: inline-block; padding: 14px 35px; background: #2563eb; color: white; text-decoration: none; border-radius: 5px; font-weight: bold; }
                .footer { text-align: center; padding: 20px; color: #6b7280; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>' . ($urgent ? '⚠️ URGENT: Trial Expiring!' : '⏰ Trial Expiration Notice') . '</h1>
                </div>
                <div class="content">
                    <h2>Hello ' . htmlspecialchars($name) . ',</h2>
                    
                    <div class="' . $urgentClass . '">
                        <h3>Your free trial ' . ($urgent ? 'ends TOMORROW!' : 'expires in ' . $daysLeft . ' days') . '</h3>
                        <p><strong>School:</strong> ' . htmlspecialchars($schoolName) . '</p>
                        <p><strong>Expiry Date:</strong> ' . $expiryDate . '</p>
                        <p><strong>Days Remaining:</strong> ' . $daysLeft . '</p>
                    </div>
                    
                    <p>To continue enjoying all EDUSCORE features, please subscribe to one of our plans:</p>
                    
                    <p style="text-align: center; margin: 30px 0;">
                        <a href="https://edu-score.app/subscription" class="button">' . $buttonText . '</a>
                    </p>
                    
                    <p style="text-align: center;">
                        <a href="https://edu-score.app/login">Login to EDUSCORE</a> | 
                        <a href="https://edu-score.app/pricing">View Pricing</a>
                    </p>
                    
                    <p><strong>Special Offer:</strong> Upgrade to annual plan and get <strong>2 months FREE</strong>!</p>
                </div>
                <div class="footer">
                    <p>&copy; ' . date('Y') . ' EDUSCORE. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ';
    }
    
    private function getInactiveUserEmailHTML($user, $lastLoginText, $daysInactive) {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9; }
                .header { background: linear-gradient(90deg, #1e3a8a 0%, #2563eb 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: white; padding: 30px; border-radius: 0 0 10px 10px; }
                .button { display: inline-block; padding: 14px 35px; background: #2563eb; color: white; text-decoration: none; border-radius: 5px; font-weight: bold; }
                .stats-box { background: #e0f2fe; padding: 15px; border-radius: 8px; margin: 20px 0; text-align: center; }
                .footer { text-align: center; padding: 20px; color: #6b7280; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>👋 We Miss You!</h1>
                </div>
                <div class="content">
                    <h2>Hello ' . htmlspecialchars($user['firstname'] . ' ' . $user['lastname']) . ',</h2>
                    
                    <div class="stats-box">
                        <p>It\'s been <strong>' . $daysInactive . ' days</strong> since your last login</p>
                        <p><strong>Last Login:</strong> ' . $lastLoginText . '</p>
                        <p><strong>School:</strong> ' . htmlspecialchars($user['school_name']) . '</p>
                    </div>
                    
                    <p>Here\'s what you\'ve been missing:</p>
                    <ul>
                        <li><strong>📊 Exam Analysis</strong> - Track student performance with detailed analytics</li>
                        <li><strong>📱 SMS Notifications</strong> - Send instant updates to parents</li>
                        <li><strong>📈 Report Cards</strong> - Generate professional report cards automatically</li>
                        <li><strong>💰 Fee Management</strong> - Track payments and send reminders</li>
                    </ul>
                    
                    <p><strong>🎁 Special Offer:</strong> Get 20% off on annual subscriptions! Use code: <strong>WELCOMEBACK20</strong></p>
                    
                    <p style="text-align: center; margin: 30px 0;">
                        <a href="https://edu-score.app/login" class="button">🚀 Login Now</a>
                    </p>
                </div>
                <div class="footer">
                    <p>&copy; ' . date('Y') . ' EDUSCORE. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ';
    }
    
    private function getMaintenanceEmailHTML($name, $message, $endTime) {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9; }
                .header { background: linear-gradient(90deg, #dc2626 0%, #ef4444 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: white; padding: 30px; border-radius: 0 0 10px 10px; }
                .maintenance-box { background: #fee2e2; border: 1px solid #ef4444; padding: 20px; border-radius: 8px; margin: 20px 0; }
                .footer { text-align: center; padding: 20px; color: #6b7280; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>🔧 System Maintenance</h1>
                </div>
                <div class="content">
                    <h2>Hello ' . htmlspecialchars($name) . ',</h2>
                    
                    <div class="maintenance-box">
                        <h3>Maintenance Notification</h3>
                        <p>' . nl2br(htmlspecialchars($message)) . '</p>
                        <p><strong>Expected completion:</strong> ' . $endTime . '</p>
                    </div>
                    
                    <p>During this time, some features may be temporarily unavailable. We apologize for any inconvenience.</p>
                    
                    <p style="text-align: center;">
                        <a href="https://edu-score.app/status" style="color: #2563eb;">Check System Status</a>
                    </p>
                </div>
                <div class="footer">
                    <p>&copy; ' . date('Y') . ' EDUSCORE. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ';
    }
    
    private function getPromotionalEmailHTML($user) {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9; }
                .header { background: linear-gradient(90deg, #059669 0%, #10b981 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: white; padding: 30px; border-radius: 0 0 10px 10px; }
                .button { display: inline-block; padding: 12px 30px; background: #059669; color: white; text-decoration: none; border-radius: 5px; font-weight: bold; }
                .highlight { background: #fef3c7; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #f59e0b; }
                .footer { text-align: center; padding: 20px; color: #6b7280; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>🚀 Exciting Updates!</h1>
                </div>
                <div class="content">
                    <h2>Hello ' . htmlspecialchars($user['firstname'] . ' ' . $user['lastname']) . ',</h2>
                    
                    <p>We\'re constantly working to make EDUSCORE better for you. Here are our latest updates:</p>
                    
                    <div class="highlight">
                        <h3>✨ New Features Added:</h3>
                        <ul>
                            <li><strong>Automated Report Cards:</strong> Generate CBC-compliant report cards with one click</li>
                            <li><strong>Bulk SMS:</strong> Send updates to hundreds of parents instantly</li>
                            <li><strong>Fee Management:</strong> Track payments and send automatic reminders</li>
                            <li><strong>Advanced Analytics:</strong> Deeper insights into student performance</li>
                        </ul>
                    </div>
                    
                    <p><strong>🎯 Limited Time Offer:</strong> Get 20% off on annual subscriptions! Use code: <strong>EDUSCORE20</strong></p>
                    
                    <p style="text-align: center; margin: 30px 0;">
                        <a href="https://edu-score.app/login" class="button">Login to Explore</a>
                    </p>
                    
                    <p style="text-align: center;">
                        <a href="https://edu-score.app/blog" style="color: #059669;">Read Our Blog</a>
                    </p>
                </div>
                <div class="footer">
                    <p>&copy; ' . date('Y') . ' EDUSCORE. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ';
    }
}

// Run the scheduler if this file is executed directly
if (basename($_SERVER['PHP_SELF']) == 'DailyNotificationScheduler.php') {
    echo "[" . date('Y-m-d H:i:s') . "] Starting daily notification tasks...\n";
    
    try {
        $scheduler = new DailyNotificationScheduler($db);
        
        // Send trial expiration reminders
        $trialReminders = $scheduler->sendTrialExpirationReminders();
        echo "✓ Sent {$trialReminders} trial expiration reminders\n";
        
        // Send inactive user reminders
        $inactiveReminders = $scheduler->sendInactiveUserReminders();
        echo "✓ Sent {$inactiveReminders} inactive user reminders\n";
        
        // Check maintenance mode
        $scheduler->checkMaintenanceMode();
        echo "✓ Checked maintenance mode\n";
        
        // Send promotional campaign (once a week)
        if (date('N') == 1) { // Monday
            $promotional = $scheduler->sendPromotionalCampaign();
            echo "✓ Sent {$promotional} promotional emails\n";
        }
        
        echo "[" . date('Y-m-d H:i:s') . "] Daily notification tasks completed.\n";
        
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
        error_log("Daily notification cron error: " . $e->getMessage());
    }
}
?>