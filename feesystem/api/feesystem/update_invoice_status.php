<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../../includes/config.php';

$data = json_decode(file_get_contents('php://input'), true);
$invoice_id = $data['invoice_id'] ?? 0;
$status = $data['status'] ?? '';
$school_id = $_SESSION['school_id'];

$allowed_statuses = ['paid', 'cancelled'];

if (!in_array($status, $allowed_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

try {
    $stmt = $db->prepare("UPDATE invoices SET status = ? WHERE id = ? AND school_id = ?");
    $stmt->execute([$status, $invoice_id, $school_id]);
    
    echo json_encode(['success' => true, 'message' => 'Invoice status updated']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>