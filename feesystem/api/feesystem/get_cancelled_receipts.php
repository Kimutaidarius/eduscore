<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once('../../includes/config.php');

$input = json_decode(file_get_contents('php://input'), true);
$school_id = $input['school_id'] ?? $_SESSION['school_id'];
$from_date = $input['from_date'] ?? date('Y-m-01');
$to_date = $input['to_date'] ?? date('Y-m-d');
$class_id = $input['class_id'] ?? null;
$stream_id = $input['stream_id'] ?? null;
$search = $input['search'] ?? '';

try {
    $database = Database::getInstance();
    $db = $database->getConnection();
    
    // Note: You may need a separate table for cancelled receipts
    // For now, we'll return empty array as placeholder
    // Create a `cancelled_receipts` table if needed
    
    echo json_encode([
        'success' => true,
        'receipts' => [],
        'total' => 0
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_cancelled_receipts: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch cancelled receipts: ' . $e->getMessage()
    ]);
}
?>