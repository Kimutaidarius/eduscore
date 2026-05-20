<?php
// ajax/get_balance.php
session_start();
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("SELECT sms_balance FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $balance = $stmt->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'balance' => number_format($balance),
        'raw_balance' => $balance
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error fetching balance']);
}