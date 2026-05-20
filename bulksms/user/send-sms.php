<?php
require_once '../config/config.php';
requireLogin();

$user_id = $_SESSION['user_id'];

// Force refresh user data from database
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Get REAL SMS balance from database (in SMS credits, not KES)
$sms_balance = (int)$user['sms_balance']; // This is 99 from your database

// Pricing configuration - customer pays 1 KES per SMS
$price_per_sms = 1.00; // Customer pays 1 KES per SMS credit

// Get message templates
$stmt = $pdo->prepare("SELECT * FROM message_templates WHERE user_id = ? ORDER BY name");
$stmt->execute([$user_id]);
$templates = $stmt->fetchAll();

// Get contacts for quick selection
$stmt = $pdo->prepare("SELECT c.*, g.name as group_name FROM contacts c LEFT JOIN contact_groups g ON c.group_id = g.id WHERE c.user_id = ? ORDER BY c.name LIMIT 20");
$stmt->execute([$user_id]);
$contacts = $stmt->fetchAll();

// Check if EDUSCORE sender ID exists, if not create it
$stmt = $pdo->prepare("SELECT * FROM sender_ids WHERE user_id = ? AND sender_id = 'EDUSCORE'");
$stmt->execute([$user_id]);
$eduscore_sender = $stmt->fetch();

if (!$eduscore_sender) {
    // Create EDUSCORE sender ID for this user
    $stmt = $pdo->prepare("
        INSERT INTO sender_ids (user_id, sender_id, status, is_default) 
        VALUES (?, 'EDUSCORE', 'approved', 1)
    ");
    $stmt->execute([$user_id]);
}

// Get default sender ID (should be EDUSCORE)
$stmt = $pdo->prepare("SELECT * FROM sender_ids WHERE user_id = ? AND is_default = 1");
$stmt->execute([$user_id]);
$default_sender = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send SMS - <?php echo APP_NAME; ?></title>
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
            border-radius: 12px 12px 0 0 !important;
        }

        .card-header i {
            margin-right: 8px;
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

        textarea.form-control {
            height: auto;
            padding: 12px 16px;
        }

        .input-group {
            position: relative;
        }

        .input-group .btn {
            border: 1px solid #e0e0e0;
            background-color: #f8f9fa;
            color: #666666;
        }

        .input-group .btn:hover {
            background-color: #e9ecef;
            color: #1e3a8a;
        }

        .btn-primary {
            background-color: #1e3a8a;
            border: 1px solid #152b63;
            color: #ffffff;
            height: 45px;
            font-weight: 500;
            padding: 0 20px;
            border-radius: 8px;
            transition: all 0.2s ease;
        }

        .btn-primary:hover {
            background-color: #152b63;
            border-color: #0f1f4a;
            transform: translateY(-1px);
        }

        .btn-outline-primary {
            border: 1px solid #1e3a8a;
            color: #1e3a8a;
            background: transparent;
            height: 45px;
            font-weight: 500;
            padding: 0 20px;
            border-radius: 8px;
            transition: all 0.2s ease;
        }

        .btn-outline-primary:hover {
            background-color: #1e3a8a;
            color: #ffffff;
        }

        .btn-light {
            background-color: #f8f9fa;
            border: 1px solid #e0e0e0;
            color: #333333;
            height: 45px;
            font-weight: 500;
            padding: 0 20px;
            border-radius: 8px;
            transition: all 0.2s ease;
        }

        .btn-light:hover {
            background-color: #e9ecef;
            border-color: #ced4da;
        }

        .balance-card {
            background: linear-gradient(135deg, #1e3a8a 0%, #152b63 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
        }

        .balance-card .label {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 5px;
        }

        .balance-card .value {
            font-size: 32px;
            font-weight: 700;
        }

        .balance-card .small {
            font-size: 12px;
            opacity: 0.8;
            margin-top: 5px;
        }

        .balance-card .refresh-btn {
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            border-radius: 30px;
            padding: 8px 15px;
            font-size: 13px;
            transition: all 0.2s ease;
            margin-top: 10px;
        }

        .balance-card .refresh-btn:hover {
            background: rgba(255,255,255,0.3);
            border-color: rgba(255,255,255,0.5);
        }

        .info-card {
            background-color: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .info-card i {
            color: #1e3a8a;
            font-size: 20px;
            margin-bottom: 10px;
        }

        .info-card h6 {
            color: #333333;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .info-card p {
            color: #666666;
            font-size: 13px;
            margin-bottom: 0;
        }

        .character-counter {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background: #f8f9fa;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            color: #666666;
            z-index: 10;
        }

        .sms-preview {
            background-color: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
        }

        .sms-preview .label {
            font-size: 12px;
            color: #666666;
            margin-bottom: 5px;
        }

        .sms-preview .content {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            margin-bottom: 10px;
        }

        .sms-preview .meta {
            font-size: 13px;
            color: #666666;
        }

        .meta i {
            color: #1e3a8a;
            margin-right: 5px;
        }

        .contact-item, .template-item {
            cursor: pointer;
            transition: all 0.2s ease;
            border: 1px solid #e0e0e0;
            margin-bottom: 10px;
            border-radius: 8px;
        }

        .contact-item:hover, .template-item:hover {
            border-color: #1e3a8a;
            background-color: #f8f9fa;
        }

        .contact-item .card-body, .template-item .card-body {
            padding: 12px 15px;
        }

        .contact-item h6, .template-item h6 {
            color: #333333;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .contact-item small, .template-item small {
            color: #666666;
        }

        .badge-group {
            background-color: #e9ecef;
            color: #495057;
            font-size: 11px;
            padding: 3px 8px;
            border-radius: 12px;
        }

        .cost-breakdown {
            background-color: #e9ecef;
            padding: 10px;
            border-radius: 6px;
            margin-top: 10px;
            font-size: 13px;
        }

        .cost-breakdown span {
            font-weight: 600;
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
                    <h2><i class="bi bi-envelope-paper me-2"></i>Send SMS</h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Send SMS</li>
                        </ol>
                    </nav>
                </div>
                <div>
                    <span class="badge bg-primary" style="background-color: #1e3a8a !important; padding: 8px 15px;" id="headerBalance">
                        <i class="bi bi-coin"></i> Balance: <?php echo number_format($sms_balance); ?> SMS
                    </span>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <!-- Compose SMS Card -->
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-pencil-square"></i> Compose New Message
                    </div>
                    <div class="card-body">
                        <form id="sendSMSForm">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Sender ID</label>
                                    <input type="text" class="form-control" value="EDUSCORE" readonly disabled>
                                    <input type="hidden" name="sender_id" value="EDUSCORE">
                                    <small class="text-muted">Fixed sender ID for all messages</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Recipient Phone *</label>
                                    <div class="input-group">
                                        <input type="tel" name="recipient" id="recipient" class="form-control" 
                                               placeholder="254712345678" required
                                               value="<?php echo isset($_GET['phone']) ? htmlspecialchars($_GET['phone']) : ''; ?>">
                                        <button class="btn btn-light" type="button" data-bs-toggle="modal" data-bs-target="#contactsModal">
                                            <i class="bi bi-person-lines-fill"></i>
                                        </button>
                                    </div>
                                    <small class="text-muted">International format (e.g., 254712345678)</small>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Message *</label>
                                <div class="position-relative">
                                    <textarea name="message" id="message" class="form-control" rows="5" 
                                              placeholder="Type your message here..." maxlength="1600" required></textarea>
                                    <div class="character-counter">
                                        <span id="charCount">0</span>/1600 | <span id="smsCount">0</span> SMS
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Schedule (Optional)</label>
                                    <input type="datetime-local" name="schedule_time" class="form-control" id="scheduleTime">
                                </div>
                                <div class="col-md-6 d-flex align-items-end">
                                    <div class="btn-group w-100">
                                        <button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#templatesModal">
                                            <i class="bi bi-file-text"></i> Templates
                                        </button>
                                        <button type="button" class="btn btn-light" id="previewBtn">
                                            <i class="bi bi-eye"></i> Preview
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Cost Breakdown (in SMS credits) -->
                            <div class="cost-breakdown" id="costBreakdown" style="display: none;">
                                <i class="bi bi-info-circle"></i>
                                <span id="costDetails"></span>
                            </div>

                            <!-- SMS Preview -->
                            <div class="sms-preview" id="smsPreview" style="display: none;">
                                <div class="label">Preview</div>
                                <div class="content" id="previewContent"></div>
                                <div class="meta">
                                    <span class="me-3"><i class="bi bi-person"></i> <span id="previewRecipient"></span></span>
                                    <span class="me-3"><i class="bi bi-tag"></i> <span id="previewSender">EDUSCORE</span></span>
                                    <span><i class="bi bi-coin"></i> <span id="previewCost">0</span> SMS credits</span>
                                </div>
                            </div>

                            <hr>

                            <div class="d-flex justify-content-between">
                                <button type="button" class="btn btn-light" onclick="resetForm()">
                                    <i class="bi bi-arrow-counterclockwise"></i> Reset
                                </button>
                                <button type="submit" class="btn btn-primary" id="submitBtn">
                                    <i class="bi bi-send"></i> Send SMS (1 SMS credit per SMS)
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- Balance Card -->
                <div class="balance-card">
                    <div class="label">Available Balance</div>
                    <div class="value" id="currentBalance"><?php echo number_format($sms_balance); ?> SMS</div>
                    <div class="small">1 SMS credit = 1 SMS (160 characters)</div>
                    <a href="topup.php" class="btn btn-light btn-sm mt-3 w-100" style="color: #1e3a8a;">
                        <i class="bi bi-plus-circle"></i> Buy More SMS Credits
                    </a>
                    <button class="refresh-btn w-100 mt-2" onclick="refreshBalance()">
                        <i class="bi bi-arrow-repeat"></i> Refresh Balance
                    </button>
                </div>

                <!-- Quick Info -->
                <div class="info-card">
                    <i class="bi bi-info-circle"></i>
                    <h6>SMS Pricing</h6>
                    <p>• <strong>1 SMS credit = 1 SMS</strong><br>
                       • 160 characters = 1 SMS<br>
                       • Messages >160 chars split into multiple SMS<br>
                       • Each part uses 1 SMS credit<br>
                       • <strong>Current balance: <?php echo number_format($sms_balance); ?> SMS</strong></p>
                </div>

                <div class="info-card">
                    <i class="bi bi-lightbulb"></i>
                    <h6>Tips</h6>
                    <p>• Use international format: 254712345678<br>
                       • Save templates for frequent messages<br>
                       • Schedule messages for later delivery<br>
                       • Check balance before sending bulk messages<br>
                       • 1 SMS credit = 1 message</p>
                </div>

                <!-- Recent Contacts -->
                <?php if (!empty($contacts)): ?>
                <div class="card mt-3">
                    <div class="card-header">
                        <i class="bi bi-person-lines-fill"></i> Recent Contacts
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php foreach (array_slice($contacts, 0, 5) as $contact): ?>
                                <a href="#" class="list-group-item list-group-item-action quick-contact" 
                                   data-phone="<?php echo $contact['phone']; ?>"
                                   data-name="<?php echo htmlspecialchars($contact['name'] ?: $contact['phone']); ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong><?php echo htmlspecialchars($contact['name'] ?: 'Unnamed'); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo $contact['phone']; ?></small>
                                        </div>
                                        <?php if ($contact['group_name']): ?>
                                            <span class="badge-group"><?php echo $contact['group_name']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Contacts Modal -->
    <div class="modal fade" id="contactsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Select Contact</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="text" class="form-control mb-3" id="contactSearch" placeholder="Search contacts...">
                    <div style="max-height: 400px; overflow-y: auto;" id="contactsList">
                        <?php foreach ($contacts as $contact): ?>
                            <div class="contact-item" data-phone="<?php echo $contact['phone']; ?>" data-name="<?php echo htmlspecialchars($contact['name'] ?: $contact['phone']); ?>">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($contact['name'] ?: 'Unnamed'); ?></h6>
                                            <small class="text-muted"><?php echo $contact['phone']; ?></small>
                                        </div>
                                        <?php if ($contact['group_name']): ?>
                                            <span class="badge-group"><?php echo $contact['group_name']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Templates Modal -->
    <div class="modal fade" id="templatesModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Message Templates</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if (empty($templates)): ?>
                        <p class="text-muted text-center py-4">No templates found. <a href="templates.php">Create one now</a></p>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($templates as $template): ?>
                                <div class="col-md-6">
                                    <div class="template-item" data-message="<?php echo htmlspecialchars($template['message']); ?>">
                                        <div class="card-body">
                                            <h6 class="mb-2"><?php echo htmlspecialchars($template['name']); ?></h6>
                                            <small class="text-muted d-block mb-2">
                                                <?php echo htmlspecialchars(substr($template['message'], 0, 100)); ?>...
                                            </small>
                                            <span class="badge bg-light text-dark"><?php echo $template['category']; ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Configuration
        const smsBalance = <?php echo $sms_balance; ?>; // 99 SMS from database

        // DOM Elements
        const messageInput = document.getElementById('message');
        const charCount = document.getElementById('charCount');
        const smsCount = document.getElementById('smsCount');
        const previewBtn = document.getElementById('previewBtn');
        const smsPreview = document.getElementById('smsPreview');
        const previewContent = document.getElementById('previewContent');
        const previewRecipient = document.getElementById('previewRecipient');
        const previewCost = document.getElementById('previewCost');
        const costBreakdown = document.getElementById('costBreakdown');
        const costDetails = document.getElementById('costDetails');
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
                <div id="${toastId}" class="toast ${type}" role="alert" style="min-width: 300px;">
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

        // Refresh balance from database
        function refreshBalance() {
            showToast('Fetching latest balance...', 'info');
            
            fetch('../ajax/get_balance.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const newBalance = data.raw_balance;
                        
                        // Update balance displays
                        if (currentBalanceEl) {
                            currentBalanceEl.textContent = data.balance + ' SMS';
                        }
                        
                        if (headerBalanceEl) {
                            headerBalanceEl.innerHTML = `<i class="bi bi-coin"></i> Balance: ${data.balance} SMS`;
                        }
                        
                        // Update the balance in the info card
                        const infoCard = document.querySelector('.info-card p strong');
                        if (infoCard) {
                            infoCard.textContent = `Current balance: ${data.balance} SMS`;
                        }
                        
                        showToast(`Balance updated: ${data.balance} SMS`, 'success');
                    } else {
                        showToast('Failed to refresh balance', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Failed to refresh balance', 'error');
                });
        }

        // Update SMS info
        function updateSmsInfo() {
            const text = messageInput.value;
            const length = text.length;
            
            charCount.textContent = length;
            
            // Calculate SMS parts
            let parts = 1;
            if (length > 160) {
                parts = Math.ceil(length / 153);
            }
            
            smsCount.textContent = parts;
            
            // Show cost breakdown in SMS credits
            if (length > 0) {
                costBreakdown.style.display = 'block';
                let costText = `This message will use <span>${parts} SMS credit${parts > 1 ? 's' : ''}</span>`;
                if (parts > 1) {
                    costText += ` (${parts} SMS parts)`;
                }
                costDetails.innerHTML = costText;
                
                // Check if user has enough balance
                if (parts > smsBalance) {
                    costDetails.innerHTML += ' - <span class="text-danger">Insufficient balance!</span>';
                }
            } else {
                costBreakdown.style.display = 'none';
            }
            
            // Update preview cost
            previewCost.textContent = parts;
        }

        messageInput.addEventListener('input', updateSmsInfo);

        // Preview button
        previewBtn.addEventListener('click', function() {
            const recipient = document.getElementById('recipient').value;
            const message = messageInput.value;
            
            if (!message) {
                showToast('Please enter a message to preview', 'error');
                return;
            }
            
            previewContent.textContent = message;
            previewRecipient.textContent = recipient || 'Not set';
            smsPreview.style.display = 'block';
        });

        // Quick contact selection
        document.querySelectorAll('.quick-contact').forEach(contact => {
            contact.addEventListener('click', function(e) {
                e.preventDefault();
                document.getElementById('recipient').value = this.dataset.phone;
            });
        });

        // Contact selection from modal
        document.querySelectorAll('.contact-item').forEach(item => {
            item.addEventListener('click', function() {
                document.getElementById('recipient').value = this.dataset.phone;
                bootstrap.Modal.getInstance(document.getElementById('contactsModal')).hide();
            });
        });

        // Template selection
        document.querySelectorAll('.template-item').forEach(item => {
            item.addEventListener('click', function() {
                messageInput.value = this.dataset.message;
                updateSmsInfo();
                bootstrap.Modal.getInstance(document.getElementById('templatesModal')).hide();
            });
        });

        // Contact search
        document.getElementById('contactSearch')?.addEventListener('input', function(e) {
            const search = e.target.value.toLowerCase();
            document.querySelectorAll('.contact-item').forEach(item => {
                const name = (item.dataset.name || '').toLowerCase();
                const phone = item.dataset.phone.toLowerCase();
                if (name.includes(search) || phone.includes(search)) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        });

        // Form submission
        document.getElementById('sendSMSForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const recipient = document.getElementById('recipient').value;
            const message = messageInput.value;
            
            if (!recipient || !message) {
                showToast('Please fill in all required fields', 'error');
                return;
            }
            
            // Validate phone number
            const phoneRegex = /^[0-9]{10,15}$/;
            if (!phoneRegex.test(recipient.replace(/\D/g, ''))) {
                showToast('Invalid phone number format. Use international format (e.g., 254712345678)', 'error');
                return;
            }
            
            // Calculate SMS parts needed
            const parts = Math.ceil(message.length > 160 ? message.length / 153 : 1);
            
            if (parts > smsBalance) {
                showToast(`Insufficient balance. You need ${parts} SMS credits but have ${smsBalance}.`, 'error');
                return;
            }
            
            // Check if scheduled
            const scheduleTime = document.getElementById('scheduleTime').value;
            if (scheduleTime) {
                const selectedDate = new Date(scheduleTime);
                const now = new Date();
                if (selectedDate <= now) {
                    showToast('Schedule time must be in the future', 'error');
                    return;
                }
            }
            
            // Submit form via AJAX
            const formData = new FormData(this);
            const submitBtn = document.getElementById('submitBtn');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Sending...';
            
            fetch('../ajax/send_sms_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.status === 'success') {
                    showToast(data.message, 'success');
                    resetForm();
                    
                    // Update balance display
                    if (data.data && data.data.new_balance) {
                        const newBalance = data.data.new_balance;
                        if (currentBalanceEl) {
                            currentBalanceEl.textContent = newBalance + ' SMS';
                        }
                        if (headerBalanceEl) {
                            headerBalanceEl.innerHTML = `<i class="bi bi-coin"></i> Balance: ${newBalance} SMS`;
                        }
                    }
                    
                    // Refresh balance after 2 seconds
                    setTimeout(refreshBalance, 2000);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('An error occurred. Please try again.', 'error');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
        });

        // Reset form
        function resetForm() {
            document.getElementById('sendSMSForm').reset();
            messageInput.value = '';
            updateSmsInfo();
            smsPreview.style.display = 'none';
            costBreakdown.style.display = 'none';
        }

        // Check for phone parameter in URL
        const urlParams = new URLSearchParams(window.location.search);
        const phoneParam = urlParams.get('phone');
        if (phoneParam) {
            document.getElementById('recipient').value = phoneParam;
        }

        // Initialize with empty message
        updateSmsInfo();

        // Refresh balance on page load
        setTimeout(refreshBalance, 1000);
    </script>
</body>
</html>