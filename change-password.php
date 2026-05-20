<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json'); // Ensure JSON response

include('includes/config.php');

$response = ['success' => false, 'message' => ''];

// Check if user is logged in
if (!isset($_SESSION['id']) || empty($_SESSION['id']) || !isset($_SESSION['login']) || empty($_SESSION['login'])) {
    $response['message'] = 'User not logged in or session expired.';
    echo json_encode($response);
    exit();
}

// Get user input
$oldPassword = $_POST['oldPassword'] ?? '';
$newPassword = $_POST['newPassword'] ?? '';

// Basic validation
if (empty($oldPassword) || empty($newPassword)) {
    $response['message'] = 'All fields are required.';
    echo json_encode($response);
    exit();
}

// Password policy (same as frontend for consistency, though backend validates securely)
if (strlen($newPassword) < 8 || !preg_match('/[A-Z]/', $newPassword) || !preg_match('/[a-z]/', $newPassword) || !preg_match('/\d/', $newPassword)) {
    $response['message'] = 'New password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, and one number.';
    echo json_encode($response);
    exit();
}

$userId = $_SESSION['id'];
$userEmail = $_SESSION['login']; // Assuming login is the unique identifier for fetching password hash

try {
    // 1. Fetch current user's password hash from the database
    // Assuming 'tblusers' stores login details. Adjust table/column names as per your DB schema.
    // Ensure you use the correct table where user login/password info is stored.
    // For example, if admin/teachers/students have separate login tables, you'd need logic to pick the right one based on user_role.
    // For simplicity, let's assume a generic 'tblusers' or you adapt this to your specific user table (e.g., tbladmin, tblteachers, tblstudents)
    // based on the $_SESSION['user_role'].

    $tableName = '';
    $idColumn = '';
    $emailColumn = '';
    $passwordColumn = '';

    // Determine the table based on user role
    if (isset($_SESSION['user_role'])) {
        switch ($_SESSION['user_role']) {
            case 'Admin':
                $tableName = 'tbladmin';
                $idColumn = 'id';
                $emailColumn = 'UserName'; // Adjust if your admin table uses a different column name for email/username
                $passwordColumn = 'Password';
                break;
            case 'Teacher':
                $tableName = 'tblteachers';
                $idColumn = 'id';
                $emailColumn = 'TeacherEmail'; // Adjust column name
                $passwordColumn = 'Password';
                break;
            case 'Student':
                $tableName = 'tblstudents';
                $idColumn = 'id';
                $emailColumn = 'StudentEmail'; // Adjust column name
                $passwordColumn = 'Password';
                break;
            default:
                $response['message'] = 'Unknown user role.';
                echo json_encode($response);
                exit();
        }
    } else {
        $response['message'] = 'User role not defined.';
        echo json_encode($response);
        exit();
    }


    $stmt = $dbh->prepare("SELECT $passwordColumn FROM $tableName WHERE $idColumn = :userId LIMIT 1");
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $response['message'] = 'User not found.';
        echo json_encode($response);
        exit();
    }

    $storedHashedPassword = $user[$passwordColumn];

    // 2. Verify old password
    if (!password_verify($oldPassword, $storedHashedPassword)) {
        $response['message'] = 'Old password is incorrect.';
        echo json_encode($response);
        exit();
    }

    // 3. Hash the new password
    $newHashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    // 4. Update the password in the database
    $updateStmt = $dbh->prepare("UPDATE $tableName SET $passwordColumn = :newPassword WHERE $idColumn = :userId");
    $updateStmt->bindParam(':newPassword', $newHashedPassword, PDO::PARAM_STR);
    $updateStmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    $updateStmt->execute();

    if ($updateStmt->rowCount() > 0) {
        $response['success'] = true;
        $response['message'] = 'Password changed successfully!';
    } else {
        $response['message'] = 'Failed to update password. It might be the same as your old password or an internal error occurred.';
    }

} catch (PDOException $e) {
    error_log("Database error during password change: " . $e->getMessage());
    $response['message'] = 'Database error. Please try again later.';
} catch (Exception $e) {
    error_log("General error during password change: " . $e->getMessage());
    $response['message'] = 'An unexpected error occurred.';
}

echo json_encode($response);
?>