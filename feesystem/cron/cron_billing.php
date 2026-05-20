<?php
// /feesystem/cron/cron_billing.php
// Run daily: 0 0 * * * php /path/to/cron_billing.php

require_once('../includes/config.php');
require_once('../includes/billing_functions.php');

error_log("Billing cron job started at " . date('Y-m-d H:i:s'));

// Process auto-billing
$invoices = processAutoBilling($conn);
error_log("Generated $invoices invoices");

// Process overdue invoices
processOverdueInvoices($conn);
error_log("Processed overdue invoices");

error_log("Billing cron job completed at " . date('Y-m-d H:i:s'));
?>