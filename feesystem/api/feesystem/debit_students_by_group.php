<?php
session_start();
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once('../../includes/config.php');

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$group_id = $data['group_id'] ?? 0;
$academic_year = $data['academic_year'] ?? date('Y');
$term = $data['term'] ?? 1;
$school_id = $_SESSION['school_id'];

try {
    global $db;
    
    if (!isset($db)) {
        throw new Exception('Database connection not established');
    }
    
    $db->beginTransaction();
    
    // Get group details (including default_amount if column exists)
    // First check if default_amount column exists
    $check_column = $db->query("SHOW COLUMNS FROM fee_groups LIKE 'default_amount'");
    $has_default_amount = $check_column->rowCount() > 0;
    
    if ($has_default_amount) {
        $stmt = $db->prepare("SELECT name, default_amount FROM fee_groups WHERE id = :id AND school_id = :school_id");
        $stmt->execute([':id' => $group_id, ':school_id' => $school_id]);
        $group = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $stmt = $db->prepare("SELECT name FROM fee_groups WHERE id = :id AND school_id = :school_id");
        $stmt->execute([':id' => $group_id, ':school_id' => $school_id]);
        $group = $stmt->fetch(PDO::FETCH_ASSOC);
        $group['default_amount'] = 0;
    }
    
    if (!$group) {
        throw new Exception('Fee group not found');
    }
    
    // Get vote heads in this group
    $stmt = $db->prepare("SELECT vote_head_id FROM fee_group_vote_heads WHERE group_id = :group_id");
    $stmt->execute([':group_id' => $group_id]);
    $vote_heads = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($vote_heads)) {
        throw new Exception('No vote heads found in this group');
    }
    
    $vote_head_ids = array_column($vote_heads, 'vote_head_id');
    $placeholders = implode(',', array_fill(0, count($vote_head_ids), '?'));
    
    // Get all active students - Using tblstudents table with proper column checks
    // First, get the actual columns in the table
    $table_columns = $db->query("DESCRIBE tblstudents")->fetchAll(PDO::FETCH_COLUMN);
    
    // Build query based on available columns
    $name_parts = [];
    if (in_array('FirstName', $table_columns)) {
        $name_parts[] = "COALESCE(s.FirstName, '')";
    }
    if (in_array('SecondName', $table_columns)) {
        $name_parts[] = "COALESCE(s.SecondName, '')";
    }
    if (in_array('LastName', $table_columns)) {
        $name_parts[] = "COALESCE(s.LastName, '')";
    }
    
    // If no name columns found, use a fallback
    if (empty($name_parts)) {
        $name_parts = ["CONCAT('Student_', s.id)"];
    }
    
    $student_name_sql = "TRIM(CONCAT_WS(' ', " . implode(', ', $name_parts) . ")) as student_name";
    
    $students_query = "SELECT s.id, $student_name_sql, COALESCE(s.AdmNo, CONCAT('STU', s.id)) as admission_no, s.class_id 
                       FROM tblstudents s
                       WHERE s.school_id = ? AND s.Status = 'Active'
                       ORDER BY s.AdmNo";
    
    $stmt = $db->prepare($students_query);
    $stmt->execute([$school_id]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get default amount from group
    $default_amount = floatval($group['default_amount'] ?? 0);
    
    $transactions = [];
    $total_debit = 0;
    
    // Check if fee_structures table exists and has the right columns
    $fee_structure_exists = $db->query("SHOW TABLES LIKE 'fee_structures'")->rowCount() > 0;
    $has_term_columns = false;
    
    if ($fee_structure_exists) {
        $fee_columns = $db->query("SHOW COLUMNS FROM fee_structures")->fetchAll(PDO::FETCH_COLUMN);
        $has_term_columns = in_array('term1', $fee_columns);
    }
    
    foreach ($students as $student) {
        // Calculate amount based on whether default amount is set
        if ($default_amount > 0) {
            // Use default amount instead of calculating
            $amount = $default_amount;
        } elseif ($fee_structure_exists) {
            // Get fee structure for this student's class from the selected vote heads
            if ($has_term_columns) {
                // Use term1, term2, term3 columns
                $fee_query = "SELECT SUM(term1 + term2 + term3) as total_fee 
                              FROM fee_structures fs
                              WHERE fs.class_level = ? AND fs.academic_year = ? AND fs.vote_head_id IN ($placeholders)";
            } else {
                // Use amount column
                $fee_query = "SELECT SUM(amount) as total_fee 
                              FROM fee_structures fs
                              WHERE fs.class_level = ? AND fs.academic_year = ? AND fs.vote_head_id IN ($placeholders)";
            }
            
            $params = array_merge([$student['class_id'], $academic_year], $vote_head_ids);
            $stmt = $db->prepare($fee_query);
            $stmt->execute($params);
            $fee_data = $stmt->fetch(PDO::FETCH_ASSOC);
            $amount = $fee_data['total_fee'] ?? 0;
        } else {
            $amount = 0;
        }
        
        if ($amount > 0) {
            // Check if student_balances table exists
            $check_balances = $db->query("SHOW TABLES LIKE 'student_balances'");
            if ($check_balances->rowCount() > 0) {
                // Insert or update student balance record
                $balance_query = "INSERT INTO student_balances (student_id, academic_year, term, balance, last_updated) 
                                  VALUES (:student_id, :academic_year, :term, :balance, NOW())
                                  ON DUPLICATE KEY UPDATE 
                                  balance = balance + VALUES(balance),
                                  last_updated = NOW()";
                
                $stmt = $db->prepare($balance_query);
                $stmt->execute([
                    ':student_id' => $student['id'],
                    ':academic_year' => $academic_year,
                    ':term' => $term,
                    ':balance' => $amount
                ]);
            }
            
            // Check if fee_transactions table exists
            $check_transactions = $db->query("SHOW TABLES LIKE 'fee_transactions'");
            if ($check_transactions->rowCount() > 0) {
                // Record transaction
                $transaction_query = "INSERT INTO fee_transactions 
                                      (student_id, group_id, amount, transaction_type, academic_year, term, description, created_at) 
                                      VALUES (:student_id, :group_id, :amount, 'debit', :academic_year, :term, :description, NOW())";
                
                $description = "Fee debit for group: {$group['name']}";
                $stmt = $db->prepare($transaction_query);
                $stmt->execute([
                    ':student_id' => $student['id'],
                    ':group_id' => $group_id,
                    ':amount' => $amount,
                    ':academic_year' => $academic_year,
                    ':term' => $term,
                    ':description' => $description
                ]);
            }
            
            $transactions[] = [
                'student' => $student['student_name'] ?: "Student {$student['id']}",
                'admission_no' => $student['admission_no'],
                'amount' => $amount
            ];
            $total_debit += $amount;
        }
    }
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "Successfully debited " . number_format($total_debit, 2) . " to " . count($transactions) . " students",
        'total_students' => count($transactions),
        'total_amount' => $total_debit,
        'transactions' => $transactions
    ]);
    
} catch (PDOException $e) {
    if (isset($db)) {
        $db->rollBack();
    }
    error_log("PDO Error in debit_students_by_group: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    if (isset($db)) {
        $db->rollBack();
    }
    error_log("Error in debit_students_by_group: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>