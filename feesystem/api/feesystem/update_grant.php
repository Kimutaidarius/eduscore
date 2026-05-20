<?php
// /feesystem/api/feesystem/update_grant.php
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
$name = trim($data['name'] ?? '');
$source = $data['source'] ?? '';
$total_amount = floatval($data['total_amount'] ?? 0);
$description = trim($data['description'] ?? '');

if ($grant_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid grant ID']);
    exit;
}

if (empty($name)) {
    echo json_encode(['success' => false, 'message' => 'Grant name is required']);
    exit;
}

if ($total_amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Valid total amount is required']);
    exit;
}

try {
    $db->beginTransaction();
    
    // Get current allocated amount
    $currentQuery = "SELECT allocated_amount FROM grants WHERE id = :grant_id AND school_id = :school_id";
    $currentStmt = $db->prepare($currentQuery);
    $currentStmt->execute([':grant_id' => $grant_id, ':school_id' => $school_id]);
    $current = $currentStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$current) {
        echo json_encode(['success' => false, 'message' => 'Grant not found']);
        exit;
    }
    
    $allocated_amount = $current['allocated_amount'];
    $remaining_balance = $total_amount - $allocated_amount;
    $status = $remaining_balance > 0 ? 'active' : 'exhausted';
    
    $query = "UPDATE grants 
              SET name = :name, 
                  source = :source, 
                  total_amount = :total_amount,
                  remaining_balance = :remaining_balance,
                  status = :status,
                  description = :description
              WHERE id = :grant_id AND school_id = :school_id";
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        ':name' => $name,
        ':source' => $source,
        ':total_amount' => $total_amount,
        ':remaining_balance' => $remaining_balance,
        ':status' => $status,
        ':description' => $description,
        ':grant_id' => $grant_id,
        ':school_id' => $school_id
    ]);
    
    $db->commit();
    
    echo json_encode(['success' => true, 'message' => 'Grant updated successfully']);
    
} catch (PDOException $e) {
    $db->rollBack();
    error_log("Update grant error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>