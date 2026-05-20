<?php
// /feesystem/api/feesystem/delete_invoice.php
session_start();
header('Content-Type: application/json');

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check if user has finance access
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'finance') {
    echo json_encode(['success' => false, 'message' => 'Access denied. Finance privileges required.']);
    exit;
}

require_once '../../includes/config.php';

// Get PDO connection
$database = Database::getInstance();
$db = $database->getConnection();

$data = json_decode(file_get_contents('php://input'), true);
$invoice_id = $data['invoice_id'] ?? 0;
$school_id = $data['school_id'] ?? $_SESSION['school_id'];

if ($invoice_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid invoice ID']);
    exit;
}

try {
    $db->beginTransaction();
    
    // Verify invoice belongs to this school
    $check_stmt = $db->prepare("SELECT id, invoice_number, status FROM invoices WHERE id = :id AND school_id = :school_id");
    $check_stmt->execute([':id' => $invoice_id, ':school_id' => $school_id]);
    $invoice = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$invoice) {
        echo json_encode(['success' => false, 'message' => 'Invoice not found or access denied']);
        $db->rollBack();
        exit;
    }
    
    // Check if invoice is already paid - prevent deletion of paid invoices
    if ($invoice['status'] === 'PAID') {
        echo json_encode(['success' => false, 'message' => 'Cannot delete a paid invoice']);
        $db->rollBack();
        exit;
    }
    
    // Delete invoice items first (if table exists)
    try {
        $table_check = $db->query("SHOW TABLES LIKE 'invoice_items'");
        if ($table_check->rowCount() > 0) {
            $delete_items = $db->prepare("DELETE FROM invoice_items WHERE invoice_id = :invoice_id");
            $delete_items->execute([':invoice_id' => $invoice_id]);
        }
    } catch (PDOException $e) {
        // Table might not exist, continue
        error_log("Invoice items table error: " . $e->getMessage());
    }
    
    // Delete the invoice
    $delete_invoice = $db->prepare("DELETE FROM invoices WHERE id = :id AND school_id = :school_id");
    $delete_invoice->execute([':id' => $invoice_id, ':school_id' => $school_id]);
    
    $db->commit();
    
    // Try to log the activity (skip if activity_logs table doesn't exist)
    try {
        $table_check = $db->query("SHOW TABLES LIKE 'activity_logs'");
        if ($table_check->rowCount() > 0) {
            $user_id = $_SESSION['user_id'] ?? 0;
            $user_email = $_SESSION['email'] ?? 'Unknown';
            $log_sql = "INSERT INTO activity_logs (school_id, user_id, user_email, action, details, ip_address, created_at) 
                        VALUES (:school_id, :user_id, :user_email, 'delete_invoice', :details, :ip_address, NOW())";
            $log_stmt = $db->prepare($log_sql);
            $details = "Deleted invoice #{$invoice['invoice_number']} (ID: $invoice_id)";
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
            $log_stmt->execute([
                ':school_id' => $school_id,
                ':user_id' => $user_id,
                ':user_email' => $user_email,
                ':details' => $details,
                ':ip_address' => $ip_address
            ]);
        }
    } catch (PDOException $e) {
        // Activity logs table doesn't exist, just continue
        error_log("Activity log error: " . $e->getMessage());
    }
    
    echo json_encode(['success' => true, 'message' => 'Invoice deleted successfully']);
    
} catch (PDOException $e) {
    $db->rollBack();
    error_log("Delete invoice PDO error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    $db->rollBack();
    error_log("Delete invoice error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>