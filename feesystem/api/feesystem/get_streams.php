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
$class_id = $data['class_id'] ?? 0;

try {
    global $db;
    
    if (!isset($db)) {
        throw new Exception('Database connection not established');
    }
    
    $query = "SELECT id, stream_name FROM tblstreams WHERE school_id = :school_id AND class_id = :class_id ORDER BY stream_name";
    
    $stmt = $db->prepare($query);
    $stmt->execute([':school_id' => $school_id, ':class_id' => $class_id]);
    
    $streams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'streams' => $streams]);
    
} catch (PDOException $e) {
    error_log("PDO Error in get_streams: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage(), 'streams' => []]);
} catch (Exception $e) {
    error_log("Error in get_streams: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage(), 'streams' => []]);
}
?>