<?php
session_start();
require_once '../includes/config.php';

header('Content-Type: application/json');

// Enable error logging for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['school_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$school_id = $_SESSION['school_id'];
$academic_level = $_SESSION['academic_level'] ?? 'primary';
$class_id = $_POST['class_id'] ?? 0;
$year = $_POST['year'] ?? '';
$term_id = $_POST['term_id'] ?? 0;
$exam_id = $_POST['exam_id'] ?? 0;

if (!$class_id || !$year || !$term_id || !$exam_id) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit();
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Set charset to avoid encoding issues
$conn->set_charset("utf8mb4");

// Get the selected class name
$classQuery = $conn->prepare("SELECT class_level FROM tblclasses WHERE id = ? AND school_id = ? AND academic_level = ?");
$classQuery->bind_param("iis", $class_id, $school_id, $academic_level);
$classQuery->execute();
$classResult = $classQuery->get_result();
$classData = $classResult->fetch_assoc();
$selected_class_name = $classData['class_level'] ?? 'Class';
$classQuery->close();

// Get all subjects for this class
$subjectsQuery = $conn->prepare("
    SELECT s.id, s.subject_name, s.alias, 
           COALESCE(CONCAT(t.firstname, ' ', t.lastname), 'Not Assigned') as teacher_name
    FROM tblsubjects s
    LEFT JOIN tblteachers t ON s.teacher_id = t.id
    WHERE s.class_id = ? AND s.school_id = ?
    ORDER BY s.subject_name
");
$subjectsQuery->bind_param("ii", $class_id, $school_id);
$subjectsQuery->execute();
$subjectsResult = $subjectsQuery->get_result();

$data = [];

while ($subject = $subjectsResult->fetch_assoc()) {
    // Get streams for this class
    $streamsQuery = $conn->prepare("
        SELECT id, stream_name 
        FROM tblstreams 
        WHERE class_id = ? AND school_id = ?
        ORDER BY stream_name
    ");
    $streamsQuery->bind_param("ii", $class_id, $school_id);
    $streamsQuery->execute();
    $streamsResult = $streamsQuery->get_result();
    
    if ($streamsResult->num_rows > 0) {
        while ($stream = $streamsResult->fetch_assoc()) {
            // Get scores for this subject and stream
            // FIXED: Removed CAST(rubric AS DECIMAL) since rubric is VARCHAR
            $scoresQuery = $conn->prepare("
                SELECT 
                    COUNT(*) as total_students,
                    SUM(CASE WHEN grade = 'EE' THEN 1 ELSE 0 END) as ee_count,
                    SUM(CASE WHEN grade = 'ME' THEN 1 ELSE 0 END) as me_count,
                    SUM(CASE WHEN grade = 'AE' THEN 1 ELSE 0 END) as ae_count,
                    SUM(CASE WHEN grade = 'AP' THEN 1 ELSE 0 END) as ap_count,
                    SUM(CASE WHEN grade = 'BE' THEN 1 ELSE 0 END) as be_count,
                    SUM(CASE WHEN grade = 'X' OR grade IS NULL OR grade = '' THEN 1 ELSE 0 END) as x_count,
                    AVG(percentage) as mean_score,
                    0 as avg_rubric
                FROM tblscores s
                JOIN tblstudents st ON s.student_id = st.id
                WHERE s.subject_id = ? AND s.exam_id = ? 
                    AND st.stream_id = ? AND s.school_id = ?
            ");
            $scoresQuery->bind_param("iiii", $subject['id'], $exam_id, $stream['id'], $school_id);
            $scoresQuery->execute();
            $scoresResult = $scoresQuery->get_result();
            $scores = $scoresResult->fetch_assoc();
            
            if ($scores && $scores['total_students'] > 0) {
                $data[] = [
                    'subject_name' => $subject['subject_name'],
                    'class_display' => $selected_class_name . ' ' . $stream['stream_name'],
                    'ee_count' => intval($scores['ee_count']),
                    'me_count' => intval($scores['me_count']),
                    'ae_count' => intval($scores['ae_count']),
                    'ap_count' => intval($scores['ap_count']),
                    'be_count' => intval($scores['be_count']),
                    'x_count' => intval($scores['x_count']),
                    'mean' => floatval($scores['mean_score']),
                    'avg_rubric' => floatval($scores['avg_rubric']),
                    'teacher_name' => $subject['teacher_name']
                ];
            }
            $scoresQuery->close();
        }
        $streamsQuery->close();
    } else {
        // No streams, get all students in class
        // FIXED: Removed CAST(rubric AS DECIMAL) since rubric is VARCHAR
        $scoresQuery = $conn->prepare("
            SELECT 
                COUNT(*) as total_students,
                SUM(CASE WHEN grade = 'EE' THEN 1 ELSE 0 END) as ee_count,
                SUM(CASE WHEN grade = 'ME' THEN 1 ELSE 0 END) as me_count,
                SUM(CASE WHEN grade = 'AE' THEN 1 ELSE 0 END) as ae_count,
                SUM(CASE WHEN grade = 'AP' THEN 1 ELSE 0 END) as ap_count,
                SUM(CASE WHEN grade = 'BE' THEN 1 ELSE 0 END) as be_count,
                SUM(CASE WHEN grade = 'X' OR grade IS NULL OR grade = '' THEN 1 ELSE 0 END) as x_count,
                AVG(percentage) as mean_score,
                0 as avg_rubric
            FROM tblscores
            WHERE subject_id = ? AND exam_id = ? AND school_id = ?
        ");
        $scoresQuery->bind_param("iii", $subject['id'], $exam_id, $school_id);
        $scoresQuery->execute();
        $scoresResult = $scoresQuery->get_result();
        $scores = $scoresResult->fetch_assoc();
        
        if ($scores && $scores['total_students'] > 0) {
            $data[] = [
                'subject_name' => $subject['subject_name'],
                'class_display' => $selected_class_name,
                'ee_count' => intval($scores['ee_count']),
                'me_count' => intval($scores['me_count']),
                'ae_count' => intval($scores['ae_count']),
                'ap_count' => intval($scores['ap_count']),
                'be_count' => intval($scores['be_count']),
                'x_count' => intval($scores['x_count']),
                'mean' => floatval($scores['mean_score']),
                'avg_rubric' => floatval($scores['avg_rubric']),
                'teacher_name' => $subject['teacher_name']
            ];
        }
        $scoresQuery->close();
    }
}

$conn->close();

echo json_encode(['success' => true, 'data' => $data]);
?>