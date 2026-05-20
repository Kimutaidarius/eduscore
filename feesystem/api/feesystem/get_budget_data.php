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
    // Get class level
    $stmt = $db->prepare("SELECT class_level FROM tblclasses WHERE id = ? AND school_id = ?");
    $stmt->execute([$class_id, $school_id]);
    $class = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$class) {
        echo json_encode(['success' => false, 'message' => 'Class not found']);
        exit;
    }
    
    $class_level = $class['class_level'];
    $currentYear = date('Y');
    
    // Get all active vote heads
    $stmt = $db->prepare("SELECT id, name, alias, priority FROM vote_heads WHERE school_id = ? AND status = 'active' ORDER BY priority ASC");
    $stmt->execute([$school_id]);
    $voteHeads = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get existing budget data
    $budget_data = [];
    foreach ($voteHeads as $vh) {
        $stmt = $db->prepare("SELECT budget_year, amount FROM budget WHERE school_id = ? AND class_level = ? AND vote_head_id = ?");
        $stmt->execute([$school_id, $class_level, $vh['id']]);
        $budgets = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $budget_data[] = [
            'id' => $vh['id'],
            'vote_head_name' => $vh['name'],
            'alias' => $vh['alias'],
            'budget_year1' => $budgets[$currentYear] ?? 0,
            'budget_year2' => $budgets[$currentYear + 1] ?? 0,
            'budget_year3' => $budgets[$currentYear + 2] ?? 0
        ];
    }
    
    echo json_encode(['success' => true, 'budget_data' => $budget_data]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>