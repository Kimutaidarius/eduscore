<?php
/**
 * API: Get Student Fee Balances
 * Endpoint: /feesystem/api/feesystem/get_fee_balances.php
 * Method: POST
 */

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'finance') {
    echo json_encode(['success' => false, 'message' => 'Access denied. Finance role required.']);
    exit;
}

require_once('../../includes/config.php');

$database = Database::getInstance();
$pdo = $database->getConnection();

$input = json_decode(file_get_contents('php://input'), true);
$school_id = $_SESSION['school_id'];

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
    // Get students in the selected class
    $sql = "SELECT s.id, s.AdmNo as admission_no, CONCAT(s.FirstName, ' ', s.SecondName, ' ', COALESCE(s.LastName, '')) as full_name,
                   c.class_level as class_name, str.stream_name,
                   s.Status as student_status
            FROM tblstudents s
            LEFT JOIN tblclasses c ON s.class_id = c.id
            LEFT JOIN tblstreams str ON s.StreamId = str.id
            WHERE s.school_id = :school_id AND s.class_id = :class_id";
    
    $params = [':school_id' => $school_id, ':class_id' => $class_id];
    
    if ($stream_id) {
        $sql .= " AND s.StreamId = :stream_id";
        $params[':stream_id'] = $stream_id;
    }
    
    if ($status) {
        $sql .= " AND s.Status = :status";
        $params[':status'] = $status;
    }
    
    $sql .= " ORDER BY s.FirstName ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // For each student, calculate fee balance
    $results = [];
    foreach ($students as $student) {
        // Get total expected fees for this student for the term
        $expected_sql = "SELECT SUM(amount) as total_expected 
                        FROM fee_structures 
                        WHERE school_id = :school_id 
                        AND class_level = (SELECT class_level FROM tblclasses WHERE id = :class_id)
                        AND academic_year = :year
                        AND status = 'active'";
        
        $expected_stmt = $pdo->prepare($expected_sql);
        $expected_stmt->execute([
            ':school_id' => $school_id,
            ':class_id' => $class_id,
            ':year' => $year
        ]);
        $expected_result = $expected_stmt->fetch(PDO::FETCH_ASSOC);
        $expected = floatval($expected_result['total_expected'] ?? 0);
        
        // Get total paid amount for this student
        $paid_sql = "SELECT SUM(amount) as total_paid 
                    FROM fee_transactions 
                    WHERE student_id = :student_id 
                    AND transaction_type = 'payment'
                    AND academic_year = :year
                    AND term = :term";
        
        $paid_stmt = $pdo->prepare($paid_sql);
        $paid_stmt->execute([
            ':student_id' => $student['id'],
            ':year' => $year,
            ':term' => $term
        ]);
        $paid_result = $paid_stmt->fetch(PDO::FETCH_ASSOC);
        $paid = floatval($paid_result['total_paid'] ?? 0);
        
        // Get balance from student_balances table
        $balance_sql = "SELECT balance FROM student_balances 
                       WHERE student_id = :student_id 
                       AND academic_year = :year 
                       AND term = :term";
        $balance_stmt = $pdo->prepare($balance_sql);
        $balance_stmt->execute([
            ':student_id' => $student['id'],
            ':year' => $year,
            ':term' => $term
        ]);
        $balance_result = $balance_stmt->fetch(PDO::FETCH_ASSOC);
        $balance = floatval($balance_result['balance'] ?? ($expected - $paid));
        
        $results[] = [
            'id' => $student['id'],
            'admission_no' => $student['admission_no'],
            'full_name' => trim($student['full_name']),
            'class_name' => $student['class_name'],
            'stream_name' => $student['stream_name'],
            'expected' => $expected,
            'paid' => $paid,
            'balance' => $balance,
            'status' => $student['student_status']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'students' => $results,
        'total_students' => count($results),
        'filters' => [
            'class_id' => $class_id,
            'stream_id' => $stream_id,
            'term' => $term,
            'year' => $year
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>