<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../../includes/config.php';

$data = json_decode(file_get_contents('php://input'), true);
$school_id = $data['school_id'] ?? $_SESSION['school_id'];

try {
    // Check if clients table exists, create if not
    $table_check = $db->query("SHOW TABLES LIKE 'clients'");
    if ($table_check->rowCount() == 0) {
        $create_table = "
        CREATE TABLE IF NOT EXISTS `clients` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `school_id` int(11) NOT NULL,
            `name` varchar(255) NOT NULL,
            `id_number` varchar(50) DEFAULT NULL,
            `contact` varchar(100) DEFAULT NULL,
            `phone` varchar(20) DEFAULT NULL,
            `email` varchar(100) DEFAULT NULL,
            `address` text DEFAULT NULL,
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_school_id` (`school_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $db->exec($create_table);
        
        // Insert sample client
        $stmt = $db->prepare("INSERT INTO clients (school_id, name, contact, phone, email) VALUES (?, 'General Client', 'General', '0000000000', 'client@example.com')");
        $stmt->execute([$school_id]);
    }
    
    $stmt = $db->prepare("SELECT * FROM clients WHERE school_id = ? ORDER BY name");
    $stmt->execute([$school_id]);
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'clients' => $clients]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>