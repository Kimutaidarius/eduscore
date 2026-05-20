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
$updates = $input['updates'] ?? [];

try {
    $db->beginTransaction();
    
    // First, get the fee structure IDs to update
    foreach ($updates as $update) {
        // Update each fee structure
        $stmt = $db->prepare("UPDATE fee_structures SET amount = ? WHERE id = ? AND school_id = ?");
        $stmt->execute([$update['amount'], $update['id'], $school_id]);
    }
    
    $db->commit();
    echo json_encode(['success' => true, 'message' => 'Fee structures updated successfully']);
} catch (PDOException $e) {
    $db->rollBack();
    error_log("Update fee structures error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>