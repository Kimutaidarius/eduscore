<?php
/**
 * API: Save Other Income Receipt
 * Endpoint: /feesystem/api/feesystem/save_other_income.php
 * Method: POST
 */

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Verify authentication
if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'finance') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied. Finance role required.']);
    exit;
}

require_once('../../includes/config.php');

// Get database connection - using your existing config
$database = Database::getInstance();
$pdo = $database->getConnection();

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit;
}

// Validate required fields
$required_fields = ['payer_type', 'payer_name', 'payment_date', 'payment_mode', 'items', 'total_amount'];
foreach ($required_fields as $field) {
    if (empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit;
    }
}

if (empty($input['items']) || !is_array($input['items'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'At least one receipt item is required']);
    exit;
}

$school_id = $_SESSION['school_id'];
$created_by = $_SESSION['user_id'] ?? 0;

// Use receipt number from frontend or generate new one
$receipt_number = isset($input['receipt_number']) && !empty($input['receipt_number']) 
    ? $input['receipt_number'] 
    : generateReceiptNumber($school_id, $pdo);

// Begin transaction
try {
    $pdo->beginTransaction();
    
    // Check if table exists, if not create it
    $check_table = $pdo->query("SHOW TABLES LIKE 'other_income_receipts'");
    if ($check_table->rowCount() == 0) {
        // Create tables if they don't exist
        $create_receipts = "CREATE TABLE IF NOT EXISTS `other_income_receipts` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `school_id` int(11) NOT NULL,
            `receipt_number` varchar(50) NOT NULL,
            `payer_type` enum('client','student','staff','other') NOT NULL DEFAULT 'other',
            `payer_id` int(11) DEFAULT NULL,
            `payer_name` varchar(255) NOT NULL,
            `payment_date` date NOT NULL,
            `payment_mode` enum('cash','mpesa','bank','cheque','card') NOT NULL,
            `payment_code` varchar(100) DEFAULT NULL,
            `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
            `tax_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
            `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
            `notes` text DEFAULT NULL,
            `status` enum('active','void','deleted') DEFAULT 'active',
            `created_by` int(11) NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `receipt_number` (`receipt_number`),
            KEY `idx_school_id` (`school_id`),
            KEY `idx_payment_date` (`payment_date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
        
        $pdo->exec($create_receipts);
        
        $create_items = "CREATE TABLE IF NOT EXISTS `other_income_receipt_items` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `receipt_id` int(11) NOT NULL,
            `vote_head_id` int(11) NOT NULL,
            `description` text NOT NULL,
            `amount` decimal(12,2) NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `idx_receipt_id` (`receipt_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
        
        $pdo->exec($create_items);
    }
    
    // Insert receipt header
    $sql = "INSERT INTO other_income_receipts (
        school_id, receipt_number, payer_type, payer_id, payer_name, 
        payment_date, payment_mode, payment_code, subtotal, tax_amount, 
        total_amount, notes, created_by
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($sql);
    
    $payer_id = isset($input['payer_id']) && !empty($input['payer_id']) ? $input['payer_id'] : null;
    $payment_code = $input['payment_code'] ?? null;
    $notes = $input['notes'] ?? null;
    $subtotal = $input['subtotal'] ?? 0;
    $tax_amount = $input['tax_amount'] ?? 0;
    
    $stmt->execute([
        $school_id, $receipt_number, $input['payer_type'], $payer_id, $input['payer_name'],
        $input['payment_date'], $input['payment_mode'], $payment_code, $subtotal, $tax_amount,
        $input['total_amount'], $notes, $created_by
    ]);
    
    $receipt_id = $pdo->lastInsertId();
    
    // Insert receipt items
    $item_sql = "INSERT INTO other_income_receipt_items (receipt_id, vote_head_id, description, amount) VALUES (?, ?, ?, ?)";
    $item_stmt = $pdo->prepare($item_sql);
    
    foreach ($input['items'] as $item) {
        if (empty($item['vote_head_id']) || empty($item['amount']) || $item['amount'] <= 0) {
            continue;
        }
        
        $description = $item['description'] ?? '';
        $item_stmt->execute([$receipt_id, $item['vote_head_id'], $description, $item['amount']]);
    }
    
    $pdo->commit();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Receipt saved successfully',
        'data' => [
            'receipt_id' => $receipt_id,
            'receipt_number' => $receipt_number,
            'receipt_date' => $input['payment_date'],
            'total_amount' => $input['total_amount']
        ]
    ]);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

/**
 * Generate unique receipt number
 */
function generateReceiptNumber($school_id, $pdo) {
    $prefix = 'RCP';
    $year = date('Y');
    $month = date('m');
    
    try {
        // Check if table exists
        $check = $pdo->query("SHOW TABLES LIKE 'other_income_receipts'");
        if ($check->rowCount() == 0) {
            return "$prefix-$year$month-0001";
        }
        
        // Get last receipt number for this school
        $sql = "SELECT receipt_number FROM other_income_receipts 
                WHERE school_id = ? AND receipt_number LIKE ? 
                ORDER BY id DESC LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $pattern = "$prefix-$year$month%";
        $stmt->execute([$school_id, $pattern]);
        $row = $stmt->fetch();
        
        $last_number = 0;
        if ($row) {
            $parts = explode('-', $row['receipt_number']);
            $last_number = intval(end($parts));
        }
        
        $next_number = str_pad($last_number + 1, 4, '0', STR_PAD_LEFT);
        return "$prefix-$year$month-$next_number";
    } catch (Exception $e) {
        return "$prefix-$year$month-" . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }
}
?>