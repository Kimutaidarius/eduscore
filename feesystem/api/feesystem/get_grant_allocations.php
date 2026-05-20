<?php
// /feesystem/api/feesystem/get_grant_allocations.php
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

try {
    $query = "SELECT ga.*, 
                     CONCAT(s.FirstName, ' ', COALESCE(s.SecondName, ''), ' ', COALESCE(s.LastName, '')) as student_name,
                     s.AdmNo as admission_no,
                     c.class_level as class_name,
                     st.stream_name as stream_name,
                     g.name as grant_name,
                     g.source as grant_source
              FROM grant_allocations ga
              JOIN tblstudents s ON ga.student_id = s.id
              LEFT JOIN tblclasses c ON s.class_id = c.id
              LEFT JOIN tblstreams st ON s.StreamId = st.id
              JOIN grants g ON ga.grant_id = g.id
              WHERE g.school_id = :school_id";
    
    $params = [':school_id' => $school_id];
    
    if ($grant_id > 0) {
        $query .= " AND ga.grant_id = :grant_id";
        $params[':grant_id'] = $grant_id;
    }
    
    if ($student_id > 0) {
        $query .= " AND ga.student_id = :student_id";
        $params[':student_id'] = $student_id;
    }
    
    $query .= " ORDER BY ga.allocated_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $allocations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'allocations' => $allocations]);
    
} catch (PDOException $e) {
    error_log("Get grant allocations error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>