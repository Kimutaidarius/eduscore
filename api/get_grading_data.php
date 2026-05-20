<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include('config.php');

header('Content-Type: application/json');

$response = ['success' => false, 'grades' => [], 'message' => ''];

$classId = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$subjectId = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;

if ($classId === 0 || $subjectId === 0) {
    $response['error'] = 'Invalid Class ID or Subject ID.';
    echo json_encode($response);
    exit();
}

try {
    $sql = "SELECT grade, lower_limit, upper_limit, points, remarks, grade_alias, principal_remarks
            FROM grading_scales
            WHERE class_id = :class_id AND subject_id = :subject_id
            ORDER BY lower_limit DESC"; // Order as appropriate, e.g., higher grades first

    $query = $dbh->prepare($sql);
    $query->bindParam(':class_id', $classId, PDO::PARAM_INT);
    $query->bindParam(':subject_id', $subjectId, PDO::PARAM_INT);
    $query->execute();
    $results = $query->fetchAll(PDO::FETCH_ASSOC);

    if ($query->rowCount() > 0) {
        $response['success'] = true;
        $response['grades'] = $results;
        $response['message'] = 'Grading data retrieved successfully.';
    } else {
        $response['message'] = 'No grading data found for the selected class and subject.';
    }
    echo json_encode($response);

} catch (PDOException $e) {
    $response['error'] = 'Database error: ' . $e->getMessage();
    echo json_encode($response);
}
?>