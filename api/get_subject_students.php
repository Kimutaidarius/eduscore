<?php
session_start();
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

include('../includes/config.php'); 

$response = ['success' => false, 'message' => 'An unknown error occurred.', 'students' => []];

if (!isset($_SESSION['school_id'])) {
    $response['message'] = 'User not authenticated or school ID not set.';
    echo json_encode($response);
    exit();
}

$school_id = $_SESSION['school_id'];

// Get parameters from the request
$subjectId = $_GET['subject_id'] ?? null;
$classId = $_GET['class_id'] ?? null;
$streamId = $_GET['stream_id'] ?? null; 

// Validate essential parameters
if ($subjectId === null || $classId === null) {
    $response['message'] = 'Missing subject ID or class ID.';
    echo json_encode($response);
    exit();
}

try {
    $sql = "
        SELECT
            s.id AS student_id,
            CONCAT(s.FirstName, ' ', s.SecondName, ' ', IFNULL(s.LastName, '')) AS student_name, 
            s.AdmNo AS roll_no, 
            c.class_level AS class_name,
            st.stream_name,
            CASE WHEN tsa.student_id IS NOT NULL THEN 1 ELSE 0 END AS is_assigned
        FROM
            tblstudents s
        JOIN
            tblclasses c ON s.class_id = c.id
        LEFT JOIN
            tblstreams st ON s.StreamId = st.id -- CHANGE 1: Corrected from s.stream_id to s.StreamId
        LEFT JOIN
            tblsubjectassignments tsa ON s.id = tsa.student_id
            AND tsa.subject_id = :subject_id AND tsa.school_id = :school_id
        WHERE
            s.class_id = :class_id AND s.school_id = :school_id
    ";

    $params = [
        ':subject_id' => $subjectId,
        ':class_id' => $classId,
        ':school_id' => $school_id
    ];

    if (!empty($streamId) && intval($streamId) > 0) {
        $sql .= " AND s.StreamId = :stream_id"; 
        $params[':stream_id'] = $streamId;
    }

    $sql .= " ORDER BY s.AdmNo ASC";

    $stmt = $dbh->prepare($sql);
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response['success'] = true;
    $response['message'] = 'Students retrieved successfully.';
    $response['students'] = $students;

} catch (PDOException $e) {
    error_log("Database Error in get_subject_students.php: " . $e->getMessage()); 
    // IMPORTANT: Remove or comment out the actual error message for production
    $response['message'] = 'A database error occurred: ' . $e->getMessage(); 
} catch (Exception $e) {
    error_log("General Error in get_subject_students.php: " . $e->getMessage());
    $response['message'] = 'An unexpected error occurred. Please try again later.';
}

echo json_encode($response);
?>