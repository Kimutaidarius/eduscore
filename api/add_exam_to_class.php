<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include('../includes/config.php'); // Corrected path

header('Content-Type: application/json');

$response = ["success" => false, "message" => ""];

if (!isset($dbh) || !($dbh instanceof PDO)) {
    $response["message"] = "Database connection failed. Ensure PDO connection is established.";
    echo json_encode($response);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

$examname = isset($input['examname']) ? trim($input['examname']) : '';
$class_id = isset($input['class_id']) ? intval($input['class_id']) : 0;

if (empty($examname) || $class_id === 0) {
    $response["message"] = "Exam name and Class ID are required.";
    echo json_encode($response);
    exit();
}

// DateAdded and Status are handled by the backend (as per your requirements)
$DateAdded = date('Y-m-d'); // Set current date
$status = 'Active';          // Set default status

try {
    $sql = "INSERT INTO tblexam(examname, DateAdded, status, class_id) VALUES(?, ?, ?, ?)";
    $stmt = $dbh->prepare($sql);
    
    $stmt->bindValue(1, $examname, PDO::PARAM_STR);
    $stmt->bindValue(2, $DateAdded, PDO::PARAM_STR);
    $stmt->bindValue(3, $status, PDO::PARAM_STR);
    $stmt->bindValue(4, $class_id, PDO::PARAM_INT);

    if ($stmt->execute()) {
        $response["success"] = true;
        $response["message"] = "Exam added successfully!";
        // You could also return the new exam ID if needed: $response["id"] = $dbh->lastInsertId();
    } else {
        $errorInfo = $stmt->errorInfo();
        $response["message"] = "Failed to add exam: " . ($errorInfo[2] ?? 'Unknown error');
    }

} catch (PDOException $e) {
    $response["message"] = "Database error: " . $e->getMessage();
    error_log("PDO Error in api/add_exam_to_class.php: " . $e->getMessage());
} catch (Exception $e) {
    $response["message"] = "An unexpected server error occurred: " . $e->getMessage();
    error_log("General Error in api/add_exam_to_class.php: " . $e->getMessage());
} finally {
    $stmt = null;
}

echo json_encode($response);
exit();<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include('../includes/config.php'); // Corrected path

header('Content-Type: application/json');

$response = ["success" => false, "message" => ""];

if (!isset($dbh) || !($dbh instanceof PDO)) {
    $response["message"] = "Database connection failed. Ensure PDO connection is established.";
    echo json_encode($response);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

$examname = isset($input['examname']) ? trim($input['examname']) : '';
$class_id = isset($input['class_id']) ? intval($input['class_id']) : 0;

if (empty($examname) || $class_id === 0) {
    $response["message"] = "Exam name and Class ID are required.";
    echo json_encode($response);
    exit();
}

// DateAdded and Status are handled by the backend (as per your requirements)
$DateAdded = date('Y-m-d'); // Set current date
$status = 'Active';          // Set default status

try {
    $sql = "INSERT INTO tblexam(examname, DateAdded, status, class_id) VALUES(?, ?, ?, ?)";
    $stmt = $dbh->prepare($sql);
    
    $stmt->bindValue(1, $examname, PDO::PARAM_STR);
    $stmt->bindValue(2, $DateAdded, PDO::PARAM_STR);
    $stmt->bindValue(3, $status, PDO::PARAM_STR);
    $stmt->bindValue(4, $class_id, PDO::PARAM_INT);

    if ($stmt->execute()) {
        $response["success"] = true;
        $response["message"] = "Exam added successfully!";
        // You could also return the new exam ID if needed: $response["id"] = $dbh->lastInsertId();
    } else {
        $errorInfo = $stmt->errorInfo();
        $response["message"] = "Failed to add exam: " . ($errorInfo[2] ?? 'Unknown error');
    }

} catch (PDOException $e) {
    $response["message"] = "Database error: " . $e->getMessage();
    error_log("PDO Error in api/add_exam_to_class.php: " . $e->getMessage());
} catch (Exception $e) {
    $response["message"] = "An unexpected server error occurred: " . $e->getMessage();
    error_log("General Error in api/add_exam_to_class.php: " . $e->getMessage());
} finally {
    $stmt = null;
}

echo json_encode($response);
exit();