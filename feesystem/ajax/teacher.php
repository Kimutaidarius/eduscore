<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../includes/config.php';

// Check authentication
if (empty($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$school_id = $_SESSION['school_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'fetch_teachers':
            $sql = "SELECT t.id, t.firstname, t.middle_name as middlename, t.lastname, t.email, t.phonenumber, 
                           t.role, t.status, r.role_name as assigned_role
                    FROM tblteachers t
                    LEFT JOIN tblroles r ON t.role_id = r.id
                    WHERE t.school_id = :school_id AND (t.is_deleted = 0 OR t.is_deleted IS NULL)
                    ORDER BY t.firstname ASC";
            $stmt = $db->prepare($sql);
            $stmt->execute([':school_id' => $school_id]);
            $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'data' => $teachers]);
            break;
            
        case 'update_teacher_role':
            $input = json_decode(file_get_contents('php://input'), true);
            $teacher_id = $input['teacher_id'] ?? 0;
            $role_name = $input['role'] ?? null;
            
            if (!$teacher_id) {
                echo json_encode(['success' => false, 'message' => 'Teacher ID required']);
                break;
            }
            
            // Get role_id from role_name
            $role_id = null;
            if ($role_name) {
                $stmt = $db->prepare("SELECT id FROM tblroles WHERE role_name = :role_name AND school_id = :school_id");
                $stmt->execute([':role_name' => $role_name, ':school_id' => $school_id]);
                $role_id = $stmt->fetchColumn();
            }
            
            $sql = "UPDATE tblteachers SET role_id = :role_id WHERE id = :teacher_id AND school_id = :school_id";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':role_id' => $role_id ?: null,
                ':teacher_id' => $teacher_id,
                ':school_id' => $school_id
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Role updated successfully']);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (PDOException $e) {
    error_log("Teacher API error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>