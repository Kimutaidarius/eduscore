<?php
// api/get_exam_total.php

session_start();
require_once __DIR__ . '/../includes/config.php';

function sendResponse($data = null, $status = 'success', $message = '', $httpCode = 200) {
    http_response_code($httpCode);
    header('Content-Type: application/json');
    
    $response = [
        'success' => $status === 'success',
        'message' => $message,
        'data' => $data,
        'status' => $status
    ];
    
    echo json_encode($response);
    exit;
}

// Ensure user is logged in
if (!isset($_SESSION['teacher_id'], $_SESSION['school_id'])) {
    sendResponse(null, 'error', 'Unauthorized', 401);
}

// Get parameters
$examId = isset($_GET['exam_id']) ? (int) $_GET['exam_id'] : null;
$subjectId = isset($_GET['subject_id']) ? (int) $_GET['subject_id'] : null;
$classId = isset($_GET['class_id']) ? (int) $_GET['class_id'] : null;
$streamId = isset($_GET['stream_id']) ? (int) $_GET['stream_id'] : null;

if (!$examId || !$subjectId || !$classId) {
    sendResponse(null, 'error', 'Missing required parameters', 400);
}

$schoolId = (int) $_SESSION['school_id'];

try {
    // Try to get the total score from existing records
    $sql = "
        SELECT DISTINCT total_score 
        FROM tblscores 
        WHERE school_id = :school_id 
        AND exam_id = :exam_id 
        AND subject_id = :subject_id 
        AND class_id = :class_id
        " . ($streamId ? " AND StreamId = :stream_id" : "") . "
        AND total_score IS NOT NULL
        LIMIT 1
    ";
    
    $params = [
        ':school_id' => $schoolId,
        ':exam_id' => $examId,
        ':subject_id' => $subjectId,
        ':class_id' => $classId
    ];
    
    if ($streamId) {
        $params[':stream_id'] = $streamId;
    }
    
    $stmt = $dbh->prepare($sql);
    $stmt->execute($params);
    
    $totalScore = $stmt->fetchColumn();
    
    if ($totalScore !== false) {
        sendResponse([
            'total_score' => (float) $totalScore,
            'is_default' => false
        ], 'success', 'Exam total score found');
    } else {
        // Return default value if not found
        sendResponse([
            'total_score' => 100,
            'is_default' => true
        ], 'success', 'Using default exam total score');
    }
    
} catch (PDOException $e) {
    error_log('Get exam total error: ' . $e->getMessage());
    sendResponse(null, 'error', 'Failed to get exam total score', 500);
}