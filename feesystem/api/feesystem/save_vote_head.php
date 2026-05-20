<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../includes/config.php';

if (empty($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$school_id = $input['school_id'] ?? $_SESSION['school_id'] ?? null;
$name = trim($input['name'] ?? '');
$alias = strtoupper(trim($input['alias'] ?? ''));
$type = $input['type'] ?? '';
$priority = $input['priority'] ?? 5;
$applies_to = $input['applies_to'] ?? 'all_students';
$status = $input['status'] ?? 'active';
$description = $input['description'] ?? '';

if (!$school_id) {
    echo json_encode(['success' => false, 'message' => 'School ID not found']);
    exit;
}

if (empty($name) || empty($alias) || empty($type)) {
    echo json_encode(['success' => false, 'message' => 'Name, alias, and type are required']);
    exit;
}

try {
    // Check if table exists
    $tableCheck = $db->query("SHOW TABLES LIKE 'vote_heads'");
    if ($tableCheck->rowCount() == 0) {
        echo json_encode(['success' => false, 'message' => 'Vote heads table not found. Please contact administrator.']);
        exit;
    }
    
    // Check for duplicate alias
    $stmt = $db->prepare("SELECT id FROM vote_heads WHERE alias = :alias AND school_id = :school_id");
    $stmt->execute([':alias' => $alias, ':school_id' => $school_id]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Alias already exists for this school']);
        exit;
    }
    
    $stmt = $db->prepare("INSERT INTO vote_heads (school_id, name, alias, type, priority, applies_to, status, description, created_at) 
                           VALUES (:school_id, :name, :alias, :type, :priority, :applies_to, :status, :description, NOW())");
    $result = $stmt->execute([
        ':school_id' => $school_id,
        ':name' => $name,
        ':alias' => $alias,
        ':type' => $type,
        ':priority' => $priority,
        ':applies_to' => $applies_to,
        ':status' => $status,
        ':description' => $description
    ]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Vote head saved successfully', 'id' => $db->lastInsertId()]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save vote head']);
    }
} catch (PDOException $e) {
    error_log("Database error in save_vote_head.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("General error in save_vote_head.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>