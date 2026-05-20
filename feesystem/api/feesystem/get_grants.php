<?php
// /feesystem/api/feesystem/get_grants.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once('../../includes/config.php');

$database = Database::getInstance();
$db = $database->getConnection();

$school_id = $_SESSION['school_id'];

$filters = json_decode(file_get_contents('php://input'), true);
$year = $filters['year'] ?? date('Y');
$term = $filters['term'] ?? 1;
$source = $filters['source'] ?? '';
$status = $filters['status'] ?? '';
$search = $filters['search'] ?? '';

try {
    // Select all grant fields including receipt_date
    $query = "SELECT g.*
              FROM grants g
              WHERE g.school_id = :school_id";
    
    $params = [':school_id' => $school_id];
    
    if (!empty($source)) {
        $query .= " AND g.source = :source";
        $params[':source'] = $source;
    }
    
    if (!empty($status)) {
        $query .= " AND g.status = :status";
        $params[':status'] = $status;
    }
    
    if (!empty($search)) {
        $query .= " AND (g.name LIKE :search OR g.grant_number LIKE :search OR g.source LIKE :search)";
        $params[':search'] = "%$search%";
    }
    
    $query .= " ORDER BY g.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $grants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get allocations and distributions for each grant
    foreach ($grants as &$grant) {
        // If grant_number is empty, generate one
        if (empty($grant['grant_number'])) {
            $grant['grant_number'] = generateGrantNumber($grant['id'], $grant['created_at']);
        }
        
        // Get distributions (vote head allocations)
        $distQuery = "SELECT gd.*, vh.name as vote_head_name, vh.alias as vote_head_alias
                      FROM grant_distributions gd
                      JOIN vote_heads vh ON gd.vote_head_id = vh.id
                      WHERE gd.grant_id = :grant_id";
        
        $distStmt = $db->prepare($distQuery);
        $distStmt->execute([':grant_id' => $grant['id']]);
        $grant['distributions'] = $distStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get student allocations
        $allocQuery = "SELECT ga.*, 
                              CONCAT(COALESCE(s.FirstName, ''), ' ', COALESCE(s.SecondName, ''), ' ', COALESCE(s.LastName, '')) as student_name,
                              s.AdmNo as admission_no,
                              c.class_level as class_name,
                              st.stream_name as stream_name
                       FROM grant_allocations ga
                       JOIN tblstudents s ON ga.student_id = s.id
                       LEFT JOIN tblclasses c ON s.class_id = c.id
                       LEFT JOIN tblstreams st ON s.StreamId = st.id
                       WHERE ga.grant_id = :grant_id
                       ORDER BY ga.allocated_at DESC";
        
        $allocStmt = $db->prepare($allocQuery);
        $allocStmt->execute([':grant_id' => $grant['id']]);
        $grant['allocations'] = $allocStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode(['success' => true, 'grants' => $grants]);
    
} catch (PDOException $e) {
    error_log("Get grants error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

function generateGrantNumber($grantId, $createdAt) {
    $date = new DateTime($createdAt);
    return 'GRT-' . $date->format('Ymd') . '-' . str_pad($grantId, 5, '0', STR_PAD_LEFT);
}
?>