<?php
require_once '../config/config.php';
requireLogin();

$user_id = $_SESSION['user_id'];

// Get filter parameters
$filter_type = isset($_GET['type']) ? $_GET['type'] : 'all';
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build query conditions
$conditions = ["user_id = ?"];
$params = [$user_id];

if ($filter_type === 'sms') {
    $conditions[] = "transaction_type = 'sms'";
} elseif ($filter_type === 'payment') {
    $conditions[] = "transaction_type = 'payment'";
}

if ($filter_status !== 'all') {
    $conditions[] = "status = ?";
    $params[] = $filter_status;
}

if (!empty($date_from)) {
    $conditions[] = "DATE(created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $conditions[] = "DATE(created_at) <= ?";
    $params[] = $date_to;
}

$where_clause = implode(" AND ", $conditions);

// Get all transactions (no limit for export)
$sql = "
    (SELECT 
        'payment' as transaction_type,
        reference,
        amount,
        sms_units,
        payment_method,
        mpesa_receipt,
        status,
        created_at,
        completed_at,
        NULL as recipient,
        NULL as message,
        NULL as sms_count
    FROM mpesa_transactions 
    WHERE $where_clause)
    
    UNION ALL
    
    (SELECT 
        'sms' as transaction_type,
        message_id as reference,
        cost_kes as amount,
        sms_count as sms_units,
        'sms' as payment_method,
        NULL as mpesa_receipt,
        status,
        created_at,
        sent_at as completed_at,
        recipient,
        message,
        sms_count
    FROM sms_messages 
    WHERE $where_clause)
    
    ORDER BY created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="transactions_' . date('Y-m-d') . '.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// Add CSV headers
fputcsv($output, [
    'Type',
    'Reference',
    'Date',
    'Amount (KES)',
    'SMS Units',
    'Recipient',
    'Message',
    'Status',
    'M-Pesa Receipt',
    'Completed Date'
]);

// Add data rows
foreach ($transactions as $transaction) {
    fputcsv($output, [
        ucfirst($transaction['transaction_type']),
        $transaction['reference'],
        date('Y-m-d H:i:s', strtotime($transaction['created_at'])),
        $transaction['amount'] ? number_format($transaction['amount'], 2) : '0.00',
        $transaction['sms_units'] ?? '',
        $transaction['recipient'] ?? '',
        $transaction['transaction_type'] === 'sms' ? substr($transaction['message'], 0, 100) : '',
        ucfirst($transaction['status']),
        $transaction['mpesa_receipt'] ?? '',
        $transaction['completed_at'] ? date('Y-m-d H:i:s', strtotime($transaction['completed_at'])) : ''
    ]);
}

fclose($output);
exit;