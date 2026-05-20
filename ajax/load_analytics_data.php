<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['school_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit();
}

require_once '../includes/config.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

$school_id = $_SESSION['school_id'];
$class_id = $input['class_id'] ?? 0;
$stream_id = $input['stream_id'] ?? 0;
$exam_id = $input['exam_id'] ?? 0;
$term_id = $input['term_id'] ?? 0;
$academic_year = $input['academic_year'] ?? date('Y');
$view = $input['view'] ?? 'learning-area-analysis';

$response = ['success' => false, 'data' => [], 'summary' => []];

try {
    switch ($view) {
        case 'learning-area-analysis':
            $response = getLearningAreaAnalysis($conn, $school_id, $class_id, $stream_id, $exam_id, $term_id, $academic_year);
            break;
        case 'learning-area-merit-analysis':
            $response = getLearningAreaMeritAnalysis($conn, $school_id, $class_id, $stream_id, $exam_id, $term_id, $academic_year);
            break;
        case 'champions':
            $response = getChampionsAnalysis($conn, $school_id, $class_id, $stream_id, $exam_id, $term_id, $academic_year);
            break;
        case 'improvement-analysis':
            $response = getImprovementAnalysis($conn, $school_id, $class_id, $stream_id, $exam_id, $term_id, $academic_year);
            break;
        case 'gender-analysis':
            $response = getGenderAnalysis($conn, $school_id, $class_id, $stream_id, $exam_id, $term_id, $academic_year);
            break;
        default:
            $response = ['success' => false, 'message' => 'Invalid view'];
    }
} catch (Exception $e) {
    $response = ['success' => false, 'message' => $e->getMessage()];
}

$conn->close();
echo json_encode($response);

// Add the specific functions here for each analytics view
function getLearningAreaAnalysis($conn, $school_id, $class_id, $stream_id, $exam_id, $term_id, $academic_year) {
    // Query to get subject-wise performance by stream
    $data = [];
    $summary = [];
    
    // Get all subjects for this class
    $subjectsQuery = $conn->prepare("
        SELECT DISTINCT s.id, s.subject_name, s.subject_code, 
               COALESCE(CONCAT(t.firstname, ' ', t.lastname), 'No Teacher Assigned') as teacher_name
        FROM tblsubjects s
        LEFT JOIN tblteachers t ON s.teacher_id = t.id
        WHERE s.class_id = ? AND s.school_id = ?
        ORDER BY s.subject_name
    ");
    $subjectsQuery->bind_param("ii", $class_id, $school_id);
    $subjectsQuery->execute();
    $subjectsResult = $subjectsQuery->get_result();
    
    $total_mean_sum = 0;
    $subject_count = 0;
    $total_rubric_sum = 0;
    
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
        
        $stream_count = $streamsResult->num_rows;
        $stream_index = 0;
        
        $subject_total_ee = 0;
        $subject_total_me = 0;
        $subject_total_ae = 0;
        $subject_total_ap = 0;
        $subject_total_be = 0;
        $subject_total_x = 0;
        $subject_total_mean = 0;
        $subject_total_rubric = 0;
        $subject_stream_count = 0;
        
        while ($stream = $streamsResult->fetch_assoc()) {
            $stream_index++;
            
            // Get scores for this subject and stream
            $scoresQuery = $conn->prepare("
                SELECT 
                    COUNT(*) as total_students,
                    SUM(CASE WHEN grade = 'EE' THEN 1 ELSE 0 END) as ee_count,
                    SUM(CASE WHEN grade = 'ME' THEN 1 ELSE 0 END) as me_count,
                    SUM(CASE WHEN grade = 'AE' THEN 1 ELSE 0 END) as ae_count,
                    SUM(CASE WHEN grade = 'AP' THEN 1 ELSE 0 END) as ap_count,
                    SUM(CASE WHEN grade = 'BE' THEN 1 ELSE 0 END) as be_count,
                    SUM(CASE WHEN grade = 'X' OR grade IS NULL THEN 1 ELSE 0 END) as x_count,
                    AVG(percentage) as mean_score,
                    AVG(rubric) as avg_rubric
                FROM tblscores s
                JOIN tblstudents st ON s.student_id = st.id
                WHERE s.subject_id = ? AND s.exam_id = ? AND s.class_id = ? 
                    AND st.stream_id = ? AND s.school_id = ?
            ");
            $scoresQuery->bind_param("iiiii", $subject['id'], $exam_id, $class_id, $stream['id'], $school_id);
            $scoresQuery->execute();
            $scoresResult = $scoresQuery->get_result();
            $scores = $scoresResult->fetch_assoc();
            
            $mean_score = $scores['mean_score'] ?? 0;
            $avg_rubric = $scores['avg_rubric'] ?? 0;
            
            // Get previous term data for comparison
            $prevQuery = $conn->prepare("
                SELECT AVG(percentage) as prev_mean
                FROM tblscores
                WHERE subject_id = ? AND exam_id < ? AND class_id = ? 
                    AND student_id IN (SELECT id FROM tblstudents WHERE stream_id = ? AND school_id = ?)
                GROUP BY subject_id
                LIMIT 1
            ");
            $prevQuery->bind_param("iiiii", $subject['id'], $exam_id, $class_id, $stream['id'], $school_id);
            $prevQuery->execute();
            $prevResult = $prevQuery->get_result();
            $prevData = $prevResult->fetch_assoc();
            $prev_mean = $prevData['prev_mean'] ?? null;
            
            $change = ($prev_mean !== null && $prev_mean > 0) ? $mean_score - $prev_mean : null;
            
            $row = [
                'subject_name' => $subject['subject_name'],
                'subject_code' => $subject['subject_code'],
                'stream_name' => $stream['stream_name'],
                'ee_count' => $scores['ee_count'] ?? 0,
                'me_count' => $scores['me_count'] ?? 0,
                'ae_count' => $scores['ae_count'] ?? 0,
                'ap_count' => $scores['ap_count'] ?? 0,
                'be_count' => $scores['be_count'] ?? 0,
                'x_count' => $scores['x_count'] ?? 0,
                'mean' => $mean_score,
                'avg_rubric' => $avg_rubric,
                'previous_mean' => $prev_mean,
                'change' => $change,
                'teacher_name' => $subject['teacher_name'],
                'is_last_stream' => ($stream_index == $stream_count)
            ];
            
            // Accumulate totals for this subject
            $subject_total_ee += $scores['ee_count'] ?? 0;
            $subject_total_me += $scores['me_count'] ?? 0;
            $subject_total_ae += $scores['ae_count'] ?? 0;
            $subject_total_ap += $scores['ap_count'] ?? 0;
            $subject_total_be += $scores['be_count'] ?? 0;
            $subject_total_x += $scores['x_count'] ?? 0;
            $subject_total_mean += $mean_score;
            $subject_total_rubric += $avg_rubric;
            $subject_stream_count++;
            
            $data[] = $row;
            
            // Add total row for this subject after last stream
            if ($stream_index == $stream_count) {
                $total_mean = $subject_stream_count > 0 ? $subject_total_mean / $subject_stream_count : 0;
                $total_avg_rubric = $subject_stream_count > 0 ? $subject_total_rubric / $subject_stream_count : 0;
                
                $total_row = [
                    'subject_name' => $subject['subject_name'],
                    'subject_code' => $subject['subject_code'],
                    'stream_name' => 'Total',
                    'total_ee' => $subject_total_ee,
                    'total_me' => $subject_total_me,
                    'total_ae' => $subject_total_ae,
                    'total_ap' => $subject_total_ap,
                    'total_be' => $subject_total_be,
                    'total_x' => $subject_total_x,
                    'total_mean' => $total_mean,
                    'total_rubric' => $subject_total_rubric,
                    'total_avg_rubric' => $total_avg_rubric,
                    'is_last_stream' => true
                ];
                $data[] = $total_row;
                
                $total_mean_sum += $total_mean;
                $total_rubric_sum += $total_avg_rubric;
                $subject_count++;
            }
        }
        $streamsQuery->close();
    }
    $subjectsQuery->close();
    
    $summary = [
        'overall_mean' => $subject_count > 0 ? $total_mean_sum / $subject_count : 0,
        'avg_rubric' => $subject_count > 0 ? $total_rubric_sum / $subject_count : 0,
        'teacher_count' => $subject_count,
        'subject_count' => $subject_count
    ];
    
    return ['success' => true, 'data' => $data, 'summary' => $summary];
}

// Add other functions similarly...
?>