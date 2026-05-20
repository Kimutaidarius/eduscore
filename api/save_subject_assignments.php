<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

include('../includes/config.php'); // Corrected path to config.php // Your database connection file

$response = [
    'success' => false,
    'message' => 'An unknown error occurred.'
];

// 1. Authenticate user
if (!isset($_SESSION['id']) || empty($_SESSION['id']) || !isset($_SESSION['login']) || empty($_SESSION['login']) || !isset($_SESSION['school_id']) || empty($_SESSION['school_id'])) {
    $response['message'] = 'Authentication required. Please log in.';
    http_response_code(401); // Unauthorized
    echo json_encode($response);
    exit();
}

// Ensure it's a POST request and JSON content type
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method. Only POST is allowed.';
    http_response_code(405); // Method Not Allowed
    echo json_encode($response);
    exit();
}

// Get the raw POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true); // true for associative array

// 2. Validate input
if (!isset($data['subject_id']) || !isset($data['assignments']) || !is_array($data['assignments'])) {
    $response['message'] = 'Invalid input: subject_id or assignments missing/malformed.';
    http_response_code(400); // Bad Request
    echo json_encode($response);
    exit();
}

$subjectId = intval($data['subject_id']);
$assignments = $data['assignments'];
$school_id = $_SESSION['school_id']; // Get school_id from session

// Start a transaction for atomicity
$dbh->beginTransaction();

try {
    // 3. Delete existing assignments for this subject for the current school
    // This simplifies updates: delete all and re-insert only those checked.
    $deleteSql = "DELETE FROM tblsubjectassignments WHERE subject_id = :subject_id AND school_id = :school_id";
    $deleteQuery = $dbh->prepare($deleteSql);
    $deleteQuery->bindParam(':subject_id', $subjectId, PDO::PARAM_INT);
    $deleteQuery->bindParam(':school_id', $school_id, PDO::PARAM_INT);
    $deleteQuery->execute();

    // 4. Insert new assignments (only for students who are assigned)
    $insertSql = "INSERT INTO tblsubjectassignments (subject_id, student_id, class_id, stream_id, school_id) VALUES (:subject_id, :student_id, :class_id, :stream_id, :school_id)";
    $insertQuery = $dbh->prepare($insertSql);

    // Prepare for batch insertion if many assignments
    foreach ($assignments as $assignment) {
        if (isset($assignment['student_id']) && $assignment['is_assigned'] == 1) {
            $studentId = intval($assignment['student_id']);

            // To get class_id and stream_id for the student, we need to query the student table.
            // This is crucial because subjectstudents table requires class_id and stream_id.
            $studentInfoSql = "SELECT class_id, streamId FROM tblstudents WHERE id = :student_id AND school_id = :school_id LIMIT 1";
            $studentInfoQuery = $dbh->prepare($studentInfoSql);
            $studentInfoQuery->bindParam(':student_id', $studentId, PDO::PARAM_INT);
            $studentInfoQuery->bindParam(':school_id', $school_id, PDO::PARAM_INT);
            $studentInfoQuery->execute();
            $studentInfo = $studentInfoQuery->fetch(PDO::FETCH_ASSOC);

            if ($studentInfo) {
                $classId = $studentInfo['class_id'];
                $streamId = $studentInfo['streamId']; // Match the column name from the database // This might be NULL if the student is not in a stream, ensure your DB handles this

                $insertQuery->bindParam(':subject_id', $subjectId, PDO::PARAM_INT);
                $insertQuery->bindParam(':student_id', $studentId, PDO::PARAM_INT);
                $insertQuery->bindParam(':class_id', $classId, PDO::PARAM_INT);
                $insertQuery->bindParam(':stream_id', $streamId, PDO::PARAM_INT); // PDO will handle NULL correctly for INT type
                $insertQuery->bindParam(':school_id', $school_id, PDO::PARAM_INT);
                $insertQuery->execute();
            } else {
                // Log or handle case where student info not found (shouldn't happen if student_id is valid)
                error_log("Student ID {$studentId} not found or does not belong to school ID {$school_id} during assignment save.");
            }
        }
    }

    $dbh->commit();
    $response['success'] = true;
    $response['message'] = 'Subject assignments updated successfully!';
    http_response_code(200); // OK

} catch (PDOException $e) {
    $dbh->rollBack();
    $response['message'] = 'Database error: ' . $e->getMessage();
    http_response_code(500); // Internal Server Error
    error_log('Error saving subject assignments: ' . $e->getMessage()); // Log the error for debugging
} catch (Exception $e) {
    $dbh->rollBack();
    $response['message'] = 'Application error: ' . $e->getMessage();
    http_response_code(500); // Internal Server Error
    error_log('Error saving subject assignments: ' . $e->getMessage()); // Log the error for debugging
}

echo json_encode($response);
?>