<?php
require_once '../config/config.php';
requireLogin();

$user_id = $_SESSION['user_id'];

// Get user data for initial load
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
} catch (Exception $e) {
    error_log("User fetch error: " . $e->getMessage());
    $user = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - <?php echo APP_NAME; ?></title>
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

        .settings-card {
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            margin-bottom: 30px;
            overflow: hidden;
        }

        .settings-header {
            background-color: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #e0e0e0;
            font-weight: 600;
            color: #1e3a8a;
        }

        .settings-header i {
            margin-right: 8px;
        }

        .settings-body {
            padding: 20px;
        }

        .form-label {
            font-weight: 500;
            color: #333333;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-control, .form-select {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 10px 15px;
            font-size: 14px;
            transition: all 0.2s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: #1e3a8a;
            box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.1);
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
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 13px;
        }

        .btn-outline-danger:hover {
            background-color: #dc3545;
            color: #ffffff;
        }

        .sender-id-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            margin-bottom: 10px;
            transition: all 0.2s ease;
        }

        .sender-id-item:hover {
            border-color: #1e3a8a;
            box-shadow: 0 2px 8px rgba(30, 58, 138, 0.1);
        }

        .sender-id-value {
            font-family: 'Courier New', monospace;
            font-weight: 600;
            color: #333333;
            font-size: 16px;
        }

        .sender-id-status {
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
        }

        .status-approved {
            background-color: #d1fae5;
            color: #065f46;
        }

        .status-pending {
            background-color: #fed7aa;
            color: #92400e;
        }

        .status-rejected {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .default-badge {
            background-color: #1e3a8a;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
            margin-left: 10px;
        }

        .info-box {
            background-color: #f8f9fa;
            border-left: 4px solid #1e3a8a;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .info-box i {
            color: #1e3a8a;
            margin-right: 8px;
        }

        .nav-tabs .nav-link {
            color: #666666;
            border: none;
            padding: 12px 20px;
            font-weight: 500;
        }

        .nav-tabs .nav-link:hover {
            color: #1e3a8a;
            border: none;
        }

        .nav-tabs .nav-link.active {
            color: #1e3a8a;
            border-bottom: 3px solid #1e3a8a;
            background: none;
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

        .loading-spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .password-strength .progress {
            height: 5px;
            margin-top: 5px;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }

            .toast-container {
                top: 70px;
                right: 10px;
                left: 10px;
            }

            .toast {
                min-width: auto;
            }

            .sender-id-item {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }

            .sender-id-item .d-flex {
                flex-wrap: wrap;
                justify-content: center;
            }
        }
    </style>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php 
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
            <h2><i class="bi bi-gear me-2"></i>Settings</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Settings</li>
                </ol>
            </nav>
        </div>

        <!-- Settings Tabs -->
        <ul class="nav nav-tabs mb-4" id="settingsTabs">
            <li class="nav-item">
                <button class="nav-link active" id="profile-tab" onclick="switchTab('profile')">
                    <i class="bi bi-person"></i> Profile
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="security-tab" onclick="switchTab('security')">
                    <i class="bi bi-shield-lock"></i> Security
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="sender-ids-tab" onclick="switchTab('sender-ids')">
                    <i class="bi bi-tag"></i> Sender IDs
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="billing-tab" onclick="switchTab('billing')">
                    <i class="bi bi-credit-card"></i> Billing
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div id="profile" class="tab-pane active">
            <!-- Profile Information Card -->
            <div class="settings-card">
                <div class="settings-header">
                    <i class="bi bi-person-circle"></i> Profile Information
                </div>
                <div class="settings-body">
                    <form id="profileForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Full Name</label>
                                <input type="text" name="full_name" id="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" disabled>
                                <small class="text-muted">Username cannot be changed</small>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Email Address</label>
                                <input type="email" name="email" id="email" class="form-control" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" name="phone" id="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="254712345678">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Company Name</label>
                            <input type="text" name="company_name" id="company_name" class="form-control" value="<?php echo htmlspecialchars($user['company_name'] ?? ''); ?>">
                        </div>
                        
                        <button type="submit" class="btn btn-primary" id="saveProfileBtn">
                            <i class="bi bi-check-lg"></i> Save Changes
                        </button>
                    </form>
                </div>
            </div>

            <!-- Account Information Card -->
            <div class="settings-card">
                <div class="settings-header">
                    <i class="bi bi-info-circle"></i> Account Information
                </div>
                <div class="settings-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Account Status:</strong> 
                                <span class="badge bg-success" id="accountStatus"><?php echo ucfirst($user['status'] ?? 'active'); ?></span>
                            </p>
                            <p><strong>Member Since:</strong> <span id="memberSince"><?php echo isset($user['created_at']) ? date('F d, Y', strtotime($user['created_at'])) : 'N/A'; ?></span></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>SMS Balance:</strong> <span id="smsBalance"><?php echo number_format($user['sms_balance'] ?? 0); ?></span> credits</p>
                            <p><strong>Last Updated:</strong> <span id="lastUpdated"><?php echo isset($user['updated_at']) ? date('F d, Y', strtotime($user['updated_at'])) : 'Never'; ?></span></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Security Tab -->
        <div id="security" class="tab-pane" style="display: none;">
            <div class="settings-card">
                <div class="settings-header">
                    <i class="bi bi-key"></i> Change Password
                </div>
                <div class="settings-body">
                    <div class="info-box">
                        <i class="bi bi-info-circle"></i>
                        Password must be at least 8 characters long and include a mix of uppercase, lowercase, numbers, and special characters.
                    </div>
                    
                    <form id="passwordForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="mb-3">
                            <label class="form-label">Current Password</label>
                            <input type="password" name="current_password" id="current_password" class="form-control" required>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">New Password</label>
                                <input type="password" name="new_password" id="new_password" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="password-strength">
                                <div class="progress">
                                    <div class="progress-bar" id="passwordStrength" style="width: 0%;"></div>
                                </div>
                            </div>
                            <small class="text-muted" id="passwordHelp"></small>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" id="changePasswordBtn">
                            <i class="bi bi-check-lg"></i> Update Password
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Sender IDs Tab -->
        <div id="sender-ids" class="tab-pane" style="display: none;">
            <div class="settings-card">
                <div class="settings-header">
                    <i class="bi bi-tag"></i> Your Sender IDs
                </div>
                <div class="settings-body">
                    <div class="info-box">
                        <i class="bi bi-info-circle"></i>
                        Sender IDs must be approved before use. This typically takes 24-48 hours.
                    </div>
                    
                    <button class="btn btn-primary mb-3" onclick="openAddSenderModal()">
                        <i class="bi bi-plus-lg"></i> Request New Sender ID
                    </button>
                    
                    <div id="senderIdsList">
                        <!-- Sender IDs will be loaded here via AJAX -->
                        <div class="text-center py-4">
                            <div class="loading-spinner" style="width: 30px; height: 30px; border-width: 3px;"></div>
                            <p class="mt-2 text-muted">Loading sender IDs...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Billing Tab -->
        <div id="billing" class="tab-pane" style="display: none;">
            <div class="settings-card">
                <div class="settings-header">
                    <i class="bi bi-credit-card"></i> Billing Information
                </div>
                <div class="settings-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Current Balance</h6>
                            <h2 class="text-primary" id="billingBalance"><?php echo number_format($user['sms_balance'] ?? 0); ?> credits</h2>
                            <a href="topup.php" class="btn btn-primary mt-2">
                                <i class="bi bi-plus-circle"></i> Top Up Credits
                            </a>
                        </div>
                        <div class="col-md-6">
                            <h6>Usage This Month</h6>
                            <h3 class="text-success" id="monthlyUsage">Loading...</h3>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <h6>Pricing Plans</h6>
                    <div class="row mt-3">
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Pay As You Go</h5>
                                    <h2 class="text-primary">KES 1</h2>
                                    <p class="text-muted">per SMS credit</p>
                                    <small>No expiry, no commitment</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Business</h5>
                                    <h2 class="text-primary">KES 0.90</h2>
                                    <p class="text-muted">per SMS credit</p>
                                    <small>Min. 10,000 credits</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Enterprise</h5>
                                    <h2 class="text-primary">KES 0.80</h2>
                                    <p class="text-muted">per SMS credit</p>
                                    <small>Min. 50,000 credits</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Sender ID Modal -->
    <div class="modal fade" id="addSenderModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Request New Sender ID</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addSenderForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="add_sender">
                        
                        <div class="mb-3">
                            <label class="form-label">Sender ID</label>
                            <input type="text" name="sender_id" id="newSenderId" class="form-control" maxlength="11" required 
                                   pattern="[A-Za-z0-9]+" title="Letters and numbers only">
                            <small class="text-muted">Maximum 11 characters, letters and numbers only. Will be converted to uppercase.</small>
                        </div>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            Sender IDs must be approved by our team. This process usually takes 24-48 hours.
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="addSenderId()" id="addSenderBtn">Submit Request</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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

        // Mobile menu toggle
        document.getElementById('menuToggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });

        // Tab switching
        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-pane').forEach(tab => {
                tab.style.display = 'none';
            });
            
            // Remove active class from all tab buttons
            document.querySelectorAll('.nav-link').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName).style.display = 'block';
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Load tab-specific data
            if (tabName === 'sender-ids') {
                loadSenderIds();
            } else if (tabName === 'billing') {
                loadMonthlyUsage();
            }
        }

        // Password strength checker
        const newPassword = document.getElementById('new_password');
        const confirmPassword = document.getElementById('confirm_password');
        const passwordStrength = document.getElementById('passwordStrength');
        const passwordHelp = document.getElementById('passwordHelp');

        function checkPasswordStrength() {
            if (!newPassword) return;
            
            const password = newPassword.value;
            let strength = 0;
            
            if (password.length >= 8) strength += 20;
            if (password.match(/[a-z]+/)) strength += 20;
            if (password.match(/[A-Z]+/)) strength += 20;
            if (password.match(/[0-9]+/)) strength += 20;
            if (password.match(/[$@#&!]+/)) strength += 20;
            
            strength = Math.min(strength, 100);
            
            passwordStrength.style.width = strength + '%';
            
            if (strength < 40) {
                passwordStrength.style.backgroundColor = '#dc2626';
                passwordHelp.textContent = 'Weak password';
                passwordHelp.style.color = '#dc2626';
            } else if (strength < 60) {
                passwordStrength.style.backgroundColor = '#f59e0b';
                passwordHelp.textContent = 'Fair password';
                passwordHelp.style.color = '#f59e0b';
            } else if (strength < 80) {
                passwordStrength.style.backgroundColor = '#3b82f6';
                passwordHelp.textContent = 'Good password';
                passwordHelp.style.color = '#3b82f6';
            } else {
                passwordStrength.style.backgroundColor = '#10b981';
                passwordHelp.textContent = 'Strong password';
                passwordHelp.style.color = '#10b981';
            }
            
            // Check if passwords match
            if (confirmPassword && confirmPassword.value) {
                if (password === confirmPassword.value) {
                    confirmPassword.style.borderColor = '#10b981';
                } else {
                    confirmPassword.style.borderColor = '#dc2626';
                }
            }
        }

        if (newPassword) {
            newPassword.addEventListener('input', checkPasswordStrength);
        }
        
        if (confirmPassword) {
            confirmPassword.addEventListener('input', checkPasswordStrength);
        }

        // Profile form submission
        document.getElementById('profileForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = document.getElementById('saveProfileBtn');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="loading-spinner me-2"></span> Saving...';
            
            fetch('../ajax/settings_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showToast(data.message, 'success');
                    
                    // Update displayed info
                    if (data.data.user) {
                        document.getElementById('full_name').value = data.data.user.full_name;
                        document.getElementById('email').value = data.data.user.email;
                        document.getElementById('phone').value = data.data.user.phone || '';
                        document.getElementById('company_name').value = data.data.user.company_name || '';
                        document.getElementById('lastUpdated').textContent = new Date(data.data.user.updated_at).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
                        
                        // Update sidebar name if needed
                        const sidebarName = document.querySelector('.user-name');
                        if (sidebarName) sidebarName.textContent = data.data.user.full_name;
                    }
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error updating profile', 'error');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
        });

        // Password form submission
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = document.getElementById('changePasswordBtn');
            const originalText = submitBtn.innerHTML;
            
            // Validate passwords match
            if (formData.get('new_password') !== formData.get('confirm_password')) {
                showToast('New passwords do not match', 'error');
                return;
            }
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="loading-spinner me-2"></span> Updating...';
            
            fetch('../ajax/settings_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showToast(data.message, 'success');
                    document.getElementById('passwordForm').reset();
                    passwordStrength.style.width = '0%';
                    passwordHelp.textContent = '';
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error changing password', 'error');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
        });

        // Load sender IDs
        function loadSenderIds() {
            const container = document.getElementById('senderIdsList');
            
            const formData = new FormData();
            formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
            formData.append('action', 'get_sender_ids');
            
            fetch('../ajax/settings_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    displaySenderIds(data.data.sender_ids);
                } else {
                    container.innerHTML = '<div class="alert alert-danger">Failed to load sender IDs</div>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                container.innerHTML = '<div class="alert alert-danger">Error loading sender IDs</div>';
            });
        }

        // Display sender IDs
        function displaySenderIds(senderIds) {
            const container = document.getElementById('senderIdsList');
            
            if (!senderIds || senderIds.length === 0) {
                container.innerHTML = '<p class="text-muted text-center py-4">No sender IDs found. Request one to start sending SMS.</p>';
                return;
            }
            
            let html = '';
            senderIds.forEach(sender => {
                const statusClass = sender.status === 'approved' ? 'status-approved' : 
                                   sender.status === 'pending' ? 'status-pending' : 'status-rejected';
                
                html += `
                    <div class="sender-id-item" id="sender-${sender.id}">
                        <div>
                            <span class="sender-id-value">${escapeHtml(sender.sender_id)}</span>
                            ${sender.is_default ? '<span class="default-badge">Default</span>' : ''}
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="sender-id-status ${statusClass}">
                                ${sender.status.charAt(0).toUpperCase() + sender.status.slice(1)}
                            </span>
                            ${sender.status === 'approved' && !sender.is_default ? `
                                <button class="btn btn-sm btn-outline-primary" onclick="setDefaultSender(${sender.id})">
                                    <i class="bi bi-star"></i> Set Default
                                </button>
                            ` : ''}
                            ${!sender.is_default ? `
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteSender(${sender.id})">
                                    <i class="bi bi-trash"></i>
                                </button>
                            ` : ''}
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }

        // Open add sender modal
        function openAddSenderModal() {
            document.getElementById('newSenderId').value = '';
            new bootstrap.Modal(document.getElementById('addSenderModal')).show();
        }

        // Add sender ID
        function addSenderId() {
            const form = document.getElementById('addSenderForm');
            const formData = new FormData(form);
            const senderId = formData.get('sender_id').toUpperCase();
            
            if (!senderId.match(/^[A-Z0-9]+$/)) {
                showToast('Sender ID can only contain letters and numbers', 'error');
                return;
            }
            
            const submitBtn = document.getElementById('addSenderBtn');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="loading-spinner me-2"></span> Submitting...';
            
            fetch('../ajax/settings_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showToast(data.message, 'success');
                    bootstrap.Modal.getInstance(document.getElementById('addSenderModal')).hide();
                    loadSenderIds();
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error adding sender ID', 'error');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
        }

        // Set default sender
        function setDefaultSender(id) {
            if (!confirm('Set this as your default sender ID?')) return;
            
            const formData = new FormData();
            formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
            formData.append('action', 'set_default_sender');
            formData.append('sender_id', id);
            
            fetch('../ajax/settings_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showToast(data.message, 'success');
                    displaySenderIds(data.data.sender_ids);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error setting default sender', 'error');
            });
        }

        // Delete sender
        function deleteSender(id) {
            if (!confirm('Are you sure you want to delete this sender ID?')) return;
            
            const formData = new FormData();
            formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
            formData.append('action', 'delete_sender');
            formData.append('sender_id', id);
            
            fetch('../ajax/settings_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showToast(data.message, 'success');
                    document.getElementById(`sender-${id}`).remove();
                    
                    // Check if any senders left
                    const container = document.getElementById('senderIdsList');
                    if (container.children.length === 0) {
                        container.innerHTML = '<p class="text-muted text-center py-4">No sender IDs found. Request one to start sending SMS.</p>';
                    }
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error deleting sender ID', 'error');
            });
        }

        // Load monthly usage
        function loadMonthlyUsage() {
            const formData = new FormData();
            formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
            formData.append('action', 'get_monthly_usage');
            
            fetch('../ajax/settings_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    document.getElementById('monthlyUsage').textContent = 
                        Number(data.data.month_usage).toFixed(2) + ' credits';
                } else {
                    document.getElementById('monthlyUsage').textContent = '0 credits';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('monthlyUsage').textContent = 'Error loading';
            });
        }

        // Escape HTML to prevent XSS
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
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

        // Initialize first tab
        document.addEventListener('DOMContentLoaded', function() {
            // Load sender IDs if that tab is active (though it's not by default)
            if (document.getElementById('sender-ids').style.display !== 'none') {
                loadSenderIds();
            }
        });
    </script>
</body>
</html>