<?php
/**
 * Sidebar UI component
 * --------------------
 * IMPORTANT:
 * - NO redirects here
 * - NO header() calls
 * - NO error_reporting()
 * - Auth must be enforced BEFORE including this file
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Safe defaults
$school_name   = $_SESSION['school_name']   ?? 'School';
$user_name     = $_SESSION['user_fullname'] ?? 'User';
$user_role     = $_SESSION['user_role']     ?? 'Administrator';
$user_initial  = strtoupper(substr($user_name, 0, 1));

// Determine base path for absolute URLs
$base_path = '/'; // Since your app is at root
?>
<!-- Sidebar Container -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="school-title-container">
            <div class="school-title"><?php echo htmlspecialchars($school_name); ?></div>
        </div>
        <button class="sidebar-collapse-btn" id="sidebarCollapse">
            <i class="fas fa-chevron-left"></i>
        </button>
    </div>

    <nav class="nav-links">
        <a href="<?php echo $base_path; ?>dashboard" class="nav-item">
            <i class="fas fa-chart-line"></i><span class="nav-text">Dashboard</span>
        </a>

        <!-- Registration Dropdown -->
        <div class="nav-dropdown">
            <div class="nav-item dropdown-toggle">
                <i class="fas fa-user-plus"></i><span class="nav-text">Registration</span>
                <i class="fas fa-chevron-down dropdown-arrow"></i>
            </div>
            <div class="sidebar-dropdown-menu">
                <a href="<?php echo $base_path; ?>classes" class="sidebar-dropdown-item">
                    <i class="fas fa-school"></i><span>Classes</span>
                </a>
                <a href="<?php echo $base_path; ?>students" class="sidebar-dropdown-item">
                    <i class="fas fa-user-graduate"></i><span>Students</span>
                </a>
                <a href="<?php echo $base_path; ?>studentslist" class="sidebar-dropdown-item">
                    <i class="fas fa-list"></i><span>Students List</span>
                </a>
                <a href="<?php echo $base_path; ?>teachers" class="sidebar-dropdown-item">
                    <i class="fas fa-chalkboard-teacher"></i><span>Teachers</span>
                </a>
                <a href="<?php echo $base_path; ?>subjects" class="sidebar-dropdown-item">
                    <i class="fas fa-book"></i><span>Subjects</span>
                </a>
            </div>
        </div>

        <a href="<?php echo $base_path; ?>roles" class="nav-item">
            <i class="fas fa-user-tag"></i><span class="nav-text">Roles</span>
        </a>

        <!-- Academic Dropdown -->
        <div class="nav-dropdown">
            <div class="nav-item dropdown-toggle">
                <i class="fas fa-graduation-cap"></i><span class="nav-text">Academic</span>
                <i class="fas fa-chevron-down dropdown-arrow"></i>
            </div>
            <div class="sidebar-dropdown-menu">
                <a href="<?php echo $base_path; ?>lessons" class="sidebar-dropdown-item">
                    <i class="fas fa-file-alt"></i><span>Lessons</span>
                </a>
                <a href="<?php echo $base_path; ?>grading" class="sidebar-dropdown-item">
                    <i class="fas fa-check-circle"></i><span>Grading</span>
                </a>
                <a href="<?php echo $base_path; ?>exams" class="sidebar-dropdown-item">
                    <i class="fas fa-clipboard-list"></i><span>Exams</span>
                </a>
            </div>
        </div>

        <a href="<?php echo $base_path; ?>scores" class="nav-item">
            <i class="fas fa-chart-bar"></i><span class="nav-text">Scores</span>
        </a>

<!-- Reports Dropdown -->
<div class="nav-dropdown">
    <div class="nav-item dropdown-toggle">
        <i class="fas fa-file-pdf"></i><span class="nav-text">Reports</span>
        <i class="fas fa-chevron-down dropdown-arrow"></i>
    </div>
    <div class="sidebar-dropdown-menu">
        <a href="<?php echo $base_path; ?>templates" class="sidebar-dropdown-item">
            <i class="fas fa-palette"></i><span>Report Templates</span>
        </a>
        <a href="<?php echo $base_path; ?>reports" class="sidebar-dropdown-item">
            <i class="fas fa-file-alt"></i><span>Report Cards</span>
        </a>
        <a href="<?php echo $base_path; ?>meritlist" class="sidebar-dropdown-item">
            <i class="fas fa-trophy"></i><span>Merit Lists</span>
        </a>
        <a href="<?php echo $base_path; ?>analytics-page" class="sidebar-dropdown-item">
            <i class="fas fa-chart-line"></i><span>Analytics</span>
        </a>
    </div>
</div>

        <a href="<?php echo $base_path; ?>sms" class="nav-item">
            <i class="fas fa-sms"></i><span class="nav-text">Messaging</span>
        </a>

        <!-- Settings Dropdown -->
        <div class="nav-dropdown">
            <div class="nav-item dropdown-toggle">
                <i class="fas fa-cog"></i><span class="nav-text">Settings</span>
                <i class="fas fa-chevron-down dropdown-arrow"></i>
            </div>
            <div class="sidebar-dropdown-menu">
                <a href="<?php echo $base_path; ?>promotion" class="sidebar-dropdown-item">
                    <i class="fas fa-arrow-up"></i><span>Promotions</span>
                </a>
                <a href="<?php echo $base_path; ?>utility" class="sidebar-dropdown-item">
                    <i class="fas fa-tools"></i><span>Utility Settings</span>
                </a>
            </div>
        </div>

        <!-- Timetable -->
        <a href="<?php echo $base_path; ?>timetable" class="nav-item">
            <i class="fas fa-calendar-alt"></i><span class="nav-text">Timetable</span>
        </a>

        <!-- Attendance -->
        <a href="<?php echo $base_path; ?>attendance" class="nav-item">
            <i class="fas fa-check-circle"></i><span class="nav-text">Attendance</span>
        </a>

        <!-- Subscription -->
        <a href="<?php echo $base_path; ?>subscription" class="nav-item">
            <i class="fas fa-credit-card"></i><span class="nav-text">Subscription</span>
        </a>
    </nav>
    
    <div class="sidebar-footer">
        <div class="sidebar-user-info">
            <div class="user-avatar-small">
                <?php echo strtoupper(substr($_SESSION['user_fullname'] ?? 'U', 0, 1)); ?>
            </div>
            <div class="sidebar-user-details">
                <div class="sidebar-user-name"><?php echo htmlspecialchars($_SESSION['user_fullname'] ?? 'User'); ?></div>
                <div class="sidebar-user-role"><?php echo htmlspecialchars($_SESSION['user_role'] ?? 'Administrator'); ?></div>
            </div>
        </div>
    </div>
</aside>

<!-- Overlay for mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<style>
/* =============================
   Modern Slim Sidebar - Matching Header Blue Theme
   ============================= */

:root {
    --sidebar-width: 240px;
    --sidebar-collapsed-width: 70px;
    /* IMPROVED: Brighter blue scheme matching header */
    --sidebar-bg: #1e3a8a;              /* Deep blue base (same as header original) */
    --sidebar-bg-start: #2b4c9e;        /* Brighter blue for gradient start */
    --sidebar-bg-end: #1e3a8a;          /* Original blue for gradient end */
    --sidebar-bg-gradient: linear-gradient(180deg, #2b4c9e 0%, #1e3a8a 100%);
    --sidebar-text: #ffffff;
    --sidebar-text-muted: rgba(255, 255, 255, 0.85);
    --sidebar-accent: #fcd34d;          /* Warm golden yellow for highlights */
    --sidebar-accent-light: #fef3c7;    /* Light yellow for hover */
    --sidebar-hover: rgba(255, 255, 255, 0.12);
    --sidebar-active: rgba(255, 255, 255, 0.2);
    --dropdown-bg: rgba(11, 44, 77, 0.95);
    --dropdown-hover: rgba(255, 255, 255, 0.08);
    --header-height: 70px;
    --mobile-header-height: 60px;
    --transition-speed: 0.3s;
}

/* Base Sidebar Styles */
.sidebar {
    width: var(--sidebar-width);
    height: calc(100vh - var(--header-height));
    position: fixed;
    left: 0;
    top: var(--header-height);
    background: var(--sidebar-bg);
    background: var(--sidebar-bg-gradient);
    padding: 0;
    overflow-y: auto;
    overflow-x: hidden;
    color: var(--sidebar-text);
    transition: all var(--transition-speed) cubic-bezier(0.4, 0, 0.2, 1);
    z-index: 900;
    box-shadow: 2px 0 20px rgba(0, 0, 0, 0.15);
    transform: translateX(0);
    display: flex;
    flex-direction: column;
}

/* Optional subtle animation on load */
@keyframes sidebarGlow {
    0% { background-position: 0% 0%; }
    100% { background-position: 100% 100%; }
}

.sidebar {
    background-size: 100% 200%;
    animation: sidebarGlow 12s ease infinite alternate;
}

/* Collapsed State */
.sidebar.collapsed {
    width: var(--sidebar-collapsed-width);
}

.sidebar.collapsed .sidebar-header .school-title-container,
.sidebar.collapsed .nav-text,
.sidebar.collapsed .dropdown-arrow,
.sidebar.collapsed .user-details {
    opacity: 0;
    visibility: hidden;
    width: 0;
    height: 0;
    margin: 0;
    padding: 0;
    white-space: nowrap;
    overflow: hidden;
}

.sidebar.collapsed .sidebar-header .sidebar-collapse-btn i {
    transform: rotate(180deg);
}

/* Sidebar Header - Fixed */
.sidebar-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 15px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.15);
    position: relative;
    min-height: 60px;
    flex-shrink: 0;
    background: rgba(0, 0, 0, 0.05);
}

.school-title-container {
    flex: 1;
    min-width: 0;
    display: flex;
    align-items: center;
}

.school-title {
    font-size: 14px;
    font-weight: 700;
    letter-spacing: 0.3px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
    line-height: 1.2;
    padding: 0;
    margin: 0;
    color: var(--sidebar-text);
}

.sidebar-collapse-btn {
    background: rgba(255, 255, 255, 0.1);
    border: none;
    border-radius: 8px;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--sidebar-text);
    cursor: pointer;
    transition: all 0.2s ease;
    flex-shrink: 0;
    margin-left: 8px;
}

.sidebar-collapse-btn:hover {
    background: rgba(252, 211, 77, 0.2);
    transform: scale(1.05);
    color: var(--sidebar-accent);
}

.sidebar-collapse-btn i {
    font-size: 12px;
    transition: transform 0.3s ease;
}

/* Navigation Links - Organized with proper spacing */
.nav-links {
    padding: 15px 0;
    display: flex;
    flex-direction: column;
    gap: 4px;
    flex: 1;
    overflow-y: auto;
}

.nav-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    color: var(--sidebar-text-muted);
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.2s ease;
    position: relative;
    cursor: pointer;
    border-left: 3px solid transparent;
    white-space: nowrap;
    overflow: hidden;
    margin: 0 8px;
    border-radius: 8px;
    min-height: 44px;
    box-sizing: border-box;
}

.nav-item:hover {
    background: linear-gradient(90deg, rgba(252, 211, 77, 0.15), var(--sidebar-hover));
    color: var(--sidebar-text);
    border-left-color: var(--sidebar-accent);
    padding-left: 13px;
}

.nav-item.active {
    background: linear-gradient(90deg, rgba(252, 211, 77, 0.2), var(--sidebar-active));
    color: var(--sidebar-text);
    border-left-color: var(--sidebar-accent);
    font-weight: 600;
    box-shadow: inset 3px 0 10px rgba(252, 211, 77, 0.2);
}

.nav-item i {
    font-size: 16px;
    width: 20px;
    text-align: center;
    flex-shrink: 0;
    transition: transform 0.2s ease;
}

.nav-item:hover i {
    transform: scale(1.1);
    color: var(--sidebar-accent);
}

.nav-text {
    transition: all var(--transition-speed) ease;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    flex: 1;
}

/* Dropdown Styles - Organized */
.nav-dropdown {
    position: relative;
}

.dropdown-toggle {
    justify-content: space-between;
    padding-right: 12px;
    cursor: pointer;
}

.dropdown-arrow {
    font-size: 10px;
    transition: transform 0.3s ease;
    flex-shrink: 0;
}

.nav-dropdown.active .dropdown-arrow {
    transform: rotate(180deg);
    color: var(--sidebar-accent);
}

.sidebar-dropdown-menu {
    display: none;
    background: rgba(30, 58, 138, 0.92);
    backdrop-filter: blur(4px);
    border-left: 3px solid var(--sidebar-accent);
    margin: 4px 8px 4px 23px;
    border-radius: 0 8px 8px 0;
    overflow: hidden;
    animation: slideDown 0.3s ease forwards;
    z-index: 902;
}

.nav-dropdown.active .sidebar-dropdown-menu {
    display: block;
}

/* Dropdown items - organized and visible */
.sidebar-dropdown-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 15px 10px 25px;
    color: var(--sidebar-text-muted) !important;
    text-decoration: none;
    font-size: 13px;
    transition: all 0.2s ease;
    border-left: 2px solid transparent;
    white-space: nowrap;
    background: transparent;
    min-height: 40px;
    box-sizing: border-box;
}

.sidebar-dropdown-item:hover {
    background: linear-gradient(90deg, rgba(252, 211, 77, 0.15), rgba(255, 255, 255, 0.08));
    color: var(--sidebar-accent) !important;
    border-left-color: var(--sidebar-accent);
    padding-left: 23px;
}

.sidebar-dropdown-item i {
    font-size: 14px;
    width: 16px;
    color: var(--sidebar-text-muted) !important;
    flex-shrink: 0;
    transition: color 0.2s ease;
}

.sidebar-dropdown-item:hover i {
    color: var(--sidebar-accent) !important;
}

.sidebar-dropdown-item.active {
    background: linear-gradient(90deg, rgba(252, 211, 77, 0.25), rgba(255, 255, 255, 0.12));
    color: var(--sidebar-accent) !important;
    border-left-color: var(--sidebar-accent);
    font-weight: 600;
}

.sidebar-dropdown-item.active i {
    color: var(--sidebar-accent) !important;
}

.sidebar-dropdown-item span {
    color: inherit !important;
    flex: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Collapsed state dropdown */
.sidebar.collapsed .nav-dropdown.active .sidebar-dropdown-menu {
    position: fixed;
    left: var(--sidebar-collapsed-width);
    top: auto;
    min-width: 200px;
    background: rgba(30, 58, 138, 0.96);
    backdrop-filter: blur(8px);
    box-shadow: 5px 5px 15px rgba(0, 0, 0, 0.3);
    border-radius: 0 8px 8px 0;
    z-index: 901;
    margin: 0;
    display: block !important;
    animation: slideRight 0.3s ease forwards;
}

@keyframes slideRight {
    from {
        opacity: 0;
        transform: translateX(-10px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

/* Sidebar Footer - Fixed at bottom */
.sidebar-footer {
    padding: 15px;
    border-top: 1px solid rgba(255, 255, 255, 0.15);
    background: rgba(0, 0, 0, 0.1);
    backdrop-filter: blur(4px);
    flex-shrink: 0;
}

.sidebar-user-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.user-avatar-small {
    width: 36px;
    height: 36px;
    background: linear-gradient(135deg, var(--sidebar-accent), #f59e0b);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 14px;
    color: #1e3a8a;
    flex-shrink: 0;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    transition: transform 0.2s ease;
}

.sidebar-user-info:hover .user-avatar-small {
    transform: scale(1.05);
}

.sidebar-user-details {
    flex: 1;
    min-width: 0;
    transition: all var(--transition-speed) ease;
}

.sidebar-user-name {
    font-size: 13px;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: var(--sidebar-text);
}

.sidebar-user-role {
    font-size: 11px;
    color: var(--sidebar-accent);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Mobile overlay */
.sidebar-overlay {
    display: none;
    position: fixed;
    top: var(--mobile-header-height);
    left: 0;
    width: 100%;
    height: calc(100vh - var(--mobile-header-height));
    background: rgba(0, 0, 0, 0.5);
    z-index: 899;
    backdrop-filter: blur(3px);
}

/* Mobile responsive */
@media (max-width: 992px) {
    .sidebar-overlay.show {
        display: block;
    }
    
    .sidebar {
        transform: translateX(-100%);
        box-shadow: 5px 0 30px rgba(0, 0, 0, 0.3);
        z-index: 999;
        top: var(--mobile-header-height);
        height: calc(100vh - var(--mobile-header-height));
        width: 260px;
    }
    
    .sidebar.show {
        transform: translateX(0);
        z-index: 1000;
    }
    
    .sidebar-overlay {
        top: var(--mobile-header-height);
        height: calc(100vh - var(--mobile-header-height));
        z-index: 998;
    }
    
    .sidebar-header {
        padding-top: 15px;
        padding-bottom: 15px;
    }
}

/* Main Content Adjustment */
.main-content {
    margin-left: var(--sidebar-width);
    transition: margin-left var(--transition-speed) cubic-bezier(0.4, 0, 0.2, 1);
    min-height: calc(100vh - var(--header-height));
    background: #f8fafc;
    position: relative;
    width: calc(100% - var(--sidebar-width));
    padding: 20px;
    padding-top: calc(var(--header-height) + 20px);
}

.sidebar.collapsed ~ .main-content {
    margin-left: var(--sidebar-collapsed-width);
    width: calc(100% - var(--sidebar-collapsed-width));
}

@media (max-width: 992px) {
    .main-content {
        margin-left: 0 !important;
        width: 100% !important;
        padding: 15px;
        padding-top: calc(var(--mobile-header-height) + 15px);
        min-height: calc(100vh - var(--mobile-header-height));
    }
    
    .sidebar.show ~ .main-content {
        margin-left: 0 !important;
    }
}

/* Scrollbar styling - matches theme */
.sidebar::-webkit-scrollbar {
    width: 4px;
}

.sidebar::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.05);
}

.sidebar::-webkit-scrollbar-thumb {
    background: rgba(252, 211, 77, 0.3);
    border-radius: 2px;
}

.sidebar::-webkit-scrollbar-thumb:hover {
    background: rgba(252, 211, 77, 0.6);
}

/* Animations */
@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
        max-height: 0;
    }
    to {
        opacity: 1;
        transform: translateY(0);
        max-height: 500px;
    }
}

/* Tooltip for collapsed state */
.sidebar.collapsed .nav-item,
.sidebar.collapsed .dropdown-toggle {
    position: relative;
}

.sidebar.collapsed .nav-item:hover::after,
.sidebar.collapsed .dropdown-toggle:hover::after {
    content: attr(data-tooltip);
    position: absolute;
    left: calc(var(--sidebar-collapsed-width) + 10px);
    top: 50%;
    transform: translateY(-50%);
    background: linear-gradient(135deg, #1e3a8a, #2b4c9e);
    color: var(--sidebar-text) !important;
    padding: 8px 12px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 500;
    white-space: nowrap;
    z-index: 903;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    pointer-events: none;
    border-left: 3px solid var(--sidebar-accent);
    letter-spacing: 0.3px;
}

/* Ensure body has no gaps */
body {
    margin: 0;
    padding: 0;
    overflow-x: hidden;
    background: #f8fafc;
}

/* Mobile responsive adjustments */
@media (max-width: 768px) {
    .sidebar {
        width: 260px;
    }
    
    .sidebar.collapsed {
        width: 70px;
    }
    
    .main-content {
        padding: 15px;
        padding-top: calc(var(--header-height) + 15px);
    }
}

@media (max-width: 576px) {
    .sidebar {
        width: 240px;
    }
    
    .sidebar-header {
        padding: 12px;
    }
    
    .nav-item {
        padding: 10px 12px;
        font-size: 13px;
        margin: 0 6px;
    }
    
    .sidebar-dropdown-item {
        padding: 8px 12px 8px 20px;
        font-size: 12px;
    }
    
    .main-content {
        padding: 12px;
        padding-top: calc(var(--mobile-header-height) + 12px);
    }
}

/* Smooth transition for all affected elements */
.sidebar,
.sidebar *,
.main-content {
    transition-duration: var(--transition-speed);
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
}
</style>

<script>
// Enhanced Sidebar functionality with proper dropdown handling
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const sidebarCollapse = document.getElementById('sidebarCollapse');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const sidebarSettingsBtn = document.getElementById('sidebarSettingsBtn');
    const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
    const mainContent = document.querySelector('.main-content');
    
    // Function to update main content width
    function updateMainContentWidth() {
        if (!mainContent) return;
        
        const isCollapsed = sidebar.classList.contains('collapsed');
        const isMobile = window.innerWidth <= 992;
        
        if (isMobile) {
            mainContent.style.marginLeft = '0';
            mainContent.style.width = '100%';
        } else if (isCollapsed) {
            mainContent.style.marginLeft = '70px';
            mainContent.style.width = 'calc(100% - 70px)';
        } else {
            mainContent.style.marginLeft = '240px';
            mainContent.style.width = 'calc(100% - 240px)';
        }
    }
    
    // Toggle sidebar collapse (internal button)
    if (sidebarCollapse) {
        sidebarCollapse.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            sidebar.classList.toggle('collapsed');
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
            updateMainContentWidth();
            
            // Close all dropdowns when collapsing
            if (sidebar.classList.contains('collapsed')) {
                document.querySelectorAll('.nav-dropdown').forEach(dropdown => {
                    dropdown.classList.remove('active');
                });
            }
        });
    }
    
    // Load sidebar state from localStorage
    function loadSidebarState() {
        const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        if (isCollapsed) {
            sidebar.classList.add('collapsed');
        }
        updateMainContentWidth();
    }

    // Listen for toggle requests from header
    window.addEventListener('toggleSidebarRequest', function() {
        console.log('Toggle sidebar request received from header');
        
        if (window.innerWidth <= 992) {
            // Mobile behavior
            const isShowing = sidebar.classList.contains('show');
            if (isShowing) {
                closeSidebar();
            } else {
                openSidebar();
            }
        } else {
            // Desktop behavior
            sidebar.classList.toggle('collapsed');
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
            updateMainContentWidth();
            
            // Close dropdowns when toggling
            if (sidebar.classList.contains('collapsed')) {
                document.querySelectorAll('.nav-dropdown').forEach(dropdown => {
                    dropdown.classList.remove('active');
                });
            }
        }
    });

    // Helper functions
    function openSidebar() {
        sidebar.classList.add('show');
        if (sidebarOverlay) sidebarOverlay.classList.add('show');
        document.body.style.overflow = 'hidden';
        console.log('Sidebar opened');
    }

    function closeSidebar() {
        sidebar.classList.remove('show');
        if (sidebarOverlay) sidebarOverlay.classList.remove('show');
        document.body.style.overflow = '';
        console.log('Sidebar closed');
    }

    // Set active dropdown based on current page
    function setActiveDropdown() {
        const currentPath = window.location.pathname;
        // Get the last part of the path (e.g., 'roles' from '/roles' or 'reports' from '/reports/')
        let currentPage = currentPath.split('/').pop();
        // Handle case where URL ends with slash (e.g., '/reports/')
        if (currentPage === '') {
            currentPage = currentPath.split('/').slice(-2, -1)[0];
        }
        
        // Map of pages to check (without .php)
        const registrationPages = ['classes', 'students', 'studentslist', 'teachers', 'subjects'];
        const academicPages = ['lessons', 'grading', 'exams'];
        const reportsPages = ['meritlist', 'analytics-page'];
        const standalonePages = ['dashboard', 'scores', 'sms', 'promotion', 'subscription', 'settings', 'roles', 'timetable', 'attendance', 'reports'];
        
        // Remove all active classes first
        document.querySelectorAll('.nav-dropdown').forEach(dropdown => {
            dropdown.classList.remove('active');
        });
        document.querySelectorAll('.nav-item, .sidebar-dropdown-item').forEach(item => {
            item.classList.remove('active');
        });
        
        // Check if current page is in registration dropdown
        if (registrationPages.includes(currentPage)) {
            const registrationDropdown = document.querySelector('.nav-dropdown:nth-child(2)');
            if (registrationDropdown) {
                registrationDropdown.classList.add('active');
            }
            
            const activeItem = document.querySelector(`.sidebar-dropdown-item[href$="${currentPage}"]`);
            if (activeItem) {
                activeItem.classList.add('active');
            }
        }
        
        // Check if current page is in academic dropdown
        else if (academicPages.includes(currentPage)) {
            const academicDropdown = document.querySelector('.nav-dropdown:nth-child(4)');
            if (academicDropdown) {
                academicDropdown.classList.add('active');
            }
            
            const activeItem = document.querySelector(`.sidebar-dropdown-item[href$="${currentPage}"]`);
            if (activeItem) {
                activeItem.classList.add('active');
            }
        }
        
        // Check if current page is in reports dropdown
        else if (reportsPages.includes(currentPage)) {
            const reportsDropdown = document.querySelector('.nav-dropdown:nth-child(6)');
            if (reportsDropdown) {
                reportsDropdown.classList.add('active');
            }
            
            const activeItem = document.querySelector(`.sidebar-dropdown-item[href$="${currentPage}"]`);
            if (activeItem) {
                activeItem.classList.add('active');
            }
        }
        
        // Check for standalone pages (including reports which is now standalone)
        else if (standalonePages.includes(currentPage)) {
            const activeItem = document.querySelector(`.nav-item[href$="${currentPage}"]`);
            if (activeItem) {
                activeItem.classList.add('active');
            }
        }
    }
    
    // Add tooltip data for collapsed state
    function addTooltips() {
        document.querySelectorAll('.nav-item').forEach(item => {
            const text = item.querySelector('.nav-text')?.textContent || '';
            item.setAttribute('data-tooltip', text.trim());
        });
    }
    
    // Enhanced Dropdown toggle functionality for both mobile and desktop
    dropdownToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const dropdown = this.parentElement;
            const isActive = dropdown.classList.contains('active');
            
            // Close all other dropdowns
            document.querySelectorAll('.nav-dropdown').forEach(d => {
                if (d !== dropdown) {
                    d.classList.remove('active');
                }
            });
            
            // Toggle current dropdown
            if (!isActive) {
                dropdown.classList.add('active');
            } else {
                dropdown.classList.remove('active');
            }
        });
    });
    
    // Close dropdowns when clicking outside (for desktop)
    document.addEventListener('click', function(e) {
        if (window.innerWidth > 992) {
            if (!e.target.closest('.nav-dropdown') && !e.target.closest('.sidebar-dropdown-menu')) {
                document.querySelectorAll('.nav-dropdown').forEach(dropdown => {
                    dropdown.classList.remove('active');
                });
            }
        }
    });
    
    // Close sidebar when clicking overlay
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function() {
            closeSidebar();
        });
    }
    
    // Auto-close sidebar on mobile when clicking a link
    document.querySelectorAll('.nav-item, .sidebar-dropdown-item').forEach(item => {
        item.addEventListener('click', function(e) {
            if (window.innerWidth <= 992 && !this.classList.contains('dropdown-toggle')) {
                closeSidebar();
            }
        });
    });
    
    // Handle window resize
    function handleResize() {
        if (window.innerWidth > 992) {
            // Desktop - remove mobile overlay and show state
            closeSidebar();
            
            // Restore collapsed state from localStorage
            const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            if (isCollapsed) {
                sidebar.classList.add('collapsed');
            } else {
                sidebar.classList.remove('collapsed');
            }
            
            // Ensure sidebar is positioned correctly
            sidebar.style.top = 'var(--header-height)';
            sidebar.style.height = 'calc(100vh - var(--header-height))';
            if (sidebarOverlay) {
                sidebarOverlay.style.top = 'var(--header-height)';
                sidebarOverlay.style.height = 'calc(100vh - var(--header-height))';
            }
        } else {
            // Mobile - ensure sidebar is hidden by default
            sidebar.classList.remove('collapsed');
            sidebar.classList.remove('show');
            if (sidebarOverlay) sidebarOverlay.classList.remove('show');
            document.body.style.overflow = '';
            
            // Ensure sidebar is positioned correctly for mobile
            sidebar.style.top = 'var(--mobile-header-height)';
            sidebar.style.height = 'calc(100vh - var(--mobile-header-height))';
            if (sidebarOverlay) {
                sidebarOverlay.style.top = 'var(--mobile-header-height)';
                sidebarOverlay.style.height = 'calc(100vh - var(--mobile-header-height))';
            }
        }
        updateMainContentWidth();
    }
    
    // Initialize
    loadSidebarState();
    setActiveDropdown();
    addTooltips();
    handleResize();
    
    // Event listeners
    window.addEventListener('resize', handleResize);
    
    // Make functions available globally
    window.updateMainContentWidth = updateMainContentWidth;
    window.openSidebar = openSidebar;
    window.closeSidebar = closeSidebar;
    
    // Dispatch sidebar state change function
    function dispatchSidebarStateChanged() {
        window.dispatchEvent(new CustomEvent('sidebarStateChanged'));
    }
    
    // Override toggle functions to dispatch events
    const originalToggle = sidebarCollapse?.click;
    if (sidebarCollapse) {
        sidebarCollapse.addEventListener('click', function() {
            setTimeout(dispatchSidebarStateChanged, 50);
        });
    }
    
    window.dispatchSidebarStateChanged = dispatchSidebarStateChanged;
    window.setActiveDropdown = setActiveDropdown;
});
</script>