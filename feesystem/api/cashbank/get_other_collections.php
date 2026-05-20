<?php
header('Content-Type: application/json');
require_once('../../includes/config.php');

$data = json_decode(file_get_contents('php://input'), true);
$school_id = $data['school_id'] ?? 0;
$from_date = $data['from_date'] ?? date('Y-m-01');
$to_date = $data['to_date'] ?? date('Y-m-t');

if (!$school_id) {
    echo json_encode(['success' => false, 'message' => 'School ID required']);
    exit;
}

try {
    $query = "SELECT ft.*, s.FirstName, s.SecondName, s.LastName, s.AdmNo
              FROM fee_transactions ft
              LEFT JOIN tblstudents s ON ft.student_id = s.id
              WHERE ft.school_id = ? 
              AND ft.transaction_type = 'payment'
              AND ft.payment_mode IN ('mpesa', 'bank', 'cheque')
              AND DATE(ft.created_at) BETWEEN ? AND ?
              ORDER BY ft.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$school_id, $from_date, $to_date]);
    $results = $stmt->fetchAll();
    
    $collections = [];
    foreach ($results as $row) {
        $student_name = trim(($row['FirstName'] ?? '') . ' ' . ($row['SecondName'] ?? '') . ' ' . ($row['LastName'] ?? ''));
        $collections[] = [
            'payment_date' => date('Y-m-d', strtotime($row['created_at'])),
            'receipt_no' => $row['receipt_no'] ?? '',
            'student_name' => $student_name,
            'adm_no' => $row['AdmNo'] ?? '',
            'amount' => $row['amount'],
            'payment_mode' => $row['payment_mode'] ?? '',
            'deposited' => 1
        ];
    }
    
    echo json_encode(['success' => true, 'collections' => $collections]);
} catch (PDOException $e) {
    error_log("Error in get_other_collections: " . $e->getMessage());
    echo json_encode(['success' => true, 'collections' => []]);
}
?>