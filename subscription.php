<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// Check if user is logged in
if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['school_id'])) {
    header('Location: login.php');
    exit();
}

require_once 'includes/session_timeout.php';
require_once 'includes/config.php';
require_once 'includes/SubscriptionManager.php';
require_once 'includes/PricingEngine.php';

if (!isset($dbh)) {
    die("Database connection error.");
}

$teacher_id = $_SESSION['teacher_id'];
$school_id = $_SESSION['school_id'];

// Ensure required tables exist (without creating views)
function ensureBillingTables($dbh) {
    // Check if billing_transactions table exists
    $stmt = $dbh->prepare("SHOW TABLES LIKE 'billing_transactions'");
    $stmt->execute();
    if ($stmt->rowCount() == 0) {
        $dbh->exec("
            CREATE TABLE IF NOT EXISTS billing_transactions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                school_id INT NOT NULL,
                transaction_ref VARCHAR(100) UNIQUE,
                amount DECIMAL(10,2) NOT NULL,
                payment_type ENUM('onboarding', 'subscription', 'extra_students') NOT NULL,
                status ENUM('pending', 'success', 'failed', 'cancelled') DEFAULT 'pending',
                phone VARCHAR(20),
                mpesa_receipt_code VARCHAR(50),
                checkout_request_id VARCHAR(100),
                merchant_request_id VARCHAR(100),
                invoice_id INT,
                callback_data TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_school_status (school_id, status),
                INDEX idx_reference (transaction_ref),
                INDEX idx_created (created_at)
            )
        ");
    }
    
    // Check if school_subscriptions table exists
    $stmt = $dbh->prepare("SHOW TABLES LIKE 'school_subscriptions'");
    $stmt->execute();
    if ($stmt->rowCount() == 0) {
        $dbh->exec("
            CREATE TABLE IF NOT EXISTS school_subscriptions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                school_id INT NOT NULL,
                subscription_state ENUM('trial', 'awaiting_onboarding', 'active_free_term', 'active_paid_term', 'expired') DEFAULT 'trial',
                plan_name VARCHAR(100),
                start_date DATE,
                expiry_date DATE,
                term_year INT,
                term_number TINYINT,
                trial_started_at DATETIME,
                trial_ends_at DATETIME,
                last_state_change DATETIME,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_school_state (school_id, subscription_state),
                INDEX idx_expiry (expiry_date)
            )
        ");
    }
    
    // Check if billing_invoices table exists
    $stmt = $dbh->prepare("SHOW TABLES LIKE 'billing_invoices'");
    $stmt->execute();
    if ($stmt->rowCount() == 0) {
        $dbh->exec("
            CREATE TABLE IF NOT EXISTS billing_invoices (
                id INT AUTO_INCREMENT PRIMARY KEY,
                invoice_no VARCHAR(50) UNIQUE NOT NULL,
                school_id INT NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                status ENUM('UNPAID', 'PAID', 'CANCELLED') DEFAULT 'UNPAID',
                invoice_type ENUM('onboarding', 'subscription', 'extra_students') NOT NULL,
                term_number TINYINT,
                term_year INT,
                student_count INT,
                price_per_student DECIMAL(10,2),
                due_date DATE,
                paid_at DATETIME,
                file_path VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_school_status (school_id, status)
            )
        ");
    }
}

// Run table check
try {
    ensureBillingTables($dbh);
} catch (Exception $e) {
    error_log("Table creation error: " . $e->getMessage());
}

// Initialize managers
try {
    $subscriptionManager = new SubscriptionManager($dbh, $school_id);
    $pricingEngine = new PricingEngine($dbh, $school_id);
} catch (Exception $e) {
    error_log("Subscription initialization error: " . $e->getMessage());
    $subscriptionManager = null;
    $pricingEngine = null;
}

// Get current subscription state
$current_state = 'trial';
$subscription = null;
if ($subscriptionManager) {
    try {
        $current_state = $subscriptionManager->getCurrentState();
        $subscription_data = $dbh->prepare("
            SELECT * FROM school_subscriptions WHERE school_id = ? ORDER BY id DESC LIMIT 1
        ");
        $subscription_data->execute([$school_id]);
        $subscription = $subscription_data->fetch();
    } catch (Exception $e) {
        error_log("Error getting subscription: " . $e->getMessage());
    }
}

// Get school info
$stmt = $dbh->prepare("SELECT * FROM tblschoolinfo WHERE id = ?");
$stmt->execute([$school_id]);
$school = $stmt->fetch();

// Get student count and pricing
if ($pricingEngine) {
    try {
        $student_count = $pricingEngine->getStudentCount();
        $price_per_student = $pricingEngine->getPricePerStudent();
        $onboarding_fee = $pricingEngine->getOnboardingFee();
        $subscription_amount = $pricingEngine->calculateSubscriptionAmount();
        $package_display = $pricingEngine->getPackageDisplayName();
        $school_level_type = ucfirst($pricingEngine->getSchoolLevel()) . ' • ' . ucfirst($pricingEngine->getSchoolType());
    } catch (Exception $e) {
        error_log("Pricing engine error: " . $e->getMessage());
        $student_count = 0;
        $price_per_student = 15;
        $onboarding_fee = 2000;
        $subscription_amount = 0;
        $package_display = 'Standard Package';
        $school_level_type = 'Primary • Public';
    }
} else {
    $student_count = 0;
    $price_per_student = 15;
    $onboarding_fee = 2000;
    $subscription_amount = 0;
    $package_display = 'Standard Package';
    $school_level_type = 'Primary • Public';
}

// Check if onboarding is paid
$stmt = $dbh->prepare("SHOW COLUMNS FROM billing_transactions LIKE 'payment_type'");
$stmt->execute();
$has_payment_type = $stmt->rowCount() > 0;

if ($has_payment_type) {
    $stmt = $dbh->prepare("
        SELECT COUNT(*) as paid FROM billing_transactions 
        WHERE school_id = ? AND payment_type = 'onboarding' AND status = 'success'
    ");
    $stmt->execute([$school_id]);
    $onboarding_paid = $stmt->fetch()['paid'] > 0;
} else {
    $stmt = $dbh->prepare("
        SELECT COUNT(*) as paid FROM billing_transactions 
        WHERE school_id = ? AND amount = ? AND status = 'success'
    ");
    $stmt->execute([$school_id, $onboarding_fee]);
    $onboarding_paid = $stmt->fetch()['paid'] > 0;
}

// Calculate pricing based on state
$total_amount = 0;
$invoice_type = null;

if ($subscriptionManager) {
    try {
        switch($current_state) {
            case 'awaiting_onboarding':
                if (!$onboarding_paid) {
                    $total_amount = $onboarding_fee;
                    $invoice_type = 'onboarding';
                }
                break;
            case 'expired':
                $total_amount = $subscription_amount;
                $invoice_type = 'subscription';
                break;
            default:
                $total_amount = 0;
                break;
        }
    } catch (Exception $e) {
        error_log("State processing error: " . $e->getMessage());
    }
}

// Get or create unpaid invoice
$invoice = null;
if ($total_amount > 0 && $invoice_type) {
    $stmt = $dbh->prepare("
        SELECT * FROM billing_invoices 
        WHERE school_id = ? AND status = 'UNPAID' AND invoice_type = ?
        ORDER BY created_at DESC LIMIT 1
    ");
    $stmt->execute([$school_id, $invoice_type]);
    $invoice = $stmt->fetch();
    
    if (!$invoice) {
        $invoice_no = 'INV-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        $term = 1;
        $year = date('Y');
        if ($subscriptionManager && method_exists($subscriptionManager, 'getCurrentTermDates')) {
            try {
                $term_info = $subscriptionManager->getCurrentTermDates();
                $term = $term_info['term'];
                $year = $term_info['year'];
            } catch (Exception $e) {
                error_log("Term info error: " . $e->getMessage());
            }
        }
        
        $stmt = $dbh->prepare("
            INSERT INTO billing_invoices 
            (invoice_no, school_id, amount, invoice_type, term_number, term_year, student_count, price_per_student, due_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))
        ");
        $stmt->execute([
            $invoice_no, $school_id, $total_amount, $invoice_type,
            $term, $year, $student_count, $price_per_student
        ]);
        
        $invoice_id = $dbh->lastInsertId();
        $stmt = $dbh->prepare("SELECT * FROM billing_invoices WHERE id = ?");
        $stmt->execute([$invoice_id]);
        $invoice = $stmt->fetch();
    }
}

// Get payment history
$history_items = [];

try {
    $stmt = $dbh->prepare("
        SELECT 
            transaction_ref as reference, 
            amount, 
            status, 
            created_at, 
            'M-PESA' as payment_method,
            CASE 
                WHEN payment_type = 'onboarding' THEN 'Onboarding Fee'
                WHEN payment_type = 'subscription' THEN 'Subscription Payment'
                ELSE 'Extra Student Charges'
            END as description
        FROM billing_transactions 
        WHERE school_id = ? AND status = 'success'
        ORDER BY created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$school_id]);
    $billing_items = $stmt->fetchAll();
    foreach ($billing_items as $item) {
        $history_items[] = $item;
    }
} catch (Exception $e) {
    error_log("Error fetching billing transactions: " . $e->getMessage());
}

try {
    $stmt = $dbh->prepare("
        SELECT 
            reference, 
            amount, 
            status, 
            created_at, 
            'M-PESA' as payment_method,
            'Legacy Payment' as description
        FROM tblpayments 
        WHERE school_id = ? AND status = 'completed'
        ORDER BY created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$school_id]);
    $legacy_items = $stmt->fetchAll();
    foreach ($legacy_items as $item) {
        $history_items[] = $item;
    }
} catch (Exception $e) {
    error_log("Error fetching legacy payments: " . $e->getMessage());
}

usort($history_items, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

$total_paid = array_sum(array_column($history_items, 'amount'));

// Current term info
$current_term = "Term " . ceil(date('n') / 4);
$current_year = date('Y');
if ($subscriptionManager) {
    try {
        $term_info = $subscriptionManager->getCurrentTermDates();
        $current_term = "Term {$term_info['term']}";
        $current_year = $term_info['year'];
    } catch (Exception $e) {
        error_log("Term info error: " . $e->getMessage());
    }
}

// Status display
$status_config = [
    'color' => '#6b7280',
    'text' => 'Unknown',
    'icon' => 'fa-circle'
];

switch($current_state) {
    case 'trial':
        $status_config = ['color' => '#8b5cf6', 'text' => 'Trial Active', 'icon' => 'fa-star-of-life'];
        break;
    case 'awaiting_onboarding':
        $status_config = ['color' => '#f59e0b', 'text' => 'Awaiting Payment', 'icon' => 'fa-clock'];
        break;
    case 'active_free_term':
        $status_config = ['color' => '#10b981', 'text' => 'Active (Free Term)', 'icon' => 'fa-check-circle'];
        break;
    case 'active_paid_term':
        $status_config = ['color' => '#10b981', 'text' => 'Active', 'icon' => 'fa-check-circle'];
        break;
    case 'expired':
        $status_config = ['color' => '#ef4444', 'text' => 'Expired', 'icon' => 'fa-times-circle'];
        break;
}

$expiry_timestamp = $subscription && isset($subscription['expiry_date']) && $subscription['expiry_date'] ? 
                    strtotime($subscription['expiry_date']) : 0;
$mpesa_till = defined('PAYHERO_TILL_NUMBER') ? PAYHERO_TILL_NUMBER : (defined('MPESA_TILL') ? MPESA_TILL : '6876258');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription | EduScore</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="images/logo.png" />
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fa; color: #1a1a2e; }
        .main-content { margin-left: 280px; min-height: 100vh; padding: 2rem; }
        @media (max-width: 992px) { .main-content { margin-left: 0; padding: 1rem; } }
        .subscription-container { max-width: 1400px; margin: 0 auto; }
        .header-section { margin-bottom: 2rem; }
        .welcome-badge { display: inline-flex; align-items: center; gap: 0.5rem; background: linear-gradient(135deg, #1e3a8a, #2563eb); color: white; padding: 0.5rem 1rem; border-radius: 100px; font-size: 0.75rem; font-weight: 500; margin-bottom: 1rem; }
        .subscription-page-title { font-size: 2rem; font-weight: 700; color: #1e293b; margin-bottom: 0.5rem; }
        .page-subtitle { color: #64748b; font-size: 0.9rem; }
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem; margin-bottom: 2rem; }
        @media (max-width: 1024px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 640px) { .stats-grid { grid-template-columns: 1fr; } }
        .stat-card { background: white; border-radius: 20px; padding: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border: 1px solid #eef2ff; transition: all 0.3s ease; position: relative; overflow: hidden; }
        .stat-card::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; background: linear-gradient(135deg, #1e3a8a, #fbbf24); }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); }
        .stat-icon { width: 48px; height: 48px; border-radius: 16px; display: flex; align-items: center; justify-content: center; margin-bottom: 1rem; }
        .stat-icon.blue { background: #dbeafe; color: #1e3a8a; }
        .stat-icon.yellow { background: #fef3c7; color: #f59e0b; }
        .stat-icon.green { background: #d1fae5; color: #10b981; }
        .stat-icon.purple { background: #ede9fe; color: #8b5cf6; }
        .stat-icon i { font-size: 1.5rem; }
        .stat-value { font-size: 1.75rem; font-weight: 700; color: #0f172a; margin-bottom: 0.25rem; }
        .stat-label { font-size: 0.75rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 500; }
        .status-card { background: white; border-radius: 20px; padding: 1.5rem; margin-bottom: 2rem; border: 1px solid #eef2ff; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.5rem; }
        .status-info { flex: 1; }
        .status-badge-large { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; border-radius: 100px; font-weight: 500; font-size: 0.875rem; margin-bottom: 1rem; background: <?php echo $status_config['color']; ?>20; color: <?php echo $status_config['color']; ?>; }
        .status-metrics { display: flex; gap: 2rem; flex-wrap: wrap; }
        .metric { display: flex; flex-direction: column; }
        .metric-label { font-size: 0.7rem; color: #64748b; text-transform: uppercase; font-weight: 500; }
        .metric-value { font-size: 1.25rem; font-weight: 600; color: #0f172a; }
        #countdownTimer { font-family: monospace; font-weight: 700; transition: color 0.3s ease; font-size: 1.25rem; }
        .pricing-card { background: white; border-radius: 20px; border: 1px solid #eef2ff; overflow: hidden; margin-bottom: 1.5rem; }
        .card-header { padding: 1.25rem 1.5rem; background: #f8fafc; border-bottom: 1px solid #eef2ff; display: flex; align-items: center; gap: 0.75rem; }
        .card-header i { color: #f59e0b; font-size: 1.25rem; }
        .card-header h3 { font-size: 1rem; font-weight: 600; color: #1e293b; }
        .card-body { padding: 1.5rem; }
        .amount-due { background: linear-gradient(135deg, #fef3c7, #fffbeb); border-radius: 16px; padding: 1.5rem; text-align: center; margin-bottom: 1.5rem; }
        .amount-due .label { font-size: 0.75rem; color: #92400e; text-transform: uppercase; letter-spacing: 1px; }
        .amount-due .value { font-size: 2.5rem; font-weight: 700; color: #f59e0b; }
        .service-item { display: flex; justify-content: space-between; align-items: center; padding: 1rem 0; border-bottom: 1px solid #eef2ff; }
        .service-item:last-child { border-bottom: none; }
        .service-name { font-weight: 600; color: #0f172a; }
        .service-price { font-weight: 600; color: #1e3a8a; }
        .total-row { display: flex; justify-content: space-between; align-items: center; padding-top: 1rem; margin-top: 1rem; border-top: 2px solid #eef2ff; font-weight: 700; }
        .payment-methods { margin-top: 1.5rem; }
        .payment-method { border: 2px solid #eef2ff; border-radius: 16px; padding: 1rem; margin-bottom: 1rem; cursor: pointer; transition: all 0.2s ease; }
        .payment-method.active { border-color: #f59e0b; background: #fffbeb; }
        .till-box { background: #1e293b; color: white; padding: 1rem; border-radius: 12px; text-align: center; margin-bottom: 1rem; }
        .till-number { font-size: 1.5rem; font-weight: 700; letter-spacing: 2px; }
        .stk-input { width: 100%; padding: 0.75rem 1rem; border: 2px solid #eef2ff; border-radius: 12px; font-size: 1rem; margin-bottom: 1rem; }
        .stk-input:focus { outline: none; border-color: #f59e0b; }
        .btn-pay { width: 100%; padding: 1rem; background: linear-gradient(135deg, #1e3a8a, #2563eb); color: white; border: none; border-radius: 12px; font-weight: 600; font-size: 1rem; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.5rem; transition: all 0.2s ease; }
        .btn-pay:hover { transform: translateY(-2px); box-shadow: 0 10px 25px -5px rgba(30,58,138,0.3); }
        .btn-pay:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-manual { background: #10b981; }
        .btn-manual:hover { background: #059669; box-shadow: 0 10px 25px -5px rgba(16,185,129,0.3); }
        .history-card { background: white; border-radius: 20px; border: 1px solid #eef2ff; overflow: hidden; }
        .history-header { padding: 1.25rem 1.5rem; background: #f8fafc; border-bottom: 1px solid #eef2ff; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; }
        .subscription-search-box { position: relative; }
        .subscription-search-box i { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #94a3b8; }
        .subscription-search-box input { padding: 0.5rem 1rem 0.5rem 2.5rem; border: 1px solid #eef2ff; border-radius: 12px; font-size: 0.875rem; width: 250px; }
        .table-container { overflow-x: auto; width: 100%; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 1rem 1.5rem; background: #f8fafc; font-size: 0.75rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 1rem 1.5rem; border-bottom: 1px solid #eef2ff; font-size: 0.875rem; }
        .status-badge { display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.25rem 0.75rem; border-radius: 100px; font-size: 0.7rem; font-weight: 500; background: #d1fae5; color: #065f46; }
        .summary-footer { padding: 1rem 1.5rem; border-top: 1px solid #eef2ff; background: #f8fafc; display: flex; justify-content: flex-end; font-weight: 700; }
        .empty-state { text-align: center; padding: 3rem; color: #64748b; }
        .empty-state i { font-size: 3rem; margin-bottom: 1rem; }
        .toast-container { position: fixed; top: 100px; right: 1.5rem; z-index: 9999; }
        .toast { background: white; border-radius: 12px; padding: 1rem 1.5rem; margin-bottom: 0.75rem; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); display: flex; align-items: center; gap: 1rem; border-left: 4px solid; animation: slideIn 0.3s ease; }
        .toast.success { border-left-color: #10b981; }
        .toast.error { border-left-color: #ef4444; }
        @keyframes slideIn { from { opacity: 0; transform: translateX(100%); } to { opacity: 1; transform: translateX(0); } }
        .loading { display: inline-block; width: 20px; height: 20px; border: 2px solid #e2e8f0; border-top-color: #1e3a8a; border-radius: 50%; animation: spin 0.6s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .info-text { font-size: 0.75rem; color: #64748b; margin-top: 0.5rem; }
        .pricing-breakdown { background: #f8fafc; border-radius: 12px; padding: 1rem; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    <?php include 'includes/header.php'; ?>

    <div class="main-content">
        <div class="subscription-container">
            <div class="header-section">
                <div class="welcome-badge"><i class="fas fa-crown"></i><span><?php echo htmlspecialchars($school['school_name'] ?? 'School'); ?></span></div>
                <h1 class="subscription-page-title">Subscription Management</h1>
                <p class="page-subtitle"><?php echo $current_term; ?> • <?php echo $current_year; ?></p>
            </div>

            <div class="stats-grid">
                <div class="stat-card"><div class="stat-icon blue"><i class="fas fa-gem"></i></div><div class="stat-value"><?php echo htmlspecialchars($package_display); ?></div><div class="stat-label">Current Plan</div></div>
                <div class="stat-card"><div class="stat-icon yellow"><i class="fas fa-tag"></i></div><div class="stat-value"><?php echo $school_level_type; ?></div><div class="stat-label">School Type</div></div>
                <div class="stat-card"><div class="stat-icon green"><i class="fas fa-envelope"></i></div><div class="stat-value"><?php echo number_format($school['sms_balance'] ?? 0); ?></div><div class="stat-label">SMS Credits</div></div>
                <div class="stat-card"><div class="stat-icon purple"><i class="fas fa-users"></i></div><div class="stat-value"><?php echo number_format($student_count); ?></div><div class="stat-label">Active Students</div><div class="info-text">@ KES <?php echo number_format($price_per_student); ?>/student</div></div>
            </div>

            <div class="status-card">
                <div class="status-info">
                    <div class="status-badge-large"><i class="fas <?php echo $status_config['icon']; ?>"></i><span><?php echo $status_config['text']; ?></span></div>
                    <div class="status-metrics">
                        <div class="metric"><span class="metric-label">Start Date</span><span class="metric-value"><?php echo $subscription && isset($subscription['start_date']) && $subscription['start_date'] ? date('M d, Y', strtotime($subscription['start_date'])) : '—'; ?></span></div>
                        <div class="metric"><span class="metric-label">Expiry Date</span><span class="metric-value"><?php echo $subscription && isset($subscription['expiry_date']) && $subscription['expiry_date'] ? date('M d, Y', strtotime($subscription['expiry_date'])) : '—'; ?></span></div>
                        <div class="metric"><span class="metric-label">Time Remaining</span><span class="metric-value" id="countdownTimer"><?php if ($expiry_timestamp > 0 && $expiry_timestamp > time()) { $diff = $expiry_timestamp - time(); $days = floor($diff / 86400); echo $days . ' days remaining'; } else { echo $status_config['text']; } ?></span></div>
                    </div>
                </div>
            </div>

            <div class="pricing-card">
                <div class="card-header"><i class="fas fa-calculator"></i><h3>Pricing Breakdown</h3></div>
                <div class="card-body">
                    <div class="pricing-breakdown">
                        <div class="service-item"><span class="service-name">Package: <?php echo $package_display; ?></span><span class="service-price"><?php echo $school_level_type; ?></span></div>
                        <div class="service-item"><span class="service-name">Price per Student</span><span class="service-price">KES <?php echo number_format($price_per_student); ?></span></div>
                        <div class="service-item"><span class="service-name">Onboarding Fee</span><span class="service-price">KES <?php echo number_format($onboarding_fee); ?></span></div>
                        <div class="service-item"><span class="service-name">Total Active Students</span><span class="service-price"><?php echo number_format($student_count); ?></span></div>
                        <div class="total-row"><span>Term Subscription Total</span><span>KES <?php echo number_format($subscription_amount, 2); ?></span></div>
                    </div>
                </div>
            </div>

            <?php if ($total_amount > 0): ?>
            <div class="pricing-card">
                <div class="card-header"><i class="fas fa-receipt"></i><h3>Outstanding Balance</h3></div>
                <div class="card-body">
                    <div class="amount-due"><div class="label">Amount Due</div><div class="value">KES <?php echo number_format($total_amount, 2); ?></div><?php if ($invoice): ?><div class="info-text">Invoice #: <?php echo htmlspecialchars($invoice['invoice_no']); ?></div><?php endif; ?></div>
                    <div class="service-item"><div class="service-name"><?php if ($invoice_type === 'onboarding'): ?>Onboarding Fee<?php else: ?><?php echo $current_term; ?> <?php echo $current_year; ?> Subscription<?php endif; ?></div><div class="service-price">KES <?php echo number_format($total_amount, 2); ?></div></div>
                    <?php if ($invoice_type === 'subscription'): ?>
                    <div class="service-item"><div class="service-name">Student Count (<?php echo number_format($student_count); ?> students × KES <?php echo number_format($price_per_student); ?>)</div><div class="service-price">KES <?php echo number_format($subscription_amount, 2); ?></div></div>
                    <?php endif; ?>
                    <div class="total-row"><span>Total Due</span><span>KES <?php echo number_format($total_amount, 2); ?></span></div>
                    <div class="payment-methods">
                        <div class="payment-method active" data-method="stk">
                            <div class="method-header"><div class="method-icon"><i class="fas fa-mobile-alt"></i></div><div class="method-title">M-PESA STK Push</div></div>
                            <div class="method-details">
                                <form id="stkForm" onsubmit="return initiateSTKPush(event, <?php echo $total_amount; ?>, '<?php echo $invoice_type; ?>')">
                                    <input type="tel" id="phoneNumber" class="stk-input" placeholder="Phone Number (e.g., 712345678)" maxlength="9">
                                    <button type="submit" class="btn-pay" id="stkBtn"><i class="fas fa-bolt"></i> Pay KES <?php echo number_format($total_amount, 2); ?></button>
                                </form>
                            </div>
                        </div>
                        <div class="payment-method" data-method="manual">
                            <div class="method-header"><div class="method-icon"><i class="fas fa-building"></i></div><div class="method-title">Buy Goods (Manual)</div></div>
                            <div class="method-details">
                                <div class="till-box"><div style="font-size:0.7rem;opacity:0.8;">Till Number</div><div class="till-number"><?php echo $mpesa_till; ?></div></div>
                                <input type="text" id="mpesaCode" class="stk-input" placeholder="Enter M-PESA confirmation code">
                                <button onclick="confirmPayment(<?php echo $total_amount; ?>, '<?php echo $invoice_type; ?>', <?php echo $invoice['id'] ?? 0; ?>)" class="btn-pay btn-manual"><i class="fas fa-check"></i> Confirm Payment</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php elseif ($current_state === 'active_free_term'): ?>
            <div class="pricing-card"><div class="card-body"><div class="empty-state"><i class="fas fa-gift" style="font-size:3rem;color:#10b981;margin-bottom:1rem;"></i><h3>Free Term Active!</h3><p>Your onboarding fee has been paid. Enjoy full access for <?php echo $current_term; ?> <?php echo $current_year; ?> at no additional cost.</p><p class="info-text" style="margin-top:1rem;">Next term subscription will be KES <?php echo number_format($subscription_amount, 2); ?></p></div></div></div>
            <?php elseif ($current_state === 'trial'): ?>
            <div class="pricing-card"><div class="card-body"><div class="empty-state"><i class="fas fa-flask" style="font-size:3rem;color:#8b5cf6;margin-bottom:1rem;"></i><h3>Free Trial Active</h3><p>You're on a 14-day free trial. No payment required until trial ends.</p><p class="info-text" style="margin-top:1rem;">After trial, pay KES <?php echo number_format($onboarding_fee); ?> onboarding fee to continue.</p></div></div></div>
            <?php elseif ($current_state === 'active_paid_term'): ?>
            <div class="pricing-card"><div class="card-body"><div class="empty-state"><i class="fas fa-check-circle" style="font-size:3rem;color:#10b981;margin-bottom:1rem;"></i><h3>Subscription Active</h3><p>Your subscription is active until <?php echo $subscription && isset($subscription['expiry_date']) ? date('F d, Y', strtotime($subscription['expiry_date'])) : 'end of term'; ?></p></div></div></div>
            <?php endif; ?>

            <div class="history-card">
                <div class="history-header"><h3><i class="fas fa-history"></i> Payment History</h3><div class="subscription-search-box"><i class="fas fa-search"></i><input type="text" id="paymentSearch" placeholder="Search transactions..."></div></div>
                <div class="table-container"><table><thead><tr><th>Reference</th><th>Date</th><th>Description</th><th>Amount</th><th>Status</th></tr></thead><tbody id="paymentTableBody">
                    <?php if (!empty($history_items)): foreach ($history_items as $item): ?>
                    <tr><td><?php echo htmlspecialchars(substr($item['reference'], 0, 20)); ?></td><td><?php echo date('M d, Y H:i', strtotime($item['created_at'])); ?></td><td><?php echo htmlspecialchars($item['description'] ?? 'Payment'); ?></td><td>KES <?php echo number_format($item['amount'], 2); ?></td><td><span class="status-badge"><i class="fas fa-check-circle"></i> Completed</span></td></tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="5"><div class="empty-state"><i class="fas fa-receipt"></i><p>No payment history yet</p></div></td></tr>
                    <?php endif; ?>
                </tbody></table></div>
                <?php if (!empty($history_items)): ?>
                <div class="summary-footer"><strong>Total Paid:</strong> KES <?php echo number_format($total_paid, 2); ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            startCountdown();
            $('.payment-method').click(function() { $('.payment-method').removeClass('active'); $(this).addClass('active'); });
            $('#paymentSearch').on('input', function() { const term = $(this).val().toLowerCase(); $('#paymentTableBody tr').each(function() { $(this).toggle($(this).text().toLowerCase().includes(term)); }); });
            $('#phoneNumber').on('input', function() { this.value = this.value.replace(/[^0-9]/g, '').slice(0, 9); });
        });

        function showToast(message, type = 'info') {
            const icons = { success: 'fa-check-circle', error: 'fa-exclamation-circle', warning: 'fa-exclamation-triangle', info: 'fa-info-circle' };
            const toast = $(`<div class="toast ${type}"><i class="fas ${icons[type]}"></i><span>${message}</span></div>`);
            $('#toastContainer').append(toast);
            setTimeout(() => toast.fadeOut(300, function() { $(this).remove(); }), 3000);
        }

        function startCountdown() {
            <?php if ($expiry_timestamp > 0 && $expiry_timestamp > time()): ?>
            const expiryTimestamp = <?php echo $expiry_timestamp * 1000; ?>;
            function updateCountdown() {
                const distance = expiryTimestamp - new Date().getTime();
                if (distance < 0) { document.getElementById('countdownTimer').innerHTML = 'Expired'; document.getElementById('countdownTimer').style.color = '#ef4444'; return; }
                const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                const hours = Math.floor((distance % (86400000)) / 3600000);
                const minutes = Math.floor((distance % 3600000) / 60000);
                let display = days > 0 ? `${days}d ` : '';
                display += `${hours}h ${minutes}m`;
                const timer = document.getElementById('countdownTimer');
                timer.innerHTML = display;
                timer.style.color = days < 7 ? '#f59e0b' : '#10b981';
            }
            updateCountdown();
            setInterval(updateCountdown, 60000);
            <?php endif; ?>
        }

        function confirmPayment(amount, paymentType, invoiceId) {
            const code = $('#mpesaCode').val().trim();
            if (!code) { showToast('Please enter M-PESA confirmation code', 'warning'); return; }
            showToast('Processing payment...', 'info');
            $.ajax({ url: 'ajax/verify_payment.php', method: 'POST', data: { code: code, school_id: <?php echo $school_id; ?>, amount: amount, payment_type: paymentType, invoice_id: invoiceId }, success: function(res) { if (res.success) { showToast('Payment verified successfully!', 'success'); setTimeout(() => location.reload(), 1500); } else { showToast(res.message || 'Verification failed', 'error'); } }, error: function() { showToast('Network error. Please try again.', 'error'); } });
        }

        function initiateSTKPush(e, amount, paymentType) {
            e.preventDefault();
            const phoneInput = $('#phoneNumber').val().trim();
            if (!phoneInput) { showToast('Please enter your M-PESA phone number', 'warning'); return false; }
            const phone = '254' + phoneInput.replace(/\D/g, '');
            if (!/^254[17]\d{8}$/.test(phone)) { showToast('Please enter a valid Kenyan phone number (e.g., 712345678)', 'warning'); return false; }
            const $btn = $('#stkBtn');
            const originalText = $btn.html();
            $btn.prop('disabled', true).html('<div class="loading"></div> Sending...');
            showToast('Sending STK push...', 'info');
            $.ajax({ url: 'includes/stk_push.php', method: 'POST', data: { phone: phone, amount: amount, school_id: <?php echo $school_id; ?>, payment_type: paymentType, invoice_id: <?php echo $invoice['id'] ?? 0; ?> }, dataType: 'json', success: function(res) { if (res.success) { showToast('STK push sent! Check your phone to complete payment.', 'success'); $('#phoneNumber').val(''); setTimeout(() => checkPaymentStatus(res.reference), 5000); } else { showToast(res.message || 'STK push failed', 'error'); } }, error: function() { showToast('Network error. Please try again.', 'error'); }, complete: function() { $btn.prop('disabled', false).html(originalText); } });
            return false;
        }

        function checkPaymentStatus(reference) { let checkCount = 0; function doCheck() { checkCount++; $.ajax({ url: 'ajax/check_payment_status.php', method: 'POST', data: { reference: reference }, success: function(res) { if (res.status === 'success') { showToast('Payment completed! Refreshing...', 'success'); setTimeout(() => location.reload(), 2000); } else if (res.status === 'failed') { showToast('Payment failed. Please try again.', 'error'); } else if (res.status === 'pending' && checkCount < 30) { setTimeout(doCheck, 10000); } } }); } doCheck(); }

        window.confirmPayment = confirmPayment;
        window.initiateSTKPush = initiateSTKPush;
    </script>
</body>
</html>