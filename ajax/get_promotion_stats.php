<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['school_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$teacher_id = $_SESSION['teacher_id'];
$school_id = $_SESSION['school_id'];

require_once '../includes/config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Get promotion statistics
$stats = [];

// Total promotions
$query = "SELECT COUNT(*) as total FROM tblpromotion_history WHERE school_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $school_id);
$stmt->execute();
$result = $stmt->get_result();
$stats['total_promotions'] = $result->fetch_assoc()['total'];
$stmt->close();

// Promotions this year
$current_year = date('Y');
$query = "SELECT COUNT(*) as total FROM tblpromotion_history WHERE school_id = ? AND academic_year = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $school_id, $current_year);
$stmt->execute();
$result = $stmt->get_result();
$stats['promotions_this_year'] = $result->fetch_assoc()['total'];
$stmt->close();

// Promotions this month
$query = "SELECT COUNT(*) as total FROM tblpromotion_history 
          WHERE school_id = ? 
          AND MONTH(promoted_at) = MONTH(CURRENT_DATE())
          AND YEAR(promoted_at) = YEAR(CURRENT_DATE())";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $school_id);
$stmt->execute();
$result = $stmt->get_result();
$stats['promotions_this_month'] = $result->fetch_assoc()['total'];
$stmt->close();

// Today's promotions
$query = "SELECT COUNT(*) as total FROM tblpromotion_history 
          WHERE school_id = ? 
          AND DATE(promoted_at) = CURRENT_DATE()";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $school_id);
$stmt->execute();
$result = $stmt->get_result();
$stats['promotions_today'] = $result->fetch_assoc()['total'];
$stmt->close();

// Most recent promotions
$query = "SELECT 
            ph.*,
            s.FirstName, s.LastName, s.AdmNo,
            fc.class_level as from_class, fc.stream as from_stream,
            tc.class_level as to_class, tc.stream as to_stream
          FROM tblpromotion_history ph
          LEFT JOIN tblstudents s ON ph.student_id = s.id
          LEFT JOIN tblclasses fc ON ph.from_class_id = fc.id
          LEFT JOIN tblclasses tc ON ph.to_class_id = tc.id
          WHERE ph.school_id = ?
          ORDER BY ph.promoted_at DESC
          LIMIT 5";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $school_id);
$stmt->execute();
$result = $stmt->get_result();

$recent = [];
while ($row = $result->fetch_assoc()) {
    $student_name = trim(($row['FirstName'] ?? '') . ' ' . ($row['LastName'] ?? ''));
    
    $recent[] = [
        'id' => $row['id'],
        'student_name' => $student_name ?: 'Unknown',
        'admission_no' => $row['AdmNo'] ?? 'N/A',
        'from_class' => $row['from_class'] . (!empty($row['from_stream']) ? ' - ' . $row['from_stream'] : ''),
        'to_class' => $row['to_class'] . (!empty($row['to_stream']) ? ' - ' . $row['to_stream'] : ''),
        'academic_year' => $row['academic_year'],
        'promoted_at' => date('d/m/Y H:i', strtotime($row['promoted_at']))
    ];
}
$stmt->close();

$stats['recent_promotions'] = $recent;

// Promotion by class
$query = "SELECT 
            c.class_level, c.stream,
            COUNT(ph.id) as promotion_count
          FROM tblpromotion_history ph
          LEFT JOIN tblclasses c ON ph.to_class_id = c.id
          WHERE ph.school_id = ? AND c.id IS NOT NULL
          GROUP BY ph.to_class_id
          ORDER BY promotion_count DESC
          LIMIT 5";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $school_id);
$stmt->execute();
$result = $stmt->get_result();

$by_class = [];
while ($row = $result->fetch_assoc()) {
    $class_name = $row['class_level'];
    if (!empty($row['stream'])) {
        $class_name .= ' - ' . $row['stream'];
    }
    
    $by_class[] = [
        'class' => $class_name,
        'count' => $row['promotion_count']
    ];
}
$stmt->close();

$stats['promotions_by_class'] = $by_class;

$conn->close();

echo json_encode([
    'success' => true,
    'stats' => $stats
]);