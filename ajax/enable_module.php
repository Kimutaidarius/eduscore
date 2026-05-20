<?php
session_start();
require_once '../includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['teacher_id']) || !isset($_POST['school_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$school_id = intval($_POST['school_id']);
$module_key = $_POST['module_key'] ?? '';

if (!$module_key) {
    echo json_encode(['success' => false, 'message' => 'Module key required']);
    exit();
}

global $dbh;

try {
    // Get module ID
    $stmt = $dbh->prepare("SELECT id FROM system_modules WHERE module_key = ?");
    $stmt->execute([$module_key]);
    $module = $stmt->fetch();
    
    if (!$module) {
        echo json_encode(['success' => false, 'message' => 'Module not found']);
        exit();
    }
    
    $module_id = $module['id'];
    
    // Check if already exists
    $check = $dbh->prepare("SELECT id FROM school_modules WHERE school_id = ? AND module_id = ?");
    $check->execute([$school_id, $module_id]);
    
    if ($check->fetch()) {
        // Update existing
        $update = $dbh->prepare("
            UPDATE school_modules 
            SET is_enabled = 1, enabled_at = NOW() 
            WHERE school_id = ? AND module_id = ?
        ");
        $update->execute([$school_id, $module_id]);
    } else {
        // Insert new
        $insert = $dbh->prepare("
            INSERT INTO school_modules (school_id, module_id, is_enabled, enabled_at) 
            VALUES (?, ?, 1, NOW())
        ");
        $insert->execute([$school_id, $module_id]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Module enabled successfully'
    ]);
    
} catch (PDOException $e) {
    error_log("Error enabling module: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}