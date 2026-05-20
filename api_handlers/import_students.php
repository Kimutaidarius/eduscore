<?php
// Turn on error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear any previous output
while (ob_get_level()) ob_end_clean();

// Set JSON header first
header('Content-Type: application/json');

try {
    error_log("=== IMPORT REQUEST STARTED ===");

    // Include database config
    require_once '../includes/config.php';

    // Authentication check
    if (empty($_SESSION['authenticated']) || empty($_SESSION['school_id']) || empty($_SESSION['teacher_id'])) {
        throw new Exception('Authentication required. Please log in again.');
    }

    $school_id = $_SESSION['school_id'];

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method. Please use POST.');
    }

    $class_id = isset($_POST['class_id']) ? (int)$_POST['class_id'] : 0;
    $stream_id = isset($_POST['stream_id']) && !empty($_POST['stream_id']) ? (int)$_POST['stream_id'] : null;
    
    if (!$class_id) {
        throw new Exception('Please select a class for import.');
    }

    // Verify class exists
    $stmt = $db->prepare("SELECT id FROM tblclasses WHERE id = ? AND school_id = ?");
    $stmt->execute([$class_id, $school_id]);
    if (!$stmt->fetch()) {
        throw new Exception("Class ID $class_id does not exist for this school");
    }

    // Get school initials for admission number generation
    function getSchoolInitials($db, $school_id) {
        try {
            $stmt = $db->prepare("SELECT school_initials FROM tblschoolinfo WHERE id = :school_id LIMIT 1");
            $stmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
            $stmt->execute();
            $school = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($school && !empty($school['school_initials'])) {
                return strtoupper($school['school_initials']);
            }
        } catch (PDOException $e) {
            error_log("Error fetching school initials: " . $e->getMessage());
        }
        return 'STU'; // Default fallback
    }

    $school_initials = getSchoolInitials($db, $school_id);
    $current_year = date('Y');

    // Get next sequential number for admission
    function getNextAdmissionNumber($db, $school_id, $school_initials, $year) {
        $pattern = $school_initials . '/%/' . $year;
        $stmt = $db->prepare("SELECT AdmNo FROM tblstudents 
                              WHERE school_id = :school_id 
                              AND AdmNo LIKE :pattern
                              ORDER BY id DESC 
                              LIMIT 1");
        $stmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
        $stmt->bindParam(':pattern', $pattern);
        $stmt->execute();
        
        $last = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($last && !empty($last['AdmNo'])) {
            preg_match('/' . preg_quote($school_initials, '/') . '\/(\d+)\/' . $year . '/', $last['AdmNo'], $matches);
            if (isset($matches[1])) {
                $next_num = intval($matches[1]) + 1;
            } else {
                $next_num = 1;
            }
        } else {
            $next_num = 1;
        }
        
        return sprintf("%s/%03d/%d", $school_initials, $next_num, $year);
    }

    // Check if file was uploaded
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error');
    }

    $file = $_FILES['file']['tmp_name'];
    $filename = $_FILES['file']['name'];
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    if ($extension !== 'csv') {
        throw new Exception('Only CSV files are supported.');
    }

    // Read CSV file
    $rows = [];
    if (($handle = fopen($file, "r")) !== FALSE) {
        $first_line = fgets($handle);
        rewind($handle);
        
        $delimiters = [',', ';', "\t", '|'];
        $best_delimiter = ',';
        $max_count = 0;
        
        foreach ($delimiters as $delimiter) {
            $test_data = str_getcsv($first_line, $delimiter);
            $count = count($test_data);
            if ($count > $max_count) {
                $max_count = $count;
                $best_delimiter = $delimiter;
            }
        }
        
        while (($data = fgetcsv($handle, 10000, $best_delimiter)) !== FALSE) {
            if (!empty($data) && strpos($data[0], "\xEF\xBB\xBF") === 0) {
                $data[0] = substr($data[0], 3);
            }
            $rows[] = $data;
        }
        fclose($handle);
    }

    if (count($rows) < 2) {
        throw new Exception('CSV file must contain a header row and at least one data row');
    }

    // Get headers
    $headers = array_map(function($header) {
        return strtolower(trim(str_replace([' ', '-', '_'], '', $header)));
    }, $rows[0]);

    $required_columns = ['firstname', 'lastname', 'gender'];
    
    $missing = [];
    foreach ($required_columns as $col) {
        if (!in_array($col, $headers)) {
            $missing[] = $col;
        }
    }
    
    if (!empty($missing)) {
        throw new Exception('Missing required columns: ' . implode(', ', $missing));
    }

    $db->beginTransaction();

    $success_count = 0;
    $fail_count = 0;
    $skip_count = 0;
    $details = [];
    
    // Track the next admission number for this import session
    $next_admission_number = getNextAdmissionNumber($db, $school_id, $school_initials, $current_year);

    for ($i = 1; $i < count($rows); $i++) {
        $row = $rows[$i];
        $row_num = $i + 1;
        
        if (empty(array_filter($row))) {
            $skip_count++;
            $details[] = ['row' => $row_num, 'status' => 'warning', 'message' => 'Empty row skipped'];
            continue;
        }
        
        $row_data = [];
        foreach ($headers as $idx => $header) {
            if (isset($row[$idx])) {
                $row_data[$header] = trim($row[$idx]);
            }
        }
        
        $first_name = $row_data['firstname'] ?? '';
        $last_name = $row_data['lastname'] ?? '';
        $middle_name = $row_data['middlename'] ?? $row_data['secondname'] ?? '';
        $gender = ucfirst(strtolower($row_data['gender'] ?? ''));
        
        // Check if admission number provided in CSV
        $provided_admission = $row_data['admissionno'] ?? $row_data['admissionnumber'] ?? '';
        
        if (!empty($provided_admission)) {
            // Use provided admission number
            $admission_no = $provided_admission;
        } else {
            // Auto-generate admission number in format: INITIALS/XXX/YYYY
            $admission_no = $next_admission_number;
            // Increment for next student
            $next_num = intval(substr($next_admission_number, -8, 3)) + 1;
            $next_admission_number = sprintf("%s/%03d/%d", $school_initials, $next_num, $current_year);
        }
        
        $guardian_name = $row_data['guardianname'] ?? $row_data['parentname'] ?? '';
        $guardian_phone = $row_data['guardianphone'] ?? $row_data['phone'] ?? '';
        $guardian_email = $row_data['guardianemail'] ?? $row_data['email'] ?? '';
        $guardian_relation = $row_data['guardianrelation'] ?? $row_data['relationship'] ?? 'Parent';
        
        // Validate required fields
        if (empty($first_name)) {
            $fail_count++;
            $details[] = ['row' => $row_num, 'status' => 'error', 'message' => 'Missing first name'];
            continue;
        }
        
        if (empty($last_name)) {
            $fail_count++;
            $details[] = ['row' => $row_num, 'status' => 'error', 'message' => 'Missing last name'];
            continue;
        }
        
        if (empty($gender)) {
            $fail_count++;
            $details[] = ['row' => $row_num, 'status' => 'error', 'message' => 'Missing gender'];
            continue;
        }
        
        $valid_genders = ['Male', 'Female', 'Other'];
        if (!in_array($gender, $valid_genders)) {
            $gender = 'Other';
        }
        
        // Check for duplicate admission number
        $stmt = $db->prepare("SELECT id FROM tblstudents WHERE school_id = ? AND AdmNo = ?");
        $stmt->execute([$school_id, $admission_no]);
        if ($stmt->fetch()) {
            $skip_count++;
            $details[] = ['row' => $row_num, 'status' => 'warning', 'message' => "Skipped duplicate admission number: $admission_no"];
            continue;
        }
        
        try {
            $query = "INSERT INTO tblstudents (
                school_id, FirstName, SecondName, LastName, Gender, AdmNo, 
                class_id, StreamId, GuardianName, GuardianRelationship, GuardianPhone, 
                admission_date, Status, academic_year
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            )";
            
            $stmt = $db->prepare($query);
            $result = $stmt->execute([
                $school_id,
                $first_name,
                $middle_name,
                $last_name,
                $gender,
                $admission_no,
                $class_id,
                $stream_id,
                $guardian_name,
                $guardian_relation,
                $guardian_phone,
                date('Y-m-d'),
                'Active',
                $current_year
            ]);
            
            if ($result) {
                $success_count++;
                $details[] = [
                    'row' => $row_num,
                    'status' => 'success',
                    'message' => "Added: $first_name $last_name (Adm: $admission_no)"
                ];
            } else {
                $fail_count++;
                $details[] = ['row' => $row_num, 'status' => 'error', 'message' => 'Database insert failed'];
            }
            
        } catch (PDOException $e) {
            error_log("Insert error for row $row_num: " . $e->getMessage());
            $fail_count++;
            $details[] = ['row' => $row_num, 'status' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => "Import completed: $success_count successful, $fail_count failed, $skip_count skipped",
        'total_records' => count($rows) - 1,
        'processed_records' => $success_count + $fail_count + $skip_count,
        'successful_records' => $success_count,
        'failed_records' => $fail_count,
        'skipped_records' => $skip_count,
        'details' => $details
    ]);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    error_log("Import error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>