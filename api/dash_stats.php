<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session and include config
require_once '../config/config.php';

// Set content type to JSON
header('Content-Type: application/json');

// Enhanced database connection check with fallback
if (!isset($db) || $db === null) {
    try {
        require_once '../config/database.php';
        if (class_exists('Database')) {
            $database = new Database();
            $db = $database->getConnection();
        }
    } catch (Exception $e) {
        error_log("Dashboard API database reinitialization failed: " . $e->getMessage());
        echo json_encode(['error' => 'Database connection failed']);
        exit;
    }
}

// Final check - if still no connection, show error
if (!isset($db) || $db === null) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Enhanced security: Check if user is properly authenticated
if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Enhanced security: Validate session variables
if (!isset($_SESSION['school_id']) || !isset($_SESSION['id'])) {
    echo json_encode(['error' => 'Invalid session']);
    exit;
}

try {
    // Initialize stats
    $stats = [
        'total_students' => 0,
        'total_teachers' => 0,
        'total_classes' => 0,
        'male_students' => 0,
        'female_students' => 0,
        'male_teachers' => 0,
        'female_teachers' => 0,
        'subscription' => ['license_tier' => 'Basic', 'status' => 'pending']
    ];

    // Get school information
    $query = "SELECT school_name, license_tier, status FROM tblschoolinfo WHERE id = :school_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":school_id", $_SESSION['school_id'], PDO::PARAM_INT);
    $stmt->execute();
    $school_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($school_info) {
        $stats['subscription']['license_tier'] = $school_info['license_tier'];
        $stats['subscription']['status'] = $school_info['status'];
    }

    // Total Active Students with Gender Breakdown
    $query = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN Gender = 'Male' THEN 1 ELSE 0 END) as male_count,
                SUM(CASE WHEN Gender = 'Female' THEN 1 ELSE 0 END) as female_count
              FROM tblstudents 
              WHERE school_id = :school_id 
              AND Status = 'Active'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":school_id", $_SESSION['school_id'], PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        $stats['total_students'] = $result['total'];
        $stats['male_students'] = $result['male_count'];
        $stats['female_students'] = $result['female_count'];
    }

    // Total Teachers with Gender Breakdown
    $query = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN gender = 'Male' THEN 1 ELSE 0 END) as male_count,
                SUM(CASE WHEN gender = 'Female' THEN 1 ELSE 0 END) as female_count
              FROM tblteachers 
              WHERE school_id = :school_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":school_id", $_SESSION['school_id'], PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        $stats['total_teachers'] = $result['total'];
        $stats['male_teachers'] = $result['male_count'];
        $stats['female_teachers'] = $result['female_count'];
    }

    // Total Classes
    $query = "SELECT COUNT(*) as total FROM tblclasses WHERE school_id = :school_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":school_id", $_SESSION['school_id'], PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_classes'] = $result ? $result['total'] : 0;

    // Recent Events (next 30 days)
    $query = "SELECT title, event_date, event_time, description 
              FROM tblevents 
              WHERE school_id = :school_id 
              AND event_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
              ORDER BY event_date ASC, event_time ASC 
              LIMIT 5";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":school_id", $_SESSION['school_id'], PDO::PARAM_INT);
    $stmt->execute();
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Recent Activity (last 7 days)
    $query = "SELECT 
                s.FirstName, 
                s.LastName, 
                s.Gender as student_gender,
                sub.subject_name, 
                sc.score_value, 
                sc.exam_type,
                sc.recorded_at 
              FROM tblscores sc 
              JOIN tblstudents s ON sc.student_id = s.id 
              JOIN tblsubjects sub ON sc.subject_id = sub.id 
              WHERE sc.school_id = :school_id 
              AND sc.recorded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
              ORDER BY sc.recorded_at DESC 
              LIMIT 8";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":school_id", $_SESSION['school_id'], PDO::PARAM_INT);
    $stmt->execute();
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Top Performing Students with Gender
    $query = "SELECT 
                s.FirstName, 
                s.LastName,
                s.Gender,
                s.AdmissionNo,
                ROUND(AVG(sc.score_value), 1) as avg_score,
                COUNT(sc.id) as total_exams
              FROM tblscores sc 
              JOIN tblstudents s ON sc.student_id = s.id 
              WHERE sc.school_id = :school_id 
              AND sc.recorded_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
              GROUP BY s.id 
              HAVING total_exams >= 3
              ORDER BY avg_score DESC 
              LIMIT 6";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":school_id", $_SESSION['school_id'], PDO::PARAM_INT);
    $stmt->execute();
    $top_students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Performance Data for Chart
    $query = "SELECT 
                DATE_FORMAT(sc.recorded_at, '%Y-%m') as month,
                ROUND(AVG(sc.score_value), 1) as avg_score,
                COUNT(DISTINCT s.id) as students_count
              FROM tblscores sc 
              JOIN tblstudents s ON sc.student_id = s.id 
              WHERE sc.school_id = :school_id 
              AND sc.recorded_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
              GROUP BY DATE_FORMAT(sc.recorded_at, '%Y-%m')
              ORDER BY month ASC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":school_id", $_SESSION['school_id'], PDO::PARAM_INT);
    $stmt->execute();
    $performance_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Prepare response
    $response = [
        'success' => true,
        'stats' => $stats,
        'events' => $events,
        'activities' => $activities,
        'top_students' => $top_students,
        'performance_data' => $performance_data,
        'school_name' => $_SESSION['school_name'],
        'user_name' => $_SESSION['user_fullname'] ?? $_SESSION['user_name']
    ];

    echo json_encode($response);

} catch(PDOException $e) {
    error_log("Dashboard API error: " . $e->getMessage());
    echo json_encode(['error' => 'Failed to fetch dashboard data']);
}
?>