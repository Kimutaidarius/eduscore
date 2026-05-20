<?php
header('Content-Type: application/json');
require_once('../../includes/config.php');

$data = json_decode(file_get_contents('php://input'), true);
$school_id = $data['school_id'] ?? 0;

if (!$school_id) {
    echo json_encode(['success' => false, 'message' => 'School ID required']);
    exit;
}

try {
    // Using your existing departments table
    $stmt = $db->prepare("SELECT id, name FROM departments WHERE school_id = ? ORDER BY name");
    $stmt->execute([$school_id]);
    $departments = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'departments' => $departments]);
} catch (PDOException $e) {
    error_log("Error in get_departments: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred', 'departments' => []]);
}
?>