<?php
/**
 * API: Get All Other Income Receipts
 * Endpoint: /feesystem/api/feesystem/get_other_income_receipts.php
 * Method: GET
 * Query Parameters:
 *   - limit: Number of records (default: 50)
 *   - offset: Pagination offset (default: 0)
 *   - start_date: Filter by start date (optional)
 *   - end_date: Filter by end date (optional)
 */

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verify authentication
if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'finance') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied. Finance role required.']);
    exit;
}

require_once('../../includes/config.php');

// Get database connection
$database = Database::getInstance();
$pdo = $database->getConnection();

$school_id = $_SESSION['school_id'];
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;
$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : null;
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : null;

try {
    // Build WHERE clause
    $where_conditions = ["r.school_id = :school_id", "r.status = 'active'"];
    $params = [':school_id' => $school_id];
    
    if ($start_date) {
        $where_conditions[] = "r.payment_date >= :start_date";
        $params[':start_date'] = $start_date;
    }
    if ($end_date) {
        $where_conditions[] = "r.payment_date <= :end_date";
        $params[':end_date'] = $end_date;
    }
    
    $where_clause = implode(" AND ", $where_conditions);
    
    // Get total count
    $count_sql = "SELECT COUNT(*) as total FROM other_income_receipts r WHERE $where_clause";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get receipts with items summary
    $sql = "SELECT 
                r.id, 
                r.receipt_number, 
                r.payer_type, 
                r.payer_name, 
                r.payment_date, 
                r.payment_mode, 
                r.payment_code,
                r.subtotal, 
                r.tax_amount, 
                r.total_amount, 
                r.notes,
                r.created_at,
                u.username as issued_by,
                COUNT(DISTINCT ri.id) as item_count,
                GROUP_CONCAT(DISTINCT ri.description SEPARATOR ' | ') as descriptions
            FROM other_income_receipts r
            LEFT JOIN other_income_receipt_items ri ON r.id = ri.receipt_id
            LEFT JOIN users u ON r.created_by = u.id
            WHERE $where_clause
            GROUP BY r.id
            ORDER BY r.payment_date DESC, r.created_at DESC
            LIMIT :limit OFFSET :offset";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    foreach ($params as $key => $value) {
        if ($key !== ':limit' && $key !== ':offset') {
            $stmt->bindValue($key, $value);
        }
    }
    $stmt->execute();
    
    $receipts = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Get items for this receipt
        $items_sql = "SELECT id, vote_head_id, description, amount FROM other_income_receipt_items WHERE receipt_id = :receipt_id";
        $items_stmt = $pdo->prepare($items_sql);
        $items_stmt->execute([':receipt_id' => $row['id']]);
        $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $receipts[] = [
            'id' => intval($row['id']),
            'receipt_number' => $row['receipt_number'],
            'payer_type' => $row['payer_type'],
            'payer_name' => $row['payer_name'],
            'payment_date' => $row['payment_date'],
            'payment_mode' => $row['payment_mode'],
            'payment_code' => $row['payment_code'],
            'subtotal' => floatval($row['subtotal']),
            'tax_amount' => floatval($row['tax_amount']),
            'total_amount' => floatval($row['total_amount']),
            'notes' => $row['notes'],
            'item_count' => intval($row['item_count']),
            'descriptions' => $row['descriptions'],
            'issued_by' => $row['issued_by'] ?? 'System',
            'items' => $items,
            'created_at' => $row['created_at']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $receipts,
        'pagination' => [
            'total' => intval($total),
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => ($offset + $limit) < $total
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>