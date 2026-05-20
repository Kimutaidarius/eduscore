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

if (!isset($_GET['search_term']) || strlen($_GET['search_term']) < 2) {
    echo json_encode(['success' => false, 'message' => 'Search term must be at least 2 characters']);
    exit;
}

$search_term = '%' . $_GET['search_term'] . '%';

try {
    // Fix: Use separate placeholders for each LIKE condition
    $sql = "SELECT 
                s.id, 
                CONCAT(s.FirstName, ' ', COALESCE(s.SecondName, ''), ' ', s.LastName) as name,
                s.AdmNo as admission_no,
                s.Gender as gender,
                s.ProfilePic as profile_pic,
                c.class_level as class
            FROM tblstudents s
            LEFT JOIN tblclasses c ON s.class_id = c.id
            WHERE s.school_id = :school_id 
            AND (s.FirstName LIKE :search1 
                OR s.SecondName LIKE :search2 
                OR s.LastName LIKE :search3
                OR CONCAT(s.FirstName, ' ', s.LastName) LIKE :search4)
            ORDER BY s.FirstName ASC
            LIMIT 20";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':school_id' => $school_id,
        ':search1' => $search_term,
        ':search2' => $search_term,
        ':search3' => $search_term,
        ':search4' => $search_term
    ]);
    
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'students' => $students
    ]);
    
} catch (PDOException $e) {
    error_log("Error searching students: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>