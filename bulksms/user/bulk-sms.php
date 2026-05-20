<?php
require_once '../config/config.php';
requireLogin();

$user_id = $_SESSION['user_id'];

// Get user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Get user's sender IDs
$stmt = $pdo->prepare("SELECT * FROM sender_ids WHERE user_id = ? AND status = 'approved'");
$stmt->execute([$user_id]);
$sender_ids = $stmt->fetchAll();

// Get default sender ID
$stmt = $pdo->prepare("SELECT * FROM sender_ids WHERE user_id = ? AND is_default = 1");
$stmt->execute([$user_id]);
$default_sender = $stmt->fetch();

// Get contact groups
$stmt = $pdo->prepare("SELECT * FROM contact_groups WHERE user_id = ?");
$stmt->execute([$user_id]);
$groups = $stmt->fetchAll();

// Get recent campaigns
$stmt = $pdo->prepare("SELECT * FROM sms_messages WHERE user_id = ? AND recipient LIKE '%,%' GROUP BY message_id ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$user_id]);
$recent_campaigns = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk SMS - <?php echo APP_NAME; ?></title>
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

        .nav-tabs {
            border-bottom: 1px solid #e0e0e0;
            margin-bottom: 20px;
        }

        .nav-tabs .nav-link {
            color: #666666;
            border: none;
            padding: 10px 20px;
            font-weight: 500;
        }

        .nav-tabs .nav-link:hover {
            color: #1e3a8a;
            border: none;
        }

        .nav-tabs .nav-link.active {
            color: #1e3a8a;
            background: none;
            border-bottom: 3px solid #1e3a8a;
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
            padding: 10px 16px;
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
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
        }

        .btn-outline-primary:hover {
            background-color: #1e3a8a;
            color: #ffffff;
        }

        .recipient-list {
            background-color: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            max-height: 200px;
            overflow-y: auto;
        }

        .recipient-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px;
            border-bottom: 1px solid #e0e0e0;
        }

        .recipient-item:last-child {
            border-bottom: none;
        }

        .remove-recipient {
            color: #dc3545;
            cursor: pointer;
            opacity: 0.7;
        }

        .remove-recipient:hover {
            opacity: 1;
        }

        .csv-upload-area {
            border: 2px dashed #e0e0e0;
            border-radius: 12px;
            padding: 40px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .csv-upload-area:hover {
            border-color: #1e3a8a;
            background-color: #f8f9fa;
        }

        .csv-upload-area i {
            font-size: 48px;
            color: #1e3a8a;
            opacity: 0.5;
            margin-bottom: 15px;
        }

        .campaign-card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            transition: all 0.2s ease;
        }

        .campaign-card:hover {
            border-color: #1e3a8a;
            box-shadow: 0 4px 12px rgba(30, 58, 138, 0.1);
        }

        .campaign-card .title {
            font-weight: 600;
            color: #333333;
            margin-bottom: 5px;
        }

        .campaign-card .meta {
            font-size: 12px;
            color: #666666;
        }

        .campaign-card .stats {
            display: flex;
            gap: 20px;
            margin-top: 10px;
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-weight: 700;
            color: #1e3a8a;
        }

        .stat-label {
            font-size: 11px;
            color: #666666;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
        }
        /* Toast container */
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
            <h2><i class="bi bi-envelopes me-2"></i>Bulk SMS</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Bulk SMS</li>
                </ol>
            </nav>
        </div>

        <!-- Tabs -->
        <ul class="nav nav-tabs" id="bulkSMSTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="manual-tab" data-bs-toggle="tab" data-bs-target="#manual" type="button">
                    <i class="bi bi-pencil"></i> Manual Entry
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="csv-tab" data-bs-toggle="tab" data-bs-target="#csv" type="button">
                    <i class="bi bi-file-earmark-spreadsheet"></i> Upload CSV
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="groups-tab" data-bs-toggle="tab" data-bs-target="#groups" type="button">
                    <i class="bi bi-people"></i> Contact Groups
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="bulkSMSTabContent">
            <!-- Manual Entry Tab -->
            <div class="tab-pane fade show active" id="manual" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-pencil-square"></i> Enter Phone Numbers
                    </div>
                    <div class="card-body">
                        <form id="manualBulkForm">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="method" value="manual">
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Sender ID *</label>
                                    <select name="sender_id" class="form-select" required>
                                        <?php if (empty($sender_ids)): ?>
                                            <option value="">No approved sender IDs</option>
                                        <?php else: ?>
                                            <?php foreach ($sender_ids as $sender): ?>
                                                <option value="<?php echo $sender['sender_id']; ?>">
                                                    <?php echo $sender['sender_id']; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Campaign Name (Optional)</label>
                                    <input type="text" name="campaign_name" class="form-control" placeholder="e.g., Marketing Campaign">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Phone Numbers *</label>
                                <textarea name="phone_numbers" class="form-control" rows="5" 
                                          placeholder="Enter one phone number per line (e.g., 254712345678)"></textarea>
                                <small class="text-muted">Enter each phone number on a new line</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Message *</label>
                                <textarea name="message" class="form-control" rows="5" 
                                          placeholder="Type your message here..." maxlength="1600"></textarea>
                                <div class="text-end mt-1">
                                    <small class="text-muted"><span id="manualCharCount">0</span>/1600 characters</small>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Schedule (Optional)</label>
                                <input type="datetime-local" name="schedule_time" class="form-control">
                            </div>

                            <hr>

                            <div class="d-flex justify-content-between">
                                <div>
                                    <span class="badge bg-primary" id="manualRecipientCount">0 recipients</span>
                                    <span class="badge bg-info" id="manualTotalCost">0 credits</span>
                                </div>
                                <button type="submit" class="btn btn-primary" id="manualSubmitBtn">
                                    <i class="bi bi-send"></i> Send Bulk SMS
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- CSV Upload Tab -->
            <div class="tab-pane fade" id="csv" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-file-earmark-spreadsheet"></i> Upload CSV File
                    </div>
                    <div class="card-body">
                        <form id="csvBulkForm" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="method" value="csv">
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Sender ID *</label>
                                    <select name="sender_id" class="form-select" required>
                                        <?php if (empty($sender_ids)): ?>
                                            <option value="">No approved sender IDs</option>
                                        <?php else: ?>
                                            <?php foreach ($sender_ids as $sender): ?>
                                                <option value="<?php echo $sender['sender_id']; ?>">
                                                    <?php echo $sender['sender_id']; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Campaign Name *</label>
                                    <input type="text" name="campaign_name" class="form-control" placeholder="e.g., Marketing Campaign" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">CSV File *</label>
                                <div class="csv-upload-area" id="csvUploadArea">
                                    <input type="file" name="csv_file" id="csvFile" accept=".csv" style="display: none;">
                                    <i class="bi bi-cloud-upload"></i>
                                    <h5>Click to upload or drag and drop</h5>
                                    <p class="text-muted">CSV file with phone numbers (one per row)</p>
                                    <p class="text-muted small">Supported format: phone, name (optional)</p>
                                </div>
                                <div id="csvPreview" class="mt-3" style="display: none;">
                                    <h6>File Preview:</h6>
                                    <div class="recipient-list" id="csvRecipientList"></div>
                                    <div class="mt-2">
                                        <span class="badge bg-primary" id="csvRecipientCount">0 recipients</span>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Message *</label>
                                <textarea name="message" class="form-control" rows="5" 
                                          placeholder="Type your message here..." maxlength="1600" required></textarea>
                                <div class="text-end mt-1">
                                    <small class="text-muted"><span id="csvCharCount">0</span>/1600 characters</small>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Schedule (Optional)</label>
                                <input type="datetime-local" name="schedule_time" class="form-control">
                            </div>

                            <hr>

                            <div class="d-flex justify-content-between">
                                <div>
                                    <span class="badge bg-info" id="csvTotalCost">0 credits</span>
                                </div>
                                <button type="submit" class="btn btn-primary" id="csvSubmitBtn">
                                    <i class="bi bi-send"></i> Send Bulk SMS
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Contact Groups Tab -->
            <div class="tab-pane fade" id="groups" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-people"></i> Send to Contact Groups
                    </div>
                    <div class="card-body">
                        <?php if (empty($groups)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-people" style="font-size: 48px; color: #e0e0e0;"></i>
                                <h5 class="mt-3">No Contact Groups Found</h5>
                                <p class="text-muted">Create contact groups to send bulk SMS to groups of contacts.</p>
                                <a href="groups.php" class="btn btn-primary">Create Group</a>
                            </div>
                        <?php else: ?>
                            <form id="groupBulkForm">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="method" value="group">
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Sender ID *</label>
                                        <select name="sender_id" class="form-select" required>
                                            <?php foreach ($sender_ids as $sender): ?>
                                                <option value="<?php echo $sender['sender_id']; ?>">
                                                    <?php echo $sender['sender_id']; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Campaign Name *</label>
                                        <input type="text" name="campaign_name" class="form-control" placeholder="e.g., Marketing Campaign" required>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Select Groups *</label>
                                    <div class="row">
                                        <?php foreach ($groups as $group): ?>
                                            <?php
                                            // Get contact count for group
                                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM contacts WHERE group_id = ?");
                                            $stmt->execute([$group['id']]);
                                            $contact_count = $stmt->fetchColumn();
                                            ?>
                                            <div class="col-md-4 mb-2">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="groups[]" 
                                                           value="<?php echo $group['id']; ?>" id="group<?php echo $group['id']; ?>">
                                                    <label class="form-check-label" for="group<?php echo $group['id']; ?>">
                                                        <?php echo htmlspecialchars($group['name']); ?>
                                                        <span class="badge bg-secondary"><?php echo $contact_count; ?> contacts</span>
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Message *</label>
                                    <textarea name="message" class="form-control" rows="5" 
                                              placeholder="Type your message here..." maxlength="1600" required></textarea>
                                    <div class="text-end mt-1">
                                        <small class="text-muted"><span id="groupCharCount">0</span>/1600 characters</small>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Schedule (Optional)</label>
                                    <input type="datetime-local" name="schedule_time" class="form-control">
                                </div>

                                <hr>

                                <div class="d-flex justify-content-between">
                                    <div>
                                        <span class="badge bg-primary" id="groupRecipientCount">0 recipients</span>
                                        <span class="badge bg-info" id="groupTotalCost">0 credits</span>
                                    </div>
                                    <button type="submit" class="btn btn-primary" id="groupSubmitBtn">
                                        <i class="bi bi-send"></i> Send Bulk SMS
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Campaigns -->
        <?php if (!empty($recent_campaigns)): ?>
        <div class="card mt-4">
            <div class="card-header">
                <i class="bi bi-clock-history"></i> Recent Bulk SMS Campaigns
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($recent_campaigns as $campaign): ?>
                        <div class="col-md-4">
                            <div class="campaign-card">
                                <div class="title"><?php echo htmlspecialchars($campaign['message_id']); ?></div>
                                <div class="meta">
                                    <?php echo date('M d, Y H:i', strtotime($campaign['created_at'])); ?>
                                </div>
                                <div class="meta">
                                    Recipients: <?php echo substr_count($campaign['recipient'], ',') + 1; ?>
                                </div>
                                <div class="stats">
                                    <div class="stat-item">
                                        <div class="stat-value"><?php echo $campaign['sms_count']; ?></div>
                                        <div class="stat-label">SMS Parts</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-value"><?php echo $campaign['cost']; ?></div>
                                        <div class="stat-label">Cost</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-value">
                                            <span class="badge bg-<?php echo $campaign['status'] == 'sent' ? 'success' : 'warning'; ?>">
                                                <?php echo ucfirst($campaign['status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mobile menu toggle
        document.getElementById('menuToggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });

        // Manual entry calculations
        const manualPhoneNumbers = document.querySelector('textarea[name="phone_numbers"]');
        const manualMessage = document.querySelector('#manual textarea[name="message"]');
        const manualCharCount = document.getElementById('manualCharCount');
        const manualRecipientCount = document.getElementById('manualRecipientCount');
        const manualTotalCost = document.getElementById('manualTotalCost');

        function updateManualCalculations() {
            // Count recipients
            const numbers = manualPhoneNumbers.value.split('\n').filter(n => n.trim());
            manualRecipientCount.textContent = numbers.length + ' recipients';
            
            // Count characters and calculate SMS parts
            const text = manualMessage.value;
            const length = text.length;
            manualCharCount.textContent = length;
            
            // Calculate cost
            const parts = length > 160 ? Math.ceil(length / 153) : 1;
            const totalCost = parts * numbers.length;
            manualTotalCost.textContent = totalCost + ' credits';
        }

        manualPhoneNumbers.addEventListener('input', updateManualCalculations);
        manualMessage.addEventListener('input', updateManualCalculations);

        // CSV upload
        const csvUploadArea = document.getElementById('csvUploadArea');
        const csvFile = document.getElementById('csvFile');
        const csvPreview = document.getElementById('csvPreview');
        const csvRecipientList = document.getElementById('csvRecipientList');
        const csvRecipientCount = document.getElementById('csvRecipientCount');
        const csvTotalCost = document.getElementById('csvTotalCost');
        const csvMessage = document.querySelector('#csv textarea[name="message"]');
        const csvCharCount = document.getElementById('csvCharCount');

        csvUploadArea.addEventListener('click', () => csvFile.click());
        
        csvUploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            csvUploadArea.style.borderColor = '#1e3a8a';
            csvUploadArea.style.backgroundColor = '#f8f9fa';
        });

        csvUploadArea.addEventListener('dragleave', () => {
            csvUploadArea.style.borderColor = '#e0e0e0';
            csvUploadArea.style.backgroundColor = 'transparent';
        });

        csvUploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            csvUploadArea.style.borderColor = '#e0e0e0';
            csvUploadArea.style.backgroundColor = 'transparent';
            
            const files = e.dataTransfer.files;
            if (files.length > 0 && files[0].type === 'text/csv') {
                csvFile.files = files;
                handleCSVFile(files[0]);
            }
        });

        csvFile.addEventListener('change', function() {
            if (this.files.length > 0) {
                handleCSVFile(this.files[0]);
            }
        });

        function handleCSVFile(file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const lines = e.target.result.split('\n');
                let recipients = [];
                
                lines.forEach(line => {
                    const parts = line.split(',');
                    if (parts.length > 0 && parts[0].trim()) {
                        recipients.push(parts[0].trim());
                    }
                });
                
                // Show preview
                csvRecipientList.innerHTML = '';
                recipients.slice(0, 10).forEach(recipient => {
                    const div = document.createElement('div');
                    div.className = 'recipient-item';
                    div.innerHTML = recipient;
                    csvRecipientList.appendChild(div);
                });
                
                if (recipients.length > 10) {
                    const div = document.createElement('div');
                    div.className = 'recipient-item text-muted';
                    div.innerHTML = `... and ${recipients.length - 10} more`;
                    csvRecipientList.appendChild(div);
                }
                
                csvRecipientCount.textContent = recipients.length + ' recipients';
                csvPreview.style.display = 'block';
                
                // Update cost calculation
                updateCSVCalculations(recipients.length);
            };
            reader.readAsText(file);
        }

        function updateCSVCalculations(recipientCount) {
            const text = csvMessage.value;
            const parts = text.length > 160 ? Math.ceil(text.length / 153) : 1;
            const totalCost = parts * recipientCount;
            csvTotalCost.textContent = totalCost + ' credits';
        }

        csvMessage.addEventListener('input', function() {
            csvCharCount.textContent = this.value.length;
            if (csvPreview.style.display === 'block') {
                const count = parseInt(csvRecipientCount.textContent);
                updateCSVCalculations(count);
            }
        });

        // Group selection
        const groupCheckboxes = document.querySelectorAll('input[name="groups[]"]');
        const groupMessage = document.querySelector('#groups textarea[name="message"]');
        const groupRecipientCount = document.getElementById('groupRecipientCount');
        const groupTotalCost = document.getElementById('groupTotalCost');
        const groupCharCount = document.getElementById('groupCharCount');

        function updateGroupCalculations() {
            // Count selected groups and their contacts
            let totalRecipients = 0;
            groupCheckboxes.forEach(cb => {
                if (cb.checked) {
                    // This would need actual contact counts from server
                    // For demo, using placeholder
                    totalRecipients += 10;
                }
            });
            
            groupRecipientCount.textContent = totalRecipients + ' recipients';
            
            const text = groupMessage.value;
            groupCharCount.textContent = text.length;
            
            const parts = text.length > 160 ? Math.ceil(text.length / 153) : 1;
            const totalCost = parts * totalRecipients;
            groupTotalCost.textContent = totalCost + ' credits';
        }

        groupCheckboxes.forEach(cb => cb.addEventListener('change', updateGroupCalculations));
        groupMessage.addEventListener('input', updateGroupCalculations);

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
        // Form submission handlers
document.getElementById('manualBulkForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    submitBulkForm(this);
});

document.getElementById('csvBulkForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Validate file is selected
    const fileInput = document.getElementById('csvFile');
    if (!fileInput.files || fileInput.files.length === 0) {
        showToast('Please select a CSV file', 'error');
        return;
    }
    
    submitBulkForm(this);
});

document.getElementById('groupBulkForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Validate at least one group is selected
    const selectedGroups = document.querySelectorAll('input[name="groups[]"]:checked');
    if (selectedGroups.length === 0) {
        showToast('Please select at least one contact group', 'error');
        return;
    }
    
    submitBulkForm(this);
});

// Function to show toast notification
function showToast(message, type = 'success') {
    const toastContainer = document.getElementById('toastContainer');
    if (!toastContainer) {
        // Create toast container if it doesn't exist
        const container = document.createElement('div');
        container.id = 'toastContainer';
        container.className = 'toast-container';
        container.style.position = 'fixed';
        container.style.top = '80px';
        container.style.right = '20px';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
    }
    
    const toastId = 'toast-' + Date.now();
    const container = document.getElementById('toastContainer');
    
    const icons = {
        success: 'bi-check-circle-fill text-success',
        error: 'bi-exclamation-triangle-fill text-danger',
        info: 'bi-info-circle-fill text-primary'
    };
    
    const toastHtml = `
        <div id="${toastId}" class="toast ${type}" role="alert" style="min-width: 350px;">
            <div class="toast-header">
                <i class="bi ${icons[type]} me-2"></i>
                <strong class="me-auto">${type === 'success' ? 'Success' : 'Error'}</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body">
                ${message}
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', toastHtml);
    
    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement, { delay: 5000 });
    toast.show();
    
    toastElement.addEventListener('hidden.bs.toast', function() {
        this.remove();
    });
}

// Function to submit bulk form
function submitBulkForm(form) {
    const formData = new FormData(form);
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    
    // Show loading state
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Sending...';
    
    // Send AJAX request
    fetch('../ajax/bulk_sms_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            showToast(data.message, 'success');
            
            // Reset form
            if (form.id === 'manualBulkForm') {
                form.querySelector('textarea[name="phone_numbers"]').value = '';
                form.querySelector('textarea[name="message"]').value = '';
                updateManualCalculations();
            } else if (form.id === 'csvBulkForm') {
                form.querySelector('textarea[name="message"]').value = '';
                document.getElementById('csvFile').value = '';
                document.getElementById('csvPreview').style.display = 'none';
                updateCSVCalculations(0);
            } else if (form.id === 'groupBulkForm') {
                form.querySelector('textarea[name="message"]').value = '';
                document.querySelectorAll('input[name="groups[]"]:checked').forEach(cb => cb.checked = false);
                updateGroupCalculations();
            }
            
            // Update topbar balance
            if (data.data && data.data.new_balance) {
                if (typeof refreshTopbarBalance === 'function') {
                    refreshTopbarBalance();
                }
            }
            
            // Reload page after 3 seconds to refresh stats
            setTimeout(() => {
                location.reload();
            }, 3000);
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
}

// Update CSV calculations function
function updateCSVCalculations(recipientCount) {
    const csvTotalCost = document.getElementById('csvTotalCost');
    const csvMessage = document.querySelector('#csv textarea[name="message"]');
    if (csvTotalCost && csvMessage) {
        const text = csvMessage.value;
        const parts = text.length > 160 ? Math.ceil(text.length / 153) : 1;
        const totalCost = parts * recipientCount;
        csvTotalCost.textContent = totalCost + ' credits';
    }
}

// Update group calculations function (enhanced)
function updateGroupCalculations() {
    const groupCheckboxes = document.querySelectorAll('input[name="groups[]"]');
    const groupRecipientCount = document.getElementById('groupRecipientCount');
    const groupTotalCost = document.getElementById('groupTotalCost');
    const groupMessage = document.querySelector('#groups textarea[name="message"]');
    
    if (groupCheckboxes && groupRecipientCount && groupTotalCost && groupMessage) {
        let totalRecipients = 0;
        groupCheckboxes.forEach(cb => {
            if (cb.checked) {
                // Get contact count from the badge
                const label = cb.closest('.col-md-4').querySelector('.form-check-label');
                const badge = label.querySelector('.badge');
                if (badge) {
                    const count = parseInt(badge.textContent);
                    if (!isNaN(count)) {
                        totalRecipients += count;
                    }
                }
            }
        });
        
        groupRecipientCount.textContent = totalRecipients + ' recipients';
        
        const text = groupMessage.value;
        const parts = text.length > 160 ? Math.ceil(text.length / 153) : 1;
        const totalCost = parts * totalRecipients;
        groupTotalCost.textContent = totalCost + ' credits';
    }
}
    </script>
</body>
</html>