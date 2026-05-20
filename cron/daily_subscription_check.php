<?php
require_once '../includes/config.php';
require_once '../includes/SubscriptionManager.php';

$dbh = getDbConnection();

// Run subscription state updates
SubscriptionManager::runDailyCron($dbh);

// Log execution
error_log("Daily subscription check completed at " . date('Y-m-d H:i:s'));
?>