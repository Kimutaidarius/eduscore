<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database config
require_once '../../includes/config.php';

// Set JSON header
header('Content-Type: application/json');

// Check authentication
if (empty($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if (empty($_SESSION['school_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'School ID not found']);
    exit;
}

$school_id = $_SESSION['school_id'];

// Check for required fields
$required_fields = ['first_name', 'last_name', 'class_id', 'admission_date', 'gender'];
foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
        echo json_encode(['status' => 'error', 'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required']);
        exit;
    }
}

try {
    $db->beginTransaction();
    
    // If admission number is not provided or empty, generate one automatically
    $admission_no = trim($_POST['admission_no'] ?? '');
    
    if (empty($admission_no)) {
        // Get school initials
        $stmt = $db->prepare("SELECT school_name FROM tblschoolinfo WHERE id = ?");
        $stmt->execute([$school_id]);
        $school = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Generate initials
        $initials = '';
        if ($school) {
            $words = explode(' ', $school['school_name']);
            foreach ($words as $word) {
                $initials .= strtoupper(substr($word, 0, 1));
            }
            $initials = substr($initials, 0, 3);
        } else {
            $initials = 'STU';
        }
        
        // Get the last admission number
        $pattern = $initials . '%';
        $stmt = $db->prepare("
            SELECT AdmNo FROM tblstudents 
            WHERE school_id = ? 
            AND AdmNo LIKE ? 
            ORDER BY id DESC 
            LIMIT 1
        ");
        $stmt->execute([$school_id, $pattern]);
        $last_student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $next_number = 1;
        if ($last_student && !empty($last_student['AdmNo'])) {
            $parts = explode('/', $last_student['AdmNo']);
            if (count($parts) == 2) {
                $next_number = intval($parts[1]) + 1;
            }
        }
        
        $admission_no = $initials . '/' . str_pad($next_number, 3, '0', STR_PAD_LEFT);
    }
    
    // Check if admission number already exists
    $stmt = $db->prepare("SELECT id FROM tblstudents WHERE school_id = ? AND AdmNo = ?");
    $stmt->execute([$school_id, $admission_no]);
    if ($stmt->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'Admission number already exists']);
        exit;
    }
    
    // Handle photo upload
    $profile_pic = 'default.png';
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../../uploads/students/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($file_extension, $allowed_extensions)) {
            $new_filename = time() . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $admission_no) . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_path)) {
                $profile_pic = $new_filename;
            }
        }
    }
    
    // Get academic year from current term or use current year
    $academic_year = date('Y');
    
    // Fix: Use correct column name 'guardian_email' (lowercase with underscore)
    $sql = "INSERT INTO tblstudents (
        school_id, class_id, FirstName, SecondName, LastName, 
        AdmNo, admission_date, Gender, GuardianName, GuardianRelationship, 
        GuardianPhone, guardian_email, ProfilePic, Status, academic_year, StreamId
    ) VALUES (
        ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?,
        ?, ?, ?, 'Active', ?, ?
    )";
    
    $stmt = $db->prepare($sql);
    $result = $stmt->execute([
        $school_id,
        $_POST['class_id'],
        $_POST['first_name'],
        $_POST['middle_name'] ?? '',
        $_POST['last_name'],
        $admission_no,
        $_POST['admission_date'],
        $_POST['gender'],
        $_POST['guardian_name'] ?? '',
        $_POST['guardian_relation'] ?? '',
        $_POST['guardian_contact'] ?? '',
        $_POST['guardian_email'] ?? '',  // Fix: Use guardian_email column
        $profile_pic,
        $academic_year,
        !empty($_POST['stream_id']) ? $_POST['stream_id'] : null
    ]);
    
    if ($result) {
        $student_id = $db->lastInsertId();
        $db->commit();
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Student added successfully',
            'student_id' => $student_id,
            'admission_no' => $admission_no
        ]);
    } else {
        throw new Exception('Failed to insert student');
    }
    
} catch (Exception $e) {
    $db->rollBack();
    error_log("Error adding student: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to add student: ' . $e->getMessage()
    ]);
}
?>