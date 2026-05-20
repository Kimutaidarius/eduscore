<?php
// /feesystem/api/feesystem/process_payment.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'finance') {
    echo json_encode(['success' => false, 'message' => 'Access denied. Finance privileges required.']);
    exit;
}

require_once '../../includes/config.php';

$database = Database::getInstance();
$db = $database->getConnection();

$data = json_decode(file_get_contents('php://input'), true);
$school_id = $data['school_id'] ?? $_SESSION['school_id'];
$student_id = $data['student_id'] ?? 0;
$amount = $data['amount'] ?? 0;
$payment_date = $data['payment_date'] ?? date('Y-m-d');
$receipt_no = $data['receipt_no'] ?? null;
$payment_mode = $data['payment_mode'] ?? '';
$payment_code = $data['payment_code'] ?? '';
$year = $data['year'] ?? date('Y');

if ($student_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Student ID is required']);
    exit;
}

if ($amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Valid amount is required']);
    exit;
}

if (empty($payment_mode)) {
    echo json_encode(['success' => false, 'message' => 'Payment mode is required']);
    exit;
}

try {
    $db->beginTransaction();
    
    // Check if receipt number already exists
    if ($receipt_no) {
        $check_stmt = $db->prepare("SELECT id FROM fee_transactions WHERE receipt_no = :receipt_no AND school_id = :school_id");
        $check_stmt->execute([':receipt_no' => $receipt_no, ':school_id' => $school_id]);
        if ($check_stmt->rowCount() > 0) {
            // Generate new receipt number if duplicate
            $receipt_no = 'RCP-' . date('Ymd') . '-' . rand(1000, 9999);
        }
    } else {
        $receipt_no = 'RCP-' . date('Ymd') . '-' . rand(1000, 9999);
    }
    
    // Insert payment transaction
    $insert_stmt = $db->prepare("
        INSERT INTO fee_transactions (
            student_id, amount, transaction_type, academic_year, term, 
            description, school_id, created_at, payment_mode, receipt_no, payment_code
        ) VALUES (
            :student_id, :amount, 'payment', :year, 0,
            :description, :school_id, NOW(), :payment_mode, :receipt_no, :payment_code
        )
    ");
    
    $description = "Fee payment via " . ucfirst($payment_mode);
    
    $insert_stmt->execute([
        ':student_id' => $student_id,
        ':amount' => $amount,
        ':year' => $year,
        ':description' => $description,
        ':school_id' => $school_id,
        ':payment_mode' => $payment_mode,
        ':receipt_no' => $receipt_no,
        ':payment_code' => $payment_code
    ]);
    
    $transaction_id = $db->lastInsertId();
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Payment processed successfully',
        'transaction_id' => $transaction_id,
        'receipt_no' => $receipt_no
    ]);
    
} catch (PDOException $e) {
    $db->rollBack();
    error_log("Process payment error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    $db->rollBack();
    error_log("Process payment error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>