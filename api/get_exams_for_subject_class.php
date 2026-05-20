<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include('../includes/config.php');

header('Content-Type: application/json');

$response = ["success" => false, "exams" => [], "message" => ""];

if (!isset($dbh) || !($dbh instanceof PDO)) {
    $response["message"] = "Database connection failed. Ensure PDO connection is established.";
    echo json_encode($response);
    exit();
}

$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
// Assuming exams are primarily linked to classes, not directly to subjects for selection
// If an exam must be associated with a specific subject, you'd need a linking table or subject_id in tblexam
// For now, this fetches all exams for the selected class.
// The subject_id passed from frontend is used in get_exam_total_score and save_student_scores for context.
$subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0; 

if ($class_id === 0) { // subject_id might not be strictly needed for fetching general exams
    $response["message"] = "Class ID is required.";
    echo json_encode($response);
    exit();
}

try {
    // Assuming tblexam is linked to class_id
    $sql = "SELECT id, examname FROM tblexam WHERE class_id = ? ORDER BY examname ASC";
    $stmt = $dbh->prepare($sql);
    $stmt->bindValue(1, $class_id, PDO::PARAM_INT);
    $stmt->execute();

    $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($stmt->rowCount() > 0) {
        $response["success"] = true;
        $response["exams"] = $exams;
        $response["message"] = "Exams fetched successfully.";
    } else {
        $response["success"] = true;
        $response["message"] = "No exams found for this class.";
        $response["exams"] = [];
    }

} catch (PDOException $e) {
    $response["message"] = "Database error: " . $e->getMessage();
    error_log("PDO Error in api/get_exams_for_subject_class.php: " . $e->getMessage());
} catch (Exception $e) {
    $response["message"] = "An unexpected error occurred: " . $e->getMessage();
    error_log("General Error in api/get_exams_for_subject_class.php: " . $e->getMessage());
} finally {
    $stmt = null;
}

echo json_encode($response);
exit();