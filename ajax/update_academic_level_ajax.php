<?php
session_start();
header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['school_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$academic_level = $_POST['academic_level'] ?? null;

if (!$academic_level) {
    echo json_encode(['success' => false, 'message' => 'No academic level provided']);
    exit();
}

// Validate academic level
$valid_levels = ['primary', 'junior_secondary', 'senior_secondary', 'college'];
if (!in_array($academic_level, $valid_levels)) {
    echo json_encode(['success' => false, 'message' => 'Invalid academic level']);
    exit();
}

// Update session
$_SESSION['academic_level'] = $academic_level;

$level_names = [
    'primary' => 'Primary School',
    'junior_secondary' => 'Junior Secondary',
    'senior_secondary' => 'Senior Secondary',
    'college' => 'College'
];

echo json_encode([
    'success' => true,
    'message' => 'Academic level updated successfully',
    'academic_level' => $academic_level,
    'display_name' => $level_names[$academic_level] ?? $academic_level
]);
?>