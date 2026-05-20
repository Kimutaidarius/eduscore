<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => '',
    'session_data' => []
];

try {
    // Check if user is logged in
    if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['school_id'])) {
        $response['message'] = 'Not authenticated';
        $response['session_data'] = [
            'has_teacher_id' => isset($_SESSION['teacher_id']),
            'has_school_id' => isset($_SESSION['school_id']),
            'session_keys' => array_keys($_SESSION)
        ];
        http_response_code(401);
    } else {
        $response['success'] = true;
        $response['message'] = 'Authenticated';
        $response['session_data'] = [
            'teacher_id' => $_SESSION['teacher_id'],
            'school_id' => $_SESSION['school_id'],
            'academic_level' => $_SESSION['academic_level'] ?? 'Not set',
            'academic_level_id' => $_SESSION['academic_level_id'] ?? 'Not set'
        ];
    }
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    http_response_code(500);
}

echo json_encode($response);
?>