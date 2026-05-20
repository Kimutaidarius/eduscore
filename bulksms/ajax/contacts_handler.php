<?php
// ajax/contacts_handler.php
session_start();
header('Content-Type: application/json');

// Include database configuration
require_once '../config/config.php';

// Function to send JSON response
function sendJsonResponse($status, $message, $data = []) {
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data
    ]);
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    sendJsonResponse('error', 'Please login to continue');
}

$user_id = $_SESSION['user_id'];

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse('error', 'Invalid request method');
}

// CSRF check
$csrf_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
if (!isset($_SESSION['csrf_token']) || $csrf_token !== $_SESSION['csrf_token']) {
    sendJsonResponse('error', 'Invalid security token');
}

// Define sanitize function if not already defined
if (!function_exists('sanitize')) {
    function sanitize($data) {
        if ($data === null || $data === '') {
            return '';
        }
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
}

// Define validatePhone function if not already defined
if (!function_exists('validatePhone')) {
    function validatePhone($phone) {
        // Remove any non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Check if it's a valid phone number (basic check)
        if (strlen($phone) >= 10 && strlen($phone) <= 15) {
            return $phone;
        }
        return false;
    }
}

$action = isset($_POST['action']) ? $_POST['action'] : '';

try {
    // Test database connection first
    $pdo->query("SELECT 1");
    
    switch ($action) {
        case 'get_contacts':
            // Pagination
            $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
            $limit = 20;
            $offset = ($page - 1) * $limit;
            
            // Filters
            $group_filter = isset($_POST['group_id']) ? (int)$_POST['group_id'] : 0;
            $search = isset($_POST['search']) ? sanitize($_POST['search']) : '';
            
            // Build main query
            $query = "SELECT c.*, g.name as group_name FROM contacts c 
                      LEFT JOIN contact_groups g ON c.group_id = g.id 
                      WHERE c.user_id = ?";
            $params = [$user_id];
            
            if ($group_filter > 0) {
                $query .= " AND c.group_id = ?";
                $params[] = $group_filter;
            }
            
            if (!empty($search)) {
                $query .= " AND (c.name LIKE ? OR c.phone LIKE ? OR c.email LIKE ?)";
                $search_param = "%$search%";
                $params[] = $search_param;
                $params[] = $search_param;
                $params[] = $search_param;
            }
            
            $query .= " ORDER BY c.name ASC LIMIT $limit OFFSET $offset";
            
            // Get contacts
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $contacts = $stmt->fetchAll();
            
            // Build count query separately to avoid parameter issues
            $count_query = "SELECT COUNT(*) FROM contacts WHERE user_id = ?";
            $count_params = [$user_id];
            
            if ($group_filter > 0) {
                $count_query .= " AND group_id = ?";
                $count_params[] = $group_filter;
            }
            
            if (!empty($search)) {
                $count_query .= " AND (name LIKE ? OR phone LIKE ? OR email LIKE ?)";
                $search_param = "%$search%";
                $count_params[] = $search_param;
                $count_params[] = $search_param;
                $count_params[] = $search_param;
            }
            
            // Get total count
            $stmt = $pdo->prepare($count_query);
            $stmt->execute($count_params);
            $total_contacts = $stmt->fetchColumn();
            $total_pages = ceil($total_contacts / $limit);
            
            sendJsonResponse('success', 'Contacts retrieved', [
                'contacts' => $contacts,
                'total' => $total_contacts,
                'page' => $page,
                'total_pages' => $total_pages
            ]);
            break;
            
        case 'get_groups':
            $stmt = $pdo->prepare("SELECT * FROM contact_groups WHERE user_id = ? ORDER BY name");
            $stmt->execute([$user_id]);
            $groups = $stmt->fetchAll();
            
            sendJsonResponse('success', 'Groups retrieved', ['groups' => $groups]);
            break;
            
        case 'add':
            $name = isset($_POST['name']) ? sanitize($_POST['name']) : '';
            $phone = isset($_POST['phone']) ? sanitize($_POST['phone']) : '';
            $email = isset($_POST['email']) ? sanitize($_POST['email']) : '';
            $group_id = !empty($_POST['group_id']) ? (int)$_POST['group_id'] : null;
            
            if (empty($name) || empty($phone)) {
                sendJsonResponse('error', 'Name and phone are required');
            }
            
            // Validate phone
            $phone = validatePhone($phone);
            if (!$phone) {
                sendJsonResponse('error', 'Invalid phone number format. Use international format (e.g., 254712345678)');
            }
            
            // Check if contact exists
            $stmt = $pdo->prepare("SELECT id FROM contacts WHERE user_id = ? AND phone = ?");
            $stmt->execute([$user_id, $phone]);
            if ($stmt->fetch()) {
                sendJsonResponse('error', 'Contact with this phone number already exists');
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO contacts (user_id, group_id, name, phone, email, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            if ($stmt->execute([$user_id, $group_id, $name, $phone, $email])) {
                $contact_id = $pdo->lastInsertId();
                
                // Get the newly created contact with group name
                $stmt = $pdo->prepare("
                    SELECT c.*, g.name as group_name 
                    FROM contacts c 
                    LEFT JOIN contact_groups g ON c.group_id = g.id 
                    WHERE c.id = ?
                ");
                $stmt->execute([$contact_id]);
                $new_contact = $stmt->fetch();
                
                sendJsonResponse('success', 'Contact added successfully!', ['contact' => $new_contact]);
            } else {
                sendJsonResponse('error', 'Failed to add contact');
            }
            break;
            
        case 'get':
            $contact_id = (int)$_POST['contact_id'];
            
            $stmt = $pdo->prepare("
                SELECT c.*, g.name as group_name 
                FROM contacts c 
                LEFT JOIN contact_groups g ON c.group_id = g.id 
                WHERE c.id = ? AND c.user_id = ?
            ");
            $stmt->execute([$contact_id, $user_id]);
            $contact = $stmt->fetch();
            
            if (!$contact) {
                sendJsonResponse('error', 'Contact not found');
            }
            
            sendJsonResponse('success', 'Contact retrieved', ['contact' => $contact]);
            break;
            
        case 'edit':
            $contact_id = (int)$_POST['contact_id'];
            $name = isset($_POST['name']) ? sanitize($_POST['name']) : '';
            $phone = isset($_POST['phone']) ? sanitize($_POST['phone']) : '';
            $email = isset($_POST['email']) ? sanitize($_POST['email']) : '';
            $group_id = !empty($_POST['group_id']) ? (int)$_POST['group_id'] : null;
            
            if (empty($name) || empty($phone)) {
                sendJsonResponse('error', 'Name and phone are required');
            }
            
            // Validate phone
            $phone = validatePhone($phone);
            if (!$phone) {
                sendJsonResponse('error', 'Invalid phone number format');
            }
            
            // Verify contact belongs to user
            $stmt = $pdo->prepare("SELECT id FROM contacts WHERE id = ? AND user_id = ?");
            $stmt->execute([$contact_id, $user_id]);
            if (!$stmt->fetch()) {
                sendJsonResponse('error', 'Contact not found');
            }
            
            // Check if contact exists for another contact
            $stmt = $pdo->prepare("SELECT id FROM contacts WHERE user_id = ? AND phone = ? AND id != ?");
            $stmt->execute([$user_id, $phone, $contact_id]);
            if ($stmt->fetch()) {
                sendJsonResponse('error', 'Another contact with this phone number already exists');
            }
            
            $stmt = $pdo->prepare("
                UPDATE contacts 
                SET name = ?, phone = ?, email = ?, group_id = ? 
                WHERE id = ? AND user_id = ?
            ");
            
            if ($stmt->execute([$name, $phone, $email, $group_id, $contact_id, $user_id])) {
                // Get updated contact
                $stmt = $pdo->prepare("
                    SELECT c.*, g.name as group_name 
                    FROM contacts c 
                    LEFT JOIN contact_groups g ON c.group_id = g.id 
                    WHERE c.id = ?
                ");
                $stmt->execute([$contact_id]);
                $updated_contact = $stmt->fetch();
                
                sendJsonResponse('success', 'Contact updated successfully!', ['contact' => $updated_contact]);
            } else {
                sendJsonResponse('error', 'Failed to update contact');
            }
            break;
            
        case 'delete':
            $contact_id = (int)$_POST['contact_id'];
            
            // Verify contact belongs to user
            $stmt = $pdo->prepare("SELECT id, name FROM contacts WHERE id = ? AND user_id = ?");
            $stmt->execute([$contact_id, $user_id]);
            $contact = $stmt->fetch();
            
            if (!$contact) {
                sendJsonResponse('error', 'Contact not found');
            }
            
            $stmt = $pdo->prepare("DELETE FROM contacts WHERE id = ? AND user_id = ?");
            if ($stmt->execute([$contact_id, $user_id])) {
                sendJsonResponse('success', 'Contact deleted successfully!', [
                    'contact_id' => $contact_id,
                    'contact_name' => $contact['name']
                ]);
            } else {
                sendJsonResponse('error', 'Failed to delete contact');
            }
            break;
            
        case 'import':
            if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] != 0) {
                sendJsonResponse('error', 'Please select a valid CSV file');
            }
            
            $file = $_FILES['csv_file']['tmp_name'];
            $handle = fopen($file, 'r');
            
            $imported = 0;
            $errors = [];
            $duplicates = 0;
            
            // Read header
            $header = fgetcsv($handle);
            
            $pdo->beginTransaction();
            
            try {
                while (($data = fgetcsv($handle)) !== FALSE) {
                    if (count($data) >= 2) {
                        $name = isset($data[0]) ? sanitize($data[0]) : '';
                        $phone = isset($data[1]) ? validatePhone(sanitize($data[1])) : '';
                        $email = isset($data[2]) ? sanitize($data[2]) : null;
                        
                        if ($phone) {
                            // Check if contact exists
                            $stmt = $pdo->prepare("SELECT id FROM contacts WHERE user_id = ? AND phone = ?");
                            $stmt->execute([$user_id, $phone]);
                            if (!$stmt->fetch()) {
                                $stmt = $pdo->prepare("
                                    INSERT INTO contacts (user_id, name, phone, email, created_at) 
                                    VALUES (?, ?, ?, ?, NOW())
                                ");
                                if ($stmt->execute([$user_id, $name, $phone, $email])) {
                                    $imported++;
                                }
                            } else {
                                $duplicates++;
                            }
                        } else {
                            $errors[] = "Invalid phone number: " . ($data[1] ?? 'unknown');
                        }
                    }
                }
                
                $pdo->commit();
                fclose($handle);
                
                $message = "Imported $imported contacts successfully!";
                if ($duplicates > 0) {
                    $message .= " Skipped $duplicates duplicates.";
                }
                
                sendJsonResponse('success', $message, [
                    'imported' => $imported,
                    'duplicates' => $duplicates,
                    'errors' => $errors
                ]);
                
            } catch (Exception $e) {
                $pdo->rollBack();
                fclose($handle);
                sendJsonResponse('error', 'Import failed: ' . $e->getMessage());
            }
            break;
            
        default:
            sendJsonResponse('error', 'Invalid action');
    }
} catch (PDOException $e) {
    error_log("Contacts handler PDO error: " . $e->getMessage());
    error_log("Error code: " . $e->getCode());
    error_log("Error file: " . $e->getFile() . " line: " . $e->getLine());
    
    // Check for specific database errors
    if ($e->getCode() == '42S02') { // Table doesn't exist
        sendJsonResponse('error', 'Database tables not found. Please run the installation script.');
    } elseif ($e->getCode() == '42S22') { // Column not found
        sendJsonResponse('error', 'Database schema mismatch. Please update your database.');
    } elseif (strpos($e->getMessage(), 'Access denied') !== false) {
        sendJsonResponse('error', 'Database access denied. Check your credentials.');
    } elseif (strpos($e->getMessage(), 'Unknown database') !== false) {
        sendJsonResponse('error', 'Database not found. Please create the database first.');
    } else {
        sendJsonResponse('error', 'Database error: ' . $e->getMessage());
    }
} catch (Exception $e) {
    error_log("Contacts handler general error: " . $e->getMessage());
    sendJsonResponse('error', $e->getMessage());
}
?>