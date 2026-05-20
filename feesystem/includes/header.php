<?php
if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

// Get current academic level from session or set default
if (!isset($_SESSION['academic_level'])) {
    $_SESSION['academic_level'] = 'primary';
}

// Handle academic level change via AJAX or POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['academic_level'])) {
    $_SESSION['academic_level'] = $_POST['academic_level'];
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'academic_level' => $_SESSION['academic_level']]);
        exit;
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Get school info from database
$school_id = $_SESSION['school_id'] ?? 0;
$school_info = [];

if ($school_id > 0) {
    try {
        require_once 'config.php';
        $stmt = $db->prepare("SELECT school_name, school_logo, school_motto, school_phone, school_email, institution_level, principal_name FROM tblschoolinfo WHERE id = ?");
        $stmt->execute([$school_id]);
        $school_info = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$school_info) $school_info = [];
    } catch (Exception $e) {
        error_log("School info fetch error: " . $e->getMessage());
    }
}

$school_name = $school_info['school_name'] ?? $_SESSION['school_name'] ?? 'EduScore';
$school_logo = $school_info['school_logo'] ?? null;
$school_motto = $school_info['school_motto'] ?? 'Excellence in Education';
$academic_level = $_SESSION['academic_level'];

// Get user avatar
$user_avatar = null;
$user_avatar_paths = [
    '../assets/images/avatars/' . $_SESSION['user_id'] . '.jpg',
    '../assets/images/avatars/' . $_SESSION['user_id'] . '.png',
    '../assets/images/avatars/default.jpg',
    '../assets/images/default-avatar.png'
];

foreach ($user_avatar_paths as $path) {
    if (file_exists($path)) {
        $user_avatar = $path;
        break;
    }
}

$user_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User';
$avatar_url = $user_avatar ?: 'https://ui-avatars.com/api/?name=' . urlencode($user_name) . '&background=0D9488&color=fff&size=128&rounded=true&bold=true&length=2';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>EduScore Fee Management System - <?php echo htmlspecialchars($school_name); ?></title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <style>
        /* ============================================
           MODERN RESPONSIVE HEADER WITH HAMBURGER MENU
           ============================================ */
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        /* Topbar Container */
        .topbar {
            background: #ffffff;
            border-bottom: 1px solid #e9ecef;
            position: sticky;
            top: 0;
            z-index: 1000;
            width: 100%;
        }
        
        /* Main Navigation Container */
        .navbar-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 1rem;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        @media (min-width: 640px) {
            .navbar-container {
                padding: 0.75rem 1.5rem;
            }
        }
        
        @media (min-width: 1024px) {
            .navbar-container {
                padding: 0.75rem 2rem;
            }
        }
        
        /* Logo Section */
        .logo-section {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-shrink: 0;
        }
        
        .logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #0d9488, #0f766e);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.2s ease;
        }
        
        .logo-icon:hover {
            transform: scale(1.05);
        }
        
        .logo-icon i {
            font-size: 1.25rem;
            color: white;
        }
        
        .school-logo-img {
            height: 40px;
            width: auto;
            object-fit: contain;
            border-radius: 8px;
        }
        
        .brand-text {
            display: none;
        }
        
        @media (min-width: 640px) {
            .brand-text {
                display: block;
            }
        }
        
        .school-name {
            font-size: 0.9rem;
            font-weight: 700;
            color: #1e293b;
            line-height: 1.2;
        }
        
        .school-motto {
            font-size: 0.65rem;
            color: #64748b;
        }
        
        /* Desktop Navigation Links */
        .nav-links {
            display: none;
            align-items: center;
            gap: 1.5rem;
        }
        
        @media (min-width: 992px) {
            .nav-links {
                display: flex;
            }
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0.75rem;
            color: #475569;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.2s ease;
        }
        
        .nav-link:hover {
            background-color: #f1f5f9;
            color: #0d9488;
        }
        
        .nav-link i {
            font-size: 1rem;
            width: 20px;
        }
        
        .nav-link.active {
            background-color: #e6f7f5;
            color: #0d9488;
        }
        
        /* Search Container */
        .search-container {
            position: relative;
            margin: 0 0.5rem;
        }
        
        .search-input {
            padding: 0.5rem 0.75rem 0.5rem 2.25rem;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            font-size: 0.875rem;
            width: 200px;
            transition: all 0.2s ease;
            background: #f8fafc;
        }
        
        .search-input:focus {
            outline: none;
            border-color: #0d9488;
            background: white;
            width: 250px;
        }
        
        .search-icon {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 0.875rem;
        }
        
        .search-results {
            position: absolute;
            top: 100%;
            right: 0;
            width: 320px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
            border: 1px solid #e9ecef;
            z-index: 1000;
            max-height: 400px;
            overflow-y: auto;
            display: none;
        }
        
        .search-results.show {
            display: block;
        }
        
        .search-result-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            text-decoration: none;
            transition: all 0.2s ease;
            border-bottom: 1px solid #f0f2f4;
        }
        
        .search-result-item:last-child {
            border-bottom: none;
        }
        
        .search-result-item:hover {
            background-color: #f8fafc;
        }
        
        .search-result-avatar {
            width: 36px;
            height: 36px;
            background: #e6f7f5;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #0d9488;
        }
        
        .search-result-info {
            flex: 1;
        }
        
        .search-result-name {
            font-size: 0.875rem;
            font-weight: 600;
            color: #1e293b;
        }
        
        .search-result-details {
            font-size: 0.7rem;
            color: #64748b;
        }
        
        .search-result-adm {
            font-size: 0.7rem;
            color: #0d9488;
            font-weight: 500;
        }
        
        @media (max-width: 768px) {
            .search-container {
                display: none;
            }
        }
        
        /* Mobile Search (visible on small screens) */
        .mobile-search-container {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #e9ecef;
            margin-bottom: 0.5rem;
        }
        
        .mobile-search-input {
            width: 100%;
            padding: 0.6rem 0.75rem 0.6rem 2.25rem;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            font-size: 0.875rem;
            background: #f8fafc;
        }
        
        .mobile-search-input:focus {
            outline: none;
            border-color: #0d9488;
            background: white;
        }
        
        /* Right Section */
        .right-section {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        @media (min-width: 640px) {
            .right-section {
                gap: 1rem;
            }
        }
        
        /* Hamburger Menu Button */
        .hamburger {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            width: 30px;
            height: 21px;
            background: transparent;
            border: none;
            cursor: pointer;
            padding: 0;
            z-index: 1010;
        }
        
        .hamburger span {
            width: 100%;
            height: 3px;
            background-color: #475569;
            border-radius: 3px;
            transition: all 0.3s ease;
        }
        
        .hamburger.active span:nth-child(1) {
            transform: translateY(9px) rotate(45deg);
        }
        
        .hamburger.active span:nth-child(2) {
            opacity: 0;
        }
        
        .hamburger.active span:nth-child(3) {
            transform: translateY(-9px) rotate(-45deg);
        }
        
        @media (min-width: 992px) {
            .hamburger {
                display: none;
            }
        }
        
        /* Mobile Sidebar Menu */
        .mobile-menu {
            position: fixed;
            top: 0;
            left: -100%;
            width: 85%;
            max-width: 320px;
            height: 100vh;
            background: white;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            z-index: 1005;
            transition: left 0.3s ease;
            overflow-y: auto;
            padding: 0;
        }
        
        .mobile-menu.open {
            left: 0;
        }
        
        .mobile-menu-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e9ecef;
            background: white;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .mobile-menu-header h3 {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1e293b;
        }
        
        .close-menu {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #64748b;
        }
        
        .mobile-nav-links {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            padding: 0.5rem 1rem;
        }
        
        .mobile-nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: #475569;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            border-radius: 10px;
            transition: all 0.2s ease;
        }
        
        .mobile-nav-link:hover,
        .mobile-nav-link.active {
            background-color: #e6f7f5;
            color: #0d9488;
        }
        
        .mobile-nav-link i {
            width: 24px;
            font-size: 1.1rem;
        }
        
        /* Overlay */
        .menu-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1004;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        
        .menu-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        
        /* Academic Level Selector */
        .level-selector-btn {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 0.5rem 1rem;
            transition: all 0.2s ease;
            cursor: pointer;
            white-space: nowrap;
        }
        
        .level-selector-btn:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
        }
        
        /* Notification Button */
        .notif-btn {
            position: relative;
            padding: 0.5rem;
            border-radius: 10px;
            transition: all 0.2s ease;
            color: #64748b;
            background: none;
            border: none;
            cursor: pointer;
        }
        
        .notif-btn:hover {
            background-color: #f8fafc;
            color: #0d9488;
        }
        
        .notif-badge {
            position: absolute;
            top: 2px;
            right: 2px;
            background: #ef4444;
            color: white;
            font-size: 0.6rem;
            font-weight: 600;
            padding: 0.15rem 0.4rem;
            border-radius: 20px;
            min-width: 18px;
            text-align: center;
        }
        
        /* Profile Button */
        .profile-btn {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.4rem 0.75rem 0.4rem 0.4rem;
            border-radius: 40px;
            transition: all 0.2s ease;
            cursor: pointer;
            background: transparent;
            border: none;
        }
        
        .profile-btn:hover {
            background-color: #f8fafc;
        }
        
        .avatar-img {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e2e8f0;
        }
        
        .profile-btn:hover .avatar-img {
            border-color: #0d9488;
        }
        
        /* Dropdown Menus */
        .dropdown-menu {
            position: absolute;
            right: 0;
            top: calc(100% + 8px);
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05);
            border: 1px solid #e9ecef;
            min-width: 260px;
            z-index: 1020;
            overflow: hidden;
            animation: fadeInDown 0.2s ease;
        }
        
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-8px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .dropdown-menu.hidden {
            display: none;
        }
        
        .dropdown-header {
            padding: 1rem;
            border-bottom: 1px solid #f0f2f4;
        }
        
        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            font-size: 0.85rem;
            color: #475569;
            transition: all 0.2s ease;
            cursor: pointer;
            text-decoration: none;
        }
        
        .dropdown-item:hover {
            background-color: #f8fafc;
            color: #0d9488;
        }
        
        .dropdown-item i {
            width: 20px;
        }
        
        .dropdown-divider {
            height: 1px;
            background: #f0f2f4;
            margin: 0.25rem 0;
        }
        
        .logout-item {
            color: #dc2626;
        }
        
        .logout-item:hover {
            background-color: #fef2f2;
            color: #dc2626;
        }
        
        .level-option {
            transition: all 0.2s ease;
            cursor: pointer;
            border-radius: 8px;
        }
        
        .level-option:hover {
            background-color: #f8fafc;
        }
        
        .level-option.active {
            background-color: #e6f7f5;
            color: #0d9488;
        }
        
        /* Toast Notification */
        .toast-notification {
            position: fixed;
            top: 80px;
            right: 20px;
            background: #0d9488;
            color: white;
            padding: 0.75rem 1.25rem;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            z-index: 1100;
            transition: all 0.3s ease;
            transform: translateX(100%);
            opacity: 0;
        }
        
        .toast-notification.show {
            transform: translateX(0);
            opacity: 1;
        }
        
        /* Mobile Responsive Adjustments */
        @media (max-width: 640px) {
            .school-name {
                font-size: 0.8rem;
            }
            
            .level-selector-btn {
                padding: 0.4rem 0.7rem;
            }
            
            .level-text {
                font-size: 0.7rem;
            }
            
            .user-name, .user-role {
                display: none;
            }
            
            .profile-btn {
                padding: 0.3rem;
            }
        }
        
        /* Prevent body scroll when menu is open */
        body.menu-open {
            overflow: hidden;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex flex-col">

<!-- Modern Responsive Topbar -->
<nav class="topbar">
    <div class="navbar-container">
        
        <!-- Left: Logo and Brand -->
        <div class="logo-section">
            <?php if (!empty($school_logo) && file_exists('../' . ltrim($school_logo, './'))): ?>
                <img src="<?php echo htmlspecialchars('../' . ltrim($school_logo, './')); ?>" alt="School Logo" class="school-logo-img">
            <?php else: ?>
                <div class="logo-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>
            <?php endif; ?>
            
            <div class="brand-text">
                <h1 class="school-name"><?php echo htmlspecialchars($school_name); ?></h1>
                <p class="school-motto"><?php echo htmlspecialchars($school_motto); ?></p>
            </div>
        </div>
        
        <!-- Desktop Navigation Links (Only Fee Management related) -->
        <div class="nav-links" id="desktopNavLinks">
            <!-- Navigation items will be populated by JavaScript -->
        </div>
        
        <!-- Right Section: Actions & Profile -->
        <div class="right-section">
            
            <!-- Search Student (Desktop) -->
            <div class="search-container">
                <i class="fas fa-search search-icon"></i>
                <input type="text" id="globalSearchInput" class="search-input" placeholder="Search student..." autocomplete="off">
                <div id="globalSearchResults" class="search-results"></div>
            </div>
            
            <!-- Academic Level Selector -->
            <div class="relative">
                <button id="academicLevelBtn" class="level-selector-btn flex items-center gap-2">
                    <i class="fas fa-layer-group text-teal-500 text-sm"></i>
                    <span class="level-text text-sm font-medium text-gray-700">
                        <?php 
                        if ($academic_level == 'primary') echo 'Primary';
                        elseif ($academic_level == 'junior_secondary') echo 'JSS';
                        else echo 'SS';
                        ?>
                    </span>
                    <i class="fas fa-chevron-down text-gray-400 text-xs"></i>
                </button>
                
                <div id="academicLevelDropdown" class="dropdown-menu hidden">
                    <div class="dropdown-header">
                        <span class="text-xs font-medium text-gray-400">ACADEMIC LEVEL</span>
                    </div>
                    <div>
                        <div class="level-option flex items-center justify-between px-4 py-3 <?php echo $academic_level == 'primary' ? 'active' : ''; ?>" data-level="primary">
                            <div class="flex items-center gap-3">
                                <i class="fas fa-chalkboard-user w-5 text-gray-400"></i>
                                <div>
                                    <div class="text-sm font-medium">Primary School</div>
                                    <div class="text-xs text-gray-400">Grades 1-6</div>
                                </div>
                            </div>
                            <?php if($academic_level == 'primary'): ?>
                                <i class="fas fa-check-circle text-teal-500 text-sm"></i>
                            <?php endif; ?>
                        </div>
                        <div class="level-option flex items-center justify-between px-4 py-3 <?php echo $academic_level == 'junior_secondary' ? 'active' : ''; ?>" data-level="junior_secondary">
                            <div class="flex items-center gap-3">
                                <i class="fas fa-users w-5 text-gray-400"></i>
                                <div>
                                    <div class="text-sm font-medium">Junior Secondary</div>
                                    <div class="text-xs text-gray-400">Grades 7-9</div>
                                </div>
                            </div>
                            <?php if($academic_level == 'junior_secondary'): ?>
                                <i class="fas fa-check-circle text-teal-500 text-sm"></i>
                            <?php endif; ?>
                        </div>
                        <div class="level-option flex items-center justify-between px-4 py-3 <?php echo $academic_level == 'senior_secondary' ? 'active' : ''; ?>" data-level="senior_secondary">
                            <div class="flex items-center gap-3">
                                <i class="fas fa-university w-5 text-gray-400"></i>
                                <div>
                                    <div class="text-sm font-medium">Senior Secondary</div>
                                    <div class="text-xs text-gray-400">Forms 1-4</div>
                                </div>
                            </div>
                            <?php if($academic_level == 'senior_secondary'): ?>
                                <i class="fas fa-check-circle text-teal-500 text-sm"></i>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Notifications -->
            <div class="relative">
                <button id="notificationsBtn" class="notif-btn">
                    <i class="fas fa-bell text-lg"></i>
                    <span class="notif-badge hidden">0</span>
                </button>
                
                <div id="notificationsDropdown" class="dropdown-menu hidden" style="min-width: 300px;">
                    <div class="dropdown-header flex justify-between items-center">
                        <span class="text-sm font-semibold text-gray-800">Notifications</span>
                        <button id="markAllReadBtn" class="text-xs text-teal-500 hover:text-teal-600">Mark all read</button>
                    </div>
                    <div id="notificationsList" class="max-h-80 overflow-y-auto">
                        <div class="text-center py-8 text-gray-400 text-sm">
                            <i class="fas fa-bell-slash text-2xl mb-2 block"></i>
                            No new notifications
                        </div>
                    </div>
                    <div class="border-t border-gray-100 p-2 text-center">
                        <a href="all-notifications.php" class="text-xs text-teal-500 hover:text-teal-600">View all</a>
                    </div>
                </div>
            </div>
            
            <!-- User Profile -->
            <div class="relative">
                <button id="profileBtn" class="profile-btn">
                    <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="Avatar" class="avatar-img" onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($user_name); ?>&background=0D9488&color=fff&size=128&rounded=true&bold=true&length=2'">
                    <div class="hidden sm:block text-left">
                        <p class="user-name"><?php echo htmlspecialchars($user_name); ?></p>
                        <p class="user-role"><?php echo htmlspecialchars($_SESSION['role'] ?? 'Administrator'); ?></p>
                    </div>
                    <i class="fas fa-chevron-down text-gray-400 text-xs hidden sm:block"></i>
                </button>
                
                <div id="profileDropdown" class="dropdown-menu hidden">
                    <div class="dropdown-header flex items-center gap-3">
                        <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="Avatar" class="w-10 h-10 rounded-full object-cover" onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($user_name); ?>&background=0D9488&color=fff&size=128&rounded=true&bold=true&length=2'">
                        <div>
                            <p class="text-sm font-semibold text-gray-800"><?php echo htmlspecialchars($user_name); ?></p>
                            <p class="text-xs text-gray-500"><?php echo htmlspecialchars($_SESSION['email'] ?? 'user@eduscore.com'); ?></p>
                        </div>
                    </div>
                    <div class="py-1">
                        <a href="profile.php" class="dropdown-item"><i class="fas fa-user-circle"></i><span>My Profile</span></a>
                        <a href="settings.php" class="dropdown-item"><i class="fas fa-sliders-h"></i><span>Preferences</span></a>
                        <a href="school-settings.php" class="dropdown-item"><i class="fas fa-school"></i><span>School Settings</span></a>
                        <div class="dropdown-divider"></div>
                        <a href="change-password.php" class="dropdown-item"><i class="fas fa-key"></i><span>Change Password</span></a>
                        <a href="../logout.php" class="dropdown-item logout-item"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
                    </div>
                </div>
            </div>
            
            <!-- Hamburger Menu Button (Mobile) -->
            <button class="hamburger" id="hamburgerBtn">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>
    </div>
</nav>

<!-- Mobile Sidebar Menu -->
<div class="mobile-menu" id="mobileMenu">
    <div class="mobile-menu-header">
        <h3>Menu</h3>
        <button class="close-menu" id="closeMenuBtn">&times;</button>
    </div>
    
    <!-- Mobile Search -->
    <div class="mobile-search-container">
        <div style="position: relative;">
            <i class="fas fa-search" style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 0.875rem;"></i>
            <input type="text" id="mobileGlobalSearchInput" class="mobile-search-input" placeholder="Search student...">
        </div>
        <div id="mobileGlobalSearchResults" class="search-results" style="position: relative; width: 100%; margin-top: 0.5rem;"></div>
    </div>
    
    <div class="mobile-nav-links" id="mobileNavLinks">
        <!-- Mobile navigation items will be populated by JavaScript -->
    </div>
</div>

<!-- Overlay -->
<div class="menu-overlay" id="menuOverlay"></div>

<!-- Toast Notification -->
<div id="levelChangeToast" class="toast-notification">
    <i class="fas fa-check-circle"></i>
    <span id="toastMessage">Academic level changed</span>
</div>

<script>
$(document).ready(function() {
    // ============================================
    // GLOBAL STUDENT SEARCH FUNCTIONALITY - FULLY FUNCTIONAL
    // ============================================
    let searchTimeout;
    let schoolId = <?php echo json_encode($school_id); ?>;
    let currentAcademicLevel = <?php echo json_encode($academic_level); ?>;
    
    function performSearch(searchTerm, isMobile = false) {
        if (!searchTerm || searchTerm.length < 2) {
            if (isMobile) {
                $('#mobileGlobalSearchResults').removeClass('show').empty();
            } else {
                $('#globalSearchResults').removeClass('show').empty();
            }
            return;
        }
        
        // Show loading indicator
        const loadingHtml = '<div class="search-result-item" style="cursor:default; justify-content:center;"><i class="fas fa-spinner fa-spin mr-2"></i><span>Searching...</span></div>';
        if (isMobile) {
            $('#mobileGlobalSearchResults').html(loadingHtml).addClass('show');
        } else {
            $('#globalSearchResults').html(loadingHtml).addClass('show');
        }
        
        $.ajax({
            url: '../../feesystem/api/search_students.php',
            method: 'POST',
            dataType: 'json',
            data: { 
                school_id: schoolId,
                search: searchTerm,
                academic_level: currentAcademicLevel
            },
            success: function(data) {
                if (data.success && data.students && data.students.length > 0) {
                    let html = '';
                    data.students.forEach(function(student) {
                        html += `
                            <a href="student-details.php?id=${student.id}" class="search-result-item">
                                <div class="search-result-avatar">
                                    <i class="fas fa-user-graduate"></i>
                                </div>
                                <div class="search-result-info">
                                    <div class="search-result-name">${escapeHtml(student.full_name)}</div>
                                    <div class="search-result-details">
                                        ${escapeHtml(student.class_name || student.class_level)} | ${escapeHtml(student.gender || 'N/A')}
                                    </div>
                                </div>
                                <div class="search-result-adm">
                                    ${escapeHtml(student.admission_number || student.admission_no)}
                                </div>
                            </a>
                        `;
                    });
                    
                    if (isMobile) {
                        $('#mobileGlobalSearchResults').html(html).addClass('show');
                    } else {
                        $('#globalSearchResults').html(html).addClass('show');
                    }
                } else {
                    const noResultsHtml = '<div class="search-result-item" style="cursor:default; justify-content:center;"><i class="fas fa-user-graduate mr-2 text-gray-400"></i><span class="text-gray-500">No students found</span></div>';
                    if (isMobile) {
                        $('#mobileGlobalSearchResults').html(noResultsHtml).addClass('show');
                    } else {
                        $('#globalSearchResults').html(noResultsHtml).addClass('show');
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('Search error:', error);
                const errorHtml = '<div class="search-result-item" style="cursor:default; justify-content:center;"><i class="fas fa-exclamation-triangle text-red-500 mr-2"></i><span class="text-red-500">Error searching students</span></div>';
                if (isMobile) {
                    $('#mobileGlobalSearchResults').html(errorHtml).addClass('show');
                } else {
                    $('#globalSearchResults').html(errorHtml).addClass('show');
                }
            }
        });
    }
    
    // Desktop search with debounce
    $('#globalSearchInput').on('input', function() {
        const searchTerm = $(this).val();
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => performSearch(searchTerm, false), 400);
    });
    
    // Mobile search with debounce
    $('#mobileGlobalSearchInput').on('input', function() {
        const searchTerm = $(this).val();
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => performSearch(searchTerm, true), 400);
    });
    
    // Clear search on escape key
    $('#globalSearchInput, #mobileGlobalSearchInput').on('keydown', function(e) {
        if (e.key === 'Escape') {
            $(this).val('');
            if ($(this).attr('id') === 'globalSearchInput') {
                $('#globalSearchResults').removeClass('show').empty();
            } else {
                $('#mobileGlobalSearchResults').removeClass('show').empty();
            }
        }
    });
    
    // Hide search results when clicking outside
    $(document).click(function(e) {
        if (!$(e.target).closest('.search-container').length) {
            $('#globalSearchResults').removeClass('show');
        }
        if (!$(e.target).closest('.mobile-search-container').length) {
            $('#mobileGlobalSearchResults').removeClass('show');
        }
    });
    
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // ============================================
    // HAMBURGER MENU TOGGLE
    // ============================================
    const hamburger = document.getElementById('hamburgerBtn');
    const mobileMenu = document.getElementById('mobileMenu');
    const menuOverlay = document.getElementById('menuOverlay');
    const closeMenuBtn = document.getElementById('closeMenuBtn');
    
    function openMenu() {
        mobileMenu.classList.add('open');
        menuOverlay.classList.add('active');
        hamburger.classList.add('active');
        document.body.classList.add('menu-open');
    }
    
    function closeMenu() {
        mobileMenu.classList.remove('open');
        menuOverlay.classList.remove('active');
        hamburger.classList.remove('active');
        document.body.classList.remove('menu-open');
    }
    
    if (hamburger) hamburger.addEventListener('click', openMenu);
    if (closeMenuBtn) closeMenuBtn.addEventListener('click', closeMenu);
    if (menuOverlay) menuOverlay.addEventListener('click', closeMenu);
    
    // Close menu on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && mobileMenu && mobileMenu.classList.contains('open')) {
            closeMenu();
        }
    });
    
    // ============================================
    // DROPDOWN FUNCTIONS
    // ============================================
    function closeAllDropdowns() {
        $('#academicLevelDropdown, #profileDropdown, #notificationsDropdown').addClass('hidden');
    }
    
    $('#academicLevelBtn').click(function(e) {
        e.stopPropagation();
        closeAllDropdowns();
        $('#academicLevelDropdown').toggleClass('hidden');
    });
    
    $('#profileBtn').click(function(e) {
        e.stopPropagation();
        closeAllDropdowns();
        $('#profileDropdown').toggleClass('hidden');
    });
    
    $('#notificationsBtn').click(function(e) {
        e.stopPropagation();
        closeAllDropdowns();
        $('#notificationsDropdown').toggleClass('hidden');
        loadNotifications(); // Refresh notifications when opened
    });
    
    $(document).click(function() {
        closeAllDropdowns();
    });
    
    // ============================================
    // ACADEMIC LEVEL CHANGE - FULLY FUNCTIONAL
    // ============================================
    $('.level-option').click(function() {
        var level = $(this).data('level');
        var levelText = '';
        var dbLevel = '';
        
        switch(level) {
            case 'primary':
                levelText = 'Primary';
                dbLevel = 'primary';
                break;
            case 'junior_secondary':
                levelText = 'Junior Secondary';
                dbLevel = 'junior_secondary';
                break;
            case 'senior_secondary':
                levelText = 'Senior Secondary';
                dbLevel = 'senior_secondary';
                break;
            default:
                levelText = 'Primary';
                dbLevel = 'primary';
        }
        
        // Show loading
        Swal.fire({
            title: 'Updating...',
            text: 'Changing academic level',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });
        
        $.ajax({
            url: window.location.pathname,
            method: 'POST',
            data: { academic_level: dbLevel },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Update UI
                    $('.level-text').text(levelText);
                    $('.level-option').removeClass('active');
                    $('.level-option i.fa-check-circle').remove();
                    $('.level-option[data-level="' + level + '"]').addClass('active');
                    $('.level-option[data-level="' + level + '"]').append('<i class="fas fa-check-circle text-teal-500 text-sm"></i>');
                    
                    // Update current academic level for search
                    currentAcademicLevel = dbLevel;
                    
                    Swal.fire({
                        icon: 'success',
                        title: 'Level Changed',
                        text: 'Changed to ' + levelText,
                        timer: 1500,
                        showConfirmButton: false
                    });
                    
                    showToast('Academic level changed to ' + levelText);
                    
                    // Store in session storage
                    sessionStorage.setItem('academic_level', dbLevel);
                    
                    // Reload page to reflect changes
                    setTimeout(() => location.reload(), 1200);
                } else {
                    Swal.fire('Error', 'Failed to change academic level', 'error');
                }
            },
            error: function() {
                Swal.close();
                Swal.fire('Error', 'An error occurred while changing level', 'error');
            }
        });
    });
    
    function showToast(message) {
        $('#toastMessage').text(message);
        $('#levelChangeToast').addClass('show');
        setTimeout(function() {
            $('#levelChangeToast').removeClass('show');
        }, 3000);
    }
    
    // ============================================
    // NOTIFICATIONS - FULLY FUNCTIONAL
    // ============================================
    function loadNotifications() {
        $.ajax({
            url: '../../feesystem/api/get_notifications.php',
            method: 'GET',
            dataType: 'json',
            success: function(data) {
                if (data.success) {
                    const count = data.unread_count || data.count || 0;
                    if (count > 0) {
                        $('.notif-badge').text(count > 99 ? '99+' : count).removeClass('hidden');
                    } else {
                        $('.notif-badge').addClass('hidden');
                    }
                    
                    if (data.notifications && data.notifications.length > 0) {
                        var html = '';
                        data.notifications.slice(0, 5).forEach(function(notif) {
                            const icon = notif.icon || (notif.type === 'payment' ? 'fa-credit-card' : 
                                                        notif.type === 'alert' ? 'fa-exclamation-triangle' : 'fa-bell');
                            html += `
                                <div class="dropdown-item flex items-start gap-3 notification-item" data-id="${notif.id}" style="cursor:pointer; ${notif.is_read ? 'opacity:0.7' : 'background:#f0fdf4'}">
                                    <div class="w-8 h-8 bg-teal-50 rounded-full flex items-center justify-center flex-shrink-0">
                                        <i class="fas ${icon} text-teal-500 text-sm"></i>
                                    </div>
                                    <div class="flex-1">
                                        <p class="text-sm font-medium text-gray-800">${escapeHtml(notif.title)}</p>
                                        <p class="text-xs text-gray-500">${escapeHtml(notif.message)}</p>
                                        <p class="text-xs text-gray-400 mt-1">${escapeHtml(notif.time_ago || notif.created_at)}</p>
                                    </div>
                                    ${!notif.is_read ? '<div class="w-2 h-2 bg-teal-500 rounded-full mt-2"></div>' : ''}
                                </div>
                            `;
                        });
                        if (data.notifications.length > 5) {
                            html += '<div class="text-center py-2 text-xs text-gray-400">+ ' + (data.notifications.length - 5) + ' more notifications</div>';
                        }
                        $('#notificationsList').html(html);
                        
                        // Mark as read when clicked
                        $('.notification-item').click(function() {
                            const notifId = $(this).data('id');
                            markNotificationAsRead(notifId);
                        });
                    } else {
                        $('#notificationsList').html('<div class="text-center py-8 text-gray-400 text-sm"><i class="fas fa-bell-slash text-2xl mb-2 block"></i>No new notifications</div>');
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('Notification load error:', error);
            }
        });
    }
    
    function markNotificationAsRead(notificationId) {
        $.ajax({
            url: '../../feesystem/api/mark_notification_read.php',
            method: 'POST',
            data: { notification_id: notificationId },
            dataType: 'json',
            success: function(data) {
                if (data.success) {
                    loadNotifications(); // Refresh list
                }
            }
        });
    }
    
    $('#markAllReadBtn').click(function(e) {
        e.preventDefault();
        $.ajax({
            url: '../../feesystem/api/mark_all_notifications_read.php',
            method: 'POST',
            dataType: 'json',
            success: function(data) {
                if (data.success) {
                    $('.notif-badge').addClass('hidden');
                    $('#notificationsList').html('<div class="text-center py-8 text-gray-400 text-sm"><i class="fas fa-bell-slash text-2xl mb-2 block"></i>No new notifications</div>');
                    showToast('All notifications marked as read');
                }
            },
            error: function() {
                showToast('Error marking notifications as read');
            }
        });
    });
    
    // Load notifications on page load
    loadNotifications();
    
    // Auto-refresh notifications every 30 seconds
    setInterval(loadNotifications, 30000);
    
    // ============================================
    // USER PROFILE - FULLY FUNCTIONAL
    // ============================================
    // Profile update functionality (if needed)
    function updateUserProfile(profileData) {
        $.ajax({
            url: '../../api/update_profile.php',
            method: 'POST',
            data: profileData,
            dataType: 'json',
            success: function(data) {
                if (data.success) {
                    showToast('Profile updated successfully');
                    // Update displayed name
                    if (profileData.full_name) {
                        $('.user-name').text(profileData.full_name);
                    }
                } else {
                    Swal.fire('Error', data.message || 'Failed to update profile', 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'An error occurred', 'error');
            }
        });
    }
    
    // Handle logout confirmation
    $('.logout-item').click(function(e) {
        e.preventDefault();
        Swal.fire({
            title: 'Logout?',
            text: 'Are you sure you want to logout?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, logout',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = '../logout.php';
            }
        });
    });
    
    // ============================================
    // ADDITIONAL UTILITIES
    // ============================================
    
    // Function to refresh data based on academic level
    function refreshDataForAcademicLevel() {
        // This can be extended to refresh page data without reload
        console.log('Refreshing data for academic level:', currentAcademicLevel);
    }
    
    // Listen for academic level changes from other tabs/windows
    window.addEventListener('storage', function(e) {
        if (e.key === 'academic_level' && e.newValue !== currentAcademicLevel) {
            currentAcademicLevel = e.newValue;
            refreshDataForAcademicLevel();
        }
    });
});
</script>
<!-- Main Layout Container -->
<div class="flex flex-grow">
    <!-- Your main content goes here -->
