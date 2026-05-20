<?php
// Start the session
session_start();

// Enable error reporting for debugging (keep this ON during development)
error_reporting(E_ALL);
ini_set('display_errors', 1);

include('../includes/config.php'); // Adjust path as necessary

header('Content-Type: application/json');

$response = ["success" => false, "message" => "", "error" => ""];

// Get the JSON data from the request body
$json_data = file_get_contents('php://input');
$gradeData = json_decode($json_data, true); // This will be a single grade object

if (json_last_error() !== JSON_ERROR_NONE) {
    $response['error'] = 'Invalid JSON received for grade update: ' . json_last_error_msg();
    echo json_encode($response);
    exit();
}

// Extract all necessary fields for update, including the 'id'
$gradeId = $gradeData['id'] ?? null;
$classId = $gradeData['class_id'] ?? null;
$streamId = $gradeData['stream_id'] ?? null;
$subjectId = $gradeData['subject_id'] ?? null;
$grade = $gradeData['grade'] ?? '';
$lower_limit = (int)($gradeData['lower_limit'] ?? 0);
$upper_limit = (int)($gradeData['upper_limit'] ?? 0);
$points = (int)($gradeData['points'] ?? 0);
$remarks = $gradeData['remarks'] ?? '';
$grade_alias = $gradeData['grade_alias'] ?? '';
$principal_remarks = $gradeData['principal_remarks'] ?? '';

// Basic validation for essential fields
if (!$gradeId || !$classId || !$streamId || !$subjectId) {
    $response['error'] = "Missing Grade ID, Class ID, Stream ID, or Subject ID for update.";
    echo json_encode($response);
    exit();
}

try {
    // SQL for updating an existing record by its ID
    $sql = "UPDATE tblsubjectgrading SET
                class_id = :class_id,
                subject_id = :subject_id,
                stream_id = :stream_id,
                grade = :grade,
                lower_limit = :lower_limit,
                upper_limit = :upper_limit,
                points = :points,
                remarks = :remarks,
                grade_alias = :grade_alias,
                principal_remarks = :principal_remarks
            WHERE id = :id"; // TARGET SPECIFIC ID

    $query = $dbh->prepare($sql);

    // Bind all parameters
    $query->bindParam(':id', $gradeId, PDO::PARAM_INT);
    $query->bindParam(':class_id', $classId, PDO::PARAM_INT);
    $query->bindParam(':subject_id', $subjectId, PDO::PARAM_INT);
    $query->bindParam(':stream_id', $streamId, PDO::PARAM_INT);
    $query->bindParam(':grade', $grade, PDO::PARAM_STR);
    $query->bindParam(':lower_limit', $lower_limit, PDO::PARAM_INT);
    $query->bindParam(':upper_limit', $upper_limit, PDO::PARAM_INT);
    $query->bindParam(':points', $points, PDO::PARAM_INT);
    $query->bindParam(':remarks', $remarks, PDO::PARAM_STR);
    $query->bindParam(':grade_alias', $grade_alias, PDO::PARAM_STR);
    $query->bindParam(':principal_remarks', $principal_remarks, PDO::PARAM_STR);

    $query->execute();

    if ($query->rowCount() > 0) {
        $response['success'] = true;
        $response['message'] = "Grade entry updated successfully.";
    } else {
        // If rowCount is 0, it means no row was updated. This could be because
        // the ID didn't exist, or the data sent was identical to existing data.
        $response['success'] = true; // Still consider it a success if no change was needed
        $response['message'] = "Grade entry found, but no changes were made.";
    }

} catch (PDOException $e) {
    $response['error'] = "Database error during update: " . $e->getMessage();
    error_log("PDO Error in update_grade_entry.php: " . $e->getMessage());
} catch (Exception $e) {
    $response['error'] = "General error during update: " . $e->getMessage();
    error_log("General Error in update_grade_entry.php: " . $e->getMessage());
}

echo json_encode($response);
exit();
?>