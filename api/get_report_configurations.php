<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Corrected path to config.php assuming this file is in 'api/' and config.php is in 'includes/'
include('../includes/config.php');

header('Content-Type: application/json');

$response = ["success" => false, "reports" => [], "message" => ""];

// Check if PDO database handle is set and is an instance of PDO
if (!isset($dbh) || !($dbh instanceof PDO)) {
    $response["message"] = "Database connection failed. Ensure PDO connection is established in config.php.";
    echo json_encode($response);
    exit();
}

try {
    // SQL query to select report configurations and join related tables
    // - `tblreportconfigurations` aliased as `rc`
    // - `tblclasses` aliased as `c`
    // - `tblstreams` aliased as `s`
    // - `tblteachers` aliased as `t` - using CONCAT for teacher's full name
    // - `tblexams` aliased as `e`
    $sql = "SELECT 
                rc.id, 
                rc.report_title, 
                rc.period, 
                rc.computation_method, 
                rc.exam_id,                  -- Correct column name
                rc.total_learning_areas, 
                rc.ranking_option, 
                rc.generation_status, 
                rc.status_message, 
                rc.report_file_path,
                rc.created_at,               -- Correct timestamp column name
                c.class_level,
                s.stream_name,
                e.examname,                  -- Get exam name from tblexams
                CONCAT(t.firstname, ' ', t.lastname) AS teacher_name -- Concatenate first and last name
            FROM tblreportconfigurations rc
            LEFT JOIN tblclasses c ON rc.class_id = c.id
            LEFT JOIN tblstreams s ON rc.stream_id = s.id
            LEFT JOIN tblteachers t ON rc.generated_by_teacher_id = t.id
            LEFT JOIN tblexam e ON rc.exam_id = e.id
            ORDER BY rc.created_at DESC"; // Order by most recent report first

    $stmt = $dbh->prepare($sql);
    $stmt->execute();
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Prepare reports data for frontend display
    foreach ($reports as &$report) {
        // Provide display names for joined data
        $report['class_display'] = $report['class_level'] ?? 'All Classes';
        $report['stream_display'] = $report['stream_name'] ?? 'All Streams';
        // Use the aliased 'teacher_name' directly
        $report['teacher_display_name'] = $report['teacher_name'] ?? 'Unknown Teacher'; 
        // Use examname from join result
        $report['exam_display_name'] = $report['examname'] ?? ($report['exam_id'] ? "ID: " . $report['exam_id'] : 'N/A');

        // Clean up raw IDs/names if not needed in the final JSON response, or keep them if useful
        unset($report['class_level']);
        unset($report['stream_name']);
        unset($report['examname']);
        unset($report['teacher_name']); // Unset the aliased name after using it for display
    }
    unset($report); // Unset the reference to the last element

    if ($stmt->rowCount() > 0) {
        $response["success"] = true;
        $response["reports"] = $reports;
        $response["message"] = "Report configurations fetched successfully.";
    } else {
        // It's still a success if no data is found, just indicate it with the message
        $response["success"] = true;
        $response["message"] = "No report configurations found.";
        $response["reports"] = [];
    }

} catch (PDOException $e) {
    // Catch PDO specific errors (database errors)
    $response["message"] = "Database error: " . $e->getMessage();
    error_log("PDO Error in api/get_report_configurations.php: " . $e->getMessage());
} catch (Exception $e) {
    // Catch any other unexpected errors
    $response["message"] = "An unexpected error occurred: " . $e->getMessage();
    error_log("General Error in api/get_report_configurations.php: " . $e->getMessage());
} finally {
    // Ensure the statement object is released
    if (isset($stmt) && $stmt instanceof PDOStatement) {
        $stmt = null;
    }
}

echo json_encode($response);
exit();

?>