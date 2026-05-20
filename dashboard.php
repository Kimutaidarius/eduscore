<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();


// Include session timeout
require_once 'includes/session_timeout.php';
require_once 'includes/ajax-handler.php';

// ✅ FORCE DEMO SESSION FOR TESTING / SAFETY
if (!isset($_SESSION['demo_expiry_time']) && isset($_GET['demo'])) {
    $_SESSION['demo_name'] = 'Demo User';
    $_SESSION['demo_email'] = 'demo@example.com';
    $_SESSION['demo_start_time'] = time();
    $_SESSION['demo_expiry_time'] = time() + (15 * 60); // 15 minutes
}

require_once 'includes/config.php';

// 🎯 CHECK FOR DEMO MODE
$is_demo_mode = false;
if (isset($_SESSION['demo_expiry_time'])) {
    $current_time = time();
    if ($current_time < $_SESSION['demo_expiry_time']) {
        $is_demo_mode = true;
        $_SESSION['demo_mode'] = true;
        
        if (!isset($_SESSION['user_fullname'])) {
            $_SESSION['user_fullname'] = $_SESSION['demo_name'] ?? 'Demo User';
        }
        if (!isset($_SESSION['school_name'])) {
            $_SESSION['school_name'] = 'Demo School';
        }
        if (!isset($_SESSION['user_role'])) {
            $_SESSION['user_role'] = 'Demo Administrator';
        }
        
        $demo_remaining_seconds = $_SESSION['demo_expiry_time'] - $current_time;
    } else {
        $is_demo_mode = true;
        $_SESSION['demo_mode'] = true;
        $_SESSION['demo_expired'] = true;
    }
}

// 🚫 REGULAR AUTH CHECK (skip if in demo mode)
if (!$is_demo_mode) {
    if (
    !isset($_SESSION['authenticated']) ||
    !isset($_SESSION['school_id']) ||
    !isset($_SESSION['teacher_id'])
) {
header('Location: login.php');
exit;
    }
}

// 🔒 TRIAL PERIOD CHECK (skip for demo mode)
if (!$is_demo_mode) {
    try {
        $check = $dbh->prepare("SELECT is_activated, created_at FROM tblschoolinfo WHERE id = :school_id");
        $check->execute(['school_id' => $_SESSION['school_id']]);
        $school_status = $check->fetch(PDO::FETCH_ASSOC);
        
        if (!$school_status) {
            session_destroy();
            header('Location: login.php');
            exit;
        }
        
        $is_activated = (bool)($school_status['is_activated'] ?? false);
        $created_at = $school_status['created_at'] ?? date('Y-m-d H:i:s');
        $trialDays = 14;
        $createdAt = strtotime($created_at);
        $trialEnds = $createdAt + ($trialDays * 86400);
        $now = time();
        $trialExpired = $now > $trialEnds;
        $remainingSeconds = max(0, $trialEnds - $now);
        
        $_SESSION['trial_info'] = [
            'is_activated' => $is_activated,
            'trial_expired' => $trialExpired,
            'remaining_seconds' => $remainingSeconds,
            'created_at' => $created_at
        ];
        
        if (!$is_activated && $trialExpired && basename($_SERVER['PHP_SELF']) !== 'activation-module.php') {
            header('Location: activation-module.php');
            exit;
        }
    } catch (PDOException $e) {
        error_log("Trial check error: " . $e->getMessage());
        $_SESSION['trial_info'] = ['is_activated' => false, 'trial_expired' => false, 'remaining_seconds' => 1209600, 'created_at' => date('Y-m-d H:i:s')];
        $is_activated = false;
        $trialExpired = false;
        $remainingSeconds = 1209600;
    }
} else {
    $is_activated = false;
    $trialExpired = false;
    $remainingSeconds = 900;
}

// Enhanced security (skip for demo mode)
if (!$is_demo_mode && (empty($_SESSION['authenticated']) || empty($_SESSION['school_id']) || empty($_SESSION['teacher_id']))) {
    session_destroy();
    header("Location: login.php");
    exit;
}

// Get academic level from session
$current_level = $_SESSION['academic_level'] ?? 'primary';
$academic_level_map = [
    'primary' => 'Primary School',
    'junior_secondary' => 'Junior Secondary',
    'senior_secondary' => 'Senior Secondary',
    'college' => 'College'
];
$current_level_display = $academic_level_map[$current_level] ?? 'Primary School';

// Initialize variables
$stats = ['total_students' => 0, 'total_teachers' => 0, 'total_classes' => 0, 'male_students' => 0, 'female_students' => 0, 'male_teachers' => 0, 'female_teachers' => 0, 'subscription' => ['license_tier' => 'Basic', 'status' => 'pending']];
$events = []; 
$activities = []; 
$top_students = []; 
$performance_data = [];
$sms_balance = 0;

// Fetch real data from database (skip for demo mode)
if (!$is_demo_mode) {
    try {
        // School information
        $query = "SELECT school_name, license_tier, status, is_activated, sms_balance FROM tblschoolinfo WHERE id = :school_id";
        $stmt = $dbh->prepare($query);
        $stmt->bindParam(":school_id", $_SESSION['school_id'], PDO::PARAM_INT);
        $stmt->execute();
        $school_info = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($school_info) {
            $_SESSION['school_name'] = $school_info['school_name'];
            $stats['subscription']['license_tier'] = $school_info['license_tier'];
            $stats['subscription']['status'] = $school_info['status'];
            $sms_balance = $school_info['sms_balance'] ?? 0;
        }

        // Students with gender breakdown (filtered by academic level)
        $query = "SELECT COUNT(*) as total, SUM(CASE WHEN s.Gender = 'Male' THEN 1 ELSE 0 END) as male_count, SUM(CASE WHEN s.Gender = 'Female' THEN 1 ELSE 0 END) as female_count FROM tblstudents s JOIN tblclasses c ON s.class_id = c.id WHERE s.school_id = :school_id AND s.Status = 'Active' AND c.academic_level = :academic_level";
        $stmt = $dbh->prepare($query);
        $stmt->bindParam(":school_id", $_SESSION['school_id'], PDO::PARAM_INT);
        $stmt->bindParam(":academic_level", $current_level);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $stats['total_students'] = $result['total'] ?? 0;
            $stats['male_students'] = $result['male_count'] ?? 0;
            $stats['female_students'] = $result['female_count'] ?? 0;
        }

        // Teachers with gender breakdown (school-wide, not filtered)
        $query = "SELECT COUNT(*) as total, SUM(CASE WHEN gender = 'Male' THEN 1 ELSE 0 END) as male_count, SUM(CASE WHEN gender = 'Female' THEN 1 ELSE 0 END) as female_count FROM tblteachers WHERE school_id = :school_id";
        $stmt = $dbh->prepare($query);
        $stmt->bindParam(":school_id", $_SESSION['school_id'], PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $stats['total_teachers'] = $result['total'] ?? 0;
            $stats['male_teachers'] = $result['male_count'] ?? 0;
            $stats['female_teachers'] = $result['female_count'] ?? 0;
        }

        // Classes (filtered by academic level)
        $query = "SELECT COUNT(*) as total FROM tblclasses WHERE school_id = :school_id AND academic_level = :academic_level";
        $stmt = $dbh->prepare($query);
        $stmt->bindParam(":school_id", $_SESSION['school_id'], PDO::PARAM_INT);
        $stmt->bindParam(":academic_level", $current_level);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['total_classes'] = $result ? $result['total'] : 0;

        // Recent Events (school-wide, not filtered)
        $query = "SELECT title, event_date, event_time, description FROM tblevents WHERE school_id = :school_id AND event_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) ORDER BY event_date ASC, event_time ASC LIMIT 5";
        $stmt = $dbh->prepare($query);
        $stmt->bindParam(":school_id", $_SESSION['school_id'], PDO::PARAM_INT);
        $stmt->execute();
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Recent Activity (filtered by academic level)
        $query = "SELECT s.FirstName, s.LastName, s.Gender as student_gender, sub.subject_name, sc.score_value, sc.exam_type, sc.recorded_at FROM tblscores sc JOIN tblstudents s ON sc.student_id = s.id JOIN tblsubjects sub ON sc.subject_id = sub.id JOIN tblclasses c ON s.class_id = c.id WHERE sc.school_id = :school_id AND c.academic_level = :academic_level AND sc.recorded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) ORDER BY sc.recorded_at DESC LIMIT 8";
        $stmt = $dbh->prepare($query);
        $stmt->bindParam(":school_id", $_SESSION['school_id'], PDO::PARAM_INT);
        $stmt->bindParam(":academic_level", $current_level);
        $stmt->execute();
        $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Top Performing Students (filtered by academic level)
        $query = "SELECT s.FirstName, s.LastName, s.Gender, s.AdmissionNo, ROUND(AVG(sc.score_value), 1) as avg_score, COUNT(sc.id) as total_exams FROM tblscores sc JOIN tblstudents s ON sc.student_id = s.id JOIN tblclasses c ON s.class_id = c.id WHERE sc.school_id = :school_id AND c.academic_level = :academic_level AND sc.recorded_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY s.id HAVING total_exams >= 3 ORDER BY avg_score DESC LIMIT 6";
        $stmt = $dbh->prepare($query);
        $stmt->bindParam(":school_id", $_SESSION['school_id'], PDO::PARAM_INT);
        $stmt->bindParam(":academic_level", $current_level);
        $stmt->execute();
        $top_students = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Performance Data for Chart (filtered by academic level)
        $query = "SELECT DATE_FORMAT(sc.recorded_at, '%Y-%m') as month, ROUND(AVG(sc.score_value), 1) as avg_score, COUNT(DISTINCT s.id) as students_count FROM tblscores sc JOIN tblstudents s ON sc.student_id = s.id JOIN tblclasses c ON s.class_id = c.id WHERE sc.school_id = :school_id AND c.academic_level = :academic_level AND sc.recorded_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) GROUP BY DATE_FORMAT(sc.recorded_at, '%Y-%m') ORDER BY month ASC";
        $stmt = $dbh->prepare($query);
        $stmt->bindParam(":school_id", $_SESSION['school_id'], PDO::PARAM_INT);
        $stmt->bindParam(":academic_level", $current_level);
        $stmt->execute();
        $performance_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch(PDOException $e) {
        error_log("Dashboard data fetch error: " . $e->getMessage());
    }

    // Get current user info
    try {
        $query = "SELECT firstname, lastname, email, phonenumber, role FROM tblteachers WHERE id = :user_id AND school_id = :school_id";
        $stmt = $dbh->prepare($query);
        $stmt->bindParam(":user_id", $_SESSION['teacher_id'], PDO::PARAM_INT);
        $stmt->bindParam(":school_id", $_SESSION['school_id'], PDO::PARAM_INT);
        $stmt->execute();
        $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user_info) {
            $_SESSION['user_fullname'] = $user_info['firstname'] . ' ' . $user_info['lastname'];
            $_SESSION['user_role'] = $user_info['role'];
        } else {
            $_SESSION['user_fullname'] = $_SESSION['user_name'] ?? 'User';
            $_SESSION['user_role'] = 'Administrator';
        }
    } catch(PDOException $e) {
        error_log("User info fetch error: " . $e->getMessage());
        $_SESSION['user_fullname'] = $_SESSION['user_name'] ?? 'User';
        $_SESSION['user_role'] = 'Administrator';
    }
} else {
    // Demo mode - use sample data
    $_SESSION['demo_mode'] = true;
    $stats = [
        'total_students' => 850,
        'total_teachers' => 42,
        'total_classes' => 24,
        'male_students' => 430,
        'female_students' => 420,
        'male_teachers' => 22,
        'female_teachers' => 20,
        'subscription' => ['license_tier' => 'Demo', 'status' => 'active']
    ];
    $sms_balance = 150;
    
    $events = [
        ['title' => 'Parents Meeting', 'event_date' => date('Y-m-d', strtotime('+2 days')), 'event_time' => '14:00', 'description' => 'Term 1 parents meeting'],
        ['title' => 'Sports Day', 'event_date' => date('Y-m-d', strtotime('+5 days')), 'event_time' => '09:00', 'description' => 'Annual sports competition'],
    ];
    
    $top_students = [
        ['FirstName' => 'John', 'LastName' => 'Mwangi', 'Gender' => 'Male', 'AdmissionNo' => 'D001', 'avg_score' => 92.5],
        ['FirstName' => 'Mary', 'LastName' => 'Wambui', 'Gender' => 'Female', 'AdmissionNo' => 'D002', 'avg_score' => 89.7],
        ['FirstName' => 'Peter', 'LastName' => 'Kamau', 'Gender' => 'Male', 'AdmissionNo' => 'D003', 'avg_score' => 87.3],
    ];
    
    $performance_data = [
        ['month' => date('Y-m'), 'avg_score' => 85.5],
        ['month' => date('Y-m', strtotime('-1 month')), 'avg_score' => 82.3],
        ['month' => date('Y-m', strtotime('-2 months')), 'avg_score' => 79.8],
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
<title>EduScore - Modern School Management System</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="icon" type="image/png" href="images/logo.png" />
<link rel="apple-touch-icon" href="images/logo.png">
<style>
/* [ALL EXISTING STYLES REMAIN THE SAME - KEEP YOUR ORIGINAL STYLES HERE] */
:root {
    --primary-blue: #1e40af; --secondary-blue: #3b82f6; --light-blue: #dbeafe; --dark-blue: #1e3a8a; --accent-blue: #60a5fa; --success-green: #10b981; --warning-orange: #f59e0b; --error-red: #ef4444; --text-dark: #1f2937; --text-light: #6b7280; --text-white: #ffffff; --bg-light: #f9fafb; --bg-white: #ffffff; --border-color: #e5e7eb; --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); --shadow-xl: 0 25px 50px -12px rgba(0, 0, 0, 0.25); --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%); --gradient-success: linear-gradient(135deg, #10b981 0%, #047857 100%); --gradient-warning: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); --gradient-purple: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); --gradient-blue: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%); --gradient-light: linear-gradient(135deg, #f0f4ff 0%, #e6f0ff 100%); --sidebar-width: 220px; --sidebar-collapsed-width: 70px; --glass-bg: rgba(255, 255, 255, 0.9); --glass-border: rgba(255, 255, 255, 0.3); --glass-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15); --transition-speed: 0.3s;
}
* { 
    margin: 0; 
    padding: 0; 
    box-sizing: border-box; 
    font-family: 'Inter', sans-serif; 
}
body { 
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); 
    color: var(--text-dark); 
    min-height: 100vh; 
    overflow-x: hidden; 
}

/* Trial Banner */
#trialBanner { 
    position: fixed; 
    top: var(--header-height); 
    left: var(--sidebar-width); 
    right: 0; 
    text-align: center; 
    padding: 10px 0; 
    font-weight: 600; 
    z-index: 998; 
    box-shadow: 0 2px 8px rgba(0,0,0,0.15); 
    border-bottom: 1px solid; 
    font-size: 14px; 
    transition: left var(--transition-speed) cubic-bezier(0.4, 0, 0.2, 1); 
    background: linear-gradient(135deg, #f4c430 0%, #fbbf24 100%); 
    color: #0a2a66; 
    border-bottom-color: #d4af37; 
    height: 40px; 
    line-height: 20px; 
}
#trialBanner.expired-trial { 
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); 
    color: #fff; 
    border-bottom-color: #991b1b; 
}
.sidebar.collapsed ~ #trialBanner { left: var(--sidebar-collapsed-width); }

/* Main Content */
.main-content { 
    margin-left: var(--sidebar-width); 
    transition: margin-left var(--transition-speed) cubic-bezier(0.4, 0, 0.2, 1); 
    min-height: calc(100vh - var(--header-height)); 
    background: #f8fafc; 
    position: relative; 
    width: calc(100% - var(--sidebar-width)); 
    padding: 20px; 
    padding-top: 80px; 
}
.sidebar.collapsed ~ .main-content { 
    margin-left: var(--sidebar-collapsed-width); 
    width: calc(100% - var(--sidebar-collapsed-width)); 
}

/* Dashboard Header */
.dashboard-header { 
    padding: 1.5rem 0 1.5rem; 
    margin-top: 20px; 
    margin-bottom: 2rem; 
    background: #ffffff; 
    border-radius: 12px; 
    border: 1px solid rgba(0, 0, 0, 0.07); 
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); 
    transition: all 0.25s ease; 
    position: relative; 
    z-index: 1; 
    overflow: hidden;
}
.dashboard-header:hover { 
    transform: translateY(-2px); 
    border: 1px solid rgba(30, 64, 175, 0.2); 
    box-shadow: 0 6px 16px rgba(30, 64, 175, 0.1); 
}
.container { 
    max-width: 1400px; 
    width: 100%; 
    margin: 0 auto; 
    padding: 0 20px; 
    position: relative; 
    z-index: 1; 
}
.welcome-section { 
    text-align: center; 
    padding: 0 1rem; 
    position: relative; 
    z-index: 1; 
}
.welcome-title { 
    font-size: clamp(1.5rem, 4vw, 2rem); 
    font-weight: 700; 
    color: #1e3a8a; 
    margin-bottom: 0.5rem; 
    line-height: 1.2; 
    word-wrap: break-word;
    overflow-wrap: break-word;
}
.welcome-title span { 
    display: inline-block; 
    margin-right: 8px; 
}
.quote-rotator { 
    font-size: clamp(0.9rem, 2vw, 1rem); 
    color: #4b5563; 
    margin-bottom: 0.75rem; 
    min-height: 24px; 
    transition: all 0.4s ease; 
    font-style: italic; 
    line-height: 1.4;
}
.welcome-subtext { 
    font-size: clamp(0.8rem, 1.5vw, 0.9rem); 
    color: #6b7280; 
    margin-bottom: 0.25rem; 
    line-height: 1.4;
}

/* =========================
   KPI GRID - RESPONSIVE
========================= */
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.25rem;
    margin-bottom: 2.5rem;
    width: 100%;
}

/* =========================
   KPI CARD
========================= */
.kpi-card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1.25rem;
    transition: all 0.25s ease;
    overflow: hidden;
    min-width: 0;
    width: 100%;
    flex-shrink: 0;
}

.kpi-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 28px rgba(0, 0, 0, 0.08);
}

/* =========================
   KPI ICON
========================= */
.kpi-icon {
    flex-shrink: 0;
    width: 64px;
    height: 64px;
    border-radius: 14px;
    border: 2px solid #d1d5db;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    color: #374151;
    transition: all 0.25s ease;
}

.kpi-card:hover .kpi-icon {
    transform: scale(1.08);
}

/* =========================
   KPI CONTENT
========================= */
.kpi-content {
    display: flex;
    flex-direction: column;
    min-width: 0;
    flex: 1;
    overflow: hidden;
}

.kpi-content h3 {
    font-size: clamp(1.5rem, 3vw, 2.1rem);
    font-weight: 800;
    margin-bottom: 0.15rem;
    color: #111827;
    line-height: 1.2;
    word-break: break-word;
    overflow: hidden;
}

.kpi-content p {
    font-size: clamp(0.9rem, 1.5vw, 1rem);
    color: #6b7280;
    font-weight: 600;
    margin-bottom: 0.6rem;
    line-height: 1.3;
    word-break: break-word;
}

/* =========================
   GENDER BREAKDOWN
========================= */
.gender-breakdown {
    display: flex;
    gap: 1rem;
    margin-bottom: 0.6rem;
    flex-wrap: wrap;
}

.gender-item {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: clamp(0.75rem, 1vw, 0.85rem);
    min-width: 0;
    flex-shrink: 0;
}

.gender-item i {
    background: transparent;
    border: 1.5px solid #d1d5db;
    color: #6b7280;
    padding: 5px;
    border-radius: 50%;
    font-size: clamp(0.7rem, 1vw, 0.8rem);
    flex-shrink: 0;
}

.male-icon { color: #2563eb; border-color: #bfdbfe; }
.female-icon { color: #db2777; border-color: #fbcfe8; }

.gender-count {
    font-weight: 700;
    color: #374151;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* =========================
   KPI TREND
========================= */
.kpi-trend {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: clamp(0.75rem, 1vw, 0.8rem);
    font-weight: 600;
    color: #6b7280;
    flex-shrink: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.2;
}

.trend-up { color: #10b981; }
.trend-down { color: #ef4444; }

/* =========================
   CARD ACCENTS
========================= */
.kpi-card.student:hover { border-color: #bfdbfe; }
.kpi-card.student .kpi-icon { color: #2563eb; border-color: #bfdbfe; }

.kpi-card.teacher:hover { border-color: #a7f3d0; }
.kpi-card.teacher .kpi-icon { color: #059669; border-color: #a7f3d0; }

.kpi-card.class:hover { border-color: #ddd6fe; }
.kpi-card.class .kpi-icon { color: #7c3aed; border-color: #ddd6fe; }

.kpi-card.subscription:hover { border-color: #fde68a; }
.kpi-card.subscription .kpi-icon { color: #d97706; border-color: #fde68a; }

.kpi-card.sms:hover { border-color: #bae6fd; }
.kpi-card.sms .kpi-icon { color: #0284c7; border-color: #bae6fd; }

/* =========================
   SMS TOPUP BUTTON
========================= */
.sms-topup-btn {
    margin-top: 0.6rem;
    padding: 0.35rem 0.6rem;
    font-size: clamp(0.75rem, 1vw, 0.8rem);
    font-weight: 600;
    border-radius: 8px;
    border: 1px solid #bae6fd;
    background: #f0f9ff;
    color: #0369a1;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    transition: all 0.2s ease;
    flex-shrink: 0;
    width: fit-content;
    max-width: 100%;
    white-space: nowrap;
}

.sms-topup-btn:hover {
    background: #e0f2fe;
    border-color: #7dd3fc;
}

/* Toast Notifications */
.toast-notification {
    position: fixed;
    top: 20px;
    right: 20px;
    background: white;
    border-radius: 12px;
    padding: 16px 20px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.15);
    display: flex;
    align-items: center;
    gap: 12px;
    z-index: 2000;
    animation: slideInRight 0.3s ease;
    max-width: 400px;
    min-width: 280px;
    border-left: 4px solid;
}

.toast-notification.success {
    border-left-color: #10b981;
}
.toast-notification.error {
    border-left-color: #ef4444;
}
.toast-notification.info {
    border-left-color: #3b82f6;
}

.toast-icon {
    font-size: 1.5rem;
}
.toast-content {
    flex: 1;
}
.toast-title {
    font-weight: 700;
    margin-bottom: 4px;
}
.toast-message {
    font-size: 0.875rem;
    color: #6b7280;
}
.toast-close {
    background: none;
    border: none;
    font-size: 1.2rem;
    cursor: pointer;
    color: #9ca3af;
    padding: 0;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.toast-close:hover {
    color: #374151;
}

@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

/* Dashboard Grid */
.dashboard-grid { 
    display: grid; 
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); 
    gap: 1.5rem; 
    margin-bottom: 2rem; 
}
.card { 
    background: var(--bg-white); 
    border-radius: 20px; 
    overflow: hidden; 
    box-shadow: var(--shadow); 
    transition: all 0.3s ease; 
    min-width: 0;
}
.card:hover { 
    transform: translateY(-5px); 
    box-shadow: var(--shadow-lg); 
}
.card-header { 
    padding: 1.5rem; 
    border-bottom: 1px solid var(--border-color); 
    background: var(--gradient-light); 
    overflow: hidden;
}
.card-header h3 { 
    display: flex; 
    align-items: center; 
    gap: 0.75rem; 
    font-size: clamp(1.1rem, 2vw, 1.25rem); 
    font-weight: 600; 
    color: var(--text-dark); 
    flex-wrap: wrap;
}
.card-body { 
    padding: 1.5rem; 
    overflow-x: hidden;
}
.card-header-flex { 
    display: flex; 
    align-items: center; 
    justify-content: space-between; 
    flex-wrap: wrap;
    gap: 1rem;
}
.add-event-btn { 
    background: #1e3a8a; 
    color: #fff; 
    border: none; 
    padding: 0.4rem 0.75rem; 
    font-size: clamp(0.75rem, 1vw, 0.8rem); 
    border-radius: 8px; 
    font-weight: 600; 
    cursor: pointer; 
    display: flex; 
    align-items: center; 
    gap: 0.35rem; 
    transition: background 0.2s ease; 
    white-space: nowrap;
    flex-shrink: 0;
}
.add-event-btn:hover { 
    background: #1e40af; 
}

/* Mini Calendar */
.mini-calendar { 
    background: #f9fafb; 
    border: 1px solid #e5e7eb; 
    border-radius: 12px; 
    padding: 0.75rem; 
    margin-bottom: 1.25rem; 
    overflow: hidden;
}
.calendar-header { 
    display: flex; 
    justify-content: space-between; 
    font-size: clamp(0.8rem, 1vw, 0.85rem); 
    font-weight: 700; 
    color: #111827; 
    margin-bottom: 0.5rem; 
    flex-wrap: wrap;
}
.calendar-grid { 
    display: grid; 
    grid-template-columns: repeat(7, 1fr); 
    gap: 4px; 
}
.calendar-day { 
    text-align: center; 
    padding: 0.35rem 0; 
    font-size: clamp(0.7rem, 1vw, 0.75rem); 
    border-radius: 6px; 
    color: #374151; 
    min-height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.calendar-day.today { 
    background: #1e3a8a; 
    color: #ffffff; 
    font-weight: 700; 
}
.calendar-day.muted { 
    color: #9ca3af; 
}
.calendar-day.has-event { 
    background: #e0ecff; 
    border: 1px solid #bfdbfe; 
    font-weight: 700; 
    cursor: pointer; 
}
.calendar-day:hover { 
    background: #1e3a8a; 
    color: #fff; 
}

/* Events List */
.events-list { 
    display: flex; 
    flex-direction: column; 
    gap: 1rem; 
}
.event-item { 
    display: flex; 
    gap: 1rem; 
    padding: 1rem; 
    background: var(--bg-light); 
    border-radius: 12px; 
    border-left: 4px solid var(--secondary-blue); 
    transition: all 0.2s ease; 
    flex-wrap: wrap;
    min-width: 0;
}
.event-item:hover { 
    background: var(--light-blue); 
    transform: translateX(5px); 
}
.event-date { 
    display: flex; 
    flex-direction: column; 
    align-items: center; 
    justify-content: center; 
    min-width: 60px; 
    background: white; 
    padding: 0.75rem; 
    border-radius: 10px; 
    box-shadow: var(--shadow); 
    flex-shrink: 0;
}
.event-date .day { 
    font-size: clamp(1.2rem, 2vw, 1.5rem); 
    font-weight: 700; 
    color: var(--primary-blue); 
}
.event-date .month { 
    font-size: clamp(0.8rem, 1vw, 0.9rem); 
    color: var(--text-light); 
    text-transform: uppercase; 
}
.event-details { 
    flex: 1; 
    min-width: 0;
}
.event-details h4 { 
    font-size: clamp(0.9rem, 1.5vw, 1rem); 
    font-weight: 600; 
    margin-bottom: 0.25rem; 
    color: var(--text-dark); 
    word-break: break-word;
}
.event-details p { 
    font-size: clamp(0.8rem, 1.2vw, 0.9rem); 
    color: var(--text-light); 
    margin-bottom: 0.5rem; 
    line-height: 1.4; 
    word-break: break-word;
}
.event-time { 
    display: flex; 
    align-items: center; 
    gap: 0.5rem; 
    font-size: clamp(0.8rem, 1vw, 0.85rem); 
    color: var(--text-light); 
    flex-wrap: wrap;
}

/* Chart */
.chart-container { 
    height: 280px; 
    margin-bottom: 1.5rem; 
    overflow: hidden;
    position: relative;
}

/* Students */
.student-list { 
    display: flex; 
    flex-direction: column; 
    gap: 0.75rem; 
}
.student-item { 
    display: flex; 
    align-items: center; 
    justify-content: space-between; 
    padding: 0.75rem; 
    background: var(--bg-light); 
    border-radius: 10px; 
    transition: all 0.2s ease; 
    flex-wrap: wrap;
    gap: 0.5rem;
}
.student-item:hover { 
    background: var(--light-blue); 
    transform: translateX(5px); 
}
.student-info { 
    display: flex; 
    align-items: center; 
    gap: 0.75rem; 
    min-width: 0;
    flex: 1;
}
.student-avatar { 
    width: 40px; 
    height: 40px; 
    border-radius: 50%; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    font-weight: 600; 
    color: white; 
    font-size: clamp(0.9rem, 1.2vw, 1rem); 
    flex-shrink: 0;
}
.student-avatar.male { 
    background: linear-gradient(135deg, #3b82f6, #60a5fa); 
}
.student-avatar.female { 
    background: linear-gradient(135deg, #ec4899, #f472b6); 
}
.student-details h5 { 
    font-size: clamp(0.85rem, 1.2vw, 0.95rem); 
    font-weight: 600; 
    margin-bottom: 0.15rem; 
    display: flex; 
    align-items: center; 
    gap: 0.5rem; 
    flex-wrap: wrap;
    word-break: break-word;
}
.student-details p { 
    font-size: clamp(0.75rem, 1vw, 0.85rem); 
    color: var(--text-light); 
    word-break: break-word;
}
.score { 
    font-weight: 700; 
    font-size: clamp(1rem, 1.5vw, 1.1rem); 
    color: var(--primary-blue); 
    flex-shrink: 0;
}

/* Activity */
.activity-feed { 
    display: flex; 
    flex-direction: column; 
    gap: 1rem; 
}
.activity-item { 
    display: flex; 
    gap: 1rem; 
    padding: 1rem; 
    background: var(--bg-light); 
    border-radius: 12px; 
    transition: all 0.2s ease; 
    flex-wrap: wrap;
    min-width: 0;
}
.activity-item:hover { 
    background: var(--light-blue); 
    transform: translateX(5px); 
}
.activity-icon { 
    width: 40px; 
    height: 40px; 
    border-radius: 10px; 
    background: var(--secondary-blue); 
    color: white; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    flex-shrink: 0; 
}
.activity-content { 
    flex: 1; 
    min-width: 0;
}
.activity-content p { 
    margin-bottom: 0.25rem; 
    font-size: clamp(0.85rem, 1.2vw, 0.95rem); 
    line-height: 1.4; 
    word-break: break-word;
}
.activity-content small { 
    color: var(--text-light); 
    display: flex; 
    align-items: center; 
    gap: 0.5rem; 
    flex-wrap: wrap;
    font-size: clamp(0.75rem, 1vw, 0.85rem);
}
.activity-gender { 
    font-size: clamp(0.7rem, 1vw, 0.8rem); 
}
.male-icon { 
    color: #3b82f6; 
}
.female-icon { 
    color: #ec4899; 
}
.exam-badge { 
    background: var(--accent-blue); 
    color: white; 
    padding: 0.15rem 0.5rem; 
    border-radius: 4px; 
    font-size: clamp(0.7rem, 1vw, 0.75rem); 
    margin-left: 0.5rem; 
}

/* Quick Actions */
.quick-actions-grid { 
    display: grid; 
    grid-template-columns: repeat(2, 1fr); 
    gap: 1rem; 
}
.quick-action-btn { 
    display: flex; 
    flex-direction: column; 
    align-items: center; 
    justify-content: center; 
    padding: 1.5rem; 
    background: var(--bg-light); 
    border-radius: 15px; 
    text-decoration: none; 
    color: var(--text-dark); 
    transition: all 0.3s ease; 
    border: 2px solid transparent; 
    text-align: center;
}
.quick-action-btn:hover { 
    background: var(--light-blue); 
    border-color: var(--secondary-blue); 
    transform: translateY(-3px); 
}
.action-icon { 
    width: 50px; 
    height: 50px; 
    border-radius: 12px; 
    background: var(--gradient-blue); 
    color: white; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    font-size: clamp(1.2rem, 2vw, 1.5rem); 
    margin-bottom: 0.75rem; 
}
.quick-action-btn span { 
    font-weight: 500; 
    font-size: clamp(0.85rem, 1.2vw, 0.95rem); 
    text-align: center; 
    word-break: break-word;
}

/* Empty State */
.empty-state { 
    text-align: center; 
    padding: 2.5rem 1rem; 
    color: var(--text-light); 
}
.empty-state i { 
    font-size: 3rem; 
    margin-bottom: 1rem; 
    opacity: 0.5; 
}
.empty-state h4 { 
    margin-bottom: 0.5rem; 
    color: var(--text-dark); 
    font-size: clamp(1rem, 1.5vw, 1.2rem);
}
.empty-state p { 
    font-size: clamp(0.9rem, 1.2vw, 1rem);
}

/* Background */
.bg-animation { 
    position: fixed; 
    top: 0; 
    left: 0; 
    width: 100%; 
    height: 100%; 
    z-index: -2; 
    overflow: hidden; 
}
.bg-shapes { 
    position: absolute; 
    width: 100%; 
    height: 100%; 
}
.shape { 
    position: absolute; 
    border-radius: 50%; 
    background: linear-gradient(45deg, var(--secondary-blue), var(--accent-blue)); 
    opacity: 0.1; 
    animation: float 6s ease-in-out infinite; 
}
.shape:nth-child(1) { 
    width: 300px; 
    height: 300px; 
    top: -150px; 
    left: -150px; 
    animation-delay: 0s; 
}
.shape:nth-child(2) { 
    width: 200px; 
    height: 200px; 
    bottom: -100px; 
    right: 10%; 
    animation-delay: 2s; 
}
.shape:nth-child(3) { 
    width: 150px; 
    height: 150px; 
    top: 20%; 
    right: -75px; 
    animation-delay: 4s; 
}
@keyframes float { 
    0%, 100% { transform: translateY(0px) rotate(0deg); } 
    50% { transform: translateY(-20px) rotate(10deg); } 
}
/* Grade Badge Styles */
.grade-badge {
    transition: all 0.3s ease;
}

.grade-badge:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

/* Class Summary Hover Effects */
#classSummaryList > div {
    transition: all 0.3s ease;
    cursor: pointer;
}

#classSummaryList > div:hover {
    transform: translateX(8px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    border-left: 4px solid #3b82f6;
}

/* Performance Chart Container */
.chart-container {
    position: relative;
    background: #ffffff;
    border-radius: 12px;
    padding: 10px;
}

/* Loading Spinner */
.loading-spinner {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: rgba(255,255,255,0.95);
    padding: 12px 24px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    z-index: 10;
    font-weight: 600;
    color: #1e3a8a;
}

.loading-spinner i {
    margin-right: 8px;
}
/* Modal */
.modal-overlay { 
    position: fixed; 
    inset: 0; 
    background: rgba(15, 23, 42, 0.55); 
    display: none; 
    align-items: center; 
    justify-content: center; 
    z-index: 1000; 
}
.modal-card { 
    background: #ffffff; 
    width: 100%; 
    max-width: 420px; 
    border-radius: 14px; 
    box-shadow: 0 20px 40px rgba(0,0,0,0.15); 
    padding: 1.25rem; 
    animation: modalFade 0.25s ease; 
    margin: 1rem;
}
@keyframes modalFade { 
    from { transform: translateY(10px); opacity: 0; } 
    to { transform: translateY(0); opacity: 1; } 
}
.modal-header { 
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
    margin-bottom: 1rem; 
}
.modal-header h3 { 
    font-size: clamp(1.1rem, 2vw, 1.2rem); 
    font-weight: 700; 
    color: #111827; 
}
.modal-close { 
    background: none; 
    border: none; 
    font-size: 1.4rem; 
    cursor: pointer; 
    color: #6b7280; 
}
.modal-body { 
    display: flex; 
    flex-direction: column; 
    gap: 0.75rem; 
}
.modal-body label { 
    font-size: clamp(0.8rem, 1vw, 0.85rem); 
    font-weight: 600; 
    color: #374151; 
}
.modal-body input, .modal-body select { 
    padding: 0.55rem 0.6rem; 
    border-radius: 8px; 
    border: 1px solid #d1d5db; 
    font-size: clamp(0.85rem, 1vw, 0.9rem); 
    width: 100%;
}
.modal-body input:focus { 
    outline: none; 
    border-color: #1e3a8a; 
    box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.15); 
}
.modal-body input:hover { 
    border-color: #94a3b8; 
}
.modal-footer { 
    display: flex; 
    justify-content: flex-end; 
    gap: 0.5rem; 
    margin-top: 1.25rem; 
    flex-wrap: wrap;
}
.modal-cancel { 
    background: #f3f4f6; 
    border: 1px solid #e5e7eb; 
    padding: 0.45rem 0.8rem; 
    border-radius: 8px; 
    font-weight: 600; 
    cursor: pointer; 
    font-size: clamp(0.8rem, 1vw, 0.9rem);
}
.modal-confirm { 
    background: #0284c7; 
    border: none; 
    color: #ffffff; 
    padding: 0.45rem 0.9rem; 
    border-radius: 8px; 
    font-weight: 600; 
    cursor: pointer; 
    font-size: clamp(0.8rem, 1vw, 0.9rem);
}
.modal-alert { 
    display: none; 
    background: #fef2f2; 
    border: 1px solid #fecaca; 
    color: #b91c1c; 
    padding: 0.6rem 0.75rem; 
    border-radius: 8px; 
    font-size: clamp(0.8rem, 1vw, 0.85rem); 
    font-weight: 600; 
}
.input-error { 
    border-color: #dc2626 !important; 
    box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.15); 
}
/* Performance Filters */
.performance-filters select {
    transition: all 0.2s ease;
}

.performance-filters select:focus {
    outline: none;
    border-color: #1e3a8a;
    box-shadow: 0 0 0 2px rgba(30, 58, 138, 0.1);
}

#loadPerformanceData {
    transition: all 0.2s ease;
}

#loadPerformanceData:hover {
    background: #1e40af;
    transform: translateY(-1px);
}

/* Grade Distribution */
#gradeBars div {
    transition: all 0.3s ease;
}

/* Class Summary */
#classSummaryList > div {
    transition: all 0.2s ease;
}

#classSummaryList > div:hover {
    transform: translateX(5px);
    background: #f0f9ff;
}
/* Animations */
@keyframes floating { 
    0%, 100% { transform: translateY(0px); } 
    50% { transform: translateY(-10px); } 
}
.floating { 
    animation: floating 3s ease-in-out infinite; 
}
@keyframes slideIn { 
    from { opacity: 0; transform: translateY(10px); } 
    to { opacity: 1; transform: translateY(0); } 
}
@keyframes slideOut { 
    from { opacity: 1; transform: translateY(0); } 
    to { opacity: 0; transform: translateY(-10px); } 
}
.quote-slide-in { 
    animation: slideIn 0.4s ease-out forwards; 
}
.quote-slide-out { 
    animation: slideOut 0.4s ease-in forwards; 
}

/* Background Classes */
.fade-bg { 
    transition: background 1.5s ease, color 0.6s ease; 
}
.morning-bg { 
    background: linear-gradient(135deg, rgba(255, 248, 225, 0.9) 0%, rgba(255, 243, 205, 0.9) 100%); 
}
.afternoon-bg { 
    background: linear-gradient(135deg, rgba(255, 245, 235, 0.9) 0%, rgba(255, 240, 220, 0.9) 100%); 
}
.evening-bg { 
    background: linear-gradient(135deg, rgba(240, 245, 255, 0.9) 0%, rgba(230, 240, 255, 0.9) 100%); 
}
.night-bg { 
    background: linear-gradient(135deg, rgba(40, 40, 80, 0.9) 0%, rgba(30, 30, 60, 0.9) 100%); 
    color: white; 
}
.night-bg .welcome-title { 
    background: linear-gradient(135deg, #a5b4fc, #c7d2fe); 
    -webkit-background-clip: text; 
    -webkit-text-fill-color: transparent; 
}
.night-bg .quote-rotator, .night-bg .welcome-subtext { 
    color: rgba(255, 255, 255, 0.8); 
}

/* =========================
   RESPONSIVE BREAKPOINTS
========================= */

/* Mobile (320px - 480px) */
@media (max-width: 480px) {
    .container {
        padding: 0 12px;
    }
    
    .main-content {
        padding: 15px;
        padding-top: 70px;
    }
    
    .dashboard-header {
        padding: 1rem 0;
        margin-bottom: 1.5rem;
    }
    
    .kpi-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .kpi-card {
        flex-direction: row;
        padding: 1.25rem;
        gap: 1rem;
    }
    
    .kpi-icon {
        width: 50px;
        height: 50px;
        font-size: 1.3rem;
    }
    
    .kpi-content h3 {
        font-size: 1.5rem;
    }
    
    .kpi-content p {
        font-size: 0.9rem;
    }
    
    .gender-breakdown {
        gap: 0.75rem;
    }
    
    .gender-item {
        font-size: 0.8rem;
    }
    
    .dashboard-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .card {
        border-radius: 15px;
    }
    
    .card-header,
    .card-body {
        padding: 1.25rem;
    }
    
    .quick-actions-grid {
        grid-template-columns: 1fr;
    }
    
    .quick-action-btn {
        padding: 1.25rem;
    }
    
    .action-icon {
        width: 45px;
        height: 45px;
        font-size: 1.3rem;
    }
    
    .calendar-grid {
        gap: 2px;
    }
    
    .calendar-day {
        padding: 0.25rem 0;
        min-height: 25px;
        font-size: 0.7rem;
    }
    
    .event-item {
        flex-direction: column;
        gap: 0.75rem;
    }
    
    .event-date {
        flex-direction: row;
        justify-content: space-between;
        width: 100%;
        padding: 0.5rem 1rem;
    }
    
    .student-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
    }
    
    .student-info {
        width: 100%;
    }
    
    .score {
        align-self: flex-end;
    }
    
    .modal-card {
        margin: 0.5rem;
        padding: 1rem;
    }
}

/* Tablet (481px - 768px) */
@media (min-width: 481px) and (max-width: 768px) {
    .container {
        padding: 0 15px;
    }
    
    .kpi-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .kpi-icon {
        width: 56px;
        height: 56px;
        font-size: 1.5rem;
    }
    
    .kpi-content h3 {
        font-size: 1.8rem;
    }
    
    .dashboard-grid {
        grid-template-columns: 1fr;
    }
    
    .quick-actions-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .event-item {
        flex-wrap: nowrap;
    }
}

/* Small Desktop (769px - 1024px) */
@media (min-width: 769px) and (max-width: 1024px) {
    .kpi-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .dashboard-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .kpi-icon {
        width: 60px;
        height: 60px;
        font-size: 1.6rem;
    }
}

/* Large Desktop (1025px and above) */
@media (min-width: 1025px) {
    .kpi-grid {
        grid-template-columns: repeat(4, 1fr);
    }
    
    .dashboard-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

/* Extra small devices */
@media (max-width: 320px) {
    .kpi-card {
        padding: 1rem;
    }
    
    .kpi-icon {
        width: 40px;
        height: 40px;
        font-size: 1.1rem;
    }
    
    .kpi-content h3 {
        font-size: 1.3rem;
    }
    
    .quick-action-btn span {
        font-size: 0.8rem;
    }
    
    .card-header h3 {
        font-size: 1rem;
    }
}

/* Landscape orientation */
@media (max-height: 500px) and (orientation: landscape) {
    .main-content {
        padding-top: 60px;
    }
    
    .dashboard-header {
        margin-top: 10px;
        margin-bottom: 1rem;
        padding: 1rem 0;
    }
    
    .kpi-grid {
        margin-bottom: 1.5rem;
    }
    
    .kpi-card {
        padding: 1rem;
    }
}

/* High DPI screens */
@media (-webkit-min-device-pixel-ratio: 2), (min-resolution: 192dpi) {
    .kpi-card,
    .card {
        border-width: 0.5px;
    }
}

/* Print styles */
@media print {
    .bg-animation,
    .modal-overlay,
    .add-event-btn,
    .sms-topup-btn {
        display: none !important;
    }
    
    .kpi-card,
    .card {
        break-inside: avoid;
        box-shadow: none;
        border: 1px solid #000;
    }
    
    .main-content {
        margin-left: 0;
        padding: 0;
    }
}
    /* Holiday Alert Styling */
.holiday-alert {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    color: #92400e;
    padding: 10px 20px;
    border-radius: 25px;
    margin: 10px auto;
    font-weight: 600;
    font-size: 0.95rem;
    display: inline-block;
    border: 2px solid #fbbf24;
    box-shadow: 0 4px 12px rgba(251, 191, 36, 0.2);
    animation: holidayPulse 2s ease-in-out infinite;
    max-width: 90%;
    text-align: center;
    line-height: 1.4;
}

.holiday-alert i {
    margin-right: 8px;
}

@keyframes holidayPulse {
    0%, 100% { 
        box-shadow: 0 4px 12px rgba(251, 191, 36, 0.2);
        transform: scale(1);
    }
    50% { 
        box-shadow: 0 6px 20px rgba(251, 191, 36, 0.4);
        transform: scale(1.02);
    }
}

/* Night mode override for holiday alert */
.night-bg .holiday-alert {
    background: linear-gradient(135deg, rgba(254, 243, 199, 0.2), rgba(253, 230, 138, 0.2));
    color: #fde68a;
    border-color: rgba(251, 191, 36, 0.5);
}
</style>
</head>
<body>

<?php include 'includes/sidebar.php'; ?>
<div class="main-content">
<?php include 'includes/header.php'; ?>
<?php include 'trial_banner.php'; ?>
<?php include 'demo_banner.php'; ?>

<div class="bg-animation">
    <div class="bg-shapes">
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
    </div>
</div>

<div class="dashboard-header">
    <div class="container">
        <div class="welcome-section">
            <h1 class="welcome-title">
                <span id="timeIcon"></span>
                <span id="greetingText"></span>, 
                <?php echo htmlspecialchars($_SESSION['user_fullname'] ?? $_SESSION['user_name'] ?? 'User'); ?>! 👋
            </h1>

            <!-- Quote from API -->
            <p id="quoteText" class="quote-rotator">Loading inspiration...</p>

            <!-- Holiday Alert -->
            <p id="holidayText" class="holiday-alert" style="display:none;"></p>

            <p class="welcome-subtext">
                Here's what's happening at <?php echo htmlspecialchars($_SESSION['school_name'] ?? 'Your School'); ?> today
            </p>
        </div>
    </div>
</div>

<div class="container">
    <!-- KPI Grid -->
    <div class="kpi-grid">
        <div class="kpi-card student">
            <div class="kpi-icon student">
                <i class="fas fa-users"></i>
            </div>
            <div class="kpi-content">
                <h3 id="studentCount"><?php echo number_format($stats['total_students']); ?></h3>
                <p>Total Students</p>
                <div class="gender-breakdown">
                    <div class="gender-item">
                        <i class="fas fa-mars male-icon"></i>
                        <span class="gender-count" id="maleStudentCount"><?php echo number_format($stats['male_students']); ?></span>
                    </div>
                    <div class="gender-item">
                        <i class="fas fa-venus female-icon"></i>
                        <span class="gender-count" id="femaleStudentCount"><?php echo number_format($stats['female_students']); ?></span>
                    </div>
                </div>
                <div class="kpi-trend trend-up">
                    <i class="fas fa-arrow-up"></i>
                    <span>Active and enrolled</span>
                </div>
            </div>
        </div>

        <div class="kpi-card teacher">
            <div class="kpi-icon teacher">
                <i class="fas fa-chalkboard-teacher"></i>
            </div>
            <div class="kpi-content">
                <h3 id="teacherCount"><?php echo number_format($stats['total_teachers']); ?></h3>
                <p>Teaching Staff</p>
                <div class="gender-breakdown">
                    <div class="gender-item">
                        <i class="fas fa-mars male-icon"></i>
                        <span class="gender-count" id="maleTeacherCount"><?php echo number_format($stats['male_teachers']); ?></span>
                    </div>
                    <div class="gender-item">
                        <i class="fas fa-venus female-icon"></i>
                        <span class="gender-count" id="femaleTeacherCount"><?php echo number_format($stats['female_teachers']); ?></span>
                    </div>
                </div>
                <div class="kpi-trend trend-up">
                    <i class="fas fa-user-check"></i>
                    <span>Active faculty</span>
                </div>
            </div>
        </div>

        <div class="kpi-card class">
            <div class="kpi-icon class">
                <i class="fas fa-school"></i>
            </div>
            <div class="kpi-content">
                <h3 id="classCount"><?php echo number_format($stats['total_classes']); ?></h3>
                <p>Active Classes</p>
                <div class="kpi-trend trend-up">
                    <i class="fas fa-layer-group"></i>
                    <span>Running classes</span>
                </div>
            </div>
        </div>

        <div class="kpi-card sms">
            <div class="kpi-icon sms">
                <i class="fas fa-sms"></i>
            </div>
            <div class="kpi-content">
                <h3 id="smsBalance"><?php echo number_format($sms_balance); ?></h3>
                <p>SMS Balance</p>
                <button class="sms-topup-btn" onclick="openSmsTopupModal()">
                    <i class="fas fa-plus-circle"></i>
                    Top Up SMS
                </button>
            </div>
        </div>
    </div>

    <!-- Dashboard Grid -->
    <div class="dashboard-grid">
        <!-- Upcoming Events Card -->
        <div class="card">
            <div class="card-header card-header-flex">
                <h3><i class="fas fa-calendar-alt"></i>Upcoming Events</h3>
                <button class="add-event-btn">
                    <i class="fas fa-plus"></i>Add Event
                </button>
            </div>
            <div class="card-body">
                <div class="mini-calendar">
                    <div class="calendar-header">
                        <span id="calendarMonth"></span>
                        <span id="calendarYear"></span>
                    </div>
                    <div class="calendar-grid" id="calendarGrid"></div>
                </div>
                <div class="events-list" id="eventsList">
                    <?php if (!empty($events)): ?>
                        <?php foreach ($events as $event): ?>
                            <div class="event-item">
                                <div class="event-date">
                                    <span class="day"><?php echo date('d', strtotime($event['event_date'])); ?></span>
                                    <span class="month"><?php echo date('M', strtotime($event['event_date'])); ?></span>
                                </div>
                                <div class="event-details">
                                    <h4><?php echo htmlspecialchars($event['title']); ?></h4>
                                    <p><?php echo htmlspecialchars($event['description'] ?? 'No description available'); ?></p>
                                    <div class="event-time">
                                        <i class="fas fa-clock"></i>
                                        <?php echo date('h:i A', strtotime($event['event_time'])); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-day"></i>
                            <h4>No Upcoming Events</h4>
                            <p>Schedule events to see them here</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

<!-- Academic Performance Card -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-chart-line"></i>Academic Performance</h3>
    </div>
    <div class="card-body">
        <!-- Performance Filters -->
        <div class="performance-filters" style="margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 120px;">
                <label style="font-size: 0.8rem; font-weight: 600; color: #6b7280; margin-bottom: 4px; display: block;">Year</label>
                <select id="performanceYear" class="performance-filter" style="width: 100%; padding: 8px; border-radius: 8px; border: 1px solid #e5e7eb; background: white;">
                    <option value="">Select Year</option>
                </select>
            </div>
            <div style="flex: 1; min-width: 120px;">
                <label style="font-size: 0.8rem; font-weight: 600; color: #6b7280; margin-bottom: 4px; display: block;">Term</label>
                <select id="performanceTerm" class="performance-filter" style="width: 100%; padding: 8px; border-radius: 8px; border: 1px solid #e5e7eb; background: white;">
                    <option value="">Select Term</option>
                </select>
            </div>
            <div style="display: flex; align-items: flex-end;">
                <button id="loadPerformanceData" class="add-event-btn" style="background: #1e3a8a; padding: 8px 16px;">
                    <i class="fas fa-chart-line"></i> Load Data
                </button>
            </div>
        </div>
        
        <!-- Overall Stats Summary -->
        <div id="overallStats" style="display: none; background: linear-gradient(135deg, #f0f9ff, #e6f0ff); border-radius: 12px; padding: 15px; margin-bottom: 20px;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
                <div style="text-align: center;">
                    <div style="font-size: 1.8rem; font-weight: 800; color: #1e3a8a;" id="totalStudents">0</div>
                    <div style="font-size: 0.85rem; color: #6b7280;">Total Students</div>
                </div>
                <div style="text-align: center;">
                    <div style="font-size: 1.8rem; font-weight: 800; color: #10b981;" id="overallMeanMark">0</div>
                    <div style="font-size: 0.85rem; color: #6b7280;">Mean Marks</div>
                </div>
                <div style="text-align: center;">
                    <div style="font-size: 1.8rem; font-weight: 800; color: #f59e0b;" id="highestScore">0%</div>
                    <div style="font-size: 0.85rem; color: #6b7280;">Highest Score</div>
                </div>
                <div style="text-align: center;">
                    <div style="font-size: 1.8rem; font-weight: 800; color: #ef4444;" id="lowestScore">0%</div>
                    <div style="font-size: 0.85rem; color: #6b7280;">Lowest Score</div>
                </div>
            </div>
        </div>
        
        <div class="chart-container" style="position: relative;">
            <canvas id="performanceChart" width="400" height="280"></canvas>
        </div>
        
        <!-- Grade Distribution -->
        <div id="gradeDistribution" style="margin: 20px 0; display: none;">
            <h4 style="margin-bottom: 12px;"><i class="fas fa-chart-pie"></i> Grade Distribution</h4>
            <div id="gradeBars" style="display: flex; flex-direction: column; gap: 8px;"></div>
        </div>
        
        <div class="top-students">
            <h4><i class="fas fa-trophy"></i>Top Performing Students</h4>
            <div class="student-list" id="topStudentsList">
                <div class="empty-state" style="padding: 1rem;">
                    <i class="fas fa-chart-bar"></i>
                    <p>Select a term and year to view performance data</p>
                </div>
            </div>
        </div>
        
        <!-- Class Performance Summary -->
        <div id="classSummary" style="margin-top: 20px; display: none;">
            <h4><i class="fas fa-school"></i> Class Performance</h4>
            <div id="classSummaryList" style="display: flex; flex-direction: column; gap: 12px;"></div>
        </div>
    </div>
</div>
        <!-- Recent Activity Card -->
        <div class="card">
            <div class="card-header card-header-flex">
                <h3><i class="fas fa-bell"></i> Recent Activity</h3>
                <button class="add-event-btn" onclick="refreshActivity()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
            <div class="card-body">
                <div class="activity-feed" id="activity-feed">
                    <div class="activity-item" style="justify-content: center; padding: 2rem;">
                        <i class="fas fa-spinner fa-spin" style="color: #3b82f6; font-size: 1.5rem;"></i>
                        <span style="margin-left: 1rem; color: #6b7280;">Loading activities...</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions Card -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-bolt"></i>Quick Actions</h3>
            </div>
            <div class="card-body">
                <div class="quick-actions-grid">
                    <a href="students.php" class="quick-action-btn">
                        <div class="action-icon">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <span>Add Student</span>
                    </a>
                    <a href="scores.php" class="quick-action-btn">
                        <div class="action-icon">
                            <i class="fas fa-edit"></i>
                        </div>
                        <span>Enter Scores</span>
                    </a>
                    <a href="reports.php" class="quick-action-btn">
                        <div class="action-icon">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <span>Generate Reports</span>
                    </a>
                    <a href="classes.php" class="quick-action-btn">
                        <div class="action-icon">
                            <i class="fas fa-users-class"></i>
                        </div>
                        <span>Manage Classes</span>
                    </a>
                    <a href="attendance.php" class="quick-action-btn">
                        <div class="action-icon">
                            <i class="fas fa-clipboard-check"></i>
                        </div>
                        <span>Take Attendance</span>
                    </a>
                    <a href="analytics.php" class="quick-action-btn">
                        <div class="action-icon">
                            <i class="fas fa-chart-pie"></i>
                        </div>
                        <span>View Analytics</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modals -->
<div class="modal-overlay" id="smsTopupModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3>Top Up SMS</h3>
            <button class="modal-close" onclick="closeSmsTopupModal()">×</button>
        </div>
        <div class="modal-body">
            <div class="modal-alert" id="smsModalAlert"></div>
            <div style="background: #f0f9ff; padding: 12px; border-radius: 8px; margin-bottom: 12px;">
                <p style="margin: 0 0 8px 0; font-size: 0.85rem;"><strong>Pricing Breakdown:</strong></p>
                <p style="margin: 0 0 4px 0; font-size: 0.8rem; color: #4b5563;">💰 Gateway Cost: KES 0.70 per SMS</p>
                <p style="margin: 0 0 8px 0; font-size: 0.8rem; color: #4b5563;">🏦 Platform Fee: KES 0.30 per SMS</p>
                <p style="margin: 0; font-size: 0.85rem; font-weight: 600; color: #0369a1;">💵 <strong>Total: KES 1.00 per SMS</strong></p>
            </div>
            <label>Phone Number (M-Pesa)</label>
            <input type="tel" placeholder="07XXXXXXXX or 2547XXXXXXXX" id="mpesaPhone">
            <label>Amount (KES)</label>
            <input type="number" min="10" placeholder="e.g. 500" id="topupAmount">
            <div id="creditsPreview" style="font-size: 0.8rem; color: #6b7280; margin-top: -0.5rem; display: none;">
                You will get <strong id="creditsCount">0</strong> SMS credits
            </div>
        </div>
        <div class="modal-footer">
            <button class="modal-cancel" onclick="closeSmsTopupModal()">Cancel</button>
            <button class="modal-confirm" onclick="submitSmsTopup()">Proceed to Pay</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="addEventModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3>Add Event</h3>
            <button class="modal-close" onclick="closeAddEventModal()">×</button>
        </div>
        <div class="modal-body">
            <label>Event Title</label>
            <input type="text" id="eventTitle">
            <label>Date</label>
            <input type="date" id="eventDate">
            <label>Time</label>
            <input type="time" id="eventTime">
        </div>
        <div class="modal-footer">
            <button class="modal-cancel" onclick="closeAddEventModal()">Cancel</button>
            <button class="modal-confirm">Save Event</button>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// UPDATED: New Pricing Constants
const SMS_COST = 1.00;

// Global variables
let classPerformanceChart = null;
let historicalTrendChart = null;

// =============================
// Performance Chart Functions
// =============================

// Initialize class performance bar chart
function initClassPerformanceChart() {
    const ctx = document.getElementById('performanceChart').getContext('2d');
    
    if (classPerformanceChart) {
        classPerformanceChart.destroy();
    }
    
    classPerformanceChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: [],
            datasets: [{
                label: 'Class Average (%)',
                data: [],
                backgroundColor: 'rgba(37, 99, 235, 0.7)',
                borderColor: '#1e3a8a',
                borderWidth: 2,
                borderRadius: 8,
                barPercentage: 0.7,
                categoryPercentage: 0.8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        font: { size: 12, weight: '600' },
                        color: '#374151'
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0,0,0,0.8)',
                    titleFont: { size: 14, weight: '600' },
                    bodyFont: { size: 13 },
                    padding: 12,
                    cornerRadius: 8,
                    callbacks: {
                        label: function(context) {
                            const value = context.raw;
                            return [`Average: ${value.toFixed(2)}%`];
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    grid: { color: 'rgba(0,0,0,0.05)' },
                    ticks: { 
                        color: '#6b7280', 
                        font: { size: 11, weight: '600' },
                        callback: function(value) {
                            return value + '%';
                        }
                    },
                    title: {
                        display: true,
                        text: 'Average Score (%)',
                        color: '#374151',
                        font: { size: 12, weight: '600' }
                    }
                },
                x: {
                    grid: { display: false },
                    ticks: { 
                        color: '#6b7280', 
                        font: { size: 11, weight: '600' },
                        maxRotation: 45,
                        minRotation: 45
                    }
                }
            },
            interaction: { intersect: false, mode: 'index' },
            animations: {
                duration: 1000,
                easing: 'linear'
            }
        }
    });
}

// Update class performance chart with real data
function updateClassPerformanceChart(classPerformance) {
    if (!classPerformanceChart) {
        initClassPerformanceChart();
    }
    
    // Use class_name from JSON, fall back to class_id
    const labels = classPerformance.map(c => c.class_name || ('Class ' + c.class_id));
    
    // Parse average_percentage to float (handle null values)
    const scores = classPerformance.map(c => {
        const avg = c.average_percentage;
        return (avg !== null && avg !== undefined) ? parseFloat(avg) : 0;
    });
    
    classPerformanceChart.data.labels = labels;
    classPerformanceChart.data.datasets[0].data = scores;
    classPerformanceChart.update();
}

// =============================
// AJAX Dashboard Update Functions
// =============================

function updateDashboardByAcademicLevel(level, displayName) {
    showDashboardLoading();
    
    fetch('api/get_dashboard_data.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'academic_level=' + encodeURIComponent(level)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateDashboardUI(data);
            updateAcademicLevelInSession(level);
            showToast('Academic Level Updated', `Switched to ${displayName} view`, 'success');
        } else {
            showToast('Error', data.message || 'Failed to load dashboard data', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error', 'Network error while updating dashboard', 'error');
    })
    .finally(() => {
        hideDashboardLoading();
    });
}

function showDashboardLoading() {
    const kpiCards = document.querySelectorAll('.kpi-card');
    kpiCards.forEach(card => {
        card.style.opacity = '0.5';
        card.style.pointerEvents = 'none';
    });
    
    const chartContainer = document.querySelector('.chart-container');
    if (chartContainer && !chartContainer.querySelector('.loading-spinner')) {
        const spinner = document.createElement('div');
        spinner.className = 'loading-spinner';
        spinner.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
        spinner.style.cssText = `
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(255,255,255,0.9);
            padding: 10px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            z-index: 10;
        `;
        chartContainer.style.position = 'relative';
        chartContainer.appendChild(spinner);
    }
    
    const studentList = document.getElementById('topStudentsList');
    if (studentList) {
        studentList.style.opacity = '0.5';
    }
}

function hideDashboardLoading() {
    const kpiCards = document.querySelectorAll('.kpi-card');
    kpiCards.forEach(card => {
        card.style.opacity = '1';
        card.style.pointerEvents = 'auto';
    });
    
    const spinner = document.querySelector('.loading-spinner');
    if (spinner) spinner.remove();
    
    const studentList = document.getElementById('topStudentsList');
    if (studentList) {
        studentList.style.opacity = '1';
    }
}

function updateDashboardUI(data) {
    document.getElementById('studentCount').textContent = data.stats.total_students.toLocaleString();
    document.getElementById('maleStudentCount').textContent = data.stats.male_students.toLocaleString();
    document.getElementById('femaleStudentCount').textContent = data.stats.female_students.toLocaleString();
    
    document.getElementById('teacherCount').textContent = data.stats.total_teachers.toLocaleString();
    document.getElementById('maleTeacherCount').textContent = data.stats.male_teachers.toLocaleString();
    document.getElementById('femaleTeacherCount').textContent = data.stats.female_teachers.toLocaleString();
    
    document.getElementById('classCount').textContent = data.stats.total_classes.toLocaleString();
    
    updateTopStudentsListDashboard(data.top_students);
    
    if (data.activities && data.activities.length > 0) {
        updateActivitiesList(data.activities);
    }
}

// Dashboard top students (uses FirstName/LastName/AdmissionNo/avg_score)
function updateTopStudentsListDashboard(students) {
    const studentList = document.getElementById('topStudentsList');
    if (!studentList) return;
    
    if (students && students.length > 0) {
        studentList.innerHTML = students.map(student => `
            <div class="student-item">
                <div class="student-info">
                    <div class="student-avatar ${(student.Gender || '').toLowerCase()}">
                        ${(student.FirstName || '?').charAt(0).toUpperCase()}
                    </div>
                    <div class="student-details">
                        <h5>
                            ${escapeHtml((student.FirstName || '') + ' ' + (student.LastName || ''))}
                            <i class="fas fa-${(student.Gender || '').toLowerCase() === 'male' ? 'mars' : 'venus'} activity-gender ${(student.Gender || '').toLowerCase()}-icon"></i>
                        </h5>
                        <p>Adm: ${escapeHtml(student.AdmissionNo || 'N/A')}</p>
                    </div>
                </div>
                <span class="score">${(student.avg_score || 0)}%</span>
            </div>
        `).join('');
    } else {
        studentList.innerHTML = `
            <div class="empty-state" style="padding: 1rem;">
                <i class="fas fa-chart-bar"></i>
                <p>No performance data available for this academic level</p>
            </div>
        `;
    }
}

// =============================
// Performance Data Loading Functions
// =============================

function initPerformanceFilters() {
    fetch('api/get_performance_data.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=get_filters'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const yearSelect = document.getElementById('performanceYear');
            const termSelect = document.getElementById('performanceTerm');
            
            yearSelect.innerHTML = '<option value="">Select Year</option>';
            if (data.data.available_years && data.data.available_years.length > 0) {
                data.data.available_years.forEach(year => {
                    yearSelect.innerHTML += `<option value="${year}">${year}</option>`;
                });
            }
            
            termSelect.innerHTML = '<option value="">Select Term</option>';
            if (data.data.available_terms && data.data.available_terms.length > 0) {
                data.data.available_terms.forEach(term => {
                    const hasDataIcon = term.has_data ? ' ✓' : ' (No Data)';
                    const optionClass = term.has_data ? 'has-data' : 'no-data';
                    termSelect.innerHTML += `<option value="${term.id}" class="${optionClass}" data-has-data="${term.has_data}">${term.term_name} ${term.academic_year}${hasDataIcon}</option>`;
                });
            }
            
            if (data.data.current_year) {
                yearSelect.value = data.data.current_year;
            }
            if (data.data.current_term) {
                termSelect.value = data.data.current_term;
            }
            
            if (yearSelect.value && termSelect.value) {
                loadPerformanceData();
            }
        }
    })
    .catch(error => console.error('Error loading filters:', error));
}

function loadPerformanceData() {
    const year = document.getElementById('performanceYear').value;
    const term = document.getElementById('performanceTerm').value;
    const academicLevel = getCurrentAcademicLevel();
    
    if (!year || !term) {
        showToast('Selection Required', 'Please select both year and term', 'warning');
        return;
    }
    
    const topStudentsList = document.getElementById('topStudentsList');
    topStudentsList.innerHTML = '<div class="activity-item" style="justify-content: center; padding: 2rem;"><i class="fas fa-spinner fa-spin"></i> Loading performance data...</div>';
    
    fetch('api/get_performance_data.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `year=${year}&term=${term}&academic_level=${academicLevel}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updatePerformanceUI(data.data);
        } else {
            showToast('Error', data.message || 'Failed to load performance data', 'error');
            topStudentsList.innerHTML = '<div class="empty-state" style="padding: 1rem;"><i class="fas fa-exclamation-triangle"></i><p>No performance data available for the selected period</p></div>';
        }
    })
    .catch(error => {
        console.error('Error loading performance data:', error);
        showToast('Error', 'Network error while loading performance data', 'error');
        topStudentsList.innerHTML = '<div class="empty-state" style="padding: 1rem;"><i class="fas fa-exclamation-triangle"></i><p>Error loading data</p></div>';
    });
}

// =============================
// UPDATED: Performance UI Functions (Fixed JSON field names)
// =============================

function updatePerformanceUI(data) {
    // Update overall stats (handle null values)
    if (data.overall_stats && data.overall_stats.total_students > 0) {
        document.getElementById('overallStats').style.display = 'block';
        document.getElementById('totalStudents').textContent = data.overall_stats.total_students;
        
        const meanMarks = data.overall_stats.mean_marks ? parseFloat(data.overall_stats.mean_marks) : 0;
        document.getElementById('overallMeanMark').textContent = meanMarks.toFixed(2);
        
        const highestScore = data.overall_stats.highest_score ? parseFloat(data.overall_stats.highest_score) : 0;
        const lowestScore = data.overall_stats.lowest_score ? parseFloat(data.overall_stats.lowest_score) : 0;
        document.getElementById('highestScore').textContent = highestScore.toFixed(2) + '%';
        document.getElementById('lowestScore').textContent = lowestScore.toFixed(2) + '%';
    } else {
        document.getElementById('overallStats').style.display = 'none';
    }
    
    // Update class performance chart
    if (data.class_performance && data.class_performance.length > 0) {
        updateClassPerformanceChart(data.class_performance);
    }
    
    // Update top students (performance version - uses student_name/admission_no/mean_marks)
    updateTopStudentsListPerformance(data.top_students);
    
    // Update class performance summary
    if (data.class_performance && data.class_performance.length > 0) {
        updateClassPerformanceSummary(data.class_performance, data.class_grade_distribution);
        document.getElementById('classSummary').style.display = 'block';
    } else {
        document.getElementById('classSummary').style.display = 'none';
    }
}

// Performance top students (uses student_name/admission_no/mean_marks/overall_grade/rank_position)
function updateTopStudentsListPerformance(students) {
    const studentList = document.getElementById('topStudentsList');
    
    if (students && students.length > 0) {
        studentList.innerHTML = students.map((student, index) => {
            const displayName = student.student_name || 'Unknown Student';
            const admissionNo = student.admission_no || 'N/A';
            const meanMarks = student.mean_marks ? parseFloat(student.mean_marks) : 0;
            const overallGrade = student.overall_grade || 'N/A';
            const rank = student.rank_position || (index + 1);
            
            return `
            <div class="student-item">
                <div class="student-info">
                    <div class="student-avatar" style="background: linear-gradient(135deg, #fbbf24, #f59e0b);">
                        ${index === 0 ? '🏆' : (index + 1)}
                    </div>
                    <div class="student-details">
                        <h5>
                            ${escapeHtml(displayName)}
                            <span style="font-size: 0.8rem; color: #6b7280;">(${escapeHtml(admissionNo)})</span>
                        </h5>
                        <p>Rank: ${rank}${getOrdinalSuffix(rank)}</p>
                    </div>
                </div>
                <div class="score" style="text-align: right;">
                    <div style="font-size: 1.2rem; font-weight: 800;">${meanMarks.toFixed(2)}%</div>
                    <div style="font-size: 0.8rem; color: #6b7280;">Grade: ${escapeHtml(overallGrade)}</div>
                </div>
            </div>
            `;
        }).join('');
    } else {
        studentList.innerHTML = '<div class="empty-state" style="padding: 1rem;"><i class="fas fa-chart-bar"></i><p>No performance data available for the selected period</p></div>';
    }
}

// Class Performance Summary (Fixed to use correct JSON fields and handle nulls)
function updateClassPerformanceSummary(classPerformance, gradeDistribution) {
    const classSummaryList = document.getElementById('classSummaryList');
    
    classSummaryList.innerHTML = classPerformance.map(classItem => {
        // Parse values with null handling
        const avgPercentage = classItem.average_percentage !== null && classItem.average_percentage !== undefined 
            ? parseFloat(classItem.average_percentage) 
            : 0;
        const lowestScore = classItem.lowest_score !== null && classItem.lowest_score !== undefined 
            ? parseFloat(classItem.lowest_score) 
            : 0;
        const highestScore = classItem.highest_score !== null && classItem.highest_score !== undefined 
            ? parseFloat(classItem.highest_score) 
            : 0;
        const totalStudents = classItem.total_students || 0;
        const className = classItem.class_name || ('Class ' + classItem.class_id);
        
        // Get grade distribution for this class
        const grades = gradeDistribution && gradeDistribution[classItem.class_id] 
            ? gradeDistribution[classItem.class_id] 
            : [];
        
        const gradeColors = {
            'EE': '#10b981',
            'ME': '#3b82f6',
            'AE': '#f59e0b',
            'BE': '#ef4444'
        };
        
        // Create grade distribution bars
        const gradeBars = grades.length > 0 ? grades.map(grade => {
            return `
                <div style="flex: 1; text-align: center;">
                    <div style="background: ${gradeColors[grade.overall_grade] || '#6b7280'}; height: 30px; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: white; font-size: 12px; font-weight: 600;">
                        ${grade.count}
                    </div>
                    <div style="font-size: 11px; margin-top: 4px; color: #6b7280;">${grade.overall_grade}</div>
                </div>
            `;
        }).join('') : '<div style="text-align: center; color: #9ca3af; width: 100%;">No grade data available</div>';
        
        return `
            <div style="background: #ffffff; border-radius: 12px; padding: 16px; border: 1px solid #e5e7eb; margin-bottom: 12px;">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 12px;">
                    <div>
                        <strong style="font-size: 1.1rem; color: #1e3a8a;">${escapeHtml(className)}</strong>
                        <div style="font-size: 0.8rem; color: #6b7280; margin-top: 2px;">${totalStudents} students</div>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-size: 1.5rem; font-weight: 800; color: ${getGradeColor(avgPercentage)};">${avgPercentage.toFixed(2)}%</div>
                        <div style="font-size: 0.7rem; color: #6b7280;">Range: ${lowestScore.toFixed(2)}% - ${highestScore.toFixed(2)}%</div>
                    </div>
                </div>
                <div style="margin-top: 12px;">
                    <div style="font-size: 0.75rem; font-weight: 600; color: #6b7280; margin-bottom: 8px;">Grade Distribution</div>
                    <div style="display: flex; gap: 8px; margin-bottom: 8px;">
                        ${gradeBars}
                    </div>
                </div>
                <div class="grade-badge" style="margin-top: 12px; padding: 8px 12px; background: ${getGradeBackgroundColor(avgPercentage)}; border-radius: 8px; text-align: center;">
                    <span style="font-weight: 600; color: white;">${getGradeLetter(avgPercentage)} - ${getGradeDescription(avgPercentage)}</span>
                </div>
            </div>
        `;
    }).join('');
}

// =============================
// Grade Utility Functions
// =============================

function getGradeColor(percentage) {
    if (percentage >= 75) return '#10b981';
    if (percentage >= 50) return '#3b82f6';
    if (percentage >= 25) return '#f59e0b';
    return '#ef4444';
}

function getGradeBackgroundColor(percentage) {
    if (percentage >= 75) return 'linear-gradient(135deg, #10b981, #059669)';
    if (percentage >= 50) return 'linear-gradient(135deg, #3b82f6, #2563eb)';
    if (percentage >= 25) return 'linear-gradient(135deg, #f59e0b, #d97706)';
    return 'linear-gradient(135deg, #ef4444, #dc2626)';
}

function getGradeLetter(percentage) {
    if (percentage >= 75) return 'EE';
    if (percentage >= 50) return 'ME';
    if (percentage >= 25) return 'AE';
    return 'BE';
}

function getGradeDescription(percentage) {
    if (percentage >= 75) return 'Exceeding Expectations';
    if (percentage >= 50) return 'Meeting Expectations';
    if (percentage >= 25) return 'Approaching Expectations';
    return 'Below Expectations';
}

function getOrdinalSuffix(n) {
    const s = ["th", "st", "nd", "rd"];
    const v = n % 100;
    return s[(v - 20) % 10] || s[v] || s[0];
}

function getCurrentAcademicLevel() {
    const levelText = document.querySelector('.center-level-text')?.textContent;
    const levelMap = {
        'Primary School': 'primary',
        'Junior Secondary': 'junior_secondary',
        'Senior Secondary': 'senior_secondary',
        'College': 'college'
    };
    return levelMap[levelText] || 'primary';
}

function updateActivitiesList(activities) {
    const activityFeed = document.getElementById('activity-feed');
    if (!activityFeed) return;
    
    if (activities && activities.length > 0) {
        activityFeed.innerHTML = activities.map(act => `
            <div class="activity-item">
                <div class="activity-icon" style="background: #3b82f6">
                    <i class="fas fa-edit"></i>
                </div>
                <div class="activity-content">
                    <p>
                        <strong>${escapeHtml((act.FirstName || '') + ' ' + (act.LastName || ''))}</strong> 
                        scored ${act.score_value || 0}% in ${escapeHtml(act.subject_name || 'N/A')} 
                        (${act.exam_type || 'N/A'})
                    </p>
                    <small>
                        <i class="fas fa-clock"></i> 
                        ${act.recorded_at ? new Date(act.recorded_at).toLocaleDateString('en-US', {
                            year: 'numeric',
                            month: 'short',
                            day: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                        }) : 'N/A'}
                        <span class="exam-badge">${act.exam_type || 'N/A'}</span>
                    </small>
                </div>
            </div>
        `).join('');
    } else {
        activityFeed.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-bell-slash"></i>
                <h4>No Recent Activity</h4>
                <p>When activities occur, they'll appear here</p>
            </div>
        `;
    }
}

function updateAcademicLevelInSession(level) {
    fetch('api/update_academic_level_ajax.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'academic_level=' + encodeURIComponent(level)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Academic level updated in session');
        }
    })
    .catch(error => console.error('Error updating session:', error));
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// =============================
// UI Initialization Functions
// =============================

document.addEventListener('DOMContentLoaded', () => {
    initClassPerformanceChart();
    
    // Animate KPI cards
    const kpiCards = document.querySelectorAll('.kpi-card');
    kpiCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(30px) scale(0.95)';
        setTimeout(() => {
            card.style.transition = 'all 0.6s cubic-bezier(0.175,0.885,0.32,1.275)';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0) scale(1)';
        }, index * 150);
    });

    // Animate quick action buttons
    const quickActionBtns = document.querySelectorAll('.quick-action-btn');
    quickActionBtns.forEach(btn => {
        btn.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-8px) scale(1.05)';
        });
        btn.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });

    // Add floating animation to icons
    const floatingElements = document.querySelectorAll('.kpi-icon, .action-icon');
    floatingElements.forEach((el, index) => {
        el.classList.add('floating');
        el.style.animationDelay = `${index * 0.2}s`;
    });
    
    initAcademicLevelHandlers();
    initPerformanceFilters();
    
    const loadButton = document.getElementById('loadPerformanceData');
    if (loadButton) {
        loadButton.addEventListener('click', loadPerformanceData);
    }
    
    const originalUpdateDashboardByAcademicLevel = window.updateDashboardByAcademicLevel;
    if (originalUpdateDashboardByAcademicLevel) {
        window.updateDashboardByAcademicLevel = function(level, displayName) {
            originalUpdateDashboardByAcademicLevel(level, displayName);
            setTimeout(loadPerformanceData, 500);
        };
    }
});

// Initialize academic level click handlers
function initAcademicLevelHandlers() {
    document.querySelectorAll('.level-option').forEach(option => {
        option.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const level = this.getAttribute('data-level');
            const displayName = this.querySelector('span')?.textContent || level;
            
            if (level) {
                updateAcademicLevelUI(level);
                const centerDropdown = document.getElementById('centerAcademicLevelDropdown');
                if (centerDropdown) centerDropdown.classList.remove('show');
                updateDashboardByAcademicLevel(level, displayName);
            }
        });
    });
    
    document.querySelectorAll('.mobile-level-option').forEach(option => {
        option.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const level = this.getAttribute('data-level');
            const displayName = this.querySelector('span')?.textContent || level;
            
            if (level) {
                updateAcademicLevelUI(level);
                const mobileDropdown = document.getElementById('mobileAcademicDropdown');
                if (mobileDropdown) mobileDropdown.classList.remove('active');
                updateDashboardByAcademicLevel(level, displayName);
            }
        });
    });
}

function updateAcademicLevelUI(level) {
    const academicLevels = {
        'primary': 'Primary School',
        'junior_secondary': 'Junior Secondary', 
        'senior_secondary': 'Senior Secondary',
        'college': 'College'
    };
    
    const displayName = academicLevels[level] || 'Primary School';
    
    document.querySelectorAll('.center-level-text').forEach(el => {
        if (el) el.textContent = displayName;
    });
    
    document.querySelectorAll('.current-level, .mobile-current-level').forEach(el => {
        if (el) el.textContent = `Current: ${displayName}`;
    });
    
    document.querySelectorAll('.level-option, .mobile-level-option').forEach(opt => {
        opt.classList.remove('active');
        if (opt.getAttribute('data-level') === level) {
            opt.classList.add('active');
        }
    });
}

// Weather and Greeting Functions
async function getWeather() {
    try {
        const apiKey = "91ebe0cbb43d9a4c35e20d5abc4504fe";
        const url = `https://api.openweathermap.org/data/2.5/weather?q=Nairobi&appid=${apiKey}&units=metric`;
        const res = await fetch(url);
        const data = await res.json();
        return {
            temp: Math.round(data.main.temp),
            condition: data.weather[0].main,
            emoji: getWeatherEmoji(data.weather[0].main)
        };
    } catch (err) {
        console.error("Weather error:", err);
        return { temp: "--", condition: "Unknown", emoji: "🌍" };
    }
}

function getWeatherEmoji(condition) {
    const map = {
        Clear: "☀️",
        Clouds: "⛅",
        Rain: "🌧",
        Thunderstorm: "⛈",
        Drizzle: "🌦",
        Mist: "🌫",
        Fog: "🌫",
        Haze: "🌁"
    };
    return map[condition] || "🌍";
}

function setGreeting() {
    const h = new Date().getHours();
    let g = "Hello";
    let i = "☀️";
    if (h >= 5 && h < 12) {
        g = "Good Morning";
        i = "🌅";
    } else if (h >= 12 && h < 17) {
        g = "Good Afternoon";
        i = "🌤";
    } else if (h >= 17 && h < 20) {
        g = "Good Evening";
        i = "🌇";
    } else {
        g = "Good Night";
        i = "🌙";
    }
    document.getElementById("greetingText").innerText = g;
    document.getElementById("timeIcon").innerText = i;
}

const quoteLibrary = {
    sun: [
        "Let today shine brighter than yesterday.",
        "A sunny mind brings sunny results.",
        "Walk in the light — your journey is blessed."
    ],
    rain: [
        "Growth comes from rainy days.",
        "Even storms prepare you for strength.",
        "Rain today, bloom tomorrow."
    ],
    cold: [
        "Stay warm, stay focused — growth continues.",
        "Even in the cold, progress never stops."
    ],
    morning: [
        "New day, new energy — rise and shine.",
        "Start strong, finish stronger."
    ],
    evening: [
        "Pause, reflect, and recharge.",
        "Every sunset brings hope for a new dawn."
    ],
    bible: [
        "“I can do all things through Christ.” — Phil 4:13",
        "“The Lord is my shepherd.” — Psalm 23:1",
        "“Commit to the Lord whatever you do.” — Prov 16:3"
    ],
    academic: [
        "Learning today, leading tomorrow.",
        "Education builds nations.",
        "Knowledge is your most powerful asset."
    ]
};

function chooseQuote(weather) {
    const h = new Date().getHours();
    if (weather.condition === "Clear") return random(quoteLibrary.sun);
    if (weather.condition === "Rain") return random(quoteLibrary.rain);
    if (weather.condition === "Clouds") return random(quoteLibrary.academic);
    if (h < 12) return random(quoteLibrary.morning);
    if (h >= 17) return random(quoteLibrary.evening);
    return random(quoteLibrary.academic);
}

function random(arr) {
    return arr[Math.floor(Math.random() * arr.length)];
}

function rotateQuote(text, isApiQuote = true) {
    const e = document.getElementById("quoteText");
    if (!e) return;
    
    e.classList.remove("quote-slide-in");
    e.classList.add("quote-slide-out");
    setTimeout(() => {
        e.innerText = text;
        e.classList.remove("quote-slide-out");
        e.classList.add("quote-slide-in");
    }, 400);
}
async function fetchQuoteFromAPI() {
    const apis = [
        // API 1: DummyJSON Quotes (CORS-friendly, always works)
        {
            url: 'https://dummyjson.com/quotes/random',
            parser: (data) => `"${data.quote}" — ${data.author}`,
            headers: {}
        },
        // API 2: TypeFit Quotes (CORS-friendly)
        {
            url: 'https://type.fit/api/quotes',
            parser: (data) => {
                const random = data[Math.floor(Math.random() * data.length)];
                return `"${random.text}" — ${random.author || 'Unknown'}`;
            },
            headers: {}
        },
        // API 3: FreeAPI Quotes
        {
            url: 'https://api.freeapi.app/api/v1/public/quotes/quote/random',
            parser: (data) => {
                if (data?.success && data?.data) {
                    return `"${data.data.content}" — ${data.data.author}`;
                }
                throw new Error('Invalid response format');
            },
            headers: {}
        }
    ];

    // Try TypeFit first (it returns all quotes in one call, more efficient)
    try {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 5000);
        
        const response = await fetch('https://type.fit/api/quotes', { 
            signal: controller.signal
        });
        clearTimeout(timeoutId);
        
        if (response.ok) {
            const data = await response.json();
            const random = data[Math.floor(Math.random() * data.length)];
            const quote = `"${random.text}" — ${random.author || 'Unknown'}`;
            
            if (quote && quote.length > 10) {
                document.getElementById("quoteText").innerText = quote;
                localStorage.setItem('dailyQuote', quote);
                localStorage.setItem('quoteDate', new Date().toDateString());
                return true;
            }
        }
    } catch (err) {
        console.warn('TypeFit API failed:', err.message);
    }

    // Try other APIs
    for (const api of apis) {
        if (api.url === 'https://type.fit/api/quotes') continue; // Already tried
        
        try {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 5000);
            
            const response = await fetch(api.url, { 
                signal: controller.signal,
                headers: api.headers
            });
            clearTimeout(timeoutId);
            
            if (!response.ok) continue;
            
            const data = await response.json();
            const quote = api.parser(data);
            
            if (quote && quote.length > 10) {
                document.getElementById("quoteText").innerText = quote;
                localStorage.setItem('dailyQuote', quote);
                localStorage.setItem('quoteDate', new Date().toDateString());
                return true;
            }
        } catch (err) {
            console.warn(`API failed: ${api.url}`, err.message);
            continue;
        }
    }
    
    return false;
}
function getLocalQuote(weatherCondition = 'Clear', hour = new Date().getHours()) {
    const quoteLibrary = {
        morning: [
            "New day, new energy — rise and shine! 🌅",
            "Start strong, finish stronger.",
            "Today is a fresh opportunity to excel.",
            "The morning sun brings new possibilities.",
            "Every sunrise is an invitation to rise."
        ],
        afternoon: [
            "Stay focused, stay determined. 💪",
            "The best preparation for tomorrow is doing your best today.",
            "Success is the sum of small efforts repeated day in and day out.",
            "Keep pushing forward — you're making progress."
        ],
        evening: [
            "Pause, reflect, and recharge. 🌇",
            "Every sunset brings hope for a new dawn.",
            "Well done today — prepare for tomorrow.",
            "Rest well, for tomorrow holds new opportunities."
        ],
        academic: [
            "Learning today, leading tomorrow. 📚",
            "Education builds nations.",
            "Knowledge is your most powerful asset.",
            "Excellence is not a skill, it's an attitude.",
            "The roots of education are bitter, but the fruit is sweet. — Aristotle"
        ],
        bible: [
            "I can do all things through Christ who strengthens me. — Phil 4:13",
            "The Lord is my shepherd; I shall not want. — Psalm 23:1",
            "Commit to the Lord whatever you do, and He will establish your plans. — Prov 16:3",
            "For I know the plans I have for you, declares the Lord. — Jer 29:11"
        ],
        general: [
            "Success is not final, failure is not fatal: it is the courage to continue that counts. — Winston Churchill",
            "The only way to do great work is to love what you do. — Steve Jobs",
            "Education is the most powerful weapon which you can use to change the world. — Nelson Mandela",
            "The future belongs to those who believe in the beauty of their dreams. — Eleanor Roosevelt",
            "It always seems impossible until it's done. — Nelson Mandela"
        ]
    };

    if (hour >= 5 && hour < 12) {
        return randomFromArray(quoteLibrary.morning);
    } else if (hour >= 12 && hour < 17) {
        return randomFromArray(quoteLibrary.afternoon);
    } else if (hour >= 17 && hour < 20) {
        return randomFromArray(quoteLibrary.evening);
    } else if (Math.random() < 0.3) {
        return randomFromArray(quoteLibrary.bible);
    } else if (Math.random() < 0.5) {
        return randomFromArray(quoteLibrary.academic);
    } else {
        return randomFromArray(quoteLibrary.general);
    }
}

async function loadDailyQuote() {
    const cachedQuote = localStorage.getItem('dailyQuote');
    const cachedDate = localStorage.getItem('quoteDate');
    const today = new Date().toDateString();
    
    // Show cached quote immediately if available
    if (cachedQuote && cachedDate === today) {
        document.getElementById("quoteText").innerText = cachedQuote;
    }
    
    // Try to fetch fresh quote from API
    const apiSuccess = await fetchQuoteFromAPI();
    
    // If API fails and no cached quote, use local quotes
    if (!apiSuccess && (!cachedQuote || cachedDate !== today)) {
        const weatherCondition = 'Clear';
        const localQuote = getLocalQuote(weatherCondition, new Date().getHours());
        document.getElementById("quoteText").innerText = localQuote;
        localStorage.setItem('dailyQuote', localQuote);
        localStorage.setItem('quoteDate', today);
    }
}


    // FETCH KENYA HOLIDAYS
// ============================================
 async function fetchKenyanHoliday() {
    try {
        const year = new Date().getFullYear();
        const today = new Date().toISOString().split('T')[0];
        
        // Check cache first
        const cachedHolidays = localStorage.getItem('kenyanHolidays');
        const cachedYear = localStorage.getItem('holidaysYear');
        
        let holidays = [];
        
        if (cachedHolidays && cachedYear == year) {
            holidays = JSON.parse(cachedHolidays);
        } else {
            try {
                // Nager.Date API
                const response = await fetch(`https://date.nager.at/api/v3/PublicHolidays/${year}/KE`);
                
                if (response.ok) {
                    holidays = await response.json();
                    localStorage.setItem('kenyanHolidays', JSON.stringify(holidays));
                    localStorage.setItem('holidaysYear', year);
                }
            } catch (err) {
                console.warn('Nager API failed, using local holiday data:', err.message);
                // Use locally defined Kenyan holidays as fallback
                holidays = getLocalKenyanHolidays(year);
                localStorage.setItem('kenyanHolidays', JSON.stringify(holidays));
                localStorage.setItem('holidaysYear', year);
            }
        }
        
        const todayHoliday = holidays.find(h => h.date === today);
        const holidayEl = document.getElementById("holidayText");
        
        if (todayHoliday) {
            holidayEl.style.display = "inline-block";
            
            let emoji = "🎉";
            const name = (todayHoliday.localName || todayHoliday.name || '').toLowerCase();
            
            if (name.includes('christmas')) emoji = "🎄";
            else if (name.includes('easter')) emoji = "✝️";
            else if (name.includes('independence') || name.includes('jamhuri')) emoji = "🇰🇪";
            else if (name.includes('labour') || name.includes('worker')) emoji = "👷";
            else if (name.includes('new year')) emoji = "🎊";
            else if (name.includes('madaraka')) emoji = "🦁";
            else if (name.includes('mashujaa')) emoji = "🦸";
            else if (name.includes('idd') || name.includes('eid')) emoji = "🕌";
            else if (name.includes('good friday')) emoji = "✝️";
            
            holidayEl.innerHTML = `${emoji} Today is <strong>${todayHoliday.localName || todayHoliday.name}</strong> ${emoji}`;
            
            const welcomeSubtext = document.querySelector('.welcome-subtext');
            if (welcomeSubtext) {
                welcomeSubtext.innerHTML = `🎯 <strong>${todayHoliday.localName || todayHoliday.name}</strong> — Here's what's happening at <?php echo htmlspecialchars($_SESSION['school_name'] ?? 'Your School'); ?> today`;
            }
        } else {
            holidayEl.style.display = "none";
        }
        
    } catch (error) {
        console.error("Holiday fetch failed:", error.message);
    }
}

// ============================================
// LOCAL KENYAN HOLIDAYS (Fallback)
// ============================================
function getLocalKenyanHolidays(year) {
    return [
        { date: `${year}-01-01`, localName: "New Year's Day", name: "New Year's Day" },
        { date: `${year}-05-01`, localName: "Labour Day", name: "Labour Day" },
        { date: `${year}-06-01`, localName: "Madaraka Day", name: "Madaraka Day" },
        { date: `${year}-10-10`, localName: "Huduma Day", name: "Huduma Day" },
        { date: `${year}-10-20`, localName: "Mashujaa Day", name: "Mashujaa Day" },
        { date: `${year}-12-12`, localName: "Jamhuri Day", name: "Jamhuri Day" },
        { date: `${year}-12-25`, localName: "Christmas Day", name: "Christmas Day" },
        { date: `${year}-12-26`, localName: "Boxing Day", name: "Boxing Day" }
    ];
}
function randomFromArray(arr) {
    return arr[Math.floor(Math.random() * arr.length)];
}

function setTimeBackground() {
    const h = new Date().getHours();
    const header = document.querySelector(".dashboard-header");
    header.classList.add("fade-bg");
    header.classList.remove("morning-bg","afternoon-bg","evening-bg","night-bg");
    
    if (h >= 5 && h < 12) {
        header.classList.add("morning-bg");
        header.style.borderColor = 'rgba(249,115,22,0.2)';
    } else if (h >= 12 && h < 17) {
        header.classList.add("afternoon-bg");
        header.style.borderColor = 'rgba(59,130,246,0.2)';
    } else if (h >= 17 && h < 20) {
        header.classList.add("evening-bg");
        header.style.borderColor = 'rgba(139,92,246,0.2)';
    } else {
        header.classList.add("night-bg");
        header.style.borderColor = 'rgba(30,64,175,0.2)';
    }
}

async function initDashboardHeader() {
    setGreeting();
    setTimeBackground();
    
    // Load quote and holiday in parallel
    loadDailyQuote(); // Don't await - let it load in background
    fetchKenyanHoliday(); // Don't await
    
    // Refresh quote every 60 minutes
    setInterval(loadDailyQuote, 3600000);
    
    // Refresh holiday check every 6 hours
    setInterval(fetchKenyanHoliday, 21600000);
}

// Initialize
initDashboardHeader();

// Event Modal Functions
function openAddEventModal() {
    document.getElementById('addEventModal').style.display = 'flex';
}

function closeAddEventModal() {
    document.getElementById('addEventModal').style.display = 'none';
}

document.querySelector('.add-event-btn')?.addEventListener('click', openAddEventModal);

// Toast Notification Functions
function showToast(title, message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast-notification ${type}`;
    
    let icon = '';
    switch(type) {
        case 'success':
            icon = '✅';
            break;
        case 'error':
            icon = '❌';
            break;
        default:
            icon = 'ℹ️';
    }
    
    toast.innerHTML = `
        <div class="toast-icon">${icon}</div>
        <div class="toast-content">
            <div class="toast-title">${title}</div>
            <div class="toast-message">${message}</div>
        </div>
        <button class="toast-close" onclick="this.parentElement.remove()">×</button>
    `;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        if (toast && toast.parentElement) {
            toast.remove();
        }
    }, 5000);
}

// SMS Topup Functions
function openSmsTopupModal() {
    document.getElementById('smsTopupModal').style.display = 'flex';
    clearSmsErrors();
    
    const amountInput = document.getElementById('topupAmount');
    const creditsPreview = document.getElementById('creditsPreview');
    const creditsCount = document.getElementById('creditsCount');
    
    const updateCreditsPreview = () => {
        const amount = parseFloat(amountInput.value);
        if (amount && amount >= 10) {
            const credits = Math.floor(amount / SMS_COST);
            creditsCount.textContent = credits;
            creditsPreview.style.display = 'block';
        } else {
            creditsPreview.style.display = 'none';
        }
    };
    
    amountInput.removeEventListener('input', updateCreditsPreview);
    amountInput.addEventListener('input', updateCreditsPreview);
}

function closeSmsTopupModal() {
    document.getElementById('smsTopupModal').style.display = 'none';
    clearSmsErrors();
    document.getElementById('creditsPreview').style.display = 'none';
    document.getElementById('topupAmount').value = '';
    document.getElementById('mpesaPhone').value = '';
}

document.getElementById('mpesaPhone')?.addEventListener('blur', function() {
    let p = this.value.replace(/\s+/g, '');
    if (p.startsWith('07')) p = '254' + p.substring(1);
    if (p.startsWith('+254')) p = p.replace('+', '');
    this.value = p;
});

function submitSmsTopup() {
    clearSmsErrors();
    const p = document.getElementById('mpesaPhone').value.trim();
    const a = document.getElementById('topupAmount').value.trim();
    let hasError = false;
    
    if (!p || !/^2547\d{8}$/.test(p)) {
        document.getElementById('mpesaPhone').classList.add('input-error');
        showSmsAlert('Enter a valid M-Pesa number (2547XXXXXXXX)');
        hasError = true;
    }
    
    if (!a || a < 10) {
        document.getElementById('topupAmount').classList.add('input-error');
        showSmsAlert('Enter a valid amount (minimum KES 10)');
        hasError = true;
    }
    
    if (hasError) return;
    
    const amount = parseFloat(a);
    const credits = Math.floor(amount / SMS_COST);
    
    showToast('Processing Payment', `Initiating M-Pesa payment of KES ${amount.toFixed(2)} for ${credits} SMS credits...`, 'info');
    
    closeSmsTopupModal();
    
    setTimeout(() => {
        const currentBalance = parseInt(document.getElementById('smsBalance').innerText.replace(/,/g, '')) || 0;
        const newBalance = currentBalance + credits;
        document.getElementById('smsBalance').innerText = newBalance.toLocaleString();
        
        showToast('Payment Successful!', `You have successfully added ${credits} SMS credits to your account.`, 'success');
    }, 2000);
}

function showSmsAlert(m) {
    const a = document.getElementById('smsModalAlert');
    a.innerText = m;
    a.style.display = 'block';
}

function clearSmsErrors() {
    document.getElementById('smsModalAlert').style.display = 'none';
    document.getElementById('mpesaPhone').classList.remove('input-error');
    document.getElementById('topupAmount').classList.remove('input-error');
}

// Calendar Functions
const eventsData = <?php echo json_encode($events ?? []); ?>;

(function generateCalendar() {
    const now = new Date();
    const monthNames = [
        "January","February","March","April","May","June",
        "July","August","September","October","November","December"
    ];

    const month = now.getMonth();
    const year = now.getFullYear();

    document.getElementById("calendarMonth").innerText = monthNames[month];
    document.getElementById("calendarYear").innerText = year;

    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();

    const calendarGrid = document.getElementById("calendarGrid");
    calendarGrid.innerHTML = "";

    const eventDates = eventsData.map(e => new Date(e.event_date).getDate());

    for (let i = 0; i < firstDay; i++) {
        calendarGrid.appendChild(document.createElement("div"));
    }

    for (let day = 1; day <= daysInMonth; day++) {
        const el = document.createElement("div");
        el.className = "calendar-day";
        el.innerText = day;

        if (day === now.getDate()) el.classList.add("today");
        if (eventDates.includes(day)) el.classList.add("has-event");

        el.onclick = () => filterEventsByDate(day, month, year);
        calendarGrid.appendChild(el);
    }
})();

function filterEventsByDate(day, month, year) {
    const selectedDate = `${year}-${String(month + 1).padStart(2,'0')}-${String(day).padStart(2,'0')}`;
    const list = document.querySelector('.events-list');
    if (!list) return;
    list.innerHTML = '';

    const filtered = eventsData.filter(e => e.event_date === selectedDate);

    if (filtered.length === 0) {
        list.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-calendar-times"></i>
                <h4>No Events</h4>
                <p>No events on ${selectedDate}</p>
            </div>
        `;
        return;
    }

    filtered.forEach(event => {
        list.innerHTML += `
            <div class="event-item">
                <div class="event-date">
                    <span class="day">${day}</span>
                    <span class="month">${new Date(event.event_date)
                        .toLocaleString('en-US',{month:'short'})}</span>
                </div>
                <div class="event-details">
                    <h4>${event.title}</h4>
                    <p>${event.description ?? 'No description available'}</p>
                    <div class="event-time">
                        <i class="fas fa-clock"></i>
                        ${event.event_time}
                    </div>
                </div>
            </div>
        `;
    });
}

// Activity Feed Functions
function fetchActivity() {
    console.log('Fetching activity data...');
    
    fetch('ajax/activity.php')
        .then(res => {
            if (!res.ok) {
                throw new Error('Network response was not ok: ' + res.status);
            }
            return res.json();
        })
        .then(data => {
            const feed = document.getElementById('activity-feed');
            if (!feed) return;
            
            feed.innerHTML = '';

            if (data.success && data.activities && data.activities.length > 0) {
                data.activities.forEach(act => {
                    const item = document.createElement('div');
                    item.className = 'activity-item';
                    
                    let formattedDate = 'N/A';
                    if (act.created_at && act.created_at !== 'N/A') {
                        const date = new Date(act.created_at);
                        formattedDate = date.toLocaleDateString('en-US', {
                            year: 'numeric',
                            month: 'short',
                            day: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                        });
                    }
                    
                    item.innerHTML = `
                        <div class="activity-icon" style="background: ${getBadgeColor(act.badge)}">
                            <i class="${act.icon}"></i>
                        </div>
                        <div class="activity-content">
                            <p><strong>${act.school_name}</strong> - ${act.activity}</p>
                            ${act.details ? `<p style="font-size: 0.9rem; color: #6b7280; margin: 0.25rem 0;">${act.details}</p>` : ''}
                            <small>
                                <i class="fas fa-clock"></i> ${formattedDate}
                                <span class="exam-badge" style="background: ${getBadgeColor(act.badge)}">${act.badge}</span>
                            </small>
                        </div>
                    `;
                    feed.appendChild(item);
                });
            } else {
                feed.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-bell-slash"></i>
                        <h4>No Recent Activity</h4>
                        <p>When activities occur, they'll appear here</p>
                    </div>
                `;
            }
        })
        .catch(err => {
            console.error('Fetch error:', err);
            const feed = document.getElementById('activity-feed');
            if (feed) {
                feed.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-exclamation-triangle"></i>
                        <h4>Error Loading Activities</h4>
                        <p>Check console for details</p>
                    </div>
                `;
            }
        });
}

function getBadgeColor(badge) {
    const colors = {
        'Account': '#3b82f6',
        'Class': '#10b981',
        'Student': '#8b5cf6',
        'Report': '#f59e0b',
        'Exam': '#ef4444',
        'Staff': '#06b6d4',
        'Default': '#6b7280'
    };
    return colors[badge] || colors['Default'];
}

function refreshActivity() {
    const feed = document.getElementById('activity-feed');
    if (feed) {
        feed.innerHTML = `
            <div class="activity-item" style="justify-content: center; padding: 2rem;">
                <i class="fas fa-spinner fa-spin" style="color: #3b82f6; font-size: 1.5rem;"></i>
                <span style="margin-left: 1rem; color: #6b7280;">Refreshing activities...</span>
            </div>
        `;
        setTimeout(fetchActivity, 500);
    }
}

// Add CSS for activity items
const styleSheet = document.createElement('style');
styleSheet.textContent = `
    .activity-item {
        display: flex;
        gap: 1rem;
        padding: 1rem;
        background: #f9fafb;
        border-radius: 12px;
        transition: all 0.2s ease;
        margin-bottom: 0.75rem;
        flex-wrap: wrap;
    }
    .activity-item:hover {
        background: #e0f2fe;
        transform: translateX(5px);
    }
    .activity-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 1.2rem;
    }
    .activity-content {
        flex: 1;
        min-width: 0;
    }
    .activity-content p {
        margin: 0 0 0.5rem 0;
        line-height: 1.4;
        word-break: break-word;
    }
    .activity-content small {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: #6b7280;
        font-size: 0.85rem;
        flex-wrap: wrap;
    }
    .exam-badge {
        background: #3b82f6;
        color: white;
        padding: 0.15rem 0.5rem;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 600;
    }
`;
document.head.appendChild(styleSheet);

// Fetch activity on page load
document.addEventListener('DOMContentLoaded', fetchActivity);

// Refresh every 60 seconds
setInterval(fetchActivity, 60000);
</script>
</body>
</html>
