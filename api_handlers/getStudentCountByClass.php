<?php
session_start();
require_once('../includes/config.php');
header('Content-Type: application/json');

$response = ['success' => false, 'message' => '', 'count' => 0];

try {
    // ==========================
    // ✅ VALIDATE INPUTS
    // ==========================
    if (!isset($_GET['class_id']) || empty($_GET['class_id'])) {
        throw new Exception("Missing or invalid class_id");
    }

    $class_id = intval($_GET['class_id']);
    $stream_id = isset($_GET['stream_id']) && !empty($_GET['stream_id']) ? intval($_GET['stream_id']) : null;
    $school_id = isset($_SESSION['school_id']) ? intval($_SESSION['school_id']) : null;

    // ==========================
    // 🧠 BUILD QUERY
    // ==========================
    $sql = "SELECT COUNT(*) AS total 
            FROM tblstudents 
            WHERE class_id = :class_id 
              AND Status = 'Active'";

    if ($stream_id) $sql .= " AND StreamId = :stream_id";
    if ($school_id) $sql .= " AND school_id = :school_id";

    $stmt = $dbh->prepare($sql);
    $stmt->bindParam(':class_id', $class_id, PDO::PARAM_INT);
    if ($stream_id) $stmt->bindParam(':stream_id', $stream_id, PDO::PARAM_INT);
    if ($school_id) $stmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);

    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    // ==========================
    // ✅ RESPONSE
    // ==========================
    $response['success'] = true;
    $response['count'] = $result ? intval($result['total']) : 0;

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>
