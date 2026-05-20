<?php
require_once '../../includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['school_id'])) {
    sendResponse(null, 'error', 'Unauthorized access');
}

$input = json_decode(file_get_contents('php://input'), true);

if (empty($input['id'])) {
    sendResponse(null, 'error', 'Supplier ID is required');
}

try {
    $sql = "UPDATE suppliers SET deleted_at = NOW() WHERE id = ? AND school_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$input['id'], $_SESSION['school_id']]);
    
    sendResponse(null, 'success', 'Supplier deleted successfully');
    
} catch (Exception $e) {
    error_log("Error deleting supplier: " . $e->getMessage());
    sendResponse(null, 'error', 'Failed to delete supplier');
}
?>