<?php
// includes/sidebar.php
// This file contains the sidebar navigation menu
// Make sure to include this after session_start()
?>
<!-- Sidebar -->
<div class="sidebar">
    <div class="logo">
        <h3><?php echo APP_NAME; ?></h3>
        <p>SMS Management System</p>
    </div>
    
    <ul class="nav-menu">
        <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
            <a href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
        </li>
        <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'send-sms.php' ? 'active' : ''; ?>">
            <a href="send-sms.php"><i class="bi bi-envelope-paper"></i> Send SMS</a>
        </li>
        <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'bulk-sms.php' ? 'active' : ''; ?>">
            <a href="bulk-sms.php"><i class="bi bi-envelopes"></i> Bulk SMS</a>
        </li>
        <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'sms-logs.php' ? 'active' : ''; ?>">
            <a href="sms-logs.php"><i class="bi bi-journal-text"></i> SMS Logs</a>
        </li>
        <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'api-keys.php' ? 'active' : ''; ?>">
            <a href="api-keys.php"><i class="bi bi-key"></i> API Keys</a>
        </li>
        <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'contacts.php' ? 'active' : ''; ?>">
            <a href="contacts.php"><i class="bi bi-person-lines-fill"></i> Contacts</a>
        </li>
        <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'groups.php' ? 'active' : ''; ?>">
            <a href="groups.php"><i class="bi bi-people"></i> Contact Groups</a>
        </li>
        <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'templates.php' ? 'active' : ''; ?>">
            <a href="templates.php"><i class="bi bi-file-text"></i> Message Templates</a>
        </li>
        <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
            <a href="reports.php"><i class="bi bi-graph-up"></i> Reports</a>
        </li>
        <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'api-docs.php' ? 'active' : ''; ?>">
            <a href="api-docs.php"><i class="bi bi-code-square"></i> API Documentation</a>
        </li>
        <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
            <a href="settings.php"><i class="bi bi-gear"></i> Settings</a>
        </li>
        <li class="nav-item">
            <a href="../logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </li>
    </ul>
</div>

<style>
.sidebar {
    background-color: #1e3a8a;
    width: 250px;
    position: fixed;
    top: 0;
    left: 0;
    bottom: 0;
    z-index: 1001;
    overflow-y: auto;
    padding-top: 20px;
    box-shadow: 2px 0 5px rgba(0,0,0,0.1);
}

.sidebar .logo {
    padding: 0 20px 20px;
    border-bottom: 1px solid #2e4a9a;
    margin-bottom: 20px;
}

.sidebar .logo h3 {
    color: #ffffff;
    font-weight: 600;
    margin: 0;
    font-size: 1.5rem;
}

.sidebar .logo p {
    color: rgba(255,255,255,0.7);
    font-size: 0.8rem;
    margin: 5px 0 0;
}

.sidebar .nav-menu {
    list-style: none;
    padding: 0;
}

.sidebar .nav-item {
    padding: 10px 20px;
    margin: 2px 0;
    cursor: pointer;
    transition: all 0.3s;
}

.sidebar .nav-item:hover {
    background-color: #2e4a9a;
}

.sidebar .nav-item.active {
    background-color: #152b63;
    border-left: 4px solid #ffd700;
}

.sidebar .nav-item a {
    color: #ffffff;
    text-decoration: none;
    display: flex;
    align-items: center;
    font-size: 14px;
}

.sidebar .nav-item i {
    margin-right: 10px;
    width: 20px;
    color: rgba(255,255,255,0.8);
}

.sidebar .nav-item:hover i,
.sidebar .nav-item.active i {
    color: #ffffff;
}

/* Scrollbar styling */
.sidebar::-webkit-scrollbar {
    width: 6px;
}

.sidebar::-webkit-scrollbar-track {
    background: #2e4a9a;
}

.sidebar::-webkit-scrollbar-thumb {
    background: #152b63;
    border-radius: 3px;
}

.sidebar::-webkit-scrollbar-thumb:hover {
    background: #0f1f4a;
}

@media (max-width: 768px) {
    .sidebar {
        left: -250px;
        transition: left 0.3s ease;
    }
    
    .sidebar.active {
        left: 0;
    }
}
</style>