<?php
// /feesystem/api/stores/delete_lpo.php
header('Content-Type: application/json');
require_once('../../includes/config.php');

$data = json_decode(file_get_contents('php://input'), true);
$school_id = $data['school_id'] ?? 0;
$lpo_id = $data['lpo_id'] ?? 0;

if (!$school_id || !$lpo_id) {
    echo json_encode(['success' => false, 'message' => 'School ID and LPO ID required']);
    exit;
}

try {
    // Start transaction
    $db->beginTransaction();
    
    // Check if LPO exists and belongs to the school
    $stmt = $db->prepare("SELECT id, status FROM lpos WHERE id = ? AND school_id = ?");
    $stmt->execute([$lpo_id, $school_id]);
    $lpo = $stmt->fetch();
    
    if (!$lpo) {
        echo json_encode(['success' => false, 'message' => 'LPO not found']);
        exit;
    }
    
    // Check if LPO has any GRNs (received goods)
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM grns WHERE lpo_id = ?");
    $stmt->execute([$lpo_id]);
    $grnCount = $stmt->fetch()['count'];
    
    if ($grnCount > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete LPO with existing GRNs. Please delete the GRNs first.']);
        exit;
    }
    
    // Delete LPO items first (foreign key constraint)
    $stmt = $db->prepare("DELETE FROM lpo_items WHERE lpo_id = ?");
    $stmt->execute([$lpo_id]);
    
    // Delete the LPO
    $stmt = $db->prepare("DELETE FROM lpos WHERE id = ? AND school_id = ?");
    $stmt->execute([$lpo_id, $school_id]);
    
    // Commit transaction
    $db->commit();
    
    echo json_encode(['success' => true, 'message' => 'LPO deleted successfully']);
    
} catch (PDOException $e) {
    $db->rollBack();
    error_log("Error in delete_lpo: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred: ' . $e->getMessage()]);
}
?>