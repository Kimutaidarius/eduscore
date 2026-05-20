<?php
session_start();
// Temporarily disable error display to prevent breaking JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);

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

// Get POST data
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

// Log received data for debugging
error_log('Save scores data received: ' . print_r($data, true));

if (empty($data) || !isset($data['scores'])) {
    echo json_encode([
        'success' => false, 
        'message' => 'No data received',
        'received_data' => $data
    ]);
    exit();
}

// Session variables
$teacher_id = $_SESSION['teacher_id'];
$school_id = $_SESSION['school_id'];

// Database connection
$config_file = dirname(__DIR__) . '/includes/config.php';
if (!file_exists($config_file)) {
    echo json_encode([
        'success' => false, 
        'message' => 'Configuration file not found',
        'config_path' => $config_file
    ]);
    exit();
}

require_once $config_file;

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    echo json_encode([
        'success' => false, 
        'message' => 'Database connection failed: ' . $conn->connect_error
    ]);
    exit();
}

$response = [
    'success' => false,
    'message' => '',
    'saved_count' => 0,
    'failed_count' => 0,
    'details' => []
];

try {
    // Begin transaction
    $conn->begin_transaction();
    
    $processed = 0;
    $failed = 0;
    
    foreach ($data['scores'] as $score_data) {
        // Validate required fields
        if (!isset($score_data['student_id']) || !isset($score_data['subject_id']) || 
            !isset($score_data['exam_id']) || !isset($score_data['score'])) {
            $failed++;
            $response['details'][] = [
                'student_id' => $score_data['student_id'] ?? 'unknown',
                'status' => 'failed',
                'message' => 'Missing required fields'
            ];
            continue;
        }
        
        $student_id = (int)$score_data['student_id'];
        $subject_id = (int)$score_data['subject_id'];
        $exam_id = (int)$score_data['exam_id'];
        $class_id = (int)($score_data['class_id'] ?? 0);
        $stream_id = (int)($score_data['stream_id'] ?? 0);
        $score = floatval($score_data['score']);
        $total_score = floatval($score_data['total_score'] ?? 0);
        
        // Validate score
        if ($score < 0) {
            $failed++;
            $response['details'][] = [
                'student_id' => $student_id,
                'status' => 'failed',
                'message' => 'Score cannot be negative'
            ];
            continue;
        }
        
        if ($total_score > 0 && $score > $total_score) {
            $failed++;
            $response['details'][] = [
                'student_id' => $student_id,
                'status' => 'failed',
                'message' => "Score exceeds total score of {$total_score}"
            ];
            continue;
        }
        
        // Calculate percentage if total_score > 0
        $percentage = 0;
        if ($total_score > 0) {
            $percentage = ($score / $total_score) * 100;
        }
        
        // Calculate rubric points based on percentage
        // Points (1-4) will be stored in the rubric column
        $rubric_points = 0;
        $grade = '';
        $rubric_description = '';
        
        if ($percentage >= 80) {
            $rubric_points = 4;      // EE - Exceeding Expectations
            $grade = 'EE';
            $rubric_description = 'Exceeding Expectations';
        } elseif ($percentage >= 65) {
            $rubric_points = 3;       // ME - Meeting Expectations
            $grade = 'ME';
            $rubric_description = 'Meeting Expectations';
        } elseif ($percentage >= 50) {
            $rubric_points = 2;       // AE - Approaching Expectations
            $grade = 'AE';
            $rubric_description = 'Approaching Expectations';
        } elseif ($percentage >= 30) {
            $rubric_points = 1;       // BE - Below Expectations
            $grade = 'BE';
            $rubric_description = 'Below Expectations';
        } else {
            $rubric_points = 1;       // BE - Below Expectations (below 30)
            $grade = 'BE';
            $rubric_description = 'Below Expectations';
        }
        
        // Check if score already exists
        $check_sql = "SELECT id, score_value FROM tblscores 
                      WHERE school_id = ? AND student_id = ? AND subject_id = ? AND exam_id = ? 
                      AND class_id = ? AND StreamId = ?";
        $check_stmt = $conn->prepare($check_sql);
        if (!$check_stmt) {
            error_log("Prepare failed: " . $conn->error);
            $failed++;
            $response['details'][] = [
                'student_id' => $student_id,
                'status' => 'failed',
                'message' => 'Database error'
            ];
            continue;
        }
        
        $check_stmt->bind_param("iiiiii", $school_id, $student_id, $subject_id, $exam_id, $class_id, $stream_id);
        
        if (!$check_stmt->execute()) {
            error_log("Execute failed: " . $check_stmt->error);
            $failed++;
            $response['details'][] = [
                'student_id' => $student_id,
                'status' => 'failed',
                'message' => 'Database error'
            ];
            $check_stmt->close();
            continue;
        }
        
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            // Update existing score - store points in rubric column
            $existing_row = $check_result->fetch_assoc();
            $old_score = $existing_row['score_value'];
            
            // Update the score with rubric points in the rubric column
            $update_sql = "UPDATE tblscores 
                          SET score_value = ?, 
                              total_score = ?, 
                              percentage = ?, 
                              rubric = ?,        -- Stores points (1-4)
                              grade = ?,          -- Stores grade code (EE, ME, AE, BE)
                              recorded_by_teacher_id = ?, 
                              recorded_at = NOW() 
                          WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            if (!$update_stmt) {
                error_log("Update prepare failed: " . $conn->error);
                $failed++;
                $response['details'][] = [
                    'student_id' => $student_id,
                    'status' => 'failed',
                    'message' => 'Database error'
                ];
                $check_stmt->close();
                continue;
            }
            
            $update_stmt->bind_param("dddisii", 
                $score, 
                $total_score, 
                $percentage, 
                $rubric_points,     // Points (1-4) stored in rubric column
                $grade,             // Short grade (EE, ME, AE, BE)
                $teacher_id, 
                $existing_row['id']
            );
            
            if ($update_stmt->execute()) {
                $processed++;
                $response['details'][] = [
                    'student_id' => $student_id,
                    'status' => 'updated',
                    'message' => 'Score updated successfully',
                    'old_score' => $old_score,
                    'new_score' => $score,
                    'percentage' => round($percentage, 2),
                    'rubric' => $rubric_description,
                    'grade' => $grade,
                    'points' => $rubric_points
                ];
            } else {
                error_log("Update execute failed: " . $update_stmt->error);
                $failed++;
                $response['details'][] = [
                    'student_id' => $student_id,
                    'status' => 'failed',
                    'message' => 'Database error'
                ];
            }
            $update_stmt->close();
        } else {
            // Insert new score with rubric points in the rubric column
            $insert_sql = "INSERT INTO tblscores 
                          (school_id, student_id, subject_id, exam_id, class_id, StreamId,
                           score_value, total_score, percentage, rubric, grade, 
                           recorded_by_teacher_id, recorded_at) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $insert_stmt = $conn->prepare($insert_sql);
            if (!$insert_stmt) {
                error_log("Insert prepare failed: " . $conn->error);
                $failed++;
                $response['details'][] = [
                    'student_id' => $student_id,
                    'status' => 'failed',
                    'message' => 'Database error'
                ];
                $check_stmt->close();
                continue;
            }
            
            $insert_stmt->bind_param("iiiiiidddisi", 
                $school_id, 
                $student_id, 
                $subject_id, 
                $exam_id, 
                $class_id, 
                $stream_id, 
                $score, 
                $total_score, 
                $percentage, 
                $rubric_points,     // Points (1-4) stored in rubric column
                $grade,             // Short grade (EE, ME, AE, BE)
                $teacher_id
            );
            
            if ($insert_stmt->execute()) {
                $processed++;
                $response['details'][] = [
                    'student_id' => $student_id,
                    'status' => 'created',
                    'message' => 'Score created successfully',
                    'score' => $score,
                    'percentage' => round($percentage, 2),
                    'rubric' => $rubric_description,
                    'grade' => $grade,
                    'points' => $rubric_points
                ];
            } else {
                error_log("Insert execute failed: " . $insert_stmt->error);
                $failed++;
                $response['details'][] = [
                    'student_id' => $student_id,
                    'status' => 'failed',
                    'message' => 'Database error'
                ];
            }
            $insert_stmt->close();
        }
        
        $check_stmt->close();
    }
    
    // Commit transaction
    $conn->commit();
    
    $response['success'] = true;
    $response['message'] = "Scores processed successfully";
    $response['saved_count'] = $processed;
    $response['failed_count'] = $failed;
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    error_log("Exception: " . $e->getMessage());
    error_log("Trace: " . $e->getTraceAsString());
    
    $response['success'] = false;
    $response['message'] = "Server error occurred";
    $response['error'] = $e->getMessage();
} finally {
    $conn->close();
}

// Ensure no output before this point
error_log("Response: " . json_encode($response));
echo json_encode($response);
exit();
?>