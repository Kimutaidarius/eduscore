<?php
// /cron/recover_stuck_transactions.php
// Run every 5 minutes via cron job

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../admin/config/db_sms.php';
require_once __DIR__ . '/../includes/PaymentRouter.php';

$smsDb = $db;
$srmsDb = $dbh;
$router = new PaymentRouter($smsDb, $srmsDb);

// Find SMS transactions stuck in 'processing' for > 10 minutes
$stmt = $smsDb->prepare("
    SELECT * FROM sms_purchases 
    WHERE status = 'processing' 
    AND updated_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)
");
$stmt->execute();
$stuck_sms = $stmt->fetchAll();

foreach ($stuck_sms as $transaction) {
    error_log("Recovering stuck SMS transaction: {$transaction['transaction_id']}");
    $router->rollbackAtomicClaim(['data' => $transaction, 'database' => 'sms']);
}

// Find billing transactions stuck in 'processing' for > 10 minutes
$stmt = $srmsDb->prepare("
    SELECT * FROM billing_transactions 
    WHERE status = 'processing' 
    AND updated_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)
");
$stmt->execute();
$stuck_billing = $stmt->fetchAll();

foreach ($stuck_billing as $transaction) {
    error_log("Recovering stuck billing transaction: {$transaction['reference']}");
    $router->rollbackAtomicClaim(['data' => $transaction, 'database' => 'srms']);
}

echo "Recovery completed. Found " . count($stuck_sms) . " SMS and " . count($stuck_billing) . " billing transactions.\n";