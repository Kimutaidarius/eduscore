<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);


require_once 'includes/config.php';
require_once 'includes/session_timeout.php'; 


// Detect AJAX
$isAjax = ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']));

// ✅ SINGLE SOURCE OF TRUTH
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

// DB
if (!isset($db)) {
    require_once 'config/database.php';
    $database = new Database();
    $db = $database->getConnection();
}


// Get current academic level from session
$current_level = $_SESSION['academic_level'] ?? 'Primary';
$school_id = $_SESSION['school_id'];

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'add_subject':
                $response = addSubject($db, $school_id);
                echo json_encode($response);
                break;
                
            case 'update_subject':
                $response = updateSubject($db, $school_id);
                echo json_encode($response);
                break;
                
            case 'delete_subject':
                $response = deleteSubject($db, $school_id);
                echo json_encode($response);
                break;
                
            case 'get_subject':
                $subject_id = intval($_POST['subject_id']);
                $query = "SELECT s.*, c.class_level, 
                         CONCAT(t.firstname, ' ', COALESCE(t.secondname, ''), ' ', t.lastname) as teacher_name
                         FROM tblsubjects s 
                         JOIN tblclasses c ON s.class_id = c.id 
                         LEFT JOIN tblteachers t ON s.teacher_id = t.id 
                         WHERE s.id = :subject_id AND s.school_id = :school_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(":subject_id", $subject_id);
                $stmt->bindParam(":school_id", $school_id);
                $stmt->execute();
                $subject = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($subject) {
                    echo json_encode(['success' => true, 'subject' => $subject]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Subject not found']);
                }
                break;
                
            case 'get_streams':
                $class_id = intval($_POST['class_id']);
                $query = "SELECT id, stream_name FROM tblstreams WHERE class_id = :class_id AND school_id = :school_id ORDER BY stream_name";
                $stmt = $db->prepare($query);
                $stmt->bindParam(":class_id", $class_id);
                $stmt->bindParam(":school_id", $school_id);
                $stmt->execute();
                $streams = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'streams' => $streams]);
                break;
                
            case 'get_subjects':
                // Get filter parameters
                $page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
                $search = isset($_POST['search']) ? trim($_POST['search']) : '';
                $class_filter = isset($_POST['class_filter']) ? intval($_POST['class_filter']) : '';
                $stream_filter = isset($_POST['stream_filter']) ? intval($_POST['stream_filter']) : '';
                
                $limit = 10;
                $offset = ($page - 1) * $limit;
                
                // Build query for subjects with alias and subject_type
                $subjects_query = "SELECT s.id, s.subject_name, s.alias, s.category_id, s.subject_type, 
                                  s.class_id, s.stream_id, s.teacher_id, 
                                  c.class_level, c.academic_level,
                                  st.stream_name,
                                  CONCAT(t.firstname, ' ', COALESCE(t.secondname, ''), ' ', t.lastname) as teacher_name
                                  FROM tblsubjects s 
                                  JOIN tblclasses c ON s.class_id = c.id 
                                  LEFT JOIN tblstreams st ON s.stream_id = st.id
                                  LEFT JOIN tblteachers t ON s.teacher_id = t.id 
                                  WHERE s.school_id = :school_id 
                                  AND c.academic_level = :academic_level";
                
                $count_query = "SELECT COUNT(*) FROM tblsubjects s 
                               JOIN tblclasses c ON s.class_id = c.id 
                               WHERE s.school_id = :school_id 
                               AND c.academic_level = :academic_level";
                
                $params = [
                    ':school_id' => $school_id,
                    ':academic_level' => $current_level
                ];
                
                if (!empty($search)) {
                    $search_term = "%$search%";
                    $subjects_query .= " AND (s.subject_name LIKE :search OR s.alias LIKE :search)";
                    $count_query .= " AND (s.subject_name LIKE :search OR s.alias LIKE :search)";
                    $params[':search'] = $search_term;
                }
                
                if (!empty($class_filter)) {
                    $subjects_query .= " AND s.class_id = :class_filter";
                    $count_query .= " AND s.class_id = :class_filter";
                    $params[':class_filter'] = $class_filter;
                }
                
                if (!empty($stream_filter)) {
                    $subjects_query .= " AND s.stream_id = :stream_filter";
                    $count_query .= " AND s.stream_id = :stream_filter";
                    $params[':stream_filter'] = $stream_filter;
                }
                
                $subjects_query .= " ORDER BY c.class_level, s.subject_name LIMIT :limit OFFSET :offset";
                
                // Get total count
                $count_stmt = $db->prepare($count_query);
                foreach ($params as $key => $value) {
                    if ($key !== ':limit' && $key !== ':offset') {
                        $count_stmt->bindValue($key, $value);
                    }
                }
                $count_stmt->execute();
                $total_subjects = $count_stmt->fetchColumn();
                $total_pages = ceil($total_subjects / $limit);
                
                // Get subjects
                $params[':limit'] = $limit;
                $params[':offset'] = $offset;
                
                $subjects_stmt = $db->prepare($subjects_query);
                foreach ($params as $key => $value) {
                    $subjects_stmt->bindValue($key, $value, $key === ':limit' || $key === ':offset' ? PDO::PARAM_INT : PDO::PARAM_STR);
                }
                $subjects_stmt->execute();
                $subjects = $subjects_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'subjects' => $subjects,
                    'total_pages' => $total_pages,
                    'current_page' => $page,
                    'total_subjects' => $total_subjects
                ]);
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (PDOException $e) {
        error_log("Subjects AJAX error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    } catch (Exception $e) {
        error_log("Subjects general error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
    }
    exit;
}

// Function to add subject
// Function to add subject
function addSubject($db, $school_id) {
    // Validate required fields
    $required_fields = ['subject_name', 'class_id', 'subject_type'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            return ['success' => false, 'message' => "Required field '$field' is missing"];
        }
    }
    
    // Check if subject already exists for this class
    $check_query = "SELECT id FROM tblsubjects WHERE subject_name = :subject_name AND class_id = :class_id AND school_id = :school_id";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(":subject_name", $_POST['subject_name']);
    $check_stmt->bindParam(":class_id", $_POST['class_id']);
    $check_stmt->bindParam(":school_id", $school_id);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() > 0) {
        return ['success' => false, 'message' => 'Subject already exists for this class'];
    }
    
    try {
        // Get values with proper null handling
        $alias = !empty($_POST['alias']) ? $_POST['alias'] : null;
        $stream_id = !empty($_POST['stream_id']) ? intval($_POST['stream_id']) : null;
        $teacher_id = !empty($_POST['teacher_id']) ? intval($_POST['teacher_id']) : null;
        $subject_type = $_POST['subject_type'];
        
        // Validate subject_type matches database ENUM
        if (!in_array($subject_type, ['Compulsory', 'Optional'])) {
            return ['success' => false, 'message' => 'Invalid subject type. Must be Compulsory or Optional'];
        }
        
        // Insert subject with all fields (category_id removed)
        $query = "INSERT INTO tblsubjects 
                 (subject_name, alias, class_id, stream_id, 
                  teacher_id, subject_type, school_id) 
                 VALUES (:subject_name, :alias, :class_id, :stream_id, 
                         :teacher_id, :subject_type, :school_id)";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(":subject_name", $_POST['subject_name']);
        $stmt->bindParam(":alias", $alias, PDO::PARAM_STR);
        $stmt->bindParam(":class_id", $_POST['class_id'], PDO::PARAM_INT);
        $stmt->bindParam(":stream_id", $stream_id, PDO::PARAM_INT | PDO::PARAM_NULL);
        $stmt->bindParam(":teacher_id", $teacher_id, PDO::PARAM_INT | PDO::PARAM_NULL);
        $stmt->bindParam(":subject_type", $subject_type);
        $stmt->bindParam(":school_id", $school_id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            $subject_id = $db->lastInsertId();
            
            // Get the created subject with full details
            $query = "SELECT s.*, c.class_level, st.stream_name,
                     CONCAT(t.firstname, ' ', COALESCE(t.secondname, ''), ' ', t.lastname) as teacher_name
                     FROM tblsubjects s 
                     JOIN tblclasses c ON s.class_id = c.id 
                     LEFT JOIN tblstreams st ON s.stream_id = st.id
                     LEFT JOIN tblteachers t ON s.teacher_id = t.id 
                     WHERE s.id = :subject_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":subject_id", $subject_id, PDO::PARAM_INT);
            $stmt->execute();
            $subject = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'success' => true, 
                'message' => 'Subject added successfully',
                'subject' => $subject
            ];
        } else {
            return ['success' => false, 'message' => 'Failed to add subject'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Function to update subject
function updateSubject($db, $school_id) {
    $subject_id = intval($_POST['subject_id']);
    
    // Check if subject exists
    $check_query = "SELECT id FROM tblsubjects WHERE id = :subject_id AND school_id = :school_id";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(":subject_id", $subject_id, PDO::PARAM_INT);
    $check_stmt->bindParam(":school_id", $school_id, PDO::PARAM_INT);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() === 0) {
        return ['success' => false, 'message' => 'Subject not found'];
    }
    
    // Validate required fields
    $required_fields = ['subject_name', 'class_id', 'subject_type'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            return ['success' => false, 'message' => "Required field '$field' is missing"];
        }
    }
    
    // Check if subject name already exists for this class (excluding current subject)
    $check_query = "SELECT id FROM tblsubjects WHERE subject_name = :subject_name AND class_id = :class_id AND school_id = :school_id AND id != :subject_id";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(":subject_name", $_POST['subject_name']);
    $check_stmt->bindParam(":class_id", $_POST['class_id'], PDO::PARAM_INT);
    $check_stmt->bindParam(":school_id", $school_id, PDO::PARAM_INT);
    $check_stmt->bindParam(":subject_id", $subject_id, PDO::PARAM_INT);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() > 0) {
        return ['success' => false, 'message' => 'Subject name already exists for this class'];
    }
    
    try {
        // Get values with proper null handling
        $alias = !empty($_POST['alias']) ? $_POST['alias'] : null;
        $stream_id = !empty($_POST['stream_id']) ? intval($_POST['stream_id']) : null;
        $teacher_id = !empty($_POST['teacher_id']) ? intval($_POST['teacher_id']) : null;
        $subject_type = $_POST['subject_type'];
        
        // Validate subject_type matches database ENUM
        if (!in_array($subject_type, ['Compulsory', 'Optional'])) {
            return ['success' => false, 'message' => 'Invalid subject type. Must be Compulsory or Optional'];
        }
        
        // Update subject with all fields (category_id removed)
        $query = "UPDATE tblsubjects 
                 SET subject_name = :subject_name, 
                     alias = :alias,
                     class_id = :class_id, 
                     stream_id = :stream_id, 
                     teacher_id = :teacher_id,
                     subject_type = :subject_type,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :subject_id AND school_id = :school_id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(":subject_name", $_POST['subject_name']);
        $stmt->bindParam(":alias", $alias, PDO::PARAM_STR);
        $stmt->bindParam(":class_id", $_POST['class_id'], PDO::PARAM_INT);
        $stmt->bindParam(":stream_id", $stream_id, PDO::PARAM_INT | PDO::PARAM_NULL);
        $stmt->bindParam(":teacher_id", $teacher_id, PDO::PARAM_INT | PDO::PARAM_NULL);
        $stmt->bindParam(":subject_type", $subject_type);
        $stmt->bindParam(":subject_id", $subject_id, PDO::PARAM_INT);
        $stmt->bindParam(":school_id", $school_id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            // Get the updated subject with full details
            $query = "SELECT s.*, c.class_level, st.stream_name,
                     CONCAT(t.firstname, ' ', COALESCE(t.secondname, ''), ' ', t.lastname) as teacher_name
                     FROM tblsubjects s 
                     JOIN tblclasses c ON s.class_id = c.id 
                     LEFT JOIN tblstreams st ON s.stream_id = st.id
                     LEFT JOIN tblteachers t ON s.teacher_id = t.id 
                     WHERE s.id = :subject_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":subject_id", $subject_id, PDO::PARAM_INT);
            $stmt->execute();
            $subject = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'success' => true, 
                'message' => 'Subject updated successfully',
                'subject' => $subject
            ];
        } else {
            return ['success' => false, 'message' => 'Failed to update subject'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}
// Function to delete subject
function deleteSubject($db, $school_id) {
    $subject_id = intval($_POST['subject_id']);
    
    // Check if subject exists
    $check_query = "SELECT id FROM tblsubjects WHERE id = :subject_id AND school_id = :school_id";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(":subject_id", $subject_id, PDO::PARAM_INT);
    $check_stmt->bindParam(":school_id", $school_id, PDO::PARAM_INT);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() === 0) {
        return ['success' => false, 'message' => 'Subject not found'];
    }
    
    try {
        // Delete subject
        $query = "DELETE FROM tblsubjects WHERE id = :subject_id AND school_id = :school_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":subject_id", $subject_id, PDO::PARAM_INT);
        $stmt->bindParam(":school_id", $school_id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Subject deleted successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to delete subject'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Fetch initial data for the page
try {
    // Fetch classes for current academic level
    $classes_query = "SELECT id, class_level FROM tblclasses WHERE school_id = :school_id AND academic_level = :academic_level ORDER BY class_level";
    $classes_stmt = $db->prepare($classes_query);
    $classes_stmt->bindParam(":school_id", $school_id, PDO::PARAM_INT);
    $classes_stmt->bindParam(":academic_level", $current_level);
    $classes_stmt->execute();
    $classes = $classes_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch teachers with full name (firstname + secondname + lastname)
    $teachers_query = "SELECT id, firstname, secondname, lastname FROM tblteachers WHERE school_id = :school_id ORDER BY firstname, lastname";
    $teachers_stmt = $db->prepare($teachers_query);
    $teachers_stmt->bindParam(":school_id", $school_id, PDO::PARAM_INT);
    $teachers_stmt->execute();
    $teachers = $teachers_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Initial subjects data (first page)
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $class_filter = isset($_GET['class_filter']) ? intval($_GET['class_filter']) : '';
    $stream_filter = isset($_GET['stream_filter']) ? intval($_GET['stream_filter']) : '';
    
} catch (PDOException $e) {
    error_log("Subjects page data fetch error: " . $e->getMessage());
    $classes = [];
    $teachers = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EduScore - Subjects Management</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="icon" type="image/png" href="images/logo.png" />
<link rel="apple-touch-icon" href="images/logo.png">
    <style>
        :root {
            --primary-blue: #1e3a8a;
            --secondary-blue: #2563eb;
            --light-blue: #dbeafe;
            --accent-yellow: #fbbf24;
            --dark-blue: #1e3a8a;
            --success-green: #10b981;
            --warning-orange: #f59e0b;
            --error-red: #ef4444;
            --text-dark: #1f2937;
            --text-light: #6b7280;
            --bg-light: #f9fafb;
            --bg-white: #ffffff;
            --border-color: #e5e7eb;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            --border-radius: 12px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --sidebar-width: 280px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            background: var(--bg-light);
            color: var(--text-dark);
            min-height: 100vh;
            display: flex;
        }

        /* Main Content Layout */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            transition: margin-left 0.3s ease;
            position: relative;
            padding: 100px 2rem 2rem;
        }

        @media (max-width: 992px) {
            .main-content {
                margin-left: 0;
                padding: 100px 1rem 1rem;
            }
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Page Header */
        .page-header {
            background: var(--bg-white);
            border-radius: var(--border-radius);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
            border-left: 4px solid var(--primary-blue);
            position: relative;
        }

        .page-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 2px;
            background: linear-gradient(90deg, var(--accent-yellow), transparent);
        }

        .subjects-page-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.5rem;
        }

        .subjects-page-title i {
            color: var(--primary-blue);
        }

        .page-description {
            color: var(--text-light);
            font-size: 1rem;
        }

        /* Stats Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--bg-white);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: var(--transition);
            border-top: 3px solid var(--primary-blue);
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .stat-icon.total { background: var(--light-blue); color: var(--primary-blue); }
        .stat-icon.compulsory { background: #d1fae5; color: var(--success-green); }
        .stat-icon.optional { background: #fef3c7; color: var(--warning-orange); }
        .stat-icon.classes { background: #f3f4f6; color: var(--text-light); }

        .stat-content {
            flex: 1;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-dark);
            line-height: 1.2;
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--text-light);
        }

        /* Action Bar */
        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            gap: 1rem;
            flex-wrap: wrap;
            background: var(--bg-white);
            padding: 1rem 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        .subjects-search-box {
            position: relative;
            flex: 1;
            max-width: 400px;
            min-width: 250px;
        }

        .subjects-search-box input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            font-size: 0.9rem;
            transition: var(--transition);
            background: var(--bg-white);
        }

        .subjects-search-box input:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .subjects-search-box i {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
        }

        .filters {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .filter-select {
            padding: 0.75rem 2rem 0.75rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            font-size: 0.9rem;
            background: var(--bg-white);
            cursor: pointer;
            transition: var(--transition);
            min-width: 150px;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.75rem center;
            background-repeat: no-repeat;
            background-size: 16px 12px;
        }

        .filter-select:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .filter-select:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: var(--shadow);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .btn-outline {
            background: var(--light-blue);
            color: var(--primary-blue);
            border: 1px solid rgba(37, 99, 235, 0.2);
        }

        .btn-outline:hover {
            background: var(--secondary-blue);
            color: white;
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--error-red), #b91c1c);
            color: white;
        }

        .btn-danger:hover {
            background: linear-gradient(135deg, #dc2626, #991b1b);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(239, 68, 68, 0.35);
        }

        /* Table Styles */
        .table-container {
            background: var(--bg-white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            margin-bottom: 2rem;
            min-height: 400px;
        }

        .table-responsive {
            overflow-x: auto;
        }

        .subjects-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }

        .subjects-table th {
            background: var(--primary-blue);
            padding: 1rem 1.5rem;
            text-align: left;
            font-weight: 600;
            color: white;
            border-bottom: 2px solid var(--accent-yellow);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .subjects-table th i {
            margin-right: 0.5rem;
        }

        .subjects-table td {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.9rem;
        }

        .subjects-table tr:last-child td {
            border-bottom: none;
        }

        .subjects-table tbody tr:hover {
            background: var(--bg-light);
        }

        .subject-name {
            font-weight: 600;
            color: var(--text-dark);
        }

        /* Subject Type Badges */
        .subject-type-badge {
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            display: inline-block;
            white-space: nowrap;
        }

        .subject-type-compulsory {
            background: #d1fae5;
            color: var(--success-green);
            border: 1px solid #a7f3d0;
        }

        .subject-type-optional {
            background: #fef3c7;
            color: var(--warning-orange);
            border: 1px solid #fde68a;
        }

        /* Alias Badge */
        .alias-badge {
            background: var(--light-blue);
            color: var(--primary-blue);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
            display: inline-block;
            border: 1px solid rgba(37, 99, 235, 0.2);
        }

        /* Actions */
        .actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .btn-icon {
            width: 32px;
            height: 32px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
        }

        .btn-edit {
            background: var(--light-blue);
            color: var(--primary-blue);
        }

        .btn-edit:hover {
            background: var(--secondary-blue);
            color: white;
        }

        .btn-delete {
            background: #fef2f2;
            color: var(--error-red);
        }

        .btn-delete:hover {
            background: var(--error-red);
            color: white;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-light);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
            color: var(--primary-blue);
        }

        .empty-state h3 {
            font-size: 1.25rem;
            margin-bottom: 0.5rem;
            color: var(--text-dark);
        }

        .empty-state p {
            margin-bottom: 1.5rem;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 1rem;
            margin-top: 2rem;
        }

        .pagination-btn {
            padding: 0.5rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            background: var(--bg-white);
            color: var(--text-dark);
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: var(--shadow);
        }

        .pagination-btn:hover:not(:disabled) {
            background: var(--primary-blue);
            color: white;
            border-color: var(--primary-blue);
            transform: translateY(-2px);
        }

        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .page-info {
            color: var(--text-light);
            font-size: 0.9rem;
        }

        /* Table Info */
        .table-info {
            color: var(--text-light);
            font-size: 0.9rem;
            margin-bottom: 1rem;
            padding: 0 1.5rem;
        }

        /* Loading Overlay */
        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.8);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 100;
            border-radius: 16px;
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 3px solid var(--light-blue);
            border-top-color: var(--primary-blue);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .modal.active {
            display: flex;
            animation: fadeIn 0.3s ease;
        }

        .modal-content {
            background: var(--bg-white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-xl);
            width: 100%;
            max-width: 700px;
            max-height: 90vh;
            overflow-y: auto;
            animation: slideUp 0.3s ease;
            border-top: 4px solid var(--accent-yellow);
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--primary-blue);
            color: white;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .modal-close {
            background: rgba(255, 255, 255, 0.1);
            border: none;
            font-size: 1.25rem;
            color: white;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 6px;
            transition: var(--transition);
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-close:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-dark);
            font-size: 0.9rem;
        }

        .form-label.required::after {
            content: '*';
            color: var(--error-red);
            margin-left: 0.25rem;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            font-size: 0.9rem;
            transition: var(--transition);
            background: var(--bg-white);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .modal-footer {
            padding: 1.5rem 2rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(30, 58, 138, 0.35);
        }

        .btn-outline {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border: 2px solid #e2e8f0;
            color: var(--text-dark);
        }

        .btn-outline:hover {
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            border-color: #cbd5e1;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(148, 163, 184, 0.15);
        }

        /* Toast Notifications */
        .toast-container {
            position: fixed;
            top: 100px;
            right: 2rem;
            z-index: 3000;
            max-width: 400px;
        }

        .toast {
            background: var(--bg-white);
            border-radius: var(--border-radius);
            padding: 1rem 1.5rem;
            margin-bottom: 1rem;
            box-shadow: var(--shadow-lg);
            border-left: 4px solid var(--success-green);
            display: flex;
            align-items: center;
            gap: 1rem;
            animation: slideInRight 0.3s ease;
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(100%);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .toast.error {
            border-left-color: var(--error-red);
        }

        .toast.warning {
            border-left-color: var(--warning-orange);
        }

        .toast.info {
            border-left-color: var(--secondary-blue);
        }

        .toast-icon {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.8rem;
        }

        .toast.success .toast-icon {
            background: var(--success-green);
        }

        .toast.error .toast-icon {
            background: var(--error-red);
        }

        .toast.warning .toast-icon {
            background: var(--warning-orange);
        }

        .toast.info .toast-icon {
            background: var(--secondary-blue);
        }

        .toast-content {
            flex: 1;
        }

        .toast-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .toast-message {
            font-size: 0.9rem;
            color: var(--text-light);
        }

        /* Loading States */
        .spinner {
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .container {
                padding: 1.5rem;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .page-header {
                padding: 1.5rem;
            }
            
            .action-bar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .subjects-search-box {
                max-width: none;
            }
            
            .filters {
                width: 100%;
            }
            
            .filter-select {
                flex: 1;
            }
            
            .subjects-table th,
            .subjects-table td {
                padding: 0.75rem 1rem;
            }
            
            .modal-content {
                width: 95%;
                margin: 1rem;
            }
            
            .actions {
                flex-direction: column;
                gap: 0.25rem;
            }

            .btn-icon {
                width: 35px;
                height: 35px;
                font-size: 0.9rem;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 100px 1rem 1rem;
            }
            
            .subjects-page-title {
                font-size: 1.5rem;
            }
            
            .actions {
                flex-direction: row;
                gap: 0.25rem;
                justify-content: center;
            }
            
            .btn-icon {
                width: 32px;
                height: 32px;
                font-size: 0.8rem;
            }

            .toast-container {
                right: 1rem;
                left: 1rem;
                max-width: none;
            }
        }
    </style>
</head>
<body>

        <?php 
    // Include the trial banner if not activated
    if (!isset($school)) {
        // Fetch school data for the banner
        $stmt = $db->prepare("SELECT * FROM tblschoolinfo WHERE id = :school_id");
        $stmt->bindParam(":school_id", $_SESSION['school_id'], PDO::PARAM_INT);
        $stmt->execute();
        $school = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    include 'trial_banner.php'; 
    ?>
    <!-- Include Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Include Topbar -->
        <?php include 'includes/header.php'; ?>

        <div class="container">
            <!-- Page Header -->


            <!-- Action Bar -->
            <div class="action-bar">
                <div class="subjects-search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search subjects by name or alias..." value="<?php echo htmlspecialchars($search ?? ''); ?>">
                </div>
                
                <div class="filters">
                    <select class="filter-select" id="classFilter">
                        <option value="">All Classes</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo $class['id']; ?>" <?php echo ($class_filter ?? '') == $class['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($class['class_level']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select class="filter-select" id="streamFilter" disabled>
                        <option value="">All Streams</option>
                    </select>
                </div>
                
                <button class="btn btn-primary" id="addSubjectBtn">
                    <i class="fas fa-plus"></i>
                    Add New Subject
                </button>
            </div>

            <!-- Subjects Table -->
            <div class="table-container" id="tableContainer">
                <div class="table-info" id="tableInfo">
                    Loading subjects...
                </div>
                <div class="table-responsive">
                    <table class="subjects-table">
                        <thead>
                            <tr>
                                <th><i class="fas fa-book"></i> Subject Name</th>
                                <th><i class="fas fa-tag"></i> Alias</th>
                                <th><i class="fas fa-info-circle"></i> Type</th>
                                <th><i class="fas fa-graduation-cap"></i> Class</th>
                                <th><i class="fas fa-stream"></i> Stream</th>
                                <th><i class="fas fa-chalkboard-teacher"></i> Teacher</th>
                                <th><i class="fas fa-cogs"></i> Actions</th>
                            </tr>
                        </thead>
                        <tbody id="subjectsTableBody">
                            <!-- Subjects will be loaded here via AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <div class="pagination" id="paginationContainer">
                <!-- Pagination will be loaded here via AJAX -->
            </div>
        </div>
    </div>

    <!-- Add/Edit Subject Modal -->
    <div class="modal" id="subjectModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">
                    <i class="fas fa-book"></i>
                    <span id="modalTitle">Add New Subject</span>
                </h2>
                <button class="modal-close" id="modalClose">&times;</button>
            </div>
<div class="modal-body">
    <form id="subjectForm">
        <input type="hidden" id="subjectId" name="subject_id">
        
        <div class="form-group">
            <label class="form-label required" for="subjectName">Subject Name</label>
            <input type="text" class="form-control" id="subjectName" name="subject_name" required>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="subjectAlias">Subject Alias</label>
                <input type="text" class="form-control" id="subjectAlias" name="alias" placeholder="e.g., Maths for Mathematics">
            </div>
            
            <div class="form-group">
                <label class="form-label required" for="subjectType">Subject Type</label>
                <select class="form-control" id="subjectType" name="subject_type" required>
                    <option value="">Select Type</option>
                    <option value="Compulsory">Compulsory</option>
                    <option value="Optional">Optional</option>
                </select>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label class="form-label required" for="classSelect">Class</label>
                <select class="form-control" id="classSelect" name="class_id" required>
                    <option value="">Select Class</option>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['class_level']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="streamSelect">Stream</label>
                <select class="form-control" id="streamSelect" name="stream_id">
                    <option value="">Select Stream</option>
                </select>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="teacherSelect">Teacher</label>
                <select class="form-control" id="teacherSelect" name="teacher_id">
                    <option value="">Select Teacher</option>
                    <?php foreach ($teachers as $teacher): 
                        $full_name = trim($teacher['firstname'] . ' ' . 
                                         (!empty($teacher['secondname']) ? $teacher['secondname'] . ' ' : '') . 
                                         $teacher['lastname']);
                    ?>
                        <option value="<?php echo $teacher['id']; ?>">
                            <?php echo htmlspecialchars($full_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            

        </div>
    </form>
</div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" id="cancelBtn">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveSubjectBtn">
                    <i class="fas fa-save"></i>
                    <span id="saveBtnText">Add Subject</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal" id="deleteModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">
                    <i class="fas fa-exclamation-triangle"></i>
                    Confirm Deletion
                </h2>
                <button class="modal-close" id="deleteModalClose">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the subject "<strong id="deleteSubjectName"></strong>"?</p>
                <p class="text-muted">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" id="cancelDeleteBtn">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                    <i class="fas fa-trash"></i>
                    Delete Subject
                </button>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>
<script>
        // Global variables
        let currentSubjectId = null;
        let currentPage = <?php echo $page; ?>;
        let isLoading = false;

        // DOM Elements
        const subjectModal = document.getElementById('subjectModal');
        const deleteModal = document.getElementById('deleteModal');
        const subjectForm = document.getElementById('subjectForm');
        const searchInput = document.getElementById('searchInput');
        const classFilter = document.getElementById('classFilter');
        const streamFilter = document.getElementById('streamFilter');
        const classSelect = document.getElementById('classSelect');
        const streamSelect = document.getElementById('streamSelect');
        const subjectsTableBody = document.getElementById('subjectsTableBody');
        const paginationContainer = document.getElementById('paginationContainer');
        const tableInfo = document.getElementById('tableInfo');
        const tableContainer = document.getElementById('tableContainer');
        const toastContainer = document.getElementById('toastContainer');

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Load initial data
            loadSubjects();
            
            // Add event listeners
            const addSubjectBtn = document.getElementById('addSubjectBtn');
            const modalClose = document.getElementById('modalClose');
            const cancelBtn = document.getElementById('cancelBtn');
            const deleteModalClose = document.getElementById('deleteModalClose');
            const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
            const saveSubjectBtn = document.getElementById('saveSubjectBtn');
            const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
            
            if (addSubjectBtn) addSubjectBtn.addEventListener('click', openAddModal);
            if (modalClose) modalClose.addEventListener('click', closeModal);
            if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
            if (deleteModalClose) deleteModalClose.addEventListener('click', closeDeleteModal);
            if (cancelDeleteBtn) cancelDeleteBtn.addEventListener('click', closeDeleteModal);
            if (saveSubjectBtn) saveSubjectBtn.addEventListener('click', saveSubject);
            if (confirmDeleteBtn) confirmDeleteBtn.addEventListener('click', confirmDelete);
            
            // Search and filter functionality
            if (searchInput) searchInput.addEventListener('input', debounce(() => {
                currentPage = 1;
                loadSubjects();
            }, 500));
            
            if (classFilter) {
                classFilter.addEventListener('change', function() {
                    currentPage = 1;
                    loadStreams(this.value, streamFilter, true);
                    loadSubjects();
                });
            }
            
            if (streamFilter) {
                streamFilter.addEventListener('change', function() {
                    currentPage = 1;
                    loadSubjects();
                });
            }
            
            // Class selection change for streams
            if (classSelect) {
                classSelect.addEventListener('change', function() {
                    loadStreams(this.value, streamSelect, false);
                });
            }
            
            // Modal close on backdrop click
            if (subjectModal) {
                subjectModal.addEventListener('click', function(e) {
                    if (e.target === subjectModal) closeModal();
                });
            }
            
            if (deleteModal) {
                deleteModal.addEventListener('click', function(e) {
                    if (e.target === deleteModal) closeDeleteModal();
                });
            }
            
            // Close modals on Escape key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    closeModal();
                    closeDeleteModal();
                }
            });
            
            // Load streams for filter if class is selected
            if (classFilter && classFilter.value) {
                loadStreams(classFilter.value, streamFilter, true);
            }
        });

        // Functions
        function openAddModal() {
            const modalTitle = document.getElementById('modalTitle');
            const saveBtnText = document.getElementById('saveBtnText');
            const subjectIdElem = document.getElementById('subjectId');
            
            if (modalTitle) modalTitle.textContent = 'Add New Subject';
            if (saveBtnText) saveBtnText.textContent = 'Add Subject';
            if (subjectIdElem) subjectIdElem.value = '';
            
            if (subjectForm) subjectForm.reset();
            if (streamSelect) {
                streamSelect.innerHTML = '<option value="">Select Stream</option>';
            }
            
            if (subjectModal) {
                subjectModal.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        }

        function closeModal() {
            if (subjectModal) {
                subjectModal.classList.remove('active');
                document.body.style.overflow = '';
            }
        }

        function closeDeleteModal() {
            if (deleteModal) {
                deleteModal.classList.remove('active');
                document.body.style.overflow = '';
            }
        }

 function editSubject(subjectId) {
    currentSubjectId = subjectId;
    
    // Show loading state
    const saveBtn = document.getElementById('saveSubjectBtn');
    if (saveBtn) {
        saveBtn.innerHTML = '<div class="spinner"></div> Loading...';
        saveBtn.disabled = true;
    }
    
    fetch('subjects.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=get_subject&subject_id=${subjectId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const subject = data.subject;
            
            // Safely get elements
            const modalTitle = document.getElementById('modalTitle');
            const saveBtnText = document.getElementById('saveBtnText');
            const subjectIdElem = document.getElementById('subjectId');
            const subjectName = document.getElementById('subjectName');
            const subjectAlias = document.getElementById('subjectAlias');
            const subjectType = document.getElementById('subjectType');
            const classSelectElem = document.getElementById('classSelect');
            const teacherSelect = document.getElementById('teacherSelect');
            
            if (modalTitle) modalTitle.textContent = 'Edit Subject';
            if (saveBtnText) saveBtnText.textContent = 'Update Subject';
            if (subjectIdElem) subjectIdElem.value = subject.id;
            if (subjectName) subjectName.value = subject.subject_name || '';
            if (subjectAlias) subjectAlias.value = subject.alias || '';
            if (subjectType) subjectType.value = subject.subject_type || '';
            if (classSelectElem) classSelectElem.value = subject.class_id || '';
            
            // Handle teacher selection - convert null to empty string
            if (teacherSelect) {
                teacherSelect.value = subject.teacher_id || '';
            }
            
            // Load streams for the class
            loadStreams(subject.class_id, streamSelect, false, subject.stream_id);
            
            if (subjectModal) {
                subjectModal.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        } else {
            showToast('Error', data.message || 'Failed to load subject data', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error', 'Failed to load subject data', 'error');
    })
    .finally(() => {
        const saveBtn = document.getElementById('saveSubjectBtn');
        if (saveBtn) {
            saveBtn.innerHTML = '<i class="fas fa-save"></i> Update Subject';
            saveBtn.disabled = false;
        }
    });
}

        function deleteSubject(subjectId, subjectName) {
            currentSubjectId = subjectId;
            const deleteSubjectName = document.getElementById('deleteSubjectName');
            if (deleteSubjectName) {
                deleteSubjectName.textContent = subjectName;
            }
            if (deleteModal) {
                deleteModal.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        }

        function confirmDelete() {
            const deleteBtn = document.getElementById('confirmDeleteBtn');
            if (deleteBtn) {
                deleteBtn.innerHTML = '<div class="spinner"></div> Deleting...';
                deleteBtn.disabled = true;
            }
            
            fetch('subjects.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=delete_subject&subject_id=${currentSubjectId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Success', data.message, 'success');
                    closeDeleteModal();
                    // Reload subjects after deletion
                    loadSubjects();
                } else {
                    showToast('Error', data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error', 'Failed to delete subject', 'error');
            })
            .finally(() => {
                const deleteBtn = document.getElementById('confirmDeleteBtn');
                if (deleteBtn) {
                    deleteBtn.innerHTML = '<i class="fas fa-trash"></i> Delete Subject';
                    deleteBtn.disabled = false;
                }
            });
        }

function saveSubject() {
    const formData = new FormData(subjectForm);
    const subjectIdElem = document.getElementById('subjectId');
    const isEdit = subjectIdElem && subjectIdElem.value !== '';
    const action = isEdit ? 'update_subject' : 'add_subject';
    
    // Add action to form data
    formData.append('action', action);
    
    // Debug: Check what's in the form data
    console.log('Form Data Contents:');
    for (let pair of formData.entries()) {
        console.log(pair[0] + ': ' + pair[1]);
    }
    
    // Validate required fields
    if (!formData.get('subject_name') || !formData.get('class_id') || !formData.get('subject_type')) {
        showToast('Validation Error', 'Please fill in all required fields', 'warning');
        return;
    }
    
    // Validate subject type
    const subjectType = formData.get('subject_type');
    if (subjectType !== 'Compulsory' && subjectType !== 'Optional') {
        showToast('Validation Error', 'Subject type must be either Compulsory or Optional', 'warning');
        return;
    }
    
    const saveBtn = document.getElementById('saveSubjectBtn');
    if (saveBtn) {
        saveBtn.innerHTML = '<div class="spinner"></div> Saving...';
        saveBtn.disabled = true;
    }
    
    fetch('subjects.php', {
        method: 'POST',
        body: new URLSearchParams(formData)
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Server Response:', data);
        if (data.success) {
            showToast('Success', data.message, 'success');
            closeModal();
            // Reload subjects after save
            loadSubjects();
        } else {
            showToast('Error', data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error', 'Failed to save subject: ' + error.message, 'error');
    })
    .finally(() => {
        const saveBtn = document.getElementById('saveSubjectBtn');
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.innerHTML = isEdit ? 
                '<i class="fas fa-save"></i> Update Subject' : 
                '<i class="fas fa-save"></i> Add Subject';
        }
    });
}

        function loadStreams(classId, targetSelect, isFilter = false, selectedStreamId = null) {
            if (!targetSelect) return;
            
            if (!classId) {
                targetSelect.innerHTML = '<option value="">Select Stream</option>';
                if (isFilter) {
                    targetSelect.disabled = true;
                }
                return;
            }
            
            targetSelect.innerHTML = '<option value="">Loading...</option>';
            if (isFilter) {
                targetSelect.disabled = true;
            }
            
            fetch('subjects.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_streams&class_id=${classId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.streams.length > 0) {
                    let options = '<option value="">Select Stream</option>';
                    data.streams.forEach(stream => {
                        const selected = selectedStreamId == stream.id ? 'selected' : '';
                        options += `<option value="${stream.id}" ${selected}>${stream.stream_name}</option>`;
                    });
                    targetSelect.innerHTML = options;
                    if (isFilter) {
                        targetSelect.disabled = false;
                    }
                } else {
                    targetSelect.innerHTML = '<option value="">No streams available</option>';
                    if (isFilter) {
                        targetSelect.disabled = true;
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                targetSelect.innerHTML = '<option value="">Error loading streams</option>';
                if (isFilter) {
                    targetSelect.disabled = true;
                }
            });
        }

        function loadSubjects() {
            if (isLoading) return;
            
            isLoading = true;
            
            // Show loading state
            if (subjectsTableBody) {
                subjectsTableBody.innerHTML = `
                    <tr>
                        <td colspan="7">
                            <div class="loading-overlay">
                                <div class="loading-spinner"></div>
                            </div>
                        </td>
                    </tr>
                `;
            }
            
            const search = searchInput ? searchInput.value : '';
            const classId = classFilter ? classFilter.value : '';
            const streamId = streamFilter ? streamFilter.value : '';
            
            fetch('subjects.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_subjects&page=${currentPage}&search=${encodeURIComponent(search)}&class_filter=${classId}&stream_filter=${streamId}`
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    updateSubjectsTable(data.subjects);
                    updatePagination(data.total_pages, data.current_page, data.total_subjects);
                    updateTableInfo(data.total_subjects, data.current_page, data.total_pages);
                } else {
                    showToast('Error', 'Failed to load subjects: ' + (data.message || 'Unknown error'), 'error');
                    if (subjectsTableBody) {
                        subjectsTableBody.innerHTML = `
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        <h3>Error Loading Subjects</h3>
                                        <p>Please try again</p>
                                    </div>
                                </td>
                            </tr>
                        `;
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error', 'Failed to load subjects: ' + error.message, 'error');
                if (subjectsTableBody) {
                    subjectsTableBody.innerHTML = `
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <h3>Error Loading Subjects</h3>
                                    <p>Please try again</p>
                                </div>
                            </td>
                        </tr>
                    `;
                }
            })
            .finally(() => {
                isLoading = false;
            });
        }

        function updateSubjectsTable(subjects) {
            if (!subjectsTableBody) return;
            
            if (!subjects || subjects.length === 0) {
                subjectsTableBody.innerHTML = `
                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                <i class="fas fa-book"></i>
                                <h3>No Subjects Found</h3>
                                <p>Get started by adding your first subject</p>
                            </div>
                        </td>
                    </tr>
                `;
                return;
            }
            
            let html = '';
            subjects.forEach(subject => {
                // Safely handle null values
                const subjectName = subject.subject_name || '';
                const alias = subject.alias || '';
                const subjectType = subject.subject_type || '';
                const classLevel = subject.class_level || 'N/A';
                const streamName = subject.stream_name || 'N/A';
                const teacherName = subject.teacher_name || 'Not Assigned';
                
                // Determine badge class for subject type
                const typeClass = subjectType === 'Compulsory' ? 'subject-type-compulsory' : 
                                 subjectType === 'Optional' ? 'subject-type-optional' : '';
                
                html += `
                    <tr>
                        <td class="subject-name">${escapeHtml(subjectName)}</td>
                        <td>
                            ${alias ? `<span class="alias-badge">${escapeHtml(alias)}</span>` : '-'}
                        </td>
                        <td>
                            ${subjectType ? `<span class="subject-type-badge ${typeClass}">${escapeHtml(subjectType)}</span>` : '-'}
                        </td>
                        <td>${escapeHtml(classLevel)}</td>
                        <td>${escapeHtml(streamName)}</td>
                        <td>${escapeHtml(teacherName)}</td>
                        <td>
                            <div class="actions">
                                <button class="btn-icon btn-edit" onclick="editSubject(${subject.id})" title="Edit Subject">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn-icon btn-delete" onclick="deleteSubject(${subject.id}, '${escapeHtml(subjectName)}')" title="Delete Subject">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            });
            
            subjectsTableBody.innerHTML = html;
        }

        function updatePagination(totalPages, currentPage, totalSubjects) {
            if (!paginationContainer) return;
            
            if (totalPages <= 1) {
                paginationContainer.innerHTML = '';
                return;
            }
            
            let html = `
                <button class="pagination-btn" onclick="changePage(${Math.max(1, currentPage - 1)})" ${currentPage <= 1 ? 'disabled' : ''}>
                    <i class="fas fa-chevron-left"></i>
                    Previous
                </button>
                
                <span class="page-info">
                    Page ${currentPage} of ${totalPages}
                </span>
                
                <button class="pagination-btn" onclick="changePage(${Math.min(totalPages, currentPage + 1)})" ${currentPage >= totalPages ? 'disabled' : ''}>
                    Next
                    <i class="fas fa-chevron-right"></i>
                </button>
            `;
            
            paginationContainer.innerHTML = html;
        }

        function updateTableInfo(totalSubjects, currentPage, totalPages) {
            if (!tableInfo) return;
            
            if (totalSubjects === 0) {
                tableInfo.innerHTML = 'No subjects found';
                return;
            }
            
            const start = ((currentPage - 1) * 10) + 1;
            const end = Math.min(currentPage * 10, totalSubjects);
            tableInfo.innerHTML = `Showing ${start} to ${end} of ${totalSubjects} subjects`;
        }

        function changePage(page) {
            currentPage = page;
            loadSubjects();
            // Scroll to top of table
            if (tableContainer) {
                tableContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }

        function showToast(title, message, type = 'success') {
            if (!toastContainer) return;
            
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            
            let icon = 'check';
            if (type === 'error') icon = 'exclamation-triangle';
            if (type === 'warning') icon = 'exclamation-circle';
            if (type === 'info') icon = 'info-circle';
            
            toast.innerHTML = `
                <div class="toast-icon">
                    <i class="fas fa-${icon}"></i>
                </div>
                <div class="toast-content">
                    <div class="toast-title">${title}</div>
                    <div class="toast-message">${message}</div>
                </div>
            `;
            
            toastContainer.appendChild(toast);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(100%)';
                toast.style.transition = 'all 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }, 5000);
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
    </script>
</body>

</body>
</html>
