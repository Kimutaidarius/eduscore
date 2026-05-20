<?php
require_once '../../includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['school_id'])) {
    sendResponse(null, 'error', 'Unauthorized access');
}

$school_id = $_SESSION['school_id'];
$input = json_decode(file_get_contents('php://input'), true);

if (empty($input['name'])) {
    sendResponse(null, 'error', 'Supplier name is required');
}

try {
    // Check if suppliers table exists
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
    
    $sql = "INSERT INTO suppliers (school_id, name, phone, address, kra_pin, created_by) 
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $db->prepare($sql);
    $stmt->execute([
        $school_id,
        $input['name'],
        $input['phone'] ?? null,
        $input['address'] ?? null,
        $input['kra_pin'] ?? null,
        $_SESSION['user_id']
    ]);
    
    sendResponse(['id' => $db->lastInsertId()], 'success', 'Supplier added successfully');
    
} catch (Exception $e) {
    error_log("Error saving supplier: " . $e->getMessage());
    sendResponse(null, 'error', 'Failed to save supplier: ' . $e->getMessage());
}
?>