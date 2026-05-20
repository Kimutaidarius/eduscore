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
$group_id = $data['id'] ?? 0;
$school_id = $_SESSION['school_id'];

try {
    global $db;
    
    if (!isset($db)) {
        throw new Exception('Database connection not established');
    }
    
    $db->beginTransaction();
    
    // Delete vote head associations
    $stmt = $db->prepare("DELETE FROM fee_group_vote_heads WHERE group_id = :group_id");
    $stmt->execute([':group_id' => $group_id]);
    
    // Delete group
    $stmt2 = $db->prepare("DELETE FROM fee_groups WHERE id = :id AND school_id = :school_id");
    $stmt2->execute([
        ':id' => $group_id,
        ':school_id' => $school_id
    ]);
    
    $db->commit();
    echo json_encode(['success' => true, 'message' => 'Fee group deleted successfully']);
} catch (PDOException $e) {
    $db->rollBack();
    error_log("PDO Error in delete_fee_group: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    $db->rollBack();
    error_log("Error in delete_fee_group: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>