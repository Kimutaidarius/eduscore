<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

include('../includes/config.php'); // Adjust path if necessary. Assuming config.php holds your PDO connection ($dbh)

$response = ["success" => false, "message" => ""];

// Authenticate user (consistent with other API files)
if (!isset($_SESSION['id']) || empty($_SESSION['id']) || !isset($_SESSION['login']) || empty($_SESSION['login']) || !isset($_SESSION['school_id']) || empty($_SESSION['school_id'])) {
    $response['message'] = 'Authentication required. Please log in.';
    http_response_code(401); // Unauthorized
    echo json_encode($response);
    exit();
}

$school_id = $_SESSION['school_id']; // Get school_id from session

// Ensure database connection is established
if (!isset($dbh) || !($dbh instanceof PDO)) {
    $response["message"] = "Database connection failed. Ensure PDO connection is established.";
    http_response_code(500); // Internal Server Error
    echo json_encode($response);
    exit();
}

// --- CRITICAL CHANGE HERE: Use $_POST to retrieve data ---
$exam_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$examname = isset($_POST['examname']) ? trim($_POST['examname']) : '';
$class_id = isset($_POST['class_id']) ? intval($_POST['class_id']) : 0;
// Stream ID is optional and might be an empty string from frontend if no stream selected
$stream_id = null;
if (isset($_POST['stream_id']) && $_POST['stream_id'] !== '') {
    $stream_id = intval($_POST['stream_id']);
}
// --- END CRITICAL CHANGE ---

// Server-side validation
if ($exam_id === 0 || empty($examname) || $class_id === 0) {
    $response["message"] = "Exam ID, Exam Name, and Class ID are required for update.";
    http_response_code(400); // Bad Request
    echo json_encode($response);
    exit();
}

try {
    // Optional: Check for duplicate exam name within the same class/stream combination
    // This prevents changing an exam name to one that already exists for the same context.
    $checkStmt = $dbh->prepare("
        SELECT COUNT(*)
        FROM tblexam
        WHERE examname = :examname
        AND class_id = :class_id
        AND school_id = :school_id
        AND (stream_id = :stream_id OR (stream_id IS NULL AND :stream_id IS NULL))
        AND id != :exam_id -- Exclude the current exam being updated
    ");
    $checkStmt->bindParam(':examname', $examname, PDO::PARAM_STR);
    $checkStmt->bindParam(':class_id', $class_id, PDO::PARAM_INT);
    $checkStmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
    $checkStmt->bindValue(':stream_id', $stream_id, $stream_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $checkStmt->bindParam(':exam_id', $exam_id, PDO::PARAM_INT);
    $checkStmt->execute();

    if ($checkStmt->fetchColumn() > 0) {
        $response["message"] = "Another exam with this name already exists for the selected class and stream (or no stream).";
        http_response_code(409); // Conflict
        echo json_encode($response);
        exit();
    }

    // Update examname and stream_id (if changed)
    $sql = "
        UPDATE tblexam
        SET examname = :examname,
            stream_id = :stream_id, -- Allow updating stream_id or setting to NULL
            last_updated = CURRENT_TIMESTAMP()
        WHERE id = :exam_id
        AND class_id = :class_id -- Added for extra security and data integrity
        AND school_id = :school_id
    ";
    $stmt = $dbh->prepare($sql);

    $stmt->bindParam(':examname', $examname, PDO::PARAM_STR);
    $stmt->bindValue(':stream_id', $stream_id, $stream_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->bindParam(':exam_id', $exam_id, PDO::PARAM_INT);
    $stmt->bindParam(':class_id', $class_id, PDO::PARAM_INT); // Bind class_id for WHERE clause
    $stmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);

    if ($stmt->execute()) {
        if ($stmt->rowCount() > 0) {
            $response["success"] = true;
            $response["message"] = "Exam updated successfully!";
            http_response_code(200); // OK
        } else {
            $response["success"] = false; // Changed to false for 'no changes made'
            $response["message"] = "Exam not found or no changes made.";
            http_response_code(200); // OK, but indicate no change
        }
    } else {
        $errorInfo = $stmt->errorInfo();
        $response["message"] = "Failed to update exam: " . ($errorInfo[2] ?? 'Unknown error');
        http_response_code(500); // Internal Server Error
    }

} catch (PDOException $e) {
    $response["message"] = "Database error: " . $e->getMessage();
    error_log("PDO Error in api/update_exam.php: " . $e->getMessage() . " (User: " . ($_SESSION['login'] ?? 'N/A') . ")");
    http_response_code(500); // Internal Server Error
} catch (Exception $e) {
    $response["message"] = "An unexpected server error occurred: " . $e->getMessage();
    error_log("General Error in api/update_exam.php: " . $e->getMessage() . " (User: " . ($_SESSION['login'] ?? 'N/A') . ")");
    http_response_code(500); // Internal Server Error
} finally {
    $stmt = null; // Close statement
    // No need to close $dbh if it's managed by config.php globally
}

echo json_encode($response);
exit();