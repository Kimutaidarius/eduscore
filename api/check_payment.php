<?php
require_once('../includes/config.php');

// Set header FIRST to ensure JSON output
header('Content-Type: application/json');

// Get parameters safely
$checkout_id = $_GET['checkout_id'] ?? '';
$school_id = (int) ($_GET['school_id'] ?? 0);

// Debug logging (temporary - remove after fixing)
error_log("DEBUG check_payment: checkout_id='$checkout_id', school_id='$school_id'");

// Validate input more thoroughly
if (empty($checkout_id) || $checkout_id === 'undefined' || $school_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid or missing parameters']);
    exit;
}

try {
    // Check payment status in database
    $stmt = $dbh->prepare("
        SELECT status, mpesa_receipt, transaction_id, error_message 
        FROM payments 
        WHERE checkout_request_id = :checkout_id 
        AND school_id = :school_id
    ");
    
    $stmt->execute([
        ':checkout_id' => $checkout_id,
        ':school_id' => $school_id
    ]);
    
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($payment) {
        echo json_encode([
            'success' => true,
            'status' => $payment['status'],
            'mpesa_receipt' => $payment['mpesa_receipt'],
            'transaction_id' => $payment['transaction_id'],
            'error_message' => $payment['error_message']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Payment record not found']);
    }
    
} catch (PDOException $e) {
    // Log the database error for debugging
    error_log("Database error in check_payment.php: " . $e->getMessage());
    
    // Return a safe JSON error
    echo json_encode([
        'success' => false, 
        'message' => 'Database error occurred while checking payment status'
    ]);
}
?>