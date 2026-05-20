<?php

session_start();
// Enable error reporting and logging for debugging, but prevent display to client
error_reporting(E_ALL);
ini_set('display_errors', 0); // DO NOT display errors directly in API response
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log'); // Adjust path as needed, ensures errors go to a file

header('Content-Type: application/json'); // Crucial: tell client to expect JSON

include('../includes/config.php'); // Corrected path: '../' means go up one directory // Your database connection and configuration

$response = array('success' => false, 'message' => '');

// Check if user is logged in and school_id is set for authorization
if (!isset($_SESSION['id']) || empty($_SESSION['id']) || !isset($_SESSION['login']) || empty($_SESSION['login']) || !isset($_SESSION['school_id']) || empty($_SESSION['school_id'])) {
    $response['message'] = 'Unauthorized access. Please log in.';
    error_log("Unauthorized API access attempt to get_single_student.php"); // Log this event
    echo json_encode($response);
    exit();
}

$schoolId = $_SESSION['school_id']; // Get the logged-in user's school ID

// Check if student_id is provided in the GET request
if (!isset($_GET['student_id']) || empty($_GET['student_id'])) {
    $response['message'] = 'Student ID not provided.';
    error_log("DEBUG: Student ID not provided in get_single_student.php request.");
    echo json_encode($response);
    exit();
}

$studentId = intval($_GET['student_id']); // Sanitize: Ensure it's an integer for security

try {
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // Set PDO error mode to throw exceptions

$sql = "SELECT 
    s.id, 
    s.FirstName, 
    s.SecondName, 
    s.LastName, 
    s.AdmNo, 
    s.Nemis, 
    s.Gender,
    s.ContactNo AS guardian_contact,
    c.academic_level AS academic_level_name,
    c.class_level, 
    c.id AS class_id,
    st.stream_name, 
    st.id AS StreamId
FROM tblstudents s
JOIN tblclasses c ON s.class_id = c.id
LEFT JOIN tblstreams st ON s.StreamId = st.id
WHERE s.id = :sid AND s.school_id = :school_id
";

    $query = $dbh->prepare($sql);
    $query->bindParam(':sid', $studentId, PDO::PARAM_INT);
    $query->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
    $query->execute();
    $student = $query->fetch(PDO::FETCH_ASSOC);

    if ($student) {
        $response['success'] = true;
        $response['data'] = $student;
        $response['message'] = 'Student data fetched successfully.';
    } else {
        $response['message'] = 'Student not found or does not belong to your school.';
        error_log("DEBUG: Student ID " . $studentId . " not found or school ID mismatch for school " . $schoolId);
    }

} catch (PDOException $e) {
    // Log database errors for debugging, but provide generic message to client
    error_log("PDO Error in get_single_student.php: " . $e->getMessage() . " (Line: " . $e->getLine() . ")");
    $response['message'] = 'A database error occurred. Please try again later.';
} catch (Exception $e) {
    // Catch any other unexpected errors
    error_log("General Error in get_single_student.php: " . $e->getMessage() . " (Line: " . $e->getLine() . ")");
    $response['message'] = 'An unexpected server error occurred.';
}

echo json_encode($response); // Output the JSON response
exit(); // Crucial: Stop script execution immediately after outputting JSON
?>