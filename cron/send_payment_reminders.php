<?php
// cron/send_payment_reminders.php
// Run every hour to remind users about pending payments

require_once '../includes/config.php';
require_once '../includes/AlertHelper.php';

$dbh = getDbConnection();
$alertHelper = new AlertHelper($dbh);

// Find pending transactions older than 30 minutes
$stmt = $dbh->prepare("
    SELECT bt.*, t.school_name, t.school_phone
    FROM billing_transactions bt
    JOIN tblschoolinfo t ON bt.school_id = t.id
    WHERE bt.status = 'pending'
    AND bt.created_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)
    AND bt.created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
    AND bt.reminder_sent = 0
    LIMIT 50
");
$stmt->execute();
$pending_payments = $stmt->fetchAll();

foreach ($pending_payments as $payment) {
    $phone = $payment['school_phone'];
    $amount = $payment['amount'];
    $reference = $payment['reference'];
    
    $message = "🔔 *Payment Reminder - EduScore*\n\n";
    $message .= "Hello {$payment['school_name']},\n\n";
    $message .= "We noticed you have a pending payment of *KES " . number_format($amount, 2) . "*.\n\n";
    $message .= "👉 *Complete Payment:* https://eduscore.co.ke/billing/pay.php?ref={$reference}\n\n";
    $message .= "The payment prompt was sent to your phone. If you missed it, click the link above to retry.\n\n";
    $message .= "_Need assistance? Reply to this message._";
    
    $alertHelper->sendWhatsApp($phone, $message, $payment['school_id']);
    
    // Mark reminder as sent
    $stmt2 = $dbh->prepare("UPDATE billing_transactions SET reminder_sent = 1 WHERE id = ?");
    $stmt2->execute([$payment['id']]);
    
    sleep(1); // Rate limiting
}

echo "Sent " . count($pending_payments) . " payment reminders\n";
?>