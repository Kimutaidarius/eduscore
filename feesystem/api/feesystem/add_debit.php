<?php
// /feesystem/api/feesystem/add_debit.php
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
$vote_head_id = $data['vote_head_id'] ?? 0;
$amount = $data['amount'] ?? 0;
$description = $data['description'] ?? '';
$term = $data['term'] ?? 1;
$year = $data['year'] ?? date('Y');

if ($student_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Student ID is required']);
    exit;
}

if ($vote_head_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Vote head is required']);
    exit;
}

if ($amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Valid amount is required']);
    exit;
}

try {
    $db->beginTransaction();
    
    // Insert debit transaction
    $insert_stmt = $db->prepare("
        INSERT INTO fee_transactions (
            student_id, group_id, amount, transaction_type, academic_year, term, 
            description, school_id, created_at
        ) VALUES (
            :student_id, :group_id, :amount, 'debit', :year, :term,
            :description, :school_id, NOW()
        )
    ");
    
    $insert_stmt->execute([
        ':student_id' => $student_id,
        ':group_id' => $vote_head_id,
        ':amount' => $amount,
        ':year' => $year,
        ':term' => $term,
        ':description' => $description,
        ':school_id' => $school_id
    ]);
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Debit added successfully'
    ]);
    
} catch (PDOException $e) {
    $db->rollBack();
    error_log("Add debit error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    $db->rollBack();
    error_log("Add debit error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>