<?php
// ajax/fetch_students.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/config.php';

// Check authentication
if (
    empty($_SESSION['authenticated']) ||
    empty($_SESSION['school_id']) ||
    empty($_SESSION['teacher_id'])
) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized session'
    ]);
    exit;
}

$school_id = (int) $_SESSION['school_id'];

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit;
}

header('Content-Type: application/json');

try {
    // Check if action is get_students
    if (!isset($_POST['action']) || $_POST['action'] !== 'get_students') {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid action'
        ]);
        exit;
    }
    
    $class_id  = !empty($_POST['class_id'])  ? (int) $_POST['class_id']  : null;
    $stream_id = !empty($_POST['stream_id']) ? (int) $_POST['stream_id'] : null;
    $page      = max(1, (int) ($_POST['page'] ?? 1));
    $search    = trim($_POST['search'] ?? '');
    
    $limit  = 10;
    $offset = ($page - 1) * $limit;
    
    // Note: Using correct column names from tblstudents table:
    // FirstName, SecondName, LastName, AdmNo (not admission_no), StreamId (not stream_id)
    $sql = "
        SELECT SQL_CALC_FOUND_ROWS
            s.id,
            s.FirstName,
            s.SecondName,
            s.LastName,
            s.AdmNo,
            s.ProfilePic,
            s.Gender,
            s.GuardianName,
            s.GuardianPhone,
            s.StreamId,
            s.Status,
            c.class_level,
            c.academic_level,
            st.stream_name
        FROM tblstudents s
        LEFT JOIN tblclasses c ON c.id = s.class_id
        LEFT JOIN tblstreams st ON st.id = s.StreamId
        WHERE s.school_id = :school_id
          AND s.Status = 'Active'
    ";
    
    $params = [':school_id' => $school_id];
    
    if ($class_id) {
        $sql .= " AND s.class_id = :class_id";
        $params[':class_id'] = $class_id;
    }
    
    if ($stream_id) {
        $sql .= " AND s.StreamId = :stream_id";
        $params[':stream_id'] = $stream_id;
    }
    
    if ($search !== '') {
        $sql .= " AND (
            CONCAT(s.FirstName, ' ', s.SecondName, ' ', s.LastName) LIKE :search OR
            s.AdmNo LIKE :search OR
            s.FirstName LIKE :search OR
            s.LastName LIKE :search
        )";
        $params[':search'] = "%{$search}%";
    }
    
    $sql .= "
        ORDER BY c.class_level, st.stream_name, s.FirstName, s.LastName
        LIMIT :limit OFFSET :offset
    ";
    
    $stmt = $db->prepare($sql);
    
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    
    $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total = $db->query("SELECT FOUND_ROWS()")->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'students' => $students,
        'current_page' => $page,
        'total_pages' => ceil($total / $limit),
        'total_students' => (int)$total
    ]);
    
} catch (PDOException $e) {
    error_log("Fetch students error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("General error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>