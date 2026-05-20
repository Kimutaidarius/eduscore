<?php
require_once '../config/config.php';
requireLogin();

$user_id = $_SESSION['user_id'];

// Force refresh user data from database
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Get REAL SMS balance from database
$actual_sms_balance = (int)$user['sms_balance'];

// Get transaction statistics - only count COMPLETED transactions
$stats_stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as completed_count, 
        COALESCE(SUM(sms_units), 0) as total_sms_purchased,
        COALESCE(SUM(amount), 0) as total_amount_spent
    FROM mpesa_transactions 
    WHERE user_id = ? AND status = 'completed'
");
$stats_stmt->execute([$user_id]);
$stats = $stats_stmt->fetch();

// Get pending transactions count
$pending_count_stmt = $pdo->prepare("
    SELECT COUNT(*) as pending_count 
    FROM mpesa_transactions 
    WHERE user_id = ? AND status = 'pending'
");
$pending_count_stmt->execute([$user_id]);
$pending_count = $pending_count_stmt->fetchColumn();

// Pricing configuration
$customer_price_per_sms = 1.00; // Customer pays 1 KES per SMS

// Get recent completed transactions
$recent_stmt = $pdo->prepare("
    SELECT * FROM mpesa_transactions 
    WHERE user_id = ? AND status = 'completed'
    ORDER BY completed_at DESC 
    LIMIT 5
");
$recent_stmt->execute([$user_id]);
$recent_transactions = $recent_stmt->fetchAll();

// Get pending M-Pesa transactions
$pending_stmt = $pdo->prepare("
    SELECT * FROM mpesa_transactions 
    WHERE user_id = ? AND status = 'pending'
    ORDER BY created_at DESC
");
$pending_stmt->execute([$user_id]);
$pending_transactions = $pending_stmt->fetchAll();

// If there are pending transactions, show a warning
$has_pending = count($pending_transactions) > 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Top Up SMS Credits - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
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

        .card {
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            background-color: #ffffff;
        }

        .card-header {
            background-color: #ffffff;
            border-bottom: 1px solid #e0e0e0;
            padding: 15px 20px;
            font-weight: 600;
            color: #1e3a8a;
            border-radius: 12px 12px 0 0 !important;
        }

        .card-header i {
            margin-right: 8px;
            color: #1e3a8a;
        }

        .card-body {
            padding: 20px;
        }

        .form-label {
            font-weight: 500;
            color: #333333;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-control, .form-select {
            height: 45px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 0 16px;
            font-size: 14px;
            color: #333333;
            transition: all 0.2s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: #1e3a8a;
            outline: 0;
            box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.1);
        }

        .btn-primary {
            background-color: #1e3a8a;
            border: 1px solid #152b63;
            color: #ffffff;
            height: 45px;
            font-weight: 500;
            padding: 0 25px;
            border-radius: 8px;
            transition: all 0.2s ease;
        }

        .btn-primary:hover {
            background-color: #152b63;
            border-color: #0f1f4a;
        }

        .btn-success {
            background-color: #10b981;
            border: 1px solid #0ea271;
            color: #ffffff;
            height: 45px;
            font-weight: 500;
            padding: 0 25px;
            border-radius: 8px;
            transition: all 0.2s ease;
        }

        .btn-success:hover {
            background-color: #0ea271;
            border-color: #0c8a5c;
        }

        .btn-outline-primary {
            border: 1px solid #1e3a8a;
            color: #1e3a8a;
            background: transparent;
            height: 40px;
            font-weight: 500;
            padding: 0 20px;
            border-radius: 8px;
            transition: all 0.2s ease;
        }

        .btn-outline-primary:hover {
            background-color: #1e3a8a;
            color: #ffffff;
        }

        .balance-card {
            background: linear-gradient(135deg, #1e3a8a 0%, #2e4a9a 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 20px;
            position: relative;
            overflow: hidden;
        }

        .balance-card::after {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200px;
            height: 200px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
        }

        .balance-card .label {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 5px;
            position: relative;
            z-index: 1;
        }

        .balance-card .value {
            font-size: 42px;
            font-weight: 700;
            margin-bottom: 5px;
            position: relative;
            z-index: 1;
        }

        .balance-card .small {
            font-size: 14px;
            opacity: 0.8;
            position: relative;
            z-index: 1;
        }

        .balance-card hr {
            border-color: rgba(255,255,255,0.2);
            margin: 15px 0;
            position: relative;
            z-index: 1;
        }

        .balance-card .refresh-btn {
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            border-radius: 30px;
            padding: 8px 15px;
            font-size: 13px;
            transition: all 0.2s ease;
            position: relative;
            z-index: 1;
        }

        .balance-card .refresh-btn:hover {
            background: rgba(255,255,255,0.3);
            border-color: rgba(255,255,255,0.5);
        }

        .price-card {
            background-color: #ffffff;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            text-align: center;
            transition: all 0.2s ease;
        }

        .price-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            border-color: #1e3a8a;
        }

        .price-card .price {
            font-size: 32px;
            font-weight: 700;
            color: #1e3a8a;
            margin: 10px 0;
        }

        .price-card .badge {
            background-color: #e6f7e6;
            color: #10b981;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
        }

        .amount-option {
            cursor: pointer;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            transition: all 0.2s ease;
            margin-bottom: 10px;
        }

        .amount-option:hover {
            border-color: #1e3a8a;
            background-color: #f0f5ff;
        }

        .amount-option.selected {
            border-color: #1e3a8a;
            background-color: #f0f5ff;
            box-shadow: 0 0 0 2px rgba(30, 58, 138, 0.1);
        }

        .amount-option .amount {
            font-size: 20px;
            font-weight: 700;
            color: #1e3a8a;
        }

        .amount-option .sms {
            font-size: 14px;
            color: #666666;
            margin-top: 5px;
        }

        .pending-warning {
            background-color: #fff3cd;
            border: 1px solid #ffeeba;
            color: #856404;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .transaction-item {
            border-bottom: 1px solid #e0e0e0;
            padding: 12px 0;
        }

        .transaction-item:last-child {
            border-bottom: none;
        }

        .transaction-status {
            font-size: 12px;
            padding: 3px 8px;
            border-radius: 4px;
            font-weight: 500;
        }

        .status-completed {
            background-color: #e6f7e6;
            color: #10b981;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-failed {
            background-color: #f8d7da;
            color: #721c24;
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
        }

        .toast.success {
            border-left-color: #10b981;
        }

        .toast.error {
            border-left-color: #dc2626;
        }

        .toast.info {
            border-left-color: #1e3a8a;
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

    <!-- Toast Container for Notifications -->
    <div class="toast-container" id="toastContainer"></div>

    <div class="main-content">
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2><i class="bi bi-plus-circle me-2"></i>Top Up SMS Credits</h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Top Up</li>
                        </ol>
                    </nav>
                </div>
                <div>
                    <span class="badge bg-primary" style="background-color: #1e3a8a; padding: 8px 15px;" id="headerBalance">
                        <i class="bi bi-coin"></i> Current Balance: <?php echo number_format($actual_sms_balance); ?> SMS
                    </span>
                </div>
            </div>
        </div>

        <?php if ($has_pending): ?>
            <div class="pending-warning">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                You have <?php echo count($pending_transactions); ?> pending transaction(s). 
                Complete the M-Pesa payment on your phone to receive your SMS credits.
            </div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i><?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Main Topup Form -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-wallet2"></i> Purchase SMS Credits
                    </div>
                    <div class="card-body">
                        <!-- Pricing Info -->
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="price-card">
                                    <i class="bi bi-tag fs-4" style="color: #1e3a8a;"></i>
                                    <div class="price">KES 1.00</div>
                                    <div>per SMS</div>
                                    <span class="badge">Best Price</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="price-card">
                                    <i class="bi bi-exclamation-circle fs-4" style="color: #1e3a8a;"></i>
                                    <div class="price">KES 10</div>
                                    <div>Minimum Topup</div>
                                    <span class="badge">M-Pesa Requirement</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="price-card">
                                    <i class="bi bi-arrow-up-circle fs-4" style="color: #1e3a8a;"></i>
                                    <div class="price">Any Amount</div>
                                    <div>Above KES 10</div>
                                    <span class="badge">No Maximum</span>
                                </div>
                            </div>
                        </div>

                        <form method="POST" id="topupForm">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            
                            <!-- Quick Amount Options -->
                            <div class="mb-4">
                                <label class="form-label">Quick Select Amount</label>
                                <div class="row g-2">
                                    <?php
                                    $amounts = [10, 20, 50, 100, 200, 500, 1000];
                                    foreach ($amounts as $amt):
                                        $sms = floor($amt);
                                    ?>
                                    <div class="col-4 col-md-3 col-lg-2">
                                        <div class="amount-option" data-amount="<?php echo $amt; ?>">
                                            <div class="amount">KES <?php echo $amt; ?></div>
                                            <div class="sms"><?php echo $sms; ?> SMS</div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Custom Amount -->
                            <div class="mb-3">
                                <label class="form-label">Or Enter Custom Amount (KES)</label>
                                <input type="number" name="amount" id="amount" class="form-control" 
                                       placeholder="Enter amount (minimum 10)" min="10" step="1" required>
                                <small class="text-muted">You'll receive <span id="estimatedSMS">0</span> SMS credits (Minimum KES 10)</small>
                            </div>

                            <!-- Payment Method -->
                            <div class="mb-3">
                                <label class="form-label">Payment Method</label>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="payment_method" 
                                                   id="mpesa" value="mpesa" checked>
                                            <label class="form-check-label" for="mpesa">
                                                <i class="bi bi-phone" style="color: #1e3a8a;"></i> M-Pesa (Paybill)
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="payment_method" 
                                                   id="card" value="card" disabled>
                                            <label class="form-check-label" for="card">
                                                <i class="bi bi-credit-card text-muted"></i> Card (Coming Soon)
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- M-Pesa Phone Number -->
                            <div class="mb-4">
                                <label class="form-label">M-Pesa Phone Number</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">254</span>
                                    <input type="tel" name="phone" id="phone" class="form-control" 
                                           placeholder="712345678" value="<?php echo $user['phone'] ?? ''; ?>" required>
                                </div>
                                <small class="text-muted">Enter the phone number registered with M-Pesa</small>
                            </div>

                            <!-- Transaction Summary -->
                            <div class="card bg-light mb-4">
                                <div class="card-body">
                                    <h6 class="mb-3">Transaction Summary</h6>
                                    <div class="row">
                                        <div class="col-sm-6">
                                            <table class="table table-sm">
                                                <tr>
                                                    <td>Amount to pay:</td>
                                                    <td class="fw-bold" id="summaryAmount">KES 0.00</td>
                                                </tr>
                                                <tr>
                                                    <td>SMS credits to receive:</td>
                                                    <td class="fw-bold" id="summarySMS">0</td>
                                                </tr>
                                                <tr>
                                                    <td>Price per SMS:</td>
                                                    <td>KES 1.00</td>
                                                </tr>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-success w-100" id="submitBtn">
                                <i class="bi bi-phone"></i> Pay with M-Pesa
                            </button>
                        </form>

                        <div class="mt-3 text-center">
                            <small class="text-muted">
                                <i class="bi bi-shield-check"></i> 
                                Secured by Lipana & M-Pesa. You'll receive an STK push on your phone.
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Recent Completed Transactions -->
                <?php if (!empty($recent_transactions)): ?>
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-clock-history"></i> Recent Completed Top Ups
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php foreach ($recent_transactions as $transaction): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>KES <?php echo number_format($transaction['amount'], 2); ?></strong>
                                        <br>
                                        <small class="text-muted">
                                            <i class="bi bi-calendar"></i> 
                                            <?php echo date('M d, Y H:i', strtotime($transaction['completed_at'] ?? $transaction['created_at'])); ?>
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-success"><?php echo $transaction['sms_units']; ?> SMS</span>
                                        <?php if (!empty($transaction['mpesa_receipt'])): ?>
                                            <br>
                                            <small class="text-muted"><?php echo $transaction['mpesa_receipt']; ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Current Balance Card -->
                <div class="balance-card">
                    <div class="label">Current SMS Balance</div>
                    <div class="value" id="currentBalance"><?php echo number_format($actual_sms_balance); ?></div>
                    <div class="small">SMS credits available (from OpenSMS)</div>
                    
                    <hr>
                    
                    <div class="row">
                        <div class="col-6">
                            <div class="label">Total Purchased</div>
                            <div class="h4"><?php echo number_format($stats['total_sms_purchased'] ?? 0); ?></div>
                            <div class="small">SMS credits</div>
                        </div>
                        <div class="col-6">
                            <div class="label">Completed</div>
                            <div class="h4"><?php echo number_format($stats['completed_count'] ?? 0); ?></div>
                            <div class="small">transactions</div>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="label">Can send up to</div>
                    <div class="h4"><?php echo number_format($actual_sms_balance); ?> messages</div>
                    <div class="small">@ KES 1.00 per SMS</div>
                    
                    <button class="refresh-btn w-100 mt-3" onclick="refreshBalance()">
                        <i class="bi bi-arrow-repeat"></i> Refresh Balance
                    </button>
                </div>

                <!-- Why Choose Us Card -->
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-star"></i> Why Choose Us
                    </div>
                    <div class="card-body">
                        <p><i class="bi bi-check-circle-fill text-success"></i> Instant SMS credit after payment</p>
                        <p><i class="bi bi-check-circle-fill text-success"></i> Best rate: KES 1 per SMS</p>
                        <p><i class="bi bi-check-circle-fill text-success"></i> M-Pesa Paybill integration</p>
                        <p><i class="bi bi-check-circle-fill text-success"></i> 24/7 automated delivery</p>
                        <p><i class="bi bi-check-circle-fill text-success"></i> Secure payments via Lipana</p>
                    </div>
                </div>

                <!-- Pending Transactions -->
                <?php if (!empty($pending_transactions)): ?>
                <div class="card mt-3">
                    <div class="card-header">
                        <i class="bi bi-hourglass-split"></i> Pending Payments
                        <span class="badge bg-warning ms-2"><?php echo count($pending_transactions); ?></span>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php foreach ($pending_transactions as $pending): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <strong>KES <?php echo number_format($pending['amount'], 2); ?></strong>
                                        <br>
                                        <small class="text-muted">
                                            <i class="bi bi-clock"></i> 
                                            <?php echo date('H:i', strtotime($pending['created_at'])); ?>
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-warning">Pending</span>
                                        <br>
                                        <small class="text-muted"><?php echo $pending['sms_units']; ?> SMS</small>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Help Card -->
                <div class="card mt-3">
                    <div class="card-header">
                        <i class="bi bi-question-circle"></i> Need Help?
                    </div>
                    <div class="card-body">
                        <p><i class="bi bi-check-circle text-success"></i> Minimum topup: KES 10</p>
                        <p><i class="bi bi-check-circle text-success"></i> Instant SMS credit after payment</p>
                        <p><i class="bi bi-check-circle text-success"></i> Best rates in Kenya: KES 1/SMS</p>
                        <p><i class="bi bi-telephone"></i> Contact: support@eduscore.app</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Configuration
        const customerPricePerSms = <?php echo $customer_price_per_sms; ?>;
        let currentBalance = <?php echo $actual_sms_balance; ?>;

        // DOM Elements
        const amountInput = document.getElementById('amount');
        const estimatedSMS = document.getElementById('estimatedSMS');
        const summaryAmount = document.getElementById('summaryAmount');
        const summarySMS = document.getElementById('summarySMS');
        const amountOptions = document.querySelectorAll('.amount-option');
        const currentBalanceEl = document.getElementById('currentBalance');
        const headerBalanceEl = document.getElementById('headerBalance');

        // Show toast function
        function showToast(message, type = 'success') {
            const toastContainer = document.getElementById('toastContainer');
            const toastId = 'toast-' + Date.now();
            
            const icons = {
                success: 'bi-check-circle-fill text-success',
                error: 'bi-exclamation-triangle-fill text-danger',
                info: 'bi-info-circle-fill text-primary'
            };
            
            const toastHtml = `
                <div id="${toastId}" class="toast ${type}" role="alert" style="min-width: 350px;">
                    <div class="toast-header">
                        <i class="bi ${icons[type]} me-2"></i>
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

        // Refresh balance via AJAX - fetches from database
        function refreshBalance() {
            showToast('Fetching latest balance from OpenSMS...', 'info');
            
            fetch('../ajax/get_balance.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        currentBalance = data.raw_balance;
                        
                        // Update balance displays
                        if (currentBalanceEl) {
                            currentBalanceEl.textContent = data.balance;
                        }
                        
                        if (headerBalanceEl) {
                            headerBalanceEl.innerHTML = `<i class="bi bi-coin"></i> Current Balance: ${data.balance} SMS`;
                        }
                        
                        // Update the "Can send up to" text
                        const h4Elements = document.querySelectorAll('.balance-card .h4');
                        if (h4Elements.length > 1) {
                            h4Elements[1].textContent = data.balance + ' messages';
                        }
                        
                        showToast('Balance updated from database!', 'success');
                    } else {
                        showToast('Failed to refresh balance', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Failed to refresh balance', 'error');
                });
        }

        // Update summary based on amount
        function updateSummary(amount) {
            amount = Math.max(10, Math.floor(amount));
            const smsUnits = Math.floor(amount); // 1 KES = 1 SMS
            
            estimatedSMS.textContent = smsUnits;
            summaryAmount.textContent = 'KES ' + amount.toFixed(2);
            summarySMS.textContent = smsUnits;
            
            return smsUnits;
        }

        // Amount input handler
        amountInput.addEventListener('input', function() {
            let amount = parseFloat(this.value) || 0;
            if (amount < 10) amount = 10;
            updateSummary(amount);
            
            // Remove selected class from all options
            amountOptions.forEach(opt => opt.classList.remove('selected'));
        });

        // Quick amount options click handler
        amountOptions.forEach(option => {
            option.addEventListener('click', function() {
                const amount = parseFloat(this.dataset.amount);
                amountInput.value = amount;
                updateSummary(amount);
                
                // Remove selected class from all options and add to this
                amountOptions.forEach(opt => opt.classList.remove('selected'));
                this.classList.add('selected');
            });
        });

        // Form submission with AJAX
        document.getElementById('topupForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const amount = parseFloat(amountInput.value);
            const phone = document.getElementById('phone').value;
            const csrf_token = document.querySelector('input[name="csrf_token"]').value;
            
            // Validate
            if (amount < 10) {
                showToast('Minimum topup amount is KES 10 (M-Pesa requirement)', 'error');
                return;
            }
            
            if (!phone || phone.length < 9) {
                showToast('Please enter a valid M-Pesa phone number', 'error');
                return;
            }
            
            // Show loading state
            const submitBtn = document.getElementById('submitBtn');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Processing...';
            
            // Prepare form data
            const formData = new FormData();
            formData.append('phone', phone);
            formData.append('amount', amount);
            formData.append('csrf_token', csrf_token);
            formData.append('payment_method', 'mpesa');
            
            // Send AJAX request
            fetch('../ajax/lipana_payment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    
                    // Store transaction reference in session storage for tracking
                    sessionStorage.setItem('last_transaction', JSON.stringify({
                        reference: data.reference,
                        amount: data.amount,
                        sms_units: data.sms_units,
                        timestamp: Date.now()
                    }));
                    
                    // Show success modal or message
                    showPaymentSuccess(data);
                    
                    // Reset form
                    amountInput.value = '';
                    document.getElementById('estimatedSMS').textContent = '0';
                    updateSummary(10);
                    
                    // Remove selected class from all options
                    amountOptions.forEach(opt => opt.classList.remove('selected'));
                    
                    // Reload the page after 3 seconds to show updated pending transactions
                    setTimeout(() => {
                        location.reload();
                    }, 3000);
                    
                } else {
                    showToast(data.message, 'error');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('An error occurred. Please try again.', 'error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
        });

        // Function to show payment success message
        function showPaymentSuccess(data) {
            const toastContainer = document.getElementById('toastContainer');
            const toastId = 'toast-success-' + Date.now();
            
            const toastHtml = `
                <div id="${toastId}" class="toast success" role="alert" style="min-width: 350px;">
                    <div class="toast-header">
                        <i class="bi bi-check-circle-fill text-success me-2"></i>
                        <strong class="me-auto">Payment Initiated</strong>
                        <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
                    </div>
                    <div class="toast-body">
                        <p class="mb-2">${data.message}</p>
                        <div class="bg-light p-2 rounded">
                            <small>Reference: <strong>${data.reference}</strong></small><br>
                            <small>Amount: <strong>KES ${data.amount}</strong></small><br>
                            <small>SMS Credits: <strong>${data.sms_units}</strong></small>
                        </div>
                        <p class="mt-2 mb-0 text-muted">
                            <i class="bi bi-info-circle"></i> 
                            Check your phone and enter PIN to complete payment
                        </p>
                    </div>
                </div>
            `;
            
            toastContainer.insertAdjacentHTML('beforeend', toastHtml);
            
            const toastElement = document.getElementById(toastId);
            const toast = new bootstrap.Toast(toastElement, { delay: 10000 });
            toast.show();
            
            // Refresh balance multiple times to check for completion
            setTimeout(refreshBalance, 5000);
            setTimeout(refreshBalance, 10000);
            setTimeout(refreshBalance, 20000);
            setTimeout(refreshBalance, 30000);
        }

        // Check for pending transaction on page load
        document.addEventListener('DOMContentLoaded', function() {
            const lastTransaction = sessionStorage.getItem('last_transaction');
            if (lastTransaction) {
                const trans = JSON.parse(lastTransaction);
                const fiveMinutesAgo = Date.now() - (5 * 60 * 1000);
                
                if (trans.timestamp > fiveMinutesAgo) {
                    // Show pending transaction notification
                    showToast(`You have a pending transaction of KES ${trans.amount}. Complete payment on your phone.`, 'info');
                    
                    // Check balance multiple times
                    setTimeout(refreshBalance, 5000);
                    setTimeout(refreshBalance, 10000);
                    setTimeout(refreshBalance, 20000);
                } else {
                    // Clear old transaction
                    sessionStorage.removeItem('last_transaction');
                }
            }
            
            // Refresh balance on page load to ensure latest data
            setTimeout(refreshBalance, 1000);
        });

        // Initialize with default amount
        updateSummary(10);
    </script>
</body>
</html>