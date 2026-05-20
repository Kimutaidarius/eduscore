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

try {
    $sql = "SELECT fs.*, vh.name as vote_head_name, vh.alias 
            FROM fee_structures fs 
            JOIN vote_heads vh ON fs.vote_head_id = vh.id 
            WHERE fs.school_id = ? AND fs.academic_year = ?";
    $params = [$school_id, $year];
    
    if (!empty($class_id)) {
        // Get class_level from class ID
        $stmt = $db->prepare("SELECT class_level FROM tblclasses WHERE id = ? AND school_id = ?");
        $stmt->execute([$class_id, $school_id]);
        $class = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($class) {
            $sql .= " AND fs.class_level = ?";
            $params[] = $class['class_level'];
        }
    }
    
    $sql .= " ORDER BY vh.priority ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $feeStructures = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'fee_structures' => $feeStructures]);
} catch (PDOException $e) {
    error_log("Get fee structures error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>