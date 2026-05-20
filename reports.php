<?php
session_start();

// Include AJAX handler


error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['school_id'])) {
    header('Location: login.php');
    exit();
}

$teacher_id = $_SESSION['teacher_id'];
$school_id = $_SESSION['school_id'];
$academic_level = $_SESSION['academic_level'] ?? 'primary';

require_once 'includes/config.php';
require_once 'includes/session_timeout.php';


$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

function getClasses($conn, $school_id, $academic_level) {
    $sql = "SELECT id, class_level as display_name FROM tblclasses WHERE school_id = ? AND academic_level = ? ORDER BY class_level";
    $stmt = $conn->prepare($sql); $stmt->bind_param("is", $school_id, $academic_level); $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
function getTerms($conn, $school_id) {
    $sql = "SELECT id, term_name, academic_year FROM tblterms WHERE school_id = ? ORDER BY academic_year DESC, term_number";
    $stmt = $conn->prepare($sql); $stmt->bind_param("i", $school_id); $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
function getYears($conn, $school_id) {
    $sql = "SELECT DISTINCT academic_year as year FROM tblterms WHERE school_id = ? ORDER BY academic_year DESC";
    $stmt = $conn->prepare($sql); $stmt->bind_param("i", $school_id); $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$classes = getClasses($conn, $school_id, $academic_level);
$terms = getTerms($conn, $school_id);
$years = getYears($conn, $school_id);
$level_names = ['primary' => 'Primary', 'junior_secondary' => 'Junior Sec', 'senior_secondary' => 'Senior Sec', 'college' => 'College'];
$current_level_name = $level_names[$academic_level] ?? 'Primary';
$conn->close();

// Read template from URL parameters (passed from templates.php)
$url_template = $_GET['template'] ?? 'enhanced';
$url_stream_rank = ($_GET['stream_rank'] ?? '1') === '1';
$url_class_rank = ($_GET['class_rank'] ?? '1') === '1';
$url_include_summary = ($_GET['include_summary'] ?? '1') === '1';
$url_mode = $_GET['mode'] ?? '';
$url_class_id = $_GET['class_id'] ?? '';
$url_stream_id = $_GET['stream_id'] ?? '';
$url_term_id = $_GET['term_id'] ?? '';
$url_exam_id = $_GET['exam_id'] ?? '';

$template_names = ['classic' => 'Classic', 'enhanced' => 'Enhanced', 'enhanced_norank' => 'Enhanced (No Ranking)'];
$active_template_name = $template_names[$url_template] ?? 'Enhanced';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Report Cards - EduScore</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="images/logo.png" />
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        :root { --primary: #1e3a8a; --primary-light: #2563eb; --accent: #fbbf24; --success: #10b981; --warning: #f59e0b; --danger: #ef4444; --text-dark: #1f2937; --text-light: #6b7280; --bg-light: #f9fafb; --white: #ffffff; --border: #e5e7eb; --shadow: 0 1px 3px rgba(0,0,0,0.1); --radius: 12px; }
        body { background: var(--bg-light); color: var(--text-dark); }
        .main-content { margin-left: 280px; min-height: 100vh; padding: 90px 1.5rem 2rem; transition: margin-left 0.3s; }
        @media (max-width: 992px) { .main-content { margin-left: 0; padding: 80px 1rem 1rem; } }
        .page-header { background: var(--white); border-radius: var(--radius); padding: 1.25rem 1.5rem; margin-bottom: 1.5rem; box-shadow: var(--shadow); border-left: 4px solid var(--primary); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem; }
        .reports-page-title { font-size: 1.25rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem; }
        .reports-page-title i { color: var(--primary); }
        .template-badge-header { background: var(--primary); color: white; padding: 0.3rem 0.75rem; border-radius: 20px; font-size: 0.7rem; font-weight: 600; display: flex; align-items: center; gap: 0.4rem; }
        .loading-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; display: none; justify-content: center; align-items: center; backdrop-filter: blur(3px); }
        .loading-overlay.active { display: flex; }
        .loading-spinner { background: white; padding: 1.5rem 2rem; border-radius: var(--radius); display: flex; flex-direction: column; align-items: center; gap: 1rem; }
        .loading-spinner i { font-size: 2rem; color: var(--primary); }
        .pdf-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 10000; justify-content: center; align-items: center; }
        .pdf-modal.active { display: flex; animation: fadeIn 0.3s ease; }
        .pdf-modal-content { background: var(--white); border-radius: var(--radius); width: 90%; max-width: 1000px; height: 85vh; display: flex; flex-direction: column; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); }
        .pdf-modal-header { padding: 1rem 1.5rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: var(--primary); color: white; border-radius: var(--radius) var(--radius) 0 0; }
        .pdf-modal-close { background: none; border: none; color: white; font-size: 1.25rem; cursor: pointer; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
        .pdf-modal-body { flex: 1; overflow: hidden; position: relative; }
        .pdf-iframe { width: 100%; height: 100%; border: none; }
        .pdf-loading { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); display: flex; flex-direction: column; align-items: center; gap: 1rem; }
        .pdf-modal-footer { padding: 0.75rem 1.5rem; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 1rem; }
        .filter-card { background: var(--white); border-radius: var(--radius); padding: 1rem; margin-bottom: 1.5rem; box-shadow: var(--shadow); }
        .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 0.75rem; margin-bottom: 0.75rem; }
        .filter-select { width: 100%; padding: 0.6rem 0.75rem; border: 1px solid var(--border); border-radius: 8px; font-size: 0.85rem; background: var(--white); }
        .filter-select:disabled { background: #f3f4f6; opacity: 0.7; }
        .mode-toggle { display: flex; gap: 0.5rem; align-items: center; padding-top: 0.75rem; border-top: 1px solid var(--border); flex-wrap: wrap; }
        .mode-label { font-size: 0.75rem; color: var(--text-light); display: flex; align-items: center; gap: 0.25rem; }
        .mode-btn { padding: 0.4rem 0.8rem; border: 1px solid var(--border); border-radius: 20px; background: var(--white); font-size: 0.75rem; cursor: pointer; transition: all 0.2s; }
        .mode-btn.active { background: var(--primary); color: white; border-color: var(--primary); }
        .student-selection { margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border); }
        .student-search-box { position: relative; margin-bottom: 0.75rem; }
        .student-search-box input { width: 100%; padding: 0.6rem 0.75rem 0.6rem 2.25rem; border: 1px solid var(--border); border-radius: 8px; font-size: 0.85rem; }
        .student-search-box i { position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: var(--text-light); }
        .student-list-container { max-height: 250px; overflow-y: auto; border: 1px solid var(--border); border-radius: 8px; background: var(--white); }
        .student-item { display: flex; align-items: center; padding: 0.6rem 1rem; border-bottom: 1px solid var(--border); cursor: pointer; transition: all 0.15s; }
        .student-item:hover { background: #f0f4ff; }
        .student-item.selected { background: #dbeafe; border-left: 3px solid var(--primary); }
        .student-checkbox { margin-right: 0.75rem; width: 16px; height: 16px; accent-color: var(--primary); }
        .student-avatar { width: 32px; height: 32px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.7rem; margin-right: 0.75rem; flex-shrink: 0; }
        .student-info { flex: 1; min-width: 0; }
        .student-name { font-weight: 600; font-size: 0.8rem; }
        .student-adm { font-size: 0.65rem; color: var(--text-light); }
        .selection-footer { display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; font-size: 0.75rem; }
        .selection-actions { display: flex; gap: 0.5rem; }
        .action-bar { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1.5rem; justify-content: space-between; align-items: center; }
        .btn-group { display: flex; flex-wrap: wrap; gap: 0.5rem; }
        .btn { padding: 0.5rem 1rem; border-radius: 8px; font-weight: 500; font-size: 0.8rem; cursor: pointer; display: inline-flex; align-items: center; gap: 0.4rem; border: none; transition: all 0.2s; background: var(--bg-light); border: 1px solid var(--border); }
        .btn:hover:not(:disabled) { transform: translateY(-1px); }
        .btn-primary { background: var(--primary); color: white; border: none; }
        .btn-success { background: var(--success); color: white; border: none; }
        .btn-warning { background: var(--warning); color: white; border: none; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .table-container { background: var(--white); border-radius: var(--radius); overflow-x: auto; box-shadow: var(--shadow); margin-bottom: 1.5rem; }
        .table-header { padding: 1rem 1.5rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .table-title { font-size: 0.9rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; }
        .reports-table { width: 100%; border-collapse: collapse; min-width: 800px; font-size: 0.8rem; }
        .reports-table th { background: var(--primary); padding: 0.75rem 0.5rem; color: white; font-weight: 600; font-size: 0.7rem; text-transform: uppercase; }
        .reports-table td { padding: 0.6rem 0.5rem; border-bottom: 1px solid var(--border); }
        .reports-table tr:hover { background: var(--bg-light); }
        .status-badge { display: inline-block; padding: 0.2rem 0.5rem; border-radius: 15px; font-size: 0.7rem; font-weight: 600; }
        .status-generated { background: rgba(16,185,129,0.15); color: #10b981; border: 1px solid #10b981; }
        .status-processing { background: rgba(245,158,11,0.15); color: #d97706; border: 1px solid #d97706; }
        .action-btns { display: flex; gap: 0.3rem; }
        .action-btn { width: 28px; height: 28px; border: none; border-radius: 6px; background: transparent; color: var(--text-light); cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }
        .action-btn:hover { background: #dbeafe; color: var(--primary); }
        .action-btn.delete:hover { background: #fee2e2; color: var(--danger); }
        .progress-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; justify-content: center; align-items: center; }
        .progress-modal.active { display: flex; animation: fadeIn 0.3s ease; }
        .progress-content { background: var(--white); border-radius: var(--radius); width: 90%; max-width: 550px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); }
        .progress-header { padding: 1rem 1.5rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: var(--primary); color: white; border-radius: var(--radius) var(--radius) 0 0; }
        .progress-body { padding: 1.5rem; }
        .progress-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem; margin: 1rem 0; }
        .confirm-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10001; justify-content: center; align-items: center; }
        .confirm-modal.active { display: flex; animation: fadeIn 0.2s ease; }
        .confirm-content { background: var(--white); border-radius: var(--radius); width: 90%; max-width: 420px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); overflow: hidden; }
        .confirm-header { padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 0.75rem; background: var(--danger); color: white; }
        .confirm-body { padding: 1.5rem; }
        .confirm-info { background: var(--bg-light); border-radius: 8px; padding: 0.75rem 1rem; font-size: 0.8rem; border-left: 3px solid var(--danger); }
        .confirm-footer { padding: 1rem 1.5rem; border-top: 1px solid var(--border); display: flex; gap: 0.75rem; justify-content: flex-end; }
        .btn-cancel { padding: 0.5rem 1.25rem; border-radius: 8px; font-size: 0.8rem; cursor: pointer; background: var(--bg-light); border: 1px solid var(--border); }
        .btn-delete-confirm { padding: 0.5rem 1.25rem; border-radius: 8px; font-size: 0.8rem; cursor: pointer; background: var(--danger); color: white; border: none; display: flex; align-items: center; gap: 0.4rem; }
        .btn-delete-confirm:disabled { opacity: 0.6; }
        .stat-box { background: var(--bg-light); padding: 0.75rem; border-radius: 8px; text-align: center; border: 1px solid var(--border); }
        .stat-value { font-size: 1.1rem; font-weight: 700; color: var(--primary); }
        .stat-label { font-size: 0.65rem; color: var(--text-light); text-transform: uppercase; }
        .progress-bar-container { margin: 1rem 0; }
        .progress-bar { height: 8px; background: var(--border); border-radius: 4px; overflow: hidden; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, var(--warning), var(--success)); border-radius: 4px; transition: width 0.3s ease; width: 0%; }
        .toast-container { position: fixed; top: 90px; right: 1rem; z-index: 3000; max-width: 320px; }
        .toast { background: var(--white); border-radius: 8px; padding: 0.75rem 1rem; margin-bottom: 0.5rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); border-left: 3px solid var(--success); font-size: 0.8rem; animation: slideIn 0.3s ease; display: flex; align-items: center; gap: 0.5rem; }
        .toast.error { border-left-color: var(--danger); }
        .toast.warning { border-left-color: var(--warning); }
        .empty-state { text-align: center; padding: 3rem 2rem; color: var(--text-light); }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideIn { from { opacity: 0; transform: translateX(100%); } to { opacity: 1; transform: translateX(0); } }
        @media (max-width: 768px) { .filter-grid { grid-template-columns: 1fr; } .action-bar { flex-direction: column; } .btn-group { width: 100%; } .btn-group .btn { flex: 1; justify-content: center; } }
    </style>
</head>
<body>

    <?php include 'includes/header.php';   include 'includes/sidebar.php'; ?>

    <div class="loading-overlay" id="loadingOverlay"><div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i><span>Loading...</span></div></div>

    <div class="pdf-modal" id="pdfModal">
        <div class="pdf-modal-content">
            <div class="pdf-modal-header"><h3><i class="fas fa-file-pdf"></i> <span id="pdfTitle">Report Card</span></h3><button class="pdf-modal-close" id="closePdfModal">&times;</button></div>
            <div class="pdf-modal-body"><div class="pdf-loading" id="pdfLoading"><i class="fas fa-spinner fa-spin"></i><span>Loading PDF...</span></div><iframe id="pdfIframe" class="pdf-iframe" style="display:none;"></iframe></div>
            <div class="pdf-modal-footer"><a href="#" id="downloadPdfLink" class="btn btn-success" download><i class="fas fa-download"></i> Download</a><button class="btn btn-primary" onclick="closePdfModal()">Close</button></div>
        </div>
    </div>

    <div class="progress-modal" id="progressModal">
        <div class="progress-content">
            <div class="progress-header"><h3><i class="fas fa-cogs"></i> Generating Reports</h3><button style="background:none;border:none;color:white;cursor:pointer;font-size:1.2rem;" onclick="closeProgressModal()">&times;</button></div>
            <div class="progress-body">
                <p id="progressStatus" style="font-weight:600;">Preparing...</p>
                <div class="progress-bar-container"><div class="progress-bar"><div class="progress-fill" id="progressFill"></div></div></div>
                <p id="progressDetails" style="font-size:0.8rem;color:var(--text-light);">0/0 students</p>
                <div class="progress-stats"><div class="stat-box"><div class="stat-value" id="totalStudents">0</div><div class="stat-label">Total</div></div><div class="stat-box"><div class="stat-value" id="completedStudents">0</div><div class="stat-label">Done</div></div><div class="stat-box"><div class="stat-value" id="totalPages">0</div><div class="stat-label">Pages</div></div></div>
            </div>
        </div>
    </div>

    <div class="main-content">
        <div class="page-header">
            <h1 class="reports-page-title"><i class="fas fa-file-pdf"></i> Report Cards</h1>
            <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
                <span class="template-badge-header"><i class="fas fa-palette"></i> <?php echo htmlspecialchars($active_template_name); ?></span>
                <a href="templates.php" class="btn btn-outline" style="font-size:0.7rem;padding:0.3rem 0.6rem;"><i class="fas fa-arrow-left"></i> Change Template</a>
                <span style="font-size:0.8rem;color:var(--text-light);"><i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($current_level_name); ?></span>
            </div>
        </div>

        <div class="filter-card">
            <div class="filter-grid">
                <select id="selectClass" class="filter-select"><option value="">Select Class</option><?php foreach($classes as $c): ?><option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['display_name']); ?></option><?php endforeach; ?></select>
                <select id="selectStream" class="filter-select" disabled><option value="0">All Streams</option></select>
                <select id="selectExam" class="filter-select" disabled><option value="">Select Exam</option></select>
                <select id="selectTerm" class="filter-select"><option value="">Select Term</option><?php foreach($terms as $t): ?><option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['term_name'].' '.$t['academic_year']); ?></option><?php endforeach; ?></select>
                <select id="selectYear" class="filter-select"><option value="">Select Year</option><?php foreach($years as $y): ?><option value="<?php echo $y['year']; ?>"><?php echo $y['year']; ?></option><?php endforeach; ?></select>
            </div>
            <div class="mode-toggle">
                <span class="mode-label"><i class="fas fa-cog"></i> Mode:</span>
                <button class="mode-btn active" data-mode="single" id="singleModeBtn"><i class="fas fa-user"></i> Single Student</button>
                <button class="mode-btn" data-mode="batch" id="batchModeBtn"><i class="fas fa-users"></i> Batch (Merged)</button>
            </div>
            <div class="student-selection" id="studentSelection">
                <div class="student-search-box"><i class="fas fa-search"></i><input type="text" id="studentSearch" placeholder="Search students..."></div>
                <div class="student-list-container" id="studentListContainer"><div class="empty-state" style="padding:1.5rem;"><i class="fas fa-user-graduate"></i><p>Select class to load students</p></div></div>
                <div class="selection-footer"><span id="selectionCount">0 selected</span><div class="selection-actions"><button class="btn btn-outline" id="selectAllBtn" style="font-size:0.7rem;padding:0.3rem 0.6rem;">Select All</button><button class="btn btn-outline" id="deselectAllBtn" style="font-size:0.7rem;padding:0.3rem 0.6rem;">Clear</button></div></div>
            </div>
        </div>

        <div class="action-bar">
            <div class="btn-group">
                <button class="btn btn-primary" id="generateBtn" disabled><i class="fas fa-cogs"></i> Generate Reports</button>
                <button class="btn btn-success" id="sendToParentsBtn" disabled><i class="fas fa-paper-plane"></i> Generate & Send</button>
                <button class="btn btn-warning" id="refreshBtn"><i class="fas fa-sync-alt"></i> Refresh</button>
            </div>
        </div>

        <div class="table-container">
            <div class="table-header"><div class="table-title"><i class="fas fa-list-ol"></i> Generated Reports <span id="reportCount">(0)</span></div></div>
            <table class="reports-table"><thead><tr><th>Report</th><th>Class</th><th>Exam/Term</th><th>Status</th><th>Students</th><th>Mean</th><th>Date</th><th>Actions</th></tr></thead>
            <tbody id="reportsTableBody"><tr><td colspan="8"><div class="empty-state"><i class="fas fa-file-pdf"></i><p>No reports yet</p></div></td></tr></tbody></table>
        </div>
    </div>

    <div class="confirm-modal" id="confirmDeleteModal">
        <div class="confirm-content">
            <div class="confirm-header"><i class="fas fa-exclamation-triangle"></i><h3>Confirm Deletion</h3></div>
            <div class="confirm-body"><p>Delete this <strong id="confirmDeleteType">report</strong>?</p><div class="confirm-info">This cannot be undone.</div></div>
            <div class="confirm-footer"><button class="btn-cancel" onclick="closeConfirmDeleteModal()">Cancel</button><button class="btn-delete-confirm" id="confirmDeleteBtn"><i class="fas fa-trash"></i> Delete</button></div>
        </div>
    </div>
    <div class="toast-container" id="toastContainer"></div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    $(document).ready(function() {
        // URL parameters from templates.php
        const urlParams = new URLSearchParams(window.location.search);
        const academicLevelDisplay = {
            'primary': 'Primary School',
            'junior_secondary': 'Junior Secondary',
            'senior_secondary': 'Senior Secondary',
            'college': 'College'
        };

        // State management with template from URL > localStorage > default
        const state = {
            mode: urlParams.get('mode') || 'single',
            classId: urlParams.get('class_id') || null,
            streamId: parseInt(urlParams.get('stream_id')) || 0,
            examId: urlParams.get('exam_id') || null,
            termId: urlParams.get('term_id') || null,
            year: null,
            students: [],
            selectedStudents: new Set(),
            reports: [],
            currentPdfUrl: null,
            isGenerating: false,
            generatedReports: [],
            academicLevel: '<?php echo $academic_level; ?>',
            // Template - URL first, then localStorage, then default
            template: urlParams.get('template') || localStorage.getItem('reportTemplate') || 'enhanced',
            streamRank: urlParams.has('stream_rank') ? urlParams.get('stream_rank') !== '0' : (localStorage.getItem('streamRank') || '1') === '1',
            classRank: urlParams.has('class_rank') ? urlParams.get('class_rank') !== '0' : (localStorage.getItem('classRank') || '1') === '1',
            includeSummary: urlParams.has('include_summary') ? urlParams.get('include_summary') !== '0' : (localStorage.getItem('includeSummary') || '1') === '1'
        };

        // Update template badge text
        var templateNames = { classic: 'Classic', enhanced: 'Enhanced', enhanced_norank: 'Enhanced (No Ranking)' };
        var activeName = templateNames[state.template] || 'Enhanced';
        $('.template-badge-header').html('<i class="fas fa-palette"></i> ' + activeName);

        // Set initial mode button
        if (state.mode === 'batch') {
            $('.mode-btn').removeClass('active');
            $('#batchModeBtn').addClass('active');
        }

        // Auto-populate filters from URL
        if (state.classId) {
            $('#selectClass').val(state.classId);
            setTimeout(function() { $('#selectClass').trigger('change'); }, 100);
        }
        if (state.termId) $('#selectTerm').val(state.termId);
        if (state.examId) {
            window._pendingExamId = state.examId;
        }
        if (state.streamId) {
            window._pendingStreamId = state.streamId;
        }

        // Academic level change - reload page
        window.addEventListener('academicLevelChanged', function(event) {
            var newLevel = event.detail && event.detail.academic_level;
            if (newLevel && newLevel !== state.academicLevel) {
                $.ajax({
                    url: 'api/update_academic_level_ajax.php',
                    method: 'POST',
                    data: { academic_level: newLevel },
                    dataType: 'json'
                }).always(function() {
                    window.location.reload();
                });
            }
        });

        // Toast notification
        var showToast = function(type, msg) {
            var icons = { success: 'fa-check-circle', error: 'fa-exclamation-circle', warning: 'fa-exclamation-triangle', info: 'fa-info-circle' };
            var toast = $('<div class="toast ' + type + '"><i class="fas ' + (icons[type] || icons.info) + '"></i><span>' + msg + '</span></div>');
            $('#toastContainer').append(toast);
            setTimeout(function() { toast.fadeOut(300, function() { $(this).remove(); }); }, 3000);
        };

        var showLoading = function(show) { $('#loadingOverlay').toggleClass('active', show); };

        var escapeHtml = function(text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        };

        // PDF Modal
        var openPdfModal = function(pdfUrl, title) {
            title = title || 'Report Card';
            $('#pdfTitle').text(title);
            $('#pdfIframe').hide();
            $('#pdfLoading').show();
            $('#downloadPdfLink').attr('href', pdfUrl);
            $('#pdfIframe').off('load').on('load', function() {
                $('#pdfLoading').hide();
                $('#pdfIframe').show();
            });
            $('#pdfIframe').attr('src', pdfUrl);
            $('#pdfModal').addClass('active');
            state.currentPdfUrl = pdfUrl;
        };

        var closePdfModal = function() {
            $('#pdfModal').removeClass('active');
            $('#pdfIframe').attr('src', 'about:blank').hide();
            $('#pdfLoading').show();
            state.currentPdfUrl = null;
        };

        $('#closePdfModal').click(closePdfModal);
        $(document).keydown(function(e) { if (e.key === 'Escape') closePdfModal(); });
        $('#pdfModal').click(function(e) { if (e.target === this) closePdfModal(); });
        window.closePdfModal = closePdfModal;

        // Progress Modal
        var closeProgressModal = function() {
            if (state.isGenerating) return;
            $('#progressModal').removeClass('active');
        };
        window.closeProgressModal = closeProgressModal;
        $('#progressModal').click(function(e) { if (e.target === this) closeProgressModal(); });

        // Mode switching
        $('.mode-btn').click(function() {
            var mode = $(this).data('mode');
            if (state.mode === mode) return;
            state.mode = mode;
            $('.mode-btn').removeClass('active');
            $(this).addClass('active');
            state.selectedStudents.clear();
            state.generatedReports = [];
            updateStudentListUI();
            checkFormValidity();
            updateGenerateButtonText();
        });

        var updateGenerateButtonText = function() {
            if (state.mode === 'single') {
                $('#generateBtn').html('<i class="fas fa-user"></i> Generate Individual Reports');
                $('#sendToParentsBtn').html('<i class="fas fa-paper-plane"></i> Generate & Send');
            } else {
                $('#generateBtn').html('<i class="fas fa-users"></i> Generate Merged Report');
                $('#sendToParentsBtn').html('<i class="fas fa-paper-plane"></i> Generate Merged & Send');
            }
        };

        // Fetch students
        var fetchStudents = function(searchTerm) {
            searchTerm = searchTerm || '';
            if (!state.classId) return;
            $.ajax({
                url: 'ajax/fetch_students_by_class.php',
                method: 'POST',
                data: {
                    class_id: state.classId,
                    stream_id: state.streamId,
                    school_id: <?php echo $school_id; ?>,
                    academic_level: state.academicLevel,
                    search: searchTerm
                },
                dataType: 'json'
            })
            .done(function(response) {
                if (response.success && response.students) {
                    state.students = response.students;
                    if (state.mode === 'batch') {
                        state.selectedStudents = new Set(state.students.map(function(s) { return s.id; }));
                    }
                    renderStudentList();
                } else {
                    state.students = [];
                    $('#studentListContainer').html('<div class="empty-state" style="padding:1.5rem;"><i class="fas fa-user-slash"></i><p>No students found</p></div>');
                }
                updateSelectionCount();
            })
            .fail(function() { showToast('error', 'Failed to load students'); });
        };

        var renderStudentList = function() {
            if (state.mode === 'batch') {
                $('#studentListContainer').html(
                    '<div style="padding:1.5rem;text-align:center;">' +
                    '<i class="fas fa-users" style="font-size:2rem;color:var(--primary);"></i>' +
                    '<p style="font-weight:600;margin-top:0.5rem;">All ' + state.students.length + ' Students</p>' +
                    '<p style="font-size:0.75rem;color:var(--text-light);">Will be processed as merged report</p>' +
                    '</div>'
                );
                $('#selectAllBtn, #deselectAllBtn').hide();
                return;
            }

            var html = '';
            state.students.forEach(function(student) {
                var fullName = student.full_name || (student.FirstName || '') + ' ' + (student.LastName || '');
                fullName = fullName.trim();
                var initials = fullName.split(' ').map(function(n) { return n.charAt(0); }).slice(0, 2).join('').toUpperCase();
                var isSelected = state.selectedStudents.has(student.id);
                html += '<div class="student-item ' + (isSelected ? 'selected' : '') + '" onclick="toggleStudent(' + student.id + ')">' +
                    '<input type="checkbox" class="student-checkbox" ' + (isSelected ? 'checked' : '') + ' onclick="event.stopPropagation();toggleStudent(' + student.id + ')">' +
                    '<div class="student-avatar">' + initials + '</div>' +
                    '<div class="student-info"><div class="student-name">' + escapeHtml(fullName) + '</div>' +
                    '<div class="student-adm">' + escapeHtml(student.admission_no || 'N/A') + '</div></div></div>';
            });
            $('#studentListContainer').html(html || '<div class="empty-state"><i class="fas fa-user-slash"></i><p>No students</p></div>');
            $('#selectAllBtn, #deselectAllBtn').show();
        };

        window.toggleStudent = function(studentId) {
            if (state.mode === 'batch') return;
            if (state.selectedStudents.has(studentId)) {
                state.selectedStudents.delete(studentId);
            } else {
                state.selectedStudents.add(studentId);
            }
            renderStudentList();
            updateSelectionCount();
            checkFormValidity();
        };

        var updateSelectionCount = function() {
            var count = state.mode === 'batch' ? state.students.length : state.selectedStudents.size;
            $('#selectionCount').text(count + ' student' + (count !== 1 ? 's' : '') + ' selected');
        };

        var updateStudentListUI = function() {
            if (state.classId) {
                state.selectedStudents.clear();
                fetchStudents();
            }
            checkFormValidity();
        };

        // Fetch streams
        var fetchStreams = function() {
            if (!state.classId) return;
            $.ajax({
                url: 'ajax/fetch_streams_reports.php',
                method: 'POST',
                data: { class_id: state.classId, school_id: <?php echo $school_id; ?> },
                dataType: 'json'
            })
            .done(function(r) {
                var opts = '<option value="0">All Streams</option>';
                if (r.success && r.data) {
                    r.data.forEach(function(s) {
                        opts += '<option value="' + s.id + '">' + escapeHtml(s.stream_name) + '</option>';
                    });
                }
                $('#selectStream').html(opts).prop('disabled', false);
                if (window._pendingStreamId) {
                    $('#selectStream').val(window._pendingStreamId);
                    state.streamId = window._pendingStreamId;
                    delete window._pendingStreamId;
                }
            });
        };

        // Fetch exams
        var fetchExams = function() {
            if (!state.classId) return;
            $.ajax({
                url: 'ajax/fetch_exams_meritlist.php',
                method: 'POST',
                data: { class_id: state.classId, stream_id: state.streamId, school_id: <?php echo $school_id; ?> },
                dataType: 'json'
            })
            .done(function(r) {
                var opts = '<option value="">Select Exam</option>';
                if (r.success && r.data) {
                    r.data.forEach(function(e) {
                        opts += '<option value="' + e.id + '">' + escapeHtml(e.examname) + '</option>';
                    });
                }
                $('#selectExam').html(opts).prop('disabled', false);
                if (window._pendingExamId) {
                    $('#selectExam').val(window._pendingExamId);
                    state.examId = window._pendingExamId;
                    delete window._pendingExamId;
                    checkFormValidity();
                    loadReports();
                }
            });
        };

        // Load reports
        var loadReports = function() {
            if (!state.classId || !state.examId || !state.termId || !state.year) return;
            $.when(
                $.ajax({
                    url: 'ajax/fetch_single_reports.php',
                    method: 'POST',
                    data: {
                        class_id: state.classId, stream_id: state.streamId,
                        exam_id: state.examId, term_id: state.termId,
                        academic_year: state.year, school_id: <?php echo $school_id; ?>
                    },
                    dataType: 'json'
                }),
                $.ajax({
                    url: 'ajax/fetch_merged_reports.php',
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({
                        class_id: state.classId, stream_id: state.streamId,
                        exam_id: state.examId, term_id: state.termId,
                        academic_year: state.year, school_id: <?php echo $school_id; ?>,
                        academic_level: state.academicLevel
                    }),
                    dataType: 'json'
                })
            ).done(function(sr, mr) {
                var singleReports = (sr && sr[0] && sr[0].success) ? (sr[0].data || []) : [];
                var mergedReports = (mr && mr[0] && mr[0].success) ? (mr[0].data || []) : [];
                state.reports = singleReports.concat(mergedReports).sort(function(a, b) {
                    return new Date(b.created_at || 0) - new Date(a.created_at || 0);
                });
                renderReportsTable();
            });
        };

        var renderReportsTable = function() {
            if (!state.reports.length) {
                $('#reportsTableBody').html('<tr><td colspan="8"><div class="empty-state"><i class="fas fa-file-pdf"></i><p>No reports yet</p></div></td></tr>');
                $('#reportCount').text('(0)');
                return;
            }
            $('#reportCount').text('(' + state.reports.length + ')');
            var html = '';
            state.reports.forEach(function(r) {
                var date = r.created_at ? new Date(r.created_at).toLocaleDateString() : 'N/A';
                var isBatch = r.report_type === 'merged';
                var badge = isBatch ?
                    '<span class="status-badge status-processing" style="margin-left:0.5rem;">Batch</span>' :
                    '<span class="status-badge status-generated" style="margin-left:0.5rem;">Individual</span>';
                var studentCount = isBatch ? (r.students_processed || r.total_students || 'All') : '1';
                var displayName = isBatch ?
                    (r.class_name || 'Merged') + (r.stream_name && r.stream_name !== 'All Streams' ? ' - ' + r.stream_name : '') + ' (Batch)' :
                    (r.student_name || r.class_name || 'N/A');
                var meanScore = 'N/A';
                if (r.mean_score != null) meanScore = parseFloat(r.mean_score).toFixed(2) + '%';
                else if (r.mean_percentage != null) meanScore = parseFloat(r.mean_percentage).toFixed(2) + '%';
                else if (r.class_mean != null) meanScore = parseFloat(r.class_mean).toFixed(2) + '%';
                html += '<tr>' +
                    '<td><div style="display:flex;align-items:center;gap:0.5rem;"><i class="fas ' + (isBatch ? 'fa-users' : 'fa-user') + '" style="color:var(--primary);"></i><div><strong>' + escapeHtml(displayName) + '</strong>' + badge + '</div></div></td>' +
                    '<td>' + escapeHtml(r.class_name || 'N/A') + '</td>' +
                    '<td><strong>' + escapeHtml(r.exam_name || 'N/A') + '</strong><br><small>' + escapeHtml(r.term_name || 'N/A') + ' ' + (r.academic_year || '') + '</small></td>' +
                    '<td><span class="status-badge status-generated">' + (r.status || 'Generated') + '</span></td>' +
                    '<td><strong>' + studentCount + '</strong></td>' +
                    '<td><strong>' + meanScore + '</strong></td>' +
                    '<td>' + date + '</td>' +
                    '<td><div class="action-btns">' +
                    '<button class="action-btn" onclick="viewReport(\'' + (r.pdf_url || '') + '\',\'' + escapeHtml(displayName).replace(/'/g, "\\'") + '\')"><i class="fas fa-eye"></i></button>' +
                    '<button class="action-btn" onclick="downloadReport(\'' + (r.pdf_url || '') + '\')"><i class="fas fa-download"></i></button>' +
                    '<button class="action-btn delete" onclick="deleteReport(' + r.id + ',\'' + (isBatch ? 'merged' : 'single') + '\')"><i class="fas fa-trash"></i></button>' +
                    '</div></td></tr>';
            });
            $('#reportsTableBody').html(html);
        };

        // Delete with confirmation modal
        var pendingDelete = null;
        window.deleteReport = function(reportId, type) {
            pendingDelete = { reportId: reportId, type: type };
            $('#confirmDeleteType').text(type === 'merged' ? 'batch report' : 'individual report');
            $('#confirmDeleteModal').addClass('active');
            $('body').css('overflow', 'hidden');
        };
        window.closeConfirmDeleteModal = function() {
            $('#confirmDeleteModal').removeClass('active');
            $('body').css('overflow', '');
            pendingDelete = null;
        };
        var executeDelete = function() {
            if (!pendingDelete) return;
            var reportId = pendingDelete.reportId;
            var type = pendingDelete.type;
            var btn = $('#confirmDeleteBtn');
            var originalHtml = btn.html();
            btn.html('<i class="fas fa-spinner fa-spin"></i>').prop('disabled', true);
            $.ajax({
                url: type === 'single' ? 'ajax/delete_single_report.php' : 'ajax/delete_merged_report.php',
                method: 'POST',
                data: { report_id: reportId, school_id: <?php echo $school_id; ?> },
                dataType: 'json'
            })
            .done(function(r) {
                if (r.success) { showToast('success', 'Deleted'); closeConfirmDeleteModal(); loadReports(); }
                else { showToast('error', r.message || 'Failed'); closeConfirmDeleteModal(); }
            })
            .fail(function() { showToast('error', 'Network error'); closeConfirmDeleteModal(); })
            .always(function() { btn.html(originalHtml).prop('disabled', false); });
        };
        $('#confirmDeleteBtn').click(executeDelete);
        $('#confirmDeleteModal').click(function(e) { if (e.target === this) closeConfirmDeleteModal(); });
        $(document).keydown(function(e) { if (e.key === 'Escape' && $('#confirmDeleteModal').hasClass('active')) closeConfirmDeleteModal(); });

        // View/Download
        window.viewReport = function(url, title) {
            if (url) openPdfModal(url, title || 'Report Card');
            else showToast('warning', 'PDF not available');
        };
        window.downloadReport = function(url) {
            if (url) {
                var a = document.createElement('a');
                a.href = url;
                a.download = url.split('/').pop();
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                showToast('success', 'Download started');
            }
        };

        // Generate reports with template parameters
        $('#generateBtn').click(function() { startGeneration(false); });
        $('#sendToParentsBtn').click(function() { startGeneration(true); });

        var startGeneration = function(sendToParents) {
            var selectedIds = state.mode === 'batch' ?
                state.students.map(function(s) { return s.id; }) :
                Array.from(state.selectedStudents);

            if (!selectedIds.length) { showToast('warning', 'No students selected'); return; }
            if (state.isGenerating) { showToast('warning', 'Already generating'); return; }

            state.isGenerating = true;
            state.generatedReports = [];
            $('#generateBtn, #sendToParentsBtn').prop('disabled', true);
            $('#progressModal').addClass('active');
            updateProgress(0, 'Starting...', 0, selectedIds.length);

            $.ajax({
                url: state.mode === 'single' ? 'ajax/generate_single_report.php' : 'ajax/generate_merged_reports.php',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    class_id: state.classId,
                    stream_id: state.streamId,
                    exam_id: state.examId,
                    term_id: state.termId,
                    academic_year: state.year,
                    school_id: <?php echo $school_id; ?>,
                    student_ids: selectedIds,
                    send_to_parents: sendToParents,
                    // Template configuration from state
                    template: state.template,
                    stream_rank: state.streamRank,
                    class_rank: state.classRank,
                    include_summary: state.includeSummary
                }),
                dataType: 'json',
                timeout: 300000
            })
            .done(function(response) {
                if (response.success) {
                    var processedCount = response.students_processed || 0;
                    var totalPages = response.total_pages || 0;
                    updateProgress(100, 'Complete!', processedCount, processedCount);
                    $('#totalPages').text(totalPages);

                    var message = '';
                    var pdfUrl = null;
                    var pdfTitle = 'Report Card';

                    if (state.mode === 'single') {
                        message = 'Generated ' + processedCount + ' individual report card' + (processedCount !== 1 ? 's' : '');
                        state.generatedReports = response.reports || [];
                        if (response.reports && response.reports[0]) {
                            pdfUrl = response.reports[0].pdf_url;
                            pdfTitle = response.reports[0].student_name || 'Report';
                        }
                    } else {
                        message = 'Generated merged report with ' + totalPages + ' pages for ' + processedCount + ' students';
                        if (response.merged_pdf_url) {
                            pdfUrl = response.merged_pdf_url;
                            pdfTitle = 'Merged Report - ' + processedCount + ' students';
                            state.generatedReports = [{ pdf_url: response.merged_pdf_url, student_name: pdfTitle }];
                        }
                    }
                    if (sendToParents && response.sent_to_parents) message += ' (' + response.sent_to_parents + ' sent)';

                    showToast('success', message);
                    setTimeout(function() {
                        closeProgressModal();
                        if (pdfUrl) setTimeout(function() { openPdfModal(pdfUrl, pdfTitle); }, 300);
                    }, 1500);
                    loadReports();
                } else {
                    throw new Error(response.message || 'Generation failed');
                }
            })
            .fail(function(jqXHR) {
                var errorMsg = 'Network error';
                if (jqXHR.responseJSON && jqXHR.responseJSON.message) errorMsg = jqXHR.responseJSON.message;
                updateProgress(0, 'Error: ' + errorMsg);
                showToast('error', errorMsg);
            })
            .always(function() {
                state.isGenerating = false;
                $('#generateBtn, #sendToParentsBtn').prop('disabled', false);
                checkFormValidity();
            });
        };

        var updateProgress = function(percentage, status, completed, total) {
            $('#progressFill').css('width', percentage + '%');
            $('#progressStatus').text(status);
            $('#progressDetails').text(completed + '/' + total + ' students');
            $('#completedStudents').text(completed);
            $('#totalStudents').text(total);
        };

        var checkFormValidity = function() {
            var hasStudents = state.mode === 'batch' ? state.students.length > 0 : state.selectedStudents.size > 0;
            var isValid = state.classId && state.examId && state.termId && state.year && hasStudents;
            $('#generateBtn, #sendToParentsBtn').prop('disabled', !isValid);
        };

        // Event handlers
        $('#selectClass').change(function() {
            state.classId = $(this).val();
            if (state.classId) {
                fetchStreams();
                fetchExams();
                fetchStudents();
                state.selectedStudents.clear();
            } else {
                $('#selectStream, #selectExam').prop('disabled', true).html('<option value="">Select class first</option>');
                state.students = [];
            }
            updateSelectionCount();
            checkFormValidity();
            loadReports();
        });

        $('#selectStream').change(function() {
            state.streamId = parseInt($(this).val()) || 0;
            if (state.classId) {
                fetchExams();
                fetchStudents();
                state.selectedStudents.clear();
            }
            updateSelectionCount();
            checkFormValidity();
        });

        $('#selectExam').change(function() { state.examId = $(this).val(); checkFormValidity(); loadReports(); });
        $('#selectTerm').change(function() { state.termId = $(this).val(); checkFormValidity(); loadReports(); });
        $('#selectYear').change(function() { state.year = $(this).val(); checkFormValidity(); loadReports(); });

        $('#selectAllBtn').click(function() {
            state.students.forEach(function(s) { state.selectedStudents.add(s.id); });
            renderStudentList();
            updateSelectionCount();
            checkFormValidity();
        });

        $('#deselectAllBtn').click(function() {
            state.selectedStudents.clear();
            renderStudentList();
            updateSelectionCount();
            checkFormValidity();
        });

        $('#refreshBtn').click(function() { loadReports(); showToast('info', 'Refreshed'); });
        $('#studentSearch').on('input', function() { fetchStudents($(this).val().trim()); });

        // Initialize
        updateGenerateButtonText();
        checkFormValidity();
    });
</script>
</body>

</body>
</html>
