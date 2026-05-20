<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set a custom error handler to catch PHP errors and output JSON
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return;
    }
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => "PHP Error: " . $message . " in " . $file . " on line " . $line,
        'type' => 'php_error',
        'severity' => $severity,
        'file' => $file,
        'line' => $line
    ]);
    exit();
});

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

// Get the raw POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    $response['message'] = "Invalid JSON input: " . json_last_error_msg();
    echo json_encode($response);
    exit();
}

try {
    if (!file_exists('../includes/config.php')) {
        throw new Exception("Configuration file not found at ../includes/config.php. Please check the path.");
    }
    require_once('../includes/config.php');

    if (!isset($dbh)) {
        throw new Exception("Database connection (dbh) not established in config.php.");
    }

    // Validate and sanitize incoming data
    $schoolId = isset($data['school_id']) ? intval($data['school_id']) : 0;
    $generatedBy = isset($data['generated_by']) ? intval($data['generated_by']) : 0;
    $reportTitle = isset($data['report_title']) ? trim($data['report_title']) : '';
    $classId = isset($data['class_id']) ? intval($data['class_id']) : 0;
    $termId = isset($data['term_id']) ? intval($data['term_id']) : 0;
    $year = isset($data['year']) ? intval($data['year']) : 0;
    $computationMethod = isset($data['computation_method']) ? trim($data['computation_method']) : '';
    $totalLearningAreas = isset($data['total_learning_areas']) ? intval($data['total_learning_areas']) : 0;
    $examIds = isset($data['exam_ids']) ? intval($data['exam_ids']) : 0; // Assuming single exam ID for now
    $streamId = isset($data['stream_id']) ? (intval($data['stream_id']) === 0 ? null : intval($data['stream_id'])) : null;
    $rankStudents = isset($data['rank_students']) ? intval($data['rank_students']) : 0;
    $rankingType = isset($data['ranking_type']) ? trim($data['ranking_type']) : null;

    // Fetch report_term_details from the frontend's term dropdown text
    // This is a bit of a hack, ideally term details are stored in a terms table
    $reportTermDetails = '';
    switch ($termId) {
        case 1: $reportTermDetails = 'Term 1, ' . $year; break;
        case 2: $reportTermDetails = 'Term 2, ' . $year; break;
        case 3: $reportTermDetails = 'Term 3, ' . $year; break;
        default: $reportTermDetails = 'Unknown Term, ' . $year; break;
    }

    // Security check: Ensure the user generating the report is the logged-in user
    if (!isset($_SESSION['school_id']) || $_SESSION['school_id'] != $schoolId ||
        !isset($_SESSION['id']) || $_SESSION['id'] != $generatedBy) {
        throw new Exception("Security Error: Mismatch in session and provided user/school IDs. Session ID: " . ($_SESSION['id'] ?? 'N/A') . ", Provided ID: " . $generatedBy);
    }
    
    // --- NEW LOGGING FOR generated_by ---
    error_log("generate_report.php: Received generated_by: " . $generatedBy . " for school_id: " . $schoolId);
    // --- END NEW LOGGING ---

    if (empty($reportTitle) || $classId === 0 || $termId === 0 || $year === 0 || empty($computationMethod) || $totalLearningAreas === 0 || $examIds === 0 || $generatedBy === 0) {
        throw new Exception("Missing or invalid required parameters for report generation. Please check all fields.");
    }

    // Insert into tblreportconfigurations
    $sql = "INSERT INTO tblreportconfigurations (school_id, generated_by, report_title, class_id, period, report_year, computation_method, total_learning_areas, exam_id, stream_id, ranking_option, batch_status, report_term_details)
            VALUES (:school_id, :generated_by, :report_title, :class_id, :period, :report_year, :computation_method, :total_learning_areas, :exam_id, :stream_id, :ranking_option, 'pending', :report_term_details)";

    $query = $dbh->prepare($sql);
    $query->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
    $query->bindParam(':generated_by', $generatedBy, PDO::PARAM_INT);
    $query->bindParam(':report_title', $reportTitle, PDO::PARAM_STR);
    $query->bindParam(':class_id', $classId, PDO::PARAM_INT);
    $query->bindParam(':period', $termId, PDO::PARAM_INT); // Using termId for 'period'
    $query->bindParam(':report_year', $year, PDO::PARAM_INT);
    $query->bindParam(':computation_method', $computationMethod, PDO::PARAM_STR);
    $query->bindParam(':total_learning_areas', $totalLearningAreas, PDO::PARAM_INT);
    $query->bindParam(':exam_id', $examIds, PDO::PARAM_INT);
    $query->bindParam(':stream_id', $streamId, PDO::PARAM_INT);
    $query->bindParam(':ranking_option', $rankingType, PDO::PARAM_STR);
    $query->bindParam(':report_term_details', $reportTermDetails, PDO::PARAM_STR);

    $query->execute();

    if ($query->rowCount() > 0) {
        $response['success'] = true;
        $response['message'] = "Report generation request submitted successfully. Report ID: " . $dbh->lastInsertId();
    } else {
        $response['message'] = "Failed to insert report configuration.";
    }

} catch (PDOException $e) {
    $response['message'] = "Database Error: " . $e->getMessage();
    error_log("generate_report.php: Database Error: " . $e->getMessage());
} catch (Exception $e) {
    $response['message'] = "Error: " . $e->getMessage();
    error_log("generate_report.php: Application Error: " . $e->getMessage());
}

echo json_encode($response);
exit();
?>