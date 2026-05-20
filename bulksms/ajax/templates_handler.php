<?php
// ajax/templates_handler.php
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

// Define calculateSmsParts function if not already defined
if (!function_exists('calculateSmsParts')) {
    function calculateSmsParts($message) {
        $length = strlen($message);
        if ($length <= 160) return 1;
        if ($length <= 306) return 2;
        if ($length <= 459) return 3;
        return ceil($length / 153);
    }
}

$action = isset($_POST['action']) ? $_POST['action'] : '';

try {
    // Test database connection first
    $pdo->query("SELECT 1");
    
    switch ($action) {
        case 'get_templates':
            // Pagination
            $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
            $limit = 12;
            $offset = ($page - 1) * $limit;
            
            // Filters
            $category_filter = isset($_POST['category']) ? sanitize($_POST['category']) : '';
            $search = isset($_POST['search']) ? sanitize($_POST['search']) : '';
            
            // Build main query
            $query = "SELECT * FROM message_templates WHERE user_id = ?";
            $params = [$user_id];
            
            if (!empty($category_filter) && $category_filter !== 'all') {
                $query .= " AND category = ?";
                $params[] = $category_filter;
            }
            
            if (!empty($search)) {
                $query .= " AND (name LIKE ? OR message LIKE ?)";
                $search_param = "%$search%";
                $params[] = $search_param;
                $params[] = $search_param;
            }
            
            $query .= " ORDER BY category, name ASC LIMIT $limit OFFSET $offset";
            
            // Get templates
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $templates = $stmt->fetchAll();
            
            // Build count query separately
            $count_query = "SELECT COUNT(*) FROM message_templates WHERE user_id = ?";
            $count_params = [$user_id];
            
            if (!empty($category_filter) && $category_filter !== 'all') {
                $count_query .= " AND category = ?";
                $count_params[] = $category_filter;
            }
            
            if (!empty($search)) {
                $count_query .= " AND (name LIKE ? OR message LIKE ?)";
                $count_params[] = "%$search%";
                $count_params[] = "%$search%";
            }
            
            // Get total count
            $stmt = $pdo->prepare($count_query);
            $stmt->execute($count_params);
            $total_templates = $stmt->fetchColumn();
            $total_pages = ceil($total_templates / $limit);
            
            // Get unique categories for filter
            $cat_query = "SELECT DISTINCT category FROM message_templates WHERE user_id = ? AND category IS NOT NULL ORDER BY category";
            $stmt = $pdo->prepare($cat_query);
            $stmt->execute([$user_id]);
            $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            sendJsonResponse('success', 'Templates retrieved', [
                'templates' => $templates,
                'categories' => $categories,
                'total' => $total_templates,
                'page' => $page,
                'total_pages' => $total_pages
            ]);
            break;
            
        case 'get_template':
            $template_id = (int)$_POST['template_id'];
            
            $stmt = $pdo->prepare("SELECT * FROM message_templates WHERE id = ? AND user_id = ?");
            $stmt->execute([$template_id, $user_id]);
            $template = $stmt->fetch();
            
            if (!$template) {
                sendJsonResponse('error', 'Template not found');
            }
            
            sendJsonResponse('success', 'Template retrieved', ['template' => $template]);
            break;
            
        case 'add':
            $name = isset($_POST['name']) ? sanitize($_POST['name']) : '';
            $message = isset($_POST['message']) ? trim($_POST['message']) : '';
            $category = isset($_POST['category']) ? sanitize($_POST['category']) : 'general';
            
            if (empty($name)) {
                sendJsonResponse('error', 'Template name is required');
            }
            
            if (empty($message)) {
                sendJsonResponse('error', 'Message content is required');
            }
            
            // Check if template with same name exists
            $stmt = $pdo->prepare("SELECT id FROM message_templates WHERE user_id = ? AND name = ?");
            $stmt->execute([$user_id, $name]);
            if ($stmt->fetch()) {
                sendJsonResponse('error', 'A template with this name already exists');
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO message_templates (user_id, name, message, category, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            
            if ($stmt->execute([$user_id, $name, $message, $category])) {
                $template_id = $pdo->lastInsertId();
                
                // Get the newly created template
                $stmt = $pdo->prepare("SELECT * FROM message_templates WHERE id = ?");
                $stmt->execute([$template_id]);
                $new_template = $stmt->fetch();
                
                sendJsonResponse('success', 'Template created successfully!', ['template' => $new_template]);
            } else {
                sendJsonResponse('error', 'Failed to create template');
            }
            break;
            
        case 'edit':
            $template_id = (int)$_POST['template_id'];
            $name = isset($_POST['name']) ? sanitize($_POST['name']) : '';
            $message = isset($_POST['message']) ? trim($_POST['message']) : '';
            $category = isset($_POST['category']) ? sanitize($_POST['category']) : 'general';
            
            if (empty($name)) {
                sendJsonResponse('error', 'Template name is required');
            }
            
            if (empty($message)) {
                sendJsonResponse('error', 'Message content is required');
            }
            
            // Verify template belongs to user
            $stmt = $pdo->prepare("SELECT id FROM message_templates WHERE id = ? AND user_id = ?");
            $stmt->execute([$template_id, $user_id]);
            if (!$stmt->fetch()) {
                sendJsonResponse('error', 'Template not found');
            }
            
            // Check if another template with this name exists
            $stmt = $pdo->prepare("SELECT id FROM message_templates WHERE user_id = ? AND name = ? AND id != ?");
            $stmt->execute([$user_id, $name, $template_id]);
            if ($stmt->fetch()) {
                sendJsonResponse('error', 'Another template with this name already exists');
            }
            
            $stmt = $pdo->prepare("
                UPDATE message_templates 
                SET name = ?, message = ?, category = ? 
                WHERE id = ? AND user_id = ?
            ");
            
            if ($stmt->execute([$name, $message, $category, $template_id, $user_id])) {
                // Get updated template
                $stmt = $pdo->prepare("SELECT * FROM message_templates WHERE id = ?");
                $stmt->execute([$template_id]);
                $updated_template = $stmt->fetch();
                
                sendJsonResponse('success', 'Template updated successfully!', ['template' => $updated_template]);
            } else {
                sendJsonResponse('error', 'Failed to update template');
            }
            break;
            
        case 'delete':
            $template_id = (int)$_POST['template_id'];
            
            // Verify template belongs to user
            $stmt = $pdo->prepare("SELECT id, name FROM message_templates WHERE id = ? AND user_id = ?");
            $stmt->execute([$template_id, $user_id]);
            $template = $stmt->fetch();
            
            if (!$template) {
                sendJsonResponse('error', 'Template not found');
            }
            
            $stmt = $pdo->prepare("DELETE FROM message_templates WHERE id = ? AND user_id = ?");
            if ($stmt->execute([$template_id, $user_id])) {
                sendJsonResponse('success', 'Template deleted successfully!', [
                    'template_id' => $template_id,
                    'template_name' => $template['name']
                ]);
            } else {
                sendJsonResponse('error', 'Failed to delete template');
            }
            break;
            
        case 'duplicate':
            $template_id = (int)$_POST['template_id'];
            
            // Get original template
            $stmt = $pdo->prepare("SELECT * FROM message_templates WHERE id = ? AND user_id = ?");
            $stmt->execute([$template_id, $user_id]);
            $template = $stmt->fetch();
            
            if (!$template) {
                sendJsonResponse('error', 'Template not found');
            }
            
            // Create duplicate with "(Copy)" suffix
            $new_name = $template['name'] . ' (Copy)';
            
            // If name already exists, add number
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM message_templates WHERE user_id = ? AND name LIKE ?");
            $stmt->execute([$user_id, $new_name . '%']);
            $count = $stmt->fetchColumn();
            
            if ($count > 0) {
                $new_name = $template['name'] . ' (Copy ' . ($count + 1) . ')';
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO message_templates (user_id, name, message, category, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            
            if ($stmt->execute([$user_id, $new_name, $template['message'], $template['category']])) {
                $new_id = $pdo->lastInsertId();
                
                // Get the duplicated template
                $stmt = $pdo->prepare("SELECT * FROM message_templates WHERE id = ?");
                $stmt->execute([$new_id]);
                $new_template = $stmt->fetch();
                
                sendJsonResponse('success', 'Template duplicated successfully!', ['template' => $new_template]);
            } else {
                sendJsonResponse('error', 'Failed to duplicate template');
            }
            break;
            
        case 'preview':
            $message = isset($_POST['message']) ? $_POST['message'] : '';
            $sample_name = isset($_POST['sample_name']) ? $_POST['sample_name'] : 'John Doe';
            $sample_phone = isset($_POST['sample_phone']) ? $_POST['sample_phone'] : '254712345678';
            
            // Replace variables with sample data
            $preview = str_replace(
                ['{contact_name}', '{contact_phone}', '{company_name}', '{date}'],
                [$sample_name, $sample_phone, APP_NAME, date('Y-m-d')],
                $message
            );
            
            $sms_parts = calculateSmsParts($preview);
            
            sendJsonResponse('success', 'Preview generated', [
                'preview' => $preview,
                'char_count' => strlen($preview),
                'sms_parts' => $sms_parts
            ]);
            break;
            
        default:
            sendJsonResponse('error', 'Invalid action');
    }
} catch (PDOException $e) {
    error_log("Templates handler PDO error: " . $e->getMessage());
    error_log("Error code: " . $e->getCode());
    error_log("Error file: " . $e->getFile() . " line: " . $e->getLine());
    
    // Check for specific database errors
    if ($e->getCode() == '42S02') {
        sendJsonResponse('error', 'Database tables not found. Please run the installation script.');
    } elseif ($e->getCode() == '42S22') {
        sendJsonResponse('error', 'Database schema mismatch. Please update your database.');
    } elseif (strpos($e->getMessage(), 'Access denied') !== false) {
        sendJsonResponse('error', 'Database access denied. Check your credentials.');
    } elseif (strpos($e->getMessage(), 'Unknown database') !== false) {
        sendJsonResponse('error', 'Database not found. Please create the database first.');
    } else {
        sendJsonResponse('error', 'Database error: ' . $e->getMessage());
    }
} catch (Exception $e) {
    error_log("Templates handler general error: " . $e->getMessage());
    sendJsonResponse('error', $e->getMessage());
}
?>