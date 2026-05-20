<?php
require_once '../../includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['school_id'])) {
    sendResponse(null, 'error', 'Unauthorized access');
}

$school_id = $_SESSION['school_id'];

try {
    // Check if suppliers table exists, if not create it
    $checkTable = $db->query("SHOW TABLES LIKE 'suppliers'");
    if ($checkTable->rowCount() == 0) {
        $db->exec("CREATE TABLE IF NOT EXISTS `suppliers` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `school_id` int(11) NOT NULL,
            `name` varchar(255) NOT NULL,
            `phone` varchar(20) DEFAULT NULL,
            `address` text DEFAULT NULL,
            `kra_pin` varchar(50) DEFAULT NULL,
            `created_by` int(11) DEFAULT NULL,
            `deleted_at` datetime DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `idx_school_id` (`school_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    }
    
    $sql = "SELECT * FROM suppliers WHERE school_id = ? AND deleted_at IS NULL ORDER BY name ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute([$school_id]);
    $suppliers = $stmt->fetchAll();
    
    sendResponse(['suppliers' => $suppliers], 'success');
    
} catch (Exception $e) {
    error_log("Error fetching suppliers: " . $e->getMessage());
    sendResponse(['suppliers' => []], 'success');
}
?>