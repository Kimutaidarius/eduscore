<?php
/**
 * ajax/check_payment_status.php
 * Checks payment status using tblpayments table
 */

session_start();
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');

// Simple error response function
function sendError($message, $code = 500) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

// Check authentication
if (!isset($_SESSION['school_id'])) {
    sendError('Unauthorized', 403);
}

$school_id = (int) $_SESSION['school_id'];

// Get reference from request - support both JSON and form data
$input = json_decode(file_get_contents('php://input'), true);
$reference = $input['reference'] ?? $_POST['reference'] ?? $_GET['reference'] ?? '';

if (empty($reference)) {
    sendError('Payment reference is required', 400);
}

// Clean reference
$reference = preg_replace('/[^a-zA-Z0-9\-_]/', '', $reference);

try {
    // Check database connection
    if (!isset($dbh) || !$dbh) {
        sendError('Database connection failed');
    }
    
    // Query using the actual column names from your table
    $query = "SELECT * FROM tblpayments 
              WHERE school_id = :school_id 
              AND reference = :reference 
              ORDER BY created_at DESC LIMIT 1";
    
    $stmt = $dbh->prepare($query);
    $stmt->execute([
        ':school_id' => $school_id,
        ':reference' => $reference
    ]);
    
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$payment) {
        // Also try searching by checkout_request_id if reference not found
        $query2 = "SELECT * FROM tblpayments 
                   WHERE school_id = :school_id 
                   AND checkout_request_id = :reference 
                   ORDER BY created_at DESC LIMIT 1";
        
        $stmt2 = $dbh->prepare($query2);
        $stmt2->execute([
            ':school_id' => $school_id,
            ':reference' => $reference
        ]);
        
        $payment = $stmt2->fetch(PDO::FETCH_ASSOC);
    }
    
    if (!$payment) {
        echo json_encode([
            'success' => true,
            'status' => 'not_found',
            'message' => 'Payment not found',
            'reference' => $reference
        ]);
        exit;
    }
    
    // Map the database columns to readable names
    $response = [
        'success' => true,
        'status' => $payment['status'],
        'message' => 'Payment status retrieved',
        'data' => [
            'id' => $payment['id'],
            'school_id' => $payment['school_id'],
            'phone' => $payment['phone'],
            'amount' => $payment['amount'],
            'reference' => $payment['reference'],
            'checkout_request_id' => $payment['checkout_request_id'],
            'transaction_id' => $payment['transaction_id'],
            'created_at' => $payment['created_at'],
            'paid_at' => $payment['paid_at']
        ]
    ];
    
    // Add status-specific messages and additional data
    switch($payment['status']) {
        case 'paid':
            $response['message'] = 'Payment completed successfully';
            break;
            
        case 'failed':
            $response['message'] = 'Payment failed';
            break;
            
        case 'pending':
            $response['message'] = 'Payment is pending';
            // Add time elapsed
            $created = strtotime($payment['created_at']);
            $elapsed = time() - $created;
            $response['data']['seconds_elapsed'] = $elapsed;
            $response['data']['minutes_elapsed'] = round($elapsed / 60);
            
            // Check if payment is taking too long (more than 2 minutes)
            if ($elapsed > 120) {
                $response['data']['timeout_warning'] = true;
                $response['message'] = 'Payment is taking longer than expected';
            } else {
                $response['data']['timeout_warning'] = false;
            }
            break;
    }
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    error_log("Payment check error: " . $e->getMessage());
    sendError('Database error occurred. Please try again.');
} catch (Exception $e) {
    error_log("Payment check error: " . $e->getMessage());
    sendError('An error occurred. Please try again.');
}
?>