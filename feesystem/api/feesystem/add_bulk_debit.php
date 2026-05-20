<?php
// /feesystem/api/feesystem/add_bulk_debit.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'finance') {
    echo json_encode(['success' => false, 'message' => 'Access denied. Finance privileges required.']);
    exit;
}

require_once '../../includes/config.php';

$database = Database::getInstance();
$db = $database->getConnection();

$data = json_decode(file_get_contents('php://input'), true);
$school_id = $data['school_id'] ?? $_SESSION['school_id'];
$class_id = $data['class_id'] ?? 0;
$stream_id = $data['stream_id'] ?? null;
$term = $data['term'] ?? 1;
$year = $data['year'] ?? date('Y');
$vote_head_id = $data['vote_head_id'] ?? 0;
$amount = $data['amount'] ?? 0;
$description = $data['description'] ?? '';

if ($class_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Class ID is required']);
    exit;
}

if ($vote_head_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Vote head is required']);
    exit;
}

if ($amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Valid amount is required']);
    exit;
}

try {
    $db->beginTransaction();
    
    // Build query to get students
    $sql = "SELECT id, AdmNo as admission_no, CONCAT(FirstName, ' ', SecondName, ' ', COALESCE(LastName, '')) as full_name 
            FROM tblstudents 
            WHERE school_id = ? AND class_id = ? AND Status = 'Active'";
    $params = [$school_id, $class_id];
    
    if ($stream_id) {
        $sql .= " AND StreamId = ?";
        $params[] = $stream_id;
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($students)) {
        echo json_encode(['success' => false, 'message' => 'No students found for the selected criteria']);
        $db->rollBack();
        exit;
    }
    
    // Check if fee_transactions table exists, if not create it
    $table_check = $db->query("SHOW TABLES LIKE 'fee_transactions'");
    if ($table_check->rowCount() == 0) {
        $create_table = "
        CREATE TABLE IF NOT EXISTS `fee_transactions` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `student_id` int(11) NOT NULL,
            `group_id` int(11) DEFAULT NULL,
            `amount` decimal(12,2) NOT NULL,
            `transaction_type` enum('debit','credit','payment') NOT NULL,
            `academic_year` int(11) NOT NULL,
            `term` int(11) NOT NULL,
            `description` text DEFAULT NULL,
            `school_id` int(11) NOT NULL,
            `created_at` timestamp NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `idx_fee_transactions_student` (`student_id`),
            KEY `idx_fee_transactions_group` (`group_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
        $db->exec($create_table);
    }
    
    // Insert debit for each student
    $insert_stmt = $db->prepare("
        INSERT INTO fee_transactions (student_id, group_id, amount, transaction_type, academic_year, term, description, school_id, created_at)
        VALUES (?, ?, ?, 'debit', ?, ?, ?, ?, NOW())
    ");
    
    $affected_count = 0;
    
    foreach ($students as $student) {
        $insert_stmt->execute([
            $student['id'],
            null,
            $amount,
            $year,
            $term,
            $description,
            $school_id
        ]);
        $affected_count++;
    }
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "Debit added to {$affected_count} student(s) successfully",
        'affected_count' => $affected_count
    ]);
    
} catch (PDOException $e) {
    $db->rollBack();
    error_log("Add bulk debit PDO error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    $db->rollBack();
    error_log("Add bulk debit error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>