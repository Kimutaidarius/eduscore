<?php
/**
 * Validate term and year combination
 */

session_start();
require_once dirname(__DIR__) . '/includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['school_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$school_id = $_SESSION['school_id'];
$term_id = $_POST['term_id'] ?? null;
$year = $_POST['year'] ?? null;

if (!$term_id || !$year) {
    echo json_encode(['success' => false, 'message' => 'Term and year required']);
    exit();
}

$conn = $dbh;

$query = $conn->prepare("
    SELECT id, term_name, term_number, academic_year, start_date, end_date,
           CASE 
               WHEN CURDATE() BETWEEN start_date AND end_date THEN 'active'
               WHEN CURDATE() < start_date THEN 'upcoming'
               ELSE 'closed'
           END as term_status
    FROM tblterms 
    WHERE id = ? AND school_id = ? AND academic_year = ?
");

$query->execute([$term_id, $school_id, $year]);
$term = $query->fetch(PDO::FETCH_ASSOC);

if ($term) {
    echo json_encode([
        'success' => true,
        'term' => $term,
        'valid' => true
    ]);
} else {
    echo json_encode([
        'success' => false,
        'valid' => false,
        'message' => 'Term does not exist for the selected year'
    ]);
}
?>