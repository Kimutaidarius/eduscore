<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/config.php';
require_once 'includes/PermissionHelper.php';
require_once 'includes/session_timeout.php'; 

// Detect AJAX
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

// Check if user has permission to view grading page
$permissionHelper->requireAnyPermission(['gradingView', 'gradingViewAll'], 'dashboard.php');

// Determine which actions are allowed based on permissions
$canCreate = $permissionHelper->hasPermission('gradingCreate');
$canEdit = $permissionHelper->hasPermission('gradingEdit');
$canDelete = $permissionHelper->hasPermission('gradingDelete');
$canViewAll = $permissionHelper->hasPermission('gradingViewAll');
$canUseDefaults = $permissionHelper->hasPermission('gradingUseDefaults');
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

// ==================== NEW 8-LEVEL CBE/KJSEA GRADING SYSTEM ====================
// This is the official Kenyan CBE grading system for KJSEA (Grade 9) and KPSEA (Grade 6)
// Uses 8 Achievement Levels (AL 1-8) based on the competency-based curriculum
$default_grades = [
    // Exceeding Expectations (EE) - Top performers
    ['al_level' => 8, 'lower_limit' => 90, 'upper_limit' => 100, 'grade' => 'EE1', 'points' => 8.00, 'remarks' => 'Exceeding Expectations - Outstanding Performance', 'cbc_level' => 8, 'cbc_level_name' => 'Exceeding Expectations (High)'],
    ['al_level' => 7, 'lower_limit' => 75, 'upper_limit' => 89, 'grade' => 'EE2', 'points' => 7.00, 'remarks' => 'Exceeding Expectations - Excellent Performance', 'cbc_level' => 7, 'cbc_level_name' => 'Exceeding Expectations (Standard)'],
    
    // Meeting Expectations (ME) - Competent performers
    ['al_level' => 6, 'lower_limit' => 58, 'upper_limit' => 74, 'grade' => 'ME1', 'points' => 6.00, 'remarks' => 'Meeting Expectations - Good Performance', 'cbc_level' => 6, 'cbc_level_name' => 'Meeting Expectations (High)'],
    ['al_level' => 5, 'lower_limit' => 41, 'upper_limit' => 57, 'grade' => 'ME2', 'points' => 5.00, 'remarks' => 'Meeting Expectations - Satisfactory Performance', 'cbc_level' => 5, 'cbc_level_name' => 'Meeting Expectations (Standard)'],
    
    // Approaching Expectations (AE) - Developing but not yet meeting
    ['al_level' => 4, 'lower_limit' => 31, 'upper_limit' => 40, 'grade' => 'AE1', 'points' => 4.00, 'remarks' => 'Approaching Expectations - Developing', 'cbc_level' => 4, 'cbc_level_name' => 'Approaching Expectations (High)'],
    ['al_level' => 3, 'lower_limit' => 21, 'upper_limit' => 30, 'grade' => 'AE2', 'points' => 3.00, 'remarks' => 'Approaching Expectations - Progressing', 'cbc_level' => 3, 'cbc_level_name' => 'Approaching Expectations (Standard)'],
    
    // Below Expectations (BE) - Needs significant improvement
    ['al_level' => 2, 'lower_limit' => 11, 'upper_limit' => 20, 'grade' => 'BE1', 'points' => 2.00, 'remarks' => 'Below Expectations - Minimal Achievement', 'cbc_level' => 2, 'cbc_level_name' => 'Below Expectations (Standard)'],
    ['al_level' => 1, 'lower_limit' => 0, 'upper_limit' => 10, 'grade' => 'BE2', 'points' => 1.00, 'remarks' => 'Below Expectations - Basic Achievement (No zero tolerance)', 'cbc_level' => 1, 'cbc_level_name' => 'Below Expectations (Basic)']
];

// Legacy 4-level scale for backward compatibility (used if no custom grades exist)
$legacy_default_grades = [
    ['lower_limit' => 0, 'upper_limit' => 24, 'grade' => 'BE', 'points' => 1.00, 'remarks' => 'Below Expectation'],
    ['lower_limit' => 25, 'upper_limit' => 49, 'grade' => 'AE', 'points' => 2.00, 'remarks' => 'Approaching Expectation'],
    ['lower_limit' => 50, 'upper_limit' => 74, 'grade' => 'ME', 'points' => 3.00, 'remarks' => 'Meet Expectation'],
    ['lower_limit' => 75, 'upper_limit' => 100, 'grade' => 'EE', 'points' => 4.00, 'remarks' => 'Exceeding Expectation']
];

// Determine which default set to use (use new 8-level system as primary)
$active_default_grades = $default_grades;

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'add_grade':
                if (!$canCreate) {
                    echo json_encode(['success' => false, 'message' => 'You do not have permission to add custom grades']);
                    break;
                }
                $response = addGrade($db, $school_id);
                echo json_encode($response);
                break;
                
            case 'update_grade':
                if (!$canEdit) {
                    echo json_encode(['success' => false, 'message' => 'You do not have permission to edit custom grades']);
                    break;
                }
                $response = updateGrade($db, $school_id);
                echo json_encode($response);
                break;
                
            case 'delete_grade':
                if (!$canDelete) {
                    echo json_encode(['success' => false, 'message' => 'You do not have permission to delete custom grades']);
                    break;
                }
                $response = deleteGrade($db, $school_id);
                echo json_encode($response);
                break;
                
            case 'get_grade':
                if (!$permissionHelper->hasAnyPermission(['gradingView', 'gradingViewAll'])) {
                    echo json_encode(['success' => false, 'message' => 'You do not have permission to view grades']);
                    break;
                }
                $grade_id = intval($_POST['grade_id']);
                $query = "SELECT * FROM tblgradingscale 
                         WHERE id = :grade_id AND school_id = :school_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(":grade_id", $grade_id);
                $stmt->bindParam(":school_id", $school_id);
                $stmt->execute();
                $grade = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($grade) {
                    echo json_encode(['success' => true, 'grade' => $grade]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Grade not found']);
                }
                break;
                
            case 'get_classes_by_level':
                if (!$permissionHelper->hasAnyPermission(['gradingView', 'gradingViewAll'])) {
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
                
            case 'get_streams_by_class':
                if (!$permissionHelper->hasAnyPermission(['gradingView', 'gradingViewAll'])) {
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
                
            case 'get_grades_by_class_stream':
                if (!$permissionHelper->hasAnyPermission(['gradingView', 'gradingViewAll'])) {
                    echo json_encode(['success' => false, 'message' => 'You do not have permission to view grades']);
                    break;
                }
                $class_id = intval($_POST['class_id']);
                $stream_id = isset($_POST['stream_id']) && $_POST['stream_id'] !== '' ? intval($_POST['stream_id']) : null;
                
                $query = "SELECT * FROM tblgradingscale 
                         WHERE class_id = :class_id AND school_id = :school_id";
                
                $params = [':class_id' => $class_id, ':school_id' => $school_id];
                
                if ($stream_id !== null) {
                    $query .= " AND (stream_id = :stream_id OR stream_id IS NULL)";
                    $params[':stream_id'] = $stream_id;
                } else {
                    $query .= " AND stream_id IS NULL";
                }
                
                $query .= " ORDER BY lower_limit ASC";
                
                $stmt = $db->prepare($query);
                $stmt->execute($params);
                $grades = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Check if using new 8-level system or old 4-level
                $using_8_level = false;
                if (!empty($grades)) {
                    // Check if any grade has cbc_level > 4 or uses EE1/EE2 format
                    foreach ($grades as $g) {
                        if (($g['cbc_level'] ?? 0) > 4 || strpos($g['grade'], 'EE1') !== false || strpos($g['grade'], 'EE2') !== false) {
                            $using_8_level = true;
                            break;
                        }
                    }
                }
                
                echo json_encode([
                    'success' => true, 
                    'custom_grades' => $grades,
                    'default_grades' => $active_default_grades,
                    'has_custom_grades' => !empty($grades),
                    'using_8_level_system' => $using_8_level || empty($grades)
                ]);
                break;
                
            case 'save_default_grades':
                if (!$canCreate && !$canUseDefaults) {
                    echo json_encode(['success' => false, 'message' => 'You do not have permission to save default grades']);
                    break;
                }
                $class_id = intval($_POST['class_id']);
                $stream_id = isset($_POST['stream_id']) && $_POST['stream_id'] !== '' ? intval($_POST['stream_id']) : null;
                $use_8_level = isset($_POST['use_8_level']) && $_POST['use_8_level'] == 'true';
                
                $grades_to_save = $use_8_level ? $default_grades : $legacy_default_grades;
                
                try {
                    $db->beginTransaction();
                    
                    $delete_query = "DELETE FROM tblgradingscale 
                                    WHERE class_id = :class_id AND school_id = :school_id";
                    $params = [':class_id' => $class_id, ':school_id' => $school_id];
                    
                    if ($stream_id !== null) {
                        $delete_query .= " AND (stream_id = :stream_id OR stream_id IS NULL)";
                        $params[':stream_id'] = $stream_id;
                    } else {
                        $delete_query .= " AND stream_id IS NULL";
                    }
                    
                    $delete_stmt = $db->prepare($delete_query);
                    $delete_stmt->execute($params);
                    
                    $insert_query = "INSERT INTO tblgradingscale 
                                    (class_id, stream_id, lower_limit, upper_limit, grade, points, remarks, school_id, cbc_level, cbc_level_name, is_cbc) 
                                    VALUES (:class_id, :stream_id, :lower_limit, :upper_limit, :grade, :points, :remarks, :school_id, :cbc_level, :cbc_level_name, :is_cbc)";
                    
                    $insert_stmt = $db->prepare($insert_query);
                    
                    foreach ($grades_to_save as $grade) {
                        $cbc_level = $grade['al_level'] ?? null;
                        $cbc_level_name = $grade['cbc_level_name'] ?? null;
                        $is_cbc = $use_8_level ? 1 : 0;
                        
                        $insert_stmt->bindParam(":class_id", $class_id);
                        $insert_stmt->bindParam(":stream_id", $stream_id, $stream_id ? PDO::PARAM_INT : PDO::PARAM_NULL);
                        $insert_stmt->bindParam(":lower_limit", $grade['lower_limit']);
                        $insert_stmt->bindParam(":upper_limit", $grade['upper_limit']);
                        $insert_stmt->bindParam(":grade", $grade['grade']);
                        $insert_stmt->bindParam(":points", $grade['points']);
                        $insert_stmt->bindParam(":remarks", $grade['remarks']);
                        $insert_stmt->bindParam(":school_id", $school_id);
                        $insert_stmt->bindParam(":cbc_level", $cbc_level);
                        $insert_stmt->bindParam(":cbc_level_name", $cbc_level_name);
                        $insert_stmt->bindParam(":is_cbc", $is_cbc);
                        $insert_stmt->execute();
                    }
                    
                    $db->commit();
                    $system_name = $use_8_level ? '8-level CBE/KJSEA' : '4-level legacy';
                    echo json_encode(['success' => true, 'message' => "$system_name grading system saved as custom grades for this class"]);
                } catch (Exception $e) {
                    $db->rollBack();
                    echo json_encode(['success' => false, 'message' => 'Failed to save grades: ' . $e->getMessage()]);
                }
                break;
                
            case 'delete_custom_grades':
                if (!$canDelete) {
                    echo json_encode(['success' => false, 'message' => 'You do not have permission to delete custom grades']);
                    break;
                }
                $class_id = intval($_POST['class_id']);
                $stream_id = isset($_POST['stream_id']) && $_POST['stream_id'] !== '' ? intval($_POST['stream_id']) : null;
                
                try {
                    $db->beginTransaction();
                    
                    $delete_query = "DELETE FROM tblgradingscale 
                                    WHERE class_id = :class_id AND school_id = :school_id";
                    $params = [':class_id' => $class_id, ':school_id' => $school_id];
                    
                    if ($stream_id !== null) {
                        $delete_query .= " AND (stream_id = :stream_id OR stream_id IS NULL)";
                        $params[':stream_id'] = $stream_id;
                    } else {
                        $delete_query .= " AND stream_id IS NULL";
                    }
                    
                    $delete_stmt = $db->prepare($delete_query);
                    $delete_stmt->execute($params);
                    
                    $db->commit();
                    echo json_encode(['success' => true, 'message' => 'Custom grades deleted. System will use default CBE grades.']);
                } catch (Exception $e) {
                    $db->rollBack();
                    echo json_encode(['success' => false, 'message' => 'Failed to delete grades: ' . $e->getMessage()]);
                }
                break;
                
            case 'get_custom_grades_count':
                if (!$permissionHelper->hasAnyPermission(['gradingView', 'gradingViewAll'])) {
                    echo json_encode(['success' => false, 'message' => 'You do not have permission to view grades count']);
                    break;
                }
                $query = "SELECT COUNT(*) as total FROM tblgradingscale WHERE school_id = :school_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(":school_id", $school_id);
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'total_custom_grades' => $result['total']]);
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (PDOException $e) {
        error_log("Grading AJAX error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    }
    exit;
}

// Function to add custom grade
function addGrade($db, $school_id) {
    $required_fields = ['class_id', 'lower_limit', 'upper_limit', 'grade', 'points', 'remarks'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            return ['success' => false, 'message' => "Required field '$field' is missing"];
        }
    }
    
    $class_id = intval($_POST['class_id']);
    $lower_limit = intval($_POST['lower_limit']);
    $upper_limit = intval($_POST['upper_limit']);
    $grade = $_POST['grade'];
    $points = floatval($_POST['points']);
    $remarks = $_POST['remarks'];
    $stream_id = !empty($_POST['stream_id']) ? intval($_POST['stream_id']) : null;
    $cbc_level = !empty($_POST['cbc_level']) ? intval($_POST['cbc_level']) : null;
    $cbc_level_name = $_POST['cbc_level_name'] ?? null;
    $is_cbc = !empty($_POST['is_cbc']) ? 1 : 0;
    
    if ($lower_limit >= $upper_limit) {
        return ['success' => false, 'message' => 'Lower limit must be less than upper limit'];
    }
    
    if ($lower_limit < 0 || $lower_limit > 100) {
        return ['success' => false, 'message' => 'Lower limit must be between 0 and 100'];
    }
    
    if ($upper_limit < 0 || $upper_limit > 100) {
        return ['success' => false, 'message' => 'Upper limit must be between 0 and 100'];
    }
    
    $overlap_query = "SELECT * FROM tblgradingscale 
                     WHERE class_id = :class_id AND school_id = :school_id 
                     AND ((:lower_limit BETWEEN lower_limit AND upper_limit) 
                     OR (:upper_limit BETWEEN lower_limit AND upper_limit)
                     OR (lower_limit BETWEEN :lower_limit2 AND :upper_limit2))";
    
    $params = [
        ':class_id' => $class_id,
        ':school_id' => $school_id,
        ':lower_limit' => $lower_limit,
        ':upper_limit' => $upper_limit,
        ':lower_limit2' => $lower_limit,
        ':upper_limit2' => $upper_limit
    ];
    
    if ($stream_id !== null) {
        $overlap_query .= " AND (stream_id = :stream_id OR stream_id IS NULL)";
        $params[':stream_id'] = $stream_id;
    } else {
        $overlap_query .= " AND stream_id IS NULL";
    }
    
    $overlap_stmt = $db->prepare($overlap_query);
    $overlap_stmt->execute($params);
    
    if ($overlap_stmt->rowCount() > 0) {
        return ['success' => false, 'message' => 'Grade range overlaps with existing custom grade'];
    }
    
    try {
        $query = "INSERT INTO tblgradingscale 
                 (class_id, stream_id, lower_limit, upper_limit, grade, points, remarks, school_id, cbc_level, cbc_level_name, is_cbc) 
                 VALUES (:class_id, :stream_id, :lower_limit, :upper_limit, :grade, :points, :remarks, :school_id, :cbc_level, :cbc_level_name, :is_cbc)";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(":class_id", $class_id);
        $stmt->bindParam(":stream_id", $stream_id, $stream_id ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindParam(":lower_limit", $lower_limit);
        $stmt->bindParam(":upper_limit", $upper_limit);
        $stmt->bindParam(":grade", $grade);
        $stmt->bindParam(":points", $points);
        $stmt->bindParam(":remarks", $remarks);
        $stmt->bindParam(":school_id", $school_id);
        $stmt->bindParam(":cbc_level", $cbc_level);
        $stmt->bindParam(":cbc_level_name", $cbc_level_name);
        $stmt->bindParam(":is_cbc", $is_cbc);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Custom grade added successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to add custom grade'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Function to update custom grade
function updateGrade($db, $school_id) {
    $grade_id = intval($_POST['grade_id']);
    
    $check_query = "SELECT id FROM tblgradingscale WHERE id = :grade_id AND school_id = :school_id";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(":grade_id", $grade_id);
    $check_stmt->bindParam(":school_id", $school_id);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() === 0) {
        return ['success' => false, 'message' => 'Custom grade not found'];
    }
    
    $required_fields = ['class_id', 'lower_limit', 'upper_limit', 'grade', 'points', 'remarks'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            return ['success' => false, 'message' => "Required field '$field' is missing"];
        }
    }
    
    $class_id = intval($_POST['class_id']);
    $lower_limit = intval($_POST['lower_limit']);
    $upper_limit = intval($_POST['upper_limit']);
    $grade = $_POST['grade'];
    $points = floatval($_POST['points']);
    $remarks = $_POST['remarks'];
    $stream_id = !empty($_POST['stream_id']) ? intval($_POST['stream_id']) : null;
    $cbc_level = !empty($_POST['cbc_level']) ? intval($_POST['cbc_level']) : null;
    $cbc_level_name = $_POST['cbc_level_name'] ?? null;
    $is_cbc = !empty($_POST['is_cbc']) ? 1 : 0;
    
    if ($lower_limit >= $upper_limit) {
        return ['success' => false, 'message' => 'Lower limit must be less than upper limit'];
    }
    
    if ($lower_limit < 0 || $lower_limit > 100) {
        return ['success' => false, 'message' => 'Lower limit must be between 0 and 100'];
    }
    
    if ($upper_limit < 0 || $upper_limit > 100) {
        return ['success' => false, 'message' => 'Upper limit must be between 0 and 100'];
    }
    
    $overlap_query = "SELECT * FROM tblgradingscale 
                     WHERE class_id = :class_id AND school_id = :school_id AND id != :grade_id
                     AND ((:lower_limit BETWEEN lower_limit AND upper_limit) 
                     OR (:upper_limit BETWEEN lower_limit AND upper_limit)
                     OR (lower_limit BETWEEN :lower_limit2 AND :upper_limit2))";
    
    $params = [
        ':class_id' => $class_id,
        ':school_id' => $school_id,
        ':grade_id' => $grade_id,
        ':lower_limit' => $lower_limit,
        ':upper_limit' => $upper_limit,
        ':lower_limit2' => $lower_limit,
        ':upper_limit2' => $upper_limit
    ];
    
    if ($stream_id !== null) {
        $overlap_query .= " AND (stream_id = :stream_id OR stream_id IS NULL)";
        $params[':stream_id'] = $stream_id;
    } else {
        $overlap_query .= " AND stream_id IS NULL";
    }
    
    $overlap_stmt = $db->prepare($overlap_query);
    $overlap_stmt->execute($params);
    
    if ($overlap_stmt->rowCount() > 0) {
        return ['success' => false, 'message' => 'Grade range overlaps with existing custom grade'];
    }
    
    try {
        $query = "UPDATE tblgradingscale 
                 SET class_id = :class_id, stream_id = :stream_id, 
                     lower_limit = :lower_limit, upper_limit = :upper_limit,
                     grade = :grade, points = :points, remarks = :remarks,
                     cbc_level = :cbc_level, cbc_level_name = :cbc_level_name, is_cbc = :is_cbc
                 WHERE id = :grade_id AND school_id = :school_id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(":class_id", $class_id);
        $stmt->bindParam(":stream_id", $stream_id, $stream_id ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindParam(":lower_limit", $lower_limit);
        $stmt->bindParam(":upper_limit", $upper_limit);
        $stmt->bindParam(":grade", $grade);
        $stmt->bindParam(":points", $points);
        $stmt->bindParam(":remarks", $remarks);
        $stmt->bindParam(":cbc_level", $cbc_level);
        $stmt->bindParam(":cbc_level_name", $cbc_level_name);
        $stmt->bindParam(":is_cbc", $is_cbc);
        $stmt->bindParam(":grade_id", $grade_id);
        $stmt->bindParam(":school_id", $school_id);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Custom grade updated successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to update custom grade'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Function to delete custom grade
function deleteGrade($db, $school_id) {
    $grade_id = intval($_POST['grade_id']);
    
    $check_query = "SELECT id FROM tblgradingscale WHERE id = :grade_id AND school_id = :school_id";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(":grade_id", $grade_id);
    $check_stmt->bindParam(":school_id", $school_id);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() === 0) {
        return ['success' => false, 'message' => 'Custom grade not found'];
    }
    
    try {
        $query = "DELETE FROM tblgradingscale WHERE id = :grade_id AND school_id = :school_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":grade_id", $grade_id);
        $stmt->bindParam(":school_id", $school_id);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Custom grade deleted successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to delete custom grade'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Fetch initial data for the page
try {
    $check_table_query = "SHOW TABLES LIKE 'tblgradingscale'";
    $table_exists = $db->query($check_table_query)->rowCount() > 0;
    
    if (!$table_exists) {
        $create_grading_table = "CREATE TABLE tblgradingscale (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            class_id INT(11) NOT NULL,
            stream_id INT(11) DEFAULT NULL,
            lower_limit INT(3) NOT NULL,
            upper_limit INT(3) NOT NULL,
            grade VARCHAR(10) NOT NULL,
            points DECIMAL(3,2) NOT NULL,
            remarks VARCHAR(255) NOT NULL,
            school_id INT(11) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            cbc_level INT(2) DEFAULT NULL,
            cbc_level_name VARCHAR(100) DEFAULT NULL,
            is_cbc TINYINT(1) DEFAULT 0,
            UNIQUE KEY unique_grade_range (class_id, stream_id, lower_limit, upper_limit, school_id)
        )";
        $db->exec($create_grading_table);
    }
    
    $classes_query = "SELECT id, class_level, academic_level FROM tblclasses 
                     WHERE school_id = :school_id 
                     ORDER BY academic_level, 
                     CASE 
                        WHEN class_level LIKE 'Grade%' THEN CAST(SUBSTRING(class_level, 7) AS UNSIGNED)
                        WHEN class_level LIKE 'Form%' THEN CAST(SUBSTRING(class_level, 6) AS UNSIGNED) + 8
                        ELSE 999 
                     END";
    $classes_stmt = $db->prepare($classes_query);
    $classes_stmt->bindParam(":school_id", $school_id, PDO::PARAM_INT);
    $classes_stmt->execute();
    $all_classes = $classes_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $grouped_classes = [];
    foreach ($all_classes as $class) {
        $level = $class['academic_level'];
        if (!isset($grouped_classes[$level])) {
            $grouped_classes[$level] = [];
        }
        $grouped_classes[$level][] = [
            'id' => $class['id'],
            'class_name' => $class['class_level'],
            'class_level' => $class['class_level']
        ];
    }
    
    $streams_query = "SELECT id, stream_name, class_id FROM tblstreams WHERE school_id = :school_id ORDER BY stream_name";
    $streams_stmt = $db->prepare($streams_query);
    $streams_stmt->bindParam(":school_id", $school_id, PDO::PARAM_INT);
    $streams_stmt->execute();
    $streams = $streams_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $custom_grades_query = "SELECT g.*, c.class_level, s.stream_name
                    FROM tblgradingscale g
                    JOIN tblclasses c ON g.class_id = c.id
                    LEFT JOIN tblstreams s ON g.stream_id = s.id
                    WHERE g.school_id = :school_id
                    ORDER BY c.class_level, g.lower_limit";
    
    $custom_grades_stmt = $db->prepare($custom_grades_query);
    $custom_grades_stmt->bindParam(":school_id", $school_id);
    $custom_grades_stmt->execute();
    $custom_grades = $custom_grades_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_custom_grades = count($custom_grades);
    
} catch (PDOException $e) {
    error_log("Grading page data fetch error: " . $e->getMessage());
    $grouped_classes = [];
    $streams = [];
    $custom_grades = [];
    $all_classes = [];
    $total_custom_grades = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduScore - Grading Management | CBE/KJSEA System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="images/logo.png">
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
            --shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1);
            --border-radius: 12px;
            --transition: all 0.3s ease;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background: var(--bg-light); color: var(--text-dark); }
        .main-content { margin-left: 280px; min-height: 100vh; padding: 100px 2rem 2rem; transition: margin-left 0.3s ease; }
        @media (max-width: 992px) { .main-content { margin-left: 0; padding: 100px 1rem 1rem; } }
        .page-header { background: var(--bg-white); border-radius: var(--border-radius); padding: 2rem; margin-bottom: 2rem; box-shadow: var(--shadow); border-left: 4px solid var(--primary-blue); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; }
        .grading-page-title { font-size: 1.8rem; font-weight: 700; display: flex; align-items: center; gap: 0.75rem; }
        .role-badge { background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue)); color: white; padding: 0.5rem 1rem; border-radius: 50px; font-size: 0.85rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.5rem; }
        .permission-denied { background: #fef2f2; border: 1px solid #fecaca; color: var(--error-red); padding: 2rem; border-radius: var(--border-radius); text-align: center; margin: 2rem 0; }
        .info-banner { background: linear-gradient(135deg, #e8f4f8, #d1e7f0); border-radius: var(--border-radius); padding: 1.5rem; margin-bottom: 2rem; border-left: 4px solid var(--secondary-blue); display: flex; gap: 1rem; }
        .stats-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .stat-card { background: var(--bg-white); border-radius: var(--border-radius); padding: 1.5rem; box-shadow: var(--shadow); display: flex; align-items: center; gap: 1rem; border-top: 3px solid var(--primary-blue); }
        .stat-value { font-size: 1.5rem; font-weight: 700; }
        .action-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; gap: 1rem; flex-wrap: wrap; background: var(--bg-white); padding: 1rem 1.5rem; border-radius: var(--border-radius); }
        .btn { padding: 0.75rem 1.25rem; border: none; border-radius: var(--border-radius); font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; transition: var(--transition); }
        .btn-primary { background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue)); color: white; }
        .btn-secondary { background: var(--light-blue); color: var(--primary-blue); }
        .btn-success { background: linear-gradient(135deg, var(--success-green), #059669); color: white; }
        .btn-danger { background: linear-gradient(135deg, var(--error-red), #b91c1c); color: white; }
        .btn-warning { background: linear-gradient(135deg, var(--warning-orange), #d97706); color: white; }
        .filter-section { background: var(--bg-white); border-radius: var(--border-radius); padding: 1.5rem; margin-bottom: 2rem; box-shadow: var(--shadow); }
        .filter-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem; }
        .filter-group { display: flex; flex-direction: column; gap: 0.5rem; }
        .filter-label { font-size: 0.9rem; font-weight: 500; color: var(--text-dark); display: flex; align-items: center; gap: 0.5rem; }
        .filter-select { width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--border-color); border-radius: var(--border-radius); font-size: 0.9rem; background: var(--bg-white); cursor: pointer; }
        .filter-select:disabled { opacity: 0.5; cursor: not-allowed; }
        .default-grades-preview { background: #fefce8; border-radius: var(--border-radius); padding: 1.5rem; margin-bottom: 2rem; border-left: 4px solid #fbbf24; overflow: hidden; }
        .preview-title { font-size: 1rem; font-weight: 600; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }
        .default-grades-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; width: 100%; }
        .default-grade-item { background: white; padding: 1rem; border-radius: 8px; border: 1px solid #fde68a; display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; }
        .grade-badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; min-width: 50px; text-align: center; }
        .grade-EE1, .grade-EE2, .grade-EE { background: #bbf7d0; color: #166534; }
        .grade-ME1, .grade-ME2, .grade-ME { background: #fef08a; color: #854d0e; }
        .grade-AE1, .grade-AE2, .grade-AE { background: #fed7aa; color: #92400e; }
        .grade-BE1, .grade-BE2, .grade-BE { background: #fca5a5; color: #7f1d1d; }
        .points-circle { width: 36px; height: 36px; background: var(--primary-blue); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.9rem; }
        .rubric-points { display: flex; align-items: center; gap: 0.5rem; }
        .al-level-badge { background: #e0e7ff; color: #3730a3; padding: 0.25rem 0.5rem; border-radius: 12px; font-size: 0.7rem; font-weight: 600; }
        .grades-table-container { background: var(--bg-white); border-radius: var(--border-radius); box-shadow: var(--shadow); overflow: hidden; min-height: 400px; }
        .table-responsive { overflow-x: auto; }
        .grades-table { width: 100%; border-collapse: collapse; min-width: 1000px; }
        .grades-table th { background: var(--primary-blue); padding: 1rem; color: white; text-align: left; }
        .grades-table td { padding: 1rem; border-bottom: 1px solid var(--border-color); }
        .actions { display: flex; gap: 0.5rem; }
        .action-btn-small { width: 32px; height: 32px; border: none; border-radius: 6px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: var(--transition); }
        .edit-btn { background: var(--light-blue); color: var(--primary-blue); }
        .delete-btn { background: #fef2f2; color: var(--error-red); }
        .empty-state { text-align: center; padding: 4rem 2rem; color: var(--text-light); }
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 2000; padding: 1rem; }
        .modal-overlay.active { display: flex; }
        .modal { background: white; border-radius: var(--border-radius); width: 100%; max-width: 600px; max-height: 90vh; overflow-y: auto; }
        .modal-header { padding: 1.5rem; background: var(--primary-blue); color: white; border-radius: var(--border-radius) var(--border-radius) 0 0; display: flex; justify-content: space-between; align-items: center; }
        .modal-body { padding: 1.5rem; }
        .modal-footer { padding: 1.5rem; border-top: 1px solid var(--border-color); display: flex; gap: 1rem; justify-content: flex-end; }
        .form-group { margin-bottom: 1rem; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
        .form-label { display: block; margin-bottom: 0.5rem; font-weight: 500; }
        .form-label.required::after { content: '*'; color: var(--error-red); margin-left: 0.25rem; }
        .form-control { width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: var(--border-radius); font-size: 0.9rem; }
        .form-control.form-select { background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e"); background-position: right 0.75rem center; background-repeat: no-repeat; background-size: 16px 12px; padding-right: 2.5rem; appearance: none; }
        .system-toggle { background: #f3f4f6; border-radius: var(--border-radius); padding: 1rem; margin-bottom: 1rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; }
        .toggle-buttons { display: flex; gap: 0.5rem; }
        .toggle-btn { padding: 0.5rem 1rem; border: 1px solid var(--border-color); background: white; border-radius: var(--border-radius); cursor: pointer; transition: var(--transition); }
        .toggle-btn.active { background: var(--primary-blue); color: white; border-color: var(--primary-blue); }
        .delete-modal .modal-header { background: linear-gradient(135deg, var(--error-red), #b91c1c); }
        .delete-warning { background: #fef2f2; border: 1px solid #fecaca; color: var(--error-red); padding: 0.75rem; border-radius: 8px; margin-top: 1rem; display: flex; align-items: center; gap: 0.5rem; }
        .toast-container { position: fixed; top: 100px; right: 2rem; z-index: 3000; max-width: 400px; }
        .toast { background: white; border-radius: var(--border-radius); padding: 1rem; margin-bottom: 1rem; box-shadow: var(--shadow-lg); border-left: 4px solid var(--success-green); display: flex; align-items: center; gap: 1rem; animation: slideInRight 0.3s ease; }
        .toast.error { border-left-color: var(--error-red); }
        .toast.warning { border-left-color: var(--warning-orange); }
        @keyframes slideInRight { from { opacity: 0; transform: translateX(100%); } to { opacity: 1; transform: translateX(0); } }
        @keyframes spin { to { transform: rotate(360deg); } }
        @media (max-width: 768px) { .main-content { padding: 100px 1rem 1rem; } .filter-row { grid-template-columns: 1fr; } .modal { max-width: 95%; } .toast-container { right: 1rem; left: 1rem; max-width: none; } .form-row { grid-template-columns: 1fr; } }
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
                <h1 class="grading-page-title"><i class="fas fa-chart-bar"></i> Grading Management <span style="font-size: 0.8rem; background: var(--secondary-blue); padding: 0.25rem 0.75rem; border-radius: 20px;">CBE/KJSEA System</span></h1>
                <span class="role-badge"><i class="fas fa-<?php echo $isSuperAdmin ? 'crown' : 'user-tag'; ?>"></i> <?php echo htmlspecialchars($currentUserRole ?? 'User'); ?></span>
            </div>
            <p class="page-description">Configure custom grading scales for classes and streams based on the 8-level CBE/KJSEA system</p>
        </div>

        <?php if (!$permissionHelper->hasAnyPermission(['gradingView', 'gradingViewAll'])): ?>
            <div class="permission-denied">
                <i class="fas fa-lock"></i>
                <h3>Access Denied</h3>
                <p>You do not have permission to view grading management.</p>
                <p style="font-size: 0.9rem; margin-top: 0.5rem;">Please contact your system administrator if you need access.</p>
            </div>
        <?php else: ?>

        <div class="info-banner">
            <i class="fas fa-info-circle" style="font-size: 1.5rem;"></i>
            <div class="info-content">
                <h4>Kenyan CBE/KJSEA Grading System (8-Level Achievement Scale)</h4>
                <p>• <strong>EE (Exceeding Expectations)</strong> - AL 7-8: 75-100% | Outstanding to Excellent performance<br>
                • <strong>ME (Meeting Expectations)</strong> - AL 5-6: 41-74% | Good to Satisfactory performance<br>
                • <strong>AE (Approaching Expectations)</strong> - AL 3-4: 21-40% | Developing to Progressing<br>
                • <strong>BE (Below Expectations)</strong> - AL 1-2: 0-20% | Minimal to Basic achievement (No zero tolerance)<br>
                • The system uses this 8-level scale by default. You can customize it for any class/stream.</p>
            </div>
        </div>

        <div class="default-grades-preview">
            <h4 class="preview-title"><i class="fas fa-star"></i> Default 8-Level CBE/KJSEA Grading Scale (System Default)</h4>
            <div class="default-grades-grid">
                <?php foreach ($default_grades as $grade): $gradeClass = 'grade-' . $grade['grade']; ?>
                    <div class="default-grade-item">
                        <span class="al-level-badge">AL <?php echo $grade['al_level']; ?></span>
                        <div class="default-grade-range"><?php echo $grade['lower_limit']; ?>-<?php echo $grade['upper_limit']; ?>%</div>
                        <span class="grade-badge <?php echo $gradeClass; ?>"><?php echo $grade['grade']; ?></span>
                        <div class="default-grade-points"><?php echo $grade['points']; ?> pts</div>
                        <div class="default-grade-remarks" style="font-size: 0.7rem;"><?php echo substr($grade['remarks'], 0, 30); ?>...</div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="stats-container">
            <div class="stat-card"><div class="stat-icon total"><i class="fas fa-graduation-cap"></i></div><div class="stat-content"><div class="stat-value" id="totalClasses"><?php echo count($all_classes); ?></div><div class="stat-label">Total Classes</div></div></div>
            <div class="stat-card"><div class="stat-icon custom"><i class="fas fa-edit"></i></div><div class="stat-content"><div class="stat-value" id="customGradesCount"><?php echo $total_custom_grades; ?></div><div class="stat-label">Custom Grades</div></div></div>
            <div class="stat-card"><div class="stat-icon default"><i class="fas fa-layer-group"></i></div><div class="stat-content"><div class="stat-value">8</div><div class="stat-label">Achievement Levels</div></div></div>
        </div>

        <div class="action-bar">
            <div class="grading-search-box"><i class="fas fa-search grading-search-icon"></i><input type="text" class="grading-search-input" id="searchInput" placeholder="Search custom grades..."></div>
            <div class="action-buttons">
                <?php if ($canCreate): ?>
                    <button class="btn btn-primary" id="addGradeBtn"><i class="fas fa-plus"></i> Add Custom Grade</button>
                <?php endif; ?>
                <?php if ($permissionHelper->hasAnyPermission(['gradingView', 'gradingViewAll'])): ?>
                    <button class="btn btn-secondary" id="viewGradesBtn"><i class="fas fa-eye"></i> View Grades</button>
                <?php endif; ?>
                <?php if ($canCreate || $canUseDefaults): ?>
                    <button class="btn btn-success" id="useDefaultsBtn"><i class="fas fa-check"></i> Use 8-Level CBE</button>
                <?php endif; ?>
                <?php if ($canDelete): ?>
                    <button class="btn btn-danger" id="deleteCustomBtn"><i class="fas fa-trash"></i> Delete Custom Grades</button>
                <?php endif; ?>
            </div>
        </div>

        <div class="filter-section">
            <div class="filter-row">
                <div class="filter-group">
                    <label class="filter-label"><i class="fas fa-chalkboard-teacher"></i> Select Class</label>
                    <select id="selectClass" class="filter-select">
                        <option value="">-- Select Class --</option>
                        <?php foreach ($grouped_classes as $level => $classes): $level_label = ucwords(str_replace('_', ' ', $level)); ?>
                            <optgroup label="<?php echo $level_label; ?>">
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['class_level']); ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="filter-label"><i class="fas fa-stream"></i> Select Stream</label>
                    <select id="selectStream" class="filter-select" disabled><option value="">-- Select Class First --</option></select>
                </div>
            </div>
        </div>

        <div class="grades-table-container">
            <div class="table-responsive">
                <table class="grades-table" id="gradesTable">
                    <thead>
                        <tr>
                            <th><i class="fas fa-graduation-cap"></i> Class</th>
                            <th><i class="fas fa-stream"></i> Stream</th>
                            <th><i class="fas fa-level-down-alt"></i> AL Level</th>
                            <th><i class="fas fa-arrow-down"></i> Lower Limit</th>
                            <th><i class="fas fa-arrow-up"></i> Upper Limit</th>
                            <th><i class="fas fa-tag"></i> Grade</th>
                            <th><i class="fas fa-star"></i> Points</th>
                            <th><i class="fas fa-comment"></i> Remarks</th>
                            <th><i class="fas fa-cogs"></i> Actions</th>
                        </tr>
                    </thead>
                    <tbody id="gradesTableBody">
                        <?php if (!empty($custom_grades)): foreach ($custom_grades as $grade): $gradeClass = 'grade-' . $grade['grade']; ?>
                            <tr data-grade-id="<?php echo $grade['id']; ?>">
                                <td><?php echo htmlspecialchars($grade['class_level']); ?></td>
                                <td><?php echo htmlspecialchars($grade['stream_name'] ?? 'All Streams'); ?></td>
                                <td><?php if ($grade['cbc_level']): ?><span class="al-level-badge">AL <?php echo $grade['cbc_level']; ?></span><?php else: ?>—<?php endif; ?></td>
                                <td><strong><?php echo $grade['lower_limit']; ?>%</strong></td>
                                <td><strong><?php echo $grade['upper_limit']; ?>%</strong></td>
                                <td><span class="grade-badge <?php echo $gradeClass; ?>"><?php echo $grade['grade']; ?></span></td>
                                <td><div class="rubric-points"><div class="points-circle"><?php echo $grade['points']; ?></div><span>pts</span></div></td>
                                <td><?php echo htmlspecialchars($grade['remarks']); ?></td>
                                <td><div class="actions"><?php if ($canEdit): ?><button class="action-btn-small edit-btn" onclick="editGrade(<?php echo $grade['id']; ?>)"><i class="fas fa-edit"></i></button><?php endif; ?><?php if ($canDelete): ?><button class="action-btn-small delete-btn" onclick="showDeleteModal(<?php echo $grade['id']; ?>)"><i class="fas fa-trash"></i></button><?php endif; ?></div></td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr>
                                <td colspan="9">
                                    <div class="empty-state">
                                        <i class="fas fa-chart-bar"></i>
                                        <h3>No Custom Grades Found</h3>
                                        <p>All classes are using the default 8-level CBE/KJSEA grading scale.</p>
                                        <p style="font-size: 0.9rem; color: var(--text-light); margin-top: 0.5rem;">
                                            <i class="fas fa-info-circle"></i> The 8-level system includes EE1/EE2, ME1/ME2, AE1/AE2, BE1/BE2
                                        </p>
                                        <?php if ($canCreate): ?>
                                            <button class="btn btn-primary" onclick="openAddGradeModal()" style="margin-top: 1.5rem;">
                                                <i class="fas fa-plus"></i> Add Custom Grade Scale
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php endif; ?>
    </div>

    <?php if ($canCreate || $canEdit): ?>
    <div class="modal-overlay" id="gradeModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-chart-bar"></i> <span id="modalTitle">Add Custom Grade</span></h3>
                <button class="close-modal" id="closeModal"><i class="fas fa-times"></i></button>
            </div>
            <form id="gradeForm">
                <div class="modal-body">
                    <input type="hidden" id="gradeId" name="grade_id">
                    <div class="system-toggle">
                        <span><i class="fas fa-cog"></i> Grading System Type:</span>
                        <div class="toggle-buttons">
                            <button type="button" id="use8LevelBtn" class="toggle-btn active">8-Level CBE/KJSEA</button>
                            <button type="button" id="useCustomBtn" class="toggle-btn">Custom</button>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label required">Class</label>
                            <select class="form-control form-select" id="classSelectModal" name="class_id" required>
                                <option value="">-- Select Class --</option>
                                <?php foreach ($grouped_classes as $level => $classes): $level_label = ucwords(str_replace('_', ' ', $level)); ?>
                                    <optgroup label="<?php echo $level_label; ?>">
                                        <?php foreach ($classes as $class): ?>
                                            <option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['class_level']); ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Stream</label>
                            <select class="form-control form-select" id="streamSelectModal" name="stream_id">
                                <option value="">-- Select Stream (Optional) --</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label required">CBC Achievement Level (1-8)</label>
                            <select id="cbc_level" name="cbc_level" class="form-control form-select">
                                <option value="">-- Select Level --</option>
                                <option value="8">AL 8 - Exceeding Expectations (High) - 90-100%</option>
                                <option value="7">AL 7 - Exceeding Expectations (Standard) - 75-89%</option>
                                <option value="6">AL 6 - Meeting Expectations (High) - 58-74%</option>
                                <option value="5">AL 5 - Meeting Expectations (Standard) - 41-57%</option>
                                <option value="4">AL 4 - Approaching Expectations (High) - 31-40%</option>
                                <option value="3">AL 3 - Approaching Expectations (Standard) - 21-30%</option>
                                <option value="2">AL 2 - Below Expectations (Standard) - 11-20%</option>
                                <option value="1">AL 1 - Below Expectations (Basic) - 0-10%</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label required">CBC Level Name</label>
                            <input type="text" id="cbc_level_name" name="cbc_level_name" class="form-control" placeholder="e.g., Exceeding Expectations (High)">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label required">Lower Limit (%)</label>
                            <input type="number" id="lowerLimit" name="lower_limit" class="form-control" min="0" max="100" placeholder="0" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label required">Upper Limit (%)</label>
                            <input type="number" id="upperLimit" name="upper_limit" class="form-control" min="0" max="100" placeholder="100" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label required">Grade</label>
                            <input type="text" id="grade" name="grade" class="form-control" placeholder="EE1, ME2, AE1, BE2, etc." maxlength="10" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label required">Points</label>
                            <input type="number" id="points" name="points" class="form-control" step="0.1" min="0" max="8" placeholder="8.0" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label required">Remarks</label>
                        <input type="text" id="remarks" name="remarks" class="form-control" placeholder="Exceeding Expectations - Outstanding Performance" required>
                    </div>
                    <input type="hidden" id="is_cbc" name="is_cbc" value="1">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" id="cancelBtn">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveGradeBtn"><i class="fas fa-save"></i> <span id="saveBtnText">Save Custom Grade</span></button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($canDelete): ?>
    <div class="modal-overlay" id="deleteModal">
        <div class="modal delete-modal">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-exclamation-triangle"></i> Delete Custom Grade</h3>
                <button class="close-modal" id="closeDeleteModal"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this custom grade?</p>
                <div class="delete-warning">
                    <i class="fas fa-info-circle"></i> After deletion, the system will use default 8-level CBE grades for this class/stream.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="cancelDeleteBtn">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn"><i class="fas fa-trash"></i> Delete Custom Grade</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="modal-overlay" id="viewModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-eye"></i> View Grades for <span id="viewClassTitle"></span></h3>
                <button class="close-modal" id="closeViewModal"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Default 8-Level CBE/KJSEA Grades (System Default)</label>
                    <div id="defaultGradesView" class="default-grades-grid" style="margin-top: 1rem;"></div>
                </div>
                <div class="form-group" style="margin-top: 2rem;">
                    <label class="form-label">Custom Grades (Override)</label>
                    <div id="customGradesView" style="margin-top: 1rem;"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="closeViewBtn">Close</button>
            </div>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        let editingGradeId = null;
        let gradeToDeleteId = null;
        let isSubmitting = false;
        let using8LevelSystem = true;

        const PERMISSIONS = { 
            canCreate: <?php echo $canCreate ? 'true' : 'false'; ?>, 
            canEdit: <?php echo $canEdit ? 'true' : 'false'; ?>, 
            canDelete: <?php echo $canDelete ? 'true' : 'false'; ?>, 
            canUseDefaults: <?php echo $canUseDefaults ? 'true' : 'false'; ?>, 
            isSuperAdmin: <?php echo $isSuperAdmin ? 'true' : 'false'; ?> 
        };

        let defaultGrades = <?php echo json_encode($default_grades); ?>;
        let legacyGrades = <?php echo json_encode($legacy_default_grades); ?>;

        function showToast(title, message, type = 'success') { 
            const toast = document.createElement('div'); 
            toast.className = `toast ${type}`; 
            let icon = 'check-circle'; 
            if (type === 'error') icon = 'exclamation-triangle'; 
            if (type === 'warning') icon = 'exclamation-circle'; 
            toast.innerHTML = `<div class="toast-icon"><i class="fas fa-${icon}"></i></div><div class="toast-content"><div class="toast-title">${title}</div><div class="toast-message">${message}</div></div>`; 
            document.getElementById('toastContainer').appendChild(toast); 
            setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 300); }, 5000); 
        }

        function checkPermission(action) { 
            if (!PERMISSIONS[action] && !PERMISSIONS.isSuperAdmin) { 
                showToast('Access Denied', 'You do not have permission', 'error'); 
                return false; 
            } 
            return true; 
        }

        function showLoading(button, text) { 
            const originalHTML = button.innerHTML; 
            button.dataset.originalHTML = originalHTML; 
            button.innerHTML = `<div style="display: inline-block; width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-radius: 50%; border-top-color: white; animation: spin 1s ease-in-out infinite; margin-right: 8px;"></div>${text}`; 
            button.disabled = true; 
        }
        
        function hideLoading(button) { 
            if (button.dataset.originalHTML) { 
                button.innerHTML = button.dataset.originalHTML; 
            } 
            button.disabled = false; 
        }

        function loadStreams(classId, targetElement, selectedStreamId = null) {
            if (!targetElement || !classId) { 
                if (targetElement) { 
                    targetElement.innerHTML = '<option value="">-- Select Class First --</option>'; 
                    targetElement.disabled = true; 
                } 
                return; 
            }
            targetElement.innerHTML = '<option value="">-- Loading streams... --</option>'; 
            targetElement.disabled = false;
            fetch('grading.php', { 
                method: 'POST', 
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, 
                body: `action=get_streams_by_class&class_id=${classId}` 
            }).then(res => res.json()).then(data => { 
                if (data.success && data.streams.length) { 
                    let options = '<option value="">-- All Streams --</option>'; 
                    data.streams.forEach(stream => { 
                        const selected = selectedStreamId && stream.id == selectedStreamId ? 'selected' : ''; 
                        options += `<option value="${stream.id}" ${selected}>${stream.stream_name}</option>`; 
                    }); 
                    targetElement.innerHTML = options; 
                } else { 
                    targetElement.innerHTML = '<option value="">-- No streams available --</option>'; 
                } 
            }).catch(() => { 
                targetElement.innerHTML = '<option value="">-- Error loading streams --</option>'; 
            });
        }

        function openAddGradeModal() { 
            if (!checkPermission('canCreate')) return; 
            document.getElementById('modalTitle').textContent = 'Add Custom Grade'; 
            document.getElementById('saveBtnText').textContent = 'Save Custom Grade'; 
            document.getElementById('gradeForm').reset(); 
            document.getElementById('gradeId').value = ''; 
            editingGradeId = null; 
            document.getElementById('classSelectModal').selectedIndex = 0; 
            document.getElementById('streamSelectModal').innerHTML = '<option value="">-- Select Stream (Optional) --</option>'; 
            document.getElementById('use8LevelBtn').classList.add('active');
            document.getElementById('useCustomBtn').classList.remove('active');
            using8LevelSystem = true;
            document.getElementById('gradeModal').classList.add('active'); 
            document.body.style.overflow = 'hidden'; 
        }

        function editGrade(gradeId) { 
            if (!checkPermission('canEdit')) return; 
            editingGradeId = gradeId; 
            showLoading(document.getElementById('saveGradeBtn'), 'Loading...'); 
            fetch('grading.php', { 
                method: 'POST', 
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, 
                body: `action=get_grade&grade_id=${gradeId}` 
            }).then(res => res.json()).then(data => { 
                if (data.success) { 
                    const grade = data.grade; 
                    document.getElementById('modalTitle').textContent = 'Edit Custom Grade'; 
                    document.getElementById('saveBtnText').textContent = 'Update Custom Grade'; 
                    document.getElementById('gradeId').value = grade.id; 
                    document.getElementById('classSelectModal').value = grade.class_id; 
                    document.getElementById('lowerLimit').value = grade.lower_limit; 
                    document.getElementById('upperLimit').value = grade.upper_limit; 
                    document.getElementById('grade').value = grade.grade; 
                    document.getElementById('points').value = grade.points; 
                    document.getElementById('remarks').value = grade.remarks; 
                    if (grade.cbc_level) document.getElementById('cbc_level').value = grade.cbc_level;
                    if (grade.cbc_level_name) document.getElementById('cbc_level_name').value = grade.cbc_level_name;
                    loadStreams(grade.class_id, document.getElementById('streamSelectModal'), grade.stream_id); 
                    hideLoading(document.getElementById('saveGradeBtn')); 
                    document.getElementById('gradeModal').classList.add('active'); 
                    document.body.style.overflow = 'hidden'; 
                } else { 
                    showToast('Error', data.message, 'error'); 
                    hideLoading(document.getElementById('saveGradeBtn')); 
                } 
            }).catch(() => { 
                showToast('Error', 'Failed to load grade', 'error'); 
                hideLoading(document.getElementById('saveGradeBtn')); 
            }); 
        }

        function showDeleteModal(gradeId) { 
            if (!checkPermission('canDelete')) return; 
            gradeToDeleteId = gradeId; 
            document.getElementById('deleteModal').classList.add('active'); 
            document.body.style.overflow = 'hidden'; 
        }

        function closeModals() { 
            document.getElementById('gradeModal')?.classList.remove('active'); 
            document.getElementById('deleteModal')?.classList.remove('active'); 
            document.getElementById('viewModal')?.classList.remove('active'); 
            document.body.style.overflow = ''; 
        }

        function validateGradeForm() { 
            const lowerLimit = parseInt(document.getElementById('lowerLimit').value); 
            const upperLimit = parseInt(document.getElementById('upperLimit').value); 
            if (lowerLimit >= upperLimit) { 
                showToast('Warning', 'Lower limit must be less than upper limit', 'warning'); 
                return false; 
            } 
            if (lowerLimit < 0 || lowerLimit > 100) { 
                showToast('Warning', 'Lower limit must be between 0 and 100', 'warning'); 
                return false; 
            } 
            if (upperLimit < 0 || upperLimit > 100) { 
                showToast('Warning', 'Upper limit must be between 0 and 100', 'warning'); 
                return false; 
            } 
            return true; 
        }

        function saveGrade() { 
            if (isSubmitting) return; 
            const isEdit = editingGradeId !== null; 
            if ((isEdit && !PERMISSIONS.canEdit) || (!isEdit && !PERMISSIONS.canCreate)) { 
                showToast('Access Denied', 'You do not have permission', 'error'); 
                return; 
            } 
            if (!validateGradeForm()) return; 
            
            // Set CBC fields based on selected values
            const cbcLevel = document.getElementById('cbc_level').value;
            const cbcLevelName = document.getElementById('cbc_level_name').value;
            if (cbcLevel) document.getElementById('is_cbc').value = '1';
            
            const formData = new FormData(document.getElementById('gradeForm')); 
            formData.append('action', isEdit ? 'update_grade' : 'add_grade'); 
            showLoading(document.getElementById('saveGradeBtn'), isEdit ? 'Updating...' : 'Saving...'); 
            isSubmitting = true; 
            fetch('grading.php', { 
                method: 'POST', 
                body: new URLSearchParams(formData) 
            }).then(res => res.json()).then(data => { 
                if (data.success) { 
                    showToast('Success', data.message, 'success'); 
                    closeModals(); 
                    location.reload(); 
                } else { 
                    showToast('Error', data.message, 'error'); 
                } 
            }).catch(() => { 
                showToast('Error', 'Failed to save', 'error'); 
            }).finally(() => { 
                isSubmitting = false; 
                hideLoading(document.getElementById('saveGradeBtn')); 
            }); 
        }

        function confirmDelete() { 
            if (!PERMISSIONS.canDelete) return; 
            showLoading(document.getElementById('confirmDeleteBtn'), 'Deleting...'); 
            fetch('grading.php', { 
                method: 'POST', 
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, 
                body: `action=delete_grade&grade_id=${gradeToDeleteId}` 
            }).then(res => res.json()).then(data => { 
                if (data.success) { 
                    showToast('Success', data.message, 'success'); 
                    closeModals(); 
                    location.reload(); 
                } else { 
                    showToast('Error', data.message, 'error'); 
                } 
            }).catch(() => { 
                showToast('Error', 'Failed to delete', 'error'); 
            }).finally(() => { 
                hideLoading(document.getElementById('confirmDeleteBtn')); 
            }); 
        }

        function viewGrades() { 
            const classId = document.getElementById('selectClass').value; 
            const streamId = document.getElementById('selectStream').value || ''; 
            if (!classId) { 
                showToast('Warning', 'Please select a class first', 'warning'); 
                return; 
            } 
            const className = document.getElementById('selectClass').options[document.getElementById('selectClass').selectedIndex].text; 
            const streamName = streamId ? document.getElementById('selectStream').options[document.getElementById('selectStream').selectedIndex].text : 'All Streams'; 
            document.getElementById('viewClassTitle').textContent = `${className} (${streamName})`; 
            
            let defaultHtml = ''; 
            defaultGrades.forEach(grade => { 
                defaultHtml += `<div class="default-grade-item">
                    <span class="al-level-badge">AL ${grade.al_level}</span>
                    <div class="default-grade-range">${grade.lower_limit}-${grade.upper_limit}%</div>
                    <span class="grade-badge grade-${grade.grade}">${grade.grade}</span>
                    <div class="default-grade-points">${grade.points} pts</div>
                    <div class="default-grade-remarks" style="font-size: 0.7rem;">${grade.remarks.substring(0, 30)}...</div>
                </div>`; 
            }); 
            document.getElementById('defaultGradesView').innerHTML = defaultHtml; 
            document.getElementById('customGradesView').innerHTML = '<p><i class="fas fa-spinner fa-spin"></i> Loading custom grades...</p>'; 
            fetch('grading.php', { 
                method: 'POST', 
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, 
                body: `action=get_grades_by_class_stream&class_id=${classId}&stream_id=${streamId}` 
            }).then(res => res.json()).then(data => { 
                if (data.success) { 
                    if (data.has_custom_grades) { 
                        let customHtml = '<div class="default-grades-grid">'; 
                        data.custom_grades.forEach(grade => { 
                            const alBadge = grade.cbc_level ? `<span class="al-level-badge">AL ${grade.cbc_level}</span>` : '';
                            customHtml += `<div class="default-grade-item" style="border-color: var(--primary-blue);">
                                ${alBadge}
                                <div class="default-grade-range">${grade.lower_limit}-${grade.upper_limit}%</div>
                                <span class="grade-badge grade-${grade.grade}">${grade.grade}</span>
                                <div class="default-grade-points">${grade.points} pts</div>
                                <div class="default-grade-remarks" style="font-size: 0.7rem;">${grade.remarks.substring(0, 30)}...</div>
                            </div>`; 
                        }); 
                        customHtml += '</div><p style="margin-top: 1rem;"><i class="fas fa-info-circle"></i> Custom grades override default 8-level CBE grades</p>'; 
                        document.getElementById('customGradesView').innerHTML = customHtml; 
                    } else { 
                        document.getElementById('customGradesView').innerHTML = '<p><i class="fas fa-info-circle"></i> No custom grades. System is using default 8-level CBE/KJSEA grades.</p>'; 
                    } 
                } else { 
                    document.getElementById('customGradesView').innerHTML = '<p style="color: var(--error-red);"><i class="fas fa-exclamation-circle"></i> Failed to load custom grades</p>'; 
                } 
            }).catch(() => { 
                document.getElementById('customGradesView').innerHTML = '<p style="color: var(--error-red);"><i class="fas fa-exclamation-circle"></i> Failed to load custom grades</p>'; 
            }); 
            document.getElementById('viewModal').classList.add('active'); 
            document.body.style.overflow = 'hidden'; 
        }

        function useDefaultGrades() { 
            if (!PERMISSIONS.canCreate && !PERMISSIONS.canUseDefaults) { 
                showToast('Access Denied', 'You do not have permission', 'error'); 
                return; 
            } 
            const classId = document.getElementById('selectClass').value; 
            const streamId = document.getElementById('selectStream').value || ''; 
            if (!classId) { 
                showToast('Warning', 'Please select a class first', 'warning'); 
                return; 
            } 
            if (!confirm('Save default 8-level CBE/KJSEA grades as custom grades for this class? This will override any existing custom grades.')) return; 
            showLoading(document.getElementById('useDefaultsBtn'), 'Saving...'); 
            fetch('grading.php', { 
                method: 'POST', 
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, 
                body: `action=save_default_grades&class_id=${classId}&stream_id=${streamId}&use_8_level=true` 
            }).then(res => res.json()).then(data => { 
                if (data.success) { 
                    showToast('Success', data.message, 'success'); 
                    location.reload(); 
                } else { 
                    showToast('Error', data.message, 'error'); 
                } 
            }).catch(() => { 
                showToast('Error', 'Failed to save', 'error'); 
            }).finally(() => { 
                hideLoading(document.getElementById('useDefaultsBtn')); 
            }); 
        }

        function deleteCustomGrades() { 
            if (!PERMISSIONS.canDelete) return; 
            const classId = document.getElementById('selectClass').value; 
            const streamId = document.getElementById('selectStream').value || ''; 
            if (!classId) { 
                showToast('Warning', 'Please select a class first', 'warning'); 
                return; 
            } 
            if (!confirm('Delete all custom grades for this class? The system will use default 8-level CBE grades.')) return; 
            showLoading(document.getElementById('deleteCustomBtn'), 'Deleting...'); 
            fetch('grading.php', { 
                method: 'POST', 
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, 
                body: `action=delete_custom_grades&class_id=${classId}&stream_id=${streamId}` 
            }).then(res => res.json()).then(data => { 
                if (data.success) { 
                    showToast('Success', data.message, 'success'); 
                    location.reload(); 
                } else { 
                    showToast('Error', data.message, 'error'); 
                } 
            }).catch(() => { 
                showToast('Error', 'Failed to delete', 'error'); 
            }).finally(() => { 
                hideLoading(document.getElementById('deleteCustomBtn')); 
            }); 
        }

        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('addGradeBtn')?.addEventListener('click', openAddGradeModal);
            document.getElementById('viewGradesBtn')?.addEventListener('click', viewGrades);
            document.getElementById('useDefaultsBtn')?.addEventListener('click', useDefaultGrades);
            document.getElementById('deleteCustomBtn')?.addEventListener('click', deleteCustomGrades);
            document.getElementById('closeModal')?.addEventListener('click', closeModals);
            document.getElementById('cancelBtn')?.addEventListener('click', closeModals);
            document.getElementById('closeDeleteModal')?.addEventListener('click', closeModals);
            document.getElementById('cancelDeleteBtn')?.addEventListener('click', closeModals);
            document.getElementById('confirmDeleteBtn')?.addEventListener('click', confirmDelete);
            document.getElementById('closeViewModal')?.addEventListener('click', closeModals);
            document.getElementById('closeViewBtn')?.addEventListener('click', closeModals);
            document.getElementById('gradeForm')?.addEventListener('submit', function(e) { e.preventDefault(); saveGrade(); });
            
            document.getElementById('selectClass')?.addEventListener('change', function() { 
                const classId = this.value; 
                if (classId) { 
                    loadStreams(classId, document.getElementById('selectStream')); 
                    document.getElementById('selectStream').disabled = false; 
                } else { 
                    document.getElementById('selectStream').innerHTML = '<option value="">-- Select Class First --</option>'; 
                    document.getElementById('selectStream').disabled = true; 
                } 
            });
            
            document.getElementById('classSelectModal')?.addEventListener('change', function() { 
                loadStreams(this.value, document.getElementById('streamSelectModal')); 
            });
            
            document.getElementById('searchInput')?.addEventListener('input', function() { 
                const term = this.value.toLowerCase(); 
                document.querySelectorAll('#gradesTableBody tr[data-grade-id]').forEach(row => { 
                    row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none'; 
                }); 
            });
            
            document.getElementById('use8LevelBtn')?.addEventListener('click', function() {
                this.classList.add('active');
                document.getElementById('useCustomBtn').classList.remove('active');
                using8LevelSystem = true;
                if (using8LevelSystem) {
                    document.getElementById('cbc_level').value = '';
                    document.getElementById('cbc_level_name').value = '';
                }
            });
            
            document.getElementById('useCustomBtn')?.addEventListener('click', function() {
                this.classList.add('active');
                document.getElementById('use8LevelBtn').classList.remove('active');
                using8LevelSystem = false;
            });
        });
    </script>
</body>
</html>