<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session and include config
require_once('../../includes/config.php');

// Include PermissionHelper if it exists
if (file_exists('../../includes/PermissionHelper.php')) {
    require_once('../../includes/PermissionHelper.php');
} else {
    // Fallback PermissionHelper class
    class PermissionHelper {
        private $db;
        private $school_id;
        private $teacher_id;
        private $role;
        
        public function __construct($db, $school_id, $teacher_id) {
            $this->db = $db;
            $this->school_id = $school_id;
            $this->teacher_id = $teacher_id;
            $this->loadUserRole();
        }
        
        private function loadUserRole() {
            try {
                $stmt = $this->db->prepare("
                    SELECT r.role_name 
                    FROM tblteachers t
                    LEFT JOIN roles r ON t.role_id = r.id
                    WHERE t.id = :teacher_id AND t.school_id = :school_id
                ");
                $stmt->execute([
                    ':teacher_id' => $this->teacher_id,
                    ':school_id' => $this->school_id
                ]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $this->role = $result['role_name'] ?? 'teacher';
            } catch (Exception $e) {
                $this->role = 'teacher';
            }
        }
        
        public function getRole() { return $this->role; }
        public function isSuperAdmin() { return $this->role === 'super_admin'; }
        
        public function hasPermission($permission) {
            if ($this->isSuperAdmin()) return true;
            try {
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) as has_permission
                    FROM role_permissions rp
                    JOIN permissions p ON rp.permission_id = p.id
                    JOIN roles r ON rp.role_id = r.id
                    WHERE r.role_name = :role_name AND p.permission_key = :permission
                ");
                $stmt->execute([':role_name' => $this->role, ':permission' => $permission]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                return $result['has_permission'] > 0;
            } catch (Exception $e) {
                return true;
            }
        }
        
        public function hasAnyPermission($permissions) {
            foreach ($permissions as $permission) {
                if ($this->hasPermission($permission)) return true;
            }
            return false;
        }
        
        public function requireAnyPermission($permissions, $redirect = null) {
            if (!$this->hasAnyPermission($permissions)) {
                if ($redirect) { header("Location: $redirect"); exit; }
                return false;
            }
            return true;
        }
    }
}

// Include session timeout if exists
if (file_exists('../../includes/session_timeout.php')) {
    require_once('../../includes/session_timeout.php');
}

// Security check
if (empty($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('Location: ../../login.php');
    exit;
}

if (empty($_SESSION['school_id']) || empty($_SESSION['teacher_id'])) {
    session_destroy();
    header("Location: ../../login.php");
    exit;
}

$school_id = $_SESSION['school_id'];
if (!$school_id) die("School ID not found in session.");

// Initialize Permission Helper
$permissionHelper = new PermissionHelper($db, $school_id, $_SESSION['teacher_id']);
$permissionHelper->requireAnyPermission(['classesView', 'classesViewAll'], '../../dashboard.php');

// Fetch teachers
$teachers = [];
if ($permissionHelper->hasAnyPermission(['teachersView', 'teachersViewAll'])) {
    try {
        $teacher_query = "SELECT id, firstname, secondname, lastname FROM tblteachers WHERE school_id = :school_id ORDER BY firstname, secondname";
        $teacher_stmt = $db->prepare($teacher_query);
        $teacher_stmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
        $teacher_stmt->execute();
        $teachers = $teacher_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

// Fetch vote heads
$vote_heads = [];
try {
    $tableCheck = $db->query("SHOW TABLES LIKE 'vote_heads'");
    if ($tableCheck->rowCount() > 0) {
        $vh_stmt = $db->prepare("SELECT id, name, alias, type, priority FROM vote_heads WHERE school_id = :school_id AND status = 'active' ORDER BY priority ASC");
        $vh_stmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
        $vh_stmt->execute();
        $vote_heads = $vh_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {}

// Permissions
$canCreate = $permissionHelper->hasPermission('classesCreate');
$canEdit = $permissionHelper->hasPermission('classesEdit');
$canDelete = $permissionHelper->hasPermission('classesDelete');
$canManageStreams = $permissionHelper->hasPermission('classesManageStreams');
$canManageFees = $permissionHelper->hasPermission('feesManage');
$isSuperAdmin = $permissionHelper->isSuperAdmin();

// Academic years
$current_academic_year = date('Y') . '/' . (date('Y') + 1);
$current_term = 1;
$academic_years = [];
try {
    $tableCheck = $db->query("SHOW TABLES LIKE 'fee_structures'");
    if ($tableCheck->rowCount() > 0) {
        $stmt = $db->prepare("SELECT DISTINCT academic_year FROM fee_structures WHERE school_id = :school_id ORDER BY academic_year DESC");
        $stmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
        $stmt->execute();
        $academic_years = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
} catch (Exception $e) {}
if (empty($academic_years)) $academic_years = [$current_academic_year];

// Include header and sidebar
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<!-- Page-specific CSS -->
<style>
    .tab-container {
        background: white;
        border-radius: 0.75rem;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
        overflow: hidden;
    }
    
    .tab-buttons {
        display: flex;
        border-bottom: 1px solid #e5e7eb;
        background: white;
        flex-wrap: wrap;
    }
    
    .tab-btn {
        padding: 1rem 1.5rem;
        background: none;
        border: none;
        font-size: 0.95rem;
        font-weight: 500;
        color: #6b7280;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .tab-btn:hover {
        color: #4f46e5;
        background: #f9fafb;
    }
    
    .tab-btn.active {
        color: #4f46e5;
        position: relative;
    }
    
    .tab-btn.active::after {
        content: '';
        position: absolute;
        bottom: -1px;
        left: 0;
        right: 0;
        height: 2px;
        background: #4f46e5;
    }
    
    .tab-pane {
        display: none;
        padding: 1.5rem;
    }
    
    .tab-pane.active {
        display: block;
        animation: fadeIn 0.3s ease;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .data-table-wrapper {
        overflow-x: auto;
        border-radius: 0.5rem;
    }
    
    .data-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .data-table th {
        background: #f8fafc;
        padding: 0.75rem 1rem;
        text-align: left;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #475569;
        border-bottom: 1px solid #e2e8f0;
    }
    
    .data-table td {
        padding: 0.75rem 1rem;
        border-bottom: 1px solid #f1f5f9;
        font-size: 0.875rem;
    }
    
    .data-table tr:hover {
        background: #f8fafc;
    }
    
    .badge {
        display: inline-flex;
        align-items: center;
        padding: 0.25rem 0.5rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
    }
    
    .badge-active {
        background: #d1fae5;
        color: #065f46;
    }
    
    .badge-inactive {
        background: #fee2e2;
        color: #991b1b;
    }
    
    .badge-optional {
        background: #fed7aa;
        color: #92400e;
    }
    
    .loading-spinner {
        display: inline-block;
        width: 2rem;
        height: 2rem;
        border: 3px solid #e2e8f0;
        border-top-color: #4f46e5;
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
    }
    
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    
    .action-btn {
        padding: 0.25rem 0.5rem;
        border-radius: 0.375rem;
        font-size: 0.75rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .action-btn-edit {
        background: #e0e7ff;
        color: #4338ca;
    }
    
    .action-btn-edit:hover {
        background: #c7d2fe;
    }
    
    .action-btn-delete {
        background: #fee2e2;
        color: #991b1b;
    }
    
    .action-btn-delete:hover {
        background: #fecaca;
    }
    
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 1000;
    }
    
    .modal-overlay.active {
        display: flex;
    }
    
    .modal-container {
        background: white;
        border-radius: 0.75rem;
        width: 100%;
        max-width: 500px;
        max-height: 90vh;
        overflow-y: auto;
        animation: slideUp 0.3s ease;
    }
    
    @keyframes slideUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .form-group {
        margin-bottom: 1rem;
    }
    
    .form-label {
        display: block;
        margin-bottom: 0.375rem;
        font-size: 0.875rem;
        font-weight: 500;
        color: #374151;
    }
    
    .form-control {
        width: 100%;
        padding: 0.5rem 0.75rem;
        border: 1px solid #d1d5db;
        border-radius: 0.5rem;
        font-size: 0.875rem;
        transition: all 0.2s;
    }
    
    .form-control:focus {
        outline: none;
        border-color: #4f46e5;
        box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.1);
    }
    
    .empty-state {
        text-align: center;
        padding: 3rem;
        color: #6b7280;
    }
    
    .empty-state i {
        font-size: 3rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }
</style>

<main class="flex-1 p-4 md:p-6">
    <!-- Page Title -->
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Class & Fee Management</h1>
        <p class="text-gray-500 text-sm mt-1">Manage classes, streams, and fee structures</p>
    </div>
    
    <!-- Tab Container -->
    <div class="tab-container">
        <div class="tab-buttons">
            <button class="tab-btn active" data-tab="classes">
                <i class="fas fa-school"></i> Classes
            </button>
            <button class="tab-btn" data-tab="streams">
                <i class="fas fa-layer-group"></i> Streams
            </button>
            <?php if ($canManageFees): ?>
            <button class="tab-btn" data-tab="fee-structures">
                <i class="fas fa-coins"></i> Fee Structures
            </button>
            <?php endif; ?>
        </div>
        
        <!-- Classes Tab -->
        <div class="tab-pane active" id="classes-tab">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-4">
                <div class="relative flex-1 max-w-md">
                    <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    <input type="text" id="classSearch" placeholder="Search classes..." 
                           class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <?php if ($canCreate): ?>
                <button id="addClassBtn" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg flex items-center gap-2 transition">
                    <i class="fas fa-plus"></i> Add Class
                </button>
                <?php endif; ?>
            </div>
            
            <div class="data-table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Class Name</th>
                            <th>Streams</th>
                            <th>Students</th>
                            <th>Class Teacher</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="classesTableBody">
                        <tr><td colspan="5" class="text-center py-8"><div class="loading-spinner mx-auto"></div><p class="mt-2 text-gray-500">Loading classes...</p></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Streams Tab -->
        <div class="tab-pane" id="streams-tab">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-4">
                <div class="relative flex-1 max-w-md">
                    <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    <input type="text" id="streamSearch" placeholder="Search streams..." 
                           class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <?php if ($canManageStreams): ?>
                <button id="addStreamBtn" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg flex items-center gap-2 transition">
                    <i class="fas fa-plus"></i> Add Stream
                </button>
                <?php endif; ?>
            </div>
            
            <div class="data-table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Class</th>
                            <th>Stream Name</th>
                            <th>Students</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="streamsTableBody">
                        <tr><td colspan="5" class="text-center py-8"><div class="loading-spinner mx-auto"></div><p class="mt-2 text-gray-500">Loading streams...</p></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Fee Structures Tab -->
        <?php if ($canManageFees): ?>
        <div class="tab-pane" id="fee-structures-tab">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-4">
                <div class="relative flex-1 max-w-md">
                    <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    <input type="text" id="feeSearch" placeholder="Search fee structures..." 
                           class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div class="flex gap-2">
                    <select id="feeAcademicYear" class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                        <?php foreach ($academic_years as $year): ?>
                            <option value="<?php echo htmlspecialchars($year); ?>" <?php echo $year == $current_academic_year ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($year); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select id="feeTerm" class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                        <option value="1" <?php echo $current_term == 1 ? 'selected' : ''; ?>>Term 1</option>
                        <option value="2" <?php echo $current_term == 2 ? 'selected' : ''; ?>>Term 2</option>
                        <option value="3" <?php echo $current_term == 3 ? 'selected' : ''; ?>>Term 3</option>
                    </select>
                    <button id="addFeeStructureBtn" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg flex items-center gap-2 transition">
                        <i class="fas fa-plus"></i> Add Fee
                    </button>
                </div>
            </div>
            
            <div class="data-table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Class</th>
                            <th>Stream</th>
                            <th>Vote Head</th>
                            <th>Amount (KES)</th>
                            <th>Optional</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="feeStructuresTableBody">
                        <tr><td colspan="7" class="text-center py-8"><div class="loading-spinner mx-auto"></div><p class="mt-2 text-gray-500">Loading fee structures...</p></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>

<!-- Class Modal -->
<div class="modal-overlay" id="classModal">
    <div class="modal-container">
        <div class="border-b px-6 py-4 flex justify-between items-center">
            <h3 class="text-lg font-semibold"><i class="fas fa-school text-indigo-600 mr-2"></i> <span id="classModalTitle">Add Class</span></h3>
            <button onclick="closeClassModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
        </div>
        <form id="classForm">
            <div class="p-6">
                <input type="hidden" id="classId" name="class_id">
                <div class="form-group">
                    <label class="form-label">Class Name *</label>
                    <input type="text" class="form-control" id="className" name="class_name" required placeholder="e.g., Grade 1, Form 1">
                </div>
                <div class="form-group">
                    <label class="form-label">Academic Level *</label>
                    <select class="form-control" id="academicLevel" name="academic_level" required>
                        <option value="primary">Primary School</option>
                        <option value="junior_secondary">Junior Secondary</option>
                        <option value="senior_secondary">Senior Secondary</option>
                        <option value="college">College</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Class Teacher</label>
                    <select class="form-control" id="teacherId" name="teacher_id">
                        <option value="">Select Class Teacher</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?php echo $teacher['id']; ?>"><?php echo htmlspecialchars($teacher['firstname'] . ' ' . $teacher['secondname'] . ' ' . $teacher['lastname']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="border-t px-6 py-4 flex justify-end gap-3">
                <button type="button" onclick="closeClassModal()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">Save Class</button>
            </div>
        </form>
    </div>
</div>

<!-- Stream Modal -->
<div class="modal-overlay" id="streamModal">
    <div class="modal-container">
        <div class="border-b px-6 py-4 flex justify-between items-center">
            <h3 class="text-lg font-semibold"><i class="fas fa-layer-group text-indigo-600 mr-2"></i> <span id="streamModalTitle">Add Stream</span></h3>
            <button onclick="closeStreamModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
        </div>
        <form id="streamForm">
            <div class="p-6">
                <input type="hidden" id="streamId" name="stream_id">
                <div class="form-group">
                    <label class="form-label">Class *</label>
                    <select class="form-control" id="streamClassId" name="class_id" required>
                        <option value="">Select Class</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Stream Name *</label>
                    <input type="text" class="form-control" id="streamName" name="stream_name" required placeholder="e.g., A, B, Red, Blue">
                </div>
            </div>
            <div class="border-t px-6 py-4 flex justify-end gap-3">
                <button type="button" onclick="closeStreamModal()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">Save Stream</button>
            </div>
        </form>
    </div>
</div>

<!-- Fee Structure Modal -->
<?php if ($canManageFees): ?>
<div class="modal-overlay" id="feeStructureModal">
    <div class="modal-container" style="max-width: 600px;">
        <div class="border-b px-6 py-4 flex justify-between items-center">
            <h3 class="text-lg font-semibold"><i class="fas fa-coins text-indigo-600 mr-2"></i> <span id="feeModalTitle">Add Fee Structure</span></h3>
            <button onclick="closeFeeModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
        </div>
        <form id="feeStructureForm">
            <div class="p-6">
                <input type="hidden" id="feeStructureId" name="fee_structure_id">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label">Academic Year *</label>
                        <select class="form-control" id="feeAcademicYearInput" name="academic_year" required>
                            <?php for ($year = 2020; $year <= date('Y') + 5; $year++): ?>
                                <option value="<?php echo $year . '/' . ($year + 1); ?>"><?php echo $year . '/' . ($year + 1); ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Term *</label>
                        <select class="form-control" id="feeTermInput" name="term" required>
                            <option value="1">Term 1</option>
                            <option value="2">Term 2</option>
                            <option value="3">Term 3</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label">Class *</label>
                        <select class="form-control" id="feeClassId" name="class_level" required>
                            <option value="">Select Class</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Stream (Optional)</label>
                        <select class="form-control" id="feeStreamId" name="stream_id">
                            <option value="">All Streams</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label">Vote Head *</label>
                        <select class="form-control" id="feeVoteHeadId" name="vote_head_id" required>
                            <option value="">Select Vote Head</option>
                            <?php foreach ($vote_heads as $vh): ?>
                                <option value="<?php echo $vh['id']; ?>"><?php echo htmlspecialchars($vh['name'] . ' (' . strtoupper($vh['alias']) . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Amount (KES) *</label>
                        <input type="number" class="form-control" id="feeAmount" name="amount" required step="0.01" min="0">
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label flex items-center gap-2">
                            <input type="checkbox" id="feeIsOptional" name="is_optional" value="1"> Optional Fee
                        </label>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select class="form-control" id="feeStatus" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="border-t px-6 py-4 flex justify-end gap-3">
                <button type="button" onclick="closeFeeModal()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">Save Fee Structure</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const schoolId = <?php echo json_encode($school_id); ?>;
const canManageFees = <?php echo $canManageFees ? 'true' : 'false'; ?>;
const canEdit = <?php echo $canEdit ? 'true' : 'false'; ?>;
const canDelete = <?php echo $canDelete ? 'true' : 'false'; ?>;
const canManageStreams = <?php echo $canManageStreams ? 'true' : 'false'; ?>;

// Tab switching
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const tabId = btn.dataset.tab;
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById(`${tabId}-tab`).classList.add('active');
        if (tabId === 'classes') loadClasses();
        else if (tabId === 'streams') loadStreams();
        else if (tabId === 'fee-structures') loadFeeStructures();
    });
});

const API_BASE = '../../api_handlers/';

// Load Classes
async function loadClasses() {
    try {
        const response = await fetch(API_BASE + 'class_handler.php?action=get_classes');
        const result = await response.json();
        if (result.success) renderClassesTable(result.data);
        else document.getElementById('classesTableBody').innerHTML = `<tr><td colspan="5" class="text-center py-8 text-red-500">${escapeHtml(result.message)}</td></tr>`;
    } catch (error) {
        document.getElementById('classesTableBody').innerHTML = `<tr><td colspan="5" class="text-center py-8 text-red-500">Error loading classes</td></tr>`;
    }
}

function renderClassesTable(classes) {
    const tbody = document.getElementById('classesTableBody');
    if (!classes || classes.length === 0) {
        tbody.innerHTML = `<tr><td colspan="5" class="text-center py-8"><div class="empty-state"><i class="fas fa-school"></i><p>No classes found</p></div></td></tr>`;
        return;
    }
    tbody.innerHTML = classes.map(cls => `
        <tr>
            <td class="font-medium">${escapeHtml(cls.class_level)}<br><span class="text-xs text-gray-500">${escapeHtml(cls.academic_level)}</span></td>
            <td>${cls.stream_count || 0}</td>
            <td>${cls.student_count || 0}</td>
            <td>${escapeHtml(cls.teacher_name || 'Not assigned')}</td>
            <td class="flex gap-2">
                ${canEdit ? `<button onclick="editClass(${cls.id})" class="action-btn action-btn-edit"><i class="fas fa-edit"></i></button>` : ''}
                ${canDelete ? `<button onclick="deleteClass(${cls.id})" class="action-btn action-btn-delete"><i class="fas fa-trash"></i></button>` : ''}
            </td>
        </tr>
    `).join('');
}

// Load Streams
async function loadStreams() {
    try {
        const response = await fetch(API_BASE + 'stream_handler.php?action=get_all_streams');
        const result = await response.json();
        if (result.success) {
            renderStreamsTable(result.data);
            populateClassSelect();
        } else {
            document.getElementById('streamsTableBody').innerHTML = `<tr><td colspan="5" class="text-center py-8 text-red-500">${escapeHtml(result.message)}</td></tr>`;
        }
    } catch (error) {
        document.getElementById('streamsTableBody').innerHTML = `<tr><td colspan="5" class="text-center py-8 text-red-500">Error loading streams</td></tr>`;
    }
}

function renderStreamsTable(streams) {
    const tbody = document.getElementById('streamsTableBody');
    if (!streams || streams.length === 0) {
        tbody.innerHTML = `<tr><td colspan="5" class="text-center py-8"><div class="empty-state"><i class="fas fa-layer-group"></i><p>No streams found</p></div></td></tr>`;
        return;
    }
    tbody.innerHTML = streams.map(stream => `
        <tr>
            <td>${escapeHtml(stream.class_level)}</td>
            <td class="font-medium">${escapeHtml(stream.stream_name)}</td>
            <td>${stream.student_count || 0}</td>
            <td><span class="badge badge-active">Active</span></td>
            <td class="flex gap-2">
                ${canManageStreams ? `<button onclick="editStream(${stream.id})" class="action-btn action-btn-edit"><i class="fas fa-edit"></i></button>` : ''}
                ${canManageStreams ? `<button onclick="deleteStream(${stream.id})" class="action-btn action-btn-delete"><i class="fas fa-trash"></i></button>` : ''}
            </td>
        </tr>
    `).join('');
}

// Load Fee Structures
async function loadFeeStructures() {
    if (!canManageFees) return;
    const academicYear = document.getElementById('feeAcademicYear')?.value || '';
    const term = document.getElementById('feeTerm')?.value || '';
    try {
        const response = await fetch(`${API_BASE}fee_structure_handler.php?action=get_fee_structures&academic_year=${academicYear}&term=${term}`);
        const result = await response.json();
        if (result.success) renderFeeStructuresTable(result.data);
        else document.getElementById('feeStructuresTableBody').innerHTML = `<tr><td colspan="7" class="text-center py-8 text-red-500">${escapeHtml(result.message)}</td></tr>`;
    } catch (error) {
        document.getElementById('feeStructuresTableBody').innerHTML = `<tr><td colspan="7" class="text-center py-8 text-red-500">Error loading fee structures</td></tr>`;
    }
}

function renderFeeStructuresTable(feeStructures) {
    const tbody = document.getElementById('feeStructuresTableBody');
    if (!feeStructures || feeStructures.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-center py-8"><div class="empty-state"><i class="fas fa-coins"></i><p>No fee structures found</p></div></td></tr>`;
        return;
    }
    tbody.innerHTML = feeStructures.map(fs => `
        <tr>
            <td>${escapeHtml(fs.class_level)}</td>
            <td>${fs.stream_name ? escapeHtml(fs.stream_name) : 'All Streams'}</td>
            <td>${escapeHtml(fs.vote_head_name)}</td>
            <td><strong>KES ${parseFloat(fs.amount).toLocaleString()}</strong></td>
            <td>${fs.is_optional ? '<span class="badge badge-optional">Optional</span>' : '<span class="badge badge-active">Required</span>'}</td>
            <td><span class="badge ${fs.status === 'active' ? 'badge-active' : 'badge-inactive'}">${fs.status === 'active' ? 'Active' : 'Inactive'}</span></td>
            <td class="flex gap-2">
                <button onclick="editFeeStructure(${fs.id})" class="action-btn action-btn-edit"><i class="fas fa-edit"></i></button>
                <button onclick="deleteFeeStructure(${fs.id})" class="action-btn action-btn-delete"><i class="fas fa-trash"></i></button>
            </td>
        </tr>
    `).join('');
}

// Populate class select dropdowns
async function populateClassSelect() {
    try {
        const response = await fetch(API_BASE + 'class_handler.php?action=get_classes');
        const result = await response.json();
        if (result.success) {
            const classSelect = document.getElementById('streamClassId');
            const feeClassSelect = document.getElementById('feeClassId');
            const options = result.data.map(cls => `<option value="${cls.id}">${escapeHtml(cls.class_level)}</option>`).join('');
            if (classSelect) classSelect.innerHTML = '<option value="">Select Class</option>' + options;
            if (feeClassSelect) {
                feeClassSelect.innerHTML = '<option value="">Select Class</option>' + options;
                feeClassSelect.onchange = async () => {
                    const classId = feeClassSelect.value;
                    if (classId) {
                        const streamRes = await fetch(`${API_BASE}stream_handler.php?action=get_streams&class_id=${classId}`);
                        const streamResult = await streamRes.json();
                        const streamSelect = document.getElementById('feeStreamId');
                        if (streamResult.success && streamResult.data) {
                            streamSelect.innerHTML = '<option value="">All Streams</option>' + streamResult.data.map(s => `<option value="${s.id}">${escapeHtml(s.stream_name)}</option>`).join('');
                        } else {
                            streamSelect.innerHTML = '<option value="">All Streams</option>';
                        }
                    }
                };
            }
        }
    } catch (error) { console.error('Error populating class select:', error); }
}

// Class CRUD
document.getElementById('addClassBtn')?.addEventListener('click', () => {
    document.getElementById('classModalTitle').textContent = 'Add Class';
    document.getElementById('classId').value = '';
    document.getElementById('classForm').reset();
    document.getElementById('classModal').classList.add('active');
});

window.editClass = async (id) => {
    try {
        const response = await fetch(`${API_BASE}class_handler.php?action=get_class&class_id=${id}`);
        const result = await response.json();
        if (result.success) {
            document.getElementById('classModalTitle').textContent = 'Edit Class';
            document.getElementById('classId').value = result.data.id;
            document.getElementById('className').value = result.data.class_level;
            document.getElementById('academicLevel').value = result.data.academic_level;
            document.getElementById('teacherId').value = result.data.teacher_id || '';
            document.getElementById('classModal').classList.add('active');
        } else Swal.fire('Error', result.message, 'error');
    } catch (error) { Swal.fire('Error', 'Failed to load class data', 'error'); }
};

window.deleteClass = async (id) => {
    const result = await Swal.fire({ title: 'Are you sure?', text: 'This will also delete all streams and fee structures!', icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Yes, delete it!' });
    if (result.isConfirmed) {
        try {
            const response = await fetch(API_BASE + 'class_handler.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `action=delete_class&class_id=${id}` });
            const data = await response.json();
            if (data.success) { Swal.fire('Deleted!', data.message, 'success'); loadClasses(); loadStreams(); }
            else Swal.fire('Error', data.message, 'error');
        } catch (error) { Swal.fire('Error', 'Failed to delete class', 'error'); }
    }
};

document.getElementById('classForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const isEdit = document.getElementById('classId').value;
    formData.append('action', isEdit ? 'edit_class' : 'add_class');
    formData.set('class_level', formData.get('class_name'));
    try {
        const response = await fetch(API_BASE + 'class_handler.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (data.success) { Swal.fire('Success', data.message, 'success'); closeClassModal(); loadClasses(); populateClassSelect(); }
        else Swal.fire('Error', data.message, 'error');
    } catch (error) { Swal.fire('Error', 'Failed to save class', 'error'); }
});

// Stream CRUD
document.getElementById('addStreamBtn')?.addEventListener('click', async () => {
    document.getElementById('streamModalTitle').textContent = 'Add Stream';
    document.getElementById('streamId').value = '';
    document.getElementById('streamForm').reset();
    await populateClassSelect();
    document.getElementById('streamModal').classList.add('active');
});

window.editStream = async (id) => {
    try {
        const response = await fetch(`${API_BASE}stream_handler.php?action=get_stream&stream_id=${id}`);
        const result = await response.json();
        if (result.success) {
            document.getElementById('streamModalTitle').textContent = 'Edit Stream';
            document.getElementById('streamId').value = result.data.id;
            document.getElementById('streamName').value = result.data.stream_name;
            await populateClassSelect();
            setTimeout(() => document.getElementById('streamClassId').value = result.data.class_id, 100);
            document.getElementById('streamModal').classList.add('active');
        } else Swal.fire('Error', result.message, 'error');
    } catch (error) { Swal.fire('Error', 'Failed to load stream data', 'error'); }
};

window.deleteStream = async (id) => {
    const result = await Swal.fire({ title: 'Are you sure?', text: 'This action cannot be undone!', icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Yes, delete it!' });
    if (result.isConfirmed) {
        try {
            const response = await fetch(API_BASE + 'stream_handler.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `action=delete_stream&stream_id=${id}` });
            const data = await response.json();
            if (data.success) { Swal.fire('Deleted!', data.message, 'success'); loadStreams(); loadClasses(); }
            else Swal.fire('Error', data.message, 'error');
        } catch (error) { Swal.fire('Error', 'Failed to delete stream', 'error'); }
    }
};

document.getElementById('streamForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const isEdit = document.getElementById('streamId').value;
    formData.append('action', isEdit ? 'edit_stream' : 'add_stream');
    try {
        const response = await fetch(API_BASE + 'stream_handler.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (data.success) { Swal.fire('Success', data.message, 'success'); closeStreamModal(); loadStreams(); loadClasses(); }
        else Swal.fire('Error', data.message, 'error');
    } catch (error) { Swal.fire('Error', 'Failed to save stream', 'error'); }
});

// Fee Structure CRUD
if (canManageFees) {
    document.getElementById('addFeeStructureBtn')?.addEventListener('click', async () => {
        document.getElementById('feeModalTitle').textContent = 'Add Fee Structure';
        document.getElementById('feeStructureId').value = '';
        document.getElementById('feeStructureForm').reset();
        document.getElementById('feeIsOptional').checked = false;
        await populateClassSelect();
        document.getElementById('feeAcademicYearInput').value = document.getElementById('feeAcademicYear').value;
        document.getElementById('feeTermInput').value = document.getElementById('feeTerm').value;
        document.getElementById('feeStructureModal').classList.add('active');
    });
    
    window.editFeeStructure = async (id) => {
        try {
            const response = await fetch(`${API_BASE}fee_structure_handler.php?action=get_fee_structure&id=${id}`);
            const result = await response.json();
            if (result.success) {
                document.getElementById('feeModalTitle').textContent = 'Edit Fee Structure';
                document.getElementById('feeStructureId').value = result.data.id;
                document.getElementById('feeAcademicYearInput').value = result.data.academic_year;
                document.getElementById('feeTermInput').value = result.data.term;
                document.getElementById('feeAmount').value = result.data.amount;
                document.getElementById('feeVoteHeadId').value = result.data.vote_head_id;
                document.getElementById('feeStatus').value = result.data.status;
                document.getElementById('feeIsOptional').checked = result.data.is_optional == 1;
                await populateClassSelect();
                setTimeout(() => {
                    document.getElementById('feeClassId').value = result.data.class_level;
                    if (result.data.stream_id) document.getElementById('feeStreamId').value = result.data.stream_id;
                }, 100);
                document.getElementById('feeStructureModal').classList.add('active');
            } else Swal.fire('Error', result.message, 'error');
        } catch (error) { Swal.fire('Error', 'Failed to load fee structure', 'error'); }
    };
    
    window.deleteFeeStructure = async (id) => {
        const result = await Swal.fire({ title: 'Are you sure?', text: 'This will remove this fee from all students!', icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Yes, delete it!' });
        if (result.isConfirmed) {
            try {
                const response = await fetch(API_BASE + 'fee_structure_handler.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `action=delete_fee_structure&id=${id}` });
                const data = await response.json();
                if (data.success) { Swal.fire('Deleted!', data.message, 'success'); loadFeeStructures(); }
                else Swal.fire('Error', data.message, 'error');
            } catch (error) { Swal.fire('Error', 'Failed to delete fee structure', 'error'); }
        }
    };
    
    document.getElementById('feeStructureForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const isEdit = document.getElementById('feeStructureId').value;
        formData.append('action', isEdit ? 'edit_fee_structure' : 'add_fee_structure');
        formData.append('school_id', schoolId);
        if (!formData.get('is_optional')) formData.append('is_optional', '0');
        try {
            const response = await fetch(API_BASE + 'fee_structure_handler.php', { method: 'POST', body: formData });
            const data = await response.json();
            if (data.success) { Swal.fire('Success', data.message, 'success'); closeFeeModal(); loadFeeStructures(); }
            else Swal.fire('Error', data.message, 'error');
        } catch (error) { Swal.fire('Error', 'Failed to save fee structure', 'error'); }
    });
    
    document.getElementById('feeAcademicYear')?.addEventListener('change', () => loadFeeStructures());
    document.getElementById('feeTerm')?.addEventListener('change', () => loadFeeStructures());
    document.getElementById('feeSearch')?.addEventListener('input', function() {
        const term = this.value.toLowerCase();
        document.querySelectorAll('#feeStructuresTableBody tr').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none';
        });
    });
}

// Search functionality
document.getElementById('classSearch')?.addEventListener('input', function() {
    const term = this.value.toLowerCase();
    document.querySelectorAll('#classesTableBody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none';
    });
});

document.getElementById('streamSearch')?.addEventListener('input', function() {
    const term = this.value.toLowerCase();
    document.querySelectorAll('#streamsTableBody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none';
    });
});

// Modal close functions
function closeClassModal() { document.getElementById('classModal').classList.remove('active'); }
function closeStreamModal() { document.getElementById('streamModal').classList.remove('active'); }
function closeFeeModal() { document.getElementById('feeStructureModal').classList.remove('active'); }

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') { closeClassModal(); closeStreamModal(); closeFeeModal(); }
});

// Initial load
document.addEventListener('DOMContentLoaded', () => {
    loadClasses();
    loadStreams();
    if (canManageFees) loadFeeStructures();
});
</script>

<?php include_once('../../includes/footer.php'); ?>