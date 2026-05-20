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

$school_id = $_SESSION['school_id'];

// Get POST data (JSON or form data)
$input = json_decode(file_get_contents('php://input'), true);
if ($input) {
    $status = $input['status'] ?? 'active';
    $search = $input['search'] ?? '';
    $type = $input['type'] ?? '';
    $status_filter = $input['status'] ?? '';
} else {
    $status = $_POST['status'] ?? $_GET['status'] ?? 'active';
    $search = $_POST['search'] ?? $_GET['search'] ?? '';
    $type = $_POST['type'] ?? $_GET['type'] ?? '';
    $status_filter = $_POST['status'] ?? $_GET['status'] ?? '';
}

try {
    global $db;
    
    if (!isset($db)) {
        throw new Exception('Database connection not established');
    }
    
    // Build query
    $query = "SELECT id, name, alias, type, priority, applies_to, status, description, created_at 
              FROM vote_heads 
              WHERE school_id = :school_id";
    $params = [':school_id' => $school_id];
    
    // Add status filter (for active only)
    if ($status === 'active' && empty($status_filter)) {
        $query .= " AND status = 'active'";
    }
    
    // Add status filter if provided
    if (!empty($status_filter)) {
        $query .= " AND status = :status";
        $params[':status'] = $status_filter;
    }
    
    // Add type filter
    if (!empty($type)) {
        $query .= " AND type = :type";
        $params[':type'] = $type;
    }
    
    // Add search filter
    if (!empty($search)) {
        $query .= " AND (name LIKE :search OR alias LIKE :search)";
        $params[':search'] = "%$search%";
    }
    
    $query .= " ORDER BY priority ASC, name ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    
    $vote_heads = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'vote_heads' => $vote_heads]);
    
} catch (PDOException $e) {
    error_log("PDO Error in get_vote_heads: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage(), 'vote_heads' => []]);
} catch (Exception $e) {
    error_log("Error in get_vote_heads: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage(), 'vote_heads' => []]);
}
?>