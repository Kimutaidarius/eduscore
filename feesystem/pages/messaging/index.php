<?php
session_start();
if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    header('Location: ../../login.php');
    exit;
}
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'finance') {
    header('Location: ../../login.php?error=access_denied');
    exit;
}

require_once('../../includes/config.php');

$school_id = $_SESSION['school_id'];
$school_name = $_SESSION['school_name'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;

include_once('../../includes/header.php');
include_once('../../includes/sidebar.php');

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
$has_valid_api = !empty($api_key) && ($school['api_status'] ?? '') === 'active';

// Fetch classes
$classes = [];
$classesQuery = $conn->prepare("SELECT id, class_level as display_name FROM tblclasses WHERE school_id = ? ORDER BY class_level");
$classesQuery->bind_param("i", $school_id);
$classesQuery->execute();
$classesResult = $classesQuery->get_result();
while ($class = $classesResult->fetch_assoc()) {
    $classes[] = $class;
}
$classesQuery->close();

// Fetch streams
$streams = [];
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
$teachers = [];
$teachersWithPhone = 0;
$teachersQuery = $conn->prepare("
    SELECT id, firstname, secondname, lastname, phonenumber, email
    FROM tblteachers 
    WHERE school_id = ? AND status = 'Active' AND is_deleted = 0
    ORDER BY firstname
");
$teachersQuery->bind_param("i", $school_id);
$teachersQuery->execute();
$teachersResult = $teachersQuery->get_result();
while ($teacher = $teachersResult->fetch_assoc()) {
    $full_name = trim($teacher['firstname'] . ' ' . ($teacher['secondname'] ?? '') . ' ' . ($teacher['lastname'] ?? ''));
    $teacher['full_name'] = $full_name;
    $teachers[] = $teacher;
    if (!empty($teacher['phonenumber'])) {
        $teachersWithPhone++;
    }
}
$teachersQuery->close();

// Fetch staff
$staff = [];
$staffWithPhone = 0;
$staffQuery = $conn->prepare("
    SELECT id, firstname, secondname, lastname, phonenumber as phone, email, role
    FROM tblteachers 
    WHERE school_id = ? AND status = 'Active' AND is_deleted = 0
    ORDER BY firstname
");
$staffQuery->bind_param("i", $school_id);
$staffQuery->execute();
$staffResult = $staffQuery->get_result();
while ($staff_member = $staffResult->fetch_assoc()) {
    $full_name = trim($staff_member['firstname'] . ' ' . ($staff_member['secondname'] ?? '') . ' ' . ($staff_member['lastname'] ?? ''));
    $staff_member['full_name'] = $full_name;
    $staff[] = $staff_member;
    if (!empty($staff_member['phone'])) {
        $staffWithPhone++;
    }
}
$staffQuery->close();

// Fetch students with phone numbers
$students = [];
$studentsWithPhone = 0;
$studentsQuery = $conn->prepare("
    SELECT id, AdmNo as admission_no, FirstName as firstname, SecondName as secondname, LastName as lastname, 
           GuardianPhone as phone, class_id, StreamId as stream_id
    FROM tblstudents 
    WHERE school_id = ? AND Status = 'Active' AND GuardianPhone IS NOT NULL AND GuardianPhone != ''
    ORDER BY FirstName
");
$studentsQuery->bind_param("i", $school_id);
$studentsQuery->execute();
$studentsResult = $studentsQuery->get_result();
while ($student = $studentsResult->fetch_assoc()) {
    $full_name = trim($student['firstname'] . ' ' . ($student['secondname'] ?? '') . ' ' . ($student['lastname'] ?? ''));
    $student['full_name'] = $full_name;
    $students[] = $student;
    $studentsWithPhone++;
}
$studentsQuery->close();

// Fetch SMS history
$sms_history = [];
$smsHistoryQuery = $conn->prepare("
    SELECT id, message_id, recipient_phone, recipient_name, recipient_type, message_content, status, cost, created_at
    FROM sms_logs 
    WHERE school_id = ? 
    ORDER BY created_at DESC 
    LIMIT 50
");
$smsHistoryQuery->bind_param("i", $school_id);
$smsHistoryQuery->execute();
$smsHistoryResult = $smsHistoryQuery->get_result();
while ($log = $smsHistoryResult->fetch_assoc()) {
    if (!isset($sms_history[$log['message_id']])) {
        $sms_history[$log['message_id']] = [
            'id' => $log['id'],
            'message_id' => $log['message_id'],
            'message_content' => $log['message_content'],
            'status' => $log['status'],
            'total_cost' => 0,
            'recipient_count' => 0,
            'created_at' => $log['created_at']
        ];
    }
    $sms_history[$log['message_id']]['recipient_count']++;
    $sms_history[$log['message_id']]['total_cost'] += floatval($log['cost'] ?? 0);
}
$smsHistoryQuery->close();
$conn->close();

$recentSmsHistory = array_values($sms_history);
?>

<style>
/* Messaging Styles */
.stats-card {
    transition: all 0.3s ease;
}
.stats-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
}
.filter-input, .filter-select {
    transition: all 0.2s ease;
}
.filter-input:focus, .filter-select:focus {
    border-color: #4f46e5;
    ring: 2px solid #4f46e5;
}
.recipient-btn {
    transition: all 0.2s ease;
}
.recipient-btn.active {
    background-color: #eef2ff;
    border-color: #4f46e5;
    color: #4f46e5;
}
.tab-btn {
    transition: all 0.2s ease;
}
.tab-btn.active {
    background-color: #4f46e5;
    color: white;
}
.list-item:hover {
    background-color: #f9fafb;
}
.shortcode {
    transition: all 0.2s ease;
}
.shortcode:hover {
    background-color: #4f46e5;
    color: white;
}
.history-item {
    transition: background 0.2s;
}
.history-item:hover {
    background-color: #f9fafb;
}
.toast {
    position: fixed;
    bottom: 2rem;
    right: 2rem;
    background: white;
    padding: 0.75rem 1.25rem;
    border-radius: 0.5rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    display: flex;
    align-items: center;
    gap: 0.75rem;
    z-index: 1100;
    animation: slideIn 0.3s ease;
}
.toast-success { border-left: 3px solid #10b981; }
.toast-error { border-left: 3px solid #ef4444; }
.toast-warning { border-left: 3px solid #f59e0b; }
@keyframes slideIn {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}
.loading-spinner {
    width: 16px;
    height: 16px;
    border: 2px solid rgba(255,255,255,0.3);
    border-top-color: white;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    display: inline-block;
}
@keyframes spin {
    to { transform: rotate(360deg); }
}
</style>

<main class="main-content flex-grow flex flex-col">
  <header class="bg-white shadow-sm dark:bg-gray-800 dark:border-gray-700">
    <div class="px-4 py-3 flex justify-between items-center">
      <div class="flex items-center">
        <button id="mobile-toggle" class="mr-4 text-gray-500 hover:text-indigo-600 focus:outline-none lg:hidden">
          <i class="fas fa-bars text-xl"></i>
        </button>
        <h1 id="page-title" class="text-xl font-semibold text-gray-800 dark:text-white">SMS Messaging Center</h1>
      </div>
      
      <div class="flex items-center space-x-4">
        <button id="theme-toggle" class="p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 focus:outline-none">
          <i class="fas fa-sun text-yellow-500 dark:hidden"></i>
          <i class="fas fa-moon text-blue-300 hidden dark:block"></i>
        </button>
        
        <div class="relative" id="user-menu-container">
          <button id="user-menu-button" class="flex items-center focus:outline-none">
            <img src="https://cdn.pixabay.com/photo/2015/10/05/22/37/blank-profile-picture-973460_960_720.png" alt="User Avatar" class="w-8 h-8 rounded-full mr-2">
            <span class="hidden md:block text-sm font-medium text-gray-700 dark:text-gray-200"><?php echo htmlspecialchars($_SESSION['email'] ?? 'User'); ?></span>
            <i class="fas fa-chevron-down text-xs ml-2 text-gray-500"></i>
          </button>
          
          <div id="user-dropdown" class="absolute right-0 mt-2 w-48 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg py-1 z-20 hidden">
            <a href="../../profile.php" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
              <i class="fas fa-user-circle mr-2"></i> My Profile
            </a>
            <div class="border-t border-gray-200 dark:border-gray-700 my-1"></div>
            <a href="../../logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-gray-100 dark:hover:bg-gray-700">
              <i class="fas fa-sign-out-alt mr-2"></i> Logout
            </a>
          </div>
        </div>
      </div>
    </div>
  </header>

  <div class="flex-grow p-4 md:p-6 overflow-auto">
    <!-- SMS Balance Card -->
    <div class="bg-gradient-to-r from-indigo-600 to-indigo-800 rounded-lg shadow-lg p-5 text-white mb-6">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-indigo-100 text-sm">SMS Balance</p>
          <p class="text-3xl font-bold mt-1" id="smsBalance"><?php echo number_format($sms_balance); ?></p>
          <small class="text-indigo-100">1 SMS = 1 Credit</small>
        </div>
        <i class="fas fa-comment-dots text-5xl opacity-30"></i>
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
      <div class="stats-card bg-gradient-to-r from-green-500 to-green-600 rounded-lg shadow-lg p-5 text-white">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-green-100 text-sm">Parents</p>
            <p class="text-3xl font-bold mt-1"><?php echo number_format($studentsWithPhone); ?></p>
          </div>
          <i class="fas fa-user-friends text-4xl opacity-50"></i>
        </div>
      </div>
      <div class="stats-card bg-gradient-to-r from-blue-500 to-blue-600 rounded-lg shadow-lg p-5 text-white">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-blue-100 text-sm">Teachers</p>
            <p class="text-3xl font-bold mt-1"><?php echo number_format($teachersWithPhone); ?></p>
          </div>
          <i class="fas fa-chalkboard-user text-4xl opacity-50"></i>
        </div>
      </div>
      <div class="stats-card bg-gradient-to-r from-purple-500 to-purple-600 rounded-lg shadow-lg p-5 text-white">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-purple-100 text-sm">Staff</p>
            <p class="text-3xl font-bold mt-1"><?php echo number_format($staffWithPhone); ?></p>
          </div>
          <i class="fas fa-users text-4xl opacity-50"></i>
        </div>
      </div>
    </div>

    <?php if (!$has_valid_api): ?>
    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6 rounded">
      <div class="flex items-center">
        <i class="fas fa-exclamation-triangle text-yellow-500 mr-3"></i>
        <span class="text-yellow-700">API key not configured. Please contact administrator to enable SMS sending.</span>
      </div>
    </div>
    <?php endif; ?>

    <!-- Main Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Left Column - SMS Composer -->
      <div class="lg:col-span-2 space-y-6">
        <!-- Recipient Selection Card -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
          <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 border-b">
            <h3 class="font-semibold text-gray-800 dark:text-white">
              <i class="fas fa-users mr-2 text-indigo-500"></i>Select Recipients
            </h3>
          </div>
          <div class="p-4">
            <!-- Recipient Type Buttons -->
            <div class="flex gap-3 mb-4">
              <button id="parentsTabBtn" class="recipient-btn flex-1 px-4 py-2 border rounded-lg text-center font-medium hover:bg-gray-50 active">
                <i class="fas fa-user-friends mr-2"></i>Parents
                <span class="ml-1 text-xs bg-gray-200 px-2 py-0.5 rounded-full"><?php echo $studentsWithPhone; ?></span>
              </button>
              <button id="teachersTabBtn" class="recipient-btn flex-1 px-4 py-2 border rounded-lg text-center font-medium hover:bg-gray-50">
                <i class="fas fa-chalkboard-user mr-2"></i>Teachers
                <span class="ml-1 text-xs bg-gray-200 px-2 py-0.5 rounded-full"><?php echo $teachersWithPhone; ?></span>
              </button>
              <button id="staffTabBtn" class="recipient-btn flex-1 px-4 py-2 border rounded-lg text-center font-medium hover:bg-gray-50">
                <i class="fas fa-users mr-2"></i>Staff
                <span class="ml-1 text-xs bg-gray-200 px-2 py-0.5 rounded-full"><?php echo $staffWithPhone; ?></span>
              </button>
            </div>

            <!-- Parents Panel -->
            <div id="parentsPanel">
              <div class="flex flex-wrap gap-2 mb-4">
                <button class="tab-btn px-3 py-1 bg-gray-100 rounded-full text-sm" data-parent-type="all">All</button>
                <button class="tab-btn px-3 py-1 bg-gray-100 rounded-full text-sm" data-parent-type="class">By Class</button>
                <button class="tab-btn px-3 py-1 bg-gray-100 rounded-full text-sm" data-parent-type="stream">By Stream</button>
                <button class="tab-btn px-3 py-1 bg-gray-100 rounded-full text-sm" data-parent-type="individual">Select</button>
              </div>

              <div id="parentsContent">
                <div id="allView">
                  <div class="bg-blue-50 p-3 rounded-lg text-sm">
                    <i class="fas fa-check-circle text-blue-500 mr-2"></i>
                    All <?php echo $studentsWithPhone; ?> parents with registered phone numbers
                  </div>
                </div>
                <div id="classView" style="display: none;">
                  <select id="parentClassSelect" class="w-full p-2 border rounded-lg mb-3">
                    <option value="">Select Class</option>
                    <?php foreach ($classes as $class): ?>
                      <option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['display_name']); ?></option>
                    <?php endforeach; ?>
                  </select>
                  <div id="classStudentsList" class="max-h-60 overflow-y-auto border rounded-lg"></div>
                </div>
                <div id="streamView" style="display: none;">
                  <select id="streamClassSelect" class="w-full p-2 border rounded-lg mb-2">
                    <option value="">Select Class</option>
                    <?php foreach ($classes as $class): ?>
                      <option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['display_name']); ?></option>
                    <?php endforeach; ?>
                  </select>
                  <select id="streamSelect" class="w-full p-2 border rounded-lg mb-3" disabled>
                    <option value="">Select Stream</option>
                  </select>
                  <div id="streamStudentsList" class="max-h-60 overflow-y-auto border rounded-lg"></div>
                </div>
                <div id="individualView" style="display: none;">
                  <button id="selectAllStudents" class="mb-3 px-3 py-1 bg-gray-100 rounded-lg text-sm"><i class="fas fa-check-double mr-1"></i> Select All</button>
                  <div id="studentList" class="max-h-60 overflow-y-auto border rounded-lg">
                    <?php foreach ($students as $student): ?>
                    <div class="student-item flex items-center p-2 border-b hover:bg-gray-50" data-id="<?php echo $student['id']; ?>" data-phone="<?php echo htmlspecialchars($student['phone']); ?>">
                      <input type="checkbox" class="student-checkbox mr-3" value="<?php echo $student['id']; ?>">
                      <div class="flex-1">
                        <div class="font-medium text-sm"><?php echo htmlspecialchars($student['full_name']); ?></div>
                        <div class="text-xs text-gray-500">Adm: <?php echo htmlspecialchars($student['admission_no']); ?> | <?php echo htmlspecialchars($student['phone']); ?></div>
                      </div>
                    </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              </div>
            </div>

            <!-- Teachers Panel -->
            <div id="teachersPanel" style="display: none;">
              <button id="selectAllTeachers" class="mb-3 px-3 py-1 bg-gray-100 rounded-lg text-sm"><i class="fas fa-check-double mr-1"></i> Select All</button>
              <div id="teachersList" class="max-h-60 overflow-y-auto border rounded-lg">
                <?php foreach ($teachers as $teacher): ?>
                <div class="teacher-item flex items-center p-2 border-b hover:bg-gray-50" data-id="<?php echo $teacher['id']; ?>" data-phone="<?php echo htmlspecialchars($teacher['phonenumber']); ?>">
                  <input type="checkbox" class="teacher-checkbox mr-3" value="<?php echo $teacher['id']; ?>">
                  <div class="flex-1">
                    <div class="font-medium text-sm"><?php echo htmlspecialchars($teacher['full_name']); ?></div>
                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($teacher['phonenumber'] ?: 'No phone'); ?></div>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
            </div>

            <!-- Staff Panel -->
            <div id="staffPanel" style="display: none;">
              <button id="selectAllStaff" class="mb-3 px-3 py-1 bg-gray-100 rounded-lg text-sm"><i class="fas fa-check-double mr-1"></i> Select All</button>
              <div id="staffList" class="max-h-60 overflow-y-auto border rounded-lg">
                <?php foreach ($staff as $member): ?>
                <div class="staff-item flex items-center p-2 border-b hover:bg-gray-50" data-id="<?php echo $member['id']; ?>" data-phone="<?php echo htmlspecialchars($member['phone']); ?>">
                  <input type="checkbox" class="staff-checkbox mr-3" value="<?php echo $member['id']; ?>">
                  <div class="flex-1">
                    <div class="font-medium text-sm"><?php echo htmlspecialchars($member['full_name']); ?></div>
                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($member['phone'] ?: 'No phone'); ?> • <?php echo htmlspecialchars(ucfirst($member['role'] ?? 'Staff')); ?></div>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
            </div>

            <div class="mt-4 p-3 bg-gray-50 rounded-lg">
              <i class="fas fa-users text-indigo-500 mr-2"></i>
              Selected: <strong id="selectedCount">0</strong> recipients
            </div>
          </div>
        </div>

        <!-- Message Composer Card -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
          <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 border-b">
            <h3 class="font-semibold text-gray-800 dark:text-white">
              <i class="fas fa-edit mr-2 text-green-500"></i>Compose Message
            </h3>
          </div>
          <div class="p-4">
            <textarea id="messageText" class="w-full p-3 border rounded-lg resize-y min-h-[120px]" placeholder="Type your message here..." <?php echo !$has_valid_api ? 'disabled' : ''; ?>></textarea>
            
            <div class="flex flex-wrap gap-2 my-3">
              <span class="shortcode px-2 py-1 bg-gray-100 rounded-full text-xs cursor-pointer" data-shortcode="{STUDENT_NAME}">{STUDENT_NAME}</span>
              <span class="shortcode px-2 py-1 bg-gray-100 rounded-full text-xs cursor-pointer" data-shortcode="{TEACHER_NAME}">{TEACHER_NAME}</span>
              <span class="shortcode px-2 py-1 bg-gray-100 rounded-full text-xs cursor-pointer" data-shortcode="{STAFF_NAME}">{STAFF_NAME}</span>
              <span class="shortcode px-2 py-1 bg-gray-100 rounded-full text-xs cursor-pointer" data-shortcode="{CLASS}">{CLASS}</span>
              <span class="shortcode px-2 py-1 bg-gray-100 rounded-full text-xs cursor-pointer" data-shortcode="{ADMISSION_NO}">{ADMISSION_NO}</span>
              <span class="shortcode px-2 py-1 bg-gray-100 rounded-full text-xs cursor-pointer" data-shortcode="{SCHOOL_NAME}">{SCHOOL_NAME}</span>
            </div>

            <div class="flex justify-between items-center text-sm text-gray-500 mb-3">
              <div><i class="fas fa-text-height mr-1"></i> <span id="charCount">0</span> chars</div>
              <div><i class="fas fa-layer-group mr-1"></i> <span id="smsSegments">0</span> SMS</div>
              <div><i class="fas fa-coins mr-1"></i> Cost: <span id="estimatedCost">0</span> KES</div>
            </div>

            <div class="p-3 bg-gray-50 rounded-lg mb-4">
              <strong>Preview:</strong><br>
              <span id="messagePreview" class="text-sm">Your message preview...</span>
            </div>

            <div class="flex gap-3">
              <button id="previewBtn" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                <i class="fas fa-eye mr-2"></i>Preview
              </button>
              <button id="sendSmsBtn" class="flex-1 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition disabled:opacity-50 disabled:cursor-not-allowed" <?php echo !$has_valid_api ? 'disabled' : ''; ?>>
                <i class="fas fa-paper-plane mr-2"></i>Send SMS
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Right Column - SMS History -->
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
        <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 border-b">
          <h3 class="font-semibold text-gray-800 dark:text-white">
            <i class="fas fa-history mr-2 text-purple-500"></i>Recent Messages
          </h3>
        </div>
        <div class="p-3 border-b">
          <div class="flex gap-2">
            <div class="flex-1 relative">
              <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 text-sm"></i>
              <input type="text" id="historySearch" placeholder="Search messages..." class="w-full pl-8 pr-3 py-1 border rounded-lg text-sm">
            </div>
            <select id="statusFilter" class="px-3 py-1 border rounded-lg text-sm">
              <option value="">All</option>
              <option value="success">Success</option>
              <option value="failed">Failed</option>
            </select>
          </div>
        </div>
        <div id="historyList" class="max-h-[500px] overflow-y-auto">
          <?php if (!empty($recentSmsHistory)): ?>
            <?php foreach ($recentSmsHistory as $sms): ?>
            <div class="history-item p-3 border-b" data-status="<?php echo strtolower($sms['status']); ?>" data-message="<?php echo strtolower(htmlspecialchars($sms['message_content'])); ?>">
              <div class="text-sm text-gray-800 mb-1"><?php echo htmlspecialchars(substr($sms['message_content'], 0, 60)) . (strlen($sms['message_content']) > 60 ? '...' : ''); ?></div>
              <div class="flex justify-between items-center text-xs text-gray-500">
                <span><?php echo date('d/m/Y H:i', strtotime($sms['created_at'])); ?></span>
                <span><?php echo $sms['recipient_count']; ?> recipients</span>
                <span class="px-2 py-0.5 rounded-full text-xs <?php echo strtolower($sms['status']) === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                  <?php echo ucfirst($sms['status']); ?>
                </span>
              </div>
            </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="text-center py-8 text-gray-500">
              <i class="fas fa-sms text-3xl mb-2 opacity-50"></i>
              <p>No messages sent yet</p>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</main>

<!-- Confirm Send Modal -->
<div id="confirmModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center">
  <div class="bg-white dark:bg-gray-800 w-full max-w-md rounded-lg shadow-xl mx-4">
    <div class="border-b px-6 py-4 flex justify-between items-center">
      <h3 class="text-lg font-semibold"><i class="fas fa-paper-plane text-indigo-500 mr-2"></i>Send SMS</h3>
      <button id="closeModalBtn" class="text-gray-400 hover:text-gray-500">&times;</button>
    </div>
    <div class="p-6">
      <div class="bg-gray-50 p-3 rounded-lg mb-3">
        <strong>Recipients:</strong> <span id="modalRecipients">0</span>
      </div>
      <div class="bg-gray-50 p-3 rounded-lg mb-3">
        <strong>Total Cost:</strong> KES <span id="modalCost">0</span>
      </div>
      <div class="bg-gray-50 p-3 rounded-lg mb-3">
        <strong>Balance After:</strong> KES <span id="modalBalanceAfter">0</span>
      </div>
      <div class="bg-gray-50 p-3 rounded-lg">
        <strong>Message:</strong><br>
        <span id="modalMessage" class="text-sm">-</span>
      </div>
    </div>
    <div class="border-t px-6 py-4 flex justify-end gap-3">
      <button id="cancelModalBtn" class="px-4 py-2 border rounded-lg hover:bg-gray-50">Cancel</button>
      <button id="confirmSendBtn" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">Send</button>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const schoolId = <?php echo json_encode($school_id); ?>;
const userId = <?php echo json_encode($user_id); ?>;
const apiKey = <?php echo json_encode($api_key); ?>;
const apiSecret = <?php echo json_encode($api_secret); ?>;
const hasValidApi = <?php echo $has_valid_api ? 'true' : 'false'; ?>;
const smsBalance = <?php echo $sms_balance; ?>;
const schoolName = <?php echo json_encode($school_name); ?>;

// Streams data from PHP
const streamsData = <?php echo json_encode($streams); ?>;
const studentsData = <?php echo json_encode($students); ?>;

let currentRecipientType = 'parents';
let currentParentType = 'all';
let currentClassStudents = [];
let currentStreamStudents = [];

// Show toast notification
function showToast(message, type = 'info') {
    const toast = $(`<div class="toast toast-${type}"><i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i><span>${message}</span></div>`);
    $('#toastContainer').append(toast);
    setTimeout(() => toast.fadeOut(300, () => toast.remove()), 3000);
}

// Update SMS counter and cost
function updateSmsCounter() {
    const text = $('#messageText').val();
    const length = text.length;
    const segments = Math.ceil(length / 160);
    const selectedCount = parseInt($('#selectedCount').text()) || 0;
    const totalCost = selectedCount * segments;
    
    $('#charCount').text(length);
    $('#smsSegments').text(segments);
    $('#estimatedCost').text(totalCost);
    
    // Preview
    let preview = text;
    preview = preview.replace(/{STUDENT_NAME}/g, 'John Doe');
    preview = preview.replace(/{TEACHER_NAME}/g, 'Mr. Smith');
    preview = preview.replace(/{STAFF_NAME}/g, 'Staff Member');
    preview = preview.replace(/{CLASS}/g, 'Grade 5');
    preview = preview.replace(/{ADMISSION_NO}/g, 'STD001');
    preview = preview.replace(/{SCHOOL_NAME}/g, schoolName);
    $('#messagePreview').text(preview || 'Your message preview...');
    
    // Enable/disable send button
    $('#sendSmsBtn').prop('disabled', selectedCount === 0 || !text.trim() || totalCost > smsBalance || !hasValidApi);
}

// Update recipient count based on selections
function updateRecipientCount() {
    let count = 0;
    if (currentRecipientType === 'parents') {
        if (currentParentType === 'all') {
            count = <?php echo $studentsWithPhone; ?>;
        } else if (currentParentType === 'class') {
            count = $('.class-student-checkbox:checked').length;
        } else if (currentParentType === 'stream') {
            count = $('.stream-student-checkbox:checked').length;
        } else if (currentParentType === 'individual') {
            count = $('.student-checkbox:checked').length;
        }
    } else if (currentRecipientType === 'teachers') {
        count = $('.teacher-checkbox:checked').length;
    } else if (currentRecipientType === 'staff') {
        count = $('.staff-checkbox:checked').length;
    }
    $('#selectedCount').text(count);
    updateSmsCounter();
}

// ==================== PARENT SELECTION LOGIC ====================
function showParentView(viewType) {
    currentParentType = viewType;
    $('#allView, #classView, #streamView, #individualView').hide();
    $(`#${viewType}View`).show();
    
    if (viewType === 'class') {
        loadClassStudents();
    } else if (viewType === 'stream') {
        loadStreamStudents();
    }
    updateRecipientCount();
}

function loadClassStudents() {
    const classId = $('#parentClassSelect').val();
    if (!classId) {
        $('#classStudentsList').html('<div class="p-3 text-center text-gray-500">Select a class</div>');
        return;
    }
    
    const classStudents = studentsData.filter(s => s.class_id == classId);
    if (classStudents.length === 0) {
        $('#classStudentsList').html('<div class="p-3 text-center text-gray-500">No students with phone numbers</div>');
        return;
    }
    
    let html = '<div class="divide-y">';
    classStudents.forEach(s => {
        html += `
            <div class="flex items-center p-2 hover:bg-gray-50">
                <input type="checkbox" class="class-student-checkbox mr-3" data-id="${s.id}" data-phone="${s.phone}">
                <div class="flex-1">
                    <div class="font-medium text-sm">${escapeHtml(s.full_name)}</div>
                    <div class="text-xs text-gray-500">Adm: ${escapeHtml(s.admission_no)} | ${escapeHtml(s.phone)}</div>
                </div>
            </div>
        `;
    });
    html += '</div>';
    $('#classStudentsList').html(html);
    $('.class-student-checkbox').on('change', updateRecipientCount);
}

function loadStreamStudents() {
    const streamId = $('#streamSelect').val();
    if (!streamId) {
        $('#streamStudentsList').html('<div class="p-3 text-center text-gray-500">Select a stream</div>');
        return;
    }
    
    const streamStudents = studentsData.filter(s => s.stream_id == streamId);
    if (streamStudents.length === 0) {
        $('#streamStudentsList').html('<div class="p-3 text-center text-gray-500">No students with phone numbers</div>');
        return;
    }
    
    let html = '<div class="divide-y">';
    streamStudents.forEach(s => {
        html += `
            <div class="flex items-center p-2 hover:bg-gray-50">
                <input type="checkbox" class="stream-student-checkbox mr-3" data-id="${s.id}" data-phone="${s.phone}">
                <div class="flex-1">
                    <div class="font-medium text-sm">${escapeHtml(s.full_name)}</div>
                    <div class="text-xs text-gray-500">Adm: ${escapeHtml(s.admission_no)} | ${escapeHtml(s.phone)}</div>
                </div>
            </div>
        `;
    });
    html += '</div>';
    $('#streamStudentsList').html(html);
    $('.stream-student-checkbox').on('change', updateRecipientCount);
}

// ==================== GET SELECTED PHONE NUMBERS ====================
function getSelectedPhoneNumbers() {
    let phones = [];
    if (currentRecipientType === 'parents') {
        if (currentParentType === 'all') {
            phones = studentsData.filter(s => s.phone).map(s => s.phone);
        } else if (currentParentType === 'class') {
            $('.class-student-checkbox:checked').each(function() {
                const phone = $(this).closest('.flex').find('.text-xs').text().split('|')[1]?.trim();
                if (phone) phones.push(phone);
            });
        } else if (currentParentType === 'stream') {
            $('.stream-student-checkbox:checked').each(function() {
                const phone = $(this).closest('.flex').find('.text-xs').text().split('|')[1]?.trim();
                if (phone) phones.push(phone);
            });
        } else if (currentParentType === 'individual') {
            $('.student-checkbox:checked').each(function() {
                const phone = $(this).closest('.student-item').data('phone');
                if (phone) phones.push(phone);
            });
        }
    } else if (currentRecipientType === 'teachers') {
        $('.teacher-checkbox:checked').each(function() {
            const phone = $(this).closest('.teacher-item').data('phone');
            if (phone) phones.push(phone);
        });
    } else if (currentRecipientType === 'staff') {
        $('.staff-checkbox:checked').each(function() {
            const phone = $(this).closest('.staff-item').data('phone');
            if (phone) phones.push(phone);
        });
    }
    return phones;
}

// ==================== EVENT HANDLERS ====================
$(document).ready(function() {
    // Recipient type switching
    $('#parentsTabBtn').on('click', function() {
        currentRecipientType = 'parents';
        $('.recipient-btn').removeClass('active border-indigo-500 bg-indigo-50 text-indigo-600');
        $(this).addClass('active border-indigo-500 bg-indigo-50 text-indigo-600');
        $('#parentsPanel, #teachersPanel, #staffPanel').hide();
        $('#parentsPanel').show();
        updateRecipientCount();
    });
    
    $('#teachersTabBtn').on('click', function() {
        currentRecipientType = 'teachers';
        $('.recipient-btn').removeClass('active border-indigo-500 bg-indigo-50 text-indigo-600');
        $(this).addClass('active border-indigo-500 bg-indigo-50 text-indigo-600');
        $('#parentsPanel, #teachersPanel, #staffPanel').hide();
        $('#teachersPanel').show();
        updateRecipientCount();
    });
    
    $('#staffTabBtn').on('click', function() {
        currentRecipientType = 'staff';
        $('.recipient-btn').removeClass('active border-indigo-500 bg-indigo-50 text-indigo-600');
        $(this).addClass('active border-indigo-500 bg-indigo-50 text-indigo-600');
        $('#parentsPanel, #teachersPanel, #staffPanel').hide();
        $('#staffPanel').show();
        updateRecipientCount();
    });
    
    // Parent type tabs
    $('.tab-btn').on('click', function() {
        const type = $(this).data('parent-type');
        $('.tab-btn').removeClass('active bg-indigo-600 text-white').addClass('bg-gray-100');
        $(this).addClass('active bg-indigo-600 text-white');
        showParentView(type);
    });
    
    // Class selection for parents
    $('#parentClassSelect').on('change', loadClassStudents);
    
    // Stream selection for parents
    $('#streamClassSelect').on('change', function() {
        const classId = $(this).val();
        const streamSelect = $('#streamSelect');
        if (!classId) {
            streamSelect.prop('disabled', true).html('<option value="">Select Stream</option>');
            return;
        }
        const classStreams = streamsData.filter(s => s.class_id == classId);
        let options = '<option value="">Select Stream</option>';
        classStreams.forEach(stream => options += `<option value="${stream.id}">${escapeHtml(stream.stream_name)}</option>`);
        streamSelect.html(options).prop('disabled', false);
    });
    $('#streamSelect').on('change', loadStreamStudents);
    
    // Select all buttons
    $('#selectAllStudents').on('click', function() {
        const checked = $('.student-checkbox:not(:checked)').length > 0;
        $('.student-checkbox').prop('checked', checked).trigger('change');
        updateRecipientCount();
    });
    $('#selectAllTeachers').on('click', function() {
        const checked = $('.teacher-checkbox:not(:checked)').length > 0;
        $('.teacher-checkbox').prop('checked', checked).trigger('change');
        updateRecipientCount();
    });
    $('#selectAllStaff').on('click', function() {
        const checked = $('.staff-checkbox:not(:checked)').length > 0;
        $('.staff-checkbox').prop('checked', checked).trigger('change');
        updateRecipientCount();
    });
    
    // Individual checkboxes
    $('.student-checkbox, .teacher-checkbox, .staff-checkbox').on('change', updateRecipientCount);
    
    // Message composer
    $('#messageText').on('input', updateSmsCounter);
    
    // Shortcodes
    $('.shortcode').on('click', function() {
        const shortcode = $(this).data('shortcode');
        const textarea = $('#messageText');
        const text = textarea.val();
        const cursorPos = textarea.prop('selectionStart');
        textarea.val(text.substring(0, cursorPos) + shortcode + text.substring(cursorPos));
        textarea.focus();
        updateSmsCounter();
    });
    
    // Preview button
    $('#previewBtn').on('click', function() {
        if (!$('#messageText').val().trim()) {
            showToast('Please enter a message', 'warning');
        } else {
            updateSmsCounter();
        }
    });
    
    // Send SMS button
    $('#sendSmsBtn').on('click', function() {
        const count = parseInt($('#selectedCount').text());
        const cost = parseInt($('#estimatedCost').text());
        if (count === 0) {
            showToast('Please select recipients', 'warning');
            return;
        }
        if (cost > smsBalance) {
            showToast('Insufficient SMS credits', 'error');
            return;
        }
        
        $('#modalRecipients').text(count);
        $('#modalCost').text(cost);
        $('#modalBalanceAfter').text(smsBalance - cost);
        $('#modalMessage').text($('#messagePreview').text());
        $('#confirmModal').removeClass('hidden');
    });
    
    // Modal actions
    $('#closeModalBtn, #cancelModalBtn').on('click', function() {
        $('#confirmModal').addClass('hidden');
    });
    
    $('#confirmSendBtn').on('click', async function() {
        const phones = getSelectedPhoneNumbers();
        const message = $('#messageText').val();
        
        if (phones.length === 0) {
            showToast('No valid phone numbers selected', 'warning');
            $('#confirmModal').addClass('hidden');
            return;
        }
        
        $(this).html('<div class="loading-spinner"></div> Sending...').prop('disabled', true);
        
        try {
            const response = await fetch('/feesystem/api/sms/send.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    school_id: schoolId,
                    user_id: userId,
                    phone_numbers: phones,
                    message: message,
                    api_key: apiKey,
                    api_secret: apiSecret
                })
            });
            const data = await response.json();
            if (data.success) {
                showToast(`SMS sent successfully to ${phones.length} recipients!`, 'success');
                $('#confirmModal').addClass('hidden');
                $('#messageText').val('');
                updateSmsCounter();
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(data.message || 'Failed to send SMS', 'error');
            }
        } catch (error) {
            showToast('Error: ' + error.message, 'error');
        } finally {
            $(this).html('<i class="fas fa-paper-plane mr-2"></i>Send').prop('disabled', false);
        }
    });
    
    // History filter
    $('#historySearch, #statusFilter').on('input change', function() {
        const search = $('#historySearch').val().toLowerCase();
        const status = $('#statusFilter').val().toLowerCase();
        $('#historyList .history-item').each(function() {
            const matchesSearch = $(this).data('message')?.includes(search) || false;
            const matchesStatus = !status || $(this).data('status') === status;
            $(this).toggle(matchesSearch && matchesStatus);
        });
    });
    
    // Initialize
    updateSmsCounter();
    showParentView('all');
});

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<div id="toastContainer"></div>

<?php include_once('../../includes/footer.php'); ?>