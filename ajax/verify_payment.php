<?php
// ============================================
// CRON JOB: Check Pending Transactions
// Run every 2 minutes: */2 * * * * php /path/to/cron/check_pending_transactions.php
// ============================================
// NOTE: Run this SQL ONCE manually before using this cron:
// ALTER TABLE billing_transactions ADD COLUMN retry_count INT DEFAULT 0;
// CREATE INDEX idx_pending_check ON billing_transactions(status, checkout_request_id, created_at, retry_count);
// ============================================

require_once '../includes/config.php';
require_once '../includes/SubscriptionManager.php';

// ============================================
// STEP 1: GET DB CONNECTION
// ============================================
$dbh = getDbConnection();
if (!$dbh) {
    error_log("Cron: Database connection failed");
    exit(1);
}

// ============================================
// STEP 2: GET PENDING TRANSACTIONS (WITH PROPER FILTERS)
// ============================================
$stmt = $dbh->prepare("
    SELECT * FROM billing_transactions 
    WHERE status = 'pending' 
    AND checkout_request_id IS NOT NULL
    AND created_at < DATE_SUB(NOW(), INTERVAL 2 MINUTE)
    AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)
    AND retry_count < 10
    ORDER BY created_at ASC
    LIMIT 50
");
$stmt->execute();
$pending_transactions = $stmt->fetchAll();

// ============================================
// STEP 3: CHECK FOR OLD PENDING TRANSACTIONS
// ============================================
$auto_expired_count = 0;
if (empty($pending_transactions)) {
    echo "No pending transactions to check\n";
    
    // Still run auto-expire to clean up any stuck transactions
    $stmt = $dbh->prepare("
        UPDATE billing_transactions 
        SET status = 'failed', 
            updated_at = NOW(),
            callback_data = ?
        WHERE status = 'pending' 
        AND (retry_count >= 10 OR created_at < DATE_SUB(NOW(), INTERVAL 1 DAY))
    ");
    $expire_note = json_encode(['auto_expired' => true, 'expired_at' => date('Y-m-d H:i:s')]);
    $stmt->execute([$expire_note]);
    $auto_expired_count = $stmt->rowCount();
    
    if ($auto_expired_count > 0) {
        error_log("Cron: Auto-expired {$auto_expired_count} old/stuck pending transactions");
        echo "Auto-expired: {$auto_expired_count}\n";
    }
    
    exit(0);
}

// ============================================
// STEP 4: SETUP AUTH HEADER
// ============================================
$auth_header = PAYHERO_BASIC_AUTH_TOKEN;
if (!preg_match('/^Basic\s/i', $auth_header)) {
    $auth_header = 'Basic ' . $auth_header;
}

$updated_count = 0;
$failed_count = 0;
$queued_count = 0;
$skipped_count = 0;
$error_count = 0;
$total_processed = count($pending_transactions);

// ============================================
// STEP 5: PROCESS EACH TRANSACTION
// ============================================
foreach ($pending_transactions as $index => $transaction) {
    $reference = $transaction['reference'];
    $transaction_id = $transaction['id'];
    $current_retry = $transaction['retry_count'];
    $next_retry = $current_retry + 1;
    
    // ============================================
    // SAFETY CHECK: Empty reference
    // ============================================
    if (empty($reference)) {
        error_log("Cron: Missing reference for transaction ID {$transaction_id}");
        $skipped_count++;
        continue;
    }
    
    // Display correct retry count (current + 1 for this attempt)
    error_log("Cron: Checking transaction {$reference} (Attempt: {$next_retry}/10)");
    
    // ============================================
    // MAKE API REQUEST TO PAYHERO V2
    // ============================================
    $ch = curl_init("https://backend.payhero.co.ke/api/v2/transaction-status?reference=" . urlencode($reference));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: ' . $auth_header,
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    // Handle CURL errors
    if ($curl_error || !$response) {
        error_log("Cron CURL Error for {$reference}: " . $curl_error);
        $error_count++;
        
        // Increment retry count for failed connections
        $stmt = $dbh->prepare("
            UPDATE billing_transactions 
            SET retry_count = retry_count + 1 
            WHERE id = ? AND status = 'pending'
        ");
        $stmt->execute([$transaction_id]);
        
        // Add delay before next request
        if ($index < $total_processed - 1) {
            usleep(200000);
        }
        continue;
    }
    
    // Handle non-200 responses
    if ($http_code !== 200) {
        error_log("Cron HTTP Error for {$reference}: HTTP {$http_code}");
        $error_count++;
        
        // Increment retry count for HTTP errors
        $stmt = $dbh->prepare("
            UPDATE billing_transactions 
            SET retry_count = retry_count + 1 
            WHERE id = ? AND status = 'pending'
        ");
        $stmt->execute([$transaction_id]);
        
        if ($index < $total_processed - 1) {
            usleep(200000);
        }
        continue;
    }
    
    // Parse response
    $result = json_decode($response, true);
    if (!$result || !isset($result['status'])) {
        error_log("Cron Invalid response for {$reference}: " . substr($response, 0, 200));
        $error_count++;
        
        // Increment retry count for invalid responses
        $stmt = $dbh->prepare("
            UPDATE billing_transactions 
            SET retry_count = retry_count + 1 
            WHERE id = ? AND status = 'pending'
        ");
        $stmt->execute([$transaction_id]);
        
        if ($index < $total_processed - 1) {
            usleep(200000);
        }
        continue;
    }
    
    $status = strtoupper($result['status']);
    
    // ============================================
    // STEP 6: HANDLE SUCCESS STATUS
    // ============================================
    // Support both SUCCESS and COMPLETED (future-proof)
    if (in_array($status, ['SUCCESS', 'COMPLETED'])) {
        // Use provider_reference (correct for v2) with fallbacks
        $receipt_code = $result['provider_reference'] 
                      ?? $result['third_party_reference'] 
                      ?? $result['MpesaReceiptNumber']
                      ?? 'RCPT-' . date('YmdHis');
        
        // Update transaction with race condition protection
        // Note: NOT incrementing retry_count here since it's successful
        $stmt = $dbh->prepare("
            UPDATE billing_transactions 
            SET status = 'success', 
                mpesa_receipt_code = ?,
                updated_at = NOW()
            WHERE id = ? AND status = 'pending'
        ");
        $stmt->execute([$receipt_code, $transaction_id]);
        
        if ($stmt->rowCount() === 0) {
            error_log("Cron: Transaction {$reference} already processed (callback may have handled it)");
            continue;
        }
        
        // ============================================
        // STEP 7: UPDATE INVOICE
        // ============================================
        if ($transaction['invoice_id']) {
            $stmt = $dbh->prepare("
                UPDATE billing_invoices 
                SET status = 'PAID', paid_at = NOW()
                WHERE id = ? AND school_id = ? AND status = 'UNPAID'
            ");
            $stmt->execute([$transaction['invoice_id'], $transaction['school_id']]);
            
            if ($stmt->rowCount() > 0) {
                error_log("Cron: Updated invoice {$transaction['invoice_id']} to PAID");
            }
        }
        
        // ============================================
        // STEP 8: ACTIVATE SUBSCRIPTION
        // ============================================
        try {
            $subscriptionManager = new SubscriptionManager($dbh, $transaction['school_id']);
            // Use consistent column name: 'type' (not payment_type)
            $payment_type = $transaction['type'] ?? '';
            
            if ($payment_type === 'onboarding') {
                $subscriptionManager->activateFreeTerm();
                error_log("Cron: Activated free term for school {$transaction['school_id']}");
            } else {
                $subscriptionManager->activatePaidTerm();
                error_log("Cron: Activated paid term for school {$transaction['school_id']}");
            }
            
            error_log("Cron SUCCESS: {$reference} - Receipt: {$receipt_code}");
            $updated_count++;
            
        } catch (Exception $e) {
            error_log("Cron Subscription activation error for {$reference}: " . $e->getMessage());
            $failed_count++;
        }
        
    // ============================================
    // STEP 9: HANDLE FAILED STATUS
    // ============================================
    } elseif (in_array($status, ['FAILED', 'CANCELLED', 'REVERSED'])) {
        $stmt = $dbh->prepare("
            UPDATE billing_transactions 
            SET status = 'failed', 
                updated_at = NOW(),
                callback_data = ?
            WHERE id = ? AND status = 'pending'
        ");
        $callback_data = json_encode([
            'cron_status' => $status, 
            'checked_at' => date('Y-m-d H:i:s'),
            'api_response' => $result
        ]);
        $stmt->execute([$callback_data, $transaction_id]);
        
        error_log("Cron FAILED: {$reference} - Status: {$status}");
        $failed_count++;
        
    // ============================================
    // STEP 10: HANDLE QUEUED/PENDING STATUS
    // ============================================
    } else {
        // Still queued - increment retry count for next attempt
        $stmt = $dbh->prepare("
            UPDATE billing_transactions 
            SET retry_count = retry_count + 1 
            WHERE id = ? AND status = 'pending'
        ");
        $stmt->execute([$transaction_id]);
        
        error_log("Cron QUEUED: {$reference} - Status: {$status} (Attempt: {$next_retry}/10)");
        $queued_count++;
    }
    
    // ============================================
    // STEP 11: RATE LIMITING - Delay between requests
    // ============================================
    if ($index < $total_processed - 1) {
        usleep(200000); // 0.2 seconds delay to avoid API rate limiting
    }
}

// ============================================
// STEP 12: AUTO-EXPIRE OLD/STUCK TRANSACTIONS
// ============================================
$stmt = $dbh->prepare("
    UPDATE billing_transactions 
    SET status = 'failed', 
        updated_at = NOW(),
        callback_data = ?
    WHERE status = 'pending' 
    AND (retry_count >= 10 OR created_at < DATE_SUB(NOW(), INTERVAL 1 DAY))
");
$expire_note = json_encode([
    'auto_expired' => true, 
    'expired_at' => date('Y-m-d H:i:s'),
    'reason' => 'Max retries or time limit exceeded'
]);
$stmt->execute([$expire_note]);
$auto_expired_count = $stmt->rowCount();

if ($auto_expired_count > 0) {
    error_log("Cron: Auto-expired {$auto_expired_count} old/stuck pending transactions");
}

// ============================================
// STEP 13: SUMMARY LOG
// ============================================
$summary = sprintf(
    "Cron completed - Checked: %d | Updated: %d | Failed: %d | Queued: %d | Errors: %d | Skipped: %d | Auto-expired: %d",
    $total_processed,
    $updated_count,
    $failed_count,
    $queued_count,
    $error_count,
    $skipped_count,
    $auto_expired_count
);

error_log($summary);
echo $summary . "\n";

exit(0);
?>