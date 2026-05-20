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

// Get groups for filter (initial load)
try {
    $stmt = $pdo->prepare("SELECT * FROM contact_groups WHERE user_id = ? ORDER BY name");
    $stmt->execute([$user_id]);
    $groups = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching groups: " . $e->getMessage());
    $groups = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contacts - <?php echo APP_NAME; ?></title>
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

        .contact-card {
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            padding: 20px;
            transition: all 0.2s ease;
            height: 100%;
            position: relative;
        }

        .contact-card:hover {
            border-color: #1e3a8a;
            box-shadow: 0 4px 12px rgba(30, 58, 138, 0.1);
            transform: translateY(-2px);
        }

        .contact-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: #1e3a8a;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .contact-name {
            font-weight: 600;
            color: #333333;
            font-size: 18px;
            margin-bottom: 5px;
            word-break: break-word;
        }

        .contact-phone {
            color: #1e3a8a;
            font-size: 14px;
            margin-bottom: 5px;
            word-break: break-word;
        }

        .contact-email {
            color: #666666;
            font-size: 13px;
            margin-bottom: 10px;
            word-break: break-word;
        }

        .contact-group {
            display: inline-block;
            padding: 4px 12px;
            background-color: #f8f9fa;
            border-radius: 20px;
            font-size: 12px;
            color: #666666;
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

        .upload-area {
            border: 2px dashed #e0e0e0;
            border-radius: 12px;
            padding: 40px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .upload-area:hover {
            border-color: #1e3a8a;
            background-color: #f8f9fa;
        }

        .upload-area i {
            font-size: 48px;
            color: #1e3a8a;
            opacity: 0.5;
            margin-bottom: 15px;
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
                    <h2><i class="bi bi-person-lines-fill me-2"></i>Contacts</h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Contacts</li>
                        </ol>
                    </nav>
                </div>
                <div>
                    <button class="btn btn-outline-primary me-2" data-bs-toggle="modal" data-bs-target="#importModal">
                        <i class="bi bi-upload"></i> Import
                    </button>
                    <button class="btn btn-primary" onclick="openAddModal()">
                        <i class="bi bi-plus-lg"></i> Add Contact
                    </button>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-section">
            <div class="row g-3">
                <div class="col-md-4">
                    <select id="groupFilter" class="form-select">
                        <option value="0">All Groups</option>
                        <?php foreach ($groups as $group): ?>
                            <option value="<?php echo $group['id']; ?>">
                                <?php echo htmlspecialchars($group['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <input type="text" id="searchInput" class="form-control" placeholder="Search by name, phone, or email...">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary w-100" onclick="loadContacts(1)">
                        <i class="bi bi-search"></i> Filter
                    </button>
                </div>
            </div>
        </div>

        <!-- Contacts Grid/Table Toggle -->
        <ul class="nav nav-tabs mb-3" id="viewToggle">
            <li class="nav-item">
                <button class="nav-link active" id="grid-view-btn" onclick="toggleView('grid')">
                    <i class="bi bi-grid-3x3-gap-fill"></i> Grid View
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="list-view-btn" onclick="toggleView('list')">
                    <i class="bi bi-list-ul"></i> List View
                </button>
            </li>
        </ul>

        <!-- Loading Indicator -->
        <div id="loadingIndicator" class="text-center py-5" style="display: none;">
            <div class="loading-spinner" style="width: 40px; height: 40px;"></div>
            <p class="mt-3 text-muted">Loading contacts...</p>
        </div>

        <!-- Contacts Grid View -->
        <div id="grid-view">
            <div class="row" id="contactsGrid">
                <!-- Contacts will be loaded here via AJAX -->
                <div class="col-12 text-center py-5">
                    <div class="loading-spinner" style="width: 40px; height: 40px;"></div>
                    <p class="mt-3 text-muted">Loading contacts...</p>
                </div>
            </div>
        </div>

        <!-- Contacts List View (Hidden by default) -->
        <div id="list-view" style="display: none;">
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Group</th>
                            <th>Added</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="contactsTableBody">
                        <!-- Contacts will be loaded here via AJAX -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <nav id="paginationContainer" class="mt-3" style="display: none;">
            <ul class="pagination" id="pagination"></ul>
        </nav>
    </div>

    <!-- Add/Edit Contact Modal -->
    <div class="modal fade" id="contactModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Add New Contact</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="contactForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" id="formAction" value="add">
                        <input type="hidden" name="contact_id" id="contactId">
                        
                        <div class="mb-3">
                            <label class="form-label">Name *</label>
                            <input type="text" name="name" id="contactName" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone Number *</label>
                            <input type="tel" name="phone" id="contactPhone" class="form-control" placeholder="254712345678" required>
                            <small class="text-muted">International format (e.g., 254712345678)</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" id="contactEmail" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Group</label>
                            <select name="group_id" id="contactGroup" class="form-select">
                                <option value="">No Group</option>
                                <?php foreach ($groups as $group): ?>
                                    <option value="<?php echo $group['id']; ?>">
                                        <?php echo htmlspecialchars($group['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveContact()" id="saveBtn">Save Contact</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Import Contacts Modal -->
    <div class="modal fade" id="importModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Import Contacts</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="importForm" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="import">
                        
                        <div class="upload-area" id="importUploadArea">
                            <input type="file" name="csv_file" id="csvFile" accept=".csv" style="display: none;" required>
                            <i class="bi bi-cloud-upload"></i>
                            <h6>Click to upload or drag and drop</h6>
                            <p class="text-muted small">CSV file with name, phone, email (optional)</p>
                        </div>
                        <div class="mt-3">
                            <h6>Sample CSV Format:</h6>
                            <pre class="bg-light p-2 rounded small">name,phone,email
John Doe,254712345678,john@example.com
Jane Smith,254798765432,jane@example.com</pre>
                        </div>
                        <div class="alert alert-info mt-3">
                            <i class="bi bi-info-circle"></i>
                            Max file size: 2MB. Duplicate phone numbers will be skipped.
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="importContacts()" id="importBtn">Import</button>
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
                    <p>Are you sure you want to delete <strong id="deleteContactName"></strong>?</p>
                    <p class="text-danger">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" onclick="confirmDelete()" id="deleteBtn">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Global variables
        let currentPage = 1;
        let totalPages = 1;
        let currentView = localStorage.getItem('contactsView') || 'grid';
        let deleteId = null;
        let contactModal = null;
        let importModal = null;
        let deleteModal = null;

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize modals
            contactModal = new bootstrap.Modal(document.getElementById('contactModal'));
            importModal = new bootstrap.Modal(document.getElementById('importModal'));
            deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            
            // Set initial view
            toggleView(currentView);
            
            // Load contacts
            loadContacts(1);
            
            // Add event listeners
            document.getElementById('groupFilter').addEventListener('change', () => loadContacts(1));
            document.getElementById('searchInput').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    loadContacts(1);
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

        // Load contacts via AJAX
        function loadContacts(page) {
            currentPage = page;
            
            const groupFilter = document.getElementById('groupFilter').value;
            const search = document.getElementById('searchInput').value;
            
            // Show loading
            document.getElementById('loadingIndicator').style.display = 'block';
            document.getElementById('contactsGrid').innerHTML = '';
            document.getElementById('contactsTableBody').innerHTML = '';
            document.getElementById('paginationContainer').style.display = 'none';
            
            const formData = new FormData();
            formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
            formData.append('action', 'get_contacts');
            formData.append('page', page);
            formData.append('group_id', groupFilter);
            formData.append('search', search);
            
            fetch('../ajax/contacts_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('loadingIndicator').style.display = 'none';
                
                if (data.status === 'success') {
                    displayContacts(data.data.contacts);
                    setupPagination(data.data.page, data.data.total_pages);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('loadingIndicator').style.display = 'none';
                showToast('Error loading contacts', 'error');
            });
        }

        // Display contacts in current view
        function displayContacts(contacts) {
            if (currentView === 'grid') {
                displayGrid(contacts);
            } else {
                displayList(contacts);
            }
        }

        // Display grid view
        function displayGrid(contacts) {
            const grid = document.getElementById('contactsGrid');
            
            if (contacts.length === 0) {
                grid.innerHTML = `
                    <div class="col-12 text-center py-5">
                        <i class="bi bi-person-lines-fill" style="font-size: 48px; color: #e0e0e0;"></i>
                        <h5 class="mt-3">No Contacts Found</h5>
                        <p class="text-muted">Add your first contact to get started.</p>
                        <button class="btn btn-primary" onclick="openAddModal()">
                            <i class="bi bi-plus-lg"></i> Add Contact
                        </button>
                    </div>
                `;
                return;
            }
            
            let html = '';
            contacts.forEach(contact => {
                const avatar = (contact.name || contact.phone).charAt(0).toUpperCase();
                const name = contact.name || 'Unnamed';
                const groupName = contact.group_name ? `<span class="contact-group">${escapeHtml(contact.group_name)}</span>` : '';
                
                html += `
                    <div class="col-md-4 col-lg-3 mb-4">
                        <div class="contact-card">
                            <div class="contact-avatar">${avatar}</div>
                            <div class="contact-name">${escapeHtml(name)}</div>
                            <div class="contact-phone">${escapeHtml(contact.phone)}</div>
                            ${contact.email ? `<div class="contact-email">${escapeHtml(contact.email)}</div>` : ''}
                            ${groupName}
                            <div class="mt-3">
                                <button class="action-btn" onclick="editContact(${contact.id})" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="action-btn" onclick="sendToContact('${contact.phone}')" title="Send SMS">
                                    <i class="bi bi-envelope"></i>
                                </button>
                                <button class="action-btn" onclick="openDeleteModal(${contact.id}, '${escapeHtml(name)}')" title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            grid.innerHTML = html;
        }

        // Display list view
        function displayList(contacts) {
            const tbody = document.getElementById('contactsTableBody');
            
            if (contacts.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" class="text-center py-5">
                            <i class="bi bi-inbox" style="font-size: 48px; color: #e0e0e0;"></i>
                            <p class="mt-3 text-muted">No contacts found</p>
                        </td>
                    </tr>
                `;
                return;
            }
            
            let html = '';
            contacts.forEach(contact => {
                const name = contact.name || 'Unnamed';
                const groupName = contact.group_name ? `<span class="badge bg-light text-dark">${escapeHtml(contact.group_name)}</span>` : '-';
                const added = new Date(contact.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                
                html += `
                    <tr>
                        <td data-label="Name">${escapeHtml(name)}</td>
                        <td data-label="Phone">${escapeHtml(contact.phone)}</td>
                        <td data-label="Email">${contact.email ? escapeHtml(contact.email) : '-'}</td>
                        <td data-label="Group">${groupName}</td>
                        <td data-label="Added">${added}</td>
                        <td data-label="Actions">
                            <button class="action-btn" onclick="editContact(${contact.id})" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="action-btn" onclick="sendToContact('${contact.phone}')" title="Send SMS">
                                <i class="bi bi-envelope"></i>
                            </button>
                            <button class="action-btn" onclick="openDeleteModal(${contact.id}, '${escapeHtml(name)}')" title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
            
            tbody.innerHTML = html;
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
                    <span class="page-link" onclick="${current > 1 ? `loadContacts(${current - 1})` : ''}">
                        <i class="bi bi-chevron-left"></i>
                    </span>
                </li>
            `;
            
            // Page numbers
            let start = Math.max(1, current - 2);
            let end = Math.min(total, current + 2);
            
            if (start > 1) {
                html += `<li class="page-item"><span class="page-link" onclick="loadContacts(1)">1</span></li>`;
                if (start > 2) {
                    html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
                }
            }
            
            for (let i = start; i <= end; i++) {
                html += `
                    <li class="page-item ${i === current ? 'active' : ''}">
                        <span class="page-link" onclick="loadContacts(${i})">${i}</span>
                    </li>
                `;
            }
            
            if (end < total) {
                if (end < total - 1) {
                    html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
                }
                html += `<li class="page-item"><span class="page-link" onclick="loadContacts(${total})">${total}</span></li>`;
            }
            
            // Next button
            html += `
                <li class="page-item ${current >= total ? 'disabled' : ''}">
                    <span class="page-link" onclick="${current < total ? `loadContacts(${current + 1})` : ''}">
                        <i class="bi bi-chevron-right"></i>
                    </span>
                </li>
            `;
            
            document.getElementById('pagination').innerHTML = html;
        }

        // Toggle view
        function toggleView(view) {
            currentView = view;
            
            const gridView = document.getElementById('grid-view');
            const listView = document.getElementById('list-view');
            const gridBtn = document.getElementById('grid-view-btn');
            const listBtn = document.getElementById('list-view-btn');
            
            if (view === 'grid') {
                gridView.style.display = 'block';
                listView.style.display = 'none';
                gridBtn.classList.add('active');
                listBtn.classList.remove('active');
            } else {
                gridView.style.display = 'none';
                listView.style.display = 'block';
                gridBtn.classList.remove('active');
                listBtn.classList.add('active');
            }
            
            localStorage.setItem('contactsView', view);
            
            // Reload contacts to ensure proper display
            loadContacts(currentPage);
        }

        // Open add contact modal
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Add New Contact';
            document.getElementById('formAction').value = 'add';
            document.getElementById('contactId').value = '';
            document.getElementById('contactName').value = '';
            document.getElementById('contactPhone').value = '';
            document.getElementById('contactEmail').value = '';
            document.getElementById('contactGroup').value = '';
            document.getElementById('saveBtn').textContent = 'Add Contact';
            contactModal.show();
        }

        // Edit contact
        function editContact(id) {
            const formData = new FormData();
            formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
            formData.append('action', 'get');
            formData.append('contact_id', id);
            
            fetch('../ajax/contacts_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    const contact = data.data.contact;
                    document.getElementById('modalTitle').textContent = 'Edit Contact';
                    document.getElementById('formAction').value = 'edit';
                    document.getElementById('contactId').value = contact.id;
                    document.getElementById('contactName').value = contact.name || '';
                    document.getElementById('contactPhone').value = contact.phone;
                    document.getElementById('contactEmail').value = contact.email || '';
                    document.getElementById('contactGroup').value = contact.group_id || '';
                    document.getElementById('saveBtn').textContent = 'Update Contact';
                    contactModal.show();
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error loading contact', 'error');
            });
        }

        // Save contact (add or edit)
        function saveContact() {
            const form = document.getElementById('contactForm');
            const formData = new FormData(form);
            
            // Validate
            const name = formData.get('name').trim();
            const phone = formData.get('phone').trim();
            
            if (!name || !phone) {
                showToast('Please fill in all required fields', 'error');
                return;
            }
            
            // Disable save button
            const saveBtn = document.getElementById('saveBtn');
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<span class="loading-spinner me-2"></span> Saving...';
            
            fetch('../ajax/contacts_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showToast(data.message, 'success');
                    contactModal.hide();
                    loadContacts(currentPage);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error saving contact', 'error');
            })
            .finally(() => {
                saveBtn.disabled = false;
                saveBtn.innerHTML = formData.get('action') === 'add' ? 'Add Contact' : 'Update Contact';
            });
        }

        // Open delete confirmation modal
        function openDeleteModal(id, name) {
            deleteId = id;
            document.getElementById('deleteContactName').textContent = name;
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
            formData.append('contact_id', deleteId);
            
            fetch('../ajax/contacts_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showToast(data.message, 'success');
                    deleteModal.hide();
                    loadContacts(currentPage);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error deleting contact', 'error');
            })
            .finally(() => {
                deleteBtn.disabled = false;
                deleteBtn.innerHTML = 'Delete';
                deleteId = null;
            });
        }

        // Send SMS to contact
        function sendToContact(phone) {
            window.location.href = 'send-sms.php?phone=' + encodeURIComponent(phone);
        }

        // Import contacts
        function importContacts() {
            const fileInput = document.getElementById('csvFile');
            if (!fileInput.files.length) {
                showToast('Please select a CSV file', 'error');
                return;
            }
            
            const formData = new FormData(document.getElementById('importForm'));
            
            const importBtn = document.getElementById('importBtn');
            importBtn.disabled = true;
            importBtn.innerHTML = '<span class="loading-spinner me-2"></span> Importing...';
            
            fetch('../ajax/contacts_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showToast(data.message, 'success');
                    importModal.hide();
                    loadContacts(1);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error importing contacts', 'error');
            })
            .finally(() => {
                importBtn.disabled = false;
                importBtn.innerHTML = 'Import';
                
                // Reset file input
                document.getElementById('csvFile').value = '';
                document.getElementById('importUploadArea').innerHTML = `
                    <i class="bi bi-cloud-upload"></i>
                    <h6>Click to upload or drag and drop</h6>
                    <p class="text-muted small">CSV file with name, phone, email (optional)</p>
                `;
            });
        }

        // Upload area click
        document.getElementById('importUploadArea')?.addEventListener('click', function() {
            document.getElementById('csvFile').click();
        });

        // File name display
        document.getElementById('csvFile')?.addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name;
            if (fileName) {
                const uploadArea = document.getElementById('importUploadArea');
                uploadArea.innerHTML = `
                    <i class="bi bi-file-earmark-check text-success"></i>
                    <h6>Selected: ${fileName}</h6>
                    <p class="text-muted small">Click to change file</p>
                `;
            }
        });

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