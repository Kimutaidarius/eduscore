<?php
session_start();
header('Content-Type: application/json');
require_once('../../includes/config.php');

if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    sendResponse(null, 'error', 'Unauthorized access');
}

$school_id = $_SESSION['school_id'] ?? 0;
$input = json_decode(file_get_contents('php://input'), true);

try {
    $sql = "
        SELECT 
            p.*,
            CONCAT(s.first_name, ' ', s.last_name) as staff_name,
            s.staff_number as staff_id,
            DATE_FORMAT(p.created_at, '%Y-%m-%d') as created_at_date,
            CASE p.month
                WHEN 1 THEN 'January'
                WHEN 2 THEN 'February'
                WHEN 3 THEN 'March'
                WHEN 4 THEN 'April'
                WHEN 5 THEN 'May'
                WHEN 6 THEN 'June'
                WHEN 7 THEN 'July'
                WHEN 8 THEN 'August'
                WHEN 9 THEN 'September'
                WHEN 10 THEN 'October'
                WHEN 11 THEN 'November'
                WHEN 12 THEN 'December'
            END as month_name
        FROM payroll_transactions p
        JOIN staff s ON p.staff_id = s.id
        WHERE p.school_id = ?
    ";
    $params = [$school_id];
    
    if (!empty($input['from_date'])) {
        $sql .= " AND DATE(p.created_at) >= ?";
        $params[] = $input['from_date'];
    }
    
    if (!empty($input['to_date'])) {
        $sql .= " AND DATE(p.created_at) <= ?";
        $params[] = $input['to_date'];
    }
    
    if (!empty($input['staff_id'])) {
        $sql .= " AND p.staff_id = ?";
        $params[] = $input['staff_id'];
    }
    
    $sql .= " ORDER BY p.created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    sendResponse(['records' => $records], 'success');
} catch (PDOException $e) {
    error_log("Error in get_payroll_history.php: " . $e->getMessage());
    sendResponse(null, 'error', 'Database error occurred');
}
?>