// api/get_classes.php - FIXED VERSION
session_start();
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

include('../includes/config.php');

// --- 1. Authentication ---
if (empty($_SESSION['id']) || empty($_SESSION['school_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required. Please log in.'
    ]);
    exit();
}

$schoolId = (int) $_SESSION['school_id'];

try {
    // --- Fetch classes ---
    $sql = "SELECT id, class_level 
            FROM tblclasses 
            WHERE school_id = :schoolId 
            ORDER BY class_level ASC";

    $query = $dbh->prepare($sql);
    $query->bindParam(':schoolId', $schoolId, PDO::PARAM_INT);
    $query->execute();
    $classes = $query->fetchAll(PDO::FETCH_ASSOC);

    if ($classes) {
        echo json_encode([
            'success' => true,
            'classes' => $classes
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No classes found for your school.',
            'classes' => []
        ]);
    }

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'PDO Error: ' . $e->getMessage()
    ]);
    exit();
}