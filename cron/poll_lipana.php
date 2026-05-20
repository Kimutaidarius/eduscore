<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/mpesa_config.php';

/**
 * Poll pending payments older than 2 minutes
 * (Lipana automatically updates status)
 */

$stmt = $dbh->query("
    SELECT id, phone, amount
    FROM tblpayments
    WHERE status = 'pending'
    AND created_at < (NOW() - INTERVAL 2 MINUTE)
");

$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($payments as $p) {
    // Polling is optional because Lipana webhook is authoritative
    // You may skip polling or add future status endpoint here
}
