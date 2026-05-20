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
    <title>Message Templates - <?php echo APP_NAME; ?></title>
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

        .filter-section {
            background-color: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .template-card {
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            padding: 20px;
            transition: all 0.2s ease;
            height: 100%;
            position: relative;
            display: flex;
            flex-direction: column;
        }

        .template-card:hover {
            border-color: #1e3a8a;
            box-shadow: 0 4px 12px rgba(30, 58, 138, 0.1);
            transform: translateY(-2px);
        }

        .template-category {
            display: inline-block;
            padding: 4px 12px;
            background-color: #f8f9fa;
            border-radius: 20px;
            font-size: 11px;
            color: #666666;
            margin-bottom: 10px;
            align-self: flex-start;
        }

        .template-name {
            font-weight: 600;
            color: #333333;
            font-size: 16px;
            margin-bottom: 10px;
            word-break: break-word;
        }

        .template-preview {
            color: #666666;
            font-size: 13px;
            margin-bottom: 15px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 8px;
            max-height: 100px;
            overflow: hidden;
            position: relative;
            flex-grow: 1;
        }

        .template-preview::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 30px;
            background: linear-gradient(transparent, #f8f9fa);
            pointer-events: none;
        }

        .template-stats {
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
            font-size: 16px;
        }

        .stat-label {
            font-size: 10px;
            color: #666666;
            text-transform: uppercase;
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

        .variable-tag {
            display: inline-block;
            padding: 4px 8px;
            background-color: #e9ecef;
            border-radius: 4px;
            font-size: 11px;
            color: #495057;
            margin: 2px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .variable-tag:hover {
            background-color: #1e3a8a;
            color: white;
        }

        .preview-box {
            background-color: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            margin-top: 10px;
            white-space: pre-wrap;
            font-size: 14px;
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

            .template-stats {
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
                    <h2><i class="bi bi-file-text me-2"></i>Message Templates</h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="send-sms.php">Send SMS</a></li>
                            <li class="breadcrumb-item active">Templates</li>
                        </ol>
                    </nav>
                </div>
                <div>
                    <button class="btn btn-primary" onclick="openAddModal()">
                        <i class="bi bi-plus-lg"></i> Create Template
                    </button>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-section">
            <div class="row g-3">
                <div class="col-md-4">
                    <select id="categoryFilter" class="form-select">
                        <option value="all">All Categories</option>
                        <option value="general">General</option>
                        <option value="marketing">Marketing</option>
                        <option value="notification">Notification</option>
                        <option value="reminder">Reminder</option>
                        <option value="otp">OTP/Verification</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <input type="text" id="searchInput" class="form-control" placeholder="Search templates by name or content...">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary w-100" onclick="loadTemplates(1)">
                        <i class="bi bi-search"></i> Filter
                    </button>
                </div>
            </div>
        </div>

        <!-- Loading Indicator -->
        <div id="loadingIndicator" class="text-center py-5" style="display: none;">
            <div class="loading-spinner" style="width: 40px; height: 40px;"></div>
            <p class="mt-3 text-muted">Loading templates...</p>
        </div>

        <!-- Templates Grid -->
        <div id="templatesGrid">
            <div class="row" id="templatesContainer">
                <!-- Templates will be loaded here via AJAX -->
                <div class="col-12 text-center py-5">
                    <div class="loading-spinner" style="width: 40px; height: 40px;"></div>
                    <p class="mt-3 text-muted">Loading templates...</p>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <nav id="paginationContainer" class="mt-3" style="display: none;">
            <ul class="pagination" id="pagination"></ul>
        </nav>

        <!-- Variables Guide -->
        <div class="card mt-4">
            <div class="card-header">
                <i class="bi bi-info-circle"></i> Available Variables
            </div>
            <div class="card-body">
                <p>Use these variables in your templates to personalize messages:</p>
                <div class="row">
                    <div class="col-md-3">
                        <span class="variable-tag" onclick="insertVariable('{contact_name}')">{contact_name}</span>
                        <small class="d-block text-muted">Contact's name</small>
                    </div>
                    <div class="col-md-3">
                        <span class="variable-tag" onclick="insertVariable('{contact_phone}')">{contact_phone}</span>
                        <small class="d-block text-muted">Contact's phone</small>
                    </div>
                    <div class="col-md-3">
                        <span class="variable-tag" onclick="insertVariable('{company_name}')">{company_name}</span>
                        <small class="d-block text-muted">Your company name</small>
                    </div>
                    <div class="col-md-3">
                        <span class="variable-tag" onclick="insertVariable('{date}')">{date}</span>
                        <small class="d-block text-muted">Current date</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Template Modal -->
    <div class="modal fade" id="templateModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Create Template</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="templateForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" id="formAction" value="add">
                        <input type="hidden" name="template_id" id="templateId">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Template Name *</label>
                                <input type="text" name="name" id="templateName" class="form-control" required maxlength="100">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Category</label>
                                <select name="category" id="templateCategory" class="form-select">
                                    <option value="general">General</option>
                                    <option value="marketing">Marketing</option>
                                    <option value="notification">Notification</option>
                                    <option value="reminder">Reminder</option>
                                    <option value="otp">OTP/Verification</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Message *</label>
                            <textarea name="message" id="templateMessage" class="form-control" rows="6" required maxlength="1600"></textarea>
                            <div class="d-flex justify-content-between mt-1">
                                <small class="text-muted">
                                    <span id="charCount">0</span>/1600 characters | 
                                    <span id="smsCount">0</span> SMS parts
                                </small>
                                <small class="text-muted">
                                    <i class="bi bi-info-circle"></i> Use variables like {contact_name}
                                </small>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Quick Insert</label>
                            <div>
                                <span class="variable-tag" onclick="insertTemplateVariable('{contact_name}')">{contact_name}</span>
                                <span class="variable-tag" onclick="insertTemplateVariable('{contact_phone}')">{contact_phone}</span>
                                <span class="variable-tag" onclick="insertTemplateVariable('{company_name}')">{company_name}</span>
                                <span class="variable-tag" onclick="insertTemplateVariable('{date}')">{date}</span>
                            </div>
                        </div>
                        
                        <!-- Live Preview -->
                        <div class="mb-3">
                            <label class="form-label">Preview</label>
                            <div class="preview-box" id="livePreview">
                                <div id="previewContent">Enter a message to see preview</div>
                                <hr class="my-2">
                                <div class="d-flex justify-content-between small text-muted">
                                    <span>Characters: <span id="previewChars">0</span></span>
                                    <span>SMS Parts: <span id="previewParts">0</span></span>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveTemplate()" id="saveBtn">Create Template</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Preview Template Modal -->
    <div class="modal fade" id="previewModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Template Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="previewModalContent"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="useTemplateFromPreview()" id="useTemplateBtn">Use Template</button>
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
                    <p>Are you sure you want to delete the template <strong id="deleteTemplateName"></strong>?</p>
                    <p class="text-danger">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" onclick="confirmDelete()" id="deleteBtn">Delete Template</button>
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
        let previewId = null;
        let templateModal = null;
        let previewModal = null;
        let deleteModal = null;

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize modals
            templateModal = new bootstrap.Modal(document.getElementById('templateModal'));
            previewModal = new bootstrap.Modal(document.getElementById('previewModal'));
            deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            
            // Load templates
            loadTemplates(1);
            
            // Add event listeners
            document.getElementById('categoryFilter').addEventListener('change', () => loadTemplates(1));
            document.getElementById('searchInput').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    loadTemplates(1);
                }
            });
            
            // Add live preview listener
            const messageInput = document.getElementById('templateMessage');
            if (messageInput) {
                messageInput.addEventListener('input', updateLivePreview);
            }
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

        // Load templates via AJAX
        function loadTemplates(page) {
            currentPage = page;
            
            const category = document.getElementById('categoryFilter').value;
            const search = document.getElementById('searchInput').value;
            
            // Show loading
            document.getElementById('loadingIndicator').style.display = 'block';
            document.getElementById('templatesContainer').innerHTML = '';
            document.getElementById('paginationContainer').style.display = 'none';
            
            const formData = new FormData();
            formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
            formData.append('action', 'get_templates');
            formData.append('page', page);
            formData.append('category', category);
            formData.append('search', search);
            
            fetch('../ajax/templates_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('loadingIndicator').style.display = 'none';
                
                if (data.status === 'success') {
                    displayTemplates(data.data.templates);
                    setupPagination(data.data.page, data.data.total_pages);
                    
                    // Update category filter with available categories
                    updateCategoryFilter(data.data.categories);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('loadingIndicator').style.display = 'none';
                showToast('Error loading templates', 'error');
            });
        }

        // Update category filter with available categories
        function updateCategoryFilter(categories) {
            const filter = document.getElementById('categoryFilter');
            const currentValue = filter.value;
            
            // Keep "All Categories" option
            filter.innerHTML = '<option value="all">All Categories</option>';
            
            // Add categories from database
            if (categories && categories.length > 0) {
                categories.forEach(cat => {
                    if (cat) {
                        const option = document.createElement('option');
                        option.value = cat;
                        option.textContent = cat.charAt(0).toUpperCase() + cat.slice(1);
                        filter.appendChild(option);
                    }
                });
            } else {
                // Add default categories
                ['general', 'marketing', 'notification', 'reminder', 'otp'].forEach(cat => {
                    const option = document.createElement('option');
                    option.value = cat;
                    option.textContent = cat.charAt(0).toUpperCase() + cat.slice(1);
                    filter.appendChild(option);
                });
            }
            
            filter.value = currentValue;
        }

        // Display templates in grid
        function displayTemplates(templates) {
            const container = document.getElementById('templatesContainer');
            
            if (templates.length === 0) {
                container.innerHTML = `
                    <div class="col-12 text-center py-5">
                        <i class="bi bi-file-text" style="font-size: 48px; color: #e0e0e0;"></i>
                        <h5 class="mt-3">No Templates Found</h5>
                        <p class="text-muted">Create your first template to save time when sending messages.</p>
                        <button class="btn btn-primary" onclick="openAddModal()">
                            <i class="bi bi-plus-lg"></i> Create Template
                        </button>
                    </div>
                `;
                return;
            }
            
            let html = '';
            templates.forEach(template => {
                const category = template.category || 'general';
                const charCount = template.message.length;
                const smsParts = Math.ceil(charCount > 160 ? charCount / 153 : 1);
                
                html += `
                    <div class="col-md-4 mb-4">
                        <div class="template-card">
                            <span class="template-category">${escapeHtml(category.charAt(0).toUpperCase() + category.slice(1))}</span>
                            <div class="template-name">${escapeHtml(template.name)}</div>
                            <div class="template-preview">${escapeHtml(template.message)}</div>
                            <div class="template-stats">
                                <div class="stat-item">
                                    <div class="stat-value">${charCount}</div>
                                    <div class="stat-label">Chars</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value">${smsParts}</div>
                                    <div class="stat-label">SMS</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value">${new Date(template.created_at).toLocaleDateString().slice(0,5)}</div>
                                    <div class="stat-label">Date</div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between">
                                <div>
                                    <button class="action-btn" onclick="useTemplate(${template.id})" title="Use Template">
                                        <i class="bi bi-send"></i>
                                    </button>
                                    <button class="action-btn" onclick="previewTemplate(${template.id})" title="Preview">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <button class="action-btn" onclick="duplicateTemplate(${template.id})" title="Duplicate">
                                        <i class="bi bi-files"></i>
                                    </button>
                                </div>
                                <div>
                                    <button class="action-btn" onclick="editTemplate(${template.id})" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="action-btn text-danger" onclick="openDeleteModal(${template.id}, '${escapeHtml(template.name)}')" title="Delete">
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
                    <span class="page-link" onclick="${current > 1 ? `loadTemplates(${current - 1})` : ''}">
                        <i class="bi bi-chevron-left"></i>
                    </span>
                </li>
            `;
            
            // Page numbers
            let start = Math.max(1, current - 2);
            let end = Math.min(total, current + 2);
            
            if (start > 1) {
                html += `<li class="page-item"><span class="page-link" onclick="loadTemplates(1)">1</span></li>`;
                if (start > 2) {
                    html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
                }
            }
            
            for (let i = start; i <= end; i++) {
                html += `
                    <li class="page-item ${i === current ? 'active' : ''}">
                        <span class="page-link" onclick="loadTemplates(${i})">${i}</span>
                    </li>
                `;
            }
            
            if (end < total) {
                if (end < total - 1) {
                    html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
                }
                html += `<li class="page-item"><span class="page-link" onclick="loadTemplates(${total})">${total}</span></li>`;
            }
            
            // Next button
            html += `
                <li class="page-item ${current >= total ? 'disabled' : ''}">
                    <span class="page-link" onclick="${current < total ? `loadTemplates(${current + 1})` : ''}">
                        <i class="bi bi-chevron-right"></i>
                    </span>
                </li>
            `;
            
            document.getElementById('pagination').innerHTML = html;
        }

        // Update live preview
        function updateLivePreview() {
            const message = document.getElementById('templateMessage').value;
            const charCount = message.length;
            const smsParts = Math.ceil(charCount > 160 ? charCount / 153 : 1);
            
            document.getElementById('charCount').textContent = charCount;
            document.getElementById('smsCount').textContent = smsParts;
            
            // Replace variables with sample data for preview
            let preview = message
                .replace(/{contact_name}/g, 'John Doe')
                .replace(/{contact_phone}/g, '254712345678')
                .replace(/{company_name}/g, '<?php echo APP_NAME; ?>')
                .replace(/{date}/g, new Date().toISOString().slice(0,10));
            
            document.getElementById('previewContent').textContent = preview || 'Enter a message to see preview';
            document.getElementById('previewChars').textContent = preview.length;
            document.getElementById('previewParts').textContent = Math.ceil(preview.length > 160 ? preview.length / 153 : 1);
        }

        // Open add template modal
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Create New Template';
            document.getElementById('formAction').value = 'add';
            document.getElementById('templateId').value = '';
            document.getElementById('templateName').value = '';
            document.getElementById('templateMessage').value = '';
            document.getElementById('templateCategory').value = 'general';
            document.getElementById('saveBtn').textContent = 'Create Template';
            updateLivePreview();
            templateModal.show();
        }

        // Edit template
        function editTemplate(id) {
            const formData = new FormData();
            formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
            formData.append('action', 'get_template');
            formData.append('template_id', id);
            
            fetch('../ajax/templates_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    const template = data.data.template;
                    document.getElementById('modalTitle').textContent = 'Edit Template';
                    document.getElementById('formAction').value = 'edit';
                    document.getElementById('templateId').value = template.id;
                    document.getElementById('templateName').value = template.name;
                    document.getElementById('templateMessage').value = template.message;
                    document.getElementById('templateCategory').value = template.category || 'general';
                    document.getElementById('saveBtn').textContent = 'Update Template';
                    updateLivePreview();
                    templateModal.show();
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error loading template', 'error');
            });
        }

        // Save template (add or edit)
        function saveTemplate() {
            const form = document.getElementById('templateForm');
            const formData = new FormData(form);
            
            // Validate
            const name = formData.get('name').trim();
            const message = formData.get('message').trim();
            
            if (!name) {
                showToast('Template name is required', 'error');
                return;
            }
            
            if (!message) {
                showToast('Message content is required', 'error');
                return;
            }
            
            // Disable save button
            const saveBtn = document.getElementById('saveBtn');
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<span class="loading-spinner me-2"></span> Saving...';
            
            fetch('../ajax/templates_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showToast(data.message, 'success');
                    templateModal.hide();
                    loadTemplates(currentPage);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error saving template', 'error');
            })
            .finally(() => {
                saveBtn.disabled = false;
                saveBtn.innerHTML = formData.get('action') === 'add' ? 'Create Template' : 'Update Template';
            });
        }

        // Use template
        function useTemplate(id) {
            window.location.href = 'send-sms.php?template_id=' + id;
        }

        // Preview template
        function previewTemplate(id) {
            const formData = new FormData();
            formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
            formData.append('action', 'get_template');
            formData.append('template_id', id);
            
            fetch('../ajax/templates_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    const template = data.data.template;
                    previewId = template.id;
                    
                    // Replace variables with sample data
                    let preview = template.message
                        .replace(/{contact_name}/g, 'John Doe')
                        .replace(/{contact_phone}/g, '254712345678')
                        .replace(/{company_name}/g, '<?php echo APP_NAME; ?>')
                        .replace(/{date}/g, new Date().toISOString().slice(0,10));
                    
                    const charCount = preview.length;
                    const smsParts = Math.ceil(charCount > 160 ? charCount / 153 : 1);
                    
                    document.getElementById('previewModalContent').innerHTML = `
                        <h6>${escapeHtml(template.name)}</h6>
                        <div class="template-category mt-2">${escapeHtml(template.category || 'general')}</div>
                        <div class="preview-box mt-3">${escapeHtml(preview)}</div>
                        <div class="d-flex justify-content-between mt-3 text-muted small">
                            <span>Characters: ${charCount}</span>
                            <span>SMS Parts: ${smsParts}</span>
                        </div>
                    `;
                    
                    previewModal.show();
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error previewing template', 'error');
            });
        }

        // Use template from preview modal
        function useTemplateFromPreview() {
            if (previewId) {
                previewModal.hide();
                useTemplate(previewId);
            }
        }

        // Duplicate template
        function duplicateTemplate(id) {
            const formData = new FormData();
            formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
            formData.append('action', 'duplicate');
            formData.append('template_id', id);
            
            fetch('../ajax/templates_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showToast(data.message, 'success');
                    loadTemplates(currentPage);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error duplicating template', 'error');
            });
        }

        // Insert variable into template message
        function insertTemplateVariable(variable) {
            const textarea = document.getElementById('templateMessage');
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const text = textarea.value;
            
            textarea.value = text.substring(0, start) + variable + text.substring(end);
            textarea.focus();
            textarea.setSelectionRange(start + variable.length, start + variable.length);
            
            updateLivePreview();
        }

        // Insert variable (for the bottom guide)
        function insertVariable(variable) {
            if (document.getElementById('templateMessage')) {
                insertTemplateVariable(variable);
            } else {
                // If modal is not open, show a message
                showToast('Open the template editor to insert variables', 'info');
            }
        }

        // Open delete confirmation modal
        function openDeleteModal(id, name) {
            deleteId = id;
            document.getElementById('deleteTemplateName').textContent = name;
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
            formData.append('template_id', deleteId);
            
            fetch('../ajax/templates_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showToast(data.message, 'success');
                    deleteModal.hide();
                    loadTemplates(currentPage);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error deleting template', 'error');
            })
            .finally(() => {
                deleteBtn.disabled = false;
                deleteBtn.innerHTML = 'Delete Template';
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