<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/includes/config.php';

header('Content-Type: application/json');

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode([
        'success' => false, 
        'message' => 'No data received'
    ]);
    exit;
}

// Basic validation
$errors = [];

if (empty($data['school_name'])) $errors['school_name'] = 'School name is required';
if (empty($data['school_email'])) $errors['school_email'] = 'School email is required';
if (empty($data['school_phone'])) $errors['school_phone'] = 'School phone is required';
if (empty($data['institution_type'])) $errors['institution_type'] = 'Institution type is required';
if (empty($data['institution_level'])) $errors['institution_level'] = 'Institution level is required';
if (empty($data['county'])) $errors['county'] = 'County is required';
if (empty($data['total_students'])) $errors['total_students'] = 'Total students is required';
if (empty($data['product_type'])) $errors['product_type'] = 'Product type is required';
if (empty($data['school_motto'])) $errors['school_motto'] = 'School motto is required';
if (empty($data['admin_name'])) $errors['admin_name'] = 'Admin name is required';
if (empty($data['admin_email'])) $errors['admin_email'] = 'Admin email is required';
if (empty($data['password'])) $errors['password'] = 'Password is required';
if ($data['password'] !== $data['confirm_password']) $errors['confirm_password'] = 'Passwords do not match';

// Password strength validation
$password = $data['password'];
$hasLength = strlen($password) >= 8 && strlen($password) <= 15;
$hasUppercase = preg_match('/[A-Z]/', $password);
$hasLowercase = preg_match('/[a-z]/', $password);
$hasNumber = preg_match('/[0-9]/', $password);
$hasSpecial = preg_match('/[!@#$%^&*()_+\-=\[\]{};:\'\"\\|,.<>\/?]/', $password);

if (!$hasLength || !$hasUppercase || !$hasLowercase || !$hasNumber || !$hasSpecial) {
    $errors['password'] = 'Password does not meet all requirements';
}

if (empty($data['terms'])) $errors['terms'] = 'You must agree to the terms';

if (!empty($errors)) {
    echo json_encode([
        'success' => false, 
        'errors' => $errors,
        'message' => 'Please fix the errors above'
    ]);
    exit;
}

try {
    // Check if email already exists
    $checkStmt = $dbh->prepare("SELECT id FROM schools WHERE school_email = ? OR admin_email = ?");
    $checkStmt->execute([$data['school_email'], $data['admin_email']]);
    
    if ($checkStmt->rowCount() > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Email already registered. Please use a different email.'
        ]);
        exit;
    }
    
    // Hash password
    $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
    
    // Generate unique school code
    $school_code = 'SCH' . strtoupper(substr(uniqid(), -6)) . rand(100, 999);
    
    // Insert school data
    $stmt = $dbh->prepare("
        INSERT INTO schools (
            school_code, school_name, school_email, school_phone, 
            institution_type, institution_level, county, total_students, 
            product_type, school_motto, school_address, 
            admin_name, admin_email, admin_phone, password,
            status, created_at
        ) VALUES (
            ?, ?, ?, ?, 
            ?, ?, ?, ?, 
            ?, ?, ?, 
            ?, ?, ?, ?,
            'pending', NOW()
        )
    ");
    
    $stmt->execute([
        $school_code,
        $data['school_name'],
        $data['school_email'],
        $data['school_phone'],
        $data['institution_type'],
        $data['institution_level'],
        $data['county'],
        $data['total_students'],
        $data['product_type'],
        $data['school_motto'],
        $data['school_address'],
        $data['admin_name'],
        $data['admin_email'],
        $data['admin_phone'],
        $hashedPassword
    ]);
    
    $school_id = $dbh->lastInsertId();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Registration successful!',
        'school_code' => $school_code,
        'license_tier' => 'Trial',
        'trial_expires' => date('Y-m-d', strtotime('+30 days')),
        'redirect_url' => 'login.php',
        'school_id' => $school_id
    ]);
    
} catch (PDOException $e) {
    error_log("Registration error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error. Please try again later.'
    ]);
}
?>