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
    <title>Profile - <?php echo APP_NAME; ?></title>
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
            background-color: #f8fafc;
            overflow-x: hidden;
        }

        .main-content {
            margin-left: 250px;
            margin-top: 60px;
            padding: 30px;
            background-color: #f8fafc;
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

        /* Profile Header */
        .profile-header {
            background: linear-gradient(135deg, #1e3a8a 0%, #2e4a9a 100%);
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 30px;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .profile-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 70%;
            height: 200%;
            background: rgba(255, 255, 255, 0.05);
            transform: rotate(35deg);
            pointer-events: none;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            font-weight: 600;
            color: #1e3a8a;
            border: 4px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            position: relative;
            overflow: hidden;
        }

        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .avatar-upload {
            position: absolute;
            bottom: 0;
            right: 0;
            background: white;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1e3a8a;
            cursor: pointer;
            border: 2px solid white;
            transition: all 0.3s ease;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .avatar-upload:hover {
            background: #1e3a8a;
            color: white;
            transform: scale(1.1);
        }

        .profile-name {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .profile-username {
            font-size: 16px;
            opacity: 0.9;
            margin-bottom: 15px;
        }

        .profile-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 5px 15px;
            border-radius: 30px;
            font-size: 14px;
            display: inline-block;
            margin-right: 10px;
        }

        .profile-badge i {
            margin-right: 5px;
        }

        /* Stats Cards */
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
            border: 1px solid #eef2f6;
            transition: all 0.3s ease;
            height: 100%;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(30, 58, 138, 0.1);
            border-color: #1e3a8a;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #1e3a8a 0%, #2e4a9a 100%);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }

        .stat-icon i {
            font-size: 28px;
            color: white;
        }

        .stat-label {
            color: #64748b;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        .stat-value {
            color: #1e293b;
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-change {
            color: #10b981;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* Info Cards */
        .info-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
            border: 1px solid #eef2f6;
            margin-bottom: 30px;
        }

        .info-card h5 {
            color: #1e3a8a;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #eef2f6;
        }

        .info-item {
            display: flex;
            align-items: center;
            padding: 15px;
            background: #f8fafc;
            border-radius: 12px;
            margin-bottom: 10px;
            transition: all 0.3s ease;
        }

        .info-item:hover {
            background: #eef2f6;
        }

        .info-icon {
            width: 45px;
            height: 45px;
            background: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: #1e3a8a;
            font-size: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
        }

        .info-content {
            flex: 1;
        }

        .info-label {
            color: #64748b;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }

        .info-value {
            color: #1e293b;
            font-size: 16px;
            font-weight: 600;
        }

        .info-edit {
            color: #1e3a8a;
            font-size: 18px;
            opacity: 0.5;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .info-edit:hover {
            opacity: 1;
        }

        /* Activity Timeline */
        .timeline {
            position: relative;
            padding-left: 30px;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 8px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #eef2f6;
        }

        .timeline-item {
            position: relative;
            padding-bottom: 20px;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -30px;
            top: 0;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #1e3a8a;
            border: 3px solid white;
            box-shadow: 0 2px 10px rgba(30, 58, 138, 0.2);
        }

        .timeline-item.success::before {
            background: #10b981;
        }

        .timeline-item.warning::before {
            background: #f59e0b;
        }

        .timeline-item.danger::before {
            background: #ef4444;
        }

        .timeline-time {
            color: #64748b;
            font-size: 12px;
            margin-bottom: 5px;
        }

        .timeline-content {
            background: #f8fafc;
            padding: 15px;
            border-radius: 12px;
        }

        .timeline-content strong {
            color: #1e293b;
        }

        .timeline-content .badge {
            margin-left: 10px;
        }

        /* Modal Styles */
        .modal-content {
            border: none;
            border-radius: 20px;
        }

        .modal-header {
            background: linear-gradient(135deg, #1e3a8a 0%, #2e4a9a 100%);
            color: white;
            border-radius: 20px 20px 0 0;
            padding: 20px 25px;
        }

        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }

        .modal-body {
            padding: 25px;
        }

        .modal-footer {
            padding: 20px 25px;
            border-top: 1px solid #eef2f6;
        }

        .btn-primary {
            background: #1e3a8a;
            border: none;
            padding: 10px 25px;
            border-radius: 10px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: #152b63;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(30, 58, 138, 0.3);
        }

        .btn-outline-primary {
            border: 2px solid #1e3a8a;
            color: #1e3a8a;
            padding: 8px 20px;
            border-radius: 10px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-outline-primary:hover {
            background: #1e3a8a;
            color: white;
            transform: translateY(-2px);
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

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }

            .profile-header {
                padding: 30px 20px;
            }

            .profile-name {
                font-size: 24px;
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
            <h2><i class="bi bi-person-circle me-2"></i>My Profile</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Profile</li>
                </ol>
            </nav>
        </div>

        <!-- Profile Header -->
        <div class="profile-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="d-flex align-items-center">
                        <div class="profile-avatar me-4" id="profileAvatarContainer">
                            <?php if (!empty($user['profile_pic'])): ?>
                                <img src="../uploads/profile_pics/<?php echo $user['profile_pic']; ?>" alt="Profile" id="profileImage">
                            <?php else: ?>
                                <span id="avatarInitial"><?php echo strtoupper(substr($user['full_name'] ?? 'U', 0, 1)); ?></span>
                            <?php endif; ?>
                            <div class="avatar-upload" onclick="document.getElementById('profilePicInput').click()">
                                <i class="bi bi-camera"></i>
                            </div>
                            <input type="file" id="profilePicInput" accept="image/*" style="display: none;">
                        </div>
                        <div>
                            <div class="profile-name" id="profileName"><?php echo htmlspecialchars($user['full_name'] ?? 'User'); ?></div>
                            <div class="profile-username" id="profileUsername">@<?php echo htmlspecialchars($user['username'] ?? 'username'); ?></div>
                            <div>
                                <span class="profile-badge"><i class="bi bi-envelope"></i> <?php echo htmlspecialchars($user['email'] ?? ''); ?></span>
                                <?php if (!empty($user['phone'])): ?>
                                    <span class="profile-badge"><i class="bi bi-phone"></i> <?php echo htmlspecialchars($user['phone']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                    <button class="btn btn-outline-light me-2" onclick="openEditProfileModal()">
                        <i class="bi bi-pencil"></i> Edit Profile
                    </button>
                    <button class="btn btn-light" onclick="location.href='settings.php#security'">
                        <i class="bi bi-shield-lock"></i> Security
                    </button>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-envelope"></i>
                    </div>
                    <div class="stat-label">Total SMS</div>
                    <div class="stat-value" id="totalSms">0</div>
                    <div class="stat-change">
                        <i class="bi bi-arrow-up"></i> messages sent
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-people"></i>
                    </div>
                    <div class="stat-label">Contacts</div>
                    <div class="stat-value" id="totalContacts">0</div>
                    <div class="stat-change">
                        <i class="bi bi-person-plus"></i> saved contacts
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-key"></i>
                    </div>
                    <div class="stat-label">API Keys</div>
                    <div class="stat-value" id="totalApiKeys">0</div>
                    <div class="stat-change">
                        <i class="bi bi-shield-check"></i> active keys
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-calendar-check"></i>
                    </div>
                    <div class="stat-label">Member Since</div>
                    <div class="stat-value" id="memberSince"><?php echo date('Y', strtotime($user['created_at'] ?? 'now')); ?></div>
                    <div class="stat-change">
                        <i class="bi bi-clock"></i> active
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Personal Information -->
            <div class="col-md-6">
                <div class="info-card">
                    <h5><i class="bi bi-person-circle me-2"></i>Personal Information</h5>
                    
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="bi bi-person"></i>
                        </div>
                        <div class="info-content">
                            <div class="info-label">Full Name</div>
                            <div class="info-value" id="displayFullName"><?php echo htmlspecialchars($user['full_name'] ?? 'Not set'); ?></div>
                        </div>
                        <i class="bi bi-pencil info-edit" onclick="openEditProfileModal()"></i>
                    </div>

                    <div class="info-item">
                        <div class="info-icon">
                            <i class="bi bi-envelope"></i>
                        </div>
                        <div class="info-content">
                            <div class="info-label">Email Address</div>
                            <div class="info-value" id="displayEmail"><?php echo htmlspecialchars($user['email'] ?? 'Not set'); ?></div>
                        </div>
                        <i class="bi bi-pencil info-edit" onclick="openEditProfileModal()"></i>
                    </div>

                    <div class="info-item">
                        <div class="info-icon">
                            <i class="bi bi-phone"></i>
                        </div>
                        <div class="info-content">
                            <div class="info-label">Phone Number</div>
                            <div class="info-value" id="displayPhone"><?php echo !empty($user['phone']) ? htmlspecialchars($user['phone']) : 'Not set'; ?></div>
                        </div>
                        <i class="bi bi-pencil info-edit" onclick="openEditProfileModal()"></i>
                    </div>

                    <div class="info-item">
                        <div class="info-icon">
                            <i class="bi bi-building"></i>
                        </div>
                        <div class="info-content">
                            <div class="info-label">Company</div>
                            <div class="info-value" id="displayCompany"><?php echo !empty($user['company_name']) ? htmlspecialchars($user['company_name']) : 'Not set'; ?></div>
                        </div>
                        <i class="bi bi-pencil info-edit" onclick="openEditProfileModal()"></i>
                    </div>
                </div>
            </div>

            <!-- Account Information -->
            <div class="col-md-6">
                <div class="info-card">
                    <h5><i class="bi bi-shield-check me-2"></i>Account Information</h5>
                    
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="bi bi-person-badge"></i>
                        </div>
                        <div class="info-content">
                            <div class="info-label">Username</div>
                            <div class="info-value"><?php echo htmlspecialchars($user['username'] ?? 'Not set'); ?></div>
                        </div>
                    </div>

                    <div class="info-item">
                        <div class="info-icon">
                            <i class="bi bi-shield"></i>
                        </div>
                        <div class="info-content">
                            <div class="info-label">Account Status</div>
                            <div class="info-value">
                                <span class="badge bg-success"><?php echo ucfirst($user['status'] ?? 'active'); ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="info-item">
                        <div class="info-icon">
                            <i class="bi bi-calendar"></i>
                        </div>
                        <div class="info-content">
                            <div class="info-label">Member Since</div>
                            <div class="info-value"><?php echo date('F d, Y', strtotime($user['created_at'] ?? 'now')); ?></div>
                        </div>
                    </div>

                    <div class="info-item">
                        <div class="info-icon">
                            <i class="bi bi-clock-history"></i>
                        </div>
                        <div class="info-content">
                            <div class="info-label">Last Updated</div>
                            <div class="info-value" id="lastUpdated"><?php echo isset($user['updated_at']) ? date('F d, Y', strtotime($user['updated_at'])) : 'Never'; ?></div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="info-card">
                    <h5><i class="bi bi-clock-history me-2"></i>Recent Activity</h5>
                    <div class="timeline" id="recentActivity">
                        <div class="text-center py-4">
                            <div class="loading-spinner" style="width: 30px; height: 30px;"></div>
                            <p class="mt-2 text-muted">Loading activity...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Profile Modal -->
    <div class="modal fade" id="editProfileModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editProfileForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="full_name" id="editFullName" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" id="editEmail" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" name="phone" id="editPhone" class="form-control" placeholder="254712345678">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Company Name</label>
                            <input type="text" name="company_name" id="editCompany" class="form-control">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveProfile()" id="saveProfileBtn">Save Changes</button>
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

        // Load profile data and stats
        function loadProfileData() {
            const formData = new FormData();
            formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
            formData.append('action', 'get_profile');
            
            fetch('../ajax/profile_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    updateProfileDisplay(data.data.user);
                }
            })
            .catch(error => console.error('Error:', error));
            
            // Load stats
            loadStats();
        }

        // Load statistics
        function loadStats() {
            const formData = new FormData();
            formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
            formData.append('action', 'get_stats');
            
            fetch('../ajax/profile_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    document.getElementById('totalSms').textContent = formatNumber(data.data.total_sms);
                    document.getElementById('totalContacts').textContent = formatNumber(data.data.total_contacts);
                    document.getElementById('totalApiKeys').textContent = formatNumber(data.data.total_api_keys);
                    
                    // Display recent activity
                    displayRecentActivity(data.data.recent_activity);
                }
            })
            .catch(error => console.error('Error:', error));
        }

        // Display recent activity
        function displayRecentActivity(activities) {
            const container = document.getElementById('recentActivity');
            
            if (!activities || activities.length === 0) {
                container.innerHTML = '<p class="text-muted text-center py-3">No recent activity</p>';
                return;
            }
            
            let html = '';
            activities.forEach(activity => {
                const date = new Date(activity.created_at);
                const timeStr = date.toLocaleString('en-US', { 
                    month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' 
                });
                
                let statusClass = 'success';
                if (activity.status === 'failed') statusClass = 'danger';
                else if (activity.status === 'pending') statusClass = 'warning';
                
                html += `
                    <div class="timeline-item ${statusClass}">
                        <div class="timeline-time">${timeStr}</div>
                        <div class="timeline-content">
                            <strong>SMS to ${activity.recipient}</strong>
                            <span class="badge bg-${statusClass} float-end">${activity.status}</span>
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }

        // Update profile display
        function updateProfileDisplay(user) {
            document.getElementById('profileName').textContent = user.full_name;
            document.getElementById('profileUsername').textContent = '@' + user.username;
            document.getElementById('displayFullName').textContent = user.full_name;
            document.getElementById('displayEmail').textContent = user.email;
            document.getElementById('displayPhone').textContent = user.phone || 'Not set';
            document.getElementById('displayCompany').textContent = user.company_name || 'Not set';
            document.getElementById('lastUpdated').textContent = user.updated_at ? new Date(user.updated_at).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }) : 'Never';
            
            // Update avatar if exists
            if (user.profile_pic) {
                const avatarContainer = document.getElementById('profileAvatarContainer');
                avatarContainer.innerHTML = `
                    <img src="../uploads/profile_pics/${user.profile_pic}" alt="Profile" id="profileImage">
                    <div class="avatar-upload" onclick="document.getElementById('profilePicInput').click()">
                        <i class="bi bi-camera"></i>
                    </div>
                `;
            }
        }

        // Open edit profile modal
        function openEditProfileModal() {
            document.getElementById('editFullName').value = document.getElementById('displayFullName').textContent;
            document.getElementById('editEmail').value = document.getElementById('displayEmail').textContent;
            document.getElementById('editPhone').value = document.getElementById('displayPhone').textContent === 'Not set' ? '' : document.getElementById('displayPhone').textContent;
            document.getElementById('editCompany').value = document.getElementById('displayCompany').textContent === 'Not set' ? '' : document.getElementById('displayCompany').textContent;
            
            new bootstrap.Modal(document.getElementById('editProfileModal')).show();
        }

        // Save profile changes
        function saveProfile() {
            const form = document.getElementById('editProfileForm');
            const formData = new FormData(form);
            
            const submitBtn = document.getElementById('saveProfileBtn');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="loading-spinner me-2"></span> Saving...';
            
            fetch('../ajax/profile_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showToast(data.message, 'success');
                    bootstrap.Modal.getInstance(document.getElementById('editProfileModal')).hide();
                    updateProfileDisplay(data.data.user);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error saving profile', 'error');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
        }

        // Profile picture upload
        document.getElementById('profilePicInput').addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                const file = this.files[0];
                
                // Validate file type
                if (!file.type.match('image.*')) {
                    showToast('Please select an image file', 'error');
                    return;
                }
                
                // Validate file size (2MB max)
                if (file.size > 2 * 1024 * 1024) {
                    showToast('Image size must be less than 2MB', 'error');
                    return;
                }
                
                const formData = new FormData();
                formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
                formData.append('action', 'upload_profile_pic');
                formData.append('profile_pic', file);
                
                // Show loading on avatar
                const avatarContainer = document.getElementById('profileAvatarContainer');
                const originalContent = avatarContainer.innerHTML;
                avatarContainer.innerHTML = `
                    <div class="profile-avatar" style="background: #1e3a8a;">
                        <div class="loading-spinner" style="width: 30px; height: 30px;"></div>
                    </div>
                `;
                
                fetch('../ajax/profile_handler.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        showToast(data.message, 'success');
                        // Reload profile to show new image
                        loadProfileData();
                    } else {
                        showToast(data.message, 'error');
                        avatarContainer.innerHTML = originalContent;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Error uploading image', 'error');
                    avatarContainer.innerHTML = originalContent;
                });
            }
        });

        // Format number with commas
        function formatNumber(num) {
            return Number(num).toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
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

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadProfileData();
        });
    </script>
</body>
</html>