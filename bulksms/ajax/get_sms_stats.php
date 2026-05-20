<?php
session_start();
header('Content-Type: application/json');
require_once '../config/config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

$sql = "
    SELECT 
        COUNT(*) as total,
        COALESCE(SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END), 0) as delivered,
        COALESCE(SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END), 0) as sent,
        COALESCE(SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END), 0) as failed,
        COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) as pending,
        COALESCE(SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END), 0) as scheduled,
        COALESCE(SUM(sms_count), 0) as total_sms_parts,
        COALESCE(SUM(cost), 0) as total_cost
    FROM sms_messages 
    WHERE user_id = ?
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$stats = $stmt->fetch();

$success_rate = $stats['total'] > 0 ? round(($stats['delivered'] / $stats['total']) * 100, 1) : 0;
$avg_cost = $stats['total'] > 0 ? round($stats['total_cost'] / $stats['total'], 2) : 0;

echo json_encode([
    'success' => true,
    'stats' => $stats,
    'success_rate' => $success_rate,
    'avg_cost' => $avg_cost
]);