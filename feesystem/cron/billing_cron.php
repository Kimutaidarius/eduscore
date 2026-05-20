<?php
// /feesystem/cron/billing_cron.php
// Run daily: 0 0 * * * php /path/to/billing_cron.php

require_once('../includes/config.php');

error_log("=== Billing Cron Started at " . date('Y-m-d H:i:s') . " ===");

try {
    // 1. Generate invoices for subscriptions that need billing
    $today = date('Y-m-d H:i:s');
    $stmt = $db->prepare("
        SELECT s.*, sc.school_name, sc.school_email 
        FROM saas_subscriptions s
        JOIN tblschoolinfo sc ON s.school_id = sc.id
        WHERE s.status = 'active' 
        AND s.next_billing_date <= ?
        AND s.auto_renew = 1
    ");
    $stmt->execute([$today]);
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $invoices_generated = 0;
    foreach ($subscriptions as $sub) {
        $invoice_id = generateInvoice($sub['id'], $db);
        if ($invoice_id) {
            $invoices_generated++;
            sendInvoiceEmail($sub['school_id'], $invoice_id, $db);
            error_log("Generated invoice for school {$sub['school_id']}: {$invoice_id}");
        }
    }
    error_log("Generated $invoices_generated invoices");
    
    // 2. Process overdue invoices (7+ days overdue)
    $stmt = $db->prepare("
        UPDATE saas_invoices 
        SET status = 'overdue' 
        WHERE status = 'pending' AND due_date < DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stmt->execute();
    error_log("Updated " . $stmt->rowCount() . " invoices to overdue");
    
    // 3. Expire subscriptions with overdue invoices (14+ days)
    $stmt = $db->prepare("
        UPDATE saas_subscriptions s
        SET s.status = 'expired', s.updated_at = NOW()
        WHERE s.status = 'active'
        AND EXISTS (
            SELECT 1 FROM saas_invoices i 
            WHERE i.subscription_id = s.id 
            AND i.status = 'overdue'
            AND i.due_date < DATE_SUB(NOW(), INTERVAL 14 DAY)
        )
    ");
    $stmt->execute();
    error_log("Expired " . $stmt->rowCount() . " subscriptions");
    
    // 4. Send payment reminders (3 days before due)
    $stmt = $db->prepare("
        SELECT i.*, sc.school_name, sc.school_email 
        FROM saas_invoices i
        JOIN tblschoolinfo sc ON i.school_id = sc.id
        WHERE i.status = 'pending' 
        AND i.due_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 DAY)
        AND DATE(i.last_reminder_sent) < CURDATE() OR i.last_reminder_sent IS NULL
    ");
    $stmt->execute();
    $reminders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($reminders as $reminder) {
        sendPaymentReminder($reminder);
        // Update last_reminder_sent (add column if needed)
        $update = $db->prepare("UPDATE saas_invoices SET last_reminder_sent = NOW() WHERE id = ?");
        $update->execute([$reminder['id']]);
    }
    error_log("Sent " . count($reminders) . " payment reminders");
    
    // 5. Update MRR metrics (monthly recurring revenue)
    updateMRRMetrics($db);
    
} catch (Exception $e) {
    error_log("Billing cron error: " . $e->getMessage());
}

error_log("=== Billing Cron Completed ===");

function generateInvoice($subscription_id, $db) {
    // Get subscription details
    $stmt = $db->prepare("
        SELECT s.*, p.price_per_student, p.onboarding_fee, p.billing_cycle 
        FROM saas_subscriptions s
        LEFT JOIN saas_plans p ON s.plan_id = p.id
        WHERE s.id = ?
    ");
    $stmt->execute([$subscription_id]);
    $sub = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$sub) return false;
    
    // Get current student count
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM tblstudents WHERE school_id = ? AND Status = 'Active'");
    $stmt->execute([$sub['school_id']]);
    $student_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Calculate amount
    $base_amount = $student_count * ($sub['price_per_student'] ?? 0);
    $onboarding_fee = (!$sub['onboarding_paid'] && $sub['onboarding_fee']) ? $sub['onboarding_fee'] : 0;
    $total = $base_amount + $onboarding_fee;
    
    // Generate invoice number
    $invoice_number = 'INV-' . date('Ymd') . '-' . str_pad($subscription_id, 5, '0', STR_PAD_LEFT);
    
    // Insert invoice
    $stmt = $db->prepare("
        INSERT INTO saas_invoices 
        (school_id, subscription_id, invoice_number, invoice_date, due_date, 
         subtotal, onboarding_fee, total_amount, student_count, price_per_student, status)
        VALUES (?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY), ?, ?, ?, ?, ?, 'pending')
    ");
    $stmt->execute([
        $sub['school_id'], $subscription_id, $invoice_number,
        $base_amount, $onboarding_fee, $total, $student_count, $sub['price_per_student']
    ]);
    
    $invoice_id = $db->lastInsertId();
    
    // Update subscription next billing date
    $next_billing = ($sub['billing_cycle'] == 'monthly') 
        ? date('Y-m-d H:i:s', strtotime('+1 month'))
        : date('Y-m-d H:i:s', strtotime('+3 months'));
    
    $stmt = $db->prepare("
        UPDATE saas_subscriptions 
        SET next_billing_date = ?, current_period_end = ? 
        WHERE id = ?
    ");
    $stmt->execute([$next_billing, $next_billing, $subscription_id]);
    
    // Mark onboarding as paid for first invoice
    if ($onboarding_fee > 0) {
        $stmt = $db->prepare("UPDATE saas_subscriptions SET onboarding_paid = 1 WHERE id = ?");
        $stmt->execute([$subscription_id]);
    }
    
    return $invoice_id;
}

function sendInvoiceEmail($school_id, $invoice_id, $db) {
    // Get invoice and school details
    $stmt = $db->prepare("
        SELECT i.*, sc.school_name, sc.school_email 
        FROM saas_invoices i
        JOIN tblschoolinfo sc ON i.school_id = sc.id
        WHERE i.id = ?
    ");
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$invoice) return;
    
    $subject = "New Invoice #{$invoice['invoice_number']} - EduScore";
    $message = "
        <h2>Invoice Generated</h2>
        <p>Dear {$invoice['school_name']},</p>
        <p>A new invoice has been generated for your subscription.</p>
        <p><strong>Invoice #:</strong> {$invoice['invoice_number']}<br>
        <strong>Amount:</strong> KES " . number_format($invoice['total_amount'], 2) . "<br>
        <strong>Due Date:</strong> " . date('d M Y', strtotime($invoice['due_date'])) . "</p>
        <p>Please log in to your account to make payment.</p>
        <a href='https://eduscore.co.ke/feesystem/finance/billing.php' style='background:#667eea;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;'>Pay Now</a>
    ";
    
    // Send email using your email function
    // mail($invoice['school_email'], $subject, $message, "Content-Type: text/html\r\n");
}

function sendPaymentReminder($invoice) {
    $subject = "Payment Reminder - Invoice #{$invoice['invoice_number']}";
    $message = "
        <h2>Payment Reminder</h2>
        <p>Dear {$invoice['school_name']},</p>
        <p>This is a reminder that invoice #{$invoice['invoice_number']} is due on " . date('d M Y', strtotime($invoice['due_date'])) . ".</p>
        <p><strong>Amount Due:</strong> KES " . number_format($invoice['total_amount'], 2) . "</p>
        <p>Please make payment to avoid service interruption.</p>
        <a href='https://eduscore.co.ke/feesystem/finance/billing.php' style='background:#667eea;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;'>Pay Now</a>
    ";
    // mail($invoice['school_email'], $subject, $message, "Content-Type: text/html\r\n");
}

function updateMRRMetrics($db) {
    // Calculate MRR (Monthly Recurring Revenue)
    $stmt = $db->prepare("
        SELECT 
            SUM(s.student_count * p.price_per_student) as mrr,
            COUNT(DISTINCT s.school_id) as active_subscriptions
        FROM saas_subscriptions s
        JOIN saas_plans p ON s.plan_id = p.id
        WHERE s.status = 'active' AND p.billing_cycle = 'monthly'
    ");
    $stmt->execute();
    $metrics = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Insert into metrics table (create if needed)
    $stmt = $db->prepare("
        INSERT INTO saas_metrics (metric_date, mrr, active_subscriptions, created_at)
        VALUES (CURDATE(), ?, ?, NOW())
        ON DUPLICATE KEY UPDATE mrr = VALUES(mrr), active_subscriptions = VALUES(active_subscriptions)
    ");
    $stmt->execute([$metrics['mrr'] ?? 0, $metrics['active_subscriptions'] ?? 0]);
}
?>