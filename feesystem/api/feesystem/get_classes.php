<?php
session_start();
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once('../../includes/config.php');

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$school_id = $_SESSION['school_id'];
$academic_level = $data['academic_level'] ?? $_SESSION['academic_level'] ?? 'primary';

try {
    global $db;
    
    if (!isset($db)) {
        throw new Exception('Database connection not established');
    }
    
    // Map academic level to class levels
    $class_levels = [];
    if ($academic_level == 'primary') {
        $class_levels = ['PP1', 'PP2', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6'];
    } elseif ($academic_level == 'junior_secondary') {
        $class_levels = ['Grade 7', 'Grade 8', 'Grade 9'];
    } else {
        $class_levels = ['Form 1', 'Form 2', 'Form 3', 'Form 4'];
    }
    
    $placeholders = implode(',', array_fill(0, count($class_levels), '?'));
    $query = "SELECT id, class_level, academic_level, stream FROM tblclasses 
              WHERE school_id = ? AND class_level IN ($placeholders)
              ORDER BY FIELD(class_level, 'PP1', 'PP2', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6', 'Grade 7', 'Grade 8', 'Grade 9', 'Form 1', 'Form 2', 'Form 3', 'Form 4')";
    
    $stmt = $db->prepare($query);
    $params = array_merge([$school_id], $class_levels);
    $stmt->execute($params);
    
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'classes' => $classes]);
    
} catch (PDOException $e) {
    error_log("PDO Error in get_classes: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage(), 'classes' => []]);
} catch (Exception $e) {
    error_log("Error in get_classes: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage(), 'classes' => []]);
}
?>