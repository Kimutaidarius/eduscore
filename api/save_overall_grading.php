<?php
// Your provided authentication and setup block
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

include('../includes/config.php'); // Adjust path if necessary. This file should establish your $dbh (PDO) or $con (mysqli) database connection.

$response = ["success" => false, "message" => ""];

// Authenticate user
if (!isset($_SESSION['id']) || empty($_SESSION['id']) || !isset($_SESSION['login']) || empty($_SESSION['login']) || !isset($_SESSION['school_id']) || empty($_SESSION['school_id'])) {
    $response['message'] = 'Authentication required. Please log in.';
    http_response_code(401); // Unauthorized
    echo json_encode($response);
    exit();
}

$school_id = $_SESSION['school_id']; // Using school_id from session as requested

// Note: Removed the $_GET class_id/stream_id retrieval here,
// as the save endpoint receives them via POST JSON.

// Start a database transaction for multiple operations (important for data integrity)
// Assuming $dbh is your PDO connection object established in config.php
if (!isset($dbh) || !$dbh instanceof PDO) {
    $response['message'] = 'Database connection not available. Check config.php.';
    http_response_code(500);
    echo json_encode($response);
    exit();
}

$dbh->beginTransaction();

try {
    // --- 1. Get Input Data from POST request body ---
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input: ' . json_last_error_msg());
    }

    $classId = $data['class_id'] ?? null;
    $streamId = $data['stream_id'] ?? null; // Can be null if 'All Streams'
    $gradingScales = $data['grading_scales'] ?? [];
    $deletedIds = $data['deleted_ids'] ?? [];

    if (!$classId) {
        throw new Exception('Class ID not provided in the request data.');
    }
    // school_id is already validated from session above

    // --- 2. Process Deletions ---
    if (!empty($deletedIds)) {
        // Ensure all IDs are integers to prevent SQL injection
        $cleanedDeletedIds = array_map('intval', $deletedIds);
        $placeholders = implode(',', array_fill(0, count($cleanedDeletedIds), '?'));

        // Delete records belonging to the current school_id and class_id
        $stmt = $dbh->prepare("DELETE FROM overall_grading_scales WHERE id IN ($placeholders) AND school_id = ? AND class_id = ?");
        $stmt->execute(array_merge($cleanedDeletedIds, [$school_id, $classId]));
    }

    // --- 3. Process Grading Scales (Insert/Update) ---
    foreach ($gradingScales as $entry) {
        $id = $entry['id'] ?? null;

        // Map frontend keys to database column names for consistency
        $lowerLimit = $entry['Lower Limit'];
        $upperLimitMarks = $entry['Upper Limit Marks'];
        $grade = $entry['Grade'];
        $gradePoints = $entry['Grade Points'];
        $gradeAlias = $entry['Grade Alias'];
        $classTeacherRemarks = $entry['Class Teacher Remarks'];
        $principalRemark = $entry['Principal Remark']; // This might be empty if not provided by CSV/manual input

        if ($id === null) { // This is a new entry (from CSV import or new add)
            $sql = "INSERT INTO overall_grading_scales (class_id, stream_id, school_id, lower_limit, upper_limit_marks, grade, grade_points, grade_alias, class_teacher_remarks, principal_remark) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $dbh->prepare($sql);
            $stmt->execute([
                $classId,
                $streamId,
                $school_id,
                $lowerLimit,
                $upperLimitMarks,
                $grade,
                $gradePoints,
                $gradeAlias,
                $classTeacherRemarks,
                $principalRemark
            ]);
        } else { // This is an existing entry to update
            $sql = "UPDATE overall_grading_scales SET
                        lower_limit = ?,
                        upper_limit_marks = ?,
                        grade = ?,
                        grade_points = ?,
                        grade_alias = ?,
                        class_teacher_remarks = ?,
                        principal_remark = ?
                    WHERE id = ? AND class_id = ? AND school_id = ?"; // Crucially, restrict update to current class and school
            $stmt = $dbh->prepare($sql);
            $stmt->execute([
                $lowerLimit,
                $upperLimitMarks,
                $grade,
                $gradePoints,
                $gradeAlias,
                $classTeacherRemarks,
                $principalRemark,
                $id,
                $classId,
                $school_id
            ]);
        }
    }

    $dbh->commit(); // Commit the transaction if all operations were successful
    $response['success'] = true;
    $response['message'] = 'Overall grading scale saved successfully!';
    echo json_encode($response);

} catch (Exception $e) {
    $dbh->rollBack(); // Rollback on any caught exception
    error_log("Error saving overall grading scale: " . $e->getMessage());
    $response['message'] = 'Failed to save overall grading scale: ' . $e->getMessage();
    http_response_code(500); // Internal Server Error
    echo json_encode($response);
} catch (PDOException $e) {
    $dbh->rollBack(); // Rollback on PDO-specific exceptions
    error_log("Database error saving overall grading scale: " . $e->getMessage());
    $response['message'] = 'Database error: ' . $e->getMessage();
    http_response_code(500); // Internal Server Error
    echo json_encode($response);
}
?>