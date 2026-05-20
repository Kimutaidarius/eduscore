<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once('../includes/config.php');
header('Content-Type: application/json');

$response = ['success' => false, 'message' => '', 'data' => []];

if (!isset($_SESSION['school_id']) || empty($_SESSION['school_id'])) {
    $response['message'] = "Authentication required.";
    echo json_encode($response);
    exit();
}
$schoolId = intval($_SESSION['school_id']);
$classId = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;

if ($classId <= 0) {
    $response['message'] = "Invalid Class ID provided.";
    echo json_encode($response);
    exit();
    // --- DEBUGGING LOGS START ---
    error_log("--- get_exams_by_class.php Debug ---");
    error_log("School ID from Session: " . $schoolId);
    error_log("Received class_id: " . $classId);
    // --- DEBUGGING LOGS END ---
}

try {
    // This query uses tblscores to find exams that have recorded scores for students in the given class and school.
    // 'ss' is an alias for tblscores.
    $sql = "SELECT DISTINCT e.id, e.examname
            FROM tblexam e
            JOIN tblscores ss ON e.id = ss.exam_id  -- Joining with tblscores (where exam_id should exist)
            JOIN tblstudents s ON ss.student_id = s.id
            WHERE ss.school_id = :school_id AND s.class_id = :class_id
            ORDER BY e.examname ASC";

    // --- DEBUGGING LOG: Final SQL Query for Exams ---
    error_log("Exam SQL Query: " . $sql);
    error_log("Exam SQL Params: school_id=" . $schoolId . ", class_id=" . $classId);
    // --- END DEBUGGING LOG ---

    $query = $dbh->prepare($sql);
    $query->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
    $query->bindParam(':class_id', $classId, PDO::PARAM_INT);
    $query->execute();
    $exams = $query->fetchAll(PDO::FETCH_ASSOC);

    $response['success'] = true;
    $response['data'] = $exams;
} catch (PDOException $e) {
    $response['message'] = "Database Error: " . $e->getMessage();
    error_log("Database Error in get_exams_by_class.php: " . $e->getMessage());
} catch (Exception $e) {
    $response['message'] = "Error: " . $e->getMessage();
    error_log("Application Error in get_exams_by_class.php: " . $e->getMessage());
}

echo json_encode($response);
?>