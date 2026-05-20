<?php
// sms_center.php
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
require_once 'includes/session_timeout.php';

// Database connection
require_once 'includes/config.php';

// Initialize variables
$classes = [];
$streams = [];
$teachers = [];
$staff = [];
$students = [];
$recentSmsHistory = [];
$school = [];
$api_key = null;
$api_secret = null;

define('SMS_COST', 1);

// Database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch school info with API key
$schoolQuery = $conn->prepare("
    SELECT s.*, a.api_key, a.api_secret, a.status as api_status
    FROM tblschoolinfo s
    LEFT JOIN api_keys a ON s.id = a.user_id AND a.status = 'active'
    WHERE s.id = ?
");
$schoolQuery->bind_param("i", $school_id);
$schoolQuery->execute();
$schoolResult = $schoolQuery->get_result();
$school = $schoolResult->fetch_assoc();
$schoolQuery->close();

$api_key = $school['api_key'] ?? null;
$api_secret = $school['api_secret'] ?? null;
$sms_balance = $school['sms_balance'] ?? 0;
$has_valid_api = !empty($api_key) && $school['api_status'] === 'active';

// Fetch classes
$classesQuery = $conn->prepare("SELECT id, class_level as display_name FROM tblclasses WHERE school_id = ? ORDER BY class_level");
$classesQuery->bind_param("i", $school_id);
$classesQuery->execute();
$classesResult = $classesQuery->get_result();
while ($class = $classesResult->fetch_assoc()) {
    $classes[] = $class;
}
$classesQuery->close();

// Fetch streams
$streamsQuery = $conn->prepare("
    SELECT s.id, s.stream_name, s.class_id, c.class_level
    FROM tblstreams s
    JOIN tblclasses c ON s.class_id = c.id
    WHERE c.school_id = ?
    ORDER BY s.stream_name
");
$streamsQuery->bind_param("i", $school_id);
$streamsQuery->execute();
$streamsResult = $streamsQuery->get_result();
while ($stream = $streamsResult->fetch_assoc()) {
    $streams[] = $stream;
}
$streamsQuery->close();

// Fetch teachers
$teachersQuery = $conn->prepare("
    SELECT id, firstname, secondname, lastname, phonenumber, email
    FROM tblteachers 
    WHERE school_id = ? AND status = 'Active' AND is_deleted = 0
    ORDER BY firstname
");
$teachersQuery->bind_param("i", $school_id);
$teachersQuery->execute();
$teachersResult = $teachersQuery->get_result();
$teachers = [];
$teachersWithPhone = 0;
while ($teacher = $teachersResult->fetch_assoc()) {
    $teachers[] = $teacher;
    if (!empty($teacher['phonenumber'])) {
        $teachersWithPhone++;
    }
}
$teachersQuery->close();

// Fetch staff
$staffQuery = $conn->prepare("
    SELECT id, firstname, secondname, lastname, phonenumber as phone, email, role
    FROM tblteachers 
    WHERE school_id = ? AND status = 'Active' AND is_deleted = 0
    ORDER BY firstname
");
$staffQuery->bind_param("i", $school_id);
$staffQuery->execute();
$staffResult = $staffQuery->get_result();
$staff = [];
$staffWithPhone = 0;
while ($staff_member = $staffResult->fetch_assoc()) {
    $full_name = trim($staff_member['firstname'] . ' ' . ($staff_member['secondname'] ?? '') . ' ' . ($staff_member['lastname'] ?? ''));
    $staff_member['full_name'] = $full_name;
    $staff[] = $staff_member;
    if (!empty($staff_member['phone'])) {
        $staffWithPhone++;
    }
}
$staffQuery->close();

// Fetch students
$studentsQuery = $conn->prepare("
    SELECT id, AdmNo as admission_no, FirstName as firstname, SecondName as secondname, LastName as lastname, GuardianPhone as phone, class_id, StreamId as stream_id
    FROM tblstudents 
    WHERE school_id = ? AND Status = 'Active'
    ORDER BY FirstName
");
$studentsQuery->bind_param("i", $school_id);
$studentsQuery->execute();
$studentsResult = $studentsQuery->get_result();
$students = [];
$studentsWithPhone = 0;
while ($student = $studentsResult->fetch_assoc()) {
    $students[] = $student;
    if (!empty($student['phone'])) {
        $studentsWithPhone++;
    }
}
$studentsQuery->close();

$school_name = $school['school_name'] ?? $_SESSION['school_name'] ?? 'EduScore';

// Fetch SMS history
$smsHistoryQuery = $conn->prepare("
    SELECT id, message_id, recipient_phone, message_content, status, cost, created_at
    FROM sms_logs 
    WHERE school_id = ? 
    ORDER BY created_at DESC 
    LIMIT 50
");
$smsHistoryQuery->bind_param("i", $school_id);
$smsHistoryQuery->execute();
$smsHistoryResult = $smsHistoryQuery->get_result();
$sms_history = [];
while ($log = $smsHistoryResult->fetch_assoc()) {
    if (!isset($sms_history[$log['message_id']])) {
        $sms_history[$log['message_id']] = [
            'id' => $log['id'],
            'message_id' => $log['message_id'],
            'message_content' => $log['message_content'],
            'status' => $log['status'],
            'total_cost' => 0,
            'recipient_count' => 0,
            'created_at' => $log['created_at'],
            'recipients' => []
        ];
    }
    $sms_history[$log['message_id']]['recipient_count']++;
    $sms_history[$log['message_id']]['total_cost'] += floatval($log['cost'] ?? 0);
    $sms_history[$log['message_id']]['recipients'][] = $log['recipient_phone'];
}
$smsHistoryQuery->close();

$recentSmsHistory = array_values($sms_history);
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMS Center - EduScore</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="images/logo.png" />
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fa; color: #1e293b; }
        .main-content { margin-left: 280px; min-height: 100vh; padding: 90px 2rem 2rem; transition: margin-left 0.3s; }
        @media (max-width: 992px) { .main-content { margin-left: 0; padding: 80px 1rem 1rem; } }
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.25rem; margin-bottom: 2rem; }
        .stat-card { background: white; border-radius: 1rem; padding: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05); transition: all 0.2s; border: 1px solid #e2e8f0; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .stat-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem; }
        .stat-icon { width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; }
        .stat-icon.parents { background: #e6f7e6; color: #10b981; }
        .stat-icon.teachers { background: #fff3e0; color: #f59e0b; }
        .stat-icon.staff { background: #e0f2fe; color: #3b82f6; }
        .stat-icon.balance { background: #e6e9ff; color: #6366f1; }
        .stat-value { font-size: 1.75rem; font-weight: 700; color: #0f172a; line-height: 1.2; }
        .stat-label { font-size: 0.8rem; color: #64748b; font-weight: 500; }
        .balance-card { background: linear-gradient(135deg, #1e3a8a, #2563eb); border-radius: 1rem; padding: 1.25rem 1.5rem; display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; }
        .balance-info h3 { font-size: 0.85rem; font-weight: 500; opacity: 0.9; margin-bottom: 0.25rem; color: white; }
        .balance-info .amount { font-size: 2rem; font-weight: 700; color: white; }
        .balance-info small { font-size: 0.7rem; opacity: 0.8; color: white; }
        .balance-icon { width: 50px; height: 50px; background: rgba(255,255,255,0.2); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: white; }
        .sms-layout { display: grid; grid-template-columns: 1fr 380px; gap: 1.5rem; }
        @media (max-width: 1024px) { .sms-layout { grid-template-columns: 1fr; } .stats-grid { grid-template-columns: repeat(2, 1fr); } }
        .card { background: white; border-radius: 1rem; border: 1px solid #e2e8f0; overflow: hidden; margin-bottom: 1.5rem; }
        .card-header { padding: 1rem 1.25rem; border-bottom: 1px solid #e2e8f0; background: #fafbfc; }
        .card-header h2 { font-size: 1rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; color: #1e293b; }
        .card-header i { color: #10b981; font-size: 1rem; }
        .card-body { padding: 1.25rem; }
        .recipient-buttons { display: flex; gap: 0.75rem; margin-bottom: 1.25rem; flex-wrap: wrap; }
        .recipient-btn { flex: 1; display: flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.75rem; background: white; border: 1.5px solid #e2e8f0; border-radius: 0.75rem; cursor: pointer; transition: all 0.2s; font-weight: 500; font-size: 0.9rem; color: #475569; }
        .recipient-btn i { font-size: 1rem; }
        .recipient-btn:hover { border-color: #3b82f6; background: #eff6ff; }
        .recipient-btn.active { border-color: #10b981; background: #f0fdf4; color: #10b981; }
        .recipient-badge { background: #e2e8f0; padding: 0.15rem 0.5rem; border-radius: 20px; font-size: 0.7rem; font-weight: 600; color: #475569; }
        .selection-tabs { display: flex; gap: 0.5rem; margin-bottom: 1rem; flex-wrap: wrap; }
        .tab-btn { padding: 0.5rem 1rem; background: #f1f5f9; border: none; border-radius: 2rem; cursor: pointer; font-size: 0.8rem; font-weight: 500; color: #475569; transition: all 0.2s; }
        .tab-btn.active { background: #10b981; color: white; }
        .item-list { max-height: 280px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 0.75rem; }
        .list-item { display: flex; align-items: center; padding: 0.75rem 1rem; border-bottom: 1px solid #e2e8f0; cursor: pointer; transition: background 0.2s; }
        .list-item:hover { background: #f8fafc; }
        .list-item input { margin-right: 0.75rem; }
        .item-info { flex: 1; }
        .item-name { font-weight: 500; font-size: 0.85rem; margin-bottom: 0.2rem; }
        .item-detail { font-size: 0.7rem; color: #64748b; }
        .select-all { margin-bottom: 0.75rem; padding: 0.5rem 1rem; background: #f1f5f9; border: none; border-radius: 0.5rem; cursor: pointer; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 0.5rem; }
        .filter-select { width: 100%; padding: 0.6rem 0.5rem; border: 1px solid #e2e8f0; border-radius: 0.5rem; font-size: 0.8rem; background: white; cursor: pointer; }
        .message-textarea { width: 100%; padding: 1rem; border: 1.5px solid #e2e8f0; border-radius: 0.75rem; font-family: inherit; font-size: 0.9rem; resize: vertical; min-height: 120px; transition: border 0.2s; }
        .message-textarea:focus { outline: none; border-color: #10b981; }
        .shortcodes { display: flex; flex-wrap: wrap; gap: 0.5rem; margin: 1rem 0; }
        .shortcode { background: #f1f5f9; padding: 0.3rem 0.75rem; border-radius: 20px; font-size: 0.7rem; font-weight: 500; cursor: pointer; transition: all 0.2s; color: #10b981; }
        .shortcode:hover { background: #10b981; color: white; }
        .sms-stats { display: flex; justify-content: space-between; align-items: center; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #e2e8f0; font-size: 0.8rem; color: #64748b; }
        .preview-content { margin-top: 1rem; padding: 0.75rem; background: #f8fafc; border-radius: 0.5rem; font-size: 0.85rem; border-left: 3px solid #10b981; }
        .action-buttons { display: flex; gap: 1rem; margin-top: 1.5rem; }
        .btn { flex: 1; padding: 0.75rem; border: none; border-radius: 0.75rem; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 0.5rem; }
        .btn-primary { background: linear-gradient(135deg, #1e3a8a, #2563eb); color: white; }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(37,99,235,0.3); }
        .btn-secondary { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
        .btn-secondary:hover { background: #e2e8f0; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .recipient-badge-info { margin-top: 1rem; padding: 0.75rem; background: #eff6ff; border-radius: 0.75rem; font-size: 0.85rem; }
        .history-panel { background: white; border-radius: 1rem; border: 1px solid #e2e8f0; position: sticky; top: 90px; }
        .history-header { padding: 1rem 1.25rem; border-bottom: 1px solid #e2e8f0; }
        .history-header h3 { font-size: 1rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; }
        .history-filters { padding: 0.75rem 1.25rem; border-bottom: 1px solid #e2e8f0; display: flex; gap: 0.75rem; }
        .search-box { flex: 1; position: relative; }
        .search-box input { width: 100%; padding: 0.5rem 0.75rem 0.5rem 2rem; border: 1px solid #e2e8f0; border-radius: 0.5rem; font-size: 0.8rem; }
        .search-box i { position: absolute; left: 0.6rem; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 0.8rem; }
        .history-list { max-height: 500px; overflow-y: auto; }
        .history-item { padding: 1rem 1.25rem; border-bottom: 1px solid #e2e8f0; transition: background 0.2s; }
        .history-item:hover { background: #fafbfc; }
        .history-message { font-size: 0.85rem; color: #1e293b; margin-bottom: 0.5rem; line-height: 1.4; }
        .history-meta { display: flex; justify-content: space-between; align-items: center; font-size: 0.7rem; color: #64748b; }
        .history-badge { padding: 0.2rem 0.5rem; border-radius: 20px; font-size: 0.65rem; font-weight: 600; }
        .badge-success { background: #d1fae5; color: #10b981; }
        .badge-failed { background: #fee2e2; color: #ef4444; }
        .empty-state { text-align: center; padding: 2rem; color: #94a3b8; }
        .api-warning { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 0.75rem 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem; }
        .modal { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 1000; }
        .modal.active { display: flex; }
        .modal-container { background: white; border-radius: 1rem; width: 90%; max-width: 450px; max-height: 90vh; overflow: auto; }
        .modal-header { padding: 1.25rem; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
        .modal-header h3 { font-size: 1.1rem; font-weight: 600; }
        .modal-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #94a3b8; }
        .modal-body { padding: 1.25rem; }
        .detail-item { padding: 0.75rem; background: #f8fafc; border-radius: 0.5rem; margin-bottom: 0.75rem; border-left: 3px solid #10b981; }
        .modal-footer { padding: 1rem 1.25rem; border-top: 1px solid #e2e8f0; display: flex; gap: 0.75rem; justify-content: flex-end; }
        .toast-container { position: fixed; bottom: 2rem; right: 2rem; z-index: 1100; }
        .toast { background: white; padding: 0.75rem 1.25rem; border-radius: 0.5rem; box-shadow: 0 4px 12px rgba(0,0,0,0.15); display: flex; align-items: center; gap: 0.75rem; animation: slideIn 0.3s ease; margin-bottom: 0.5rem; }
        .toast-success { border-left: 3px solid #10b981; }
        .toast-error { border-left: 3px solid #ef4444; }
        .toast-warning { border-left: 3px solid #f59e0b; }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        .loading-spinner { width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-top-color: white; border-radius: 50%; animation: spin 0.8s linear infinite; display: inline-block; margin-right: 0.5rem; }
        @keyframes spin { to { transform: rotate(360deg); } }
        @media (max-width: 768px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } .recipient-buttons { flex-direction: column; } }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    <?php include 'includes/header.php'; ?>

    <div class="main-content">
        <div class="balance-card">
            <div class="balance-info">
                <h3>SMS Balance</h3>
                <div class="amount"><?php echo number_format($sms_balance); ?></div>
                <small>1 SMS = 1 Credit</small>
            </div>
            <div class="balance-icon"><i class="fas fa-comment-dots"></i></div>
        </div>

        <div class="stats-grid">
            <div class="stat-card"><div class="stat-header"><div class="stat-icon parents"><i class="fas fa-user-friends"></i></div></div><div class="stat-value"><?php echo number_format($studentsWithPhone); ?></div><div class="stat-label">Parents</div></div>
            <div class="stat-card"><div class="stat-header"><div class="stat-icon teachers"><i class="fas fa-chalkboard-user"></i></div></div><div class="stat-value"><?php echo number_format($teachersWithPhone); ?></div><div class="stat-label">Teachers</div></div>
            <div class="stat-card"><div class="stat-header"><div class="stat-icon staff"><i class="fas fa-users"></i></div></div><div class="stat-value"><?php echo number_format($staffWithPhone); ?></div><div class="stat-label">Staff</div></div>
            <div class="stat-card"><div class="stat-header"><div class="stat-icon balance"><i class="fas fa-coins"></i></div></div><div class="stat-value"><?php echo number_format($sms_balance); ?></div><div class="stat-label">Credits</div></div>
        </div>

        <?php if (!$has_valid_api): ?>
        <div class="api-warning">
            <i class="fas fa-exclamation-triangle" style="color: #f59e0b;"></i>
            <span style="margin-left: 0.5rem; font-size: 0.85rem;">API key not configured. Contact administrator.</span>
        </div>
        <?php endif; ?>

        <div class="sms-layout">
            <div>
                <div class="card">
                    <div class="card-header"><h2><i class="fas fa-users"></i> Select Recipients</h2></div>
                    <div class="card-body">
                        <div class="recipient-buttons">
                            <div class="recipient-btn active" data-recipient="parents"><i class="fas fa-user-friends"></i> Parents <span class="recipient-badge"><?php echo $studentsWithPhone; ?></span></div>
                            <div class="recipient-btn" data-recipient="teachers"><i class="fas fa-chalkboard-user"></i> Teachers <span class="recipient-badge"><?php echo $teachersWithPhone; ?></span></div>
                            <div class="recipient-btn" data-recipient="staff"><i class="fas fa-users"></i> Staff <span class="recipient-badge"><?php echo $staffWithPhone; ?></span></div>
                        </div>

                        <div id="parentsPanel">
                            <div class="selection-tabs">
                                <button class="tab-btn active" data-parent-type="all">All</button>
                                <button class="tab-btn" data-parent-type="class">By Class</button>
                                <button class="tab-btn" data-parent-type="stream">By Stream</button>
                                <button class="tab-btn" data-parent-type="individual">Select</button>
                            </div>
                            <div id="parentsContent">
                                <div id="allView"><div class="recipient-badge-info"><i class="fas fa-check-circle"></i> All <?php echo $studentsWithPhone; ?> students with phone numbers</div></div>
                                <div id="classView" style="display: none;">
                                    <select id="parentClassSelect" class="filter-select" style="margin-bottom:1rem;"><option value="">Select Class</option><?php foreach ($classes as $class): ?><option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['display_name']); ?></option><?php endforeach; ?></select>
                                    <div id="classStudentsList"></div>
                                </div>
                                <div id="streamView" style="display: none;">
                                    <select id="streamClassSelect" class="filter-select" style="margin-bottom:0.5rem;"><option value="">Select Class</option><?php foreach ($classes as $class): ?><option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['display_name']); ?></option><?php endforeach; ?></select>
                                    <select id="streamSelect" class="filter-select" style="margin-bottom:1rem;" disabled><option value="">Select Stream</option></select>
                                    <div id="streamStudentsList"></div>
                                </div>
                                <div id="individualView" style="display: none;">
                                    <button class="select-all" id="selectAllStudents"><i class="fas fa-check-double"></i> Select All</button>
                                    <div class="item-list" id="studentList">
                                        <?php foreach ($students as $student): ?>
                                        <div class="list-item" data-phone="<?php echo $student['phone']; ?>">
                                            <input type="checkbox" value="<?php echo $student['id']; ?>">
                                            <div class="item-info">
                                                <div class="item-name"><?php echo htmlspecialchars($student['firstname'] . ' ' . ($student['secondname'] ?? '') . ' ' . ($student['lastname'] ?? '')); ?></div>
                                                <div class="item-detail">Adm: <?php echo htmlspecialchars($student['admission_no'] ?? 'N/A'); ?> | <?php echo htmlspecialchars($student['phone'] ?? 'No phone'); ?></div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="teachersPanel" style="display: none;">
                            <button class="select-all" id="selectAllTeachers"><i class="fas fa-check-double"></i> Select All</button>
                            <div class="item-list" id="teachersList">
                                <?php foreach ($teachers as $teacher): ?>
                                <div class="list-item" data-phone="<?php echo $teacher['phonenumber']; ?>">
                                    <input type="checkbox" value="<?php echo $teacher['id']; ?>">
                                    <div class="item-info">
                                        <div class="item-name"><?php echo htmlspecialchars($teacher['firstname'] . ' ' . ($teacher['secondname'] ?? '') . ' ' . ($teacher['lastname'] ?? '')); ?></div>
                                        <div class="item-detail"><?php echo htmlspecialchars($teacher['phonenumber'] ?: 'No phone'); ?></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div id="staffPanel" style="display: none;">
                            <button class="select-all" id="selectAllStaff"><i class="fas fa-check-double"></i> Select All</button>
                            <div class="item-list" id="staffList">
                                <?php foreach ($staff as $member): ?>
                                <div class="list-item" data-phone="<?php echo $member['phone']; ?>">
                                    <input type="checkbox" value="<?php echo $member['id']; ?>">
                                    <div class="item-info">
                                        <div class="item-name"><?php echo htmlspecialchars($member['full_name']); ?></div>
                                        <div class="item-detail"><?php echo htmlspecialchars($member['phone'] ?: 'No phone'); ?> • <?php echo htmlspecialchars(ucfirst($member['role'] ?? 'Teacher')); ?></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="recipient-badge-info"><i class="fas fa-users"></i> Selected: <strong id="selectedCount">0</strong> recipients</div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h2><i class="fas fa-edit"></i> Compose Message</h2></div>
                    <div class="card-body">
                        <textarea id="messageText" class="message-textarea" placeholder="Type your message here..." <?php echo !$has_valid_api ? 'disabled' : ''; ?>></textarea>
                        <div class="shortcodes">
                            <span class="shortcode" data-shortcode="{STUDENT_NAME}">{STUDENT_NAME}</span>
                            <span class="shortcode" data-shortcode="{TEACHER_NAME}">{TEACHER_NAME}</span>
                            <span class="shortcode" data-shortcode="{STAFF_NAME}">{STAFF_NAME}</span>
                            <span class="shortcode" data-shortcode="{CLASS}">{CLASS}</span>
                            <span class="shortcode" data-shortcode="{ADMISSION_NO}">{ADMISSION_NO}</span>
                            <span class="shortcode" data-shortcode="{SCHOOL_NAME}">{SCHOOL_NAME}</span>
                        </div>
                        <div class="sms-stats">
                            <div><i class="fas fa-text-height"></i> <span id="charCount">0</span> chars</div>
                            <div><i class="fas fa-layer-group"></i> <span id="smsSegments">0</span> SMS</div>
                            <div><i class="fas fa-coins"></i> Cost: <span id="estimatedCost">0</span> KES</div>
                        </div>
                        <div class="preview-content"><strong>Preview:</strong><br><span id="messagePreview">Your message preview...</span></div>
                        <div class="action-buttons">
                            <button class="btn btn-secondary" id="previewBtn"><i class="fas fa-eye"></i> Preview</button>
                            <button class="btn btn-primary" id="sendSmsBtn" disabled><i class="fas fa-paper-plane"></i> Send</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="history-panel">
                <div class="history-header"><h3><i class="fas fa-history"></i> Recent Messages</h3></div>
                <div class="history-filters">
                    <div class="search-box"><i class="fas fa-search"></i><input type="text" id="historySearch" placeholder="Search..."></div>
                    <select id="statusFilter" class="filter-select" style="padding:0.5rem;"><option value="">All</option><option value="success">Success</option><option value="failed">Failed</option></select>
                </div>
                <div class="history-list" id="historyList">
                    <?php if (!empty($recentSmsHistory)): ?>
                        <?php foreach ($recentSmsHistory as $sms): ?>
                        <div class="history-item" data-status="<?php echo strtolower($sms['status']); ?>" data-message="<?php echo strtolower(htmlspecialchars($sms['message_content'])); ?>">
                            <div class="history-message"><?php echo htmlspecialchars(substr($sms['message_content'], 0, 60)) . (strlen($sms['message_content']) > 60 ? '...' : ''); ?></div>
                            <div class="history-meta">
                                <span><?php echo date('d/m/Y H:i', strtotime($sms['created_at'])); ?></span>
                                <span><?php echo $sms['recipient_count']; ?> recipients</span>
                                <span class="history-badge badge-<?php echo strtolower($sms['status']); ?>"><?php echo ucfirst($sms['status']); ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state"><i class="fas fa-sms" style="font-size:2rem;opacity:0.5;"></i><p style="margin-top:0.5rem;">No messages sent yet</p></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="modal" id="confirmModal">
        <div class="modal-container">
            <div class="modal-header"><h3><i class="fas fa-paper-plane"></i> Send SMS</h3><button class="modal-close" id="closeModalBtn">&times;</button></div>
            <div class="modal-body">
                <div class="detail-item"><strong>Recipients:</strong> <span id="modalRecipients">0</span></div>
                <div class="detail-item"><strong>Total Cost:</strong> KES <span id="modalCost">0</span></div>
                <div class="detail-item"><strong>Balance After:</strong> KES <span id="modalBalanceAfter">0</span></div>
                <div style="margin-top:1rem;padding:0.75rem;background:#f8fafc;border-radius:0.5rem;"><strong>Message:</strong><br><span id="modalMessage" style="font-size:0.85rem;"></span></div>
            </div>
            <div class="modal-footer"><button class="btn btn-secondary" id="cancelModalBtn">Cancel</button><button class="btn btn-primary" id="confirmSendBtn">Send</button></div>
        </div>
    </div>

    <div id="toastContainer"></div>

    <script src="js/session_timeout.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            const state = {
                recipientType: 'parents',
                parentSelectionType: 'all',
                smsBalance: <?php echo $sms_balance; ?>,
                schoolName: '<?php echo htmlspecialchars($school_name); ?>',
                schoolId: <?php echo $school_id; ?>,
                apiKey: '<?php echo $api_key; ?>',
                apiSecret: '<?php echo $api_secret; ?>',
                hasValidApi: <?php echo $has_valid_api ? 'true' : 'false'; ?>,
                smsCost: 1
            };

            const streams = <?php echo json_encode($streams); ?>;
            const students = <?php echo json_encode($students); ?>;

            function showToast(message, type = 'success') {
                const toast = $(`<div class="toast toast-${type}"><i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i><span>${message}</span></div>`);
                $('#toastContainer').append(toast);
                setTimeout(() => toast.fadeOut(300, () => toast.remove()), 3000);
            }

            function updateSmsCounter() {
                const text = $('#messageText').val();
                const length = text.length;
                const segments = Math.ceil(length / 160);
                const count = parseInt($('#selectedCount').text()) || 0;
                const totalCost = count * segments * state.smsCost;
                $('#charCount').text(length);
                $('#smsSegments').text(segments);
                $('#estimatedCost').text(totalCost);
                $('#sendSmsBtn').prop('disabled', totalCost === 0 || !text.trim() || !state.hasValidApi);
                let preview = text || '';
                preview = preview.replace(/{STUDENT_NAME}/g, 'John Doe');
                preview = preview.replace(/{TEACHER_NAME}/g, 'Mr. Smith');
                preview = preview.replace(/{STAFF_NAME}/g, 'Staff Member');
                preview = preview.replace(/{CLASS}/g, 'Grade 5');
                preview = preview.replace(/{ADMISSION_NO}/g, 'STD001');
                preview = preview.replace(/{SCHOOL_NAME}/g, state.schoolName);
                $('#messagePreview').text(preview || 'Your message preview...');
            }

            function loadRecipientCount() {
                let count = 0;
                if (state.recipientType === 'parents') {
                    if (state.parentSelectionType === 'all') count = students.filter(s => s.phone).length;
                    else if (state.parentSelectionType === 'individual') count = $('#studentList input:checked').length;
                    else if (state.parentSelectionType === 'class') count = $('.student-checkbox:checked').length;
                    else if (state.parentSelectionType === 'stream') count = $('.stream-student-checkbox:checked').length;
                } else if (state.recipientType === 'teachers') count = $('#teachersList input:checked').length;
                else if (state.recipientType === 'staff') count = $('#staffList input:checked').length;
                $('#selectedCount').text(count);
                updateSmsCounter();
            }

            $('.recipient-btn').on('click', function() {
                const type = $(this).data('recipient');
                state.recipientType = type;
                $('.recipient-btn').removeClass('active');
                $(this).addClass('active');
                $('#parentsPanel, #teachersPanel, #staffPanel').hide();
                if (type === 'parents') $('#parentsPanel').show();
                else if (type === 'teachers') $('#teachersPanel').show();
                else if (type === 'staff') $('#staffPanel').show();
                loadRecipientCount();
            });

            $('.tab-btn').on('click', function() {
                const type = $(this).data('parent-type');
                state.parentSelectionType = type;
                $('.tab-btn').removeClass('active');
                $(this).addClass('active');
                $('#allView, #classView, #streamView, #individualView').hide();
                if (type === 'all') $('#allView').show();
                else if (type === 'class') $('#classView').show();
                else if (type === 'stream') $('#streamView').show();
                else if (type === 'individual') $('#individualView').show();
                loadRecipientCount();
            });

            $('#selectAllStudents, #selectAllTeachers, #selectAllStaff').on('click', function() {
                $(this).closest('.card-body').find('input[type="checkbox"]').prop('checked', true);
                loadRecipientCount();
            });

            $('.item-list').on('change', 'input', function() { loadRecipientCount(); });

            $('#streamClassSelect').on('change', function() {
                const classId = $(this).val();
                const streamSelect = $('#streamSelect');
                if (!classId) { streamSelect.prop('disabled', true).html('<option value="">Select Stream</option>'); return; }
                const classStreams = streams.filter(s => s.class_id == classId);
                let options = '<option value="">Select Stream</option>';
                classStreams.forEach(stream => options += `<option value="${stream.id}">${stream.stream_name}</option>`);
                streamSelect.html(options).prop('disabled', false);
            });

            $('#parentClassSelect, #streamSelect').on('change', function() { loadRecipientCount(); });
            $('#messageText').on('input', updateSmsCounter);
            $('.shortcode').on('click', function() {
                const shortcode = $(this).data('shortcode');
                const textarea = $('#messageText');
                const text = textarea.val();
                const cursorPos = textarea.prop('selectionStart');
                textarea.val(text.substring(0, cursorPos) + shortcode + text.substring(cursorPos));
                textarea.focus();
                updateSmsCounter();
            });

            $('#previewBtn').on('click', function() { if (!$('#messageText').val().trim()) showToast('Please enter a message', 'warning'); else updateSmsCounter(); });

            $('#sendSmsBtn').on('click', function() {
                const count = parseInt($('#selectedCount').text());
                const cost = parseInt($('#estimatedCost').text());
                if (count === 0) { showToast('Select recipients', 'warning'); return; }
                if (cost > state.smsBalance) { showToast('Insufficient credits', 'error'); return; }
                $('#modalRecipients').text(count);
                $('#modalCost').text(cost);
                $('#modalBalanceAfter').text(state.smsBalance - cost);
                $('#modalMessage').text($('#messagePreview').text());
                $('#confirmModal').addClass('active');
            });

            $('#closeModalBtn, #cancelModalBtn').on('click', function() { $('#confirmModal').removeClass('active'); });

            $('#confirmSendBtn').on('click', async function() {
                let phoneNumbers = [];
                if (state.recipientType === 'parents') {
                    if (state.parentSelectionType === 'all') phoneNumbers = students.filter(s => s.phone).map(s => s.phone);
                    else if (state.parentSelectionType === 'individual') $('#studentList input:checked').each(function() { const p = $(this).closest('.list-item').data('phone'); if(p) phoneNumbers.push(p); });
                    else if (state.parentSelectionType === 'class') $('.student-checkbox:checked').each(function() { const p = $(this).closest('.list-item').data('phone'); if(p) phoneNumbers.push(p); });
                    else if (state.parentSelectionType === 'stream') $('.stream-student-checkbox:checked').each(function() { const p = $(this).closest('.list-item').data('phone'); if(p) phoneNumbers.push(p); });
                } else if (state.recipientType === 'teachers') { $('#teachersList input:checked').each(function() { const p = $(this).closest('.list-item').data('phone'); if(p) phoneNumbers.push(p); }); }
                else if (state.recipientType === 'staff') { $('#staffList input:checked').each(function() { const p = $(this).closest('.list-item').data('phone'); if(p) phoneNumbers.push(p); }); }
                
                if (phoneNumbers.length === 0) { showToast('No valid phone numbers', 'warning'); return; }
                $('#confirmSendBtn').html('<div class="loading-spinner"></div> Sending...').prop('disabled', true);
                try {
                    const response = await $.ajax({
                        url: '/api/school_sms.php/send',
                        method: 'POST',
                        headers: { 'X-API-Key': state.apiKey, 'X-API-Secret': state.apiSecret, 'Content-Type': 'application/json' },
                        data: JSON.stringify({ phone: phoneNumbers, message: $('#messageText').val(), sender_id: state.schoolName.substring(0,11) })
                    });
                    if (response.success) {
                        $('#confirmModal').removeClass('active');
                        showToast('SMS sent successfully!', 'success');
                        $('#messageText').val('');
                        updateSmsCounter();
                        setTimeout(() => location.reload(), 1500);
                    } else showToast(response.message || 'Failed to send', 'error');
                } catch (error) { showToast('Error: ' + (error.responseJSON?.message || error.message), 'error'); }
                finally { $('#confirmSendBtn').html('<i class="fas fa-paper-plane"></i> Send').prop('disabled', false); }
            });

            $('#historySearch, #statusFilter').on('input change', function() {
                const search = $('#historySearch').val().toLowerCase();
                const status = $('#statusFilter').val().toLowerCase();
                $('#historyList .history-item').each(function() {
                    const matchesSearch = $(this).data('message')?.includes(search) || false;
                    const matchesStatus = !status || $(this).data('status') === status;
                    $(this).toggle(matchesSearch && matchesStatus);
                });
            });

            updateSmsCounter();
            loadRecipientCount();
            $('#parentsPanel').show();
        });
    </script>
</body>
</html>