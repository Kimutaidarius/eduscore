<?php
session_start();
require_once('../../includes/config.php');
header('Content-Type: application/json');

// Check authentication
if (empty($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

/**
 * Mask phone number - shows first 3 and last 2 digits
 * @param string $phone Phone number to mask
 * @return string Masked phone number
 */
function maskPhoneNumber($phone) {
    if (empty($phone) || strlen($phone) < 7) {
        return $phone;
    }
    $length = strlen($phone);
    $visibleStart = 3;
    $visibleEnd = 2;
    $maskedLength = $length - $visibleStart - $visibleEnd;
    return substr($phone, 0, $visibleStart) . str_repeat('*', $maskedLength) . substr($phone, -$visibleEnd);
}

/**
 * Mask account number - shows first 4 and last 4 digits
 * @param string $account Account number to mask
 * @return string Masked account number
 */
function maskAccountNumber($account) {
    if (empty($account) || strlen($account) < 8) {
        // For short account numbers, just show first 2 and last 2
        if (strlen($account) >= 4) {
            return substr($account, 0, 2) . str_repeat('*', strlen($account) - 4) . substr($account, -2);
        }
        return $account;
    }
    $visibleStart = 4;
    $visibleEnd = 4;
    $maskedLength = strlen($account) - $visibleStart - $visibleEnd;
    return substr($account, 0, $visibleStart) . str_repeat('*', $maskedLength) . substr($account, -$visibleEnd);
}

/**
 * Mask ID number - shows first 2 and last 2 digits
 * @param string $idNumber ID number to mask
 * @return string Masked ID number
 */
function maskIdNumber($idNumber) {
    if (empty($idNumber) || strlen($idNumber) < 5) {
        return $idNumber;
    }
    $visibleStart = 2;
    $visibleEnd = 2;
    $maskedLength = strlen($idNumber) - $visibleStart - $visibleEnd;
    return substr($idNumber, 0, $visibleStart) . str_repeat('*', $maskedLength) . substr($idNumber, -$visibleEnd);
}

/**
 * Check if user has permission to view unmasked data
 * @param array $session Session data
 * @return bool True if user can see unmasked data
 */
function canViewUnmaskedData($session) {
    // Super admin or specific roles can view unmasked data
    $allowedRoles = ['super_admin', 'admin', 'finance_admin', 'hr_admin'];
    $userRole = $session['role'] ?? 'teacher';
    
    // Check if user has permission to view sensitive data
    if (isset($session['can_view_sensitive_data']) && $session['can_view_sensitive_data'] === true) {
        return true;
    }
    
    // Check if user role is in allowed list
    if (in_array(strtolower($userRole), $allowedRoles)) {
        return true;
    }
    
    return false;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $school_id = $input['school_id'] ?? $_SESSION['school_id'] ?? null;
    $id = $input['id'] ?? null;
    $search = $input['search'] ?? '';
    $department_id = $input['department_id'] ?? '';
    $show_unmasked = $input['show_unmasked'] ?? false;
    
    if (!$school_id) {
        echo json_encode(['success' => false, 'message' => 'School ID is required']);
        exit;
    }
    
    // Determine if user can view unmasked data
    $canViewUnmasked = canViewUnmaskedData($_SESSION) || $show_unmasked;
    
    // If ID is provided, get single staff member
    if ($id) {
        $stmt = $db->prepare("
            SELECT s.*, d.name as department_name 
            FROM staff s
            LEFT JOIN departments d ON s.department_id = d.id
            WHERE s.id = ? AND s.school_id = ?
        ");
        $stmt->execute([$id, $school_id]);
        $staff = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($staff) {
            // For single record, always return unmasked if user has permission
            if (!$canViewUnmasked) {
                $staff['phone_number_masked'] = maskPhoneNumber($staff['phone_number']);
                $staff['account_number_masked'] = maskAccountNumber($staff['account_number']);
                $staff['id_number_masked'] = maskIdNumber($staff['id_number']);
                $staff['phone_number'] = $staff['phone_number_masked'];
                $staff['account_number'] = $staff['account_number_masked'];
                $staff['id_number'] = $staff['id_number_masked'];
                $staff['is_masked'] = true;
            } else {
                $staff['is_masked'] = false;
            }
            echo json_encode(['success' => true, 'staff' => $staff]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Staff member not found']);
        }
        exit;
    }
    
    // Build query for listing staff
    $sql = "SELECT s.*, d.name as department_name 
            FROM staff s
            LEFT JOIN departments d ON s.department_id = d.id
            WHERE s.school_id = ?";
    $params = [$school_id];
    
    if (!empty($search)) {
        $sql .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.staff_number LIKE ? OR s.id_number LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    if (!empty($department_id)) {
        $sql .= " AND s.department_id = ?";
        $params[] = $department_id;
    }
    
    $sql .= " ORDER BY s.created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format full name and apply masking if needed
    foreach ($staff as &$member) {
        $member['full_name'] = trim($member['title'] . ' ' . $member['first_name'] . ' ' . $member['middle_name'] . ' ' . $member['last_name']);
        $member['full_name'] = preg_replace('/\s+/', ' ', $member['full_name']);
        
        // Store original values for reference
        $member['phone_number_original'] = $member['phone_number'];
        $member['account_number_original'] = $member['account_number'];
        $member['id_number_original'] = $member['id_number'];
        
        // Apply masking if user doesn't have permission
        if (!$canViewUnmasked) {
            $member['phone_number'] = maskPhoneNumber($member['phone_number']);
            $member['account_number'] = maskAccountNumber($member['account_number']);
            $member['id_number'] = maskIdNumber($member['id_number']);
            $member['is_masked'] = true;
        } else {
            $member['is_masked'] = false;
        }
        
        // Add masked versions for toggle functionality
        $member['phone_number_masked'] = maskPhoneNumber($member['phone_number_original']);
        $member['account_number_masked'] = maskAccountNumber($member['account_number_original']);
        $member['id_number_masked'] = maskIdNumber($member['id_number_original']);
    }
    
    echo json_encode([
        'success' => true, 
        'staff' => $staff,
        'is_masked' => !$canViewUnmasked,
        'can_view_unmasked' => $canViewUnmasked,
        'masking_info' => [
            'phone_pattern' => 'Shows first 3 and last 2 digits',
            'account_pattern' => 'Shows first 4 and last 4 digits',
            'id_pattern' => 'Shows first 2 and last 2 digits'
        ]
    ]);
} catch (PDOException $e) {
    error_log("Database error in get_staff.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("General error in get_staff.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>