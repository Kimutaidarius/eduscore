<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);


require_once 'includes/config.php';
require_once 'includes/session_timeout.php'; 

// Detect AJAX
$isAjax = ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']));

// ✅ SINGLE SOURCE OF TRUTH
if (
    empty($_SESSION['authenticated']) ||
    empty($_SESSION['school_id']) ||
    empty($_SESSION['teacher_id'])
) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Unauthorized session'
        ]);
        exit;
    }

    header('Location: login.php');
    exit;
}

// Safe session values
$school_id  = (int) $_SESSION['school_id'];
$teacher_id = (int) $_SESSION['teacher_id'];

// DB
if (!isset($db)) {
    require_once 'config/database.php';
    $database = new Database();
    $db = $database->getConnection();
}


// Check if student ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: student-list.php");
    exit;
}

$student_id = intval($_GET['id']);
$school_id = $_SESSION['school_id'];

// Fetch student data
try {
    // Main student query
    $query = "SELECT s.*, 
                     c.class_level as class_name, 
                     c.academic_level,
                     st.stream_name,
                     t.firstname as teacher_firstname,
                     t.secondname as teacher_secondname,
                     t.lastname as teacher_lastname
              FROM tblstudents s 
              LEFT JOIN tblclasses c ON CAST(s.class_id AS UNSIGNED) = c.id 
              LEFT JOIN tblstreams st ON s.StreamId = st.id
              LEFT JOIN tblteachers t ON c.teacher_id = t.id
              WHERE s.id = ? AND s.school_id = ? AND s.Status = 'Active'";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$student_id, $school_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        $_SESSION['error'] = "Student not found or you don't have permission to view this profile.";
        header("Location: student-list.php");
        exit;
    }
    
    // Fetch class history
    $historyQuery = "SELECT sh.*, 
                            c.class_level as class_name,
                            st.stream_name
                     FROM student_history sh
                     LEFT JOIN tblclasses c ON sh.class_id = c.id
                     LEFT JOIN tblstreams st ON sh.stream_id = st.id
                     WHERE sh.student_id = ? AND sh.school_id = ?
                     ORDER BY sh.academic_year DESC, sh.promotion_date DESC";
    
    $historyStmt = $db->prepare($historyQuery);
    $historyStmt->execute([$student_id, $school_id]);
    $classHistory = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If no history exists, create current class as history
    if (empty($classHistory) && $student['class_id']) {
        $classHistory = [[
            'academic_year' => date('Y'),
            'class_name' => $student['class_name'] ?? 'N/A',
            'stream_name' => $student['stream_name'] ?? 'N/A',
            'class_level' => $student['academic_level'] ?? 'N/A',
            'BoardingStatus' => $student['BoardingStatus'] ?? 'Day Scholar',
            'Status' => $student['Status'] ?? 'Active'
        ]];
    }
    
    // Fetch student subjects
    $subjectsQuery = "SELECT sj.subject_name, sj.alias, sj.subject_type,
                             t.firstname as teacher_firstname,
                             t.secondname as teacher_secondname
                      FROM student_subjects ss
                      LEFT JOIN tblsubjects sj ON ss.subject_id = sj.id
                      LEFT JOIN tblteachers t ON sj.teacher_id = t.id
                      WHERE ss.student_id = ? AND ss.school_id = ?
                      ORDER BY sj.subject_type DESC, sj.subject_name";
    
    $subjectsStmt = $db->prepare($subjectsQuery);
    $subjectsStmt->execute([$student_id, $school_id]);
    $subjects = $subjectsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate age if date of birth exists
    $age = 'N/A';
    if (!empty($student['date_of_birth']) && $student['date_of_birth'] !== '0000-00-00') {
        $dob = new DateTime($student['date_of_birth']);
        $now = new DateTime();
        $age = $dob->diff($now)->y;
    }
    
} catch (PDOException $e) {
    error_log("Error fetching student profile: " . $e->getMessage());
    $_SESSION['error'] = "Error loading student profile. Please try again.";
    header("Location: student-list.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($student['FirstName'] . ' ' . $student['LastName']); ?> - Student Profile - EduScore</title>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-blue: #1A73E8;
            --secondary-blue: #1976D2;
            --dark-blue: #0D47A1;
            --light-blue: #E8F0FE;
            --success-green: #10b981;
            --warning-orange: #f59e0b;
            --error-red: #ef4444;
            --text-dark: #1f2937;
            --text-light: #6b7280;
            --bg-light: #f9fafb;
            --bg-white: #ffffff;
            --border-color: #e5e7eb;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            --gradient-primary: linear-gradient(135deg, #1A73E8 0%, #0D47A1 100%);
            --sidebar-width: 280px;
            --header-height: 70px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        body {
            background: var(--bg-light);
            color: var(--text-dark);
            min-height: 100vh;
            display: flex;
            overflow-x: hidden;
            -webkit-tap-highlight-color: transparent;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* Main Content Layout */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            transition: margin-left 0.3s ease;
            position: relative;
            padding-top: var(--header-height);
            width: 100%;
            overflow-x: hidden;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: clamp(1rem, 4vw, 2rem);
            width: 100%;
        }

        /* Page Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: clamp(1.5rem, 4vw, 2rem);
            padding-bottom: clamp(0.75rem, 2vw, 1rem);
            border-bottom: 1px solid var(--border-color);
            flex-wrap: wrap;
            gap: clamp(0.5rem, 2vw, 1rem);
        }

        .profile-page-title {
            font-size: clamp(1.25rem, 5vw, 2rem);
            font-weight: 700;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: clamp(0.5rem, 2vw, 1rem);
        }

        .profile-page-title i {
            color: var(--primary-blue);
            font-size: clamp(1.5rem, 5vw, 2.2rem);
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: clamp(0.625rem, 2vw, 0.75rem) clamp(1rem, 3vw, 1.5rem);
            border: none;
            border-radius: clamp(8px, 2vw, 12px);
            font-size: clamp(0.85rem, 2vw, 0.95rem);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: clamp(0.375rem, 1vw, 0.5rem);
            text-decoration: none;
            white-space: nowrap;
            min-height: 44px;
            justify-content: center;
        }

        .btn-primary {
            background: var(--gradient-primary);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid var(--primary-blue);
            color: var(--primary-blue);
        }

        .btn-outline:hover {
            background: var(--primary-blue);
            color: white;
        }

        .btn-secondary {
            background: var(--text-light);
            color: white;
        }

        .btn-secondary:hover {
            background: var(--text-dark);
        }

        /* Student Profile Layout */
        .profile-layout {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: clamp(1.5rem, 4vw, 2rem);
        }

        /* Profile Card */
        .profile-card {
            grid-column: span 4;
            background: var(--bg-white);
            border-radius: 16px;
            box-shadow: var(--shadow);
            overflow: hidden;
            border: 1px solid var(--border-color);
        }

        .profile-header {
            background: var(--gradient-primary);
            padding: clamp(1.5rem, 4vw, 2rem);
            text-align: center;
            color: white;
            position: relative;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            margin: 0 auto 1.5rem;
            border: 4px solid rgba(255, 255, 255, 0.3);
            overflow: hidden;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-avatar i {
            font-size: 3rem;
            color: var(--primary-blue);
        }

        .student-name {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .student-admission {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .profile-details {
            padding: clamp(1.5rem, 4vw, 2rem);
        }

        .detail-group {
            margin-bottom: 1.5rem;
        }

        .detail-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-light);
            margin-bottom: 0.25rem;
            font-weight: 600;
        }

        .detail-value {
            font-size: 0.95rem;
            color: var(--text-dark);
            font-weight: 500;
        }

        .detail-value strong {
            color: var(--primary-blue);
        }

        /* Info Cards */
        .info-card {
            grid-column: span 4;
            background: var(--bg-white);
            border-radius: 16px;
            box-shadow: var(--shadow);
            overflow: hidden;
            border: 1px solid var(--border-color);
        }

        .card-header {
            padding: clamp(1rem, 3vw, 1.5rem);
            border-bottom: 1px solid var(--border-color);
            background: var(--light-blue);
        }

        .card-header h3 {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--dark-blue);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .card-header i {
            color: var(--primary-blue);
        }

        .card-body {
            padding: clamp(1rem, 3vw, 1.5rem);
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border-color);
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            font-size: 0.85rem;
            color: var(--text-light);
            font-weight: 500;
        }

        .info-value {
            font-size: 0.9rem;
            color: var(--text-dark);
            font-weight: 600;
            text-align: right;
        }

        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-info {
            background: var(--light-blue);
            color: var(--dark-blue);
        }

        /* Table Styles */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }

        .data-table th {
            background: var(--light-blue);
            font-weight: 600;
            color: var(--dark-blue);
            text-align: left;
            padding: 1rem;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--border-color);
        }

        .data-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.9rem;
            color: var(--text-dark);
        }

        .data-table tr:hover {
            background: var(--light-blue);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: var(--text-light);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* Loading Spinner */
        .spinner {
            width: 40px;
            height: 40px;
            border: 3px solid transparent;
            border-top: 3px solid var(--primary-blue);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 2rem auto;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Toast Notifications */
        .toast-container {
            position: fixed;
            top: calc(var(--header-height) + 1rem);
            right: 1rem;
            z-index: 1100;
            width: min(400px, calc(100% - 2rem));
        }

        .toast {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            box-shadow: var(--shadow-lg);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            transform: translateX(400px);
            opacity: 0;
            transition: all 0.3s ease;
        }

        .toast.show {
            transform: translateX(0);
            opacity: 1;
        }

        .toast-success {
            background: var(--success-green);
            color: white;
        }

        .toast-error {
            background: var(--error-red);
            color: white;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .profile-card,
            .info-card {
                grid-column: span 6;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding-top: calc(var(--header-height) + 1rem);
            }

            .profile-layout {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }

            .profile-card,
            .info-card {
                grid-column: span 12;
            }

            .page-header {
                flex-direction: column;
                align-items: stretch;
                gap: 1rem;
            }

            .action-buttons {
                justify-content: center;
            }
        }

        @media (max-width: 576px) {
            .container {
                padding: 1rem;
            }

            .profile-header {
                padding: 1.5rem;
            }

            .profile-avatar {
                width: 100px;
                height: 100px;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            .action-buttons {
                flex-direction: column;
                width: 100%;
            }
        }

        /* Mobile Menu Toggle */
        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1000;
            background: var(--primary-blue);
            color: white;
            width: 44px;
            height: 44px;
            border-radius: 8px;
            border: none;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1.25rem;
            box-shadow: var(--shadow);
        }

        @media (max-width: 768px) {
            .mobile-menu-toggle {
                display: flex;
            }
        }
    </style>
</head>
<body>
        <?php 
    // Include the trial banner if not activated
    if (!isset($school)) {
        // Fetch school data for the banner
        $stmt = $db->prepare("SELECT * FROM tblschoolinfo WHERE id = :school_id");
        $stmt->bindParam(":school_id", $_SESSION['school_id'], PDO::PARAM_INT);
        $stmt->execute();
        $school = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    include 'trial_banner.php'; 
    ?>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle menu">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Include Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Include Topbar -->
        <?php include 'includes/header.php'; ?>

        <div class="container">
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="profile-page-title">
                    <i class="fas fa-user-graduate"></i>
                    Student Profile
                </h1>
                <div class="action-buttons">
                    <a href="studentslist.php" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i>
                        Back to List
                    </a>
                    <button class="btn btn-primary" onclick="window.print()">
                        <i class="fas fa-print"></i>
                        Print Profile
                    </button>
                </div>
            </div>

            <!-- Student Profile Layout -->
            <div class="profile-layout">
                <!-- Profile Card -->
                <div class="profile-card">
                    <div class="profile-header">
                        <div class="profile-avatar">
                            <?php if (!empty($student['ProfilePic']) && $student['ProfilePic'] !== 'default.png'): ?>
                                <img src="uploads/students/<?php echo htmlspecialchars($student['ProfilePic']); ?>" 
                                     alt="<?php echo htmlspecialchars($student['FirstName'] . ' ' . $student['LastName']); ?>"
                                     onerror="this.style.display='none'; this.parentNode.innerHTML='<i class=\"fas fa-user-graduate\"></i>';">
                            <?php else: ?>
                                <i class="fas fa-user-graduate"></i>
                            <?php endif; ?>
                        </div>
                        <h2 class="student-name">
                            <?php echo htmlspecialchars($student['FirstName'] . ' ' . $student['SecondName'] . ' ' . $student['LastName']); ?>
                        </h2>
                        <p class="student-admission">
                            Admission: <?php echo htmlspecialchars($student['AdmNo'] ?? 'N/A'); ?>
                        </p>
                    </div>
                    
                    <div class="profile-details">
                        <div class="detail-group">
                            <div class="detail-label">Personal Information</div>
                            <div class="detail-value">
                                <strong>Assessment Number:</strong> <?php echo htmlspecialchars($student['assessment_no'] ?? 'N/A'); ?><br>
                                <strong>Gender:</strong> <?php echo htmlspecialchars($student['Gender'] ?? 'N/A'); ?><br>
                                <strong>Date of Birth:</strong> <?php echo !empty($student['date_of_birth']) && $student['date_of_birth'] !== '0000-00-00' ? htmlspecialchars($student['date_of_birth']) : 'N/A'; ?><br>
                                <strong>Age:</strong> <?php echo $age; ?><br>
                                <strong>NEMIS UPI:</strong> <?php echo htmlspecialchars($student['Nemis'] ?? 'N/A'); ?>
                            </div>
                        </div>

                        <div class="detail-group">
                            <div class="detail-label">Guardian Information</div>
                            <div class="detail-value">
                                <strong>Name:</strong> <?php echo htmlspecialchars($student['GuardianName'] ?? 'N/A'); ?><br>
                                <strong>Relationship:</strong> <?php echo htmlspecialchars($student['GuardianRelationship'] ?? 'N/A'); ?><br>
                                <strong>Phone:</strong> <?php echo htmlspecialchars($student['GuardianPhone'] ?? 'N/A'); ?>
                            </div>
                        </div>

                        <div class="detail-group">
                            <div class="detail-label">Contact Information</div>
                            <div class="detail-value">
                                <strong>Admission Date:</strong> <?php echo !empty($student['admission_date']) ? htmlspecialchars($student['admission_date']) : 'N/A'; ?><br>
                                <strong>Boarding Status:</strong> <?php echo htmlspecialchars($student['BoardingStatus'] ?? 'Day Scholar'); ?><br>
                                <strong>Current Status:</strong> <span class="badge badge-success"><?php echo htmlspecialchars($student['Status'] ?? 'Active'); ?></span>
                            </div>
                        </div>

                        <div class="detail-group">
                            <a href="edit-student.php?id=<?php echo $student_id; ?>" class="btn btn-primary" style="width: 100%;">
                                <i class="fas fa-edit"></i>
                                Edit Student Information
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Current Class Card -->
                <div class="info-card">
                    <div class="card-header">
                        <h3><i class="fas fa-users-class"></i> Current Class Information</h3>
                    </div>
                    <div class="card-body">
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Class</span>
                                <span class="info-value"><?php echo htmlspecialchars($student['class_name'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Form</span>
                                <span class="info-value"><?php echo htmlspecialchars($student['academic_level'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Stream</span>
                                <span class="info-value"><?php echo htmlspecialchars($student['stream_name'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Year</span>
                                <span class="info-value"><?php echo date('Y'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Class Teacher</span>
                                <span class="info-value">
                                    <?php 
                                    $teacherName = '';
                                    if (!empty($student['teacher_firstname'])) {
                                        $teacherName = $student['teacher_firstname'];
                                        if (!empty($student['teacher_secondname'])) {
                                            $teacherName .= ' ' . $student['teacher_secondname'];
                                        }
                                        if (!empty($student['teacher_lastname'])) {
                                            $teacherName .= ' ' . $student['teacher_lastname'];
                                        }
                                        echo htmlspecialchars($teacherName);
                                    } else {
                                        echo 'Not Assigned';
                                    }
                                    ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Student Type</span>
                                <span class="info-value"><?php echo htmlspecialchars($student['BoardingStatus'] ?? 'Day Scholar'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Status</span>
                                <span class="info-value">
                                    <span class="badge badge-success"><?php echo htmlspecialchars($student['Status'] ?? 'Active'); ?></span>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Class History Card -->
                <div class="info-card" style="grid-column: span 4;">
                    <div class="card-header">
                        <h3><i class="fas fa-history"></i> Class History</h3>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($classHistory)): ?>
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Year</th>
                                            <th>Class</th>
                                            <th>Form</th>
                                            <th>Stream</th>
                                            <th>Type</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($classHistory as $history): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($history['academic_year'] ?? date('Y')); ?></td>
                                                <td><?php echo htmlspecialchars($history['class_name'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($history['class_level'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($history['stream_name'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($history['BoardingStatus'] ?? 'Day Scholar'); ?></td>
                                                <td>
                                                    <span class="badge badge-success"><?php echo htmlspecialchars($history['Status'] ?? 'Active'); ?></span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-history"></i>
                                <h3>No Class History</h3>
                                <p>This student has no recorded class history.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Subjects Card -->
                <div class="info-card" style="grid-column: span 4;">
                    <div class="card-header">
                        <h3><i class="fas fa-book-open"></i> Enrolled Subjects</h3>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($subjects)): ?>
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Subject</th>
                                            <th>Alias</th>
                                            <th>Type</th>
                                            <th>Teacher</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($subjects as $subject): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                                                <td><?php echo htmlspecialchars($subject['alias'] ?? '-'); ?></td>
                                                <td>
                                                    <span class="badge <?php echo $subject['subject_type'] === 'Optional' ? 'badge-warning' : 'badge-info'; ?>">
                                                        <?php echo htmlspecialchars($subject['subject_type']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $teacher = '';
                                                    if (!empty($subject['teacher_firstname'])) {
                                                        $teacher = $subject['teacher_firstname'];
                                                        if (!empty($subject['teacher_secondname'])) {
                                                            $teacher .= ' ' . $subject['teacher_secondname'];
                                                        }
                                                        echo htmlspecialchars($teacher);
                                                    } else {
                                                        echo 'Not Assigned';
                                                    }
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-book"></i>
                                <h3>No Subjects</h3>
                                <p>This student is not enrolled in any subjects.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Performance Card (Placeholder) -->
                <div class="info-card" style="grid-column: span 4;">
                    <div class="card-header">
                        <h3><i class="fas fa-chart-line"></i> Recent Performance</h3>
                    </div>
                    <div class="card-body">
                        <div class="empty-state">
                            <i class="fas fa-chart-bar"></i>
                            <h3>Performance Data</h3>
                            <p>Performance charts and statistics will be displayed here once the student has exam results.</p>
                            <a href="student-results.php?id=<?php echo $student_id; ?>" class="btn btn-outline" style="margin-top: 1rem;">
                                <i class="fas fa-poll"></i>
                                View Full Results
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer" aria-live="polite" aria-atomic="true"></div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Mobile menu toggle
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        if (mobileMenuToggle) {
            mobileMenuToggle.addEventListener('click', function() {
                const sidebar = document.querySelector('.sidebar');
                if (sidebar) {
                    sidebar.classList.toggle('active');
                }
            });
        }

        // Show any session messages
        <?php if (isset($_SESSION['success'])): ?>
            showToast('<?php echo addslashes($_SESSION['success']); ?>', 'success');
            <?php unset($_SESSION['success']); ?>
        <?php elseif (isset($_SESSION['error'])): ?>
            showToast('<?php echo addslashes($_SESSION['error']); ?>', 'error');
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        // Print functionality
        function setupPrint() {
            const printBtn = document.querySelector('button[onclick="window.print()"]');
            if (printBtn) {
                printBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    window.print();
                });
            }
        }

        // Toast notification function
        function showToast(message, type = 'info') {
            const toastContainer = document.getElementById('toastContainer');
            if (!toastContainer) return;
            
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.innerHTML = `
                <i class="fas fa-${getToastIcon(type)}"></i>
                <span>${escapeHtml(message)}</span>
            `;
            
            toastContainer.appendChild(toast);
            
            setTimeout(() => toast.classList.add('show'), 10);
            
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => {
                    if (toast.parentNode === toastContainer) {
                        toastContainer.removeChild(toast);
                    }
                }, 300);
            }, 5000);
        }

        function getToastIcon(type) {
            switch (type) {
                case 'success': return 'check-circle';
                case 'error': return 'exclamation-circle';
                case 'warning': return 'exclamation-triangle';
                default: return 'info-circle';
            }
        }

        function escapeHtml(text) {
            if (text === null || text === undefined) return '';
            const div = document.createElement('div');
            div.textContent = text.toString();
            return div.innerHTML;
        }

        // Initialize
        setupPrint();
    });
    </script>
</body>
</html>