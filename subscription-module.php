<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/config.php';

// Check if user is coming from login redirect OR has valid session
$valid_access = false;

// Check 1: Has subscription_redirect flag from login
if (isset($_SESSION['subscription_redirect']) && $_SESSION['subscription_redirect'] === true) {
    $valid_access = true;
}
// Check 2: Has valid teacher_id and school_id (maybe coming directly)
elseif (isset($_SESSION['teacher_id']) && isset($_SESSION['school_id'])) {
    $valid_access = true;
}
// Check 3: Has subscription status in URL (direct access with status)
elseif (isset($_GET['status'])) {
    // Allow direct access with status parameter, but we'll need school_id from somewhere
    if (!isset($_SESSION['school_id'])) {
        // Try to get from a cookie or redirect to login
        header('Location: login.php');
        exit();
    }
    $valid_access = true;
}

if (!$valid_access) {
    header('Location: login.php');
    exit();
}

// Get school_id from session
$school_id = $_SESSION['school_id'] ?? 0;
$teacher_id = $_SESSION['teacher_id'] ?? 0;

if (!$school_id) {
    header('Location: login.php');
    exit();
}

// Check if user is coming from successful payment
$payment_success = isset($_GET['payment']) && $_GET['payment'] === 'success';

// Initialize variables
$school_data = null;
$subscription = null;
$user = null;
$error_message = '';

// M-PESA Till Number
$mpesa_till = defined('MPESA_TILL') ? MPESA_TILL : '6876258';

// Default values from system settings or hardcoded
$onboarding_fee = 2000; // One-time onboarding fee
$per_student_per_term = 15; // KES per student per term
$trial_days = 14; // Free trial period
$term_duration_days = 90; // 90 days per term after onboarding

// Fetch system settings if available
try {
    // Check if system_settings table exists and get values
    $settings_check = $dbh->query("SHOW TABLES LIKE 'system_settings'");
    if ($settings_check->rowCount() > 0) {
        $stmt = $dbh->prepare("SELECT setting_value FROM system_settings WHERE setting_name = ?");
        
        $stmt->execute(['onboarding_fee']);
        $fee_row = $stmt->fetch();
        if ($fee_row) {
            $onboarding_fee = floatval($fee_row['setting_value']);
        }
        
        $stmt->execute(['extra_student_fee']);
        $extra_row = $stmt->fetch();
        if ($extra_row) {
            $per_student_per_term = floatval($extra_row['setting_value']);
        }
        
        $stmt->execute(['trial_days']);
        $trial_row = $stmt->fetch();
        if ($trial_row) {
            $trial_days = intval($trial_row['setting_value']);
        }
    }
} catch (PDOException $e) {
    // Silently continue with defaults
    error_log("Error fetching system settings: " . $e->getMessage());
}

// Fetch school details and current user info
try {
    // First, check if the connection is working
    if (!$dbh) {
        throw new Exception("Database connection failed");
    }
    
    // Get school info from tblschoolinfo - using the correct column names from your table structure
    $stmt = $dbh->prepare("
        SELECT 
            school_name,
            school_email,
            school_phone,
            school_address as address,
            county,
            principal_name,
            principal_email,
            school_logo,
            status,
            is_activated,
            total_students,
            created_at,
            updated_at
        FROM tblschoolinfo 
        WHERE id = ?
    ");
    
    if (!$stmt) {
        throw new Exception("Failed to prepare school query");
    }
    
    $stmt->execute([$school_id]);
    $school_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get subscription info if any
    $stmt_sub = $dbh->prepare("
        SELECT * FROM subscriptions 
        WHERE school_id = ? 
        ORDER BY id DESC 
        LIMIT 1
    ");
    
    if ($stmt_sub) {
        $stmt_sub->execute([$school_id]);
        $subscription = $stmt_sub->fetch(PDO::FETCH_ASSOC);
    }
    
    // Get current user info from tblteachers
    $stmt_user = $dbh->prepare("
        SELECT 
            firstname,
            secondname,
            lastname,
            email,
            phonenumber
        FROM tblteachers 
        WHERE id = ? AND school_id = ? AND (is_deleted = 0 OR is_deleted IS NULL)
    ");
    
    if ($stmt_user) {
        $stmt_user->execute([$teacher_id, $school_id]);
        $user = $stmt_user->fetch(PDO::FETCH_ASSOC);
    }
    
    // Get current active student count
    $stmt_students = $dbh->prepare("
        SELECT COUNT(*) as active_count FROM tblstudents 
        WHERE school_id = ? AND Status = 'Active'
    ");
    $stmt_students->execute([$school_id]);
    $student_count = $stmt_students->fetch(PDO::FETCH_ASSOC);
    $current_students = intval($student_count['active_count'] ?? 0);
    
    // If no active students found, fall back to total_students from school info
    if ($current_students == 0) {
        $current_students = intval($school_data['total_students'] ?? 0);
    }
    
    // Calculate subscription amounts
    $registration_date = isset($school_data['created_at']) ? new DateTime($school_data['created_at']) : null;
    $now = new DateTime();
    
    // Calculate days since registration
    $days_since_registration = $registration_date ? $now->diff($registration_date)->days : 0;
    
    // Determine if trial period is still active (within first 14 days)
    $trial_active = ($days_since_registration <= $trial_days);
    
    // Calculate payment amounts
    if (!$subscription) {
        // New school - needs onboarding fee after trial
        if ($trial_active) {
            // Still in trial period
            $days_left_in_trial = $trial_days - $days_since_registration;
            $payment_amount = 0; // No payment required yet
            $payment_due_date = $registration_date ? clone $registration_date : new DateTime();
            $payment_due_date->modify('+' . $trial_days . ' days');
        } else {
            // Trial ended - onboarding fee due
            $payment_amount = $onboarding_fee;
            $payment_due_date = null;
        }
    } else {
        // Existing subscription - check if onboarding fee was paid
        // Check if onboarding fee was ever paid
        $stmt_check_onboarding = $dbh->prepare("
            SELECT id FROM payments 
            WHERE school_id = ? AND amount = ? AND status = 'completed'
            UNION
            SELECT id FROM tbltransactions 
            WHERE school_id = ? AND amount = ? AND status = 'completed'
            LIMIT 1
        ");
        $stmt_check_onboarding->execute([$school_id, $onboarding_fee, $school_id, $onboarding_fee]);
        $onboarding_paid = $stmt_check_onboarding->fetch() ? true : false;
        
        if (!$onboarding_paid && !$trial_active) {
            // Onboarding fee not paid and trial ended
            $payment_amount = $onboarding_fee;
        } elseif ($subscription && isset($subscription['expires_at'])) {
            // Regular subscription - calculate per term fee based on student count
            $expiry = new DateTime($subscription['expires_at']);
            if ($expiry < $now) {
                // Subscription expired - charge per term based on student count
                $payment_amount = $current_students * $per_student_per_term;
            } else {
                $payment_amount = 0; // Active subscription
            }
        } else {
            $payment_amount = $current_students * $per_student_per_term;
        }
    }
    
    if (!$school_data) {
        // School not found - let's check if the school exists at all
        $check_stmt = $dbh->prepare("SELECT COUNT(*) as count FROM tblschoolinfo WHERE id = ?");
        $check_stmt->execute([$school_id]);
        $count = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($count['count'] == 0) {
            throw new Exception("School with ID $school_id not found in database");
        } else {
            throw new Exception("School found but data fetch returned empty");
        }
    }
    
} catch (PDOException $e) {
    error_log("Database error in subscription-module: " . $e->getMessage());
    $error_message = "Database error: " . $e->getMessage();
    $school_data = null;
    $subscription = null;
    $user = null;
} catch (Exception $e) {
    error_log("General error in subscription-module: " . $e->getMessage());
    $error_message = $e->getMessage();
    $school_data = null;
    $subscription = null;
    $user = null;
}

// Determine subscription status
$status = $_GET['status'] ?? $_SESSION['subscription_status'] ?? 'no_subscription';

if ($subscription && is_array($subscription)) {
    try {
        $expiry = isset($subscription['expires_at']) ? new DateTime($subscription['expires_at']) : null;
        $now = new DateTime();
        
        if (isset($subscription['suspended']) && $subscription['suspended'] == 1) {
            $status = 'suspended';
        } elseif ($expiry && $expiry < $now) {
            $status = 'expired';
        } elseif ($expiry) {
            $status = 'active';
        }
    } catch (Exception $e) {
        error_log("Date error: " . $e->getMessage());
    }
}

/**
 * Enhanced function to hide phone number with better formatting
 * Shows only first 3 and last 3 digits with xxx in between
 * Handles various phone number formats (with/without country code, spaces, dashes)
 */
function hidePhoneNumber($phone) {
    if (!$phone) return 'Not provided';
    
    // Remove all non-numeric characters
    $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
    
    // If after cleaning we have less than 9 digits, return as is with notice
    if (strlen($cleanPhone) < 9) {
        return 'Invalid format';
    }
    
    // Handle different phone number lengths
    $length = strlen($cleanPhone);
    
    if ($length >= 9) {
        // For Kenyan numbers: 07XX XXX XXX or 2547XX XXX XXX
        if ($length == 10) { // Format: 0712345678
            return substr($cleanPhone, 0, 3) . 'xxx' . substr($cleanPhone, -3);
        } elseif ($length == 12) { // Format: 254712345678
            return substr($cleanPhone, 0, 3) . 'xxx' . substr($cleanPhone, -3);
        } elseif ($length == 9) { // Format: 712345678
            return '0' . substr($cleanPhone, 0, 2) . 'xxx' . substr($cleanPhone, -3);
        } else {
            // Generic masking for other lengths
            return substr($cleanPhone, 0, 3) . 'xxx' . substr($cleanPhone, -3);
        }
    }
    
    return $phone;
}

// Format date
function formatDate($date) {
    if (!$date) return 'N/A';
    try {
        return date('M j, Y', strtotime($date));
    } catch (Exception $e) {
        return 'N/A';
    }
}

// Get user's full name
function getUserFullName($user) {
    if (!$user) return 'N/A';
    $parts = [];
    if (!empty($user['firstname'])) $parts[] = $user['firstname'];
    if (!empty($user['secondname'])) $parts[] = $user['secondname'];
    if (!empty($user['lastname'])) $parts[] = $user['lastname'];
    return !empty($parts) ? implode(' ', $parts) : 'N/A';
}

// Calculate payment details for display
function getPaymentDescription($payment_amount, $onboarding_fee, $per_student_per_term, $current_students, $trial_active, $days_since_registration, $trial_days) {
    if ($trial_active) {
        $days_left = $trial_days - $days_since_registration;
        return [
            'amount' => 0,
            'description' => "Free Trial Period",
            'details' => "You are on a {$trial_days}-day free trial. Payment of KES " . number_format($onboarding_fee, 2) . " onboarding fee will be required after {$days_left} days.",
            'show_payment' => false
        ];
    } elseif ($payment_amount == $onboarding_fee) {
        return [
            'amount' => $onboarding_fee,
            'description' => "One-time Onboarding Fee",
            'details' => "After your {$trial_days}-day free trial, a one-time onboarding fee of KES " . number_format($onboarding_fee, 2) . " is required. This activates your account for 90 days.",
            'show_payment' => true
        ];
    } elseif ($payment_amount > 0) {
        $total = $current_students * $per_student_per_term;
        return [
            'amount' => $total,
            'description' => "Term Subscription Fee",
            'details' => "KES " . number_format($per_student_per_term, 2) . " per student per term × {$current_students} active students = KES " . number_format($total, 2),
            'show_payment' => true
        ];
    } else {
        return [
            'amount' => 0,
            'description' => "No Payment Required",
            'details' => "Your subscription is currently active.",
            'show_payment' => false
        ];
    }
}

$payment_info = getPaymentDescription(
    $payment_amount ?? 0, 
    $onboarding_fee, 
    $per_student_per_term, 
    $current_students ?? 0, 
    $trial_active ?? false, 
    $days_since_registration ?? 0, 
    $trial_days
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription Required - EduScore</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="images/logo.png" />
 <link rel="apple-touch-icon" href="images/logo.png">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #e6f3ff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            position: relative;
        }

        .subscription-container {
            max-width: 480px;
            width: 100%;
            margin: 0 auto;
        }

        .subscription-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 8px 24px rgba(0, 100, 200, 0.15);
            border: 1px solid #d4e8ff;
            max-height: 90vh;
            overflow-y: auto;
        }

        .subscription-card::-webkit-scrollbar {
            width: 4px;
        }

        .subscription-card::-webkit-scrollbar-track {
            background: #f0f7ff;
        }

        .subscription-card::-webkit-scrollbar-thumb {
            background: #99c2ff;
            border-radius: 4px;
        }

        .status-icon {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.75rem;
        }

        .status-icon.expired {
            background: #fff3cd;
            color: #856404;
        }

        .status-icon.suspended {
            background: #fff3cd;
            color: #856404;
        }

        .status-icon.no_subscription {
            background: #d4e8ff;
            color: #0066cc;
        }

        .status-icon.active {
            background: #d4e8ff;
            color: #0066cc;
        }

        h1 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e3a5f;
            text-align: center;
            margin-bottom: 0.25rem;
        }

        .school-name {
            text-align: center;
            color: #5a6d86;
            font-size: 0.8rem;
            margin-bottom: 1rem;
        }

        .status-message {
            background: #f8fbff;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            border-left: 4px solid;
            font-size: 0.9rem;
        }

        .status-message.expired {
            border-left-color: #ffc107;
        }

        .status-message.suspended {
            border-left-color: #ffc107;
        }

        .status-message.no_subscription {
            border-left-color: #66b0ff;
        }

        .status-message p {
            color: #2c3e5c;
            line-height: 1.4;
        }

        .section-title {
            color: #1e3a5f;
            font-size: 0.95rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .section-title i {
            color: #66b0ff;
        }

        .school-details {
            background: #f8fbff;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            border: 1px solid #d4e8ff;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #d4e8ff;
            font-size: 0.85rem;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            color: #5a6d86;
            font-weight: 500;
        }

        .detail-value {
            font-weight: 600;
            color: #1e3a5f;
        }

        .user-info {
            background: #d4e8ff;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .user-detail {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.4rem 0;
            color: #1e3a5f;
            font-size: 0.85rem;
        }

        .user-detail i {
            width: 18px;
            color: #0066cc;
        }

        .phone-masked {
            font-family: 'Courier New', monospace;
            font-weight: 600;
            color: #0066cc;
            background: rgba(0, 102, 204, 0.1);
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            letter-spacing: 1px;
        }

        .masked-hint {
            font-size: 0.7rem;
            color: #5a6d86;
            margin-left: 0.5rem;
            font-style: italic;
        }

        .error-box {
            background: #fee2e2;
            border-left: 4px solid #dc2626;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            color: #991b1b;
            font-size: 0.9rem;
        }

        .btn {
            width: 100%;
            padding: 0.85rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
            margin-bottom: 0.75rem;
        }

        .btn-activation {
            background: #66b0ff;
            color: white;
        }

        .btn-activation:hover {
            background: #4d94e6;
        }

        .btn-primary {
            background: #1e3a5f;
            color: white;
        }

        .btn-primary:hover {
            background: #15304d;
        }

        .btn-mpesa {
            background: #1e3a5f;
            color: white;
            margin-top: 1rem;
        }

        .btn-mpesa:hover {
            background: #15304d;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(21, 48, 77, 0.2);
        }

        .btn-back {
            background: #6c757d;
            color: white;
            margin-top: 0.5rem;
        }

        .btn-back:hover {
            background: #5a6268;
        }

        .contact-support {
            text-align: center;
            padding-top: 1rem;
            border-top: 1px solid #d4e8ff;
        }

        .contact-support p {
            color: #5a6d86;
            font-size: 0.8rem;
        }

        .contact-support a {
            color: #66b0ff;
            text-decoration: none;
            font-weight: 500;
        }

        /* Toast Styles */
        .toast-container {
            position: fixed;
            top: 100px;
            right: 1.5rem;
            z-index: 99999;
            pointer-events: none;
        }

        @media (max-width: 768px) {
            .toast-container {
                top: 70px;
                right: 1rem;
                left: 1rem;
                max-width: calc(100% - 2rem);
            }
            
            .toast {
                min-width: auto;
                max-width: 100%;
                width: 100%;
            }
        }

        .toast {
            background: white;
            border-radius: 12px;
            padding: 1rem 1.25rem;
            margin-bottom: 0.75rem;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 1rem;
            border-left: 4px solid;
            animation: slideInToast 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            min-width: 300px;
            max-width: 400px;
            pointer-events: auto;
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .toast-success { 
            border-left-color: #22c55e;
            background: rgba(240, 253, 244, 0.98);
        }
        .toast-error { 
            border-left-color: #ef4444;
            background: rgba(254, 242, 242, 0.98);
        }
        .toast-warning { 
            border-left-color: #f59e0b;
            background: rgba(255, 251, 235, 0.98);
        }
        .toast-info { 
            border-left-color: #3b82f6;
            background: rgba(239, 246, 255, 0.98);
        }

        .toast::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            height: 3px;
            width: 100%;
            background: rgba(0,0,0,0.1);
            animation: progressBar 3s linear forwards;
        }

        @keyframes progressBar {
            from { width: 100%; }
            to { width: 0%; }
        }

        @keyframes slideInToast {
            from {
                opacity: 0;
                transform: translateX(100%) translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0) translateY(0);
            }
        }

        @keyframes slideOutToast {
            from {
                opacity: 1;
                transform: translateX(0) translateY(0);
            }
            to {
                opacity: 0;
                transform: translateX(100%) translateY(-20px);
            }
        }

        .toast.toast-hiding {
            animation: slideOutToast 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55) forwards;
        }

        .toast i {
            font-size: 1.5rem;
        }

        .toast-success i { color: #22c55e; }
        .toast-error i { color: #ef4444; }
        .toast-warning i { color: #f59e0b; }
        .toast-info i { color: #3b82f6; }

        .toast-content {
            flex: 1;
            font-weight: 500;
            font-size: 0.95rem;
            color: #1e293b;
        }

        .toast-close {
            background: none;
            border: none;
            color: #64748b;
            cursor: pointer;
            font-size: 1rem;
            padding: 0.25rem;
            transition: all 0.2s ease;
            pointer-events: auto;
            border-radius: 4px;
        }

        .toast-close:hover {
            color: #0f172a;
            transform: scale(1.1);
            background: rgba(0, 0, 0, 0.05);
        }

        .toast-container .toast {
            margin-bottom: 0.75rem;
            transform-origin: top right;
        }

        .toast-container .toast:last-child {
            margin-bottom: 0;
        }

        .toast {
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.3);
            align-items: center;
            justify-content: center;
            z-index: 1001;
        }

        .modal.show {
            display: flex;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            max-width: 440px;
            width: 90%;
            max-height: 85vh;
            overflow-y: auto;
            position: relative;
            border: 1px solid #d4e8ff;
            box-shadow: 0 12px 32px rgba(0, 80, 160, 0.2);
            animation: slideUpModal 0.3s ease;
        }

        @keyframes slideUpModal {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #d4e8ff;
        }

        .modal-header h2 {
            font-size: 1.25rem;
            color: #1e3a5f;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #5a6d86;
            transition: color 0.2s;
        }

        .close-modal:hover {
            color: #1e3a5f;
        }

        .modal-body {
            margin-bottom: 1rem;
        }

        .info-box {
            background: #f8fbff;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            border: 1px solid #d4e8ff;
        }

        .info-item {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            padding: 0.5rem 0;
            border-bottom: 1px solid #d4e8ff;
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-item i {
            color: #66b0ff;
            font-size: 1rem;
            width: 20px;
        }

        .info-item .content {
            flex: 1;
        }

        .info-item .label {
            font-size: 0.75rem;
            color: #5a6d86;
            margin-bottom: 0.1rem;
        }

        .info-item .value {
            font-weight: 600;
            color: #1e3a5f;
            font-size: 0.9rem;
        }

        /* Payment Info Styles */
        .payment-info-box {
            background: #fff3cd;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border: 1px solid #ffe070;
        }

        .payment-amount {
            font-size: 2rem;
            font-weight: 700;
            color: #1e3a5f;
            text-align: center;
            margin-bottom: 0.5rem;
        }

        .payment-amount small {
            font-size: 0.9rem;
            font-weight: normal;
            color: #5a6d86;
        }

        .payment-description {
            font-size: 0.9rem;
            color: #856404;
            margin-bottom: 0.5rem;
            text-align: center;
            font-weight: 500;
        }

        .payment-details {
            background: white;
            border-radius: 8px;
            padding: 0.75rem;
            font-size: 0.85rem;
            color: #2c3e5c;
            border: 1px solid #ffe070;
        }

        .payment-details i {
            color: #f59e0b;
            margin-right: 0.5rem;
        }

        .trial-badge {
            background: #d4e8ff;
            color: #0066cc;
            padding: 0.25rem 0.75rem;
            border-radius: 100px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 0.5rem;
        }

        .warning-message {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 0.85rem;
            border-radius: 8px;
            margin: 1rem 0;
            font-size: 0.85rem;
            color: #856404;
        }

        .checkbox-group {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            margin: 1rem 0;
        }

        .checkbox-group input[type="checkbox"] {
            margin-top: 0.2rem;
            width: 16px;
            height: 16px;
            cursor: pointer;
            accent-color: #66b0ff;
        }

        .checkbox-group label {
            color: #2c3e5c;
            font-size: 0.85rem;
            line-height: 1.4;
            cursor: pointer;
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .text-muted {
            color: #5a6d86;
            font-size: 0.75rem;
            margin-top: 0.25rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            font-size: 0.8rem;
            font-weight: 500;
            color: #1e3a5f;
            margin-bottom: 0.35rem;
        }

        .form-control {
            width: 100%;
            padding: 0.65rem;
            border: 2px solid #d4e8ff;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: all 0.2s ease;
            font-family: 'Inter', sans-serif;
        }

        .form-control:focus {
            outline: none;
            border-color: #66b0ff;
            background-color: #f8fbff;
        }

        .phone-privacy-note {
            font-size: 0.7rem;
            color: #5a6d86;
            margin-top: 0.25rem;
            text-align: right;
        }

        /* STK Push Modal Styles */
        .stk-modal .modal-content {
            max-width: 400px;
        }

        .stk-header {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .stk-header i {
            font-size: 3rem;
            color: #1e3a5f;
            margin-bottom: 0.5rem;
        }

        .stk-header h3 {
            font-size: 1.25rem;
            color: #1e3a5f;
            margin-bottom: 0.25rem;
        }

        .stk-header p {
            color: #5a6d86;
            font-size: 0.875rem;
        }

        .stk-amount-display {
            background: #f8fbff;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            text-align: center;
            border: 2px solid #d4e8ff;
        }

        .stk-amount-label {
            font-size: 0.8rem;
            color: #5a6d86;
            margin-bottom: 0.25rem;
        }

        .stk-amount-value {
            font-size: 2rem;
            font-weight: 700;
            color: #1e3a5f;
        }

        .stk-amount-value i {
            font-size: 1.5rem;
            color: #f59e0b;
            margin-right: 0.5rem;
        }

        .stk-input-group {
            margin-bottom: 1.5rem;
        }

        .stk-input-group label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: #1e3a5f;
            margin-bottom: 0.5rem;
        }

        .stk-input-group label i {
            color: #66b0ff;
            margin-right: 0.25rem;
        }

        .stk-input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .stk-input-prefix {
            position: absolute;
            left: 1rem;
            color: #64748b;
            font-weight: 500;
            background: #f1f5f9;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            z-index: 1;
        }

        .stk-input-field {
            width: 100%;
            padding: 0.85rem 1rem 0.85rem 5rem;
            border: 2px solid #d4e8ff;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.2s ease;
            background: white;
        }

        .stk-input-field:focus {
            outline: none;
            border-color: #66b0ff;
            box-shadow: 0 0 0 3px rgba(102, 176, 255, 0.1);
        }

        .stk-input-field::placeholder {
            color: #94a3b8;
            font-size: 0.875rem;
        }

        .stk-helper-text {
            font-size: 0.7rem;
            color: #64748b;
            margin-top: 0.25rem;
            margin-left: 0.5rem;
        }

        .btn-stk {
            width: 100%;
            padding: 1rem;
            background: #1e3a5f;
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            transition: all 0.2s ease;
            margin-bottom: 1rem;
        }

        .btn-stk:hover:not(:disabled) {
            background: #15304d;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(21, 48, 77, 0.2);
        }

        .btn-stk:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-stk i {
            font-size: 1.1rem;
        }

        .stk-footer {
            text-align: center;
            font-size: 0.75rem;
            color: #94a3b8;
        }

        .stk-footer i {
            color: #66b0ff;
        }

        /* Loading Overlay */
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            z-index: 10000;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 1rem;
        }

        .loading-overlay.show {
            display: flex;
        }

        .loading-spinner {
            width: 60px;
            height: 60px;
            border: 5px solid #e2e8f0;
            border-top-color: #1e3a5f;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        .loading-text {
            color: #1e3a5f;
            font-size: 1.1rem;
            font-weight: 500;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Floating Contact Button */
        .floating-contact {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 1000;
        }

        .contact-button {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #25D366;
            color: white;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            box-shadow: 0 4px 12px rgba(37, 211, 102, 0.3);
            transition: all 0.3s ease;
            animation: pulse 2s infinite;
        }

        .contact-button:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 16px rgba(37, 211, 102, 0.4);
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(37, 211, 102, 0.7);
            }
            70% {
                box-shadow: 0 0 0 15px rgba(37, 211, 102, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(37, 211, 102, 0);
            }
        }

        /* Contact Modal */
        .contact-modal .modal-content {
            max-width: 380px;
        }

        .contact-info {
            padding: 1rem 0;
        }

        .contact-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            margin-bottom: 1rem;
            background: #f8fbff;
            border-radius: 12px;
            border: 1px solid #d4e8ff;
            transition: transform 0.2s ease;
        }

        .contact-item:hover {
            transform: translateX(5px);
            background: #e6f3ff;
        }

        .contact-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .contact-icon.email {
            background: #ea4335;
            color: white;
        }

        .contact-icon.phone {
            background: #34b7f1;
            color: white;
        }

        .contact-icon.whatsapp {
            background: #25D366;
            color: white;
        }

        .contact-details {
            flex: 1;
        }

        .contact-label {
            font-size: 0.8rem;
            color: #5a6d86;
            margin-bottom: 0.25rem;
        }

        .contact-value {
            font-weight: 600;
            color: #1e3a5f;
            font-size: 0.95rem;
        }

        .whatsapp-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: #25D366;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            margin-top: 1rem;
            transition: background 0.2s ease;
            border: none;
            cursor: pointer;
            width: 100%;
            justify-content: center;
        }

        .whatsapp-btn:hover {
            background: #128C7E;
        }

        @media (max-width: 768px) {
            .floating-contact {
                bottom: 20px;
                right: 20px;
            }
            
            .contact-button {
                width: 50px;
                height: 50px;
                font-size: 24px;
            }
        }

        @media (max-width: 480px) {
            .subscription-card {
                padding: 1rem;
            }
            
            .detail-row {
                flex-direction: column;
                gap: 0.25rem;
            }
            
            .modal-content {
                padding: 1rem;
            }
            
            .payment-amount {
                font-size: 1.5rem;
            }
            
            .stk-amount-value {
                font-size: 1.5rem;
            }
        }

        .mt-3 {
            margin-top: 1rem;
        }
        
        .mt-2 {
            margin-top: 0.5rem;
        }
        
        .text-center {
            text-align: center;
        }
        
        .w-100 {
            width: 100%;
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
        <div class="loading-text">Processing payment, please wait...</div>
    </div>

    <!-- Floating Contact Button -->
    <div class="floating-contact">
        <button class="contact-button" onclick="openContactModal()">
            <i class="fab fa-whatsapp"></i>
        </button>
    </div>

    <div class="subscription-container">
        <div class="subscription-card">
            <?php
            $icon_class = '';
            $title = '';
            $message = '';
            
            switch($status) {
                case 'expired':
                    $icon_class = 'expired';
                    $title = 'Subscription Expired';
                    $message = 'Your subscription has expired. Please request activation to continue.';
                    break;
                case 'suspended':
                    $icon_class = 'suspended';
                    $title = 'Subscription Suspended';
                    $message = 'Your subscription has been suspended. Please contact support.';
                    break;
                case 'no_subscription':
                    $icon_class = 'no_subscription';
                    $title = 'No Active Subscription';
                    $message = 'You don\'t have an active subscription. Please request activation.';
                    break;
                default:
                    $icon_class = 'expired';
                    $title = 'Subscription Required';
                    $message = 'Please request activation to continue using EduScore.';
            }
            ?>
            
            <div class="status-icon <?php echo $icon_class; ?>">
                <i class="fas <?php echo $status === 'expired' ? 'fa-clock' : ($status === 'suspended' ? 'fa-pause-circle' : 'fa-credit-card'); ?>"></i>
            </div>
            
            <h1><?php echo $title; ?></h1>
            <div class="school-name"><?php echo htmlspecialchars($school_data['school_name'] ?? 'School Name'); ?></div>
            
            <div class="status-message <?php echo $icon_class; ?>">
                <p><i class="fas fa-info-circle" style="margin-right: 0.5rem; font-size: 0.85rem;"></i>
                <?php echo $message; ?></p>
                <?php if ($status === 'expired' && isset($subscription['expires_at']) && $subscription['expires_at']): ?>
                <p style="margin-top: 0.5rem; font-size: 0.8rem;">
                    <strong>Expired:</strong> <?php echo formatDate($subscription['expires_at']); ?>
                </p>
                <?php endif; ?>
            </div>
            
            <!-- School Details Section -->
            <div class="school-details">
                <div class="section-title">
                    <i class="fas fa-school"></i> School Information
                </div>
                <div class="detail-row">
                    <span class="detail-label">School Name:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($school_data['school_name'] ?? 'Not Available'); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Email:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($school_data['school_email'] ?? 'Not Available'); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Phone:</span>
                    <span class="detail-value">
                        <span class="phone-masked"><?php echo hidePhoneNumber($school_data['school_phone'] ?? ''); ?></span>
                        <span class="masked-hint">(privacy protected)</span>
                    </span>
                </div>
                <?php if (!empty($school_data['county'])): ?>
                <div class="detail-row">
                    <span class="detail-label">County:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($school_data['county']); ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($school_data['address'])): ?>
                <div class="detail-row">
                    <span class="detail-label">Address:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($school_data['address']); ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($school_data['principal_name'])): ?>
                <div class="detail-row">
                    <span class="detail-label">Principal:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($school_data['principal_name']); ?></span>
                </div>
                <?php endif; ?>
                <div class="detail-row">
                    <span class="detail-label">Registered:</span>
                    <span class="detail-value"><?php echo formatDate($school_data['created_at'] ?? ''); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Active Students:</span>
                    <span class="detail-value"><?php echo number_format($current_students ?? 0); ?></span>
                </div>
                <div class="phone-privacy-note">
                    <i class="fas fa-shield-alt"></i> Phone numbers are masked for privacy (showing only first 3 and last 3 digits)
                </div>
            </div>
            
            <!-- Current User Information -->
            <?php if ($user && is_array($user)): ?>
            <div class="user-info">
                <div class="section-title" style="color: #0066cc;">
                    <i class="fas fa-user-circle"></i> Logged in as
                </div>
                <div class="user-detail">
                    <i class="fas fa-user"></i>
                    <span><?php echo htmlspecialchars(getUserFullName($user)); ?></span>
                </div>
                <div class="user-detail">
                    <i class="fas fa-envelope"></i>
                    <span><?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?></span>
                </div>
                <div class="user-detail">
                    <i class="fas fa-phone"></i>
                    <span class="phone-masked"><?php echo hidePhoneNumber($user['phonenumber'] ?? ''); ?></span>
                    <span class="masked-hint">(privacy protected)</span>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($subscription && is_array($subscription) && isset($subscription['suspended']) && $subscription['suspended'] == 1 && !empty($subscription['suspended_reason'])): ?>
            <div class="school-details">
                <div class="detail-row">
                    <span class="detail-label">Suspension Reason:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($subscription['suspended_reason']); ?></span>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
            <div class="error-box">
                <i class="fas fa-exclamation-triangle" style="margin-right: 0.5rem;"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
            <?php endif; ?>
            
            <!-- Request Activation Button -->
            <button onclick="openActivationModal()" class="btn btn-activation">
                <i class="fas fa-key"></i> Request Activation
            </button>
            
            <?php if ($payment_info['amount'] > 0): ?>
            <!-- Pay via M-PESA Button -->
            <button onclick="openSTKPushModal(<?php echo $payment_info['amount']; ?>)" class="btn btn-mpesa">
                <i class="fas fa-mobile-alt"></i> Pay via M-PESA (KES <?php echo number_format($payment_info['amount'], 2); ?>)
            </button>
            <?php endif; ?>
            
            <!-- Back to Login Button -->
            <button onclick="goToLogin()" class="btn btn-back">
                <i class="fas fa-arrow-left"></i> Back to Login
            </button>
            
            <div class="contact-support">
                <p>Need help? <a href="mailto:support@eduscore.com">Contact Support</a></p>
            </div>
        </div>
    </div>
    
    <!-- Activation Request Modal -->
    <div class="modal" id="activationModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-key" style="margin-right: 0.5rem; color: #66b0ff;"></i>Request Activation</h2>
                <button class="close-modal" onclick="closeActivationModal()">&times;</button>
            </div>
            
            <div class="modal-body">
                <div class="info-box">
                    <div class="info-item">
                        <i class="fas fa-school"></i>
                        <div class="content">
                            <div class="label">School</div>
                            <div class="value"><?php echo htmlspecialchars($school_data['school_name'] ?? 'N/A'); ?></div>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <i class="fas fa-user"></i>
                        <div class="content">
                            <div class="label">Requesting User</div>
                            <div class="value"><?php echo htmlspecialchars(getUserFullName($user)); ?></div>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <i class="fas fa-phone"></i>
                        <div class="content">
                            <div class="label">Contact Number</div>
                            <div class="value">
                                <span class="phone-masked"><?php echo hidePhoneNumber($user['phonenumber'] ?? ''); ?></span>
                            </div>
                            <div class="text-muted">
                                <i class="fas fa-lock" style="font-size: 0.7rem;"></i> 
                                Full number will be visible to admin only
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <i class="fas fa-envelope"></i>
                        <div class="content">
                            <div class="label">Email</div>
                            <div class="value"><?php echo htmlspecialchars($user['email'] ?? $school_data['school_email'] ?? 'N/A'); ?></div>
                        </div>
                    </div>
                </div>
                
                <!-- Payment Information Section -->
                <div class="payment-info-box">
                    <?php if (isset($trial_active) && $trial_active): ?>
                        <div class="trial-badge">
                            <i class="fas fa-clock"></i> Free Trial Active (<?php echo $trial_days - $days_since_registration; ?> days remaining)
                        </div>
                    <?php endif; ?>
                    
                    <div class="payment-amount">
                        <?php if ($payment_info['amount'] > 0): ?>
                            KES <?php echo number_format($payment_info['amount'], 2); ?>
                            <small>due</small>
                        <?php else: ?>
                            <i class="fas fa-check-circle" style="color: #22c55e; font-size: 2rem;"></i>
                        <?php endif; ?>
                    </div>
                    
                    <div class="payment-description">
                        <?php echo $payment_info['description']; ?>
                    </div>
                    
                    <div class="payment-details">
                        <i class="fas fa-info-circle"></i>
                        <?php echo $payment_info['details']; ?>
                    </div>
                    
                    <?php if ($payment_info['amount'] == $onboarding_fee): ?>
                        <div class="payment-details" style="margin-top: 0.5rem; background: #e6f3ff; border-color: #66b0ff;">
                            <i class="fas fa-calendar-alt" style="color: #0066cc;"></i>
                            After payment, your subscription will be active for 90 days. Thereafter, you'll be charged KES <?php echo number_format($per_student_per_term, 2); ?> per student per term.
                        </div>
                    <?php elseif ($payment_info['amount'] > 0 && $payment_info['amount'] != $onboarding_fee): ?>
                        <div class="payment-details" style="margin-top: 0.5rem; background: #e6f3ff; border-color: #66b0ff;">
                            <i class="fas fa-calendar-alt" style="color: #0066cc;"></i>
                            This payment covers one term (90 days). You will be billed again at the start of the next term based on your active student count.
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="warning-message">
                    <i class="fas fa-info-circle" style="margin-right: 0.5rem;"></i>
                    By requesting activation, you confirm that the information above is correct and you are authorized to act on behalf of this school.
                    <?php if ($payment_info['amount'] > 0): ?>
                        <strong>You will be required to pay the amount shown above upon approval.</strong>
                    <?php endif; ?>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" id="confirmCheck" onclick="toggleSubmitButton()">
                    <label for="confirmCheck">
                        I confirm that the information provided is accurate and I understand that 
                        <?php if ($payment_info['amount'] > 0): ?>
                            payment of KES <?php echo number_format($payment_info['amount'], 2); ?> will be required upon approval.
                        <?php else: ?>
                            activation may take 1-2 business days.
                        <?php endif; ?>
                    </label>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-comment"></i> Additional Notes (Optional)</label>
                    <textarea id="additionalNotes" class="form-control" rows="2" placeholder="Any additional information for the admin..."></textarea>
                </div>
                
                <div class="phone-privacy-note">
                    <i class="fas fa-shield-alt"></i> Your phone number is masked for privacy. Only administrators can see the full number.
                </div>
            </div>
            
            <button id="submitActivationBtn" class="btn btn-primary" onclick="submitActivationRequest()" disabled>
                <i class="fas fa-paper-plane"></i> Submit Activation Request
            </button>
        </div>
    </div>
    
    <!-- STK Push Payment Modal -->
    <div class="modal" id="stkPushModal">
        <div class="modal-content stk-modal">
            <div class="modal-header">
                <h2><i class="fas fa-mobile-alt" style="margin-right: 0.5rem; color: #66b0ff;"></i>M-PESA Payment</h2>
                <button class="close-modal" onclick="closeSTKPushModal()">&times;</button>
            </div>
            
            <div class="modal-body">
                <div class="stk-header">
                    <i class="fas fa-bolt"></i>
                    <h3>STK Push</h3>
                    <p>Enter your M-PESA registered phone number to receive payment prompt</p>
                </div>
                
                <div class="stk-amount-display">
                    <div class="stk-amount-label">Amount to Pay</div>
                    <div class="stk-amount-value" id="stkAmount">KES 0.00</div>
                </div>
                
                <form id="stkForm" onsubmit="return initiateSTKPush(event)">
                    <div class="stk-input-group">
                        <label for="stkPhoneNumber">
                            <i class="fas fa-mobile-alt"></i> M-PESA Phone Number
                        </label>
                        <div class="stk-input-wrapper">
                            <span class="stk-input-prefix">+254</span>
                            <input type="tel" id="stkPhoneNumber" class="stk-input-field" 
                                   placeholder="712 345 678" 
                                   pattern="[0-9]{9}"
                                   maxlength="9"
                                   title="Please enter your 9-digit M-PESA phone number without the country code"
                                   value="<?php echo preg_replace('/[^0-9]/', '', $user['phonenumber'] ?? ''); ?>">
                        </div>
                        <div class="stk-helper-text">
                            <i class="fas fa-info-circle"></i> Enter your M-PESA registered phone number (e.g., 712345678)
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-stk" id="stkPushBtn">
                        <i class="fas fa-bolt"></i> Send STK Push
                    </button>
                </form>
                
                <div class="stk-footer">
                    <i class="fas fa-lock"></i> Secured by Safaricom M-PESA
                </div>
            </div>
        </div>
    </div>
    
    <!-- Payment Status Check Modal -->
    <div class="modal" id="paymentStatusModal">
        <div class="modal-content" style="max-width: 380px; text-align: center;">
            <div class="modal-header" style="border-bottom: none;">
                <h2><i class="fas fa-clock" style="color: #f59e0b;"></i> Payment Processing</h2>
                <button class="close-modal" onclick="closePaymentStatusModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div style="margin: 2rem 0;">
                    <div class="loading-spinner" style="margin: 0 auto 1rem; width: 50px; height: 50px;"></div>
                    <p style="color: #1e3a5f; font-weight: 500; margin-bottom: 0.5rem;">Waiting for payment confirmation...</p>
                    <p style="color: #5a6d86; font-size: 0.9rem;">Please complete the payment on your phone</p>
                </div>
                <div id="paymentStatusMessage" style="background: #f8fbff; padding: 0.75rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.9rem;">
                    <i class="fas fa-hourglass-half" style="color: #f59e0b; margin-right: 0.5rem;"></i>
                    <span>Checking status...</span>
                </div>
                <div id="paymentStatusActions" class="mt-2"></div>
                <button onclick="closePaymentStatusModal()" class="btn" style="background: #e2e8f0; color: #1e293b; margin-bottom: 0;">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
        </div>
    </div>
    
    <!-- Contact Modal -->
    <div class="modal contact-modal" id="contactModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-headset" style="margin-right: 0.5rem; color: #25D366;"></i>Contact Support</h2>
                <button class="close-modal" onclick="closeContactModal()">&times;</button>
            </div>
            
            <div class="modal-body">
                <div class="contact-info">
                    <!-- Email -->
                    <div class="contact-item">
                        <div class="contact-icon email">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="contact-details">
                            <div class="contact-label">Email</div>
                            <div class="contact-value">kymtechnologiesltd@gmail.com</div>
                        </div>
                    </div>
                    
                    <!-- Phone -->
                    <div class="contact-item">
                        <div class="contact-icon phone">
                            <i class="fas fa-phone-alt"></i>
                        </div>
                        <div class="contact-details">
                            <div class="contact-label">Phone / WhatsApp</div>
                            <div class="contact-value">+254 799 115 282</div>
                        </div>
                    </div>
                    
                    <!-- WhatsApp Button -->
                    <a href="https://wa.me/254799115282?text=Hello%20I%20need%20help%20with%20my%20EduScore%20subscription" 
                       target="_blank" 
                       class="whatsapp-btn">
                        <i class="fab fa-whatsapp"></i>
                        Send Message on WhatsApp
                    </a>
                    
                    <p class="text-muted mt-2" style="text-align: center; font-size: 0.8rem;">
                        <i class="fas fa-clock"></i> Response time: Within 24 hours
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="toast-container" id="toastContainer"></div>
    
    <script>
        // =========================================================================
        // INTELLIGENT PAYMENT CHECKER
        // =========================================================================
        
        let currentPaymentAmount = 0;
        let currentPaymentReference = null;
        let paymentCheckInterval = null;
        let paymentCheckCount = 0;
        const MAX_PAYMENT_CHECKS = 36; // Check for 6 minutes (36 * 10 seconds)
        const CHECK_INTERVAL = 10000; // 10 seconds
        let retryButtonAdded = false;

        // =========================================================================
        // NAVIGATION FUNCTIONS
        // =========================================================================
        
        function goToLogin() {
            // Clear session and redirect to login
            window.location.href = 'logout.php';
        }

        // =========================================================================
        // CONTACT MODAL FUNCTIONS
        // =========================================================================
        
        function openContactModal() {
            document.getElementById('contactModal').classList.add('show');
            document.body.style.overflow = 'hidden';
        }
        
        function closeContactModal() {
            document.getElementById('contactModal').classList.remove('show');
            document.body.style.overflow = 'auto';
        }

        // =========================================================================
        // MODAL FUNCTIONS
        // =========================================================================
        
        function openActivationModal() {
            document.getElementById('activationModal').classList.add('show');
            document.body.style.overflow = 'hidden';
        }
        
        function closeActivationModal() {
            document.getElementById('activationModal').classList.remove('show');
            document.body.style.overflow = 'auto';
            document.getElementById('confirmCheck').checked = false;
            document.getElementById('additionalNotes').value = '';
            toggleSubmitButton();
        }
        
        function openSTKPushModal(amount) {
            currentPaymentAmount = amount;
            document.getElementById('stkAmount').innerHTML = `KES ${amount.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
            document.getElementById('stkPushModal').classList.add('show');
            document.body.style.overflow = 'hidden';
        }
        
        function closeSTKPushModal() {
            document.getElementById('stkPushModal').classList.remove('show');
            document.body.style.overflow = 'auto';
            document.getElementById('stkPhoneNumber').value = '<?php echo preg_replace('/[^0-9]/', '', $user['phonenumber'] ?? ''); ?>';
            
            // Clear any pending payment checks
            if (paymentCheckInterval) {
                clearInterval(paymentCheckInterval);
                paymentCheckInterval = null;
            }
        }
        
        function openPaymentStatusModal(reference) {
            currentPaymentReference = reference;
            paymentCheckCount = 0;
            retryButtonAdded = false;
            
            // Clear actions div
            document.getElementById('paymentStatusActions').innerHTML = '';
            
            document.getElementById('paymentStatusModal').classList.add('show');
            document.getElementById('loadingOverlay').classList.remove('show');
            
            // Clear any existing interval
            if (paymentCheckInterval) {
                clearInterval(paymentCheckInterval);
            }
            
            // Start checking payment status
            paymentCheckInterval = setInterval(checkPaymentStatus, CHECK_INTERVAL);
            
            // Also check immediately
            setTimeout(checkPaymentStatus, 1000);
        }
        
        function closePaymentStatusModal() {
            document.getElementById('paymentStatusModal').classList.remove('show');
            
            if (paymentCheckInterval) {
                clearInterval(paymentCheckInterval);
                paymentCheckInterval = null;
            }
        }
        
        function toggleSubmitButton() {
            const isChecked = document.getElementById('confirmCheck').checked;
            document.getElementById('submitActivationBtn').disabled = !isChecked;
        }
        
        function showToast(msg, type = 'info') {
            const icons = {
                success: 'fa-check-circle',
                error: 'fa-exclamation-circle',
                warning: 'fa-exclamation-triangle',
                info: 'fa-info-circle'
            };
            
            const toastId = 'toast-' + Date.now();
            const toast = document.createElement('div');
            toast.id = toastId;
            toast.className = `toast toast-${type}`;
            toast.innerHTML = `
                <i class="fas ${icons[type]}"></i>
                <span class="toast-content">${msg}</span>
                <button class="toast-close" onclick="closeToast('${toastId}')">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            document.getElementById('toastContainer').appendChild(toast);
            
            // Auto remove after 3 seconds
            setTimeout(() => {
                closeToast(toastId);
            }, 3000);
        }
        
        function closeToast(toastId) {
            const toast = document.getElementById(toastId);
            if (toast) {
                toast.classList.add('toast-hiding');
                setTimeout(() => {
                    toast.remove();
                }, 300);
            }
        }
        
        function addRetryButton() {
            if (retryButtonAdded) return;
            
            const actionsDiv = document.getElementById('paymentStatusActions');
            const retryBtn = document.createElement('button');
            retryBtn.className = 'btn btn-primary w-100 mt-2';
            retryBtn.innerHTML = '<i class="fas fa-redo"></i> Try Again';
            retryBtn.onclick = function() {
                closePaymentStatusModal();
                openSTKPushModal(currentPaymentAmount);
            };
            actionsDiv.appendChild(retryBtn);
            retryButtonAdded = true;
        }
        
        // =========================================================================
        // ACTIVATION REQUEST
        // =========================================================================
        
        function submitActivationRequest() {
            const submitBtn = document.getElementById('submitActivationBtn');
            const notes = document.getElementById('additionalNotes').value;
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
            
            const requestData = {
                school_id: <?php echo $school_id; ?>,
                teacher_id: <?php echo $teacher_id; ?>,
                notes: notes,
                payment_amount: <?php echo $payment_info['amount'] ?? 0; ?>,
                payment_type: '<?php echo addslashes($payment_info['description']); ?>'
            };
            
            // Send activation request to the new endpoint
            fetch('ajax/request_activation.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(requestData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Activation request submitted successfully! Admin will review your request.', 'success');
                    closeActivationModal();
                } else {
                    showToast(data.message || 'Failed to submit request. Please try again.', 'error');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Activation Request';
                }
            })
            .catch(error => {
                console.error('Activation request error:', error);
                showToast('Network error. Please try again.', 'error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Activation Request';
            });
        }
        
        // =========================================================================
        // STK PUSH INITIATION
        // =========================================================================
        
        function initiateSTKPush(e) {
            e.preventDefault();
            
            const phoneInput = document.getElementById('stkPhoneNumber').value.trim();
            if (!phoneInput) {
                showToast('Please enter your M-PESA phone number', 'warning');
                return false;
            }
            
            // Format phone number
            const phone = '254' + phoneInput.replace(/\D/g, '');
            
            // Validate phone number
            const phoneRegex = /^254[17]\d{8}$/;
            if (!phoneRegex.test(phone)) {
                showToast('Please enter a valid Kenyan phone number (e.g., 712345678)', 'warning');
                return false;
            }
            
            // Disable button and show loading
            const $btn = document.getElementById('stkPushBtn');
            const originalText = $btn.innerHTML;
            $btn.disabled = true;
            $btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
            
            // Show loading overlay
            document.getElementById('loadingOverlay').classList.add('show');
            
            // Create form data
            const formData = new FormData();
            formData.append('phone', phone);
            formData.append('amount', currentPaymentAmount);
            formData.append('school_id', <?php echo $school_id; ?>);
            formData.append('payment_type', '<?php echo addslashes($payment_info['description']); ?>');
            
            // Create abort controller for timeout
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 30000); // 30 second timeout
            
            // Send STK push request
            fetch('includes/stk_push.php', {
                method: 'POST',
                body: formData,
                signal: controller.signal
            })
            .then(async response => {
                clearTimeout(timeoutId);
                
                if (!response.ok) {
                    const text = await response.text();
                    console.error('STK push server error:', response.status, text.substring(0, 200));
                    throw new Error(`Server error: ${response.status}`);
                }
                
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    const text = await response.text();
                    console.error('Non-JSON response from STK push:', text.substring(0, 200));
                    throw new Error('Invalid response format from payment server');
                }
                
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    showToast('STK push sent! Please check your phone and enter your PIN to complete payment.', 'success');
                    
                    // Clear phone input
                    document.getElementById('stkPhoneNumber').value = '';
                    
                    // Close STK modal
                    closeSTKPushModal();
                    
                    // Get reference from response
                    let reference = null;
                    if (data.reference) {
                        reference = data.reference;
                    } else if (data.lipana && data.lipana.reference) {
                        reference = data.lipana.reference;
                    } else if (data.data && data.data.reference) {
                        reference = data.data.reference;
                    } else if (data.checkout_request_id) {
                        reference = data.checkout_request_id;
                    }
                    
                    if (reference) {
                        openPaymentStatusModal(reference);
                    } else {
                        // No reference found, show generic message
                        showToast('Payment initiated. Please check your payment history later.', 'info');
                        document.getElementById('loadingOverlay').classList.remove('show');
                    }
                } else {
                    showToast(data.message || 'STK push failed. Please try again.', 'error');
                    document.getElementById('loadingOverlay').classList.remove('show');
                }
            })
            .catch(error => {
                clearTimeout(timeoutId);
                
                if (error.name === 'AbortError') {
                    showToast('Request timed out. Please check your payment later.', 'warning');
                } else {
                    console.error('STK push error:', error);
                    showToast('Network error. Please check your connection and try again.', 'error');
                }
                
                document.getElementById('loadingOverlay').classList.remove('show');
            })
            .finally(() => {
                $btn.disabled = false;
                $btn.innerHTML = originalText;
            });
            
            return false;
        }
        
        // =========================================================================
        // INTELLIGENT PAYMENT STATUS CHECKER
        // =========================================================================
        
        function checkPaymentStatus() {
            if (!currentPaymentReference) {
                console.error('No payment reference available');
                return;
            }
            
            paymentCheckCount++;
            
            const statusMessage = document.getElementById('paymentStatusMessage');
            statusMessage.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking payment status...';
            
            // Create abort controller for timeout
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 15000); // 15 second timeout
            
            fetch('ajax/check_payment_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ reference: currentPaymentReference }),
                signal: controller.signal
            })
            .then(async response => {
                clearTimeout(timeoutId);
                
                // Check if response is ok
                if (!response.ok) {
                    const text = await response.text();
                    console.error('Server returned error:', response.status, text.substring(0, 200));
                    throw new Error(`Server error: ${response.status}`);
                }
                
                // Try to parse JSON
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    const text = await response.text();
                    console.error('Non-JSON response:', text.substring(0, 200));
                    throw new Error('Invalid response format');
                }
                
                return response.json();
            })
            .then(data => {
                console.log('Payment status response:', data);
                
                if (!data.success) {
                    throw new Error(data.message || 'Unknown error');
                }
                
                // Handle different statuses based on the action
                switch(data.action) {
                    case 'redirect':
                        // Payment successful - redirect to dashboard
                        statusMessage.innerHTML = '<i class="fas fa-check-circle" style="color: #22c55e;"></i> Payment completed! Redirecting...';
                        
                        clearInterval(paymentCheckInterval);
                        paymentCheckInterval = null;
                        
                        showToast('Payment completed successfully!', 'success');
                        
                        setTimeout(() => {
                            closePaymentStatusModal();
                            window.location.href = data.redirect_url || 'dashboard.php?payment=success';
                        }, 2000);
                        break;
                        
                    case 'stop_checking':
                        // Stop checking - payment failed, not found, or timed out
                        if (data.status === 'failed') {
                            statusMessage.innerHTML = '<i class="fas fa-times-circle" style="color: #ef4444;"></i> Payment failed. Please try again.';
                            showToast('Payment failed. Please try again.', 'error');
                        } else if (data.status === 'not_found') {
                            statusMessage.innerHTML = '<i class="fas fa-question-circle" style="color: #f59e0b;"></i> Payment reference not found.';
                        } else if (data.status === 'timed_out') {
                            statusMessage.innerHTML = '<i class="fas fa-hourglass-end" style="color: #f59e0b;"></i> Payment timed out. Please try again.';
                            showToast('Payment timed out. Please try again.', 'warning');
                        }
                        
                        clearInterval(paymentCheckInterval);
                        paymentCheckInterval = null;
                        document.getElementById('loadingOverlay').classList.remove('show');
                        
                        // Add retry button if allowed
                        if (data.can_retry) {
                            addRetryButton();
                        }
                        break;
                        
                    case 'continue_checking':
                        // Continue checking but update message
                        let message = data.message;
                        let minutesElapsed = data.data?.minutes_elapsed || 0;
                        
                        if (data.warning) {
                            message += ' ⚠️ ' + minutesElapsed + ' minute(s) elapsed';
                            statusMessage.innerHTML = `<i class="fas fa-exclamation-triangle" style="color: #f59e0b;"></i> ${message}`;
                        } else {
                            statusMessage.innerHTML = `<i class="fas fa-hourglass-half" style="color: #f59e0b;"></i> ${message}`;
                        }
                        
                        // Show time elapsed
                        if (minutesElapsed > 0) {
                            statusMessage.innerHTML += `<br><small>Time elapsed: ${minutesElapsed} minute(s)</small>`;
                        }
                        
                        // Stop if max checks reached
                        if (paymentCheckCount >= MAX_PAYMENT_CHECKS) {
                            statusMessage.innerHTML = '<i class="fas fa-hourglass-end" style="color: #f59e0b;"></i> Payment verification timeout. Please check your M-PESA statement.';
                            
                            clearInterval(paymentCheckInterval);
                            paymentCheckInterval = null;
                            document.getElementById('loadingOverlay').classList.remove('show');
                            
                            showToast('Payment verification timeout. Check your M-PESA.', 'warning');
                            
                            // Add manual check button
                            if (!retryButtonAdded) {
                                const actionsDiv = document.getElementById('paymentStatusActions');
                                const checkBtn = document.createElement('button');
                                checkBtn.className = 'btn btn-primary w-100 mt-2';
                                checkBtn.innerHTML = '<i class="fas fa-search"></i> Check Again';
                                checkBtn.onclick = function() {
                                    paymentCheckCount = 0;
                                    this.remove();
                                    checkPaymentStatus();
                                };
                                actionsDiv.appendChild(checkBtn);
                                retryButtonAdded = true;
                            }
                        }
                        break;
                        
                    default:
                        // Unknown action, treat as continue
                        statusMessage.innerHTML = `<i class="fas fa-info-circle" style="color: #3b82f6;"></i> ${data.message || 'Checking payment status...'}`;
                        
                        if (paymentCheckCount >= MAX_PAYMENT_CHECKS) {
                            if (paymentCheckInterval) {
                                clearInterval(paymentCheckInterval);
                                paymentCheckInterval = null;
                            }
                            document.getElementById('loadingOverlay').classList.remove('show');
                        }
                }
            })
            .catch(error => {
                clearTimeout(timeoutId);
                console.error('Error checking payment status:', error);
                
                // Show error but continue if within limits
                if (paymentCheckCount < MAX_PAYMENT_CHECKS) {
                    statusMessage.innerHTML = '<i class="fas fa-exclamation-triangle" style="color: #ef4444;"></i> Error checking status. Retrying...';
                } else {
                    statusMessage.innerHTML = '<i class="fas fa-exclamation-triangle" style="color: #ef4444;"></i> Unable to verify payment. Please check your M-PESA.';
                    
                    clearInterval(paymentCheckInterval);
                    paymentCheckInterval = null;
                    document.getElementById('loadingOverlay').classList.remove('show');
                    
                    // Add manual check button
                    if (!retryButtonAdded) {
                        const actionsDiv = document.getElementById('paymentStatusActions');
                        const checkBtn = document.createElement('button');
                        checkBtn.className = 'btn btn-primary w-100 mt-2';
                        checkBtn.innerHTML = '<i class="fas fa-search"></i> Check Again';
                        checkBtn.onclick = function() {
                            paymentCheckCount = 0;
                            this.remove();
                            checkPaymentStatus();
                        };
                        actionsDiv.appendChild(checkBtn);
                        retryButtonAdded = true;
                    }
                }
            });
        }
        
        // =========================================================================
        // MODAL CLOSE HANDLERS
        // =========================================================================
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const activationModal = document.getElementById('activationModal');
            const stkModal = document.getElementById('stkPushModal');
            const paymentStatusModal = document.getElementById('paymentStatusModal');
            const contactModal = document.getElementById('contactModal');
            
            if (event.target == activationModal) {
                closeActivationModal();
            }
            if (event.target == stkModal) {
                closeSTKPushModal();
            }
            if (event.target == paymentStatusModal) {
                closePaymentStatusModal();
            }
            if (event.target == contactModal) {
                closeContactModal();
            }
        }
        
        // Close modals on escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeActivationModal();
                closeSTKPushModal();
                closePaymentStatusModal();
                closeContactModal();
            }
        });
        
        // Add input validation for phone number
        document.getElementById('stkPhoneNumber')?.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 9);
        });
        
        // Check for payment success parameter on page load
        <?php if ($payment_success): ?>
        document.addEventListener('DOMContentLoaded', function() {
            showToast('Payment completed successfully! Redirecting to dashboard...', 'success');
            setTimeout(() => {
                window.location.href = 'dashboard.php';
            }, 2000);
        });
        <?php endif; ?>
    </script>
</body>
</html>