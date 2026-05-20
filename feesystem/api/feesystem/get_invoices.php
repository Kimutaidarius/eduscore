<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../../includes/config.php';

// Get PDO connection
$database = Database::getInstance();
$db = $database->getConnection();

$data = json_decode(file_get_contents('php://input'), true);
$school_id = $data['school_id'] ?? $_SESSION['school_id'];
$status = $data['status'] ?? 'all';
$client_id = $data['client_id'] ?? null;

try {
    $sql = "
        SELECT 
            i.*,
            c.name as client_name,
            c.phone as client_phone,
            c.email as client_email,
            i.total_amount as total,
            i.subtotal,
            i.tax_amount,
            (SELECT COUNT(*) FROM invoice_items WHERE invoice_id = i.id) as item_count
        FROM invoices i
        JOIN clients c ON i.client_id = c.id
        WHERE i.school_id = ?
    ";
    
    $params = [$school_id];
    
    if ($status !== 'all') {
        // Convert frontend status to database status (uppercase)
        $db_status = strtoupper($status);
        $sql .= " AND i.status = ?";
        $params[] = $db_status;
    }
    
    if ($client_id) {
        $sql .= " AND i.client_id = ?";
        $params[] = $client_id;
    }
    
    $sql .= " ORDER BY i.created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Update overdue status
    $update_stmt = $db->prepare("UPDATE invoices SET status = 'OVERDUE' WHERE due_date < CURDATE() AND status = 'UNPAID' AND school_id = ?");
    $update_stmt->execute([$school_id]);
    
    echo json_encode(['success' => true, 'invoices' => $invoices]);
    
} catch (Exception $e) {
    error_log("Get invoices error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>