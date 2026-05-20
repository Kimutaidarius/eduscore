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
$school_id = $_SESSION['school_id'];

try {
    // Get invoice details
    $stmt = $db->prepare("
        SELECT i.*, c.name as client_name, c.phone as client_phone, c.email as client_email, c.address as client_address
        FROM invoices i
        JOIN clients c ON i.client_id = c.id
        WHERE i.id = ? AND i.school_id = ?
    ");
    $stmt->execute([$invoice_id, $school_id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$invoice) {
        echo json_encode(['success' => false, 'message' => 'Invoice not found']);
        exit;
    }
    
    // Get invoice items
    $stmt = $db->prepare("
        SELECT ii.*, vh.name as vote_head_name, vh.alias as vote_head_alias
        FROM invoice_items ii
        LEFT JOIN vote_heads vh ON ii.vote_head_id = vh.id
        WHERE ii.invoice_id = ?
    ");
    $stmt->execute([$invoice_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'invoice' => $invoice, 'items' => $items]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>