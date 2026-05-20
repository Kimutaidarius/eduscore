<?php
session_start();
require_once('../../includes/config.php');
header('Content-Type: application/json');

if (empty($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$school_id = $input['school_id'] ?? $_SESSION['school_id'];
$class_id = $input['class_id'] ?? '';

try {
    // Get class_level from class ID
    $stmt = $db->prepare("SELECT class_level FROM tblclasses WHERE id = ? AND school_id = ?");
    $stmt->execute([$class_id, $school_id]);
    $class = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$class) {
        echo json_encode(['success' => false, 'message' => 'Class not found']);
        exit;
    }
    
    $class_level = $class['class_level'];
    
    // Get student count for the class
    $stmt = $db->prepare("SELECT COUNT(*) FROM tblstudents WHERE school_id = ? AND class_id = ?");
    $stmt->execute([$school_id, $class_id]);
    $student_count = $stmt->fetchColumn();
    
    // Get fee structures for budget calculation
    $stmt = $db->prepare("
        SELECT vh.id, vh.name as vote_head_name, vh.alias,
               SUM(fs.amount) as total_fee
        FROM fee_structures fs
        JOIN vote_heads vh ON fs.vote_head_id = vh.id
        WHERE fs.school_id = ? AND fs.class_level = ? AND fs.status = 'active'
        GROUP BY vh.id
        ORDER BY vh.priority ASC
    ");
    $stmt->execute([$school_id, $class_level]);
    $budget_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate budgets for 3 years with 5% annual increase
    $current_year = date('Y');
    foreach ($budget_data as &$item) {
        $base = $item['total_fee'] * $student_count;
        $item['budget_year1'] = round($base, 2);
        $item['budget_year2'] = round($base * 1.05, 2);
        $item['budget_year3'] = round($base * 1.10, 2);
        unset($item['total_fee']);
    }
    
    echo json_encode(['success' => true, 'budget_data' => $budget_data, 'student_count' => $student_count]);
} catch (PDOException $e) {
    error_log("Calculate budget error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>