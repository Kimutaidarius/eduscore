<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['school_id'])) {
    header('Location: login.php');
    exit();
}

// Session variables
$teacher_id = $_SESSION['teacher_id'];
$school_id = $_SESSION['school_id'];
$academic_level = $_SESSION['academic_level'] ?? 'primary';

// Database connection
require_once 'includes/config.php';
require_once 'includes/session_timeout.php'; 

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize variables
$classes = [];
$streams = [];
$terms = [];
$students = [];
$attendance_statuses = ['Present', 'Absent', 'Late', 'Excused', 'Sick'];

// Fetch school details for trial banner
$schoolQuery = $conn->prepare("SELECT * FROM tblschoolinfo WHERE id = ?");
$schoolQuery->bind_param("i", $school_id);
$schoolQuery->execute();
$schoolResult = $schoolQuery->get_result();
$school = $schoolResult->fetch_assoc();
$schoolQuery->close();

// Fetch classes
$classesQuery = $conn->prepare("
    SELECT c.id, c.class_level as display_name, c.academic_level
    FROM tblclasses c
    WHERE c.school_id = ?
    ORDER BY c.class_level
");
$classesQuery->bind_param("i", $school_id);
$classesQuery->execute();
$classesResult = $classesQuery->get_result();
while ($class = $classesResult->fetch_assoc()) {
    $classes[] = $class;
}
$classesQuery->close();

// Fetch streams
$streamsQuery = $conn->prepare("
    SELECT DISTINCT stream as stream_name 
    FROM tblclasses 
    WHERE school_id = ? AND stream IS NOT NULL AND stream != ''
    ORDER BY stream
");
$streamsQuery->bind_param("i", $school_id);
$streamsQuery->execute();
$streamsResult = $streamsQuery->get_result();
while ($stream = $streamsResult->fetch_assoc()) {
    $streams[] = $stream;
}
$streamsQuery->close();

// Fetch terms
$termsQuery = $conn->prepare("
    SELECT * FROM tblterms 
    WHERE school_id = ? 
    ORDER BY academic_year DESC, term_number
");
$termsQuery->bind_param("i", $school_id);
$termsQuery->execute();
$termsResult = $termsQuery->get_result();
while ($term = $termsResult->fetch_assoc()) {
    $terms[] = $term;
}
$termsQuery->close();

// Get active term
$activeTerm = null;
foreach ($terms as $term) {
    if (!empty($term['is_current']) && $term['is_current'] == 1) {
        $activeTerm = $term;
        break;
    }
}

// If no active term, use the first term
if (!$activeTerm && !empty($terms)) {
    $activeTerm = $terms[0];
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance - EduScore</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="images/logo.png" />
    <link rel="apple-touch-icon" href="images/logo.png">
    <link rel="stylesheet" href="assets/banner/banner.css">
    <style>
        :root {
            --primary-blue: #1e3a8a;
            --secondary-blue: #2563eb;
            --light-blue: #dbeafe;
            --accent-green: #10b981;
            --success-green: #10b981;
            --warning-orange: #f59e0b;
            --error-red: #ef4444;
            --text-dark: #1f2937;
            --text-light: #6b7280;
            --bg-light: #f9fafb;
            --bg-white: #ffffff;
            --border-color: #e5e7eb;
            --shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1);
            --border-radius: 12px;
            --transition: all 0.3s ease;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background: var(--bg-light); color: var(--text-dark); min-height: 100vh; }
        .main-content { margin-left: 280px; min-height: 100vh; padding: 100px 2rem 2rem; transition: margin-left 0.3s ease; }
        @media (max-width: 992px) { .main-content { margin-left: 0; padding: 100px 1rem 1rem; } }
        
        .academic-level-indicator { background: var(--bg-white); border-radius: var(--border-radius); padding: 1rem 1.5rem; margin-bottom: 1.5rem; box-shadow: var(--shadow); display: flex; align-items: center; gap: 1rem; border-left: 4px solid var(--accent-green); }
        .academic-level-icon { width: 50px; height: 50px; background: #d1fae5; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: var(--accent-green); font-size: 1.25rem; }
        .academic-level-content h3 { font-size: 1.1rem; font-weight: 600; color: var(--text-dark); margin-bottom: 0.25rem; }
        .academic-level-badge { background: #d1fae5; color: var(--accent-green); padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }
        
        .page-header { background: var(--bg-white); border-radius: var(--border-radius); padding: 2rem; margin-bottom: 2rem; box-shadow: var(--shadow); border-left: 4px solid var(--accent-green); }
        .page-title { font-size: 1.8rem; font-weight: 700; display: flex; align-items: center; gap: 0.75rem; }
        .page-title i { color: var(--accent-green); }
        
        .filter-section { background: var(--bg-white); border-radius: var(--border-radius); padding: 1.5rem; margin-bottom: 2rem; box-shadow: var(--shadow); }
        .filter-title { font-size: 1.2rem; font-weight: 600; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.75rem; color: var(--text-dark); }
        .filter-title i { color: var(--accent-green); }
        .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px,1fr)); gap: 1.5rem; margin-bottom: 1rem; }
        .filter-group { display: flex; flex-direction: column; gap: 0.5rem; }
        .filter-label { font-size: 0.9rem; font-weight: 500; color: var(--text-dark); display: flex; align-items: center; gap: 0.5rem; }
        .filter-select, .filter-input { width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--border-color); border-radius: var(--border-radius); font-size: 0.9rem; background: var(--bg-white); }
        .filter-select:focus, .filter-input:focus { outline: none; border-color: var(--primary-blue); box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
        
        .action-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; gap: 1rem; flex-wrap: wrap; background: var(--bg-white); padding: 1rem 1.5rem; border-radius: var(--border-radius); box-shadow: var(--shadow); }
        .action-buttons { display: flex; gap: 0.75rem; flex-wrap: wrap; }
        .btn { padding: 0.75rem 1.25rem; border: none; border-radius: var(--border-radius); font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: var(--transition); display: flex; align-items: center; gap: 0.5rem; }
        .btn-primary { background: linear-gradient(135deg, var(--accent-green), #059669); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: var(--shadow-lg); }
        .btn-secondary { background: var(--light-blue); color: var(--primary-blue); }
        .btn-secondary:hover { background: var(--secondary-blue); color: white; }
        .btn-outline { background: transparent; border: 1px solid var(--border-color); color: var(--text-dark); }
        .btn-outline:hover { background: var(--bg-light); border-color: var(--primary-blue); }
        .btn-sm { padding: 0.5rem 1rem; font-size: 0.85rem; }
        
        .auto-save-indicator { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; background: var(--bg-light); border-radius: 20px; font-size: 0.85rem; color: var(--text-light); }
        .auto-save-indicator.saving { background: #fff3cd; color: #856404; }
        .auto-save-indicator.saved { background: #d1fae5; color: var(--accent-green); }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px,1fr)); gap: 1rem; margin-bottom: 2rem; }
        .stat-card { background: var(--bg-white); border-radius: var(--border-radius); padding: 1.25rem; box-shadow: var(--shadow); display: flex; align-items: center; gap: 1rem; }
        .stat-icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; }
        .stat-icon.present { background: #d1fae5; color: var(--accent-green); }
        .stat-icon.absent { background: #fee2e2; color: var(--error-red); }
        .stat-icon.late { background: #fef3c7; color: var(--warning-orange); }
        .stat-icon.total { background: var(--light-blue); color: var(--primary-blue); }
        .stat-value { font-size: 1.5rem; font-weight: 700; }
        .stat-label { font-size: 0.875rem; color: var(--text-light); }
        
        .attendance-container { background: var(--bg-white); border-radius: var(--border-radius); box-shadow: var(--shadow); overflow: hidden; margin-bottom: 2rem; }
        .attendance-header { padding: 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue)); color: white; }
        .attendance-title { font-size: 1.1rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; }
        .class-info { display: flex; gap: 1rem; background: rgba(255,255,255,0.1); padding: 0.5rem 1rem; border-radius: 20px; }
        .table-responsive { overflow-x: auto; max-height: 600px; overflow-y: auto; }
        .attendance-table { width: 100%; border-collapse: collapse; min-width: 1000px; }
        .attendance-table th { background: var(--bg-light); padding: 1rem; text-align: left; font-weight: 600; border-bottom: 2px solid var(--border-color); position: sticky; top: 0; z-index: 10; }
        .attendance-table td { padding: 1rem; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
        .attendance-table tr:hover { background: var(--bg-light); }
        
        .student-info { display: flex; align-items: center; gap: 0.75rem; }
        .student-avatar { width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-weight: 600; background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue)); color: white; }
        
        .status-select { padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.85rem; cursor: pointer; width: 120px; }
        .status-select.changed { border-color: var(--warning-orange); background-color: #fff3cd; }
        
        .attendance-legend { display: flex; gap: 1rem; flex-wrap: wrap; background: var(--bg-light); padding: 0.75rem 1.25rem; border-radius: var(--border-radius); }
        .legend-item { display: flex; align-items: center; gap: 0.5rem; font-size: 0.8rem; }
        .legend-color { width: 12px; height: 12px; border-radius: 4px; }
        .legend-color.present { background: var(--accent-green); }
        .legend-color.absent { background: var(--error-red); }
        .legend-color.late { background: var(--warning-orange); }
        
        .settings-card { background: var(--bg-white); border-radius: var(--border-radius); box-shadow: var(--shadow); overflow: hidden; }
        .card-header { padding: 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; gap: 1rem; background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue)); color: white; }
        .card-header i { color: var(--accent-green); }
        .card-header h2 { font-size: 1.2rem; font-weight: 600; }
        .card-body { padding: 1.5rem; }
        
        .modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 2000; opacity: 0; visibility: hidden; transition: var(--transition); }
        .modal-overlay.active { opacity: 1; visibility: visible; }
        .modal-content { background: var(--bg-white); border-radius: var(--border-radius); width: 90%; max-width: 500px; max-height: 90vh; overflow-y: auto; transform: translateY(20px); transition: var(--transition); }
        .modal-overlay.active .modal-content { transform: translateY(0); }
        .modal-header { padding: 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue)); color: white; }
        .modal-header h2 { font-size: 1.2rem; font-weight: 600; display: flex; align-items: center; gap: 0.75rem; }
        .close-modal { background: none; border: none; font-size: 1.5rem; color: white; cursor: pointer; }
        .modal-body { padding: 1.5rem; }
        .modal-footer { padding: 1.5rem; border-top: 1px solid var(--border-color); display: flex; gap: 1rem; justify-content: flex-end; background: var(--bg-light); }
        .form-group { display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 1rem; }
        .form-label { font-size: 0.9rem; font-weight: 500; display: flex; align-items: center; gap: 0.5rem; }
        .form-control { width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--border-color); border-radius: var(--border-radius); font-size: 0.9rem; }
        
        .toast-container { position: fixed; top: 100px; right: 2rem; z-index: 3000; max-width: 400px; }
        .toast { background: var(--bg-white); border-radius: var(--border-radius); padding: 1rem 1.5rem; margin-bottom: 1rem; box-shadow: var(--shadow-lg); border-left: 4px solid var(--success-green); display: flex; align-items: center; gap: 1rem; animation: slideInRight 0.3s ease; }
        .toast.error { border-left-color: var(--error-red); }
        .toast.warning { border-left-color: var(--warning-orange); }
        .toast-icon { width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; }
        .toast.success .toast-icon { background: var(--success-green); }
        .toast.error .toast-icon { background: var(--error-red); }
        @keyframes slideInRight { from { opacity: 0; transform: translateX(100%); } to { opacity: 1; transform: translateX(0); } }
        .loading-spinner { display: inline-block; width: 40px; height: 40px; border: 3px solid var(--light-blue); border-top-color: var(--primary-blue); border-radius: 50%; animation: spin 1s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        @media (max-width:768px) { .main-content { padding: 100px 1rem 1rem; } .filter-grid { grid-template-columns: 1fr; } .toast-container { right: 1rem; left: 1rem; max-width: none; } }
    </style>
</head>
<body>
    <?php 
    if (isset($school) && $school && (!isset($school['is_activated']) || $school['is_activated'] == 0)) {
        include 'trial_banner.php';
    }
    ?>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="academic-level-indicator">
            <div class="academic-level-icon"><i class="fas fa-check-circle"></i></div>
            <div class="academic-level-content">
                <h3>Academic Level: <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $academic_level))); ?></h3>
                <p>Attendance Management - Track student attendance and generate reports</p>
            </div>
            <div class="academic-level-badge"><i class="fas fa-check-circle"></i> Attendance</div>
        </div>

        <div class="filter-section">
            <h3 class="filter-title"><i class="fas fa-filter"></i> Filter Attendance</h3>
            <div class="filter-grid">
                <div class="filter-group"><label class="filter-label"><i class="fas fa-calendar"></i> Date</label><input type="date" id="attendanceDate" class="filter-input" value="<?php echo date('Y-m-d'); ?>"></div>
                <div class="filter-group"><label class="filter-label"><i class="fas fa-graduation-cap"></i> Class</label><select id="filterClass" class="filter-select"><option value="">All Classes</option><?php foreach ($classes as $class): ?><option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['display_name']); ?></option><?php endforeach; ?></select></div>
                <div class="filter-group"><label class="filter-label"><i class="fas fa-stream"></i> Stream</label><select id="filterStream" class="filter-select" disabled><option value="">All Streams</option></select></div>
                <div class="filter-group"><label class="filter-label"><i class="fas fa-calendar-alt"></i> Term</label><select id="filterTerm" class="filter-select"><option value="">All Terms</option><?php foreach ($terms as $term): ?><option value="<?php echo $term['id']; ?>" <?php echo ($activeTerm && $activeTerm['id'] == $term['id']) ? 'selected' : ''; ?>>Term <?php echo $term['term_number'] . ' - ' . $term['academic_year']; ?></option><?php endforeach; ?></select></div>
                <div class="filter-group"><label class="filter-label"><i class="fas fa-search"></i> &nbsp;</label><button class="btn btn-primary" id="loadAttendanceBtn"><i class="fas fa-check-circle"></i> Load Attendance</button></div>
            </div>
        </div>

        <div class="action-bar">
            <div class="action-buttons"><button class="btn btn-primary" id="takeAttendanceBtn"><i class="fas fa-pen"></i> Take Attendance</button><button class="btn btn-secondary" id="saveAttendanceBtn"><i class="fas fa-save"></i> Save All Changes</button><button class="btn btn-outline" id="exportAttendanceBtn"><i class="fas fa-file-export"></i> Export Report</button></div>
            <div style="display:flex; align-items:center; gap:1rem;"><div class="auto-save-indicator" id="autoSaveIndicator"><i class="fas fa-cloud"></i> <span>Auto-save enabled</span></div><div class="attendance-legend"><div class="legend-item"><span class="legend-color present"></span><span>Present</span></div><div class="legend-item"><span class="legend-color absent"></span><span>Absent</span></div><div class="legend-item"><span class="legend-color late"></span><span>Late</span></div></div></div>
        </div>

        <div class="stats-grid" id="statsGrid">
            <div class="stat-card"><div class="stat-icon total"><i class="fas fa-users"></i></div><div class="stat-content"><div class="stat-value" id="totalStudents">0</div><div class="stat-label">Total Students</div></div></div>
            <div class="stat-card"><div class="stat-icon present"><i class="fas fa-check-circle"></i></div><div class="stat-content"><div class="stat-value" id="presentCount">0</div><div class="stat-label">Present</div></div></div>
            <div class="stat-card"><div class="stat-icon absent"><i class="fas fa-times-circle"></i></div><div class="stat-content"><div class="stat-value" id="absentCount">0</div><div class="stat-label">Absent</div></div></div>
            <div class="stat-card"><div class="stat-icon late"><i class="fas fa-clock"></i></div><div class="stat-content"><div class="stat-value" id="lateCount">0</div><div class="stat-label">Late</div></div></div>
        </div>

        <div class="attendance-container">
            <div class="attendance-header">
                <div class="attendance-title"><i class="fas fa-clipboard-list"></i> Attendance Register - <span id="currentDate"><?php echo date('l, F j, Y'); ?></span></div>
                <div class="class-info"><span><i class="fas fa-graduation-cap"></i> <span id="selectedClass">All Classes</span></span><span><i class="fas fa-users"></i> <span id="studentCount">0</span> Students</span></div>
                <div class="quick-actions"><button class="action-btn-sm" id="markAllPresent" title="Mark All Present"><i class="fas fa-check-double"></i></button><button class="action-btn-sm" id="refreshTable" title="Refresh"><i class="fas fa-sync-alt"></i></button></div>
            </div>
            <div class="table-responsive">
                <table class="attendance-table">
                    <thead><tr><th style="width:40px;"><input type="checkbox" id="selectAll"></th><th>Admission No.</th><th>Student Name</th><th>Gender</th><th>Class/Stream</th><th>Status</th><th>Remarks</th></tr></thead>
                    <tbody id="attendanceTableBody"><tr><td colspan="7" style="text-align:center;padding:3rem;"><i class="fas fa-users" style="font-size:3rem;color:var(--text-light);opacity:0.5;margin-bottom:1rem;"></i><h3 style="color:var(--text-dark);">Select filters and click Load Attendance</h3><p style="color:var(--text-light);">Choose class, stream, and date to load attendance records.</p></td></tr></tbody>
                </table>
            </div>
            <div style="padding:1rem 1.5rem;border-top:1px solid var(--border-color);display:flex;justify-content:space-between;align-items:center;">
                <div class="bulk-actions"><span style="font-weight:500;">Bulk Actions:</span><select id="bulkStatus" class="status-select" style="width:150px;"><option value="">Select Status</option><option value="Present">Present</option><option value="Absent">Absent</option><option value="Late">Late</option></select><button class="btn btn-outline btn-sm" id="applyBulkBtn">Apply</button></div>
                <div style="display:flex;gap:1rem;align-items:center;"><span><i class="fas fa-chart-pie"></i> Attendance Rate: <strong id="attendanceRate">0%</strong></span><span><i class="fas fa-clock"></i> Last updated: <span id="lastUpdated">Just now</span></span></div>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-top:1rem;">
            <div class="settings-card"><div class="card-header" style="background:linear-gradient(135deg,var(--primary-blue),var(--dark-blue));"><i class="fas fa-calendar-week"></i><h2>This Week's Attendance</h2></div><div class="card-body" id="weeklySummary"><div style="display:flex;justify-content:space-around;padding:1rem 0;"><div style="text-align:center;"><div style="font-size:2rem;font-weight:700;color:var(--accent-green);">0</div><div style="font-size:0.85rem;color:var(--text-light);">Present</div></div><div style="text-align:center;"><div style="font-size:2rem;font-weight:700;color:var(--error-red);">0</div><div style="font-size:0.85rem;color:var(--text-light);">Absent</div></div><div style="text-align:center;"><div style="font-size:2rem;font-weight:700;color:var(--warning-orange);">0</div><div style="font-size:0.85rem;color:var(--text-light);">Late</div></div></div></div></div>
            <div class="settings-card"><div class="card-header" style="background:linear-gradient(135deg,var(--accent-green),#059669);"><i class="fas fa-chart-line"></i><h2>Attendance Trends</h2></div><div class="card-body" id="trendsSummary"><div style="display:flex;flex-direction:column;gap:1rem;"><div><div style="display:flex;justify-content:space-between;margin-bottom:0.25rem;"><span>Today</span><span style="font-weight:600;" id="todayRate">0%</span></div><div style="width:100%;height:8px;background:var(--border-color);border-radius:4px;"><div id="todayBar" style="width:0%;height:8px;background:linear-gradient(90deg,var(--accent-green),#059669);border-radius:4px;"></div></div></div><div><div style="display:flex;justify-content:space-between;margin-bottom:0.25rem;"><span>This Week</span><span style="font-weight:600;" id="weekRate">0%</span></div><div style="width:100%;height:8px;background:var(--border-color);border-radius:4px;"><div id="weekBar" style="width:0%;height:8px;background:linear-gradient(90deg,var(--primary-blue),var(--secondary-blue));border-radius:4px;"></div></div></div></div></div></div>
        </div>
    </div>

    <div class="modal-overlay" id="takeAttendanceModal">
        <div class="modal-content">
            <div class="modal-header"><h2><i class="fas fa-pen"></i> Take Attendance</h2><button class="close-modal" onclick="closeAttendanceModal()">&times;</button></div>
            <div class="modal-body">
                <form id="takeAttendanceForm">
                    <div class="form-group"><label class="form-label"><i class="fas fa-calendar"></i> Date</label><input type="date" class="form-control" name="attendance_date" value="<?php echo date('Y-m-d'); ?>" required></div>
                    <div class="form-group"><label class="form-label"><i class="fas fa-graduation-cap"></i> Class</label><select class="form-control" name="class_id" id="modalClass" required><option value="">Select Class</option><?php foreach ($classes as $class): ?><option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['display_name']); ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label class="form-label"><i class="fas fa-stream"></i> Stream</label><select class="form-control" name="stream" id="modalStream"><option value="">No Stream</option></select></div>
                    <div class="form-group"><label class="form-label"><i class="fas fa-calendar-alt"></i> Term</label><select class="form-control" name="term_id" required><option value="">Select Term</option><?php foreach ($terms as $term): ?><option value="<?php echo $term['id']; ?>" <?php echo ($activeTerm && $activeTerm['id'] == $term['id']) ? 'selected' : ''; ?>>Term <?php echo $term['term_number'] . ' - ' . $term['academic_year']; ?></option><?php endforeach; ?></select></div>
                </form>
            </div>
            <div class="modal-footer"><button onclick="closeAttendanceModal()" class="btn btn-outline">Cancel</button><button onclick="startAttendance()" class="btn btn-primary"><i class="fas fa-arrow-right"></i> Continue</button></div>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            let currentAttendance = [];
            let changedRecords = new Set();
            let autoSaveTimer;
            let isSaving = false;

            function showToast(type, title, message) {
                const toast = $(`<div class="toast ${type}"><div class="toast-icon"><i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i></div><div class="toast-content"><div class="toast-title">${title}</div><div class="toast-message">${message}</div></div></div>`);
                $('#toastContainer').append(toast);
                setTimeout(() => toast.fadeOut(300, function() { $(this).remove(); }), 4000);
            }

            function updateAutoSaveIndicator(status, message) {
                const indicator = $('#autoSaveIndicator');
                indicator.removeClass('saving saved');
                if (status === 'saving') { indicator.addClass('saving'); indicator.html('<i class="fas fa-spinner fa-spin"></i> <span>Saving...</span>'); }
                else if (status === 'saved') { indicator.addClass('saved'); indicator.html('<i class="fas fa-cloud"></i> <span>All changes saved</span>'); }
                else { indicator.html('<i class="fas fa-cloud"></i> <span>Auto-save enabled</span>'); }
            }

            function updateStats() {
                let present = 0, absent = 0, late = 0;
                $('#attendanceTableBody .status-select').each(function() {
                    const status = $(this).val();
                    if (status === 'Present') present++;
                    else if (status === 'Absent') absent++;
                    else if (status === 'Late') late++;
                });
                const total = present + absent + late;
                const rate = total > 0 ? Math.round((present / total) * 100) : 0;
                $('#totalStudents').text(total);
                $('#presentCount').text(present);
                $('#absentCount').text(absent);
                $('#lateCount').text(late);
                $('#attendanceRate').text(rate + '%');
                $('#todayRate').text(rate + '%');
                $('#todayBar').css('width', rate + '%');
            }

            function markAsChanged(studentId, element) {
                changedRecords.add(studentId);
                $(element).addClass('changed');
                updateAutoSaveIndicator('saving');
                if (autoSaveTimer) clearTimeout(autoSaveTimer);
                autoSaveTimer = setTimeout(() => saveChangedRecords(), 2000);
            }

            function saveChangedRecords() {
                if (changedRecords.size === 0 || isSaving) return;
                isSaving = true;
                const records = [];
                changedRecords.forEach(studentId => {
                    const row = $(`.status-select[data-student-id="${studentId}"]`).closest('tr');
                    records.push({ student_id: studentId, status: row.find('.status-select').val(), remarks: row.find('.remarks-input').val() || '', date: $('#attendanceDate').val(), term_id: $('#filterTerm').val() || null });
                });
                $.ajax({ url: 'ajax/save_attendance.php', method: 'POST', data: JSON.stringify({ records: records }), contentType: 'application/json', dataType: 'json', success: function(response) { if (response.success) { changedRecords.forEach(id => $(`.status-select[data-student-id="${id}"]`).removeClass('changed')); changedRecords.clear(); updateAutoSaveIndicator('saved'); $('#lastUpdated').text('Just now'); } else { updateAutoSaveIndicator('error'); showToast('error', 'Save Failed', response.message || 'Failed to save changes'); } }, error: function() { updateAutoSaveIndicator('error'); showToast('error', 'Error', 'Network error.'); }, complete: function() { isSaving = false; } });
            }

            function loadStreamsForClass(classId, targetSelect, callback) {
                if (!classId) { targetSelect.empty().append('<option value="">All Streams</option>').prop('disabled', true); if (callback) callback([]); return; }
                $.ajax({ url: 'ajax/fetch_streams_attendance.php', method: 'POST', data: { class_id: classId, school_id: <?php echo $school_id; ?> }, dataType: 'json', success: function(response) { targetSelect.empty().append('<option value="">All Streams</option>'); if (response.success && response.data.length > 0) { response.data.forEach(stream => targetSelect.append(`<option value="${stream.id}">${stream.stream_name}</option>`)); targetSelect.prop('disabled', false); } else { targetSelect.append('<option value="0">No Stream</option>').prop('disabled', false); } if (callback) callback(response.data); } });
            }

            function loadAttendanceData() {
                const classId = $('#filterClass').val();
                if (!classId) { showToast('warning', 'Select Class', 'Please select a class to load attendance.'); return; }
                $('#attendanceTableBody').html(`<tr><td colspan="7" style="text-align:center;padding:3rem;"><div class="loading-spinner" style="width:40px;height:40px;border-width:3px;"></div><p style="margin-top:1rem;">Loading attendance data...</p></td></tr>`);
                $.ajax({ url: 'ajax/get_attendance.php', method: 'POST', data: { class_id: classId, stream_id: $('#filterStream').val(), date: $('#attendanceDate').val(), term_id: $('#filterTerm').val() }, dataType: 'json', success: function(response) { if (response.success && response.data && response.data.length > 0) { renderAttendanceTable(response.data); currentAttendance = response.data; changedRecords.clear(); } else { $('#attendanceTableBody').html(`<tr><td colspan="7" style="text-align:center;padding:3rem;"><i class="fas fa-users" style="font-size:3rem;color:var(--text-light);opacity:0.5;"></i><h3>No Students Found</h3><p>No students match the selected filters.</p></td></tr>`); } }, error: function() { $('#attendanceTableBody').html(`<tr><td colspan="7" style="text-align:center;padding:3rem;"><i class="fas fa-exclamation-circle" style="font-size:3rem;color:var(--error-red);"></i><h3>Network Error</h3><p>Failed to connect to server.</p></td></tr>`); } });
            }

            function renderAttendanceTable(data) {
                let html = '';
                data.forEach(student => {
                    const initials = (student.fullname || 'Unknown').split(' ').map(n => n[0]).join('').toUpperCase().substring(0,2);
                    const status = student.attendance_status || 'Present';
                    html += `<tr data-student-id="${student.id}"><td><input type="checkbox" class="student-checkbox" value="${student.id}"></td><td>${escapeHtml(student.admission_no || 'N/A')}</td><td><div class="student-info"><div class="student-avatar">${initials || 'ST'}</div><span>${escapeHtml(student.fullname || 'Unknown')}</span></div></td><td>${escapeHtml(student.gender || 'N/A')}</td><td>${escapeHtml((student.class_name || 'N/A') + ' - ' + (student.stream_name || 'No Stream'))}</td><td><select class="status-select" data-student-id="${student.id}" onchange="handleStatusChange(this, ${student.id})"><option value="Present" ${status === 'Present' ? 'selected' : ''}>Present</option><option value="Absent" ${status === 'Absent' ? 'selected' : ''}>Absent</option><option value="Late" ${status === 'Late' ? 'selected' : ''}>Late</option></select></td><td><input type="text" class="form-control remarks-input" placeholder="Remarks" style="width:150px;padding:0.5rem;" value="${escapeHtml(student.remarks || '')}" onchange="handleRemarksChange(this, ${student.id})"></td></tr>`;
                });
                $('#attendanceTableBody').html(html);
                $('#studentCount').text(data.length);
                updateStats();
            }

            function escapeHtml(text) { if (!text) return ''; const div = document.createElement('div'); div.textContent = text; return div.innerHTML; }

            window.handleStatusChange = function(element, studentId) { markAsChanged(studentId, element); updateStats(); };
            window.handleRemarksChange = function(element, studentId) { markAsChanged(studentId, element); };

            $('#filterClass').on('change', function() { loadStreamsForClass($(this).val(), $('#filterStream'), function() { if ($('#filterClass').val()) loadAttendanceData(); }); });
            $('#filterStream, #attendanceDate, #filterTerm').on('change', function() { if ($('#filterClass').val()) loadAttendanceData(); });
            $('#modalClass').on('change', function() { loadStreamsForClass($(this).val(), $('#modalStream')); });
            $('#loadAttendanceBtn, #refreshTable').on('click', loadAttendanceData);
            $('#selectAll').on('change', function() { $('.student-checkbox').prop('checked', $(this).is(':checked')); });
            $('#takeAttendanceBtn').on('click', function() { $('#takeAttendanceModal').addClass('active'); $('body').css('overflow', 'hidden'); });
            $('#saveAttendanceBtn').on('click', function() { if (changedRecords.size > 0) saveChangedRecords(); else showToast('info', 'No Changes', 'No changes to save.'); });
            $('#exportAttendanceBtn').on('click', function() { const classId = $('#filterClass').val(); if (!classId) { showToast('warning', 'Select Class', 'Please select a class to export.'); return; } window.location.href = `ajax/export_attendance.php?class_id=${classId}&stream_id=${$('#filterStream').val()}&date=${$('#attendanceDate').val()}&term_id=${$('#filterTerm').val()}`; });
            $('#applyBulkBtn').on('click', function() { const bulkStatus = $('#bulkStatus').val(); if (!bulkStatus) { showToast('warning', 'No Status', 'Please select a status to apply.'); return; } const selectedStudents = $('.student-checkbox:checked'); if (selectedStudents.length === 0) { showToast('warning', 'No Students', 'Please select at least one student.'); return; } selectedStudents.each(function() { const row = $(this).closest('tr'); const studentId = $(this).val(); const select = row.find('.status-select'); select.val(bulkStatus); markAsChanged(studentId, select); }); showToast('success', 'Applied', `Marked ${selectedStudents.length} students as ${bulkStatus}.`); $('#selectAll').prop('checked', false); updateStats(); });
            $('#markAllPresent').on('click', function() { $('.status-select').each(function() { const studentId = $(this).data('student-id'); $(this).val('Present'); markAsChanged(studentId, this); }); showToast('success', 'Success', 'All students marked as Present.'); updateStats(); });
            window.addEventListener('beforeunload', function() { if (changedRecords.size > 0) saveChangedRecords(); });
        });

        function closeAttendanceModal() { $('#takeAttendanceModal').removeClass('active'); $('body').css('overflow', ''); }
        function startAttendance() { const classId = $('#modalClass').val(); const date = $('input[name="attendance_date"]').val(); if (!classId || !date) { showToast('warning', 'Missing Fields', 'Please select class and date.'); return; } $('#filterClass').val(classId); $('#attendanceDate').val(date); loadAttendanceData(); closeAttendanceModal(); }
    </script>
</body>
</html>