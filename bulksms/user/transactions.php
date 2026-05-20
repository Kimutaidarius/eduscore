<?php
require_once '../config/config.php';
requireLogin();

// Enable error logging but don't display to users
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$user_id = $_SESSION['user_id'];

try {
    // Get user data
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        throw new Exception("User not found");
    }

    // Pagination setup
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 20;
    $offset = ($page - 1) * $limit;

    // Filter setup
    $filter_type = isset($_GET['type']) ? $_GET['type'] : 'all';
    $filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
    $date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
    $date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

    // Build query conditions
    $conditions = ["user_id = ?"];
    $params = [$user_id];

    // Get transactions from mpesa_transactions
    $mpesa_sql = "SELECT 
            'payment' as transaction_type,
            id as original_id,
            COALESCE(reference, CONCAT('TXN-', id)) as reference,
            amount,
            COALESCE(sms_units, 0) as sms_units,
            your_cost,
            your_profit,
            COALESCE(payment_method, 'mpesa') as payment_method,
            mpesa_receipt,
            status,
            created_at,
            completed_at,
            NULL as recipient,
            NULL as message,
            NULL as sms_count,
            NULL as cost_kes
        FROM mpesa_transactions 
        WHERE user_id = ?";
    
    $mpesa_params = [$user_id];
    
    if ($filter_status !== 'all' && in_array($filter_status, ['completed', 'pending', 'failed'])) {
        $mpesa_sql .= " AND status = ?";
        $mpesa_params[] = $filter_status;
    }
    
    if (!empty($date_from)) {
        $mpesa_sql .= " AND DATE(created_at) >= ?";
        $mpesa_params[] = $date_from;
    }
    
    if (!empty($date_to)) {
        $mpesa_sql .= " AND DATE(created_at) <= ?";
        $mpesa_params[] = $date_to;
    }

    // Get transactions from sms_messages
    $sms_sql = "SELECT 
            'sms' as transaction_type,
            id as original_id,
            COALESCE(message_id, CONCAT('SMS-', id)) as reference,
            COALESCE(cost_kes, 0) as amount,
            COALESCE(sms_count, 0) as sms_units,
            NULL as your_cost,
            NULL as your_profit,
            'sms' as payment_method,
            NULL as mpesa_receipt,
            status,
            created_at,
            sent_at as completed_at,
            recipient,
            message,
            sms_count,
            cost_kes
        FROM sms_messages 
        WHERE user_id = ?";
    
    $sms_params = [$user_id];
    
    if ($filter_status !== 'all' && in_array($filter_status, ['sent', 'scheduled', 'pending', 'failed'])) {
        $sms_sql .= " AND status = ?";
        $sms_params[] = $filter_status;
    }
    
    if (!empty($date_from)) {
        $sms_sql .= " AND DATE(created_at) >= ?";
        $sms_params[] = $date_from;
    }
    
    if (!empty($date_to)) {
        $sms_sql .= " AND DATE(created_at) <= ?";
        $sms_params[] = $date_to;
    }

    // Combine based on filter type
    if ($filter_type === 'payment') {
        $sql = $mpesa_sql . " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params = array_merge($mpesa_params, [$limit, $offset]);
    } elseif ($filter_type === 'sms') {
        $sql = $sms_sql . " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params = array_merge($sms_params, [$limit, $offset]);
    } else {
        // Combine both
        $sql = "($mpesa_sql) UNION ALL ($sms_sql) ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params = array_merge($mpesa_params, $sms_params, [$limit, $offset]);
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll();

    // Get total count for pagination
    if ($filter_type === 'payment') {
        $count_sql = "SELECT COUNT(*) as total FROM mpesa_transactions WHERE user_id = ?";
        $count_params = [$user_id];
        if ($filter_status !== 'all' && in_array($filter_status, ['completed', 'pending', 'failed'])) {
            $count_sql .= " AND status = ?";
            $count_params[] = $filter_status;
        }
    } elseif ($filter_type === 'sms') {
        $count_sql = "SELECT COUNT(*) as total FROM sms_messages WHERE user_id = ?";
        $count_params = [$user_id];
        if ($filter_status !== 'all' && in_array($filter_status, ['sent', 'scheduled', 'pending', 'failed'])) {
            $count_sql .= " AND status = ?";
            $count_params[] = $filter_status;
        }
    } else {
        $count_sql = "
            SELECT (SELECT COUNT(*) FROM mpesa_transactions WHERE user_id = ?) + 
                   (SELECT COUNT(*) FROM sms_messages WHERE user_id = ?) as total
        ";
        $count_params = [$user_id, $user_id];
    }

    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($count_params);
    $total_rows = $count_stmt->fetchColumn();
    $total_pages = ceil($total_rows / $limit);

    // Get summary statistics with error handling
    $summary = [
        'total_payments' => 0,
        'total_sms' => 0,
        'total_spent' => 0,
        'total_sms_purchased' => 0,
        'total_sms_sent' => 0
    ];

    try {
        $summary_sql = "
            SELECT
                (SELECT COUNT(*) FROM mpesa_transactions WHERE user_id = ? AND status = 'completed') as total_payments,
                (SELECT COUNT(*) FROM sms_messages WHERE user_id = ?) as total_sms,
                (SELECT COALESCE(SUM(amount), 0) FROM mpesa_transactions WHERE user_id = ? AND status = 'completed') as total_spent,
                (SELECT COALESCE(SUM(sms_units), 0) FROM mpesa_transactions WHERE user_id = ? AND status = 'completed') as total_sms_purchased,
                (SELECT COALESCE(SUM(sms_count), 0) FROM sms_messages WHERE user_id = ? AND status = 'sent') as total_sms_sent
        ";
        $summary_stmt = $pdo->prepare($summary_sql);
        $summary_stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id]);
        $summary = $summary_stmt->fetch();
    } catch (Exception $e) {
        error_log("Summary query error: " . $e->getMessage());
    }

    // Calculate savings
    $customer_price_per_sms = 1.00;
    $opensms_cost_per_sms = defined('OPENSMS_PRICE_PER_SMS') ? OPENSMS_PRICE_PER_SMS : 0.70;
    $total_savings = ($summary['total_sms_sent'] ?? 0) * ($customer_price_per_sms - $opensms_cost_per_sms);

} catch (Exception $e) {
    error_log("Transactions page error: " . $e->getMessage());
    $error_message = "An error occurred loading transactions. Please try again later.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transactions - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        /* ... (keep all your existing CSS styles) ... */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background-color: #f8f9fa;
            overflow-x: hidden;
        }

        .main-content {
            margin-left: 250px;
            margin-top: 70px;
            padding: 30px;
            background-color: #f8f9fa;
            min-height: calc(100vh - 70px);
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

        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border: 1px solid #e0e0e0;
            transition: all 0.2s ease;
        }

        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .stats-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
        }

        .stats-icon.payment {
            background-color: #e6f7e6;
            color: #10b981;
        }

        .stats-icon.sms {
            background-color: #e6f0ff;
            color: #1e3a8a;
        }

        .stats-icon.savings {
            background-color: #fff3cd;
            color: #856404;
        }

        .stats-value {
            font-size: 28px;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }

        .stats-label {
            color: #666;
            font-size: 14px;
        }

        .filter-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #e0e0e0;
        }

        .transaction-item {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 15px;
            transition: all 0.2s ease;
        }

        .transaction-item:hover {
            border-color: #1e3a8a;
            box-shadow: 0 2px 8px rgba(30, 58, 138, 0.1);
        }

        .transaction-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge-payment {
            background-color: #e6f7e6;
            color: #10b981;
        }

        .badge-sms {
            background-color: #e6f0ff;
            color: #1e3a8a;
        }

        .badge-completed {
            background-color: #e6f7e6;
            color: #10b981;
        }

        .badge-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .badge-failed {
            background-color: #f8d7da;
            color: #721c24;
        }

        .badge-sent {
            background-color: #e6f7e6;
            color: #10b981;
        }

        .badge-scheduled {
            background-color: #cfe2ff;
            color: #1e3a8a;
        }

        .transaction-detail {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }

        .transaction-detail i {
            margin-right: 5px;
            color: #1e3a8a;
        }

        .pagination {
            justify-content: center;
            margin-top: 30px;
        }

        .page-link {
            color: #1e3a8a;
            border: 1px solid #e0e0e0;
            margin: 0 5px;
            border-radius: 8px !important;
        }

        .page-item.active .page-link {
            background-color: #1e3a8a;
            border-color: #1e3a8a;
        }

        .export-btn {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 8px 15px;
            color: #666;
            background: white;
            transition: all 0.2s ease;
        }

        .export-btn:hover {
            border-color: #1e3a8a;
            color: #1e3a8a;
        }

        .empty-state {
            text-align: center;
            padding: 50px;
            background: white;
            border-radius: 12px;
            border: 1px solid #e0e0e0;
        }

        .empty-state i {
            font-size: 48px;
            color: #ccc;
            margin-bottom: 20px;
        }

        .error-alert {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
        }
    </style>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    <?php include '../includes/topbar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2><i class="bi bi-clock-history me-2"></i>Transaction History</h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Transactions</li>
                        </ol>
                    </nav>
                </div>
                <div>
                    <button class="export-btn me-2" onclick="exportTransactions()">
                        <i class="bi bi-download"></i> Export
                    </button>
                    <button class="export-btn" onclick="window.print()">
                        <i class="bi bi-printer"></i> Print
                    </button>
                </div>
            </div>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="error-alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon payment">
                        <i class="bi bi-cash-stack fs-4"></i>
                    </div>
                    <div class="stats-value">KES <?php echo number_format($summary['total_spent'] ?? 0, 2); ?></div>
                    <div class="stats-label">Total Spent</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon payment">
                        <i class="bi bi-credit-card fs-4"></i>
                    </div>
                    <div class="stats-value"><?php echo number_format($summary['total_payments'] ?? 0); ?></div>
                    <div class="stats-label">Payments Made</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon sms">
                        <i class="bi bi-envelope fs-4"></i>
                    </div>
                    <div class="stats-value"><?php echo number_format($summary['total_sms_sent'] ?? 0); ?></div>
                    <div class="stats-label">SMS Sent</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon savings">
                        <i class="bi bi-piggy-bank fs-4"></i>
                    </div>
                    <div class="stats-value">KES <?php echo number_format($total_savings, 2); ?></div>
                    <div class="stats-label">Total Savings</div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-card">
            <form method="GET" id="filterForm">
                <div class="row">
                    <div class="col-md-2">
                        <label class="form-label">Type</label>
                        <select name="type" class="form-select">
                            <option value="all" <?php echo $filter_type === 'all' ? 'selected' : ''; ?>>All</option>
                            <option value="payment" <?php echo $filter_type === 'payment' ? 'selected' : ''; ?>>Payments</option>
                            <option value="sms" <?php echo $filter_type === 'sms' ? 'selected' : ''; ?>>SMS</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All</option>
                            <option value="completed" <?php echo $filter_status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="failed" <?php echo $filter_status === 'failed' ? 'selected' : ''; ?>>Failed</option>
                            <option value="sent" <?php echo $filter_status === 'sent' ? 'selected' : ''; ?>>Sent</option>
                            <option value="scheduled" <?php echo $filter_status === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">From</label>
                        <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">To</label>
                        <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-funnel"></i> Filter
                        </button>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <a href="transactions.php" class="btn btn-light w-100">
                            <i class="bi bi-arrow-counterclockwise"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Transactions List -->
        <?php if (empty($transactions)): ?>
            <div class="empty-state">
                <i class="bi bi-inbox"></i>
                <h5>No Transactions Found</h5>
                <p class="text-muted">You haven't made any transactions yet.</p>
                <a href="topup.php" class="btn btn-primary mt-3">
                    <i class="bi bi-plus-circle"></i> Make Your First Top Up
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($transactions as $transaction): ?>
                <div class="transaction-item">
                    <div class="row align-items-center">
                        <div class="col-md-5">
                            <div class="d-flex align-items-center">
                                <?php if ($transaction['transaction_type'] === 'payment'): ?>
                                    <div class="me-3">
                                        <span class="transaction-badge badge-payment">
                                            <i class="bi bi-credit-card"></i> Payment
                                        </span>
                                    </div>
                                <?php else: ?>
                                    <div class="me-3">
                                        <span class="transaction-badge badge-sms">
                                            <i class="bi bi-envelope"></i> SMS
                                        </span>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <strong><?php echo htmlspecialchars($transaction['reference']); ?></strong>
                                    <div class="transaction-detail">
                                        <i class="bi bi-calendar"></i> <?php echo date('M d, Y H:i', strtotime($transaction['created_at'])); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <?php if ($transaction['transaction_type'] === 'payment'): ?>
                                <div>
                                    <strong>KES <?php echo number_format($transaction['amount'], 2); ?></strong>
                                    <div class="transaction-detail">
                                        <i class="bi bi-envelope"></i> <?php echo $transaction['sms_units']; ?> SMS credits
                                    </div>
                                </div>
                            <?php else: ?>
                                <div>
                                    <strong>To: <?php echo htmlspecialchars($transaction['recipient']); ?></strong>
                                    <div class="transaction-detail">
                                        <i class="bi bi-chat"></i> <?php echo htmlspecialchars(substr($transaction['message'], 0, 50)); ?>...
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-2">
                            <?php
                            $status_class = '';
                            if (in_array($transaction['status'], ['completed', 'sent'])) {
                                $status_class = 'badge-completed';
                            } elseif (in_array($transaction['status'], ['pending', 'scheduled'])) {
                                $status_class = 'badge-pending';
                            } elseif ($transaction['status'] === 'failed') {
                                $status_class = 'badge-failed';
                            }
                            ?>
                            <span class="transaction-badge <?php echo $status_class; ?>">
                                <?php echo ucfirst($transaction['status']); ?>
                            </span>
                            
                            <?php if ($transaction['transaction_type'] === 'payment' && !empty($transaction['mpesa_receipt'])): ?>
                                <div class="transaction-detail">
                                    <i class="bi bi-receipt"></i> <?php echo htmlspecialchars($transaction['mpesa_receipt']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-2 text-end">
                            <?php if ($transaction['transaction_type'] === 'payment'): ?>
                                <small class="text-success">
                                    <i class="bi bi-graph-up-arrow"></i> 
                                    +KES <?php echo number_format($transaction['your_profit'] ?? 0, 2); ?> profit
                                </small>
                            <?php else: ?>
                                <small class="text-muted">
                                    <i class="bi bi-calculator"></i>
                                    <?php echo $transaction['sms_count']; ?> SMS | 
                                    KES <?php echo number_format($transaction['amount'] ?? 0, 2); ?>
                                </small>
                            <?php endif; ?>
                            
                            <div class="mt-2">
                                <button class="btn btn-sm btn-outline-primary" onclick='viewDetails(<?php echo json_encode($transaction, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav>
                    <ul class="pagination">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&type=<?php echo urlencode($filter_type); ?>&status=<?php echo urlencode($filter_status); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Transaction Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Transaction Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalContent">
                    <!-- Content will be populated by JavaScript -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // View transaction details
        function viewDetails(transaction) {
            const modal = new bootstrap.Modal(document.getElementById('detailsModal'));
            const modalContent = document.getElementById('modalContent');
            
            let html = '';
            
            if (transaction.transaction_type === 'payment') {
                html = `
                    <div class="mb-3">
                        <label class="fw-bold">Reference:</label>
                        <p>${transaction.reference || 'N/A'}</p>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold">Type:</label>
                        <p><span class="transaction-badge badge-payment">Payment</span></p>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold">Amount:</label>
                        <p>KES ${Number(transaction.amount || 0).toFixed(2)}</p>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold">SMS Credits:</label>
                        <p>${transaction.sms_units || 0}</p>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold">Payment Method:</label>
                        <p>${transaction.payment_method || 'mpesa'}</p>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold">Status:</label>
                        <p><span class="transaction-badge ${transaction.status === 'completed' ? 'badge-completed' : 'badge-pending'}">${transaction.status}</span></p>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold">Date:</label>
                        <p>${new Date(transaction.created_at).toLocaleString()}</p>
                    </div>
                    ${transaction.mpesa_receipt ? `
                    <div class="mb-3">
                        <label class="fw-bold">M-Pesa Receipt:</label>
                        <p>${transaction.mpesa_receipt}</p>
                    </div>
                    ` : ''}
                    ${transaction.completed_at ? `
                    <div class="mb-3">
                        <label class="fw-bold">Completed:</label>
                        <p>${new Date(transaction.completed_at).toLocaleString()}</p>
                    </div>
                    ` : ''}
                `;
            } else {
                html = `
                    <div class="mb-3">
                        <label class="fw-bold">Message ID:</label>
                        <p>${transaction.reference || 'N/A'}</p>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold">Type:</label>
                        <p><span class="transaction-badge badge-sms">SMS</span></p>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold">Recipient:</label>
                        <p>${transaction.recipient || 'N/A'}</p>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold">Message:</label>
                        <p class="bg-light p-2 rounded">${transaction.message || 'N/A'}</p>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold">SMS Parts:</label>
                        <p>${transaction.sms_count || 0}</p>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold">Cost:</label>
                        <p>KES ${Number(transaction.amount || 0).toFixed(2)}</p>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold">Status:</label>
                        <p><span class="transaction-badge ${
                            transaction.status === 'sent' ? 'badge-completed' : 
                            transaction.status === 'scheduled' ? 'badge-pending' : 'badge-failed'
                        }">${transaction.status}</span></p>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold">Created:</label>
                        <p>${new Date(transaction.created_at).toLocaleString()}</p>
                    </div>
                    ${transaction.completed_at ? `
                    <div class="mb-3">
                        <label class="fw-bold">Sent:</label>
                        <p>${new Date(transaction.completed_at).toLocaleString()}</p>
                    </div>
                    ` : ''}
                `;
            }
            
            modalContent.innerHTML = html;
            modal.show();
        }

        // Export transactions as CSV
        function exportTransactions() {
            const type = document.querySelector('select[name="type"]').value;
            const status = document.querySelector('select[name="status"]').value;
            const date_from = document.querySelector('input[name="date_from"]').value;
            const date_to = document.querySelector('input[name="date_to"]').value;
            
            window.location.href = `export_transactions.php?type=${encodeURIComponent(type)}&status=${encodeURIComponent(status)}&date_from=${encodeURIComponent(date_from)}&date_to=${encodeURIComponent(date_to)}`;
        }

        // Auto-submit filters on change (optional)
        document.querySelectorAll('.form-select, input[type="date"]').forEach(element => {
            element.addEventListener('change', function() {
                if (this.name !== 'date_from' && this.name !== 'date_to') {
                    document.getElementById('filterForm').submit();
                }
            });
        });
    </script>
</body>
</html>