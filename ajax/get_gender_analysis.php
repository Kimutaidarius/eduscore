<?php
session_start();
require_once '../includes/config.php';

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['school_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$school_id = $_SESSION['school_id'];
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

// Get class name and stream information
$classQuery = $conn->prepare("
    SELECT c.class_level, s.stream_name
    FROM tblclasses c
    LEFT JOIN tblstreams s ON s.class_id = c.id AND s.school_id = c.school_id
    WHERE c.id = ? AND c.school_id = ?
");
$classQuery->bind_param("ii", $class_id, $school_id);
$classQuery->execute();
$classResult = $classQuery->get_result();
$classData = $classResult->fetch_assoc();
$class_level = $classData['class_level'] ?? 'Class';
$stream_name = $classData['stream_name'] ?? '';

// Get gender-based statistics
$genders = ['Male', 'Female'];
$data = [];

foreach ($genders as $gender) {
    // Format gender display
    $gender_display = ($gender == 'Male') ? 'Boys' : 'Girls';
    
    // Get stream information for each student
    $streamQuery = $conn->prepare("
        SELECT DISTINCT s.StreamId, str.stream_name
        FROM tblstudents s
        LEFT JOIN tblstreams str ON s.StreamId = str.id
        WHERE s.class_id = ? AND s.school_id = ? AND s.Gender = ?
    ");
    $streamQuery->bind_param("iis", $class_id, $school_id, $gender);
    $streamQuery->execute();
    $streamResult = $streamQuery->get_result();
    
    if ($streamResult->num_rows > 0) {
        while ($streamRow = $streamResult->fetch_assoc()) {
            $current_stream_id = $streamRow['StreamId'];
            $current_stream_name = $streamRow['stream_name'] ?? '';
            
            // Get student count for this gender and stream
            $studentCountQuery = $conn->prepare("
                SELECT COUNT(*) as total_students
                FROM tblstudents
                WHERE class_id = ? AND school_id = ? AND Gender = ? 
                AND (StreamId = ? OR (? IS NULL AND StreamId IS NULL))
            ");
            $studentCountQuery->bind_param("iisii", $class_id, $school_id, $gender, $current_stream_id, $current_stream_id);
            $studentCountQuery->execute();
            $studentCountResult = $studentCountQuery->get_result();
            $studentCount = $studentCountResult->fetch_assoc();
            $total_students = intval($studentCount['total_students'] ?? 0);
            
            if ($total_students > 0) {
                // Get grade counts and mean percentage for this gender and stream
                $statsQuery = $conn->prepare("
                    SELECT 
                        COUNT(CASE WHEN sc.grade = 'EE' THEN 1 END) as ee_count,
                        COUNT(CASE WHEN sc.grade = 'ME' THEN 1 END) as me_count,
                        COUNT(CASE WHEN sc.grade = 'AE' THEN 1 END) as ae_count,
                        COUNT(CASE WHEN sc.grade = 'AP' THEN 1 END) as ap_count,
                        COUNT(CASE WHEN sc.grade = 'BE' THEN 1 END) as be_count,
                        COUNT(CASE WHEN sc.grade = 'X' OR sc.grade IS NULL OR sc.grade = '' THEN 1 END) as x_count,
                        AVG(sc.percentage) as mean_mark,
                        AVG(CAST(sc.rubric AS DECIMAL(5,2))) as mean_rubric
                    FROM tblstudents s
                    LEFT JOIN tblscores sc ON s.id = sc.student_id AND sc.exam_id = ?
                    WHERE s.class_id = ? AND s.school_id = ? AND s.Gender = ?
                    AND (s.StreamId = ? OR (? IS NULL AND s.StreamId IS NULL))
                ");
                $statsQuery->bind_param("iiisii", $exam_id, $class_id, $school_id, $gender, $current_stream_id, $current_stream_id);
                $statsQuery->execute();
                $statsResult = $statsQuery->get_result();
                $stats = $statsResult->fetch_assoc();
                
                if ($stats) {
                    // Convert rubric from varchar to numeric for averaging
                    $mean_rubric = 0;
                    if (!empty($stats['mean_rubric'])) {
                        $mean_rubric = floatval($stats['mean_rubric']);
                    }
                    
                    $mean_mark = floatval($stats['mean_mark'] ?? 0);
                    
                    // Determine overall grade based on mean mark
                    $grade = 'X';
                    if ($mean_mark >= 80) $grade = 'EE';
                    else if ($mean_mark >= 65) $grade = 'ME';
                    else if ($mean_mark >= 50) $grade = 'AE';
                    else if ($mean_mark >= 40) $grade = 'AP';
                    else if ($mean_mark >= 0) $grade = 'BE';
                    
                    // Format class display with stream if exists
                    $class_display = $class_level;
                    if (!empty($current_stream_name)) {
                        $class_display .= ' ' . $current_stream_name;
                    }
                    $class_display .= ' ' . $gender_display;
                    
                    $data[] = [
                        'class_display' => $class_display,
                        'gender' => $gender_display,
                        'entry_count' => $total_students,
                        'ee_count' => intval($stats['ee_count'] ?? 0),
                        'me_count' => intval($stats['me_count'] ?? 0),
                        'ae_count' => intval($stats['ae_count'] ?? 0),
                        'ap_count' => intval($stats['ap_count'] ?? 0),
                        'be_count' => intval($stats['be_count'] ?? 0),
                        'x_count' => intval($stats['x_count'] ?? 0),
                        'mean_rubric' => round($mean_rubric, 2),
                        'mean_mark' => round($mean_mark, 2),
                        'grade' => $grade
                    ];
                }
            }
        }
    } else {
        // No streams, get all students of this gender in the class
        $studentCountQuery = $conn->prepare("
            SELECT COUNT(*) as total_students
            FROM tblstudents
            WHERE class_id = ? AND school_id = ? AND Gender = ?
        ");
        $studentCountQuery->bind_param("iis", $class_id, $school_id, $gender);
        $studentCountQuery->execute();
        $studentCountResult = $studentCountQuery->get_result();
        $studentCount = $studentCountResult->fetch_assoc();
        $total_students = intval($studentCount['total_students'] ?? 0);
        
        if ($total_students > 0) {
            // Get grade counts and mean percentage for this gender
            $statsQuery = $conn->prepare("
                SELECT 
                    COUNT(CASE WHEN sc.grade = 'EE' THEN 1 END) as ee_count,
                    COUNT(CASE WHEN sc.grade = 'ME' THEN 1 END) as me_count,
                    COUNT(CASE WHEN sc.grade = 'AE' THEN 1 END) as ae_count,
                    COUNT(CASE WHEN sc.grade = 'AP' THEN 1 END) as ap_count,
                    COUNT(CASE WHEN sc.grade = 'BE' THEN 1 END) as be_count,
                    COUNT(CASE WHEN sc.grade = 'X' OR sc.grade IS NULL OR sc.grade = '' THEN 1 END) as x_count,
                    AVG(sc.percentage) as mean_mark,
                    AVG(CAST(sc.rubric AS DECIMAL(5,2))) as mean_rubric
                FROM tblstudents s
                LEFT JOIN tblscores sc ON s.id = sc.student_id AND sc.exam_id = ?
                WHERE s.class_id = ? AND s.school_id = ? AND s.Gender = ?
            ");
            $statsQuery->bind_param("iiis", $exam_id, $class_id, $school_id, $gender);
            $statsQuery->execute();
            $statsResult = $statsQuery->get_result();
            $stats = $statsResult->fetch_assoc();
            
            if ($stats) {
                $mean_rubric = floatval($stats['mean_rubric'] ?? 0);
                $mean_mark = floatval($stats['mean_mark'] ?? 0);
                
                $grade = 'X';
                if ($mean_mark >= 80) $grade = 'EE';
                else if ($mean_mark >= 65) $grade = 'ME';
                else if ($mean_mark >= 50) $grade = 'AE';
                else if ($mean_mark >= 40) $grade = 'AP';
                else if ($mean_mark >= 0) $grade = 'BE';
                
                $class_display = $class_level . ' ' . $gender_display;
                
                $data[] = [
                    'class_display' => $class_display,
                    'gender' => $gender_display,
                    'entry_count' => $total_students,
                    'ee_count' => intval($stats['ee_count'] ?? 0),
                    'me_count' => intval($stats['me_count'] ?? 0),
                    'ae_count' => intval($stats['ae_count'] ?? 0),
                    'ap_count' => intval($stats['ap_count'] ?? 0),
                    'be_count' => intval($stats['be_count'] ?? 0),
                    'x_count' => intval($stats['x_count'] ?? 0),
                    'mean_rubric' => round($mean_rubric, 2),
                    'mean_mark' => round($mean_mark, 2),
                    'grade' => $grade
                ];
            }
        }
    }
}

$conn->close();

echo json_encode(['success' => true, 'data' => $data]);
?>