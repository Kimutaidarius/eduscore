<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

session_start();
header('Content-Type: application/json');

// Check if config file exists
if (!file_exists('../../includes/config.php')) {
    echo json_encode(['success' => false, 'message' => 'Config file not found']);
    exit;
}

require_once('../../includes/config.php');

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$school_id = $_SESSION['school_id'];

try {
    // Use PDO connection from config
    global $db;
    
    if (!isset($db)) {
        throw new Exception('Database connection not established');
    }
    
    // Check if tables exist using PDO (without named placeholders for SHOW TABLES)
    $tables_needed = ['fee_groups', 'fee_group_vote_heads', 'vote_heads'];
    $missing_tables = [];
    
    foreach ($tables_needed as $table) {
        // Use direct query instead of prepared statement for SHOW TABLES
        $result = $db->query("SHOW TABLES LIKE '$table'");
        if ($result->rowCount() == 0) {
            $missing_tables[] = $table;
        }
    }
    
    if (!empty($missing_tables)) {
        echo json_encode([ 
            'success' => true, 
            'groups' => [], 
            'message' => 'Missing tables: ' . implode(', ', $missing_tables) . '. Please run the SQL installation script.',
            'missing_tables' => $missing_tables
        ]);
        exit;
    }
    
// In the query, add default_amount to SELECT
$query = "SELECT fg.*, 
          GROUP_CONCAT(DISTINCT vh.id) as vote_head_ids,
          GROUP_CONCAT(DISTINCT vh.name) as vote_head_names
          FROM fee_groups fg
          LEFT JOIN fee_group_vote_heads fgvh ON fg.id = fgvh.group_id
          LEFT JOIN vote_heads vh ON fgvh.vote_head_id = vh.id AND vh.school_id = :school_id1
          WHERE fg.school_id = :school_id2
          GROUP BY fg.id
          ORDER BY fg.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        ':school_id1' => $school_id,
        ':school_id2' => $school_id
    ]);
    
    $groups = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $groups[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'description' => $row['description'],
            'vote_head_ids' => $row['vote_head_ids'] ? explode(',', $row['vote_head_ids']) : [],
            'vote_heads' => $row['vote_head_names'] ? array_map(function($item) {
                return ['name' => trim($item)];
            }, explode(',', $row['vote_head_names'])) : []
        ];
    }
    
    echo json_encode(['success' => true, 'groups' => $groups]);
    
} catch (PDOException $e) {
    error_log("PDO Error in get_fee_groups.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage(), 'groups' => []]);
} catch (Exception $e) {
    error_log("Error in get_fee_groups.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage(), 'groups' => []]);
}
?>