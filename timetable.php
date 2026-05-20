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
$academic_level_id = $_SESSION['academic_level_id'] ?? null;

require_once 'includes/config.php';
require_once 'includes/session_timeout.php'; 


$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

// Fetch settings
$settings = [];
$settingsStmt = $conn->prepare("SELECT * FROM tbl_timetable_settings WHERE school_id = ?");
if ($settingsStmt) {
    $settingsStmt->bind_param("i", $school_id);
    $settingsStmt->execute();
    $result = $settingsStmt->get_result();
    $settings = $result->fetch_assoc() ?: [];
    $settingsStmt->close();
}

// Default settings
$periods_per_day = $settings['periods_per_day'] ?? 8;
$first_period_start = $settings['first_period_start'] ?? '07:30';
$period_duration = $settings['period_duration'] ?? 60;
$school_days_count = $settings['school_days'] ?? 5;
$include_saturday = $settings['include_saturday'] ?? 0;
$monday_assembly = $settings['monday_assembly'] ?? 1;
$friday_games = $settings['friday_games'] ?? 1;

// Fetch periods
$periods = [];
$periodStmt = $conn->prepare("SELECT * FROM tbl_timetable_periods WHERE school_id = ? ORDER BY period_number");
if ($periodStmt) {
    $periodStmt->bind_param("i", $school_id);
    $periodStmt->execute();
    $periods = $periodStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $periodStmt->close();
}

// Generate default periods if none exist
if (empty($periods)) {
    $start = strtotime($first_period_start . ':00');
    for ($i = 1; $i <= $periods_per_day; $i++) {
        $pStart = date('H:i', $start);
        $pEnd = date('H:i', $start + ($period_duration * 60));
        $periods[] = [
            'period_number' => $i,
            'period_name' => 'P' . $i,
            'start_time' => $pStart,
            'end_time' => $pEnd,
            'is_break' => 0,
            'break_type' => 'none'
        ];
        $start += ($period_duration * 60);
    }
}

// Build time slots from periods
$time_slots = [];
foreach ($periods as $p) {
    if (!$p['is_break']) {
        $time_slots[] = $p['start_time'] . '-' . $p['end_time'];
    }
}

// Days
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
if ($include_saturday) $days[] = 'Saturday';

// Fetch classes, streams, teachers, subjects
$classes = $conn->query("SELECT c.*, CASE WHEN c.academic_level='primary' THEN CONCAT('Grade ',c.class_level) WHEN c.academic_level='junior_secondary' THEN CONCAT('Grade ',c.class_level) ELSE c.class_level END as display_name FROM tblclasses c WHERE c.school_id=$school_id")->fetch_all(MYSQLI_ASSOC);
$streams = $conn->query("SELECT * FROM tblstreams WHERE school_id=$school_id")->fetch_all(MYSQLI_ASSOC);
$teachers = $conn->query("SELECT t.*, CONCAT(t.firstname,' ',COALESCE(t.middle_name,''),' ',t.lastname) as fullname FROM tblteachers t WHERE t.school_id=$school_id AND t.is_deleted=0")->fetch_all(MYSQLI_ASSOC);
$subjects = $conn->query("SELECT * FROM tblsubjects WHERE school_id=$school_id")->fetch_all(MYSQLI_ASSOC);

// Fetch timetable entries
$timetable = $conn->query("
    SELECT t.*, s.subject_name, CONCAT(te.firstname,' ',COALESCE(te.middle_name,''),' ',te.lastname) as teacher_name,
    c.class_level, str.stream_name
    FROM tbl_timetable t
    LEFT JOIN tblsubjects s ON t.subject_id=s.id
    LEFT JOIN tblteachers te ON t.teacher_id=te.id
    LEFT JOIN tblclasses c ON t.class_id=c.id
    LEFT JOIN tblstreams str ON t.stream_id=str.id
    WHERE t.school_id=$school_id
    ORDER BY FIELD(t.day,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), t.period_number
")->fetch_all(MYSQLI_ASSOC);

// Fetch subject lessons config
$subject_lessons = [];
$slStmt = $conn->prepare("SELECT sl.*, s.subject_name, CONCAT(t.firstname,' ',t.lastname) as teacher_name FROM tbl_subject_lessons sl JOIN tblsubjects s ON sl.subject_id=s.id JOIN tblteachers t ON sl.teacher_id=t.id WHERE sl.school_id=?");
if ($slStmt) {
    $slStmt->bind_param("i", $school_id);
    $slStmt->execute();
    $subject_lessons = $slStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $slStmt->close();
}

// School info
$school = $conn->query("SELECT * FROM tblschoolinfo WHERE id=$school_id")->fetch_assoc();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Timetable Generator - EduScore</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="images/logo.png" />
    <link rel="stylesheet" href="assets/banner/banner.css">
    <style>
        :root { --primary-blue:#1e3a8a; --secondary-blue:#2563eb; --light-blue:#dbeafe; --accent-green:#10b981; --dark-blue:#1e3a8a; --success-green:#10b981; --warning-orange:#f59e0b; --error-red:#ef4444; --text-dark:#1f2937; --text-light:#6b7280; --bg-light:#f9fafb; --bg-white:#ffffff; --border-color:#e5e7eb; --shadow:0 4px 6px -1px rgba(0,0,0,0.1); --shadow-lg:0 10px 15px -3px rgba(0,0,0,0.1); --border-radius:12px; --transition:all 0.3s ease; }
        *{margin:0;padding:0;box-sizing:border-box;font-family:'Inter',sans-serif;}
        body{background:var(--bg-light);color:var(--text-dark);min-height:100vh;}
        .main-content{margin-left:280px;min-height:100vh;padding:90px 1.5rem 2rem;transition:margin-left 0.3s;}
        @media(max-width:992px){.main-content{margin-left:0;padding:80px 1rem 1rem;}}
        .page-header{background:var(--bg-white);border-radius:var(--border-radius);padding:1.5rem 2rem;margin-bottom:1.5rem;box-shadow:var(--shadow);border-left:4px solid var(--accent-green);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;}
        .page-title{font-size:1.5rem;font-weight:700;display:flex;align-items:center;gap:0.75rem;}
        .page-title i{color:var(--primary-blue);}
        .filter-card{background:var(--bg-white);border-radius:var(--border-radius);padding:1rem;margin-bottom:1.5rem;box-shadow:var(--shadow);}
        .filter-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:0.75rem;margin-bottom:0.75rem;}
        .filter-select{width:100%;padding:0.6rem 0.75rem;border:1px solid var(--border-color);border-radius:8px;font-size:0.85rem;background:var(--bg-white);}
        .action-bar{display:flex;flex-wrap:wrap;gap:0.5rem;margin-bottom:1.5rem;justify-content:space-between;align-items:center;background:var(--bg-white);padding:0.75rem 1rem;border-radius:var(--border-radius);box-shadow:var(--shadow);}
        .btn-group{display:flex;flex-wrap:wrap;gap:0.5rem;}
        .btn{padding:0.5rem 1rem;border-radius:8px;font-weight:500;font-size:0.8rem;cursor:pointer;display:inline-flex;align-items:center;gap:0.4rem;border:none;transition:all 0.2s;background:var(--bg-light);border:1px solid var(--border-color);}
        .btn:hover:not(:disabled){transform:translateY(-1px);}
        .btn-primary{background:var(--primary-blue);color:white;border:none;}
        .btn-success{background:var(--success-green);color:white;border:none;}
        .btn-warning{background:var(--warning-orange);color:white;border:none;}
        .btn-danger{background:var(--error-red);color:white;border:none;}
        .btn-outline{background:transparent;border:1px solid var(--border-color);color:var(--text-dark);}
        .btn-magic{background:linear-gradient(135deg,#7c3aed,#a855f7);color:white;}
        .btn:disabled{opacity:0.5;cursor:not-allowed;}
        .view-toggle{display:flex;gap:0.3rem;background:var(--bg-light);padding:0.2rem;border-radius:8px;}
        .view-btn{padding:0.4rem 0.8rem;border:none;border-radius:6px;background:transparent;color:var(--text-light);cursor:pointer;font-size:0.75rem;display:flex;align-items:center;gap:0.3rem;}
        .view-btn.active{background:var(--bg-white);color:var(--accent-green);box-shadow:var(--shadow);}
        .table-container{background:var(--bg-white);border-radius:var(--border-radius);overflow-x:auto;box-shadow:var(--shadow);margin-bottom:1.5rem;}
        .table-header{padding:1rem 1.5rem;border-bottom:1px solid var(--border-color);display:flex;justify-content:space-between;align-items:center;background:linear-gradient(135deg,var(--primary-blue),var(--dark-blue));color:white;}
        .timetable-table{width:100%;border-collapse:collapse;min-width:900px;font-size:0.8rem;}
        .timetable-table th{background:var(--bg-light);padding:0.6rem 0.5rem;text-align:center;font-weight:600;border:1px solid var(--border-color);font-size:0.75rem;}
        .timetable-table td{border:1px solid var(--border-color);padding:0;vertical-align:top;min-width:120px;}
        .time-slot-cell{background:var(--bg-light);font-weight:600;text-align:center;padding:0.5rem!important;width:90px;font-size:0.7rem;}
        .lesson-cell{padding:0.5rem;min-height:70px;cursor:pointer;transition:all 0.15s;position:relative;}
        .lesson-cell:hover{background:var(--light-blue);}
        .lesson-cell.occupied{background:rgba(16,185,129,0.06);border-left:3px solid var(--accent-green);}
        .lesson-cell.assembly{background:rgba(245,158,11,0.1);border-left:3px solid var(--warning-orange);}
        .lesson-cell.games{background:rgba(59,130,246,0.08);border-left:3px solid var(--secondary-blue);}
        .lesson-cell.break{background:#fef3c7;text-align:center;font-weight:600;color:var(--warning-orange);cursor:default;}
        .lesson-subject{font-weight:700;color:var(--primary-blue);font-size:0.8rem;margin-bottom:0.15rem;}
        .lesson-teacher{font-size:0.7rem;color:var(--text-dark);}
        .lesson-type{font-size:0.6rem;color:var(--text-light);margin-top:0.15rem;}
        .empty-cell{display:flex;align-items:center;justify-content:center;height:100%;min-height:70px;color:var(--text-light);font-size:0.7rem;cursor:pointer;}
        .empty-cell:hover{background:var(--light-blue);}
        .modal-overlay{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;z-index:2000;opacity:0;visibility:hidden;transition:all 0.3s;}
        .modal-overlay.active{opacity:1;visibility:visible;}
        .modal-content{background:var(--bg-white);border-radius:var(--border-radius);width:90%;max-width:650px;max-height:85vh;overflow-y:auto;box-shadow:var(--shadow-lg);}
        .modal-header{padding:1.25rem 1.5rem;border-bottom:1px solid var(--border-color);display:flex;align-items:center;justify-content:space-between;background:linear-gradient(135deg,var(--primary-blue),var(--dark-blue));color:white;}
        .modal-header h3{font-size:1.1rem;font-weight:600;display:flex;align-items:center;gap:0.5rem;}
        .close-modal-btn{background:none;border:none;color:white;font-size:1.25rem;cursor:pointer;}
        .modal-body{padding:1.5rem;}
        .modal-footer{padding:1rem 1.5rem;border-top:1px solid var(--border-color);display:flex;justify-content:flex-end;gap:0.75rem;background:var(--bg-light);}
        .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem;}
        .form-group{display:flex;flex-direction:column;gap:0.4rem;}
        .form-group.full{grid-column:span 2;}
        .form-label{font-size:0.85rem;font-weight:500;display:flex;align-items:center;gap:0.5rem;}
        .form-control{width:100%;padding:0.6rem 0.75rem;border:1px solid var(--border-color);border-radius:8px;font-size:0.85rem;}
        .form-control:focus{outline:none;border-color:var(--primary-blue);box-shadow:0 0 0 3px rgba(59,130,246,0.1);}
        .checkbox-group{display:flex;align-items:center;gap:0.5rem;}
        .checkbox-group input[type="checkbox"]{width:18px;height:18px;accent-color:var(--primary-blue);}
        .section-title{font-size:1rem;font-weight:600;margin:1.25rem 0 0.75rem;color:var(--primary-blue);border-bottom:1px solid var(--border-color);padding-bottom:0.5rem;}
        .config-card{background:var(--bg-light);border-radius:8px;padding:1rem;margin-bottom:0.75rem;}
        .config-card h4{font-size:0.85rem;font-weight:600;margin-bottom:0.5rem;}
        .toast-container{position:fixed;top:90px;right:1rem;z-index:3000;max-width:320px;}
        .toast{background:var(--bg-white);border-radius:8px;padding:0.75rem 1rem;margin-bottom:0.5rem;box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);border-left:3px solid var(--success-green);font-size:0.8rem;animation:slideIn 0.3s ease;display:flex;align-items:center;gap:0.5rem;}
        .toast.error{border-left-color:var(--error-red);}
        .toast.warning{border-left-color:var(--warning-orange);}
        .gen-progress{text-align:center;padding:2rem;}
        .gen-progress i{font-size:3rem;color:var(--primary-blue);animation:spin 1.5s linear infinite;}
        @keyframes fadeIn{from{opacity:0;}to{opacity:1;}}
        @keyframes slideIn{from{opacity:0;transform:translateX(100%);}to{opacity:1;transform:translateX(0);}}
        @keyframes spin{0%{transform:rotate(0deg);}100%{transform:rotate(360deg);}}
        @media(max-width:768px){.form-grid{grid-template-columns:1fr;}.form-group.full{grid-column:span 1;}}
    </style>
</head>
<body>

    <?php if($school&&(!isset($school['is_activated'])||$school['is_activated']==0))include'trial_banner.php';  include'includes/header.php';include'includes/sidebar.php';?>

    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title"><i class="fas fa-calendar-alt"></i>Timetable Generator</h1>
            <span style="font-size:0.8rem;color:var(--text-light);"><i class="fas fa-graduation-cap"></i> <?php echo ucwords(str_replace('_',' ',$academic_level));?></span>
        </div>

        <!-- Action Bar -->
        <div class="action-bar">
            <div class="btn-group">
                <button class="btn btn-magic" id="generateBtn"><i class="fas fa-magic"></i>Auto-Generate Timetable</button>
                <button class="btn btn-outline" id="settingsBtn"><i class="fas fa-cog"></i>Settings</button>
                <button class="btn btn-outline" id="subjectsConfigBtn"><i class="fas fa-book"></i>Subject Lessons</button>
                <button class="btn btn-outline" id="periodsBtn"><i class="fas fa-clock"></i>Periods/Bells</button>
                <button class="btn btn-outline" id="manualAddBtn"><i class="fas fa-plus-circle"></i>Add Lesson</button>
            </div>
            <div class="view-toggle">
                <button class="view-btn active" data-view="grid"><i class="fas fa-table"></i>Grid</button>
                <button class="view-btn" data-view="list"><i class="fas fa-list"></i>List</button>
            </div>
        </div>

        <!-- Filter -->
        <div class="filter-card">
            <div class="filter-grid">
                <select id="filterClass" class="filter-select"><option value="">All Classes</option>
                    <?php foreach($classes as $c):?><option value="<?php echo$c['id'];?>"><?php echo htmlspecialchars($c['display_name']);?></option><?php endforeach;?></select>
                <select id="filterStream" class="filter-select"><option value="">All Streams</option>
                    <?php foreach($streams as $s):?><option value="<?php echo$s['id'];?>"><?php echo htmlspecialchars($s['stream_name']);?></option><?php endforeach;?></select>
                <button class="btn btn-primary" id="applyFilterBtn"><i class="fas fa-filter"></i>Apply</button>
            </div>
        </div>

        <!-- Grid View -->
        <div class="table-container" id="gridView">
            <div class="table-header"><span><i class="fas fa-calendar-week"></i> Class Timetable</span><span id="gridClassLabel"></span></div>
            <div style="overflow-x:auto;padding:1rem;">
                <table class="timetable-table">
                    <thead><tr><th>Period</th><?php foreach($days as$d):?><th><?php echo$d;?></th><?php endforeach;?></tr></thead>
                    <tbody id="gridTbody">
                        <?php
                        $gridPeriods = [];
                        foreach($periods as$p){
                            $label = $p['period_name'];
                            $timeLabel = $p['start_time'].'-'.$p['end_time'];
                            echo'<tr><td class="time-slot-cell">'.$label.'<br><small>'.$timeLabel.'</small></td>';
                            foreach($days as$d){
                                $found=false;
                                $cellClass='lesson-cell';
                                $content='<div class="empty-cell"><i class="fas fa-plus-circle"></i></div>';
                                foreach($timetable as$t){
                                    if($t['day']==$d&&$t['period_number']==$p['period_number']){
                                        $found=true;
                                        if($t['lesson_type']=='assembly'){$cellClass='lesson-cell assembly';$content='<div class="lesson-subject"><i class="fas fa-flag"></i> Assembly</div>';}
                                        elseif($t['lesson_type']=='games'){$cellClass='lesson-cell games';$content='<div class="lesson-subject"><i class="fas fa-futbol"></i> Games</div>';}
                                        elseif($p['is_break']){$cellClass='lesson-cell break';$content='<div class="lesson-subject"><i class="fas fa-coffee"></i> '.$p['break_type'].'</div>';}
                                        else{$cellClass='lesson-cell occupied';$content='<div class="lesson-subject">'.htmlspecialchars($t['subject_name']).'</div><div class="lesson-teacher"><i class="fas fa-user"></i> '.htmlspecialchars($t['teacher_name']).'</div><div class="lesson-type">'.$t['lesson_type'].'</div>';}
                                        break;
                                    }
                                }
                                if($p['is_break']&&!$found){$cellClass='lesson-cell break';$content='<div class="lesson-subject"><i class="fas fa-coffee"></i> Break</div>';}
                                echo'<td class="'.$cellClass.'">'.$content.'</td>';
                            }
                            echo'</tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- List View -->
        <div class="table-container" id="listView" style="display:none;">
            <div class="table-header"><span><i class="fas fa-list"></i> All Lessons</span></div>
            <div style="overflow-x:auto;padding:1rem;">
                <table class="timetable-table">
                    <thead><tr><th>Day</th><th>Period</th><th>Time</th><th>Class</th><th>Subject</th><th>Teacher</th><th>Type</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php if(!empty($timetable)):foreach($timetable as$t):?>
                        <tr>
                            <td><?php echo$t['day'];?></td><td><?php echo$t['period_number'];?></td><td><?php echo$t['time_slot'];?></td>
                            <td><?php echo htmlspecialchars($t['class_level']??'N/A');?></td>
                            <td><?php echo htmlspecialchars($t['subject_name']);?></td>
                            <td><?php echo htmlspecialchars($t['teacher_name']);?></td>
                            <td><span class="status-badge"><?php echo$t['lesson_type'];?></span></td>
                            <td><div class="action-btns"><button class="action-btn" onclick="deleteEntry(<?php echo$t['id'];?>)"><i class="fas fa-trash"></i></button></div></td>
                        </tr>
                        <?php endforeach;else:?>
                        <tr><td colspan="8" style="text-align:center;padding:2rem;">No timetable entries</td></tr>
                        <?php endif;?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Settings Modal -->
    <div class="modal-overlay" id="settingsModal">
        <div class="modal-content">
            <div class="modal-header"><h3><i class="fas fa-cog"></i>Timetable Settings</h3><button class="close-modal-btn" onclick="closeModal('settingsModal')">&times;</button></div>
            <div class="modal-body">
                <form id="settingsForm">
                    <div class="section-title">Period Configuration</div>
                    <div class="form-grid">
                        <div class="form-group"><label class="form-label">Periods Per Day</label><input type="number" class="form-control" id="setPeriodsPerDay" value="<?php echo$periods_per_day;?>" min="4" max="12"></div>
                        <div class="form-group"><label class="form-label">First Period Start</label><input type="time" class="form-control" id="setFirstPeriodStart" value="<?php echo$first_period_start;?>"></div>
                        <div class="form-group"><label class="form-label">Period Duration (min)</label><input type="number" class="form-control" id="setPeriodDuration" value="<?php echo$period_duration;?>" min="30" max="90" step="5"></div>
                        <div class="form-group"><label class="form-label">School Days</label><select class="form-control" id="setSchoolDays"><option value="5" <?php echo$school_days_count==5?'selected':'';?>>5 Days (Mon-Fri)</option><option value="6" <?php echo$school_days_count==6?'selected':'';?>>6 Days (Mon-Sat)</option></select></div>
                    </div>
                    <div class="section-title">Kenya School Rules</div>
                    <div class="config-card">
                        <div class="checkbox-group"><input type="checkbox" id="setMondayAssembly" <?php echo$monday_assembly?'checked':'';?>><label>Monday Assembly (Blocks Period 1 on Monday)</label></div>
                        <div class="checkbox-group" style="margin-top:0.5rem;"><input type="checkbox" id="setFridayGames" <?php echo$friday_games?'checked':'';?>><label>Friday Games (Blocks last period on Friday)</label></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer"><button class="btn btn-outline" onclick="closeModal('settingsModal')">Cancel</button><button class="btn btn-primary" onclick="saveSettings()"><i class="fas fa-save"></i>Save Settings</button></div>
        </div>
    </div>

    <!-- Subject Lessons Modal -->
    <div class="modal-overlay" id="subjectsModal">
        <div class="modal-content">
            <div class="modal-header"><h3><i class="fas fa-book"></i>Subject Lesson Configuration</h3><button class="close-modal-btn" onclick="closeModal('subjectsModal')">&times;</button></div>
            <div class="modal-body">
                <div class="form-grid"><div class="form-group"><label class="form-label">Class</label><select class="form-control" id="subjClass"><option value="">Select Class</option><?php foreach($classes as$c):?><option value="<?php echo$c['id'];?>"><?php echo htmlspecialchars($c['display_name']);?></option><?php endforeach;?></select></div></div>
                <div class="section-title">Current Configurations</div>
                <div id="subjectLessonsList" style="max-height:250px;overflow-y:auto;">
                    <?php if(!empty($subject_lessons)):foreach($subject_lessons as$sl):?>
                    <div class="config-card"><strong><?php echo htmlspecialchars($sl['subject_name']);?></strong> - <?php echo$sl['lessons_per_week'];?> lessons/wk (<?php echo$sl['lesson_type'];?>) - <?php echo htmlspecialchars($sl['teacher_name']);?></div>
                    <?php endforeach;else:?>
                    <p style="color:var(--text-light);">No subject lessons configured. Use the form below.</p>
                    <?php endif;?>
                </div>
                <div class="section-title">Add/Update Subject</div>
                <form id="subjectLessonForm">
                    <div class="form-grid">
                        <div class="form-group"><label class="form-label">Subject</label><select class="form-control" id="slSubject" required><option value="">Select</option><?php foreach($subjects as$s):?><option value="<?php echo$s['id'];?>"><?php echo htmlspecialchars($s['subject_name']);?></option><?php endforeach;?></select></div>
                        <div class="form-group"><label class="form-label">Teacher</label><select class="form-control" id="slTeacher" required><option value="">Select</option><?php foreach($teachers as$t):?><option value="<?php echo$t['id'];?>"><?php echo htmlspecialchars($t['fullname']);?></option><?php endforeach;?></select></div>
                        <div class="form-group"><label class="form-label">Lessons/Week</label><input type="number" class="form-control" id="slLessons" value="5" min="1" max="10"></div>
                        <div class="form-group"><label class="form-label">Lesson Type</label><select class="form-control" id="slType"><option value="single">Single</option><option value="double">Double</option></select></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer"><button class="btn btn-outline" onclick="closeModal('subjectsModal')">Cancel</button><button class="btn btn-primary" onclick="saveSubjectLesson()"><i class="fas fa-save"></i>Save</button></div>
        </div>
    </div>

    <!-- Generate Modal -->
    <div class="modal-overlay" id="generateModal">
        <div class="modal-content" style="max-width:450px;">
            <div class="modal-header" style="background:linear-gradient(135deg,#7c3aed,#a855f7);"><h3><i class="fas fa-magic"></i>Generate Timetable</h3><button class="close-modal-btn" onclick="closeModal('generateModal')">&times;</button></div>
            <div class="modal-body" style="text-align:center;">
                <p style="margin-bottom:1rem;">Select a class to auto-generate a conflict-free timetable.</p>
                <div class="form-group" style="text-align:left;"><label class="form-label">Select Class</label><select class="form-control" id="genClassSelect"><option value="">Select Class</option><?php foreach($classes as$c):?><option value="<?php echo$c['id'];?>"><?php echo htmlspecialchars($c['display_name']);?></option><?php endforeach;?></select></div>
                <div id="genStatus" class="gen-progress" style="display:none;"><i class="fas fa-spinner fa-spin"></i><p>Generating timetable...</p></div>
            </div>
            <div class="modal-footer"><button class="btn btn-outline" onclick="closeModal('generateModal')">Cancel</button><button class="btn btn-magic" id="executeGenBtn" disabled onclick="executeGeneration()"><i class="fas fa-magic"></i>Generate</button></div>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    $(document).ready(function(){
        // Toast
        function showToast(type,msg){var i={success:'fa-check-circle',error:'fa-exclamation-circle',warning:'fa-exclamation-triangle',info:'fa-info-circle'};$('#toastContainer').append($('<div class="toast '+type+'"><i class="fas '+i[type]+'"></i><span>'+msg+'</span></div>'));setTimeout(function(){$('#toastContainer .toast').first().fadeOut(300,function(){$(this).remove();});},3500);}
        
        // View toggle
        $('.view-btn').click(function(){$('.view-btn').removeClass('active');$(this).addClass('active');$('#gridView,#listView').toggle();});
        
        // Modal open/close
        window.openModal=function(id){$('#'+id).addClass('active');$('body').css('overflow','hidden');};
        window.closeModal=function(id){$('#'+id).removeClass('active');$('body').css('overflow','');};
        $('.modal-overlay').click(function(e){if(e.target===this)$(this).removeClass('active');$('body').css('overflow','');});
        $(document).keydown(function(e){if(e.key==='Escape'){$('.modal-overlay').removeClass('active');$('body').css('overflow','');}});
        
        // Button clicks
        $('#settingsBtn').click(function(){openModal('settingsModal');});
        $('#subjectsConfigBtn').click(function(){openModal('subjectsModal');});
        $('#generateBtn').click(function(){openModal('generateModal');});
        $('#genClassSelect').change(function(){$('#executeGenBtn').prop('disabled',!$(this).val());});
        $('#periodsBtn').click(function(){showToast('info','Periods configuration coming soon');});
        $('#manualAddBtn').click(function(){showToast('info','Manual add coming soon');});
        $('#applyFilterBtn').click(function(){showToast('info','Filter applied');});
        
        // Save Settings
        window.saveSettings=function(){
            var data={
                periods_per_day:$('#setPeriodsPerDay').val(),
                first_period_start:$('#setFirstPeriodStart').val(),
                period_duration:$('#setPeriodDuration').val(),
                school_days:$('#setSchoolDays').val(),
                include_saturday:$('#setSchoolDays').val()=='6'?1:0,
                monday_assembly:$('#setMondayAssembly').is(':checked')?1:0,
                friday_games:$('#setFridayGames').is(':checked')?1:0
            };
            $.ajax({url:'ajax/save_timetable_settings.php',method:'POST',contentType:'application/json',data:JSON.stringify(data),dataType:'json'})
            .done(function(r){if(r.success){showToast('success','Settings saved!');closeModal('settingsModal');}else{showToast('error',r.message||'Failed');}})
            .fail(function(){showToast('error','Network error');});
        };
        
        // Save Subject Lesson
        window.saveSubjectLesson=function(){
            var classId=$('#subjClass').val();
            var subjectId=$('#slSubject').val();
            var teacherId=$('#slTeacher').val();
            var lessons=$('#slLessons').val();
            var type=$('#slType').val();
            if(!classId||!subjectId||!teacherId){showToast('warning','Please fill all fields');return;}
            $.ajax({url:'ajax/save_subject_lesson.php',method:'POST',data:{class_id:classId,subject_id:subjectId,teacher_id:teacherId,lessons_per_week:lessons,lesson_type:type,school_id:<?php echo$school_id;?>},dataType:'json'})
            .done(function(r){if(r.success){showToast('success','Subject lesson saved!');closeModal('subjectsModal');setTimeout(function(){location.reload();},1000);}else{showToast('error',r.message||'Failed');}})
            .fail(function(){showToast('error','Network error');});
        };
        
        // Execute Generation
        window.executeGeneration=function(){
            var classId=$('#genClassSelect').val();
            if(!classId)return;
            var btn=$('#executeGenBtn');btn.prop('disabled',true).html('<i class="fas fa-spinner fa-spin"></i> Generating...');
            $('#genStatus').show();
            $.ajax({url:'ajax/generate_timetable.php',method:'POST',contentType:'application/json',data:JSON.stringify({class_id:classId,stream_id:0}),dataType:'json',timeout:120000})
            .done(function(r){if(r.success){showToast('success','Generated! '+r.stats.total_lessons+' lessons, '+r.stats.days+' days');closeModal('generateModal');setTimeout(function(){location.reload();},1500);}else{showToast('error',r.message||'Generation failed');$('#genStatus').hide();}})
            .fail(function(){showToast('error','Network error');$('#genStatus').hide();})
            .always(function(){btn.prop('disabled',false).html('<i class="fas fa-magic"></i> Generate');});
        };
        
        // Delete entry
        window.deleteEntry=function(id){
            if(!confirm('Delete this lesson?'))return;
            $.ajax({url:'ajax/delete_timetable_entry.php',method:'POST',data:{id:id},dataType:'json'})
            .done(function(r){if(r.success){showToast('success','Deleted');setTimeout(function(){location.reload();},1000);}else{showToast('error',r.message||'Failed');}})
            .fail(function(){showToast('error','Network error');});
        };
    });
    </script>
</body>

</body>
</html>
