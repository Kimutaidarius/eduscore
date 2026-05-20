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
$year = $input['year'] ?? date('Y');
$vote_head_id = $input['vote_head_id'] ?? '';
$field = $input['field'] ?? '';
$value = $input['value'] ?? 0;

if (!$class_id || !$vote_head_id || !$field) {
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
    
    // Check if record exists
    $stmt = $db->prepare("SELECT id FROM fee_structures WHERE school_id = ? AND class_level = ? AND academic_year = ? AND vote_head_id = ?");
    $stmt->execute([$school_id, $class_level, $year, $vote_head_id]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        // Update existing
        $stmt = $db->prepare("UPDATE fee_structures SET $field = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$value, $existing['id']]);
    } else {
        // Insert new with default values for other fields
        $term1 = ($field == 'term1') ? $value : 0;
        $term2 = ($field == 'term2') ? $value : 0;
        $term3 = ($field == 'term3') ? $value : 0;
        
        $stmt = $db->prepare("INSERT INTO fee_structures (school_id, academic_year, class_level, vote_head_id, term1, term2, term3, status, created_at) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW())");
        $stmt->execute([$school_id, $year, $class_level, $vote_head_id, $term1, $term2, $term3]);
    }
    
    echo json_encode(['success' => true, 'message' => 'Saved successfully']);
} catch (PDOException $e) {
    error_log("Save fee structure field error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>