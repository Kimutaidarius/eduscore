<?php
session_start();
include('../includes/config.php'); // Adjusted path
header('Content-Type: application/json');

$response = [
    'status' => 'error',
    'message' => 'Invalid request.',
    'data' => null
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $streamId = $_POST['id'] ?? null;
    $newStreamName = trim($_POST['stream_name'] ?? '');
    $newClassId = $_POST['class_id'] ?? null;
    $schoolId = $_SESSION['school_id'] ?? null;

    if (empty($streamId) || empty($newStreamName) || empty($newClassId) || empty($schoolId)) {
        $response['message'] = 'All fields (ID, stream name, class ID, and school ID) are required.';
        echo json_encode($response);
        exit();
    }

    try {
        // Validate class ownership
        $checkClassStmt = $dbh->prepare("SELECT COUNT(*) FROM tblclasses WHERE id = :class_id AND school_id = :school_id");
        $checkClassStmt->bindParam(':class_id', $newClassId, PDO::PARAM_INT);
        $checkClassStmt->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
        $checkClassStmt->execute();

        if ($checkClassStmt->fetchColumn() == 0) {
            $response['message'] = 'Selected class not found or does not belong to your school.';
            echo json_encode($response);
            exit();
        }

        // Check for stream name duplicates in the same class (excluding current)
        $checkStreamStmt = $dbh->prepare("SELECT COUNT(*) FROM tblstreams WHERE stream_name = :stream_name AND class_id = :class_id AND school_id = :school_id AND id != :id");
        $checkStreamStmt->bindParam(':stream_name', $newStreamName, PDO::PARAM_STR);
        $checkStreamStmt->bindParam(':class_id', $newClassId, PDO::PARAM_INT);
        $checkStreamStmt->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
        $checkStreamStmt->bindParam(':id', $streamId, PDO::PARAM_INT);
        $checkStreamStmt->execute();

        if ($checkStreamStmt->fetchColumn() > 0) {
            $response['message'] = 'Stream with this name already exists in the selected class.';
        } else {
            $stmt = $dbh->prepare("UPDATE tblstreams SET stream_name = :stream_name, class_id = :class_id WHERE id = :id AND school_id = :school_id");
            $stmt->bindParam(':stream_name', $newStreamName, PDO::PARAM_STR);
            $stmt->bindParam(':class_id', $newClassId, PDO::PARAM_INT);
            $stmt->bindParam(':id', $streamId, PDO::PARAM_INT);
            $stmt->bindParam(':school_id', $schoolId, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $response['status'] = 'success';
                $response['message'] = 'Stream updated successfully!';
                $response['data'] = [
                    'id' => $streamId,
                    'stream_name' => $newStreamName,
                    'class_id' => $newClassId
                ];
            } else {
                $response['message'] = 'Failed to update stream in the database.';
            }
        }
    } catch (PDOException $e) {
        $response['message'] = 'Database error: ' . $e->getMessage();
        error_log("API Update Stream Error: " . $e->getMessage());
    }
}

echo json_encode($response);
?>
