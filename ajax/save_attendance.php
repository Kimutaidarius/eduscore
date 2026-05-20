<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['school_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

require_once '../includes/config.php';

$teacher_id = $_SESSION['teacher_id'];
$school_id = $_SESSION['school_id'];

$input = json_decode(file_get_contents('php://input'), true);
$records = $input['records'] ?? [];

if (empty($records)) {
    echo json_encode(['success' => false, 'message' => 'No records to save']);
    exit();
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Check if tblattendance table exists
$checkTableQuery = "SHOW TABLES LIKE 'tblattendance'";
$checkResult = $conn->query($checkTableQuery);
if ($checkResult->num_rows == 0) {
    // Create attendance table if it doesn't exist
    $createTableQuery = "
        CREATE TABLE IF NOT EXISTS `tblattendance` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `school_id` int(11) NOT NULL,
            `student_id` int(11) NOT NULL,
            `attendance_date` date NOT NULL,
            `status` enum('Present','Absent','Late','Excused','Sick') NOT NULL DEFAULT 'Present',
            `remarks` text DEFAULT NULL,
            `term_id` int(11) DEFAULT NULL,
            `created_by` int(11) DEFAULT NULL,
            `updated_by` int(11) DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `school_id` (`school_id`),
            KEY `student_id` (`student_id`),
            KEY `term_id` (`term_id`),
            KEY `attendance_date` (`attendance_date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    if (!$conn->query($createTableQuery)) {
        echo json_encode(['success' => false, 'message' => 'Failed to create attendance table']);
        exit();
    }
}

$conn->begin_transaction();

try {
    $success_count = 0;
    $failed_records = [];
    
    foreach ($records as $record) {
        $student_id = $record['student_id'];
        $status = $record['status'];
        $remarks = $record['remarks'] ?? '';
        $date = $record['date'];
        $term_id = $record['term_id'] ?? null;
        
        // Validate required fields
        if (!$student_id || !$date) {
            $failed_records[] = $student_id;
            continue;
        }
        
        // Check if attendance record exists
        $checkQuery = "SELECT id FROM tblattendance 
                      WHERE student_id = ? AND attendance_date = ?";
        $params = [$student_id, $date];
        $types = "is";
        
        if ($term_id) {
            $checkQuery .= " AND term_id = ?";
            $params[] = $term_id;
            $types .= "i";
        } else {
            $checkQuery .= " AND term_id IS NULL";
        }
        
        $stmt = $conn->prepare($checkQuery);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Update existing record
            $row = $result->fetch_assoc();
            $updateQuery = "UPDATE tblattendance 
                           SET status = ?, remarks = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP 
                           WHERE id = ?";
            $updateStmt = $conn->prepare($updateQuery);
            $updateStmt->bind_param("ssii", $status, $remarks, $teacher_id, $row['id']);
            
            if ($updateStmt->execute()) {
                $success_count++;
            } else {
                $failed_records[] = $student_id;
            }
            $updateStmt->close();
        } else {
            // Insert new record
            $insertQuery = "INSERT INTO tblattendance 
                           (school_id, student_id, attendance_date, status, remarks, term_id, created_by) 
                           VALUES (?, ?, ?, ?, ?, ?, ?)";
            $insertStmt = $conn->prepare($insertQuery);
            $insertStmt->bind_param("iissssi", $school_id, $student_id, $date, $status, $remarks, $term_id, $teacher_id);
            
            if ($insertStmt->execute()) {
                $success_count++;
            } else {
                $failed_records[] = $student_id;
            }
            $insertStmt->close();
        }
        $stmt->close();
    }
    
    $conn->commit();
    
    $message = "$success_count records saved successfully";
    if (!empty($failed_records)) {
        $message .= ". " . count($failed_records) . " records failed.";
    }
    
    echo json_encode([
        'success' => true, 
        'message' => $message,
        'data' => [
            'successful' => $success_count,
            'failed' => count($failed_records),
            'failed_records' => $failed_records
        ]
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>