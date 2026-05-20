<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['school_id'])) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit();
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$teacher_id = $_SESSION['teacher_id'];
$school_id = $_SESSION['school_id'];

// Get POST data
$year = isset($_POST['year']) ? intval($_POST['year']) : date('Y');

// Database connection
require_once dirname(__DIR__) . '/includes/config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

try {
    // Since tblterms doesn't have class_id, we just fetch by school_id and academic_year
    $sql = "SELECT * FROM tblterms 
            WHERE school_id = ? AND academic_year = ?
            ORDER BY term_number";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $school_id, $year);
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $terms = [];
    while ($row = $result->fetch_assoc()) {
        // Format dates for display
        $row['start_date_formatted'] = date('d M Y', strtotime($row['start_date']));
        $row['end_date_formatted'] = date('d M Y', strtotime($row['end_date']));
        
        // Add status
        $today = date('Y-m-d');
        if ($row['is_current'] == 1) {
            $row['status'] = 'active';
            $row['status_text'] = 'Active';
        } elseif ($today < $row['start_date']) {
            $row['status'] = 'upcoming';
            $row['status_text'] = 'Upcoming';
        } elseif ($today > $row['end_date']) {
            $row['status'] = 'closed';
            $row['status_text'] = 'Closed';
        } else {
            $row['status'] = 'ongoing';
            $row['status_text'] = 'Ongoing';
        }
        
        $terms[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'terms' => $terms,
        'year' => $year
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} finally {
    $conn->close();
}
?>