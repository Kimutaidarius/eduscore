<?php
// ajax/groups_handler.php
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

$action = isset($_POST['action']) ? $_POST['action'] : '';

try {
    // Test database connection first
    $pdo->query("SELECT 1");
    
    switch ($action) {
        case 'get_groups':
            // Pagination
            $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
            $limit = 20;
            $offset = ($page - 1) * $limit;
            
            // Search filter
            $search = isset($_POST['search']) ? sanitize($_POST['search']) : '';
            
            // Build query with contact counts
            $query = "SELECT g.*, COUNT(c.id) as contact_count 
                      FROM contact_groups g 
                      LEFT JOIN contacts c ON g.id = c.group_id 
                      WHERE g.user_id = ?";
            $params = [$user_id];
            
            if (!empty($search)) {
                $query .= " AND g.name LIKE ?";
                $params[] = "%$search%";
            }
            
            $query .= " GROUP BY g.id ORDER BY g.name ASC LIMIT $limit OFFSET $offset";
            
            // Count query
            $count_query = "SELECT COUNT(*) FROM contact_groups WHERE user_id = ?";
            $count_params = [$user_id];
            
            if (!empty($search)) {
                $count_query .= " AND name LIKE ?";
                $count_params[] = "%$search%";
            }
            
            // Get groups
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $groups = $stmt->fetchAll();
            
            // Get total count
            $stmt = $pdo->prepare($count_query);
            $stmt->execute($count_params);
            $total_groups = $stmt->fetchColumn();
            $total_pages = ceil($total_groups / $limit);
            
            sendJsonResponse('success', 'Groups retrieved', [
                'groups' => $groups,
                'total' => $total_groups,
                'page' => $page,
                'total_pages' => $total_pages
            ]);
            break;
            
        case 'get_group':
            $group_id = (int)$_POST['group_id'];
            
            $stmt = $pdo->prepare("
                SELECT g.*, COUNT(c.id) as contact_count 
                FROM contact_groups g 
                LEFT JOIN contacts c ON g.id = c.group_id 
                WHERE g.id = ? AND g.user_id = ?
                GROUP BY g.id
            ");
            $stmt->execute([$group_id, $user_id]);
            $group = $stmt->fetch();
            
            if (!$group) {
                sendJsonResponse('error', 'Group not found');
            }
            
            // Get contacts in this group
            $stmt = $pdo->prepare("
                SELECT id, name, phone, email 
                FROM contacts 
                WHERE group_id = ? AND user_id = ?
                ORDER BY name ASC
                LIMIT 10
            ");
            $stmt->execute([$group_id, $user_id]);
            $contacts = $stmt->fetchAll();
            
            sendJsonResponse('success', 'Group retrieved', [
                'group' => $group,
                'contacts' => $contacts
            ]);
            break;
            
        case 'add':
            $name = isset($_POST['name']) ? sanitize($_POST['name']) : '';
            $description = isset($_POST['description']) ? sanitize($_POST['description']) : '';
            
            if (empty($name)) {
                sendJsonResponse('error', 'Group name is required');
            }
            
            // Check if group already exists
            $stmt = $pdo->prepare("SELECT id FROM contact_groups WHERE user_id = ? AND name = ?");
            $stmt->execute([$user_id, $name]);
            if ($stmt->fetch()) {
                sendJsonResponse('error', 'A group with this name already exists');
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO contact_groups (user_id, name, description, created_at) 
                VALUES (?, ?, ?, NOW())
            ");
            
            if ($stmt->execute([$user_id, $name, $description])) {
                $group_id = $pdo->lastInsertId();
                
                // Get the newly created group
                $stmt = $pdo->prepare("
                    SELECT g.*, COUNT(c.id) as contact_count 
                    FROM contact_groups g 
                    LEFT JOIN contacts c ON g.id = c.group_id 
                    WHERE g.id = ?
                    GROUP BY g.id
                ");
                $stmt->execute([$group_id]);
                $new_group = $stmt->fetch();
                
                sendJsonResponse('success', 'Group created successfully!', ['group' => $new_group]);
            } else {
                sendJsonResponse('error', 'Failed to create group');
            }
            break;
            
        case 'edit':
            $group_id = (int)$_POST['group_id'];
            $name = isset($_POST['name']) ? sanitize($_POST['name']) : '';
            $description = isset($_POST['description']) ? sanitize($_POST['description']) : '';
            
            if (empty($name)) {
                sendJsonResponse('error', 'Group name is required');
            }
            
            // Verify group belongs to user
            $stmt = $pdo->prepare("SELECT id FROM contact_groups WHERE id = ? AND user_id = ?");
            $stmt->execute([$group_id, $user_id]);
            if (!$stmt->fetch()) {
                sendJsonResponse('error', 'Group not found');
            }
            
            // Check if another group with this name exists
            $stmt = $pdo->prepare("SELECT id FROM contact_groups WHERE user_id = ? AND name = ? AND id != ?");
            $stmt->execute([$user_id, $name, $group_id]);
            if ($stmt->fetch()) {
                sendJsonResponse('error', 'Another group with this name already exists');
            }
            
            $stmt = $pdo->prepare("
                UPDATE contact_groups 
                SET name = ?, description = ? 
                WHERE id = ? AND user_id = ?
            ");
            
            if ($stmt->execute([$name, $description, $group_id, $user_id])) {
                // Get updated group
                $stmt = $pdo->prepare("
                    SELECT g.*, COUNT(c.id) as contact_count 
                    FROM contact_groups g 
                    LEFT JOIN contacts c ON g.id = c.group_id 
                    WHERE g.id = ?
                    GROUP BY g.id
                ");
                $stmt->execute([$group_id]);
                $updated_group = $stmt->fetch();
                
                sendJsonResponse('success', 'Group updated successfully!', ['group' => $updated_group]);
            } else {
                sendJsonResponse('error', 'Failed to update group');
            }
            break;
            
        case 'delete':
            $group_id = (int)$_POST['group_id'];
            
            // Verify group belongs to user
            $stmt = $pdo->prepare("SELECT id, name FROM contact_groups WHERE id = ? AND user_id = ?");
            $stmt->execute([$group_id, $user_id]);
            $group = $stmt->fetch();
            
            if (!$group) {
                sendJsonResponse('error', 'Group not found');
            }
            
            // Check if group has contacts
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM contacts WHERE group_id = ? AND user_id = ?");
            $stmt->execute([$group_id, $user_id]);
            $contact_count = $stmt->fetchColumn();
            
            if ($contact_count > 0) {
                // Option 1: Prevent deletion if group has contacts
                // sendJsonResponse('error', 'Cannot delete group that has contacts. Please reassign or delete contacts first.');
                
                // Option 2: Remove group_id from contacts (set to NULL)
                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare("UPDATE contacts SET group_id = NULL WHERE group_id = ? AND user_id = ?");
                    $stmt->execute([$group_id, $user_id]);
                    
                    $stmt = $pdo->prepare("DELETE FROM contact_groups WHERE id = ? AND user_id = ?");
                    $stmt->execute([$group_id, $user_id]);
                    
                    $pdo->commit();
                    
                    sendJsonResponse('success', 'Group deleted successfully! Contacts have been moved to "No Group".', [
                        'group_id' => $group_id,
                        'group_name' => $group['name'],
                        'contacts_affected' => $contact_count
                    ]);
                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw $e;
                }
            } else {
                // Delete empty group
                $stmt = $pdo->prepare("DELETE FROM contact_groups WHERE id = ? AND user_id = ?");
                if ($stmt->execute([$group_id, $user_id])) {
                    sendJsonResponse('success', 'Group deleted successfully!', [
                        'group_id' => $group_id,
                        'group_name' => $group['name']
                    ]);
                } else {
                    sendJsonResponse('error', 'Failed to delete group');
                }
            }
            break;
            
        case 'get_group_contacts':
            $group_id = (int)$_POST['group_id'];
            $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
            $limit = 20;
            $offset = ($page - 1) * $limit;
            
            // Verify group belongs to user
            $stmt = $pdo->prepare("SELECT id FROM contact_groups WHERE id = ? AND user_id = ?");
            $stmt->execute([$group_id, $user_id]);
            if (!$stmt->fetch()) {
                sendJsonResponse('error', 'Group not found');
            }
            
            // Get contacts in this group
            $query = "SELECT id, name, phone, email, created_at 
                      FROM contacts 
                      WHERE group_id = ? AND user_id = ?
                      ORDER BY name ASC
                      LIMIT $limit OFFSET $offset";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute([$group_id, $user_id]);
            $contacts = $stmt->fetchAll();
            
            // Get total count
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM contacts WHERE group_id = ? AND user_id = ?");
            $stmt->execute([$group_id, $user_id]);
            $total_contacts = $stmt->fetchColumn();
            $total_pages = ceil($total_contacts / $limit);
            
            sendJsonResponse('success', 'Group contacts retrieved', [
                'contacts' => $contacts,
                'total' => $total_contacts,
                'page' => $page,
                'total_pages' => $total_pages
            ]);
            break;
            
        case 'remove_contact_from_group':
            $contact_id = (int)$_POST['contact_id'];
            $group_id = (int)$_POST['group_id'];
            
            // Verify contact belongs to user and is in this group
            $stmt = $pdo->prepare("
                SELECT id FROM contacts 
                WHERE id = ? AND user_id = ? AND group_id = ?
            ");
            $stmt->execute([$contact_id, $user_id, $group_id]);
            if (!$stmt->fetch()) {
                sendJsonResponse('error', 'Contact not found in this group');
            }
            
            $stmt = $pdo->prepare("UPDATE contacts SET group_id = NULL WHERE id = ? AND user_id = ?");
            if ($stmt->execute([$contact_id, $user_id])) {
                sendJsonResponse('success', 'Contact removed from group');
            } else {
                sendJsonResponse('error', 'Failed to remove contact from group');
            }
            break;
            
        default:
            sendJsonResponse('error', 'Invalid action');
    }
} catch (PDOException $e) {
    error_log("Groups handler PDO error: " . $e->getMessage());
    error_log("Error code: " . $e->getCode());
    error_log("Error file: " . $e->getFile() . " line: " . $e->getLine());
    
    sendJsonResponse('error', 'Database error: ' . $e->getMessage());
} catch (Exception $e) {
    error_log("Groups handler general error: " . $e->getMessage());
    sendJsonResponse('error', $e->getMessage());
}
?>