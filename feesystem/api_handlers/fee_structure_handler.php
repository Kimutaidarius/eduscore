<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Start session with proper settings
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once('../../includes/config.php');

// Check if user is logged in
if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    if (!isset($_SESSION['teacher_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized - Please log in again']);
        exit;
    }
}

$school_id = $_SESSION['school_id'] ?? $_SESSION['schoolId'] ?? null;

if (!$school_id) {
    echo json_encode(['success' => false, 'message' => 'School ID not found in session']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch($action) {
        case 'get_fee_structures':
            getFeeStructures($db, $school_id);
            break;
        case 'get_fee_structure':
            getFeeStructure($db, $school_id);
            break;
        case 'add_fee_structure':
            addFeeStructure($db, $school_id);
            break;
        case 'edit_fee_structure':
            editFeeStructure($db, $school_id);
            break;
        case 'delete_fee_structure':
            deleteFeeStructure($db, $school_id);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
    }
} catch(Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function getFeeStructures($db, $school_id) {
    $academic_year = $_GET['academic_year'] ?? '';
    $term = $_GET['term'] ?? '';
    
    try {
        $sql = "
            SELECT fs.*, 
                   c.class_level,
                   s.stream_name,
                   vh.name as vote_head_name,
                   vh.alias as vote_head_alias
            FROM fee_structures fs
            JOIN tblclasses c ON fs.class_level = c.id
            LEFT JOIN tblstreams s ON fs.stream_id = s.id
            JOIN vote_heads vh ON fs.vote_head_id = vh.id
            WHERE c.school_id = ?
        ";
        $params = [$school_id];
        
        if (!empty($academic_year)) {
            $sql .= " AND fs.academic_year = ?";
            $params[] = $academic_year;
        }
        if (!empty($term)) {
            $sql .= " AND fs.term = ?";
            $params[] = $term;
        }
        
        $sql .= " ORDER BY c.class_level, COALESCE(s.stream_name, ''), vh.priority";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $feeStructures = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $feeStructures]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function getFeeStructure($db, $school_id) {
    $id = $_GET['id'] ?? 0;
    $stmt = $db->prepare("
        SELECT fs.*, c.class_level
        FROM fee_structures fs
        JOIN tblclasses c ON fs.class_level = c.id
        WHERE fs.id = ? AND c.school_id = ?
    ");
    $stmt->execute([$id, $school_id]);
    $feeStructure = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($feeStructure) {
        echo json_encode(['success' => true, 'data' => $feeStructure]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Fee structure not found']);
    }
}

function addFeeStructure($db, $school_id) {
    $academic_year = $_POST['academic_year'] ?? '';
    $term = $_POST['term'] ?? '';
    $class_level = $_POST['class_level'] ?? '';
    $stream_id = !empty($_POST['stream_id']) ? $_POST['stream_id'] : null;
    $vote_head_id = $_POST['vote_head_id'] ?? '';
    $amount = $_POST['amount'] ?? 0;
    $is_optional = isset($_POST['is_optional']) ? 1 : 0;
    $status = $_POST['status'] ?? 'active';
    
    if (empty($academic_year) || empty($term) || empty($class_level) || empty($vote_head_id) || $amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
        return;
    }
    
    // Verify class belongs to school
    $stmt = $db->prepare("SELECT id FROM tblclasses WHERE id = ? AND school_id = ?");
    $stmt->execute([$class_level, $school_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Invalid class']);
        return;
    }
    
    $stmt = $db->prepare("
        INSERT INTO fee_structures (school_id, academic_year, term, class_level, stream_id, vote_head_id, amount, is_optional, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$school_id, $academic_year, $term, $class_level, $stream_id, $vote_head_id, $amount, $is_optional, $status]);
    
    echo json_encode(['success' => true, 'message' => 'Fee structure added successfully', 'id' => $db->lastInsertId()]);
}

function editFeeStructure($db, $school_id) {
    $id = $_POST['fee_structure_id'] ?? 0;
    $academic_year = $_POST['academic_year'] ?? '';
    $term = $_POST['term'] ?? '';
    $class_level = $_POST['class_level'] ?? '';
    $stream_id = !empty($_POST['stream_id']) ? $_POST['stream_id'] : null;
    $vote_head_id = $_POST['vote_head_id'] ?? '';
    $amount = $_POST['amount'] ?? 0;
    $is_optional = isset($_POST['is_optional']) ? 1 : 0;
    $status = $_POST['status'] ?? 'active';
    
    if (empty($id) || empty($academic_year) || empty($term) || empty($class_level) || empty($vote_head_id) || $amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
        return;
    }
    
    $stmt = $db->prepare("
        UPDATE fee_structures 
        SET academic_year = ?, term = ?, class_level = ?, stream_id = ?, vote_head_id = ?, amount = ?, is_optional = ?, status = ?
        WHERE id = ? AND school_id = ?
    ");
    $stmt->execute([$academic_year, $term, $class_level, $stream_id, $vote_head_id, $amount, $is_optional, $status, $id, $school_id]);
    
    echo json_encode(['success' => true, 'message' => 'Fee structure updated successfully']);
}

function deleteFeeStructure($db, $school_id) {
    $id = $_POST['id'] ?? 0;
    
    // Check if fee structure has student fees
    $stmt = $db->prepare("SELECT COUNT(*) FROM student_fees WHERE fee_structure_id = ?");
    $stmt->execute([$id]);
    $feeCount = $stmt->fetchColumn();
    
    if ($feeCount > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete fee structure with ' . $feeCount . ' associated student fees']);
        return;
    }
    
    $stmt = $db->prepare("DELETE FROM fee_structures WHERE id = ? AND school_id = ?");
    $stmt->execute([$id, $school_id]);
    
    echo json_encode(['success' => true, 'message' => 'Fee structure deleted successfully']);
}
?>