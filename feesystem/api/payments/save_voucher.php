<?php
require_once '../../includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['school_id'])) {
    sendResponse(null, 'error', 'Unauthorized access');
}

$school_id = $_SESSION['school_id'];
$user_id = $_SESSION['user_id'];

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    sendResponse(null, 'error', 'Invalid request data');
}

if (empty($input['payee_name'])) {
    sendResponse(null, 'error', 'Payee name is required');
}
if (empty($input['payment_mode'])) {
    sendResponse(null, 'error', 'Payment mode is required');
}
if (empty($input['items']) || count($input['items']) == 0) {
    sendResponse(null, 'error', 'At least one expense item is required');
}

$total_amount = 0;
foreach ($input['items'] as $item) {
    $total_amount += floatval($item['amount']);
}

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
    
    // Check if payment_voucher_items table exists
    $checkItemsTable = $db->query("SHOW TABLES LIKE 'payment_voucher_items'");
    if ($checkItemsTable->rowCount() == 0) {
        $db->exec("CREATE TABLE IF NOT EXISTS `payment_voucher_items` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `voucher_id` int(11) NOT NULL,
            `vote_head_id` int(11) DEFAULT NULL,
            `particulars` text NOT NULL,
            `amount` decimal(12,2) NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `idx_voucher_id` (`voucher_id`),
            KEY `idx_vote_head_id` (`vote_head_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    }
    
    $db->beginTransaction();
    
    // Generate voucher number
    $year = date('Y');
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM payment_vouchers WHERE school_id = ? AND YEAR(created_at) = ?");
    $stmt->execute([$school_id, $year]);
    $count = $stmt->fetch()['count'] + 1;
    $voucher_no = 'PV-' . $year . '-' . str_pad($count, 5, '0', STR_PAD_LEFT);
    
    $sql = "INSERT INTO payment_vouchers 
            (school_id, voucher_no, type, supplier_id, payee_name, id_number, 
             payment_date, payment_mode, reference, lpo_number, lpo_date, 
             delivery_note_no, delivery_note_date, notes, total_amount, status, created_by) 
            VALUES 
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        $school_id,
        $voucher_no,
        $input['type'],
        !empty($input['supplier_id']) ? $input['supplier_id'] : null,
        $input['payee_name'],
        $input['id_number'] ?? null,
        $input['payment_date'] ?? date('Y-m-d'),
        $input['payment_mode'],
        $input['reference'] ?? null,
        $input['lpo_number'] ?? null,
        $input['lpo_date'] ?? null,
        $input['delivery_note_no'] ?? null,
        $input['delivery_note_date'] ?? null,
        $input['notes'] ?? null,
        $total_amount,
        $user_id
    ]);
    
    $voucher_id = $db->lastInsertId();
    
    $sql_item = "INSERT INTO payment_voucher_items 
                 (voucher_id, vote_head_id, particulars, amount) 
                 VALUES (?, ?, ?, ?)";
    $stmt_item = $db->prepare($sql_item);
    
    foreach ($input['items'] as $item) {
        $stmt_item->execute([
            $voucher_id,
            !empty($item['vote_head_id']) ? $item['vote_head_id'] : null,
            $item['particulars'] ?? '',
            $item['amount']
        ]);
    }
    
    $db->commit();
    
    sendResponse([
        'voucher_id' => $voucher_id,
        'voucher_no' => $voucher_no,
        'total_amount' => $total_amount
    ], 'success', 'Payment voucher saved successfully');
    
} catch (Exception $e) {
    if (isset($db)) $db->rollBack();
    error_log("Error saving voucher: " . $e->getMessage());
    sendResponse(null, 'error', 'Failed to save voucher: ' . $e->getMessage());
}
?>