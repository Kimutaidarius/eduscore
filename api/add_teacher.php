<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include('../includes/config.php'); // Adjust path as needed

header('Content-Type: application/json');

$response = ["status" => "error", "message" => ""];

// Ensure school_id is available from the session for security
if (!isset($_SESSION['school_id']) || empty($_SESSION['school_id'])) {
    $response["message"] = "Unauthorized access. School ID not found in session.";
    echo json_encode($response);
    exit();
}

$schoolId = $_SESSION['school_id']; // Get school_id from session, not from POST for security

// Get data from POST request (FormData is used by frontend)
$firstname = trim($_POST['firstname'] ?? '');
$secondname = trim($_POST['secondname'] ?? '');
$lastname = trim($_POST['lastname'] ?? '');
$email = trim($_POST['email'] ?? '');
$phonenumber = trim($_POST['phonenumber'] ?? '');
$role = trim($_POST['role'] ?? '');
$gender = trim($_POST['gender'] ?? ''); // UPDATED: Get gender from POST data

// Basic validation
if (empty($firstname) || empty($email) || empty($phonenumber) || empty($role) || empty($gender)) {
    $response["message"] = "First Name, Gender, Email, Phone Number, and Role are required.";
    echo json_encode($response);
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $response["message"] = "Invalid email format.";
    echo json_encode($response);
    exit();
}

if (!isset($dbh) || !($dbh instanceof PDO)) {
    $response["message"] = "Database connection failed. Please contact support.";
    error_log("DB connection failed in add_teacher.php");
    echo json_encode($response);
    exit();
}

try {
    // Check for duplicate email or phone number within the same school
    $stmt = $dbh->prepare("SELECT COUNT(*) FROM tblteachers WHERE (email = :email OR phonenumber = :phonenumber) AND school_id = :school_id");
    $stmt->bindParam(':email', $email, PDO::PARAM_STR);
    $stmt->bindParam(':phonenumber', $phonenumber, PDO::PARAM_STR);
    $stmt->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
    $stmt->execute();
    if ($stmt->fetchColumn() > 0) {
        $response["message"] = "A teacher with this email or phone number already exists in your school.";
        echo json_encode($response);
        exit();
    }

    // Generate a default password (e.g., "password123") or send a reset link
    // For simplicity, we'll use a default and highly recommend changing it.
    $defaultPassword = 'password123'; // !!! IMPORTANT: Advise user to change this immediately !!!
    $hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);

    // UPDATED: Added 'gender' to the column list and ':gender' to the values list
    $sql = "INSERT INTO tblteachers (school_id, firstname, secondname, lastname, gender, email, phonenumber, password, role, RegDate, UpdationDate) 
            VALUES (:school_id, :firstname, :secondname, :lastname, :gender, :email, :phonenumber, :password, :role, NOW(), NOW())";
    
    $query = $dbh->prepare($sql);
    $query->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
    $query->bindParam(':firstname', $firstname, PDO::PARAM_STR);
    $query->bindParam(':secondname', $secondname, PDO::PARAM_STR);
    $query->bindParam(':lastname', $lastname, PDO::PARAM_STR);
    $query->bindParam(':gender', $gender, PDO::PARAM_STR); // UPDATED: Bind gender parameter
    $query->bindParam(':email', $email, PDO::PARAM_STR);
    $query->bindParam(':phonenumber', $phonenumber, PDO::PARAM_STR);
    $query->bindParam(':password', $hashedPassword, PDO::PARAM_STR);
    $query->bindParam(':role', $role, PDO::PARAM_STR);
    
    if ($query->execute()) {
        $response["status"] = "success";
        $response["message"] = "Teacher added successfully! Default password is '{$defaultPassword}'. Please advise the teacher to change it.";
    } else {
        $response["message"] = "Failed to add teacher.";
    }

} catch (PDOException $e) {
    $response["message"] = "Database error: " . $e->getMessage();
    error_log("PDO Error in add_teacher.php: " . $e->getMessage());
} catch (Exception $e) {
    $response["message"] = "An unexpected error occurred: " . $e->getMessage();
    error_log("General Error in add_teacher.php: " . $e->getMessage());
} finally {
    if (isset($stmt) && $stmt instanceof PDOStatement) {
        $stmt = null;
    }
    if (isset($query) && $query instanceof PDOStatement) {
        $query = null;
    }
}

echo json_encode($response);
exit();