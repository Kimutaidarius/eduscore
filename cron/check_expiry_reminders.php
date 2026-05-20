// Get amount for the upcoming subscription
$stmt2 = $dbh->prepare("SELECT price_per_student, student_count FROM billing_invoices WHERE school_id = ? AND status = 'UNPAID' ORDER BY created_at DESC LIMIT 1");
$stmt2->execute([$sub['school_id']]);
$invoice = $stmt2->fetch();
$amount = $invoice ? ($invoice['price_per_student'] * $invoice['student_count']) : null;

// Send reminder with amount
$alertHelper->sendExpiryReminder($sub['school_id'], $days_left, $expiry_date, $amount);