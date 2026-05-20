<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once('../../includes/config.php');

$input = json_decode(file_get_contents('php://input'), true);
$school_id = $input['school_id'] ?? $_SESSION['school_id'];
$class_id = $input['class_id'] ?? null;
$stream_id = $input['stream_id'] ?? null;
$term = $input['term'] ?? 1;
$year = $input['year'] ?? date('Y');
$status = $input['status'] ?? null;

if (!$class_id) {
    echo json_encode(['success' => false, 'message' => 'Class ID is required']);
    exit;
}

try {
    $database = Database::getInstance();
    $db = $database->getConnection();
    
    // Get fee structures for the selected class and term
    $fee_query = "SELECT fs.*, vh.name as vote_head_name, vh.alias 
                  FROM fee_structures fs
                  LEFT JOIN vote_heads vh ON fs.vote_head_id = vh.id
                  WHERE fs.school_id = :school_id 
                  AND fs.class_level = (SELECT class_level FROM tblclasses WHERE id = :class_id)
                  AND fs.academic_year = :year
                  AND fs.status = 'active'";
    
    $fee_stmt = $db->prepare($fee_query);
    $fee_stmt->execute([
        ':school_id' => $school_id,
        ':class_id' => $class_id,
        ':year' => $year
    ]);
    $fee_structures = $fee_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate expected fee per student based on term
    $expected_per_student = 0;
    foreach ($fee_structures as $fee) {
        if ($term == 1 && $fee['term1'] > 0) {
            $expected_per_student += $fee['term1'];
        } elseif ($term == 2 && $fee['term2'] > 0) {
            $expected_per_student += $fee['term2'];
        } elseif ($term == 3 && $fee['term3'] > 0) {
            $expected_per_student += $fee['term3'];
        } elseif ($fee['amount'] > 0) {
            $expected_per_student += $fee['amount'];
        }
    }
    
    // Get next term's expected fee
    $next_term_fee = 0;
    $next_term = $term + 1;
    if ($next_term <= 3) {
        foreach ($fee_structures as $fee) {
            if ($next_term == 1 && $fee['term1'] > 0) {
                $next_term_fee += $fee['term1'];
            } elseif ($next_term == 2 && $fee['term2'] > 0) {
                $next_term_fee += $fee['term2'];
            } elseif ($next_term == 3 && $fee['term3'] > 0) {
                $next_term_fee += $fee['term3'];
            } elseif ($fee['amount'] > 0) {
                $next_term_fee += $fee['amount'];
            }
        }
    }
    
    // Build query to get students with their payment details
    $query = "SELECT 
                s.id,
                s.AdmNo as admission_no,
                CONCAT(s.FirstName, ' ', s.SecondName, ' ', COALESCE(s.LastName, '')) as full_name,
                s.Status as student_status,
                c.class_level as class_name,
                st.stream_name,
                s.Gender,
                s.GuardianName,
                s.GuardianPhone,
                s.BoardingStatus
              FROM tblstudents s
              LEFT JOIN tblclasses c ON s.class_id = c.id
              LEFT JOIN tblstreams st ON s.StreamId = st.id
              WHERE s.school_id = :school_id 
              AND s.class_id = :class_id
              AND s.Status = 'Active'";
    
    $params = [
        ':school_id' => $school_id,
        ':class_id' => $class_id
    ];
    
    if ($stream_id) {
        $query .= " AND s.StreamId = :stream_id";
        $params[':stream_id'] = $stream_id;
    }
    
    if ($status) {
        $query .= " AND s.Status = :status";
        $params[':status'] = $status;
    }
    
    $query .= " ORDER BY s.AdmNo";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate paid amount and balance for each student
    foreach ($students as &$student) {
        // Get total paid for this term/year from fee_transactions
        $payment_query = "SELECT COALESCE(SUM(amount), 0) as paid_amount 
                          FROM fee_transactions 
                          WHERE student_id = :student_id 
                          AND academic_year = :year 
                          AND term = :term 
                          AND transaction_type = 'payment'";
        
        $payment_stmt = $db->prepare($payment_query);
        $payment_stmt->execute([
            ':student_id' => $student['id'],
            ':year' => $year,
            ':term' => $term
        ]);
        $payment_result = $payment_stmt->fetch(PDO::FETCH_ASSOC);
        $paid_amount = $payment_result['paid_amount'] ?? 0;
        
        // Get debit amounts (fee charges) for this term/year
        $debit_query = "SELECT COALESCE(SUM(amount), 0) as debit_amount 
                        FROM fee_transactions 
                        WHERE student_id = :student_id 
                        AND academic_year = :year 
                        AND term = :term 
                        AND transaction_type = 'debit'";
        
        $debit_stmt = $db->prepare($debit_query);
        $debit_stmt->execute([
            ':student_id' => $student['id'],
            ':year' => $year,
            ':term' => $term
        ]);
        $debit_result = $debit_stmt->fetch(PDO::FETCH_ASSOC);
        $debit_amount = $debit_result['debit_amount'] ?? 0;
        
        // Expected amount is the fee structure amount (or debit amount if set)
        $expected_amount = ($debit_amount > 0) ? $debit_amount : $expected_per_student;
        
        $student['expected_amount'] = floatval($expected_amount);
        $student['paid_amount'] = floatval($paid_amount);
        $student['balance'] = $student['expected_amount'] - $student['paid_amount'];
        $student['next_term_fee'] = $next_term_fee;
    }
    
    echo json_encode([
        'success' => true,
        'students' => $students,
        'total_students' => count($students),
        'fee_structure' => [
            'expected_per_student' => $expected_per_student,
            'next_term_fee' => $next_term_fee,
            'breakdown' => $fee_structures
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_detailed_fee_balances: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch fee details: ' . $e->getMessage()
    ]);
}
?>