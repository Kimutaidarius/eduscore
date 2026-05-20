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
    echo json_encode(['success' => false, 'message' => 'No subscription found']);
    exit();
}

// Define modules based on plan
$plan_modules = [
    'Basic' => [
        ['name' => 'Exam Analysis System', 'icon' => 'fas fa-chart-line', 'status' => 'active'],
        ['name' => 'Student Management', 'icon' => 'fas fa-user-graduate', 'status' => 'active'],
        ['name' => 'Basic Reports', 'icon' => 'fas fa-file-alt', 'status' => 'active'],
        ['name' => 'SMS (100/month)', 'icon' => 'fas fa-sms', 'status' => 'active'],
        ['name' => 'Advanced Analytics', 'icon' => 'fas fa-chart-pie', 'status' => 'inactive'],
        ['name' => 'Priority Support', 'icon' => 'fas fa-headset', 'status' => 'inactive']
    ],
    'Standard' => [
        ['name' => 'Exam Analysis System', 'icon' => 'fas fa-chart-line', 'status' => 'active'],
        ['name' => 'Student Management', 'icon' => 'fas fa-user-graduate', 'status' => 'active'],
        ['name' => 'Advanced Reports', 'icon' => 'fas fa-file-alt', 'status' => 'active'],
        ['name' => 'SMS (500/month)', 'icon' => 'fas fa-sms', 'status' => 'active'],
        ['name' => 'Advanced Analytics', 'icon' => 'fas fa-chart-pie', 'status' => 'active'],
        ['name' => 'Priority Support', 'icon' => 'fas fa-headset', 'status' => 'active'],
        ['name' => 'Custom Branding', 'icon' => 'fas fa-paint-brush', 'status' => 'active'],
        ['name' => 'Unlimited Usage', 'icon' => 'fas fa-infinity', 'status' => 'inactive']
    ],
    'Premium' => [
        ['name' => 'Exam Analysis System', 'icon' => 'fas fa-chart-line', 'status' => 'active'],
        ['name' => 'Student Management', 'icon' => 'fas fa-user-graduate', 'status' => 'active'],
        ['name' => 'Premium Reports', 'icon' => 'fas fa-file-alt', 'status' => 'active'],
        ['name' => 'Unlimited SMS', 'icon' => 'fas fa-sms', 'status' => 'active'],
        ['name' => 'Advanced Analytics', 'icon' => 'fas fa-chart-pie', 'status' => 'active'],
        ['name' => '24/7 Priority Support', 'icon' => 'fas fa-headset', 'status' => 'active'],
        ['name' => 'Custom Branding', 'icon' => 'fas fa-paint-brush', 'status' => 'active'],
        ['name' => 'Unlimited Usage', 'icon' => 'fas fa-infinity', 'status' => 'active'],
        ['name' => 'API Access', 'icon' => 'fas fa-code', 'status' => 'active'],
        ['name' => 'Custom Integrations', 'icon' => 'fas fa-plug', 'status' => 'active']
    ]
];

$modules = $plan_modules[$subscription['plan_name']] ?? $plan_modules['Basic'];

$response = [
    'success' => true,
    'data' => [
        'plan_name' => $subscription['plan_name'],
        'start_date' => date('M j, Y', strtotime($subscription['started_at'] ?? $subscription['created_at'])),
        'expiry_date' => date('M j, Y', strtotime($subscription['expires_at'])),
        'modules' => $modules
    ]
];

echo json_encode($response);
$conn->close();
?>