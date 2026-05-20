<?php
declare(strict_types=1);

// Start output buffering immediately to catch any warnings
ob_start();

session_start();

// Disable error display for AJAX responses
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

// Create logs directory if it doesn't exist
$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
ini_set('error_log', $logDir . '/ajax_errors.log');

require_once '../includes/config.php';
require_once '../includes/EmailHelper.php';

/* =========================
   BASIC RESPONSE HELPERS
========================= */
function jsonResponse(array $data, int $code = 200): void {
    // Clear any previous output (warnings, notices, etc.)
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Start fresh output buffer
    ob_start();
    
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
    
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    ob_end_flush();
    exit;
}

function fail(string $message, int $code = 500): void {
    jsonResponse(['success' => false, 'message' => $message], $code);
}

/* =========================
   AUTH CHECK
========================= */
if (
    empty($_SESSION['authenticated']) ||
    $_SESSION['authenticated'] !== true ||
    empty($_SESSION['school_id'])
) {
    fail('Unauthorized', 401);
}

$school_id = (int) $_SESSION['school_id'];
$action    = $_GET['action'] ?? $_POST['action'] ?? '';

/* =========================
   DB ACCESS
========================= */
function db(): PDO {
    global $dbh;
    if (!$dbh instanceof PDO) {
        fail('Database not connected');
    }
    return $dbh;
}

/* =========================
   ROUTER
========================= */
try {
    switch ($action) {
        case 'fetch_teachers':
            fetchTeachers();
            break;

        case 'fetch_roles':
            fetchRoles();
            break;

        case 'fetch_subjects':
            fetchSubjects();
            break;

        case 'fetch_teacher_subjects':
            fetchTeacherSubjects();
            break;

        case 'add_teacher':
            addTeacher();
            break;

        case 'update_teacher':
            updateTeacher();
            break;

        case 'delete_teacher':
            deleteTeacher();
            break;

        case 'assign_subjects':
            assignSubjects();
            break;
            
        case 'update_teacher_role':
            updateTeacherRole();
            break;
            
        case 'import_teachers':
            importTeachers();
            break;

        default:
            fail('Invalid action', 400);
    }

} catch (Throwable $e) {
    error_log($e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
    fail('Server error: ' . $e->getMessage());
}

/* =========================
   ACTIONS
========================= */

/**
 * Fetch all teachers
 */
function fetchTeachers(): void {
    global $school_id;

    $sql = "
        SELECT *
        FROM tblteachers
        WHERE school_id = :school
          AND deleted_at IS NULL
        ORDER BY 
          CASE 
            WHEN role IN ('Super Admin', 'Admin', 'Administrator') THEN 0
            ELSE 1
          END,
          firstname, lastname
    ";

    $stmt = db()->prepare($sql);
    $stmt->execute(['school' => $school_id]);
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Remove the subjects subquery from here - let frontend load them separately
    // This reduces the initial load time and prevents nested query issues

    jsonResponse(['success' => true, 'data' => $teachers]);
}

/**
 * Fetch available roles
 */
function fetchRoles(): void {
    // Return available roles for dropdown - only Teacher and ICT Teacher
    $roles = [
        ['role_name' => 'Teacher'],
        ['role_name' => 'ICT Teacher']
    ];

    jsonResponse(['success' => true, 'data' => $roles]);
}

/**
 * Fetch all subjects
 */
function fetchSubjects(): void {
    global $school_id;

    $stmt = db()->prepare("
        SELECT id, subject_name
        FROM tblsubjects
        WHERE school_id = :school AND deleted_at IS NULL
        ORDER BY subject_name
    ");
    $stmt->execute(['school' => $school_id]);

    jsonResponse(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

/**
 * Fetch subjects assigned to a teacher
 */
function fetchTeacherSubjects(): void {
    global $school_id;

    $teacher_id = (int) ($_GET['teacher_id'] ?? 0);
    if (!$teacher_id) {
        fail('Teacher ID required', 400);
    }

    // Verify teacher exists
    $teacherCheck = db()->prepare("SELECT id FROM tblteachers WHERE id = :tid AND school_id = :school AND deleted_at IS NULL");
    $teacherCheck->execute([
        'tid'    => $teacher_id,
        'school' => $school_id
    ]);
    
    if (!$teacherCheck->fetch()) {
        fail('Teacher not found', 404);
    }

    $stmt = db()->prepare("
        SELECT s.id, s.subject_name
        FROM teacher_subjects ts
        JOIN tblsubjects s ON s.id = ts.subject_id
        WHERE ts.teacher_id = :tid
          AND ts.school_id = :school
          AND ts.deleted_at IS NULL
          AND s.deleted_at IS NULL
    ");
    $stmt->execute([
        'tid'    => $teacher_id,
        'school' => $school_id
    ]);

    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    jsonResponse(['success' => true, 'data' => $subjects]);
}

/**
 * Update teacher role
 */
function updateTeacherRole(): void {
    global $school_id;
    
    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    
    $teacher_id = (int)($input['teacher_id'] ?? 0);
    $role = $input['role'] ?? '';
    
    if (!$teacher_id || empty($role)) {
        fail('Teacher ID and role required', 400);
    }
    
    try {
        // Check if teacher is protected
        $check = db()->prepare("SELECT role, firstname, lastname FROM tblteachers WHERE id = ? AND school_id = ? AND deleted_at IS NULL");
        $check->execute([$teacher_id, $school_id]);
        $teacher = $check->fetch(PDO::FETCH_ASSOC);
        
        if (!$teacher) {
            fail('Teacher not found', 404);
        }
        
        // Prevent modifying Super Admin role
        if ($teacher['role'] === 'Super Admin' && $role !== 'Super Admin') {
            fail('Cannot change Super Admin role', 403);
        }
        
        // Update teacher's role
        $stmt = db()->prepare("
            UPDATE tblteachers 
            SET role = :role
            WHERE id = :teacher_id 
            AND school_id = :school_id
            AND deleted_at IS NULL
        ");
        
        $stmt->execute([
            ':role' => $role,
            ':teacher_id' => $teacher_id,
            ':school_id' => $school_id
        ]);
        
        jsonResponse([
            'success' => true,
            'message' => 'Role updated successfully'
        ]);
        
    } catch (PDOException $e) {
        error_log('Error in updateTeacherRole: ' . $e->getMessage());
        fail('Database error: ' . $e->getMessage());
    }
}

/**
 * Add a new teacher
 */
function addTeacher(): void {
    global $school_id;

    $required = ['firstname', 'lastname', 'email', 'phonenumber', 'gender', 'role', 'status'];
    foreach ($required as $f) {
        if (empty($_POST[$f])) fail("$f is required", 400);
    }

    // Validate email format
    if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        fail('Invalid email format', 400);
    }

    // Check if teacher with this email already exists
    $check = db()->prepare("SELECT id FROM tblteachers WHERE email = ? AND school_id = ? AND deleted_at IS NULL");
    $check->execute([$_POST['email'], $school_id]);
    if ($check->fetch()) {
        fail('A teacher with this email already exists', 400);
    }

    // Check if teacher with this phone number already exists
    $checkPhone = db()->prepare("SELECT id FROM tblteachers WHERE phonenumber = ? AND school_id = ? AND deleted_at IS NULL");
    $checkPhone->execute([$_POST['phonenumber'], $school_id]);
    if ($checkPhone->fetch()) {
        fail('A teacher with this phone number already exists', 400);
    }

    // Generate a unique teacher number
    $teacher_number = generateUniqueTeacherNumber($school_id);

    // Set default password
    $default_password = '@123';
    $hashed_password = password_hash($default_password, PASSWORD_DEFAULT);

    $sql = "
        INSERT INTO tblteachers
        (teacher_number, school_id, firstname, middle_name, lastname, email, phonenumber, 
         gender, role, status, title, secondname, password, plain_password, created_at)
        VALUES
        (:num, :school, :fn, :mn, :ln, :email, :phone, :gender, :role, :status, :title, :secondname, :password, :plain_password, NOW())
    ";

    try {
        $pdo = db();
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'num'            => $teacher_number,
            'school'         => $school_id,
            'fn'             => $_POST['firstname'],
            'mn'             => $_POST['middlename'] ?? '',
            'ln'             => $_POST['lastname'],
            'email'          => $_POST['email'],
            'phone'          => $_POST['phonenumber'],
            'gender'         => $_POST['gender'],
            'role'           => $_POST['role'],
            'status'         => $_POST['status'],
            'title'          => $_POST['title'] ?? '',
            'secondname'     => $_POST['secondname'] ?? '',
            'password'       => $hashed_password,
            'plain_password' => $default_password
        ]);

        // Get the newly created teacher ID
        $teacher_id = (int)$pdo->lastInsertId();
        
        $pdo->commit();
        
        // Prepare teacher name for email
        $teacher_name = trim($_POST['firstname'] . ' ' . ($_POST['middlename'] ?? '') . ' ' . $_POST['lastname']);
        
        // Send welcome email (don't block the response if email fails)
        try {
            $email_sent = sendWelcomeEmail($_POST['email'], $teacher_name, $teacher_number, $default_password);
            $message = 'Teacher added successfully';
            if ($email_sent) {
                $message .= ' and login credentials sent to ' . $_POST['email'];
            } else {
                $message .= ' but email notification could not be sent. Default password: @123';
            }
        } catch (Exception $emailError) {
            error_log("Failed to send welcome email: " . $emailError->getMessage());
            $message = 'Teacher added successfully but email notification could not be sent. Default password: @123';
        }

        jsonResponse(['success' => true, 'message' => $message, 'teacher_id' => $teacher_id]);
        
    } catch (PDOException $e) {
        if (isset($pdo)) {
            $pdo->rollBack();
        }
        error_log('Database error in addTeacher: ' . $e->getMessage());
        fail('Database error: ' . $e->getMessage());
    }
}

/**
 * Generate a unique teacher number
 */
function generateUniqueTeacherNumber(int $school_id): string {
    $year = date('Y');
    $max_attempts = 50;
    $attempt = 0;
    
    while ($attempt < $max_attempts) {
        if ($attempt > 0) {
            // Generate random number
            $random = mt_rand(1, 9999);
            $teacher_number = 'TCH-' . $year . '-' . str_pad((string)$random, 4, '0', STR_PAD_LEFT);
        } else {
            // First try: get the highest existing number for this year
            $stmt = db()->prepare("
                SELECT teacher_number 
                FROM tblteachers 
                WHERE school_id = ? 
                  AND teacher_number LIKE ? 
                  AND deleted_at IS NULL
                ORDER BY CAST(SUBSTRING_INDEX(teacher_number, '-', -1) AS UNSIGNED) DESC 
                LIMIT 1
            ");
            $stmt->execute([$school_id, "TCH-{$year}-%"]);
            $last_number = $stmt->fetchColumn();
            
            if ($last_number) {
                $parts = explode('-', $last_number);
                $last_sequence = (int)end($parts);
                $next_sequence = $last_sequence + 1;
                $teacher_number = 'TCH-' . $year . '-' . str_pad((string)$next_sequence, 4, '0', STR_PAD_LEFT);
            } else {
                $teacher_number = 'TCH-' . $year . '-0001';
            }
        }
        
        // Check if this number already exists
        $check = db()->prepare("SELECT id FROM tblteachers WHERE teacher_number = ? AND school_id = ? AND deleted_at IS NULL");
        $check->execute([$teacher_number, $school_id]);
        
        if (!$check->fetch()) {
            return $teacher_number;
        }
        
        $attempt++;
    }
    
    // Fallback
    return 'TCH-' . $year . '-' . date('His') . str_pad((string)mt_rand(1, 99), 2, '0', STR_PAD_LEFT);
}

/**
 * Send welcome email to new teacher
 */
function sendWelcomeEmail(string $to_email, string $teacher_name, string $teacher_number, string $password): bool {
    try {
        if (!class_exists('EmailHelper')) {
            error_log("EmailHelper class not found");
            return false;
        }
        $emailHelper = new EmailHelper();
        return $emailHelper->sendWelcomeEmail($to_email, $teacher_name, $teacher_number, $password);
    } catch (Exception $e) {
        error_log("Welcome email could not be sent to {$to_email}. Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Update teacher information
 */
function updateTeacher(): void {
    global $school_id;

    $id = (int)($_POST['teacher_id'] ?? 0);
    if (!$id) fail('Teacher ID required', 400);

    // Check if teacher exists and is not deleted
    $check = db()->prepare("SELECT role, firstname, lastname, email FROM tblteachers WHERE id = ? AND school_id = ? AND deleted_at IS NULL");
    $check->execute([$id, $school_id]);
    $teacher = $check->fetch(PDO::FETCH_ASSOC);
    
    if (!$teacher) {
        fail('Teacher not found or has been deleted', 404);
    }
    
    // Check if this is a protected account
    if (in_array($teacher['role'], ['Super Admin', 'Admin', 'Administrator'])) {
        fail('This teacher account is protected and cannot be modified', 403);
    }

    // If email is being changed, check if it's already taken
    if (isset($_POST['email']) && $_POST['email'] !== $teacher['email']) {
        $checkEmail = db()->prepare("SELECT id FROM tblteachers WHERE email = ? AND school_id = ? AND id != ? AND deleted_at IS NULL");
        $checkEmail->execute([$_POST['email'], $school_id, $id]);
        if ($checkEmail->fetch()) {
            fail('Email already exists for another teacher', 400);
        }
    }

    $fields = [];
    $params = ['id' => $id, 'school' => $school_id];

    // Handle regular fields
    $allowedFields = [
        'firstname' => 'firstname',
        'middle_name' => 'middlename',
        'lastname' => 'lastname',
        'email' => 'email',
        'phonenumber' => 'phonenumber',
        'gender' => 'gender',
        'status' => 'status',
        'title' => 'title',
        'secondname' => 'secondname'
    ];
    
    foreach ($allowedFields as $dbField => $formField) {
        if (isset($_POST[$formField])) {
            $fields[] = "$dbField = :$dbField";
            $params[$dbField] = $_POST[$formField] === '' ? null : $_POST[$formField];
        }
    }
    
    // Handle role field (if allowed)
    if (isset($_POST['role']) && !in_array($teacher['role'], ['Super Admin', 'Admin', 'Administrator'])) {
        $fields[] = "role = :role";
        $params['role'] = $_POST['role'] === '' ? null : $_POST['role'];
    }

    if (empty($fields)) {
        fail('Nothing to update', 400);
    }

    try {
        $pdo = db();
        $pdo->beginTransaction();
        
        $sql = "UPDATE tblteachers SET " . implode(',', $fields) . ", updated_at = NOW() WHERE id = :id AND school_id = :school AND deleted_at IS NULL";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        $pdo->commit();
        
        jsonResponse(['success' => true, 'message' => 'Teacher updated successfully']);
        
    } catch (PDOException $e) {
        if (isset($pdo)) {
            $pdo->rollBack();
        }
        error_log('Database error in updateTeacher: ' . $e->getMessage());
        fail('Database error: ' . $e->getMessage());
    }
}

/**
 * Permanently delete a teacher
 */
function deleteTeacher(): void {
    global $school_id;

    $id = (int)($_POST['teacher_id'] ?? 0);
    if (!$id) fail('Teacher ID required', 400);

    // Check if teacher is protected
    $check = db()->prepare("SELECT role, firstname, lastname FROM tblteachers WHERE id = ? AND school_id = ? AND deleted_at IS NULL");
    $check->execute([$id, $school_id]);
    $teacher = $check->fetch(PDO::FETCH_ASSOC);
    
    if (!$teacher) {
        fail('Teacher not found', 404);
    }
    
    if (in_array($teacher['role'], ['Super Admin', 'Admin', 'Administrator'])) {
        fail('This teacher account is protected and cannot be deleted', 403);
    }

    // Start transaction
    $pdo = db();
    $pdo->beginTransaction();
    
    try {
        // Delete associated subjects first
        $stmt2 = $pdo->prepare("
            DELETE FROM teacher_subjects
            WHERE teacher_id = ? AND school_id = ?
        ");
        $stmt2->execute([$id, $school_id]);

        // Delete the teacher
        $stmt1 = $pdo->prepare("
            DELETE FROM tblteachers
            WHERE id = ? AND school_id = ?
        ");
        $stmt1->execute([$id, $school_id]);
        
        $pdo->commit();
        
        jsonResponse(['success' => true, 'message' => 'Teacher permanently deleted successfully']);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('Database error in deleteTeacher: ' . $e->getMessage());
        fail('Database error: ' . $e->getMessage());
    }
}

/**
 * Import teachers from CSV/Excel
 */
function importTeachers(): void {
    global $school_id;
    
    // Check if file was uploaded
    if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
        fail('No file uploaded or upload error occurred', 400);
    }
    
    $file = $_FILES['excel_file'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // Validate file type
    if (!in_array($file_ext, ['csv', 'xlsx', 'xls'])) {
        fail('Invalid file type. Please upload CSV or Excel file.', 400);
    }
    
    try {
        // For simplicity, handle CSV files
        if ($file_ext === 'csv') {
            $handle = fopen($file['tmp_name'], 'r');
            if (!$handle) {
                fail('Could not read uploaded file', 500);
            }
            
            // Read header row
            $header = fgetcsv($handle);
            if (!$header) {
                fclose($handle);
                fail('Invalid CSV format', 400);
            }
            
            // Expected headers (case insensitive)
            $expectedHeaders = ['title', 'first name', 'middle name', 'last name', 'email', 'phone number', 'gender', 'role'];
            
            // Validate headers
            $headerMap = [];
            foreach ($header as $index => $colName) {
                $colLower = strtolower(trim($colName));
                foreach ($expectedHeaders as $expected) {
                    if (strpos($colLower, $expected) !== false) {
                        $headerMap[$expected] = $index;
                        break;
                    }
                }
            }
            
            // Check required fields
            $required = ['first name', 'last name', 'email', 'phone number'];
            $missing = [];
            foreach ($required as $req) {
                if (!isset($headerMap[$req])) {
                    $missing[] = $req;
                }
            }
            
            if (!empty($missing)) {
                fclose($handle);
                fail('Missing required columns: ' . implode(', ', $missing), 400);
            }
            
            $pdo = db();
            $pdo->beginTransaction();
            
            $imported = 0;
            $errors = [];
            $rowNum = 1;
            
            while (($row = fgetcsv($handle)) !== false) {
                $rowNum++;
                
                try {
                    // Extract data using header map
                    $firstname = trim($row[$headerMap['first name']] ?? '');
                    $lastname = trim($row[$headerMap['last name']] ?? '');
                    $email = trim($row[$headerMap['email']] ?? '');
                    $phonenumber = trim($row[$headerMap['phone number']] ?? '');
                    $title = isset($headerMap['title']) ? trim($row[$headerMap['title']] ?? '') : '';
                    $middlename = isset($headerMap['middle name']) ? trim($row[$headerMap['middle name']] ?? '') : '';
                    $gender = isset($headerMap['gender']) ? trim($row[$headerMap['gender']] ?? '') : '';
                    $role = isset($headerMap['role']) ? trim($row[$headerMap['role']] ?? '') : 'Teacher';
                    
                    // Validate required fields
                    if (empty($firstname) || empty($lastname) || empty($email) || empty($phonenumber)) {
                        $errors[] = "Row $rowNum: Missing required fields";
                        continue;
                    }
                    
                    // Validate email
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $errors[] = "Row $rowNum: Invalid email format - $email";
                        continue;
                    }
                    
                    // Check if teacher already exists
                    $check = $pdo->prepare("SELECT id FROM tblteachers WHERE email = ? AND school_id = ? AND deleted_at IS NULL");
                    $check->execute([$email, $school_id]);
                    if ($check->fetch()) {
                        $errors[] = "Row $rowNum: Teacher with email $email already exists";
                        continue;
                    }
                    
                    // Generate teacher number
                    $teacher_number = generateUniqueTeacherNumber($school_id);
                    
                    // Set default password
                    $default_password = '@123';
                    $hashed_password = password_hash($default_password, PASSWORD_DEFAULT);
                    
                    // Insert teacher
                    $sql = "
                        INSERT INTO tblteachers
                        (teacher_number, school_id, firstname, middle_name, lastname, email, phonenumber, 
                         gender, role, status, title, secondname, password, plain_password, created_at)
                        VALUES
                        (:num, :school, :fn, :mn, :ln, :email, :phone, :gender, :role, 'Active', :title, '', :password, :plain_password, NOW())
                    ";
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        'num' => $teacher_number,
                        'school' => $school_id,
                        'fn' => $firstname,
                        'mn' => $middlename,
                        'ln' => $lastname,
                        'email' => $email,
                        'phone' => $phonenumber,
                        'gender' => $gender,
                        'role' => $role,
                        'title' => $title,
                        'password' => $hashed_password,
                        'plain_password' => $default_password
                    ]);
                    
                    $imported++;
                    
                } catch (Exception $e) {
                    $errors[] = "Row $rowNum: " . $e->getMessage();
                }
            }
            
            fclose($handle);
            
            if ($imported > 0) {
                $pdo->commit();
                
                $message = "Successfully imported $imported teachers";
                if (!empty($errors)) {
                    $message .= " with " . count($errors) . " errors";
                }
                
                jsonResponse([
                    'success' => true,
                    'message' => $message,
                    'imported' => $imported,
                    'errors' => $errors
                ]);
            } else {
                $pdo->rollBack();
                fail('No teachers were imported. Errors: ' . implode('; ', $errors), 400);
            }
            
        } else {
            fail('Excel file support requires additional library. Please use CSV format.', 400);
        }
        
    } catch (PDOException $e) {
        if (isset($pdo)) {
            $pdo->rollBack();
        }
        error_log('Database error in importTeachers: ' . $e->getMessage());
        fail('Database error during import: ' . $e->getMessage());
    } catch (Exception $e) {
        error_log('Error in importTeachers: ' . $e->getMessage());
        fail('Error during import: ' . $e->getMessage());
    }
}

/**
 * Assign subjects to a teacher
 */
function assignSubjects(): void {
    global $school_id;

    $teacher_id = (int)($_POST['teacher_id'] ?? 0);
    $subjects = json_decode($_POST['subjects'] ?? '[]', true);

    if (!$teacher_id || !is_array($subjects)) {
        fail('Invalid data', 400);
    }

    // Check if teacher is protected
    $check = db()->prepare("SELECT role, firstname, lastname FROM tblteachers WHERE id = ? AND school_id = ? AND deleted_at IS NULL");
    $check->execute([$teacher_id, $school_id]);
    $teacherData = $check->fetch(PDO::FETCH_ASSOC);
    
    if (!$teacherData) {
        fail('Teacher not found', 404);
    }
    
    if (in_array($teacherData['role'], ['Super Admin', 'Admin', 'Administrator'])) {
        fail('Cannot assign subjects to protected admin accounts', 403);
    }

    $pdo = db();
    $pdo->beginTransaction();

    try {
        // Soft delete existing assignments
        $pdo->prepare("UPDATE teacher_subjects SET deleted_at = NOW() WHERE teacher_id = ? AND school_id = ? AND deleted_at IS NULL")
           ->execute([$teacher_id, $school_id]);

        // Insert new assignments
        $ins = $pdo->prepare("
            INSERT INTO teacher_subjects (teacher_id, subject_id, school_id, created_at)
            VALUES (?, ?, ?, NOW())
        ");

        foreach ($subjects as $sid) {
            $subject_id = (int)$sid;
            if ($subject_id > 0) {
                $ins->execute([$teacher_id, $subject_id, $school_id]);
            }
        }

        $pdo->commit();

        jsonResponse(['success' => true, 'message' => 'Subjects assigned successfully']);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('Database error in assignSubjects: ' . $e->getMessage());
        fail('Database error: ' . $e->getMessage());
    }
}
?>