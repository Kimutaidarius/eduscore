<?php
// update_teacher.php - Handle teacher CRUD operations via AJAX

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// DON'T start session here - let config.php handle it
// session_start();

// Include database configuration
require_once '../config/config.php';

// Check if user is authenticated
if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if user has school_id
if (!isset($_SESSION['school_id'])) {
    echo json_encode(['success' => false, 'message' => 'School information not found']);
    exit;
}

// Set content type to JSON
header('Content-Type: application/json');

// Handle different actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    switch ($action) {
        case 'add_teacher':
            handleAddTeacher($db, $_SESSION['school_id']);
            break;
            
        case 'update_teacher':
            handleUpdateTeacher($db, $_SESSION['school_id']);
            break;
            
        case 'get_teacher':
            handleGetTeacher($db, $_SESSION['school_id']);
            break;
            
        case 'delete_teacher':
            handleDeleteTeacher($db, $_SESSION['school_id']);
            break;
            
        case 'generate_teacher_number':
            handleGenerateTeacherNumber($db, $_SESSION['school_id']);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

// Function to handle adding a teacher
function handleAddTeacher($db, $school_id) {
    try {
        // Validate required fields
        $required_fields = ['firstname', 'lastname', 'email', 'phonenumber', 'gender'];
        $missing_fields = [];
        
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                $missing_fields[] = $field;
            }
        }
        
        if (!empty($missing_fields)) {
            echo json_encode([
                'success' => false, 
                'message' => 'Missing required fields: ' . implode(', ', $missing_fields)
            ]);
            return;
        }
        
        // Check if email already exists
        $check_query = "SELECT id FROM tblteachers WHERE email = :email AND school_id = :school_id";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(":email", $_POST['email']);
        $check_stmt->bindParam(":school_id", $school_id);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() > 0) {
            echo json_encode(['success' => false, 'message' => 'Email already exists']);
            return;
        }
        
        // Generate teacher number if not provided
        $teacher_number = isset($_POST['teacher_number']) ? trim($_POST['teacher_number']) : '';
        if (empty($teacher_number)) {
            $teacher_number = generateTeacherNumber($db, $school_id);
        }
        
        // Prepare subjects taught (store as comma-separated string)
        $subjects_taught = '';
        if (isset($_POST['subjects_taught']) && !empty($_POST['subjects_taught'])) {
            if (is_array($_POST['subjects_taught'])) {
                $subjects_taught = implode(', ', array_filter($_POST['subjects_taught']));
            } else {
                $subjects_taught = trim($_POST['subjects_taught']);
            }
        }
        
        // Get other fields with defaults
        $title = isset($_POST['title']) ? trim($_POST['title']) : '';
        $middle_name = isset($_POST['middle_name']) ? trim($_POST['middle_name']) : '';
        $role = isset($_POST['role']) ? trim($_POST['role']) : 'Teacher';
        $status = isset($_POST['status']) ? trim($_POST['status']) : 'Active';
        $contact = isset($_POST['contact']) ? trim($_POST['contact']) : '';
        
        // Generate a temporary password (teachers can reset later)
        $temp_password = password_hash('Teacher@123', PASSWORD_DEFAULT);
        
        // Insert teacher record - REMOVED 'photo' column
        $query = "INSERT INTO tblteachers 
                 (school_id, firstname, secondname, lastname, email, phonenumber, password, 
                  teacher_number, title, gender, role, status, subjects_taught, contact) 
                 VALUES 
                 (:school_id, :firstname, :secondname, :lastname, :email, :phonenumber, :password,
                  :teacher_number, :title, :gender, :role, :status, :subjects_taught, :contact)";
        
        $stmt = $db->prepare($query);
        
        // Bind parameters
        $stmt->bindParam(":school_id", $school_id);
        $stmt->bindParam(":firstname", $_POST['firstname']);
        $stmt->bindParam(":secondname", $middle_name);
        $stmt->bindParam(":lastname", $_POST['lastname']);
        $stmt->bindParam(":email", $_POST['email']);
        $stmt->bindParam(":phonenumber", $_POST['phonenumber']);
        $stmt->bindParam(":password", $temp_password);
        $stmt->bindParam(":teacher_number", $teacher_number);
        $stmt->bindParam(":title", $title);
        $stmt->bindParam(":gender", $_POST['gender']);
        $stmt->bindParam(":role", $role);
        $stmt->bindParam(":status", $status);
        $stmt->bindParam(":subjects_taught", $subjects_taught);
        $stmt->bindParam(":contact", $contact);
        
        if ($stmt->execute()) {
            $teacher_id = $db->lastInsertId();
            
            echo json_encode([
                'success' => true, 
                'message' => 'Teacher added successfully',
                'teacher_id' => $teacher_id,
                'teacher_number' => $teacher_number
            ]);
            // In the addTeacher() function, after successful insertion:
if ($stmt->execute()) {
    $teacher_id = $db->lastInsertId();
    
    // Get the inserted teacher data
    $query = "SELECT * FROM tblteachers WHERE id = :teacher_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":teacher_id", $teacher_id);
    $stmt->execute();
    $new_teacher = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return [
        'success' => true, 
        'message' => 'Teacher added successfully',
        'teacher' => $new_teacher
    ];
}
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add teacher to database']);
        }
        
    } catch (PDOException $e) {
        error_log("Add teacher error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred: ' . $e->getMessage()]);
    }
}

// Function to handle updating a teacher
function handleUpdateTeacher($db, $school_id) {
    try {
        // Validate required fields
        if (!isset($_POST['teacher_id']) || empty($_POST['teacher_id'])) {
            echo json_encode(['success' => false, 'message' => 'Teacher ID is required']);
            return;
        }
        
        $teacher_id = intval($_POST['teacher_id']);
        
        // Check if teacher exists and belongs to school
        $check_query = "SELECT id FROM tblteachers WHERE id = :teacher_id AND school_id = :school_id";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(":teacher_id", $teacher_id);
        $check_stmt->bindParam(":school_id", $school_id);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'message' => 'Teacher not found']);
            return;
        }
        
        // Check if email already exists (excluding current teacher)
        if (isset($_POST['email'])) {
            $check_query = "SELECT id FROM tblteachers WHERE email = :email AND school_id = :school_id AND id != :teacher_id";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(":email", $_POST['email']);
            $check_stmt->bindParam(":school_id", $school_id);
            $check_stmt->bindParam(":teacher_id", $teacher_id);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() > 0) {
                echo json_encode(['success' => false, 'message' => 'Email already exists']);
                return;
            }
        }
        
        // Prepare update fields
        $update_fields = [];
        $params = [
            ':teacher_id' => $teacher_id,
            ':school_id' => $school_id
        ];
        
        // Add fields to update based on your table structure
        $fields_to_update = [
            'firstname', 'lastname', 'email', 'phonenumber', 'gender',
            'teacher_number', 'title', 'middle_name', 'role', 'status', 'contact'
        ];
        
        foreach ($fields_to_update as $field) {
            if (isset($_POST[$field]) && $_POST[$field] !== '') {
                // Handle middle_name specially
                if ($field === 'middle_name') {
                    $update_fields[] = "secondname = :middle_name";
                    $params[":middle_name"] = $_POST['middle_name'];
                } else {
                    $update_fields[] = "{$field} = :{$field}";
                    $params[":{$field}"] = $_POST[$field];
                }
            }
        }
        
        // Handle subjects taught
        if (isset($_POST['subjects_taught'])) {
            if (is_array($_POST['subjects_taught'])) {
                $subjects_taught = implode(', ', array_filter($_POST['subjects_taught']));
            } else {
                $subjects_taught = trim($_POST['subjects_taught']);
            }
            $update_fields[] = "subjects_taught = :subjects_taught";
            $params[':subjects_taught'] = $subjects_taught;
        }
        
        // Add update timestamp
        $update_fields[] = "UpdationDate = CURRENT_TIMESTAMP";
        
        if (empty($update_fields)) {
            echo json_encode(['success' => false, 'message' => 'No fields to update']);
            return;
        }
        
        // Build and execute update query
        $query = "UPDATE tblteachers SET " . implode(', ', $update_fields) . 
                 " WHERE id = :teacher_id AND school_id = :school_id";
        
        $stmt = $db->prepare($query);
        
        // Bind all parameters
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true, 
                'message' => 'Teacher updated successfully'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update teacher']);
        }
        
    } catch (PDOException $e) {
        error_log("Update teacher error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred: ' . $e->getMessage()]);
    }
}

// Function to handle getting a teacher
function handleGetTeacher($db, $school_id) {
    try {
        if (!isset($_POST['teacher_id']) || empty($_POST['teacher_id'])) {
            echo json_encode(['success' => false, 'message' => 'Teacher ID is required']);
            return;
        }
        
        $teacher_id = intval($_POST['teacher_id']);
        
        $query = "SELECT * FROM tblteachers WHERE id = :teacher_id AND school_id = :school_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":teacher_id", $teacher_id);
        $stmt->bindParam(":school_id", $school_id);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Format the response based on actual table structure
            $formatted_teacher = [
                'id' => $teacher['id'],
                'teacher_number' => $teacher['teacher_number'] ?? '',
                'title' => $teacher['title'] ?? '',
                'firstname' => $teacher['firstname'],
                'middle_name' => $teacher['secondname'] ?? '',
                'lastname' => $teacher['lastname'],
                'gender' => $teacher['gender'] ?? '',
                'email' => $teacher['email'],
                'phonenumber' => $teacher['phonenumber'],
                'contact' => $teacher['contact'] ?? '',
                'role' => $teacher['role'] ?? 'Teacher',
                'status' => $teacher['status'] ?? 'Active',
                'subjects_taught' => $teacher['subjects_taught'] ?? ''
            ];
            
            echo json_encode([
                'success' => true, 
                'teacher' => $formatted_teacher
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Teacher not found']);
        }
        
    } catch (PDOException $e) {
        error_log("Get teacher error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred: ' . $e->getMessage()]);
    }
}

// Function to handle deleting a teacher
function handleDeleteTeacher($db, $school_id) {
    try {
        if (!isset($_POST['teacher_id']) || empty($_POST['teacher_id'])) {
            echo json_encode(['success' => false, 'message' => 'Teacher ID is required']);
            return;
        }
        
        $teacher_id = intval($_POST['teacher_id']);
        
        // Check if teacher exists and belongs to school
        $check_query = "SELECT id FROM tblteachers WHERE id = :teacher_id AND school_id = :school_id";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(":teacher_id", $teacher_id);
        $check_stmt->bindParam(":school_id", $school_id);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'message' => 'Teacher not found']);
            return;
        }
        
        // Delete teacher
        $query = "DELETE FROM tblteachers WHERE id = :teacher_id AND school_id = :school_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":teacher_id", $teacher_id);
        $stmt->bindParam(":school_id", $school_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Teacher deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete teacher']);
        }
        
    } catch (PDOException $e) {
        error_log("Delete teacher error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred: ' . $e->getMessage()]);
    }
}

// Function to generate teacher number
function handleGenerateTeacherNumber($db, $school_id) {
    try {
        $teacher_number = generateTeacherNumber($db, $school_id);
        echo json_encode(['success' => true, 'teacher_number' => $teacher_number]);
    } catch (PDOException $e) {
        error_log("Generate teacher number error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to generate teacher number: ' . $e->getMessage()]);
    }
}

// Helper function to generate teacher number
function generateTeacherNumber($db, $school_id) {
    $year = date('Y');
    
    // Check if there are any teachers with teacher_number
    $check_query = "SELECT teacher_number FROM tblteachers 
                    WHERE school_id = :school_id 
                    AND teacher_number IS NOT NULL 
                    AND teacher_number != '' 
                    ORDER BY id DESC LIMIT 1";
    
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(":school_id", $school_id);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() > 0) {
        $teacher = $check_stmt->fetch(PDO::FETCH_ASSOC);
        $last_number = $teacher['teacher_number'];
        
        // Extract number from format like TCH-2025-0001
        if (preg_match('/TCH-(\d{4})-(\d{4})/', $last_number, $matches)) {
            $last_year = $matches[1];
            $last_seq = intval($matches[2]);
            
            if ($last_year == $year) {
                $next_number = $last_seq + 1;
            } else {
                $next_number = 1;
            }
        } else {
            $next_number = 1;
        }
    } else {
        $next_number = 1;
    }
    
    return 'TCH-' . $year . '-' . str_pad($next_number, 4, '0', STR_PAD_LEFT);
}