<?php
session_start();
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once('../../includes/config.php');

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$school_id = $_SESSION['school_id'];
$class_id = $_POST['class_id'] ?? 0;
$vote_head_id = $_POST['vote_head_id'] ?? 0;
$year = $_POST['year'] ?? date('Y');
$term = $_POST['term'] ?? 1;
$recorded_at = $_POST['recorded_at'] ?? date('Y-m-d');

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
    exit;
}

try {
    global $db;
    
    if (!isset($db)) {
        throw new Exception('Database connection not established');
    }
    
    $file = $_FILES['file']['tmp_name'];
    $extension = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
    
    // Parse the file based on extension
    $rows = [];
    
    if ($extension == 'csv') {
        // Handle CSV file
        if (($handle = fopen($file, 'r')) !== false) {
            while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                $rows[] = $data;
            }
            fclose($handle);
        }
    } else {
        // For Excel files, we need to use a simpler approach or suggest CSV
        // Since PhpSpreadsheet might not be installed, let's provide a helpful message
        echo json_encode([
            'success' => false, 
            'message' => 'Please upload CSV files. For Excel files (.xls, .xlsx), please convert to CSV first or install PhpSpreadsheet.'
        ]);
        exit;
    }
    
    if (count($rows) < 2) {
        throw new Exception('File contains no data rows');
    }
    
    // Get headers (first row)
    $headers = array_map('strtolower', $rows[0]);
    $admission_col = null;
    $name_col = null;
    $amount_col = null;
    
    // Find columns
    foreach ($headers as $index => $header) {
        if (strpos($header, 'admission') !== false || strpos($header, 'adm') !== false || strpos($header, 'reg') !== false) {
            $admission_col = $index;
        } elseif (strpos($header, 'name') !== false) {
            $name_col = $index;
        } elseif (strpos($header, 'balance') !== false || strpos($header, 'amount') !== false || strpos($header, 'fee') !== false) {
            $amount_col = $index;
        }
    }
    
    if ($admission_col === null || $amount_col === null) {
        throw new Exception('Required columns not found. Need Admission Number and Balance columns.');
    }
    
    $db->beginTransaction();
    
    $imported_count = 0;
    $updated_count = 0;
    $errors = [];
    
    // Skip header row
    for ($i = 1; $i < count($rows); $i++) {
        $row = $rows[$i];
        $admission_no = trim($row[$admission_col] ?? '');
        $amount = floatval($row[$amount_col] ?? 0);
        
        if (empty($admission_no) || $amount <= 0) {
            continue;
        }
        
        // Find student by admission number
        $student_stmt = $db->prepare("SELECT id FROM tblstudents 
                                      WHERE school_id = :school_id 
                                      AND AdmNo = :admission_no 
                                      AND class_id = :class_id 
                                      AND Status = 'Active'");
        $student_stmt->execute([
            ':school_id' => $school_id,
            ':admission_no' => $admission_no,
            ':class_id' => $class_id
        ]);
        $student = $student_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$student) {
            $errors[] = "Student not found: $admission_no";
            continue;
        }
        
        // Check if balance record exists
        $check_stmt = $db->prepare("SELECT id FROM student_balances 
                                    WHERE student_id = :student_id 
                                    AND academic_year = :year 
                                    AND term = :term 
                                    AND vote_head_id = :vote_head_id");
        $check_stmt->execute([
            ':student_id' => $student['id'],
            ':year' => $year,
            ':term' => $term,
            ':vote_head_id' => $vote_head_id
        ]);
        
        $existing = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            // Update existing record - add to balance
            $stmt = $db->prepare("UPDATE student_balances 
                                  SET balance = balance + :amount, 
                                      last_updated = NOW(),
                                      recorded_at = :recorded_at,
                                      recorded_by = :recorded_by
                                  WHERE student_id = :student_id 
                                  AND academic_year = :year 
                                  AND term = :term 
                                  AND vote_head_id = :vote_head_id");
            $updated_count++;
        } else {
            // Insert new record
            $stmt = $db->prepare("INSERT INTO student_balances 
                                  (student_id, academic_year, term, vote_head_id, balance, recorded_at, recorded_by, created_at, last_updated) 
                                  VALUES (:student_id, :year, :term, :vote_head_id, :amount, :recorded_at, :recorded_by, NOW(), NOW())");
            $imported_count++;
        }
        
        $stmt->execute([
            ':student_id' => $student['id'],
            ':year' => $year,
            ':term' => $term,
            ':vote_head_id' => $vote_head_id,
            ':amount' => $amount,
            ':recorded_at' => $recorded_at,
            ':recorded_by' => $_SESSION['user_id'] ?? 0
        ]);
        
        // Also record in fee_transactions for audit trail
        $transaction_stmt = $db->prepare("INSERT INTO fee_transactions 
                                          (student_id, vote_head_id, amount, transaction_type, academic_year, term, description, created_at) 
                                          VALUES (:student_id, :vote_head_id, :amount, 'initial_balance', :year, :term, :description, NOW())");
        
        $transaction_stmt->execute([
            ':student_id' => $student['id'],
            ':vote_head_id' => $vote_head_id,
            ':amount' => $amount,
            ':year' => $year,
            ':term' => $term,
            ':description' => "Initial balance import on " . date('Y-m-d H:i:s')
        ]);
    }
    
    $db->commit();
    
    $message = "Imported {$imported_count} new balances";
    if ($updated_count > 0) {
        $message .= ", updated {$updated_count} existing balances";
    }
    $message .= " successfully.";
    
    if (!empty($errors)) {
        $message .= " Errors: " . implode(', ', array_slice($errors, 0, 5));
        if (count($errors) > 5) {
            $message .= " and " . (count($errors) - 5) . " more...";
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'imported_count' => $imported_count,
        'updated_count' => $updated_count,
        'errors' => $errors
    ]);
    
} catch (Exception $e) {
    if (isset($db)) {
        $db->rollBack();
    }
    error_log("Error in import_initial_balances: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>