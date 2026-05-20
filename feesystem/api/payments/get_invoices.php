<?php
require_once '../../includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['school_id'])) {
    sendResponse(null, 'error', 'Unauthorized access');
}

$school_id = $_SESSION['school_id'];

try {
    // Check if invoice_items table exists
    $checkItemsTable = $db->query("SHOW TABLES LIKE 'invoice_items'");
    if ($checkItemsTable->rowCount() == 0) {
        $db->exec("CREATE TABLE IF NOT EXISTS `invoice_items` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `invoice_id` int(11) NOT NULL,
            `vote_head_id` int(11) DEFAULT NULL,
            `description` text NOT NULL,
            `quantity` int(11) DEFAULT 1,
            `unit_price` decimal(12,2) NOT NULL,
            `total` decimal(12,2) NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `idx_invoice_id` (`invoice_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    }
    
    // Query to get all invoices with their items
    $sql = "SELECT i.*, 
                   s.name as supplier_name,
                   (SELECT COALESCE(SUM(total), 0) FROM invoice_items WHERE invoice_id = i.id) as calculated_total,
                   (SELECT COUNT(*) FROM invoice_items WHERE invoice_id = i.id) as item_count
            FROM invoices i
            LEFT JOIN suppliers s ON i.client_id = s.id
            WHERE i.school_id = ?
            ORDER BY i.created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$school_id]);
    $invoices = $stmt->fetchAll();
    
    // If no invoices found, return empty array
    if (empty($invoices)) {
        sendResponse(['invoices' => []], 'success', 'No invoices found');
        exit;
    }
    
    // Format the response for each invoice
    foreach ($invoices as &$invoice) {
        // Handle total_amount
        if (empty($invoice['total_amount']) || $invoice['total_amount'] == 0) {
            $invoice['total_amount'] = $invoice['calculated_total'] ?? 0;
        }
        
        // Handle paid_amount (default to 0 if not set)
        $paid_amount = $invoice['paid_amount'] ?? 0;
        
        // Calculate balance
        $invoice['balance'] = $invoice['total_amount'] - $paid_amount;
        
        // Determine status
        if ($invoice['balance'] <= 0) {
            $invoice['status'] = 'paid';
        } elseif ($paid_amount > 0) {
            $invoice['status'] = 'partial';
        } else {
            $invoice['status'] = 'pending';
        }
        
        // Format invoice_date
        if (empty($invoice['invoice_date']) || $invoice['invoice_date'] == '0000-00-00') {
            $invoice['invoice_date'] = date('Y-m-d');
        }
        
        // Ensure supplier_name is set
        $invoice['supplier_name'] = $invoice['supplier_name'] ?? 'Unknown Supplier';
        
        // Clean up response - remove sensitive/internal fields if needed
        unset($invoice['calculated_total']);
        unset($invoice['item_count']);
    }
    
    sendResponse(['invoices' => $invoices], 'success');
    
} catch (Exception $e) {
    error_log("Error fetching invoices: " . $e->getMessage());
    sendResponse(['invoices' => []], 'error', 'Failed to fetch invoices: ' . $e->getMessage());
}
?>