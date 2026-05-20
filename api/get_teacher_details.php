<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include('../includes/config.php'); // Adjust path as needed

header('Content-Type: application/json');

$response = ["status" => "error", "message" => "", "teacher" => null];

// Ensure school_id is available from the session for security
if (!isset($_SESSION['school_id']) || empty($_SESSION['school_id'])) {
    $response["message"] = "Unauthorized access. School ID not found in session.";
    echo json_encode($response);
    exit();
}

$schoolId = $_SESSION['school_id'];
$teacherId = $_GET['id'] ?? null;

if (empty($teacherId)) {
    $response["message"] = "Teacher ID is required.";
    echo json_encode($response);
    exit();
}

if (!isset($dbh) || !($dbh instanceof PDO)) {
    $response["message"] = "Database connection failed. Please contact support.";
    error_log("DB connection failed in get_teacher_details.php");
    echo json_encode($response);
    exit();
}

try {
    // UPDATED: Fetch teacher details, ensuring the teacher belongs to the logged-in school
    $sql = "SELECT id, firstname, secondname, lastname, gender, email, phonenumber, role 
            FROM tblteachers 
            WHERE id = :id AND school_id = :school_id";
    
    $query = $dbh->prepare($sql);
    $query->bindParam(':id', $teacherId, PDO::PARAM_INT);
    $query->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
    $query->execute();
    $teacher = $query->fetch(PDO::FETCH_ASSOC);

    if ($teacher) {
        $response["status"] = "success";
        $response["message"] = "Teacher details fetched successfully.";
        $response["teacher"] = $teacher;
    } else {
        $response["message"] = "Teacher not found or you are not authorized to view this teacher.";
    }

} catch (PDOException $e) {
    $response["message"] = "Database error: " . $e->getMessage();
    error_log("PDO Error in get_teacher_details.php: " . $e->getMessage());
} catch (Exception $e) {
    $response["message"] = "An unexpected error occurred: " . $e->getMessage();
    error_log("General Error in get_teacher_details.php: " . $e->getMessage());
} finally {
    if (isset($query) && $query instanceof PDOStatement) {
        $query = null;
    }
}

echo json_encode($response);
exit();