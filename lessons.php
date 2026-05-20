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

// Check if user has permission to view lessons page
$permissionHelper->requireAnyPermission(['lessonsView', 'lessonsViewAll'], 'dashboard.php');

// Determine which actions are allowed based on permissions
$canCreate = $permissionHelper->hasPermission('lessonsCreate');
$canEdit = $permissionHelper->hasPermission('lessonsEdit');
$canDelete = $permissionHelper->hasPermission('lessonsDelete');
$canAssignStudents = $permissionHelper->hasPermission('lessonsAssignStudents');
$canViewAll = $permissionHelper->hasPermission('lessonsViewAll');
$isSuperAdmin = $permissionHelper->isSuperAdmin();
$currentUserRole = $permissionHelper->getRole();

// DB
if (!isset($db)) {
    require_once 'config/database.php';
    $database = new Database();
    $db = $database->getConnection();
}

// Get current academic level from session
$current_level = $_SESSION['academic_level'] ?? 'Primary';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'add_lesson':
                if (!$canCreate) {
                    echo json_encode(['success' => false, 'message' => 'You do not have permission to add lessons']);
                    break;
                }
                $response = addLesson($db, $school_id, $teacher_id);
                echo json_encode($response);
                break;
                
            case 'update_lesson':
                if (!$canEdit) {
                    echo json_encode(['success' => false, 'message' => 'You do not have permission to edit lessons']);
                    break;
                }
                $response = updateLesson($db, $school_id);
                echo json_encode($response);
                break;
                
            case 'delete_lesson':
                if (!$canDelete) {
                    echo json_encode(['success' => false, 'message' => 'You do not have permission to delete lessons']);
                    break;
                }
                $response = deleteLesson($db, $school_id);
                echo json_encode($response);
                break;
                
            case 'get_lesson':
                if (!$permissionHelper->hasAnyPermission(['lessonsView', 'lessonsViewAll'])) {
                    echo json_encode(['success' => false, 'message' => 'You do not have permission to view lessons']);
                    break;
                }
                $lesson_id = intval($_POST['lesson_id']);
                $query = "SELECT l.*, s.subject_name, c.class_level, st.stream_name,
                         CONCAT(t.firstname, ' ', t.lastname) as teacher_name
                         FROM tbllessons l 
                         JOIN tblsubjects s ON l.subject_id = s.id 
                         JOIN tblclasses c ON l.class_id = c.id 
                         LEFT JOIN tblstreams st ON l.stream_id = st.id
                         LEFT JOIN tblteachers t ON l.teacher_id = t.id 
                         WHERE l.id = :lesson_id AND l.school_id = :school_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(":lesson_id", $lesson_id);
                $stmt->bindParam(":school_id", $school_id);
                $stmt->execute();
                $lesson = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($lesson) {
                    $student_query = "SELECT student_id FROM tbllesson_students WHERE lesson_id = :lesson_id";
                    $student_stmt = $db->prepare($student_query);
                    $student_stmt->bindParam(":lesson_id", $lesson_id);
                    $student_stmt->execute();
                    $assigned_students = $student_stmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    $lesson['assigned_students'] = $assigned_students;
                    echo json_encode(['success' => true, 'lesson' => $lesson]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Lesson not found']);
                }
                break;
                
            case 'get_classes_by_level':
                if (!$permissionHelper->hasAnyPermission(['lessonsView', 'lessonsViewAll'])) {
                    echo json_encode(['success' => false, 'message' => 'You do not have permission to view classes']);
                    break;
                }
                $academic_level = $_POST['academic_level'] ?? $current_level;
                
                $academic_level_map = [
                    'Primary' => 'primary',
                    'primary' => 'primary',
                    'Junior Secondary' => 'junior_secondary',
                    'junior_secondary' => 'junior_secondary',
                    'Secondary' => 'secondary',
                    'secondary' => 'secondary'
                ];
                
                $db_level = $academic_level_map[$academic_level] ?? $academic_level;
                
                $query = "SELECT id, class_level FROM tblclasses 
                         WHERE school_id = :school_id AND academic_level = :academic_level
                         ORDER BY CASE 
                            WHEN class_level LIKE 'Grade%' THEN CAST(SUBSTRING(class_level, 7) AS UNSIGNED)
                            WHEN class_level LIKE 'Form%' THEN CAST(SUBSTRING(class_level, 6) AS UNSIGNED) + 8
                            ELSE 999 END";
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(":school_id", $school_id, PDO::PARAM_INT);
                $stmt->bindParam(":academic_level", $db_level);
                $stmt->execute();
                $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'classes' => $classes]);
                break;
                
            case 'get_students_by_class':
                if (!$permissionHelper->hasAnyPermission(['studentsView', 'studentsViewAll', 'lessonsView', 'lessonsViewAll'])) {
                    echo json_encode(['success' => false, 'message' => 'You do not have permission to view students']);
                    break;
                }
                $class_id = intval($_POST['class_id']);
                $stream_id = isset($_POST['stream_id']) && $_POST['stream_id'] !== '' ? intval($_POST['stream_id']) : null;
                
                $query = "SELECT id, FirstName, SecondName, LastName, AdmNo 
                         FROM tblstudents 
                         WHERE class_id = :class_id AND school_id = :school_id";
                
                $params = [':class_id' => $class_id, ':school_id' => $school_id];
                
                if ($stream_id) {
                    $query .= " AND StreamId = :stream_id";
                    $params[':stream_id'] = $stream_id;
                }
                
                $query .= " ORDER BY FirstName, SecondName";
                
                $stmt = $db->prepare($query);
                $stmt->execute($params);
                $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'students' => $students]);
                break;
                
            case 'get_streams_by_class':
                if (!$permissionHelper->hasAnyPermission(['lessonsView', 'lessonsViewAll'])) {
                    echo json_encode(['success' => false, 'message' => 'You do not have permission to view streams']);
                    break;
                }
                $class_id = intval($_POST['class_id']);
                
                $query = "SELECT id, stream_name FROM tblstreams 
                         WHERE class_id = :class_id AND school_id = :school_id 
                         ORDER BY stream_name";
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(":class_id", $class_id);
                $stmt->bindParam(":school_id", $school_id);
                $stmt->execute();
                $streams = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'streams' => $streams]);
                break;
                
            case 'get_subjects_by_class_stream':
                if (!$permissionHelper->hasAnyPermission(['lessonsView', 'lessonsViewAll'])) {
                    echo json_encode(['success' => false, 'message' => 'You do not have permission to view subjects']);
                    break;
                }
                $class_id = intval($_POST['class_id']);
                $stream_id = isset($_POST['stream_id']) && $_POST['stream_id'] !== '' ? intval($_POST['stream_id']) : null;
                
                $query = "SELECT id, subject_name FROM tblsubjects 
                         WHERE class_id = :class_id AND school_id = :school_id";
                
                $params = [':class_id' => $class_id, ':school_id' => $school_id];
                
                if ($stream_id) {
                    $query .= " AND (stream_id = :stream_id OR stream_id IS NULL)";
                    $params[':stream_id'] = $stream_id;
                }
                
                $query .= " ORDER BY subject_name";
                
                $stmt = $db->prepare($query);
                $stmt->execute($params);
                $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'subjects' => $subjects]);
                break;
                
            case 'get_students_count_by_class':
                if (!$permissionHelper->hasAnyPermission(['lessonsView', 'lessonsViewAll'])) {
                    echo json_encode(['success' => false, 'message' => 'You do not have permission to view counts']);
                    break;
                }
                $class_id = intval($_POST['class_id']);
                $stream_id = isset($_POST['stream_id']) && $_POST['stream_id'] !== '' ? intval($_POST['stream_id']) : null;
                
                $query = "SELECT COUNT(*) as student_count 
                         FROM tblstudents 
                         WHERE class_id = :class_id AND school_id = :school_id";
                
                $params = [':class_id' => $class_id, ':school_id' => $school_id];
                
                if ($stream_id) {
                    $query .= " AND StreamId = :stream_id";
                    $params[':stream_id'] = $stream_id;
                }
                
                $stmt = $db->prepare($query);
                $stmt->execute($params);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'student_count' => $result['student_count']]);
                break;
                
            case 'get_student_subject_assignments':
                if (!$permissionHelper->hasAnyPermission(['lessonsView', 'lessonsViewAll'])) {
                    echo json_encode(['success' => false, 'message' => 'You do not have permission to view assignments']);
                    break;
                }
                $class_id = intval($_POST['class_id']);
                $stream_id = isset($_POST['stream_id']) && $_POST['stream_id'] !== '' ? intval($_POST['stream_id']) : null;
                
                $student_query = "SELECT id, FirstName, SecondName, LastName, AdmNo 
                                 FROM tblstudents 
                                 WHERE class_id = :class_id AND school_id = :school_id";
                
                $student_params = [':class_id' => $class_id, ':school_id' => $school_id];
                
                if ($stream_id) {
                    $student_query .= " AND StreamId = :stream_id";
                    $student_params[':stream_id'] = $stream_id;
                }
                
                $student_query .= " ORDER BY FirstName, SecondName";
                
                $student_stmt = $db->prepare($student_query);
                $student_stmt->execute($student_params);
                $students = $student_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $subject_query = "SELECT id, subject_name FROM tblsubjects 
                                 WHERE class_id = :class_id AND school_id = :school_id";
                
                $subject_params = [':class_id' => $class_id, ':school_id' => $school_id];
                
                if ($stream_id) {
                    $subject_query .= " AND (stream_id = :stream_id OR stream_id IS NULL)";
                    $subject_params[':stream_id'] = $stream_id;
                }
                
                $subject_query .= " ORDER BY subject_name";
                
                $subject_stmt = $db->prepare($subject_query);
                $subject_stmt->execute($subject_params);
                $subjects = $subject_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $lesson_query = "SELECT l.id, l.subject_id, s.subject_name 
                                FROM tbllessons l 
                                JOIN tblsubjects s ON l.subject_id = s.id 
                                WHERE l.class_id = :class_id AND l.school_id = :school_id";
                
                $lesson_params = [':class_id' => $class_id, ':school_id' => $school_id];
                
                if ($stream_id) {
                    $lesson_query .= " AND (l.stream_id = :stream_id OR l.stream_id IS NULL)";
                    $lesson_params[':stream_id'] = $stream_id;
                }
                
                $lesson_stmt = $db->prepare($lesson_query);
                $lesson_stmt->execute($lesson_params);
                $lessons = $lesson_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $lesson_subject_map = [];
                foreach ($lessons as $lesson) {
                    $lesson_subject_map[$lesson['id']] = $lesson['subject_id'];
                }
                
                $assignment_query = "SELECT ls.lesson_id, ls.student_id 
                                    FROM tbllesson_students ls 
                                    JOIN tbllessons l ON ls.lesson_id = l.id 
                                    WHERE l.class_id = :class_id AND ls.school_id = :school_id";
                
                $assignment_params = [':class_id' => $class_id, ':school_id' => $school_id];
                
                if ($stream_id) {
                    $assignment_query .= " AND (l.stream_id = :stream_id OR l.stream_id IS NULL)";
                    $assignment_params[':stream_id'] = $stream_id;
                }
                
                $assignment_stmt = $db->prepare($assignment_query);
                $assignment_stmt->execute($assignment_params);
                $assignments = $assignment_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $student_assignments = [];
                foreach ($assignments as $assignment) {
                    $student_id = $assignment['student_id'];
                    $lesson_id = $assignment['lesson_id'];
                    $subject_id = $lesson_subject_map[$lesson_id] ?? null;
                    
                    if ($subject_id) {
                        if (!isset($student_assignments[$student_id])) {
                            $student_assignments[$student_id] = [];
                        }
                        if (!in_array($subject_id, $student_assignments[$student_id])) {
                            $student_assignments[$student_id][] = $subject_id;
                        }
                    }
                }
                
                echo json_encode([
                    'success' => true, 
                    'students' => $students,
                    'subjects' => $subjects,
                    'student_assignments' => $student_assignments
                ]);
                break;
                
            case 'get_lessons_stats':
                if (!$permissionHelper->hasAnyPermission(['lessonsView', 'lessonsViewAll'])) {
                    echo json_encode(['success' => false, 'message' => 'You do not have permission to view stats']);
                    break;
                }
                $total_query = "SELECT COUNT(*) as total FROM tbllessons WHERE school_id = :school_id";
                $total_stmt = $db->prepare($total_query);
                $total_stmt->bindParam(":school_id", $school_id);
                $total_stmt->execute();
                $total_lessons = $total_stmt->fetchColumn();
                
                $teachers_query = "SELECT COUNT(DISTINCT teacher_id) as assigned_teachers 
                                  FROM tbllessons 
                                  WHERE school_id = :school_id AND teacher_id IS NOT NULL";
                $teachers_stmt = $db->prepare($teachers_query);
                $teachers_stmt->bindParam(":school_id", $school_id);
                $teachers_stmt->execute();
                $assigned_teachers = $teachers_stmt->fetchColumn();
                
                $students_query = "SELECT COUNT(DISTINCT student_id) as total_students 
                                  FROM tbllesson_students 
                                  WHERE school_id = :school_id";
                $students_stmt = $db->prepare($students_query);
                $students_stmt->bindParam(":school_id", $school_id);
                $students_stmt->execute();
                $total_students = $students_stmt->fetchColumn();
                
                echo json_encode([
                    'success' => true,
                    'stats' => [
                        'total_lessons' => $total_lessons,
                        'assigned_teachers' => $assigned_teachers,
                        'total_students' => $total_students
                    ]
                ]);
                break;
                
            case 'update_student_assignment':
                if (!$canAssignStudents && !$canEdit && !$isSuperAdmin) {
                    echo json_encode(['success' => false, 'message' => 'You do not have permission to assign students to lessons']);
                    break;
                }
                
                $student_id = intval($_POST['student_id']);
                $subject_id = intval($_POST['subject_id']);
                $assign = ($_POST['assign'] === 'true' || $_POST['assign'] === '1');
                
                try {
                    $student_query = "SELECT class_id, StreamId FROM tblstudents WHERE id = :student_id AND school_id = :school_id";
                    $student_stmt = $db->prepare($student_query);
                    $student_stmt->bindParam(":student_id", $student_id, PDO::PARAM_INT);
                    $student_stmt->bindParam(":school_id", $school_id, PDO::PARAM_INT);
                    $student_stmt->execute();
                    $student_info = $student_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$student_info) {
                        echo json_encode(['success' => false, 'message' => 'Student not found']);
                        break;
                    }
                    
                    $lesson_query = "SELECT id FROM tbllessons 
                                    WHERE subject_id = :subject_id 
                                    AND class_id = :class_id 
                                    AND school_id = :school_id
                                    AND (stream_id = :stream_id_param OR (stream_id IS NULL AND :stream_id_null IS NULL))";
                    
                    $lesson_stmt = $db->prepare($lesson_query);
                    $lesson_stmt->bindParam(":subject_id", $subject_id, PDO::PARAM_INT);
                    $lesson_stmt->bindParam(":class_id", $student_info['class_id'], PDO::PARAM_INT);
                    $lesson_stmt->bindParam(":school_id", $school_id, PDO::PARAM_INT);
                    
                    $stream_param = !empty($student_info['StreamId']) ? $student_info['StreamId'] : null;
                    $lesson_stmt->bindParam(":stream_id_param", $stream_param, $stream_param ? PDO::PARAM_INT : PDO::PARAM_NULL);
                    $lesson_stmt->bindParam(":stream_id_null", $stream_param, $stream_param ? PDO::PARAM_INT : PDO::PARAM_NULL);
                    $lesson_stmt->execute();
                    $lesson = $lesson_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$lesson) {
                        $default_teacher_id = $_SESSION['teacher_id'] ?? null;
                        
                        if (!$default_teacher_id) {
                            $teacher_fallback = $db->prepare("SELECT id FROM tblteachers WHERE school_id = :school_id LIMIT 1");
                            $teacher_fallback->bindParam(":school_id", $school_id, PDO::PARAM_INT);
                            $teacher_fallback->execute();
                            $default_teacher_id = $teacher_fallback->fetchColumn();
                        }
                        
                        $create_lesson_query = "INSERT INTO tbllessons 
                                              (subject_id, class_id, stream_id, teacher_id, school_id, created_by) 
                                              VALUES (:subject_id, :class_id, :stream_id, :teacher_id, :school_id, :created_by)";
                        
                        $create_lesson_stmt = $db->prepare($create_lesson_query);
                        $create_lesson_stmt->bindParam(":subject_id", $subject_id, PDO::PARAM_INT);
                        $create_lesson_stmt->bindParam(":class_id", $student_info['class_id'], PDO::PARAM_INT);
                        $create_lesson_stmt->bindParam(":stream_id", $stream_param, $stream_param ? PDO::PARAM_INT : PDO::PARAM_NULL);
                        $create_lesson_stmt->bindParam(":teacher_id", $default_teacher_id, PDO::PARAM_INT);
                        $create_lesson_stmt->bindParam(":school_id", $school_id, PDO::PARAM_INT);
                        
                        $created_by = $_SESSION['id'] ?? $_SESSION['teacher_id'] ?? $default_teacher_id;
                        $create_lesson_stmt->bindParam(":created_by", $created_by, PDO::PARAM_INT);
                        
                        if (!$create_lesson_stmt->execute()) {
                            $errorInfo = $create_lesson_stmt->errorInfo();
                            error_log("Create lesson error: " . print_r($errorInfo, true));
                            echo json_encode(['success' => false, 'message' => 'Failed to create lesson: ' . ($errorInfo[2] ?? 'Unknown error')]);
                            break;
                        }
                        
                        $lesson_id = $db->lastInsertId();
                    } else {
                        $lesson_id = $lesson['id'];
                    }
                    
                    if ($assign) {
                        $check_query = "SELECT id FROM tbllesson_students 
                                       WHERE lesson_id = :lesson_id AND student_id = :student_id AND school_id = :school_id";
                        $check_stmt = $db->prepare($check_query);
                        $check_stmt->bindParam(":lesson_id", $lesson_id, PDO::PARAM_INT);
                        $check_stmt->bindParam(":student_id", $student_id, PDO::PARAM_INT);
                        $check_stmt->bindParam(":school_id", $school_id, PDO::PARAM_INT);
                        $check_stmt->execute();
                        
                        if (!$check_stmt->fetch()) {
                            $assign_query = "INSERT INTO tbllesson_students (lesson_id, student_id, school_id) 
                                            VALUES (:lesson_id, :student_id, :school_id)";
                            
                            $assign_stmt = $db->prepare($assign_query);
                            $assign_stmt->bindParam(":lesson_id", $lesson_id, PDO::PARAM_INT);
                            $assign_stmt->bindParam(":student_id", $student_id, PDO::PARAM_INT);
                            $assign_stmt->bindParam(":school_id", $school_id, PDO::PARAM_INT);
                            
                            if ($assign_stmt->execute()) {
                                echo json_encode(['success' => true, 'message' => 'Student assigned to subject']);
                            } else {
                                $errorInfo = $assign_stmt->errorInfo();
                                error_log("Assign student error: " . print_r($errorInfo, true));
                                echo json_encode(['success' => false, 'message' => 'Failed to assign student: ' . ($errorInfo[2] ?? 'Unknown error')]);
                            }
                        } else {
                            echo json_encode(['success' => true, 'message' => 'Student already assigned to subject']);
                        }
                    } else {
                        $remove_query = "DELETE FROM tbllesson_students 
                                        WHERE lesson_id = :lesson_id 
                                        AND student_id = :student_id 
                                        AND school_id = :school_id";
                        
                        $remove_stmt = $db->prepare($remove_query);
                        $remove_stmt->bindParam(":lesson_id", $lesson_id, PDO::PARAM_INT);
                        $remove_stmt->bindParam(":student_id", $student_id, PDO::PARAM_INT);
                        $remove_stmt->bindParam(":school_id", $school_id, PDO::PARAM_INT);
                        
                        if ($remove_stmt->execute()) {
                            echo json_encode(['success' => true, 'message' => 'Student removed from subject']);
                        } else {
                            $errorInfo = $remove_stmt->errorInfo();
                            error_log("Remove student error: " . print_r($errorInfo, true));
                            echo json_encode(['success' => false, 'message' => 'Failed to remove student: ' . ($errorInfo[2] ?? 'Unknown error')]);
                        }
                    }
                    
                } catch (PDOException $e) {
                    error_log("Update student assignment error: " . $e->getMessage());
                    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
                }
                break;
                
            case 'get_lessons_ajax':
                if (!$permissionHelper->hasAnyPermission(['lessonsView', 'lessonsViewAll'])) {
                    echo json_encode(['success' => false, 'message' => 'You do not have permission to view lessons']);
                    break;
                }
                $page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
                $class_filter = isset($_POST['class_filter']) && $_POST['class_filter'] !== '' ? intval($_POST['class_filter']) : '';
                $limit = 10;
                $offset = ($page - 1) * $limit;
                
                $lessons_query = "SELECT l.id, s.subject_name, c.class_level, st.stream_name,
                                 CONCAT(t.firstname, ' ', t.lastname) as teacher_name,
                                 (SELECT COUNT(*) FROM tbllesson_students ls WHERE ls.lesson_id = l.id) as student_count
                                 FROM tbllessons l 
                                 JOIN tblsubjects s ON l.subject_id = s.id 
                                 JOIN tblclasses c ON l.class_id = c.id 
                                 LEFT JOIN tblstreams st ON l.stream_id = st.id
                                 LEFT JOIN tblteachers t ON l.teacher_id = t.id 
                                 WHERE l.school_id = :school_id";
                
                $count_query = "SELECT COUNT(*) FROM tbllessons l 
                               JOIN tblsubjects s ON l.subject_id = s.id 
                               JOIN tblclasses c ON l.class_id = c.id 
                               WHERE l.school_id = :school_id";
                
                $params = [':school_id' => $school_id];
                
                if (!empty($class_filter)) {
                    $lessons_query .= " AND l.class_id = :class_filter";
                    $count_query .= " AND l.class_id = :class_filter";
                    $params[':class_filter'] = $class_filter;
                }
                
                $lessons_query .= " ORDER BY c.class_level, s.subject_name LIMIT :limit OFFSET :offset";
                
                $count_stmt = $db->prepare($count_query);
                foreach ($params as $key => $value) {
                    if ($key !== ':limit' && $key !== ':offset') {
                        $count_stmt->bindValue($key, $value);
                    }
                }
                $count_stmt->execute();
                $total_lessons = $count_stmt->fetchColumn();
                $total_pages = ceil($total_lessons / $limit);
                
                $params[':limit'] = $limit;
                $params[':offset'] = $offset;
                
                $lessons_stmt = $db->prepare($lessons_query);
                foreach ($params as $key => $value) {
                    $lessons_stmt->bindValue($key, $value, $key === ':limit' || $key === ':offset' ? PDO::PARAM_INT : PDO::PARAM_STR);
                }
                $lessons_stmt->execute();
                $lessons = $lessons_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $html = '';
                if (!empty($lessons)) {
                    foreach ($lessons as $lesson) {
                        $html .= '<tr data-lesson-id="' . $lesson['id'] . '">';
                        $html .= '<td>' . htmlspecialchars($lesson['subject_name']) . '</td>';
                        $html .= '<td>' . htmlspecialchars($lesson['class_level']) . '</td>';
                        $html .= '<td>' . htmlspecialchars($lesson['stream_name'] ?? 'N/A') . '</td>';
                        $html .= '<td>' . htmlspecialchars($lesson['teacher_name'] ?? 'Not Assigned') . '</td>';
                        $html .= '<td>' . htmlspecialchars($lesson['student_count']) . ' students\n                        <td>';
                        $html .= '<div class="actions">';
                        if ($canEdit) {
                            $html .= '<button class="action-btn-small edit-btn" onclick="editLesson(' . $lesson['id'] . ')" title="Edit Lesson">';
                            $html .= '<i class="fas fa-edit"></i>';
                            $html .= '</button>';
                        }
                        if ($canDelete) {
                            $html .= '<button class="action-btn-small delete-btn" onclick="showDeleteModal(' . $lesson['id'] . ')" title="Delete Lesson">';
                            $html .= '<i class="fas fa-trash"></i>';
                            $html .= '</button>';
                        }
                        $html .= '</div>';
                        $html .= '\n                        </tr>';
                    }
                } else {
                    $html .= '<tr>';
                    $html .= '<td colspan="6">';
                    $html .= '<div class="empty-state">';
                    $html .= '<i class="fas fa-book"></i>';
                    $html .= '<h3>No Lessons Found</h3>';
                    $html .= '<p>Get started by assigning your first lesson</p>';
                    if ($canCreate) {
                        $html .= '<button class="btn btn-primary" onclick="openAddLessonModal()">';
                        $html .= '<i class="fas fa-plus"></i> Assign Lesson';
                        $html .= '</button>';
                    }
                    $html .= '</div>';
                    $html .= '\n                    </tr>';
                }
                
                $pagination_html = '';
                if ($total_pages > 1) {
                    $pagination_html .= '<div class="pagination">';
                    $pagination_html .= '<button class="btn btn-secondary" onclick="changePage(' . max(1, $page - 1) . ')" ' . ($page <= 1 ? 'disabled' : '') . '>';
                    $pagination_html .= '<i class="fas fa-chevron-left"></i> Previous';
                    $pagination_html .= '</button>';
                    
                    $pagination_html .= '<span style="display: flex; gap: 5px;">';
                    for ($i = 1; $i <= $total_pages; $i++) {
                        $active_class = $i === $page ? 'active' : '';
                        $pagination_html .= '<button class="btn ' . $active_class . '" onclick="changePage(' . $i . ')">';
                        $pagination_html .= $i;
                        $pagination_html .= '</button>';
                    }
                    $pagination_html .= '</span>';
                    
                    $pagination_html .= '<button class="btn btn-secondary" onclick="changePage(' . min($total_pages, $page + 1) . ')" ' . ($page >= $total_pages ? 'disabled' : '') . '>';
                    $pagination_html .= 'Next <i class="fas fa-chevron-right"></i>';
                    $pagination_html .= '</button>';
                    $pagination_html .= '</div>';
                }
                
                echo json_encode([
                    'success' => true,
                    'html' => $html,
                    'pagination' => $pagination_html,
                    'total_lessons' => $total_lessons,
                    'total_pages' => $total_pages,
                    'current_page' => $page
                ]);
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (PDOException $e) {
        error_log("Lessons AJAX error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    }
    exit;
}

// Function to add lesson
function addLesson($db, $school_id, $teacher_id) {
    $required_fields = ['class_id', 'teacher_id'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            return ['success' => false, 'message' => "Required field '$field' is missing"];
        }
    }
    
    $subject_ids = [];
    if (!empty($_POST['subject_ids'])) {
        $subject_ids = explode(',', $_POST['subject_ids']);
        $subject_ids = array_filter($subject_ids, 'strlen');
    }
    
    if (empty($subject_ids)) {
        return ['success' => false, 'message' => 'Please select at least one subject'];
    }
    
    $created_lessons = [];
    $errors = [];
    
    try {
        $db->beginTransaction();
        
        $created_by = $_SESSION['id'] ?? $_SESSION['teacher_id'] ?? null;
        
        if (!$created_by) {
            return ['success' => false, 'message' => 'User session not found'];
        }
        
        foreach ($subject_ids as $subject_id) {
            $check_query = "SELECT id FROM tbllessons WHERE subject_id = :subject_id AND class_id = :class_id AND school_id = :school_id";
            $check_params = [':subject_id' => $subject_id, ':class_id' => $_POST['class_id'], ':school_id' => $school_id];
            
            if (!empty($_POST['stream_id'])) {
                $check_query .= " AND (stream_id = :stream_id OR stream_id IS NULL)";
                $check_params[':stream_id'] = $_POST['stream_id'];
            } else {
                $check_query .= " AND stream_id IS NULL";
            }
            
            $check_stmt = $db->prepare($check_query);
            $check_stmt->execute($check_params);
            
            if ($check_stmt->rowCount() > 0) {
                $subject_name_query = "SELECT subject_name FROM tblsubjects WHERE id = :subject_id";
                $subject_name_stmt = $db->prepare($subject_name_query);
                $subject_name_stmt->bindParam(":subject_id", $subject_id);
                $subject_name_stmt->execute();
                $subject_name = $subject_name_stmt->fetchColumn();
                
                $errors[] = "Lesson already exists for subject: " . $subject_name;
                continue;
            }
            
            $query = "INSERT INTO tbllessons 
                     (subject_id, class_id, stream_id, teacher_id, school_id, created_by) 
                     VALUES (:subject_id, :class_id, :stream_id, :teacher_id, :school_id, :created_by)";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(":subject_id", $subject_id);
            $stmt->bindParam(":class_id", $_POST['class_id']);
            
            $stream_id = !empty($_POST['stream_id']) ? $_POST['stream_id'] : null;
            $stmt->bindParam(":stream_id", $stream_id, $stream_id ? PDO::PARAM_INT : PDO::PARAM_NULL);
            
            $stmt->bindParam(":teacher_id", $_POST['teacher_id']);
            $stmt->bindParam(":school_id", $school_id);
            $stmt->bindParam(":created_by", $created_by, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                $lesson_id = $db->lastInsertId();
                
                if (isset($_POST['assign_all_students']) && $_POST['assign_all_students'] == '1') {
                    $student_query = "SELECT id FROM tblstudents 
                                    WHERE class_id = :class_id AND school_id = :school_id";
                    
                    $params = [':class_id' => $_POST['class_id'], ':school_id' => $school_id];
                    
                    if (!empty($stream_id)) {
                        $student_query .= " AND StreamId = :stream_id";
                        $params[':stream_id'] = $stream_id;
                    }
                    
                    $student_stmt = $db->prepare($student_query);
                    $student_stmt->execute($params);
                    $all_students = $student_stmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    if (!empty($all_students)) {
                        $assign_query = "INSERT INTO tbllesson_students (lesson_id, student_id, school_id) VALUES (:lesson_id, :student_id, :school_id)";
                        $assign_stmt = $db->prepare($assign_query);
                        
                        foreach ($all_students as $student_id) {
                            $assign_stmt->bindParam(":lesson_id", $lesson_id);
                            $assign_stmt->bindParam(":student_id", $student_id);
                            $assign_stmt->bindParam(":school_id", $school_id);
                            $assign_stmt->execute();
                        }
                    }
                } elseif (!empty($_POST['student_ids'])) {
                    $student_ids = is_array($_POST['student_ids']) ? $_POST['student_ids'] : explode(',', $_POST['student_ids']);
                    
                    $assign_query = "INSERT INTO tbllesson_students (lesson_id, student_id, school_id) VALUES (:lesson_id, :student_id, :school_id)";
                    $assign_stmt = $db->prepare($assign_query);
                    
                    foreach ($student_ids as $student_id) {
                        if (!empty(trim($student_id))) {
                            $assign_stmt->bindParam(":lesson_id", $lesson_id);
                            $assign_stmt->bindParam(":student_id", $student_id);
                            $assign_stmt->bindParam(":school_id", $school_id);
                            $assign_stmt->execute();
                        }
                    }
                }
                
                $new_lesson_query = "SELECT l.id, s.subject_name, c.class_level, st.stream_name,
                                    CONCAT(t.firstname, ' ', t.lastname) as teacher_name,
                                    (SELECT COUNT(*) FROM tbllesson_students ls WHERE ls.lesson_id = l.id) as student_count
                                    FROM tbllessons l 
                                    JOIN tblsubjects s ON l.subject_id = s.id 
                                    JOIN tblclasses c ON l.class_id = c.id 
                                    LEFT JOIN tblstreams st ON l.stream_id = st.id
                                    LEFT JOIN tblteachers t ON l.teacher_id = t.id 
                                    WHERE l.id = :lesson_id AND l.school_id = :school_id";
                
                $new_lesson_stmt = $db->prepare($new_lesson_query);
                $new_lesson_stmt->bindParam(":lesson_id", $lesson_id);
                $new_lesson_stmt->bindParam(":school_id", $school_id);
                $new_lesson_stmt->execute();
                $created_lessons[] = $new_lesson_stmt->fetch(PDO::FETCH_ASSOC);
            }
        }
        
        if (empty($created_lessons) && !empty($errors)) {
            $db->rollBack();
            return ['success' => false, 'message' => implode('<br>', $errors)];
        }
        
        if (empty($created_lessons)) {
            $db->rollBack();
            return ['success' => false, 'message' => 'No lessons were created'];
        }
        
        $db->commit();
        
        $message = count($created_lessons) . ' lesson(s) assigned successfully';
        if (!empty($errors)) {
            $message .= '<br>' . implode('<br>', $errors);
        }
        
        return [
            'success' => true, 
            'message' => $message, 
            'lessons' => $created_lessons
        ];
        
    } catch (Exception $e) {
        $db->rollBack();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Function to update lesson
function updateLesson($db, $school_id) {
    $lesson_id = intval($_POST['lesson_id']);
    
    $check_query = "SELECT id FROM tbllessons WHERE id = :lesson_id AND school_id = :school_id";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(":lesson_id", $lesson_id);
    $check_stmt->bindParam(":school_id", $school_id);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() === 0) {
        return ['success' => false, 'message' => 'Lesson not found'];
    }
    
    $required_fields = ['subject_id', 'class_id', 'teacher_id'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            return ['success' => false, 'message' => "Required field '$field' is missing"];
        }
    }
    
    try {
        $db->beginTransaction();
        
        $stream_id = !empty($_POST['stream_id']) ? $_POST['stream_id'] : null;
        
        $query = "UPDATE tbllessons 
                 SET subject_id = :subject_id, class_id = :class_id, 
                     stream_id = :stream_id, teacher_id = :teacher_id,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :lesson_id AND school_id = :school_id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(":subject_id", $_POST['subject_id']);
        $stmt->bindParam(":class_id", $_POST['class_id']);
        $stmt->bindParam(":stream_id", $stream_id, $stream_id ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindParam(":teacher_id", $_POST['teacher_id']);
        $stmt->bindParam(":lesson_id", $lesson_id);
        $stmt->bindParam(":school_id", $school_id);
        
        if ($stmt->execute()) {
            $delete_query = "DELETE FROM tbllesson_students WHERE lesson_id = :lesson_id";
            $delete_stmt = $db->prepare($delete_query);
            $delete_stmt->bindParam(":lesson_id", $lesson_id);
            $delete_stmt->execute();
            
            if (isset($_POST['assign_all_students']) && $_POST['assign_all_students'] == '1') {
                $student_query = "SELECT id FROM tblstudents 
                                WHERE class_id = :class_id AND school_id = :school_id";
                
                $params = [':class_id' => $_POST['class_id'], ':school_id' => $school_id];
                
                if (!empty($stream_id)) {
                    $student_query .= " AND StreamId = :stream_id";
                    $params[':stream_id'] = $stream_id;
                }
                
                $student_stmt = $db->prepare($student_query);
                $student_stmt->execute($params);
                $all_students = $student_stmt->fetchAll(PDO::FETCH_COLUMN);
                
                if (!empty($all_students)) {
                    $assign_query = "INSERT INTO tbllesson_students (lesson_id, student_id, school_id) VALUES (:lesson_id, :student_id, :school_id)";
                    $assign_stmt = $db->prepare($assign_query);
                    
                    foreach ($all_students as $student_id) {
                        $assign_stmt->bindParam(":lesson_id", $lesson_id);
                        $assign_stmt->bindParam(":student_id", $student_id);
                        $assign_stmt->bindParam(":school_id", $school_id);
                        $assign_stmt->execute();
                    }
                }
            } elseif (!empty($_POST['student_ids'])) {
                $student_ids = is_array($_POST['student_ids']) ? $_POST['student_ids'] : explode(',', $_POST['student_ids']);
                
                $assign_query = "INSERT INTO tbllesson_students (lesson_id, student_id, school_id) VALUES (:lesson_id, :student_id, :school_id)";
                $assign_stmt = $db->prepare($assign_query);
                
                foreach ($student_ids as $student_id) {
                    if (!empty(trim($student_id))) {
                        $assign_stmt->bindParam(":lesson_id", $lesson_id);
                        $assign_stmt->bindParam(":student_id", $student_id);
                        $assign_stmt->bindParam(":school_id", $school_id);
                        $assign_stmt->execute();
                    }
                }
            }
            
            $updated_lesson_query = "SELECT l.id, s.subject_name, c.class_level, st.stream_name,
                                    CONCAT(t.firstname, ' ', t.lastname) as teacher_name,
                                    (SELECT COUNT(*) FROM tbllesson_students ls WHERE ls.lesson_id = l.id) as student_count
                                    FROM tbllessons l 
                                    JOIN tblsubjects s ON l.subject_id = s.id 
                                    JOIN tblclasses c ON l.class_id = c.id 
                                    LEFT JOIN tblstreams st ON l.stream_id = st.id
                                    LEFT JOIN tblteachers t ON l.teacher_id = t.id 
                                    WHERE l.id = :lesson_id AND l.school_id = :school_id";
            
            $updated_lesson_stmt = $db->prepare($updated_lesson_query);
            $updated_lesson_stmt->bindParam(":lesson_id", $lesson_id);
            $updated_lesson_stmt->bindParam(":school_id", $school_id);
            $updated_lesson_stmt->execute();
            $updated_lesson = $updated_lesson_stmt->fetch(PDO::FETCH_ASSOC);
            
            $db->commit();
            return [
                'success' => true, 
                'message' => 'Lesson updated successfully', 
                'lesson' => $updated_lesson
            ];
        } else {
            $db->rollBack();
            return ['success' => false, 'message' => 'Failed to update lesson'];
        }
        
    } catch (Exception $e) {
        $db->rollBack();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Function to delete lesson
function deleteLesson($db, $school_id) {
    $lesson_id = intval($_POST['lesson_id']);
    
    $check_query = "SELECT id FROM tbllessons WHERE id = :lesson_id AND school_id = :school_id";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(":lesson_id", $lesson_id);
    $check_stmt->bindParam(":school_id", $school_id);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() === 0) {
        return ['success' => false, 'message' => 'Lesson not found'];
    }
    
    try {
        $db->beginTransaction();
        
        $delete_students_query = "DELETE FROM tbllesson_students WHERE lesson_id = :lesson_id";
        $delete_students_stmt = $db->prepare($delete_students_query);
        $delete_students_stmt->bindParam(":lesson_id", $lesson_id);
        $delete_students_stmt->execute();
        
        $query = "DELETE FROM tbllessons WHERE id = :lesson_id AND school_id = :school_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":lesson_id", $lesson_id);
        $stmt->bindParam(":school_id", $school_id);
        
        if ($stmt->execute()) {
            $db->commit();
            return ['success' => true, 'message' => 'Lesson deleted successfully'];
        } else {
            $db->rollBack();
            return ['success' => false, 'message' => 'Failed to delete lesson'];
        }
        
    } catch (Exception $e) {
        $db->rollBack();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Fetch initial data for the page
try {
    $check_table_query = "SHOW TABLES LIKE 'tbllessons'";
    $table_exists = $db->query($check_table_query)->rowCount() > 0;
    
    if (!$table_exists) {
        $create_lessons_table = "CREATE TABLE tbllessons (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            subject_id INT(11) NOT NULL,
            class_id INT(11) NOT NULL,
            stream_id INT(11) DEFAULT NULL,
            teacher_id INT(11) NOT NULL,
            school_id INT(11) NOT NULL,
            created_by INT(11) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        $db->exec($create_lessons_table);
        
        $create_lesson_students_table = "CREATE TABLE tbllesson_students (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            lesson_id INT(11) NOT NULL,
            student_id INT(11) NOT NULL,
            school_id INT(11) NOT NULL,
            assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_lesson_student (lesson_id, student_id)
        )";
        $db->exec($create_lesson_students_table);
    }
    
    $teachers_query = "SELECT id, firstname, lastname FROM tblteachers WHERE school_id = :school_id ORDER BY firstname, lastname";
    $teachers_stmt = $db->prepare($teachers_query);
    $teachers_stmt->bindParam(":school_id", $school_id, PDO::PARAM_INT);
    $teachers_stmt->execute();
    $teachers = $teachers_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch stats data
    $stats_query = "SELECT 
        (SELECT COUNT(*) FROM tbllessons WHERE school_id = :school_id1) as total_lessons,
        (SELECT COUNT(DISTINCT teacher_id) FROM tbllessons WHERE school_id = :school_id2 AND teacher_id IS NOT NULL) as assigned_teachers,
        (SELECT COUNT(DISTINCT student_id) FROM tbllesson_students WHERE school_id = :school_id3) as total_students";
    
    $stats_stmt = $db->prepare($stats_query);
    $stats_stmt->bindParam(":school_id1", $school_id);
    $stats_stmt->bindParam(":school_id2", $school_id);
    $stats_stmt->bindParam(":school_id3", $school_id);
    $stats_stmt->execute();
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;
    
    $class_filter = isset($_GET['class_filter']) ? intval($_GET['class_filter']) : '';
    
    $lessons_query = "SELECT l.id, s.subject_name, c.class_level, st.stream_name,
                     CONCAT(t.firstname, ' ', t.lastname) as teacher_name,
                     (SELECT COUNT(*) FROM tbllesson_students ls WHERE ls.lesson_id = l.id) as student_count
                     FROM tbllessons l 
                     JOIN tblsubjects s ON l.subject_id = s.id 
                     JOIN tblclasses c ON l.class_id = c.id 
                     LEFT JOIN tblstreams st ON l.stream_id = st.id
                     LEFT JOIN tblteachers t ON l.teacher_id = t.id 
                     WHERE l.school_id = :school_id";
    
    $count_query = "SELECT COUNT(*) FROM tbllessons l 
                   JOIN tblsubjects s ON l.subject_id = s.id 
                   JOIN tblclasses c ON l.class_id = c.id 
                   WHERE l.school_id = :school_id";
    
    $params = [':school_id' => $school_id];
    
    if (!empty($class_filter)) {
        $lessons_query .= " AND l.class_id = :class_filter";
        $count_query .= " AND l.class_id = :class_filter";
        $params[':class_filter'] = $class_filter;
    }
    
    $lessons_query .= " ORDER BY c.class_level, s.subject_name LIMIT :limit OFFSET :offset";
    
    $count_stmt = $db->prepare($count_query);
    foreach ($params as $key => $value) {
        if ($key !== ':limit' && $key !== ':offset') {
            $count_stmt->bindValue($key, $value);
        }
    }
    $count_stmt->execute();
    $total_lessons = $count_stmt->fetchColumn();
    $total_pages = ceil($total_lessons / $limit);
    
    $params[':limit'] = $limit;
    $params[':offset'] = $offset;
    
    $lessons_stmt = $db->prepare($lessons_query);
    foreach ($params as $key => $value) {
        $lessons_stmt->bindValue($key, $value, $key === ':limit' || $key === ':offset' ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $lessons_stmt->execute();
    $lessons = $lessons_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Lessons page data fetch error: " . $e->getMessage());
    $lessons = [];
    $teachers = [];
    $total_lessons = 0;
    $total_pages = 1;
    $stats = ['total_lessons' => 0, 'assigned_teachers' => 0, 'total_students' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduScore - Lessons Management</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="images/logo.png" />
    <link rel="apple-touch-icon" href="images/logo.png">
    <link rel="stylesheet" href="assets/banner/banner.css">
    <style>
        /* All your CSS styles here (same as before) */
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
            --shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1);
            --border-radius: 12px;
            --transition: all 0.3s ease;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background: var(--bg-light); color: var(--text-dark); }
        .main-content { margin-left: 280px; min-height: 100vh; padding: 100px 2rem 2rem; transition: margin-left 0.3s ease; }
        @media (max-width: 992px) { .main-content { margin-left: 0; padding: 100px 1rem 1rem; } }
        .page-header { background: var(--bg-white); border-radius: var(--border-radius); padding: 2rem; margin-bottom: 2rem; box-shadow: var(--shadow); border-left: 4px solid var(--primary-blue); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; }
        .lessons-page-title { font-size: 1.8rem; font-weight: 700; display: flex; align-items: center; gap: 0.75rem; }
        .role-badge { background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue)); color: white; padding: 0.5rem 1rem; border-radius: 50px; font-size: 0.85rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.5rem; }
        .stats-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .stat-card { background: var(--bg-white); border-radius: var(--border-radius); padding: 1.5rem; box-shadow: var(--shadow); display: flex; align-items: center; gap: 1rem; border-top: 3px solid var(--primary-blue); }
        .stat-value { font-size: 1.5rem; font-weight: 700; }
        .action-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; gap: 1rem; flex-wrap: wrap; background: var(--bg-white); padding: 1rem 1.5rem; border-radius: var(--border-radius); }
        .btn { padding: 0.75rem 1.25rem; border: none; border-radius: var(--border-radius); font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 0.5rem; }
        .btn-primary { background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue)); color: white; }
        .btn-secondary { background: var(--light-blue); color: var(--primary-blue); }
        .tab-navigation { display: flex; gap: 10px; margin-bottom: 20px; background: var(--bg-white); padding: 10px; border-radius: var(--border-radius); }
        .tab-button { padding: 12px 24px; border: none; border-radius: var(--border-radius); font-weight: 600; background: var(--bg-light); cursor: pointer; display: flex; align-items: center; gap: 8px; }
        .tab-button.active { background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue)); color: white; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .filter-selects { display: flex; gap: 20px; margin-bottom: 20px; flex-wrap: wrap; }
        .filter-selects select { padding: 10px 14px; border-radius: var(--border-radius); border: 1px solid var(--border-color); }
        .lessons-table-container { background: var(--bg-white); border-radius: var(--border-radius); overflow: hidden; }
        .lessons-table { width: 100%; border-collapse: collapse; }
        .lessons-table th { background: var(--primary-blue); padding: 1rem; color: white; text-align: left; }
        .lessons-table td { padding: 1rem; border-bottom: 1px solid var(--border-color); }
        .actions { display: flex; gap: 0.5rem; }
        .action-btn-small { width: 32px; height: 32px; border: none; border-radius: 6px; cursor: pointer; }
        .edit-btn { background: var(--light-blue); color: var(--primary-blue); }
        .delete-btn { background: #fef2f2; color: var(--error-red); }
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 2000; }
        .modal-overlay.active { display: flex; }
        .modal { background: white; border-radius: var(--border-radius); width: 100%; max-width: 700px; max-height: 90vh; overflow-y: auto; }
        .modal-header { padding: 1.5rem; background: var(--primary-blue); color: white; border-radius: var(--border-radius) var(--border-radius) 0 0; display: flex; justify-content: space-between; }
        .modal-body { padding: 1.5rem; }
        .modal-footer { padding: 1.5rem; border-top: 1px solid var(--border-color); display: flex; gap: 1rem; justify-content: flex-end; }
        .form-group { margin-bottom: 1rem; }
        .form-label { display: block; margin-bottom: 0.5rem; font-weight: 500; }
        .form-control { width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: var(--border-radius); }
        .toast-container { position: fixed; top: 100px; right: 2rem; z-index: 3000; }
        .toast { background: white; border-radius: var(--border-radius); padding: 1rem; margin-bottom: 1rem; box-shadow: var(--shadow-lg); border-left: 4px solid var(--success-green); display: flex; align-items: center; gap: 1rem; }
        .empty-state { text-align: center; padding: 4rem; color: var(--text-light); }
        .pagination { display: flex; gap: 10px; margin-top: 1rem; justify-content: center; }
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
                <h1 class="lessons-page-title"><i class="fas fa-book-open"></i> Lessons Management</h1>
                <span class="role-badge"><i class="fas fa-<?php echo $isSuperAdmin ? 'crown' : 'user-tag'; ?>"></i> <?php echo htmlspecialchars($currentUserRole ?? 'User'); ?></span>
            </div>
        </div>

        <div class="stats-container">
            <div class="stat-card"><div class="stat-icon total"><i class="fas fa-book"></i></div><div class="stat-content"><div class="stat-value" id="totalLessons"><?php echo $stats['total_lessons'] ?? 0; ?></div><div class="stat-label">Total Lessons</div></div></div>
            <div class="stat-card"><div class="stat-icon active"><i class="fas fa-chalkboard-teacher"></i></div><div class="stat-content"><div class="stat-value" id="assignedTeachers"><?php echo $stats['assigned_teachers'] ?? 0; ?></div><div class="stat-label">Assigned Teachers</div></div></div>
            <div class="stat-card"><div class="stat-icon inactive"><i class="fas fa-users"></i></div><div class="stat-content"><div class="stat-value" id="totalStudents"><?php echo $stats['total_students'] ?? 0; ?></div><div class="stat-label">Enrolled Students</div></div></div>
        </div>

        <div class="action-bar">
            <div class="lessons-search-box"><i class="fas fa-search lessons-search-icon"></i><input type="text" class="lessons-search-input" id="searchInput" placeholder="Search lessons..."></div>
            <div class="action-buttons"><?php if ($canCreate): ?><button class="btn btn-primary" id="addLessonBtn"><i class="fas fa-plus"></i> Assign Lesson</button><?php endif; ?></div>
        </div>

        <div class="tab-navigation">
            <button class="tab-button active" data-tab="lessons-tab"><i class="fas fa-book"></i> Lessons</button>
            <button class="tab-button" data-tab="students-tab"><i class="fas fa-users"></i> Students</button>
        </div>

        <div id="lessons-tab" class="tab-content active">
            <div class="filter-selects">
                <div><label>Academic Level</label><select class="filter-select" id="academicLevelFilter"><option value="Primary">Primary</option><option value="Junior Secondary">Junior Secondary</option><option value="Secondary">Secondary</option></select></div>
                <div><label>Filter by Class</label><select class="filter-select" id="classFilter" disabled><option value="">-- Loading classes --</option></select></div>
            </div>
            <div class="lessons-table-container">
                <div class="table-responsive">
                    <table class="lessons-table">
                        <thead><tr><th><i class="fas fa-book"></i> Subject</th><th><i class="fas fa-graduation-cap"></i> Class</th><th><i class="fas fa-stream"></i> Stream</th><th><i class="fas fa-chalkboard-teacher"></i> Teacher</th><th><i class="fas fa-users"></i> Students</th><th><i class="fas fa-cogs"></i> Actions</th></tr></thead>
                        <tbody id="lessonsTableBody">
                            <?php if (!empty($lessons)): foreach ($lessons as $lesson): ?>
                            <tr data-lesson-id="<?php echo $lesson['id']; ?>">
                                <td><?php echo htmlspecialchars($lesson['subject_name']); ?></td>
                                <td><?php echo htmlspecialchars($lesson['class_level']); ?></td>
                                <td><?php echo htmlspecialchars($lesson['stream_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($lesson['teacher_name'] ?? 'Not Assigned'); ?></td>
                                <td><?php echo htmlspecialchars($lesson['student_count']); ?> students</td>
                                <td><div class="actions"><?php if ($canEdit): ?><button class="action-btn-small edit-btn" onclick="editLesson(<?php echo $lesson['id']; ?>)"><i class="fas fa-edit"></i></button><?php endif; ?><?php if ($canDelete): ?><button class="action-btn-small delete-btn" onclick="showDeleteModal(<?php echo $lesson['id']; ?>)"><i class="fas fa-trash"></i></button><?php endif; ?></div></td>
                            </tr>
                            <?php endforeach; else: ?>
                            <tr><td colspan="6"><div class="empty-state"><i class="fas fa-book"></i><h3>No Lessons Found</h3><p>Get started by assigning your first lesson</p><?php if ($canCreate): ?><button class="btn btn-primary" onclick="openAddLessonModal()"><i class="fas fa-plus"></i> Assign Lesson</button><?php endif; ?></div></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php if ($total_pages > 1): ?>
            <div class="pagination"><button class="btn btn-secondary" onclick="changePage(<?php echo max(1, $page - 1); ?>)" <?php echo $page <= 1 ? 'disabled' : ''; ?>><i class="fas fa-chevron-left"></i> Previous</button><span style="display: flex; gap: 5px;"><?php for ($i = 1; $i <= $total_pages; $i++): ?><button class="btn <?php echo $i === $page ? 'active' : ''; ?>" onclick="changePage(<?php echo $i; ?>)"><?php echo $i; ?></button><?php endfor; ?></span><button class="btn btn-secondary" onclick="changePage(<?php echo min($total_pages, $page + 1); ?>)" <?php echo $page >= $total_pages ? 'disabled' : ''; ?>>Next <i class="fas fa-chevron-right"></i></button></div>
            <?php endif; ?>
        </div>

        <div id="students-tab" class="tab-content">
            <div class="filter-selects">
                <div><label>Class</label><select class="filter-select" id="studentClassFilter"><option value="">Select Class</option></select></div>
                <div><label>Stream</label><select class="filter-select" id="studentStreamFilter" disabled><option value="">Select Stream</option></select></div>
            </div>
            <div class="lessons-table-container">
                <div class="table-responsive">
                    <table class="lessons-table student-table" id="studentsTable">
                        <thead id="studentsTableHead"><tr><th><i class="fas fa-user"></i> Student Name</th><th><i class="fas fa-id-card"></i> Admission No</th></tr></thead>
                        <tbody id="studentsTableBody"><tr><td colspan="2"><div class="empty-state"><i class="fas fa-users"></i><h3>No Data to Display</h3><p>Please select a class to view student assignments</p></div></td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <?php if ($canCreate || $canEdit): ?>
    <div class="modal-overlay" id="lessonModal"><div class="modal"><div class="modal-header"><h3 class="modal-title"><i class="fas fa-book"></i> <span id="modalTitle">Assign Lesson</span></h3><button class="close-modal" id="closeModal"><i class="fas fa-times"></i></button></div><form id="lessonForm"><div class="modal-body"><input type="hidden" id="lessonId" name="lesson_id"><div class="form-group"><label class="form-label required">Class</label><select class="form-control" id="classSelect" name="class_id" required><option value="">-- Select Class --</option></select></div><div class="form-group"><label class="form-label">Stream</label><select class="form-control" id="streamSelect" name="stream_id"><option value="">-- Select Stream (Optional) --</option></select></div><div class="form-group"><label class="form-label required">Subject</label><select class="form-control" id="subjectSelect" name="subject_id" required><option value="">-- Select Subject --</option></select></div><div class="form-group"><label class="form-label required">Teacher</label><select class="form-control" id="teacherSelect" name="teacher_id" required><option value="">-- Select Teacher --</option><?php foreach ($teachers as $teacher): ?><option value="<?php echo $teacher['id']; ?>"><?php echo htmlspecialchars($teacher['firstname'] . ' ' . $teacher['lastname']); ?></option><?php endforeach; ?></select></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" id="cancelBtn">Cancel</button><button type="submit" class="btn btn-primary" id="saveLessonBtn"><i class="fas fa-save"></i> <span id="saveBtnText">Assign Lesson</span></button></div></form></div></div>
    <?php endif; ?>

    <?php if ($canDelete): ?>
    <div class="modal-overlay" id="deleteModal"><div class="modal"><div class="modal-header"><h3 class="modal-title"><i class="fas fa-exclamation-triangle"></i> Confirm Delete</h3><button class="close-modal" id="closeDeleteModal"><i class="fas fa-times"></i></button></div><div class="modal-body"><p>Are you sure you want to delete this lesson assignment?</p><div class="delete-warning" style="background:#fef2f2;padding:0.75rem;border-radius:8px;margin-top:1rem;"><i class="fas fa-info-circle"></i> This will remove all student enrollments for this lesson.</div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" id="cancelDeleteBtn">Cancel</button><button type="button" class="btn btn-danger" id="confirmDeleteBtn"><i class="fas fa-trash"></i> Delete Lesson</button></div></div></div>
    <?php endif; ?>

    <div class="toast-container" id="toastContainer"></div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        let currentPage = <?php echo $page; ?>;
        let editingLessonId = null;
        let lessonToDeleteId = null;
        let currentAcademicLevel = '<?php echo $current_level; ?>';
        let isSubmitting = false;

        const PERMISSIONS = { canCreate: <?php echo $canCreate ? 'true' : 'false'; ?>, canEdit: <?php echo $canEdit ? 'true' : 'false'; ?>, canDelete: <?php echo $canDelete ? 'true' : 'false'; ?> };

        function showToast(title, message, type = 'success') { const toast = document.createElement('div'); toast.className = `toast ${type}`; toast.innerHTML = `<div class="toast-icon"><i class="fas fa-${type === 'success' ? 'check' : 'exclamation-triangle'}"></i></div><div class="toast-content"><div class="toast-title">${title}</div><div class="toast-message">${message}</div></div>`; document.getElementById('toastContainer').appendChild(toast); setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 300); }, 5000); }

        function loadClasses(academicLevel, targetElement, selectedClassId = null) {
            if (!targetElement) return;
            targetElement.disabled = true;
            targetElement.innerHTML = '<option value="">-- Loading classes... --</option>';
            fetch('lessons.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `action=get_classes_by_level&academic_level=${encodeURIComponent(academicLevel)}` })
            .then(response => response.json()).then(data => { if (data.success && data.classes.length) { targetElement.innerHTML = '<option value="">-- Select Class --</option>'; data.classes.forEach(cls => { const option = document.createElement('option'); option.value = cls.id; option.textContent = cls.class_level; if (selectedClassId && cls.id == selectedClassId) option.selected = true; targetElement.appendChild(option); }); targetElement.disabled = false; } else { targetElement.innerHTML = '<option value="">-- No classes found --</option>'; } }).catch(() => { targetElement.innerHTML = '<option value="">-- Error loading classes --</option>'; });
        }

        function loadSubjects(classId) {
            const subjectSelect = document.getElementById('subjectSelect');
            if (!subjectSelect || !classId) return;
            subjectSelect.innerHTML = '<option value="">-- Loading subjects... --</option>';
            fetch('lessons.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `action=get_subjects_by_class_stream&class_id=${classId}&stream_id=` })
            .then(response => response.json()).then(data => { if (data.success && data.subjects.length) { subjectSelect.innerHTML = '<option value="">-- Select Subject --</option>'; data.subjects.forEach(subject => { const option = document.createElement('option'); option.value = subject.id; option.textContent = subject.subject_name; subjectSelect.appendChild(option); }); subjectSelect.disabled = false; } else { subjectSelect.innerHTML = '<option value="">-- No subjects found --</option>'; } }).catch(() => { subjectSelect.innerHTML = '<option value="">-- Error loading subjects --</option>'; });
        }

        function loadStreams(classId) {
            const streamSelect = document.getElementById('streamSelect');
            if (!streamSelect || !classId) { if(streamSelect) streamSelect.innerHTML = '<option value="">-- Select Stream (Optional) --</option>'; return; }
            streamSelect.innerHTML = '<option value="">-- Loading streams... --</option>';
            fetch('lessons.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `action=get_streams_by_class&class_id=${classId}` })
            .then(response => response.json()).then(data => { if (data.success && data.streams.length) { streamSelect.innerHTML = '<option value="">-- Select Stream (Optional) --</option>'; data.streams.forEach(stream => { const option = document.createElement('option'); option.value = stream.id; option.textContent = stream.stream_name; streamSelect.appendChild(option); }); streamSelect.disabled = false; } else { streamSelect.innerHTML = '<option value="">-- Select Stream (Optional) --</option>'; } }).catch(() => { streamSelect.innerHTML = '<option value="">-- Select Stream (Optional) --</option>'; });
        }

        function openAddLessonModal() { if (!PERMISSIONS.canCreate) { showToast('Access Denied', 'You do not have permission', 'error'); return; } document.getElementById('modalTitle').textContent = 'Assign Lesson'; document.getElementById('saveBtnText').textContent = 'Assign Lesson'; document.getElementById('lessonForm').reset(); document.getElementById('lessonId').value = ''; editingLessonId = null; loadClasses(currentAcademicLevel, document.getElementById('classSelect')); document.getElementById('lessonModal').classList.add('active'); document.body.style.overflow = 'hidden'; }

        function editLesson(lessonId) { if (!PERMISSIONS.canEdit) { showToast('Access Denied', 'You do not have permission', 'error'); return; } editingLessonId = lessonId; fetch('lessons.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `action=get_lesson&lesson_id=${lessonId}` }).then(res => res.json()).then(data => { if (data.success) { const lesson = data.lesson; document.getElementById('modalTitle').textContent = 'Edit Lesson'; document.getElementById('saveBtnText').textContent = 'Update Lesson'; document.getElementById('lessonId').value = lesson.id; loadClasses(currentAcademicLevel, document.getElementById('classSelect'), lesson.class_id); setTimeout(() => { document.getElementById('subjectSelect').value = lesson.subject_id; document.getElementById('teacherSelect').value = lesson.teacher_id; loadStreams(lesson.class_id); setTimeout(() => { if(lesson.stream_id) document.getElementById('streamSelect').value = lesson.stream_id; }, 100); }, 100); document.getElementById('lessonModal').classList.add('active'); document.body.style.overflow = 'hidden'; } else { showToast('Error', data.message, 'error'); } }).catch(() => { showToast('Error', 'Failed to load lesson', 'error'); }); }

        function showDeleteModal(lessonId) { if (!PERMISSIONS.canDelete) { showToast('Access Denied', 'You do not have permission', 'error'); return; } lessonToDeleteId = lessonId; document.getElementById('deleteModal').classList.add('active'); document.body.style.overflow = 'hidden'; }

        function closeModals() { document.getElementById('lessonModal')?.classList.remove('active'); document.getElementById('deleteModal')?.classList.remove('active'); document.body.style.overflow = ''; }

        function saveLesson() { if (isSubmitting) return; const isEdit = editingLessonId !== null; if ((isEdit && !PERMISSIONS.canEdit) || (!isEdit && !PERMISSIONS.canCreate)) { showToast('Access Denied', 'You do not have permission', 'error'); return; } const formData = new FormData(document.getElementById('lessonForm')); formData.append('action', isEdit ? 'update_lesson' : 'add_lesson'); if (!isEdit) formData.append('subject_ids', document.getElementById('subjectSelect').value); isSubmitting = true; fetch('lessons.php', { method: 'POST', body: new URLSearchParams(formData) }).then(res => res.json()).then(data => { if (data.success) { showToast('Success', data.message, 'success'); closeModals(); loadLessonsAjax(); updateStats(); } else { showToast('Error', data.message, 'error'); } }).catch(() => { showToast('Error', 'Failed to save', 'error'); }).finally(() => { isSubmitting = false; }); }

        function confirmDelete() { if (!PERMISSIONS.canDelete) return; fetch('lessons.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `action=delete_lesson&lesson_id=${lessonToDeleteId}` }).then(res => res.json()).then(data => { if (data.success) { showToast('Success', data.message, 'success'); closeModals(); loadLessonsAjax(); updateStats(); } else { showToast('Error', data.message, 'error'); } }).catch(() => { showToast('Error', 'Failed to delete', 'error'); }); }

        function loadLessonsAjax(classId = '', page = 1) { const tbody = document.getElementById('lessonsTableBody'); if (!tbody) return; tbody.innerHTML = '<tr><td colspan="6"><div class="loading-state"><i class="fas fa-spinner fa-spin"></i> Loading lessons...</div></td></tr>'; const formData = new FormData(); formData.append('action', 'get_lessons_ajax'); formData.append('page', page); if (classId) formData.append('class_filter', classId); fetch('lessons.php', { method: 'POST', body: formData }).then(res => res.json()).then(data => { if (data.success) { tbody.innerHTML = data.html; const paginationDiv = document.querySelector('#lessons-tab .pagination'); if (data.pagination && paginationDiv) paginationDiv.outerHTML = data.pagination; else if (paginationDiv && !data.pagination) paginationDiv.remove(); } else { showToast('Error', 'Failed to load lessons', 'error'); } }).catch(() => { showToast('Error', 'Failed to load lessons', 'error'); }); }

        function changePage(page) { const classFilter = document.getElementById('classFilter')?.value || ''; loadLessonsAjax(classFilter, page); }

        function updateStats() { fetch('lessons.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'action=get_lessons_stats' }).then(res => res.json()).then(data => { if (data.success) { document.getElementById('totalLessons').textContent = data.stats.total_lessons; document.getElementById('assignedTeachers').textContent = data.stats.assigned_teachers; document.getElementById('totalStudents').textContent = data.stats.total_students; } }); }

        function loadStudentAssignments() { const classId = document.getElementById('studentClassFilter')?.value; const streamId = document.getElementById('studentStreamFilter')?.value || ''; const tbody = document.getElementById('studentsTableBody'); if (!classId) { tbody.innerHTML = '<tr><td colspan="2"><div class="empty-state"><i class="fas fa-users"></i><h3>No Data to Display</h3><p>Please select a class to view student assignments</p></div></td></tr>'; return; } tbody.innerHTML = '<tr><td colspan="2"><div class="loading-state"><i class="fas fa-spinner fa-spin"></i> Loading students...</div></td></tr>'; fetch('lessons.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `action=get_student_subject_assignments&class_id=${classId}&stream_id=${streamId}` }).then(res => res.json()).then(data => { if (data.success && data.students && data.subjects) { const students = data.students, subjects = data.subjects, assignments = data.student_assignments; let html = '<thead><tr><th>Student Name</th><th>Admission No</th>'; subjects.forEach(s => { html += `<th class="subject-column" data-subject-id="${s.id}">${escapeHtml(s.subject_name)}</th>`; }); html += '</tr></thead><tbody>'; students.forEach(student => { const fullName = [student.FirstName, student.SecondName, student.LastName].filter(n => n && n.trim()).join(' '); html += `<tr><td class="student-name-cell"><i class="fas fa-user-graduate"></i> ${escapeHtml(fullName)}</td><td>${escapeHtml(student.AdmNo || 'N/A')}</td>`; subjects.forEach(subject => { const isAssigned = assignments[student.id] && assignments[student.id].includes(parseInt(subject.id)); html += `<td class="subject-cell"><input type="checkbox" class="student-checkbox" data-student-id="${student.id}" data-subject-id="${subject.id}" ${isAssigned ? 'checked' : ''} ${!PERMISSIONS.canEdit ? 'disabled' : ''} onchange="toggleStudentAssignment(this, ${student.id}, ${subject.id})"></td>`; }); html += '</tr>'; }); html += '</tbody>'; document.getElementById('studentsTable').innerHTML = html; } else { tbody.innerHTML = '<tr><td colspan="2"><div class="empty-state"><i class="fas fa-info-circle"></i><h3>No Data Available</h3><p>No students or subjects found</p></div></td></tr>'; } }).catch(() => { tbody.innerHTML = '<tr><td colspan="2"><div class="empty-state"><i class="fas fa-exclamation-triangle"></i><h3>Error</h3><p>Failed to load data</p></div></td></tr>'; }); }

        function toggleStudentAssignment(checkbox, studentId, subjectId) { if (!PERMISSIONS.canEdit && !<?php echo $canAssignStudents ? 'true' : 'false'; ?>) { showToast('Access Denied', 'You do not have permission', 'error'); checkbox.checked = !checkbox.checked; return; } const isChecked = checkbox.checked; checkbox.disabled = true; fetch('lessons.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `action=update_student_assignment&student_id=${studentId}&subject_id=${subjectId}&assign=${isChecked}` }).then(res => res.json()).then(data => { if (!data.success) { checkbox.checked = !isChecked; showToast('Error', data.message, 'error'); } else { updateStats(); } }).catch(() => { checkbox.checked = !isChecked; showToast('Error', 'Failed to update assignment', 'error'); }).finally(() => { checkbox.disabled = false; }); }

        function escapeHtml(text) { const div = document.createElement('div'); div.textContent = text; return div.innerHTML; }

        document.addEventListener('DOMContentLoaded', function() { loadClasses(currentAcademicLevel, document.getElementById('classFilter'), <?php echo isset($class_filter) && $class_filter !== '' ? $class_filter : 'null'; ?>); loadClasses(currentAcademicLevel, document.getElementById('studentClassFilter')); document.getElementById('addLessonBtn')?.addEventListener('click', openAddLessonModal); document.getElementById('closeModal')?.addEventListener('click', closeModals); document.getElementById('cancelBtn')?.addEventListener('click', closeModals); document.getElementById('closeDeleteModal')?.addEventListener('click', closeModals); document.getElementById('cancelDeleteBtn')?.addEventListener('click', closeModals); document.getElementById('confirmDeleteBtn')?.addEventListener('click', confirmDelete); document.getElementById('lessonForm')?.addEventListener('submit', function(e) { e.preventDefault(); saveLesson(); }); document.getElementById('academicLevelFilter')?.addEventListener('change', function() { currentAcademicLevel = this.value; loadClasses(currentAcademicLevel, document.getElementById('classFilter')); loadClasses(currentAcademicLevel, document.getElementById('studentClassFilter')); }); document.getElementById('classFilter')?.addEventListener('change', function() { loadLessonsAjax(this.value, 1); }); document.getElementById('studentClassFilter')?.addEventListener('change', function() { const classId = this.value; const streamSelect = document.getElementById('studentStreamFilter'); if (classId) { streamSelect.innerHTML = '<option value="">Select Stream</option>'; streamSelect.disabled = false; fetch('lessons.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `action=get_streams_by_class&class_id=${classId}` }).then(res => res.json()).then(data => { if (data.success && data.streams.length) { data.streams.forEach(stream => { const option = document.createElement('option'); option.value = stream.id; option.textContent = stream.stream_name; streamSelect.appendChild(option); }); } }); } else { streamSelect.innerHTML = '<option value="">Select Stream</option>'; streamSelect.disabled = true; } loadStudentAssignments(); }); document.getElementById('studentStreamFilter')?.addEventListener('change', loadStudentAssignments); document.getElementById('classSelect')?.addEventListener('change', function() { loadStreams(this.value); loadSubjects(this.value); }); document.getElementById('searchInput')?.addEventListener('input', function() { const term = this.value.toLowerCase(); document.querySelectorAll('#lessonsTableBody tr[data-lesson-id]').forEach(row => { row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none'; }); }); document.querySelectorAll('.tab-button').forEach(btn => { btn.addEventListener('click', function() { const tabId = this.getAttribute('data-tab'); document.querySelectorAll('.tab-button').forEach(b => b.classList.remove('active')); this.classList.add('active'); document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active')); document.getElementById(tabId).classList.add('active'); if (tabId === 'students-tab' && document.getElementById('studentClassFilter').value) loadStudentAssignments(); }); }); });
    </script>
</body>
</html>