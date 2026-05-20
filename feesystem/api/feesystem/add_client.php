<?php
header('Content-Type: application/json');
session_start();

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if user has finance access
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'finance') {
    echo json_encode(['success' => false, 'message' => 'Access denied. Finance privileges required.']);
    exit;
}

require_once('../../includes/config.php');

// Get PDO connection from Database class
$database = Database::getInstance();
$pdo = $database->getConnection();

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit;
}

// Validate required fields for ADD (no client_id needed)
$school_id = isset($input['school_id']) ? intval($input['school_id']) : 0;
$name = isset($input['name']) ? trim($input['name']) : '';
$phone = isset($input['phone']) ? trim($input['phone']) : '';

if ($school_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'School ID is required']);
    exit;
}

if (empty($name)) {
    echo json_encode(['success' => false, 'message' => 'Client name is required']);
    exit;
}

if (empty($phone)) {
    echo json_encode(['success' => false, 'message' => 'Phone number is required']);
    exit;
}

// Sanitize input data
$id_number = isset($input['id_number']) && !empty($input['id_number']) ? trim($input['id_number']) : null;
$contact = isset($input['contact']) && !empty($input['contact']) ? trim($input['contact']) : null;
$email = isset($input['email']) && !empty($input['email']) ? trim($input['email']) : null;
$address = isset($input['address']) && !empty($input['address']) ? trim($input['address']) : null;

// Validate email format if provided
if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

// Validate phone number (basic validation)
if (!preg_match('/^[0-9+\-\s()]+$/', $phone)) {
    echo json_encode(['success' => false, 'message' => 'Invalid phone number format']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Check if client with same name already exists for this school
    $check_sql = "SELECT id FROM clients WHERE school_id = :school_id AND name = :name";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([':school_id' => $school_id, ':name' => $name]);
    
    if ($check_stmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'A client with this name already exists']);
        $pdo->rollBack();
        exit;
    }
    
    // Check if client with same phone already exists for this school
    $check_phone_sql = "SELECT id FROM clients WHERE school_id = :school_id AND phone = :phone";
    $check_phone_stmt = $pdo->prepare($check_phone_sql);
    $check_phone_stmt->execute([':school_id' => $school_id, ':phone' => $phone]);
    
    if ($check_phone_stmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'A client with this phone number already exists']);
        $pdo->rollBack();
        exit;
    }
    
    // Check if email already exists (if provided)
    if (!empty($email)) {
        $check_email_sql = "SELECT id FROM clients WHERE school_id = :school_id AND email = :email";
        $check_email_stmt = $pdo->prepare($check_email_sql);
        $check_email_stmt->execute([':school_id' => $school_id, ':email' => $email]);
        
        if ($check_email_stmt->rowCount() > 0) {
            echo json_encode(['success' => false, 'message' => 'A client with this email already exists']);
            $pdo->rollBack();
            exit;
        }
    }
    
    // Insert new client
    $insert_sql = "INSERT INTO clients (school_id, name, id_number, contact, phone, email, address, created_at, updated_at) 
                   VALUES (:school_id, :name, :id_number, :contact, :phone, :email, :address, NOW(), NOW())";
    
    $insert_stmt = $pdo->prepare($insert_sql);
    $result = $insert_stmt->execute([
        ':school_id' => $school_id,
        ':name' => $name,
        ':id_number' => $id_number,
        ':contact' => $contact,
        ':phone' => $phone,
        ':email' => $email,
        ':address' => $address
    ]);
    
    if ($result) {
        $client_id = $pdo->lastInsertId();
        
        // Log the activity (skip if activity_logs table doesn't exist)
        $user_id = $_SESSION['user_id'] ?? 0;
        $user_email = $_SESSION['email'] ?? 'Unknown';
        
        // Check if activity_logs table exists
        $log_table_check = $pdo->query("SHOW TABLES LIKE 'activity_logs'");
        if ($log_table_check->rowCount() > 0) {
            $log_sql = "INSERT INTO activity_logs (school_id, user_id, user_email, action, details, ip_address, created_at) 
                        VALUES (:school_id, :user_id, :user_email, 'add_client', :details, :ip_address, NOW())";
            $log_stmt = $pdo->prepare($log_sql);
            $details = "Added new client: $name (ID: $client_id)";
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
            $log_stmt->execute([
                ':school_id' => $school_id,
                ':user_id' => $user_id,
                ':user_email' => $user_email,
                ':details' => $details,
                ':ip_address' => $ip_address
            ]);
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Client added successfully',
            'client_id' => $client_id
        ]);
    } else {
        throw new Exception("Failed to add client");
    }
    
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Add client PDO error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Add client error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>