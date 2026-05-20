<?php
session_start();
require_once 'db_connection.php';

// Set proper headers for JSON response
header('Content-Type: application/json');

// Security check
if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get and validate input
$query = isset($_POST['query']) ? trim($_POST['query']) : '';
$academic_level = isset($_POST['academic_level']) ? $_POST['academic_level'] : 'primary';
$school_id = $_SESSION['school_id'] ?? null;

// Validate school_id
if (!$school_id) {
    echo json_encode(['error' => 'Invalid school']);
    exit;
}

// If query is too short, return empty results
if (strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

try {
    $search_results = [];
    $search_term = "%$query%";

    // Search students
    $student_sql = "SELECT id, FirstName, SecondName, LastName, AdmNo, class_id 
                    FROM tblstudents 
                    WHERE school_id = ? AND (FirstName LIKE ? OR SecondName LIKE ? OR LastName LIKE ? OR AdmNo LIKE ?)
                    LIMIT 5";
    $student_stmt = $conn->prepare($student_sql);
    if ($student_stmt) {
        $student_stmt->bind_param("issss", $school_id, $search_term, $search_term, $search_term, $search_term);
        $student_stmt->execute();
        $student_result = $student_stmt->get_result();

        while ($student = $student_result->fetch_assoc()) {
            $full_name = $student['FirstName'] . ' ' . $student['SecondName'];
            if (!empty($student['LastName'])) {
                $full_name .= ' ' . $student['LastName'];
            }
            
            $search_results[] = [
                'type' => 'Student',
                'id' => (int)$student['id'],
                'title' => $full_name,
                'description' => 'Admission No: ' . $student['AdmNo'],
                'icon' => 'fas fa-user-graduate',
                'url' => 'students.php?action=view&id=' . $student['id']
            ];
        }
        $student_stmt->close();
    }

    // Search teachers
    $teacher_sql = "SELECT id, firstname, secondname, lastname, email 
                    FROM tblteachers 
                    WHERE school_id = ? AND (firstname LIKE ? OR secondname LIKE ? OR lastname LIKE ? OR email LIKE ?)
                    LIMIT 5";
    $teacher_stmt = $conn->prepare($teacher_sql);
    if ($teacher_stmt) {
        $teacher_stmt->bind_param("issss", $school_id, $search_term, $search_term, $search_term, $search_term);
        $teacher_stmt->execute();
        $teacher_result = $teacher_stmt->get_result();

        while ($teacher = $teacher_result->fetch_assoc()) {
            $full_name = $teacher['firstname'] . ' ' . $teacher['secondname'];
            if (!empty($teacher['lastname'])) {
                $full_name .= ' ' . $teacher['lastname'];
            }
            
            $search_results[] = [
                'type' => 'Teacher',
                'id' => (int)$teacher['id'],
                'title' => $full_name,
                'description' => 'Email: ' . $teacher['email'],
                'icon' => 'fas fa-chalkboard-teacher',
                'url' => 'teachers.php?action=view&id=' . $teacher['id']
            ];
        }
        $teacher_stmt->close();
    }

    // Search classes
    $class_sql = "SELECT id, class_level, academic_level 
                  FROM tblclasses 
                  WHERE school_id = ? AND academic_level = ? AND class_level LIKE ?
                  LIMIT 5";
    $class_stmt = $conn->prepare($class_sql);
    if ($class_stmt) {
        $class_stmt->bind_param("iss", $school_id, $academic_level, $search_term);
        $class_stmt->execute();
        $class_result = $class_stmt->get_result();

        while ($class = $class_result->fetch_assoc()) {
            $search_results[] = [
                'type' => 'Class',
                'id' => (int)$class['id'],
                'title' => $class['class_level'],
                'description' => 'Academic Level: ' . $academic_level,
                'icon' => 'fas fa-chalkboard',
                'url' => 'classes.php?action=view&id=' . $class['id']
            ];
        }
        $class_stmt->close();
    }

    // Search subjects
    $subject_sql = "SELECT id, subject_name, alias 
                    FROM tblsubjects 
                    WHERE school_id = ? AND (subject_name LIKE ? OR alias LIKE ?)
                    LIMIT 5";
    $subject_stmt = $conn->prepare($subject_sql);
    if ($subject_stmt) {
        $subject_stmt->bind_param("iss", $school_id, $search_term, $search_term);
        $subject_stmt->execute();
        $subject_result = $subject_stmt->get_result();

        while ($subject = $subject_result->fetch_assoc()) {
            $search_results[] = [
                'type' => 'Subject',
                'id' => (int)$subject['id'],
                'title' => $subject['subject_name'],
                'description' => 'Alias: ' . ($subject['alias'] ?: 'N/A'),
                'icon' => 'fas fa-book',
                'url' => 'subjects.php?action=view&id=' . $subject['id']
            ];
        }
        $subject_stmt->close();
    }

    // Limit total results to 10
    $search_results = array_slice($search_results, 0, 10);

    // Return JSON response
    echo json_encode($search_results);

} catch (Exception $e) {
    // Log error and return empty array
    error_log("Search error: " . $e->getMessage());
    echo json_encode([]);
}
?>