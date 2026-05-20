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
$source_class_id = $input['source_class_id'] ?? '';
$target_class_id = $input['target_class_id'] ?? '';
$year = $input['year'] ?? date('Y');

// Get the class levels for the IDs
try {
    // Get source class level
    $stmt = $db->prepare("SELECT class_level FROM tblclasses WHERE id = ? AND school_id = ?");
    $stmt->execute([$source_class_id, $school_id]);
    $source_class = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get target class level
    $stmt = $db->prepare("SELECT class_level FROM tblclasses WHERE id = ? AND school_id = ?");
    $stmt->execute([$target_class_id, $school_id]);
    $target_class = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$source_class || !$target_class) {
        echo json_encode(['success' => false, 'message' => 'Class not found']);
        exit;
    }
    
    $source_class_level = $source_class['class_level'];
    $target_class_level = $target_class['class_level'];
    
    // Get source fee structures
    $stmt = $db->prepare("SELECT * FROM fee_structures WHERE school_id = ? AND class_level = ? AND academic_year = ?");
    $stmt->execute([$school_id, $source_class_level, $year]);
    $source_fees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($source_fees)) {
        echo json_encode(['success' => false, 'message' => 'No fee structures found to clone']);
        exit;
    }
    
    // Delete existing for target
    $stmt = $db->prepare("DELETE FROM fee_structures WHERE school_id = ? AND class_level = ? AND academic_year = ?");
    $stmt->execute([$school_id, $target_class_level, $year]);
    
    // Insert cloned records
    $stmt = $db->prepare("INSERT INTO fee_structures (school_id, academic_year, term, class_level, stream_id, vote_head_id, amount, is_optional, status, created_at) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    
    foreach ($source_fees as $fee) {
        $stmt->execute([
            $school_id, 
            $year, 
            $fee['term'], 
            $target_class_level, 
            null, 
            $fee['vote_head_id'], 
            $fee['amount'], 
            $fee['is_optional'], 
            $fee['status']
        ]);
    }
    
    echo json_encode(['success' => true, 'message' => 'Fee structure cloned successfully']);
} catch (PDOException $e) {
    error_log("Clone fee structure error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>