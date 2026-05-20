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
$name = trim($data['name'] ?? '');
$description = trim($data['description'] ?? '');
$default_amount = floatval($data['default_amount'] ?? 0);
$vote_head_ids = $data['vote_head_ids'] ?? [];
$school_id = $_SESSION['school_id'];

if (empty($name)) {
    echo json_encode(['success' => false, 'message' => 'Group name is required']);
    exit;
}

try {
    global $db;
    
    if (!isset($db)) {
        throw new Exception('Database connection not established');
    }
    
    $db->beginTransaction();
    
    // Update group
    $stmt = $db->prepare("UPDATE fee_groups SET name = :name, description = :description, default_amount = :default_amount WHERE id = :id AND school_id = :school_id");
    $stmt->execute([
        ':name' => $name,
        ':description' => $description,
        ':default_amount' => $default_amount,
        ':id' => $group_id,
        ':school_id' => $school_id
    ]);
    
    // Delete existing vote head associations
    $stmt2 = $db->prepare("DELETE FROM fee_group_vote_heads WHERE group_id = :group_id");
    $stmt2->execute([':group_id' => $group_id]);
    
    // Insert new associations
    if (!empty($vote_head_ids)) {
        $stmt3 = $db->prepare("INSERT INTO fee_group_vote_heads (group_id, vote_head_id) VALUES (:group_id, :vote_head_id)");
        foreach ($vote_head_ids as $vh_id) {
            $stmt3->execute([
                ':group_id' => $group_id,
                ':vote_head_id' => $vh_id
            ]);
        }
    }
    
    $db->commit();
    echo json_encode(['success' => true, 'message' => 'Fee group updated successfully']);
    
} catch (PDOException $e) {
    $db->rollBack();
    error_log("PDO Error in update_fee_group: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    $db->rollBack();
    error_log("Error in update_fee_group: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>