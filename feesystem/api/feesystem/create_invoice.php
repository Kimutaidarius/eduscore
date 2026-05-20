<?php
session_start();
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

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
$client_id = $data['client_id'] ?? 0;
$invoice_date = $data['invoice_date'] ?? date('Y-m-d');
$due_date = $data['due_date'] ?? date('Y-m-d', strtotime('+30 days'));
$notes = $data['notes'] ?? '';
$subtotal = $data['subtotal'] ?? 0;
$tax_amount = $data['tax_amount'] ?? 0;
$total = $data['total'] ?? 0;
$items = $data['items'] ?? [];

try {
    $db->beginTransaction();
    
    // Verify client exists in clients table
    if ($client_id > 0) {
        $check_client = $db->prepare("SELECT id FROM clients WHERE id = :id AND school_id = :school_id");
        $check_client->execute([':id' => $client_id, ':school_id' => $school_id]);
        if ($check_client->rowCount() == 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid client selected']);
            $db->rollBack();
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Client ID is required']);
        $db->rollBack();
        exit;
    }
    
    // Generate invoice number
    $year = date('Y');
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM invoices WHERE school_id = :school_id AND YEAR(created_at) = :year");
    $stmt->execute([':school_id' => $school_id, ':year' => $year]);
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    $invoice_number = 'INV-' . $year . '-' . str_pad($count + 1, 5, '0', STR_PAD_LEFT);
    
    // Insert invoice - using the actual table structure
    $stmt = $db->prepare("
        INSERT INTO invoices (
            school_id, 
            invoice_number, 
            due_date, 
            total_amount, 
            notes, 
            status, 
            created_at
        ) VALUES (
            :school_id, 
            :invoice_number, 
            :due_date, 
            :total_amount, 
            :notes, 
            'UNPAID', 
            NOW()
        )
    ");
    
    $stmt->execute([
        ':school_id' => $school_id,
        ':invoice_number' => $invoice_number,
        ':due_date' => $due_date,
        ':total_amount' => $total,
        ':notes' => $notes
    ]);
    
    $invoice_id = $db->lastInsertId();
    
    // Create invoice_items table if it doesn't exist
    $table_check = $db->query("SHOW TABLES LIKE 'invoice_items'");
    if ($table_check->rowCount() == 0) {
        $create_items = "
        CREATE TABLE IF NOT EXISTS `invoice_items` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `invoice_id` int(11) NOT NULL,
            `vote_head_id` int(11) DEFAULT NULL,
            `description` text NOT NULL,
            `quantity` int(11) DEFAULT 1,
            `unit_price` decimal(10,2) NOT NULL,
            `total` decimal(10,2) NOT NULL,
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_invoice` (`invoice_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $db->exec($create_items);
    }
    
    // Insert invoice items
    $stmt_item = $db->prepare("
        INSERT INTO invoice_items (invoice_id, vote_head_id, description, quantity, unit_price, total)
        VALUES (:invoice_id, :vote_head_id, :description, :quantity, :unit_price, :total)
    ");
    
    foreach ($items as $item) {
        $item_total = $item['qty'] * $item['unit_price'];
        $stmt_item->execute([
            ':invoice_id' => $invoice_id,
            ':vote_head_id' => $item['vote_head_id'] ?? null,
            ':description' => $item['description'],
            ':quantity' => $item['qty'],
            ':unit_price' => $item['unit_price'],
            ':total' => $item_total
        ]);
    }
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Invoice created successfully',
        'invoice_id' => $invoice_id,
        'invoice_number' => $invoice_number
    ]);
    
} catch (PDOException $e) {
    $db->rollBack();
    error_log("Create invoice PDO error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    $db->rollBack();
    error_log("Create invoice error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>