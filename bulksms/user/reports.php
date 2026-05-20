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

// Default date range
$date_from = date('Y-m-01');
$date_to = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        .filter-card {
            background-color: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: linear-gradient(135deg, #1e3a8a 0%, #152b63 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 20px;
            height: 100%;
            transition: transform 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
        }

        .stat-card .label {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 5px;
        }

        .stat-card .value {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-card .change {
            font-size: 13px;
            opacity: 0.8;
        }

        .stat-card-green {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
        }

        .stat-card-orange {
            background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
        }

        .stat-card-purple {
            background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);
        }

        .chart-container {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .chart-container h5 {
            color: #1e3a8a;
            margin-bottom: 20px;
        }

        .table-container {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .table-container h5 {
            color: #1e3a8a;
            margin-bottom: 20px;
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
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-success {
            background-color: #d1fae5;
            color: #065f46;
        }

        .status-warning {
            background-color: #fed7aa;
            color: #92400e;
        }

        .status-danger {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .status-info {
            background-color: #dbeafe;
            color: #1e40af;
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

        .report-tabs {
            margin-bottom: 20px;
        }

        .report-tabs .nav-link {
            color: #666666;
            border: none;
            padding: 10px 20px;
            font-weight: 500;
            cursor: pointer;
        }

        .report-tabs .nav-link:hover {
            color: #1e3a8a;
        }

        .report-tabs .nav-link.active {
            color: #1e3a8a;
            border-bottom: 3px solid #1e3a8a;
            background: none;
        }

        .export-dropdown .dropdown-menu {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 5px;
        }

        .export-dropdown .dropdown-item {
            padding: 8px 15px;
            border-radius: 6px;
            font-size: 13px;
        }

        .export-dropdown .dropdown-item:hover {
            background-color: #f8f9fa;
            color: #1e3a8a;
        }

        .loading-spinner {
            display: inline-block;
            width: 40px;
            height: 40px;
            border: 4px solid rgba(30, 58, 138, 0.3);
            border-radius: 50%;
            border-top-color: #1e3a8a;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
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

        .no-data-message {
            text-align: center;
            padding: 40px;
            color: #666666;
        }

        .no-data-message i {
            font-size: 48px;
            color: #e0e0e0;
            margin-bottom: 15px;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }

            .stat-card .value {
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
        /* Ensure chart containers maintain consistent height */
.chart-container {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 30px;
    min-height: 400px; /* Fixed minimum height */
    position: relative;
    overflow: hidden;
}

.chart-container canvas {
    max-height: 300px;
    width: 100% !important;
}

/* No data overlay styling */
.no-data-overlay {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
    color: #999;
    background: rgba(255, 255, 255, 0.95);
    padding: 20px;
    border-radius: 8px;
    z-index: 10;
    pointer-events: none;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    border: 1px solid #f0f0f0;
}

.no-data-overlay i {
    font-size: 32px;
    color: #ccc;
    margin-bottom: 10px;
    display: block;
}

.no-data-overlay p {
    margin: 0;
    font-size: 14px;
    color: #999;
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
                    <h2><i class="bi bi-graph-up me-2"></i>Reports & Analytics</h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Reports</li>
                        </ol>
                    </nav>
                </div>
                <div class="export-dropdown dropdown">
                    <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-download"></i> Export Report
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="#" onclick="exportReport('pdf')"><i class="bi bi-file-earmark-pdf"></i> PDF</a></li>
                        <li><a class="dropdown-item" href="#" onclick="exportReport('excel')"><i class="bi bi-file-earmark-excel"></i> Excel</a></li>
                        <li><a class="dropdown-item" href="#" onclick="exportReport('csv')"><i class="bi bi-file-earmark-spreadsheet"></i> CSV</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Date Range Filter -->
        <div class="filter-card">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Date From</label>
                    <input type="date" id="dateFrom" class="form-control" value="<?php echo $date_from; ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Date To</label>
                    <input type="date" id="dateTo" class="form-control" value="<?php echo $date_to; ?>">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary w-100" onclick="applyFilter()">
                        <i class="bi bi-filter"></i> Apply Filter
                    </button>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-outline-primary w-100" onclick="resetFilter()">
                        <i class="bi bi-arrow-counterclockwise"></i> Reset
                    </button>
                </div>
            </div>
        </div>

        <!-- Loading Indicator -->
        <div id="loadingIndicator" class="text-center py-5" style="display: none;">
            <div class="loading-spinner"></div>
            <p class="mt-3 text-muted">Loading report data...</p>
        </div>

        <!-- Statistics Cards -->
        <div class="row" id="statCards">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="label">Total SMS Sent</div>
                    <div class="value" id="totalSms">0</div>
                    <div class="change" id="totalMessages">0 messages</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card stat-card-green">
                    <div class="label">Total Cost</div>
                    <div class="value" id="totalCost">KES 0</div>
                    <div class="change" id="costChange">0 credits used</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card stat-card-orange">
                    <div class="label">Active Days</div>
                    <div class="value" id="activeDays">0</div>
                    <div class="change" id="activeDaysChange">days with activity</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card stat-card-purple">
                    <div class="label">Unique Recipients</div>
                    <div class="value" id="uniqueRecipients">0</div>
                    <div class="change" id="recipientsChange">contacts reached</div>
                </div>
            </div>
        </div>

        <!-- Report Tabs -->
        <ul class="nav nav-tabs report-tabs" id="reportTabs">
            <li class="nav-item">
                <button class="nav-link active" onclick="changeReportType('summary')">
                    <i class="bi bi-pie-chart"></i> Summary
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" onclick="changeReportType('daily')">
                    <i class="bi bi-calendar-day"></i> Daily Breakdown
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" onclick="changeReportType('recipients')">
                    <i class="bi bi-people"></i> Top Recipients
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" onclick="changeReportType('hourly')">
                    <i class="bi bi-clock"></i> Hourly Distribution
                </button>
            </li>
        </ul>

        <!-- Charts Row -->
        <div class="row">
            <div class="col-md-6">
                <div class="chart-container">
                    <h5><i class="bi bi-pie-chart"></i> Status Distribution</h5>
                    <canvas id="statusChart" style="height: 300px;"></canvas>
                </div>
            </div>
            <div class="col-md-6">
                <div class="chart-container">
                    <h5><i class="bi bi-bar-chart"></i> Daily SMS Volume</h5>
                    <canvas id="smsChart" style="height: 300px;"></canvas>
                </div>
            </div>
        </div>

        <!-- Second Charts Row -->
        <div class="row">
            <div class="col-md-12">
                <div class="chart-container">
                    <h5><i class="bi bi-clock-history"></i> Hourly Distribution</h5>
                    <canvas id="hourlyChart" style="height: 300px;"></canvas>
                </div>
            </div>
        </div>

        <!-- Detailed Tables Container -->
        <div id="tablesContainer"></div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Chart instances
        let smsChart = null;
        let statusChart = null;
        let hourlyChart = null;
        
        // Current report type
        let currentReportType = 'summary';
        
        // Date range
        let currentDateFrom = '<?php echo $date_from; ?>';
        let currentDateTo = '<?php echo $date_to; ?>';

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadAllReports();
        });

        // Mobile menu toggle
        document.getElementById('menuToggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });

        // Toast notification function
        function showToast(message, type = 'success') {
            const toastContainer = document.getElementById('toastContainer');
            const toastId = 'toast-' + Date.now();
            
            const icon = type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill';
            
            const toastHtml = `
                <div id="${toastId}" class="toast ${type}" role="alert">
                    <div class="toast-header">
                        <i class="bi ${icon} me-2"></i>
                        <strong class="me-auto">${type === 'success' ? 'Success' : 'Error'}</strong>
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

        // Apply filter
        function applyFilter() {
            currentDateFrom = document.getElementById('dateFrom').value;
            currentDateTo = document.getElementById('dateTo').value;
            loadAllReports();
        }

        // Reset filter
        function resetFilter() {
            const today = new Date();
            const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
            
            currentDateFrom = firstDay.toISOString().split('T')[0];
            currentDateTo = today.toISOString().split('T')[0];
            
            document.getElementById('dateFrom').value = currentDateFrom;
            document.getElementById('dateTo').value = currentDateTo;
            
            loadAllReports();
        }

        // Load all reports data
        function loadAllReports() {
            // Show loading
            document.getElementById('loadingIndicator').style.display = 'block';
            
            const formData = new FormData();
            formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
            formData.append('action', 'get_all_reports');
            formData.append('date_from', currentDateFrom);
            formData.append('date_to', currentDateTo);
            
            fetch('../ajax/reports_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('loadingIndicator').style.display = 'none';
                
                if (data.status === 'success') {
                    updateStatistics(data.data.summary);
                    updateCharts(data.data);
                    updateTables(data.data);
                } else {
                    showToast(data.message, 'error');
                    // Show empty state
                    updateCharts({ daily_stats: [], status_breakdown: [], hourly_stats: Array(24).fill(0) });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('loadingIndicator').style.display = 'none';
                showToast('Error loading reports', 'error');
            });
        }

        // Update statistics cards
        function updateStatistics(summary) {
            document.getElementById('totalSms').textContent = formatNumber(summary.total_sms || 0);
            document.getElementById('totalMessages').textContent = formatNumber(summary.total_messages || 0) + ' messages';
            document.getElementById('totalCost').textContent = 'KES ' + formatNumber(summary.total_cost || 0, 2);
            document.getElementById('costChange').textContent = formatNumber(summary.total_cost || 0, 2) + ' credits used';
            document.getElementById('activeDays').textContent = formatNumber(summary.active_days || 0);
            document.getElementById('uniqueRecipients').textContent = formatNumber(summary.unique_recipients || 0);
        }
// Update charts
function updateCharts(data) {
    // Status Chart (Pie Chart)
    const statusData = data.status_breakdown || [];
    const statusLabels = statusData.map(s => s.status.charAt(0).toUpperCase() + s.status.slice(1));
    const statusCounts = statusData.map(s => s.count);
    const statusColors = {
        'delivered': '#10b981',
        'sent': '#3b82f6',
        'pending': '#f59e0b',
        'failed': '#ef4444',
        'scheduled': '#8b5cf6'
    };
    
    if (statusChart) statusChart.destroy();
    const ctx2 = document.getElementById('statusChart').getContext('2d');
    
    if (statusData.length === 0) {
        // Create a placeholder that maintains card size
        statusChart = new Chart(ctx2, {
            type: 'doughnut',
            data: {
                labels: ['No Data'],
                datasets: [{
                    data: [1],
                    backgroundColor: ['#f0f0f0']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: { 
                        display: true,
                        labels: {
                            generateLabels: function(chart) {
                                return [{
                                    text: 'No Data Available',
                                    fillStyle: '#f0f0f0',
                                    hidden: false,
                                    index: 0
                                }];
                            }
                        }
                    },
                    tooltip: { enabled: false },
                    title: { 
                        display: true, 
                        text: 'No data for selected period',
                        color: '#666666',
                        font: { size: 14 }
                    }
                }
            }
        });
    } else {
        statusChart = new Chart(ctx2, {
            type: 'doughnut',
            data: {
                labels: statusLabels,
                datasets: [{
                    data: statusCounts,
                    backgroundColor: statusData.map(s => statusColors[s.status] || '#6b7280'),
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    }

    // Daily SMS Chart (Bar Chart) - FIXED to maintain size
    const dailyStats = data.daily_stats || [];
    const chartLabels = dailyStats.map(stat => {
        const date = new Date(stat.date);
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    });
    const chartSms = dailyStats.map(stat => stat.sms_count || 0);
    
    if (smsChart) smsChart.destroy();
    const ctx1 = document.getElementById('smsChart').getContext('2d');
    
    // Always use the same number of data points to maintain consistent height
    // If no data, show empty bars with message overlay
    const defaultLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const defaultData = [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
    
    smsChart = new Chart(ctx1, {
        type: 'bar',
        data: {
            labels: dailyStats.length > 0 ? chartLabels : defaultLabels,
            datasets: [{
                label: 'SMS Sent',
                data: dailyStats.length > 0 ? chartSms : defaultData,
                backgroundColor: dailyStats.length > 0 ? '#1e3a8a' : '#e0e0e0',
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                title: { 
                    display: dailyStats.length === 0, 
                    text: 'No data available for selected period',
                    color: '#666666',
                    font: { size: 14 }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: '#e0e0e0' }
                }
            }
        }
    });

    // Add overlay message if no data (optional - using CSS)
    const smsChartContainer = document.getElementById('smsChart').parentElement;
    let overlay = smsChartContainer.querySelector('.no-data-overlay');
    
    if (dailyStats.length === 0) {
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.className = 'no-data-overlay';
            overlay.innerHTML = '<i class="bi bi-bar-chart"></i><p>No data available</p>';
            overlay.style.cssText = `
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                text-align: center;
                color: #999;
                background: rgba(255,255,255,0.9);
                padding: 20px;
                border-radius: 8px;
                z-index: 10;
                pointer-events: none;
            `;
            smsChartContainer.style.position = 'relative';
            smsChartContainer.appendChild(overlay);
        }
    } else {
        if (overlay) overlay.remove();
    }

    // Hourly Chart (Bar Chart)
    const hourlyStats = data.hourly_stats || Array(24).fill(0);
    const hourlyLabels = [];
    for (let i = 0; i < 24; i++) {
        hourlyLabels.push(i.toString().padStart(2, '0') + ':00');
    }
    
    if (hourlyChart) hourlyChart.destroy();
    const ctx3 = document.getElementById('hourlyChart').getContext('2d');
    
    const hasHourlyData = hourlyStats.some(val => val > 0);
    
    // Always use 24 bars to maintain consistent height
    hourlyChart = new Chart(ctx3, {
        type: 'bar',
        data: {
            labels: hourlyLabels,
            datasets: [{
                label: 'Messages',
                data: hourlyStats,
                backgroundColor: hasHourlyData ? '#1e3a8a' : '#e0e0e0',
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                title: { 
                    display: !hasHourlyData, 
                    text: 'No data available for selected period',
                    color: '#666666',
                    font: { size: 14 }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: '#e0e0e0' }
                }
            }
        }
    });

    // Add overlay for hourly chart if no data
    const hourlyChartContainer = document.getElementById('hourlyChart').parentElement;
    let hourlyOverlay = hourlyChartContainer.querySelector('.no-data-overlay');
    
    if (!hasHourlyData) {
        if (!hourlyOverlay) {
            hourlyOverlay = document.createElement('div');
            hourlyOverlay.className = 'no-data-overlay';
            hourlyOverlay.innerHTML = '<i class="bi bi-clock-history"></i><p>No hourly data</p>';
            hourlyOverlay.style.cssText = `
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                text-align: center;
                color: #999;
                background: rgba(255,255,255,0.9);
                padding: 20px;
                border-radius: 8px;
                z-index: 10;
                pointer-events: none;
            `;
            hourlyChartContainer.style.position = 'relative';
            hourlyChartContainer.appendChild(hourlyOverlay);
        }
    } else {
        if (hourlyOverlay) hourlyOverlay.remove();
    }
}

        // Update tables based on report type
        function updateTables(data) {
            const container = document.getElementById('tablesContainer');
            
            if (currentReportType === 'summary' || currentReportType === 'daily') {
                container.innerHTML = generateDailyTable(data.daily_stats);
            } else if (currentReportType === 'recipients') {
                container.innerHTML = generateRecipientsTable(data.top_recipients);
            } else if (currentReportType === 'hourly') {
                container.innerHTML = generateHourlyTable(data.hourly_stats);
            }
        }

        // Generate daily table HTML
        function generateDailyTable(dailyStats) {
            if (!dailyStats || dailyStats.length === 0) {
                return `
                    <div class="table-container">
                        <h5><i class="bi bi-calendar-check"></i> Daily Report</h5>
                        <div class="no-data-message">
                            <i class="bi bi-calendar-x"></i>
                            <p>No data available for selected period</p>
                        </div>
                    </div>
                `;
            }
            
            let rows = '';
            dailyStats.forEach(stat => {
                const date = new Date(stat.date).toLocaleDateString('en-US', { 
                    month: 'short', day: 'numeric', year: 'numeric' 
                });
                const avgCost = stat.messages > 0 ? (stat.cost / stat.messages).toFixed(2) : '0.00';
                
                rows += `
                    <tr>
                        <td>${date}</td>
                        <td>${formatNumber(stat.messages)}</td>
                        <td>${formatNumber(stat.sms_count)}</td>
                        <td>KES ${formatNumber(stat.cost, 2)}</td>
                        <td>KES ${avgCost}</td>
                    </tr>
                `;
            });
            
            return `
                <div class="table-container">
                    <h5><i class="bi bi-calendar-check"></i> Daily Report</h5>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Messages</th>
                                    <th>SMS Parts</th>
                                    <th>Cost (KES)</th>
                                    <th>Avg per Message</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${rows}
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
        }

        // Generate recipients table HTML
        function generateRecipientsTable(recipients) {
            if (!recipients || recipients.length === 0) {
                return `
                    <div class="table-container">
                        <h5><i class="bi bi-trophy"></i> Top 10 Recipients</h5>
                        <div class="no-data-message">
                            <i class="bi bi-people"></i>
                            <p>No data available for selected period</p>
                        </div>
                    </div>
                `;
            }
            
            let rows = '';
            recipients.forEach((recipient, index) => {
                rows += `
                    <tr>
                        <td>${index + 1}</td>
                        <td>${recipient.recipient}</td>
                        <td>${formatNumber(recipient.message_count)}</td>
                        <td>${formatNumber(recipient.total_sms)}</td>
                        <td>KES ${formatNumber(recipient.total_cost, 2)}</td>
                        <td>
                            <a href="send-sms.php?phone=${encodeURIComponent(recipient.recipient)}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-envelope"></i> Send
                            </a>
                        </td>
                    </tr>
                `;
            });
            
            return `
                <div class="table-container">
                    <h5><i class="bi bi-trophy"></i> Top 10 Recipients</h5>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Phone Number</th>
                                    <th>Messages</th>
                                    <th>SMS Parts</th>
                                    <th>Total Cost</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${rows}
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
        }

        // Generate hourly table HTML
        function generateHourlyTable(hourlyStats) {
            if (!hourlyStats) hourlyStats = Array(24).fill(0);
            const total = hourlyStats.reduce((a, b) => a + b, 0);
            
            if (total === 0) {
                return `
                    <div class="table-container">
                        <h5><i class="bi bi-clock"></i> Hourly Distribution</h5>
                        <div class="no-data-message">
                            <i class="bi bi-clock-history"></i>
                            <p>No data available for selected period</p>
                        </div>
                    </div>
                `;
            }
            
            let rows = '';
            for (let i = 0; i < 24; i++) {
                const count = hourlyStats[i] || 0;
                const percentage = total > 0 ? ((count / total) * 100).toFixed(1) : 0;
                const timeRange = `${i.toString().padStart(2, '0')}:00 - ${((i + 1) % 24).toString().padStart(2, '0')}:00`;
                
                rows += `
                    <tr>
                        <td>${i.toString().padStart(2, '0')}:00</td>
                        <td>${timeRange}</td>
                        <td>${formatNumber(count)}</td>
                        <td>
                            ${percentage}%
                            <div class="progress mt-1" style="height: 4px;">
                                <div class="progress-bar" style="width: ${percentage}%; background-color: #1e3a8a;"></div>
                            </div>
                        </td>
                    </tr>
                `;
            }
            
            return `
                <div class="table-container">
                    <h5><i class="bi bi-clock"></i> Hourly Distribution</h5>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Hour</th>
                                    <th>Time Range</th>
                                    <th>Messages</th>
                                    <th>Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${rows}
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
        }

        // Change report type
        function changeReportType(type) {
            currentReportType = type;
            
            // Update active tab
            document.querySelectorAll('#reportTabs .nav-link').forEach(tab => {
                tab.classList.remove('active');
            });
            event.target.classList.add('active');
            
            // Reload tables with new type
            loadAllReports();
        }

        // Export report
        function exportReport(format) {
            showToast(`Exporting as ${format.toUpperCase()}...`, 'info');
            // Implement export functionality here
        }

        // Format number with commas
        function formatNumber(num, decimals = 0) {
            return Number(num).toFixed(decimals).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
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