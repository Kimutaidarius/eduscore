<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

include('../includes/config.php');
$response = ["success" => false, "message" => ""];

if (
    !isset($_SESSION['id']) || empty($_SESSION['id']) ||
    !isset($_SESSION['login']) || empty($_SESSION['login']) ||
    !isset($_SESSION['school_id']) || empty($_SESSION['school_id'])
) {
    http_response_code(401);
    $response['message'] = 'Authentication required.';
    echo json_encode($response);
    exit;
}

$school_id = (int) $_SESSION['school_id'];
$data = json_decode(file_get_contents('php://input'), true);
$exam_id = isset($data['exam_id']) ? (int)$data['exam_id'] : 0;
$status = isset($data['status']) ? trim($data['status']) : '';

if ($exam_id === 0 || $status === '') {
    http_response_code(400);
    $response['message'] = 'Exam ID and status are required.';
    echo json_encode($response);
    exit;
}

try {
    $check = $dbh->prepare("SELECT id FROM tblexam WHERE id = :exam_id AND school_id = :school_id");
    $check->execute([':exam_id' => $exam_id, ':school_id' => $school_id]);
    if ($check->rowCount() === 0) {
        http_response_code(403);
        $response['message'] = 'Unauthorized action.';
        echo json_encode($response);
        exit;
    }

    $update = $dbh->prepare("UPDATE tblexam SET status = :status, last_updated = NOW() WHERE id = :exam_id");
    $update->execute([':status' => $status, ':exam_id' => $exam_id]);

    $response['success'] = true;
    $response['message'] = "Exam status updated to $status.";
    echo json_encode($response);
} catch (PDOException $e) {
    error_log("DB Error (update_exam_status.php): " . $e->getMessage());
    http_response_code(500);
    $response['message'] = 'Database error occurred.';
    echo json_encode($response);
}
?>
