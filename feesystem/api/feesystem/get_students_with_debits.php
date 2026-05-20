<?php
// /feesystem/api/feesystem/get_students_with_debits.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../../includes/config.php';

$database = Database::getInstance();
$db = $database->getConnection();

$data = json_decode(file_get_contents('php://input'), true);
$school_id = $data['school_id'] ?? $_SESSION['school_id'];
$class_id = $data['class_id'] ?? 0;
$stream_id = $data['stream_id'] ?? null;
$term = $data['term'] ?? 1;
$year = $data['year'] ?? date('Y');

if ($class_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Class ID is required', 'debits' => []]);
    exit;
}

try {
    // Build query to get students with their debit amounts
    $sql = "
        SELECT 
            s.id,
            s.AdmNo as admission_no,
            CONCAT(s.FirstName, ' ', s.SecondName, ' ', COALESCE(s.LastName, '')) as full_name,
            s.Gender as gender,
            COALESCE(SUM(ft.amount), 0) as amount,
            ? as term
        FROM tblstudents s
        LEFT JOIN fee_transactions ft ON ft.student_id = s.id 
            AND ft.term = ? 
            AND ft.academic_year = ?
            AND ft.school_id = ?
            AND ft.transaction_type = 'debit'
        WHERE s.school_id = ? 
            AND s.class_id = ?
            AND s.Status = 'Active'
    ";
    
    $params = [$term, $term, $year, $school_id, $school_id, $class_id];
    
    if ($stream_id) {
        $sql .= " AND s.StreamId = ?";
        $params[] = $stream_id;
    }
    
    $sql .= " GROUP BY s.id ORDER BY s.FirstName ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $debits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'debits' => $debits]);
    
} catch (PDOException $e) {
    error_log("Get students with debits PDO error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage(), 'debits' => []]);
} catch (Exception $e) {
    error_log("Get students with debits error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage(), 'debits' => []]);
}
?>