<?php
// includes/sidebar.php - Sidebar Navigation
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="dashboard.php" class="logo">
            <img src="../images/logo.png" alt="EduScore logo">
        </a>
        <button class="sidebar-close" id="sidebarClose">
            <i class="fas fa-times"></i>
        </button>
    </div>
    
    <nav class="sidebar-nav">
        <div class="nav-item">
            <a href="dashboard.php" class="nav-link <?php echo $current_page == 'dashboard' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
        </div>
        
        <div class="nav-item">
            <a href="performance.php" class="nav-link <?php echo $current_page == 'performance' ? 'active' : ''; ?>">
                <i class="fas fa-chart-line"></i>
                <span>Performance</span>
            </a>
        </div>
        
        <div class="nav-item">
            <a href="fee-balance.php" class="nav-link <?php echo $current_page == 'fee-balance' ? 'active' : ''; ?>">
                <i class="fas fa-coins"></i>
                <span>Fee Balance</span>
            </a>
        </div>
        
        <div class="nav-item dropdown">
            <button class="dropdown-toggle" id="reportsDropdown">
                <span><i class="fas fa-file-alt"></i> Reports</span>
                <i class="fas fa-chevron-down"></i>
            </button>
            <div class="dropdown-menu" id="reportsMenu">
                <a href="report-card.php" class="nav-link <?php echo $current_page == 'report-card' ? 'active' : ''; ?>">
                    <i class="fas fa-graduation-cap"></i>
                    <span>Report Cards</span>
                </a>
                <a href="fee-report.php" class="nav-link <?php echo $current_page == 'fee-report' ? 'active' : ''; ?>">
                    <i class="fas fa-receipt"></i>
                    <span>Fee Reports</span>
                </a>
            </div>
        </div>
    </nav>
</aside>

<script>
// Sidebar toggle functionality
document.addEventListener('DOMContentLoaded', function() {
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    const sidebarClose = document.getElementById('sidebarClose');
    
    if (menuToggle) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
        });
    }
    
    if (sidebarClose) {
        sidebarClose.addEventListener('click', function() {
            sidebar.classList.remove('open');
        });
    }
    
    // Dropdown toggle
    const reportsDropdown = document.getElementById('reportsDropdown');
    const reportsMenu = document.getElementById('reportsMenu');
    
    if (reportsDropdown && reportsMenu) {
        reportsDropdown.addEventListener('click', function() {
            reportsDropdown.classList.toggle('active');
            reportsMenu.classList.toggle('show');
        });
    }
    
    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(event) {
        if (window.innerWidth <= 992) {
            if (sidebar && sidebar.classList.contains('open')) {
                if (!sidebar.contains(event.target) && !menuToggle.contains(event.target)) {
                    sidebar.classList.remove('open');
                }
            }
        }
    });
});
</script>