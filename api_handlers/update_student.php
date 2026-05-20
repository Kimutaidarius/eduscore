<?php
// ==========================
// ✅ update_student.php - CORRECTED COLUMN NAMES
// ==========================

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// --- Start or resume session safely ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse([], 'error', 'Only POST requests allowed.');
    exit();
}

// Use includes/config.php
require_once '../includes/config.php';

// Function to send consistent JSON responses
function sendResponse($data = [], $status = 'success', $message = '', $studentId = null) {
    $response = [
        'status' => $status,
        'message' => $message,
        'data' => $data
    ];
    
    if ($studentId) {
        $response['student_id'] = $studentId;
    }
    
    echo json_encode($response);
    exit();
}

// Check authentication
$schoolId = $_SESSION['school_id'] ?? null;
if (!$schoolId) {
    sendResponse([], 'error', 'Authentication required.');
    exit();
}

// Get input data
$input = $_POST;

// If no form data, try to get JSON input
if (empty($input)) {
    $json_input = file_get_contents('php://input');
    $input = json_decode($json_input, true) ?? [];
}

// --- Sanitize inputs ---
$student_id = isset($input['student_id']) ? (int)$input['student_id'] : 0;
$first_name = trim($input['first_name'] ?? '');
$last_name = trim($input['last_name'] ?? '');
$admission_no = trim($input['admission_no'] ?? '');
$gender = trim($input['gender'] ?? '');
$class_id = trim($input['class_id'] ?? '');
$stream_id = trim($input['stream_id'] ?? '');
$admission_date = trim($input['admission_date'] ?? '');
$middle_name = trim($input['middle_name'] ?? '');
$guardian_name = trim($input['guardian_name'] ?? '');
$guardian_relation = trim($input['guardian_relation'] ?? '');
$guardian_contact = trim($input['guardian_contact'] ?? '');
$guardian_email = trim($input['guardian_email'] ?? '');

// Validate student_id
if ($student_id <= 0) {
    sendResponse([], 'error', 'Invalid student ID.');
    exit();
}

// Validate required fields
if (empty($first_name) || empty($last_name) || empty($admission_no) || empty($gender) || empty($class_id) || empty($admission_date)) {
    sendResponse([], 'error', 'Please fill in all required fields: First Name, Last Name, Admission Number, Gender, Class, and Admission Date.');
    exit();
}

// Handle file upload
$profile_pic_filename = null;
$hasNewPhoto = false;

if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    $photo = $_FILES['photo'];
    
    // Validate file type
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $file_type = mime_content_type($photo['tmp_name']);
    
    if (!in_array($file_type, $allowed_types)) {
        sendResponse([], 'error', 'Invalid file type. Only JPG, PNG, and GIF images are allowed.');
        exit();
    }
    
    // Validate file size (max 2MB)
    if ($photo['size'] > 2 * 1024 * 1024) {
        sendResponse([], 'error', 'File size too large. Maximum 2MB allowed.');
        exit();
    }
    
    // Generate unique filename
    $file_extension = pathinfo($photo['name'], PATHINFO_EXTENSION);
    $profile_pic_filename = uniqid() . '_' . time() . '.' . $file_extension;
    $upload_dir = __DIR__ . '/../uploads/students/';
    
    // Create directory if it doesn't exist
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $filepath = $upload_dir . $profile_pic_filename;
    
    // Move uploaded file
    if (!move_uploaded_file($photo['tmp_name'], $filepath)) {
        sendResponse([], 'error', 'Failed to upload profile picture.');
        exit();
    }
    
    $hasNewPhoto = true;
}

try {
    $dbh = $db; // Use database connection from config.php
    
    // First, get the current student data to check existing photo
    $stmt_check = $dbh->prepare("SELECT ProfilePic FROM tblstudents WHERE id = :id AND school_id = :school_id LIMIT 1");
    $stmt_check->bindParam(':id', $student_id, PDO::PARAM_INT);
    $stmt_check->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
    $stmt_check->execute();
    $current_student = $stmt_check->fetch(PDO::FETCH_ASSOC);
    
    if (!$current_student) {
        // Delete uploaded file if student not found
        if ($hasNewPhoto && isset($upload_dir) && file_exists($upload_dir . $profile_pic_filename)) {
            unlink($upload_dir . $profile_pic_filename);
        }
        sendResponse([], 'error', 'Student not found.');
        exit();
    }
    
    // Validate class
    $class_pk_id = (int)$class_id;
    $stmt_class = $dbh->prepare("SELECT id, class_level FROM tblclasses WHERE id = :class_id AND school_id = :school_id LIMIT 1");
    $stmt_class->bindParam(':class_id', $class_pk_id, PDO::PARAM_INT);
    $stmt_class->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
    $stmt_class->execute();
    $class_info = $stmt_class->fetch(PDO::FETCH_ASSOC);
    
    if (!$class_info) {
        if ($hasNewPhoto && isset($upload_dir) && file_exists($upload_dir . $profile_pic_filename)) {
            unlink($upload_dir . $profile_pic_filename);
        }
        sendResponse([], 'error', 'Invalid Class selected.');
        exit();
    }
    
    // Check admission number uniqueness (excluding current student)
    $stmt_check_admno = $dbh->prepare("SELECT id FROM tblstudents WHERE AdmNo = :admno AND school_id = :school_id AND id != :student_id LIMIT 1");
    $stmt_check_admno->bindParam(':admno', $admission_no);
    $stmt_check_admno->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
    $stmt_check_admno->bindParam(':student_id', $student_id, PDO::PARAM_INT);
    $stmt_check_admno->execute();
    
    if ($stmt_check_admno->fetch(PDO::FETCH_ASSOC)) {
        if ($hasNewPhoto && isset($upload_dir) && file_exists($upload_dir . $profile_pic_filename)) {
            unlink($upload_dir . $profile_pic_filename);
        }
        sendResponse([], 'error', 'Admission Number already exists. Please use a different admission number.');
        exit();
    }
    
    // Validate stream if provided
    $stream_pk_id = null;
    if (!empty($stream_id)) {
        $stream_pk_id_int = (int)$stream_id;
        $stmt_stream = $dbh->prepare("SELECT id FROM tblstreams WHERE id = :stream_id AND school_id = :school_id LIMIT 1");
        $stmt_stream->bindParam(':stream_id', $stream_pk_id_int, PDO::PARAM_INT);
        $stmt_stream->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
        $stmt_stream->execute();
        $valid_stream = $stmt_stream->fetchColumn();
        
        if ($valid_stream) {
            $stream_pk_id = $valid_stream;
        }
    }
    
    // Extract year from admission number for academic_year field
    $academic_year = date('Y');
    if (preg_match('/\/(\d{4})$/', $admission_no, $year_matches)) {
        $academic_year = $year_matches[1];
    }
    
    // CORRECTED: Use proper column names from your table
    // - guardian_email (lowercase with underscore) NOT GuardianEmail
    // - GuardianName (capital G, capital N)
    // - GuardianRelationship (capital G, capital R)
    // - GuardianPhone (capital G, capital P)
    
    if ($hasNewPhoto) {
        $sql = "UPDATE tblstudents SET 
            class_id = :class_id,
            FirstName = :first_name,
            SecondName = :second_name,
            LastName = :last_name,
            AdmNo = :admission_no,
            Gender = :gender,
            admission_date = :admission_date,
            GuardianName = :guardian_name,
            GuardianRelationship = :guardian_relation,
            GuardianPhone = :guardian_contact,
            guardian_email = :guardian_email,
            ProfilePic = :profile_pic,
            StreamId = :stream_id,
            academic_year = :academic_year,
            updated_at = NOW()
        WHERE id = :student_id AND school_id = :school_id";
        
        $params = [
            ':class_id' => $class_pk_id,
            ':first_name' => $first_name,
            ':second_name' => !empty($middle_name) ? $middle_name : '',
            ':last_name' => $last_name,
            ':admission_no' => $admission_no,
            ':gender' => $gender,
            ':admission_date' => $admission_date,
            ':guardian_name' => !empty($guardian_name) ? $guardian_name : null,
            ':guardian_relation' => !empty($guardian_relation) ? $guardian_relation : null,
            ':guardian_contact' => !empty($guardian_contact) ? $guardian_contact : null,
            ':guardian_email' => !empty($guardian_email) ? $guardian_email : null,
            ':profile_pic' => $profile_pic_filename,
            ':stream_id' => $stream_pk_id,
            ':academic_year' => $academic_year,
            ':student_id' => $student_id,
            ':school_id' => $schoolId
        ];
        
        // Delete old photo if it exists and is not default
        if ($current_student['ProfilePic'] && $current_student['ProfilePic'] !== 'default.png') {
            $old_photo_path = __DIR__ . '/../uploads/students/' . $current_student['ProfilePic'];
            if (file_exists($old_photo_path)) {
                unlink($old_photo_path);
            }
        }
    } else {
        $sql = "UPDATE tblstudents SET 
            class_id = :class_id,
            FirstName = :first_name,
            SecondName = :second_name,
            LastName = :last_name,
            AdmNo = :admission_no,
            Gender = :gender,
            admission_date = :admission_date,
            GuardianName = :guardian_name,
            GuardianRelationship = :guardian_relation,
            GuardianPhone = :guardian_contact,
            guardian_email = :guardian_email,
            StreamId = :stream_id,
            academic_year = :academic_year,
            updated_at = NOW()
        WHERE id = :student_id AND school_id = :school_id";
        
        $params = [
            ':class_id' => $class_pk_id,
            ':first_name' => $first_name,
            ':second_name' => !empty($middle_name) ? $middle_name : '',
            ':last_name' => $last_name,
            ':admission_no' => $admission_no,
            ':gender' => $gender,
            ':admission_date' => $admission_date,
            ':guardian_name' => !empty($guardian_name) ? $guardian_name : null,
            ':guardian_relation' => !empty($guardian_relation) ? $guardian_relation : null,
            ':guardian_contact' => !empty($guardian_contact) ? $guardian_contact : null,
            ':guardian_email' => !empty($guardian_email) ? $guardian_email : null,
            ':stream_id' => $stream_pk_id,
            ':academic_year' => $academic_year,
            ':student_id' => $student_id,
            ':school_id' => $schoolId
        ];
    }
    
    $stmt = $dbh->prepare($sql);
    $success = $stmt->execute($params);
    
    if ($success) {
        $studentData = [
            'id' => $student_id,
            'name' => $first_name . (!empty($middle_name) ? ' ' . $middle_name : '') . ' ' . $last_name,
            'admission_no' => $admission_no,
            'class' => $class_info['class_level'],
            'gender' => $gender
        ];
        
        sendResponse($studentData, 'success', 'Student updated successfully!', $student_id);
    } else {
        $errorInfo = $stmt->errorInfo();
        
        // Delete uploaded file if update failed
        if ($hasNewPhoto && isset($upload_dir) && file_exists($upload_dir . $profile_pic_filename)) {
            unlink($upload_dir . $profile_pic_filename);
        }
        
        sendResponse([], 'error', 'Failed to update student. Database error: ' . ($errorInfo[2] ?? 'Unknown error.'));
    }
    
} catch (PDOException $e) {
    // Delete uploaded file if exception occurred
    if ($hasNewPhoto && isset($upload_dir) && file_exists($upload_dir . $profile_pic_filename)) {
        unlink($upload_dir . $profile_pic_filename);
    }
    
    error_log("Update student error: " . $e->getMessage());
    sendResponse([], 'error', 'Database error: ' . $e->getMessage());
}

exit;
?>