<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include('../includes/config.php'); // Adjust path as needed

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        $response['error'] = 'Invalid JSON data.';
        echo json_encode($response);
        exit();
    }

    $classId = isset($data['class_id']) ? intval($data['class_id']) : 0;
    $streamId = isset($data['stream_id']) ? intval($data['stream_id']) : 0;
    $subjectId = isset($data['subject_id']) ? intval($data['subject_id']) : 0;
    $assignments = $data['assignments'] ?? [];

    if ($classId === 0 || $streamId === 0 || $subjectId === 0) {
        $response['error'] = 'Missing Class ID, Stream ID, or Subject ID.';
        echo json_encode($response);
        exit();
    }

    if (empty($assignments)) {
        $response['error'] = 'No student assignments received.';
        echo json_encode($response);
        exit();
    }

    $dbh->beginTransaction();
    try {
        // 1. Delete ALL existing assignments for this specific subject, class, and stream
        // This prevents old assignments from lingering if a student is unselected.
        $deleteSql = "DELETE FROM student_subjects
                      WHERE class_id = :class_id AND stream_id = :stream_id AND subject_id = :subject_id";
        $deleteQuery = $dbh->prepare($deleteSql);
        $deleteQuery->bindParam(':class_id', $classId, PDO::PARAM_INT);
        $deleteQuery->bindParam(':stream_id', $streamId, PDO::PARAM_INT);
        $deleteQuery->bindParam(':subject_id', $subjectId, PDO::PARAM_INT);
        $deleteQuery->execute();

        // 2. Insert new assignments for selected students
        $insertSql = "INSERT INTO student_subjects (student_id, subject_id, class_id, stream_id)
                      VALUES (:student_id, :subject_id, :class_id, :stream_id)";
        $insertQuery = $dbh->prepare($insertSql);

        $insertedCount = 0;
        foreach ($assignments as $assignment) {
            if ($assignment['is_assigned']) {
                $studentId = intval($assignment['student_id']);
                if ($studentId > 0) {
                    $insertQuery->bindParam(':student_id', $studentId, PDO::PARAM_INT);
                    $insertQuery->bindParam(':subject_id', $subjectId, PDO::PARAM_INT);
                    $insertQuery->bindParam(':class_id', $classId, PDO::PARAM_INT);
                    $insertQuery->bindParam(':stream_id', $streamId, PDO::PARAM_INT);
                    $insertQuery->execute();
                    $insertedCount++;
                }
            }
        }

        $dbh->commit();
        $response['success'] = true;
        $response['message'] = "Assignments updated successfully. {$insertedCount} students assigned to subject.";
        echo json_encode($response);

    } catch (PDOException $e) {
        $dbh->rollBack();
        $response['error'] = 'Database error: ' . $e->getMessage();
        echo json_encode($response);
    }
} else {
    $response['error'] = 'Invalid request method.';
    echo json_encode($response);
}
?>