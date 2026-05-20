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
$stream_id = $data['stream_id'] ?? null;
$vote_head_id = $data['vote_head_id'] ?? 0;
$year = $data['year'] ?? date('Y');
$term = $data['term'] ?? 1;

try {
    global $db;
    
    if (!isset($db)) {
        throw new Exception('Database connection not established');
    }
    
    // Get students in the selected class/stream
    $query = "SELECT s.id, s.AdmNo as admission_no, s.FirstName, s.SecondName, s.LastName, s.Gender,
              COALESCE(sb.balance, 0) as previous_balance
              FROM tblstudents s
              LEFT JOIN student_balances sb ON s.id = sb.student_id 
                  AND sb.academic_year = :year 
                  AND sb.term = :term
              WHERE s.school_id = :school_id 
              AND s.class_id = :class_id 
              AND s.Status = 'Active'";
    
    $params = [
        ':year' => $year,
        ':term' => $term,
        ':school_id' => $school_id,
        ':class_id' => $class_id
    ];
    
    if ($stream_id) {
        $query .= " AND s.StreamId = :stream_id";
        $params[':stream_id'] = $stream_id;
    }
    
    $query .= " ORDER BY s.AdmNo";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format student names
    foreach ($students as &$student) {
        $student['full_name'] = trim($student['FirstName'] . ' ' . ($student['SecondName'] ?? '') . ' ' . ($student['LastName'] ?? ''));
        $student['initial_balance'] = 0;
        $student['previous_balance'] = floatval($student['previous_balance']);
    }
    
    echo json_encode(['success' => true, 'students' => $students]);
    
} catch (PDOException $e) {
    error_log("PDO Error in get_students_balances: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage(), 'students' => []]);
} catch (Exception $e) {
    error_log("Error in get_students_balances: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage(), 'students' => []]);
}
?>