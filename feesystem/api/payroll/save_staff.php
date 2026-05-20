<?php
session_start();
header('Content-Type: application/json');
require_once('../../includes/config.php');

if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    sendResponse(null, 'error', 'Unauthorized access');
}

$school_id = $_SESSION['school_id'] ?? 0;
$input = json_decode(file_get_contents('php://input'), true);

try {
    // Split full_name into first_name and last_name
    $fullName = trim($input['full_name']);
    $nameParts = explode(' ', $fullName, 2);
    $firstName = $nameParts[0];
    $lastName = $nameParts[1] ?? '';
    
    if (isset($input['id']) && !empty($input['id'])) {
        // UPDATE existing staff - includes ALL columns
        $stmt = $db->prepare("
            UPDATE staff SET 
                staff_number = ?,
                title = ?,
                first_name = ?,
                middle_name = ?,
                last_name = ?,
                email = ?,
                gender = ?,
                phone_number = ?,
                id_number = ?,
                department_id = ?,
                position = ?,
                employment_type = ?,
                basic_salary = ?,
                bank_name = ?,
                bank_account = ?,
                bank = ?,
                account_number = ?,
                kra_pin = ?,
                nssf_no = ?,
                nhif_no = ?,
                hire_date = ?,
                status = ?
            WHERE id = ? AND school_id = ?
        ");
        
        $result = $stmt->execute([
            $input['staff_id'],                    // staff_number
            $input['title'] ?? null,               // title
            $firstName,                            // first_name
            $input['middle_name'] ?? null,         // middle_name
            $lastName,                             // last_name
            $input['email'] ?? null,               // email
            $input['gender'] ?? 'Male',            // gender
            $input['phone'],                       // phone_number
            $input['id_number'] ?? $input['kra_pin'] ?? '', // id_number
            $input['department_id'] ?: null,       // department_id
            $input['position'] ?? null,            // position
            $input['employment_type'] ?? 'permanent', // employment_type
            $input['basic_salary'] ?? 0,           // basic_salary
            $input['bank_name'] ?? null,           // bank_name
            $input['bank_account'] ?? null,        // bank_account
            $input['bank_name'] ?? '',             // bank (for backward compatibility)
            $input['bank_account'] ?? '',          // account_number
            $input['kra_pin'] ?? null,             // kra_pin
            $input['nssf_no'] ?? null,             // nssf_no
            $input['nhif_no'] ?? null,             // nhif_no
            $input['hire_date'] ?? null,           // hire_date
            $input['status'] ?? 'active',          // status
            $input['id'],                          // WHERE id
            $school_id                             // AND school_id
        ]);
        
        if ($result) {
            sendResponse(null, 'success', 'Staff updated successfully');
        } else {
            sendResponse(null, 'error', 'Failed to update staff');
        }
    } else {
        // Check if staff_number already exists
        $checkStmt = $db->prepare("SELECT id FROM staff WHERE staff_number = ? AND school_id = ?");
        $checkStmt->execute([$input['staff_id'], $school_id]);
        if ($checkStmt->fetch()) {
            sendResponse(null, 'error', 'Staff ID already exists');
            exit;
        }
        
        // INSERT new staff - includes ALL columns
        $stmt = $db->prepare("
            INSERT INTO staff (
                school_id, staff_number, title, first_name, middle_name, last_name,
                email, gender, phone_number, id_number, department_id, position,
                employment_type, basic_salary, bank_name, bank_account, bank,
                account_number, kra_pin, nssf_no, nhif_no, hire_date, status,
                created_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?,
                NOW()
            )
        ");
        
        $result = $stmt->execute([
            $school_id,                            // school_id
            $input['staff_id'],                    // staff_number
            $input['title'] ?? null,               // title
            $firstName,                            // first_name
            $input['middle_name'] ?? null,         // middle_name
            $lastName,                             // last_name
            $input['email'] ?? null,               // email
            $input['gender'] ?? 'Male',            // gender
            $input['phone'],                       // phone_number
            $input['id_number'] ?? $input['kra_pin'] ?? '', // id_number
            $input['department_id'] ?: null,       // department_id
            $input['position'] ?? null,            // position
            $input['employment_type'] ?? 'permanent', // employment_type
            $input['basic_salary'] ?? 0,           // basic_salary
            $input['bank_name'] ?? null,           // bank_name
            $input['bank_account'] ?? null,        // bank_account
            $input['bank_name'] ?? '',             // bank (for backward compatibility)
            $input['bank_account'] ?? '',          // account_number
            $input['kra_pin'] ?? null,             // kra_pin
            $input['nssf_no'] ?? null,             // nssf_no
            $input['nhif_no'] ?? null,             // nhif_no
            $input['hire_date'] ?? null,           // hire_date
            $input['status'] ?? 'active'           // status
        ]);
        
        if ($result) {
            $newId = $db->lastInsertId();
            sendResponse(['id' => $newId], 'success', 'Staff added successfully');
        } else {
            sendResponse(null, 'error', 'Failed to add staff');
        }
    }
} catch (PDOException $e) {
    error_log("Error in save_staff.php: " . $e->getMessage());
    sendResponse(null, 'error', 'Database error: ' . $e->getMessage());
}
?>