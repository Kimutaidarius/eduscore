<?php
session_start();
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once('../../includes/config.php');

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$school_id = $_SESSION['school_id'];
$class_id = $data['class_id'] ?? 0;
$vote_head_id = $data['vote_head_id'] ?? 0;
$year = $data['year'] ?? date('Y');
$term = $data['term'] ?? 1;
$recorded_at = $data['recorded_at'] ?? date('Y-m-d');
$balances = $data['balances'] ?? [];

if (empty($balances)) {
    echo json_encode(['success' => false, 'message' => 'No balances to save']);
    exit;
}

try {
    global $db;
    
    if (!isset($db)) {
        throw new Exception('Database connection not established');
    }
    
    $db->beginTransaction();
    
    $saved_count = 0;
    
    foreach ($balances as $balance) {
        $student_id = $balance['student_id'];
        $amount = floatval($balance['amount']);
        
        // Check if balance record exists (without vote_head_id)
        $check_stmt = $db->prepare("SELECT id FROM student_balances 
                                    WHERE student_id = :student_id 
                                    AND academic_year = :year 
                                    AND term = :term");
        $check_stmt->execute([
            ':student_id' => $student_id,
            ':year' => $year,
            ':term' => $term
        ]);
        
        $existing = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            // Update existing record - add to current balance
            $stmt = $db->prepare("UPDATE student_balances 
                                  SET balance = balance + :amount, 
                                      last_updated = NOW()
                                  WHERE student_id = :student_id 
                                  AND academic_year = :year 
                                  AND term = :term");
            $stmt->execute([
                ':student_id' => $student_id,
                ':year' => $year,
                ':term' => $term,
                ':amount' => $amount
            ]);
        } else {
            // Insert new record
            $stmt = $db->prepare("INSERT INTO student_balances 
                                  (student_id, academic_year, term, balance, last_updated) 
                                  VALUES (:student_id, :year, :term, :amount, NOW())");
            $stmt->execute([
                ':student_id' => $student_id,
                ':year' => $year,
                ':term' => $term,
                ':amount' => $amount
            ]);
        }
        
        // Also record in fee_transactions for audit trail
        // First check if fee_transactions table has the expected columns
        $check_fee_trans = $db->query("SHOW COLUMNS FROM fee_transactions");
        $fee_columns = [];
        while ($col = $check_fee_trans->fetch(PDO::FETCH_ASSOC)) {
            $fee_columns[] = $col['Field'];
        }
        
        if (in_array('vote_head_id', $fee_columns)) {
            $transaction_stmt = $db->prepare("INSERT INTO fee_transactions 
                                              (student_id, vote_head_id, amount, transaction_type, academic_year, term, description, created_at) 
                                              VALUES (:student_id, :vote_head_id, :amount, 'initial_balance', :year, :term, :description, NOW())");
            $transaction_stmt->execute([
                ':student_id' => $student_id,
                ':vote_head_id' => $vote_head_id,
                ':amount' => $amount,
                ':year' => $year,
                ':term' => $term,
                ':description' => "Initial balance setup for " . date('Y-m-d')
            ]);
        } else {
            // Fallback if vote_head_id doesn't exist
            $transaction_stmt = $db->prepare("INSERT INTO fee_transactions 
                                              (student_id, amount, transaction_type, academic_year, term, description, created_at) 
                                              VALUES (:student_id, :amount, 'initial_balance', :year, :term, :description, NOW())");
            $transaction_stmt->execute([
                ':student_id' => $student_id,
                ':amount' => $amount,
                ':year' => $year,
                ':term' => $term,
                ':description' => "Initial balance setup for vote head ID: $vote_head_id on " . date('Y-m-d')
            ]);
        }
        
        $saved_count++;
    }
    
    $db->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => "Successfully saved {$saved_count} balance records",
        'saved_count' => $saved_count
    ]);
    
} catch (PDOException $e) {
    $db->rollBack();
    error_log("PDO Error in save_initial_balances: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    $db->rollBack();
    error_log("Error in save_initial_balances: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>