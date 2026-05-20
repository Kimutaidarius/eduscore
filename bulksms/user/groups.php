<?php
require_once '../config/config.php';
requireLogin();

$user_id = $_SESSION['user_id'];

// Get user data
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
    <title>Contact Groups - <?php echo APP_NAME; ?></title>
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

        .btn-outline-danger {
            border: 1px solid #dc3545;
            color: #dc3545;
            background: transparent;
            padding: 5px 10px;
            border-radius: 6px;
        }

        .btn-outline-danger:hover {
            background-color: #dc3545;
            color: #ffffff;
        }

        .group-card {
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            padding: 20px;
            transition: all 0.2s ease;
            height: 100%;
            position: relative;
            cursor: pointer;
        }

        .group-card:hover {
            border-color: #1e3a8a;
            box-shadow: 0 4px 12px rgba(30, 58, 138, 0.1);
            transform: translateY(-2px);
        }

        .group-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            background-color: #1e3a8a;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 15px;
        }

        .group-name {
            font-weight: 600;
            color: #333333;
            font-size: 18px;
            margin-bottom: 5px;
            word-break: break-word;
        }

        .group-description {
            color: #666666;
            font-size: 13px;
            margin-bottom: 15px;
            word-break: break-word;
        }

        .group-stats {
            display: flex;
            gap: 15px;
            margin: 15px 0;
            padding: 10px 0;
            border-top: 1px solid #e0e0e0;
            border-bottom: 1px solid #e0e0e0;
        }

        .stat-item {
            text-align: center;
            flex: 1;
        }

        .stat-value {
            font-weight: 700;
            color: #1e3a8a;
            font-size: 20px;
        }

        .stat-label {
            font-size: 11px;
            color: #666666;
            text-transform: uppercase;
        }

        .filter-section {
            background-color: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
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

        .table th {
            background-color: #f8f9fa;
            color: #333333;
            font-weight: 600;
            font-size: 13px;
            padding: 15px;
        }

        .table td {
            padding: 15px;
            color: #333333;
            font-size: 13px;
            border-bottom: 1px solid #e0e0e0;
            vertical-align: middle;
        }

        .table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .action-btn {
            padding: 5px 10px;
            border: 1px solid #e0e0e0;
            background: white;
            border-radius: 6px;
            color: #666666;
            margin: 0 2px;
            transition: all 0.2s ease;
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
            border-radius: 8px;
            cursor: pointer;
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
            cursor: not-allowed;
        }

        .modal-header {
            background-color: #1e3a8a;
            color: white;
        }

        .modal-header .btn-close {
            filter: brightness(0) invert(1);
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

        .toast.info {
            border-left-color: #1e3a8a;
        }

        .badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 11px;
        }

        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(30, 58, 138, 0.3);
            border-radius: 50%;
            border-top-color: #1e3a8a;
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

            .toast-container {
                top: 70px;
                right: 10px;
                left: 10px;
            }

            .toast {
                min-width: auto;
            }

            .group-stats {
                flex-direction: column;
                gap: 5px;
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
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2><i class="bi bi-people me-2"></i>Contact Groups</h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="contacts.php">Contacts</a></li>
                            <li class="breadcrumb-item active">Groups</li>
                        </ol>
                    </nav>
                </div>
                <div>
                    <button class="btn btn-primary" onclick="openAddModal()">
                        <i class="bi bi-plus-lg"></i> Create Group
                    </button>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-section">
            <div class="row g-3">
                <div class="col-md-10">
                    <input type="text" id="searchInput" class="form-control" placeholder="Search groups by name...">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary w-100" onclick="loadGroups(1)">
                        <i class="bi bi-search"></i> Search
                    </button>
                </div>
            </div>
        </div>

        <!-- Loading Indicator -->
        <div id="loadingIndicator" class="text-center py-5" style="display: none;">
            <div class="loading-spinner" style="width: 40px; height: 40px;"></div>
            <p class="mt-3 text-muted">Loading groups...</p>
        </div>

        <!-- Groups Grid -->
        <div id="groupsGrid">
            <div class="row" id="groupsContainer">
                <!-- Groups will be loaded here via AJAX -->
                <div class="col-12 text-center py-5">
                    <div class="loading-spinner" style="width: 40px; height: 40px;"></div>
                    <p class="mt-3 text-muted">Loading groups...</p>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <nav id="paginationContainer" class="mt-3" style="display: none;">
            <ul class="pagination" id="pagination"></ul>
        </nav>
    </div>

    <!-- Add/Edit Group Modal -->
    <div class="modal fade" id="groupModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Create New Group</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="groupForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" id="formAction" value="add">
                        <input type="hidden" name="group_id" id="groupId">
                        
                        <div class="mb-3">
                            <label class="form-label">Group Name *</label>
                            <input type="text" name="name" id="groupName" class="form-control" required maxlength="100">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" id="groupDescription" class="form-control" rows="3" maxlength="255"></textarea>
                            <small class="text-muted">Optional description for this group (max 255 characters)</small>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveGroup()" id="saveBtn">Create Group</button>
                </div>
            </div>
        </div>
    </div>

    <!-- View Group Details Modal -->
    <div class="modal fade" id="viewGroupModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewModalTitle">Group Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="groupDetails"></div>
                    <div id="groupContactsList"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="sendToGroup()" id="sendToGroupBtn">Send SMS to Group</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the group <strong id="deleteGroupName"></strong>?</p>
                    <p id="deleteWarning" class="text-danger"></p>
                    <p class="text-danger">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" onclick="confirmDelete()" id="deleteBtn">Delete Group</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Global variables
        let currentPage = 1;
        let totalPages = 1;
        let deleteId = null;
        let currentGroupId = null;
        let groupModal = null;
        let viewGroupModal = null;
        let deleteModal = null;

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize modals
            groupModal = new bootstrap.Modal(document.getElementById('groupModal'));
            viewGroupModal = new bootstrap.Modal(document.getElementById('viewGroupModal'));
            deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            
            // Load groups
            loadGroups(1);
            
            // Add event listeners
            document.getElementById('searchInput').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    loadGroups(1);
                }
            });
        });

        // Mobile menu toggle
        document.getElementById('menuToggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });

        // Toast notification function
        function showToast(message, type = 'success') {
            const toastContainer = document.getElementById('toastContainer');
            const toastId = 'toast-' + Date.now();
            
            const icon = type === 'success' ? 'bi-check-circle-fill' : 
                        type === 'error' ? 'bi-exclamation-triangle-fill' : 
                        type === 'warning' ? 'bi-exclamation-triangle-fill' : 'bi-info-circle-fill';
            
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

        // Load groups via AJAX
        function loadGroups(page) {
            currentPage = page;
            
            const search = document.getElementById('searchInput').value;
            
            // Show loading
            document.getElementById('loadingIndicator').style.display = 'block';
            document.getElementById('groupsContainer').innerHTML = '';
            document.getElementById('paginationContainer').style.display = 'none';
            
            const formData = new FormData();
            formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
            formData.append('action', 'get_groups');
            formData.append('page', page);
            formData.append('search', search);
            
            fetch('../ajax/groups_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('loadingIndicator').style.display = 'none';
                
                if (data.status === 'success') {
                    displayGroups(data.data.groups);
                    setupPagination(data.data.page, data.data.total_pages);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('loadingIndicator').style.display = 'none';
                showToast('Error loading groups', 'error');
            });
        }

        // Display groups in grid
        function displayGroups(groups) {
            const container = document.getElementById('groupsContainer');
            
            if (groups.length === 0) {
                container.innerHTML = `
                    <div class="col-12 text-center py-5">
                        <i class="bi bi-people" style="font-size: 48px; color: #e0e0e0;"></i>
                        <h5 class="mt-3">No Groups Found</h5>
                        <p class="text-muted">Create your first group to organize contacts.</p>
                        <button class="btn btn-primary" onclick="openAddModal()">
                            <i class="bi bi-plus-lg"></i> Create Group
                        </button>
                    </div>
                `;
                return;
            }
            
            let html = '';
            groups.forEach(group => {
                const description = group.description || '<em>No description</em>';
                const contactCount = parseInt(group.contact_count) || 0;
                
                html += `
                    <div class="col-md-4 col-lg-3 mb-4">
                        <div class="group-card" onclick="viewGroup(${group.id})">
                            <div class="group-icon">
                                <i class="bi bi-people-fill"></i>
                            </div>
                            <div class="group-name">${escapeHtml(group.name)}</div>
                            <div class="group-description">${escapeHtml(description)}</div>
                            <div class="group-stats">
                                <div class="stat-item">
                                    <div class="stat-value">${contactCount}</div>
                                    <div class="stat-label">Contacts</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value">${contactCount}</div>
                                    <div class="stat-label">Messages</div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between" onclick="event.stopPropagation()">
                                <div>
                                    <button class="action-btn" onclick="editGroup(${group.id})" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="action-btn" onclick="viewGroup(${group.id})" title="View">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <button class="action-btn" onclick="sendToGroup(${group.id})" title="Send SMS">
                                        <i class="bi bi-envelope"></i>
                                    </button>
                                </div>
                                <div>
                                    <button class="action-btn text-danger" onclick="openDeleteModal(${group.id}, '${escapeHtml(group.name)}', ${contactCount})" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }

        // Setup pagination
        function setupPagination(current, total) {
            totalPages = total;
            
            if (total <= 1) {
                document.getElementById('paginationContainer').style.display = 'none';
                return;
            }
            
            document.getElementById('paginationContainer').style.display = 'block';
            
            let html = '';
            
            // Previous button
            html += `
                <li class="page-item ${current <= 1 ? 'disabled' : ''}">
                    <span class="page-link" onclick="${current > 1 ? `loadGroups(${current - 1})` : ''}">
                        <i class="bi bi-chevron-left"></i>
                    </span>
                </li>
            `;
            
            // Page numbers
            let start = Math.max(1, current - 2);
            let end = Math.min(total, current + 2);
            
            if (start > 1) {
                html += `<li class="page-item"><span class="page-link" onclick="loadGroups(1)">1</span></li>`;
                if (start > 2) {
                    html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
                }
            }
            
            for (let i = start; i <= end; i++) {
                html += `
                    <li class="page-item ${i === current ? 'active' : ''}">
                        <span class="page-link" onclick="loadGroups(${i})">${i}</span>
                    </li>
                `;
            }
            
            if (end < total) {
                if (end < total - 1) {
                    html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
                }
                html += `<li class="page-item"><span class="page-link" onclick="loadGroups(${total})">${total}</span></li>`;
            }
            
            // Next button
            html += `
                <li class="page-item ${current >= total ? 'disabled' : ''}">
                    <span class="page-link" onclick="${current < total ? `loadGroups(${current + 1})` : ''}">
                        <i class="bi bi-chevron-right"></i>
                    </span>
                </li>
            `;
            
            document.getElementById('pagination').innerHTML = html;
        }

        // Open add group modal
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Create New Group';
            document.getElementById('formAction').value = 'add';
            document.getElementById('groupId').value = '';
            document.getElementById('groupName').value = '';
            document.getElementById('groupDescription').value = '';
            document.getElementById('saveBtn').textContent = 'Create Group';
            groupModal.show();
        }

        // Edit group
        function editGroup(id) {
            const formData = new FormData();
            formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
            formData.append('action', 'get_group');
            formData.append('group_id', id);
            
            fetch('../ajax/groups_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    const group = data.data.group;
                    document.getElementById('modalTitle').textContent = 'Edit Group';
                    document.getElementById('formAction').value = 'edit';
                    document.getElementById('groupId').value = group.id;
                    document.getElementById('groupName').value = group.name;
                    document.getElementById('groupDescription').value = group.description || '';
                    document.getElementById('saveBtn').textContent = 'Update Group';
                    groupModal.show();
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error loading group', 'error');
            });
        }

        // Save group (add or edit)
        function saveGroup() {
            const form = document.getElementById('groupForm');
            const formData = new FormData(form);
            
            // Validate
            const name = formData.get('name').trim();
            
            if (!name) {
                showToast('Group name is required', 'error');
                return;
            }
            
            // Disable save button
            const saveBtn = document.getElementById('saveBtn');
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<span class="loading-spinner me-2"></span> Saving...';
            
            fetch('../ajax/groups_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showToast(data.message, 'success');
                    groupModal.hide();
                    loadGroups(currentPage);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error saving group', 'error');
            })
            .finally(() => {
                saveBtn.disabled = false;
                saveBtn.innerHTML = formData.get('action') === 'add' ? 'Create Group' : 'Update Group';
            });
        }

        // View group details
        function viewGroup(id) {
            currentGroupId = id;
            
            const formData = new FormData();
            formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
            formData.append('action', 'get_group');
            formData.append('group_id', id);
            
            fetch('../ajax/groups_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    const group = data.data.group;
                    const contacts = data.data.contacts || [];
                    
                    document.getElementById('viewModalTitle').textContent = group.name;
                    
                    let contactsHtml = '<h6 class="mt-4">Contacts in this group:</h6>';
                    if (contacts.length === 0) {
                        contactsHtml += '<p class="text-muted">No contacts in this group yet.</p>';
                    } else {
                        contactsHtml += '<div class="list-group">';
                        contacts.forEach(contact => {
                            contactsHtml += `
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>${escapeHtml(contact.name || 'Unnamed')}</strong><br>
                                        <small>${escapeHtml(contact.phone)}</small>
                                    </div>
                                    <button class="btn btn-sm btn-outline-primary" onclick="sendToContact('${contact.phone}')">
                                        <i class="bi bi-envelope"></i>
                                    </button>
                                </div>
                            `;
                        });
                        contactsHtml += '</div>';
                        
                        if (group.contact_count > 10) {
                            contactsHtml += `<p class="text-muted mt-2">... and ${group.contact_count - 10} more contacts</p>`;
                        }
                    }
                    
                    document.getElementById('groupDetails').innerHTML = `
                        <p><strong>Description:</strong> ${group.description || '<em>No description</em>'}</p>
                        <p><strong>Total Contacts:</strong> ${group.contact_count}</p>
                        <p><strong>Created:</strong> ${new Date(group.created_at).toLocaleDateString()}</p>
                    `;
                    
                    document.getElementById('groupContactsList').innerHTML = contactsHtml;
                    
                    viewGroupModal.show();
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error loading group details', 'error');
            });
        }

        // Send SMS to group
        function sendToGroup(id) {
            if (id) {
                window.location.href = 'bulk-sms.php?group_id=' + id;
            } else if (currentGroupId) {
                window.location.href = 'bulk-sms.php?group_id=' + currentGroupId;
            }
        }

        // Send SMS to individual contact
        function sendToContact(phone) {
            window.location.href = 'send-sms.php?phone=' + encodeURIComponent(phone);
        }

        // Open delete confirmation modal
        function openDeleteModal(id, name, contactCount) {
            deleteId = id;
            document.getElementById('deleteGroupName').textContent = name;
            
            if (contactCount > 0) {
                document.getElementById('deleteWarning').textContent = 
                    `This group has ${contactCount} contact(s). They will be moved to "No Group".`;
            } else {
                document.getElementById('deleteWarning').textContent = '';
            }
            
            deleteModal.show();
        }

        // Confirm delete
        function confirmDelete() {
            if (!deleteId) return;
            
            const deleteBtn = document.getElementById('deleteBtn');
            deleteBtn.disabled = true;
            deleteBtn.innerHTML = '<span class="loading-spinner me-2"></span> Deleting...';
            
            const formData = new FormData();
            formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
            formData.append('action', 'delete');
            formData.append('group_id', deleteId);
            
            fetch('../ajax/groups_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showToast(data.message, 'success');
                    deleteModal.hide();
                    loadGroups(currentPage);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error deleting group', 'error');
            })
            .finally(() => {
                deleteBtn.disabled = false;
                deleteBtn.innerHTML = 'Delete Group';
                deleteId = null;
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
    </script>
</body>
</html>