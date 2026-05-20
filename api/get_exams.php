<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

include('../includes/config.php'); // Ensure $dbh (PDO) is available

$response = ["success" => false, "data" => [], "message" => ""];

// --- AUTH CHECK ---
if (
    !isset($_SESSION['id']) || empty($_SESSION['id']) ||
    !isset($_SESSION['login']) || empty($_SESSION['login']) ||
    !isset($_SESSION['school_id']) || empty($_SESSION['school_id'])
) {
    http_response_code(401);
    $response['message'] = 'Authentication required. Please log in.';
    echo json_encode($response);
    exit;
}

$school_id = (int) $_SESSION['school_id'];
$class_id = isset($_GET['class_id']) ? (int) $_GET['class_id'] : 0;
$stream_id = isset($_GET['stream_id']) && $_GET['stream_id'] !== '' ? (int) $_GET['stream_id'] : null;

if ($class_id === 0) {
    http_response_code(400);
    $response['message'] = 'Class ID is required.';
    echo json_encode($response);
    exit;
}

try {
    if (!isset($dbh)) {
        throw new Exception("Database connection not available.");
    }

    $sql = "
        SELECT 
            e.id AS id,
            e.examname AS examname,
            e.class_id,
            e.stream_id,
            c.class_level AS class_name,
            s.stream_name AS stream_name,
            DATE_FORMAT(e.DateAdded, '%Y-%m-%d') AS date_added,
            COALESCE(DATE_FORMAT(e.deadline_date, '%Y-%m-%d'), '—') AS deadline_date,
            e.status,
            COALESCE(DATE_FORMAT(e.last_updated, '%Y-%m-%d %H:%i'), '—') AS last_updated
        FROM tblexam e
        JOIN tblclasses c ON e.class_id = c.id
        LEFT JOIN tblstreams s ON e.stream_id = s.id
        WHERE e.school_id = :school_id
          AND e.class_id = :class_id
    ";

    if ($stream_id !== null) {
        $sql .= " AND e.stream_id = :stream_id";
    }

    $sql .= " ORDER BY e.DateAdded DESC, e.id DESC";

    $stmt = $dbh->prepare($sql);
    $stmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
    $stmt->bindParam(':class_id', $class_id, PDO::PARAM_INT);
    if ($stream_id !== null) {
        $stmt->bindParam(':stream_id', $stream_id, PDO::PARAM_INT);
    }

    $stmt->execute();
    $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response['success'] = true;
    $response['data'] = $exams;
    $response['message'] = count($exams)
        ? "Exams fetched successfully."
        : "No exams found for this class.";

    echo json_encode($response);

} catch (PDOException $e) {
    error_log("DB Error (get_exams.php): " . $e->getMessage());
    http_response_code(500);
    $response['message'] = "Database error occurred while fetching exams.";
    echo json_encode($response);
} catch (Exception $e) {
    error_log("General Error (get_exams.php): " . $e->getMessage());
    http_response_code(500);
    $response['message'] = "Unexpected application error occurred.";
    echo json_encode($response);
}
?>
