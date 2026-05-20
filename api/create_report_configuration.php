<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include('../includes/config.php'); // Corrected path

header('Content-Type: application/json');

$response = ["success" => false, "message" => ""];

if (!isset($dbh) || !($dbh instanceof PDO)) {
    $response["message"] = "Database connection failed. Ensure PDO connection is established.";
    echo json_encode($response);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

$report_title = isset($input['report_title']) ? trim($input['report_title']) : '';
$period = isset($input['period']) ? trim($input['period']) : '';
$class_id = isset($input['class_id']) && $input['class_id'] !== '' ? intval($input['class_id']) : null; // Use null for 'All Classes'
$stream_id = isset($input['stream_id']) && $input['stream_id'] !== '' ? intval($input['stream_id']) : null; // Use null for 'All Streams'
$exam_ids = isset($input['exam_ids']) ? trim($input['exam_ids']) : ''; // Stored as string for now
$computation_method = isset($input['computation_method']) ? trim($input['computation_method']) : '';
$ranking_option = isset($input['ranking_option']) ? trim($input['ranking_option']) : '';
$total_learning_areas = isset($input['total_learning_areas']) ? intval($input['total_learning_areas']) : 0;
$generated_by_teacher_id = isset($input['generated_by_teacher_id']) ? intval($input['generated_by_teacher_id']) : null;


if (empty($report_title) || empty($period) || empty($exam_ids) || empty($computation_method) || empty($ranking_option) || $total_learning_areas <= 0) {
    $response["message"] = "Required fields are missing or invalid (Report Title, Period, Exams Included, Computation, Ranking, Total L. Areas).";
    echo json_encode($response);
    exit();
}

try {
    $sql = "INSERT INTO tblreportconfigurations (
                report_title, period, class_id, stream_id, exam_ids, 
                computation_method, ranking_option, total_learning_areas, 
                generation_status, status_message, generated_by_teacher_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $dbh->prepare($sql);
    
    $stmt->bindValue(1, $report_title, PDO::PARAM_STR);
    $stmt->bindValue(2, $period, PDO::PARAM_STR);
    $stmt->bindValue(3, $class_id, PDO::PARAM_INT); // PDO handles null for PARAM_INT
    $stmt->bindValue(4, $stream_id, PDO::PARAM_INT); // PDO handles null for PARAM_INT
    $stmt->bindValue(5, $exam_ids, PDO::PARAM_STR);
    $stmt->bindValue(6, $computation_method, PDO::PARAM_STR);
    $stmt->bindValue(7, $ranking_option, PDO::PARAM_STR);
    $stmt->bindValue(8, $total_learning_areas, PDO::PARAM_INT);
    $stmt->bindValue(9, 'Pending', PDO::PARAM_STR); // Initial status
    $stmt->bindValue(10, 'Report generation pending.', PDO::PARAM_STR); // Initial message
    $stmt->bindValue(11, $generated_by_teacher_id, PDO::PARAM_INT);

    if ($stmt->execute()) {
        $response["success"] = true;
        $response["message"] = "Report configuration created successfully!";
    } else {
        $errorInfo = $stmt->errorInfo();
        $response["message"] = "Failed to create report configuration: " . ($errorInfo[2] ?? 'Unknown error');
    }

} catch (PDOException $e) {
    $response["message"] = "Database error: " . $e->getMessage();
    error_log("PDO Error in api/create_report_configuration.php: " . $e->getMessage());
} catch (Exception $e) {
    $response["message"] = "An unexpected server error occurred: " . $e->getMessage();
    error_log("General Error in api/create_report_configuration.php: " . $e->getMessage());
} finally {
    $stmt = null;
}

echo json_encode($response);
exit();