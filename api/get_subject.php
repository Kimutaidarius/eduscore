<?php
// api/get_subjects.php
session_start();
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

include('../includes/config.php'); // Adjust path

$response = ['success' => false, 'message' => '', 'subjects' => []];

if (!isset($_SESSION['id']) || empty($_SESSION['id']) || !isset($_SESSION['school_id']) || empty($_SESSION['school_id'])) {
    $response['message'] = 'Authentication required. Please log in.';
    echo json_encode($response);
    exit();
}

$loggedInUserId = $_SESSION['id'];
$schoolId = $_SESSION['school_id'];
$classId = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$streamId = isset($_GET['stream_id']) ? (empty($_GET['stream_id']) ? null : intval($_GET['stream_id'])) : null; // Handle 'All' option as NULL

if ($classId <= 0) {
    $response['message'] = 'Invalid Class ID provided.';
    echo json_encode($response);
    exit();
}

try {
    // Determine user role
    $stmtUser = $dbh->prepare("SELECT role FROM tblteachers WHERE id = :loggedInUserId AND school_id = :schoolId");
    $stmtUser->bindParam(':loggedInUserId', $loggedInUserId, PDO::PARAM_INT);
    $stmtUser->bindParam(':schoolId', $schoolId, PDO::PARAM_INT);
    $stmtUser->execute();
    $userResult = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$userResult || ($userResult['role'] !== 'Admin' && $userResult['role'] !== 'Teacher')) {
        $response['message'] = 'Access denied. You do not have sufficient permissions.';
        echo json_encode($response);
        exit();
    }
    $userRole = $userResult['role'];

    $sql = "SELECT id, subject_name FROM tblsubjects WHERE school_id = :schoolId AND class_id = :classId";
    $params = [
        ':schoolId' => $schoolId,
        ':classId' => $classId
    ];

    if ($streamId !== null) {
        // If a specific stream is selected, filter by it.
        // It's possible a subject applies to a specific stream OR to the whole class (stream_id IS NULL)
        // So, we fetch subjects that are specifically for this stream OR are general to the class (stream_id IS NULL)
        $sql .= " AND (stream_id = :streamId OR stream_id IS NULL)";
        $params[':streamId'] = $streamId;
    } else {
        // If "All Streams" is selected, we fetch all subjects for the class regardless of stream_id
        // (i.e., those assigned to a specific stream OR those general to the class - stream_id IS NULL).
        // The previous condition `(stream_id = :streamId OR stream_id IS NULL)` handles this if `streamId` is the specific stream or `NULL`.
        // For 'All Streams', we simply don't filter by stream_id. The base WHERE clause already covers class_id.
        // So no additional WHERE clause for stream_id needed if streamId is NULL.
    }

    // Teachers can only see subjects they are assigned to. Admins see all.
    if ($userRole === 'Teacher') {
        $sql .= " AND teacher_id = :teacherId";
        $params[':teacherId'] = $loggedInUserId;
    }

    $sql .= " ORDER BY subject_name ASC";

    $query = $dbh->prepare($sql);
    foreach ($params as $key => $value) {
        if ($value === null) {
            $query->bindValue($key, null, PDO::PARAM_NULL);
        } else {
            $query->bindParam($key, $params[$key], is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
    }
    $query->execute();
    $subjects = $query->fetchAll(PDO::FETCH_ASSOC);

    if ($subjects) {
        $response['success'] = true;
        $response['subjects'] = $subjects;
    } else {
        $response['message'] = 'No subjects found for this selection or you are not assigned to any subjects for this class/stream.';
    }

} catch (PDOException $e) {
    error_log("Error fetching subjects in get_subjects.php: " . $e->getMessage());
    $response['message'] = 'Database error during subject retrieval. Please try again later.';
}

echo json_encode($response);
?>