<?php
session_start();
require_once '../includes/config.php';

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
        SELECT COUNT(DISTINCT l.subject_id) as count 
        FROM tbllessons l
        JOIN tblclasses c ON l.class_id = c.id
        WHERE l.teacher_id = ? 
        AND l.school_id = ?
        AND c.academic_level = ?
    ");
    $subjectQuery->execute([$teacher_id, $school_id, $academic_level]);
    $subjectRow = $subjectQuery->fetch(PDO::FETCH_ASSOC);
    $subjectCount = $subjectRow['count'] ?? 0;
    
    // Fetch exams count
    $examQuery = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM tblexam e
        JOIN tblclasses c ON e.class_id = c.id
        WHERE e.school_id = ? 
        AND c.academic_level = ?
    ");
    $examQuery->execute([$school_id, $academic_level]);
    $examRow = $examQuery->fetch(PDO::FETCH_ASSOC);
    $examCount = $examRow['count'] ?? 0;
    
    // Fetch student count
    $studentCountQuery = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM tblstudents s
        JOIN tblclasses c ON s.class_id = c.id
        WHERE s.school_id = ? 
        AND c.academic_level = ?
    ");
    $studentCountQuery->execute([$school_id, $academic_level]);
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
    error_log("Error in get_scores_page_data.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
}
?>