<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['academic_level'])) {
    echo json_encode(['success' => false, 'message' => 'No academic level set']);
    exit();
}

echo json_encode([
    'success' => true,
    'academic_level' => $_SESSION['academic_level']
]);
?>