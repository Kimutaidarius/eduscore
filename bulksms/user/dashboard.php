<?php
require_once '../config/config.php';
requireLogin();

$user_id = $_SESSION['user_id'];

// Get user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Get statistics
// Total SMS sent
$stmt = $pdo->prepare("SELECT COUNT(*) as total, SUM(sms_count) as total_sms FROM sms_messages WHERE user_id = ?");
$stmt->execute([$user_id]);
$sms_stats = $stmt->fetch();

// SMS by status
$stmt = $pdo->prepare("
    SELECT status, COUNT(*) as count, SUM(sms_count) as total_sms 
    FROM sms_messages 
    WHERE user_id = ? 
    GROUP BY status
");
$stmt->execute([$user_id]);
$status_stats = $stmt->fetchAll();

// Recent messages
$stmt = $pdo->prepare("
    SELECT * FROM sms_messages 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->execute([$user_id]);
$recent_messages = $stmt->fetchAll();

// Get API key
$api_key = getUserApiKey($pdo, $user_id);

// Get contacts count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM contacts WHERE user_id = ?");
$stmt->execute([$user_id]);
$contacts_count = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo APP_NAME; ?></title>
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

        /* Main Content */
        .main-content {
            margin-left: 250px;
            margin-top: 60px;
            padding: 30px;
            background-color: #ffffff;
            min-height: calc(100vh - 60px);
        }

        /* Cards */
        .stat-card {
            background-color: #ffffff;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(30, 58, 138, 0.1);
        }

        .stat-card .title {
            color: #666666;
            font-size: 0.9rem;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-card .value {
            color: #1e3a8a;
            font-size: 2.2rem;
            font-weight: 700;
            line-height: 1.2;
        }

        .stat-card .icon {
            float: right;
            color: #1e3a8a;
            font-size: 3rem;
            opacity: 0.2;
        }

        /* Tables */
        .table-container {
            background-color: #ffffff;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            padding: 20px;
            margin-top: 20px;
        }

        .table-container h5 {
            color: #1e3a8a;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .table {
            color: #333333;
            margin-bottom: 0;
        }

        .table thead th {
            border-bottom: 2px solid #e0e0e0;
            color: #666666;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table tbody tr:hover {
            background-color: #f8f9fa;
        }

        /* Buttons */
        .btn-outline-primary {
            border-color: #1e3a8a;
            color: #1e3a8a;
        }

        .btn-outline-primary:hover {
            background-color: #1e3a8a;
            border-color: #1e3a8a;
            color: #ffffff;
        }

        .btn-primary {
            background-color: #1e3a8a;
            border-color: #152b63;
            color: #ffffff;
        }

        .btn-primary:hover {
            background-color: #152b63;
            border-color: #0f1f4a;
        }

        /* Quick Actions */
        .quick-actions .btn {
            text-align: left;
            padding: 12px 16px;
            border: 1px solid #e0e0e0;
            background-color: #ffffff;
            color: #333333;
            transition: all 0.2s ease;
        }

        .quick-actions .btn:hover {
            background-color: #f8f9fa;
            border-color: #1e3a8a;
            color: #1e3a8a;
        }

        .quick-actions .btn i {
            margin-right: 10px;
            color: #1e3a8a;
        }

        /* Badges */
        .badge {
            padding: 6px 10px;
            font-weight: 500;
            font-size: 0.8rem;
        }

        /* Modal */
        .modal-header {
            background-color: #1e3a8a;
            color: #ffffff;
            border-bottom: none;
        }

        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }

        .modal-footer {
            border-top: 1px solid #e0e0e0;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
        }
    </style>
    <!-- Add Inter font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Include Sidebar -->
    <?php include '../includes/sidebar.php'; ?>
    
    <!-- Include Topbar -->
    <?php include '../includes/topbar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Statistics Cards -->
        <div class="row">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="icon"><i class="bi bi-envelope"></i></div>
                    <div class="title">Total SMS Sent</div>
                    <div class="value"><?php echo number_format($sms_stats['total_sms'] ?? 0); ?></div>
                    <small class="text-muted"><?php echo number_format($sms_stats['total'] ?? 0); ?> messages</small>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="icon"><i class="bi bi-check-circle"></i></div>
                    <div class="title">SMS Balance</div>
                    <div class="value"><?php echo number_format($user['sms_balance']); ?></div>
                    <small class="text-muted">credits remaining</small>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="icon"><i class="bi bi-people"></i></div>
                    <div class="title">Contacts</div>
                    <div class="value"><?php echo number_format($contacts_count); ?></div>
                    <small class="text-muted">saved contacts</small>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="icon"><i class="bi bi-key"></i></div>
                    <div class="title">API Status</div>
                    <div class="value">
                        <?php if ($api_key): ?>
                            <span class="badge bg-success">Active</span>
                        <?php else: ?>
                            <span class="badge bg-danger">No API Key</span>
                        <?php endif; ?>
                    </div>
                    <small class="text-muted">
                        <?php echo $api_key ? 'Key: ' . substr($api_key['api_key'], 0, 10) . '...' : '<a href="api-keys.php" class="text-primary">Generate API key</a>'; ?>
                    </small>
                </div>
            </div>
        </div>
        
        <!-- Status Distribution and Quick Actions -->
        <div class="row mt-3">
            <div class="col-md-6">
                <div class="stat-card">
                    <h5 class="mb-3" style="color: #1e3a8a;">SMS Status Distribution</h5>
                    <div class="row">
                        <?php
                        $status_colors = [
                            'delivered' => 'success',
                            'sent' => 'info',
                            'pending' => 'warning',
                            'failed' => 'danger',
                            'scheduled' => 'primary'
                        ];
                        
                        foreach ($status_stats as $stat):
                            $color = $status_colors[$stat['status']] ?? 'secondary';
                        ?>
                            <div class="col-6 mb-3">
                                <div class="d-flex align-items-center">
                                    <span class="badge bg-<?php echo $color; ?> me-2"><?php echo ucfirst($stat['status']); ?></span>
                                    <strong><?php echo number_format($stat['total_sms'] ?? $stat['count']); ?></strong>
                                    <small class="text-muted ms-1">SMS</small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="stat-card">
                    <h5 class="mb-3" style="color: #1e3a8a;">Quick Actions</h5>
                    <div class="quick-actions d-grid gap-2">
                        <button class="btn" onclick="location.href='send-sms.php'">
                            <i class="bi bi-envelope-paper"></i> Send Single SMS
                        </button>
                        <button class="btn" onclick="location.href='bulk-sms.php'">
                            <i class="bi bi-envelopes"></i> Send Bulk SMS
                        </button>
                        <button class="btn" onclick="location.href='contacts.php?action=import'">
                            <i class="bi bi-upload"></i> Import Contacts
                        </button>
                        <button class="btn" onclick="location.href='api-keys.php'">
                            <i class="bi bi-key"></i> Manage API Keys
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Messages -->
        <div class="table-container">
            <h5 class="mb-3">Recent SMS Activity</h5>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Message ID</th>
                            <th>Recipient</th>
                            <th>Message</th>
                            <th>Sender ID</th>
                            <th>Status</th>
                            <th>Sent At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_messages)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <i class="bi bi-envelope" style="font-size: 3rem; color: #e0e0e0;"></i>
                                    <p class="mt-3 text-muted">No SMS sent yet.</p>
                                    <a href="send-sms.php" class="btn btn-primary btn-sm">Send your first SMS</a>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recent_messages as $msg): ?>
                                <tr>
                                    <td><code class="text-primary"><?php echo $msg['message_id']; ?></code></td>
                                    <td><?php echo $msg['recipient']; ?></td>
                                    <td>
                                        <?php echo htmlspecialchars(substr($msg['message'], 0, 30)); ?>
                                        <?php if (strlen($msg['message']) > 30): ?>...<?php endif; ?>
                                    </td>
                                    <td><?php echo $msg['sender_id']; ?></td>
                                    <td><?php echo getStatusBadge($msg['status']); ?></td>
                                    <td><?php echo formatDate($msg['created_at']); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary view-message" 
                                                data-id="<?php echo $msg['id']; ?>"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#messageModal">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if (!empty($recent_messages)): ?>
                <div class="text-center mt-3">
                    <a href="sms-logs.php" class="btn btn-outline-primary">View All Messages</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Message View Modal -->
    <div class="modal fade" id="messageModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Message Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="messageDetails">
                    <div class="text-center py-3">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mobile menu toggle
        document.getElementById('menuToggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });
        
        // View message details
        document.querySelectorAll('.view-message').forEach(button => {
            button.addEventListener('click', function() {
                const id = this.dataset.id;
                const messageDetails = document.getElementById('messageDetails');
                
                // Show loading state
                messageDetails.innerHTML = '<div class="text-center py-3"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
                
                fetch(`get-message.php?id=${id}`)
                    .then(response => response.json())
                    .then(data => {
                        let html = `
                            <div class="mb-3">
                                <label class="fw-bold">Message ID:</label>
                                <p class="text-primary">${data.message_id}</p>
                            </div>
                            <div class="mb-3">
                                <label class="fw-bold">Recipient:</label>
                                <p>${data.recipient}</p>
                            </div>
                            <div class="mb-3">
                                <label class="fw-bold">Sender ID:</label>
                                <p>${data.sender_id}</p>
                            </div>
                            <div class="mb-3">
                                <label class="fw-bold">Message:</label>
                                <p class="bg-light p-3 rounded">${data.message}</p>
                            </div>
                            <div class="row">
                                <div class="col-6 mb-3">
                                    <label class="fw-bold">SMS Parts:</label>
                                    <p>${data.sms_count}</p>
                                </div>
                                <div class="col-6 mb-3">
                                    <label class="fw-bold">Cost:</label>
                                    <p>${data.cost} credits</p>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="fw-bold">Status:</label>
                                <p>${data.status}</p>
                            </div>
                            <div class="mb-3">
                                <label class="fw-bold">Created:</label>
                                <p>${data.created_at}</p>
                            </div>
                        `;
                        
                        if (data.sent_at) html += `<div class="mb-3"><label class="fw-bold">Sent:</label><p>${data.sent_at}</p></div>`;
                        if (data.delivered_at) html += `<div class="mb-3"><label class="fw-bold">Delivered:</label><p>${data.delivered_at}</p></div>`;
                        if (data.error_message) html += `<div class="mb-3"><label class="fw-bold">Error:</label><p class="text-danger">${data.error_message}</p></div>`;
                        
                        messageDetails.innerHTML = html;
                    })
                    .catch(error => {
                        messageDetails.innerHTML = '<div class="alert alert-danger">Error loading message details</div>';
                    });
            });
        });

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