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

$group_id = $_GET['group_id'] ?? 0;
$academic_year = $_GET['year'] ?? date('Y');
$term = $_GET['term'] ?? 1;
$school_id = $_SESSION['school_id'];

try {
    global $db;
    
    if (!isset($db)) {
        throw new Exception('Database connection not established');
    }
    
    // Get the actual columns in tblstudents
    $table_columns = $db->query("DESCRIBE tblstudents")->fetchAll(PDO::FETCH_COLUMN);
    
    // Build name concatenation based on available columns
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
    
    if (empty($name_parts)) {
        $student_name_sql = "CONCAT('Student_', s.id) as student_name";
    } else {
        $student_name_sql = "TRIM(CONCAT_WS(' ', " . implode(', ', $name_parts) . ")) as student_name";
    }
    
    // Build the query (without vote_head_id)
    $query = "SELECT s.id, 
              $student_name_sql,
              COALESCE(s.AdmNo, CONCAT('STU', s.id)) as admission_no,
              s.class_id,
              COALESCE(sb.balance, 0) as balance
              FROM tblstudents s
              LEFT JOIN student_balances sb ON s.id = sb.student_id 
                  AND sb.academic_year = :academic_year 
                  AND sb.term = :term
              WHERE s.school_id = :school_id 
              AND s.Status = 'Active'
              ORDER BY s.AdmNo";
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        ':academic_year' => $academic_year,
        ':term' => $term,
        ':school_id' => $school_id
    ]);
    
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get class levels for each student
    foreach ($students as &$student) {
        $class_stmt = $db->prepare("SELECT class_level FROM tblclasses WHERE id = :class_id AND school_id = :school_id");
        $class_stmt->execute([
            ':class_id' => $student['class_id'],
            ':school_id' => $school_id
        ]);
        $class = $class_stmt->fetch(PDO::FETCH_ASSOC);
        $student['class_level'] = $class['class_level'] ?? 'N/A';
    }
    
    echo json_encode(['success' => true, 'students' => $students]);
    
} catch (PDOException $e) {
    error_log("PDO Error in get_student_balances: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("Error in get_student_balances: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>