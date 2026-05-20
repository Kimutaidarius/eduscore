<?php
session_start();
include('../includes/config.php'); // Adjusted path to config.php
header('Content-Type: application/json');

$response = ['status' => 'error', 'message' => 'Invalid request.'];

$classId = $_GET['class_id'] ?? null;
$schoolId = $_SESSION['school_id'] ?? null;

if (empty($classId) || empty($schoolId)) {
    $response['message'] = 'Class ID or School ID missing.';
    echo json_encode($response);
    exit();
}

try {
    // Check if the class belongs to the school
    $checkOwnershipStmt = $dbh->prepare("SELECT COUNT(*) FROM tblclasses WHERE id = :class_id AND school_id = :school_id");
    $checkOwnershipStmt->bindParam(':class_id', $classId, PDO::PARAM_INT);
    $checkOwnershipStmt->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
    $checkOwnershipStmt->execute();
    if ($checkOwnershipStmt->fetchColumn() == 0) {
        $response['message'] = 'Class not found or does not belong to your school.';
        echo json_encode($response);
        exit();
    }

$stmt = $dbh->prepare("DELETE FROM tblstudents WHERE class_id = :class_id AND school_id = :school_id");
    $stmt->bindParam(':class_id', $classId, PDO::PARAM_INT);
    $stmt->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        $response['status'] = 'success';
        $response['message'] = 'All students in the class deleted successfully!';
    } else {
        $response['message'] = 'Failed to delete students.';
    }
} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
    error_log("API Delete Students Error: " . $e->getMessage());
}

echo json_encode($response);
?>
