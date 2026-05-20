<?php
require_once '../config/config.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$stmt = $pdo->prepare("SELECT * FROM sms_messages WHERE id = ? AND user_id = ?");
$stmt->execute([$id, $user_id]);
$message = $stmt->fetch();

if ($message) {
    $message['created_at'] = formatDate($message['created_at']);
    $message['sent_at'] = $message['sent_at'] ? formatDate($message['sent_at']) : null;
    $message['delivered_at'] = $message['delivered_at'] ? formatDate($message['delivered_at']) : null;
    header('Content-Type: application/json');
    echo json_encode($message);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Message not found']);
}
?>