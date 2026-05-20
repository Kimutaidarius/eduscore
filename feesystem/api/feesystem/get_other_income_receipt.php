<?php
/**
 * API: Get Single Other Income Receipt
 * Endpoint: /feesystem/api/feesystem/get_other_income_receipt.php?id={receipt_id}
 * Method: GET
 */

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Verify authentication
if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Receipt ID is required']);
    exit;
}

require_once('../../includes/config.php');

$receipt_id = intval($_GET['id']);
$school_id = $_SESSION['school_id'];

// Get receipt header
$sql = "SELECT r.*, u.username as created_by_name 
        FROM other_income_receipts r
        LEFT JOIN users u ON r.created_by = u.id
        WHERE r.id = ? AND r.school_id = ? AND r.status = 'active'";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'ii', $receipt_id, $school_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Receipt not found']);
    exit;
}

$receipt = mysqli_fetch_assoc($result);

// Get receipt items
$items_sql = "SELECT ri.*, vh.name as vote_head_name, vh.alias 
              FROM other_income_receipt_items ri
              LEFT JOIN vote_heads vh ON ri.vote_head_id = vh.id
              WHERE ri.receipt_id = ?";
$items_stmt = mysqli_prepare($conn, $items_sql);
mysqli_stmt_bind_param($items_stmt, 'i', $receipt_id);
mysqli_stmt_execute($items_stmt);
$items_result = mysqli_stmt_get_result($items_stmt);

$items = [];
while ($item = mysqli_fetch_assoc($items_result)) {
    $items[] = [
        'id' => $item['id'],
        'vote_head_id' => $item['vote_head_id'],
        'vote_head_name' => $item['vote_head_name'],
        'alias' => $item['alias'],
        'description' => $item['description'],
        'amount' => floatval($item['amount'])
    ];
}

// Get school info
$school_sql = "SELECT school_name, school_address, school_phone, school_email, principal_name 
               FROM tblschoolinfo WHERE id = ?";
$school_stmt = mysqli_prepare($conn, $school_sql);
mysqli_stmt_bind_param($school_stmt, 'i', $school_id);
mysqli_stmt_execute($school_stmt);
$school_result = mysqli_stmt_get_result($school_stmt);
$school_info = mysqli_fetch_assoc($school_result);

echo json_encode([
    'success' => true,
    'data' => [
        'receipt' => [
            'id' => $receipt['id'],
            'receipt_number' => $receipt['receipt_number'],
            'payer_type' => $receipt['payer_type'],
            'payer_id' => $receipt['payer_id'],
            'payer_name' => $receipt['payer_name'],
            'payment_date' => $receipt['payment_date'],
            'payment_mode' => $receipt['payment_mode'],
            'payment_code' => $receipt['payment_code'],
            'subtotal' => floatval($receipt['subtotal']),
            'tax_amount' => floatval($receipt['tax_amount']),
            'total_amount' => floatval($receipt['total_amount']),
            'notes' => $receipt['notes'],
            'created_by' => $receipt['created_by_name'],
            'created_at' => $receipt['created_at']
        ],
        'items' => $items,
        'school_info' => $school_info
    ]
]);

mysqli_stmt_close($stmt);
mysqli_stmt_close($items_stmt);
mysqli_close($conn);
?>