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
$vote_head_id = $input['vote_head_id'] ?? '';
$year = $input['year'] ?? '';
$value = $input['value'] ?? 0;

if (!$class_id || !$vote_head_id || !$year) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

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
    $budget_year = '';
    
    if ($year == 'year1') $budget_year = $currentYear;
    elseif ($year == 'year2') $budget_year = $currentYear + 1;
    elseif ($year == 'year3') $budget_year = $currentYear + 2;
    
    // Check if budget record exists
    $stmt = $db->prepare("SELECT id FROM budget WHERE school_id = ? AND class_level = ? AND vote_head_id = ? AND budget_year = ?");
    $stmt->execute([$school_id, $class_level, $vote_head_id, $budget_year]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        $stmt = $db->prepare("UPDATE budget SET amount = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$value, $existing['id']]);
    } else {
        $stmt = $db->prepare("INSERT INTO budget (school_id, class_level, vote_head_id, budget_year, amount, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$school_id, $class_level, $vote_head_id, $budget_year, $value]);
    }
    
    echo json_encode(['success' => true, 'message' => 'Budget saved successfully']);
} catch (PDOException $e) {
    error_log("Save budget error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>