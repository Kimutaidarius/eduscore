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
$category = $input['category'] ?? 'all';
$data = $input['data'] ?? [];

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
    
    $db->beginTransaction();
    
    foreach ($data as $item) {
        $vote_head_id = $item['id'];
        $term1 = $item['term1'] ?? 0;
        $term2 = $item['term2'] ?? 0;
        $term3 = $item['term3'] ?? 0;
        
        // Check if record exists
        $stmt = $db->prepare("SELECT id FROM fee_structures WHERE school_id = ? AND class_level = ? AND academic_year = ? AND vote_head_id = ?");
        $stmt->execute([$school_id, $class_level, $year, $vote_head_id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            // Update existing
            $stmt = $db->prepare("UPDATE fee_structures SET term1 = ?, term2 = ?, term3 = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$term1, $term2, $term3, $existing['id']]);
        } else {
            // Insert new
            $stmt = $db->prepare("INSERT INTO fee_structures (school_id, academic_year, class_level, vote_head_id, term1, term2, term3, status, created_at) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW())");
            $stmt->execute([$school_id, $year, $class_level, $vote_head_id, $term1, $term2, $term3]);
        }
    }
    
    $db->commit();
    echo json_encode(['success' => true, 'message' => 'Fee structure saved successfully']);
} catch (PDOException $e) {
    $db->rollBack();
    error_log("Save fee structure error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>