<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

include('../includes/config.php'); // Adjust path if necessary

$response = ["success" => false, "gradingScales" => [], "message" => ""];

// Authenticate user
if (!isset($_SESSION['id']) || empty($_SESSION['id']) || !isset($_SESSION['login']) || empty($_SESSION['login']) || !isset($_SESSION['school_id']) || empty($_SESSION['school_id'])) {
    $response['message'] = 'Authentication required. Please log in.';
    http_response_code(401); // Unauthorized
    echo json_encode($response);
    exit();
}

$school_id = $_SESSION['school_id'];

// Get class_id from GET request (mandatory)
$classId = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
// Get stream_id from GET request (optional, can be 'all' or null)
$streamId = isset($_GET['stream_id']) && $_GET['stream_id'] !== 'all' ? intval($_GET['stream_id']) : null;

if ($classId === 0) {
    $response['message'] = 'Class ID is required.';
    http_response_code(400); // Bad Request
    echo json_encode($response);
    exit();
}

try {
    // SQL query to select grading scales
    // Assuming 'overall_grading_scales' is your table name for this data
    // And columns are: id, class_id, stream_id, lower_limit, upper_limit, grade, grade_alias, rubric
$sql = "SELECT id, lower_limit, upper_limit_marks, grade, grade_points, grade_alias, class_teacher_remarks, principal_remark 
        FROM overall_grading_scales 
        WHERE class_id = :class_id AND school_id = :school_id";

    // Add stream_id condition if a specific stream is selected
    if ($streamId !== null) {
        $sql .= " AND stream_id = :stream_id";
    } else {
        // If streamId is null (meaning 'All Streams' or no stream selected),
        // ensure we only fetch records where stream_id is also NULL in the database.
        // This handles cases where a scale applies to a class generally, not a specific stream.
        $sql .= " AND stream_id IS NULL";
    }

    $sql .= " ORDER BY lower_limit ASC"; // Order by lower limit for logical display

    $query = $dbh->prepare($sql);
    $query->bindParam(':class_id', $classId, PDO::PARAM_INT);
    $query->bindParam(':school_id', $school_id, PDO::PARAM_INT);
    if ($streamId !== null) {
        $query->bindParam(':stream_id', $streamId, PDO::PARAM_INT);
    }
    $query->execute();
    $gradingScales = $query->fetchAll(PDO::FETCH_ASSOC);

    if ($query->rowCount() > 0) {
        // Reformat keys to match frontend's expected format (e.g., "Lower Limit")
$formattedScales = array_map(function($scale) {
    return [
        'id' => $scale['id'],
        'Lower Limit' => $scale['lower_limit'],
        'Upper Limit Marks' => $scale['upper_limit_marks'], // Renamed
        'Grade' => $scale['grade'],
        'Grade Points' => $scale['grade_points'], // Renamed
        'Grade Alias' => $scale['grade_alias'],
        'Class Teacher Remarks' => $scale['class_teacher_remarks'], // Renamed
        'Principal Remark' => $scale['principal_remark'] // Retained
    ];
}, $gradingScales);

        $response["success"] = true;
        $response["gradingScales"] = $formattedScales;
        $response["message"] = "Overall grading scales fetched successfully.";
    } else {
        $response["success"] = true; // Not an error, just no data found
        $response["message"] = "No overall grading scales found for this criteria.";
    }

} catch (PDOException $e) {
    $response["message"] = "Database error: " . $e->getMessage();
    http_response_code(500); // Internal Server Error
    error_log("Database error in api/fetch_overall_grading.php: " . $e->getMessage());
}

echo json_encode($response);
exit();
?>