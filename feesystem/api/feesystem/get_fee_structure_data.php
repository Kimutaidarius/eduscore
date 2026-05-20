<?php
// /feesystem/api/feesystem/get_fee_structure_data.php
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
$class_id = $data['class_id'] ?? '';
$year = $data['year'] ?? date('Y');
$term = $data['term'] ?? 1;

if (empty($class_id)) {
    echo json_encode(['success' => false, 'message' => 'Class ID is required']);
    exit;
}

// Get school info
$school_query = "SELECT school_name, school_address, school_phone, school_email, school_logo FROM tblschoolinfo WHERE id = :school_id";
$school_stmt = $db->prepare($school_query);
$school_stmt->execute([':school_id' => $school_id]);
$school_info = $school_stmt->fetch(PDO::FETCH_ASSOC);

// Get fee structure
$fee_query = "SELECT 
                fs.id,
                fs.class_level,
                fs.academic_year,
                fs.term,
                fs.vote_head_id,
                fs.amount,
                fs.term1,
                fs.term2,
                fs.term3,
                fs.is_optional,
                vh.name as vote_head_name,
                vh.alias as vote_head_alias
              FROM fee_structures fs
              LEFT JOIN vote_heads vh ON fs.vote_head_id = vh.id
              WHERE fs.school_id = :school_id 
                AND fs.class_level = :class_level
                AND fs.academic_year = :academic_year
                AND fs.status = 'active'
              ORDER BY vh.priority ASC";

$fee_stmt = $db->prepare($fee_query);
$fee_stmt->execute([
    ':school_id' => $school_id,
    ':class_level' => $class_id,
    ':academic_year' => $year
]);
$fee_items = $fee_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals per term
$term_totals = [1 => 0, 2 => 0, 3 => 0];
foreach ($fee_items as &$item) {
    $term_totals[1] += $item['term1'] > 0 ? $item['term1'] : $item['amount'];
    $term_totals[2] += $item['term2'] > 0 ? $item['term2'] : $item['amount'];
    $term_totals[3] += $item['term3'] > 0 ? $item['term3'] : $item['amount'];
}

echo json_encode([
    'success' => true,
    'fee_items' => $fee_items,
    'term_totals' => $term_totals,
    'school_name' => $school_info['school_name'] ?? '',
    'school_address' => $school_info['school_address'] ?? '',
    'school_phone' => $school_info['school_phone'] ?? '',
    'school_email' => $school_info['school_email'] ?? '',
    'class_name' => $class_id,
    'year' => $year,
    'term' => $term
]);
?>