<?php
// dashboard.php
session_start();
require_once 'config/db.php';

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// Get user data
$admin_id = $_SESSION['admin_id'];
$query = "SELECT * FROM superadmins WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get SMS balance (this would come from your database)
$sms_balance = 12450; // Example data
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Remix Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.6.0/remixicon.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/dashboard.css">
    
    <link rel="icon" type="image/png" href="assets/images/logo.png">
    <title>Dashboard - EduScore Bulk SMS</title>
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar__logo">
                <img src="assets/images/logo.png" alt="EduScore" class="sidebar__logo-img">
                <span class="sidebar__logo-text">EduScore SMS</span>
            </div>
            
            <nav class="sidebar__nav">
                <a href="#" class="sidebar__link active">
                    <i class="ri-dashboard-line"></i>
                    <span>Dashboard</span>
                </a>
                <a href="#" class="sidebar__link">
                    <i class="ri-mail-send-line"></i>
                    <span>Send SMS</span>
                </a>
                <a href="#" class="sidebar__link">
                    <i class="ri-stack-line"></i>
                    <span>Bulk Campaigns</span>
                </a>
                <a href="#" class="sidebar__link">
                    <i class="ri-contacts-line"></i>
                    <span>Contacts / Groups</span>
                </a>
                <a href="#" class="sidebar__link">
                    <i class="ri-key-line"></i>
                    <span>API Keys</span>
                </a>
                <a href="#" class="sidebar__link">
                    <i class="ri-bar-chart-line"></i>
                    <span>Delivery Reports</span>
                </a>
                <a href="#" class="sidebar__link">
                    <i class="ri-bank-card-line"></i>
                    <span>Transactions</span>
                </a>
                <a href="#" class="sidebar__link">
                    <i class="ri-money-dollar-circle-line"></i>
                    <span>Buy SMS</span>
                </a>
                <a href="#" class="sidebar__link">
                    <i class="ri-link"></i>
                    <span>Webhooks</span>
                </a>
                <a href="#" class="sidebar__link">
                    <i class="ri-settings-4-line"></i>
                    <span>Settings</span>
                </a>
                <a href="#" class="sidebar__link">
                    <i class="ri-customer-service-line"></i>
                    <span>Support</span>
                </a>
            </nav>
            
            <div class="sidebar__footer">
                <div class="sidebar__school-info">
                    <div class="sidebar__school-name"><?php echo htmlspecialchars($user['fullname'] ?: $user['username']); ?></div>
                    <div class="sidebar__school-role">Super Admin</div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Topbar -->
            <header class="topbar">
                <button class="topbar__menu-toggle" id="menuToggle">
                    <i class="ri-menu-line"></i>
                </button>
                
                <div class="topbar__search">
                    <i class="ri-search-line topbar__search-icon"></i>
                    <input type="text" class="topbar__search-input" placeholder="Search messages, logs, API keys...">
                </div>
                
                <div class="topbar__right">
                    <div class="topbar__balance">
                        <span class="topbar__balance-dot"></span>
                        <span class="topbar__balance-text">Balance: <?php echo number_format($sms_balance); ?> SMS</span>
                    </div>
                    
                    <button class="topbar__notification" id="notificationBtn">
                        <i class="ri-notification-3-line"></i>
                        <span class="topbar__notification-badge">3</span>
                    </button>
                    
                    <div class="topbar__profile" id="profileDropdown">
                        <div class="topbar__profile-trigger">
                            <div class="topbar__profile-avatar">
                                <?php echo strtoupper(substr($user['fullname'] ?: $user['username'], 0, 1)); ?>
                            </div>
                            <i class="ri-arrow-down-s-line"></i>
                        </div>
                        
                        <div class="topbar__dropdown">
                            <div class="topbar__dropdown-header">
                                <div class="topbar__dropdown-name"><?php echo htmlspecialchars($user['fullname'] ?: $user['username']); ?></div>
                                <div class="topbar__dropdown-email"><?php echo htmlspecialchars($user['email']); ?></div>
                            </div>
                            <a href="#" class="topbar__dropdown-item">
                                <i class="ri-user-settings-line"></i>
                                Settings
                            </a>
                            <a href="#" class="topbar__dropdown-item">
                                <i class="ri-bill-line"></i>
                                Billing
                            </a>
                            <div class="topbar__dropdown-divider"></div>
                            <a href="logout.php" class="topbar__dropdown-item">
                                <i class="ri-logout-box-line"></i>
                                Logout
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Dashboard Content -->
            <div class="content">
                <!-- Overview Cards -->
                <div class="cards-grid">
                    <div class="stat-card">
                        <div class="stat-card__icon" style="background: #EFF6FF;">
                            <i class="ri-mail-open-line" style="color: #3B82F6;"></i>
                        </div>
                        <div class="stat-card__info">
                            <div class="stat-card__label">Total SMS Sent</div>
                            <div class="stat-card__value">145,892</div>
                            <div class="stat-card__trend positive">
                                <i class="ri-arrow-up-line"></i> +12.5%
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-card__icon" style="background: #F0FDF4;">
                            <i class="ri-stack-line" style="color: #10B981;"></i>
                        </div>
                        <div class="stat-card__info">
                            <div class="stat-card__label">SMS Balance</div>
                            <div class="stat-card__value"><?php echo number_format($sms_balance); ?></div>
                            <div class="stat-card__trend positive">
                                <i class="ri-arrow-up-line"></i> +2,000
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-card__icon" style="background: #FEF3F2;">
                            <i class="ri-checkbox-circle-line" style="color: #EF4444;"></i>
                        </div>
                        <div class="stat-card__info">
                            <div class="stat-card__label">Delivery Rate</div>
                            <div class="stat-card__value">98.2%</div>
                            <div class="stat-card__trend positive">
                                <i class="ri-arrow-up-line"></i> +2.1%
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-card__icon" style="background: #FEF9C3;">
                            <i class="ri-key-line" style="color: #EAB308;"></i>
                        </div>
                        <div class="stat-card__info">
                            <div class="stat-card__label">Active API Keys</div>
                            <div class="stat-card__value">3</div>
                            <div class="stat-card__trend">
                                2 active, 1 test
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SMS Usage Graph -->
                <div class="chart-card">
                    <div class="chart-card__header">
                        <h3 class="chart-card__title">SMS Usage Overview</h3>
                        <div class="chart-card__filters">
                            <button class="chart-card__filter active">Week</button>
                            <button class="chart-card__filter">Month</button>
                            <button class="chart-card__filter">Year</button>
                        </div>
                    </div>
                    <div class="chart-card__body">
                        <canvas id="usageChart" style="width: 100%; height: 300px;"></canvas>
                    </div>
                </div>

                <!-- Two Column Layout -->
                <div class="content-grid">
                    <!-- Recent Activity Table -->
                    <div class="table-card">
                        <div class="table-card__header">
                            <h3 class="table-card__title">Recent Activity</h3>
                            <a href="#" class="table-card__view-all">View All</a>
                        </div>
                        <div class="table-card__body">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Message Type</th>
                                        <th>Recipients</th>
                                        <th>Status</th>
                                        <th>Cost</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>2024-03-15 09:45</td>
                                        <td>Bulk Campaign</td>
                                        <td>1,245</td>
                                        <td><span class="badge badge-success">Delivered</span></td>
                                        <td>124.50 KES</td>
                                    </tr>
                                    <tr>
                                        <td>2024-03-15 08:30</td>
                                        <td>Transactional</td>
                                        <td>1</td>
                                        <td><span class="badge badge-success">Delivered</span></td>
                                        <td>0.50 KES</td>
                                    </tr>
                                    <tr>
                                        <td>2024-03-14 16:20</td>
                                        <td>Bulk Campaign</td>
                                        <td>3,500</td>
                                        <td><span class="badge badge-warning">Pending</span></td>
                                        <td>350.00 KES</td>
                                    </tr>
                                    <tr>
                                        <td>2024-03-14 14:15</td>
                                        <td>Transactional</td>
                                        <td>1</td>
                                        <td><span class="badge badge-success">Delivered</span></td>
                                        <td>0.50 KES</td>
                                    </tr>
                                    <tr>
                                        <td>2024-03-14 11:00</td>
                                        <td>Bulk Campaign</td>
                                        <td>850</td>
                                        <td><span class="badge badge-error">Failed</span></td>
                                        <td>0.00 KES</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Buy SMS Section -->
                    <div class="buy-sms-card">
                        <h3 class="buy-sms-card__title">Buy SMS Credits</h3>
                        <div class="buy-sms-card__body">
                            <div class="buy-sms-card__info">
                                <span class="buy-sms-card__label">School ID</span>
                                <span class="buy-sms-card__value"><?php echo $user['id']; ?></span>
                                <button class="buy-sms-card__copy" onclick="copySchoolId()">
                                    <i class="ri-file-copy-line"></i>
                                </button>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Number of SMS</label>
                                <input type="number" class="form-input" id="smsQuantity" value="1000" min="100" step="100">
                            </div>
                            
                            <div class="buy-sms-card__calculation">
                                <div class="buy-sms-card__row">
                                    <span>SMS Credits</span>
                                    <span id="smsCount">1,000</span>
                                </div>
                                <div class="buy-sms-card__row">
                                    <span>Price per SMS</span>
                                    <span>0.50 KES</span>
                                </div>
                                <div class="buy-sms-card__row total">
                                    <span>Total Amount</span>
                                    <span id="totalAmount">500.00 KES</span>
                                </div>
                            </div>
                            
                            <button class="btn btn-success btn-block" onclick="showPaymentModal()">
                                <i class="ri-money-dollar-circle-line"></i>
                                Pay via M-Pesa Buy Goods
                            </button>
                            
                            <div class="buy-sms-card__payment-status">
                                <span class="payment-status pending">
                                    <i class="ri-time-line"></i>
                                    Pending payment
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- API Key Management -->
                <div class="api-key-card">
                    <div class="api-key-card__header">
                        <h3 class="api-key-card__title">API Key Management</h3>
                        <button class="btn btn-primary" onclick="showGenerateKeyModal()">
                            <i class="ri-add-line"></i>
                            Generate New API Key
                        </button>
                    </div>
                    
                    <div class="api-key-card__body">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>API Key</th>
                                    <th>Name</th>
                                    <th>Created</th>
                                    <th>Last Used</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <div class="api-key-display">
                                            <code>edk_live_••••••••••••••••••••••••••••</code>
                                            <button class="api-key-copy" onclick="copyApiKey('prod_key_1')">
                                                <i class="ri-file-copy-line"></i>
                                            </button>
                                        </div>
                                    </td>
                                    <td>Production Key</td>
                                    <td>2024-01-15</td>
                                    <td>2024-03-15 09:30</td>
                                    <td><span class="badge badge-success">Active</span></td>
                                    <td>
                                        <div class="api-key-actions">
                                            <button class="api-key-action" title="Regenerate">
                                                <i class="ri-refresh-line"></i>
                                            </button>
                                            <button class="api-key-action" title="Revoke">
                                                <i class="ri-delete-bin-line"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <div class="api-key-display">
                                            <code>edk_test_••••••••••••••••••••••••••••</code>
                                            <button class="api-key-copy" onclick="copyApiKey('test_key_1')">
                                                <i class="ri-file-copy-line"></i>
                                            </button>
                                        </div>
                                    </td>
                                    <td>Test Key</td>
                                    <td>2024-02-20</td>
                                    <td>2024-03-14 16:45</td>
                                    <td><span class="badge badge-warning">Test Mode</span></td>
                                    <td>
                                        <div class="api-key-actions">
                                            <button class="api-key-action" title="Regenerate">
                                                <i class="ri-refresh-line"></i>
                                            </button>
                                            <button class="api-key-action" title="Revoke">
                                                <i class="ri-delete-bin-line"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        
                        <div class="api-key-card__webhook">
                            <label class="form-label">Webhook URL</label>
                            <div class="webhook-input">
                                <input type="url" class="form-input" value="https://api.yourschool.com/sms-webhook" placeholder="https://">
                                <button class="btn btn-secondary">Save</button>
                            </div>
                            <p class="webhook-hint">We'll send delivery reports to this URL</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- SMS Modal -->
    <div class="modal" id="smsModal">
        <div class="modal__content">
            <div class="modal__header">
                <h3 class="modal__title">Send SMS</h3>
                <button class="modal__close" onclick="closeModal('smsModal')">
                    <i class="ri-close-line"></i>
                </button>
            </div>
            <div class="modal__body">
                <div class="form-group">
                    <label class="form-label">Recipient(s)</label>
                    <input type="text" class="form-input" placeholder="+254712345678, +254798765432">
                    <p class="form-hint">Separate multiple numbers with commas</p>
                </div>
                <div class="form-group">
                    <label class="form-label">Message</label>
                    <textarea class="form-input" rows="4" placeholder="Type your message here..."></textarea>
                    <p class="form-hint character-count"><span id="charCount">0</span>/160 characters</p>
                </div>
                <div class="form-group">
                    <label class="form-label">Sender ID</label>
                    <input type="text" class="form-input" value="EduScore" placeholder="Sender ID">
                </div>
                <div class="form-group">
                    <label class="form-label">Schedule (Optional)</label>
                    <input type="datetime-local" class="form-input">
                </div>
            </div>
            <div class="modal__footer">
                <button class="btn btn-secondary" onclick="closeModal('smsModal')">Cancel</button>
                <button class="btn btn-primary" onclick="sendSMS()">
                    <span>Send SMS</span>
                    <div class="spinner" id="smsSpinner" style="display: none;"></div>
                </button>
            </div>
        </div>
    </div>

    <!-- Generate API Key Modal -->
    <div class="modal" id="apiKeyModal">
        <div class="modal__content">
            <div class="modal__header">
                <h3 class="modal__title">Generate API Key</h3>
                <button class="modal__close" onclick="closeModal('apiKeyModal')">
                    <i class="ri-close-line"></i>
                </button>
            </div>
            <div class="modal__body">
                <div class="form-group">
                    <label class="form-label">Key Name</label>
                    <input type="text" class="form-input" placeholder="e.g., Production Key">
                </div>
                <div class="form-group">
                    <label class="form-label">Environment</label>
                    <select class="form-input">
                        <option>Production</option>
                        <option>Test</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Permissions</label>
                    <div class="checkbox-group">
                        <label class="checkbox-label">
                            <input type="checkbox" checked> Send SMS
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" checked> View Reports
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox"> Manage Webhooks
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox"> Manage Contacts
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal__footer">
                <button class="btn btn-secondary" onclick="closeModal('apiKeyModal')">Cancel</button>
                <button class="btn btn-primary" onclick="generateApiKey()">Generate Key</button>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div class="toast" id="toast">
        <div class="toast__icon">
            <i class="ri-checkbox-circle-line"></i>
        </div>
        <div class="toast__content">
            <div class="toast__title">Success</div>
            <div class="toast__message">API key copied to clipboard</div>
        </div>
    </div>

    <script>
        // Chart Initialization
        const ctx = document.getElementById('usageChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'SMS Sent',
                    data: [12500, 18200, 15800, 21400, 19800, 14500, 16200],
                    borderColor: '#3B82F6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#3B82F6',
                    pointBorderColor: 'white',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'white',
                        titleColor: '#111827',
                        bodyColor: '#6B7280',
                        borderColor: '#E5E7EB',
                        borderWidth: 1,
                        padding: 12,
                        cornerRadius: 8,
                        boxPadding: 6
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: '#F3F4F6',
                            drawBorder: false
                        },
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString();
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // SMS Quantity Calculator
        document.getElementById('smsQuantity').addEventListener('input', function(e) {
            const quantity = parseInt(e.target.value) || 0;
            document.getElementById('smsCount').textContent = quantity.toLocaleString();
            document.getElementById('totalAmount').textContent = (quantity * 0.5).toFixed(2) + ' KES';
        });

        // Character Counter
        const messageInput = document.querySelector('textarea');
        if (messageInput) {
            messageInput.addEventListener('input', function() {
                document.getElementById('charCount').textContent = this.value.length;
            });
        }

        // Modal Functions
        function showSendSMSModal() {
            document.getElementById('smsModal').classList.add('active');
        }

        function showGenerateKeyModal() {
            document.getElementById('apiKeyModal').classList.add('active');
        }

        function showPaymentModal() {
            showToast('Initiating M-Pesa payment...', 'info');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        // Copy Functions
        function copySchoolId() {
            navigator.clipboard.writeText('<?php echo $user['id']; ?>');
            showToast('School ID copied to clipboard');
        }

        function copyApiKey(key) {
            navigator.clipboard.writeText(key);
            showToast('API key copied to clipboard');
        }

        // Toast Notification
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            const icon = toast.querySelector('.toast__icon i');
            const title = toast.querySelector('.toast__title');
            const msgEl = toast.querySelector('.toast__message');
            
            // Set icon and title based on type
            if (type === 'success') {
                icon.className = 'ri-checkbox-circle-line';
                title.textContent = 'Success';
            } else if (type === 'error') {
                icon.className = 'ri-error-warning-line';
                title.textContent = 'Error';
            } else if (type === 'info') {
                icon.className = 'ri-information-line';
                title.textContent = 'Info';
            }
            
            msgEl.textContent = message;
            
            toast.classList.add('active');
            
            setTimeout(() => {
                toast.classList.remove('active');
            }, 3000);
        }

        // Send SMS
        function sendSMS() {
            const spinner = document.getElementById('smsSpinner');
            const btn = spinner.parentElement;
            const btnText = btn.querySelector('span');
            
            btn.disabled = true;
            btnText.style.opacity = '0';
            spinner.style.display = 'inline-block';
            
            // Simulate API call
            setTimeout(() => {
                btn.disabled = false;
                btnText.style.opacity = '1';
                spinner.style.display = 'none';
                closeModal('smsModal');
                showToast('SMS sent successfully');
            }, 2000);
        }

        // Generate API Key
        function generateApiKey() {
            closeModal('apiKeyModal');
            showToast('API key generated successfully');
        }

        // Dropdown Toggle
        document.getElementById('profileDropdown').addEventListener('click', function(e) {
            e.stopPropagation();
            this.classList.toggle('active');
        });

        document.addEventListener('click', function() {
            document.getElementById('profileDropdown').classList.remove('active');
        });

        // Mobile Menu Toggle
        document.getElementById('menuToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });

        // Notification Click
        document.getElementById('notificationBtn').addEventListener('click', function() {
            showToast('No new notifications', 'info');
        });

        // Filter Buttons
        document.querySelectorAll('.chart-card__filter').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.chart-card__filter').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                showToast('Chart updated', 'info');
            });
        });

        // Close modals when clicking outside
        window.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal')) {
                e.target.classList.remove('active');
            }
        });
    </script>
</body>
</html>