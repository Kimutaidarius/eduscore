<?php
header('Content-Type: application/json');
require_once('../../includes/config.php');

$data = json_decode(file_get_contents('php://input'), true);
$school_id = $data['school_id'] ?? 0;

$stmt = $db->prepare("SELECT * FROM invoices WHERE school_id = ? ORDER BY created_at DESC LIMIT 10");
$stmt->execute([$school_id]);
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'invoices' => $invoices]);
?>