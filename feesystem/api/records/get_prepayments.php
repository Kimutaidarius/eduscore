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
$year = $input['year'] ?? date('Y');
$vote_head_id = $input['vote_head_id'] ?? null;
$search = $input['search'] ?? '';

try {
    $database = Database::getInstance();
    $db = $database->getConnection();
    
    $query = "SELECT 
                ft.id,
                ft.amount,
                ft.created_at as payment_date,
                ft.receipt_no,
                ft.payment_mode,
                ft.description,
                s.AdmNo as admission_no,
                CONCAT(s.FirstName, ' ', s.SecondName, ' ', COALESCE(s.LastName, '')) as student_name,
                c.class_level as class_name,
                vh.name as vote_head_name,
                CASE 
                    WHEN ft.transaction_type = 'payment' AND ft.academic_year > :year THEN 'Yes'
                    ELSE 'No'
                END as distributed
              FROM fee_transactions ft
              LEFT JOIN tblstudents s ON ft.student_id = s.id
              LEFT JOIN tblclasses c ON s.class_id = c.id
              LEFT JOIN vote_heads vh ON ft.vote_head_id = vh.id
              WHERE ft.school_id = :school_id 
              AND ft.transaction_type = 'payment'
              AND ft.academic_year > :year";
    
    $params = [
        ':school_id' => $school_id,
        ':year' => $year
    ];
    
    if ($class_id) {
        $query .= " AND s.class_id = :class_id";
        $params[':class_id'] = $class_id;
    }
    
    if ($stream_id) {
        $query .= " AND s.StreamId = :stream_id";
        $params[':stream_id'] = $stream_id;
    }
    
    if ($vote_head_id) {
        $query .= " AND ft.vote_head_id = :vote_head_id";
        $params[':vote_head_id'] = $vote_head_id;
    }
    
    if ($search) {
        $query .= " AND (s.AdmNo LIKE :search OR CONCAT(s.FirstName, ' ', s.SecondName, ' ', COALESCE(s.LastName, '')) LIKE :search OR vh.name LIKE :search)";
        $params[':search'] = "%$search%";
    }
    
    $query .= " ORDER BY ft.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'records' => $records,
        'total' => count($records)
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_prepayments: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch pre-payments: ' . $e->getMessage()
    ]);
}
?>