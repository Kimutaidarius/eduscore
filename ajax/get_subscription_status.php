<?php
session_start();
require_once '../includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['teacher_id']) || !isset($_GET['school_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$school_id = intval($_GET['school_id']);

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Fetch current subscription
$subQuery = $conn->prepare("
    SELECT * FROM subscriptions 
    WHERE school_id = ? 
    ORDER BY created_at DESC 
    LIMIT 1
");
$subQuery->bind_param("i", $school_id);
$subQuery->execute();
$subResult = $subQuery->get_result();
$subscription = $subResult->fetch_assoc();

if (!$subscription) {
    echo json_encode([
        'success' => true,
        'data' => [
            'status' => 'inactive',
            'status_text' => 'Inactive',
            'status_class' => 'status-inactive',
            'time_remaining' => 'No active subscription'
        ]
    ]);
    exit();
}

// Calculate remaining time
$expiry = new DateTime($subscription['expires_at']);
$now = new DateTime();
$interval = $now->diff($expiry);

if ($expiry > $now) {
    $days = $interval->days;
    $hours = $interval->h;
    $minutes = $interval->i;
    $seconds = $interval->s;
    
    $time_remaining = sprintf("%dd %dh %dm %ds", $days, $hours, $minutes, $seconds);
    
    if ($days <= 7) {
        $status = 'expiring_soon';
        $status_text = 'Expiring Soon';
        $status_class = 'status-expiring';
    } else {
        $status = 'active';
        $status_text = 'Active';
        $status_class = 'status-active';
    }
} else {
    $status = 'expired';
    $status_text = 'Expired';
    $status_class = 'status-expired';
    $time_remaining = 'Expired';
}

$response = [
    'success' => true,
    'data' => [
        'status' => $status,
        'status_text' => $status_text,
        'status_class' => $status_class,
        'time_remaining' => $time_remaining,
        'expiry_timestamp' => $expiry->getTimestamp()
    ]
];

echo json_encode($response);
$conn->close();
?>