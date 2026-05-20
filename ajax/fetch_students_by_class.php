<?php
// ajax/fetch_students_by_class.php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['school_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Database connection
require_once dirname(__DIR__) . '/includes/config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}
$conn->set_charset("utf8mb4");

$school_id = (int)$_SESSION['school_id'];

// Get input parameters
$class_id = isset($_POST['class_id']) ? (int)$_POST['class_id'] : 0;
$stream_id = isset($_POST['stream_id']) ? (int)$_POST['stream_id'] : 0;
$academic_level = isset($_POST['academic_level']) ? $_POST['academic_level'] : '';
$search = isset($_POST['search']) ? trim($_POST['search']) : '';

if ($class_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Class ID is required']);
    exit();
}

try {
    // Build query based on tblstudents table structure
    $sql = "
        SELECT 
            id,
            AdmNo as admission_no,
            FirstName,
            SecondName,
            LastName,
            assessment_no as upi_no,
            ProfilePic as profile_pic,
            Gender,
            GuardianName as guardian_name,
            GuardianPhone as guardian_phone,
            guardian_email,
            StreamId as stream_id,
            Status,
            CONCAT(
                TRIM(FirstName), 
                ' ', 
                COALESCE(NULLIF(TRIM(SecondName), ''), ''), 
                CASE 
                    WHEN TRIM(LastName) IS NOT NULL AND TRIM(LastName) != '' 
                    THEN CONCAT(' ', TRIM(LastName)) 
                    ELSE '' 
                END
            ) as full_name
        FROM tblstudents 
        WHERE school_id = ? 
        AND class_id = ? 
        AND Status = 'Active'
    ";
    
    $params = [$school_id, $class_id];
    $types = "ii";
    
    // Filter by stream if specified (StreamId column)
    if ($stream_id > 0) {
        $sql .= " AND StreamId = ?";
        $params[] = $stream_id;
        $types .= "i";
    }
    
    // Filter by academic level if provided (using class level reference)
    if (!empty($academic_level)) {
        // Join with tblclasses to filter by academic_level
        $sql = "
            SELECT 
                s.id,
                s.AdmNo as admission_no,
                s.FirstName,
                s.SecondName,
                s.LastName,
                s.assessment_no as upi_no,
                s.ProfilePic as profile_pic,
                s.Gender,
                s.GuardianName as guardian_name,
                s.GuardianPhone as guardian_phone,
                s.guardian_email,
                s.StreamId as stream_id,
                s.Status,
                CONCAT(
                    TRIM(s.FirstName), 
                    ' ', 
                    COALESCE(NULLIF(TRIM(s.SecondName), ''), ''), 
                    CASE 
                        WHEN TRIM(s.LastName) IS NOT NULL AND TRIM(s.LastName) != '' 
                        THEN CONCAT(' ', TRIM(s.LastName)) 
                        ELSE '' 
                    END
                ) as full_name
            FROM tblstudents s
            INNER JOIN tblclasses c ON s.class_id = c.id
            WHERE s.school_id = ? 
            AND s.class_id = ? 
            AND s.Status = 'Active'
            AND c.academic_level = ?
        ";
        
        $params = [$school_id, $class_id, $academic_level];
        $types = "iis";
        
        if ($stream_id > 0) {
            $sql .= " AND s.StreamId = ?";
            $params[] = $stream_id;
            $types .= "i";
        }
    }
    
    // Add search functionality
    if (!empty($search)) {
        $searchTerm = '%' . $search . '%';
        $sql .= " AND (
            CONCAT(FirstName, ' ', COALESCE(SecondName, ''), ' ', COALESCE(LastName, '')) LIKE ?
            OR AdmNo LIKE ?
            OR assessment_no LIKE ?
        )";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= "sss";
    }
    
    // Order by name
    $sql .= " ORDER BY FirstName ASC, SecondName ASC, LastName ASC";
    
    // Limit results for performance
    $sql .= " LIMIT 200";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $students = [];
    while ($row = $result->fetch_assoc()) {
        // Clean up null values
        foreach ($row as $key => $value) {
            if ($value === null) {
                $row[$key] = '';
            }
        }
        $students[] = $row;
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'students' => $students,
        'total' => count($students),
        'message' => count($students) . ' students loaded'
    ]);
    
} catch (Exception $e) {
    error_log("Error in fetch_students_by_class.php: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error loading students: ' . $e->getMessage()
    ]);
}

$conn->close();
?>