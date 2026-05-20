<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/config.php';
require_once 'includes/PermissionHelper.php';
require_once 'includes/session_timeout.php'; 

// Detect AJAX (Keep for backend logic)
$isAjax = ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']));

// SINGLE SOURCE OF TRUTH
if (
    empty($_SESSION['authenticated']) ||
    empty($_SESSION['school_id']) ||
    empty($_SESSION['teacher_id'])
) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Unauthorized session'
        ]);
        exit;
    }

    header('Location: login.php');
    exit;
}

// Safe session values
$school_id  = (int) $_SESSION['school_id'];
$teacher_id = (int) $_SESSION['teacher_id'];

// Initialize Permission Helper
$permissionHelper = new PermissionHelper($db, $school_id, $teacher_id);

// Check if user has permission to view exams page
$permissionHelper->requireAnyPermission(['examsView', 'examsViewAll'], 'dashboard.php');

// Determine which actions are allowed based on permissions
$canCreate = $permissionHelper->hasPermission('examsCreate');
$canEdit = $permissionHelper->hasPermission('examsEdit');
$canDelete = $permissionHelper->hasPermission('examsDelete');
$canViewAll = $permissionHelper->hasPermission('examsViewAll');
$isSuperAdmin = $permissionHelper->isSuperAdmin();
$currentUserRole = $permissionHelper->getRole();

// DB
if (!isset($db)) {
    try {
        require_once 'config/database.php';
        $database = new Database();
        $db = $database->getConnection();
    } catch (Exception $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}

// Set default session variables
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = $_SESSION['id'] ?? 0;
}

if (!isset($_SESSION['user_name']) && isset($_SESSION['login'])) {
    $_SESSION['user_name'] = $_SESSION['login'];
}

if (!isset($_SESSION['school_name'])) {
    $_SESSION['school_name'] = 'EduScore School';
}

// Initialize variables
$classes = [];
$streams = [];
$terms = [];
$exams = [];
$error_message = '';

// Fetch classes and streams for filters and modal
try {
    // Fetch classes
    $classesQuery = "SELECT id, class_level FROM tblclasses WHERE school_id = :school_id ORDER BY class_level";
    $classesStmt = $db->prepare($classesQuery);
    $classesStmt->bindParam(":school_id", $_SESSION['school_id'], PDO::PARAM_INT);
    $classesStmt->execute();
    $classes = $classesStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch streams
    $streamsQuery = "SELECT s.id, s.stream_name, s.class_id, c.class_level 
                    FROM tblstreams s 
                    LEFT JOIN tblclasses c ON s.class_id = c.id 
                    WHERE s.school_id = :school_id 
                    ORDER BY c.class_level, s.stream_name";
    $streamsStmt = $db->prepare($streamsQuery);
    $streamsStmt->bindParam(":school_id", $_SESSION['school_id'], PDO::PARAM_INT);
    $streamsStmt->execute();
    $streams = $streamsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch terms (academic terms for the school)
    $termsQuery = "SELECT id, term_name, term_number, academic_year, start_date, end_date 
                   FROM tblterms 
                   WHERE school_id = :school_id 
                   ORDER BY academic_year DESC, term_number DESC";
    $termsStmt = $db->prepare($termsQuery);
    $termsStmt->bindParam(":school_id", $_SESSION['school_id'], PDO::PARAM_INT);
    $termsStmt->execute();
    $terms = $termsStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Classes/Streams/Terms fetch error: " . $e->getMessage());
    $error_message = "Error loading data: " . $e->getMessage();
}

// Handle AJAX requests for exam operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'add_exam':
                if (!$canCreate) {
                    echo json_encode(['success' => false, 'message' => 'You do not have permission to create exams']);
                    break;
                }
                $response = addExam($db, $_SESSION['school_id']);
                echo json_encode($response);
                break;
                
            case 'update_exam':
                if (!$canEdit) {
                    echo json_encode(['success' => false, 'message' => 'You do not have permission to edit exams']);
                    break;
                }
                $response = updateExam($db, $_SESSION['school_id']);
                echo json_encode($response);
                break;
                
            case 'delete_exam':
                if (!$canDelete) {
                    echo json_encode(['success' => false, 'message' => 'You do not permission to delete exams']);
                    break;
                }
                $response = deleteExam($db, $_SESSION['school_id']);
                echo json_encode($response);
                break;
                
            case 'get_exam':
                if (!$permissionHelper->hasAnyPermission(['examsView', 'examsViewAll'])) {
                    echo json_encode(['success' => false, 'message' => 'You do not have permission to view exams']);
                    break;
                }
                $exam_id = intval($_POST['exam_id']);
                $query = "SELECT e.*, c.class_level, s.stream_name, t.term_name 
                         FROM tblexam e
                         LEFT JOIN tblclasses c ON e.class_id = c.id
                         LEFT JOIN tblstreams s ON e.stream_id = s.id
                         LEFT JOIN tblterms t ON e.term_id = t.id
                         WHERE e.id = :exam_id AND e.school_id = :school_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(":exam_id", $exam_id);
                $stmt->bindParam(":school_id", $_SESSION['school_id']);
                $stmt->execute();
                $exam = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($exam) {
                    echo json_encode(['success' => true, 'exam' => $exam]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Exam not found']);
                }
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (PDOException $e) {
        error_log("Exam AJAX error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred: ' . $e->getMessage()]);
    }
    exit;
}

// Function to format term name (remove "Term" text if present)
function formatTermName($term) {
    if (is_array($term) && isset($term['term_name'])) {
        $termName = $term['term_name'];
    } elseif (is_string($term)) {
        $termName = $term;
    } else {
        return '';
    }
    
    $termName = preg_replace('/^Term\s+/i', '', $termName);
    return $termName;
}

// Function to add exam
function addExam($db, $school_id) {
    $required_fields = ['examName', 'examDeadline', 'class_id', 'term_id'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            return ['success' => false, 'message' => "Required field '$field' is missing"];
        }
    }
    
    try {
        $stream_id = !empty($_POST['stream_id']) ? $_POST['stream_id'] : null;
        
        if ($stream_id) {
            $verify_stream = "SELECT id FROM tblstreams 
                             WHERE id = :stream_id AND school_id = :school_id 
                             AND (class_id = :class_id OR class_id IS NULL)";
            $stmt_verify = $db->prepare($verify_stream);
            $stmt_verify->bindParam(":stream_id", $stream_id);
            $stmt_verify->bindParam(":school_id", $school_id);
            $stmt_verify->bindParam(":class_id", $_POST['class_id']);
            $stmt_verify->execute();
            
            if ($stmt_verify->rowCount() === 0) {
                return ['success' => false, 'message' => 'Invalid stream selected or stream does not belong to this class'];
            }
        }
        
        $verify_term = "SELECT id FROM tblterms WHERE id = :term_id AND school_id = :school_id";
        $stmt_term = $db->prepare($verify_term);
        $stmt_term->bindParam(":term_id", $_POST['term_id']);
        $stmt_term->bindParam(":school_id", $school_id);
        $stmt_term->execute();
        
        if ($stmt_term->rowCount() === 0) {
            return ['success' => false, 'message' => 'Invalid term selected'];
        }
        
        $query = "INSERT INTO tblexam 
                 (examname, deadline_date, class_id, stream_id, term_id, school_id, status, DateAdded) 
                 VALUES (:examname, :deadline_date, :class_id, :stream_id, :term_id, :school_id, 'Active', CURDATE())";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(":examname", $_POST['examName']);
        $stmt->bindParam(":deadline_date", $_POST['examDeadline']);
        $stmt->bindParam(":class_id", $_POST['class_id']);
        $stmt->bindParam(":term_id", $_POST['term_id']);
        
        if ($stream_id) {
            $stmt->bindParam(":stream_id", $stream_id);
        } else {
            $stmt->bindValue(":stream_id", null, PDO::PARAM_NULL);
        }
        
        $stmt->bindParam(":school_id", $school_id);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Exam created successfully', 'exam_id' => $db->lastInsertId()];
        } else {
            return ['success' => false, 'message' => 'Failed to create exam'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Function to update exam
function updateExam($db, $school_id) {
    $exam_id = intval($_POST['examId']);
    
    $check_query = "SELECT id FROM tblexam WHERE id = :exam_id AND school_id = :school_id";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(":exam_id", $exam_id);
    $check_stmt->bindParam(":school_id", $school_id);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() === 0) {
        return ['success' => false, 'message' => 'Exam not found'];
    }
    
    $required_fields = ['examName', 'examDeadline', 'class_id', 'term_id'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            return ['success' => false, 'message' => "Required field '$field' is missing"];
        }
    }
    
    try {
        $stream_id = !empty($_POST['stream_id']) ? $_POST['stream_id'] : null;
        
        if ($stream_id) {
            $verify_stream = "SELECT id FROM tblstreams 
                             WHERE id = :stream_id AND school_id = :school_id 
                             AND (class_id = :class_id OR class_id IS NULL)";
            $stmt_verify = $db->prepare($verify_stream);
            $stmt_verify->bindParam(":stream_id", $stream_id);
            $stmt_verify->bindParam(":school_id", $school_id);
            $stmt_verify->bindParam(":class_id", $_POST['class_id']);
            $stmt_verify->execute();
            
            if ($stmt_verify->rowCount() === 0) {
                return ['success' => false, 'message' => 'Invalid stream selected or stream does not belong to this class'];
            }
        }
        
        $verify_term = "SELECT id FROM tblterms WHERE id = :term_id AND school_id = :school_id";
        $stmt_term = $db->prepare($verify_term);
        $stmt_term->bindParam(":term_id", $_POST['term_id']);
        $stmt_term->bindParam(":school_id", $school_id);
        $stmt_term->execute();
        
        if ($stmt_term->rowCount() === 0) {
            return ['success' => false, 'message' => 'Invalid term selected'];
        }
        
        $query = "UPDATE tblexam 
                 SET examname = :examname, deadline_date = :deadline_date,
                     class_id = :class_id, stream_id = :stream_id, term_id = :term_id,
                     last_updated = CURRENT_TIMESTAMP
                 WHERE id = :exam_id AND school_id = :school_id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(":examname", $_POST['examName']);
        $stmt->bindParam(":deadline_date", $_POST['examDeadline']);
        $stmt->bindParam(":class_id", $_POST['class_id']);
        $stmt->bindParam(":term_id", $_POST['term_id']);
        
        if ($stream_id) {
            $stmt->bindParam(":stream_id", $stream_id);
        } else {
            $stmt->bindValue(":stream_id", null, PDO::PARAM_NULL);
        }
        
        $stmt->bindParam(":exam_id", $exam_id);
        $stmt->bindParam(":school_id", $school_id);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Exam updated successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to update exam'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Function to delete exam
function deleteExam($db, $school_id) {
    $exam_id = intval($_POST['exam_id']);
    
    $check_query = "SELECT id FROM tblexam WHERE id = :exam_id AND school_id = :school_id";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(":exam_id", $exam_id);
    $check_stmt->bindParam(":school_id", $school_id);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() === 0) {
        return ['success' => false, 'message' => 'Exam not found'];
    }
    
    try {
        $query = "DELETE FROM tblexam WHERE id = :exam_id AND school_id = :school_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":exam_id", $exam_id);
        $stmt->bindParam(":school_id", $school_id);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Exam deleted successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to delete exam'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Fetch exams data from tblexam table with sorting and filtering
try {
    $class_filter = isset($_GET['class_filter']) && !empty($_GET['class_filter']) ? $_GET['class_filter'] : null;
    $stream_filter = isset($_GET['stream_filter']) && !empty($_GET['stream_filter']) ? $_GET['stream_filter'] : null;
    $term_filter = isset($_GET['term_filter']) && !empty($_GET['term_filter']) ? $_GET['term_filter'] : null;
    
    $query = "SELECT e.id, e.examname, e.deadline_date, e.status, e.DateAdded, e.last_updated, 
                     e.class_id, e.stream_id, e.term_id,
                     c.class_level, s.stream_name,
                     t.term_name, t.term_number, t.academic_year
              FROM tblexam e
              LEFT JOIN tblclasses c ON e.class_id = c.id
              LEFT JOIN tblstreams s ON e.stream_id = s.id
              LEFT JOIN tblterms t ON e.term_id = t.id
              WHERE e.school_id = :school_id";
    
    if ($class_filter) {
        $query .= " AND e.class_id = :class_id";
    }
    if ($stream_filter) {
        $query .= " AND e.stream_id = :stream_id";
    }
    if ($term_filter) {
        $query .= " AND e.term_id = :term_id";
    }
    
    $query .= " ORDER BY e.deadline_date ASC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":school_id", $_SESSION['school_id'], PDO::PARAM_INT);
    
    if ($class_filter) {
        $stmt->bindParam(":class_id", $class_filter, PDO::PARAM_INT);
    }
    if ($stream_filter) {
        $stmt->bindParam(":stream_id", $stream_filter, PDO::PARAM_INT);
    }
    if ($term_filter) {
        $stmt->bindParam(":term_id", $term_filter, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    error_log("Exam Management data fetch error: " . $e->getMessage());
    $error_message = "Error loading exams: " . $e->getMessage();
}

// Get current user info
try {
    $query = "SELECT firstname, lastname, email, phonenumber, role FROM tblteachers WHERE id = :user_id AND school_id = :school_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":user_id", $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->bindParam(":school_id", $_SESSION['school_id'], PDO::PARAM_INT);
    $stmt->execute();
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user_info) {
        $_SESSION['user_fullname'] = $user_info['firstname'] . ' ' . $user_info['lastname'];
        $_SESSION['user_role'] = $user_info['role'];
    } else {
        $_SESSION['user_fullname'] = $_SESSION['user_name'] ?? 'User';
        $_SESSION['user_role'] = 'Administrator';
    }
} catch(PDOException $e) {
    error_log("User info fetch error: " . $e->getMessage());
    $_SESSION['user_fullname'] = $_SESSION['user_name'] ?? 'User';
    $_SESSION['user_role'] = 'Administrator';
}

// Calculate stats
$totalExams = count($exams);
$upcomingExams = 0;
$activeExams = 0;
$closedExams = 0;

foreach ($exams as $exam) {
    try {
        $deadline = new DateTime($exam['deadline_date']);
        $now = new DateTime();
        
        if ($now > $deadline) {
            $closedExams++;
        } else if ($now->diff($deadline)->days <= 7) {
            $activeExams++;
        } else {
            $upcomingExams++;
        }
    } catch (Exception $e) {
        error_log("Date parsing error for exam {$exam['id']}: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduScore - Exam Management</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="images/logo.png" />
    <link rel="apple-touch-icon" href="images/logo.png">
    <link rel="stylesheet" href="assets/banner/banner.css">
    <style>
        :root {
            --primary-blue: #1e3a8a;
            --primary-blue-light: #2563eb;
            --primary-blue-dark: #1e2a5a;
            --accent-yellow: #fbbf24;
            --accent-yellow-dark: #f59e0b;
            --white: #ffffff;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            --success-green: #10b981;
            --error-red: #ef4444;
            --warning-orange: #f59e0b;
            --info-blue: #3b82f6;
            --border-radius: 12px;
            --transition: all 0.3s ease;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body { font-family: 'Inter', sans-serif; background: var(--gray-50); color: var(--gray-900); min-height: 100vh; }

        .main-content { margin-left: 280px; min-height: 100vh; padding: 100px 2rem 2rem; transition: margin-left 0.3s ease; }

        @media (max-width: 992px) { .main-content { margin-left: 0; padding: 100px 1rem 1rem; } }

        .page-header { background: var(--white); border-radius: var(--border-radius); padding: 2rem; margin-bottom: 2rem; box-shadow: var(--shadow); border: 1px solid var(--gray-200); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; }

        .exam-page-title { font-size: 1.8rem; font-weight: 700; color: var(--primary-blue); display: flex; align-items: center; gap: 0.75rem; }

        .exam-page-title i { color: var(--accent-yellow); }

        .page-description { color: var(--gray-500); font-size: 1rem; }

        .role-badge { background: var(--primary-blue); color: var(--white); padding: 0.5rem 1rem; border-radius: 50px; font-size: 0.85rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.5rem; border: 1px solid var(--accent-yellow); }

        .permission-denied { background: #fef2f2; border: 1px solid #fecaca; color: var(--error-red); padding: 2rem; border-radius: var(--border-radius); text-align: center; margin: 2rem 0; }

        .stats-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem; }

        .stat-card { background: var(--white); border-radius: var(--border-radius); padding: 1.5rem; box-shadow: var(--shadow); display: flex; align-items: center; gap: 1rem; border: 1px solid var(--gray-200); transition: var(--transition); }

        .stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); border-color: var(--accent-yellow); }

        .stat-icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; color: var(--white); }

        .stat-card:nth-child(1) .stat-icon { background: var(--primary-blue); }
        .stat-card:nth-child(2) .stat-icon { background: var(--info-blue); }
        .stat-card:nth-child(3) .stat-icon { background: var(--warning-orange); }
        .stat-card:nth-child(4) .stat-icon { background: var(--gray-400); }

        .stat-value { font-size: 1.5rem; font-weight: 700; color: var(--gray-800); }

        .stat-label { font-size: 0.875rem; color: var(--gray-500); }

        .action-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; gap: 1rem; flex-wrap: wrap; background: var(--white); padding: 1rem 1.5rem; border-radius: var(--border-radius); box-shadow: var(--shadow); border: 1px solid var(--gray-200); }

        .exam-search-box { position: relative; flex: 1; max-width: 400px; min-width: 250px; }

        .exam-search-input { width: 100%; padding: 0.75rem 1rem 0.75rem 2.5rem; border: 1px solid var(--gray-200); border-radius: var(--border-radius); font-size: 0.9rem; background: var(--white); }

        .exam-search-input:focus { outline: none; border-color: var(--primary-blue); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }

        .exam-search-icon { position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: var(--gray-400); }

        .action-buttons { display: flex; gap: 0.75rem; flex-wrap: wrap; }

        .btn { padding: 0.75rem 1.25rem; border: none; border-radius: var(--border-radius); font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: var(--transition); display: flex; align-items: center; gap: 0.5rem; }

        .btn:disabled { opacity: 0.5; cursor: not-allowed; }

        .btn-primary { background: var(--primary-blue); color: var(--white); border: 1px solid var(--accent-yellow); }

        .btn-primary:hover:not(:disabled) { background: var(--primary-blue-dark); transform: translateY(-1px); }

        .btn-secondary { background: var(--gray-100); color: var(--gray-700); border: 1px solid var(--gray-200); }

        .btn-secondary:hover:not(:disabled) { background: var(--gray-200); transform: translateY(-1px); }

        .btn-danger { background: var(--error-red); color: var(--white); border: 1px solid var(--error-red); }

        .filter-section { background: var(--white); border-radius: var(--border-radius); padding: 1.5rem; margin-bottom: 2rem; box-shadow: var(--shadow); border: 1px solid var(--gray-200); }

        .filter-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; }

        .filter-group { display: flex; flex-direction: column; gap: 0.5rem; }

        .filter-label { font-size: 0.9rem; font-weight: 500; color: var(--gray-700); display: flex; align-items: center; gap: 0.5rem; }

        .filter-label i { color: var(--accent-yellow); }

        .filter-select { width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--gray-200); border-radius: var(--border-radius); font-size: 0.9rem; background: var(--white); cursor: pointer; }

        .exams-table-container { background: var(--white); border-radius: var(--border-radius); box-shadow: var(--shadow); overflow: hidden; border: 1px solid var(--gray-200); }

        .table-responsive { overflow-x: auto; }

        .exams-table { width: 100%; border-collapse: collapse; min-width: 1000px; }

        .exams-table th { background: var(--primary-blue); padding: 1rem 1.5rem; text-align: left; font-weight: 600; color: var(--white); font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; }

        .exams-table th i { margin-right: 0.5rem; color: var(--accent-yellow); }

        .exams-table td { padding: 1rem 1.5rem; border-bottom: 1px solid var(--gray-200); font-size: 0.9rem; vertical-align: middle; }

        .exams-table tr:hover { background: var(--gray-50); }

        .status-badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; min-width: 80px; text-align: center; }

        .status-upcoming { background: var(--gray-100); color: var(--gray-600); border: 1px solid var(--gray-200); }
        .status-active { background: var(--accent-yellow); color: var(--primary-blue-dark); border: 1px solid var(--accent-yellow-dark); }
        .status-closed { background: var(--gray-100); color: var(--gray-500); border: 1px solid var(--gray-200); }

        .date-badge { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.25rem 0.75rem; background: var(--gray-100); border-radius: 20px; font-size: 0.8rem; font-weight: 500; border: 1px solid var(--gray-200); }

        .date-badge.warning { background: #fef3c7; color: var(--warning-orange); border-color: #fde68a; }
        .date-badge.danger { background: #fee2e2; color: var(--error-red); border-color: #fecaca; }

        .term-badge { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.25rem 0.75rem; background: var(--gray-100); border-radius: 20px; font-size: 0.75rem; font-weight: 500; color: var(--primary-blue); border: 1px solid var(--gray-200); }

        .actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }

        .action-btn-small { padding: 0.5rem 0.75rem; border: none; border-radius: 6px; cursor: pointer; transition: var(--transition); display: inline-flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 500; gap: 0.25rem; }

        .edit-btn { background: var(--primary-blue); color: var(--white); border: 1px solid var(--accent-yellow); }

        .delete-btn { background: var(--error-red); color: var(--white); border: 1px solid var(--error-red); }

        .view-btn { background: var(--gray-100); color: var(--gray-700); border: 1px solid var(--gray-200); }

        .empty-state { text-align: center; padding: 4rem 2rem; color: var(--gray-500); }

        .empty-state i { font-size: 4rem; margin-bottom: 1rem; opacity: 0.5; color: var(--primary-blue); }

        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 2000; padding: 1rem; }

        .modal-overlay.active { display: flex; animation: fadeIn 0.3s ease; }

        .modal { background: var(--white); border-radius: var(--border-radius); box-shadow: var(--shadow-lg); width: 100%; max-width: 500px; max-height: 90vh; overflow-y: auto; animation: slideUp 0.3s ease; border-top: 4px solid var(--accent-yellow); }

        .modal-header { padding: 1.5rem 2rem; border-bottom: 1px solid var(--gray-200); display: flex; align-items: center; justify-content: space-between; }

        .modal-title { font-size: 1.25rem; font-weight: 600; color: var(--primary-blue); display: flex; align-items: center; gap: 0.5rem; }

        .close-modal { background: none; border: none; font-size: 1.25rem; color: var(--gray-400); cursor: pointer; padding: 0.5rem; border-radius: 6px; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; }

        .close-modal:hover { background: var(--gray-100); }

        .modal-body { padding: 2rem; }

        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }

        @media (max-width: 768px) { .form-row { grid-template-columns: 1fr; } }

        .form-group { margin-bottom: 1.5rem; }

        .form-label { display: block; margin-bottom: 0.5rem; font-weight: 500; color: var(--gray-700); font-size: 0.9rem; }

        .form-label.required::after { content: '*'; color: var(--error-red); margin-left: 0.25rem; }

        .form-control { width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--gray-200); border-radius: var(--border-radius); font-size: 0.9rem; background: var(--white); }

        .form-control:focus { outline: none; border-color: var(--primary-blue); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }

        .form-control.form-select { background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e"); background-position: right 0.75rem center; background-repeat: no-repeat; background-size: 16px 12px; padding-right: 2.5rem; appearance: none; }

        .modal-footer { padding: 1.5rem 2rem; border-top: 1px solid var(--gray-200); display: flex; gap: 1rem; justify-content: flex-end; }

        .delete-modal .modal-header { border-top-color: var(--error-red); }

        .delete-warning { background: #fef2f2; border: 1px solid #fecaca; color: var(--error-red); padding: 0.75rem; border-radius: 8px; margin-top: 1rem; display: flex; align-items: center; gap: 0.5rem; }

        .toast-container { position: fixed; top: 100px; right: 2rem; z-index: 3000; max-width: 400px; }

        .toast { background: var(--white); border-radius: var(--border-radius); padding: 1rem 1.5rem; margin-bottom: 1rem; box-shadow: var(--shadow-lg); border-left: 4px solid var(--success-green); display: flex; align-items: center; gap: 1rem; animation: slideInRight 0.3s ease; }

        .toast.error { border-left-color: var(--error-red); }

        .toast.warning { border-left-color: var(--warning-orange); }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

        @keyframes slideInRight { from { opacity: 0; transform: translateX(100%); } to { opacity: 1; transform: translateX(0); } }

        @keyframes spin { to { transform: rotate(360deg); } }

        .spinner { display: inline-block; width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-radius: 50%; border-top-color: var(--white); animation: spin 1s ease-in-out infinite; margin-right: 8px; }

        @media (max-width: 768px) { .main-content { padding: 100px 1rem 1rem; } .filter-row { grid-template-columns: 1fr; } .modal { max-width: 95%; } .toast-container { right: 1rem; left: 1rem; max-width: none; } }
    </style>
</head>
<body>
    <?php 
    if (!isset($school)) {
        $stmt = $db->prepare("SELECT * FROM tblschoolinfo WHERE id = :school_id");
        $stmt->bindParam(":school_id", $_SESSION['school_id'], PDO::PARAM_INT);
        $stmt->execute();
        $school = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    include 'trial_banner.php'; 
    ?>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <div class="header-left">
                <h1 class="exam-page-title"><i class="fas fa-file-alt"></i> Exam Management</h1>
                <span class="role-badge"><i class="fas fa-<?php echo $isSuperAdmin ? 'crown' : 'user-tag'; ?>"></i> <?php echo htmlspecialchars($currentUserRole ?? 'User'); ?></span>
            </div>
            <p class="page-description">Create and manage exams for your classes</p>
        </div>

        <?php if (!$permissionHelper->hasAnyPermission(['examsView', 'examsViewAll'])): ?>
            <div class="permission-denied">
                <i class="fas fa-lock"></i>
                <h3>Access Denied</h3>
                <p>You do not have permission to view exams.</p>
                <p style="font-size: 0.9rem; margin-top: 0.5rem;">Please contact your system administrator if you need access.</p>
            </div>
        <?php else: ?>

        <div class="stats-container">
            <div class="stat-card"><div class="stat-icon total"><i class="fas fa-file-alt"></i></div><div class="stat-content"><div class="stat-value" id="totalExams"><?php echo $totalExams; ?></div><div class="stat-label">Total Exams</div></div></div>
            <div class="stat-card"><div class="stat-icon upcoming"><i class="fas fa-clock"></i></div><div class="stat-content"><div class="stat-value" id="upcomingExams"><?php echo $upcomingExams; ?></div><div class="stat-label">Upcoming</div></div></div>
            <div class="stat-card"><div class="stat-icon active"><i class="fas fa-spinner"></i></div><div class="stat-content"><div class="stat-value" id="activeExams"><?php echo $activeExams; ?></div><div class="stat-label">Active</div></div></div>
            <div class="stat-card"><div class="stat-icon closed"><i class="fas fa-check-circle"></i></div><div class="stat-content"><div class="stat-value" id="closedExams"><?php echo $closedExams; ?></div><div class="stat-label">Closed</div></div></div>
        </div>

        <div class="action-bar">
            <div class="exam-search-box"><i class="fas fa-search exam-search-icon"></i><input type="text" class="exam-search-input" id="searchInput" placeholder="Search exams..."></div>
            <div class="action-buttons">
                <?php if ($canCreate): ?>
                    <button class="btn btn-primary" id="addExamBtn"><i class="fas fa-plus"></i> Add New Exam</button>
                <?php endif; ?>
                <button class="btn btn-secondary" id="refreshBtn"><i class="fas fa-sync-alt"></i> Refresh</button>
            </div>
        </div>

        <div class="filter-section">
            <div class="filter-row">
                <div class="filter-group"><label class="filter-label"><i class="fas fa-graduation-cap"></i> Filter by Class</label><select id="filterClass" class="filter-select"><option value="">All Classes</option><?php foreach ($classes as $class): ?><option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['class_level']); ?></option><?php endforeach; ?></select></div>
                <div class="filter-group"><label class="filter-label"><i class="fas fa-stream"></i> Filter by Stream</label><select id="filterStream" class="filter-select"><option value="">All Streams</option><?php foreach ($streams as $stream): ?><option value="<?php echo $stream['id']; ?>" data-class="<?php echo $stream['class_id']; ?>"><?php echo htmlspecialchars($stream['stream_name']); ?></option><?php endforeach; ?></select></div>
                <div class="filter-group"><label class="filter-label"><i class="fas fa-calendar-alt"></i> Filter by Term</label><select id="filterTerm" class="filter-select"><option value="">All Terms</option><?php foreach ($terms as $term): ?><option value="<?php echo $term['id']; ?>"><?php echo htmlspecialchars(formatTermName($term)); ?></option><?php endforeach; ?></select></div>
                <div class="filter-group"><label class="filter-label"><i class="fas fa-filter"></i> Filter by Status</label><select id="filterStatus" class="filter-select"><option value="">All Status</option><option value="upcoming">Upcoming</option><option value="active">Active</option><option value="closed">Closed</option></select></div>
            </div>
        </div>

        <div class="exams-table-container">
            <div class="table-responsive">
                <table class="exams-table" id="examsTable">
                    <thead><tr><th><i class="fas fa-file-alt"></i> Exam Name</th><th><i class="fas fa-calendar-alt"></i> Term</th><th><i class="fas fa-clock"></i> Deadline</th><th><i class="fas fa-graduation-cap"></i> Class</th><th><i class="fas fa-stream"></i> Stream</th><th><i class="fas fa-info-circle"></i> Status</th><th><i class="fas fa-cogs"></i> Actions</th></tr></thead>
                    <tbody id="examsTableBody">
                        <?php if (!empty($exams)): foreach ($exams as $exam): 
                            $deadline = new DateTime($exam['deadline_date']);
                            $now = new DateTime();
                            $status = 'upcoming';
                            $dateClass = 'upcoming';
                            
                            if ($now > $deadline) {
                                $status = 'closed';
                                $dateClass = 'danger';
                            } else if ($now->diff($deadline)->days <= 7) {
                                $status = 'active';
                                $dateClass = 'warning';
                            }
                            
                            $dateDiff = $now->diff($deadline);
                            $daysLeft = $dateDiff->days;
                            $hoursLeft = $dateDiff->h;
                            $termDisplay = formatTermName($exam['term_name']);
                        ?>
                            <tr data-exam-id="<?php echo $exam['id']; ?>" data-class="<?php echo $exam['class_id']; ?>" data-stream="<?php echo $exam['stream_id']; ?>" data-term="<?php echo $exam['term_id']; ?>" data-status="<?php echo $status; ?>">
                                <td><strong><?php echo htmlspecialchars($exam['examname']); ?></strong><div style="font-size: 0.75rem; color: var(--gray-400); margin-top: 0.25rem;"><i class="far fa-calendar"></i> Created: <?php echo date('M j, Y', strtotime($exam['DateAdded'])); ?></div></td>
                                <td><span class="term-badge"><i class="fas fa-calendar-alt"></i> <?php echo htmlspecialchars($termDisplay); ?></span></td>
                                <td><div class="date-badge <?php echo $dateClass; ?>"><i class="fas fa-clock"></i> <?php echo $deadline->format('M j, Y g:i A'); ?><span style="font-size: 0.7rem; margin-left: 0.25rem;">(<?php echo $status === 'closed' ? 'Expired' : $daysLeft . 'd ' . $hoursLeft . 'h left'; ?>)</span></div></td>
                                <td><strong><?php echo htmlspecialchars($exam['class_level'] ?? 'N/A'); ?></strong></td>
                                <td><?php echo !empty($exam['stream_name']) ? htmlspecialchars($exam['stream_name']) : '<span style="color: var(--gray-400);">All Streams</span>'; ?></td>
                                <td><span class="status-badge status-<?php echo $status; ?>"><?php echo ucfirst($status); ?></span></td>
                                <td><div class="actions"><?php if ($canEdit): ?><button class="action-btn-small edit-btn" onclick="editExam(<?php echo $exam['id']; ?>)"><i class="fas fa-edit"></i> Edit</button><?php endif; ?><?php if ($canDelete): ?><button class="action-btn-small delete-btn" onclick="showDeleteModal(<?php echo $exam['id']; ?>)"><i class="fas fa-trash"></i> Delete</button><?php endif; ?><button class="action-btn-small view-btn" onclick="viewExam(<?php echo $exam['id']; ?>)"><i class="fas fa-eye"></i> View</button></div></td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="7"><div class="empty-state"><i class="fas fa-file-alt"></i><h3>No Exams Found</h3><p>Create your first exam to get started with exam management.</p><?php if ($canCreate): ?><button class="btn btn-primary" onclick="openAddExamModal()" style="margin-top: 1.5rem;"><i class="fas fa-plus"></i> Create First Exam</button><?php endif; ?></div></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($canCreate || $canEdit): ?>
    <div class="modal-overlay" id="examModal"><div class="modal"><div class="modal-header"><h3 class="modal-title"><i class="fas fa-file-alt"></i> <span id="modalTitle">Add New Exam</span></h3><button class="close-modal" id="closeModal"><i class="fas fa-times"></i></button></div><form id="examForm"><div class="modal-body"><input type="hidden" id="examId" name="examId"><div class="form-group"><label class="form-label required">Exam Name</label><input type="text" id="examName" name="examName" class="form-control" placeholder="Enter exam name" required></div><div class="form-group"><label class="form-label required">Academic Term</label><select class="form-control form-select" id="examTerm" name="term_id" required><option value="">-- Select Term --</option><?php foreach ($terms as $term): ?><option value="<?php echo $term['id']; ?>"><?php echo htmlspecialchars(formatTermName($term)); ?></option><?php endforeach; ?></select></div><div class="form-row"><div class="form-group"><label class="form-label required">Class</label><select class="form-control form-select" id="examClass" name="class_id" required><option value="">-- Select Class --</option><?php foreach ($classes as $class): ?><option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['class_level']); ?></option><?php endforeach; ?></select></div><div class="form-group"><label class="form-label">Stream (Optional)</label><select class="form-control form-select" id="examStream" name="stream_id"><option value="">-- All Streams --</option></select></div></div><div class="form-group"><label class="form-label required">Exam Deadline</label><input type="datetime-local" id="examDeadline" name="examDeadline" class="form-control" required></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" id="cancelBtn">Cancel</button><button type="submit" class="btn btn-primary" id="saveExamBtn"><i class="fas fa-save"></i> <span id="saveBtnText">Save Exam</span></button></div></form></div></div>
    <?php endif; ?>

    <?php if ($canDelete): ?>
    <div class="modal-overlay" id="deleteModal"><div class="modal delete-modal"><div class="modal-header"><h3 class="modal-title"><i class="fas fa-exclamation-triangle"></i> Delete Exam</h3><button class="close-modal" id="closeDeleteModal"><i class="fas fa-times"></i></button></div><div class="modal-body"><p>Are you sure you want to delete this exam?</p><div class="delete-warning"><i class="fas fa-info-circle"></i> This action cannot be undone. All associated data will be permanently removed.</div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" id="cancelDeleteBtn">Cancel</button><button type="button" class="btn btn-danger" id="confirmDeleteBtn"><i class="fas fa-trash"></i> Delete Exam</button></div></div></div>
    <?php endif; ?>

    <div class="toast-container" id="toastContainer"></div>

    <script>
        let editingExamId = null;
        let examToDeleteId = null;
        let isSubmitting = false;

        const PERMISSIONS = { canCreate: <?php echo $canCreate ? 'true' : 'false'; ?>, canEdit: <?php echo $canEdit ? 'true' : 'false'; ?>, canDelete: <?php echo $canDelete ? 'true' : 'false'; ?>, isSuperAdmin: <?php echo $isSuperAdmin ? 'true' : 'false'; ?> };

        function showToast(title, message, type = 'success') { const toast = document.createElement('div'); toast.className = `toast ${type}`; let icon = 'check-circle'; if (type === 'error') icon = 'exclamation-triangle'; if (type === 'warning') icon = 'exclamation-circle'; toast.innerHTML = `<div class="toast-icon"><i class="fas fa-${icon}"></i></div><div class="toast-content"><div class="toast-title">${escapeHtml(title)}</div><div class="toast-message">${escapeHtml(message)}</div></div>`; document.getElementById('toastContainer').appendChild(toast); setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 300); }, 5000); }

        function escapeHtml(text) { if (!text) return ''; const div = document.createElement('div'); div.textContent = text; return div.innerHTML; }

        function checkPermission(action) { if (!PERMISSIONS[action] && !PERMISSIONS.isSuperAdmin) { showToast('Access Denied', 'You do not have permission', 'error'); return false; } return true; }

        function showLoading(button, text) { const originalHTML = button.innerHTML; button.dataset.originalHTML = originalHTML; button.innerHTML = `<span class="spinner"></span>${text}`; button.disabled = true; }
        function hideLoading(button) { if (button.dataset.originalHTML) { button.innerHTML = button.dataset.originalHTML; delete button.dataset.originalHTML; } button.disabled = false; }

        function loadStreams(classId, targetElement, selectedStreamId = null) {
            if (!targetElement || !classId) { if (targetElement) { targetElement.innerHTML = '<option value="">-- All Streams --</option>'; targetElement.disabled = true; } return; }
            targetElement.innerHTML = '<option value="">-- Loading streams... --</option>';
            const allStreamOptions = document.querySelectorAll('#filterStream option');
            targetElement.innerHTML = '<option value="">-- All Streams --</option>';
            allStreamOptions.forEach(option => {
                if (option.value && option.dataset.class == classId) {
                    const newOption = document.createElement('option');
                    newOption.value = option.value;
                    newOption.textContent = option.textContent;
                    if (selectedStreamId && option.value == selectedStreamId) newOption.selected = true;
                    targetElement.appendChild(newOption);
                }
            });
            if (targetElement.children.length <= 1) targetElement.innerHTML = '<option value="">-- No streams available --</option>';
            targetElement.disabled = false;
        }

        function filterExamsTable() {
            const classFilter = document.getElementById('filterClass').value;
            const streamFilter = document.getElementById('filterStream').value;
            const termFilter = document.getElementById('filterTerm').value;
            const statusFilter = document.getElementById('filterStatus').value;
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const rows = document.querySelectorAll('#examsTableBody tr[data-exam-id]');
            let visibleCount = 0;
            rows.forEach(row => { const classMatch = !classFilter || row.dataset.class == classFilter; const streamMatch = !streamFilter || row.dataset.stream == streamFilter; const termMatch = !termFilter || row.dataset.term == termFilter; const statusMatch = !statusFilter || row.dataset.status == statusFilter; const searchMatch = !searchTerm || row.textContent.toLowerCase().includes(searchTerm); if (classMatch && streamMatch && termMatch && statusMatch && searchMatch) { row.style.display = ''; visibleCount++; } else { row.style.display = 'none'; } });
            const emptyState = document.querySelector('#examsTableBody tr:not([data-exam-id])');
            if (emptyState) emptyState.style.display = visibleCount === 0 ? '' : 'none';
        }

        function updateStats() { const rows = document.querySelectorAll('#examsTableBody tr[data-exam-id]'); let total = rows.length, upcoming = 0, active = 0, closed = 0; rows.forEach(row => { const status = row.dataset.status; if (status === 'upcoming') upcoming++; if (status === 'active') active++; if (status === 'closed') closed++; }); document.getElementById('totalExams').textContent = total; document.getElementById('upcomingExams').textContent = upcoming; document.getElementById('activeExams').textContent = active; document.getElementById('closedExams').textContent = closed; }

        function openAddExamModal() { if (!checkPermission('canCreate')) return; document.getElementById('modalTitle').textContent = 'Add New Exam'; document.getElementById('saveBtnText').textContent = 'Save Exam'; document.getElementById('examForm').reset(); document.getElementById('examId').value = ''; editingExamId = null; const now = new Date(); const localDateTime = new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0, 16); document.getElementById('examDeadline').min = localDateTime; document.getElementById('examStream').innerHTML = '<option value="">-- All Streams --</option>'; document.getElementById('examModal').classList.add('active'); document.body.style.overflow = 'hidden'; }

        function editExam(examId) { if (!checkPermission('canEdit')) return; editingExamId = examId; showLoading(document.getElementById('saveExamBtn'), 'Loading...'); fetch('exams.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `action=get_exam&exam_id=${examId}` }).then(res => res.json()).then(data => { if (data.success) { const exam = data.exam; document.getElementById('modalTitle').textContent = 'Edit Exam'; document.getElementById('saveBtnText').textContent = 'Update Exam'; document.getElementById('examId').value = exam.id; document.getElementById('examName').value = exam.examname; document.getElementById('examClass').value = exam.class_id; document.getElementById('examTerm').value = exam.term_id; const deadline = new Date(exam.deadline_date); const localDateTime = new Date(deadline.getTime() - deadline.getTimezoneOffset() * 60000).toISOString().slice(0, 16); document.getElementById('examDeadline').value = localDateTime; loadStreams(exam.class_id, document.getElementById('examStream'), exam.stream_id); hideLoading(document.getElementById('saveExamBtn')); document.getElementById('examModal').classList.add('active'); document.body.style.overflow = 'hidden'; } else { showToast('Error', data.message, 'error'); hideLoading(document.getElementById('saveExamBtn')); } }).catch(() => { showToast('Error', 'Failed to load exam data', 'error'); hideLoading(document.getElementById('saveExamBtn')); }); }

        function viewExam(examId) { showToast('Info', 'View exam functionality coming soon!', 'info'); }

        function showDeleteModal(examId) { if (!checkPermission('canDelete')) return; examToDeleteId = examId; document.getElementById('deleteModal').classList.add('active'); document.body.style.overflow = 'hidden'; }

        function closeModals() { document.getElementById('examModal')?.classList.remove('active'); document.getElementById('deleteModal')?.classList.remove('active'); document.body.style.overflow = ''; }

        function validateExamForm() { const examName = document.getElementById('examName').value.trim(); const examClass = document.getElementById('examClass').value; const examTerm = document.getElementById('examTerm').value; const examDeadline = document.getElementById('examDeadline').value; if (!examName) { showToast('Warning', 'Please enter exam name', 'warning'); return false; } if (!examClass) { showToast('Warning', 'Please select a class', 'warning'); return false; } if (!examTerm) { showToast('Warning', 'Please select a term', 'warning'); return false; } if (!examDeadline) { showToast('Warning', 'Please select exam deadline', 'warning'); return false; } const deadline = new Date(examDeadline); if (deadline <= new Date()) { showToast('Warning', 'Deadline must be in the future', 'warning'); return false; } return true; }

        function saveExam() { if (isSubmitting) return; const isEdit = editingExamId !== null; if ((isEdit && !PERMISSIONS.canEdit) || (!isEdit && !PERMISSIONS.canCreate)) { showToast('Access Denied', 'You do not have permission', 'error'); return; } if (!validateExamForm()) return; const formData = new FormData(document.getElementById('examForm')); const action = isEdit ? 'update_exam' : 'add_exam'; const streamValue = document.getElementById('examStream').value; if (streamValue === '') formData.delete('stream_id'); formData.append('action', action); showLoading(document.getElementById('saveExamBtn'), isEdit ? 'Updating...' : 'Saving...'); isSubmitting = true; fetch('exams.php', { method: 'POST', body: new URLSearchParams(formData) }).then(res => res.json()).then(data => { if (data.success) { showToast('Success', data.message, 'success'); closeModals(); window.location.reload(); } else { showToast('Error', data.message, 'error'); } }).catch(() => { showToast('Error', 'Failed to save exam', 'error'); }).finally(() => { isSubmitting = false; hideLoading(document.getElementById('saveExamBtn')); }); }

        function confirmDelete() { if (!PERMISSIONS.canDelete) return; showLoading(document.getElementById('confirmDeleteBtn'), 'Deleting...'); fetch('exams.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `action=delete_exam&exam_id=${examToDeleteId}` }).then(res => res.json()).then(data => { if (data.success) { showToast('Success', data.message, 'success'); closeModals(); window.location.reload(); } else { showToast('Error', data.message, 'error'); } }).catch(() => { showToast('Error', 'Failed to delete exam', 'error'); }).finally(() => { hideLoading(document.getElementById('confirmDeleteBtn')); }); }

        function refreshTable() { window.location.reload(); }

        function updateExamStatuses() { const now = new Date(); document.querySelectorAll('#examsTableBody tr[data-exam-id]').forEach(row => { const deadlineText = row.cells[2].textContent.match(/(\w+ \d+, \d+ \d+:\d+ [AP]M)/); if (deadlineText) { const deadline = new Date(deadlineText[0]); let status = 'upcoming', dateClass = 'upcoming'; if (now > deadline) { status = 'closed'; dateClass = 'danger'; } else if ((deadline - now) <= 7 * 24 * 60 * 60 * 1000) { status = 'active'; dateClass = 'warning'; } row.dataset.status = status; const badge = row.cells[5].querySelector('.status-badge'); if (badge) { badge.className = `status-badge status-${status}`; badge.textContent = status.charAt(0).toUpperCase() + status.slice(1); } const dateBadge = row.cells[2].querySelector('.date-badge'); if (dateBadge) { dateBadge.className = `date-badge ${dateClass}`; const diffMs = deadline - now; const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24)); const diffHours = Math.floor((diffMs % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60)); const timeSpan = dateBadge.querySelector('span'); if (timeSpan) timeSpan.textContent = `(${status === 'closed' ? 'Expired' : `${Math.max(0, diffDays)}d ${Math.max(0, diffHours)}h left`})`; } } }); if (document.getElementById('filterStatus').value) filterExamsTable(); updateStats(); }

        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('addExamBtn')?.addEventListener('click', openAddExamModal);
            document.getElementById('refreshBtn')?.addEventListener('click', refreshTable);
            document.getElementById('closeModal')?.addEventListener('click', closeModals);
            document.getElementById('cancelBtn')?.addEventListener('click', closeModals);
            document.getElementById('closeDeleteModal')?.addEventListener('click', closeModals);
            document.getElementById('cancelDeleteBtn')?.addEventListener('click', closeModals);
            document.getElementById('confirmDeleteBtn')?.addEventListener('click', confirmDelete);
            document.getElementById('examForm')?.addEventListener('submit', function(e) { e.preventDefault(); saveExam(); });
            document.getElementById('examClass')?.addEventListener('change', function() { loadStreams(this.value, document.getElementById('examStream')); });
            document.getElementById('filterClass')?.addEventListener('change', function() { const classId = this.value; const streamOptions = document.getElementById('filterStream').querySelectorAll('option'); streamOptions.forEach(option => { if (!option.value || option.dataset.class == classId || !classId) option.style.display = ''; else option.style.display = 'none'; }); if (classId) document.getElementById('filterStream').value = ''; filterExamsTable(); });
            document.getElementById('filterStream')?.addEventListener('change', filterExamsTable);
            document.getElementById('filterTerm')?.addEventListener('change', filterExamsTable);
            document.getElementById('filterStatus')?.addEventListener('change', filterExamsTable);
            document.getElementById('searchInput')?.addEventListener('input', filterExamsTable);
            setInterval(updateExamStatuses, 60000);
        });
    </script>
</body>
</html>