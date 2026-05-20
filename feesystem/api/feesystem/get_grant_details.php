<?php
// /feesystem/api/feesystem/get_grant_details.php
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
$grant_id = $data['grant_id'] ?? 0;

try {
    // Get grant details - using tblschoolinfo instead of schools
    $query = "SELECT g.*, s.school_name, s.school_address
              FROM grants g
              LEFT JOIN tblschoolinfo s ON g.school_id = s.id
              WHERE g.id = :grant_id";
    
    $stmt = $db->prepare($query);
    $stmt->execute([':grant_id' => $grant_id]);
    $grant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$grant) {
        echo json_encode(['success' => false, 'message' => 'Grant not found']);
        exit;
    }
    
    // Get distributions
    $distQuery = "SELECT gd.*, vh.name as vote_head_name, vh.alias as vote_head_alias
                  FROM grant_distributions gd
                  LEFT JOIN vote_heads vh ON gd.vote_head_id = vh.id
                  WHERE gd.grant_id = :grant_id";
    
    $distStmt = $db->prepare($distQuery);
    $distStmt->execute([':grant_id' => $grant_id]);
    $grant['distributions'] = $distStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'grant' => $grant]);
    
} catch (PDOException $e) {
    error_log("Get grant details error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>