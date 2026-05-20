<?php
session_start();
include('../includes/config.php'); // Adjusted path to config.php
header('Content-Type: application/json');

$response = ['status' => 'error', 'message' => 'Invalid request.'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $academic_level = trim($_POST['academic_level'] ?? '');
    $class_level = trim($_POST['class_level'] ?? '');
    $schoolId = $_SESSION['school_id'] ?? null;

    if (empty($academic_level) || empty($class_level) || empty($schoolId)) {
        $response['message'] = 'Academic level, class level, and school ID are required.';
        echo json_encode($response);
        exit();
    }

    try {
        // Check for duplicate class within the same school
        $checkStmt = $dbh->prepare("SELECT COUNT(*) FROM tblclasses WHERE academic_level = :academic_level AND class_level = :class_level AND school_id = :school_id");
        $checkStmt->bindParam(':academic_level', $academic_level, PDO::PARAM_STR);
        $checkStmt->bindParam(':class_level', $class_level, PDO::PARAM_STR);
        $checkStmt->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
        $checkStmt->execute();
        $classExists = $checkStmt->fetchColumn();

        if ($classExists > 0) {
            $response['message'] = 'Class with this academic level and class level already exists.';
        } else {
            $stmt = $dbh->prepare("INSERT INTO tblclasses (academic_level, class_level, school_id) VALUES (:academic_level, :class_level, :school_id)");
            $stmt->bindParam(':academic_level', $academic_level, PDO::PARAM_STR);
            $stmt->bindParam(':class_level', $class_level, PDO::PARAM_STR);
            $stmt->bindParam(':school_id', $schoolId, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $response['status'] = 'success';
                $response['message'] = 'Class added successfully!';
            } else {
                $response['message'] = 'Failed to add class to the database.';
            }
        }
    } catch (PDOException $e) {
        $response['message'] = 'Database error: ' . $e->getMessage();
        error_log("API Create Class Error: " . $e->getMessage());
    }
}
echo json_encode($response);
?>
