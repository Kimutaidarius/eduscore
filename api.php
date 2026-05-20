<?php
// api.php - Dedicated API Endpoint for AJAX requests

// --- Session Management ---
session_start();

// --- Error Reporting & Logging Configuration ---
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php-error.log');
error_reporting(E_ALL);

// --- Headers ---
header('Content-Type: application/json');

// --- Database Connection Inclusion ---
include('includes/config.php');

// --- Database Connection Verification ---
if (!isset($dbh) || !($dbh instanceof PDO)) {
    error_log("CRITICAL ERROR: Database connection variable \$dbh is not set or not a PDO instance in api.php.");
    sendResponse([], 'error', 'Server configuration error: Database not connected.', 500);
    exit();
}

// --- Helper Function: sendResponse ---
function sendResponse($data, $status, $message, $httpCode = 200) {
    http_response_code($httpCode);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data,
    ]);
    exit();
}

// --- Authentication Check (Example) ---
if (!isset($_SESSION['id']) || strlen($_SESSION['id']) == 0) {
    sendResponse([], 'error', 'Authentication required. Please log in.', 401);
}

// Determine the request method
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Initialize data and action based on request method
$data = null;
$action = null;

if ($requestMethod === 'POST' || $requestMethod === 'PUT' || $requestMethod === 'DELETE') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON Decode Error for {$requestMethod} request: " . json_last_error_msg() . " Raw Input: " . $input);
        sendResponse([], 'error', "Invalid JSON payload.", 400);
    }
    $action = $data['action'] ?? null;
} elseif ($requestMethod === 'GET') {
    $action = $_GET['action'] ?? null;
    $data = $_GET;
}


// --- Main API Action Handler ---
switch ($action) {

    case 'get_all_classes':
        try {
            $stmt = $dbh->prepare("SELECT id, academic_level, class_level FROM tblclasses ORDER BY academic_level, class_level");
            $stmt->execute();
            $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            sendResponse($classes, 'success', 'Classes fetched successfully.');
        } catch (PDOException $e) {
            error_log("Error fetching classes: " . $e->getMessage());
            sendResponse([], 'error', 'Failed to fetch classes: ' . $e->getMessage(), 500);
        }
        break;

    case 'get_subjects_by_class':
        $classId = $data['class_id'] ?? null;

        if ($classId === null || !is_numeric($classId)) {
            sendResponse([], 'error', 'Class ID is required and must be a number.', 400);
        }

        try {
            // Ensure no comments inside the SQL string
            $stmt = $dbh->prepare("
                SELECT
                    s.id AS subject_id,
                    s.subject_name,
                    s.alias,
                    s.category AS category_id,
                    s.class_id,
                    s.teacher_id,
                    sc.id AS group_id,
                    sc.name AS group_name,
                    sc.computation_type,
                    t.firstname,
                    t.secondname,
                    t.lastname
                FROM
                    tblsubjects s
                LEFT JOIN
                    tblsubjectcombination sc ON s.category = sc.id
                LEFT JOIN
                    tblteachers t ON s.teacher_id = t.id
                WHERE
                    s.class_id = :class_id
                ORDER BY
                    s.subject_name
            ");
            $stmt->bindParam(':class_id', $classId, PDO::PARAM_INT);
            $stmt->execute();
            $subjectsData = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $subjects = [];
            $subjectGroups = [];
            $processedGroupIds = [];

            foreach ($subjectsData as $row) {
                $teacherName = 'Not Assigned'; // Default to 'Not Assigned'
                if ($row['teacher_id'] !== null && ($row['firstname'] || $row['lastname'])) { // Check if teacher details exist
                    $teacherName = trim($row['firstname'] . ' ' . $row['secondname'] . ' ' . $row['lastname']);
                    // If secondname is empty, remove extra space
                    $teacherName = preg_replace('/\s+/', ' ', $teacherName);
                }

                $subjects[] = [
                    'id' => $row['subject_id'],
                    'subject_name' => $row['subject_name'],
                    'alias' => $row['alias'],
                    'category_id' => $row['category_id'],
                    'group_id' => $row['group_id'],
                    'group_name' => $row['group_name'],
                    'class_id' => $row['class_id'], // Ensure class_id is passed
                    'assigned_teacher_id' => $row['teacher_id'],
                    'assigned_teacher_name' => $teacherName
                ];

                if ($row['group_id'] !== null && !isset($processedGroupIds[$row['group_id']])) {
                    $subjectGroups[] = [
                        'id' => $row['group_id'],
                        'name' => $row['group_name'],
                        'computation_type' => $row['computation_type']
                    ];
                    $processedGroupIds[$row['group_id']] = true;
                }
            }

            sendResponse(['subjects' => $subjects, 'subject_groups' => $subjectGroups], 'success', 'Subjects and groups fetched successfully.');

        } catch (PDOException $e) {
            error_log("Error fetching subjects by class: " . $e->getMessage());
            sendResponse([], 'error', 'Failed to fetch subjects: ' . $e->getMessage(), 500);
        }
        break;

    // --- NEW: Case for getting a single subject by ID ---
    case 'get_subject_by_id':
        $subjectId = $data['subject_id'] ?? null;

        if ($subjectId === null || !is_numeric($subjectId)) {
            sendResponse([], 'error', 'Subject ID is required and must be a number.', 400);
        }

        try {
            $stmt = $dbh->prepare("
                SELECT
                    s.id AS subject_id,
                    s.subject_name,
                    s.alias,
                    s.category AS category_id,
                    s.class_id,
                    s.teacher_id,
                    sc.id AS group_id,
                    sc.name AS group_name,
                    sc.computation_type,
                    t.firstname,
                    t.secondname,
                    t.lastname
                FROM
                    tblsubjects s
                LEFT JOIN
                    tblsubjectcombination sc ON s.category = sc.id
                LEFT JOIN
                    tblteachers t ON s.teacher_id = t.id
                WHERE
                    s.id = :subject_id
            ");
            $stmt->bindParam(':subject_id', $subjectId, PDO::PARAM_INT);
            $stmt->execute();
            $subjectData = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($subjectData) {
                $teacherName = 'Not Assigned';
                if ($subjectData['teacher_id'] !== null && ($subjectData['firstname'] || $subjectData['lastname'])) {
                    $teacherName = trim($subjectData['firstname'] . ' ' . $subjectData['secondname'] . ' ' . $subjectData['lastname']);
                    $teacherName = preg_replace('/\s+/', ' ', $teacherName);
                }

                $subject = [
                    'id' => $subjectData['subject_id'],
                    'subject_name' => $subjectData['subject_name'],
                    'alias' => $subjectData['alias'],
                    'category_id' => $subjectData['category_id'],
                    'group_id' => $subjectData['group_id'],
                    'group_name' => $subjectData['group_name'],
                    'class_id' => $subjectData['class_id'],
                    'assigned_teacher_id' => $subjectData['teacher_id'],
                    'assigned_teacher_name' => $teacherName
                ];
                sendResponse($subject, 'success', 'Subject fetched successfully.');
            } else {
                sendResponse([], 'error', 'Subject not found.', 404);
            }

        } catch (PDOException $e) {
            error_log("Error fetching single subject: " . $e->getMessage());
            sendResponse([], 'error', 'Failed to fetch subject: ' . $e->getMessage(), 500);
        }
        break;

    // --- NEW: Case for getting all teachers ---
    case 'get_all_teachers':
        try {
            $stmt = $dbh->prepare("SELECT id, firstname, secondname, lastname FROM tblteachers ORDER BY firstname, lastname");
            $stmt->execute();
            $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $formattedTeachers = array_map(function($teacher) {
                $fullName = trim($teacher['firstname'] . ' ' . $teacher['secondname'] . ' ' . $teacher['lastname']);
                $fullName = preg_replace('/\s+/', ' ', $fullName); // Remove extra spaces
                return [
                    'id' => $teacher['id'],
                    'name' => $fullName
                ];
            }, $teachers);

            sendResponse($formattedTeachers, 'success', 'Teachers fetched successfully.');
        } catch (PDOException $e) {
            error_log("Error fetching teachers: " . $e->getMessage());
            sendResponse([], 'error', 'Failed to fetch teachers: ' . $e->getMessage(), 500);
        }
        break;

    case 'add_learning_area_category':
        // ... (your existing add_learning_area_category code)
        $categoryName = $data['name'] ?? null;
        $computationType = $data['computation_type'] ?? '';
        $classId = $data['class_id'] ?? null;

        if (empty($categoryName)) {
            sendResponse([], 'error', 'Category Name cannot be empty.', 400);
        }
        if (empty($classId) || !is_numeric($classId) || (int)$classId <= 0) {
            sendResponse([], 'error', 'Invalid or missing class selection. Please select a valid class.', 400);
        }

        try {
            $checkStmt = $dbh->prepare("SELECT COUNT(*) FROM tblsubjectcombination WHERE name = :name AND class_id = :class_id");
            $checkStmt->bindParam(':name', $categoryName, PDO::PARAM_STR);
            $checkStmt->bindParam(':class_id', $classId, PDO::PARAM_INT);
            $checkStmt->execute();
            if ($checkStmt->fetchColumn() > 0) {
                sendResponse([], 'error', "Category '$categoryName' already exists for the selected class.", 409);
            }

            $stmt = $dbh->prepare("INSERT INTO tblsubjectcombination (name, computation_type, class_id) VALUES (:name, :computation_type, :class_id)");
            $stmt->bindParam(':name', $categoryName, PDO::PARAM_STR);
            $stmt->bindParam(':computation_type', $computationType, PDO::PARAM_STR);
            $stmt->bindParam(':class_id', $classId, PDO::PARAM_INT);

            if ($stmt->execute()) {
                sendResponse([], 'success', 'Learning Area Category added successfully.', 201);
            } else {
                sendResponse([], 'error', 'Failed to add category to the database.', 500);
            }
        } catch (PDOException $e) {
            error_log("Error adding learning area category: " . $e->getMessage());
            sendResponse([], 'error', 'Database error: ' . $e->getMessage(), 500);
        }
        break;

    case 'get_learning_area_categories':
        // Optional: Get class_id from $data (which would be $_GET in this case)
        $classId = $data['class_id'] ?? null;

        try {
            $sql = "SELECT id, name, computation_type, class_id FROM tblsubjectcombination";
            $params = [];

            if ($classId !== null && is_numeric($classId) && (int)$classId > 0) {
                $sql .= " WHERE class_id = :class_id";
                $params[':class_id'] = (int)$classId;
            }

            $sql .= " ORDER BY name ASC";

            $stmt = $dbh->prepare($sql);
            $stmt->execute($params);

            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $message = 'Learning Area Categories fetched successfully.';
            if (empty($categories)) {
                $message = $classId ? "No learning area categories found for the selected class." : "No learning area categories found in the database.";
            }
            sendResponse($categories, 'success', $message);

        } catch (PDOException $e) {
            error_log("Error fetching learning area categories: " . $e->getMessage());
            sendResponse([], 'error', 'Database error: ' . $e->getMessage(), 500);
        }
        break;

    case 'update_learning_area_category':
        // ... (your existing update_learning_area_category code)
        $categoryId = isset($data['id']) ? (int)$data['id'] : null;
        $categoryName = isset($data['name']) ? $data['name'] : null;
        $classId = isset($data['class_id']) ? (int)$data['class_id'] : null;

        if ($categoryId === null || $categoryId <= 0) {
            sendResponse([], 'error', "Category ID is required and must be a valid positive number for update.", 400);
        }
        if ($categoryName === null || !is_string($categoryName) || empty(trim($categoryName))) {
            sendResponse([], 'error', "Category Name must be a non-empty string.", 400);
        }
        if ($classId === null || $classId <= 0) {
            sendResponse([], 'error', "Class ID must be a valid positive number.", 400);
        }

        try {
            $checkStmt = $dbh->prepare("SELECT COUNT(*) FROM tblsubjectcombination WHERE name = :name AND class_id = :class_id AND id != :id");
            $checkStmt->bindParam(':name', $categoryName, PDO::PARAM_STR);
            $checkStmt->bindParam(':class_id', $classId, PDO::PARAM_INT);
            $checkStmt->bindParam(':id', $categoryId, PDO::PARAM_INT);
            $checkStmt->execute();
            if ($checkStmt->fetchColumn() > 0) {
                sendResponse([], 'error', "Category '$categoryName' already exists for the selected class.", 409);
            }

            $sql = "UPDATE tblsubjectcombination SET name = :name, class_id = :class_id WHERE id = :id";
            $stmt = $dbh->prepare($sql);
            $stmt->bindParam(':name', $categoryName, PDO::PARAM_STR);
            $stmt->bindParam(':class_id', $classId, PDO::PARAM_INT);
            $stmt->bindParam(':id', $categoryId, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                sendResponse([], 'success', "Learning Area Category updated successfully.", 200);
            } else {
                sendResponse([], 'info', "No category found with the provided ID for update, or no changes were made.", 200);
            }
        } catch (PDOException $e) {
            error_log("Database error during category update: " . $e->getMessage());
            sendResponse([], 'error', "Database error: " . $e->getMessage(), 500);
        }
        break;

    case 'add_learning_area':
        // ... (your existing add_learning_area code - IMPORTANT: Ensure this is the version I provided in the previous turn
        //      where it correctly saves 'name' to subject_name and 'alias' to the new alias column)
        $learningAreaName = $data['name'] ?? null;
        $learningAreaAlias = $data['alias'] ?? null;
        $categoryId = $data['category_id'] ?? null;
        $classId = $data['class_id'] ?? null;

        if (empty($learningAreaName) || empty($learningAreaAlias) || $categoryId === null || $categoryId <= 0 || $classId === null || $classId <= 0) {
            sendResponse([], 'error', "Learning Area name, alias, Category ID, and Class ID are all required and must be valid.", 400);
        }

        try {
            $checkStmt = $dbh->prepare("SELECT COUNT(*) FROM tblsubjects WHERE (subject_name = :subject_name OR alias = :alias) AND category = :category_id AND class_id = :class_id");
            $checkStmt->bindParam(':subject_name', $learningAreaName, PDO::PARAM_STR);
            $checkStmt->bindParam(':alias', $learningAreaAlias, PDO::PARAM_STR);
            $checkStmt->bindParam(':category_id', $categoryId, PDO::PARAM_INT);
            $checkStmt->bindParam(':class_id', $classId, PDO::PARAM_INT);
            $checkStmt->execute();
            if ($checkStmt->fetchColumn() > 0) {
                sendResponse([], 'error', "A Learning Area with this name or alias already exists in this category for the selected class.", 409);
            }

            $sql = "INSERT INTO tblsubjects (subject_name, alias, category, class_id, teacher_id) VALUES (:subject_name, :alias, :category_id, :class_id, :teacher_id)";
            $stmt = $dbh->prepare($sql);
            $stmt->bindParam(':subject_name', $learningAreaName, PDO::PARAM_STR);
            $stmt->bindParam(':alias', $learningAreaAlias, PDO::PARAM_STR);
            $stmt->bindParam(':category_id', $categoryId, PDO::PARAM_INT);
            $stmt->bindParam(':class_id', $classId, PDO::PARAM_INT);
            $stmt->bindValue(':teacher_id', null, PDO::PARAM_NULL);
            $stmt->execute();

            sendResponse([], 'success', "Learning Area '{$learningAreaName}' added successfully.", 201);

        } catch (PDOException $e) {
            error_log("Database error adding learning area (subject): " . $e->getMessage());
            sendResponse([], 'error', "Database error: " . $e->getMessage(), 500);
        }
        break;

    // --- NEW: Case for updating a learning area (subject) ---
    case 'update_learning_area':
        $subjectId = $data['id'] ?? null;
        $subjectName = $data['name'] ?? null;
        $alias = $data['alias'] ?? null;
        $categoryId = $data['category_id'] ?? null;
        $teacherId = $data['teacher_id'] ?? null; // Can be null if not assigned

        // Basic validation
        if (empty($subjectId) || !is_numeric($subjectId) || $subjectId <= 0) {
            sendResponse([], 'error', 'Subject ID is required and must be a valid number.', 400);
        }
        if (empty($subjectName) || empty($alias) || empty($categoryId) || !is_numeric($categoryId) || $categoryId <= 0) {
            sendResponse([], 'error', 'Subject Name, Alias, and Category are required.', 400);
        }

        try {
            // Optional: Check for duplicate name/alias within the same class/category (excluding current subject)
            // This requires knowing the class_id of the subject being updated.
            // For simplicity, we'll skip this check here, but consider implementing it.
            // You could fetch the class_id based on subjectId first.

            $sql = "UPDATE tblsubjects SET subject_name = :subject_name, alias = :alias, category = :category_id, teacher_id = :teacher_id WHERE id = :id";
            $stmt = $dbh->prepare($sql);
            $stmt->bindParam(':subject_name', $subjectName, PDO::PARAM_STR);
            $stmt->bindParam(':alias', $alias, PDO::PARAM_STR);
            $stmt->bindParam(':category_id', $categoryId, PDO::PARAM_INT);
            $stmt->bindParam(':id', $subjectId, PDO::PARAM_INT);

            if ($teacherId !== null && is_numeric($teacherId) && $teacherId > 0) {
                $stmt->bindParam(':teacher_id', $teacherId, PDO::PARAM_INT);
            } else {
                $stmt->bindValue(':teacher_id', null, PDO::PARAM_NULL);
            }

            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                sendResponse([], 'success', "Learning Area updated successfully.", 200);
            } else {
                sendResponse([], 'info', "No learning area found with the provided ID, or no changes were made.", 200);
            }

        } catch (PDOException $e) {
            error_log("Database error updating learning area (subject ID: {$subjectId}): " . $e->getMessage());
            sendResponse([], 'error', "Database error: " . $e->getMessage(), 500);
        }
        break;


    case 'delete_learning_area_category':
        // ... (your existing delete_learning_area_category code)
        $categoryId = isset($data['id']) ? (int)$data['id'] : null;

        if ($categoryId === null || $categoryId <= 0) {
            error_log("Validation Error: Category ID is missing or invalid for deletion. Received ID: " . ($categoryId ?? 'NULL'));
            sendResponse([], 'error', "Category ID is required and must be a positive number for deletion.", 400);
        }

        try {
            // Optional: Check for existing subjects linked to this category before deleting the category
            $checkSubjectsStmt = $dbh->prepare("SELECT COUNT(*) FROM tblsubjects WHERE category = :category_id");
            $checkSubjectsStmt->bindParam(':category_id', $categoryId, PDO::PARAM_INT);
            $checkSubjectsStmt->execute();
            if ($checkSubjectsStmt->fetchColumn() > 0) {
                sendResponse([], 'error', "Cannot delete category: It contains associated learning areas. Please delete them first.", 409); // 409 Conflict
            }

            $sql = "DELETE FROM tblsubjectcombination WHERE id = :id";
            $stmt = $dbh->prepare($sql);
            $stmt->bindParam(':id', $categoryId, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                sendResponse([], 'success', "Learning Area Category deleted successfully.", 200);
            } else {
                error_log("Deletion Warning: No category found with ID: {$categoryId} to delete.");
                sendResponse([], 'error', "No category found with that ID to delete.", 404);
            }

        } catch (PDOException $e) {
            error_log("Database error during category deletion (ID: {$categoryId}): " . $e->getMessage());
            sendResponse([], 'error', "A database error occurred during deletion. Please try again.", 500);
        }
        break;

        // --- NEW: Case for deleting a learning area (subject) ---
    case 'delete_learning_area':
        $subjectId = $data['id'] ?? null;

        if (empty($subjectId) || !is_numeric($subjectId) || (int)$subjectId <= 0) {
            sendResponse([], 'error', 'Learning Area ID is required and must be a valid number for deletion.', 400);
        }

        try {
            // IMPORTANT: Consider any foreign key constraints or related data (e.g., grades).
            // If grades are linked to tblsubjects, you might need to
            // 1. Set `ON DELETE SET NULL` for the foreign key in tblgrades, or
            // 2. Add a check here to prevent deletion if grades exist, or
            // 3. Delete related grades first.
            // For now, this is a direct delete from tblsubjects.

            $stmt = $dbh->prepare("DELETE FROM tblsubjects WHERE id = :id");
            $stmt->bindParam(':id', $subjectId, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                sendResponse([], 'success', 'Learning Area deleted successfully.', 200);
            } else {
                // If rowCount is 0, it means no row was deleted, possibly ID not found.
                sendResponse([], 'error', 'No learning area found with the provided ID to delete.', 404);
            }
        } catch (PDOException $e) {
            error_log("Database error deleting learning area (ID: {$subjectId}): " . $e->getMessage());
            sendResponse([], 'error', 'Database error: ' . $e->getMessage(), 500);
        }
        break;

            case 'update_category_computation_type':
        $categoryId = $data['id'] ?? null;
        $computationType = $data['computation_type'] ?? null;

        if (empty($categoryId) || !is_numeric($categoryId) || (int)$categoryId <= 0) {
            sendResponse([], 'error', 'Category ID is required and must be a positive number.', 400);
        }

        // Validate computation type against allowed values
        $allowedTypes = ['PickAll', 'PickTheBestOne', 'PickTheBestTwo', 'PickTheBestThree'];
        if ($computationType === null || !in_array($computationType, $allowedTypes)) {
            sendResponse([], 'error', 'Invalid or missing computation type provided.', 400);
        }

        try {
            $stmt = $dbh->prepare("UPDATE tblsubjectcombination SET computation_type = :computation_type WHERE id = :id");
            $stmt->bindParam(':computation_type', $computationType, PDO::PARAM_STR);
            $stmt->bindParam(':id', $categoryId, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                sendResponse([], 'success', 'Computation type updated successfully.', 200);
            } else {
                // This might mean the ID wasn't found, or the type was already the same
                sendResponse([], 'info', 'No category found with the provided ID, or no changes were made.', 200);
            }
        } catch (PDOException $e) {
            error_log("Database error updating computation type for category (ID: {$categoryId}): " . $e->getMessage());
            sendResponse([], 'error', 'Database error: ' . $e->getMessage(), 500);
        }
        break;

    default:
        sendResponse([], 'error', 'Invalid or unknown action specified.', 400);
        break;
}
?>