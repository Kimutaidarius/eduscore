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
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (empty($_SESSION['school_id'])) {
    echo json_encode(['success' => false, 'message' => 'School ID not found']);
    exit;
}

$school_id = $_SESSION['school_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    // Get school initials
    $stmt = $db->prepare("SELECT school_name FROM tblschoolinfo WHERE id = :school_id");
    $stmt->execute([':school_id' => $school_id]);
    $school = $stmt->fetch(PDO::FETCH_ASSOC);
    
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
    
    // Get the highest current admission number for this school
    $stmt = $db->prepare("
        SELECT AdmNo FROM tblstudents 
        WHERE school_id = :school_id 
        AND AdmNo LIKE :pattern 
        ORDER BY id DESC 
        LIMIT 1
    ");
    $pattern = $initials . '%';
    $stmt->execute([':school_id' => $school_id, ':pattern' => $pattern]);
    $last_student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $next_number = 1;
    if ($last_student && !empty($last_student['AdmNo'])) {
        $parts = explode('/', $last_student['AdmNo']);
        if (count($parts) == 2) {
            $next_number = intval($parts[1]) + 1;
        }
    }
    
    // Check if file was uploaded
    if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Please upload a valid Excel file']);
        exit;
    }
    
    require_once '../../vendor/autoload.php'; // For PhpSpreadsheet
    
    use PhpOffice\PhpSpreadsheet\IOFactory;
    
    $file = $_FILES['excel_file']['tmp_name'];
    $spreadsheet = IOFactory::load($file);
    $worksheet = $spreadsheet->getActiveSheet();
    $rows = $worksheet->toArray();
    
    // Remove header row
    $header = array_shift($rows);
    
    $success_count = 0;
    $error_count = 0;
    $errors = [];
    
    $db->beginTransaction();
    
    $academic_year = date('Y');
    
    foreach ($rows as $row_index => $row) {
        // Skip empty rows
        if (empty(array_filter($row))) {
            continue;
        }
        
        // Map Excel columns (adjust indices based on your Excel template)
        $first_name = trim($row[0] ?? '');
        $middle_name = trim($row[1] ?? '');
        $last_name = trim($row[2] ?? '');
        $gender = trim($row[3] ?? '');
        $class_name = trim($row[4] ?? '');
        $stream_name = trim($row[5] ?? '');
        $guardian_name = trim($row[6] ?? '');
        $guardian_relation = trim($row[7] ?? '');
        $guardian_contact = trim($row[8] ?? '');
        $guardian_email = trim($row[9] ?? '');
        $admission_date = !empty($row[10]) ? date('Y-m-d', strtotime($row[10])) : date('Y-m-d');
        
        // Validate required fields
        if (empty($first_name) || empty($last_name) || empty($gender) || empty($class_name)) {
            $errors[] = "Row " . ($row_index + 2) . ": Missing required fields (First Name, Last Name, Gender, Class)";
            $error_count++;
            continue;
        }
        
        // Get class ID
        $stmt = $db->prepare("SELECT id FROM tblclasses WHERE school_id = :school_id AND class_level = :class_level");
        $stmt->execute([':school_id' => $school_id, ':class_level' => $class_name]);
        $class = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$class) {
            $errors[] = "Row " . ($row_index + 2) . ": Class '$class_name' not found";
            $error_count++;
            continue;
        }
        
        $class_id = $class['id'];
        $stream_id = null;
        
        // Get stream ID if provided
        if (!empty($stream_name)) {
            $stmt = $db->prepare("SELECT id FROM tblstreams WHERE school_id = :school_id AND class_id = :class_id AND stream_name = :stream_name");
            $stmt->execute([
                ':school_id' => $school_id,
                ':class_id' => $class_id,
                ':stream_name' => $stream_name
            ]);
            $stream = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($stream) {
                $stream_id = $stream['id'];
            }
        }
        
        // Generate admission number
        $admission_no = $initials . '/' . str_pad($next_number, 3, '0', STR_PAD_LEFT);
        
        // Check if admission number already exists (shouldn't happen with sequential generation)
        $stmt = $db->prepare("SELECT id FROM tblstudents WHERE school_id = :school_id AND AdmNo = :admission_no");
        $stmt->execute([':school_id' => $school_id, ':admission_no' => $admission_no]);
        if ($stmt->fetch()) {
            // If duplicate, increment and try again
            $next_number++;
            $admission_no = $initials . '/' . str_pad($next_number, 3, '0', STR_PAD_LEFT);
        }
        
        // Insert student
        $sql = "INSERT INTO tblstudents (
            school_id, class_id, FirstName, SecondName, LastName,
            AdmNo, admission_date, Gender, GuardianName, GuardianRelationship,
            GuardianPhone, guardian_email, Status, academic_year, StreamId
        ) VALUES (
            :school_id, :class_id, :first_name, :middle_name, :last_name,
            :admission_no, :admission_date, :gender, :guardian_name, :guardian_relation,
            :guardian_contact, :guardian_email, 'Active', :academic_year, :stream_id
        )";
        
        $stmt = $db->prepare($sql);
        $result = $stmt->execute([
            ':school_id' => $school_id,
            ':class_id' => $class_id,
            ':first_name' => $first_name,
            ':middle_name' => $middle_name,
            ':last_name' => $last_name,
            ':admission_no' => $admission_no,
            ':admission_date' => $admission_date,
            ':gender' => $gender,
            ':guardian_name' => $guardian_name,
            ':guardian_relation' => $guardian_relation,
            ':guardian_contact' => $guardian_contact,
            ':guardian_email' => $guardian_email,
            ':academic_year' => $academic_year,
            ':stream_id' => $stream_id
        ]);
        
        if ($result) {
            $success_count++;
            $next_number++; // Increment for next student
        } else {
            $errors[] = "Row " . ($row_index + 2) . ": Database insert failed";
            $error_count++;
        }
    }
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "Import completed: $success_count students added successfully, $error_count failed",
        'success_count' => $success_count,
        'error_count' => $error_count,
        'errors' => $errors
    ]);
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Import error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Import failed: ' . $e->getMessage()
    ]);
}
?>