<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['school_id'])) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit();
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$teacher_id = $_SESSION['teacher_id'];
$school_id = $_SESSION['school_id'];

// Get POST data
$terms_json = isset($_POST['terms']) ? $_POST['terms'] : '';
$academic_year = isset($_POST['academic_year']) ? intval($_POST['academic_year']) : date('Y');

if (empty($terms_json)) {
    echo json_encode(['success' => false, 'message' => 'No term data provided']);
    exit();
}

$terms = json_decode($terms_json, true);
if (!is_array($terms)) {
    echo json_encode(['success' => false, 'message' => 'Invalid term data format']);
    exit();
}

// Database connection
require_once dirname(__DIR__) . '/includes/config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Fetch all classes for this school
$classes_sql = "SELECT id FROM tblclasses WHERE school_id = ?";
$classes_stmt = $conn->prepare($classes_sql);
$classes_stmt->bind_param("i", $school_id);
$classes_stmt->execute();
$classes_result = $classes_stmt->get_result();

$classes = [];
while ($class = $classes_result->fetch_assoc()) {
    $classes[] = $class['id'];
}
$classes_stmt->close();

if (empty($classes)) {
    echo json_encode(['success' => false, 'message' => 'No classes found for this school']);
    exit();
}

try {
    // Begin transaction
    $conn->begin_transaction();
    
    $processed_count = 0;
    $errors = [];

    foreach ($classes as $class_id) {
        foreach ($terms as $term_data) {
            $term_number = $term_data['term_number'];
            $start_date = $term_data['start_date'];
            $end_date = $term_data['end_date'];
            $term_name = "Term " . $term_number;
            
            // Check if term already exists for this class
            $check_sql = "SELECT id FROM tblterms 
                          WHERE school_id = ? AND class_id = ? AND term_number = ? AND academic_year = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("iiii", $school_id, $class_id, $term_number, $academic_year);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                // Update existing term
                $term_row = $check_result->fetch_assoc();
                $term_id = $term_row['id'];
                
                $update_sql = "UPDATE tblterms 
                               SET term_name = ?, start_date = ?, end_date = ?, updation_date = NOW() 
                               WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("sssi", $term_name, $start_date, $end_date, $term_id);
                
                if (!$update_stmt->execute()) {
                    $errors[] = "Failed to update Term {$term_number} for Class {$class_id}";
                } else {
                    $processed_count++;
                }
                $update_stmt->close();
            } else {
                // Insert new term
                $insert_sql = "INSERT INTO tblterms 
                              (school_id, class_id, term_name, term_number, academic_year, start_date, end_date, is_current, creation_date) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, 0, NOW())";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("iisiiiss", 
                    $school_id, $class_id, $term_name, $term_number, $academic_year, $start_date, $end_date
                );
                
                if (!$insert_stmt->execute()) {
                    $errors[] = "Failed to insert Term {$term_number} for Class {$class_id}";
                } else {
                    $processed_count++;
                }
                $insert_stmt->close();
            }
            
            $check_stmt->close();
        }
    }

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => "Applied terms to {$processed_count} class-term combinations",
        'processed_count' => $processed_count,
        'errors' => $errors
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} finally {
    $conn->close();
}
?>