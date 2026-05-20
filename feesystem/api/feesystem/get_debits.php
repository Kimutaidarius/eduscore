<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../../includes/config.php';

$data = json_decode(file_get_contents('php://input'), true);
$school_id = $data['school_id'] ?? $_SESSION['school_id'];
$class_id = $data['class_id'] ?? 0;
$stream_id = $data['stream_id'] ?? null;
$term = $data['term'] ?? 1;
$year = $data['year'] ?? date('Y');

try {
    $sql = "
        SELECT 
            s.id as student_id,
            s.AdmNo as admission_no,
            CONCAT(s.FirstName, ' ', s.SecondName, ' ', COALESCE(s.LastName, '')) as full_name,
            s.Gender as gender,
            ft.amount,
            ft.description,
            ft.term,
            ft.academic_year as year,
            ft.created_at,
            vh.name as vote_head_name,
            vh.alias as vote_head_alias
        FROM fee_transactions ft
        JOIN tblstudents s ON ft.student_id = s.id
        LEFT JOIN vote_heads vh ON ft.vote_head_id = vh.id
        WHERE ft.school_id = ? 
        AND ft.transaction_type = 'debit'
        AND ft.term = ?
        AND ft.academic_year = ?
    ";
    
    $params = [$school_id, $term, $year];
    
    if ($class_id > 0) {
        $sql .= " AND s.class_id = ?";
        $params[] = $class_id;
    }
    
    if ($stream_id > 0) {
        $sql .= " AND s.StreamId = ?";
        $params[] = $stream_id;
    }
    
    $sql .= " ORDER BY s.FirstName, s.SecondName";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $debits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'debits' => $debits]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>