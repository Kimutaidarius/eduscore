<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// Check if user is logged in
if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['school_id'])) {
    header('Location: login.php');
    exit();
}

// Session variables
$teacher_id = $_SESSION['teacher_id'];
$school_id = $_SESSION['school_id'];

// Database connection
require_once 'includes/config.php';
require_once 'includes/session_timeout.php'; 

// Initialize variables
$classes = [];
$streams = [];
$promotion_history = [];
$school = [];

// Database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get school info
$schoolQuery = $conn->prepare("SELECT * FROM tblschoolinfo WHERE id = ?");
$schoolQuery->bind_param("i", $school_id);
$schoolQuery->execute();
$schoolResult = $schoolQuery->get_result();
$school = $schoolResult->fetch_assoc();
$schoolQuery->close();

// Fetch classes for dropdown
$classesQuery = $conn->prepare("
    SELECT c.id, c.class_level, c.stream as stream_name, c.academic_year, c.academic_level,
           (SELECT COUNT(*) FROM tblstudents WHERE class_id = c.id AND Status = 'Active') as student_count
    FROM tblclasses c
    WHERE c.school_id = ?
    ORDER BY c.academic_level, c.class_level, c.stream
");
$classesQuery->bind_param("i", $school_id);
$classesQuery->execute();
$classesResult = $classesQuery->get_result();
while ($class = $classesResult->fetch_assoc()) {
    $classes[] = $class;
}
$classesQuery->close();

// Fetch distinct streams
$streamsQuery = $conn->prepare("
    SELECT DISTINCT stream as stream_name 
    FROM tblclasses 
    WHERE school_id = ? AND stream IS NOT NULL AND stream != ''
    ORDER BY stream
");
$streamsQuery->bind_param("i", $school_id);
$streamsQuery->execute();
$streamsResult = $streamsQuery->get_result();
$streams = [];
while ($stream = $streamsResult->fetch_assoc()) {
    $streams[] = $stream;
}
$streamsQuery->close();

// Fetch promotion history
$historyQuery = $conn->prepare("
    SELECT ph.*, 
           s.FirstName, s.LastName, s.AdmNo,
           fc.class_level as from_class, fc.stream as from_stream,
           tc.class_level as to_class, tc.stream as to_stream,
           t.firstname as promoted_by_name
    FROM tblpromotion_history ph
    LEFT JOIN tblstudents s ON ph.student_id = s.id
    LEFT JOIN tblclasses fc ON ph.from_class_id = fc.id
    LEFT JOIN tblclasses tc ON ph.to_class_id = tc.id
    LEFT JOIN tblteachers t ON ph.promoted_by = t.id
    WHERE ph.school_id = ?
    ORDER BY ph.promoted_at DESC
    LIMIT 50
");
$historyQuery->bind_param("i", $school_id);
$historyQuery->execute();
$historyResult = $historyQuery->get_result();
while ($history = $historyResult->fetch_assoc()) {
    $promotion_history[] = $history;
}
$historyQuery->close();

$conn->close();

// Initialize message variables
$success_message = '';
$error_message = '';

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle regular form submissions (fallback for non-JS)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error_message'] = "Invalid security token. Please try again.";
        header('Location: promotion.php');
        exit();
    }
    
    $_SESSION['error_message'] = "Please enable JavaScript for better experience";
    header('Location: promotion.php');
    exit();
}

// Retrieve messages from session and clear them
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Promotions - EduScore</title>
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
            --dark-blue: #1e3a8a;
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
            --gradient-primary: linear-gradient(135deg, #1e3a8a, #2563eb);
            --border-radius: 12px;
            --transition: all 0.3s ease;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background: var(--bg-light); color: var(--text-dark); min-height: 100vh; }
        .main-content { margin-left: 280px; min-height: 100vh; padding: 100px 2rem 2rem; transition: margin-left 0.3s ease; }
        @media (max-width: 992px) { .main-content { margin-left: 0; padding: 100px 1rem 1rem; } }
        .promotion-container { max-width: 1600px; margin: 0 auto; }
        .page-header { background: var(--bg-white); border-radius: var(--border-radius); padding: 2rem; margin-bottom: 2rem; box-shadow: var(--shadow); border-left: 4px solid var(--accent-green); position: relative; }
        .promotion-page-title { font-size: 1.8rem; font-weight: 700; color: var(--text-dark); display: flex; align-items: center; gap: 0.75rem; }
        .promotion-page-title i { color: var(--accent-green); }
        .page-description { color: var(--text-light); font-size: 1rem; margin-top: 0.5rem; }
        .alert { padding: 1rem 1.5rem; border-radius: var(--border-radius); margin-bottom: 2rem; display: flex; align-items: center; gap: 0.75rem; }
        .alert-success { background: rgba(16,185,129,0.1); border-left: 4px solid var(--success-green); color: #065f46; }
        .alert-error { background: rgba(239,68,68,0.1); border-left: 4px solid var(--error-red); color: #991b1b; }
        .tab-navigation { display: flex; gap: 1rem; margin-bottom: 2rem; flex-wrap: wrap; }
        .tab-btn { padding: 1rem 2rem; background: var(--bg-white); border: 2px solid var(--border-color); border-radius: var(--border-radius); font-size: 1rem; font-weight: 600; cursor: pointer; transition: var(--transition); display: flex; align-items: center; gap: 0.75rem; flex: 1; min-width: 200px; justify-content: center; }
        .tab-btn:hover { border-color: var(--secondary-blue); transform: translateY(-2px); box-shadow: var(--shadow); }
        .tab-btn.active { background: var(--gradient-primary); color: white; border-color: var(--secondary-blue); }
        .tab-content { display: none; animation: fadeIn 0.3s ease; }
        .tab-content.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .promotion-card { background: var(--bg-white); border-radius: var(--border-radius); box-shadow: var(--shadow); overflow: hidden; margin-bottom: 2rem; }
        .card-header { padding: 1.5rem; border-bottom: 1px solid var(--border-color); background: var(--bg-light); display: flex; align-items: center; gap: 0.75rem; }
        .card-header i { color: var(--accent-green); font-size: 1.2rem; }
        .card-header h2 { font-size: 1.2rem; font-weight: 600; color: var(--text-dark); }
        .card-body { padding: 1.5rem; }
        .form-group { margin-bottom: 1.5rem; }
        .form-label { display: block; margin-bottom: 0.5rem; font-weight: 500; color: var(--text-dark); font-size: 0.9rem; }
        .form-control { width: 100%; padding: 0.875rem 1rem; border: 2px solid var(--border-color); border-radius: var(--border-radius); font-size: 0.95rem; background: var(--bg-white); }
        .form-control:focus { outline: none; border-color: var(--secondary-blue); box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px,1fr)); gap: 1rem; }
        .two-column-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; }
        .three-column-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 1.5rem; }
        @media (max-width:1200px) { .two-column-grid { grid-template-columns: 1fr; } .three-column-grid { grid-template-columns: repeat(2,1fr); } }
        @media (max-width:768px) { .three-column-grid { grid-template-columns: 1fr; } .tab-navigation { flex-direction: column; } .tab-btn { width: 100%; } }
        .action-section { background: var(--bg-light); border-radius: var(--border-radius); padding: 1.5rem; margin-top: 1.5rem; }
        .action-section h4 { color: var(--primary-blue); margin-bottom: 1.5rem; font-size: 1.1rem; display: flex; align-items: center; gap: 0.5rem; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.875rem 1.75rem; border: none; border-radius: var(--border-radius); font-size: 0.95rem; font-weight: 600; cursor: pointer; transition: var(--transition); position: relative; overflow: hidden; }
        .btn-primary { background: var(--gradient-primary); color: white; }
        .btn-success { background: linear-gradient(135deg, #10b981, #059669); color: white; }
        .btn-full { width: 100%; }
        .btn:hover { transform: translateY(-2px); box-shadow: var(--shadow-lg); }
        .student-search-container { position: relative; }
        .student-search-results { position: absolute; top: 100%; left: 0; right: 0; background: white; border: 2px solid var(--border-color); border-radius: var(--border-radius); max-height: 300px; overflow-y: auto; z-index: 1000; display: none; }
        .student-search-results.active { display: block; }
        .student-result-item { padding: 1rem; border-bottom: 1px solid var(--border-color); cursor: pointer; }
        .student-result-item:hover { background: var(--light-blue); }
        .selected-student { margin-top: 1rem; padding: 1rem; background: rgba(16,185,129,0.1); border-radius: var(--border-radius); border-left: 4px solid var(--accent-green); display: flex; align-items: center; gap: 0.75rem; }
        .table-container { overflow-x: auto; border-radius: var(--border-radius); box-shadow: var(--shadow); background: var(--bg-white); margin-bottom: 2rem; }
        .classes-table, .history-table { width: 100%; border-collapse: collapse; min-width: 800px; }
        .classes-table th, .history-table th { padding: 1rem; text-align: left; font-weight: 600; font-size: 0.85rem; text-transform: uppercase; border-bottom: 2px solid var(--border-color); background: var(--bg-light); color: var(--text-dark); }
        .classes-table td, .history-table td { padding: 1rem; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
        .badge { display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
        .badge-primary { background: var(--light-blue); color: var(--primary-blue); }
        .badge-success { background: rgba(16,185,129,0.15); color: var(--success-green); }
        .promotion-search-box { position: relative; margin-bottom: 1rem; }
        .promotion-search-box input { width: 100%; padding: 0.75rem 1rem 0.75rem 2.5rem; border-radius: var(--border-radius); border: 2px solid var(--border-color); background: var(--bg-white); }
        .promotion-search-box i { position: absolute; left: 0.8rem; top: 50%; transform: translateY(-50%); color: var(--text-light); }
        .empty-state { text-align: center; padding: 3rem; color: var(--text-light); }
        .empty-state i { font-size: 3rem; margin-bottom: 1rem; opacity: 0.5; color: var(--accent-green); }
        .loading-container { display: flex; align-items: center; justify-content: center; gap: 1rem; padding: 2rem; }
        .loading-spinner { width: 30px; height: 30px; border: 3px solid var(--light-blue); border-top-color: var(--primary-blue); border-radius: 50%; animation: spin 1s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .toast-container { position: fixed; top: 100px; right: 2rem; z-index: 3000; max-width: 400px; }
        .toast { background: var(--bg-white); border-radius: var(--border-radius); padding: 1rem 1.5rem; margin-bottom: 1rem; box-shadow: var(--shadow-lg); border-left: 4px solid var(--success-green); display: flex; align-items: center; gap: 1rem; animation: slideInRight 0.3s ease; position: relative; padding-right: 40px; }
        .toast.error { border-left-color: var(--error-red); }
        .toast.warning { border-left-color: var(--warning-orange); }
        .toast-close { position: absolute; top: 10px; right: 10px; background: none; border: none; cursor: pointer; opacity: 0.5; }
        @keyframes slideInRight { from { opacity: 0; transform: translateX(100%); } to { opacity: 1; transform: translateX(0); } }
        @media (max-width:768px) { .main-content { padding: 100px 1rem 1rem; } .toast-container { right: 1rem; left: 1rem; max-width: none; } }
    </style>
</head>
<body>
    <?php 
    if (isset($school) && $school && (!isset($school['is_activated']) || $school['is_activated'] == 0)) {
        include 'trial_banner.php'; 
    }
    ?>
    <?php include 'includes/sidebar.php'; ?>
    <?php include 'includes/header.php'; ?>

    <div class="main-content">
        <div class="promotion-container">
            <div class="page-header">
                <h1 class="promotion-page-title"><i class="fas fa-exchange-alt"></i> Student Promotions</h1>
                <p class="page-description">Manage student promotions, class movements, and track promotion history</p>
            </div>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <div class="tab-navigation">
                <button class="tab-btn active" data-tab="single"><i class="fas fa-user-graduate"></i> Single Promotion</button>
                <button class="tab-btn" data-tab="bulk"><i class="fas fa-users"></i> Bulk Promotion</button>
                <button class="tab-btn" data-tab="history"><i class="fas fa-history"></i> Promotion History</button>
            </div>

            <div id="single-tab" class="tab-content active">
                <div class="promotion-card">
                    <div class="card-header"><i class="fas fa-user-graduate"></i><h2>Promote Single Student</h2></div>
                    <div class="card-body">
                        <form method="POST" id="singlePromotionForm">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <div class="two-column-grid">
                                <div class="form-group">
                                    <label class="form-label">Search Student</label>
                                    <div class="student-search-container">
                                        <input type="text" class="form-control" id="studentSearch" placeholder="Type name or admission number..." autocomplete="off">
                                        <div class="student-search-results" id="studentResults"></div>
                                    </div>
                                    <input type="hidden" name="student_id" id="selectedStudentId">
                                    <div id="selectedStudentInfo" class="selected-student" style="display: none;">
                                        <i class="fas fa-check-circle" style="color: var(--success-green);"></i>
                                        <div><strong id="selectedStudentName"></strong><br><small id="selectedStudentAdmission"></small></div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Target Year</label>
                                    <select name="target_year" class="form-control" required><?php for($i=date('Y'); $i<=date('Y')+5; $i++){ $selected=($i==date('Y')+1)?'selected':''; echo "<option value=\"$i\" $selected>$i</option>"; } ?></select>
                                </div>
                            </div>
                            <div class="action-section">
                                <h4><i class="fas fa-arrow-right"></i> Move To</h4>
                                <div class="three-column-grid">
                                    <div class="form-group"><label class="form-label">Class</label><select name="target_class" class="form-control" required id="targetClass"><option value="">Select Class</option><?php $unique=[]; foreach($classes as $c){ if(!in_array($c['class_level'],$unique)){ $unique[]=$c['class_level']; echo "<option value=\"".htmlspecialchars($c['class_level'])."\">".htmlspecialchars($c['class_level'])."</option>"; } } ?></select></div>
                                    <div class="form-group"><label class="form-label">Stream</label><select name="target_stream" class="form-control" id="targetStream"><option value="">No Stream</option><?php foreach($streams as $s){ echo "<option value=\"".htmlspecialchars($s['stream_name'])."\">".htmlspecialchars($s['stream_name'])."</option>"; } ?></select></div>
                                    <div class="form-group"><button type="submit" name="promote_single_student" class="btn btn-primary btn-full"><i class="fas fa-exchange-alt"></i> Promote Student</button></div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div id="bulk-tab" class="tab-content">
                <div class="promotion-card">
                    <div class="card-header"><i class="fas fa-users"></i><h2>Bulk Student Promotion</h2></div>
                    <div class="card-body">
                        <form method="POST" id="bulkPromotionForm">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <div class="action-section">
                                <h4><i class="fas fa-box-arrow-in-right"></i> From (Source)</h4>
                                <div class="form-group"><label class="form-label">Source Class</label><select name="from_class_id" class="form-control" required id="sourceClass"><option value="">Select Class</option><?php foreach($classes as $c){ echo "<option value=\"{$c['id']}\">".htmlspecialchars($c['class_level'].(!empty($c['stream_name'])?' - '.$c['stream_name']:''))." ({$c['student_count']} students)</option>"; } ?></select></div>
                            </div>
                            <div class="action-section">
                                <h4><i class="fas fa-arrow-right"></i> To (Destination)</h4>
                                <div class="three-column-grid">
                                    <div class="form-group"><label class="form-label">Target Class</label><select name="to_class_level" class="form-control" required id="toClassLevel"><option value="">Select Class</option><?php $unique=[]; foreach($classes as $c){ if(!in_array($c['class_level'],$unique)){ $unique[]=$c['class_level']; echo "<option value=\"".htmlspecialchars($c['class_level'])."\">".htmlspecialchars($c['class_level'])."</option>"; } } ?></select></div>
                                    <div class="form-group"><label class="form-label">Target Stream</label><select name="to_stream" class="form-control" id="toStream"><option value="">No Stream</option><?php foreach($streams as $s){ echo "<option value=\"".htmlspecialchars($s['stream_name'])."\">".htmlspecialchars($s['stream_name'])."</option>"; } ?></select></div>
                                    <div class="form-group"><label class="form-label">Target Year</label><select name="to_year" class="form-control" required><?php for($i=date('Y'); $i<=date('Y')+5; $i++){ $selected=($i==date('Y')+1)?'selected':''; echo "<option value=\"$i\" $selected>$i</option>"; } ?></select></div>
                                </div>
                                <div class="form-group" style="margin-top:1.5rem;"><button type="submit" name="promote_multiple" class="btn btn-primary btn-full"><i class="fas fa-exchange-alt"></i> Promote All Students</button>
                                <p style="font-size:0.85rem; color:var(--text-light); margin-top:0.5rem;"><i class="fas fa-info-circle"></i> This will move ALL active students from the source class to the target class.</p></div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="promotion-card">
                    <div class="card-header"><i class="fas fa-school"></i><h2>Classes Overview</h2></div>
                    <div class="card-body">
                        <div class="promotion-search-box"><i class="fas fa-search"></i><input type="text" id="classSearch" placeholder="Search classes..."></div>
                        <div class="table-container">
                            <table class="classes-table">
                                <thead><tr><th>Class</th><th>Stream</th><th>Year</th><th>Students</th><th>Status</th></tr></thead>
                                <tbody id="classesTableBody">
                                    <?php if (!empty($classes)): foreach ($classes as $class): ?>
                                        <tr data-class-id="<?php echo $class['id']; ?>">
                                            <td><strong><?php echo htmlspecialchars($class['class_level']); ?></strong></td>
                                            <td><?php if(!empty($class['stream_name'])){ ?><span class="badge badge-primary"><i class="fas fa-stream"></i> <?php echo htmlspecialchars($class['stream_name']); ?></span><?php }else{ ?><span style="color:var(--text-light);">—</span><?php } ?></td>
                                            <td><?php echo htmlspecialchars($class['academic_year'] ?? ''); ?></td>
                                            <td class="student-count-cell"><span class="badge badge-success"><i class="fas fa-users"></i> <span class="student-count"><?php echo $class['student_count']; ?></span></span></td>
                                            <td><span class="badge badge-primary"><i class="fas fa-check-circle"></i> Active</span></td>
                                        </tr>
                                    <?php endforeach; else: ?>
                                        <tr><td colspan="5"><div class="empty-state"><i class="fas fa-school"></i><h3>No Classes Found</h3><p>No classes have been created yet.</p></div></td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div id="history-tab" class="tab-content">
                <div class="promotion-card">
                    <div class="card-header"><i class="fas fa-history"></i><h2>Promotion History</h2></div>
                    <div class="card-body">
                        <div class="promotion-search-box"><i class="fas fa-search"></i><input type="text" id="historySearch" placeholder="Search history..."></div>
                        <div class="table-container">
                            <table class="history-table">
                                <thead><tr><th>Date</th><th>Student</th><th>From</th><th>To</th><th>Year</th><th>Promoted By</th></tr></thead>
                                <tbody id="historyTableBody">
                                    <?php if (!empty($promotion_history)): foreach ($promotion_history as $history): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y H:i', strtotime($history['promoted_at'] ?? 'now')); ?></td>
                                            <td><strong><?php echo htmlspecialchars(trim(($history['FirstName']??'').' '.($history['LastName']??'')), ENT_QUOTES, 'UTF-8'); ?></strong><br><small>Adm: <?php echo htmlspecialchars($history['AdmNo']??'N/A'); ?></small></td>
                                            <td><?php echo htmlspecialchars($history['from_class']??'N/A'); ?><?php if(!empty($history['from_stream'])): ?><br><small><?php echo htmlspecialchars($history['from_stream']); ?></small><?php endif; ?></td>
                                            <td><?php echo htmlspecialchars($history['to_class']??'N/A'); ?><?php if(!empty($history['to_stream'])): ?><br><small><?php echo htmlspecialchars($history['to_stream']); ?></small><?php endif; ?></td>
                                            <td><span class="badge badge-primary"><?php echo htmlspecialchars((string)($history['academic_year']??'')); ?></span></td>
                                            <td><i class="fas fa-user"></i> <?php echo htmlspecialchars($history['promoted_by_name']??'System'); ?></td>
                                        </tr>
                                    <?php endforeach; else: ?>
                                        <tr><td colspan="6"><div class="empty-state"><i class="fas fa-history"></i><h3>No Promotion History</h3><p>Promotion records will appear here</p></div></td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    $(document).ready(function() {
        // Tab switching
        $('.tab-btn').on('click', function() {
            const tabId = $(this).data('tab');
            $('.tab-btn').removeClass('active');
            $(this).addClass('active');
            $('.tab-content').removeClass('active');
            $('#' + tabId + '-tab').addClass('active');
        });

        // Student search
        let searchTimeout;
        $('#studentSearch').on('input', function() {
            clearTimeout(searchTimeout);
            const query = $(this).val().trim();
            if (query.length < 2) { $('#studentResults').removeClass('active').empty(); return; }
            searchTimeout = setTimeout(() => {
                $.ajax({
                    url: 'ajax/search_students.php',
                    method: 'POST',
                    data: { query: query },
                    dataType: 'json',
                    beforeSend: function() { $('#studentResults').html('<div class="loading-container"><div class="loading-spinner"></div><span>Searching...</span></div>').addClass('active'); },
                    success: function(response) {
                        if (response.success && response.students.length > 0) {
                            let html = '';
                            response.students.forEach(s => {
                                html += `<div class="student-result-item" data-id="${s.id}" data-name="${s.fullname}" data-admission="${s.admission_no}"><div class="student-info-small"><span class="student-name">${s.fullname}</span><span class="student-details"><i class="fas fa-id-card"></i> Adm: ${s.admission_no} | <i class="fas fa-school"></i> ${s.class_name}</span></div></div>`;
                            });
                            $('#studentResults').html(html).addClass('active');
                        } else {
                            $('#studentResults').html('<div class="empty-state" style="padding:1rem;"><i class="fas fa-search"></i><p>No students found</p></div>').addClass('active');
                        }
                    },
                    error: function() { showToast('Failed to search students', 'error'); $('#studentResults').removeClass('active').empty(); }
                });
            }, 300);
        });

        $(document).on('click', '.student-result-item', function() {
            const id = $(this).data('id');
            const name = $(this).data('name');
            const admission = $(this).data('admission');
            $('#selectedStudentId').val(id);
            $('#selectedStudentName').text(name);
            $('#selectedStudentAdmission').html('<i class="fas fa-id-card"></i> Adm: ' + admission);
            $('#selectedStudentInfo').show();
            $('#studentSearch').val('');
            $('#studentResults').removeClass('active').empty();
        });

        $(document).on('click', function(e) { if (!$(e.target).closest('.student-search-container').length) { $('#studentResults').removeClass('active'); } });

        // Single promotion
        $('#singlePromotionForm').on('submit', function(e) {
            e.preventDefault();
            if (!$('#selectedStudentId').val()) { showToast('Please select a student', 'warning'); return; }
            if (!$('#targetClass').val()) { showToast('Please select target class', 'warning'); return; }
            if (!confirm('Are you sure you want to promote this student?')) return;
            
            $.ajax({
                url: 'ajax/promote_student.php',
                method: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                beforeSend: function() { $('button[name="promote_single_student"]').html('<i class="fas fa-spinner fa-spin"></i> Promoting...').prop('disabled', true); },
                success: function(response) {
                    if (response.success) {
                        showToast(response.message, 'success');
                        $('#selectedStudentId').val('');
                        $('#selectedStudentInfo').hide();
                        $('#studentSearch').val('');
                        $('#targetClass').val('');
                        $('#targetStream').val('');
                    } else {
                        showToast(response.message, 'error');
                    }
                },
                error: function() { showToast('Failed to promote student', 'error'); },
                complete: function() { $('button[name="promote_single_student"]').html('<i class="fas fa-exchange-alt"></i> Promote Student').prop('disabled', false); }
            });
        });

        // Bulk promotion
        $('#bulkPromotionForm').on('submit', function(e) {
            e.preventDefault();
            const sourceClass = $('#sourceClass').val();
            const toClass = $('#toClassLevel').val();
            if (!sourceClass) { showToast('Please select source class', 'warning'); return; }
            if (!toClass) { showToast('Please select target class', 'warning'); return; }
            const countMatch = $('#sourceClass option:selected').text().match(/\((\d+)/);
            const count = countMatch ? parseInt(countMatch[1]) : 0;
            if (count === 0) { showToast('No active students in the selected class', 'warning'); return; }
            if (!confirm(`Are you sure you want to promote ${count} students?\n\nThis action cannot be undone.`)) return;
            
            $.ajax({
                url: 'ajax/promote_bulk.php',
                method: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                beforeSend: function() { $('button[name="promote_multiple"]').html('<i class="fas fa-spinner fa-spin"></i> Promoting...').prop('disabled', true); },
                success: function(response) {
                    if (response.success) {
                        showToast(response.message, 'success');
                        $('#sourceClass').val('');
                        $('#toClassLevel').val('');
                        $('#toStream').val('');
                    } else {
                        showToast(response.message, 'error');
                    }
                },
                error: function() { showToast('Failed to promote students', 'error'); },
                complete: function() { $('button[name="promote_multiple"]').html('<i class="fas fa-exchange-alt"></i> Promote All Students').prop('disabled', false); }
            });
        });

        // Class search
        $('#classSearch').on('input', function() {
            const term = $(this).val().toLowerCase();
            $('#classesTableBody tr').each(function() { $(this).toggle($(this).text().toLowerCase().includes(term)); });
        });

        // History search
        $('#historySearch').on('input', function() {
            const term = $(this).val().toLowerCase();
            $('#historyTableBody tr').each(function() { $(this).toggle($(this).text().toLowerCase().includes(term)); });
        });

        function showToast(message, type = 'info') {
            const icons = { success: 'fa-check-circle', error: 'fa-exclamation-circle', warning: 'fa-exclamation-triangle', info: 'fa-info-circle' };
            const titles = { success: 'Success', error: 'Error', warning: 'Warning', info: 'Information' };
            const toastId = 'toast-' + Date.now();
            const toast = $(`<div id="${toastId}" class="toast ${type}"><div class="toast-icon"><i class="${icons[type]}"></i></div><div class="toast-content"><div class="toast-title">${titles[type]}</div><div class="toast-message">${message}</div></div><button class="toast-close"><i class="fas fa-times"></i></button></div>`);
            $('#toastContainer').append(toast);
            toast.find('.toast-close').on('click', function() { toast.fadeOut(300, function() { $(this).remove(); }); });
            setTimeout(() => { toast.fadeOut(300, function() { $(this).remove(); }); }, 4000);
        }

        window.showToast = showToast;
    });
    </script>
</body>
</html>