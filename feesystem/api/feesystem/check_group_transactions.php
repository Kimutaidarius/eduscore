<?php
session_start();
header('Content-Type: application/json');

require_once('../../includes/config.php');

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$group_id = $data['group_id'] ?? 0;
$school_id = $_SESSION['school_id'];

try {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM fee_transactions WHERE group_id = ?");
    $stmt->bind_param("i", $group_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'];
    
    echo json_encode([
        'success' => true, 
        'has_transactions' => $count > 0,
        'transaction_count' => $count
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>