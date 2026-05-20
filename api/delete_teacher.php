<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include('../includes/config.php'); // Adjust path as needed

header('Content-Type: application/json');

$response = ["status" => "error", "message" => ""];

// Ensure school_id and logged-in user ID are available from the session for security
if (!isset($_SESSION['school_id']) || empty($_SESSION['school_id']) || !isset($_SESSION['id']) || empty($_SESSION['id'])) {
    $response["message"] = "Unauthorized access. School ID or User ID not found in session.";
    echo json_encode($response);
    exit();
}

$schoolId = $_SESSION['school_id'];
$loggedInUserId = $_SESSION['id'];
$loggedInUserRole = $_SESSION['user_role'] ?? ''; // Get the role of the user performing the delete

$input = file_get_contents('php://input');
$data = json_decode($input, true);

$teacherIdToDelete = $data['teacher_id'] ?? null;
// For extra security, you could also verify school_id from payload against session,
// but relying on session's school_id for the WHERE clause is primary.
// $schoolIdFromPayload = $data['school_id'] ?? null; 

if (empty($teacherIdToDelete)) {
    $response["message"] = "Teacher ID is required for deletion.";
    echo json_encode($response);
    exit();
}

if (!isset($dbh) || !($dbh instanceof PDO)) {
    $response["message"] = "Database connection failed. Please contact support.";
    error_log("DB connection failed in delete_teacher.php");
    echo json_encode($response);
    exit();
}

try {
    // IMPORTANT SECURITY CHECK: Prevent a user from deleting themselves or the main school admin account
    // First, get the role of the teacher being deleted
    $stmt = $dbh->prepare("SELECT id, role FROM tblteachers WHERE id = :id AND school_id = :school_id");
    $stmt->bindParam(':id', $teacherIdToDelete, PDO::PARAM_INT);
    $stmt->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
    $stmt->execute();
    $teacherToDelete = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$teacherToDelete) {
        $response["message"] = "Teacher not found or you are not authorized to delete this teacher.";
        echo json_encode($response);
        exit();
    }

    // Prevent deleting the currently logged-in user
    if ($teacherIdToDelete == $loggedInUserId) {
        $response["message"] = "You cannot delete your own account from here.";
        echo json_encode($response);
        exit();
    }

    // Prevent deleting the main school admin account (if it's not the logged-in user, but another admin)
    // This assumes there's only one 'main' admin per school, or you have a way to identify it.
    // The frontend already disables the button for the logged-in admin.
    // This backend check adds another layer.
    if ($teacherToDelete['role'] === 'Admin') {
        // You might need more sophisticated logic here if multiple admins are allowed to delete each other.
        // For now, assuming the primary admin should not be deleted by anyone from this interface.
        $response["message"] = "Cannot delete a school admin account from this interface.";
        echo json_encode($response);
        exit();
    }


    // Delete the teacher, ensuring it's from the correct school
    $sql = "DELETE FROM tblteachers WHERE id = :id AND school_id = :school_id";
    
    $query = $dbh->prepare($sql);
    $query->bindParam(':id', $teacherIdToDelete, PDO::PARAM_INT);
    $query->bindParam(':school_id', $schoolId, PDO::PARAM_INT); // Ensure deletion is limited to current school
    
    if ($query->execute()) {
        if ($query->rowCount() > 0) {
            $response["status"] = "success";
            $response["message"] = "Teacher deleted successfully.";
        } else {
            $response["message"] = "Teacher not found or already deleted.";
        }
    } else {
        $response["message"] = "Failed to delete teacher.";
    }

} catch (PDOException $e) {
    $response["message"] = "Database error: " . $e->getMessage();
    error_log("PDO Error in delete_teacher.php: " . $e->getMessage());
} catch (Exception $e) {
    $response["message"] = "An unexpected error occurred: " . $e->getMessage();
    error_log("General Error in delete_teacher.php: " . $e->getMessage());
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