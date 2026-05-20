<?php
require_once '../includes/config.php';
header('Content-Type: application/json');

// ✅ Only start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 🔒 Ensure session and school ID exist
if (!isset($_SESSION['school_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access. Please log in again.',
        'terms' => []
    ]);
    exit;
}

$school_id = $_SESSION['school_id'];
$today = date('Y-m-d');

try {
    // 🧾 Fetch all terms for this school
    $stmt = $dbh->prepare("
        SELECT 
            id,
            term_name,
            term_number,
            academic_year,
            start_date,
            end_date,
            is_current
        FROM tblterms
        WHERE school_id = :school_id
        ORDER BY academic_year DESC, term_number ASC
    ");
    $stmt->execute([':school_id' => $school_id]);
    $terms = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$terms) {
        echo json_encode([
            'success' => false,
            'message' => 'No terms found for this school.',
            'terms' => []
        ]);
        exit;
    }

    // 🔍 Detect preferred term
    $preferredTermId = null;

    // 1️⃣ Prefer date range match (today between start and end)
    foreach ($terms as $term) {
        if ($today >= $term['start_date'] && $today <= $term['end_date']) {
            $preferredTermId = $term['id'];
            break;
        }
    }

    // 2️⃣ Fallback to "is_current" flag
    if (!$preferredTermId) {
        foreach ($terms as $term) {
            if ((int)$term['is_current'] === 1) {
                $preferredTermId = $term['id'];
                break;
            }
        }
    }

    // 3️⃣ Mark preferred term
    foreach ($terms as &$term) {
        $term['preferred'] = ($term['id'] == $preferredTermId);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Terms fetched successfully.',
        'terms' => $terms
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching terms: ' . $e->getMessage(),
        'terms' => []
    ]);
}
