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
$from_date = $input['from_date'] ?? date('Y-m-01');
$to_date = $input['to_date'] ?? date('Y-m-d');
$class_id = $input['class_id'] ?? null;
$stream_id = $input['stream_id'] ?? null;
$year = $input['year'] ?? date('Y');
$search = $input['search'] ?? '';

try {
    $database = Database::getInstance();
    $db = $database->getConnection();
    
    $query = "SELECT 
                ft.id,
                ft.receipt_no,
                ft.amount,
                ft.payment_mode,
                ft.created_at as payment_date,
                ft.description as notes,
                s.AdmNo as admission_no,
                CONCAT(s.FirstName, ' ', s.SecondName, ' ', COALESCE(s.LastName, '')) as student_name,
                c.class_level as class_name,
                st.stream_name
              FROM fee_transactions ft
              LEFT JOIN tblstudents s ON ft.student_id = s.id
              LEFT JOIN tblclasses c ON s.class_id = c.id
              LEFT JOIN tblstreams st ON s.StreamId = st.id
              WHERE ft.school_id = :school_id 
              AND ft.transaction_type = 'payment'
              AND DATE(ft.created_at) BETWEEN :from_date AND :to_date";
    
    $params = [
        ':school_id' => $school_id,
        ':from_date' => $from_date,
        ':to_date' => $to_date
    ];
    
    if ($class_id) {
        $query .= " AND s.class_id = :class_id";
        $params[':class_id'] = $class_id;
    }
    
    if ($stream_id) {
        $query .= " AND s.StreamId = :stream_id";
        $params[':stream_id'] = $stream_id;
    }
    
    if ($search) {
        $query .= " AND (s.AdmNo LIKE :search OR CONCAT(s.FirstName, ' ', s.SecondName, ' ', COALESCE(s.LastName, '')) LIKE :search)";
        $params[':search'] = "%$search%";
    }
    
    $query .= " ORDER BY ft.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $receipts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'receipts' => $receipts,
        'total' => count($receipts)
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_receipts: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch receipts: ' . $e->getMessage()
    ]);
}
?>