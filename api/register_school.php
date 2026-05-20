<?php
// api/register_school.php

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set the correct path to config.php
$configPath = __DIR__ . '/../includes/config.php';

if (file_exists($configPath)) {
    require_once $configPath;
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Configuration file not found'
    ]);
    exit;
}

// Check if $db is set after including config
if (!isset($db) || !($db instanceof PDO)) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Database connection not established'
    ]);
    exit;
}

// Load EmailHelper
require_once __DIR__ . '/../includes/EmailHelper.php';

// Set header to JSON
header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method. Use POST.']);
    exit;
}

// Get the JSON input or form data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if ($input && json_last_error() !== JSON_ERROR_NONE) {
    $data = $_POST;
}

if (empty($data)) {
    $data = $_POST;
}

error_log("Received registration data: " . print_r($data, true));

// Parse multi-select values if they come as JSON strings
if (isset($data['institution_level']) && is_string($data['institution_level'])) {
    $decoded = json_decode($data['institution_level'], true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $data['institution_level'] = $decoded;
    }
}

if (isset($data['product_type']) && is_string($data['product_type'])) {
    $decoded = json_decode($data['product_type'], true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $data['product_type'] = $decoded;
    }
}

// Validate required fields
$errors = [];
$requiredFields = [
    'school_name', 'school_email', 'school_phone', 'institution_type', 
    'institution_level', 'county', 'total_students', 'product_type',
    'school_motto', 'admin_name', 'admin_email', 'password', 'confirm_password'
];

foreach ($requiredFields as $field) {
    if (empty($data[$field])) {
        $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
    }
}

// Validate email format
if (!empty($data['school_email']) && !filter_var($data['school_email'], FILTER_VALIDATE_EMAIL)) {
    $errors['school_email'] = 'Please enter a valid school email address';
}

if (!empty($data['admin_email']) && !filter_var($data['admin_email'], FILTER_VALIDATE_EMAIL)) {
    $errors['admin_email'] = 'Please enter a valid admin email address';
}

// Validate phone format
if (!empty($data['school_phone']) && !preg_match('/^[\d\s\-\+\(\)]{8,}$/', $data['school_phone'])) {
    $errors['school_phone'] = 'Please enter a valid school phone number';
}

// Validate password
if (!empty($data['password']) && strlen($data['password']) < 6) {
    $errors['password'] = 'Password must be at least 6 characters';
}

if (!empty($data['password']) && !empty($data['confirm_password']) && $data['password'] !== $data['confirm_password']) {
    $errors['confirm_password'] = 'Passwords do not match';
}

// Validate terms
if (empty($data['terms'])) {
    $errors['terms'] = 'You must agree to the terms and conditions';
}

// Check if school email already exists
try {
    $checkSchoolEmail = $db->prepare("SELECT id FROM tblschoolinfo WHERE school_email = ?");
    $checkSchoolEmail->execute([$data['school_email'] ?? '']);
    if ($checkSchoolEmail->rowCount() > 0) {
        $errors['school_email'] = 'This school email is already registered';
    }
} catch (Exception $e) {
    error_log("Error checking school email: " . $e->getMessage());
}

// Check if admin email already exists
try {
    $checkAdminEmail = $db->prepare("SELECT id FROM tblteachers WHERE email = ?");
    $checkAdminEmail->execute([$data['admin_email'] ?? '']);
    if ($checkAdminEmail->rowCount() > 0) {
        $errors['admin_email'] = 'This admin email is already registered';
    }
} catch (Exception $e) {
    error_log("Error checking admin email: " . $e->getMessage());
}

// Return errors if any
if (!empty($errors)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Please fix the validation errors',
        'errors' => $errors
    ]);
    exit;
}

// Start transaction
try {
    $db->beginTransaction();

    // Helper functions
    function generateActivationCode() {
        return strtoupper(bin2hex(random_bytes(4)));
    }

    function generateTeacherNumber($db, $school_id) {
        $baseNumber = 'TCH-' . str_pad($school_id, 4, '0', STR_PAD_LEFT);
        
        $checkStmt = $db->prepare("SELECT COUNT(*) as count FROM tblteachers WHERE teacher_number = ?");
        $checkStmt->execute([$baseNumber]);
        $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] == 0) {
            return $baseNumber;
        }
        
        $attempts = 0;
        $maxAttempts = 10;
        
        while ($attempts < $maxAttempts) {
            $randomSuffix = strtoupper(bin2hex(random_bytes(1)));
            $teacherNumber = 'TCH-' . str_pad($school_id, 3, '0', STR_PAD_LEFT) . $randomSuffix;
            
            $checkStmt->execute([$teacherNumber]);
            $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['count'] == 0) {
                return $teacherNumber;
            }
            
            $attempts++;
        }
        
        return 'TCH-' . time() . rand(100, 999);
    }

    $license_tier = 'Basic';
    $trial_expires = date('Y-m-d', strtotime('+30 days'));
    $activation_code = generateActivationCode();
    $principal_name = trim($data['admin_name']);
    
    // Process multi-select values
    $institution_level = is_array($data['institution_level']) 
        ? implode(',', $data['institution_level']) 
        : trim($data['institution_level']);
    
    $product_type = is_array($data['product_type']) 
        ? implode(',', $data['product_type']) 
        : trim($data['product_type']);
    
    // Map institution_type to school_type (public/private)
    $institution_type = strtolower(trim($data['institution_type']));
    if (strpos($institution_type, 'public') !== false) {
        $school_type = 'public';
    } elseif (strpos($institution_type, 'private') !== false || strpos($institution_type, 'international') !== false) {
        $school_type = 'private';
    } else {
        $school_type = 'public';
    }
    
    // Generate school initials from school name
    $school_initials = generateSchoolInitials($data['school_name']);
    
    // Insert into tblschoolinfo - MATCHING DATABASE SCHEMA EXACTLY
    $insertSchool = $db->prepare("
        INSERT INTO tblschoolinfo (
            school_name, 
            school_email, 
            school_motto, 
            school_address, 
            county,
            school_phone, 
            total_students, 
            institution_level, 
            school_type, 
            product_type,
            license_tier, 
            principal_name, 
            principal_email, 
            activation_code, 
            is_activated, 
            status, 
            activation_status,
            activation_locked,
            created_at, 
            updated_at, 
            sms_balance, 
            sms_monthly_limit,
            school_initials,
            subscription_expiry_date
        ) VALUES (
            :school_name, 
            :school_email, 
            :school_motto, 
            :school_address, 
            :county,
            :school_phone, 
            :total_students, 
            :institution_level, 
            :school_type, 
            :product_type,
            :license_tier, 
            :principal_name, 
            :principal_email, 
            :activation_code, 
            0,
            'pending',
            'pending',
            1,
            NOW(), 
            NOW(), 
            0, 
            1000,
            :school_initials,
            DATE_ADD(NOW(), INTERVAL 30 DAY)
        )
    ");
    
    $insertSchool->execute([
        ':school_name' => trim($data['school_name']),
        ':school_email' => trim($data['school_email']),
        ':school_motto' => trim($data['school_motto']),
        ':school_address' => trim($data['school_address'] ?? ''),
        ':county' => trim($data['county']),
        ':school_phone' => trim($data['school_phone']),
        ':total_students' => intval($data['total_students']),
        ':institution_level' => $institution_level,
        ':school_type' => $school_type,
        ':product_type' => $product_type,
        ':license_tier' => $license_tier,
        ':principal_name' => $principal_name,
        ':principal_email' => trim($data['admin_email']),
        ':activation_code' => $activation_code,
        ':school_initials' => $school_initials
    ]);
    
    $school_id = $db->lastInsertId();
    
    if (!$school_id || $school_id == 0) {
        throw new Exception("Failed to get valid school ID");
    }
    
    error_log("School created with ID: " . $school_id);

    // Hash password for login
    $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
    
    // Generate teacher number
    $teacher_number = generateTeacherNumber($db, $school_id);
    error_log("Generated teacher number: " . $teacher_number);
    
    // Split admin name into parts
    $admin_name_parts = explode(' ', trim($data['admin_name']), 3);
    $firstname = $admin_name_parts[0];
    $secondname = isset($admin_name_parts[1]) ? $admin_name_parts[1] : '';
    $lastname = isset($admin_name_parts[2]) ? $admin_name_parts[2] : '';
    
    // Insert admin as teacher into tblteachers - MATCHING DATABASE SCHEMA EXACTLY
    $insertTeacher = $db->prepare("
        INSERT INTO tblteachers (
            teacher_number, 
            school_id, 
            firstname, 
            secondname, 
            lastname, 
            email, 
            phonenumber, 
            password, 
            role, 
            status, 
            RegDate, 
            UpdationDate, 
            created_at
        ) VALUES (
            :teacher_number, 
            :school_id, 
            :firstname, 
            :secondname, 
            :lastname,
            :email, 
            :phonenumber, 
            :password, 
            'Super Admin', 
            'Active', 
            NOW(), 
            NOW(), 
            NOW()
        )
    ");
    
    $insertTeacher->execute([
        ':teacher_number' => $teacher_number,
        ':school_id' => $school_id,
        ':firstname' => $firstname,
        ':secondname' => $secondname,
        ':lastname' => $lastname,
        ':email' => trim($data['admin_email']),
        ':phonenumber' => trim($data['admin_phone'] ?? $data['school_phone']),
        ':password' => $hashed_password
    ]);
    
    $admin_id = $db->lastInsertId();
    error_log("Admin created with ID: " . $admin_id);

    // Insert default roles
    try {
        $defaultRoles = ['Super Admin', 'Admin', 'Teacher', 'Head Teacher', 'Deputy Head Teacher'];
        $insertRole = $db->prepare("INSERT INTO roles (school_id, role_name, created_at) VALUES (:school_id, :role_name, NOW())");
        foreach ($defaultRoles as $role) {
            $insertRole->execute([
                ':school_id' => $school_id,
                ':role_name' => $role
            ]);
        }
        error_log("Roles inserted successfully");
    } catch (PDOException $e) {
        error_log("Roles insertion: " . $e->getMessage());
    }

    // Insert default terms
    try {
        $currentYear = date('Y');
        $terms = [
            ['Term 1', 1, $currentYear, "$currentYear-01-01", "$currentYear-04-30", 1],
            ['Term 2', 2, $currentYear, "$currentYear-05-01", "$currentYear-08-04", 0],
            ['Term 3', 3, $currentYear, "$currentYear-08-05", "$currentYear-12-31", 0]
        ];
        
        $insertTerm = $db->prepare("
            INSERT INTO tblterms (
                school_id, term_name, term_number, academic_year, 
                start_date, end_date, is_current, creation_date, updation_date
            ) VALUES (
                :school_id, :term_name, :term_number, :academic_year,
                :start_date, :end_date, :is_current, NOW(), NOW()
            )
        ");
        
        foreach ($terms as $term) {
            $insertTerm->execute([
                ':school_id' => $school_id,
                ':term_name' => $term[0],
                ':term_number' => $term[1],
                ':academic_year' => $term[2],
                ':start_date' => $term[3],
                ':end_date' => $term[4],
                ':is_current' => $term[5]
            ]);
        }
        error_log("Terms inserted successfully");
    } catch (PDOException $e) {
        error_log("Terms insertion: " . $e->getMessage());
    }

    // Create subscription
    try {
        $subscriptionExpires = date('Y-m-d H:i:s', strtotime('+30 days'));
        $insertSubscription = $db->prepare("
            INSERT INTO subscriptions (
                school_id, plan_name, is_trial, status, auto_renew, 
                started_at, expires_at, created_at, updated_at
            ) VALUES (
                :school_id, 'Free Trial', 1, 'active', 1, NOW(), :expires_at, NOW(), NOW()
            )
        ");
        $insertSubscription->execute([
            ':school_id' => $school_id,
            ':expires_at' => $subscriptionExpires
        ]);
        error_log("Subscription created successfully");
    } catch (PDOException $e) {
        error_log("Subscription insertion: " . $e->getMessage());
    }

    // Commit transaction
    $db->commit();
    
    // ============================================
    // SEND WELCOME EMAILS
    // ============================================
    $adminWelcomeSent = false;
    $schoolConfirmationSent = false;
    
    try {
        $emailHelper = new EmailHelper();
        
        $adminWelcomeSent = $emailHelper->sendWelcomeEmail(
            trim($data['admin_email']),
            trim($data['admin_name']),
            $teacher_number,
            $data['password']
        );
        
        $schoolConfirmationSent = $emailHelper->sendSchoolConfirmationEmail(
            trim($data['school_email']),
            trim($data['school_name']),
            trim($data['admin_name']),
            $school_id,
            $activation_code
        );
        
        if ($adminWelcomeSent) {
            error_log("Admin welcome email sent to: " . $data['admin_email']);
        }
        if ($schoolConfirmationSent) {
            error_log("School confirmation email sent to: " . $data['school_email']);
        }
        
    } catch (Exception $e) {
        error_log("Email error: " . $e->getMessage());
    }
    
    // Prepare success response
    $response = [
        'success' => true,
        'message' => 'Registration successful! ' . 
            (($adminWelcomeSent || $schoolConfirmationSent) ? 
                'Welcome emails have been sent.' : 
                'Registration complete.'),
        'school_id' => $school_id,
        'admin_id' => $admin_id,
        'teacher_number' => $teacher_number,
        'license_tier' => $license_tier,
        'trial_expires' => date('F d, Y', strtotime($trial_expires)),
        'redirect_url' => '../login.php',
        'principal_name' => $principal_name,
        'activation_code' => $activation_code,
        'school_initials' => $school_initials,
        'emails_sent' => [
            'admin_welcome' => $adminWelcomeSent,
            'school_confirmation' => $schoolConfirmationSent
        ]
    ];

    http_response_code(200);
    echo json_encode($response);

} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Registration PDO error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Registration failed: Database error occurred'
    ]);
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Registration error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Registration failed: ' . $e->getMessage()
    ]);
}

/**
 * Generate school initials from school name
 */
function generateSchoolInitials($school_name) {
    $words = preg_split('/\s+/', trim($school_name));
    $initials = '';
    foreach ($words as $word) {
        if (!empty($word)) {
            $initials .= strtoupper(substr($word, 0, 1));
        }
    }
    return substr($initials, 0, 10);
}
?>