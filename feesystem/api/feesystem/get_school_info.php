<?php
session_start();
require_once('../../includes/config.php');
header('Content-Type: application/json');

if (empty($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$school_id = $input['school_id'] ?? $_SESSION['school_id'];

try {
    $stmt = $db->prepare("SELECT school_name, school_address, school_phone, school_email, school_logo as logo_path FROM tblschoolinfo WHERE id = ?");
    $stmt->execute([$school_id]);
    $school_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'school_info' => $school_info ?: []]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>