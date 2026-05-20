<?php
session_start();
header('Content-Type: application/json');
require_once('../../includes/config.php');

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        throw new Exception('Invalid request data');
    }
    
    $type = $data['type'] ?? 'school';
    
    // Only allow school registration
    if ($type !== 'school') {
        throw new Exception('Only school registration is allowed');
    }
    
    $password = $data['password'] ?? '';
    
    // Validate password
    if (strlen($password) < 6) {
        throw new Exception('Password must be at least 6 characters');
    }
    
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // School registration
    $schoolName = $data['school_name'] ?? '';
    $registrationNo = $data['registration_no'] ?? '';
    $email = $data['email'] ?? '';
    $phone = $data['phone'] ?? '';
    $county = $data['county'] ?? '';
    $schoolType = $data['school_type'] ?? '';
    $principalName = $data['principal_name'] ?? '';
    $principalPhone = $data['principal_phone'] ?? '';
    $address = $data['address'] ?? '';
    $postalCode = $data['postal_code'] ?? '';
    
    // Validate required fields
    if (empty($schoolName) || empty($email) || empty($phone) || empty($schoolType)) {
        throw new Exception('School Name, Email, Phone, and School Type are required');
    }
    
    // Check if school already exists
    $checkStmt = $dbh->prepare("SELECT id FROM tblschoolinfo WHERE school_email = ? OR school_phone = ?");
    $checkStmt->execute([$email, $phone]);
    if ($checkStmt->fetch()) {
        throw new Exception('School already registered with this email or phone');
    }
    
    // Insert school
    $stmt = $dbh->prepare("
        INSERT INTO tblschoolinfo 
        (school_name, school_email, school_phone, county, institution_level, license_tier, status, address, created_at) 
        VALUES (?, ?, ?, ?, 'primary', 'Basic', 'pending', ?, NOW())
    ");
    $addressFull = $address . ($postalCode ? ", " . $postalCode : "");
    $stmt->execute([$schoolName, $email, $phone, $county, $addressFull]);
    $schoolId = $dbh->lastInsertId();
    
    // Insert admin/principal as teacher
    $teacherStmt = $dbh->prepare("
        INSERT INTO tblteachers 
        (school_id, firstname, lastname, email, phonenumber, role, plain_password, password, status, created_at) 
        VALUES (?, ?, ?, ?, ?, 'Super Admin', ?, ?, 'Active', NOW())
    ");
    $teacherStmt->execute([
        $schoolId,
        $principalName ?: $schoolName,
        'Admin',
        $email,
        $principalPhone ?: $phone,
        $password, // Store plain for admin reference
        $hashedPassword
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'School registered successfully! Please wait for admin approval.'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>