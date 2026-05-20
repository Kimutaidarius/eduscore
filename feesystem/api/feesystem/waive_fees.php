<?php
// /feesystem/api/feesystem/waive_fees.php
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
$fee_item_id = $data['fee_item_id'] ?? 0;
$amount = $data['amount'] ?? 0;
$reason = $data['reason'] ?? '';

if ($student_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Student ID is required']);
    exit;
}

if ($fee_item_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Fee item is required']);
    exit;
}

if ($amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Valid amount is required']);
    exit;
}

try {
    $db->beginTransaction();
    
    // Get the original debit to check amount
    $debit_stmt = $db->prepare("SELECT amount, group_id FROM fee_transactions WHERE id = :id AND student_id = :student_id AND transaction_type = 'debit'");
    $debit_stmt->execute([':id' => $fee_item_id, ':student_id' => $student_id]);
    $debit = $debit_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$debit) {
        echo json_encode(['success' => false, 'message' => 'Fee item not found']);
        $db->rollBack();
        exit;
    }
    
    if ($amount > $debit['amount']) {
        echo json_encode(['success' => false, 'message' => 'Waiver amount cannot exceed the original debit amount']);
        $db->rollBack();
        exit;
    }
    
    // Create a credit transaction for the waiver
    $insert_stmt = $db->prepare("
        INSERT INTO fee_transactions (
            student_id, group_id, amount, transaction_type, academic_year, term,
            description, school_id, created_at, payment_mode
        ) VALUES (
            :student_id, :group_id, :amount, 'credit', :year, :term,
            :description, :school_id, NOW(), 'waiver'
        )
    ");
    
    $description = "Fee waiver: " . ($reason ?: "Administrative adjustment");
    $year = date('Y');
    $term = 0;
    
    $insert_stmt->execute([
        ':student_id' => $student_id,
        ':group_id' => $debit['group_id'],
        ':amount' => $amount,
        ':year' => $year,
        ':term' => $term,
        ':description' => $description,
        ':school_id' => $school_id
    ]);
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Fees waived successfully'
    ]);
    
} catch (PDOException $e) {
    $db->rollBack();
    error_log("Waive fees error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    $db->rollBack();
    error_log("Waive fees error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>