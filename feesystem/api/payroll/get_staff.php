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
    if (isset($input['id']) && !empty($input['id'])) {
        // Get single staff member
        $stmt = $db->prepare("
            SELECT 
                s.*, 
                d.name as department_name,
                CONCAT(s.first_name, ' ', s.last_name) as full_name,
                s.phone_number as phone,
                s.staff_number as staff_id
            FROM staff s
            LEFT JOIN departments d ON s.department_id = d.id
            WHERE s.id = ? AND s.school_id = ?
        ");
        $stmt->execute([$input['id'], $school_id]);
        $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format the data for frontend
        foreach ($staff as &$s) {
            $s['full_name'] = trim($s['first_name'] . ' ' . $s['last_name']);
            $s['staff_id'] = $s['staff_number'];
            $s['phone'] = $s['phone_number'];
        }
        
        sendResponse(['staff' => $staff], 'success');
    } else {
        // Get all staff with filters
        $sql = "SELECT 
                    s.*, 
                    d.name as department_name,
                    CONCAT(s.first_name, ' ', s.last_name) as full_name,
                    s.phone_number as phone,
                    s.staff_number as staff_id
                FROM staff s
                LEFT JOIN departments d ON s.department_id = d.id
                WHERE s.school_id = ?";
        $params = [$school_id];
        
        // Search filter
        if (!empty($input['search'])) {
            $sql .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.staff_number LIKE ? OR s.phone_number LIKE ?)";
            $search = "%{$input['search']}%";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        // Status filter - NOW WORKS because staff table has status column
        if (!empty($input['status'])) {
            $sql .= " AND s.status = ?";
            $params[] = $input['status'];
        }
        
        // Employment type filter
        if (!empty($input['type'])) {
            $sql .= " AND s.employment_type = ?";
            $params[] = $input['type'];
        }
        
        // Department filter
        if (!empty($input['department'])) {
            $sql .= " AND s.department_id = ?";
            $params[] = $input['department'];
        }
        
        $sql .= " ORDER BY s.first_name ASC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format the data for frontend - using actual values from database
        foreach ($staff as &$s) {
            $s['full_name'] = trim($s['first_name'] . ' ' . $s['last_name']);
            $s['staff_id'] = $s['staff_number'];
            $s['phone'] = $s['phone_number'];
            // These fields already exist in the table, so we don't need defaults
            // $s['employment_type'] is already in the SELECT *
            // $s['basic_salary'] is already in the SELECT *
            // $s['status'] is already in the SELECT *
            // $s['email'] is already in the SELECT *
            // $s['position'] is already in the SELECT *
        }
        
        sendResponse(['staff' => $staff], 'success');
    }
} catch (PDOException $e) {
    error_log("Error in get_staff.php: " . $e->getMessage());
    sendResponse(null, 'error', 'Database error: ' . $e->getMessage());
}
?>