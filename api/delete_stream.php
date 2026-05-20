<?php
session_start();
include('../includes/config.php'); // Adjusted path to config.php
header('Content-Type: application/json');

$response = ['status' => 'error', 'message' => 'Invalid request.'];

$streamId = $_GET['id'] ?? null;
$schoolId = $_SESSION['school_id'] ?? null;

if (empty($streamId) || empty($schoolId)) {
    $response['message'] = 'Stream ID or School ID missing.';
    echo json_encode($response);
    exit();
}

try {
    // Check if the stream belongs to the school
    $checkOwnershipStmt = $dbh->prepare("SELECT COUNT(*) FROM tblstreams WHERE id = :stream_id AND school_id = :school_id");
    $checkOwnershipStmt->bindParam(':stream_id', $streamId, PDO::PARAM_INT);
    $checkOwnershipStmt->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
    $checkOwnershipStmt->execute();
    if ($checkOwnershipStmt->fetchColumn() == 0) {
        $response['message'] = 'Stream not found or does not belong to your school.';
        echo json_encode($response);
        exit();
    }

    $stmt = $dbh->prepare("DELETE FROM tblstreams WHERE id = :id AND school_id = :school_id");
    $stmt->bindParam(':id', $streamId, PDO::PARAM_INT);
    $stmt->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        $response['status'] = 'success';
        $response['message'] = 'Stream deleted successfully!';
    } else {
        $response['message'] = 'Failed to delete stream.';
    }
} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
    error_log("API Delete Stream Error: " . $e->getMessage());
}

echo json_encode($response);
?>
