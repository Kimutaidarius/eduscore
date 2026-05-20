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
?>

<style>
/* Toggle Button Styles */
.main-toggle-btn {
    display: inline-flex;
    align-items: center;
    padding: 0.75rem 1.5rem;
    font-size: 0.875rem;
    font-weight: 500;
    color: #6b7280;
    background: transparent;
    border: none;
    border-bottom: 2px solid transparent;
    transition: all 0.2s ease;
    cursor: pointer;
}

.main-toggle-btn i {
    margin-right: 0.5rem;
    font-size: 1rem;
}

.main-toggle-btn:hover {
    color: #4f46e5;
    background-color: #eef2ff;
}

.main-toggle-btn.active {
    color: #4f46e5;
    border-bottom-color: #4f46e5;
    background-color: #eef2ff;
}

.dark .main-toggle-btn {
    color: #9ca3af;
}

.dark .main-toggle-btn:hover {
    color: #818cf8;
    background-color: #374151;
}

.dark .main-toggle-btn.active {
    color: #818cf8;
    border-bottom-color: #818cf8;
    background-color: #374151;
}

/* Tab Content */
.main-tab-content {
    display: none;
}

.main-tab-content.active {
    display: block;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* ===== MODAL Z-INDEX FIX ===== */
/* Ensure modals appear above header and all content */
.modal, 
#staffModal, 
#payslipModal,
div[id$="Modal"] {
    z-index: 9999 !important;
}

.modal-backdrop,
.fixed.inset-0 {
    z-index: 9998 !important;
}

/* Ensure modal content is also above */
.modal > div,
#staffModal > div,
#payslipModal > div {
    z-index: 10000 !important;
    position: relative;
}

/* Override any header z-index conflicts */
header, 
.main-header,
.bg-white.shadow-sm {
    z-index: 100 !important;
    position: relative;
}
</style>

<main class="main-content flex-grow flex flex-col">
  <header class="bg-white shadow-sm dark:bg-gray-800 dark:border-gray-700">
    <div class="px-4 py-3 flex justify-between items-center">
      <div class="flex items-center">
        <button id="mobile-toggle" class="mr-4 text-gray-500 hover:text-indigo-600 focus:outline-none lg:hidden">
          <i class="fas fa-bars text-xl"></i>
        </button>
        <h1 id="page-title" class="text-xl font-semibold text-gray-800 dark:text-white">Payroll Management</h1>
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
    <!-- Main Toggle Buttons - FULLY STYLED -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm mb-6">
      <div class="border-b border-gray-200 dark:border-gray-700">
        <nav class="flex flex-wrap" id="mainTabNav">
          <button class="main-toggle-btn active" data-main-tab="staff">
            <i class="fas fa-users mr-2"></i>Staff Management
          </button>
          <button class="main-toggle-btn" data-main-tab="process">
            <i class="fas fa-calculator mr-2"></i>Process Payroll
          </button>
          <button class="main-toggle-btn" data-main-tab="history">
            <i class="fas fa-history mr-2"></i>Payroll History
          </button>
          <button class="main-toggle-btn" data-main-tab="reports">
            <i class="fas fa-chart-line mr-2"></i>Reports
          </button>
        </nav>
      </div>
    </div>

    <!-- ==================== TAB 1: STAFF MANAGEMENT ==================== -->
    <div id="main-tab-staff" class="main-tab-content active">
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
        <div class="flex justify-between items-center mb-6">
          <h2 class="text-xl font-semibold text-gray-800 dark:text-white">
            <i class="fas fa-users text-indigo-500 mr-2"></i>Staff Directory
          </h2>
          <button id="addStaffBtn" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
            <i class="fas fa-plus mr-2"></i>Add Staff
          </button>
        </div>
        
        <!-- Staff Filters -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Search</label>
            <input type="text" id="staff_search" placeholder="Name, ID, or Phone..." class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Employment Type</label>
            <select id="staff_type_filter" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
              <option value="">All Types</option>
              <option value="permanent">Permanent</option>
              <option value="contract">Contract</option>
              <option value="part-time">Part-time</option>
              <option value="casual">Casual</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Department</label>
            <select id="staff_department_filter" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
              <option value="">All Departments</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
            <select id="staff_status_filter" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
              <option value="">All</option>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
        </div>
        
        <div class="overflow-x-auto">
          <table class="w-full text-sm text-left">
            <thead class="bg-gray-50 dark:bg-gray-700">
              <tr>
                <th class="px-4 py-3">Staff ID</th>
                <th class="px-4 py-3">Name</th>
                <th class="px-4 py-3">Department</th>
                <th class="px-4 py-3">Position</th>
                <th class="px-4 py-3 text-right">Basic Salary</th>
                <th class="px-4 py-3">Type</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">Actions</th>
              </tr>
            </thead>
            <tbody id="staffListBody">
              <tr><td colspan="8" class="text-center py-8">Loading staff...</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ==================== TAB 2: PROCESS PAYROLL ==================== -->
    <div id="main-tab-process" class="main-tab-content">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column: Payroll Form -->
        <div class="lg:col-span-1">
          <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-5 sticky top-4">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white mb-4 border-b pb-2">
              <i class="fas fa-calendar-alt text-green-500 mr-2"></i>Process Payroll
            </h2>
            
            <form id="payrollForm">
              <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Select Staff <span class="text-red-500">*</span></label>
                <select id="payroll_staff_id" required class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
                  <option value="">Select staff member...</option>
                </select>
              </div>
              
              <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Pay Period</label>
                <select id="payroll_period" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
                  <option value="monthly">Monthly</option>
                  <option value="bi-weekly">Bi-Weekly</option>
                  <option value="weekly">Weekly</option>
                </select>
              </div>
              
              <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Month</label>
                  <select id="payroll_month" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
                    <option value="1">January</option>
                    <option value="2">February</option>
                    <option value="3">March</option>
                    <option value="4">April</option>
                    <option value="5">May</option>
                    <option value="6">June</option>
                    <option value="7">July</option>
                    <option value="8">August</option>
                    <option value="9">September</option>
                    <option value="10">October</option>
                    <option value="11">November</option>
                    <option value="12">December</option>
                  </select>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Year</label>
                  <select id="payroll_year" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
                    <option value="2024">2024</option>
                    <option value="2025">2025</option>
                    <option value="2026" selected>2026</option>
                    <option value="2027">2027</option>
                  </select>
                </div>
              </div>
              
              <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Basic Salary</label>
                <input type="number" id="basic_salary" readonly class="w-full px-3 py-2 border rounded-lg bg-gray-100 dark:bg-gray-600">
              </div>
              
              <!-- Allowances Section -->
              <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Allowances</label>
                <button type="button" id="addAllowanceBtn" class="mb-3 px-3 py-1 bg-green-100 text-green-700 rounded-lg text-sm hover:bg-green-200 transition">
                  <i class="fas fa-plus mr-1"></i>Add Allowance
                </button>
                <div id="allowancesContainer" class="space-y-2">
                  <div class="allowance-item flex gap-2 items-center">
                    <input type="text" placeholder="Allowance name" class="allowance_name flex-1 px-2 py-1 text-sm border rounded dark:bg-gray-700 dark:border-gray-600">
                    <input type="number" placeholder="Amount" class="allowance_amount w-32 px-2 py-1 text-sm border rounded dark:bg-gray-700 dark:border-gray-600" step="0.01">
                    <button type="button" class="removeAllowanceBtn text-red-500 hover:text-red-700"><i class="fas fa-trash"></i></button>
                  </div>
                </div>
              </div>
              
              <!-- Deductions Section -->
              <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Deductions</label>
                <button type="button" id="addDeductionBtn" class="mb-3 px-3 py-1 bg-red-100 text-red-700 rounded-lg text-sm hover:bg-red-200 transition">
                  <i class="fas fa-plus mr-1"></i>Add Deduction
                </button>
                <div id="deductionsContainer" class="space-y-2">
                  <div class="deduction-item flex gap-2 items-center">
                    <input type="text" placeholder="Deduction name" class="deduction_name flex-1 px-2 py-1 text-sm border rounded dark:bg-gray-700 dark:border-gray-600">
                    <input type="number" placeholder="Amount" class="deduction_amount w-32 px-2 py-1 text-sm border rounded dark:bg-gray-700 dark:border-gray-600" step="0.01">
                    <button type="button" class="removeDeductionBtn text-red-500 hover:text-red-700"><i class="fas fa-trash"></i></button>
                  </div>
                </div>
              </div>
              
              <!-- Summary -->
              <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3 mb-4">
                <div class="flex justify-between text-sm mb-1">
                  <span>Gross Salary:</span>
                  <span id="grossSalary">KES 0.00</span>
                </div>
                <div class="flex justify-between text-sm mb-1">
                  <span>Total Allowances:</span>
                  <span id="totalAllowances">KES 0.00</span>
                </div>
                <div class="flex justify-between text-sm mb-1">
                  <span>Total Deductions:</span>
                  <span id="totalDeductions">KES 0.00</span>
                </div>
                <div class="flex justify-between font-bold text-lg mt-2 pt-2 border-t">
                  <span>Net Pay:</span>
                  <span id="netPay">KES 0.00</span>
                </div>
              </div>
              
              <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Payment Mode</label>
                <select id="payroll_payment_mode" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
                  <option value="">Select mode...</option>
                  <option value="bank">Bank Transfer</option>
                  <option value="cash">Cash</option>
                  <option value="cheque">Cheque</option>
                </select>
              </div>
              
              <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notes</label>
                <textarea id="payroll_notes" rows="2" placeholder="Additional notes..." class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600"></textarea>
              </div>
              
              <button type="submit" class="w-full px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                <i class="fas fa-save mr-2"></i>Process Payroll
              </button>
            </form>
          </div>
        </div>
        
        <!-- Right Column: Current Payroll Summary -->
        <div class="lg:col-span-2">
          <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-5">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white mb-4 border-b pb-2">
              <i class="fas fa-chart-pie text-purple-500 mr-2"></i>Payroll Summary
            </h2>
            <div id="payrollSummary">
              <div class="text-center text-gray-500 py-8">Select a staff member to view details</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ==================== TAB 3: PAYROLL HISTORY ==================== -->
    <div id="main-tab-history" class="main-tab-content">
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
        <h2 class="text-xl font-semibold text-gray-800 dark:text-white mb-4">
          <i class="fas fa-history text-blue-500 mr-2"></i>Payroll History
        </h2>
        
        <!-- Filters -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">From Date</label>
            <input type="date" id="history_from_date" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">To Date</label>
            <input type="date" id="history_to_date" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Staff</label>
            <select id="history_staff_filter" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
              <option value="">All Staff</option>
            </select>
          </div>
          <div class="flex items-end">
            <button id="filterHistoryBtn" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
              <i class="fas fa-search mr-2"></i>Filter
            </button>
          </div>
        </div>
        
        <div class="overflow-x-auto">
          <table class="w-full text-sm text-left">
            <thead class="bg-gray-50 dark:bg-gray-700">
              <tr>
                <th class="px-4 py-3">Payroll No.</th>
                <th class="px-4 py-3">Staff Name</th>
                <th class="px-4 py-3">Pay Period</th>
                <th class="px-4 py-3 text-right">Basic Salary</th>
                <th class="px-4 py-3 text-right">Allowances</th>
                <th class="px-4 py-3 text-right">Deductions</th>
                <th class="px-4 py-3 text-right">Net Pay</th>
                <th class="px-4 py-3">Payment Mode</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">Actions</th>
              </tr>
            </thead>
            <tbody id="payrollHistoryBody">
              <tr><td colspan="10" class="text-center py-8">Loading payroll history...</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ==================== TAB 4: REPORTS ==================== -->
    <div id="main-tab-reports" class="main-tab-content">
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
        <h2 class="text-xl font-semibold text-gray-800 dark:text-white mb-4">
          <i class="fas fa-chart-line text-green-500 mr-2"></i>Payroll Reports
        </h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div class="border rounded-lg p-4">
            <h3 class="font-semibold mb-3">Monthly Payroll Summary</h3>
            <div id="monthlySummaryChart" class="h-64"></div>
          </div>
          
          <div class="border rounded-lg p-4">
            <h3 class="font-semibold mb-3">Department-wise Payroll</h3>
            <div id="departmentSummary" class="space-y-2">
              <div class="text-center text-gray-500">Select a month to view</div>
            </div>
          </div>
        </div>
        
        <div class="mt-6 flex gap-3 justify-end">
          <button id="exportPayrollReportBtn" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
            <i class="fas fa-file-excel mr-2"></i>Export to Excel
          </button>
          <button id="printPayrollReportBtn" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
            <i class="fas fa-print mr-2"></i>Print Report
          </button>
        </div>
      </div>
    </div>
  </div>
</main>

<!-- ==================== MODALS ==================== -->

<!-- Add/Edit Staff Modal -->
<div id="staffModal" class="fixed inset-0 bg-black bg-opacity-50 z-30 hidden flex items-center justify-center overflow-y-auto">
  <div class="bg-white dark:bg-gray-800 w-full max-w-2xl rounded-lg shadow-xl mx-4 my-8">
    <div class="border-b border-gray-200 dark:border-gray-700 px-6 py-4 flex justify-between items-center">
      <h3 class="text-lg font-semibold"><i class="fas fa-user-plus text-indigo-500 mr-2"></i><span id="staffModalTitle">Add Staff</span></h3>
      <button class="closeStaffModalBtn text-gray-400 hover:text-gray-500">&times;</button>
    </div>
    <div class="px-6 py-4 max-h-[70vh] overflow-y-auto">
      <form id="staffForm">
        <input type="hidden" id="staff_edit_id">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium mb-1">Staff ID <span class="text-red-500">*</span></label>
            <input type="text" id="staff_id" required class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Full Name <span class="text-red-500">*</span></label>
            <input type="text" id="staff_fullname" required class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Email</label>
            <input type="email" id="staff_email" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Phone</label>
            <input type="text" id="staff_phone" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Department</label>
            <select id="staff_department" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
              <option value="">Select department...</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Position</label>
            <input type="text" id="staff_position" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Employment Type</label>
            <select id="staff_employment_type" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
              <option value="permanent">Permanent</option>
              <option value="contract">Contract</option>
              <option value="part-time">Part-time</option>
              <option value="casual">Casual</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Basic Salary <span class="text-red-500">*</span></label>
            <input type="number" id="staff_basic_salary" required class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600" step="0.01">
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Bank Name</label>
            <input type="text" id="staff_bank_name" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Bank Account No.</label>
            <input type="text" id="staff_bank_account" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">KRA PIN</label>
            <input type="text" id="staff_kra_pin" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">NSSF No.</label>
            <input type="text" id="staff_nssf" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">NHIF No.</label>
            <input type="text" id="staff_nhif" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Hire Date</label>
            <input type="date" id="staff_hire_date" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Status</label>
            <select id="staff_status" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
        </div>
        <div class="flex justify-end gap-3 mt-6">
          <button type="button" class="closeStaffModalBtn px-4 py-2 border rounded-lg dark:border-gray-600">Cancel</button>
          <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">Save Staff</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- View Payslip Modal -->
<div id="payslipModal" class="fixed inset-0 bg-black bg-opacity-50 z-30 hidden flex items-center justify-center overflow-y-auto">
  <div class="bg-white dark:bg-gray-800 w-full max-w-2xl rounded-lg shadow-xl mx-4 my-8">
    <div class="border-b border-gray-200 dark:border-gray-700 px-6 py-4 flex justify-between items-center">
      <h3 class="text-lg font-semibold"><i class="fas fa-file-invoice text-blue-500 mr-2"></i>Payslip</h3>
      <button class="closePayslipModalBtn text-gray-400 hover:text-gray-500">&times;</button>
    </div>
    <div class="px-6 py-4" id="payslipContent">
      <div class="text-center py-8">Loading...</div>
    </div>
    <div class="border-t px-6 py-4 flex justify-end gap-3">
      <button id="downloadPayslipBtn" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">Download PDF</button>
      <button id="printPayslipBtn" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">Print</button>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
const schoolId = <?php echo json_encode($school_id); ?>;
const userId = <?php echo json_encode($user_id); ?>;

// Set default dates
function setDefaultDates() {
  const today = new Date().toISOString().split('T')[0];
  const firstDayOfMonth = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0];
  document.getElementById('history_from_date').value = firstDayOfMonth;
  document.getElementById('history_to_date').value = today;
  document.getElementById('staff_hire_date').value = today;
}

// ==================== MAIN TOGGLE FUNCTIONALITY ====================
document.querySelectorAll('.main-toggle-btn').forEach(btn => {
  btn.addEventListener('click', function(e) {
    e.preventDefault();
    const tabId = this.dataset.mainTab;
    
    document.querySelectorAll('.main-toggle-btn').forEach(b => {
      b.classList.remove('active');
    });
    
    this.classList.add('active');
    
    document.querySelectorAll('.main-tab-content').forEach(content => {
      content.classList.remove('active');
    });
    
    const activeTab = document.getElementById(`main-tab-${tabId}`);
    if (activeTab) {
      activeTab.classList.add('active');
    }
    
    if (tabId === 'staff') loadStaffList();
    else if (tabId === 'history') loadPayrollHistory();
    else if (tabId === 'reports') loadReports();
  });
});

// ==================== STAFF MANAGEMENT ====================
async function loadStaffList() {
  const search = document.getElementById('staff_search').value;
  const type = document.getElementById('staff_type_filter').value;
  const department = document.getElementById('staff_department_filter').value;
  const status = document.getElementById('staff_status_filter').value;
  
  try {
    const response = await fetch('/feesystem/api/payroll/get_staff.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ search: search, type: type, department: department, status: status })
    });
    const result = await response.json();
    
    // Handle both response structures: { data: { staff: [] } } or { staff: [] }
    let staffData = [];
    if (result.success) {
      if (result.data && result.data.staff) {
        staffData = result.data.staff;
      } else if (result.staff) {
        staffData = result.staff;
      }
    }
    
    const tbody = document.getElementById('staffListBody');
    if (staffData.length === 0) {
      tbody.innerHTML = '<tr><td colspan="8" class="text-center py-8">No staff found</td></tr>';
    } else {
      tbody.innerHTML = staffData.map(s => `
        <tr class="border-b dark:border-gray-700">
          <td class="px-4 py-3">${escapeHtml(s.staff_id || s.staff_number || '-')}</td>
          <td class="px-4 py-3">${escapeHtml(s.full_name || s.first_name + ' ' + s.last_name)}</td>
          <td class="px-4 py-3">${escapeHtml(s.department_name || '-')}</td>
          <td class="px-4 py-3">${escapeHtml(s.position || '-')}</td>
          <td class="px-4 py-3 text-right">KES ${parseFloat(s.basic_salary || 0).toLocaleString()}</td>
          <td class="px-4 py-3">${escapeHtml(s.employment_type || 'permanent')}</td>
          <td class="px-4 py-3"><span class="px-2 py-1 rounded-full text-xs ${(s.status || 'active') === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">${s.status || 'active'}</span></td>
          <td class="px-4 py-3">
            <button onclick="editStaff(${s.id})" class="text-blue-500 hover:text-blue-700 mr-2"><i class="fas fa-edit"></i></button>
            <button onclick="deleteStaff(${s.id})" class="text-red-500 hover:text-red-700"><i class="fas fa-trash"></i></button>
          </td>
        </tr>
      `).join('');
    }
  } catch (error) { 
    console.error('Error loading staff:', error);
    document.getElementById('staffListBody').innerHTML = '<tr><td colspan="8" class="text-center py-8 text-red-500">Error loading staff</td></tr>';
  }
}

async function loadDepartments() {
  try {
    const response = await fetch('/feesystem/api/payroll/get_departments.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ school_id: schoolId })
    });
    const result = await response.json();
    
    let departments = [];
    if (result.success) {
      if (result.data && result.data.departments) {
        departments = result.data.departments;
      } else if (result.departments) {
        departments = result.departments;
      }
    }
    
    const options = '<option value="">Select department...</option>' + departments.map(d => `<option value="${d.id}">${escapeHtml(d.name)}</option>`).join('');
    document.querySelectorAll('#staff_department, #staff_department_filter').forEach(sel => {
      if (sel) sel.innerHTML = options;
    });
  } catch (error) { 
    console.error('Error loading departments:', error);
  }
}

document.getElementById('addStaffBtn').addEventListener('click', () => {
  document.getElementById('staffModalTitle').innerText = 'Add Staff';
  document.getElementById('staffForm').reset();
  document.getElementById('staff_edit_id').value = '';
  document.getElementById('staffModal').classList.remove('hidden');
});

document.querySelectorAll('.closeStaffModalBtn').forEach(btn => {
  btn.addEventListener('click', () => document.getElementById('staffModal').classList.add('hidden'));
});

document.getElementById('staffForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const editId = document.getElementById('staff_edit_id').value;
  const data = {
    school_id: schoolId,
    staff_id: document.getElementById('staff_id').value,
    full_name: document.getElementById('staff_fullname').value,
    email: document.getElementById('staff_email').value,
    phone: document.getElementById('staff_phone').value,
    department_id: document.getElementById('staff_department').value,
    position: document.getElementById('staff_position').value,
    employment_type: document.getElementById('staff_employment_type').value,
    basic_salary: document.getElementById('staff_basic_salary').value,
    bank_name: document.getElementById('staff_bank_name').value,
    bank_account: document.getElementById('staff_bank_account').value,
    kra_pin: document.getElementById('staff_kra_pin').value,
    nssf_no: document.getElementById('staff_nssf').value,
    nhif_no: document.getElementById('staff_nhif').value,
    hire_date: document.getElementById('staff_hire_date').value,
    status: document.getElementById('staff_status').value
  };
  
  if (editId) data.id = editId;
  
  try {
    const response = await fetch('/feesystem/api/payroll/save_staff.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    });
    const result = await response.json();
    if (result.success) {
      Swal.fire('Success', `Staff ${editId ? 'updated' : 'added'} successfully`, 'success');
      document.getElementById('staffModal').classList.add('hidden');
      loadStaffList();
      loadStaffForDropdown();
    } else {
      Swal.fire('Error', result.message || 'Failed to save staff', 'error');
    }
  } catch (error) { Swal.fire('Error', 'An error occurred', 'error'); }
});

window.editStaff = async (id) => {
  try {
    const response = await fetch('/feesystem/api/payroll/get_staff.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: id, school_id: schoolId })
    });
    const result = await response.json();
    
    let staffData = null;
    if (result.success) {
      if (result.data && result.data.staff && result.data.staff[0]) {
        staffData = result.data.staff[0];
      } else if (result.staff && result.staff[0]) {
        staffData = result.staff[0];
      }
    }
    
    if (staffData) {
      document.getElementById('staff_edit_id').value = staffData.id;
      document.getElementById('staff_id').value = staffData.staff_id || '';
      document.getElementById('staff_fullname').value = staffData.full_name || '';
      document.getElementById('staff_email').value = staffData.email || '';
      document.getElementById('staff_phone').value = staffData.phone || '';
      document.getElementById('staff_department').value = staffData.department_id || '';
      document.getElementById('staff_position').value = staffData.position || '';
      document.getElementById('staff_employment_type').value = staffData.employment_type || 'permanent';
      document.getElementById('staff_basic_salary').value = staffData.basic_salary || 0;
      document.getElementById('staff_bank_name').value = staffData.bank_name || '';
      document.getElementById('staff_bank_account').value = staffData.bank_account || '';
      document.getElementById('staff_kra_pin').value = staffData.kra_pin || '';
      document.getElementById('staff_nssf').value = staffData.nssf_no || '';
      document.getElementById('staff_nhif').value = staffData.nhif_no || '';
      document.getElementById('staff_hire_date').value = staffData.hire_date || '';
      document.getElementById('staff_status').value = staffData.status || 'active';
      document.getElementById('staffModalTitle').innerText = 'Edit Staff';
      document.getElementById('staffModal').classList.remove('hidden');
    } else {
      Swal.fire('Error', 'Staff not found', 'error');
    }
  } catch (error) { 
    console.error('Error loading staff:', error);
    Swal.fire('Error', 'Failed to load staff data', 'error');
  }
};

window.deleteStaff = async (id) => {
  const result = await Swal.fire({ title: 'Confirm', text: 'Delete this staff member?', icon: 'warning', showCancelButton: true });
  if (result.isConfirmed) {
    try {
      const response = await fetch('/feesystem/api/payroll/delete_staff.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id, school_id: schoolId })
      });
      const data = await response.json();
      if (data.success) {
        Swal.fire('Deleted', 'Staff deleted successfully', 'success');
        loadStaffList();
        loadStaffForDropdown();
      } else { Swal.fire('Error', data.message || 'Failed to delete', 'error'); }
    } catch (error) { Swal.fire('Error', 'An error occurred', 'error'); }
  }
};

// ==================== PAYROLL PROCESSING ====================
async function loadStaffForDropdown() {
  try {
    const response = await fetch('/feesystem/api/payroll/get_staff.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ school_id: schoolId })
    });
    const result = await response.json();
    
    let staffData = [];
    if (result.success) {
      if (result.data && result.data.staff) {
        staffData = result.data.staff;
      } else if (result.staff) {
        staffData = result.staff;
      }
    }
    
    const options = '<option value="">Select staff member...</option>' + staffData.map(s => `<option value="${s.id}" data-salary="${s.basic_salary || 0}">${escapeHtml(s.full_name || s.first_name + ' ' + s.last_name)} (${escapeHtml(s.staff_id || s.staff_number)})</option>`).join('');
    const staffSelect = document.getElementById('payroll_staff_id');
    const historyFilter = document.getElementById('history_staff_filter');
    if (staffSelect) staffSelect.innerHTML = options;
    if (historyFilter) historyFilter.innerHTML = '<option value="">All Staff</option>' + staffData.map(s => `<option value="${s.id}">${escapeHtml(s.full_name || s.first_name + ' ' + s.last_name)}</option>`).join('');
  } catch (error) { 
    console.error('Error loading staff dropdown:', error);
  }
}

document.getElementById('payroll_staff_id').addEventListener('change', (e) => {
  const selected = e.target.options[e.target.selectedIndex];
  const salary = selected.dataset.salary || 0;
  document.getElementById('basic_salary').value = parseFloat(salary).toLocaleString();
  document.getElementById('basic_salary').dataset.baseSalary = salary;
  calculatePayroll();
});

function calculatePayroll() {
  const basicSalary = parseFloat(document.getElementById('basic_salary').dataset.baseSalary) || 0;
  let totalAllowances = 0;
  let totalDeductions = 0;
  
  document.querySelectorAll('.allowance_amount').forEach(input => {
    totalAllowances += parseFloat(input.value) || 0;
  });
  document.querySelectorAll('.deduction_amount').forEach(input => {
    totalDeductions += parseFloat(input.value) || 0;
  });
  
  const grossSalary = basicSalary + totalAllowances;
  const netPay = grossSalary - totalDeductions;
  
  document.getElementById('totalAllowances').innerHTML = `KES ${totalAllowances.toLocaleString()}`;
  document.getElementById('totalDeductions').innerHTML = `KES ${totalDeductions.toLocaleString()}`;
  document.getElementById('grossSalary').innerHTML = `KES ${grossSalary.toLocaleString()}`;
  document.getElementById('netPay').innerHTML = `KES ${netPay.toLocaleString()}`;
}

document.getElementById('addAllowanceBtn').addEventListener('click', () => {
  const container = document.getElementById('allowancesContainer');
  const newItem = document.createElement('div');
  newItem.className = 'allowance-item flex gap-2 items-center';
  newItem.innerHTML = `
    <input type="text" placeholder="Allowance name" class="allowance_name flex-1 px-2 py-1 text-sm border rounded dark:bg-gray-700 dark:border-gray-600">
    <input type="number" placeholder="Amount" class="allowance_amount w-32 px-2 py-1 text-sm border rounded dark:bg-gray-700 dark:border-gray-600" step="0.01">
    <button type="button" class="removeAllowanceBtn text-red-500 hover:text-red-700"><i class="fas fa-trash"></i></button>
  `;
  container.appendChild(newItem);
  newItem.querySelector('.allowance_amount').addEventListener('input', calculatePayroll);
  newItem.querySelector('.removeAllowanceBtn').addEventListener('click', () => {
    newItem.remove();
    calculatePayroll();
  });
});

document.getElementById('addDeductionBtn').addEventListener('click', () => {
  const container = document.getElementById('deductionsContainer');
  const newItem = document.createElement('div');
  newItem.className = 'deduction-item flex gap-2 items-center';
  newItem.innerHTML = `
    <input type="text" placeholder="Deduction name" class="deduction_name flex-1 px-2 py-1 text-sm border rounded dark:bg-gray-700 dark:border-gray-600">
    <input type="number" placeholder="Amount" class="deduction_amount w-32 px-2 py-1 text-sm border rounded dark:bg-gray-700 dark:border-gray-600" step="0.01">
    <button type="button" class="removeDeductionBtn text-red-500 hover:text-red-700"><i class="fas fa-trash"></i></button>
  `;
  container.appendChild(newItem);
  newItem.querySelector('.deduction_amount').addEventListener('input', calculatePayroll);
  newItem.querySelector('.removeDeductionBtn').addEventListener('click', () => {
    newItem.remove();
    calculatePayroll();
  });
});

document.getElementById('payrollForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  
  const staffId = document.getElementById('payroll_staff_id').value;
  if (!staffId) { Swal.fire('Error', 'Please select a staff member', 'error'); return; }
  
  const allowances = [];
  document.querySelectorAll('.allowance-item').forEach(item => {
    const name = item.querySelector('.allowance_name')?.value;
    const amount = parseFloat(item.querySelector('.allowance_amount')?.value) || 0;
    if (name && amount > 0) allowances.push({ name, amount });
  });
  
  const deductions = [];
  document.querySelectorAll('.deduction-item').forEach(item => {
    const name = item.querySelector('.deduction_name')?.value;
    const amount = parseFloat(item.querySelector('.deduction_amount')?.value) || 0;
    if (name && amount > 0) deductions.push({ name, amount });
  });
  
  const basicSalary = parseFloat(document.getElementById('basic_salary').dataset.baseSalary) || 0;
  const totalAllowances = allowances.reduce((sum, a) => sum + a.amount, 0);
  const totalDeductions = deductions.reduce((sum, d) => sum + d.amount, 0);
  const grossSalary = basicSalary + totalAllowances;
  const netPay = grossSalary - totalDeductions;
  
  const data = {
    school_id: schoolId,
    user_id: userId,
    staff_id: staffId,
    month: document.getElementById('payroll_month').value,
    year: document.getElementById('payroll_year').value,
    period: document.getElementById('payroll_period').value,
    basic_salary: basicSalary,
    allowances: allowances,
    total_allowances: totalAllowances,
    deductions: deductions,
    total_deductions: totalDeductions,
    gross_salary: grossSalary,
    net_pay: netPay,
    payment_mode: document.getElementById('payroll_payment_mode').value,
    notes: document.getElementById('payroll_notes').value
  };
  
  try {
    const response = await fetch('/feesystem/api/payroll/process_payroll.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    });
    const result = await response.json();
    if (result.success) {
      Swal.fire('Success', 'Payroll processed successfully!', 'success');
      resetPayrollForm();
      loadPayrollHistory();
    } else {
      Swal.fire('Error', result.message || 'Failed to process payroll', 'error');
    }
  } catch (error) { Swal.fire('Error', 'An error occurred', 'error'); }
});

function resetPayrollForm() {
  document.getElementById('payroll_staff_id').value = '';
  document.getElementById('basic_salary').value = '';
  document.getElementById('basic_salary').dataset.baseSalary = '0';
  document.getElementById('allowancesContainer').innerHTML = `
    <div class="allowance-item flex gap-2 items-center">
      <input type="text" placeholder="Allowance name" class="allowance_name flex-1 px-2 py-1 text-sm border rounded dark:bg-gray-700 dark:border-gray-600">
      <input type="number" placeholder="Amount" class="allowance_amount w-32 px-2 py-1 text-sm border rounded dark:bg-gray-700 dark:border-gray-600" step="0.01">
      <button type="button" class="removeAllowanceBtn text-red-500 hover:text-red-700"><i class="fas fa-trash"></i></button>
    </div>
  `;
  document.getElementById('deductionsContainer').innerHTML = `
    <div class="deduction-item flex gap-2 items-center">
      <input type="text" placeholder="Deduction name" class="deduction_name flex-1 px-2 py-1 text-sm border rounded dark:bg-gray-700 dark:border-gray-600">
      <input type="number" placeholder="Amount" class="deduction_amount w-32 px-2 py-1 text-sm border rounded dark:bg-gray-700 dark:border-gray-600" step="0.01">
      <button type="button" class="removeDeductionBtn text-red-500 hover:text-red-700"><i class="fas fa-trash"></i></button>
    </div>
  `;
  document.getElementById('payroll_notes').value = '';
  calculatePayroll();
}

// ==================== PAYROLL HISTORY ====================
async function loadPayrollHistory() {
  const fromDate = document.getElementById('history_from_date').value;
  const toDate = document.getElementById('history_to_date').value;
  const staffId = document.getElementById('history_staff_filter').value;
  
  try {
    const response = await fetch('/feesystem/api/payroll/get_payroll_history.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ school_id: schoolId, from_date: fromDate, to_date: toDate, staff_id: staffId })
    });
    const result = await response.json();
    
    let records = [];
    if (result.success) {
      if (result.data && result.data.records) {
        records = result.data.records;
      } else if (result.records) {
        records = result.records;
      }
    }
    
    const tbody = document.getElementById('payrollHistoryBody');
    if (records.length === 0) {
      tbody.innerHTML = '<tr><td colspan="10" class="text-center py-8">No payroll records found</td></tr>';
    } else {
      tbody.innerHTML = records.map(r => `
        <tr class="border-b dark:border-gray-700">
          <td class="px-4 py-3">${escapeHtml(r.payroll_no)}</td>
          <td class="px-4 py-3">${escapeHtml(r.staff_name)}</td>
          <td class="px-4 py-3">${r.month_name || ''} ${r.year || ''}</td>
          <td class="px-4 py-3 text-right">KES ${parseFloat(r.basic_salary || 0).toLocaleString()}</td>
          <td class="px-4 py-3 text-right">KES ${parseFloat(r.total_allowances || 0).toLocaleString()}</td>
          <td class="px-4 py-3 text-right">KES ${parseFloat(r.total_deductions || 0).toLocaleString()}</td>
          <td class="px-4 py-3 text-right font-bold">KES ${parseFloat(r.net_pay || 0).toLocaleString()}</td>
          <td class="px-4 py-3">${escapeHtml(r.payment_mode || '-')}</td>
          <td class="px-4 py-3"><span class="px-2 py-1 rounded-full text-xs ${(r.status || 'pending') === 'paid' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'}">${r.status || 'pending'}</span></td>
          <td class="px-4 py-3">
            <button onclick="viewPayslip(${r.id})" class="text-blue-500 hover:text-blue-700"><i class="fas fa-eye"></i></button>
          </td>
        </tr>
      `).join('');
    }
  } catch (error) { 
    console.error('Error loading payroll history:', error);
    document.getElementById('payrollHistoryBody').innerHTML = '<tr><td colspan="10" class="text-center py-8 text-red-500">Error loading history</td></tr>';
  }
}

document.getElementById('filterHistoryBtn').addEventListener('click', loadPayrollHistory);

// ==================== PAYSLIP VIEW ====================
window.viewPayslip = async (id) => {
  try {
    const response = await fetch('/feesystem/api/payroll/get_payslip.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: id, school_id: schoolId })
    });
    const result = await response.json();
    
    let payslipData = null;
    if (result.success) {
      if (result.data && result.data.payslip) {
        payslipData = result.data.payslip;
      } else if (result.payslip) {
        payslipData = result.payslip;
      }
    }
    
    if (payslipData) {
      const p = payslipData;
      let allowancesHtml = '', deductionsHtml = '';
      if (p.allowances && p.allowances.length) {
        allowancesHtml = p.allowances.map(a => `<tr><td style="padding: 8px; border: 1px solid #ddd;">${escapeHtml(a.name)}</td><td style="padding: 8px; border: 1px solid #ddd; text-align: right;">KES ${parseFloat(a.amount).toLocaleString()}</td></tr>`).join('');
      }
      if (p.deductions && p.deductions.length) {
        deductionsHtml = p.deductions.map(d => `<tr><td style="padding: 8px; border: 1px solid #ddd;">${escapeHtml(d.name)}</td><td style="padding: 8px; border: 1px solid #ddd; text-align: right;">KES ${parseFloat(d.amount).toLocaleString()}</td></tr>`).join('');
      }
      
      const html = `
        <div id="payslipPrintArea" style="font-family: Arial, sans-serif; padding: 20px;">
          <div style="text-align: center; border-bottom: 2px solid #4f46e5; padding-bottom: 10px; margin-bottom: 20px;">
            <h2 style="margin: 0;">PAYSLIP</h2>
            <p>${escapeHtml(p.school_name || '')}</p>
            <p><strong>Payroll No:</strong> ${escapeHtml(p.payroll_no)} | <strong>Date:</strong> ${p.created_at || ''}</p>
          </div>
          
          <div style="margin-bottom: 20px;">
            <table style="width: 100%; border-collapse: collapse;">
              <tr><td style="padding: 5px;"><strong>Staff ID:</strong></td><td>${escapeHtml(p.staff_id)}</td>
                  <td><strong>Name:</strong></td><td>${escapeHtml(p.staff_name)}</td></tr>
              <tr><td style="padding: 5px;"><strong>Department:</strong></td><td>${escapeHtml(p.department || '-')}</td>
                  <td><strong>Position:</strong></td><td>${escapeHtml(p.position || '-')}</td></tr>
              <tr><td style="padding: 5px;"><strong>Pay Period:</strong></td><td>${p.month_name || ''} ${p.year || ''}</td>
                  <td><strong>Payment Mode:</strong></td><td>${escapeHtml(p.payment_mode || '-')}</td></tr>
            </table>
          </div>
          
          <div style="margin-bottom: 20px;">
            <h3>Earnings</h3>
            <table style="width: 100%; border-collapse: collapse; border: 1px solid #ddd;">
              <tr style="background: #f3f4f6;"><th style="padding: 8px; text-align: left;">Description</th><th style="padding: 8px; text-align: right;">Amount (KES)</th></tr>
              <tr><td style="padding: 8px; border: 1px solid #ddd;">Basic Salary</td><td style="padding: 8px; border: 1px solid #ddd; text-align: right;">${parseFloat(p.basic_salary || 0).toLocaleString()}</td></tr>
              ${allowancesHtml}
              <tr style="background: #e5e7eb; font-weight: bold;"><td style="padding: 8px;">Total Earnings</td><td style="padding: 8px; text-align: right;">${parseFloat(p.gross_salary || 0).toLocaleString()}</td></tr>
            </table>
          </div>
          
          <div style="margin-bottom: 20px;">
            <h3>Deductions</h3>
            <table style="width: 100%; border-collapse: collapse; border: 1px solid #ddd;">
              <tr style="background: #f3f4f6;"><th style="padding: 8px; text-align: left;">Description</th><th style="padding: 8px; text-align: right;">Amount (KES)</th></tr>
              ${deductionsHtml || '<tr><td colspan="2" style="text-align: center;">No deductions</td></tr>'}
              <tr style="background: #e5e7eb; font-weight: bold;"><td style="padding: 8px;">Total Deductions</td><td style="padding: 8px; text-align: right;">${parseFloat(p.total_deductions || 0).toLocaleString()}</td></tr>
            </table>
          </div>
          
          <div style="background: #e0e7ff; padding: 15px; text-align: center; border-radius: 8px;">
            <strong>NET PAY: KES ${parseFloat(p.net_pay || 0).toLocaleString()}</strong>
          </div>
          
          ${p.notes ? `<div style="margin-top: 20px;"><strong>Notes:</strong><p>${escapeHtml(p.notes)}</p></div>` : ''}
          
          <div style="margin-top: 30px; display: flex; justify-content: space-between;">
            <div style="border-top: 1px solid #000; width: 200px; text-align: center; padding-top: 5px;">Prepared By</div>
            <div style="border-top: 1px solid #000; width: 200px; text-align: center; padding-top: 5px;">Employee Signature</div>
          </div>
        </div>
      `;
      document.getElementById('payslipContent').innerHTML = html;
      document.getElementById('payslipModal').classList.remove('hidden');
    } else {
      Swal.fire('Error', 'Failed to load payslip', 'error');
    }
  } catch (error) { 
    console.error('Error loading payslip:', error);
    Swal.fire('Error', 'An error occurred', 'error'); 
  }
};

document.getElementById('downloadPayslipBtn').addEventListener('click', () => {
  const element = document.getElementById('payslipPrintArea');
  if (element) {
    html2pdf().set({ margin: 0.5, filename: 'payslip.pdf', image: { type: 'jpeg', quality: 0.98 }, html2canvas: { scale: 2 }, jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' } }).from(element).save();
  }
});

document.getElementById('printPayslipBtn').addEventListener('click', () => {
  const printContent = document.getElementById('payslipPrintArea').innerHTML;
  const printWindow = window.open('', '_blank');
  printWindow.document.write(`
    <html><head><title>Payslip</title><style>body{font-family:Arial;padding:20px} table{border-collapse:collapse;width:100%} th,td{border:1px solid #ddd;padding:8px}</style></head><body>${printContent}</body></html>
  `);
  printWindow.document.close();
  printWindow.print();
});

document.querySelectorAll('.closePayslipModalBtn').forEach(btn => {
  btn.addEventListener('click', () => document.getElementById('payslipModal').classList.add('hidden'));
});

// ==================== REPORTS ====================
async function loadReports() {
  const year = new Date().getFullYear();
  try {
    const response = await fetch('/feesystem/api/payroll/get_payroll_summary.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ school_id: schoolId, year: year })
    });
    const result = await response.json();
    
    let deptSummary = [];
    if (result.success) {
      if (result.data && result.data.department_summary) {
        deptSummary = result.data.department_summary;
      } else if (result.department_summary) {
        deptSummary = result.department_summary;
      }
    }
    
    if (deptSummary && deptSummary.length) {
      const deptHtml = deptSummary.map(d => `
        <div class="flex justify-between items-center p-2 border-b dark:border-gray-700">
          <span>${escapeHtml(d.department_name)}</span>
          <span class="font-bold">KES ${parseFloat(d.total_net_pay || 0).toLocaleString()}</span>
        </div>
      `).join('');
      document.getElementById('departmentSummary').innerHTML = deptHtml;
    }
  } catch (error) { console.error('Error loading reports:', error); }
}

document.getElementById('exportPayrollReportBtn').addEventListener('click', () => {
  Swal.fire('Info', 'Export feature will be available soon', 'info');
});

document.getElementById('printPayrollReportBtn').addEventListener('click', () => {
  window.print();
});

// Event listeners for filters
document.getElementById('staff_search').addEventListener('input', loadStaffList);
document.getElementById('staff_type_filter').addEventListener('change', loadStaffList);
document.getElementById('staff_department_filter').addEventListener('change', loadStaffList);
document.getElementById('staff_status_filter').addEventListener('change', loadStaffList);

function escapeHtml(text) {
  if (!text) return '';
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
  setDefaultDates();
  loadDepartments();
  loadStaffList();
  loadStaffForDropdown();
  loadPayrollHistory();
  calculatePayroll();
});
</script>

<?php include_once('../../includes/footer.php'); ?>