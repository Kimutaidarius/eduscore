<?php
// /feesystem/api/feesystem/delete_grant.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once('../../includes/config.php');

$database = Database::getInstance();
$db = $database->getConnection();

$data = json_decode(file_get_contents('php://input'), true);
$school_id = $_SESSION['school_id'];
$grant_id = intval($data['grant_id'] ?? 0);

if ($grant_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid grant ID']);
    exit;
}

try {
    $db->beginTransaction();
    
    // Check if grant has allocations
    $checkQuery = "SELECT COUNT(*) as count FROM grant_allocations WHERE grant_id = :grant_id";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->execute([':grant_id' => $grant_id]);
    $count = $checkStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($count > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete grant with existing allocations. Consider archiving instead.']);
        exit;
    }
    
    $query = "DELETE FROM grants WHERE id = :grant_id AND school_id = :school_id";
    $stmt = $db->prepare($query);
    $stmt->execute([':grant_id' => $grant_id, ':school_id' => $school_id]);
    
    $db->commit();
    
    echo json_encode(['success' => true, 'message' => 'Grant deleted successfully']);
    
} catch (PDOException $e) {
    $db->rollBack();
    error_log("Delete grant error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>