<?php
// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// Include config
require_once '../includes/config.php';

try {
    // Initialize response array
    $response = [
        'success' => true,
        'data' => [
            'schools' => 0,
            'students' => 0,
            'teachers' => 0,
            'reports' => 0
        ]
    ];
    
    // Get total schools count (only approved/activated schools)
    $stmt = $dbh->prepare("SELECT COUNT(*) as total FROM tblschoolinfo WHERE is_activated = 1");
    $stmt->execute();
    $schools = $stmt->fetch(PDO::FETCH_ASSOC);
    $response['data']['schools'] = (int)$schools['total'];
    
    // Get total students count
    $stmt = $dbh->prepare("SELECT COUNT(*) as total FROM tblstudents WHERE Status = 'Active'");
    $stmt->execute();
    $students = $stmt->fetch(PDO::FETCH_ASSOC);
    $response['data']['students'] = (int)$students['total'];
    
    // Get total teachers count (active teachers)
    $stmt = $dbh->prepare("SELECT COUNT(*) as total FROM tblteachers WHERE status = 'Active' AND is_deleted = 0");
    $stmt->execute();
    $teachers = $stmt->fetch(PDO::FETCH_ASSOC);
    $response['data']['teachers'] = (int)$teachers['total'];
    
    // Get total reports count from merged_reports
    $stmt = $dbh->prepare("SELECT COUNT(*) as total FROM report_cards");
    $stmt->execute();
    $reports = $stmt->fetch(PDO::FETCH_ASSOC);
    $response['data']['reports'] = (int)$reports['total'];
    
    echo json_encode($response);
    exit;
    
} catch (PDOException $e) {
    error_log("Database error in get_stats.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred',
        'data' => ['schools' => 0, 'students' => 0, 'teachers' => 0, 'reports' => 0]
    ]);
    exit;
} catch (Exception $e) {
    error_log("Error in get_stats.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred',
        'data' => ['schools' => 0, 'students' => 0, 'teachers' => 0, 'reports' => 0]
    ]);
    exit;
}
?>