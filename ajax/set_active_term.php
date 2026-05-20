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
$term_number = isset($_POST['term_number']) ? intval($_POST['term_number']) : 0;
$academic_year = isset($_POST['academic_year']) ? intval($_POST['academic_year']) : date('Y');

// Validate required fields
if (!$term_number) {
    echo json_encode(['success' => false, 'message' => 'Term number is required']);
    exit();
}

// Database connection
require_once dirname(__DIR__) . '/includes/config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

try {
    // Begin transaction
    $conn->begin_transaction();

    // First, unset all current terms for this school/year
    $unset_sql = "UPDATE tblterms SET is_current = 0 
                  WHERE school_id = ? AND academic_year = ?";
    $unset_stmt = $conn->prepare($unset_sql);
    $unset_stmt->bind_param("ii", $school_id, $academic_year);
    $unset_stmt->execute();
    $unset_stmt->close();

    // Then set the selected term as current
    $set_sql = "UPDATE tblterms SET is_current = 1 
                WHERE school_id = ? AND term_number = ? AND academic_year = ?";
    $set_stmt = $conn->prepare($set_sql);
    $set_stmt->bind_param("iii", $school_id, $term_number, $academic_year);
    
    if ($set_stmt->execute()) {
        if ($set_stmt->affected_rows > 0) {
            $conn->commit();
            echo json_encode([
                'success' => true,
                'message' => "Term {$term_number} is now active"
            ]);
        } else {
            $conn->rollback();
            echo json_encode([
                'success' => false,
                'message' => "Term {$term_number} not found for the selected year"
            ]);
        }
    } else {
        throw new Exception("Failed to set active term: " . $conn->error);
    }
    
    $set_stmt->close();

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} finally {
    $conn->close();
}
?>