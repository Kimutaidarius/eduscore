<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once('../../includes/config.php');

$data = json_decode(file_get_contents('php://input'), true);
$school_id = $data['school_id'] ?? 0;
$from_date = $data['from_date'] ?? date('Y-m-01');
$to_date = $data['to_date'] ?? date('Y-m-d');
$class_id = $data['class_id'] ?? '';
$stream_id = $data['stream_id'] ?? '';

if (!$school_id) {
    echo json_encode(['success' => false, 'message' => 'School ID required']);
    exit;
}

try {
    // First, check if tables exist
    $tables_to_check = ['tblstudents', 'tblclasses', 'fee_structures', 'fee_transactions', 'other_income_receipts'];
    $missing_tables = [];
    
    foreach ($tables_to_check as $table) {
        $stmt = $db->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() == 0) {
            $missing_tables[] = $table;
        }
    }
    
    if (!empty($missing_tables)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Missing tables: ' . implode(', ', $missing_tables),
            'students' => []
        ]);
        exit;
    }
    
    // Check columns in fee_transactions
    $stmt = $db->query("DESCRIBE fee_transactions");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    error_log("fee_transactions columns: " . implode(', ', $columns));
    
    // Get all active students
$sql = "SELECT s.id, s.AdmNo as admission_no, 
               CONCAT(TRIM(s.FirstName), ' ', TRIM(COALESCE(s.SecondName, '')), ' ', TRIM(COALESCE(s.LastName, ''))) as student_name,
               c.class_level as class_name, s.StreamId as stream_id
        FROM tblstudents s
        LEFT JOIN tblclasses c ON s.class_id = c.id
        WHERE s.school_id = ? AND s.Status = 'Active'";
    $params = [$school_id];
    
    if (!empty($class_id)) {
        $sql .= " AND s.class_id = ?";
        $params[] = $class_id;
    }
    
    $sql .= " ORDER BY s.FirstName ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Found " . count($students) . " students");
    
    $results = [];
    $total_expected = 0;
    $total_paid = 0;
    $total_balance = 0;
    
    // Get fee structures
    $stmt2 = $db->prepare("
        SELECT COALESCE(SUM(amount), 0) as expected 
        FROM fee_structures 
        WHERE school_id = ? AND status = 'active'
    ");
    $stmt2->execute([$school_id]);
    $expected_fees = $stmt2->fetch(PDO::FETCH_ASSOC);
    $expected_amount = $expected_fees['expected'] ?? 0;
    
    error_log("Expected amount per student: " . $expected_amount);
    
    foreach ($students as $student) {
        $paid = 0;
        
        // Try to get payments from fee_transactions
        try {
            // Check if student_id column exists
            if (in_array('student_id', $columns)) {
                $stmt3 = $db->prepare("
                    SELECT COALESCE(SUM(amount), 0) as paid 
                    FROM fee_transactions 
                    WHERE student_id = ? AND transaction_type = 'payment' 
                    AND DATE(created_at) BETWEEN ? AND ?
                ");
                $stmt3->execute([$student['id'], $from_date, $to_date]);
                $paid = $stmt3->fetch(PDO::FETCH_ASSOC)['paid'];
                error_log("Student {$student['id']} paid from fee_transactions: " . $paid);
            }
        } catch (PDOException $e) {
            error_log("Error in fee_transactions: " . $e->getMessage());
        }
        
        // Also check other_income_receipts
        try {
            $stmt4 = $db->prepare("
                SELECT COALESCE(SUM(total_amount), 0) as paid 
                FROM other_income_receipts 
                WHERE payer_type = 'student' AND payer_id = ? AND status = 'active'
                AND DATE(created_at) BETWEEN ? AND ?
            ");
            $stmt4->execute([$student['id'], $from_date, $to_date]);
            $other_paid = $stmt4->fetch(PDO::FETCH_ASSOC)['paid'];
            $paid += $other_paid;
            error_log("Student {$student['id']} paid from other_income: " . $other_paid);
        } catch (PDOException $e) {
            error_log("Error in other_income_receipts: " . $e->getMessage());
        }
        
        $balance = $expected_amount - $paid;
        
        $results[] = [
            'admission_no' => $student['admission_no'],
            'student_name' => trim($student['student_name']),
            'class_name' => $student['class_name'] ?? 'N/A',
            'expected_fees' => (float)$expected_amount,
            'paid_amount' => (float)$paid,
            'balance' => (float)$balance
        ];
        
        $total_expected += $expected_amount;
        $total_paid += $paid;
        $total_balance += $balance;
    }
    
    echo json_encode([
        'success' => true,
        'students' => $results,
        'total_expected' => $total_expected,
        'total_paid' => $total_paid,
        'total_balance' => $total_balance
    ]);
    
} catch (PDOException $e) {
    error_log("PDO Error in get_fee_register: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage(), 
        'students' => []
    ]);
} catch (Exception $e) {
    error_log("General error in get_fee_register: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage(), 
        'students' => []
    ]);
}
?>