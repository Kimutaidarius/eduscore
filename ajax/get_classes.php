<?php
// ajax/get_classes.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// CORRECTED PATH - use includes/config.php
require_once __DIR__ . '/../includes/config.php';

// Check authentication
if (
    empty($_SESSION['authenticated']) ||
    empty($_SESSION['school_id']) ||
    empty($_SESSION['teacher_id'])
) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized session'
    ]);
    exit;
}

$school_id = (int) $_SESSION['school_id'];

header('Content-Type: application/json');

try {
    // Check if db connection exists
    if (!isset($db) && !isset($dbh)) {
        throw new Exception('Database connection not available');
    }
    
    $connection = isset($db) ? $db : $dbh;
    
    // Using academic_level and class_level columns from tblclasses table
    $stmt = $connection->prepare("
        SELECT id, class_level, academic_level
        FROM tblclasses
        WHERE school_id = :school_id
        ORDER BY 
            CASE academic_level
                WHEN 'pre-primary' THEN 1
                WHEN 'primary' THEN 2
                WHEN 'junior_secondary' THEN 3
                WHEN 'secondary' THEN 4
                WHEN 'college' THEN 5
                WHEN 'university' THEN 6
                WHEN 'mixed' THEN 7
                ELSE 8
            END,
            class_level
    ");
    $stmt->execute([':school_id' => $school_id]);
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'classes' => $classes
    ]);
    
} catch (PDOException $e) {
    error_log("Get classes error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Get classes error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>