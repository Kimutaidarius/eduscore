<?php
/**
 * Student Usage Monitor
 * Runs daily to track student counts and generate invoices
 * CRON: 0 2 * * * php /path/to/cron/student_usage_monitor.php
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';

class StudentUsageMonitor {
    private $conn;
    private $extra_fee_per_student;
    private $grace_period_days;
    
    public function __construct() {
        $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($this->conn->connect_error) {
            $this->log("Connection failed: " . $this->conn->connect_error);
            die("Connection failed");
        }
        
        // Load settings
        $this->loadSettings();
    }
    
    private function loadSettings() {
        $settings = [];
        $result = $this->conn->query("SELECT setting_name, setting_value FROM system_settings");
        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_name']] = $row['setting_value'];
        }
        
        $this->extra_fee_per_student = $settings['extra_student_fee'] ?? 50;
        $this->grace_period_days = $settings['grace_period_days'] ?? 7;
    }
    
    private function log($message) {
        echo date('Y-m-d H:i:s') . " - " . $message . "\n";
        file_put_contents(__DIR__ . '/logs/usage_monitor.log', 
            date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
    }
    
    public function monitorAllSchools() {
        $this->log("Starting student usage monitoring...");
        
        // Get all active schools
        $query = "SELECT s.*, sub.plan_id, sub.expires_at, sub.status as sub_status 
                  FROM tblschoolinfo s 
                  LEFT JOIN subscriptions sub ON s.id = sub.school_id AND sub.status = 'active'
                  WHERE s.is_activated = 1";
        
        $result = $this->conn->query($query);
        
        while ($school = $result->fetch_assoc()) {
            $this->monitorSchool($school);
        }
        
        $this->log("Student usage monitoring completed.");
    }
    
    private function monitorSchool($school) {
        $school_id = $school['id'];
        $this->log("Processing school ID: " . $school_id);
        
        // Get current active student count
        $student_count = $this->getActiveStudentCount($school_id);
        
        // Log daily usage
        $this->logStudentUsage($school_id, $student_count);
        
        // Check if we need to generate invoice (monthly)
        if ($this->shouldGenerateInvoice($school_id)) {
            $this->generateMonthlyInvoice($school_id, $student_count);
        }
        
        // Check for student limit excess
        if ($school['plan_id']) {
            $this->checkStudentLimit($school_id, $school['plan_id'], $student_count);
        }
        
        // Check subscription expiry
        if ($school['expires_at'] && strtotime($school['expires_at']) < time()) {
            $this->handleExpiredSubscription($school_id);
        }
    }
    
    private function getActiveStudentCount($school_id) {
        $query = "SELECT COUNT(*) as total FROM tblstudents 
                  WHERE school_id = ? AND Status = 'Active'";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $school_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['total'] ?? 0;
    }
    
    private function logStudentUsage($school_id, $count) {
        $today = date('Y-m-d');
        
        // Check if already logged today
        $check = $this->conn->prepare("SELECT id FROM student_usage_log 
                                       WHERE school_id = ? AND logged_at = ?");
        $check->bind_param("is", $school_id, $today);
        $check->execute();
        $result = $check->get_result();
        
        if ($result->num_rows == 0) {
            $insert = $this->conn->prepare("INSERT INTO student_usage_log (school_id, student_count, logged_at) 
                                           VALUES (?, ?, ?)");
            $insert->bind_param("iis", $school_id, $count, $today);
            $insert->execute();
            $this->log("Logged student count: $count for school $school_id");
        }
    }
    
    private function shouldGenerateInvoice($school_id) {
        // Check if it's time for monthly invoice
        $query = "SELECT MAX(cycle_end) as last_cycle FROM billing_cycles 
                  WHERE school_id = ? AND status = 'closed'";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $school_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        $last_cycle = $row['last_cycle'] ?? date('Y-m-d', strtotime('-1 month'));
        
        // Generate invoice if last cycle was more than 25 days ago
        return strtotime($last_cycle) < strtotime('-25 days');
    }
    
    private function generateMonthlyInvoice($school_id, $current_students) {
        // Get school's plan
        $query = "SELECT s.*, p.price, p.max_students 
                  FROM subscriptions s
                  JOIN subscription_plans p ON s.plan_id = p.id
                  WHERE s.school_id = ? AND s.status = 'active'
                  ORDER BY s.id DESC LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $school_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $subscription = $result->fetch_assoc();
        
        if (!$subscription) {
            $this->log("No active subscription for school $school_id");
            return;
        }
        
        // Calculate extra students and charges
        $max_students = $subscription['max_students'];
        $extra_students = max(0, $current_students - $max_students);
        $extra_charges = $extra_students * $this->extra_fee_per_student;
        
        // Calculate total amount
        $base_amount = $subscription['price'];
        $total_amount = $base_amount + $extra_charges;
        
        // Generate invoice number
        $invoice_number = 'INV-' . date('Ymd') . '-' . str_pad($school_id, 5, '0', STR_PAD_LEFT);
        
        // Create billing cycle
        $cycle_start = date('Y-m-d', strtotime('first day of last month'));
        $cycle_end = date('Y-m-d', strtotime('last day of last month'));
        
        $cycle_stmt = $this->conn->prepare("INSERT INTO billing_cycles (school_id, cycle_start, cycle_end) 
                                           VALUES (?, ?, ?)");
        $cycle_stmt->bind_param("iss", $school_id, $cycle_start, $cycle_end);
        $cycle_stmt->execute();
        
        // Create invoice
        $due_date = date('Y-m-d', strtotime('+7 days'));
        $invoice_stmt = $this->conn->prepare("
            INSERT INTO invoices (
                school_id, invoice_number, plan_id, base_amount, 
                extra_students, extra_charges, total_amount, due_date
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $invoice_stmt->bind_param(
            "isidddds",
            $school_id, $invoice_number, $subscription['plan_id'],
            $base_amount, $extra_students, $extra_charges, $total_amount, $due_date
        );
        
        if ($invoice_stmt->execute()) {
            $this->log("Generated invoice $invoice_number for school $school_id: KES $total_amount");
            
            // Send SMS notification
            $this->sendInvoiceNotification($school_id, $total_amount, $due_date);
        }
    }
    
    private function checkStudentLimit($school_id, $plan_id, $current_students) {
        $query = "SELECT max_students FROM subscription_plans WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $plan_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $plan = $result->fetch_assoc();
        
        if ($current_students > $plan['max_students'] * 1.2) { // 20% over limit
            // Send warning notification
            $this->sendLimitWarning($school_id, $current_students, $plan['max_students']);
        }
    }
    
    private function handleExpiredSubscription($school_id) {
        // Set grace period
        $grace_expires = date('Y-m-d H:i:s', strtotime('+' . $this->grace_period_days . ' days'));
        
        $update = $this->conn->prepare("UPDATE tblschoolinfo 
                                        SET grace_expires_at = ?, subscription_status = 'expired' 
                                        WHERE id = ?");
        $update->bind_param("si", $grace_expires, $school_id);
        $update->execute();
        
        $this->log("Subscription expired for school $school_id, grace until $grace_expires");
        
        // Send expiry notification
        $this->sendExpiryNotification($school_id);
    }
    
    private function sendInvoiceNotification($school_id, $amount, $due_date) {
        // Get school phone
        $query = "SELECT school_phone, school_name FROM tblschoolinfo WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $school_id);
        $stmt->execute();
        $school = $stmt->get_result()->fetch_assoc();
        
        if ($school && $school['school_phone']) {
            $message = "Your EduScore invoice for KES " . number_format($amount) . 
                      " is ready. Due date: " . date('d/m/Y', strtotime($due_date)) . 
                      ". Pay via M-PESA Till: 6876258";
            
            // Send SMS (implement your SMS function)
            // sendSMS($school['school_phone'], $message);
            
            $this->log("Invoice notification sent to school $school_id");
        }
    }
    
    private function sendLimitWarning($school_id, $current, $limit) {
        $query = "SELECT school_phone, school_name FROM tblschoolinfo WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $school_id);
        $stmt->execute();
        $school = $stmt->get_result()->fetch_assoc();
        
        if ($school && $school['school_phone']) {
            $excess = $current - $limit;
            $message = "Warning: You have $excess students over your plan limit of $limit. " .
                      "Extra charges of KES " . ($excess * $this->extra_fee_per_student) . 
                      " will apply. Upgrade your plan to avoid extra fees.";
            
            // Send SMS
            // sendSMS($school['school_phone'], $message);
            
            $this->log("Limit warning sent to school $school_id");
        }
    }
    
    private function sendExpiryNotification($school_id) {
        $query = "SELECT school_phone, school_name FROM tblschoolinfo WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $school_id);
        $stmt->execute();
        $school = $stmt->get_result()->fetch_assoc();
        
        if ($school && $school['school_phone']) {
            $message = "Your EduScore subscription has expired. Please renew within $this->grace_period_days days to avoid system lock.";
            
            // Send SMS
            // sendSMS($school['school_phone'], $message);
            
            $this->log("Expiry notification sent to school $school_id");
        }
    }
    
    public function __destruct() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
}

// Run the monitor
$monitor = new StudentUsageMonitor();
$monitor->monitorAllSchools();