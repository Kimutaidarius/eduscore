<?php
session_start(); // Start the session at the very beginning
error_reporting(E_ALL);
ini_set('display_errors', 1);

$script_version = "V1.2.4"; 

set_error_handler(function ($severity, $message, $file, $line) use ($script_version) {
    if (!(error_reporting() & $severity)) return;
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => "PHP Error ($script_version): $message in $file on line $line",
        'type' => 'php_error',
        'severity' => $severity,
        'file' => $file,
        'line' => $line
    ]);
    exit();
});

header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => '',
    'report_batch_id' => null
];

// Use $_POST because form submits urlencoded form data (not JSON)
$data = $_POST;

// Ensure exam_ids is an array
if (!empty($_POST['exam_ids']) && is_array($_POST['exam_ids'])) {
    $data['exam_ids'] = $_POST['exam_ids'];
} else {
    $data['exam_ids'] = [];
}

error_log("generate_report.php ($script_version START): Received POST data: " . json_encode($data));

try {
    if (!file_exists('../includes/config.php')) {
        throw new Exception("Configuration file not found at ../includes/config.php. Please check the path.");
    }
    require_once('../includes/config.php');

    if (!isset($dbh)) {
        throw new Exception("Database connection (dbh) not established in config.php.");
    }
    error_log("generate_report.php ($script_version): Database connection (dbh) established.");

    // Validate session IDs
    if (!isset($_SESSION['school_id']) || !is_numeric($_SESSION['school_id'])) {
        throw new Exception("Security Error: School ID missing or invalid in session.");
    }
    $schoolId = intval($_SESSION['school_id']);

    if (!isset($_SESSION['id']) || !is_numeric($_SESSION['id'])) {
        throw new Exception("Security Error: User ID missing or invalid in session.");
    }
    $generatedByUserId = intval($_SESSION['id']);

    // Validate required fields
    $requiredFields = [
    'class_id', 'term_id', 'year', 'exam_ids'
];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || (empty($data[$field]) && $data[$field] !== '0' && $data[$field] !== 0)) {
            throw new Exception("Missing or invalid required field: $field");
        }
    }

    if (empty($data['exam_ids'])) {
        throw new Exception("No exam IDs selected.");
    }

    // Sanitize inputs
    $reportTitle = isset($data['report_title']) ? filter_var($data['report_title'], FILTER_SANITIZE_SPECIAL_CHARS) : '';
    $classId = intval($data['class_id']);
    $period = intval($data['term_id']);
    $reportYear = intval($data['year']);
    $computationMethod = filter_var($data['computation_method'], FILTER_SANITIZE_SPECIAL_CHARS);
    $totalLearningAreas = intval($data['total_learning_areas']);
    $streamId = isset($data['stream_id']) && $data['stream_id'] !== '' ? intval($data['stream_id']) : null;
    $rankingOption = isset($data['ranking_type']) ? filter_var($data['ranking_type'], FILTER_SANITIZE_SPECIAL_CHARS) : null;

    $reportTermDetails = "Term $period, $reportYear";

    // Prepare insert statement once
    $sql = "INSERT INTO tblreportconfigurations (
                school_id, generated_by, report_title, class_id, period, report_year,
                computation_method, total_learning_areas, exam_id, stream_id,
                ranking_option, batch_status, report_term_details, report_files_json
            ) VALUES (
                :school_id, :generated_by_user_id, :report_title, :class_id, :period, :report_year,
                :computation_method, :total_learning_areas, :exam_id, :stream_id,
                :ranking_option, 'pending', :report_term_details, :report_files_json
            )";
    $query = $dbh->prepare($sql);

    $emptyJsonArray = '[]';

    $lastInsertId = null;
    $insertCount = 0;

    // Loop through exam IDs to insert multiple rows
    foreach ($data['exam_ids'] as $examIdRaw) {
        $examId = intval($examIdRaw);
        if ($examId <= 0) continue; // skip invalid exam ids

        $params = [
            ':school_id' => $schoolId,
            ':generated_by_user_id' => $generatedByUserId,
            ':report_title' => $reportTitle,
            ':class_id' => $classId,
            ':period' => $period,
            ':report_year' => $reportYear,
            ':computation_method' => $computationMethod,
            ':total_learning_areas' => $totalLearningAreas,
            ':exam_id' => $examId,
            ':stream_id' => $streamId,
            ':ranking_option' => $rankingOption,
            ':report_term_details' => $reportTermDetails,
            ':report_files_json' => $emptyJsonArray
        ];

        $query->bindParam(':school_id', $params[':school_id'], PDO::PARAM_INT);
        $query->bindParam(':generated_by_user_id', $params[':generated_by_user_id'], PDO::PARAM_INT);
        $query->bindParam(':report_title', $params[':report_title'], PDO::PARAM_STR);
        $query->bindParam(':class_id', $params[':class_id'], PDO::PARAM_INT);
        $query->bindParam(':period', $params[':period'], PDO::PARAM_INT);
        $query->bindParam(':report_year', $params[':report_year'], PDO::PARAM_INT);
        $query->bindParam(':computation_method', $params[':computation_method'], PDO::PARAM_STR);
        $query->bindParam(':total_learning_areas', $params[':total_learning_areas'], PDO::PARAM_INT);
        $query->bindParam(':exam_id', $params[':exam_id'], PDO::PARAM_INT);
        if ($params[':stream_id'] === null) {
            $query->bindValue(':stream_id', null, PDO::PARAM_NULL);
        } else {
            $query->bindParam(':stream_id', $params[':stream_id'], PDO::PARAM_INT);
        }
        $query->bindParam(':ranking_option', $params[':ranking_option'], PDO::PARAM_STR);
        $query->bindParam(':report_term_details', $params[':report_term_details'], PDO::PARAM_STR);
        $query->bindParam(':report_files_json', $params[':report_files_json'], PDO::PARAM_STR);

        $executionResult = $query->execute();

        if (!$executionResult) {
            $errorInfo = $query->errorInfo();
            throw new Exception("Failed to insert report batch. PDO ErrorInfo: " . json_encode($errorInfo));
        }

        $lastInsertId = $dbh->lastInsertId();
        $insertCount++;
    }

    if ($insertCount > 0) {
        $response['report_batch_id'] = $lastInsertId; // last inserted batch id
        $response['success'] = true;
        $response['message'] = "Report batch generation request submitted successfully for $insertCount exam(s) ($script_version).";
        error_log("generate_report.php ($script_version END): Successfully inserted $insertCount report batch(es). Last ID: $lastInsertId");
    } else {
        throw new Exception("No valid exam IDs processed for insertion.");
    }

} catch (PDOException $e) {
    $response['message'] = "Database Error ($script_version): " . $e->getMessage();
    error_log("generate_report.php ($script_version): PDOException: " . $e->getMessage() . " SQLSTATE: " . $e->getCode());
} catch (Exception $e) {
    $response['message'] = "Error ($script_version): " . $e->getMessage();
    error_log("generate_report.php ($script_version): Exception: " . $e->getMessage());
}

echo json_encode($response);
exit();
?>
