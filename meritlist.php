<?php
session_start();

// Include AJAX handler


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

// --- Data Fetching Functions for Dropdowns Only ---
function getClasses($conn, $school_id, $academic_level) {
    $sql = "SELECT id, class_level as display_name FROM tblclasses WHERE school_id = ? AND academic_level = ? ORDER BY class_level";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $school_id, $academic_level);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getTerms($conn, $school_id) {
    $sql = "SELECT id, term_name, academic_year FROM tblterms WHERE school_id = ? ORDER BY academic_year DESC, term_number";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $school_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getYears($conn, $school_id) {
    $sql = "SELECT DISTINCT academic_year as year FROM tblterms WHERE school_id = ? ORDER BY academic_year DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $school_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getTeacherName($conn, $teacher_id) {
    $sql = "SELECT CONCAT(firstname, ' ', secondname) as teacher_name FROM tblteachers WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['teacher_name'] ?? 'Teacher';
}

// --- Fetch initial data for dropdowns ---
$classes = getClasses($conn, $school_id, $academic_level);
$terms = getTerms($conn, $school_id);
$years = getYears($conn, $school_id);
$teacher_name = getTeacherName($conn, $teacher_id);
$level_names = ['primary' => 'Primary', 'junior_secondary' => 'Junior Sec', 'senior_secondary' => 'Senior Sec', 'college' => 'College'];
$current_level_name = $level_names[$academic_level] ?? 'Primary';

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Merit Lists - EduScore</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="images/logo.png" />
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        :root { --primary: #1e3a8a; --primary-light: #2563eb; --accent: #fbbf24; --success: #10b981; --warning: #f59e0b; --danger: #ef4444; --text-dark: #1f2937; --text-light: #6b7280; --bg-light: #f9fafb; --white: #ffffff; --border: #e5e7eb; --shadow: 0 1px 3px rgba(0,0,0,0.1); --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1); --radius: 12px; }
        body { background: var(--bg-light); color: var(--text-dark); }
        .main-content { margin-left: 280px; min-height: 100vh; padding: 90px 1.5rem 2rem; transition: margin-left 0.3s; }
        @media (max-width: 992px) { .main-content { margin-left: 0; padding: 80px 1rem 1rem; } }
        .loading-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; display: none; justify-content: center; align-items: center; backdrop-filter: blur(3px); }
        .loading-overlay.active { display: flex; }
        .loading-spinner { background: white; padding: 1.5rem 2rem; border-radius: var(--radius); display: flex; flex-direction: column; align-items: center; gap: 1rem; }
        .loading-spinner i { font-size: 2rem; color: var(--primary); }
        .pdf-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 10000; justify-content: center; align-items: center; }
        .pdf-modal.active { display: flex; animation: fadeIn 0.3s ease; }
        .pdf-modal-content { background: var(--white); border-radius: var(--radius); width: 90%; max-width: 1000px; height: 85vh; display: flex; flex-direction: column; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); }
        .pdf-modal-header { padding: 1rem 1.5rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: var(--primary); color: white; border-radius: var(--radius) var(--radius) 0 0; }
        .pdf-modal-header h3 { font-size: 1.1rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; }
        .pdf-modal-close { background: none; border: none; color: white; font-size: 1.25rem; cursor: pointer; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
        .pdf-modal-close:hover { background: rgba(255,255,255,0.2); }
        .pdf-modal-body { flex: 1; overflow: hidden; padding: 0; position: relative; }
        .pdf-iframe { width: 100%; height: 100%; border: none; }
        .pdf-loading { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); display: flex; flex-direction: column; align-items: center; gap: 1rem; color: var(--text-light); }
        .pdf-loading i { font-size: 2rem; color: var(--primary); }
        .pdf-modal-footer { padding: 0.75rem 1.5rem; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 1rem; }
        .btn-download { background: var(--success); color: white; border: none; padding: 0.5rem 1rem; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; }
        .btn-download:hover { background: #059669; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .filter-card { background: var(--white); border-radius: var(--radius); padding: 1rem; margin-bottom: 1.5rem; box-shadow: var(--shadow); }
        .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.75rem; margin-bottom: 1rem; }
        .filter-select { width: 100%; padding: 0.6rem 0.75rem; border: 1px solid var(--border); border-radius: 8px; font-size: 0.85rem; background: var(--white); }
        .rank-buttons { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid var(--border); }
        .rank-btn { padding: 0.4rem 0.8rem; border: 1px solid var(--border); border-radius: 20px; background: var(--white); font-size: 0.75rem; cursor: pointer; transition: all 0.2s; }
        .rank-btn.active { background: var(--primary); color: white; border-color: var(--primary); }
        .action-bar { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1.5rem; justify-content: space-between; align-items: center; }
        .btn-group { display: flex; flex-wrap: wrap; gap: 0.5rem; }
        .btn { padding: 0.5rem 1rem; border-radius: 8px; font-weight: 500; font-size: 0.8rem; cursor: pointer; display: inline-flex; align-items: center; gap: 0.4rem; border: none; transition: all 0.2s; background: var(--bg-light); border: 1px solid var(--border); }
        .btn-primary { background: var(--primary); color: white; border: none; }
        .btn-success { background: var(--success); color: white; border: none; }
        .btn-warning { background: var(--warning); color: white; border: none; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .table-container { background: var(--white); border-radius: var(--radius); overflow-x: auto; box-shadow: var(--shadow); margin-bottom: 1.5rem; }
        .merit-table { width: 100%; border-collapse: collapse; min-width: 800px; font-size: 0.8rem; }
        .merit-table th { background: var(--primary); padding: 0.75rem 0.5rem; color: white; font-weight: 600; font-size: 0.7rem; text-transform: uppercase; white-space: nowrap; }
        .merit-table td { padding: 0.6rem 0.5rem; border-bottom: 1px solid var(--border); text-align: center; vertical-align: middle; }
        .merit-table tr:hover { background: var(--bg-light); }
        .student-info-cell { text-align: left; }
        .student-name { font-weight: 600; font-size: 0.85rem; margin-bottom: 0.2rem; }
        .student-adm { font-size: 0.65rem; color: var(--text-light); }
        .subject-score { display: flex; flex-direction: column; align-items: center; gap: 0.2rem; }
        .score-value { font-weight: 600; }
        .achievement-level { font-size: 0.6rem; padding: 0.15rem 0.3rem; border-radius: 4px; }
        .grade-badge { display: inline-block; padding: 0.2rem 0.5rem; border-radius: 15px; font-size: 0.7rem; font-weight: 600; background: var(--bg-light); border: 1px solid var(--border); }
        .grade-EE { background: rgba(16,185,129,0.15); color: #10b981; border-color: #10b981; }
        .grade-ME { background: rgba(59,130,246,0.15); color: #2563eb; border-color: #2563eb; }
        .grade-AE { background: rgba(245,158,11,0.15); color: #d97706; border-color: #d97706; }
        .grade-BE { background: rgba(239,68,68,0.15); color: #dc2626; border-color: #dc2626; }
        .pagination { padding: 1rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem; border-top: 1px solid var(--border); }
        .pagination-controls { display: flex; gap: 0.3rem; flex-wrap: wrap; }
        .page-btn { min-width: 32px; height: 32px; border: 1px solid var(--border); background: var(--white); border-radius: 6px; cursor: pointer; font-size: 0.8rem; }
        .page-btn.active { background: var(--primary); color: white; border-color: var(--primary); }
        .rows-select { padding: 0.3rem; border: 1px solid var(--border); border-radius: 6px; font-size: 0.8rem; }
        .empty-state { text-align: center; padding: 2rem; color: var(--text-light); }
        .empty-state i { font-size: 3rem; margin-bottom: 0.5rem; opacity: 0.5; }
        .toast-container { position: fixed; top: 90px; right: 1rem; z-index: 3000; max-width: 320px; }
        .toast { background: var(--white); border-radius: 8px; padding: 0.75rem 1rem; margin-bottom: 0.5rem; box-shadow: var(--shadow-md); border-left: 3px solid var(--success); font-size: 0.8rem; animation: slideIn 0.3s ease; display: flex; align-items: center; gap: 0.5rem; }
        .toast.error { border-left-color: var(--danger); }
        @keyframes slideIn { from { opacity: 0; transform: translateX(100%); } to { opacity: 1; transform: translateX(0); } }
        .class-performance-table { width: 100%; border-collapse: collapse; font-size: 0.8rem; }
        .class-performance-table th { background: #f3f4f6; padding: 0.6rem; font-weight: 600; text-align: center; border-bottom: 2px solid var(--border); }
        .class-performance-table td { padding: 0.5rem; text-align: center; border-bottom: 1px solid var(--border); }
        .table-title { font-size: 1rem; font-weight: 600; margin: 1rem 0 0.5rem 0; color: var(--text-dark); }
        @media (max-width: 768px) {
            .filter-grid { grid-template-columns: 1fr; }
            .action-bar { flex-direction: column; align-items: stretch; }
            .btn-group { width: 100%; }
            .btn-group .btn { flex: 1; justify-content: center; }
            .pagination { flex-direction: column; }
        }
    </style>
</head>
<body>

    <?php include 'includes/header.php';   include 'includes/sidebar.php'; ?>

    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i><span>Loading...</span></div>
    </div>

    <div class="pdf-modal" id="pdfModal">
        <div class="pdf-modal-content">
            <div class="pdf-modal-header">
                <h3><i class="fas fa-file-pdf"></i> Merit List PDF</h3>
                <button class="pdf-modal-close" id="closePdfModal">&times;</button>
            </div>
            <div class="pdf-modal-body">
                <div class="pdf-loading" id="pdfLoading">
                    <i class="fas fa-spinner fa-spin"></i>
                    <span>Loading PDF...</span>
                </div>
                <iframe id="pdfIframe" class="pdf-iframe" style="display: none;"></iframe>
            </div>
            <div class="pdf-modal-footer">
                <a href="#" id="downloadPdfLink" class="btn-download" download>
                    <i class="fas fa-download"></i> Download PDF
                </a>
            </div>
        </div>
    </div>

    <div class="main-content">
        <div class="filter-card">
            <div class="filter-grid">
                <select id="selectClass" class="filter-select">
                    <option value="">Select Class</option>
                    <?php foreach($classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['display_name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="selectStream" class="filter-select" disabled>
                    <option value="0">All Streams</option>
                </select>
                <select id="selectExam" class="filter-select" disabled>
                    <option value="">Select Exam</option>
                </select>
                <select id="selectTerm" class="filter-select">
                    <option value="">Select Term</option>
                    <?php foreach($terms as $t): ?>
                        <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['term_name'] . ' ' . $t['academic_year']); ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="selectYear" class="filter-select">
                    <option value="">Select Year</option>
                    <?php foreach($years as $y): ?>
                        <option value="<?php echo $y['year']; ?>"><?php echo $y['year']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="rank-buttons">
                <span style="font-size:0.75rem; color:var(--text-light);"><i class="fas fa-sort"></i> Rank by:</span>
                <button class="rank-btn active" data-rank="total_marks">Total Marks</button>
                <button class="rank-btn" data-rank="total_rubric">Total Rubric</button>
                <button class="rank-btn" data-rank="mean_grade">Mean Grade</button>
            </div>
        </div>

        <div class="action-bar">
            <div class="btn-group">
                <button class="btn btn-success" id="exportPdfBtn" disabled><i class="fas fa-file-pdf"></i> PDF</button>
                <button class="btn btn-success" id="exportCsvBtn" disabled><i class="fas fa-file-csv"></i> CSV</button>
                <button class="btn btn-warning" id="printBtn" disabled><i class="fas fa-print"></i> Print</button>
            </div>
            <select id="rowsPerPage" class="rows-select">
                <option value="10">10 per page</option>
                <option value="20">20</option>
                <option value="50">50</option>
                <option value="100">100</option>
            </select>
        </div>

        <div class="table-container">
            <table class="merit-table" id="meritTable">
                <thead id="tableHeader">
                    <tr>
                        <th>#</th>
                        <th>Adm No</th>
                        <th>Student Name</th>
                        <th>TM</th>
                        <th>TR</th>
                        <th>Grade</th>
                        <th>Rank</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <tr><td colspan="7"><div class="empty-state"><i class="fas fa-trophy"></i><p>Select criteria to load merit list</p></div></td></tr>
                </tbody>
                <tfoot id="tableFooter" style="display:none;"></tfoot>
            </table>
            <div class="pagination" id="pagination" style="display:none">
                <span id="pageInfo">Showing 0 of 0</span>
                <div class="pagination-controls" id="paginationControls"></div>
            </div>
        </div>

        <div class="table-title">Class Performance Summary</div>
        <div class="table-container" id="classPerformanceContainer" style="display:none;">
            <table class="class-performance-table">
                <thead>
                    <tr>
                        <th>Class</th><th>Entry</th><th>EE</th><th>ME</th><th>AE</th><th>AP</th><th>BE</th><th>X</th><th>MEAN</th><th>Rubric</th><th>DEV</th><th>Teacher</th>
                    </tr>
                </thead>
                <tbody id="classPerformanceBody"></tbody>
                <tfoot id="classPerformanceFooter" style="display:none;"></tfoot>
            </table>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
let state = {
    classId: null, streamId: null, examId: null, termId: null, year: null,
    rankBy: 'total_marks', subjects: [], meritList: [], classPerformance: [], currentPdfUrl: null,
    currentPage: 1, rowsPerPage: 10
};

            const showToast = (type, msg) => {
                const toast = $(`<div class="toast ${type}"><i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i><span>${msg}</span></div>`);
                $('#toastContainer').append(toast);
                setTimeout(() => toast.fadeOut(300, () => toast.remove()), 3000);
            };
            const showLoading = (show) => $('#loadingOverlay').toggleClass('active', show);
            const escapeHtml = (text) => { if (!text) return ''; const div = document.createElement('div'); div.textContent = text; return div.innerHTML; };
            const buildFullName = (student) => `${student.first_name || ''} ${student.second_name || ''} ${student.last_name || ''}`.trim() || student.full_name || 'Unknown Student';

            // PDF Modal Functions
            const openPdfModal = (pdfUrl, downloadUrl) => {
                const modal = $('#pdfModal');
                const iframe = $('#pdfIframe');
                const loading = $('#pdfLoading');
                const downloadLink = $('#downloadPdfLink');
                
                iframe.hide();
                loading.show();
                downloadLink.attr('href', downloadUrl || pdfUrl);
                
                iframe.off('load').on('load', function() {
                    loading.hide();
                    iframe.show();
                });
                
                iframe.attr('src', pdfUrl);
                modal.addClass('active');
            };
            
            const closePdfModal = () => {
                $('#pdfModal').removeClass('active');
                const iframe = $('#pdfIframe');
                iframe.attr('src', 'about:blank');
                iframe.hide();
                $('#pdfLoading').show();
                if (state.currentPdfUrl) {
                    URL.revokeObjectURL(state.currentPdfUrl);
                    state.currentPdfUrl = null;
                }
            };
            
            $('#closePdfModal').click(closePdfModal);
            $(document).keydown(function(e) { if (e.key === 'Escape') closePdfModal(); });
            $('#pdfModal').click(function(e) { if (e.target === this) closePdfModal(); });

            const renderTable = () => {
                if (!state.meritList.length) {
                    $('#tableBody').html('<tr><td colspan="100"><div class="empty-state"><i class="fas fa-trophy"></i><p>No data available</p></div></td></tr>');
                    $('#tableFooter').hide();
                    $('#pagination').hide();
                    return;
                }

                const start = (state.currentPage - 1) * state.rowsPerPage;
                const pageData = state.meritList.slice(start, start + state.rowsPerPage);
                const totalPages = Math.ceil(state.meritList.length / state.rowsPerPage);

                let headerHtml = '<tr><th>#</th><th>Adm No</th><th>Student Name</th>';
                state.subjects.forEach(s => headerHtml += `<th title="${escapeHtml(s.subject_name)}">${escapeHtml(s.alias || s.subject_name.substring(0,4))}</th>`);
                headerHtml += '<th>TM</th><th>TR</th><th>Grade</th><th>Rank</th></tr>';
                $('#tableHeader').html(headerHtml);

                let bodyHtml = '';
                pageData.forEach((s, idx) => {
                    const rank = start + idx + 1;
                    const fullName = buildFullName(s);
                    const admissionNo = s.admission_no || '-';
                    bodyHtml += `<tr>
                        <td>${rank}</td>
                        <td class="student-adm">${escapeHtml(admissionNo)}</td>
                        <td class="student-info-cell"><div class="student-name">${escapeHtml(fullName)}</div><div class="student-adm">${escapeHtml(admissionNo)}</div></td>`;
                    state.subjects.forEach(sub => {
                        const scoreInfo = s.subject_scores?.[sub.id] || {};
                        // Display X for null, empty, or 0 scores
                        const score = (scoreInfo.score !== undefined && scoreInfo.score !== null && scoreInfo.score != 0) ? scoreInfo.score : 'X';
                        const grade = scoreInfo.achievement_abbreviation || scoreInfo.grade || 'X';
                        const scoreDisplay = score === 'X' ? 'X' : parseFloat(score).toFixed(2);
                        bodyHtml += `<td><div class="subject-score"><span class="score-value">${scoreDisplay}</span><span class="achievement-level grade-${grade}">${grade}</span></div></td>`;
                    });
                    const totalMarksDisplay = s.total_marks !== undefined ? parseFloat(s.total_marks).toFixed(2) : '0.00';
                    const totalRubricDisplay = s.total_rubric_points !== undefined ? parseFloat(s.total_rubric_points).toFixed(2) : '0.00';
                    const overallGrade = s.overall_grade || s.most_common_grade || 'N/A';
                    bodyHtml += `<td><strong>${totalMarksDisplay}</strong></td>
                                  <td>${totalRubricDisplay}</td>
                                  <td><span class="grade-badge grade-${overallGrade}">${overallGrade}</span></td>
                                  <td><strong>${s.rank || rank}</strong></td>
                                  </tr>`;
                });
                $('#tableBody').html(bodyHtml);

                // Calculate Totals & Averages Row
                const subjectTotals = Array(state.subjects.length).fill(0);
                const subjectValidCounts = Array(state.subjects.length).fill(0);

                state.meritList.forEach(student => {
                    state.subjects.forEach((subject, idx) => {
                        const scoreInfo = student.subject_scores?.[subject.id] || {};
                        if (scoreInfo.score !== undefined && scoreInfo.score !== null && scoreInfo.score != 0) {
                            subjectTotals[idx] += parseFloat(scoreInfo.score);
                            subjectValidCounts[idx]++;
                        }
                    });
                });

                let footerHtml = '<tr style="background:#f9fafb; font-weight:600;"><td colspan="3">Totals</td>';
                state.subjects.forEach((_, idx) => {
                    footerHtml += `<td>${subjectValidCounts[idx] > 0 ? subjectTotals[idx].toFixed(2) : '--'}</td>`;
                });
                const totalMarksSum = state.meritList.reduce((sum, s) => sum + (parseFloat(s.total_marks) || 0), 0);
                const totalRubricSum = state.meritList.reduce((sum, s) => sum + (parseFloat(s.total_rubric_points) || 0), 0);
                footerHtml += `<td>${totalMarksSum.toFixed(2)}</td><td>${totalRubricSum.toFixed(2)}</td><td>--</td><td>--</td></tr>`;
                
                footerHtml += '<tr style="background:#f3f4f6;"><td colspan="3">Averages</td>';
                state.subjects.forEach((_, idx) => {
                    if (subjectValidCounts[idx] > 0) {
                        const avg = subjectTotals[idx] / subjectValidCounts[idx];
                        // Simplified grading for display - actual grade from PHP data
                        let grade = 'BE';
                        if (avg >= 75) grade = 'EE';
                        else if (avg >= 50) grade = 'ME';
                        else if (avg >= 25) grade = 'AE';
                        footerHtml += `<td>${avg.toFixed(2)} ${grade}</td>`;
                    } else {
                        footerHtml += `<td>--</td>`;
                    }
                });
                const avgMarks = state.meritList.length > 0 ? totalMarksSum / state.meritList.length : 0;
                const avgRubric = state.meritList.length > 0 ? totalRubricSum / state.meritList.length : 0;
                let avgGrade = 'BE';
                if (avgMarks >= 75) avgGrade = 'EE';
                else if (avgMarks >= 50) avgGrade = 'ME';
                else if (avgMarks >= 25) avgGrade = 'AE';
                footerHtml += `<td>${avgMarks.toFixed(2)} ${avgGrade}</td><td>${avgRubric.toFixed(2)}</td><td>${avgGrade}</td><td>--</td></tr>`;
                $('#tableFooter').html(footerHtml).show();

                // Pagination
                $('#pagination').show();
                $('#pageInfo').text(`Showing ${start+1}-${Math.min(start+state.rowsPerPage, state.meritList.length)} of ${state.meritList.length}`);
                let pagesHtml = `<button class="page-btn" onclick="window.changePage(${state.currentPage-1})" ${state.currentPage===1 ? 'disabled' : ''}><i class="fas fa-chevron-left"></i></button>`;
                let startPage = Math.max(1, state.currentPage - 2);
                let endPage = Math.min(totalPages, startPage + 4);
                for (let i = startPage; i <= endPage; i++) {
                    pagesHtml += `<button class="page-btn ${i===state.currentPage ? 'active' : ''}" onclick="window.changePage(${i})">${i}</button>`;
                }
                pagesHtml += `<button class="page-btn" onclick="window.changePage(${state.currentPage+1})" ${state.currentPage===totalPages ? 'disabled' : ''}><i class="fas fa-chevron-right"></i></button>`;
                $('#paginationControls').html(pagesHtml);
            };

            window.changePage = (page) => { state.currentPage = page; renderTable(); };

            const fetchStreams = () => {
                if (!state.classId) return;
                $.ajax({ url: 'ajax/fetch_streams_reports.php', method: 'POST', data: { class_id: state.classId, school_id: <?php echo json_encode($school_id); ?> }, dataType: 'json' })
                    .done(r => { let opts = '<option value="0">All Streams</option>'; if(r.success && r.data) r.data.forEach(s => opts += `<option value="${s.id}">${escapeHtml(s.stream_name)}</option>`); $('#selectStream').html(opts).prop('disabled', false); })
                    .fail(() => $('#selectStream').html('<option value="0">All Streams</option>').prop('disabled', false));
            };

            const fetchExams = () => {
                if (!state.classId) return;
                $.ajax({ url: 'ajax/fetch_exams_meritlist.php', method: 'POST', data: { class_id: state.classId, stream_id: state.streamId || 0, school_id: <?php echo json_encode($school_id); ?> }, dataType: 'json' })
                    .done(r => { let opts = '<option value="">Select Exam</option>'; if(r.success && r.data) r.data.forEach(e => opts += `<option value="${e.id}">${escapeHtml(e.examname)}</option>`); $('#selectExam').html(opts).prop('disabled', false); })
                    .fail(() => $('#selectExam').html('<option value="">Error</option>').prop('disabled', false));
            };

const generateMerit = () => {
    if (!state.classId || !state.examId || !state.termId || !state.year) return;
    showLoading(true);
    $.ajax({
        url: 'ajax/fetch_meritlist.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({ 
            class_id: state.classId, 
            stream_id: state.streamId || 0, 
            exam_id: state.examId, 
            term_id: state.termId, 
            year: state.year, 
            rank_by: state.rankBy, 
            school_id: <?php echo json_encode($school_id); ?> 
        }),
        dataType: 'json'
    }).done(r => {
        if (r.success) {
            state.meritList = r.data?.merit_list || [];
            state.subjects = r.data?.subjects || [];
            state.classPerformance = r.data?.class_performance || [];
            state.currentPage = 1;
            renderTable();
            renderClassPerformance(); // Call the new function to render class performance
            $('#exportPdfBtn, #exportCsvBtn, #printBtn').prop('disabled', false);
            if (state.meritList.length > 0) showToast('success', state.meritList.length + ' students loaded');
            else showToast('warning', 'No students found for selected criteria');
        } else showToast('error', r.message || 'Generation failed');
    }).fail(() => showToast('error', 'Network error')).always(() => showLoading(false));
};
const renderClassPerformance = () => {
    if (!state.classPerformance || !state.classPerformance.length) {
        $('#classPerformanceContainer').hide();
        return;
    }
    
    let perfHtml = '';
    let totals = { 
        entry: 0, EE: 0, ME: 0, AE: 0, AP: 0, BE: 0, X: 0, 
        mean_sum: 0, mean_count: 0 
    };
    
    state.classPerformance.forEach(perf => {
        perfHtml += `<tr>
            <td>${escapeHtml(perf.class_name)}</td>
            <td>${perf.entry}</td>
            <td>${perf.EE}</td>
            <td>${perf.ME}</td>
            <td>${perf.AE}</td>
            <td>${perf.AP}</td>
            <td>${perf.BE}</td>
            <td>${perf.X}</td>
            <td>${perf.mean_score.toFixed(2)}</td>
            <td>${perf.rubric}</td>
            <td>${perf.std_dev ? perf.std_dev.toFixed(2) : '--'}</td>
            <td>${escapeHtml(perf.teacher_name)}</td>
        </tr>`;
        
        totals.entry += perf.entry;
        totals.EE += perf.EE;
        totals.ME += perf.ME;
        totals.AE += perf.AE;
        totals.AP += perf.AP;
        totals.BE += perf.BE;
        totals.X += perf.X;
        totals.mean_sum += perf.mean_score;
        totals.mean_count++;
    });
    
    // Calculate total mean
    const totalMean = totals.mean_count > 0 ? totals.mean_sum / totals.mean_count : 0;
    
    // Determine total rubric grade
    let totalRubricGrade = 'BE';
    if (totalMean >= 75) totalRubricGrade = 'EE';
    else if (totalMean >= 50) totalRubricGrade = 'ME';
    else if (totalMean >= 25) totalRubricGrade = 'AE';
    
    const footerHtml = `<tr style="background:#f3f4f6; font-weight:600;">
        <td>Totals</td>
        <td>${totals.entry}</td>
        <td>${totals.EE}</td>
        <td>${totals.ME}</td>
        <td>${totals.AE}</td>
        <td>${totals.AP}</td>
        <td>${totals.BE}</td>
        <td>${totals.X}</td>
        <td>${totalMean.toFixed(2)}</td>
        <td>${totalRubricGrade}</td>
        <td>--</td>
        <td>--</td>
    </tr>`;
    
    $('#classPerformanceBody').html(perfHtml);
    $('#classPerformanceFooter').html(footerHtml).show();
    $('#classPerformanceContainer').show();
};
            const autoGenerateMerit = () => {
                if (state.classId && state.examId && state.termId && state.year) generateMerit();
            };

const generatePDF = () => {
    if (!state.meritList.length) {
        showToast('warning', 'No data to export');
        return;
    }
    
    showLoading(true);
    showToast('info', 'Generating PDF...');
    
    const selections = {
        class_name: $('#selectClass option:selected').text() || 'Class',
        stream_name: $('#selectStream option:selected').text() || '',
        exam_name: $('#selectExam option:selected').text() || 'Exam',
        term_name: $('#selectTerm option:selected').text() || 'Term',
        year: state.year,
        academic_level: '<?php echo $current_level_name; ?>'
    };
    
    // Calculate summary statistics
    const summary = {
        studentCount: state.meritList.length,
        subjectCount: state.subjects.length,
        meanAbbreviation: 'BE',
        avgRubric: (state.meritList.reduce((sum, s) => sum + (parseFloat(s.total_rubric_points) || 0), 0) / state.meritList.length).toFixed(2),
        meanGradeCounts: { EE: 0, ME: 0, AE: 0, AP: 0, BE: 0 }
    };
    
    state.meritList.forEach(s => {
        const grade = s.overall_grade || s.most_common_grade || 'BE';
        if (summary.meanGradeCounts[grade] !== undefined) summary.meanGradeCounts[grade]++;
        else summary.meanGradeCounts.BE++;
    });
    
    const avgMarks = state.meritList.reduce((sum, s) => sum + (parseFloat(s.total_marks) || 0), 0) / state.meritList.length;
    if (avgMarks >= 75) summary.meanAbbreviation = 'EE';
    else if (avgMarks >= 50) summary.meanAbbreviation = 'ME';
    else if (avgMarks >= 25) summary.meanAbbreviation = 'AE';
    
    const pdfData = {
        school_id: <?php echo json_encode($school_id); ?>,
        merit_list: state.meritList,
        subjects: state.subjects,
        selections: selections,
        class_performance: state.classPerformance, // Include class performance data
        summary: summary,
        print_mode: false
    };
    
    $('#exportPdfBtn').html('<i class="fas fa-spinner fa-spin"></i> Generating...');
    $('#exportPdfBtn').prop('disabled', true);
    
    $.ajax({
        url: 'ajax/generate_meritlist_pdf.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(pdfData),
        xhrFields: { responseType: 'blob' },
        success: function(blob) {
            const blobUrl = URL.createObjectURL(blob);
            state.currentPdfUrl = blobUrl;
            openPdfModal(blobUrl, blobUrl);
            showToast('success', 'PDF Ready');
        },
        error: function(xhr) {
            let errorMsg = 'Failed to generate PDF';
            try {
                const response = JSON.parse(xhr.responseText);
                errorMsg = response.message || errorMsg;
            } catch(e) {}
            showToast('error', errorMsg);
        },
        complete: function() {
            $('#exportPdfBtn').html('<i class="fas fa-file-pdf"></i> PDF');
            $('#exportPdfBtn').prop('disabled', false);
            showLoading(false);
        }
    });
};

            $('#selectClass').change(function() {
                state.classId = $(this).val();
                state.streamId = 0;
                if (state.classId) { fetchStreams(); fetchExams(); autoGenerateMerit(); }
                else { $('#selectStream, #selectExam').prop('disabled', true).html('<option value="">Select Class</option>'); state.meritList = []; state.subjects = []; renderTable(); $('#exportPdfBtn, #exportCsvBtn, #printBtn').prop('disabled', true); $('#classPerformanceContainer').hide(); }
                $('#selectExam').val('');
            });
            $('#selectStream').change(function() { state.streamId = $(this).val(); if(state.classId) { fetchExams(); autoGenerateMerit(); } });
            $('#selectExam').change(function() { state.examId = $(this).val(); autoGenerateMerit(); });
            $('#selectTerm').change(function() { state.termId = $(this).val(); autoGenerateMerit(); });
            $('#selectYear').change(function() { state.year = $(this).val(); autoGenerateMerit(); });
            $('.rank-btn').click(function() { $('.rank-btn').removeClass('active'); $(this).addClass('active'); state.rankBy = $(this).data('rank'); if(state.meritList.length) generateMerit(); });
            $('#rowsPerPage').change(function() { state.rowsPerPage = parseInt($(this).val()); state.currentPage = 1; if(state.meritList.length) renderTable(); });
            $('#exportPdfBtn').click(generatePDF);
            $('#printBtn').click(function() { if(state.meritList.length) generatePDF(); else showToast('warning', 'No data to print'); });

            $('#exportCsvBtn').click(function() {
                if(!state.meritList.length) { showToast('warning', 'No data to export'); return; }
                let csv = "Rank,Admission No,Full Name,";
                state.subjects.forEach(s => csv += `"${s.subject_name}",${s.subject_name} Grade,`);
                csv += "Total Marks,Total Rubric,Mean Grade\n";
                state.meritList.forEach((s,i) => {
                    const fullName = buildFullName(s);
                    csv += `${s.rank || i+1},${s.admission_no || ''},"${fullName}",`;
                    state.subjects.forEach(sub => {
                        const scoreInfo = s.subject_scores?.[sub.id] || {};
                        const score = (scoreInfo.score !== undefined && scoreInfo.score !== null && scoreInfo.score != 0) ? scoreInfo.score : 'X';
                        const grade = scoreInfo.achievement_abbreviation || scoreInfo.grade || 'X';
                        csv += `${score},${grade},`;
                    });
                    csv += `${s.total_marks || 0},${(s.total_rubric_points || 0).toFixed(2)},${s.overall_grade || s.most_common_grade || 'N/A'}\n`;
                });
                const link = document.createElement('a'); link.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv); link.download = `merit_list_${state.year}.csv`; link.click(); showToast('success', 'CSV exported');
            });

            // Initial stats fetch
            $.ajax({ url: 'ajax/get_meritlist_page_data.php', method: 'POST', data: { academic_level: '<?php echo $academic_level; ?>', teacher_id: <?php echo json_encode($teacher_id); ?>, school_id: <?php echo json_encode($school_id); ?> }, dataType: 'json' })
                .done(r => { if(r.success) { $('#totalClasses, #totalSubjects, #totalExams, #totalStudents').each((i,el) => $(el).text(r.stats[el.id] || 0)); } });
        });
    </script>
</body>

</body>
</html>
