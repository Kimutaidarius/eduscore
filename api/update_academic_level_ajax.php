<?php
// Enable error logging but don't display errors
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json');

// Function to send JSON response and exit
function sendJsonResponse($success, $message = '', $data = []) {
    $response = ['success' => $success];
    if ($message) $response['message'] = $message;
    if (!empty($data)) $response = array_merge($response, $data);
    echo json_encode($response);
    exit;
}

// Check if academic_level was provided
if (!isset($_POST['academic_level']) && !isset($_GET['academic_level'])) {
    sendJsonResponse(false, 'No academic level provided');
}

$academic_level = $_POST['academic_level'] ?? $_GET['academic_level'];

// Validate academic level
$valid_levels = ['primary', 'junior_secondary', 'senior_secondary', 'college'];
if (!in_array($academic_level, $valid_levels)) {
    sendJsonResponse(false, 'Invalid academic level');
}

// Update session
$_SESSION['academic_level'] = $academic_level;

// Try to update database if user is logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['school_id'])) {
    try {
        // Fix the config path - adjust based on your actual file structure
        $configPaths = [
            __DIR__ . '/../includes/config.php',
            __DIR__ . '/../config/config.php',
            __DIR__ . '/../../includes/config.php',
            __DIR__ . '/../../config/config.php'
        ];
        
        $db = null;
        foreach ($configPaths as $path) {
            if (file_exists($path)) {
                require_once $path;
                if (isset($db) && $db instanceof PDO) {
                    break;
                }
            }
        }
        
        if (isset($db) && $db instanceof PDO) {
            // Determine which table to update based on user role
            $table = 'users'; // default table
            $id_column = 'id';
            
            if (isset($_SESSION['user_role'])) {
                $role = strtolower($_SESSION['user_role']);
                if (strpos($role, 'teacher') !== false) {
                    $table = 'tblteachers';
                    $id_column = 'id';
                } elseif (strpos($role, 'student') !== false) {
                    $table = 'tblstudents';
                    $id_column = 'id';
                } else {
                    $table = 'users';
                    $id_column = 'id';
                }
            }
            
            // Check if academic_level column exists
            $stmt = $db->prepare("SHOW COLUMNS FROM `$table` LIKE 'academic_level'");
            $stmt->execute();
            $columnExists = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($columnExists) {
                $stmt = $db->prepare("UPDATE `$table` SET academic_level = :academic_level WHERE $id_column = :user_id AND school_id = :school_id");
                $stmt->execute([
                    ':academic_level' => $academic_level,
                    ':user_id' => $_SESSION['user_id'],
                    ':school_id' => $_SESSION['school_id']
                ]);
            }
        }
    } catch (Exception $e) {
        error_log("Failed to save academic level to database: " . $e->getMessage());
        // Don't fail the request - session is already updated
    }
}

sendJsonResponse(true, 'Academic level updated successfully', ['academic_level' => $academic_level]);
?>