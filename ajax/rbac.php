<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

// Security check
if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['school_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../includes/config.php';
require_once '../includes/RBAC.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$rbac = RBAC::getInstance();
$action = $_GET['action'] ?? '';
$school_id = $_SESSION['school_id'];
$teacher_id = $_SESSION['teacher_id'];

// Check if user has permission to manage roles
if (!hasPermission('rolesEdit') && !isSuperAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

switch ($action) {
    case 'get_roles':
        $roles = $rbac->getSchoolRoles($school_id);
        echo json_encode(['success' => true, 'data' => $roles]);
        break;
        
    case 'get_role':
        $role_id = $_GET['role_id'] ?? 0;
        $role = $rbac->getRoleById($role_id, $school_id);
        if ($role) {
            $permissions = $rbac->getRolePermissions($role_id, $school_id);
            echo json_encode(['success' => true, 'data' => ['role' => $role, 'permissions' => $permissions]]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Role not found']);
        }
        break;
        
    case 'create_role':
        $data = json_decode(file_get_contents('php://input'), true);
        $result = $rbac->createRole(
            $school_id,
            $data['role_name'] ?? '',
            $data['description'] ?? '',
            $data['permission_ids'] ?? []
        );
        echo json_encode($result);
        break;
        
    case 'update_role':
        $data = json_decode(file_get_contents('php://input'), true);
        $result = $rbac->updateRole(
            $data['role_id'] ?? 0,
            $school_id,
            $data['role_name'] ?? '',
            $data['description'] ?? ''
        );
        echo json_encode($result);
        break;
        
    case 'delete_role':
        $data = json_decode(file_get_contents('php://input'), true);
        $result = $rbac->deleteRole($data['role_id'] ?? 0, $school_id);
        echo json_encode($result);
        break;
        
    case 'save_permissions':
        $data = json_decode(file_get_contents('php://input'), true);
        $result = $rbac->saveRolePermissions(
            $data['role_id'] ?? 0,
            $school_id,
            $data['permission_ids'] ?? []
        );
        echo json_encode($result);
        break;
        
    case 'get_permissions':
        $permissions = $rbac->getPermissionsByCategory();
        echo json_encode(['success' => true, 'data' => $permissions]);
        break;
        
    case 'get_teacher_roles':
        $teacher_id = $_GET['teacher_id'] ?? 0;
        if (!$teacher_id) {
            echo json_encode(['success' => false, 'message' => 'Teacher ID required']);
            break;
        }
        $roles = $rbac->getUserRoles($teacher_id, $school_id);
        echo json_encode(['success' => true, 'data' => $roles]);
        break;
        
    case 'assign_role':
        $data = json_decode(file_get_contents('php://input'), true);
        $result = $rbac->assignRoleToTeacher(
            $data['teacher_id'] ?? 0,
            $data['role_id'] ?? 0,
            $school_id,
            $teacher_id
        );
        echo json_encode($result);
        break;
        
    case 'remove_role':
        $data = json_decode(file_get_contents('php://input'), true);
        $result = $rbac->removeRoleFromTeacher(
            $data['teacher_id'] ?? 0,
            $data['role_id'] ?? 0,
            $school_id
        );
        echo json_encode($result);
        break;
        
    case 'get_teachers':
        $query = "
            SELECT t.id, t.firstname, t.lastname, t.email, 
                   GROUP_CONCAT(DISTINCT r.role_name SEPARATOR ', ') as role_names,
                   COUNT(DISTINCT tr.role_id) as role_count
            FROM tblteachers t
            LEFT JOIN tblteacher_roles tr ON t.id = tr.teacher_id AND tr.school_id = t.school_id
            LEFT JOIN tblroles r ON tr.role_id = r.id
            WHERE t.school_id = ? AND t.is_deleted = 0
            GROUP BY t.id
            ORDER BY t.firstname, t.lastname
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $school_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $teachers = [];
        while ($row = $result->fetch_assoc()) {
            $teachers[] = $row;
        }
        $stmt->close();
        
        echo json_encode(['success' => true, 'data' => $teachers]);
        break;
        
    case 'check_permission':
        $permission_key = $_GET['permission'] ?? '';
        $has = hasPermission($permission_key);
        echo json_encode(['success' => true, 'has_permission' => $has]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

$conn->close();