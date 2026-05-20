<?php
session_start();
include('../includes/config.php'); // Adjusted path to config.php
header('Content-Type: application/json');

$response = ['status' => 'error', 'message' => 'Invalid request.'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $stream_name = trim($_POST['stream_name'] ?? '');
    $class_id = $_POST['class_id'] ?? null;
    $schoolId = $_SESSION['school_id'] ?? null;

    if (empty($stream_name) || empty($class_id) || empty($schoolId)) {
        $response['message'] = 'Stream name, class ID, and school ID are required.';
        echo json_encode($response);
        exit();
    }

    try {
        // Verify class belongs to the school
        $checkClassStmt = $dbh->prepare("SELECT COUNT(*) FROM tblclasses WHERE id = :class_id AND school_id = :school_id");
        $checkClassStmt->bindParam(':class_id', $class_id, PDO::PARAM_INT);
        $checkClassStmt->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
        $checkClassStmt->execute();
        if ($checkClassStmt->fetchColumn() == 0) {
            $response['message'] = 'Selected class not found or does not belong to your school.';
            echo json_encode($response);
            exit();
        }

        // Check for duplicate stream within the same class and school
        $checkStreamStmt = $dbh->prepare("SELECT COUNT(*) FROM tblstreams WHERE stream_name = :stream_name AND class_id = :class_id AND school_id = :school_id");
        $checkStreamStmt->bindParam(':stream_name', $stream_name, PDO::PARAM_STR);
        $checkStreamStmt->bindParam(':class_id', $class_id, PDO::PARAM_INT);
        $checkStreamStmt->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
        $checkStreamStmt->execute();
        $streamExists = $checkStreamStmt->fetchColumn();

        if ($streamExists > 0) {
            $response['message'] = 'Stream with this name already exists for this class.';
        } else {
            $stmt = $dbh->prepare("INSERT INTO tblstreams (stream_name, class_id, school_id) VALUES (:stream_name, :class_id, :school_id)");
            $stmt->bindParam(':stream_name', $stream_name, PDO::PARAM_STR);
            $stmt->bindParam(':class_id', $class_id, PDO::PARAM_INT);
            $stmt->bindParam(':school_id', $schoolId, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $response['status'] = 'success';
                $response['message'] = 'Stream added successfully!';
            } else {
                $response['message'] = 'Failed to add stream to the database.';
            }
        }
    } catch (PDOException $e) {
        $response['message'] = 'Database error: ' . $e->getMessage();
        error_log("API Add Stream Error: " . $e->getMessage());
    }
}
echo json_encode($response);
?>
