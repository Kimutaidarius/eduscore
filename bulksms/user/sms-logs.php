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
    die("Error loading user data. Please try again.");
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Filters
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Initialize variables
$messages = [];
$total_messages = 0;
$total_pages = 1;
$stats = [
    'total' => 0,
    'sent' => 0,
    'delivered' => 0,
    'failed' => 0,
    'pending' => 0,
    'scheduled' => 0
];
$totals = ['total_parts' => 0, 'total_cost' => 0];
$monthly_stats = [];

try {
    // Build query
    $query = "SELECT * FROM sms_messages WHERE user_id = ?";
    $count_query = "SELECT COUNT(*) FROM sms_messages WHERE user_id = ?";
    $params = [$user_id];

    if (!empty($status_filter)) {
        $query .= " AND status = ?";
        $count_query .= " AND status = ?";
        $params[] = $status_filter;
    }

    if (!empty($date_from)) {
        $query .= " AND DATE(created_at) >= ?";
        $count_query .= " AND DATE(created_at) >= ?";
        $params[] = $date_from;
    }

    if (!empty($date_to)) {
        $query .= " AND DATE(created_at) <= ?";
        $count_query .= " AND DATE(created_at) <= ?";
        $params[] = $date_to;
    }

    if (!empty($search)) {
        $query .= " AND (recipient LIKE ? OR message LIKE ? OR message_id LIKE ?)";
        $count_query .= " AND (recipient LIKE ? OR message LIKE ? OR message_id LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }

    $query .= " ORDER BY created_at DESC LIMIT $limit OFFSET $offset";

    // Get messages
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $messages = $stmt->fetchAll();

    // Get total count for pagination
    $count_params = $params;
    if (strpos($query, 'LIMIT') !== false) {
        $count_params = array_slice($params, 0, count($params) - 3);
    }
    $stmt = $pdo->prepare($count_query);
    $stmt->execute($count_params);
    $total_messages = $stmt->fetchColumn();
    $total_pages = ceil($total_messages / $limit);

    // Get REAL statistics from database (counts based on actual data)
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
            SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled
        FROM sms_messages 
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch();
    
    // Ensure all values are set
    $stats = array_merge(['total' => 0, 'sent' => 0, 'delivered' => 0, 'failed' => 0, 'pending' => 0, 'scheduled' => 0], (array)$stats);

    // Get total SMS parts and cost (using real cost values)
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(sms_count), 0) as total_parts, 
            COALESCE(SUM(cost), 0) as total_cost 
        FROM sms_messages 
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $totals = $stmt->fetch();

    // Get monthly statistics (last 6 months)
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as message_count,
            COALESCE(SUM(sms_count), 0) as total_sms,
            COALESCE(SUM(cost), 0) as total_cost
        FROM sms_messages 
        WHERE user_id = ? 
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month DESC
        LIMIT 6
    ");
    $stmt->execute([$user_id]);
    $monthly_stats = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("SMS Logs database error: " . $e->getMessage());
    // Continue with empty data rather than crashing
}

// Calculate success rate and average cost
$success_rate = $stats['total'] > 0 ? round(($stats['delivered'] / $stats['total']) * 100, 1) : 0;
$avg_cost = $stats['total'] > 0 ? round(($totals['total_cost'] ?? 0) / $stats['total'], 2) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMS Logs - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        /* Your existing styles - keeping them as is */
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
            height: 100%;
            transition: transform 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
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

        .filter-card {
            background-color: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .filter-card .form-label {
            font-weight: 500;
            color: #333333;
            font-size: 13px;
            margin-bottom: 5px;
        }

        .filter-card .form-control, .filter-card .form-select {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 14px;
        }

        .filter-card .form-control:focus, .filter-card .form-select:focus {
            border-color: #1e3a8a;
            box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.1);
        }

        .btn-primary {
            background-color: #1e3a8a;
            border: 1px solid #152b63;
            color: #ffffff;
            padding: 8px 16px;
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

        .btn-outline-secondary {
            border: 1px solid #e0e0e0;
            color: #666666;
            background: transparent;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 500;
        }

        .btn-outline-secondary:hover {
            background-color: #f8f9fa;
            border-color: #1e3a8a;
            color: #1e3a8a;
        }

        .table-container {
            background-color: #ffffff;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            overflow: hidden;
        }

        .table {
            margin-bottom: 0;
        }

        .table thead th {
            background-color: #f8f9fa;
            color: #333333;
            font-weight: 600;
            font-size: 13px;
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
        }

        .table tbody td {
            padding: 15px;
            color: #333333;
            font-size: 13px;
            border-bottom: 1px solid #e0e0e0;
            vertical-align: middle;
        }

        .table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .table tbody tr:last-child td {
            border-bottom: none;
        }

        .badge {
            padding: 6px 10px;
            font-weight: 500;
            font-size: 11px;
            border-radius: 20px;
        }

        .badge-success {
            background-color: #d1fae5;
            color: #065f46;
        }

        .badge-danger {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .badge-warning {
            background-color: #fed7aa;
            color: #92400e;
        }

        .badge-info {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .badge-secondary {
            background-color: #f3f4f6;
            color: #374151;
        }

        .message-preview {
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .action-btn {
            padding: 5px 10px;
            border: 1px solid #e0e0e0;
            background: white;
            border-radius: 6px;
            color: #666666;
            transition: all 0.2s ease;
            margin: 0 2px;
        }

        .action-btn:hover {
            border-color: #1e3a8a;
            color: #1e3a8a;
            background-color: #f8f9fa;
        }

        .pagination {
            margin-top: 20px;
            justify-content: center;
        }

        .page-link {
            color: #1e3a8a;
            border: 1px solid #e0e0e0;
            padding: 8px 14px;
            margin: 0 3px;
            border-radius: 8px !important;
        }

        .page-link:hover {
            background-color: #f8f9fa;
            border-color: #1e3a8a;
            color: #1e3a8a;
        }

        .page-item.active .page-link {
            background-color: #1e3a8a;
            border-color: #1e3a8a;
            color: white;
        }

        .page-item.disabled .page-link {
            color: #999999;
            border-color: #e0e0e0;
            background-color: #f8f9fa;
        }

        .export-dropdown .dropdown-menu {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 5px;
        }

        .export-dropdown .dropdown-item {
            padding: 8px 15px;
            border-radius: 6px;
            font-size: 13px;
        }

        .export-dropdown .dropdown-item:hover {
            background-color: #f8f9fa;
            color: #1e3a8a;
        }

        .export-dropdown .dropdown-item i {
            margin-right: 8px;
            color: #666666;
        }

        .monthly-stats {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
        }

        .monthly-stats h6 {
            color: #1e3a8a;
            margin-bottom: 15px;
        }

        .stat-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #e0e0e0;
        }

        .stat-item:last-child {
            border-bottom: none;
        }

        .stat-month {
            font-weight: 600;
            color: #333333;
        }

        .stat-numbers {
            color: #666666;
            font-size: 13px;
        }

        .stat-numbers strong {
            color: #1e3a8a;
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

        .toast.warning {
            border-left-color: #f59e0b;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }

            .stat-card .value {
                font-size: 22px;
            }

            .table thead {
                display: none;
            }

            .table tbody td {
                display: block;
                text-align: right;
                padding: 10px 15px;
                border-bottom: 1px solid #e0e0e0;
            }

            .table tbody td:before {
                content: attr(data-label);
                float: left;
                font-weight: 600;
                color: #666666;
            }

            .table tbody td:last-child {
                border-bottom: 2px solid #e0e0e0;
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
        echo '<div style="color:red">Sidebar not found at: ' . $sidebar_path . '</div>';
    }
    
    if (file_exists($topbar_path)) {
        include $topbar_path;
    } else {
        echo '<div style="color:red">Topbar not found at: ' . $topbar_path . '</div>';
    }
    ?>

    <!-- Toast Container for Notifications -->
    <div class="toast-container" id="toastContainer"></div>

    <div class="main-content">
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2><i class="bi bi-journal-text me-2"></i>SMS Logs</h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">SMS Logs</li>
                        </ol>
                    </nav>
                </div>
                <div class="export-dropdown dropdown">
                    <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-download"></i> Export
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="#" onclick="exportLogs('csv')"><i class="bi bi-file-earmark-spreadsheet"></i> Export as CSV</a></li>
                        <li><a class="dropdown-item" href="#" onclick="exportLogs('excel')"><i class="bi bi-file-earmark-excel"></i> Export as Excel</a></li>
                        <li><a class="dropdown-item" href="#" onclick="exportLogs('pdf')"><i class="bi bi-file-earmark-pdf"></i> Export as PDF</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Statistics Cards - Updated with real data -->
        <div class="row">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="label">Total SMS Sent</div>
                    <div class="value"><?php echo number_format($totals['total_parts'] ?? 0); ?></div>
                    <div class="small"><?php echo number_format($stats['total']); ?> messages</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #059669 0%, #047857 100%);">
                    <div class="label">Total Spent</div>
                    <div class="value">KES <?php echo number_format($totals['total_cost'] ?? 0, 2); ?></div>
                    <div class="small"><?php echo number_format($stats['delivered']); ?> delivered</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #d97706 0%, #b45309 100%);">
                    <div class="label">Success Rate</div>
                    <div class="value"><?php echo $success_rate; ?>%</div>
                    <div class="small"><?php echo number_format($stats['delivered']); ?> delivered</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);">
                    <div class="label">Average Cost</div>
                    <div class="value">KES <?php echo number_format($avg_cost, 2); ?></div>
                    <div class="small">per message</div>
                </div>
            </div>
        </div>

        <!-- Status Breakdown Row - Updated with real counts -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-title text-muted">Delivered</h6>
                        <h3 class="text-success"><?php echo number_format($stats['delivered']); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-title text-muted">Sent</h6>
                        <h3 class="text-info"><?php echo number_format($stats['sent']); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-title text-muted">Pending</h6>
                        <h3 class="text-warning"><?php echo number_format($stats['pending']); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-title text-muted">Failed</h6>
                        <h3 class="text-danger"><?php echo number_format($stats['failed']); ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-card">
            <form method="GET" action="" id="filterForm">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="sent" <?php echo $status_filter == 'sent' ? 'selected' : ''; ?>>Sent</option>
                            <option value="delivered" <?php echo $status_filter == 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                            <option value="failed" <?php echo $status_filter == 'failed' ? 'selected' : ''; ?>>Failed</option>
                            <option value="scheduled" <?php echo $status_filter == 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">From Date</label>
                        <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">To Date</label>
                        <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Phone, Message, ID..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100 me-2">
                            <i class="bi bi-search"></i> Filter
                        </button>
                        <a href="sms-logs.php" class="btn btn-outline-secondary w-100">
                            <i class="bi bi-arrow-counterclockwise"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Logs Table -->
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Message ID</th>
                        <th>Date & Time</th>
                        <th>Recipient</th>
                        <th>Message</th>
                        <th>Sender ID</th>
                        <th>Parts</th>
                        <th>Cost (KES)</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($messages)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5">
                                <i class="bi bi-inbox" style="font-size: 48px; color: #e0e0e0;"></i>
                                <p class="mt-3 text-muted">No SMS logs found</p>
                                <a href="send-sms.php" class="btn btn-primary btn-sm">Send your first SMS</a>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($messages as $msg): ?>
                            <tr>
                                <td data-label="Message ID"><code class="text-primary"><?php echo htmlspecialchars($msg['message_id']); ?></code></td>
                                <td data-label="Date & Time"><?php echo date('M d, Y H:i', strtotime($msg['created_at'])); ?></td>
                                <td data-label="Recipient"><?php echo htmlspecialchars($msg['recipient']); ?></td>
                                <td data-label="Message">
                                    <div class="message-preview" title="<?php echo htmlspecialchars($msg['message']); ?>">
                                        <?php echo htmlspecialchars(substr($msg['message'], 0, 50)); ?>
                                        <?php if (strlen($msg['message']) > 50): ?>...<?php endif; ?>
                                    </div>
                                </td>
                                <td data-label="Sender ID"><?php echo htmlspecialchars($msg['sender_id']); ?></td>
                                <td data-label="Parts"><?php echo $msg['sms_count']; ?></td>
                                <td data-label="Cost (KES)"><strong>KES <?php echo number_format($msg['cost'], 2); ?></strong></td>
                                <td data-label="Status">
                                    <?php
                                    $status_class = '';
                                    switch($msg['status']) {
                                        case 'delivered':
                                            $status_class = 'badge-success';
                                            break;
                                        case 'sent':
                                            $status_class = 'badge-info';
                                            break;
                                        case 'pending':
                                            $status_class = 'badge-warning';
                                            break;
                                        case 'failed':
                                            $status_class = 'badge-danger';
                                            break;
                                        case 'scheduled':
                                            $status_class = 'badge-secondary';
                                            break;
                                        default:
                                            $status_class = 'badge-secondary';
                                    }
                                    ?>
                                    <span class="badge <?php echo $status_class; ?>"><?php echo ucfirst($msg['status']); ?></span>
                                </td>
                                <td data-label="Actions">
                                    <button class="action-btn view-message" 
                                            data-id="<?php echo $msg['id']; ?>"
                                            data-bs-toggle="modal" 
                                            data-bs-target="#messageModal"
                                            title="View Details">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <button class="action-btn resend-message" 
                                            data-id="<?php echo $msg['id']; ?>"
                                            data-phone="<?php echo htmlspecialchars($msg['recipient']); ?>"
                                            data-message="<?php echo htmlspecialchars($msg['message']); ?>"
                                            title="Resend">
                                        <i class="bi bi-arrow-counterclockwise"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <nav>
                <ul class="pagination">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($status_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&search=<?php echo urlencode($search); ?>">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    </li>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if ($i >= $page - 2 && $i <= $page + 2): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&search=<?php echo urlencode($search); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($status_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&search=<?php echo urlencode($search); ?>">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>

        <!-- Monthly Statistics -->
        <?php if (!empty($monthly_stats)): ?>
        <div class="monthly-stats">
            <h6><i class="bi bi-calendar-check"></i> Monthly Usage (Last 6 Months)</h6>
            <?php foreach ($monthly_stats as $month): ?>
                <div class="stat-item">
                    <span class="stat-month"><?php echo date('F Y', strtotime($month['month'] . '-01')); ?></span>
                    <span class="stat-numbers">
                        <strong><?php echo number_format($month['total_sms']); ?></strong> SMS | 
                        <strong>KES <?php echo number_format($month['total_cost'], 2); ?></strong>
                        <span class="text-muted">(<?php echo number_format($month['message_count']); ?> messages)</span>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Message Details Modal -->
    <div class="modal fade" id="messageModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Message Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="messageDetails">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
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
            
            const icon = type === 'success' ? 'bi-check-circle-fill' : 
                        type === 'error' ? 'bi-exclamation-triangle-fill' : 'bi-info-circle-fill';
            
            const toastHtml = `
                <div id="${toastId}" class="toast ${type}" role="alert">
                    <div class="toast-header">
                        <i class="bi ${icon} me-2"></i>
                        <strong class="me-auto">${type.charAt(0).toUpperCase() + type.slice(1)}</strong>
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

        // View message details
        document.querySelectorAll('.view-message').forEach(button => {
            button.addEventListener('click', function() {
                const id = this.dataset.id;
                const messageDetails = document.getElementById('messageDetails');
                
                // Show loading
                messageDetails.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
                
                fetch(`get-message.php?id=${id}`)
                    .then(response => response.json())
                    .then(data => {
                        let html = `
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="fw-bold">Message ID:</label>
                                    <p class="text-primary">${data.message_id}</p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="fw-bold">Date:</label>
                                    <p>${data.created_at}</p>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="fw-bold">Recipient:</label>
                                    <p>${data.recipient}</p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="fw-bold">Sender ID:</label>
                                    <p>${data.sender_id}</p>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="fw-bold">Message:</label>
                                <div class="bg-light p-3 rounded" style="white-space: pre-wrap;">${data.message}</div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="fw-bold">SMS Parts:</label>
                                    <p>${data.sms_count}</p>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="fw-bold">Cost:</label>
                                    <p><strong>KES ${parseFloat(data.cost).toFixed(2)}</strong></p>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="fw-bold">Status:</label>
                                    <p>${data.status}</p>
                                </div>
                            </div>
                        `;
                        
                        if (data.sent_at) {
                            html += `<div class="mb-3"><label class="fw-bold">Sent at:</label><p>${data.sent_at}</p></div>`;
                        }
                        
                        if (data.delivered_at) {
                            html += `<div class="mb-3"><label class="fw-bold">Delivered at:</label><p>${data.delivered_at}</p></div>`;
                        }
                        
                        if (data.error_message) {
                            html += `<div class="mb-3"><label class="fw-bold text-danger">Error:</label><p class="text-danger">${data.error_message}</p></div>`;
                        }
                        
                        messageDetails.innerHTML = html;
                    })
                    .catch(error => {
                        messageDetails.innerHTML = '<div class="alert alert-danger">Error loading message details</div>';
                        showToast('Error loading message details', 'error');
                    });
            });
        });

        // Resend message
        document.querySelectorAll('.resend-message').forEach(button => {
            button.addEventListener('click', function() {
                const phone = this.dataset.phone;
                const message = this.dataset.message;
                
                if (confirm('Do you want to resend this message?')) {
                    window.location.href = `send-sms.php?phone=${encodeURIComponent(phone)}&message=${encodeURIComponent(message)}`;
                }
            });
        });

        // Export logs
        function exportLogs(format) {
            const status = '<?php echo $status_filter; ?>';
            const date_from = '<?php echo $date_from; ?>';
            const date_to = '<?php echo $date_to; ?>';
            const search = '<?php echo $search; ?>';
            
            showToast(`Exporting as ${format.toUpperCase()}...`, 'info');
            window.location.href = `export-logs.php?format=${format}&status=${status}&date_from=${date_from}&date_to=${date_to}&search=${search}`;
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

        // Auto-submit filter on select change
        document.querySelector('select[name="status"]')?.addEventListener('change', function() {
            document.getElementById('filterForm').submit();
        });
    </script>
</body>
</html>