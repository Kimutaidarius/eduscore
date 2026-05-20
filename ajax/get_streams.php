<?php
// ajax/get_streams.php
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

if (!isset($_POST['class_id'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Class ID is required'
    ]);
    exit;
}

$class_id = (int) $_POST['class_id'];

header('Content-Type: application/json');

try {
    // Check if database connection exists
    if (!isset($db) && !isset($dbh)) {
        throw new Exception('Database connection not available');
    }
    
    $connection = isset($db) ? $db : $dbh;
    
    $stmt = $connection->prepare("
        SELECT id, stream_name
        FROM tblstreams
        WHERE class_id = :class_id 
        AND school_id = :school_id
        ORDER BY stream_name
    ");
    $stmt->execute([
        ':class_id' => $class_id,
        ':school_id' => $school_id
    ]);
    $streams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'streams' => $streams
    ]);
    
} catch (PDOException $e) {
    error_log("Get streams error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Get streams error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>