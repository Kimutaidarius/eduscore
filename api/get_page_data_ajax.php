<?php
/**
 * AJAX endpoint to fetch page data for academic level switching
 * This file handles dynamic data loading for various pages when academic level changes
 */

session_start();
require_once dirname(__DIR__) . '/includes/config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['school_id'])) {
    echo json_encode([
        'success' => false, 
        'message' => 'Unauthorized access. Please log in.'
    ]);
    exit();
}

// Get parameters
$academic_level = $_POST['academic_level'] ?? $_GET['academic_level'] ?? $_SESSION['academic_level'] ?? 'primary';
$page = $_POST['page'] ?? $_GET['page'] ?? 'dashboard';
$teacher_id = $_SESSION['teacher_id'];
$school_id = $_SESSION['school_id'];

$conn = $dbh;

// Validate academic level
$valid_levels = ['primary', 'junior_secondary', 'senior_secondary', 'college'];
if (!in_array($academic_level, $valid_levels)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid academic level'
    ]);
    exit();
}

// Level display names
$level_names = [
    'primary' => 'Primary School',
    'junior_secondary' => 'Junior Secondary',
    'senior_secondary' => 'Senior Secondary',
    'college' => 'College'
];

try {
    // Common data for all pages
    $response = [
        'success' => true,
        'academic_level' => $academic_level,
        'academic_level_display' => $level_names[$academic_level],
        'stats' => [],
        'classes' => [],
        'subjects' => [],
        'streams' => []
    ];
    
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
    $response['classes'] = $classes;
    
    // Fetch streams for classes
    if (!empty($classes)) {
        $classIds = array_column($classes, 'id');
        $placeholders = implode(',', array_fill(0, count($classIds), '?'));
        $streamsQuery = $conn->prepare("
            SELECT id, stream_name, class_id 
            FROM tblstreams 
            WHERE class_id IN ($placeholders) 
            AND school_id = ?
            ORDER BY stream_name
        ");
        $params = array_merge($classIds, [$school_id]);
        $streamsQuery->execute($params);
        $streams = $streamsQuery->fetchAll(PDO::FETCH_ASSOC);
        $response['streams'] = $streams;
    }
    
    // Fetch subjects based on teacher's assignments
    $subjectsQuery = $conn->prepare("
        SELECT DISTINCT s.id, s.subject_name, s.alias, s.subject_type
        FROM tblsubjects s
        JOIN tbllessons l ON l.subject_id = s.id
        JOIN tblclasses c ON l.class_id = c.id
        WHERE l.teacher_id = ? 
        AND l.school_id = ?
        AND c.academic_level = ?
        ORDER BY s.subject_name
    ");
    $subjectsQuery->execute([$teacher_id, $school_id, $academic_level]);
    $subjects = $subjectsQuery->fetchAll(PDO::FETCH_ASSOC);
    $response['subjects'] = $subjects;
    
    // Fetch teacher's name
    $teacherQuery = $conn->prepare("
        SELECT CONCAT(firstname, ' ', secondname) as teacher_name 
        FROM tblteachers 
        WHERE id = ?
    ");
    $teacherQuery->execute([$teacher_id]);
    $teacherRow = $teacherQuery->fetch(PDO::FETCH_ASSOC);
    $response['teacher_name'] = $teacherRow['teacher_name'] ?? 'Teacher';
    
    // Common stats
    $response['stats']['total_classes'] = count($classes);
    $response['stats']['total_subjects'] = count($subjects);
    
    // Fetch exams count
    $examQuery = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM tblexam e
        JOIN tblclasses c ON e.class_id = c.id
        WHERE e.school_id = ? 
        AND c.academic_level = ?
        AND e.status = 'Active'
    ");
    $examQuery->execute([$school_id, $academic_level]);
    $examRow = $examQuery->fetch(PDO::FETCH_ASSOC);
    $response['stats']['total_exams'] = $examRow['count'] ?? 0;
    
    // Fetch student count
    $studentCountQuery = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM tblstudents s
        JOIN tblclasses c ON s.class_id = c.id
        WHERE s.school_id = ? 
        AND c.academic_level = ?
        AND s.Status = 'Active'
    ");
    $studentCountQuery->execute([$school_id, $academic_level]);
    $studentCountRow = $studentCountQuery->fetch(PDO::FETCH_ASSOC);
    $response['stats']['total_students'] = $studentCountRow['count'] ?? 0;
    
    // Fetch teacher's assigned subjects count
    $teacherSubjectsQuery = $conn->prepare("
        SELECT COUNT(DISTINCT l.subject_id) as count 
        FROM tbllessons l
        JOIN tblclasses c ON l.class_id = c.id
        WHERE l.teacher_id = ? 
        AND l.school_id = ?
        AND c.academic_level = ?
    ");
    $teacherSubjectsQuery->execute([$teacher_id, $school_id, $academic_level]);
    $teacherSubjectsRow = $teacherSubjectsQuery->fetch(PDO::FETCH_ASSOC);
    $response['stats']['teacher_subjects'] = $teacherSubjectsRow['count'] ?? 0;
    
    // Fetch terms
    $termsQuery = $conn->prepare("
        SELECT id, term_name, term_number, academic_year, start_date, end_date,
               CASE 
                   WHEN CURDATE() BETWEEN start_date AND end_date THEN 'active'
                   WHEN CURDATE() < start_date THEN 'upcoming'
                   ELSE 'closed'
               END as term_status
        FROM tblterms 
        WHERE school_id = ?
        ORDER BY academic_year DESC, term_number
    ");
    $termsQuery->execute([$school_id]);
    $response['terms'] = $termsQuery->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch years
    $yearsQuery = $conn->prepare("
        SELECT DISTINCT academic_year as year 
        FROM tblterms 
        WHERE school_id = ?
        ORDER BY academic_year DESC
    ");
    $yearsQuery->execute([$school_id]);
    $response['years'] = $yearsQuery->fetchAll(PDO::FETCH_COLUMN);
    
    // Page-specific data
    switch($page) {
        case 'dashboard':
            // Fetch recent students
            $recentStudentsQuery = $conn->prepare("
                SELECT s.id, CONCAT(s.FirstName, ' ', s.SecondName) as full_name, 
                       s.AdmNo as admission_no, s.Gender as gender, c.class_level as class_name
                FROM tblstudents s
                JOIN tblclasses c ON s.class_id = c.id
                WHERE s.school_id = ? 
                AND c.academic_level = ?
                AND s.Status = 'Active'
                ORDER BY s.id DESC
                LIMIT 10
            ");
            $recentStudentsQuery->execute([$school_id, $academic_level]);
            $response['recent_students'] = $recentStudentsQuery->fetchAll(PDO::FETCH_ASSOC);
            
            // Fetch performance data for chart
            $performanceQuery = $conn->prepare("
                SELECT 
                    DATE_FORMAT(sc.recorded_at, '%Y-%m') as month,
                    AVG(sc.percentage) as avg_score
                FROM tblscores sc
                JOIN tblstudents s ON sc.student_id = s.id
                JOIN tblclasses c ON s.class_id = c.id
                WHERE sc.school_id = ? 
                AND c.academic_level = ?
                AND sc.recorded_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                GROUP BY DATE_FORMAT(sc.recorded_at, '%Y-%m')
                ORDER BY month ASC
            ");
            $performanceQuery->execute([$school_id, $academic_level]);
            $response['performance_data'] = $performanceQuery->fetchAll(PDO::FETCH_ASSOC);
            
            // Fetch top students
            $topStudentsQuery = $conn->prepare("
                SELECT 
                    s.id, s.FirstName, s.SecondName, s.LastName, s.Gender, s.AdmNo,
                    AVG(sc.percentage) as avg_score
                FROM tblstudents s
                JOIN tblscores sc ON s.id = sc.student_id
                JOIN tblclasses c ON s.class_id = c.id
                WHERE s.school_id = ? 
                AND c.academic_level = ?
                AND s.Status = 'Active'
                GROUP BY s.id
                ORDER BY avg_score DESC
                LIMIT 10
            ");
            $topStudentsQuery->execute([$school_id, $academic_level]);
            $response['top_students'] = $topStudentsQuery->fetchAll(PDO::FETCH_ASSOC);
            break;
            
        case 'students':
            // Fetch all students
            $studentsQuery = $conn->prepare("
                SELECT s.id, s.FirstName, s.SecondName, s.LastName, s.AdmNo as admission_no,
                       s.Gender, s.Status, c.class_level as class_name, st.stream_name
                FROM tblstudents s
                JOIN tblclasses c ON s.class_id = c.id
                LEFT JOIN tblstreams st ON s.StreamId = st.id
                WHERE s.school_id = ? 
                AND c.academic_level = ?
                ORDER BY s.FirstName
            ");
            $studentsQuery->execute([$school_id, $academic_level]);
            $response['students'] = $studentsQuery->fetchAll(PDO::FETCH_ASSOC);
            $response['stats']['total_students'] = count($response['students']);
            break;
            
        case 'classes':
            // Fetch classes with stream information
            $response['classes_with_streams'] = $classes;
            foreach ($classes as &$class) {
                $classStreams = array_filter($streams, function($stream) use ($class) {
                    return $stream['class_id'] == $class['id'];
                });
                $class['streams'] = array_values($classStreams);
                $class['stream_count'] = count($classStreams);
            }
            break;
            
        case 'subjects':
            // Fetch all subjects for this academic level
            $allSubjectsQuery = $conn->prepare("
                SELECT s.id, s.subject_name, s.alias, s.subject_type, c.class_level as class_name
                FROM tblsubjects s
                JOIN tblclasses c ON s.class_id = c.id
                WHERE s.school_id = ? 
                AND c.academic_level = ?
                ORDER BY c.class_level, s.subject_name
            ");
            $allSubjectsQuery->execute([$school_id, $academic_level]);
            $response['subjects'] = $allSubjectsQuery->fetchAll(PDO::FETCH_ASSOC);
            $response['stats']['total_subjects'] = count($response['subjects']);
            break;
            
        case 'teachers':
            // Fetch teachers for this academic level
            $teachersQuery = $conn->prepare("
                SELECT t.id, t.firstname, t.secondname, t.lastname, t.email, t.phonenumber,
                       t.gender, t.role, t.status
                FROM tblteachers t
                WHERE t.school_id = ? 
                AND t.status = 'Active'
                ORDER BY t.firstname
            ");
            $teachersQuery->execute([$school_id]);
            $teachers = $teachersQuery->fetchAll(PDO::FETCH_ASSOC);
            
            // Filter teachers by academic level based on their lessons
            $filteredTeachers = [];
            foreach ($teachers as $teacher) {
                $teacherSubjectsQuery = $conn->prepare("
                    SELECT COUNT(*) as count
                    FROM tbllessons l
                    JOIN tblclasses c ON l.class_id = c.id
                    WHERE l.teacher_id = ? 
                    AND c.academic_level = ?
                ");
                $teacherSubjectsQuery->execute([$teacher['id'], $academic_level]);
                $count = $teacherSubjectsQuery->fetch(PDO::FETCH_ASSOC);
                if ($count['count'] > 0) {
                    $filteredTeachers[] = $teacher;
                }
            }
            $response['teachers'] = $filteredTeachers;
            $response['stats']['total_teachers'] = count($filteredTeachers);
            break;
            
        case 'scores':
            // Fetch subjects for score entry
            $scoreSubjectsQuery = $conn->prepare("
                SELECT DISTINCT s.id, s.subject_name, s.alias
                FROM tblsubjects s
                JOIN tbllessons l ON l.subject_id = s.id
                JOIN tblclasses c ON l.class_id = c.id
                WHERE l.teacher_id = ? 
                AND l.school_id = ?
                AND c.academic_level = ?
                ORDER BY s.subject_name
            ");
            $scoreSubjectsQuery->execute([$teacher_id, $school_id, $academic_level]);
            $response['score_subjects'] = $scoreSubjectsQuery->fetchAll(PDO::FETCH_ASSOC);
            $response['stats']['teacher_subjects'] = count($response['score_subjects']);
            break;
            
        case 'attendance':
            // Fetch attendance data
            $currentTermQuery = $conn->prepare("
                SELECT id, term_name, term_number, academic_year
                FROM tblterms 
                WHERE school_id = ? 
                AND start_date <= CURDATE() 
                AND end_date >= CURDATE()
                LIMIT 1
            ");
            $currentTermQuery->execute([$school_id]);
            $currentTerm = $currentTermQuery->fetch(PDO::FETCH_ASSOC);
            
            if ($currentTerm) {
                $attendanceQuery = $conn->prepare("
                    SELECT a.id, s.FirstName, s.SecondName, a.attendance_date, a.status, a.remarks
                    FROM tblattendance a
                    JOIN tblstudents s ON a.student_id = s.id
                    JOIN tblclasses c ON s.class_id = c.id
                    WHERE a.school_id = ? 
                    AND a.term_id = ?
                    AND c.academic_level = ?
                    ORDER BY a.attendance_date DESC
                    LIMIT 100
                ");
                $attendanceQuery->execute([$school_id, $currentTerm['id'], $academic_level]);
                $response['attendance'] = $attendanceQuery->fetchAll(PDO::FETCH_ASSOC);
                $response['current_term'] = $currentTerm;
            }
            break;
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Error in get_page_data_ajax.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>