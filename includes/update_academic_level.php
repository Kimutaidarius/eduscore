<?php
// update_academic_level.php
session_start();
require_once 'config/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['academic_level'])) {
    $academic_level = $_POST['academic_level'];
    
    // Validate academic level - using lowercase values
    $valid_levels = ['primary', 'junior_secondary', 'senior_secondary', 'college'];
    if (!in_array($academic_level, $valid_levels)) {
        echo json_encode(['success' => false, 'message' => 'Invalid academic level']);
        exit;
    }
    
    // Save to session
    $_SESSION['academic_level'] = $academic_level;
    
    echo json_encode(['success' => true, 'message' => 'Academic level updated']);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>