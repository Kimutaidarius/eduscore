<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once('../includes/config.php');
header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => '',
    'subjects' => []
];

try {
    // --- Validate session ---
    if (empty($_SESSION['school_id']) || !is_numeric($_SESSION['school_id'])) {
        throw new Exception("Security Error: School ID missing/invalid. Please log in again.");
    }
    if (empty($_SESSION['id']) || !is_numeric($_SESSION['id'])) {
        throw new Exception("Security Error: User ID missing/invalid. Please log in again.");
    }

    $schoolId  = (int) $_SESSION['school_id'];
    $teacherId = (int) $_SESSION['id'];
    $classId   = isset($_GET['class_id']) ? (int) $_GET['class_id'] : 0;
    $streamId  = (isset($_GET['stream_id']) && $_GET['stream_id'] !== '') ? (int) $_GET['stream_id'] : null;

    if ($classId <= 0) {
        throw new Exception("Invalid or missing Class ID.");
    }

    // --- Base query ---
    $sql = "
        SELECT id, subject_name, alias
        FROM tblsubjects
        WHERE school_id = :school_id
          AND class_id = :class_id
          AND (teacher_id = :teacher_id OR teacher_id IS NULL OR teacher_id = 0)
    ";

    // Optional stream filter
    if ($streamId !== null) {
        $sql .= " AND (stream_id = :stream_id OR stream_id IS NULL)";
    }

    $sql .= " ORDER BY subject_name ASC";

    $query = $dbh->prepare($sql);
    $query->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
    $query->bindParam(':class_id', $classId, PDO::PARAM_INT);
    $query->bindParam(':teacher_id', $teacherId, PDO::PARAM_INT);

    if ($streamId !== null) {
        $query->bindParam(':stream_id', $streamId, PDO::PARAM_INT);
    }

    $query->execute();
    $subjects = $query->fetchAll(PDO::FETCH_ASSOC);

    $response['success']  = true;
    $response['subjects'] = $subjects ?: [];
    $response['message']  = $subjects ? "Subjects fetched successfully." : "No subjects found for this class/stream.";

} catch (PDOException $e) {
    $response['message'] = "Database Error: " . $e->getMessage();
} catch (Exception $e) {
    $response['message'] = "Error: " . $e->getMessage();
}

echo json_encode($response);
