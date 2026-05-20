<?php
session_start();
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once('../../includes/config.php');

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get the raw input for debugging
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Check both POST/GET and JSON input
$group_id = 0;

if ($data && isset($data['id'])) {
    $group_id = intval($data['id']);
} elseif (isset($_POST['id'])) {
    $group_id = intval($_POST['id']);
} elseif (isset($_GET['id'])) {
    $group_id = intval($_GET['id']);
}

$school_id = $_SESSION['school_id'];

// Debug log
error_log("get_fee_group.php - Received ID: " . $group_id . ", School ID: " . $school_id);
error_log("get_fee_group.php - Raw input: " . $input);

try {
    global $db;
    
    if (!isset($db)) {
        throw new Exception('Database connection not established');
    }
    
    // First, check if the group exists at all
    $check_stmt = $db->prepare("SELECT COUNT(*) as count FROM fee_groups WHERE id = :id");
    $check_stmt->execute([':id' => $group_id]);
    $exists = $check_stmt->fetch(PDO::FETCH_ASSOC);
    error_log("get_fee_group.php - Group exists in DB: " . $exists['count']);
    
    // Get group details
    $stmt = $db->prepare("SELECT * FROM fee_groups WHERE id = :id AND school_id = :school_id");
    $stmt->execute([':id' => $group_id, ':school_id' => $school_id]);
    $group = $stmt->fetch(PDO::FETCH_ASSOC);
    
    error_log("get_fee_group.php - Query result: " . ($group ? "Found" : "Not found"));
    
    if ($group) {
        // Get vote head IDs for this group
        $stmt2 = $db->prepare("SELECT vote_head_id FROM fee_group_vote_heads WHERE group_id = :group_id");
        $stmt2->execute([':group_id' => $group_id]);
        
        $vote_head_ids = [];
        while ($row = $stmt2->fetch(PDO::FETCH_ASSOC)) {
            $vote_head_ids[] = $row['vote_head_id'];
        }
        
        $group['vote_head_ids'] = $vote_head_ids;
        echo json_encode(['success' => true, 'group' => $group]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Group not found', 'debug' => ['group_id' => $group_id, 'school_id' => $school_id]]);
    }
} catch (PDOException $e) {
    error_log("PDO Error in get_fee_group: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("Error in get_fee_group: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>