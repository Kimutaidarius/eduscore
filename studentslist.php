<?php
// --------------------------------------------------
// Debug (disable in production)
// --------------------------------------------------
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/config.php';
require_once 'includes/session_timeout.php'; 


// --------------------------------------------------
// AUTH CHECK (AJAX vs HTML SAFE)
// --------------------------------------------------
$isAjax = ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']));

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

// --------------------------------------------------
// Session values
// --------------------------------------------------
$school_id  = (int) $_SESSION['school_id'];
$teacher_id = (int) $_SESSION['teacher_id'];

// --------------------------------------------------
// AJAX HANDLER
// --------------------------------------------------
if ($isAjax) {
    header('Content-Type: application/json');
    
    try {
        // GET STUDENTS LIST ACTION
        if ($_POST['action'] === 'get_students') {
            $class_id  = !empty($_POST['class_id'])  ? (int) $_POST['class_id']  : null;
            $stream_id = !empty($_POST['stream_id']) ? (int) $_POST['stream_id'] : null;
            $page      = max(1, (int) ($_POST['page'] ?? 1));
            $search    = trim($_POST['search'] ?? '');
            
            $limit  = 10;
            $offset = ($page - 1) * $limit;
            
            // SQL QUERY: Use correct column names that exist in the database
            $sql = "
                SELECT SQL_CALC_FOUND_ROWS
                    s.id,
                    s.FirstName,
                    s.SecondName,
                    s.LastName,
                    s.AdmNo,
                    s.assessment_no,
                    s.admission_date,
                    s.Class,
                    s.StreamId,
                    s.Nemis,
                    s.Gender,
                    s.GuardianName,
                    s.GuardianRelationship,
                    s.GuardianPhone,
                    s.BoardingStatus,
                    s.ProfilePic,
                    s.class_id,
                    s.school_id,
                    s.status,
                    s.academic_year,
                    c.class_level,
                    st.stream_name
                FROM tblstudents s
                LEFT JOIN tblclasses c  ON c.id  = s.class_id
                LEFT JOIN tblstreams st ON st.id = s.StreamId
                WHERE s.school_id = :school_id
                  AND s.status = 'Active'
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
                    s.FirstName LIKE :search OR
                    s.LastName LIKE :search OR
                    s.AdmNo LIKE :search
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
            exit;
        }
        
        // DELETE STUDENT ACTION
        if ($_POST['action'] === 'delete_student') {
            $student_id = (int) ($_POST['student_id'] ?? 0);
            
            if ($student_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid student ID']);
                exit;
            }
            
            // First, check if the student belongs to this school and is active
            $stmt = $db->prepare("SELECT id FROM tblstudents WHERE id = :student_id AND school_id = :school_id AND status = 'Active'");
            $stmt->execute([
                ':student_id' => $student_id,
                ':school_id' => $school_id
            ]);
            
            $student = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$student) {
                echo json_encode(['success' => false, 'message' => 'Student not found or unauthorized']);
                exit;
            }
            
            // Instead of actual deletion, update status to 'Deleted' (soft delete)
            $stmt = $db->prepare("UPDATE tblstudents SET status = 'Deleted', updated_at = NOW() WHERE id = :student_id");
            $stmt->execute([':student_id' => $student_id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Student deleted successfully'
            ]);
            exit;
        }
        
        // GET STUDENT DETAILS FOR EDITING
        if ($_POST['action'] === 'get_student_details') {
            $student_id = (int) ($_POST['student_id'] ?? 0);
            
            if ($student_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid student ID']);
                exit;
            }
            
            // FIXED SQL QUERY: Only use columns that exist in tblstudents table
            $sql = "
                SELECT 
                    s.id,
                    s.FirstName,
                    s.SecondName,
                    s.LastName,
                    s.AdmNo,
                    s.assessment_no,
                    s.admission_date,
                    s.Class,
                    s.StreamId,
                    s.Nemis,
                    s.Gender,
                    s.GuardianName,
                    s.GuardianRelationship,
                    s.GuardianPhone,
                    s.BoardingStatus,
                    s.ProfilePic,
                    s.class_id,
                    s.school_id,
                    s.status,
                    s.academic_year,
                    c.class_level,
                    st.stream_name
                FROM tblstudents s
                LEFT JOIN tblclasses c ON c.id = s.class_id
                LEFT JOIN tblstreams st ON st.id = s.StreamId
                WHERE s.id = :student_id 
                AND s.school_id = :school_id
                AND s.status = 'Active'
            ";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':student_id' => $student_id,
                ':school_id' => $school_id
            ]);
            
            $student = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$student) {
                echo json_encode(['success' => false, 'message' => 'Student not found']);
                exit;
            }
            
            // Also get available classes and streams for dropdowns
            $classes = $db->prepare("SELECT id, class_level FROM tblclasses WHERE school_id = :school_id ORDER BY class_level");
            $classes->execute([':school_id' => $school_id]);
            
            $streams = $db->prepare("SELECT id, stream_name FROM tblstreams WHERE school_id = :school_id ORDER BY stream_name");
            $streams->execute([':school_id' => $school_id]);
            
            echo json_encode([
                'success' => true,
                'student' => $student,
                'classes' => $classes->fetchAll(PDO::FETCH_ASSOC),
                'streams' => $streams->fetchAll(PDO::FETCH_ASSOC)
            ]);
            exit;
        }
        
        // UPDATE STUDENT ACTION
        if ($_POST['action'] === 'update_student') {
            $student_id = (int) ($_POST['student_id'] ?? 0);
            
            if ($student_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid student ID']);
                exit;
            }
            
            // Validate required fields
            $required_fields = ['first_name', 'last_name', 'admission_no', 'gender', 'class_id'];
            foreach ($required_fields as $field) {
                if (empty($_POST[$field])) {
                    echo json_encode(['success' => false, 'message' => "Field '$field' is required"]);
                    exit;
                }
            }
            
            // Check if student belongs to this school
            $stmt = $db->prepare("SELECT id FROM tblstudents WHERE id = :student_id AND school_id = :school_id AND status = 'Active'");
            $stmt->execute([
                ':student_id' => $student_id,
                ':school_id' => $school_id
            ]);
            
            if (!$stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Student not found or unauthorized']);
                exit;
            }
            
            // Check if admission number already exists (excluding current student)
            $stmt = $db->prepare("SELECT id FROM tblstudents WHERE AdmNo = :admission_no AND id != :student_id AND school_id = :school_id");
            $stmt->execute([
                ':admission_no' => $_POST['admission_no'],
                ':student_id' => $student_id,
                ':school_id' => $school_id
            ]);
            
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Admission number already exists']);
                exit;
            }
            
            // Update student information - only update columns that exist in the database
            // Based on your tblstudents structure, we have these columns:
            // id, school_id, class_id, FirstName, SecondName, LastName, ProfilePic, AdmNo, 
            // assessment_no, admission_date, Class, StreamId, Nemis, Gender, GuardianName, 
            // GuardianRelationship, GuardianPhone, BoardingStatus, Status, academic_year
            
            $update_sql = "
                UPDATE tblstudents SET
                    FirstName = :first_name,
                    SecondName = :second_name,
                    LastName = :last_name,
                    AdmNo = :admission_no,
                    Gender = :gender,
                    class_id = :class_id,
                    StreamId = :stream_id,
                    GuardianPhone = :guardian_phone,
                    GuardianName = :guardian_name,
                    updated_at = NOW()
                WHERE id = :student_id
            ";
            
            $stmt = $db->prepare($update_sql);
            
            $result = $stmt->execute([
                ':first_name' => $_POST['first_name'],
                ':second_name' => $_POST['second_name'] ?? null,
                ':last_name' => $_POST['last_name'],
                ':admission_no' => $_POST['admission_no'],
                ':gender' => $_POST['gender'],
                ':class_id' => (int)$_POST['class_id'],
                ':stream_id' => !empty($_POST['stream_id']) ? (int)$_POST['stream_id'] : null,
                ':guardian_phone' => $_POST['guardian_phone'] ?? null,
                ':guardian_name' => $_POST['guardian_name'] ?? null,
                ':student_id' => $student_id
            ]);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Student updated successfully'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to update student'
                ]);
            }
            exit;
        }
        
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;
        
    } catch (PDOException $e) {
        error_log("Studentlist error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
        exit;
    }
}

// --------------------------------------------------
// NORMAL HTML PAGE DATA
// --------------------------------------------------
try {
    $stmt = $db->prepare("
        SELECT id, class_level
        FROM tblclasses
        WHERE school_id = :school_id
        ORDER BY class_level
    ");
    $stmt->execute([':school_id' => $school_id]);
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Class load error: " . $e->getMessage());
    $classes = [];
}

// HTML continues below 👇
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduScore - Student Management</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="images/logo.png" />
    <link rel="apple-touch-icon" href="images/logo.png">
    <link rel="stylesheet" href="assets/banner/banner.css">
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
        }

        .main-content {
            margin-left: 280px;
            min-height: 100vh;
            padding: 100px 2rem 2rem;
            transition: margin-left 0.3s ease;
        }

        @media (max-width: 992px) {
            .main-content {
                margin-left: 0;
                padding: 100px 1rem 1rem;
            }
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

        .students-page-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .students-page-title i {
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
        .stat-icon.male { background: #dbeafe; color: #1e40af; }
        .stat-icon.female { background: #fce7f3; color: #be185d; }

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

        .students-search-box {
            position: relative;
            flex: 1;
            max-width: 400px;
            min-width: 250px;
        }

        .students-search-input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            font-size: 0.9rem;
            transition: var(--transition);
            background: var(--bg-white);
        }

        .students-search-input:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .students-search-icon {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
        }

        .action-buttons {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.75rem 1.25rem;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
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

        .btn-secondary {
            background: var(--light-blue);
            color: var(--primary-blue);
        }

        .btn-secondary:hover {
            background: var(--secondary-blue);
            color: white;
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success-green), #059669);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid var(--primary-blue);
            color: var(--primary-blue);
        }

        .btn-outline:hover {
            background: var(--primary-blue);
            color: white;
        }

        /* Filter Selects */
        .filter-selects {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            background: var(--bg-white);
            padding: 20px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        .filter-selects label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: var(--text-dark);
        }

        .filter-selects select {
            width: 200px;
            padding: 10px 14px;
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
            background: var(--bg-white);
            color: var(--text-dark);
            font-weight: 500;
            box-shadow: var(--shadow);
            transition: var(--transition);
            cursor: pointer;
        }

        .filter-selects select:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 10px var(--primary-blue), var(--shadow);
        }

        .filter-selects select:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Students Table */
        .students-table-container {
            background: var(--bg-white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            min-height: 400px;
        }

        .table-responsive {
            overflow-x: auto;
        }

        .students-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }

        .students-table th {
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

        .students-table th i {
            margin-right: 0.5rem;
        }

        .students-table td {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.9rem;
        }

        .students-table tr:last-child td {
            border-bottom: none;
        }

        .students-table tr:hover {
            background: var(--bg-light);
        }

        /* Actions */
        .actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .action-btn-small {
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

        .edit-btn {
            background: var(--light-blue);
            color: var(--primary-blue);
        }

        .edit-btn:hover {
            background: var(--secondary-blue);
            color: white;
        }

        .delete-btn {
            background: #fef2f2;
            color: var(--error-red);
        }

        .delete-btn:hover {
            background: var(--error-red);
            color: white;
        }

        /* Student Info */
        .student-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            cursor: pointer;
            transition: var(--transition);
            padding: 0.5rem;
            border-radius: 8px;
        }

        .student-info:hover {
            background: var(--light-blue);
            transform: translateX(4px);
        }

        .student-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1.1rem;
            overflow: hidden;
            flex-shrink: 0;
        }

        .student-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .student-details {
            min-width: 0;
        }

        .student-details h4 {
            font-weight: 600;
            margin-bottom: 0.125rem;
            color: var(--text-dark);
            font-size: 1rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 180px;
        }

        .student-details p {
            font-size: 0.85rem;
            color: var(--text-light);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 180px;
        }

        /* Badges */
        .admission-no {
            background: var(--light-blue);
            color: var(--primary-blue);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
        }

        .gender-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: capitalize;
            display: inline-block;
        }

        .gender-male {
            background: #dbeafe;
            color: #1e40af;
        }

        .gender-female {
            background: #fce7f3;
            color: #be185d;
        }

        .gender-other {
            background: #f3e8ff;
            color: #7c3aed;
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
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
            justify-content: center;
        }

        .pagination button.active {
            background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
            color: white;
        }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            padding: 1rem;
            backdrop-filter: blur(4px);
        }

        .modal-overlay.active {
            display: flex;
            animation: fadeIn 0.3s ease;
        }

        .modal {
            background: var(--bg-white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-xl);
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            animation: slideUp 0.3s ease;
            border-top: 4px solid var(--accent-yellow);
        }

        .modal-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
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

        .close-modal {
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

        .close-modal:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .modal-body {
            padding: 2rem;
        }

        .modal-footer {
            padding: 1.5rem 2rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }

        /* Delete Confirmation */
        .delete-confirmation {
            text-align: center;
        }

        .delete-icon {
            font-size: 3rem;
            color: var(--error-red);
            margin-bottom: 1rem;
        }

        .delete-message {
            margin-bottom: 1.5rem;
            color: var(--text-dark);
            line-height: 1.6;
        }

        .student-name-highlight {
            font-weight: 700;
            color: var(--error-red);
            background: #fee;
            padding: 0.125rem 0.5rem;
            border-radius: 4px;
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

        /* Loading Spinner */
        .loading-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 4rem 2rem;
            text-align: center;
            animation: fadeIn 0.5s ease;
        }

        .modern-spinner {
            position: relative;
            width: 120px;
            height: 120px;
            margin-bottom: 2rem;
        }

        .spinner-container {
            position: relative;
            width: 100%;
            height: 100%;
        }

        .spinner-ring {
            position: absolute;
            border-radius: 50%;
            border: 3px solid transparent;
            animation: rotate 2s linear infinite;
        }

        .spinner-ring:nth-child(1) {
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            border-top-color: var(--primary-blue);
            animation-delay: 0s;
            animation-duration: 2s;
        }

        .spinner-ring:nth-child(2) {
            top: 10px;
            left: 10px;
            right: 10px;
            bottom: 10px;
            border-top-color: var(--secondary-blue);
            animation-delay: 0.1s;
            animation-duration: 1.8s;
        }

        .spinner-ring:nth-child(3) {
            top: 20px;
            left: 20px;
            right: 20px;
            bottom: 20px;
            border-top-color: var(--accent-yellow);
            animation-delay: 0.2s;
            animation-duration: 1.6s;
        }

        .spinner-center {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            box-shadow: 0 4px 15px rgba(30, 58, 138, 0.2);
        }

        .loading-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.75rem;
        }

        .loading-text {
            color: var(--text-light);
            font-size: 0.95rem;
            margin-bottom: 1.5rem;
            line-height: 1.5;
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
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

        @keyframes rotate {
            0% {
                transform: rotate(0deg);
            }
            100% {
                transform: rotate(360deg);
            }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .main-content {
                padding: 100px 1rem 1rem;
            }

            .page-header {
                padding: 1.5rem;
            }

            .action-bar {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-selects {
                flex-direction: column;
            }

            .filter-selects select {
                width: 100%;
            }

            .modal {
                max-width: 95%;
                margin: 1rem;
            }

            .modal-header,
            .modal-body,
            .modal-footer {
                padding: 1.25rem;
            }

            .toast-container {
                right: 1rem;
                left: 1rem;
                max-width: none;
            }
        }
        
        .stat-value .fa-spinner {
            font-size: 0.8em;
            color: var(--text-light);
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .fa-spin {
            animation: spin 1s linear infinite;
        }
        /* Edit Modal Form Styles */
.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 1rem;
}

.form-group {
    margin-bottom: 1rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: var(--text-dark);
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    font-size: 0.9rem;
    transition: var(--transition);
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--primary-blue);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.form-group.full-width {
    grid-column: span 2;
}

.required::after {
    content: " *";
    color: var(--error-red);
}

/* Spinner for edit buttons */
.fa-spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
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
    
    <!-- Include Header -->
    <?php include 'includes/header.php'; ?>

    <!-- Include Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="students-page-title">
                <i class="fas fa-user-graduate"></i>
                Student Management
            </h1>
            <p class="page-description">View and manage all student information in your school</p>
        </div>

        <!-- Stats Cards -->
        <div class="stats-container" id="statsContainer">
            <div class="stat-card">
                <div class="stat-icon total">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value" id="totalStudentsCount">0</div>
                    <div class="stat-label">Total Students</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon male">
                    <i class="fas fa-male"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value" id="maleStudentsCount">0</div>
                    <div class="stat-label">Male Students</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon female">
                    <i class="fas fa-female"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value" id="femaleStudentsCount">0</div>
                    <div class="stat-label">Female Students</div>
                </div>
            </div>
        </div>

        <!-- Action Bar -->
<!-- Action Bar -->
<div class="action-bar">
    <div class="students-search-box">
        <i class="fas fa-search students-search-icon"></i>
        <input type="text" class="students-search-input" id="searchInput" placeholder="Search students by name or admission number...">
    </div>
<div class="action-buttons">
    <button class="btn btn-danger" id="exportPdfBtn">
        <i class="fas fa-file-pdf"></i>
        Export to PDF
    </button>
    
    <!-- Dropdown for additional export options -->
    <div class="dropdown" style="position: relative; display: inline-block;">
        <button class="btn btn-secondary" id="exportDropdownBtn">
            <i class="fas fa-download"></i>
            More Export Options
            <i class="fas fa-caret-down" style="margin-left: 5px;"></i>
        </button>
        <div class="dropdown-content" style="
            display: none;
            position: absolute;
            background: white;
            min-width: 180px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            border-radius: 8px;
            z-index: 1000;
            margin-top: 5px;
        ">
            <a href="#" id="exportExcelLink" style="
                display: block;
                padding: 10px 15px;
                color: #333;
                text-decoration: none;
                border-bottom: 1px solid #eee;
            " onmouseover="this.style.background='#f5f5f5'" onmouseout="this.style.background='white'">
                <i class="fas fa-file-excel" style="color: #217346; margin-right: 8px;"></i>
                Excel (.csv)
            </a>
            <a href="#" id="printLink" style="
                display: block;
                padding: 10px 15px;
                color: #333;
                text-decoration: none;
            " onmouseover="this.style.background='#f5f5f5'" onmouseout="this.style.background='white'">
                <i class="fas fa-print" style="color: #666; margin-right: 8px;"></i>
                Print Preview
            </a>
        </div>
    </div>
</div>
</div>

        <!-- Filter Selects -->
        <div class="filter-selects">
            <div>
                <label for="classFilter">Filter by Class</label>
                <select class="filter-select" id="classFilter">
                    <option value="">-- Loading classes... --</option>
                </select>
            </div>
            <div>
                <label for="streamFilter">Filter by Stream</label>
                <select class="filter-select" id="streamFilter" disabled>
                    <option value="">-- All Streams --</option>
                </select>
            </div>
        </div>

        <!-- Students Table -->
        <div class="students-table-container">
            <div class="table-responsive">
                <table class="students-table" id="studentsTable">
                    <thead>
                        <tr>
                            <th><i class="fas fa-user"></i> Student</th>
                            <th><i class="fas fa-id-card"></i> Admission No</th>
                            <th><i class="fas fa-graduation-cap"></i> Class</th>
                            <th><i class="fas fa-stream"></i> Stream</th>
                            <th><i class="fas fa-venus-mars"></i> Gender</th>
                            <th><i class="fas fa-phone"></i> Contact</th>
                            <th><i class="fas fa-user-friends"></i> Guardian</th>
                            <th><i class="fas fa-cogs"></i> Actions</th>
                        </tr>
                    </thead>
                    <tbody id="studentsTableBody">
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <i class="fas fa-user-graduate"></i>
                                    <h3>No Students Found</h3>
                                    <p>Select a class to view students or start adding students to the system</p>
                                    <button class="btn btn-primary" onclick="resetFilters()" style="margin-top: 1rem;">
                                        <i class="fas fa-redo"></i> Clear Filters
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <div class="pagination" id="paginationContainer" style="display: none;">
            <button class="btn btn-secondary" id="prevPageBtn" disabled>
                <i class="fas fa-chevron-left"></i> Previous
            </button>
            
            <span style="display: flex; gap: 5px;" id="pageNumbers"></span>
            
            <button class="btn btn-secondary" id="nextPageBtn" disabled>
                Next <i class="fas fa-chevron-right"></i>
            </button>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal delete-modal">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-exclamation-triangle"></i>
                    Confirm Deletion
                </h3>
                <button class="close-modal" id="closeDeleteModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="delete-confirmation">
                    <div class="delete-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <p class="delete-message">
                        Are you sure you want to delete student 
                        <span class="student-name-highlight" id="deleteStudentName">[Student Name]</span>?
                        This action cannot be undone and all associated data will be permanently removed.
                    </p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="cancelDeleteBtn">
                    Cancel
                </button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                    <i class="fas fa-trash"></i> Delete Student
                </button>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- JavaScript -->
<script>
    // Global variables
    let currentPage = 1;
    let totalPages = 1;
    let totalStudents = 0;
    let currentClassId = '';
    let currentStreamId = '';
    let currentSearch = '';
    let debounceTimer;
    let currentStudentToDelete = null;
    let isSubmitting = false;
    let currentStudentToEdit = null;

    // DOM Elements
    const classFilter = document.getElementById('classFilter');
    const streamFilter = document.getElementById('streamFilter');
    const searchInput = document.getElementById('searchInput');
    const studentsTableBody = document.getElementById('studentsTableBody');
    const paginationContainer = document.getElementById('paginationContainer');
    const prevPageBtn = document.getElementById('prevPageBtn');
    const nextPageBtn = document.getElementById('nextPageBtn');
    const pageNumbers = document.getElementById('pageNumbers');
    const exportPdfBtn = document.getElementById('exportPdfBtn');
    const toastContainer = document.getElementById('toastContainer');
    const deleteModal = document.getElementById('deleteModal');
    const closeDeleteModal = document.getElementById('closeDeleteModal');
    const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    const deleteStudentName = document.getElementById('deleteStudentName');
    const exportDropdownBtn = document.getElementById('exportDropdownBtn');
    const exportExcelLink = document.getElementById('exportExcelLink');
    const printLink = document.getElementById('printLink');

    // Toast Notification Function
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
        
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(100%)';
            toast.style.transition = 'all 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    }

    // Loading States
    function showLoading(button, text) {
        if (!button) return;
        const originalHTML = button.innerHTML;
        button.dataset.originalHTML = originalHTML;
        button.innerHTML = `
            <div style="display: inline-block; width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-radius: 50%; border-top-color: white; animation: spin 1s ease-in-out infinite; margin-right: 8px;"></div>
            ${text}
        `;
        button.disabled = true;
    }
    
    function hideLoading(button) {
        if (!button || !button.dataset.originalHTML) return;
        button.innerHTML = button.dataset.originalHTML;
        button.disabled = false;
    }

    // Add CSS for spinner animation
    const style = document.createElement('style');
    style.textContent = `
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    `;
    document.head.appendChild(style);

    function escapeHtml(text) {
        if (text === null || text === undefined) return '';
        const div = document.createElement('div');
        div.textContent = text.toString();
        return div.innerHTML;
    }

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        initializePage();
        
        // Event listeners setup
        if (classFilter) {
            classFilter.addEventListener('change', function() {
                currentClassId = this.value;
                if (streamFilter) {
                    streamFilter.disabled = !currentClassId;
                }
                
                if (currentClassId) {
                    loadStreams(currentClassId);
                } else {
                    resetStreamFilter();
                }
                
                resetPaginationAndLoadStudents();
            });
        }

        if (streamFilter) {
            streamFilter.addEventListener('change', function() {
                currentStreamId = this.value;
                resetPaginationAndLoadStudents();
            });
        }

        if (searchInput) {
            searchInput.addEventListener('input', function() {
                currentSearch = this.value.trim();
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => {
                    resetPaginationAndLoadStudents();
                }, 300);
            });
        }

        if (prevPageBtn) {
            prevPageBtn.addEventListener('click', function() {
                if (currentPage > 1) {
                    currentPage--;
                    loadStudents();
                }
            });
        }

        if (nextPageBtn) {
            nextPageBtn.addEventListener('click', function() {
                if (currentPage < totalPages) {
                    currentPage++;
                    loadStudents();
                }
            });
        }

        if (exportPdfBtn) {
            exportPdfBtn.addEventListener('click', exportToPDF);
        }

        if (exportDropdownBtn) {
            exportDropdownBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                const dropdown = this.nextElementSibling;
                dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
            });
        }

        if (exportExcelLink) {
            exportExcelLink.addEventListener('click', function(e) {
                e.preventDefault();
                exportToExcel();
                const dropdown = exportDropdownBtn ? exportDropdownBtn.nextElementSibling : null;
                if (dropdown) dropdown.style.display = 'none';
            });
        }

        if (printLink) {
            printLink.addEventListener('click', function(e) {
                e.preventDefault();
                printTable();
                const dropdown = exportDropdownBtn ? exportDropdownBtn.nextElementSibling : null;
                if (dropdown) dropdown.style.display = 'none';
            });
        }

        if (closeDeleteModal) {
            closeDeleteModal.addEventListener('click', () => hideModal(deleteModal));
        }

        if (cancelDeleteBtn) {
            cancelDeleteBtn.addEventListener('click', () => hideModal(deleteModal));
        }
        
        if (confirmDeleteBtn) {
            confirmDeleteBtn.addEventListener('click', async () => {
                if (currentStudentToDelete) {
                    await deleteStudent(currentStudentToDelete.id, currentStudentToDelete.name);
                    hideModal(deleteModal);
                }
            });
        }

        // Click outside modal to close
        if (deleteModal) {
            deleteModal.addEventListener('click', (e) => {
                if (e.target === deleteModal) {
                    hideModal(deleteModal);
                }
            });
        }

        // Escape key to close modal
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && deleteModal && deleteModal.classList.contains('active')) {
                hideModal(deleteModal);
            }
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            const dropdowns = document.querySelectorAll('.dropdown-content');
            dropdowns.forEach(dropdown => {
                if (dropdown.style.display === 'block' && 
                    !dropdown.contains(e.target) && 
                    exportDropdownBtn && 
                    !exportDropdownBtn.contains(e.target)) {
                    dropdown.style.display = 'none';
                }
            });
        });
    });

    // Initialize page
    function initializePage() {
        loadClasses();
    }

    // Load classes
    async function loadClasses() {
        if (!classFilter) return;
        
        try {
            showLoading(classFilter, 'Loading classes...');
            
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 10000);
            
            const response = await fetch('ajax/get_classes.php', {
                signal: controller.signal,
                credentials: 'same-origin'
            });
            
            clearTimeout(timeoutId);
            
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                populateClassFilter(data.classes || []);
                
                // Auto-select first class if available
                if (data.classes && data.classes.length > 0) {
                    classFilter.value = data.classes[0].id;
                    currentClassId = data.classes[0].id;
                    if (streamFilter) {
                        streamFilter.disabled = false;
                    }
                    loadStreams(currentClassId);
                    setTimeout(() => loadStudents(), 300);
                } else {
                    showToast('Info', 'No classes found. Please add classes first.', 'info');
                }
            } else {
                throw new Error(data.message || 'Failed to load classes');
            }
            
        } catch (error) {
            console.error('Error loading classes:', error);
            
            let errorMessage = 'Failed to load classes. Please try again.';
            if (error.name === 'AbortError') {
                errorMessage = 'Request timed out. Please check your internet connection.';
            }
            
            showToast('Error', errorMessage, 'error');
            classFilter.innerHTML = '<option value="">Error loading classes</option>';
            removeLoading(classFilter);
        }
    }

    // Load streams for selected class
    async function loadStreams(classId) {
        if (!streamFilter) return;
        
        try {
            showLoading(streamFilter, 'Loading streams...');
            
            const formData = new URLSearchParams();
            formData.append('class_id', classId);
            
            const response = await fetch('ajax/get_streams.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: formData
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                populateStreamFilter(data.streams || []);
            } else {
                throw new Error(data.message || 'Failed to load streams');
            }
            
        } catch (error) {
            console.error('Error loading streams:', error);
            showToast('Error', 'Failed to load streams. Please try again.', 'error');
            streamFilter.innerHTML = '<option value="">No streams available</option>';
            removeLoading(streamFilter);
        }
    }

    // Load students with filters
    async function loadStudents() {
        if (!studentsTableBody) {
            console.error('studentsTableBody not found');
            return;
        }

        showTableLoading();

        try {
            const params = new URLSearchParams();
            params.append('action', 'get_students');
            params.append('page', currentPage);
            
            if (currentSearch) {
                params.append('search', currentSearch);
            }
            
            if (currentClassId && currentClassId !== '') {
                params.append('class_id', currentClassId);
            }
            
            if (currentStreamId && currentStreamId !== '') {
                params.append('stream_id', currentStreamId);
            }

            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 10000);

            const response = await fetch('studentslist.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: params,
                signal: controller.signal,
                credentials: 'same-origin'
            });

            clearTimeout(timeoutId);

            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                displayStudents(data.students || []);
                updatePagination({
                    total_pages: data.total_pages || 1,
                    total_students: data.total_students || 0,
                    current_page: data.current_page || 1
                });
            } else {
                throw new Error(data.message || 'Failed to load students from server');
            }
            
        } catch (error) {
            console.error('Error loading students:', error);
            
            let errorMessage = error.message;
            if (error.name === 'AbortError') {
                errorMessage = 'Request timed out. Please check your internet connection and try again.';
            }
            
            showToast('Error', 'Error loading students: ' + errorMessage, 'error');
            showTableError(errorMessage);
        }
    }

    function displayStudents(students) {
        if (!studentsTableBody) return;
        
        if (students.length === 0) {
            studentsTableBody.innerHTML = `
                <tr>
                    <td colspan="8">
                        <div class="empty-state">
                            <i class="fas fa-user-graduate"></i>
                            <h3>No Students Found</h3>
                            <p>${currentSearch ? 'No students match your search' : 'Select a class to view students'}</p>
                            ${currentSearch ? `<button class="btn btn-primary" onclick="resetFilters()" style="margin-top: 1rem;">Clear Search</button>` : ''}
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        const rows = students.map((student) => {
            const firstName = student.FirstName || student.first_name || '';
            const secondName = student.SecondName || student.second_name || '';
            const lastName = student.LastName || student.last_name || '';
            const fullName = [firstName, secondName, lastName]
                .filter(name => name && name.trim() !== '')
                .join(' ');
            
            const contact = student.GuardianPhone || student.guardian_phone || 'N/A';
            const guardianName = student.GuardianName || student.guardian_name || 'N/A';
            const guardianRelationship = student.GuardianRelationship || student.guardian_relationship || '';
            
            let guardianDisplay = guardianName;
            if (guardianRelationship) {
                guardianDisplay += ` (${guardianRelationship})`;
            }
            
            const admissionNo = student.AdmNo || student.adm_no || 'N/A';
            const className = student.class_level || 'N/A';
            const streamName = student.stream_name || 'N/A';
            const gender = student.Gender || student.gender || 'other';
            const profilePic = student.ProfilePic || student.profile_pic;
            const studentId = student.id || student.Id || '';
            const nemis = student.Nemis || student.nemis || '';
            
            return `
                <tr data-student-id="${studentId}">
                    <td>
                        <div class="student-info" onclick="window.openProfile(${studentId})" 
                             role="button" tabindex="0" 
                             aria-label="View profile of ${escapeHtml(fullName)}"
                             onkeydown="if(event.key === 'Enter' || event.key === ' ') { event.preventDefault(); window.openProfile(${studentId}); }">
                            <div class="student-avatar">
                                ${profilePic && profilePic !== 'default.png' 
                                    ? `<img src="uploads/students/${profilePic}" alt="${escapeHtml(fullName)}" 
                                       onerror="this.style.display='none'; this.parentNode.innerHTML='<i class=\"fas fa-user\"></i>';" 
                                       loading="lazy">` :
                                    `<i class="fas fa-user"></i>`
                                }
                            </div>
                            <div class="student-details">
                                <h4>${escapeHtml(fullName)}</h4>
                                <p>${nemis ? `NEMIS: ${escapeHtml(nemis)}` : 'No NEMIS number'}</p>
                            </div>
                        </div>
                    </td>
                    <td><span class="admission-no">${escapeHtml(admissionNo)}</span></td>
                    <td>${escapeHtml(className)}</td>
                    <td>${escapeHtml(streamName)}</td>
                    <td>
                        <span class="gender-badge gender-${gender.toLowerCase()}">
                            ${escapeHtml(gender)}
                        </span>
                    </td>
                    <td>${escapeHtml(contact)}</td>
                    <td>${escapeHtml(guardianDisplay)}</td>
                    <td>
                        <div class="actions">
                            <button class="action-btn-small edit-btn" onclick="editStudent(${studentId}, '${escapeHtml(fullName)}')" title="Edit Student">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="action-btn-small delete-btn" onclick="showDeleteConfirmation(${studentId}, '${escapeHtml(fullName)}')" title="Delete Student">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');

        studentsTableBody.innerHTML = rows;
        updateStatsCounts(students);
    }

    // Function to open student profile
    window.openProfile = function(studentId) {
        window.location.href = `student-profile.php?id=${studentId}`;
    };

    // Show delete confirmation modal
    window.showDeleteConfirmation = function(studentId, studentName) {
        currentStudentToDelete = {
            id: studentId,
            name: studentName
        };
        
        if (deleteStudentName) {
            deleteStudentName.textContent = studentName;
        }
        showModal(deleteModal);
    };

    // Edit student function - now fully functional
// Edit student function
window.editStudent = async function(studentId, studentName) {
    try {
        // Show loading indicator
        const editButtons = document.querySelectorAll(`button[onclick*="editStudent(${studentId}"]`);
        editButtons.forEach(btn => {
            const originalHTML = btn.innerHTML;
            btn.innerHTML = `<i class="fas fa-spinner fa-spin"></i>`;
            btn.disabled = true;
            btn.dataset.originalHTML = originalHTML;
        });
        
        currentStudentToEdit = studentId;
        
        // Get student details
        const formData = new URLSearchParams();
        formData.append('action', 'get_student_details');
        formData.append('student_id', studentId);
        
        const response = await fetch('studentslist.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: formData
        });
        
        const data = await response.json();
        
        // Restore buttons
        editButtons.forEach(btn => {
            if (btn.dataset.originalHTML) {
                btn.innerHTML = btn.dataset.originalHTML;
                btn.disabled = false;
            }
        });
        
        if (data.success) {
            showEditModal(data.student, data.classes, data.streams);
        } else {
            showToast('Error', data.message || 'Failed to load student details', 'error');
        }
    } catch (error) {
        console.error('Error loading student details:', error);
        showToast('Error', 'Error loading student details. Please try again.', 'error');
        
        // Restore buttons on error
        const editButtons = document.querySelectorAll(`button[onclick*="editStudent(${studentId}"]`);
        editButtons.forEach(btn => {
            if (btn.dataset.originalHTML) {
                btn.innerHTML = btn.dataset.originalHTML;
                btn.disabled = false;
            }
        });
    }
};
// Show edit modal with student data
function showEditModal(student, classes, streams) {
    // Create or update edit modal
    let editModal = document.getElementById('editModal');
    
    if (!editModal) {
        editModal = document.createElement('div');
        editModal.id = 'editModal';
        editModal.className = 'modal-overlay';
        editModal.innerHTML = `
            <div class="modal" style="max-width: 700px;">
                <div class="modal-header">
                    <h3 class="modal-title">
                        <i class="fas fa-edit"></i>
                        Edit Student
                    </h3>
                    <button class="close-modal" onclick="hideEditModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="editStudentForm">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                            <div>
                                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-dark);">
                                    First Name *
                                </label>
                                <input type="text" name="first_name" required 
                                       style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); 
                                              border-radius: var(--border-radius);">
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-dark);">
                                    Second Name
                                </label>
                                <input type="text" name="second_name" 
                                       style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); 
                                              border-radius: var(--border-radius);">
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-dark);">
                                    Last Name *
                                </label>
                                <input type="text" name="last_name" required 
                                       style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); 
                                              border-radius: var(--border-radius);">
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-dark);">
                                    Admission No *
                                </label>
                                <input type="text" name="admission_no" required 
                                       style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); 
                                              border-radius: var(--border-radius);">
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-dark);">
                                    Gender *
                                </label>
                                <select name="gender" required 
                                        style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); 
                                               border-radius: var(--border-radius);">
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-dark);">
                                    Date of Birth
                                </label>
                                <input type="date" name="date_of_birth" 
                                       style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); 
                                              border-radius: var(--border-radius);">
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-dark);">
                                    Class *
                                </label>
                                <select name="class_id" required 
                                        style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); 
                                               border-radius: var(--border-radius);" 
                                        onchange="updateStreamsForEdit(this.value)">
                                    <option value="">Select Class</option>
                                </select>
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-dark);">
                                    Stream
                                </label>
                                <select name="stream_id" 
                                        style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); 
                                               border-radius: var(--border-radius);">
                                    <option value="">Select Stream</option>
                                </select>
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-dark);">
                                    Guardian Phone
                                </label>
                                <input type="tel" name="guardian_phone" 
                                       style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); 
                                              border-radius: var(--border-radius);">
                            </div>
                            <div style="grid-column: span 2;">
                                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-dark);">
                                    Guardian Name
                                </label>
                                <input type="text" name="guardian_name" 
                                       style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); 
                                              border-radius: var(--border-radius);">
                            </div>
                        </div>
                        <input type="hidden" name="student_id" value="${student.id}">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="hideEditModal()">
                        Cancel
                    </button>
                    <button type="button" class="btn btn-primary" onclick="updateStudent()" id="updateStudentBtn">
                        <i class="fas fa-save"></i> Update Student
                    </button>
                </div>
            </div>
        `;
        document.body.appendChild(editModal);
    }
    
    // Populate form with student data
    const form = editModal.querySelector('#editStudentForm');
    
    // Format date for input field
    let dob = student.date_of_birth || '';
    if (dob) {
        const date = new Date(dob);
        if (!isNaN(date.getTime())) {
            dob = date.toISOString().split('T')[0];
        }
    }
    
    // Populate form fields with actual data from database
    form.querySelector('[name="first_name"]').value = student.FirstName || student.first_name || '';
    form.querySelector('[name="second_name"]').value = student.SecondName || student.second_name || '';
    form.querySelector('[name="last_name"]').value = student.LastName || student.last_name || '';
    form.querySelector('[name="admission_no"]').value = student.AdmNo || student.admission_no || '';
    form.querySelector('[name="gender"]').value = student.Gender || student.gender || 'Male';
    form.querySelector('[name="date_of_birth"]').value = dob;
    form.querySelector('[name="guardian_phone"]').value = student.GuardianPhone || student.guardian_phone || '';
    form.querySelector('[name="guardian_name"]').value = student.GuardianName || student.guardian_name || '';
    form.querySelector('[name="student_id"]').value = student.id;
    
    // Populate classes dropdown
    const classSelect = form.querySelector('[name="class_id"]');
    classSelect.innerHTML = '<option value="">Select Class</option>';
    
    if (classes && classes.length > 0) {
        classes.forEach(cls => {
            const option = document.createElement('option');
            option.value = cls.id;
            option.textContent = cls.class_level;
            if (cls.id == student.class_id) {
                option.selected = true;
            }
            classSelect.appendChild(option);
        });
    }
    
    // Initially populate streams dropdown
    if (student.class_id) {
        updateStreamsForEdit(student.class_id, student.StreamId || student.stream_id);
    }
    
    showModal(editModal);
}
// Helper function to hide edit modal
function hideEditModal() {
    const editModal = document.getElementById('editModal');
    if (editModal) {
        hideModal(editModal);
        currentStudentToEdit = null;
    }
}
    // Update streams dropdown for edit modal
async function updateStreamsForEdit(classId, selectedStreamId = null) {
    const editModal = document.getElementById('editModal');
    if (!editModal) return;
    
    const streamSelect = editModal.querySelector('[name="stream_id"]');
    streamSelect.innerHTML = '<option value="">Select Stream</option>';
    streamSelect.disabled = true;
    
    if (!classId) return;
    
    try {
        const formData = new URLSearchParams();
        formData.append('class_id', classId);
        
        const response = await fetch('ajax/get_streams.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success && data.streams) {
            data.streams.forEach(stream => {
                const option = document.createElement('option');
                option.value = stream.id;
                option.textContent = stream.stream_name;
                if (stream.id == selectedStreamId) {
                    option.selected = true;
                }
                streamSelect.appendChild(option);
            });
            streamSelect.disabled = false;
        }
    } catch (error) {
        console.error('Error loading streams:', error);
        showToast('Error', 'Failed to load streams', 'error');
    }
}

// Update student function
async function updateStudent() {
    const editModal = document.getElementById('editModal');
    if (!editModal) return;
    
    const form = editModal.querySelector('#editStudentForm');
    const updateBtn = editModal.querySelector('#updateStudentBtn');
    
    if (!form.checkValidity()) {
        showToast('Warning', 'Please fill in all required fields', 'warning');
        form.reportValidity();
        return;
    }
    
    try {
        showLoading(updateBtn, 'Updating...');
        
        const formData = new FormData(form);
        formData.append('action', 'update_student');
        
        const response = await fetch('studentslist.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams(formData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast('Success', data.message || 'Student updated successfully', 'success');
            hideEditModal();
            loadStudents(); // Reload the student list
        } else {
            showToast('Error', data.message || 'Failed to update student', 'error');
        }
    } catch (error) {
        console.error('Error updating student:', error);
        showToast('Error', 'Error updating student. Please try again.', 'error');
    } finally {
        hideLoading(updateBtn);
    }
}
    // Update streams dropdown for edit modal
    async function updateStreamsForEdit(classId, selectedStreamId = null) {
        const editModal = document.getElementById('editModal');
        if (!editModal) return;
        
        const streamSelect = editModal.querySelector('[name="stream_id"]');
        streamSelect.innerHTML = '<option value="">Select Stream</option>';
        streamSelect.disabled = true;
        
        if (!classId) return;
        
        try {
            const formData = new URLSearchParams();
            formData.append('class_id', classId);
            
            const response = await fetch('ajax/get_streams.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success && data.streams) {
                data.streams.forEach(stream => {
                    const option = document.createElement('option');
                    option.value = stream.id;
                    option.textContent = stream.stream_name;
                    if (stream.id == selectedStreamId) {
                        option.selected = true;
                    }
                    streamSelect.appendChild(option);
                });
                streamSelect.disabled = false;
            }
        } catch (error) {
            console.error('Error loading streams:', error);
        }
    }

    // Update student function
    async function updateStudent() {
        if (!currentStudentToEdit) return;
        
        const editModal = document.getElementById('editModal');
        const form = editModal.querySelector('#editStudentForm');
        const updateBtn = editModal.querySelector('#updateStudentBtn');
        
        if (!form.checkValidity()) {
            showToast('Warning', 'Please fill in all required fields', 'warning');
            return;
        }
        
        try {
            showLoading(updateBtn, 'Updating...');
            
            const formData = new FormData(form);
            formData.append('action', 'update_student');
            formData.append('student_id', currentStudentToEdit);
            
            const response = await fetch('studentslist.php', {
                method: 'POST',
                body: new URLSearchParams(formData)
            });
            
            const data = await response.json();
            
            if (data.success) {
                showToast('Success', data.message, 'success');
                hideModal(editModal);
                loadStudents(); // Reload the student list
            } else {
                showToast('Error', data.message || 'Failed to update student', 'error');
            }
        } catch (error) {
            console.error('Error updating student:', error);
            showToast('Error', 'Error updating student. Please try again.', 'error');
        } finally {
            hideLoading(updateBtn);
        }
    }

    // Delete student function
    async function deleteStudent(studentId, studentName) {
        try {
            const deleteBtn = document.getElementById('confirmDeleteBtn');
            showLoading(deleteBtn, 'Deleting...');
            
            const formData = new URLSearchParams();
            formData.append('action', 'delete_student');
            formData.append('student_id', studentId);
            
            const response = await fetch('studentslist.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                showToast('Success', data.message || `Student ${studentName} deleted successfully`, 'success');
                loadStudents(); // Reload the student list
            } else {
                showToast('Error', data.message || 'Failed to delete student', 'error');
            }
        } catch (error) {
            console.error('Error deleting student:', error);
            showToast('Error', 'Error deleting student. Please try again.', 'error');
        } finally {
            if (document.getElementById('confirmDeleteBtn')) {
                hideLoading(document.getElementById('confirmDeleteBtn'));
            }
        }
    }

    // Modal functions
    function showModal(modal) {
        if (!modal) return;
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function hideModal(modal) {
        if (!modal) return;
        modal.classList.remove('active');
        document.body.style.overflow = '';
        currentStudentToDelete = null;
        currentStudentToEdit = null;
    }

    function showTableLoading() {
        if (!studentsTableBody) return;
        
        studentsTableBody.innerHTML = `
            <tr>
                <td colspan="8">
                    <div class="loading-state">
                        <div class="modern-spinner">
                            <div class="spinner-container">
                                <div class="spinner-ring"></div>
                                <div class="spinner-ring"></div>
                                <div class="spinner-ring"></div>
                                <div class="spinner-center">
                                    <i class="fas fa-user-graduate"></i>
                                </div>
                            </div>
                        </div>
                        <div class="loading-content">
                            <h3 class="loading-title">Loading Students</h3>
                            <p class="loading-text">Please wait while we fetch student information...</p>
                        </div>
                    </div>
                </td>
            </tr>
        `;
    }

    function showTableError(message) {
        if (!studentsTableBody) return;
        
        studentsTableBody.innerHTML = `
            <tr>
                <td colspan="8">
                    <div class="empty-state">
                        <i class="fas fa-exclamation-circle" style="color: var(--error-red); font-size: 3rem;"></i>
                        <h3>Error Loading Students</h3>
                        <p style="max-width: 500px; margin: 0 auto; line-height: 1.5;">${escapeHtml(message)}</p>
                        <div style="margin-top: 1.5rem; display: flex; gap: 1rem; justify-content: center;">
                            <button class="btn btn-primary" onclick="loadStudents()">
                                <i class="fas fa-redo"></i> Try Again
                            </button>
                            <button class="btn btn-outline" onclick="resetFilters()">
                                <i class="fas fa-times"></i> Clear Filters
                            </button>
                        </div>
                    </div>
                </td>
            </tr>
        `;
        
        if (paginationContainer) {
            paginationContainer.style.display = 'none';
        }
    }

function updateStatsCounts(students) {
    if (!students || !Array.isArray(students)) return;
    
    const totalStudents = students.length;
    
    // Fix: Use 'Gender' (capital G) from database, not 'gender'
    const maleStudents = students.filter(s => {
        const gender = s.Gender || s.gender || '';
        return gender.toLowerCase() === 'male';
    }).length;
    
    const femaleStudents = students.filter(s => {
        const gender = s.Gender || s.gender || '';
        return gender.toLowerCase() === 'female';
    }).length;
    
    console.log('Stats - Total:', totalStudents, 'Male:', maleStudents, 'Female:', femaleStudents);
    
    animateNumber(document.getElementById('totalStudentsCount'), totalStudents);
    animateNumber(document.getElementById('maleStudentsCount'), maleStudents);
    animateNumber(document.getElementById('femaleStudentsCount'), femaleStudents);
}
    // Helper function for smooth number animation
    function animateNumber(element, targetValue) {
        if (!element) return;
        
        const currentValue = parseInt(element.textContent) || 0;
        const duration = 500;
        const steps = 20;
        const increment = (targetValue - currentValue) / steps;
        let currentStep = 0;
        
        const timer = setInterval(() => {
            currentStep++;
            if (currentStep >= steps) {
                element.textContent = targetValue.toLocaleString();
                clearInterval(timer);
            } else {
                const value = Math.round(currentValue + (increment * currentStep));
                element.textContent = value.toLocaleString();
            }
        }, duration / steps);
    }

    function populateClassFilter(classes) {
        if (!classFilter) return;
        
        classFilter.innerHTML = '<option value="">All Classes</option>';
        
        if (classes && classes.length > 0) {
            classes.forEach(cls => {
                const option = document.createElement('option');
                option.value = cls.id;
                const className = cls.class_level || cls.class_level || `Class ${cls.id}`;
                const displayText = cls.academic_level ? 
                    `${className} (${cls.academic_level})` : 
                    className;
                option.textContent = displayText;
                classFilter.appendChild(option);
            });
        } else {
            const option = document.createElement('option');
            option.value = "";
            option.textContent = "No classes found";
            classFilter.appendChild(option);
        }
        
        removeLoading(classFilter);
    }

    function populateStreamFilter(streams) {
        if (!streamFilter) return;
        
        streamFilter.innerHTML = '<option value="">All Streams</option>';
        
        if (streams && streams.length > 0) {
            streams.forEach(stream => {
                const option = document.createElement('option');
                option.value = stream.id;
                option.textContent = stream.stream_name || `Stream ${stream.id}`;
                streamFilter.appendChild(option);
            });
        } else {
            const option = document.createElement('option');
            option.value = "";
            option.textContent = "No streams found";
            streamFilter.appendChild(option);
        }
        
        streamFilter.disabled = !streams || streams.length === 0;
        removeLoading(streamFilter);
    }

    function resetStreamFilter() {
        if (!streamFilter) return;
        
        streamFilter.innerHTML = '<option value="">All Streams</option>';
        streamFilter.disabled = true;
        currentStreamId = '';
        removeLoading(streamFilter);
    }

    function updatePagination(data) {
        if (!paginationContainer || !prevPageBtn || !nextPageBtn || !pageNumbers) return;
        
        totalPages = data.total_pages || 1;
        totalStudents = data.total_students || 0;
        currentPage = data.current_page || 1;
        
        paginationContainer.style.display = totalPages > 1 ? 'flex' : 'none';
        
        prevPageBtn.disabled = currentPage <= 1;
        nextPageBtn.disabled = currentPage >= totalPages;
        
        updatePageNumbers();
    }

    function updatePageNumbers() {
        if (!pageNumbers) return;
        
        pageNumbers.innerHTML = '';
        
        let startPage = Math.max(1, currentPage - 2);
        let endPage = Math.min(totalPages, currentPage + 2);
        
        if (currentPage <= 3) {
            endPage = Math.min(5, totalPages);
        }
        
        if (currentPage >= totalPages - 2) {
            startPage = Math.max(1, totalPages - 4);
        }
        
        for (let i = startPage; i <= endPage; i++) {
            const pageBtn = document.createElement('button');
            pageBtn.className = `btn ${i === currentPage ? 'active' : ''}`;
            pageBtn.textContent = i;
            pageBtn.onclick = () => {
                currentPage = i;
                loadStudents();
            };
            pageNumbers.appendChild(pageBtn);
        }
    }

    function resetPaginationAndLoadStudents() {
        currentPage = 1;
        loadStudents();
    }

    window.resetFilters = function() {
        if (classFilter) classFilter.value = '';
        if (streamFilter) {
            streamFilter.innerHTML = '<option value="">All Streams</option>';
            streamFilter.disabled = true;
        }
        if (searchInput) searchInput.value = '';
        
        currentClassId = '';
        currentStreamId = '';
        currentSearch = '';
        currentPage = 1;
        
        if (studentsTableBody) {
            studentsTableBody.innerHTML = `
                <tr>
                    <td colspan="8">
                        <div class="empty-state">
                            <i class="fas fa-user-graduate"></i>
                            <h3>No Students Found</h3>
                            <p>Select a class to view students</p>
                        </div>
                    </td>
                </tr>
            `;
        }
        
        if (paginationContainer) {
            paginationContainer.style.display = 'none';
        }
    };

    function showLoading(element, text) {
        if (!element) return;
        element.innerHTML = `<option value="" disabled selected>${escapeHtml(text)}</option>`;
        element.disabled = true;
    }

    function removeLoading(element) {
        if (!element) return;
        element.disabled = false;
    }

    // PDF Export function
    async function exportToPDF() {
        if (!exportPdfBtn) return;
        
        try {
            if (!currentClassId && !currentSearch) {
                showToast('Warning', 'Please select a class or enter search criteria first', 'warning');
                return;
            }

            showLoading(exportPdfBtn, 'Generating PDF...');
            
            let url = 'ajax/export_students_pdf.php?';
            const params = [];
            
            if (currentClassId && currentClassId !== '') {
                params.push(`class_id=${currentClassId}`);
            }
            
            if (currentStreamId && currentStreamId !== '') {
                params.push(`stream_id=${currentStreamId}`);
            }
            
            if (currentSearch) {
                params.push(`search=${encodeURIComponent(currentSearch)}`);
            }
            
            url += params.join('&');
            url += '&t=' + Date.now();
            
            const newWindow = window.open(url, '_blank');
            
            if (!newWindow) {
                showToast('Warning', 'Popup blocked! Please allow popups for this site.', 'warning');
            } else {
                showToast('Success', 'PDF is being generated...', 'success');
            }
            
        } catch (error) {
            console.error('PDF Export error:', error);
            showToast('Error', 'Error generating PDF: ' + error.message, 'error');
        } finally {
            setTimeout(() => hideLoading(exportPdfBtn), 1000);
        }
    }

    // Excel Export function
    async function exportToExcel() {
        try {
            if (!currentClassId && !currentSearch) {
                showToast('Warning', 'Please select a class or enter search criteria first', 'warning');
                return;
            }

            showToast('Info', 'Preparing Excel export...', 'info');
            
            const params = new URLSearchParams();
            params.append('action', 'get_students');
            params.append('page', 1);
            params.append('limit', 10000);
            
            if (currentSearch) {
                params.append('search', currentSearch);
            }
            
            if (currentClassId && currentClassId !== '') {
                params.append('class_id', currentClassId);
            }
            
            if (currentStreamId && currentStreamId !== '') {
                params.append('stream_id', currentStreamId);
            }

            const response = await fetch('studentslist.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: params
            });

            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }

            const data = await response.json();
            
            if (data.success && data.students && data.students.length > 0) {
                const csv = convertToCSV(data.students);
                downloadCSV(csv, `students_${new Date().toISOString().split('T')[0]}.csv`);
                showToast('Success', `Exported ${data.students.length} students`, 'success');
            } else {
                showToast('Warning', 'No data to export', 'warning');
            }
        } catch (error) {
            console.error('Export error:', error);
            showToast('Error', 'Error exporting data: ' + error.message, 'error');
        }
    }

    // Add CSS for PDF loading animation
    const pdfStyle = document.createElement('style');
    pdfStyle.textContent = `
        @keyframes pdf-spin {
            0% { transform: rotate(0deg); opacity: 0.2; }
            50% { transform: rotate(180deg); opacity: 1; }
            100% { transform: rotate(360deg); opacity: 0.2; }
        }
        
        .pdf-loading-spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #dc2626;
            border-radius: 50%;
            border-top-color: transparent;
            animation: pdf-spin 1s ease-in-out infinite;
            margin-right: 8px;
        }
    `;
    document.head.appendChild(pdfStyle);

    // Print function
    function printTable() {
        try {
            if (!currentClassId && !currentSearch) {
                showToast('Warning', 'Please select a class or search for students first', 'warning');
                return;
            }
            
            let reportTitle = 'STUDENT LIST REPORT';
            let classInfo = '';
            
            if (currentClassId && classFilter) {
                const selectedClass = classFilter.options[classFilter.selectedIndex];
                classInfo = 'Class: ' + selectedClass.text;
            }
            
            if (currentStreamId && currentStreamId !== '' && streamFilter) {
                const selectedStream = streamFilter.options[streamFilter.selectedIndex];
                if (classInfo) {
                    classInfo += '  |  ';
                } else {
                    classInfo = '';
                }
                classInfo += 'Stream: ' + selectedStream.text;
            }
            
            let tableHTML = `
                <table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
                    <thead>
                        <tr style="background: #1e3a8a; color: white;">
                            <th style="padding: 10px; text-align: left; border: 1px solid #ddd;">#</th>
                            <th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Adm No</th>
                            <th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Student Name</th>
                            <th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Gender</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            const rows = document.querySelectorAll('#studentsTableBody tr');
            rows.forEach((row, index) => {
                const cells = row.querySelectorAll('td');
                if (cells.length > 0) {
                    const studentName = cells[0].querySelector('.student-details h4')?.textContent || '';
                    const admissionNo = cells[1].querySelector('.admission-no')?.textContent || '';
                    const genderElement = cells[4].querySelector('.gender-badge');
                    const gender = genderElement ? genderElement.textContent.trim() : '';
                    
                    tableHTML += `
                        <tr style="${index % 2 === 0 ? 'background: #f9f9f9;' : ''}">
                            <td style="padding: 8px 10px; border: 1px solid #ddd;">${index + 1}</td>
                            <td style="padding: 8px 10px; border: 1px solid #ddd;">${admissionNo}</td>
                            <td style="padding: 8px 10px; border: 1px solid #ddd;">${studentName}</td>
                            <td style="padding: 8px 10px; border: 1px solid #ddd;">${gender}</td>
                        </tr>
                    `;
                }
            });
            
            tableHTML += `
                    </tbody>
                </table>
            `;
            
            const printContent = `
                
<!DOCTYPE html>
                <html>
                <head>
                    <title>${reportTitle}</title>
                    <style>
                        @media print {
                            body { 
                                font-family: Arial, sans-serif; 
                                margin: 20px; 
                                color: #1f2937;
                            }
                            .header { 
                                text-align: center; 
                                margin-bottom: 30px;
                            }
                            .school-name {
                                font-size: 24px;
                                font-weight: bold;
                                color: #1e3a8a;
                                margin-bottom: 10px;
                            }
                            .report-title {
                                font-size: 20px;
                                font-weight: bold;
                                color: #1e3a8a;
                                margin: 20px 0;
                            }
                            .class-info {
                                font-size: 16px;
                                color: #fbbf24;
                                font-weight: bold;
                                margin-bottom: 20px;
                            }
                            table { 
                                width: 100%; 
                                border-collapse: collapse; 
                                margin-top: 20px;
                            }
                            th { 
                                background: #1e3a8a; 
                                color: white; 
                                padding: 10px; 
                                text-align: left; 
                                border: 1px solid #ddd;
                            }
                            td { 
                                padding: 8px 10px; 
                                border: 1px solid #ddd; 
                            }
                            tr:nth-child(even) { 
                                background: #f9f9f9; 
                            }
                            .footer { 
                                margin-top: 30px; 
                                padding-top: 10px; 
                                border-top: 1px solid #ddd;
                                color: #666; 
                                font-size: 12px;
                            }
                            .total-students {
                                font-weight: bold;
                                margin-top: 20px;
                                text-align: right;
                                color: #1e3a8a;
                            }
                        }
                        @page {
                            margin: 20mm;
                        }
                    </style>
                </head>
                <body>

                    <div class="header">
                        <div class="school-name">${document.title.replace('EduScore - ', '')}</div>
                        <div class="report-title">${reportTitle}</div>
                        ${classInfo ? `<div class="class-info">${classInfo}</div>` : ''}
                    </div>
                    ${tableHTML}
                    <div class="total-students">
                        Total Students: ${totalStudents}
                    </div>
                    <div class="footer">
                        Generated on ${new Date().toLocaleDateString()} ${new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}
                    </div>
                </body>
                </html>
            `;
            
            const printWindow = window.open('', '_blank', 'width=800,height=600');
            printWindow.document.write(printContent);
            printWindow.document.close();
            printWindow.focus();
            
            setTimeout(() => {
                printWindow.print();
                printWindow.close();
            }, 250);
            
        } catch (error) {
            console.error('Print error:', error);
            showToast('Error', 'Error printing document: ' + error.message, 'error');
            window.print();
        }
    }

    function convertToCSV(students) {
        const headers = ['Adm No', 'Student Name', 'Gender'];
        
        const rows = students.map(student => {
            const firstName = student.first_name || student.FirstName || '';
            const secondName = student.second_name || student.SecondName || '';
            const lastName = student.last_name || student.LastName || '';
            const fullName = [firstName, secondName, lastName]
                .filter(name => name && name.trim() !== '')
                .join(' ');
                
            return [
                student.admission_no || student.AdmNo || '',
                `"${fullName}"`,
                student.gender || ''
            ];
        });
        
        return [headers, ...rows].map(row => row.join(',')).join('\n');
    }
    
    function downloadCSV(csv, filename) {
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', filename);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    }
</script>
</body>

</body>
</html>
