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
// Note: While class_id might be sent by the frontend,
// for a DELETE operation using only exam_id and school_id is often sufficient
// if exam_id is a unique identifier within the school's exams.
// However, if you want the extra layer of verification, you can keep class_id here.
// For now, I'll remove class_id from the *required* fields for simplicity in delete,
// as the exam_id should be unique enough for a delete operation.
// If your primary key is (id, class_id), then keep it.
// Given you only send 'id' from JS delete, let's just use 'id' and 'school_id'.
// If 'class_id' is critical for your `DELETE` statement, then you MUST also pass it from JS.
// Based on your previous JS, you only pass 'id' for deletion, so let's rely on that.
// The provided JS for deleteExam only appends `formData.append('id', examId);`
// So, $class_id will *not* be present in $_POST for delete.
// Let's remove $class_id from the required check for DELETE.
// If your table relies on (id, class_id) as a composite key for uniqueness,
// you'd need to modify the JS to send class_id for delete as well.

// Server-side validation
if ($exam_id === 0) {
    $response["message"] = "Exam ID is required for deletion.";
    http_response_code(400); // Bad Request
    echo json_encode($response);
    exit();
}

try {
    // We will delete based on exam ID and school ID for security.
    // If you need to also verify class_id, you'd need to send it from JS.
    $sql = "DELETE FROM tblexam WHERE id = :exam_id AND school_id = :school_id";
    $stmt = $dbh->prepare($sql);
    $stmt->bindParam(':exam_id', $exam_id, PDO::PARAM_INT);
    $stmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);

    if ($stmt->execute()) {
        if ($stmt->rowCount() > 0) {
            $response["success"] = true;
            $response["message"] = "Exam deleted successfully!";
            http_response_code(200); // OK
        } else {
            $response["success"] = false; // Indicate that no record was deleted
            $response["message"] = "Exam not found or already deleted.";
            http_response_code(200); // Still OK, but operation didn't change anything
        }
    } else {
        $errorInfo = $stmt->errorInfo();
        $response["message"] = "Failed to delete exam: " . ($errorInfo[2] ?? 'Unknown error');
        http_response_code(500); // Internal Server Error
    }

} catch (PDOException $e) {
    $response["message"] = "Database error: " . $e->getMessage();
    error_log("PDO Error in api/delete_exam.php: " . $e->getMessage() . " (User: " . ($_SESSION['login'] ?? 'N/A') . ")");
    http_response_code(500); // Internal Server Error
} catch (Exception $e) {
    $response["message"] = "An unexpected server error occurred: " . $e->getMessage();
    error_log("General Error in api/delete_exam.php: " . $e->getMessage() . " (User: " . ($_SESSION['login'] ?? 'N/A') . ")");
    http_response_code(500); // Internal Server Error
} finally {
    $stmt = null;
}

echo json_encode($response);
exit();