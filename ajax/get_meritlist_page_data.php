<?php
session_start();
require_once dirname(__DIR__) . '/includes/config.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['school_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$teacher_id = $_SESSION['teacher_id'];
$school_id = $_SESSION['school_id'];
$academic_level = $_POST['academic_level'] ?? $_SESSION['academic_level'] ?? 'primary';

$conn = $dbh;

try {
    // Fetch classes for this academic level
    $classesQuery = $conn->prepare("
        SELECT id, class_level as display_name 
        FROM tblclasses 
        WHERE school_id = ? 
        AND academic_level = ?
        ORDER BY class_level
    ");
    $classesQuery->execute([$school_id, $academic_level]);
    $classes = $classesQuery->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch teacher's assigned subjects count
    $subjectQuery = $conn->prepare("
        SELECT COUNT(DISTINCT s.id) as count 
        FROM tblsubjects s
        LEFT JOIN tbllessons l ON s.id = l.subject_id 
        WHERE l.teacher_id = ? AND s.school_id = ?
    ");
    $subjectQuery->execute([$teacher_id, $school_id]);
    $subjectRow = $subjectQuery->fetch(PDO::FETCH_ASSOC);
    $subjectCount = $subjectRow['count'] ?? 0;
    
    // Fetch exams count
    $examQuery = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM tblexam 
        WHERE school_id = ?
    ");
    $examQuery->execute([$school_id]);
    $examRow = $examQuery->fetch(PDO::FETCH_ASSOC);
    $examCount = $examRow['count'] ?? 0;
    
    // Fetch student count
    $studentCountQuery = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM tblstudents 
        WHERE school_id = ?
    ");
    $studentCountQuery->execute([$school_id]);
    $studentCountRow = $studentCountQuery->fetch(PDO::FETCH_ASSOC);
    $studentCount = $studentCountRow['count'] ?? 0;
    
    echo json_encode([
        'success' => true,
        'academic_level' => $academic_level,
        'classes' => $classes,
        'stats' => [
            'total_classes' => count($classes),
            'total_subjects' => $subjectCount,
            'total_exams' => $examCount,
            'total_students' => $studentCount
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_meritlist_page_data.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
}
?>