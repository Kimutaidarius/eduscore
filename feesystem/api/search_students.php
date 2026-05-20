<?php
session_start();
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Check authentication
if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../includes/config.php';

// Get POST data
$school_id = isset($_POST['school_id']) ? (int)$_POST['school_id'] : 0;
$search = isset($_POST['search']) ? trim($_POST['search']) : '';
$academic_level = isset($_POST['academic_level']) ? trim($_POST['academic_level']) : 'primary';

// Validate inputs
if (empty($search) || strlen($search) < 2) {
    echo json_encode(['success' => true, 'students' => []]);
    exit;
}

if ($school_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid school ID']);
    exit;
}

try {
    $searchTerm = "%{$search}%";
    
    // Check if students table exists
    $table_check = $db->query("SHOW TABLES LIKE 'tblstudents'");
    if ($table_check->rowCount() == 0) {
        echo json_encode(['success' => true, 'students' => []]);
        exit;
    }
    
    // Query using the actual tblstudents table structure
    $query = "SELECT 
                s.id, 
                CONCAT(s.FirstName, ' ', s.SecondName, ' ', COALESCE(s.LastName, '')) as full_name,
                s.AdmNo as admission_number,
                s.Gender as gender,
                s.class_id,
                c.class_level as class_name
              FROM tblstudents s
              LEFT JOIN tblclasses c ON s.class_id = c.id AND c.school_id = s.school_id
              WHERE s.school_id = ? 
              AND s.Status = 'Active'
              AND (s.FirstName LIKE ? 
                   OR s.SecondName LIKE ? 
                   OR s.LastName LIKE ? 
                   OR s.AdmNo LIKE ?)
              LIMIT 10";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$school_id, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If no results, try a simpler query
    if (empty($students)) {
        $query = "SELECT 
                    id, 
                    CONCAT(FirstName, ' ', SecondName) as full_name,
                    AdmNo as admission_number,
                    Gender as gender,
                    class_id
                  FROM tblstudents 
                  WHERE school_id = ? 
                  AND Status = 'Active'
                  AND (FirstName LIKE ? OR SecondName LIKE ? OR AdmNo LIKE ?)
                  LIMIT 10";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$school_id, $searchTerm, $searchTerm, $searchTerm]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Format the response
    $formatted_students = [];
    foreach ($students as $student) {
        $formatted_students[] = [
            'id' => $student['id'],
            'full_name' => trim($student['full_name']),
            'admission_number' => $student['admission_number'],
            'gender' => $student['gender'] ?? 'N/A',
            'class_name' => $student['class_name'] ?? 'N/A',
            'class_id' => $student['class_id'] ?? 0
        ];
    }
    
    echo json_encode(['success' => true, 'students' => $formatted_students]);
    
} catch (PDOException $e) {
    error_log("Database error in search_students.php: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Database error occurred',
        'debug' => $e->getMessage() // Remove in production
    ]);
} catch (Exception $e) {
    error_log("General error in search_students.php: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred',
        'debug' => $e->getMessage() // Remove in production
    ]);
}
?>