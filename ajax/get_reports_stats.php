<?php
session_start();
header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['school_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Database connection
require_once '../config/config.php';

$teacher_id = $_SESSION['teacher_id'];
$school_id = $_SESSION['school_id'];
$academic_level = $_POST['academic_level'] ?? $_SESSION['academic_level'] ?? 'primary';

$response = ['success' => false];

try {
    // Get class count - Using correct table structure
    $classQuery = $db->prepare("
        SELECT COUNT(*) as count FROM tblclasses 
        WHERE school_id = :school_id AND academic_level = :academic_level
    ");
    $classQuery->bindParam(':school_id', $school_id, PDO::PARAM_INT);
    $classQuery->bindParam(':academic_level', $academic_level, PDO::PARAM_STR);
    $classQuery->execute();
    $classResult = $classQuery->fetch(PDO::FETCH_ASSOC);
    $classCount = $classResult['count'] ?? 0;
    
    // Get classes list for dropdown
    $classesQuery = $db->prepare("
        SELECT id, class_level as display_name FROM tblclasses 
        WHERE school_id = :school_id AND academic_level = :academic_level
        ORDER BY class_level
    ");
    $classesQuery->bindParam(':school_id', $school_id, PDO::PARAM_INT);
    $classesQuery->bindParam(':academic_level', $academic_level, PDO::PARAM_STR);
    $classesQuery->execute();
    $classes = $classesQuery->fetchAll(PDO::FETCH_ASSOC);
    
    // Get teacher's subject count - Using correct joins
    $subjectQuery = $db->prepare("
        SELECT COUNT(DISTINCT s.id) as count 
        FROM tblsubjects s
        LEFT JOIN tbllessons l ON s.id = l.subject_id 
        WHERE l.teacher_id = :teacher_id AND s.school_id = :school_id
    ");
    $subjectQuery->bindParam(':teacher_id', $teacher_id, PDO::PARAM_INT);
    $subjectQuery->bindParam(':school_id', $school_id, PDO::PARAM_INT);
    $subjectQuery->execute();
    $subjectResult = $subjectQuery->fetch(PDO::FETCH_ASSOC);
    $subjectCount = $subjectResult['count'] ?? 0;
    
    // Get exam count - Filter by academic level through class join
    $examQuery = $db->prepare("
        SELECT COUNT(DISTINCT e.id) as count 
        FROM tblexam e
        INNER JOIN tblclasses c ON e.class_id = c.id
        WHERE e.school_id = :school_id AND c.academic_level = :academic_level
    ");
    $examQuery->bindParam(':school_id', $school_id, PDO::PARAM_INT);
    $examQuery->bindParam(':academic_level', $academic_level, PDO::PARAM_STR);
    $examQuery->execute();
    $examResult = $examQuery->fetch(PDO::FETCH_ASSOC);
    $examCount = $examResult['count'] ?? 0;
    
    // Get student count - Filter by academic level through class join
    $studentQuery = $db->prepare("
        SELECT COUNT(*) as count 
        FROM tblstudents s
        INNER JOIN tblclasses c ON s.class_id = c.id
        WHERE s.school_id = :school_id AND c.academic_level = :academic_level
        AND s.Status = 'Active'
    ");
    $studentQuery->bindParam(':school_id', $school_id, PDO::PARAM_INT);
    $studentQuery->bindParam(':academic_level', $academic_level, PDO::PARAM_STR);
    $studentQuery->execute();
    $studentResult = $studentQuery->fetch(PDO::FETCH_ASSOC);
    $studentCount = $studentResult['count'] ?? 0;
    
    // Get recent reports count - Using correct report_cards table
    $reportsQuery = $db->prepare("
        SELECT COUNT(DISTINCT CONCAT(rc.class_id, '-', rc.exam_id, '-', rc.term_id, '-', rc.academic_year)) as count 
        FROM report_cards rc
        INNER JOIN tblclasses c ON rc.class_id = c.id
        WHERE rc.school_id = :school_id AND rc.student_id = 0 
        AND rc.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        AND c.academic_level = :academic_level
    ");
    $reportsQuery->bindParam(':school_id', $school_id, PDO::PARAM_INT);
    $reportsQuery->bindParam(':academic_level', $academic_level, PDO::PARAM_STR);
    $reportsQuery->execute();
    $reportsResult = $reportsQuery->fetch(PDO::FETCH_ASSOC);
    $recentReports = $reportsResult['count'] ?? 0;
    
    $response = [
        'success' => true,
        'class_count' => $classCount,
        'subject_count' => $subjectCount,
        'exam_count' => $examCount,
        'student_count' => $studentCount,
        'recent_reports' => $recentReports,
        'classes' => $classes
    ];
    
} catch (Exception $e) {
    error_log("Error in get_reports_stats.php: " . $e->getMessage());
    $response = ['success' => false, 'message' => $e->getMessage()];
}

echo json_encode($response);
?>