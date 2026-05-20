<?php
/**
 * API: Void/Cancel Other Income Receipt
 * Endpoint: /feesystem/api/feesystem/void_other_income_receipt.php
 * Method: PUT
 * 
 * Request Body:
 * {
 *   "receipt_id": 123,
 *   "reason": "Reason for voiding"
 * }
 */

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

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

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['receipt_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Receipt ID is required']);
    exit;
}

require_once('../../includes/config.php');

$receipt_id = intval($input['receipt_id']);
$school_id = $_SESSION['school_id'];
$reason = $input['reason'] ?? 'No reason provided';

// Begin transaction
mysqli_begin_transaction($conn);

try {
    // Check if receipt exists and is active
    $check_sql = "SELECT id, receipt_number FROM other_income_receipts 
                  WHERE id = ? AND school_id = ? AND status = 'active'";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, 'ii', $receipt_id, $school_id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    
    if (mysqli_num_rows($check_result) == 0) {
        throw new Exception('Receipt not found or already voided');
    }
    
    $receipt = mysqli_fetch_assoc($check_result);
    
    // Void the receipt
    $void_sql = "UPDATE other_income_receipts SET status = 'void', notes = CONCAT(IFNULL(notes, ''), ' [VOIDED: ', ?, ']') 
                 WHERE id = ?";
    $void_stmt = mysqli_prepare($conn, $void_sql);
    $void_reason = date('Y-m-d H:i:s') . ' - ' . $reason;
    mysqli_stmt_bind_param($void_stmt, 'si', $void_reason, $receipt_id);
    mysqli_stmt_execute($void_stmt);
    
    // Also update fee_transactions to mark as void if possible
    $ft_sql = "UPDATE fee_transactions SET status = 'void' WHERE receipt_no = ? AND school_id = ?";
    $ft_stmt = mysqli_prepare($conn, $ft_sql);
    mysqli_stmt_bind_param($ft_stmt, 'si', $receipt['receipt_number'], $school_id);
    mysqli_stmt_execute($ft_stmt);
    
    mysqli_commit($conn);
    
    echo json_encode([
        'success' => true,
        'message' => 'Receipt voided successfully'
    ]);
    
} catch (Exception $e) {
    mysqli_rollback($conn);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

mysqli_close($conn);
?>