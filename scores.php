<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/session_timeout.php';
require_once 'includes/config.php';
require_once 'includes/PermissionHelper.php';

// Check if user is logged in
if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['school_id'])) {
    header('Location: login.php');
    exit();
}

// Session variables
$teacher_id = $_SESSION['teacher_id'];
$school_id = $_SESSION['school_id'];
$academic_level = $_SESSION['academic_level'] ?? 'primary';

// Initialize Permission Helper
$permissionHelper = new PermissionHelper($dbh, $school_id, $teacher_id);

// Check if user has permission to view scores page
$permissionHelper->requireAnyPermission(['scoresView', 'scoresViewAll'], 'dashboard.php');

// Determine which actions are allowed based on permissions
$canCreate = $permissionHelper->hasPermission('scoresCreate');
$canEdit = $permissionHelper->hasPermission('scoresEdit');
$canDelete = $permissionHelper->hasPermission('scoresDelete');
$canImport = $permissionHelper->hasPermission('scoresImport');
$canExport = $permissionHelper->hasPermission('scoresExport');
$canViewAll = $permissionHelper->hasPermission('scoresViewAll');
$isSuperAdmin = $permissionHelper->isSuperAdmin();
$currentUserRole = $permissionHelper->getRole();

$conn = $dbh;

// Initialize variables
$subjectCount = 0;
$examCount = 0;
$studentCount = 0;
$classes = [];
$exams = [];
$school = [];
$teacher_name = '';

// Fetch teacher's name
$teacherQuery = $conn->prepare("SELECT CONCAT(firstname, ' ', lastname) as teacher_name FROM tblteachers WHERE id = ?");
$teacherQuery->execute([$teacher_id]);
$teacherRow = $teacherQuery->fetch(PDO::FETCH_ASSOC);
if ($teacherRow) {
    $teacher_name = $teacherRow['teacher_name'];
}

// Fetch teacher's assigned subjects (based on academic level)
$subjectQuery = $conn->prepare("
    SELECT COUNT(DISTINCT l.subject_id) as count 
    FROM tbllessons l
    JOIN tblclasses c ON l.class_id = c.id
    WHERE l.teacher_id = ? 
    AND l.school_id = ?
    AND c.academic_level = ?
");
$subjectQuery->execute([$teacher_id, $school_id, $academic_level]);
$subjectRow = $subjectQuery->fetch(PDO::FETCH_ASSOC);
if ($subjectRow) {
    $subjectCount = $subjectRow['count'];
}

// Fetch classes - FILTERED BY ACADEMIC LEVEL FROM SESSION
$classesQuery = $conn->prepare("
    SELECT id, class_level as display_name 
    FROM tblclasses 
    WHERE school_id = ? 
    AND academic_level = ?
    ORDER BY class_level
");
$classesQuery->execute([$school_id, $academic_level]);
$classes = $classesQuery->fetchAll(PDO::FETCH_ASSOC);

// Get current year
$current_year = date('Y');

// Fetch terms - filtered by school
$termsQuery = $conn->prepare("
    SELECT id, term_name, term_number, academic_year, start_date, end_date,
           CASE 
               WHEN CURDATE() BETWEEN start_date AND end_date THEN 'active'
               WHEN CURDATE() < start_date THEN 'upcoming'
               ELSE 'closed'
           END as term_status
    FROM tblterms 
    WHERE school_id = ?
    ORDER BY academic_year DESC, term_number
");
$termsQuery->execute([$school_id]);
$terms = $termsQuery->fetchAll(PDO::FETCH_ASSOC);

// Get distinct years from terms
$yearsQuery = $conn->prepare("
    SELECT DISTINCT academic_year as year 
    FROM tblterms 
    WHERE school_id = ?
    ORDER BY academic_year DESC
");
$yearsQuery->execute([$school_id]);
$years = $yearsQuery->fetchAll(PDO::FETCH_COLUMN);

// Fetch exams count (filtered by academic level)
$examQuery = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM tblexam e
    JOIN tblclasses c ON e.class_id = c.id
    WHERE e.school_id = ? 
    AND c.academic_level = ?
");
$examQuery->execute([$school_id, $academic_level]);
$examRow = $examQuery->fetch(PDO::FETCH_ASSOC);
if ($examRow) {
    $examCount = $examRow['count'];
}

// Fetch student count (filtered by academic level)
$studentCountQuery = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM tblstudents s
    JOIN tblclasses c ON s.class_id = c.id
    WHERE s.school_id = ? 
    AND c.academic_level = ?
");
$studentCountQuery->execute([$school_id, $academic_level]);
$studentCountRow = $studentCountQuery->fetch(PDO::FETCH_ASSOC);
if ($studentCountRow) {
    $studentCount = $studentCountRow['count'];
}

// Get school info
$schoolQuery = $conn->prepare("SELECT * FROM tblschoolinfo WHERE id = ?");
$schoolQuery->execute([$school_id]);
$school = $schoolQuery->fetch(PDO::FETCH_ASSOC);

// Academic level display names
$level_names = [
    'primary' => 'Primary School',
    'junior_secondary' => 'Junior Secondary',
    'senior_secondary' => 'Senior Secondary',
    'college' => 'College'
];
$current_level_name = $level_names[$academic_level] ?? 'Primary School';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Score Entry - EduScore</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="images/logo.png" />
    <link rel="apple-touch-icon" href="images/logo.png">
    <style>
        :root {
            --primary-blue: #1e3a8a;
            --primary-blue-light: #2563eb;
            --accent-yellow: #fbbf24;
            --accent-yellow-dark: #f59e0b;
            --success-green: #10b981;
            --warning-orange: #f59e0b;
            --error-red: #ef4444;
            --text-dark: #1f2937;
            --text-light: #6b7280;
            --bg-light: #f9fafb;
            --bg-white: #ffffff;
            --border-color: #e5e7eb;
            --border-radius: 12px;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --transition: all 0.2s ease;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background: var(--bg-light); color: var(--text-dark); }
        .main-content { margin-left: 280px; min-height: 100vh; padding: 90px 1.5rem 2rem; transition: margin-left 0.3s ease; }
        @media (max-width: 992px) { .main-content { margin-left: 0; padding: 80px 1rem 1rem; } }
        .page-header { background: var(--bg-white); border-radius: var(--border-radius); padding: 1.25rem 1.5rem; margin-bottom: 1.5rem; box-shadow: var(--shadow); border-left: 3px solid var(--accent-yellow); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem; }
        .scores-page-title { font-size: 1.35rem; font-weight: 700; color: var(--primary-blue); display: flex; align-items: center; gap: 0.5rem; }
        .academic-level-badge { background: var(--accent-yellow); color: var(--primary-blue); padding: 0.2rem 0.6rem; border-radius: 20px; font-size: 0.65rem; font-weight: 600; margin-left: 0.5rem; }
        .role-badge { background: var(--primary-blue); color: white; padding: 0.35rem 0.8rem; border-radius: 20px; font-size: 0.7rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.35rem; }
        .permission-denied { background: #fef2f2; border: 1px solid #fecaca; color: var(--error-red); padding: 2rem; border-radius: var(--border-radius); text-align: center; margin: 2rem 0; }
        .teacher-info-banner { background: var(--primary-blue); border-radius: var(--border-radius); padding: 1rem 1.25rem; margin-bottom: 1.5rem; color: white; display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; }
        .teacher-avatar { width: 45px; height: 45px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
        .stats-container { display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.75rem; margin-bottom: 1.5rem; }
        .stat-card { background: var(--bg-white); border-radius: var(--border-radius); padding: 0.75rem; display: flex; align-items: center; gap: 0.75rem; box-shadow: var(--shadow); transition: var(--transition); }
        .stat-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1rem; }
        .stat-icon.classes { background: #dbeafe; color: var(--primary-blue); }
        .stat-icon.subjects { background: #d1fae5; color: var(--success-green); }
        .stat-icon.exams { background: #fef3c7; color: var(--warning-orange); }
        .stat-icon.students { background: #ede9fe; color: #7c3aed; }
        .stat-value { font-size: 1.2rem; font-weight: 700; }
        .stat-label { font-size: 0.65rem; color: var(--text-light); }
        .filter-section { background: var(--bg-white); border-radius: var(--border-radius); padding: 1rem; margin-bottom: 1.5rem; box-shadow: var(--shadow); }
        .filter-title { font-size: 0.9rem; font-weight: 600; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem; color: var(--primary-blue); }
        .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 0.75rem; }
        .filter-group { display: flex; flex-direction: column; gap: 0.25rem; }
        .filter-label { font-size: 0.7rem; font-weight: 500; color: var(--text-light); display: flex; align-items: center; gap: 0.25rem; }
        .filter-select { width: 100%; padding: 0.6rem 0.5rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.8rem; background: var(--bg-white); cursor: pointer; }
        .filter-select:disabled { background: #f3f4f6; cursor: not-allowed; opacity: 0.7; }
        .term-badge { display: inline-block; padding: 0.15rem 0.4rem; border-radius: 4px; font-size: 0.6rem; font-weight: 600; margin-left: 0.4rem; }
        .term-active { background: rgba(16,185,129,0.15); color: #10b981; }
        .term-upcoming { background: rgba(245,158,11,0.15); color: #f59e0b; }
        .term-closed { background: rgba(239,68,68,0.15); color: #ef4444; }
        .total-scores-container { margin-top: 1rem; background: #fef3c7; border-radius: 8px; padding: 0.75rem; border-left: 3px solid var(--accent-yellow-dark); }
        .total-scores-inner { display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; }
        .total-score-badge { background: var(--accent-yellow-dark); color: white; padding: 0.3rem 0.8rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
        .action-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; gap: 0.75rem; flex-wrap: wrap; }
        .action-buttons { display: flex; gap: 0.5rem; }
        .btn { padding: 0.5rem 1rem; border-radius: 8px; font-weight: 500; font-size: 0.75rem; cursor: pointer; display: inline-flex; align-items: center; gap: 0.4rem; border: none; transition: var(--transition); }
        .btn-primary { background: var(--primary-blue); color: white; border: 1px solid var(--accent-yellow); }
        .btn-success { background: var(--success-green); color: white; }
        .btn-warning { background: var(--warning-orange); color: white; }
        .btn-info { background: #3b82f6; color: white; }
        .btn-secondary { background: #f3f4f6; color: var(--text-dark); border: 1px solid var(--border-color); }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn:hover:not(:disabled) { transform: translateY(-1px); box-shadow: var(--shadow-md); }
        .scores-table-container { background: var(--bg-white); border-radius: var(--border-radius); overflow-x: auto; box-shadow: var(--shadow); }
        .scores-table { width: 100%; border-collapse: collapse; min-width: 700px; }
        .scores-table th { background: var(--primary-blue); padding: 0.75rem; text-align: left; font-weight: 600; color: white; font-size: 0.7rem; text-transform: uppercase; position: sticky; top: 0; }
        .scores-table td { padding: 0.6rem 0.75rem; border-bottom: 1px solid var(--border-color); font-size: 0.8rem; vertical-align: middle; }
        .scores-table tr:hover { background: var(--bg-light); }
        .student-name-cell { display: flex; align-items: center; gap: 0.6rem; }
        .student-avatar { width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.8rem; flex-shrink: 0; }
        .student-avatar.male { background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; border: 2px solid #bfdbfe; }
        .student-avatar.female { background: linear-gradient(135deg, #ec4899, #db2777); color: white; border: 2px solid #fbcfe8; }
        .student-avatar.other { background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: white; border: 2px solid #ddd6fe; }
        .score-input { width: 70px; padding: 0.5rem 0.25rem; border: 1px solid var(--border-color); border-radius: 6px; text-align: center; font-size: 0.75rem; font-weight: 500; }
        .score-input:focus { outline: none; border-color: var(--primary-blue); box-shadow: 0 0 0 2px rgba(30,58,138,0.1); }
        .score-input:disabled { background: #f3f4f6; cursor: not-allowed; }
        .grade-badge { display: inline-block; padding: 0.2rem 0.5rem; border-radius: 15px; font-size: 0.7rem; font-weight: 600; min-width: 45px; text-align: center; }
        .grade-EE { background: rgba(16,185,129,0.15); color: #10b981; }
        .grade-ME { background: rgba(59,130,246,0.15); color: #2563eb; }
        .grade-AE { background: rgba(245,158,11,0.15); color: #d97706; }
        .grade-BE { background: rgba(239,68,68,0.15); color: #dc2626; }
        .grade-X { background: rgba(107,114,128,0.15); color: #6b7280; font-weight: 700; }
        .empty-state { text-align: center; padding: 2rem; color: var(--text-light); }
        .empty-state i { font-size: 2rem; margin-bottom: 0.5rem; opacity: 0.5; }
        .loading-spinner { display: inline-block; width: 20px; height: 20px; border: 2px solid rgba(59,130,246,0.3); border-radius: 50%; border-top-color: var(--primary-blue); animation: spin 1s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 2000; backdrop-filter: blur(2px); }
        .modal-overlay.active { display: flex; animation: fadeIn 0.2s ease; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .modal { background: var(--bg-white); border-radius: var(--border-radius); width: 90%; max-width: 400px; max-height: 80vh; overflow-y: auto; animation: slideUp 0.3s ease; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .modal-header { padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-color); background: var(--primary-blue); color: white; display: flex; justify-content: space-between; align-items: center; border-radius: var(--border-radius) var(--border-radius) 0 0; }
        .modal-title { font-size: 1rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; }
        .close-modal { background: none; border: none; color: white; cursor: pointer; font-size: 1.2rem; width: 32px; height: 32px; border-radius: 6px; }
        .close-modal:hover { background: rgba(255,255,255,0.1); }
        .modal-body { padding: 1.25rem; }
        .modal-footer { padding: 1rem 1.25rem; border-top: 1px solid var(--border-color); display: flex; gap: 0.75rem; justify-content: flex-end; }
        .form-group { margin-bottom: 1rem; }
        .form-label { font-size: 0.8rem; font-weight: 500; margin-bottom: 0.25rem; display: block; }
        .form-control { width: 100%; padding: 0.6rem 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.8rem; }
        .toast-container { position: fixed; bottom: 20px; right: 20px; z-index: 3000; max-width: 320px; }
        .toast { background: var(--bg-white); border-radius: 8px; padding: 0.75rem 1rem; margin-bottom: 0.5rem; box-shadow: 0 4px 12px rgba(0,0,0,0.15); border-left: 3px solid var(--success-green); display: flex; align-items: center; gap: 0.75rem; font-size: 0.8rem; animation: slideInRight 0.3s ease; }
        .toast.error { border-left-color: var(--error-red); }
        .toast.warning { border-left-color: var(--warning-orange); }
        @keyframes slideInRight { from { opacity: 0; transform: translateX(100%); } to { opacity: 1; transform: translateX(0); } }
        .save-indicator { position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); background: var(--primary-blue); color: white; padding: 0.5rem 1rem; border-radius: 30px; font-size: 0.75rem; z-index: 1000; display: flex; align-items: center; gap: 0.5rem; white-space: nowrap; box-shadow: var(--shadow-md); }
        .academic-level-warning { background: #fef3c7; border-left: 3px solid var(--warning-orange); padding: 0.75rem 1rem; margin-bottom: 1rem; border-radius: 8px; font-size: 0.75rem; color: #92400e; display: flex; align-items: center; gap: 0.5rem; }
        .academic-level-loading { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; display: none; justify-content: center; align-items: center; backdrop-filter: blur(3px); }
        .academic-level-loading.active { display: flex; }
        .loading-content { background: white; padding: 20px 30px; border-radius: 12px; display: flex; flex-direction: column; align-items: center; gap: 15px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); }
        @media (max-width: 768px) { .stats-container { grid-template-columns: repeat(2, 1fr); } .filter-grid { grid-template-columns: 1fr; } .action-bar { flex-direction: column; align-items: stretch; } .total-scores-inner { flex-direction: column; align-items: flex-start; } }
    </style>
</head>
<body>
    <?php include 'trial_banner.php'; ?>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <div>
                <h1 class="scores-page-title">
                    <i class="fas fa-chart-line"></i> Score Entry
                    <span class="academic-level-badge" id="academicLevelBadge"><?php echo htmlspecialchars($current_level_name); ?></span>
                </h1>
                <p class="page-description">Enter student scores for exams - <span id="academicLevelDescription"><?php echo htmlspecialchars($current_level_name); ?></span></p>
            </div>
            <span class="role-badge"><i class="fas fa-<?php echo $isSuperAdmin ? 'crown' : 'user-tag'; ?>"></i> <?php echo htmlspecialchars($currentUserRole ?? 'User'); ?></span>
        </div>

        <?php if (!$permissionHelper->hasAnyPermission(['scoresView', 'scoresViewAll'])): ?>
            <div class="permission-denied"><i class="fas fa-lock"></i><h3>Access Denied</h3><p>You do not have permission to view score entry.</p></div>
        <?php else: ?>

        <div class="academic-level-warning" id="academicLevelWarning" style="<?php echo empty($classes) ? '' : 'display: none;'; ?>">
            <i class="fas fa-info-circle"></i> 
            <span id="warningMessage">No classes found for <?php echo htmlspecialchars($current_level_name); ?>. Please change the academic level from the header dropdown to view relevant data.</span>
        </div>

        <div class="teacher-info-banner">
            <div class="teacher-avatar"><i class="fas fa-chalkboard-teacher"></i></div>
            <div>
                <div class="teacher-name"><?php echo htmlspecialchars($teacher_name); ?></div>
                <div class="teacher-meta">
                    <span><i class="fas fa-book"></i> <?php echo $subjectCount; ?> Subjects</span>
                    <span><i class="fas fa-calendar"></i> <?php echo $current_year; ?></span>
                    <span><i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($current_level_name); ?></span>
                </div>
            </div>
        </div>

        <div class="stats-container">
            <div class="stat-card"><div class="stat-icon classes"><i class="fas fa-graduation-cap"></i></div><div><div class="stat-value"><?php echo count($classes); ?></div><div class="stat-label">Classes</div></div></div>
            <div class="stat-card"><div class="stat-icon subjects"><i class="fas fa-book"></i></div><div><div class="stat-value"><?php echo $subjectCount; ?></div><div class="stat-label">Your Subjects</div></div></div>
            <div class="stat-card"><div class="stat-icon exams"><i class="fas fa-file-alt"></i></div><div><div class="stat-value"><?php echo $examCount; ?></div><div class="stat-label">Exams</div></div></div>
            <div class="stat-card"><div class="stat-icon students"><i class="fas fa-users"></i></div><div><div class="stat-value"><?php echo $studentCount; ?></div><div class="stat-label">Students</div></div></div>
        </div>

        <div class="filter-section">
            <div class="filter-title"><i class="fas fa-filter"></i> Select Criteria</div>
            <div class="filter-grid">
                <div class="filter-group">
                    <label class="filter-label"><i class="fas fa-graduation-cap"></i> Class</label>
                    <select id="selectClass" class="filter-select">
                        <option value="">Select Class</option>
                        <?php foreach($classes as $class): ?>
                            <option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['display_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="filter-label"><i class="fas fa-stream"></i> Stream</label>
                    <select id="selectStream" class="filter-select" disabled><option value="">Select Class First</option></select>
                </div>
                <div class="filter-group">
                    <label class="filter-label"><i class="fas fa-book"></i> Subject</label>
                    <select id="selectSubject" class="filter-select" disabled><option value="">Select Class First</option></select>
                </div>
                <div class="filter-group">
                    <label class="filter-label"><i class="fas fa-calendar-alt"></i> Year</label>
                    <select id="selectYear" class="filter-select">
                        <?php foreach($years as $year): ?>
                            <option value="<?php echo $year; ?>" <?php echo ($year == $current_year) ? 'selected' : ''; ?>><?php echo $year; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="filter-label"><i class="fas fa-calendar"></i> Term</label>
                    <select id="selectTerm" class="filter-select">
                        <?php foreach($terms as $term): ?>
                            <option value="<?php echo $term['id']; ?>" data-status="<?php echo $term['term_status']; ?>" <?php echo ($term['term_status'] == 'active') ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($term['term_name'] . ' ' . $term['academic_year']); ?> 
                                <span class="term-badge term-<?php echo $term['term_status']; ?>"><?php echo ucfirst($term['term_status']); ?></span>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="filter-label"><i class="fas fa-file-alt"></i> Exam</label>
                    <select id="selectExam" class="filter-select" disabled><option value="">Select Class & Subject</option></select>
                </div>
            </div>

            <div class="total-scores-container">
                <div class="total-scores-inner">
                    <div class="total-score-badge"><i class="fas fa-star"></i> Total</div>
                    <div class="total-score-display" id="totalScoreDisplay">0</div>
                    <?php if ($canEdit): ?>
                    <div class="total-score-input-group">
                        <label><i class="fas fa-pencil-alt"></i> Max Score:</label>
                        <input type="number" id="totalScoreInput" class="total-score-input" min="0" max="500" placeholder="e.g., 100" disabled>
                        <span id="totalScoreStatus"></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="action-bar">
            <div><i class="fas fa-users"></i> Students <span id="studentCount">(0)</span></div>
            <div class="action-buttons">
                <?php if ($canImport): ?><button class="btn btn-info" id="importBtn" disabled><i class="fas fa-file-import"></i> Import</button><?php endif; ?>
                <?php if ($canExport): ?><button class="btn btn-success" id="downloadBtn" disabled><i class="fas fa-download"></i> CSV</button><?php endif; ?>
                <?php if ($canExport): ?><button class="btn btn-warning" id="printBtn" disabled><i class="fas fa-print"></i> Print</button><?php endif; ?>
            </div>
        </div>

        <div class="scores-table-container">
            <table class="scores-table">
                <thead>
                    <tr><th><i class="fas fa-hashtag"></i> #</th><th><i class="fas fa-user"></i> Student</th><th><i class="fas fa-percentage"></i> Score</th><th><i class="fas fa-tag"></i> Grade</th><th><i class="fas fa-chart-bar"></i> Rubric</th><th><i class="fas fa-comment"></i> Remarks</th></tr>
                </thead>
                <tbody id="scoresTableBody">
                    <tr><td colspan="6"><div class="empty-state"><i class="fas fa-users"></i><h3>No Students</h3><p>Select class, subject, and exam to load students</p></div></td></tr>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($canImport): ?>
    <div class="modal-overlay" id="importModal">
        <div class="modal">
            <div class="modal-header"><h3 class="modal-title"><i class="fas fa-file-import"></i> Import Scores</h3><button class="close-modal" id="closeImportModal">&times;</button></div>
            <div class="modal-body">
                <div class="form-group"><label class="form-label">Excel File (.xlsx, .xls, .csv)</label><input type="file" id="importFile" class="form-control" accept=".xlsx,.xls,.csv"></div>
                <button class="btn btn-info" id="downloadTemplateBtn" style="width:100%"><i class="fas fa-download"></i> Download Template</button>
            </div>
            <div class="modal-footer"><button class="btn btn-secondary" id="cancelImportBtn">Cancel</button><button class="btn btn-primary" id="saveImportBtn"><i class="fas fa-upload"></i> Import</button></div>
        </div>
    </div>
    <?php endif; ?>

    <div class="toast-container" id="toastContainer"></div>
    <div class="save-indicator" id="saveIndicator" style="display: none;"><i class="fas fa-sync fa-spin"></i> Saving...</div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            const PERMISSIONS = { canEdit: <?php echo $canEdit ? 'true' : 'false'; ?>, canImport: <?php echo $canImport ? 'true' : 'false'; ?>, canExport: <?php echo $canExport ? 'true' : 'false'; ?> };
            
            let state = { classId: null, streamId: null, subjectId: null, examId: null, termId: null, year: <?php echo $current_year; ?>, teacherId: <?php echo $teacher_id; ?>, schoolId: <?php echo $school_id; ?>, totalScore: 0, students: [], pendingScores: {}, saveTimer: null, gradingScale: [], currentAcademicLevel: '<?php echo $academic_level; ?>', academicLevelName: '<?php echo $current_level_name; ?>' };
            
            const $class = $('#selectClass'), $stream = $('#selectStream'), $subject = $('#selectSubject'), $exam = $('#selectExam'), $term = $('#selectTerm'), $year = $('#selectYear'), $tableBody = $('#scoresTableBody'), $studentCount = $('#studentCount'), $totalInput = $('#totalScoreInput'), $totalDisplay = $('#totalScoreDisplay'), $saveIndicator = $('#saveIndicator');
            
            function showToast(type, title, message) { const toast = $(`<div class="toast ${type}"><div class="toast-icon"><i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i></div><div class="toast-content"><div class="toast-title">${title}</div><div class="toast-message">${message}</div></div></div>`); $('#toastContainer').append(toast); setTimeout(() => toast.fadeOut(300, function() { $(this).remove(); }), 3000); }
            
            function escapeHtml(text) { if (!text) return ''; const div = document.createElement('div'); div.textContent = text; return div.innerHTML; }
            
            function fetchGradingScale(classId, streamId) { return new Promise((resolve) => { $.ajax({ url: 'ajax/get_grading_scale.php', method: 'POST', data: { class_id: classId, stream_id: streamId || 0 }, dataType: 'json' }).done(function(response) { if (response.success && response.grading_scale) { state.gradingScale = response.grading_scale; resolve(response.grading_scale); } else { state.gradingScale = [{ lower_limit: 80, upper_limit: 100, grade: 'EE', points: 4, remarks: 'Exceeding Expectations' }, { lower_limit: 65, upper_limit: 79, grade: 'ME', points: 3, remarks: 'Meeting Expectations' }, { lower_limit: 50, upper_limit: 64, grade: 'AE', points: 2, remarks: 'Approaching Expectations' }, { lower_limit: 0, upper_limit: 49, grade: 'BE', points: 1, remarks: 'Below Expectations' }]; resolve(state.gradingScale); } }).fail(function() { state.gradingScale = [{ lower_limit: 80, upper_limit: 100, grade: 'EE', points: 4, remarks: 'Exceeding Expectations' }, { lower_limit: 65, upper_limit: 79, grade: 'ME', points: 3, remarks: 'Meeting Expectations' }, { lower_limit: 50, upper_limit: 64, grade: 'AE', points: 2, remarks: 'Approaching Expectations' }, { lower_limit: 0, upper_limit: 49, grade: 'BE', points: 1, remarks: 'Below Expectations' }]; resolve(state.gradingScale); }); }); }
            
            function calculateGrade(score, total) { if (score === null || score === undefined || score === '') return { grade: '-', rubric: '-', remarks: 'Not scored', points: 0 }; const numericScore = parseFloat(score); const numericTotal = parseFloat(total) || 100; if (isNaN(numericScore)) return { grade: '-', rubric: '-', remarks: 'Invalid score', points: 0 }; if (numericScore === 0) return { grade: 'X', rubric: '0', remarks: 'No score / Absent', points: 0 }; const percentage = (numericScore / numericTotal) * 100; if (state.gradingScale && state.gradingScale.length > 0) { for (let i = 0; i < state.gradingScale.length; i++) { const grade = state.gradingScale[i]; if (percentage >= grade.lower_limit && percentage <= grade.upper_limit) return { grade: grade.grade, rubric: grade.points, remarks: grade.remarks, points: grade.points }; } } if (percentage >= 80) return { grade: 'EE', rubric: 4, remarks: 'Exceeding Expectations', points: 4 }; if (percentage >= 65) return { grade: 'ME', rubric: 3, remarks: 'Meeting Expectations', points: 3 }; if (percentage >= 50) return { grade: 'AE', rubric: 2, remarks: 'Approaching Expectations', points: 2 }; if (percentage > 0) return { grade: 'BE', rubric: 1, remarks: 'Below Expectations', points: 1 }; return { grade: '-', rubric: '-', remarks: 'Not graded', points: 0 }; }
            
            function renderTable() { if (!state.students.length) { $tableBody.html('<tr><td colspan="6"><div class="empty-state"><i class="fas fa-users"></i><h3>No Students</h3><p>Select criteria to load students</p></div></td></tr>'); return; } let html = ''; state.students.forEach((student, index) => { const name = student.full_name || student.name || 'Unknown'; const adm = student.admission_no || student.adm_no || 'N/A'; const gender = (student.gender || student.Gender || '').toLowerCase(); let avatarClass = 'other'; if (gender === 'male') avatarClass = 'male'; else if (gender === 'female') avatarClass = 'female'; let initials = ''; if (name.includes(' ')) { const parts = name.split(' '); initials = parts[0].charAt(0).toUpperCase() + (parts[parts.length-1].charAt(0) || '').toUpperCase(); } else { initials = name.charAt(0).toUpperCase(); } if (initials.length > 2) initials = initials.substring(0,2); const score = student.score || ''; const gradeInfo = calculateGrade(score, state.totalScore || 100); const gradeClass = gradeInfo ? `grade-${gradeInfo.grade}` : ''; html += `<tr data-student-id="${student.id}"><td>${index+1}</td><td><div class="student-name-cell"><div class="student-avatar ${avatarClass}">${initials}</div><div class="student-info"><div class="student-name">${escapeHtml(name)}</div><div class="student-adm"><i class="fas fa-id-card"></i> ${escapeHtml(adm)}</div></div></div></td><td><div class="score-input-wrapper"><input type="number" class="score-input" data-id="${student.id}" value="${score}" min="0" max="${state.totalScore || 100}" step="1" placeholder="-" ${!PERMISSIONS.canEdit ? 'disabled' : ''}></div></td><td><span class="grade-badge ${gradeClass}">${gradeInfo?.grade || '-'}</span></td><td class="rubric-display">${gradeInfo?.grade === 'X' ? '0' : (gradeInfo?.rubric || '-')}</td><td class="remarks-display">${gradeInfo?.grade === 'X' ? 'No score / Absent' : (gradeInfo?.remarks || '-')}</td></tr>`; }); $tableBody.html(html); attachScoreEvents(); }
            
            function attachScoreEvents() { $('.score-input').off('input').on('input', function() { const $input = $(this); const studentId = $input.data('id'); let value = parseFloat($input.val()) || 0; if (value < 0) { $input.val(0); showToast('warning', 'Invalid', 'Score cannot be negative'); return; } if (state.totalScore > 0 && value > state.totalScore) { $input.val(state.totalScore); showToast('warning', 'Limit', `Max score is ${state.totalScore}`); return; } const gradeInfo = calculateGrade(value, state.totalScore || 100); const $row = $input.closest('tr'); if (gradeInfo) { $row.find('.grade-badge').text(gradeInfo.grade).removeClass('grade-EE grade-ME grade-AE grade-BE grade-X').addClass(`grade-${gradeInfo.grade}`); $row.find('.rubric-display').text(gradeInfo.grade === 'X' ? '0' : gradeInfo.rubric); $row.find('.remarks-display').text(gradeInfo.grade === 'X' ? 'No score / Absent' : gradeInfo.remarks); } state.pendingScores[studentId] = { score: value }; if (state.saveTimer) clearTimeout(state.saveTimer); state.saveTimer = setTimeout(saveScores, 1500); }); }
            
            function saveScores() { const pending = Object.keys(state.pendingScores); if (!pending.length) return; $saveIndicator.show(); const scores = []; pending.forEach(studentId => { scores.push({ student_id: parseInt(studentId), subject_id: state.subjectId, exam_id: state.examId, class_id: state.classId, stream_id: state.streamId || 0, term_id: state.termId, academic_year: state.year, score: state.pendingScores[studentId].score, total_score: state.totalScore || 100 }); }); $.ajax({ url: 'ajax/save_scores.php', method: 'POST', contentType: 'application/json', data: JSON.stringify({ scores }), dataType: 'json' }).done(function(response) { if (response.success) { let saved = 0; pending.forEach(studentId => { if (response.details?.find(d => d.student_id == studentId && d.status !== 'failed')) { saved++; delete state.pendingScores[studentId]; } }); showToast('success', 'Saved', `${saved} score(s) saved`); } else { showToast('error', 'Save Failed', response.message || 'Could not save scores'); } }).fail(function() { showToast('error', 'Error', 'Network error while saving'); }).always(function() { $saveIndicator.fadeOut(); }); }
            
            function saveExamTotal(total) { if (!state.classId || !state.subjectId || !state.examId) return; if (total <= 0) { showToast('warning', 'Invalid', 'Total score must be greater than 0'); return; } $.ajax({ url: 'ajax/save_exam_total.php', method: 'POST', contentType: 'application/json', data: JSON.stringify({ class_id: state.classId, stream_id: state.streamId || 0, subject_id: state.subjectId, exam_id: state.examId, term_id: state.termId, year: state.year, total_score: total, teacher_id: state.teacherId, school_id: state.schoolId, academic_level: state.currentAcademicLevel }), dataType: 'json' }).done(function(response) { if (response.success) { state.totalScore = total; $totalDisplay.text(total); $('.score-input').attr('max', total); showToast('success', 'Total Saved', `Max score set to ${total}`); } else { showToast('error', 'Failed', response.message || 'Could not save total'); } }).fail(function() { showToast('error', 'Error', 'Network error'); }); }
            
            function fetchStreams(classId) { if (!classId) { $stream.html('<option value="">Select Class First</option>').prop('disabled', true); return; } $.ajax({ url: 'ajax/get_streams.php', method: 'POST', data: { class_id: classId, academic_level: state.currentAcademicLevel }, dataType: 'json' }).done(function(response) { if (response.success && response.streams && response.streams.length > 0) { let options = '<option value="">All Streams (Optional)</option>'; response.streams.forEach(stream => { options += `<option value="${stream.id}">${escapeHtml(stream.stream_name)}</option>`; }); $stream.html(options).prop('disabled', false); } else { $stream.html('<option value="">No Streams Available</option>').prop('disabled', false); } }).fail(function() { $stream.html('<option value="">Error loading streams</option>').prop('disabled', false); }); }
            
            function fetchSubjects(classId) { if (!classId) { $subject.html('<option value="">Select Class First</option>').prop('disabled', true); return; } const streamId = $stream.val() || null; $.ajax({ url: 'ajax/get_subjects_scores.php', method: 'POST', data: { class_id: classId, stream_id: streamId, teacher_id: state.teacherId, academic_level: state.currentAcademicLevel }, dataType: 'json' }).done(function(response) { if (response.success && response.subjects && response.subjects.length > 0) { let options = '<option value="">Select Subject</option>'; response.subjects.forEach(subject => { options += `<option value="${subject.id}">${escapeHtml(subject.subject_name)}</option>`; }); $subject.html(options).prop('disabled', false); } else { $subject.html('<option value="">No subjects assigned</option>').prop('disabled', true); showToast('warning', 'No Subjects', 'You have no subjects assigned for this class.'); } }).fail(function() { $subject.html('<option value="">Error loading subjects</option>').prop('disabled', true); showToast('error', 'Error', 'Failed to load subjects.'); }); }
            
            function fetchExams(classId, subjectId) { if (!classId || !subjectId) { $exam.html('<option value="">Select Class & Subject</option>').prop('disabled', true); return; } const streamId = $stream.val() || null; $.ajax({ url: 'ajax/get_exams.php', method: 'POST', data: { class_id: classId, stream_id: streamId, subject_id: subjectId, academic_level: state.currentAcademicLevel }, dataType: 'json' }).done(function(response) { if (response.success && response.exams && response.exams.length > 0) { let options = '<option value="">Select Exam</option>'; response.exams.forEach(exam => { options += `<option value="${exam.id}">${escapeHtml(exam.examname)}</option>`; }); $exam.html(options).prop('disabled', false); } else { $exam.html('<option value="">No exams available</option>').prop('disabled', false); showToast('warning', 'No Exams', 'No active exams found.'); } }).fail(function() { $exam.html('<option value="">Error loading exams</option>').prop('disabled', false); showToast('error', 'Error', 'Failed to load exams.'); }); }
            
            function fetchStudents() { if (!state.classId || !state.subjectId || !state.examId) return; if (!state.termId) { showToast('warning', 'Term Required', 'Select a term first'); return; } if (!state.year) { showToast('warning', 'Year Required', 'Select a year first'); return; } const termStatus = $term.find('option:selected').data('status'); if (termStatus === 'closed') { showToast('error', 'Term Closed', 'Cannot load scores for closed term'); return; } $tableBody.html('<tr><td colspan="6"><div class="empty-state"><div class="loading-spinner"></div><h3>Loading students...</h3></div></td></tr>'); $.ajax({ url: 'ajax/get_students.php', method: 'POST', data: { class_id: state.classId, stream_id: state.streamId || 0, subject_id: state.subjectId, exam_id: state.examId, term_id: state.termId, year: state.year, academic_level: state.currentAcademicLevel }, dataType: 'json' }).done(function(response) { if (response.success && response.data && response.data.length > 0) { state.students = response.data; renderTable(); $studentCount.text(`(${state.students.length})`); if (PERMISSIONS.canImport) $('#importBtn').prop('disabled', false); if (PERMISSIONS.canExport) $('#downloadBtn, #printBtn').prop('disabled', false); if (response.exam_total && response.exam_total > 0) { state.totalScore = response.exam_total; $totalDisplay.text(state.totalScore); if (PERMISSIONS.canEdit) $totalInput.val(state.totalScore).prop('disabled', false); } else { state.totalScore = 0; $totalDisplay.text('0'); if (PERMISSIONS.canEdit) $totalInput.val('').prop('disabled', false); } showToast('success', 'Loaded', `${state.students.length} students loaded`); } else { $tableBody.html(`<tr><td colspan="6"><div class="empty-state"><i class="fas fa-users-slash"></i><h3>No Students</h3><p>${response.message || 'No students found'}</p></div></td></tr>`); $studentCount.text('(0)'); } }).fail(function() { $tableBody.html('<tr><td colspan="6"><div class="empty-state"><i class="fas fa-plug"></i><h3>Connection Error</h3><p>Failed to load students. Please refresh and try again.</p></div></td></tr>'); showToast('error', 'Error', 'Failed to load students'); }); }
            
            $class.on('change', function() { const val = $(this).val(); if (val) { state.classId = val; state.streamId = null; state.subjectId = null; state.examId = null; $stream.val('').prop('disabled', false); $subject.html('<option value="">Select Class First</option>').prop('disabled', true); $exam.html('<option value="">Select Class & Subject</option>').prop('disabled', true); fetchStreams(val); fetchSubjects(val); const streamId = $stream.val() || null; fetchGradingScale(val, streamId); } else { state.classId = null; $stream.html('<option value="">Select Class First</option>').prop('disabled', true); $subject.html('<option value="">Select Class First</option>').prop('disabled', true); $exam.html('<option value="">Select Class First</option>').prop('disabled', true); } });
            $stream.on('change', function() { state.streamId = $(this).val() || null; if (state.classId) { fetchSubjects(state.classId); fetchGradingScale(state.classId, state.streamId); } });
            $subject.on('change', function() { const val = $(this).val(); if (val) { state.subjectId = val; state.examId = null; fetchExams(state.classId, val); } else { state.subjectId = null; $exam.html('<option value="">Select Subject First</option>').prop('disabled', true); } });
            $exam.on('change', function() { const val = $(this).val(); if (val) { state.examId = val; fetchStudents(); } else { state.examId = null; $tableBody.html('<tr><td colspan="6"><div class="empty-state"><i class="fas fa-users"></i><h3>Select Exam</h3><p>Choose an exam to load students</p></div></td></tr>'); } });
            $term.on('change', function() { state.termId = $(this).val(); const status = $(this).find('option:selected').data('status'); if (status === 'closed') showToast('warning', 'Term Closed', 'This term is closed'); if (state.examId) fetchStudents(); });
            $year.on('change', function() { state.year = $(this).val(); if (state.examId) fetchStudents(); });
            if (PERMISSIONS.canEdit) { $totalInput.on('input', function() { const val = parseFloat($(this).val()) || 0; if (state.totalTimer) clearTimeout(state.totalTimer); state.totalTimer = setTimeout(() => saveExamTotal(val), 1500); }); }
            
            $('#importBtn').on('click', function() { if (state.termId && state.year) $('#importModal').addClass('active'); else showToast('warning', 'Required', 'Select term & year first'); });
            $('#closeImportModal, #cancelImportBtn').on('click', () => $('#importModal').removeClass('active'));
            $('#downloadTemplateBtn').on('click', function() { let csv = "Student ID,Student Name,Score\n"; state.students.forEach(s => { csv += `${s.id},${s.full_name || s.name || 'Unknown'},\n`; }); const link = document.createElement('a'); link.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv); link.download = `score_template_${state.year}.csv`; link.click(); showToast('success', 'Template', 'Template downloaded'); });
            $('#saveImportBtn').on('click', function() { const file = $('#importFile')[0].files[0]; if (!file) { showToast('warning', 'No File', 'Select a file'); return; } const fd = new FormData(); fd.append('file', file); fd.append('class_id', state.classId); fd.append('stream_id', state.streamId || 0); fd.append('subject_id', state.subjectId); fd.append('exam_id', state.examId); fd.append('term_id', state.termId); fd.append('year', state.year); fd.append('academic_level', state.currentAcademicLevel); fd.append('total_score', state.totalScore); $.ajax({ url: 'ajax/import_scores.php', method: 'POST', data: fd, processData: false, contentType: false, dataType: 'json' }).done(function(r) { if (r.success) { fetchStudents(); $('#importModal').removeClass('active'); showToast('success', 'Imported', `${r.imported} scores imported`); } else { showToast('error', 'Failed', r.message); } }).fail(() => showToast('error', 'Error', 'Import failed')); });
            $('#downloadBtn').on('click', function() { let csv = "Rank,Student ID,Name,Admission No,Score,Grade,Rubric,Remarks,Term,Year\n"; state.students.forEach((s, i) => { const score = s.score || ''; const gradeInfo = calculateGrade(score, state.totalScore || 100); csv += `${i+1},${s.id},${s.full_name || s.name},${s.admission_no || s.adm_no},${score},${gradeInfo?.grade || ''},${gradeInfo?.rubric || ''},${gradeInfo?.remarks || ''},${$term.find('option:selected').text()},${state.year}\n`; }); const link = document.createElement('a'); link.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv); link.download = `scores_${state.year}.csv`; link.click(); showToast('success', 'Exported', 'Scores exported'); });
            $('#printBtn').on('click', function() { const w = window.open('', '_blank'); let rows = ''; state.students.forEach((s, i) => { const score = s.score || ''; const gradeInfo = calculateGrade(score, state.totalScore || 100); rows += `<tr><td>${i+1}</td><td>${s.full_name || s.name}<br><small>${s.admission_no || s.adm_no}</small></td><td>${score}</td><td>${gradeInfo?.grade || ''}</td><td>${gradeInfo?.rubric || ''}</td><td>${gradeInfo?.remarks || ''}</td></tr>`; }); w.document.write(`<!DOCTYPE html><html><head><title>Score Sheet</title><style>body{font-family:Arial;margin:20px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ddd;padding:8px;text-align:left}th{background:#1e3a8a;color:white}</style></head><body><h1>Score Sheet</h1><p>${$class.find('option:selected').text()} - ${$subject.find('option:selected').text()} | ${$term.find('option:selected').text()} ${state.year}</p><p>Total Score: ${state.totalScore || 100} points</p><table><thead><tr><th>#</th><th>Student</th><th>Score</th><th>Grade</th><th>Rubric</th><th>Remarks</th></tr></thead><tbody>${rows}</tbody></table><button onclick="window.print()" style="margin-top:20px;padding:10px 20px;background:#1e3a8a;color:white;border:none;border-radius:5px">Print</button></body></html>`); w.document.close(); showToast('info', 'Print', 'Score sheet opened'); });
            
            $('#importModal').on('click', function(e) { if (e.target === this) $(this).removeClass('active'); });
            $(document).on('keydown', function(e) { if (e.key === 'Escape') $('#importModal').removeClass('active'); });
            
            $year.val('<?php echo $current_year; ?>'); state.year = '<?php echo $current_year; ?>';
            $term.find('option').each(function() { if ($(this).data('status') === 'active') { $term.val($(this).val()); state.termId = $(this).val(); return false; } });
            if (!PERMISSIONS.canImport) $('#importBtn').prop('disabled', true);
            if (!PERMISSIONS.canExport) $('#downloadBtn, #printBtn').prop('disabled', true);
        });
    </script>
</body>
</html>