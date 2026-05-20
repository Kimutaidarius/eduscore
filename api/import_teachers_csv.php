<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include('../includes/config.php'); // Adjust path as needed

header('Content-Type: application/json');

$response = ["status" => "error", "message" => "", "imported_count" => 0, "updated_count" => 0, "failed_count" => 0];

// Ensure school_id is available from the session for security
if (!isset($_SESSION['school_id']) || empty($_SESSION['school_id'])) {
    $response["message"] = "Unauthorized access. School ID not found in session.";
    echo json_encode($response);
    exit();
}

$schoolId = $_SESSION['school_id'];

$input = file_get_contents('php://input');
$data = json_decode($input, true);

$teachersData = $data['teachers'] ?? [];

if (empty($teachersData) || !is_array($teachersData)) {
    $response["message"] = "No teacher data provided for import.";
    echo json_encode($response);
    exit();
}

if (!isset($dbh) || !($dbh instanceof PDO)) {
    $response["message"] = "Database connection failed. Please contact support.";
    error_log("DB connection failed in import_teachers_csv.php");
    echo json_encode($response);
    exit();
}

$importedCount = 0;
$updatedCount = 0;
$failedCount = 0;

try {
    $dbh->beginTransaction();

    // Prepare statements outside the loop for efficiency
    $checkExistingStmt = $dbh->prepare("SELECT id FROM tblteachers WHERE (email = :email OR phonenumber = :phonenumber) AND school_id = :school_id");
    $insertStmt = $dbh->prepare("INSERT INTO tblteachers (school_id, firstname, secondname, lastname, email, phonenumber, password, role, RegDate, UpdationDate) 
                                 VALUES (:school_id, :firstname, :secondname, :lastname, :email, :phonenumber, :password, :role, NOW(), NOW())");
    $updateStmt = $dbh->prepare("UPDATE tblteachers SET 
                                 firstname = :firstname, secondname = :secondname, lastname = :lastname, email = :email, phonenumber = :phonenumber, role = :role, UpdationDate = NOW() 
                                 WHERE id = :id AND school_id = :school_id");

    foreach ($teachersData as $teacher) {
        $firstname = trim($teacher['firstname'] ?? '');
        $secondname = trim($teacher['secondname'] ?? '');
        $lastname = trim($teacher['lastname'] ?? '');
        $email = trim($teacher['email'] ?? '');
        $phonenumber = trim($teacher['phonenumber'] ?? '');
        $role = trim($teacher['role'] ?? 'Teacher'); // Default role to 'Teacher' if not specified

        // Basic validation for each row
        if (empty($firstname) || empty($email) || empty($phonenumber)) {
            $failedCount++;
            error_log("CSV Import: Skipping row due to missing required fields (firstname, email, phonenumber). Data: " . json_encode($teacher));
            continue;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $failedCount++;
            error_log("CSV Import: Skipping row due to invalid email format. Email: " . $email);
            continue;
        }

        // Check if teacher already exists by email or phone number for this school
        $checkExistingStmt->bindParam(':email', $email, PDO::PARAM_STR);
        $checkExistingStmt->bindParam(':phonenumber', $phonenumber, PDO::PARAM_STR);
        $checkExistingStmt->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
        $checkExistingStmt->execute();
        $existingTeacher = $checkExistingStmt->fetch(PDO::FETCH_ASSOC);

        if ($existingTeacher) {
            // Update existing teacher
            $updateStmt->bindParam(':firstname', $firstname, PDO::PARAM_STR);
            $updateStmt->bindParam(':secondname', $secondname, PDO::PARAM_STR);
            $updateStmt->bindParam(':lastname', $lastname, PDO::PARAM_STR);
            $updateStmt->bindParam(':email', $email, PDO::PARAM_STR);
            $updateStmt->bindParam(':phonenumber', $phonenumber, PDO::PARAM_STR);
            $updateStmt->bindParam(':role', $role, PDO::PARAM_STR);
            $updateStmt->bindParam(':id', $existingTeacher['id'], PDO::PARAM_INT);
            $updateStmt->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
            
            if ($updateStmt->execute()) {
                $updatedCount++;
            } else {
                $failedCount++;
                error_log("CSV Import: Failed to update teacher. Email: " . $email . ", ErrorInfo: " . json_encode($updateStmt->errorInfo()));
            }
        } else {
            // Insert new teacher
            // Generate a default password. IMPORTANT: User should be forced to change this on first login.
            $defaultPassword = 'password123'; 
            $hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);

            $insertStmt->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
            $insertStmt->bindParam(':firstname', $firstname, PDO::PARAM_STR);
            $insertStmt->bindParam(':secondname', $secondname, PDO::PARAM_STR);
            $insertStmt->bindParam(':lastname', $lastname, PDO::PARAM_STR);
            $insertStmt->bindParam(':email', $email, PDO::PARAM_STR);
            $insertStmt->bindParam(':phonenumber', $phonenumber, PDO::PARAM_STR);
            $insertStmt->bindParam(':password', $hashedPassword, PDO::PARAM_STR);
            $insertStmt->bindParam(':role', $role, PDO::PARAM_STR);
            
            if ($insertStmt->execute()) {
                $importedCount++;
            } else {
                $failedCount++;
                error_log("CSV Import: Failed to insert teacher. Email: " . $email . ", ErrorInfo: " . json_encode($insertStmt->errorInfo()));
            }
        }
    }

    $dbh->commit();
    $response["status"] = "success";
    $response["message"] = "CSV import complete. Imported: {$importedCount}, Updated: {$updatedCount}, Failed: {$failedCount}.";
    $response["imported_count"] = $importedCount;
    $response["updated_count"] = $updatedCount;
    $response["failed_count"] = $failedCount;

} catch (PDOException $e) {
    $dbh->rollBack();
    $response["message"] = "Database error during CSV import: " . $e->getMessage();
    error_log("PDO Error in import_teachers_csv.php: " . $e->getMessage());
    $response["failed_count"] = count($teachersData) - $importedCount - $updatedCount; // All remaining failed
} catch (Exception $e) {
    $dbh->rollBack();
    $response["message"] = "An unexpected error occurred during CSV import: " . $e->getMessage();
    error_log("General Error in import_teachers_csv.php: " . $e->getMessage());
    $response["failed_count"] = count($teachersData) - $importedCount - $updatedCount; // All remaining failed
} finally {
    if (isset($checkExistingStmt) && $checkExistingStmt instanceof PDOStatement) {
        $checkExistingStmt = null;
    }
    if (isset($insertStmt) && $insertStmt instanceof PDOStatement) {
        $insertStmt = null;
    }
    if (isset($updateStmt) && $updateStmt instanceof PDOStatement) {
        $updateStmt = null;
    }
}

echo json_encode($response);
exit();