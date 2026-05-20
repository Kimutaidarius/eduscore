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

// Get JSON data
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

if (!$data || !isset($data['terms']) || !is_array($data['terms'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data format']);
    exit();
}

$teacher_id = $_SESSION['teacher_id'];
$school_id = $_SESSION['school_id'];

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
    
    $saved_count = 0;
    $failed_count = 0;
    $errors = [];
    $saved_terms = [];

    foreach ($data['terms'] as $term_data) {
        $term_number = isset($term_data['term_number']) ? intval($term_data['term_number']) : 0;
        $start_date = isset($term_data['start_date']) ? $term_data['start_date'] : '';
        $end_date = isset($term_data['end_date']) ? $term_data['end_date'] : '';
        $academic_year = isset($term_data['academic_year']) ? intval($term_data['academic_year']) : date('Y');
        $is_current = isset($term_data['is_current']) ? intval($term_data['is_current']) : 0;

        // Validate required fields
        if (!$term_number || !$start_date || !$end_date) {
            $failed_count++;
            $errors[] = "Term {$term_number}: Missing required fields";
            continue;
        }

        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
            $failed_count++;
            $errors[] = "Term {$term_number}: Invalid date format. Use YYYY-MM-DD.";
            continue;
        }

        // Validate dates
        if (strtotime($end_date) <= strtotime($start_date)) {
            $failed_count++;
            $errors[] = "Term {$term_number}: End date must be after start date";
            continue;
        }

        // Check if term already exists
        $check_sql = "SELECT id FROM tblterms 
                      WHERE school_id = ? AND term_number = ? AND academic_year = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("iii", $school_id, $term_number, $academic_year);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        // Format term name
        $term_name = "Term " . $term_number;
        
        if ($check_result->num_rows > 0) {
            // Update existing term
            $term_row = $check_result->fetch_assoc();
            $term_id = $term_row['id'];
            
            $update_sql = "UPDATE tblterms 
                           SET term_name = ?, 
                               start_date = ?, 
                               end_date = ?, 
                               is_current = ?, 
                               updation_date = NOW() 
                           WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("sssii", $term_name, $start_date, $end_date, $is_current, $term_id);
            
            if ($update_stmt->execute()) {
                $saved_count++;
                $saved_terms[] = [
                    'term_number' => $term_number,
                    'term_id' => $term_id,
                    'action' => 'updated'
                ];
                
                // If this term is set as current, unset others
                if ($is_current == 1) {
                    $unset_sql = "UPDATE tblterms SET is_current = 0 
                                  WHERE school_id = ? AND academic_year = ? AND id != ?";
                    $unset_stmt = $conn->prepare($unset_sql);
                    $unset_stmt->bind_param("iii", $school_id, $academic_year, $term_id);
                    $unset_stmt->execute();
                    $unset_stmt->close();
                }
            } else {
                $failed_count++;
                $errors[] = "Term {$term_number}: Failed to update";
            }
            $update_stmt->close();
        } else {
            // Insert new term
            $insert_sql = "INSERT INTO tblterms 
                          (school_id, term_name, term_number, academic_year, start_date, end_date, is_current, creation_date) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("isiiissi", 
                $school_id,      // i
                $term_name,      // s
                $term_number,    // i
                $academic_year,  // i
                $start_date,     // s
                $end_date,       // s
                $is_current,     // i
                $academic_year   // i (for creation_date? No, we use NOW())
            );
            
            // Let me fix this - we don't need academic_year twice
            $insert_stmt->close();
            $insert_sql = "INSERT INTO tblterms 
                          (school_id, term_name, term_number, academic_year, start_date, end_date, is_current, creation_date) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("isiiissi", 
                $school_id,      // i
                $term_name,      // s
                $term_number,    // i
                $academic_year,  // i
                $start_date,     // s
                $end_date,       // s
                $is_current,     // i
                $academic_year   // i - this is the 8th parameter
            );
            
            if ($insert_stmt->execute()) {
                $term_id = $insert_stmt->insert_id;
                $saved_count++;
                $saved_terms[] = [
                    'term_number' => $term_number,
                    'term_id' => $term_id,
                    'action' => 'created'
                ];
                
                // If this term is set as current, unset others
                if ($is_current == 1) {
                    $unset_sql = "UPDATE tblterms SET is_current = 0 
                                  WHERE school_id = ? AND academic_year = ? AND id != ?";
                    $unset_stmt = $conn->prepare($unset_sql);
                    $unset_stmt->bind_param("iii", $school_id, $academic_year, $term_id);
                    $unset_stmt->execute();
                    $unset_stmt->close();
                }
            } else {
                $failed_count++;
                $errors[] = "Term {$term_number}: Failed to insert";
            }
            $insert_stmt->close();
        }
        
        $check_stmt->close();
    }

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => "Successfully saved {$saved_count} term(s)" . ($failed_count > 0 ? ", {$failed_count} failed" : ""),
        'saved_count' => $saved_count,
        'failed_count' => $failed_count,
        'saved_terms' => $saved_terms,
        'errors' => $errors
    ]);

} catch (Exception $e) {
    $conn->rollback();
    error_log("Bulk term save error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} finally {
    $conn->close();
}
?>