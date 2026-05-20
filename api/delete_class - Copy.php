<?php
session_start();
include('../includes/config.php'); // Adjusted path to config.php
header('Content-Type: application/json');

$response = ['status' => 'error', 'message' => 'Invalid request.'];

$classId = $_GET['id'] ?? null;
$schoolId = $_SESSION['school_id'] ?? null;

if (empty($classId) || empty($schoolId)) {
    $response['message'] = 'Class ID or School ID missing.';
    echo json_encode($response);
    exit();
}

try {
    $dbh->beginTransaction();

    // Check if the class belongs to the school
    $checkOwnershipStmt = $dbh->prepare("SELECT COUNT(*) FROM tblclasses WHERE id = :class_id AND school_id = :school_id");
    $checkOwnershipStmt->bindParam(':class_id', $classId, PDO::PARAM_INT);
    $checkOwnershipStmt->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
    $checkOwnershipStmt->execute();
    if ($checkOwnershipStmt->fetchColumn() == 0) {
        $dbh->rollBack();
        $response['message'] = 'Class not found or does not belong to your school.';
        echo json_encode($response);
        exit();
    }

    // Delete associated students (important: filter by class_id and school_id)
    $stmtStudents = $dbh->prepare("DELETE FROM tblstudents WHERE class_id = :class_id AND school_id = :school_id");
    $stmtStudents->bindParam(':class_id', $classId, PDO::PARAM_INT);
    $stmtStudents->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
    $stmtStudents->execute();

    // Delete associated streams (important: filter by class_id and school_id)
    $stmtStreams = $dbh->prepare("DELETE FROM tblstreams WHERE class_id = :class_id AND school_id = :school_id");
    $stmtStreams->bindParam(':class_id', $classId, PDO::PARAM_INT);
    $stmtStreams->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
    $stmtStreams->execute();

    // Delete the class itself
    $stmtClass = $dbh->prepare("DELETE FROM tblclasses WHERE id = :id AND school_id = :school_id");
    $stmtClass->bindParam(':id', $classId, PDO::PARAM_INT);
    $stmtClass->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
    
    if ($stmtClass->execute()) {
        $dbh->commit();
        $response['status'] = 'success';
        $response['message'] = 'Class and all associated students/streams deleted successfully!';
    } else {
        $dbh->rollBack();
        $response['message'] = 'Failed to delete class.';
    }
} catch (PDOException $e) {
    $dbh->rollBack();
    $response['message'] = 'Database error during deletion: ' . $e->getMessage();
    error_log("API Delete Class Error: " . $e->getMessage());
}

echo json_encode($response);
?>
