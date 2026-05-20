<?php
// admin/sms_dashboard.php
session_start();
require_once '../config/db.php';
require_once '../includes/SMSApiGenerator.php';
require_once '../includes/MPesaPayment.php';

// Check admin authentication
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$apiGenerator = new SMSApiGenerator($conn);
$mpesa = new MPesaPayment($conn);

// Get statistics
$school_count = $conn->query("SELECT COUNT(*) as total FROM tblschoolinfo WHERE status = 'approved'")->fetch_assoc()['total'];
$active_apis = $conn->query("SELECT COUNT(*) as total FROM sms_api_credentials WHERE status = 'active'")->fetch_assoc()['total'];
$total_credits = $conn->query("SELECT SUM(total_credits_purchased) as total FROM sms_credits")->fetch_assoc()['total'] ?? 0;
$recent_transactions = $conn->query("
    SELECT t.*, s.school_name 
    FROM sms_mpesa_transactions t
    JOIN tblschoolinfo s ON t.school_id = s.id
    WHERE t.status = 'completed'
    ORDER BY t.completed_at DESC
    LIMIT 10
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduScore SMS Management Dashboard</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #f5f5f5; }
        
        /* Color theme */
        .bg-white { background-color: #ffffff; }
        .bg-blue { background-color: #2196F3; }
        .bg-green { background-color: #4CAF50; }
        .bg-yellow { background-color: #FFC107; }
        
        .text-blue { color: #2196F3; }
        .text-green { color: #4CAF50; }
        .text-yellow { color: #FFC107; }
        
        /* Header */
        .header { background-color: #2196F3; color: white; padding: 1rem 2rem; }
        .header h1 { margin: 0; font-size: 24px; }
        
        /* Sidebar */
        .sidebar {
            width: 250px;
            background-color: #2196F3;
            color: white;
            position: fixed;
            height: 100vh;
            padding: 20px 0;
        }
        .sidebar a {
            display: block;
            color: white;
            text-decoration: none;
            padding: 12px 20px;
            margin: 5px 0;
            transition: 0.3s;
        }
        .sidebar a:hover { background-color: #1976D2; }
        .sidebar a.active { background-color: #1976D2; border-left: 4px solid #FFC107; }
        
        /* Main content */
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        
        /* Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stat-card h3 { color: #666; font-size: 14px; margin-bottom: 10px; }
        .stat-card .number { font-size: 32px; font-weight: bold; color: #2196F3; }
        
        /* Buttons */
        .btn {
            display: inline-block;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            transition: 0.3s;
        }
        .btn-green { background-color: #4CAF50; color: white; }
        .btn-green:hover { background-color: #45a049; }
        .btn-blue { background-color: #2196F3; color: white; }
        .btn-blue:hover { background-color: #1976D2; }
        
        /* Spinner */
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #FFC107;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        
        /* Tables */
        .table {
            width: 100%;
            background: white;
            border-collapse: collapse;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .table th {
            background-color: #2196F3;
            color: white;
            padding: 12px;
            text-align: left;
        }
        .table td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
        }
        .table tr:hover { background-color: #f5f5f5; }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
        }
        .modal-content {
            background: white;
            width: 500px;
            margin: 100px auto;
            padding: 20px;
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div style="padding: 20px; text-align: center;">
            <h2>EduScore SMS</h2>
        </div>
        <a href="sms_dashboard.php" class="active">Dashboard</a>
        <a href="sms_schools.php">Schools</a>
        <a href="sms_apis.php">API Keys</a>
        <a href="sms_packages.php">Packages</a>
        <a href="sms_transactions.php">Transactions</a>
        <a href="sms_reports.php">Reports</a>
        <a href="sms_settings.php">Settings</a>
    </div>
    
    <div class="main-content">
        <div class="header">
            <h1>SMS Service Dashboard</h1>
        </div>
        
        <div style="padding: 20px;">
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Registered Schools</h3>
                    <div class="number"><?php echo $school_count; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Active API Keys</h3>
                    <div class="number"><?php echo $active_apis; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Total SMS Credits</h3>
                    <div class="number"><?php echo number_format($total_credits); ?></div>
                </div>
                <div class="stat-card">
                    <h3>Revenue (KSh)</h3>
                    <div class="number"><?php 
                        $revenue = $conn->query("SELECT SUM(amount) as total FROM sms_mpesa_transactions WHERE status = 'completed'")->fetch_assoc()['total'];
                        echo number_format($revenue ?? 0, 2);
                    ?></div>
                </div>
            </div>
            
            <div style="display: flex; gap: 10px; margin-bottom: 20px;">
                <button class="btn btn-green" onclick="showAddSchoolModal()">
                    Add New School
                </button>
                <button class="btn btn-blue" onclick="showPackageModal()">
                    Manage Packages
                </button>
            </div>
            
            <h2 style="margin-bottom: 20px;">Recent Transactions</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>School</th>
                        <th>Phone</th>
                        <th>Amount</th>
                        <th>Credits</th>
                        <th>M-Pesa Receipt</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $recent_transactions->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['school_name']); ?></td>
                        <td><?php echo $row['phone_number']; ?></td>
                        <td>KSh <?php echo number_format($row['amount'], 2); ?></td>
                        <td><?php echo number_format($row['credits_purchased']); ?></td>
                        <td><?php echo $row['mpesa_receipt'] ?? 'Pending'; ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($row['completed_at'] ?? $row['created_at'])); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Add School Modal -->
    <div id="addSchoolModal" class="modal">
        <div class="modal-content">
            <h3>Add School to SMS Service</h3>
            <form id="addSchoolForm">
                <div style="margin-bottom: 15px;">
                    <label>Select School:</label>
                    <select name="school_id" style="width: 100%; padding: 8px; margin-top: 5px;">
                        <?php
                        $schools = $conn->query("SELECT id, school_name FROM tblschoolinfo WHERE status = 'approved'");
                        while($school = $schools->fetch_assoc()) {
                            echo "<option value='{$school['id']}'>{$school['school_name']}</option>";
                        }
                        ?>
                    </select>
                </div>
                <div style="margin-bottom: 15px;">
                    <label>Select Package:</label>
                    <select name="package_id" style="width: 100%; padding: 8px; margin-top: 5px;">
                        <?php
                        $packages = $conn->query("SELECT * FROM sms_packages WHERE is_active = 1");
                        while($pkg = $packages->fetch_assoc()) {
                            echo "<option value='{$pkg['id']}'>{$pkg['package_name']} - {$pkg['credits']} credits (KSh {$pkg['price']})</option>";
                        }
                        ?>
                    </select>
                </div>
                <div style="margin-bottom: 15px;">
                    <label>Phone Number (M-Pesa):</label>
                    <input type="text" name="phone" required style="width: 100%; padding: 8px; margin-top: 5px;">
                </div>
                <div style="text-align: right;">
                    <button type="button" class="btn" onclick="hideModal('addSchoolModal')" style="background: #999; color: white; margin-right: 10px;">Cancel</button>
                    <button type="submit" class="btn btn-green">Process Payment</button>
                </div>
            </form>
            <div id="paymentSpinner" class="spinner" style="display: none;"></div>
        </div>
    </div>
    
    <script>
        function showAddSchoolModal() {
            document.getElementById('addSchoolModal').style.display = 'block';
        }
        
        function hideModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        document.getElementById('addSchoolForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            document.getElementById('paymentSpinner').style.display = 'block';
            
            var formData = new FormData(this);
            
            fetch('process_payment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('paymentSpinner').style.display = 'none';
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
        });
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>