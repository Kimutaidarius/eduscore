<?php
/**
 * API: Get Other Income Summary
 * Endpoint: /feesystem/api/feesystem/get_other_income_summary.php
 * Method: GET
 * 
 * Query Parameters:
 * - year: Year to summarize (default: current year)
 * - month: Month to summarize (optional)
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

require_once('../../includes/config.php');

$school_id = $_SESSION['school_id'];
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$month = isset($_GET['month']) ? intval($_GET['month']) : null;

$where_conditions = ["school_id = $school_id", "status = 'active'", "YEAR(payment_date) = $year"];

if ($month) {
    $where_conditions[] = "MONTH(payment_date) = $month";
}

$where_clause = implode(" AND ", $where_conditions);

// Get total summary
$summary_sql = "SELECT 
                    COUNT(*) as total_receipts,
                    SUM(subtotal) as total_subtotal,
                    SUM(tax_amount) as total_tax,
                    SUM(total_amount) as total_amount,
                    COUNT(DISTINCT payer_type) as payer_types_count,
                    COUNT(DISTINCT DATE(payment_date)) as days_with_receipts
                FROM other_income_receipts 
                WHERE $where_clause";

$summary_result = mysqli_query($conn, $summary_sql);
$summary = mysqli_fetch_assoc($summary_result);

// Get breakdown by payment mode
$mode_sql = "SELECT 
                payment_mode,
                COUNT(*) as count,
                SUM(total_amount) as total
             FROM other_income_receipts 
             WHERE $where_clause
             GROUP BY payment_mode
             ORDER BY total DESC";
$mode_result = mysqli_query($conn, $mode_sql);

$payment_modes = [];
while ($row = mysqli_fetch_assoc($mode_result)) {
    $payment_modes[] = [
        'mode' => $row['payment_mode'],
        'count' => intval($row['count']),
        'total' => floatval($row['total'])
    ];
}

// Get breakdown by payer type
$payer_sql = "SELECT 
                payer_type,
                COUNT(*) as count,
                SUM(total_amount) as total
             FROM other_income_receipts 
             WHERE $where_clause
             GROUP BY payer_type
             ORDER BY total DESC";
$payer_result = mysqli_query($conn, $payer_sql);

$payer_types = [];
while ($row = mysqli_fetch_assoc($payer_result)) {
    $payer_types[] = [
        'type' => $row['payer_type'],
        'count' => intval($row['count']),
        'total' => floatval($row['total'])
    ];
}

// Get monthly breakdown (if not filtered by month)
$monthly_sql = "SELECT 
                    DATE_FORMAT(payment_date, '%Y-%m') as month,
                    COUNT(*) as count,
                    SUM(total_amount) as total
                FROM other_income_receipts 
                WHERE school_id = $school_id AND status = 'active' AND YEAR(payment_date) = $year
                GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
                ORDER BY month ASC";
$monthly_result = mysqli_query($conn, $monthly_sql);

$monthly_breakdown = [];
while ($row = mysqli_fetch_assoc($monthly_result)) {
    $monthly_breakdown[] = [
        'month' => $row['month'],
        'count' => intval($row['count']),
        'total' => floatval($row['total'])
    ];
}

// Get vote head breakdown
$vote_head_sql = "SELECT 
                    vh.name as vote_head_name,
                    SUM(ri.amount) as total_amount,
                    COUNT(DISTINCT ri.receipt_id) as receipt_count
                 FROM other_income_receipt_items ri
                 JOIN other_income_receipts r ON ri.receipt_id = r.id
                 JOIN vote_heads vh ON ri.vote_head_id = vh.id
                 WHERE r.school_id = $school_id AND r.status = 'active' AND YEAR(r.payment_date) = $year
                 GROUP BY ri.vote_head_id
                 ORDER BY total_amount DESC";
$vote_head_result = mysqli_query($conn, $vote_head_sql);

$vote_head_breakdown = [];
while ($row = mysqli_fetch_assoc($vote_head_result)) {
    $vote_head_breakdown[] = [
        'vote_head' => $row['vote_head_name'],
        'total' => floatval($row['total_amount']),
        'receipt_count' => intval($row['receipt_count'])
    ];
}

echo json_encode([
    'success' => true,
    'data' => [
        'summary' => [
            'total_receipts' => intval($summary['total_receipts']),
            'total_subtotal' => floatval($summary['total_subtotal']),
            'total_tax' => floatval($summary['total_tax']),
            'total_amount' => floatval($summary['total_amount']),
            'payer_types_count' => intval($summary['payer_types_count']),
            'days_with_receipts' => intval($summary['days_with_receipts'])
        ],
        'payment_modes' => $payment_modes,
        'payer_types' => $payer_types,
        'monthly_breakdown' => $monthly_breakdown,
        'vote_head_breakdown' => $vote_head_breakdown,
        'filters' => [
            'year' => $year,
            'month' => $month
        ]
    ]
]);

mysqli_close($conn);
?>