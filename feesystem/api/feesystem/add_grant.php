<?php
// /feesystem/api/feesystem/add_grant.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once('../../includes/config.php');

$database = Database::getInstance();
$db = $database->getConnection();

$data = json_decode(file_get_contents('php://input'), true);

$school_id = $_SESSION['school_id'];
$user_id = $_SESSION['user_id'] ?? 1;

try {
    $db->beginTransaction();
    
    // Generate unique grant number
    $grantNumber = generateUniqueGrantNumber($db, $school_id);
    
    // Insert grant with all fields including receipt_date
    // Fixed: Use separate parameter names for total_amount and remaining_balance
    $query = "INSERT INTO grants (grant_number, school_id, name, source, receipt_date, payment_mode, reference_no, total_amount, allocated_amount, remaining_balance, notes, status, created_by, created_at) 
              VALUES (:grant_number, :school_id, :name, :source, :receipt_date, :payment_mode, :reference_no, :total_amount, 0, :remaining_balance, :notes, 'active', :created_by, NOW())";
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        ':grant_number' => $grantNumber,
        ':school_id' => $school_id,
        ':name' => $data['name'],
        ':source' => $data['source'],
        ':receipt_date' => $data['receipt_date'],
        ':payment_mode' => $data['payment_mode'],
        ':reference_no' => $data['reference_no'] ?? null,
        ':total_amount' => $data['total_amount'],
        ':remaining_balance' => $data['total_amount'],  // Separate parameter for remaining_balance
        ':notes' => $data['notes'] ?? '',
        ':created_by' => $user_id
    ]);
    
    $grantId = $db->lastInsertId();
    
    // Insert distributions into grant_distributions table
    if (!empty($data['distributions'])) {
        $distQuery = "INSERT INTO grant_distributions (grant_id, vote_head_id, amount) VALUES (:grant_id, :vote_head_id, :amount)";
        $distStmt = $db->prepare($distQuery);
        
        foreach ($data['distributions'] as $dist) {
            $distStmt->execute([
                ':grant_id' => $grantId,
                ':vote_head_id' => $dist['vote_head_id'],
                ':amount' => $dist['amount']
            ]);
        }
    }
    
    $db->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Grant created successfully',
        'grant_id' => $grantId,
        'grant_number' => $grantNumber
    ]);
    
} catch (PDOException $e) {
    $db->rollBack();
    error_log("Add grant error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    $db->rollBack();
    error_log("Add grant error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

function generateUniqueGrantNumber($db, $school_id) {
    $prefix = 'GRT';
    $year = date('Y');
    $month = date('m');
    
    try {
        // Get the last grant number for this school and year
        $query = "SELECT grant_number FROM grants 
                  WHERE school_id = :school_id 
                  AND grant_number LIKE :pattern 
                  ORDER BY id DESC LIMIT 1";
        
        $pattern = $prefix . '-' . $year . $month . '-%';
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':school_id' => $school_id,
            ':pattern' => $pattern
        ]);
        
        $lastGrant = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($lastGrant && preg_match('/-(\d+)$/', $lastGrant['grant_number'], $matches)) {
            $nextNum = intval($matches[1]) + 1;
        } else {
            $nextNum = 1;
        }
        
        return $prefix . '-' . $year . $month . '-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
        
    } catch (PDOException $e) {
        error_log("Generate grant number error: " . $e->getMessage());
        // Fallback to timestamp-based number
        return $prefix . '-' . $year . $month . '-' . date('His') . rand(10, 99);
    }
}
?>