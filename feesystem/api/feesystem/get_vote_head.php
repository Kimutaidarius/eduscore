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
$id = $input['id'] ?? 0;
$school_id = $input['school_id'] ?? $_SESSION['school_id'] ?? null;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Vote head ID required']);
    exit;
}

if (!$school_id) {
    echo json_encode(['success' => false, 'message' => 'School ID not found']);
    exit;
}

try {
    $stmt = $db->prepare("SELECT * FROM vote_heads WHERE id = :id AND school_id = :school_id");
    $stmt->execute([':id' => $id, ':school_id' => $school_id]);
    $voteHead = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($voteHead) {
        echo json_encode(['success' => true, 'vote_head' => $voteHead]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Vote head not found']);
    }
} catch (PDOException $e) {
    error_log("Database error in get_vote_head.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>