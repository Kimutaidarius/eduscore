<?php
// Enable error logging but don't display errors
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json');

// Function to send JSON response
function sendJsonResponse($success, $message = '', $data = []) {
    $response = ['success' => $success];
    if ($message) $response['message'] = $message;
    if (!empty($data)) {
        foreach ($data as $key => $value) {
            $response[$key] = $value;
        }
    }
    echo json_encode($response);
    exit;
}

// Check if user is logged in
if (empty($_SESSION['school_id']) && empty($_SESSION['demo_mode'])) {
    sendJsonResponse(false, 'Not authenticated');
}

// CORRECTED PATH - use includes/config.php
$configPath = __DIR__ . '/../includes/config.php';

if (!file_exists($configPath)) {
    sendJsonResponse(false, 'Configuration file not found');
}

require_once $configPath;

// Get academic level from request
$academic_level = isset($_POST['academic_level']) ? $_POST['academic_level'] : ($_SESSION['academic_level'] ?? 'primary');

// Map academic level to display name
$academic_level_map = [
    'primary' => 'Primary School',
    'junior_secondary' => 'Junior Secondary',
    'senior_secondary' => 'Senior Secondary',
    'college' => 'College'
];

$display_name = $academic_level_map[$academic_level] ?? 'Primary School';

try {
    $stats = [
        'total_students' => 0,
        'male_students' => 0,
        'female_students' => 0,
        'total_teachers' => 0,
        'male_teachers' => 0,
        'female_teachers' => 0,
        'total_classes' => 0
    ];
    
    // Get students count with gender breakdown (FILTERED BY ACADEMIC LEVEL)
    $query = "SELECT COUNT(*) as total, 
              SUM(CASE WHEN s.Gender = 'Male' THEN 1 ELSE 0 END) as male_count, 
              SUM(CASE WHEN s.Gender = 'Female' THEN 1 ELSE 0 END) as female_count 
              FROM tblstudents s 
              LEFT JOIN tblclasses c ON s.class_id = c.id 
              WHERE s.school_id = :school_id 
              AND s.Status = 'Active' 
              AND c.academic_level = :academic_level";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":school_id", $_SESSION['school_id'], PDO::PARAM_INT);
    $stmt->bindParam(":academic_level", $academic_level);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $stats['total_students'] = (int)($result['total'] ?? 0);
        $stats['male_students'] = (int)($result['male_count'] ?? 0);
        $stats['female_students'] = (int)($result['female_count'] ?? 0);
    }
    
    // Get teachers count (teachers are school-wide, not filtered by academic level)
    $query = "SELECT COUNT(*) as total, 
              SUM(CASE WHEN gender = 'Male' THEN 1 ELSE 0 END) as male_count, 
              SUM(CASE WHEN gender = 'Female' THEN 1 ELSE 0 END) as female_count 
              FROM tblteachers 
              WHERE school_id = :school_id 
              AND status = 'Active'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":school_id", $_SESSION['school_id'], PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $stats['total_teachers'] = (int)($result['total'] ?? 0);
        $stats['male_teachers'] = (int)($result['male_count'] ?? 0);
        $stats['female_teachers'] = (int)($result['female_count'] ?? 0);
    }
    
    // Get classes count (FILTERED BY ACADEMIC LEVEL)
    $query = "SELECT COUNT(*) as total FROM tblclasses 
              WHERE school_id = :school_id 
              AND academic_level = :academic_level";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":school_id", $_SESSION['school_id'], PDO::PARAM_INT);
    $stmt->bindParam(":academic_level", $academic_level);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_classes'] = (int)($result['total'] ?? 0);
    
    // Get top performing students (FILTERED BY ACADEMIC LEVEL)
    $query = "SELECT s.FirstName, s.SecondName, s.LastName, s.Gender, s.AdmNo as AdmissionNo, 
              ROUND(AVG(sc.score_value), 1) as avg_score, COUNT(sc.id) as total_exams 
              FROM tblscores sc 
              JOIN tblstudents s ON sc.student_id = s.id 
              LEFT JOIN tblclasses c ON s.class_id = c.id 
              WHERE sc.school_id = :school_id 
              AND c.academic_level = :academic_level
              AND sc.recorded_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
              GROUP BY s.id 
              HAVING total_exams >= 1 
              ORDER BY avg_score DESC 
              LIMIT 6";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":school_id", $_SESSION['school_id'], PDO::PARAM_INT);
    $stmt->bindParam(":academic_level", $academic_level);
    $stmt->execute();
    $top_students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent activities (FILTERED BY ACADEMIC LEVEL)
    // FIXED: Removed sc.exam_type, using sc.grade instead
    $query = "SELECT s.FirstName, s.SecondName, s.LastName, s.Gender as student_gender, 
              sub.subject_name, sc.score_value, sc.grade as exam_type, sc.recorded_at 
              FROM tblscores sc 
              JOIN tblstudents s ON sc.student_id = s.id 
              JOIN tblsubjects sub ON sc.subject_id = sub.id 
              LEFT JOIN tblclasses c ON s.class_id = c.id 
              WHERE sc.school_id = :school_id 
              AND c.academic_level = :academic_level
              AND sc.recorded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
              ORDER BY sc.recorded_at DESC 
              LIMIT 8";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":school_id", $_SESSION['school_id'], PDO::PARAM_INT);
    $stmt->bindParam(":academic_level", $academic_level);
    $stmt->execute();
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    sendJsonResponse(true, 'Data retrieved successfully', [
        'academic_level' => $academic_level,
        'display_name' => $display_name,
        'stats' => $stats,
        'top_students' => $top_students,
        'activities' => $activities
    ]);
    
} catch (PDOException $e) {
    error_log("Dashboard data fetch error: " . $e->getMessage());
    sendJsonResponse(false, 'Database error: ' . $e->getMessage());
}
?>