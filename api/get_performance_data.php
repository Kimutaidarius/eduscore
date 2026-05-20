<?php
// Enable error logging but don't display errors
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json');

function sendJsonResponse($success, $message = '', $data = []) {
    $response = ['success' => $success];
    if ($message) $response['message'] = $message;
    if (!empty($data)) $response['data'] = $data;
    echo json_encode($response);
    exit;
}

// Check authentication
if (empty($_SESSION['school_id']) && empty($_SESSION['demo_mode'])) {
    sendJsonResponse(false, 'Not authenticated');
}

// CORRECTED PATH
$configPath = __DIR__ . '/../includes/config.php';

if (!file_exists($configPath)) {
    sendJsonResponse(false, 'Configuration file not found');
}

require_once $configPath;

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'get_filters') {
    // Return available years and terms
    $years = [];
    $terms = [];
    
    try {
        // Get available years from scores table
        $stmt = $db->prepare("SELECT DISTINCT YEAR(recorded_at) as year FROM tblscores WHERE school_id = :school_id ORDER BY year DESC");
        $stmt->bindParam(":school_id", $_SESSION['school_id'], PDO::PARAM_INT);
        $stmt->execute();
        $years = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // If no years found, add current year
        if (empty($years)) {
            $years = [date('Y')];
        }
        
        // Get available terms
        $terms = [
            ['id' => 1, 'term_name' => 'Term 1', 'academic_year' => date('Y')],
            ['id' => 2, 'term_name' => 'Term 2', 'academic_year' => date('Y')],
            ['id' => 3, 'term_name' => 'Term 3', 'academic_year' => date('Y')]
        ];
        
        sendJsonResponse(true, 'Filters retrieved', [
            'available_years' => $years,
            'available_terms' => $terms,
            'current_year' => date('Y'),
            'current_term' => 1
        ]);
    } catch (Exception $e) {
        sendJsonResponse(false, 'Error loading filters: ' . $e->getMessage());
    }
} else {
    // Load performance data
    $year = $_POST['year'] ?? $_GET['year'] ?? date('Y');
    $term = $_POST['term'] ?? $_GET['term'] ?? 1;
    $academic_level = $_POST['academic_level'] ?? $_SESSION['academic_level'] ?? 'primary';
    
    try {
        // Get class performance - FIXED: Using class_level instead of name
        $query = "SELECT 
                    c.id as class_id,
                    c.class_level as class_name,
                    COUNT(DISTINCT s.id) as total_students,
                    ROUND(AVG(sc.score_value), 2) as average_percentage,
                    MIN(sc.score_value) as lowest_score,
                    MAX(sc.score_value) as highest_score
                  FROM tblclasses c
                  LEFT JOIN tblstudents s ON s.class_id = c.id
                  LEFT JOIN tblscores sc ON sc.student_id = s.id
                  WHERE c.school_id = :school_id
                  AND c.academic_level = :academic_level
                  AND (YEAR(sc.recorded_at) = :year OR sc.recorded_at IS NULL)
                  GROUP BY c.id
                  ORDER BY average_percentage DESC";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(":school_id", $_SESSION['school_id'], PDO::PARAM_INT);
        $stmt->bindParam(":academic_level", $academic_level);
        $stmt->bindParam(":year", $year);
        $stmt->execute();
        $class_performance = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get top students - FIXED: Using correct column names
        $query = "SELECT 
                    CONCAT(s.FirstName, ' ', COALESCE(s.SecondName, ''), ' ', COALESCE(s.LastName, '')) as student_name,
                    s.AdmNo as admission_no,
                    s.Gender,
                    ROUND(AVG(sc.score_value), 2) as mean_marks,
                    CASE 
                        WHEN AVG(sc.score_value) >= 75 THEN 'EE'
                        WHEN AVG(sc.score_value) >= 50 THEN 'ME'
                        WHEN AVG(sc.score_value) >= 25 THEN 'AE'
                        ELSE 'BE'
                    END as overall_grade
                  FROM tblstudents s
                  LEFT JOIN tblscores sc ON sc.student_id = s.id
                  LEFT JOIN tblclasses c ON c.id = s.class_id
                  WHERE s.school_id = :school_id
                  AND s.Status = 'Active'
                  AND c.academic_level = :academic_level
                  AND (YEAR(sc.recorded_at) = :year OR sc.recorded_at IS NULL)
                  GROUP BY s.id
                  HAVING mean_marks IS NOT NULL
                  ORDER BY mean_marks DESC
                  LIMIT 10";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(":school_id", $_SESSION['school_id'], PDO::PARAM_INT);
        $stmt->bindParam(":academic_level", $academic_level);
        $stmt->bindParam(":year", $year);
        $stmt->execute();
        $top_students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Add rank to top students
        foreach ($top_students as $index => $student) {
            $top_students[$index]['rank_position'] = $index + 1;
        }
        
        // Calculate overall stats
        $overall_stats = [
            'total_students' => 0,
            'mean_marks' => 0,
            'highest_score' => 0,
            'lowest_score' => 0
        ];
        
        if (!empty($class_performance)) {
            $overall_stats['total_students'] = array_sum(array_column($class_performance, 'total_students'));
            $valid_scores = array_filter(array_column($class_performance, 'average_percentage'));
            if (!empty($valid_scores)) {
                $overall_stats['mean_marks'] = round(array_sum($valid_scores) / count($valid_scores), 2);
            }
            $overall_stats['highest_score'] = max(array_column($class_performance, 'highest_score'));
            $overall_stats['lowest_score'] = min(array_column($class_performance, 'lowest_score'));
        }
        
        sendJsonResponse(true, 'Performance data loaded', [
            'class_performance' => $class_performance,
            'top_students' => $top_students,
            'overall_stats' => $overall_stats,
            'class_grade_distribution' => []
        ]);
        
    } catch (Exception $e) {
        error_log("Performance data error: " . $e->getMessage());
        sendJsonResponse(false, 'Error loading performance data: ' . $e->getMessage());
    }
}
?>