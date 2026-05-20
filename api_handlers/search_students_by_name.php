<?php
session_start();
require_once('../includes/config.php');

header('Content-Type: application/json');

// Enable error logging for debugging
error_log("=== Search Students API Called ===");

// Check authentication
if (empty($_SESSION['authenticated']) || empty($_SESSION['school_id'])) {
    error_log("Authentication failed: " . print_r($_SESSION, true));
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$school_id = $_SESSION['school_id'];
$search_term = trim($_GET['search_term'] ?? '');

error_log("Search term: '$search_term', School ID: $school_id");

if (strlen($search_term) < 2) {
    echo json_encode(['success' => false, 'message' => 'Search term too short']);
    exit;
}

try {
    // Check if database connection exists
    if (!isset($db) && !isset($dbh)) {
        error_log("Database connection not established");
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit;
    }
    
    // Use the available database connection
    $connection = isset($db) ? $db : $dbh;
    
    $search_pattern = '%' . $search_term . '%';
    
    // FIXED: Use separate named placeholders for each LIKE condition
    $query = "
        SELECT 
            s.id,
            TRIM(CONCAT(COALESCE(s.FirstName, ''), ' ', COALESCE(s.SecondName, ''), ' ', COALESCE(s.LastName, ''))) as name,
            s.AdmNo as admission_no,
            s.Gender as gender,
            s.ProfilePic as profile_pic,
            c.class_level as class_name
        FROM tblstudents s
        LEFT JOIN tblclasses c ON s.class_id = c.id
        WHERE s.school_id = :school_id 
        AND (
            s.FirstName LIKE :search1 
            OR s.LastName LIKE :search2 
            OR CONCAT(COALESCE(s.FirstName, ''), ' ', COALESCE(s.LastName, '')) LIKE :search3
            OR s.AdmNo LIKE :search4
        )
        ORDER BY s.FirstName ASC
        LIMIT 20
    ";
    
    $stmt = $connection->prepare($query);
    $stmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
    $stmt->bindParam(':search1', $search_pattern, PDO::PARAM_STR);
    $stmt->bindParam(':search2', $search_pattern, PDO::PARAM_STR);
    $stmt->bindParam(':search3', $search_pattern, PDO::PARAM_STR);
    $stmt->bindParam(':search4', $search_pattern, PDO::PARAM_STR);
    $stmt->execute();
    
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Found " . count($students) . " students matching search");
    
    // Clean up the data
    foreach ($students as &$student) {
        // Trim whitespace from name
        $student['name'] = trim(preg_replace('/\s+/', ' ', $student['name']));
        if (empty($student['name'])) {
            $student['name'] = $student['admission_no'] ?? 'Unknown Student';
        }
        // Ensure admission_no is not null
        $student['admission_no'] = $student['admission_no'] ?? '';
        // Ensure gender is set
        $student['gender'] = $student['gender'] ?? 'Not specified';
        // Ensure profile_pic has a default
        $student['profile_pic'] = $student['profile_pic'] ?? 'default.png';
        // Ensure class_name is set
        $student['class_name'] = $student['class_name'] ?? 'N/A';
    }
    
    echo json_encode([
        'success' => true,
        'students' => $students,
        'count' => count($students),
        'search_term' => $search_term
    ]);
    
} catch (PDOException $e) {
    error_log("Search error: " . $e->getMessage());
    error_log("Error code: " . $e->getCode());
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("General error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>