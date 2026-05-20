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

// Fetch real school info
$school_name = 'School';
$school_address = '';
$school_phone = '';
$school_email = '';
$school_motto = '';
$school_stmt = $conn->prepare("SELECT school_name, school_address, school_phone, school_email, school_motto FROM tblschoolinfo WHERE id = ?");
$school_stmt->bind_param("i", $school_id);
$school_stmt->execute();
$school_result = $school_stmt->get_result();
if ($row = $school_result->fetch_assoc()) {
    $school_name = $row['school_name'] ?? 'School';
    $school_address = $row['school_address'] ?? '';
    $school_phone = $row['school_phone'] ?? '';
    $school_email = $row['school_email'] ?? '';
    $school_motto = $row['school_motto'] ?? 'Strive for Excellence';
}
$school_stmt->close();

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

$classes = getClasses($conn, $school_id, $academic_level);
$terms = getTerms($conn, $school_id);
$level_names = ['primary' => 'Primary', 'junior_secondary' => 'Junior Sec', 'senior_secondary' => 'Senior Sec', 'college' => 'College'];
$current_level_name = $level_names[$academic_level] ?? 'Primary';
$conn->close();

// Build school details string
$school_details = '';
if ($school_address) $school_details .= 'P.O. Box ' . htmlspecialchars($school_address);
if ($school_phone) $school_details .= ($school_details ? ' | ' : '') . 'Tel: ' . htmlspecialchars($school_phone);
if ($school_email) $school_details .= ($school_details ? ' | ' : '') . 'Email: ' . htmlspecialchars($school_email);
if (empty($school_details)) $school_details = 'P.O. Box 123 | Tel: 0700-000000 | Email: info@school.ac.ke';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Templates - <?php echo htmlspecialchars($school_name); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="images/logo.png" />
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        :root {
            --primary: #1e3a8a; --primary-light: #2563eb; --accent: #fbbf24;
            --success: #10b981; --warning: #f59e0b; --danger: #ef4444;
            --text-dark: #1f2937; --text-light: #6b7280; --bg-light: #f9fafb;
            --white: #ffffff; --border: #e5e7eb;
            --shadow: 0 1px 3px rgba(0,0,0,0.1); --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1);
            --radius: 14px;
        }
        body { background: var(--bg-light); color: var(--text-dark); }
        .main-content { margin-left: 280px; min-height: 100vh; padding: 90px 2rem 2rem; transition: margin-left 0.3s; }
        @media (max-width: 992px) { .main-content { margin-left: 0; padding: 80px 1rem 1rem; } }
        .page-header { background: var(--white); border-radius: var(--radius); padding: 1.75rem 2rem; margin-bottom: 2rem; box-shadow: var(--shadow); border-left: 4px solid var(--primary); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; }
        .templates-page-title { font-size: 1.5rem; font-weight: 700; display: flex; align-items: center; gap: 0.75rem; }
        .templates-page-title i { color: var(--primary); }
        .section-title { font-size: 1.1rem; font-weight: 600; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem; }
        .section-title i { color: var(--primary); }

        .templates-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 1.75rem; margin-bottom: 2rem; }
        .template-card { background: var(--white); border-radius: var(--radius); overflow: hidden; box-shadow: var(--shadow); border: 3px solid transparent; cursor: pointer; transition: all 0.35s ease; position: relative; }
        .template-card:hover { transform: translateY(-6px); box-shadow: 0 15px 35px rgba(0,0,0,0.12); }
        .template-card.selected { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(30,58,138,0.12); }
        .template-card.selected::after { content: '\f00c'; font-family: 'Font Awesome 6 Free'; font-weight: 900; position: absolute; top: 14px; right: 14px; background: var(--primary); color: white; width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 15px; z-index: 2; }

        .template-preview { height: 420px; overflow: hidden; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: center; background: #e8e8e8; padding: 20px; }
        .template-preview .report-wrapper { width: 210px; /* A4 ratio */ background: white; box-shadow: 0 4px 20px rgba(0,0,0,0.15); position: relative; }
        
        .mini-report { width: 100%; background: white; font-family: Arial, sans-serif; font-size: 4.8px; line-height: 1.3; color: #000; position: relative; padding: 6px 8px; }
        .mini-report .double-border { border: 1.5px solid #000; padding: 4px; position: relative; }
        .mini-report .double-border::after { content: ''; position: absolute; top: 2px; left: 2px; right: 2px; bottom: 2px; border: 0.5px solid #000; pointer-events: none; }
        .mr-school { text-align: center; font-weight: 700; font-size: 7px; text-transform: uppercase; letter-spacing: 0.3px; }
        .mr-details { text-align: center; font-size: 4.5px; color: #222; }
        .mr-line { border: none; border-top: 0.5px solid #000; margin: 3px 0; }
        .mr-title { text-align: center; font-weight: 700; font-size: 6px; margin: 2px 0; }
        .mr-subtitle { text-align: center; font-size: 4.5px; }
        .mr-section-header { background: #000; color: #fff; text-align: center; font-size: 5px; font-weight: 600; padding: 2px 0; margin: 4px 0 2px 0; letter-spacing: 0.5px; }
        .mr-info-row { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0; margin: 2px 0; }
        .mr-info-cell { border: 0.5px solid #ccc; padding: 2px 3px; font-size: 4.5px; }
        .mr-info-cell b { font-size: 4px; text-transform: uppercase; }
        .mr-table { width: 100%; border-collapse: collapse; margin: 3px 0; }
        .mr-table th { background: #e8e8e8; border: 0.5px solid #999; padding: 1.5px 2px; font-size: 4.5px; text-align: center; font-weight: 600; }
        .mr-table td { border: 0.5px solid #ccc; padding: 1.5px 2px; text-align: center; font-size: 4.5px; }
        .mr-table td:first-child { text-align: left; }
        .mr-summary-row { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 2px; margin: 2px 0; }
        .mr-summary-row.two-col { grid-template-columns: 1fr 1fr; }
        .mr-sum-box { border: 0.5px solid #000; text-align: center; padding: 2px; }
        .mr-sum-box .label { font-size: 3.8px; text-transform: uppercase; color: #333; }
        .mr-sum-box .value { font-weight: 700; font-size: 6px; }
        .mr-chart { border: 0.5px solid #000; padding: 3px; margin: 3px 0; display: flex; align-items: flex-end; gap: 4px; justify-content: space-around; height: 22px; }
        .mr-bar { background: #000; width: 10px; position: relative; }
        .mr-bar .val { position: absolute; top: -8px; left: 50%; transform: translateX(-50%); font-size: 4px; font-weight: 600; }
        .mr-bar .lbl { position: absolute; bottom: -8px; left: 50%; transform: translateX(-50%); font-size: 3.5px; white-space: nowrap; }
        .mr-remarks { display: grid; grid-template-columns: 1fr 1fr; gap: 2px; margin: 3px 0; }
        .mr-remark-box { border: 0.5px solid #000; }
        .mr-remark-box .title { background: #000; color: #fff; text-align: center; font-size: 4.5px; font-weight: 600; padding: 1.5px; }
        .mr-remark-box .body { height: 16px; }
        .mr-footer-form { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 2px; font-size: 4.2px; margin-top: 3px; }
        .mr-footer-form .underline { border-bottom: 0.5px solid #000; display: inline-block; width: 35px; }
        .mr-footer-line { display: flex; justify-content: space-between; font-size: 4px; font-style: italic; margin-top: 4px; padding-top: 2px; border-top: 0.5px solid #ccc; }

        .template-info { padding: 1rem 1.25rem; }
        .template-name { font-size: 1rem; font-weight: 700; margin-bottom: 0.3rem; }
        .template-desc { font-size: 0.78rem; color: var(--text-light); margin-bottom: 0.6rem; line-height: 1.4; }
        .template-badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 20px; font-size: 0.65rem; font-weight: 600; margin-right: 0.3rem; }
        .badge-recommended { background: rgba(16,185,129,0.12); color: #059669; }
        .badge-simple { background: rgba(100,116,139,0.12); color: #475569; }
        .badge-featured { background: rgba(37,99,235,0.12); color: #2563eb; }

        .preview-modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 10000; justify-content: center; align-items: center; padding: 1rem; }
        .preview-modal-overlay.active { display: flex; animation: fadeIn 0.25s ease; }
        .preview-modal { background: white; border-radius: var(--radius); width: 95%; max-width: 550px; max-height: 90vh; overflow-y: auto; box-shadow: 0 25px 50px rgba(0,0,0,0.2); }
        .preview-modal-header { padding: 1rem 1.25rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: var(--primary); color: white; border-radius: var(--radius) var(--radius) 0 0; }
        .preview-modal-body { padding: 1.5rem; display: flex; justify-content: center; background: #e5e5e5; }
        .preview-modal-body .report-wrapper { width: 380px; background: white; box-shadow: 0 4px 20px rgba(0,0,0,0.15); }
        .preview-modal-body .mini-report { font-size: 8px; padding: 10px 14px; }
        .preview-modal-body .mr-school { font-size: 11px; }
        .preview-modal-body .mr-details { font-size: 7px; }
        .preview-modal-body .mr-title { font-size: 9px; }
        .preview-modal-body .mr-section-header { font-size: 7px; }
        .preview-modal-body .mr-table th, .preview-modal-body .mr-table td { font-size: 7px; padding: 3px 4px; }
        .preview-modal-body .mr-sum-box .value { font-size: 9px; }
        .preview-modal-body .mr-remark-box .body { height: 28px; }
        .preview-modal-body .mr-footer-form { font-size: 7px; }
        .preview-modal-body .mr-chart { height: 35px; }
        .preview-modal-body .mr-bar { width: 18px; }
        .preview-modal-close { background: none; border: none; color: white; font-size: 1.2rem; cursor: pointer; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
        .preview-modal-close:hover { background: rgba(255,255,255,0.2); }

        .config-panel { background: var(--white); border-radius: var(--radius); padding: 1.5rem; box-shadow: var(--shadow); margin-bottom: 1.5rem; }
        .config-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1rem; }
        .config-item label { font-size: 0.85rem; font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 0.5rem; }
        .config-item input[type="checkbox"] { width: 18px; height: 18px; accent-color: var(--primary); }
        .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 0.75rem; }
        .filter-select { width: 100%; padding: 0.6rem 0.8rem; border: 1px solid var(--border); border-radius: 8px; font-size: 0.85rem; background: var(--white); }
        .filter-select:disabled { background: #f3f4f6; opacity: 0.7; }
        .generate-section { margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--border); }
        .generate-buttons { display: flex; flex-wrap: wrap; gap: 0.75rem; }
        .btn { padding: 0.6rem 1.2rem; border-radius: 8px; font-weight: 500; font-size: 0.85rem; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; border: none; transition: all 0.2s; }
        .btn:hover:not(:disabled) { transform: translateY(-2px); }
        .btn-primary { background: var(--primary); color: white; }
        .btn-outline { background: var(--white); border: 1px solid var(--border); color: var(--text-dark); }
        .btn-sm { padding: 0.3rem 0.7rem; font-size: 0.7rem; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }

        .toast-container { position: fixed; top: 90px; right: 1rem; z-index: 3000; max-width: 320px; }
        .toast { background: var(--white); border-radius: 8px; padding: 0.75rem 1rem; margin-bottom: 0.5rem; box-shadow: var(--shadow-md); border-left: 3px solid var(--success); font-size: 0.8rem; animation: slideIn 0.3s ease; display: flex; align-items: center; gap: 0.5rem; }
        .toast.error { border-left-color: var(--danger); }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideIn { from { opacity: 0; transform: translateX(100%); } to { opacity: 1; transform: translateX(0); } }
        @media (max-width: 768px) { .main-content { padding: 80px 1rem 1rem; } .templates-grid { grid-template-columns: 1fr; } .template-preview { height: 350px; } }
    </style>
</head>
<body>

    <?php include 'includes/header.php';   include 'includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="page-header">
            <h1 class="templates-page-title"><i class="fas fa-palette"></i> Report Templates</h1>
            <span style="font-size:0.85rem;color:var(--text-light);"><i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($current_level_name); ?></span>
        </div>
        <h3 class="section-title"><i class="fas fa-th-large"></i> Select Report Template</h3>
        <div class="templates-grid">

            <!-- CLASSIC -->
            <div class="template-card" data-template="classic" onclick="selectTemplate('classic')">
                <div class="template-preview">
                    <div class="report-wrapper">
                        <div class="mini-report">
                            <div class="double-border">
                                <div class="mr-school"><?php echo htmlspecialchars(strtoupper($school_name)); ?></div>
                                <div class="mr-details"><?php echo $school_details; ?></div>
                                <hr class="mr-line"><div class="mr-title">STUDENT REPORT FORM</div>
                                <div class="mr-subtitle">Term One - Academic Year 2026</div><hr class="mr-line">
                                <div style="font-size:4.8px;margin:3px 0;line-height:1.6;">
                                    <b>ADM NO:</b> STU-2024-001 &nbsp; <b>NAME:</b> John Doe Smith<br>
                                    <b>CLASS:</b> Grade 7 - A &nbsp; <b>UPI NO:</b> 12345678<br>
                                    <b>EXAM:</b> Opener &nbsp; <b>TERM:</b> Term 1 &nbsp; <b>YEAR:</b> 2026
                                </div>
                                <table class="mr-table"><tr><th>SUBJECT</th><th>MARKS</th><th>GRADE</th></tr>
                                    <tr><td>Mathematics</td><td>78/100</td><td>B+</td></tr>
                                    <tr><td>English</td><td>85/100</td><td>A-</td></tr>
                                    <tr><td>Science</td><td>72/100</td><td>B</td></tr>
                                    <tr><td>Social Studies</td><td>80/100</td><td>B+</td></tr></table>
                                <div class="mr-summary-row">
                                    <div class="mr-sum-box"><div class="label">TOTAL</div><div class="value">315/400</div></div>
                                    <div class="mr-sum-box"><div class="label">MEAN</div><div class="value">78.8%</div></div>
                                    <div class="mr-sum-box"><div class="label">GRADE</div><div class="value">B+</div></div></div>
                                <div style="text-align:center;font-size:5px;margin:2px 0;"><b>Class Position:</b> 5 out of 45</div>
                                <div class="mr-footer-line"><span>Class Teacher</span><span>Principal</span></div>
                                <div class="mr-footer-line" style="border:none;"><span>Date: ________</span><span>Date: ________</span></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="template-info">
                    <div class="template-name">Classic Template</div>
                    <div class="template-desc">Clean black-and-white design with single-column student info, subject scores table with grades, summary stats, and class rank.</div>
                    <span class="template-badge badge-simple">Simple</span>
                    <button class="btn btn-outline btn-sm" onclick="event.stopPropagation(); previewTemplate('classic')" style="margin-top:0.4rem;"><i class="fas fa-search-plus"></i> Enlarge</button>
                </div>
            </div>

            <!-- ENHANCED -->
            <div class="template-card selected" data-template="enhanced" onclick="selectTemplate('enhanced')">
                <div class="template-preview">
                    <div class="report-wrapper">
                        <div class="mini-report">
                            <div class="double-border">
                                <div class="mr-school"><?php echo htmlspecialchars(strtoupper($school_name)); ?></div>
                                <div class="mr-details"><?php echo $school_details; ?></div>
                                <hr class="mr-line"><div class="mr-title">STUDENT REPORT FORM</div>
                                <div class="mr-subtitle">Term One</div><hr class="mr-line">
                                <div class="mr-section-header">STUDENT DETAILS &nbsp;|&nbsp; CLASS INFORMATION &nbsp;|&nbsp; EXAMINATION DETAILS</div>
                                <div class="mr-info-row">
                                    <div class="mr-info-cell"><b>ADM NO:</b> STU-001<br><b>NAME:</b> John Doe<br><b>UPI NO:</b> 12345678</div>
                                    <div class="mr-info-cell"><b>CLASS:</b> Grade 7<br><b>STREAM:</b> A<br><b>YEAR:</b> 2026</div>
                                    <div class="mr-info-cell"><b>ENTRY:</b> 45<br><b>TERM:</b> Term 1<br><b>EXAM:</b> Opener</div></div>
                                <div class="mr-section-header">ACADEMIC PERFORMANCE</div>
                                <table class="mr-table"><tr><th>SUBJECT</th><th>MARKS</th><th>GRADE</th><th>RUB</th><th>RNK</th><th>REMARK</th><th>TEACHER</th></tr>
                                    <tr><td>Math</td><td>78/100</td><td>B+</td><td>3</td><td>3</td><td>Good</td><td>Mr. K</td></tr>
                                    <tr><td>Eng</td><td>85/100</td><td>A-</td><td>4</td><td>1</td><td>Exc</td><td>Ms. M</td></tr>
                                    <tr><td>Sci</td><td>72/100</td><td>B</td><td>3</td><td>5</td><td>Good</td><td>Mr. O</td></tr>
                                    <tr><td>SST</td><td>80/100</td><td>B+</td><td>3</td><td>2</td><td>Good</td><td>Ms. A</td></tr></table>
                                <div class="mr-section-header">PERFORMANCE SUMMARY</div>
                                <div class="mr-summary-row">
                                    <div class="mr-sum-box"><div class="label">TOTAL MARKS</div><div class="value">315/400</div></div>
                                    <div class="mr-sum-box"><div class="label">OVERALL GRADE</div><div class="value">B+</div></div>
                                    <div class="mr-sum-box"><div class="label">CLASS POSITION</div><div class="value">5/45</div></div></div>
                                <div class="mr-summary-row two-col">
                                    <div class="mr-sum-box"><div class="label">MEAN RUBRIC</div><div class="value">3.25</div></div>
                                    <div class="mr-sum-box"><div class="label">MEAN SCORE</div><div class="value">78.8%</div></div></div>
                                <div class="mr-section-header">PERFORMANCE TREND</div>
                                <div class="mr-chart">
                                    <div class="mr-bar" style="height:8px;"><div class="val">70%</div><div class="lbl">Entry</div></div>
                                    <div class="mr-bar" style="height:12px;"><div class="val">78%</div><div class="lbl">T1</div></div>
                                    <div class="mr-bar" style="height:10px;"><div class="val">75%</div><div class="lbl">T2</div></div>
                                    <div class="mr-bar" style="height:15px;"><div class="val">82%</div><div class="lbl">T3</div></div></div>
                                <div class="mr-remarks">
                                    <div class="mr-remark-box"><div class="title">CLASS TEACHER'S REMARKS</div><div class="body"></div></div>
                                    <div class="mr-remark-box"><div class="title">PRINCIPAL'S REMARKS</div><div class="body"></div></div></div>
                                <div class="mr-footer-form">
                                    <div>Teacher: <span class="underline"></span><br>Sign: <span class="underline"></span><br>Date: <span class="underline"></span></div>
                                    <div>Closing: <span class="underline"></span><br>Re-open: <span class="underline"></span><br>Balance: <span class="underline"></span></div>
                                    <div>Next Fees: <span class="underline"></span><br>Total: <span class="underline"></span><br>Parent: <span class="underline"></span></div></div>
                                <div class="mr-footer-line"><span><?php echo htmlspecialchars($school_motto); ?></span><span>Page 1</span></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="template-info">
                    <div class="template-name">Enhanced Template</div>
                    <div class="template-desc">Professional design with 3-column student info, 7-column subject table, summary, trend chart, rubrics & ranking. Most popular.</div>
                    <span class="template-badge badge-recommended">Recommended</span>
                    <span class="template-badge badge-featured">Full Features</span>
                    <button class="btn btn-outline btn-sm" onclick="event.stopPropagation(); previewTemplate('enhanced')" style="margin-top:0.4rem;"><i class="fas fa-search-plus"></i> Enlarge</button>
                </div>
            </div>

            <!-- ENHANCED NO RANKING -->
            <div class="template-card" data-template="enhanced_norank" onclick="selectTemplate('enhanced_norank')">
                <div class="template-preview">
                    <div class="report-wrapper">
                        <div class="mini-report">
                            <div class="double-border">
                                <div class="mr-school"><?php echo htmlspecialchars(strtoupper($school_name)); ?></div>
                                <div class="mr-details"><?php echo $school_details; ?></div>
                                <hr class="mr-line"><div class="mr-title">STUDENT REPORT FORM</div>
                                <div class="mr-subtitle">Term One</div><hr class="mr-line">
                                <div class="mr-section-header">STUDENT DETAILS &nbsp;|&nbsp; CLASS INFORMATION &nbsp;|&nbsp; EXAMINATION DETAILS</div>
                                <div class="mr-info-row">
                                    <div class="mr-info-cell"><b>ADM NO:</b> STU-001<br><b>NAME:</b> John Doe<br><b>UPI NO:</b> 12345678</div>
                                    <div class="mr-info-cell"><b>CLASS:</b> Grade 7<br><b>STREAM:</b> A<br><b>YEAR:</b> 2026</div>
                                    <div class="mr-info-cell"><b>ENTRY:</b> 45<br><b>TERM:</b> Term 1<br><b>EXAM:</b> Opener</div></div>
                                <div class="mr-section-header">ACADEMIC PERFORMANCE</div>
                                <table class="mr-table"><tr><th>SUBJECT</th><th>MARKS</th><th>GRADE</th><th>RUB</th><th>REMARK</th><th>TEACHER</th></tr>
                                    <tr><td>Math</td><td>78/100</td><td>B+</td><td>3</td><td>Good</td><td>Mr. K</td></tr>
                                    <tr><td>Eng</td><td>85/100</td><td>A-</td><td>4</td><td>Exc</td><td>Ms. M</td></tr>
                                    <tr><td>Sci</td><td>72/100</td><td>B</td><td>3</td><td>Good</td><td>Mr. O</td></tr>
                                    <tr><td>SST</td><td>80/100</td><td>B+</td><td>3</td><td>Good</td><td>Ms. A</td></tr></table>
                                <div class="mr-section-header">PERFORMANCE SUMMARY</div>
                                <div class="mr-summary-row">
                                    <div class="mr-sum-box"><div class="label">TOTAL MARKS</div><div class="value">315/400</div></div>
                                    <div class="mr-sum-box"><div class="label">OVERALL GRADE</div><div class="value">B+</div></div>
                                    <div class="mr-sum-box"><div class="label">POSITION</div><div class="value">N/A</div></div></div>
                                <div class="mr-summary-row two-col">
                                    <div class="mr-sum-box"><div class="label">MEAN RUBRIC</div><div class="value">3.25</div></div>
                                    <div class="mr-sum-box"><div class="label">MEAN SCORE</div><div class="value">78.8%</div></div></div>
                                <div class="mr-section-header">PERFORMANCE TREND</div>
                                <div class="mr-chart">
                                    <div class="mr-bar" style="height:8px;"><div class="val">70%</div><div class="lbl">Entry</div></div>
                                    <div class="mr-bar" style="height:12px;"><div class="val">78%</div><div class="lbl">T1</div></div>
                                    <div class="mr-bar" style="height:10px;"><div class="val">75%</div><div class="lbl">T2</div></div>
                                    <div class="mr-bar" style="height:15px;"><div class="val">82%</div><div class="lbl">T3</div></div></div>
                                <div class="mr-remarks">
                                    <div class="mr-remark-box"><div class="title">CLASS TEACHER'S REMARKS</div><div class="body"></div></div>
                                    <div class="mr-remark-box"><div class="title">PRINCIPAL'S REMARKS</div><div class="body"></div></div></div>
                                <div class="mr-footer-form">
                                    <div>Teacher: <span class="underline"></span><br>Sign: <span class="underline"></span><br>Date: <span class="underline"></span></div>
                                    <div>Closing: <span class="underline"></span><br>Re-open: <span class="underline"></span><br>Balance: <span class="underline"></span></div>
                                    <div>Next Fees: <span class="underline"></span><br>Total: <span class="underline"></span><br>Parent: <span class="underline"></span></div></div>
                                <div class="mr-footer-line"><span><?php echo htmlspecialchars($school_motto); ?></span><span>Page 1</span></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="template-info">
                    <div class="template-name">Enhanced (No Ranking)</div>
                    <div class="template-desc">Same professional Enhanced design without ranking column or position. For mastery-based assessment.</div>
                    <span class="template-badge badge-simple">No Rankings</span>
                    <span class="template-badge badge-featured">Full Features</span>
                    <button class="btn btn-outline btn-sm" onclick="event.stopPropagation(); previewTemplate('enhanced_norank')" style="margin-top:0.4rem;"><i class="fas fa-search-plus"></i> Enlarge</button>
                </div>
            </div>
        </div>

        <div class="config-panel">
            <h3 class="section-title"><i class="fas fa-sliders-h"></i> Configuration & Filters</h3>
            <div class="config-grid">
                <div class="config-item"><label><input type="checkbox" id="enableStreamRank" checked onchange="updateConfig()"><span>Calculate Stream Rank</span></label></div>
                <div class="config-item"><label><input type="checkbox" id="enableClassRank" checked onchange="updateConfig()"><span>Calculate Class Rank</span></label></div>
                <div class="config-item"><label><input type="checkbox" id="includeSummary" checked><span>Include Summary Page</span></label></div>
            </div>
            <div class="filter-grid">
                <select id="selectClass" class="filter-select"><option value="">Select Class</option>
                    <?php foreach($classes as $c): ?><option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['display_name']); ?></option><?php endforeach; ?></select>
                <select id="selectStream" class="filter-select" disabled><option value="0">All Streams</option></select>
                <select id="selectTerm" class="filter-select"><option value="">Select Term</option>
                    <?php foreach($terms as $t): ?><option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['term_name'].' '.$t['academic_year']); ?></option><?php endforeach; ?></select>
                <select id="selectExam" class="filter-select" disabled><option value="">Select Exam</option></select>
            </div>
            <div class="generate-section">
                <h4 style="margin-bottom:0.75rem;font-size:0.9rem;font-weight:600;">Generate Reports</h4>
                <div class="generate-buttons">
                    <button class="btn btn-primary" id="generateSingleBtn" disabled onclick="goToReports('single')"><i class="fas fa-user"></i> Single Student Report</button>
                    <button class="btn btn-primary" id="generateBatchBtn" disabled onclick="goToReports('batch')"><i class="fas fa-users"></i> Batch (Merged) Report</button>
                    <button class="btn btn-outline" onclick="goToReports()"><i class="fas fa-arrow-right"></i> Full Report Generator</button>
                </div>
            </div>
        </div>
    </div>

    <div class="preview-modal-overlay" id="previewModal">
        <div class="preview-modal">
            <div class="preview-modal-header"><h3><i class="fas fa-eye"></i> Preview: <span id="previewTitle">Enhanced</span></h3><button class="preview-modal-close" onclick="closePreview()">&times;</button></div>
            <div class="preview-modal-body" id="previewBody"></div>
        </div>
    </div>
    <div class="toast-container" id="toastContainer"></div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    $(document).ready(function() {
        // Load saved preferences from localStorage on page load
        const savedTemplate = localStorage.getItem('reportTemplate') || 'enhanced';
        const savedStreamRank = (localStorage.getItem('streamRank') || '1') === '1';
        const savedClassRank = (localStorage.getItem('classRank') || '1') === '1';
        const savedIncludeSummary = (localStorage.getItem('includeSummary') || '1') === '1';

        // State initialized from localStorage
        const state = {
            template: savedTemplate,
            streamRank: savedStreamRank,
            classRank: savedClassRank,
            includeSummary: savedIncludeSummary
        };

        // Apply saved template selection on load
        $('.template-card').removeClass('selected');
        $(`.template-card[data-template="${state.template}"]`).addClass('selected');

        // Apply saved config states
        $('#enableStreamRank').prop('checked', state.streamRank);
        $('#enableClassRank').prop('checked', state.classRank);
        $('#includeSummary').prop('checked', state.includeSummary);

        // Apply ranking disable for enhanced_norank
        if (state.template === 'enhanced_norank') {
            $('#enableStreamRank, #enableClassRank').prop('checked', false).prop('disabled', true);
            state.streamRank = false;
            state.classRank = false;
        }

        // Template selection
        window.selectTemplate = function(t) {
            state.template = t;
            $('.template-card').removeClass('selected');
            $(`.template-card[data-template="${t}"]`).addClass('selected');
            updateConfig();
            savePreference();
            showToast('info', getTemplateName(t) + ' template selected');
        };

        function getTemplateName(t) {
            const names = { classic: 'Classic', enhanced: 'Enhanced', enhanced_norank: 'Enhanced (No Ranking)' };
            return names[t] || t;
        }

        // Update configuration based on template and checkboxes
        window.updateConfig = function() {
            state.streamRank = $('#enableStreamRank').prop('checked');
            state.classRank = $('#enableClassRank').prop('checked');
            state.includeSummary = $('#includeSummary').prop('checked');

            if (state.template === 'enhanced_norank') {
                $('#enableStreamRank, #enableClassRank').prop('checked', false).prop('disabled', true);
                state.streamRank = false;
                state.classRank = false;
            } else {
                $('#enableStreamRank, #enableClassRank').prop('disabled', false);
            }
            savePreference();
        };

        // Save to both session (AJAX) and localStorage
        function savePreference() {
            // Save to localStorage for instant persistence
            localStorage.setItem('reportTemplate', state.template);
            localStorage.setItem('streamRank', state.streamRank ? '1' : '0');
            localStorage.setItem('classRank', state.classRank ? '1' : '0');
            localStorage.setItem('includeSummary', state.includeSummary ? '1' : '0');

            // Also save to PHP session via AJAX for server-side access
            $.ajax({
                url: 'api/save_template_preference.php',
                method: 'POST',
                data: {
                    template: state.template,
                    stream_rank: state.streamRank ? 1 : 0,
                    class_rank: state.classRank ? 1 : 0,
                    include_summary: state.includeSummary ? 1 : 0
                },
                dataType: 'json'
            }).fail(function() {
                // Silent fail - localStorage already has the data
            });
        }

        // Navigate to reports page with all parameters
        window.goToReports = function(mode) {
            const params = new URLSearchParams();
            params.set('template', state.template);
            params.set('stream_rank', state.streamRank ? '1' : '0');
            params.set('class_rank', state.classRank ? '1' : '0');
            params.set('include_summary', state.includeSummary ? '1' : '0');
            if (mode) params.set('mode', mode);

            const classId = $('#selectClass').val();
            const streamId = $('#selectStream').val();
            const termId = $('#selectTerm').val();
            const examId = $('#selectExam').val();

            if (classId) params.set('class_id', classId);
            if (streamId && streamId !== '0') params.set('stream_id', streamId);
            if (termId) params.set('term_id', termId);
            if (examId) params.set('exam_id', examId);

            // Save one final time before navigating
            savePreference();

            window.location.href = 'reports.php?' + params.toString();
        };

        // Preview modal
        window.previewTemplate = function(t) {
            const card = document.querySelector(`.template-card[data-template="${t}"]`);
            if (!card) return;
            const clone = card.querySelector('.report-wrapper').cloneNode(true);
            $('#previewTitle').text(getTemplateName(t));
            $('#previewBody').html(
                '<div style="background:#e5e5e5;padding:20px;display:flex;justify-content:center;">' +
                clone.outerHTML +
                '</div>'
            );
            $('#previewModal').addClass('active');
            $('body').css('overflow', 'hidden');
        };

        window.closePreview = function() {
            $('#previewModal').removeClass('active');
            $('body').css('overflow', '');
        };

        // Close preview on overlay click or Escape
        $('#previewModal').click(function(e) {
            if (e.target === this) closePreview();
        });
        $(document).keydown(function(e) {
            if (e.key === 'Escape') closePreview();
        });

        // Form validation for generate buttons
        function checkValidity() {
            const isValid = $('#selectClass').val() && $('#selectTerm').val() && $('#selectExam').val();
            $('#generateSingleBtn, #generateBatchBtn').prop('disabled', !isValid);
        }

        // Fetch streams and exams when class changes
        $('#selectClass').change(function() {
            const classId = $(this).val();
            if (classId) {
                // Fetch streams
                $.ajax({
                    url: 'ajax/fetch_streams_reports.php',
                    method: 'POST',
                    data: { class_id: classId, school_id: <?php echo $school_id; ?> },
                    dataType: 'json'
                }).done(function(r) {
                    let opts = '<option value="0">All Streams</option>';
                    if (r.success && r.data) {
                        r.data.forEach(function(s) {
                            opts += '<option value="' + s.id + '">' + s.stream_name + '</option>';
                        });
                    }
                    $('#selectStream').html(opts).prop('disabled', false);
                });

                // Fetch exams
                $.ajax({
                    url: 'ajax/fetch_exams_meritlist.php',
                    method: 'POST',
                    data: { class_id: classId, stream_id: 0, school_id: <?php echo $school_id; ?> },
                    dataType: 'json'
                }).done(function(r) {
                    let opts = '<option value="">Select Exam</option>';
                    if (r.success && r.data) {
                        r.data.forEach(function(e) {
                            opts += '<option value="' + e.id + '">' + e.examname + '</option>';
                        });
                    }
                    $('#selectExam').html(opts).prop('disabled', false);
                });
            }
            checkValidity();
        });

        $('#selectTerm, #selectExam').change(checkValidity);

        // Toast notification
        function showToast(type, msg) {
            const icons = {
                success: 'fa-check-circle',
                error: 'fa-exclamation-circle',
                warning: 'fa-exclamation-triangle',
                info: 'fa-info-circle'
            };
            const toast = $(
                '<div class="toast ' + type + '">' +
                '<i class="fas ' + (icons[type] || icons.info) + '"></i>' +
                '<span>' + msg + '</span>' +
                '</div>'
            );
            $('#toastContainer').append(toast);
            setTimeout(function() {
                toast.fadeOut(300, function() { $(this).remove(); });
            }, 3000);
        }

        // Initialize on page load
        updateConfig();
        checkValidity();
    });
</script>
</body>

</body>
</html>
