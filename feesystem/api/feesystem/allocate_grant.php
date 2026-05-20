<?php
// /feesystem/api/feesystem/allocate_grant.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once('../../includes/config.php');

$database = Database::getInstance();
$db = $database->getConnection();

$data = json_decode(file_get_contents('php://input'), true);
$school_id = $_SESSION['school_id'];
$grant_id = intval($data['grant_id'] ?? 0);
$student_id = intval($data['student_id'] ?? 0);
$amount = floatval($data['amount'] ?? 0);
$notes = trim($data['notes'] ?? '');
$user_id = $_SESSION['user_id'] ?? 0;

if ($grant_id <= 0 || $student_id <= 0 || $amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid request parameters']);
    exit;
}

try {
    $db->beginTransaction();
    
    // Get grant details and lock for update
    $grantQuery = "SELECT * FROM grants WHERE id = :grant_id AND school_id = :school_id FOR UPDATE";
    $grantStmt = $db->prepare($grantQuery);
    $grantStmt->execute([':grant_id' => $grant_id, ':school_id' => $school_id]);
    $grant = $grantStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$grant) {
        echo json_encode(['success' => false, 'message' => 'Grant not found']);
        exit;
    }
    
    if ($amount > $grant['remaining_balance']) {
        echo json_encode(['success' => false, 'message' => 'Amount exceeds remaining balance']);
        exit;
    }
    
    // Verify student belongs to this school
    $studentQuery = "SELECT id, FirstName, SecondName, LastName, AdmNo, class_id, StreamId 
                     FROM tblstudents 
                     WHERE id = :student_id AND school_id = :school_id";
    $studentStmt = $db->prepare($studentQuery);
    $studentStmt->execute([':student_id' => $student_id, ':school_id' => $school_id]);
    $student = $studentStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        exit;
    }
    
    // Create allocation record
    $allocQuery = "INSERT INTO grant_allocations (grant_id, student_id, amount, allocated_by, notes) 
                   VALUES (:grant_id, :student_id, :amount, :allocated_by, :notes)";
    $allocStmt = $db->prepare($allocQuery);
    $allocStmt->execute([
        ':grant_id' => $grant_id,
        ':student_id' => $student_id,
        ':amount' => $amount,
        ':allocated_by' => $user_id,
        ':notes' => $notes
    ]);
    
    // Log the allocation
    $logQuery = "INSERT INTO grant_allocations_log (grant_id, student_id, amount, action, allocated_by, notes) 
                 VALUES (:grant_id, :student_id, :amount, 'allocated', :allocated_by, :notes)";
    $logStmt = $db->prepare($logQuery);
    $logStmt->execute([
        ':grant_id' => $grant_id,
        ':student_id' => $student_id,
        ':amount' => $amount,
        ':allocated_by' => $user_id,
        ':notes' => $notes
    ]);
    
    // Update grant balances
    $newAllocated = $grant['allocated_amount'] + $amount;
    $newRemaining = $grant['remaining_balance'] - $amount;
    $newStatus = $newRemaining > 0 ? 'active' : 'exhausted';
    
    $updateQuery = "UPDATE grants 
                    SET allocated_amount = :allocated_amount,
                        remaining_balance = :remaining_balance,
                        status = :status
                    WHERE id = :grant_id";
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->execute([
        ':allocated_amount' => $newAllocated,
        ':remaining_balance' => $newRemaining,
        ':status' => $newStatus,
        ':grant_id' => $grant_id
    ]);
    
    // Create fee transaction for the student (credit the grant amount)
    $year = date('Y');
    $term = $data['term'] ?? 1;
    
    $feeTransactionQuery = "INSERT INTO fee_transactions (student_id, amount, transaction_type, academic_year, term, description, created_at, school_id) 
                            VALUES (:student_id, :amount, 'credit', :academic_year, :term, :description, NOW(), :school_id)";
    $feeStmt = $db->prepare($feeTransactionQuery);
    $feeStmt->execute([
        ':student_id' => $student_id,
        ':amount' => $amount,
        ':academic_year' => $year,
        ':term' => $term,
        ':description' => "Grant allocation: {$grant['name']} - " . ($notes ?: "Financial aid"),
        ':school_id' => $school_id
    ]);
    
    // Update or create student balance
    $balanceQuery = "INSERT INTO student_balances (student_id, academic_year, term, balance, last_updated, school_id) 
                     VALUES (:student_id, :academic_year, :term, -:amount, NOW(), :school_id)
                     ON DUPLICATE KEY UPDATE 
                     balance = balance - :amount,
                     last_updated = NOW()";
    $balanceStmt = $db->prepare($balanceQuery);
    $balanceStmt->execute([
        ':student_id' => $student_id,
        ':academic_year' => $year,
        ':term' => $term,
        ':amount' => $amount,
        ':school_id' => $school_id
    ]);
    
    $db->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => "KES " . number_format($amount, 2) . " allocated successfully",
        'remaining_balance' => $newRemaining
    ]);
    
} catch (PDOException $e) {
    $db->rollBack();
    error_log("Allocate grant error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>