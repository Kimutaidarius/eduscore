<?php
require_once '../../includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['school_id'])) {
    sendResponse(null, 'error', 'Unauthorized access');
}

$school_id = $_SESSION['school_id'];

try {
    // Check if payment_vouchers table exists
    $checkTable = $db->query("SHOW TABLES LIKE 'payment_vouchers'");
    if ($checkTable->rowCount() == 0) {
        $db->exec("CREATE TABLE IF NOT EXISTS `payment_vouchers` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `school_id` int(11) NOT NULL,
            `voucher_no` varchar(50) NOT NULL,
            `type` enum('simple','detailed') DEFAULT 'simple',
            `supplier_id` int(11) DEFAULT NULL,
            `payee_name` varchar(255) NOT NULL,
            `id_number` varchar(50) DEFAULT NULL,
            `payment_date` date NOT NULL,
            `payment_mode` enum('cash','bank','cheque','mpesa') NOT NULL,
            `reference` varchar(100) DEFAULT NULL,
            `lpo_number` varchar(100) DEFAULT NULL,
            `lpo_date` date DEFAULT NULL,
            `delivery_note_no` varchar(100) DEFAULT NULL,
            `delivery_note_date` date DEFAULT NULL,
            `notes` text DEFAULT NULL,
            `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
            `status` enum('pending','approved','rejected','cancelled') DEFAULT 'pending',
            `created_by` int(11) NOT NULL,
            `approved_by` int(11) DEFAULT NULL,
            `approved_at` datetime DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `voucher_no` (`voucher_no`),
            KEY `idx_school_id` (`school_id`),
            KEY `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    }
    
    $sql = "SELECT pv.*, 
                   s.name as supplier_name,
                   (SELECT COALESCE(SUM(amount), 0) FROM payment_voucher_items WHERE voucher_id = pv.id) as total_amount
            FROM payment_vouchers pv
            LEFT JOIN suppliers s ON pv.supplier_id = s.id
            WHERE pv.school_id = ?
            ORDER BY pv.created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$school_id]);
    $vouchers = $stmt->fetchAll();
    
    sendResponse(['vouchers' => $vouchers], 'success');
    
} catch (Exception $e) {
    error_log("Error fetching vouchers: " . $e->getMessage());
    sendResponse(['vouchers' => []], 'success');
}
?>