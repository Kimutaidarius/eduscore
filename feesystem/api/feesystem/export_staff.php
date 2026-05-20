<?php
session_start();
require_once('../../includes/config.php');

// Check authentication
if (empty($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('Location: ../../login.php');
    exit;
}

$school_id = $_GET['school_id'] ?? $_SESSION['school_id'] ?? null;

if (!$school_id) {
    die('School ID is required');
}

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="staff_export_' . date('Y-m-d') . '.xls"');

try {
    $stmt = $db->prepare("
        SELECT s.staff_number, s.title, s.first_name, s.middle_name, s.last_name, 
               s.gender, s.id_number, s.phone_number, s.bank, s.account_number, 
               d.name as department_name, s.created_at
        FROM staff s
        LEFT JOIN departments d ON s.department_id = d.id
        WHERE s.school_id = ?
        ORDER BY s.created_at DESC
    ");
    $stmt->execute([$school_id]);
    $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Output headers
    echo "Staff Number\tTitle\tFirst Name\tMiddle Name\tLast Name\tGender\tID Number\tPhone Number\tBank\tAccount Number\tDepartment\tDate Added\n";
    
    // Output data
    foreach ($staff as $member) {
        echo implode("\t", [
            $member['staff_number'],
            $member['title'],
            $member['first_name'],
            $member['middle_name'] ?? '',
            $member['last_name'],
            $member['gender'],
            $member['id_number'],
            $member['phone_number'],
            $member['bank'],
            $member['account_number'],
            $member['department_name'] ?? '',
            date('Y-m-d', strtotime($member['created_at']))
        ]) . "\n";
    }
} catch (PDOException $e) {
    error_log("Database error in export_staff.php: " . $e->getMessage());
    echo "Error exporting data";
}
?>