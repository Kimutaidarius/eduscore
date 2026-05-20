<?php
/**
 * API: Parents Login
 * Endpoint: /api/parents_login.php
 * Method: POST
 */

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Database configuration
define('DB_HOST', 'sql107.infinityfree.com');
define('DB_USERNAME', 'if0_41566747');
define('DB_PASSWORD', 'Bit06882020');
define('DB_NAME', 'if0_41566747_srms');

// Database connection
$database = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($database->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $database->connect_error]);
    exit();
}

// Allow big selects to prevent MAX_JOIN_SIZE error
$database->query("SET SQL_BIG_SELECTS=1");

// Get and validate input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
    $database->close();
    exit();
}

$phone = isset($input['phone']) ? trim($input['phone']) : '';
$password = isset($input['password']) ? $input['password'] : '';

// Validate phone number
if (empty($phone)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Phone number is required']);
    $database->close();
    exit();
}

// Clean phone number
$phone = preg_replace('/\D/', '', $phone);

// Validate Kenyan phone number format
if (!preg_match('/^(07|01)\d{8}$/', $phone)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid phone number format. Use 0712345678']);
    $database->close();
    exit();
}

// Validate password
if (empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Password is required']);
    $database->close();
    exit();
}

/**
 * Create parents table if it doesn't exist
 */
function createParentsTable($database) {
    $createTable = "CREATE TABLE IF NOT EXISTS `parents` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `parent_id` varchar(20) NOT NULL,
        `phone` varchar(20) NOT NULL,
        `password_hash` varchar(255) NOT NULL,
        `full_name` varchar(255) DEFAULT NULL,
        `email` varchar(255) DEFAULT NULL,
        `status` enum('active','inactive','suspended') NOT NULL DEFAULT 'active',
        `last_login` datetime DEFAULT NULL,
        `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `idx_parent_id` (`parent_id`),
        UNIQUE KEY `idx_phone` (`phone`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    return $database->query($createTable);
}

/**
 * Check if phone number exists as a guardian in any student record
 */
function isPhoneValidGuardian($database, $phone) {
    $stmt = $database->prepare("SELECT COUNT(*) as count FROM tblstudents WHERE GuardianPhone = ? AND Status = 'Active'");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['count'] > 0;
}

/**
 * Get or create parent account from student's guardian phone
 */
function getOrCreateParent($database, $phone, $password) {
    // First, check if parent already exists in parents table
    $stmt = $database->prepare("SELECT id, parent_id, phone, password_hash, full_name, status FROM parents WHERE phone = ?");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $stmt->close();
        
        // Verify password
        if (!password_verify($password, $row['password_hash'])) {
            return ['success' => false, 'message' => 'Invalid password'];
        }
        
        if ($row['status'] !== 'active') {
            return ['success' => false, 'message' => 'Account is not active. Please contact support.'];
        }
        
        // Update last login
        $updateStmt = $database->prepare("UPDATE parents SET last_login = NOW() WHERE id = ?");
        $updateStmt->bind_param("i", $row['id']);
        $updateStmt->execute();
        $updateStmt->close();
        
        return [
            'success' => true, 
            'parent' => [
                'id' => $row['id'],
                'parent_id' => $row['parent_id'],
                'phone' => $row['phone'],
                'full_name' => $row['full_name']
            ]
        ];
    }
    $stmt->close();
    
    // Parent doesn't exist - check if this phone number exists as a guardian
    if (!isPhoneValidGuardian($database, $phone)) {
        return ['success' => false, 'message' => 'No student found associated with this phone number. Please contact your child\'s school to register your number.'];
    }
    
    // Get guardian name from first student record
    $stmt = $database->prepare("SELECT GuardianName FROM tblstudents WHERE GuardianPhone = ? AND Status = 'Active' LIMIT 1");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $result = $stmt->get_result();
    $guardianData = $result->fetch_assoc();
    $stmt->close();
    
    $guardianName = $guardianData['GuardianName'] ?? 'Parent';
    
    // Create new parent account
    $parentId = 'PRT' . str_pad((string)random_int(0, 99999), 5, '0', STR_PAD_LEFT);
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    $insertStmt = $database->prepare("
        INSERT INTO parents (parent_id, phone, password_hash, full_name, status, created_at) 
        VALUES (?, ?, ?, ?, 'active', NOW())
    ");
    $insertStmt->bind_param("ssss", $parentId, $phone, $hashedPassword, $guardianName);
    
    if ($insertStmt->execute()) {
        $newParentId = $insertStmt->insert_id;
        $insertStmt->close();
        
        return [
            'success' => true,
            'parent' => [
                'id' => $newParentId,
                'parent_id' => $parentId,
                'phone' => $phone,
                'full_name' => $guardianName
            ]
        ];
    }
    
    $insertStmt->close();
    return ['success' => false, 'message' => 'Failed to create account'];
}

/**
 * Get all children (students) for a parent based on phone number
 * Optimized query to avoid MAX_JOIN_SIZE error
 */
function getParentChildren($database, $phone) {
    // First get all student IDs for this guardian
    $studentIds = [];
    $stmt = $database->prepare("SELECT id, school_id, AdmNo, FirstName, SecondName, LastName, Gender, GuardianName, GuardianRelationship, GuardianPhone, BoardingStatus, Status, academic_year, class_id, StreamId FROM tblstudents WHERE GuardianPhone = ? AND Status = 'Active'");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $studentsResult = $stmt->get_result();
    
    $students = [];
    while ($student = $studentsResult->fetch_assoc()) {
        $students[] = $student;
        $studentIds[] = $student['id'];
    }
    $stmt->close();
    
    if (empty($students)) {
        return [];
    }
    
    // Get school info for each unique school_id
    $schoolIds = array_unique(array_column($students, 'school_id'));
    $schoolInfo = [];
    
    if (!empty($schoolIds)) {
        $placeholders = implode(',', array_fill(0, count($schoolIds), '?'));
        $types = str_repeat('i', count($schoolIds));
        $stmt = $database->prepare("SELECT id, school_name, school_logo, school_phone, school_email FROM tblschoolinfo WHERE id IN ($placeholders)");
        $stmt->bind_param($types, ...$schoolIds);
        $stmt->execute();
        $schoolsResult = $stmt->get_result();
        while ($school = $schoolsResult->fetch_assoc()) {
            $schoolInfo[$school['id']] = $school;
        }
        $stmt->close();
    }
    
    // Get class info for each student
    $classIds = array_filter(array_unique(array_column($students, 'class_id')));
    $classInfo = [];
    
    if (!empty($classIds)) {
        $placeholders = implode(',', array_fill(0, count($classIds), '?'));
        $types = str_repeat('i', count($classIds));
        $stmt = $database->prepare("SELECT id, class_level, academic_level FROM tblclasses WHERE id IN ($placeholders)");
        $stmt->bind_param($types, ...$classIds);
        $stmt->execute();
        $classesResult = $stmt->get_result();
        while ($class = $classesResult->fetch_assoc()) {
            $classInfo[$class['id']] = $class;
        }
        $stmt->close();
    }
    
    // Get stream info for each student
    $streamIds = array_filter(array_unique(array_column($students, 'StreamId')));
    $streamInfo = [];
    
    if (!empty($streamIds)) {
        $placeholders = implode(',', array_fill(0, count($streamIds), '?'));
        $types = str_repeat('i', count($streamIds));
        $stmt = $database->prepare("SELECT id, stream_name FROM tblstreams WHERE id IN ($placeholders)");
        $stmt->bind_param($types, ...$streamIds);
        $stmt->execute();
        $streamsResult = $stmt->get_result();
        while ($stream = $streamsResult->fetch_assoc()) {
            $streamInfo[$stream['id']] = $stream;
        }
        $stmt->close();
    }
    
    // Build children array
    $children = [];
    foreach ($students as $student) {
        $class = isset($classInfo[$student['class_id']]) ? $classInfo[$student['class_id']] : ['class_level' => 'N/A', 'academic_level' => 'N/A'];
        $stream = isset($streamInfo[$student['StreamId']]) ? $streamInfo[$student['StreamId']] : ['stream_name' => ''];
        $school = isset($schoolInfo[$student['school_id']]) ? $schoolInfo[$student['school_id']] : ['school_name' => 'Unknown School', 'school_logo' => null, 'school_phone' => null, 'school_email' => null];
        
        $children[] = [
            'student_id' => $student['id'],
            'admission_no' => $student['AdmNo'],
            'first_name' => $student['FirstName'],
            'second_name' => $student['SecondName'],
            'last_name' => $student['LastName'],
            'full_name' => trim($student['FirstName'] . ' ' . ($student['LastName'] ?? '')),
            'gender' => $student['Gender'],
            'relationship' => $student['GuardianRelationship'],
            'class' => $class['class_level'],
            'academic_level' => $class['academic_level'],
            'stream' => $stream['stream_name'],
            'academic_year' => $student['academic_year'],
            'school_id' => $student['school_id'],
            'school_name' => $school['school_name'],
            'school_logo' => $school['school_logo'],
            'school_phone' => $school['school_phone'],
            'school_email' => $school['school_email'],
            'boarding_status' => $student['BoardingStatus']
        ];
    }
    
    return $children;
}

/**
 * Log the login attempt
 */
function logLoginAttempt($database, $phone, $status, $ipAddress) {
    $stmt = $database->prepare("
        INSERT INTO parents_login_logs (phone, status, ip_address, user_agent, created_at) 
        VALUES (?, ?, ?, ?, NOW())
    ");
    $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    $stmt->bind_param("ssss", $phone, $status, $ipAddress, $userAgent);
    $stmt->execute();
    $stmt->close();
}

// Create tables if they don't exist
createParentsTable($database);

// Also create login logs table
$database->query("
    CREATE TABLE IF NOT EXISTS `parents_login_logs` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `phone` varchar(20) NOT NULL,
        `status` enum('success','failed') NOT NULL,
        `ip_address` varchar(45) DEFAULT NULL,
        `user_agent` text DEFAULT NULL,
        `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_phone` (`phone`),
        KEY `idx_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Create sessions table
$database->query("
    CREATE TABLE IF NOT EXISTS `parents_sessions` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `parent_id` int(11) NOT NULL,
        `session_token` varchar(64) NOT NULL,
        `ip_address` varchar(45) DEFAULT NULL,
        `user_agent` text DEFAULT NULL,
        `expires_at` datetime NOT NULL,
        `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `idx_session_token` (`session_token`),
        KEY `idx_parent_id` (`parent_id`),
        KEY `idx_expires_at` (`expires_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';

// Get or create parent account and verify password
$parentResult = getOrCreateParent($database, $phone, $password);

if (!$parentResult['success']) {
    logLoginAttempt($database, $phone, 'failed', $ipAddress);
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => $parentResult['message']]);
    $database->close();
    exit();
}

// Get all children for this parent
$children = getParentChildren($database, $phone);

if (empty($children)) {
    logLoginAttempt($database, $phone, 'failed', $ipAddress);
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'No students found associated with this phone number']);
    $database->close();
    exit();
}

// Generate session token for the parent
$sessionToken = bin2hex(random_bytes(32));
$expiresAt = date('Y-m-d H:i:s', time() + (7 * 24 * 60 * 60)); // 7 days

// Store session in database
$stmt = $database->prepare("
    INSERT INTO parents_sessions (parent_id, session_token, ip_address, user_agent, expires_at, created_at) 
    VALUES (?, ?, ?, ?, ?, NOW())
");
$userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
$stmt->bind_param("issss", $parentResult['parent']['id'], $sessionToken, $ipAddress, $userAgent, $expiresAt);
$stmt->execute();
$stmt->close();

// Set session variables
$_SESSION['parent_logged_in'] = true;
$_SESSION['parent_id'] = $parentResult['parent']['id'];
$_SESSION['parent_phone'] = $phone;
$_SESSION['parent_name'] = $parentResult['parent']['full_name'];
$_SESSION['parent_session_token'] = $sessionToken;

logLoginAttempt($database, $phone, 'success', $ipAddress);

// Return success response
http_response_code(200);
echo json_encode([
    'success' => true,
    'message' => 'Login successful',
    'data' => [
        'parent_id' => $parentResult['parent']['parent_id'],
        'phone' => $parentResult['parent']['phone'],
        'full_name' => $parentResult['parent']['full_name'],
        'session_token' => $sessionToken,
        'children' => $children
    ]
]);

$database->close();
?>