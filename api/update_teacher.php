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

$schoolId = $_SESSION['school_id'];
$loggedInUserId = $_SESSION['id']; // Get logged-in user's ID for self-edit check

// Get data from POST request (FormData is used by frontend)
$teacherId = $_POST['teacher_id'] ?? null;
$firstname = trim($_POST['firstname'] ?? '');
$secondname = trim($_POST['secondname'] ?? '');
$lastname = trim($_POST['lastname'] ?? '');
$email = trim($_POST['email'] ?? '');
$phonenumber = trim($_POST['phonenumber'] ?? '');
$role = trim($_POST['role'] ?? '');
$gender = trim($_POST['gender'] ?? ''); // UPDATED: Get gender from POST data

// Basic validation
if (empty($teacherId) || empty($firstname) || empty($email) || empty($phonenumber) || empty($role) || empty($gender)) {
    $response["message"] = "All required fields must be filled.";
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
    error_log("DB connection failed in update_teacher.php");
    echo json_encode($response);
    exit();
}

try {
    // First, verify that the teacher being updated belongs to the current school
    $stmt = $dbh->prepare("SELECT id, role FROM tblteachers WHERE id = :id AND school_id = :school_id");
    $stmt->bindParam(':id', $teacherId, PDO::PARAM_INT);
    $stmt->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
    $stmt->execute();
    $existingTeacher = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$existingTeacher) {
        $response["message"] = "Teacher not found or you are not authorized to update this teacher.";
        echo json_encode($response);
        exit();
    }

    // Prevent changing the role of the main admin (the currently logged-in admin)
    // if they are trying to edit themselves and they are the main admin.
    // This prevents accidental demotion or locking out the main admin.
    if ($teacherId == $loggedInUserId && $existingTeacher['role'] === 'Admin' && $role !== 'Admin') {
        $response["message"] = "Cannot change the role of the main school admin account.";
        echo json_encode($response);
        exit();
    }

    // Check for duplicate email or phone number (excluding the current teacher being updated)
    $stmt = $dbh->prepare("SELECT COUNT(*) FROM tblteachers WHERE (email = :email OR phonenumber = :phonenumber) AND id != :teacher_id AND school_id = :school_id");
    $stmt->bindParam(':email', $email, PDO::PARAM_STR);
    $stmt->bindParam(':phonenumber', $phonenumber, PDO::PARAM_STR);
    $stmt->bindParam(':teacher_id', $teacherId, PDO::PARAM_INT);
    $stmt->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
    $stmt->execute();
    if ($stmt->fetchColumn() > 0) {
        $response["message"] = "Another teacher with this email or phone number already exists in your school.";
        echo json_encode($response);
        exit();
    }
    // UPDATED: Added 'gender' to the SET statement
    $sql = "UPDATE tblteachers SET 
                firstname = :firstname, 
                secondname = :secondname, 
                lastname = :lastname, 
                gender = :gender, 
                email = :email, 
                phonenumber = :phonenumber, 
                role = :role, 
                UpdationDate = NOW() 
            WHERE id = :id AND school_id = :school_id"; // Ensure update is limited to current school

    $query = $dbh->prepare($sql);
    $query->bindParam(':firstname', $firstname, PDO::PARAM_STR);
    $query->bindParam(':secondname', $secondname, PDO::PARAM_STR);
    $query->bindParam(':lastname', $lastname, PDO::PARAM_STR);
    $query->bindParam(':gender', $gender, PDO::PARAM_STR); // UPDATED: Bind gender parameter
    $query->bindParam(':email', $email, PDO::PARAM_STR);
    $query->bindParam(':phonenumber', $phonenumber, PDO::PARAM_STR);
    $query->bindParam(':role', $role, PDO::PARAM_STR);
    $query->bindParam(':id', $teacherId, PDO::PARAM_INT);
    $query->bindParam(':school_id', $schoolId, PDO::PARAM_INT); // Bind school_id for WHERE clause
    
    if ($query->execute()) {
        $response["status"] = "success";
        $response["message"] = "Teacher updated successfully.";
    } else {
        $response["message"] = "Failed to update teacher.";
    }

} catch (PDOException $e) {
    $response["message"] = "Database error: " . $e->getMessage();
    error_log("PDO Error in update_teacher.php: " . $e->getMessage());
} catch (Exception $e) {
    $response["message"] = "An unexpected error occurred: " . $e->getMessage();
    error_log("General Error in update_teacher.php: " . $e->getMessage());
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