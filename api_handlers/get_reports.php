<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include('../includes/config.php'); // Adjust path as necessary

header('Content-Type: application/json');

// Authentication check
if (!isset($_SESSION['id'], $_SESSION['school_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

$schoolId = $_SESSION['school_id'];  // use school id from session
$classId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : null;

try {
    // Base SQL, filtering by school
    $sql = "SELECT
                trc.id,
                trc.report_title,
                trc.report_term_details,
                trc.computation_method,
                trc.exam_id,
                COALESCE(te.examname, trc.exam_id) AS exam_name_display,
                trc.total_learning_areas,
                trc.ranking_option AS ranking_type,
                trc.batch_status AS progress_status,
                trc.report_files_json,
                trc.created_at
            FROM tblreportconfigurations AS trc
            LEFT JOIN tblexam AS te ON trc.exam_id = te.id
            WHERE trc.school_id = :school_id";

    // If class_id is provided, add filter
    if ($classId) {
        $sql .= " AND trc.class_id = :class_id";
    }

    $sql .= " ORDER BY trc.created_at DESC";

    $query = $dbh->prepare($sql);
    $query->bindParam(':school_id', $schoolId, PDO::PARAM_INT);

    if ($classId) {
        $query->bindParam(':class_id', $classId, PDO::PARAM_INT);
    }

    $query->execute();
    $results = $query->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $results]);

} catch (PDOException $e) {
    error_log("Database error in get_reports.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred.']);
}
?>
