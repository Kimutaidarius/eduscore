<?php
// api/generate_report.php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../classes/ReportGenerator.php';

header('Content-Type: application/json');

// Validate session
session_start();
if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['school_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$teacher_id = $_SESSION['teacher_id'];
$school_id = $_SESSION['school_id'];

// Get input
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

$report_type = $input['report_type'] ?? 'single'; // 'single' or 'merged'

try {
    $reportGenerator = new ReportGenerator($db, $school_id, $teacher_id);
    
    if ($report_type === 'single') {
        // Validate required fields
        $required = ['student_id', 'exam_id', 'term_id', 'academic_year'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }
        
        $result = $reportGenerator->generateSingleStudentReport(
            $input['student_id'],
            $input['exam_id'],
            $input['term_id'],
            $input['academic_year']
        );
        
    } else {
        // Merged report
        $required = ['class_id', 'exam_id', 'term_id', 'academic_year', 'student_ids'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }
        
        $result = $reportGenerator->generateMergedReport(
            $input['class_id'],
            $input['stream_id'] ?? 0,
            $input['exam_id'],
            $input['term_id'],
            $input['academic_year'],
            $input['student_ids']
        );
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}