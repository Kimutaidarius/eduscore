<?php
require_once '../config/config.php';
requireLogin();

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

$user_id = $_SESSION['user_id'];

// Get user data
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    if (!$user) {
        throw new Exception("User not found");
    }
} catch (Exception $e) {
    error_log("User fetch error: " . $e->getMessage());
    $_SESSION['error'] = "Error loading user data";
    header('Location: dashboard.php');
    exit();
}

// Handle API key actions via POST
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Debug: Log POST data
    error_log("POST received: " . print_r($_POST, true));
    
    // CSRF check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        error_log("CSRF token mismatch");
        $_SESSION['error'] = 'Invalid security token';
        header('Location: api-keys.php');
        exit();
    }
    
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $key_id = isset($_POST['key_id']) ? (int)$_POST['key_id'] : 0;
    
    try {
        if ($action == 'generate') {
            // Begin transaction
            $pdo->beginTransaction();
            
            // Count existing keys
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM api_keys WHERE user_id = ?");
            $countStmt->execute([$user_id]);
            $existingCount = $countStmt->fetchColumn();
            
            // Delete ALL existing API keys for this user
            $deleteStmt = $pdo->prepare("DELETE FROM api_keys WHERE user_id = ?");
            $deleteStmt->execute([$user_id]);
            
            error_log("Deleted $existingCount existing API keys for user: $user_id");
            
            // Generate new API key
            $api_key = 'esk_live_' . bin2hex(random_bytes(24));
            $api_secret = bin2hex(random_bytes(32));
            $name = isset($_POST['name']) ? sanitize($_POST['name']) : 'My API Key';
            
            if (empty($name)) {
                $name = 'My API Key';
            }
            
            error_log("Generating new API key for user: $user_id with name: $name");
            
            $stmt = $pdo->prepare("
                INSERT INTO api_keys (user_id, api_key, api_secret, name, status, created_at) 
                VALUES (?, ?, ?, ?, 'active', NOW())
            ");
            
            if ($stmt->execute([$user_id, $api_key, $api_secret, $name])) {
                $new_key_id = $pdo->lastInsertId();
                error_log("API key generated successfully with ID: $new_key_id");
                
                // Commit transaction
                $pdo->commit();
                
                // Log the key generation
                try {
                    $log_stmt = $pdo->prepare("
                        INSERT INTO api_requests (user_id, endpoint, method, ip_address, user_agent, response_code) 
                        VALUES (?, 'api_key_generate', 'POST', ?, ?, 200)
                    ");
                    $log_stmt->execute([
                        $user_id,
                        $_SERVER['REMOTE_ADDR'] ?? '',
                        substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)
                    ]);
                } catch (Exception $logError) {
                    error_log("Failed to log API key generation: " . $logError->getMessage());
                }
                
                $_SESSION['success'] = 'New API key generated successfully! All previous keys have been revoked.';
                $_SESSION['new_key_secret'] = $api_secret;
            } else {
                $pdo->rollBack();
                throw new Exception('Failed to insert API key into database');
            }
        } elseif ($action == 'regenerate' && $key_id) {
            // Verify key belongs to user
            $stmt = $pdo->prepare("SELECT id FROM api_keys WHERE id = ? AND user_id = ?");
            $stmt->execute([$key_id, $user_id]);
            if (!$stmt->fetch()) {
                throw new Exception('Invalid API key');
            }
            
            // Regenerate existing API key
            $api_key = 'esk_live_' . bin2hex(random_bytes(24));
            $api_secret = bin2hex(random_bytes(32));
            
            $stmt = $pdo->prepare("UPDATE api_keys SET api_key = ?, api_secret = ?, last_used = NULL WHERE id = ? AND user_id = ?");
            if ($stmt->execute([$api_key, $api_secret, $key_id, $user_id])) {
                $_SESSION['success'] = 'API key regenerated successfully!';
                $_SESSION['regenerated_secret'] = $api_secret;
                $_SESSION['regenerated_key_id'] = $key_id;
            }
        } elseif ($action == 'toggle_status' && $key_id) {
            // Verify key belongs to user
            $stmt = $pdo->prepare("SELECT status FROM api_keys WHERE id = ? AND user_id = ?");
            $stmt->execute([$key_id, $user_id]);
            $key = $stmt->fetch();
            if (!$key) {
                throw new Exception('Invalid API key');
            }
            
            // Toggle API key status
            $new_status = $key['status'] == 'active' ? 'inactive' : 'active';
            
            $stmt = $pdo->prepare("UPDATE api_keys SET status = ? WHERE id = ? AND user_id = ?");
            if ($stmt->execute([$new_status, $key_id, $user_id])) {
                $_SESSION['success'] = 'API key ' . ($new_status == 'active' ? 'activated' : 'deactivated') . ' successfully!';
            }
        } elseif ($action == 'update_name' && $key_id) {
            $new_name = isset($_POST['name']) ? sanitize($_POST['name']) : '';
            if (empty($new_name)) {
                throw new Exception('Name cannot be empty');
            }
            
            $stmt = $pdo->prepare("UPDATE api_keys SET name = ? WHERE id = ? AND user_id = ?");
            if ($stmt->execute([$new_name, $key_id, $user_id])) {
                $_SESSION['success'] = 'API key name updated successfully!';
            }
        } elseif ($action == 'delete' && $key_id) {
            // Verify key belongs to user
            $stmt = $pdo->prepare("SELECT id FROM api_keys WHERE id = ? AND user_id = ?");
            $stmt->execute([$key_id, $user_id]);
            if (!$stmt->fetch()) {
                throw new Exception('Invalid API key');
            }
            
            // Delete API key
            $stmt = $pdo->prepare("DELETE FROM api_keys WHERE id = ? AND user_id = ?");
            if ($stmt->execute([$key_id, $user_id])) {
                $_SESSION['success'] = 'API key deleted successfully!';
            }
        } else {
            throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        // Rollback transaction if active
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("API key action error: " . $e->getMessage());
        $_SESSION['error'] = $e->getMessage();
    }
    
    header('Location: api-keys.php');
    exit();
}

// Get API keys
try {
    $stmt = $pdo->prepare("SELECT * FROM api_keys WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $api_keys = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching API keys: " . $e->getMessage());
    $api_keys = [];
}

// Get REAL API usage statistics from database
try {
    // Get overall stats
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_requests,
            COUNT(DISTINCT DATE(created_at)) as active_days,
            SUM(CASE WHEN response_code = 200 THEN 1 ELSE 0 END) as successful_requests,
            MAX(created_at) as last_request
        FROM api_requests 
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $api_stats = $stmt->fetch();
    
    // Ensure all values are set
    $api_stats['total_requests'] = $api_stats['total_requests'] ?? 0;
    $api_stats['active_days'] = $api_stats['active_days'] ?? 0;
    $api_stats['successful_requests'] = $api_stats['successful_requests'] ?? 0;
    
    // Calculate success rate
    $success_rate = $api_stats['total_requests'] > 0 
        ? round(($api_stats['successful_requests'] / $api_stats['total_requests']) * 100, 1) 
        : 0;
    
} catch (Exception $e) {
    error_log("Error fetching API stats: " . $e->getMessage());
    $api_stats = ['total_requests' => 0, 'active_days' => 0, 'successful_requests' => 0, 'last_request' => null];
    $success_rate = 0;
}

// Get requests by endpoint (top 5)
try {
    $stmt = $pdo->prepare("
        SELECT 
            endpoint, 
            COUNT(*) as count,
            SUM(CASE WHEN response_code = 200 THEN 1 ELSE 0 END) as successful,
            AVG(response_code) as avg_code
        FROM api_requests 
        WHERE user_id = ? 
        GROUP BY endpoint 
        ORDER BY count DESC 
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $top_endpoints = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching top endpoints: " . $e->getMessage());
    $top_endpoints = [];
}

// Get requests by status code distribution
try {
    $stmt = $pdo->prepare("
        SELECT 
            response_code,
            COUNT(*) as count
        FROM api_requests 
        WHERE user_id = ? 
        GROUP BY response_code
        ORDER BY count DESC
    ");
    $stmt->execute([$user_id]);
    $status_distribution = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching status distribution: " . $e->getMessage());
    $status_distribution = [];
}

// Get recent API activity (last 10 requests)
try {
    $stmt = $pdo->prepare("
        SELECT 
            endpoint, 
            method, 
            response_code,
            created_at
        FROM api_requests 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $recent_activity = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching recent activity: " . $e->getMessage());
    $recent_activity = [];
}

// Check for session messages
if (isset($_SESSION['success'])) {
    $message = $_SESSION['success'];
    $message_type = 'success';
    unset($_SESSION['success']);
} elseif (isset($_SESSION['error'])) {
    $message = $_SESSION['error'];
    $message_type = 'danger';
    unset($_SESSION['error']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Keys - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        /* Your existing styles remain the same */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background-color: #ffffff;
            overflow-x: hidden;
        }

        .main-content {
            margin-left: 250px;
            margin-top: 60px;
            padding: 30px;
            background-color: #ffffff;
            min-height: calc(100vh - 60px);
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-header h2 {
            color: #1e3a8a;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .page-header .breadcrumb {
            background: none;
            padding: 0;
            margin: 0;
        }

        .page-header .breadcrumb-item a {
            color: #666666;
            text-decoration: none;
        }

        .page-header .breadcrumb-item.active {
            color: #1e3a8a;
        }

        .card {
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }

        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #e0e0e0;
            padding: 15px 20px;
            font-weight: 600;
            color: #1e3a8a;
            border-radius: 12px 12px 0 0 !important;
        }

        .card-header i {
            margin-right: 8px;
        }

        .card-body {
            padding: 20px;
        }

        .stat-card {
            background: linear-gradient(135deg, #1e3a8a 0%, #152b63 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
        }

        .stat-card .label {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 5px;
        }

        .stat-card .value {
            font-size: 28px;
            font-weight: 700;
        }

        .stat-card .small {
            font-size: 12px;
            opacity: 0.8;
        }

        .api-key-card {
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.2s ease;
        }

        .api-key-card:hover {
            border-color: #1e3a8a;
            box-shadow: 0 4px 12px rgba(30, 58, 138, 0.1);
        }

        .api-key-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .api-key-name {
            font-weight: 600;
            color: #333333;
            font-size: 16px;
        }

        .api-key-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-active {
            background-color: #d1fae5;
            color: #065f46;
        }

        .status-inactive {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .status-revoked {
            background-color: #f3f4f6;
            color: #374151;
        }

        .api-key-display {
            background-color: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 12px 15px;
            margin-bottom: 10px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .api-key-value {
            color: #1e3a8a;
            word-break: break-all;
        }

        .api-secret-value {
            color: #666666;
            word-break: break-all;
        }

        .copy-btn {
            background: none;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            padding: 5px 10px;
            color: #666666;
            transition: all 0.2s ease;
        }

        .copy-btn:hover {
            border-color: #1e3a8a;
            color: #1e3a8a;
            background-color: #f8f9fa;
        }

        .api-key-meta {
            display: flex;
            gap: 20px;
            font-size: 12px;
            color: #666666;
            margin-top: 10px;
        }

        .api-key-meta i {
            margin-right: 4px;
            color: #1e3a8a;
        }

        .btn-primary {
            background-color: #1e3a8a;
            border: 1px solid #152b63;
            color: #ffffff;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .btn-primary:hover {
            background-color: #152b63;
            transform: translateY(-1px);
        }

        .btn-outline-primary {
            border: 1px solid #1e3a8a;
            color: #1e3a8a;
            background: transparent;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 500;
        }

        .btn-outline-primary:hover {
            background-color: #1e3a8a;
            color: #ffffff;
        }

        .btn-outline-danger {
            border: 1px solid #dc3545;
            color: #dc3545;
            background: transparent;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 500;
        }

        .btn-outline-danger:hover {
            background-color: #dc3545;
            color: #ffffff;
        }

        .btn-outline-success {
            border: 1px solid #10b981;
            color: #10b981;
            background: transparent;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 500;
        }

        .btn-outline-success:hover {
            background-color: #10b981;
            color: #ffffff;
        }

        .alert {
            border: none;
            border-radius: 8px;
            padding: 15px 20px;
            margin-bottom: 20px;
        }

        .alert-success {
            background-color: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        .alert-danger {
            background-color: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #dc2626;
        }

        .alert-info {
            background-color: #dbeafe;
            color: #1e40af;
            border-left: 4px solid #1e3a8a;
        }

        .alert-warning {
            background-color: #fff3cd;
            color: #856404;
            border-left: 4px solid #ffc107;
        }

        .endpoint-list {
            list-style: none;
            padding: 0;
        }

        .endpoint-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #e0e0e0;
        }

        .endpoint-item:last-child {
            border-bottom: none;
        }

        .endpoint-name {
            font-family: 'Courier New', monospace;
            font-size: 13px;
            color: #333333;
        }

        .endpoint-count {
            background-color: #f8f9fa;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 12px;
            color: #666666;
        }

        .activity-item {
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-time {
            font-size: 11px;
            color: #999;
        }

        .badge-200 {
            background-color: #d1fae5;
            color: #065f46;
        }

        .badge-400, .badge-401, .badge-403, .badge-404 {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .badge-500 {
            background-color: #ffedd5;
            color: #c2410c;
        }

        .modal-header {
            background-color: #1e3a8a;
            color: white;
        }

        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }

        .secret-reveal {
            background-color: #fff3cd;
            border: 1px solid #ffeeba;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
            font-family: 'Courier New', monospace;
            word-break: break-all;
        }

        .toast-container {
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 9999;
        }

        .toast {
            background-color: white;
            border-left: 4px solid #1e3a8a;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            min-width: 300px;
        }

        .toast.success {
            border-left-color: #10b981;
        }

        .toast.error {
            border-left-color: #dc2626;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }

            .api-key-meta {
                flex-direction: column;
                gap: 5px;
            }

            .toast-container {
                top: 70px;
                right: 10px;
                left: 10px;
            }

            .toast {
                min-width: auto;
            }
        }
    </style>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php 
    // Fix include paths
    $sidebar_path = dirname(__DIR__) . '/includes/sidebar.php';
    $topbar_path = dirname(__DIR__) . '/includes/topbar.php';
    
    if (file_exists($sidebar_path)) {
        include $sidebar_path;
    } else {
        echo '<div style="color:red; margin-left:250px; margin-top:60px;">Warning: Sidebar not found</div>';
    }
    
    if (file_exists($topbar_path)) {
        include $topbar_path;
    } else {
        echo '<div style="color:red; margin-left:250px; margin-top:60px;">Warning: Topbar not found</div>';
    }
    ?>

    <!-- Toast Container for Notifications -->
    <div class="toast-container" id="toastContainer"></div>

    <div class="main-content">
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2><i class="bi bi-key me-2"></i>API Keys</h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">API Keys</li>
                        </ol>
                    </nav>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#generateKeyModal">
                    <i class="bi bi-plus-lg"></i> Generate New Key
                </button>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <i class="bi bi-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-triangle'; ?>-fill me-2"></i>
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Show new secret if just generated -->
        <?php if (isset($_SESSION['new_key_secret'])): ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <strong>IMPORTANT: Save this secret now!</strong> This is the only time it will be shown.
                <div class="secret-reveal mt-2">
                    <?php echo htmlspecialchars($_SESSION['new_key_secret']); ?>
                </div>
                <button class="btn btn-sm btn-outline-secondary mt-2" onclick="copyToClipboard('<?php echo $_SESSION['new_key_secret']; ?>')">
                    <i class="bi bi-clipboard"></i> Copy Secret
                </button>
            </div>
            <?php unset($_SESSION['new_key_secret']); ?>
        <?php endif; ?>

        <!-- Show regenerated secret if just regenerated -->
        <?php if (isset($_SESSION['regenerated_secret'])): ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <strong>New API Secret Generated!</strong> Save this now - it won't be shown again.
                <div class="secret-reveal mt-2">
                    <?php echo htmlspecialchars($_SESSION['regenerated_secret']); ?>
                </div>
                <button class="btn btn-sm btn-outline-secondary mt-2" onclick="copyToClipboard('<?php echo $_SESSION['regenerated_secret']; ?>')">
                    <i class="bi bi-clipboard"></i> Copy Secret
                </button>
            </div>
            <?php unset($_SESSION['regenerated_secret']); ?>
        <?php endif; ?>

        <!-- API Statistics - Updated with REAL data from database -->
        <div class="row">
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="label">Total API Requests</div>
                    <div class="value"><?php echo number_format($api_stats['total_requests']); ?></div>
                    <div class="small">Last request: <?php echo $api_stats['last_request'] ? date('M d, Y', strtotime($api_stats['last_request'])) : 'Never'; ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card" style="background: linear-gradient(135deg, #059669 0%, #047857 100%);">
                    <div class="label">Successful Requests</div>
                    <div class="value"><?php echo number_format($api_stats['successful_requests']); ?></div>
                    <div class="small">Success rate: <?php echo $success_rate; ?>%</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card" style="background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);">
                    <div class="label">Active Days</div>
                    <div class="value"><?php echo number_format($api_stats['active_days']); ?></div>
                    <div class="small">Days with API activity</div>
                </div>
            </div>
        </div>

        <!-- API Keys List -->
        <div class="card">
            <div class="card-header">
                <i class="bi bi-key"></i> Your API Keys
            </div>
            <div class="card-body">
                <?php if (empty($api_keys)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-key" style="font-size: 48px; color: #e0e0e0;"></i>
                        <h5 class="mt-3">No API Keys Found</h5>
                        <p class="text-muted">Generate your first API key to start using the SMS API.</p>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#generateKeyModal">
                            <i class="bi bi-plus-lg"></i> Generate API Key
                        </button>
                    </div>
                <?php else: ?>
                    <?php foreach ($api_keys as $key): ?>
                        <div class="api-key-card" id="key-<?php echo $key['id']; ?>">
                            <div class="api-key-header">
                                <div class="d-flex align-items-center">
                                    <span class="api-key-name" id="key-name-<?php echo $key['id']; ?>"><?php echo htmlspecialchars($key['name']); ?></span>
                                    <span class="api-key-status status-<?php echo $key['status']; ?> ms-2">
                                        <?php echo ucfirst($key['status']); ?>
                                    </span>
                                </div>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <button class="dropdown-item" onclick="editKeyName(<?php echo $key['id']; ?>, '<?php echo htmlspecialchars($key['name']); ?>')">
                                                <i class="bi bi-pencil"></i> Edit Name
                                            </button>
                                        </li>
                                        <li>
                                            <button class="dropdown-item" onclick="regenerateKey(<?php echo $key['id']; ?>)">
                                                <i class="bi bi-arrow-counterclockwise"></i> Regenerate
                                            </button>
                                        </li>
                                        <?php if ($key['status'] == 'active'): ?>
                                            <li>
                                                <button class="dropdown-item text-warning" onclick="toggleKeyStatus(<?php echo $key['id']; ?>, 'inactive')">
                                                    <i class="bi bi-pause-circle"></i> Deactivate
                                                </button>
                                            </li>
                                        <?php else: ?>
                                            <li>
                                                <button class="dropdown-item text-success" onclick="toggleKeyStatus(<?php echo $key['id']; ?>, 'active')">
                                                    <i class="bi bi-play-circle"></i> Activate
                                                </button>
                                            </li>
                                        <?php endif; ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <button class="dropdown-item text-danger" onclick="deleteKey(<?php echo $key['id']; ?>)">
                                                <i class="bi bi-trash"></i> Delete
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            
                            <div class="api-key-display">
                                <span class="api-key-value"><?php echo htmlspecialchars($key['api_key']); ?></span>
                                <button class="copy-btn" onclick="copyToClipboard('<?php echo $key['api_key']; ?>')">
                                    <i class="bi bi-clipboard"></i> Copy
                                </button>
                            </div>
                            
                            <div class="api-key-display">
                                <span class="api-secret-value" id="secret-<?php echo $key['id']; ?>">••••••••••••••••••••••••••••••••</span>
                                <button class="copy-btn" onclick="revealSecret(<?php echo $key['id']; ?>, '<?php echo $key['api_secret']; ?>')" id="secret-btn-<?php echo $key['id']; ?>">
                                    <i class="bi bi-eye"></i> Show
                                </button>
                            </div>
                            
                            <div class="api-key-meta">
                                <span><i class="bi bi-calendar"></i> Created: <?php echo date('M d, Y', strtotime($key['created_at'])); ?></span>
                                <?php if ($key['last_used']): ?>
                                    <span><i class="bi bi-clock-history"></i> Last used: <?php echo date('M d, Y H:i', strtotime($key['last_used'])); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- API Usage Statistics - Updated with REAL data -->
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-graph-up"></i> Top Endpoints
                    </div>
                    <div class="card-body">
                        <?php if (empty($top_endpoints)): ?>
                            <p class="text-muted text-center py-3">No API requests yet</p>
                        <?php else: ?>
                            <ul class="endpoint-list">
                                <?php foreach ($top_endpoints as $endpoint): ?>
                                    <li class="endpoint-item">
                                        <span class="endpoint-name"><?php echo htmlspecialchars($endpoint['endpoint']); ?></span>
                                        <span class="endpoint-count">
                                            <?php echo $endpoint['count']; ?> requests
                                            <?php if ($endpoint['successful'] > 0): ?>
                                                <span class="text-success">(<?php echo round(($endpoint['successful'] / $endpoint['count']) * 100); ?>% success)</span>
                                            <?php endif; ?>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-bar-chart"></i> Response Status Distribution
                    </div>
                    <div class="card-body">
                        <?php if (empty($status_distribution)): ?>
                            <p class="text-muted text-center py-3">No requests yet</p>
                        <?php else: ?>
                            <ul class="endpoint-list">
                                <?php foreach ($status_distribution as $status): ?>
                                    <li class="endpoint-item">
                                        <span class="endpoint-name">
                                            <span class="badge badge-<?php echo $status['response_code']; ?>">
                                                <?php echo $status['response_code']; ?>
                                            </span>
                                        </span>
                                        <span class="endpoint-count"><?php echo $status['count']; ?> requests</span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent API Activity -->
        <?php if (!empty($recent_activity)): ?>
        <div class="card">
            <div class="card-header">
                <i class="bi bi-clock-history"></i> Recent API Activity
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Method</th>
                                <th>Endpoint</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_activity as $activity): ?>
                                <tr>
                                    <td class="activity-time"><?php echo date('M d, H:i', strtotime($activity['created_at'])); ?></td>
                                    <td><span class="badge bg-secondary"><?php echo $activity['method']; ?></span></td>
                                    <td><code><?php echo htmlspecialchars($activity['endpoint']); ?></code></td>
                                    <td>
                                        <span class="badge badge-<?php echo $activity['response_code']; ?>">
                                            <?php echo $activity['response_code']; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- API Documentation Card -->
        <div class="card">
            <div class="card-header">
                <i class="bi bi-info-circle"></i> API Documentation
            </div>
            <div class="card-body">
                <h6>Base URL</h6>
                <div class="api-key-display mb-3">
                    <span class="api-key-value"><?php echo APP_URL; ?>/api/</span>
                    <button class="copy-btn" onclick="copyToClipboard('<?php echo APP_URL; ?>/api/')">
                        <i class="bi bi-clipboard"></i> Copy
                    </button>
                </div>
                
                <h6>Example Request</h6>
                <pre class="bg-light p-3 rounded" style="font-size: 12px;"><code>POST /api/send_sms.php
Content-Type: application/json

{
    "api_key": "YOUR_API_KEY",
    "phone": "254712345678",
    "message": "Hello World",
    "sender_id": "EDUSCORE"
}</code></pre>
                
                <a href="api-docs.php" class="btn btn-outline-primary w-100 mt-2">
                    <i class="bi bi-book"></i> View Full Documentation
                </a>
            </div>
        </div>
    </div>

    <!-- Generate API Key Modal -->
    <div class="modal fade" id="generateKeyModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Generate New API Key</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" id="generateKeyForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="generate">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">API Key Name</label>
                            <input type="text" name="name" class="form-control" placeholder="e.g., Production API Key" required maxlength="50">
                            <small class="text-muted">Give your API key a descriptive name (max 50 characters)</small>
                        </div>
                        
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <strong>⚠️ WARNING: This will revoke ALL existing API keys!</strong>
                            <p class="mb-0 mt-2">Any applications using existing keys will immediately lose access. Only the new key will work.</p>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="bi bi-info-circle-fill me-2"></i>
                            <strong>Important Security Notice:</strong>
                            <ul class="mb-0 mt-2">
                                <li>Your API secret will only be shown once</li>
                                <li>Store it securely - treat it like a password</li>
                                <li>Never share it or commit it to code repositories</li>
                            </ul>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger" id="generateBtn">Generate & Revoke All</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Name Modal -->
    <div class="modal fade" id="editNameModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit API Key Name</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" id="editNameForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="update_name">
                    <input type="hidden" name="key_id" id="edit_key_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">API Key Name</label>
                            <input type="text" name="name" id="edit_key_name" class="form-control" required maxlength="50">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mobile menu toggle
        document.getElementById('menuToggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });

        // Toast notification function
        function showToast(message, type = 'success') {
            const toastContainer = document.getElementById('toastContainer');
            const toastId = 'toast-' + Date.now();
            
            const icon = type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill';
            
            const toastHtml = `
                <div id="${toastId}" class="toast ${type}" role="alert">
                    <div class="toast-header">
                        <i class="bi ${icon} me-2"></i>
                        <strong class="me-auto">${type === 'success' ? 'Success' : 'Error'}</strong>
                        <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
                    </div>
                    <div class="toast-body">
                        ${message}
                    </div>
                </div>
            `;
            
            toastContainer.insertAdjacentHTML('beforeend', toastHtml);
            
            const toastElement = document.getElementById(toastId);
            const toast = new bootstrap.Toast(toastElement, { delay: 5000 });
            toast.show();
            
            toastElement.addEventListener('hidden.bs.toast', function() {
                this.remove();
            });
        }

        // Copy to clipboard with feedback
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                showToast('Copied to clipboard!', 'success');
            }).catch(function() {
                const textarea = document.createElement('textarea');
                textarea.value = text;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                showToast('Copied to clipboard!', 'success');
            });
        }

        // Reveal API secret (one-time view)
        function revealSecret(keyId, secret) {
            const secretSpan = document.getElementById('secret-' + keyId);
            const secretBtn = document.getElementById('secret-btn-' + keyId);
            
            if (secretSpan.textContent.includes('•')) {
                secretSpan.textContent = secret;
                secretBtn.innerHTML = '<i class="bi bi-eye-slash"></i> Hide';
                showToast('API secret revealed - make sure no one is watching!', 'warning');
            } else {
                secretSpan.textContent = '••••••••••••••••••••••••••••••••';
                secretBtn.innerHTML = '<i class="bi bi-eye"></i> Show';
            }
        }

        // Edit key name
        function editKeyName(keyId, currentName) {
            document.getElementById('edit_key_id').value = keyId;
            document.getElementById('edit_key_name').value = currentName;
            new bootstrap.Modal(document.getElementById('editNameModal')).show();
        }

        // Regenerate key with confirmation
        function regenerateKey(keyId) {
            if (confirm('⚠️ WARNING: Regenerating this key will immediately invalidate the old key. Any applications using it will stop working. Continue?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>';
                form.innerHTML = `
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="regenerate">
                    <input type="hidden" name="key_id" value="${keyId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Toggle key status
        function toggleKeyStatus(keyId, newStatus) {
            const action = newStatus == 'active' ? 'activate' : 'deactivate';
            if (confirm(`Are you sure you want to ${action} this API key?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>';
                form.innerHTML = `
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="key_id" value="${keyId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Delete key with confirmation
        function deleteKey(keyId) {
            if (confirm('⚠️ WARNING: Deleting this API key is permanent and cannot be undone. Any applications using it will immediately lose access. Continue?')) {
                if (confirm('This is your last chance! Are you absolutely sure you want to delete this API key?')) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>';
                    form.innerHTML = `
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="key_id" value="${keyId}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            }
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.querySelector('.sidebar');
            const menuToggle = document.getElementById('menuToggle');
            
            if (window.innerWidth <= 768 && 
                sidebar.classList.contains('active') && 
                !sidebar.contains(event.target) && 
                !menuToggle.contains(event.target)) {
                sidebar.classList.remove('active');
            }
        });

        // Prevent double submission of generate form
        document.getElementById('generateKeyForm')?.addEventListener('submit', function() {
            const btn = document.getElementById('generateBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Revoking & Generating...';
        });
    </script>
</body>
</html>