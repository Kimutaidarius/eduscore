<?php
// ajax/filter_reports.php
session_start();
require_once '../config/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    // Get JSON data
    $json_data = file_get_contents('php://input');
    $data = json_decode($json_data, true);
    
    $school_id = $_SESSION['school_id'];
    $academic_level = isset($_SESSION['academic_level']) ? $_SESSION['academic_level'] : null;
    
    // Start building the query
    $query = "SELECT 
                rc.id,
                rc.report_title,
                rc.class_id,
                rc.stream_id,
                rc.exam_id,
                rc.term_id,
                c.class_level as class_name,
                s.stream_name,
                e.examname,
                t.term_name,
                rc.report_year,
                rc.total_learning_areas as learning_areas,
                rc.ranking_option,
                rc.batch_status as status,
                rc.created_at,
                rc.updated_at
              FROM tblreportconfigurations rc
              LEFT JOIN tblclasses c ON rc.class_id = c.id
              LEFT JOIN tblstreams s ON rc.stream_id = s.id
              LEFT JOIN tblexam e ON rc.exam_id = e.id
              LEFT JOIN tblterms t ON rc.term_id = t.id
              WHERE rc.school_id = :school_id";
    
    $params = [':school_id' => $school_id];
    
    // Add academic level filter if set
    if ($academic_level && $academic_level != 'all') {
        $level_mapping = [
            'primary' => ['Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6', 'Grade 7', 'Grade 8'],
            'junior_secondary' => ['Form 1', 'Form 2', 'Form 3'],
            'senior_secondary' => ['Form 4', 'Form 5', 'Form 6'],
            'college' => ['Year 1', 'Year 2', 'Year 3', 'Year 4']
        ];
        
        $class_levels = $level_mapping[$academic_level] ?? [];
        
        if (!empty($class_levels)) {
            $placeholders = [];
            foreach ($class_levels as $index => $level) {
                $placeholder = ":class_level_$index";
                $placeholders[] = $placeholder;
                $params[$placeholder] = $level;
            }
            
            $query .= " AND c.class_level IN (" . implode(',', $placeholders) . ")";
        }
    }
    
    // Add other filters from the JSON data
    if (!empty($data['class_id'])) {
        $query .= " AND rc.class_id = :class_id";
        $params[':class_id'] = $data['class_id'];
    }
    
    if (!empty($data['stream_id'])) {
        $query .= " AND rc.stream_id = :stream_id";
        $params[':stream_id'] = $data['stream_id'];
    }
    
    if (!empty($data['exam_id'])) {
        $query .= " AND rc.exam_id = :exam_id";
        $params[':exam_id'] = $data['exam_id'];
    }
    
    if (!empty($data['term_id'])) {
        $query .= " AND rc.term_id = :term_id";
        $params[':term_id'] = $data['term_id'];
    }
    
    if (!empty($data['year'])) {
        $query .= " AND rc.report_year = :year";
        $params[':year'] = $data['year'];
    }
    
    $query .= " ORDER BY rc.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate mean grade for each report
    foreach ($reports as &$report) {
        $gradeQuery = "SELECT AVG(mean_score) as avg_score 
                      FROM report_cards 
                      WHERE school_id = :school_id 
                      AND class_id = :class_id 
                      AND exam_id = :exam_id";
        
        $gradeParams = [
            ':school_id' => $school_id,
            ':class_id' => $report['class_id'],
            ':exam_id' => $report['exam_id']
        ];
        
        if (!empty($report['stream_id'])) {
            $gradeQuery .= " AND stream_id = :stream_id";
            $gradeParams[':stream_id'] = $report['stream_id'];
        }
        
        $gradeStmt = $db->prepare($gradeQuery);
        $gradeStmt->execute($gradeParams);
        $avgResult = $gradeStmt->fetch(PDO::FETCH_ASSOC);
        
        $report['mean_grade'] = ($avgResult && $avgResult['avg_score'] !== null) ? 
            calculateGradeFromScore($avgResult['avg_score']) : 'N/A';
    }
    
    // Always return a consistent structure
    $response = [
        'success' => true,
        'count' => count($reports),
        'reports' => $reports
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch(PDOException $e) {
    error_log("Database Error in filter_reports.php: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'error' => 'Database error: ' . $e->getMessage(),
        'reports' => []
    ]);
} catch(Exception $e) {
    error_log("General Error in filter_reports.php: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'error' => 'Error: ' . $e->getMessage(),
        'reports' => []
    ]);
}

function calculateGradeFromScore($score) {
    if ($score >= 80) return 'A';
    if ($score >= 70) return 'B';
    if ($score >= 60) return 'C';
    if ($score >= 50) return 'D';
    if ($score >= 40) return 'E';
    return 'F';
}
?>