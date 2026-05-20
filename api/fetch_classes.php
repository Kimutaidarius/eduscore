<?php
// Enable detailed error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session with proper configuration
session_start([
    'cookie_lifetime' => 86400, // 24 hours
    'read_and_close'  => false,
]);

// Set headers for JSON response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . (isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*'));
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Headers: X-Requested-With, Content-Type, Accept, Origin, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Log session info for debugging
error_log("API Request: " . $_SERVER['REQUEST_METHOD'] . " " . $_SERVER['REQUEST_URI']);
error_log("Session ID: " . session_id());
error_log("Session Data: " . json_encode($_SESSION));

// Check authentication
if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['school_id'])) {
    error_log("Authentication failed - Missing session variables");
    error_log("teacher_id exists: " . (isset($_SESSION['teacher_id']) ? 'Yes' : 'No'));
    error_log("school_id exists: " . (isset($_SESSION['school_id']) ? 'Yes' : 'No'));
    
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized: Session expired or not authenticated',
        'session_status' => [
            'has_session' => (session_status() === PHP_SESSION_ACTIVE),
            'session_id' => session_id(),
            'session_keys' => array_keys($_SESSION)
        ]
    ]);
    exit();
}

// Include database helper
require_once 'db_helper.php';

class FetchClasses extends DBHelper {

    public function __construct() {
        parent::__construct();

        // Allow both GET and POST methods
        if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
            $this->sendError('Method not allowed', 405);
        }

        $this->handleRequest();
    }

    private function handleRequest() {
        try {
            // Get parameters
            $academicLevel = isset($_REQUEST['academic_level']) ? $_REQUEST['academic_level'] : null;
            $academicLevelId = isset($_REQUEST['academic_level_id']) ? $_REQUEST['academic_level_id'] : null;

            // Use academic level from session if not provided
            if (!$academicLevel && isset($_SESSION['academic_level'])) {
                $academicLevel = $_SESSION['academic_level'];
            }

            if (!$academicLevel) {
                $this->sendError('Academic level is required', 400);
            }

            error_log("Fetching classes for academic level: " . $academicLevel);
            error_log("School ID: " . $this->school_id);

$sql = "
    SELECT 
        id,
        class_level AS display_name
    FROM tblclasses
    WHERE school_id = ?
      AND academic_level = ?
    ORDER BY class_level ASC
";

$params = [
    $this->school_id,
    $academicLevel
];

            $classes = $this->fetchAll($sql, $params);

            error_log("Found " . count($classes) . " classes");

            $this->sendResponse($classes, 'Classes fetched successfully');

        } catch (Exception $e) {
            error_log("FetchClasses Error: " . $e->getMessage());
            $this->sendError('Failed to fetch classes: ' . $e->getMessage(), 500);
        }
    }
}

// Instantiate the class
new FetchClasses();
?>