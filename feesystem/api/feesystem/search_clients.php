<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../../includes/config.php';

$data = json_decode(file_get_contents('php://input'), true);
$school_id = $data['school_id'] ?? $_SESSION['school_id'];
$name = $data['name'] ?? '';
$id_number = $data['id_number'] ?? '';
$contact = $data['contact'] ?? '';

try {
    $sql = "SELECT * FROM clients WHERE school_id = ?";
    $params = [$school_id];
    
    if (!empty($name)) {
        $sql .= " AND name LIKE ?";
        $params[] = "%$name%";
    }
    
    if (!empty($id_number)) {
        $sql .= " AND id_number LIKE ?";
        $params[] = "%$id_number%";
    }
    
    if (!empty($contact)) {
        $sql .= " AND (contact LIKE ? OR phone LIKE ?)";
        $params[] = "%$contact%";
        $params[] = "%$contact%";
    }
    
    $sql .= " ORDER BY name";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'clients' => $clients]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>