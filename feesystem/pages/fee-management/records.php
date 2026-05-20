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

include_once('../../includes/header.php');
include_once('../../includes/sidebar.php');
?>

<style>
/* ==================== Toggle Button Styles ==================== */
.toggle-btn {
    position: relative;
    padding: 0.75rem 1.5rem;
    font-size: 0.875rem;
    font-weight: 500;
    color: #6B7280;
    background: transparent;
    border: none;
    border-bottom: 2px solid transparent;
    transition: all 0.2s ease-in-out;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.toggle-btn i {
    font-size: 1rem;
    transition: transform 0.2s ease;
}

.toggle-btn:hover {
    color: #4F46E5;
    background: rgba(79, 70, 229, 0.05);
}

.toggle-btn:hover i {
    transform: translateY(-1px);
}

.toggle-btn.active {
    color: #4F46E5;
    border-bottom-color: #4F46E5;
    background: linear-gradient(to bottom, rgba(79, 70, 229, 0.05), transparent);
}

.toggle-btn.active i {
    color: #4F46E5;
}

/* Dark mode toggle buttons */
.dark .toggle-btn {
    color: #9CA3AF;
}

.dark .toggle-btn:hover {
    color: #818CF8;
    background: rgba(129, 140, 248, 0.1);
}

.dark .toggle-btn.active {
    color: #818CF8;
    border-bottom-color: #818CF8;
    background: linear-gradient(to bottom, rgba(129, 140, 248, 0.1), transparent);
}

/* Tab Content Transitions */
.tab-content {
    animation: fadeIn 0.3s ease-in-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* ==================== Table Styles ==================== */
.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}

.data-table thead {
    background-color: #F9FAFB;
    border-bottom: 1px solid #E5E7EB;
}

.dark .data-table thead {
    background-color: #374151;
    border-bottom-color: #4B5563;
}

.data-table th {
    padding: 0.75rem 1rem;
    text-align: left;
    font-weight: 600;
    color: #374151;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.05em;
}

.dark .data-table th {
    color: #E5E7EB;
}

.data-table td {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #E5E7EB;
}

.dark .data-table td {
    border-bottom-color: #4B5563;
}

.data-table tbody tr:hover {
    background-color: #F9FAFB;
}

.dark .data-table tbody tr:hover {
    background-color: #374151;
}

/* ==================== Filter Card Styles ==================== */
.filter-card {
    background: white;
    border-radius: 0.5rem;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.dark .filter-card {
    background: #1F2937;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
}

.filter-label {
    display: block;
    font-size: 0.875rem;
    font-weight: 500;
    margin-bottom: 0.5rem;
    color: #374151;
}

.dark .filter-label {
    color: #E5E7EB;
}

.filter-input, .filter-select {
    width: 100%;
    padding: 0.5rem 0.75rem;
    border: 1px solid #D1D5DB;
    border-radius: 0.5rem;
    background: white;
    transition: all 0.2s;
}

.dark .filter-input, .dark .filter-select {
    background: #374151;
    border-color: #4B5563;
    color: #F3F4F6;
}

.filter-input:focus, .filter-select:focus {
    outline: none;
    border-color: #4F46E5;
    ring: 2px solid rgba(79, 70, 229, 0.2);
}

/* ==================== Button Styles ==================== */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
    font-weight: 500;
    border-radius: 0.5rem;
    transition: all 0.2s;
    cursor: pointer;
    border: none;
}

.btn-primary {
    background: #4F46E5;
    color: white;
}

.btn-primary:hover {
    background: #4338CA;
    transform: translateY(-1px);
}

.btn-success {
    background: #10B981;
    color: white;
}

.btn-success:hover {
    background: #059669;
    transform: translateY(-1px);
}

.btn-danger {
    background: #EF4444;
    color: white;
}

.btn-danger:hover {
    background: #DC2626;
    transform: translateY(-1px);
}

.btn-secondary {
    background: #6B7280;
    color: white;
}

.btn-secondary:hover {
    background: #4B5563;
    transform: translateY(-1px);
}

/* ==================== Summary Cards ==================== */
.summary-card {
    border: 1px solid #E5E7EB;
    border-radius: 0.5rem;
    padding: 1rem;
    margin-bottom: 1rem;
}

.dark .summary-card {
    border-color: #4B5563;
}

.summary-card h3 {
    font-size: 1.125rem;
    font-weight: 600;
    margin-bottom: 0.75rem;
    color: #1F2937;
}

.dark .summary-card h3 {
    color: #F3F4F6;
}

/* ==================== Checkbox Styles ==================== */
.custom-checkbox {
    width: 1rem;
    height: 1rem;
    border-radius: 0.25rem;
    border: 1px solid #D1D5DB;
    cursor: pointer;
    accent-color: #4F46E5;
}

.dark .custom-checkbox {
    border-color: #4B5563;
}

/* ==================== Loading Spinner ==================== */
.loading-spinner {
    display: inline-block;
    width: 1.5rem;
    height: 1.5rem;
    border: 2px solid #E5E7EB;
    border-top-color: #4F46E5;
    border-radius: 50%;
    animation: spin 0.6s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* ==================== Responsive ==================== */
@media (max-width: 768px) {
    .toggle-btn {
        padding: 0.5rem 1rem;
        font-size: 0.75rem;
    }
    
    .toggle-btn i {
        font-size: 0.875rem;
    }
    
    .data-table th, .data-table td {
        padding: 0.5rem 0.75rem;
    }
}
</style>

<main class="main-content flex-grow flex flex-col">
  <header class="bg-white shadow-sm dark:bg-gray-800 dark:border-gray-700">
    <div class="px-4 py-3 flex justify-between items-center">
      <div class="flex items-center">
        <button id="mobile-toggle" class="mr-4 text-gray-500 hover:text-indigo-600 focus:outline-none lg:hidden">
          <i class="fas fa-bars text-xl"></i>
        </button>
        <h1 id="page-title" class="text-xl font-semibold text-gray-800 dark:text-white">Records Management</h1>
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
    <!-- Toggle Buttons -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm mb-6 overflow-x-auto">
      <div class="border-b border-gray-200 dark:border-gray-700">
        <nav class="flex flex-nowrap md:flex-wrap -mb-px">
          <button class="toggle-btn active" data-tab="receipts">
            <i class="fas fa-receipt"></i> Receipts
          </button>
          <button class="toggle-btn" data-tab="cancelled">
            <i class="fas fa-ban"></i> Cancelled Receipts
          </button>
          <button class="toggle-btn" data-tab="classlist">
            <i class="fas fa-users"></i> Class List
          </button>
          <button class="toggle-btn" data-tab="prepayment">
            <i class="fas fa-clock"></i> Pre-Payment Records
          </button>
          <button class="toggle-btn" data-tab="dailysummary">
            <i class="fas fa-chart-bar"></i> Daily Summary
          </button>
        </nav>
      </div>
    </div>

    <!-- ==================== TAB 1: RECEIPTS ==================== -->
    <div id="tab-receipts" class="tab-content active">
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
        <div class="p-6">
          <!-- Filter Section -->
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div>
              <label class="filter-label">From Date</label>
              <input type="date" id="receipts_from_date" class="filter-input">
            </div>
            <div>
              <label class="filter-label">To Date</label>
              <input type="date" id="receipts_to_date" class="filter-input">
            </div>
            <div>
              <label class="filter-label">Class</label>
              <select id="receipts_class" class="filter-select">
                <option value="">All Classes</option>
              </select>
            </div>
            <div>
              <label class="filter-label">Stream</label>
              <select id="receipts_stream" class="filter-select">
                <option value="">All Streams</option>
              </select>
            </div>
            <div>
              <label class="filter-label">Year</label>
              <select id="receipts_year" class="filter-select">
                <option value="2024">2024</option>
                <option value="2025">2025</option>
                <option value="2026" selected>2026</option>
                <option value="2027">2027</option>
              </select>
            </div>
            <div class="md:col-span-2 lg:col-span-2">
              <label class="filter-label">Search Student</label>
              <input type="text" id="receipts_search" placeholder="Search by name or admission number..." class="filter-input">
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="flex flex-wrap gap-3 mb-6 pb-4 border-b border-gray-200 dark:border-gray-700">
            <button id="import_receipts_btn" class="btn btn-primary">
              <i class="fas fa-upload"></i> Import
            </button>
            <button id="export_receipts_btn" class="btn btn-success">
              <i class="fas fa-download"></i> Export
            </button>
            <button id="print_receipts_btn" class="btn btn-secondary">
              <i class="fas fa-print"></i> Print
            </button>
          </div>

          <!-- Receipts Table -->
          <div class="overflow-x-auto">
            <table class="data-table">
              <thead>
                <tr>
                  <th class="w-10"><input type="checkbox" id="select_all_receipts" class="custom-checkbox"></th>
                  <th>Date</th>
                  <th>Receipt No.</th>
                  <th class="text-right">Amount</th>
                  <th>Mode</th>
                  <th>Adm No.</th>
                  <th>Student</th>
                  <th>Class</th>
                  <th>Notes</th>
                </tr>
              </thead>
              <tbody id="receipts_table_body">
                <tr><td colspan="9" class="text-center py-8">
                  <div class="loading-spinner"></div>
                  <span class="ml-2">Loading receipts...</span>
                </td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- ==================== TAB 2: CANCELLED RECEIPTS ==================== -->
    <div id="tab-cancelled" class="tab-content hidden">
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
        <div class="p-6">
          <!-- Filter Section -->
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div>
              <label class="filter-label">From Date</label>
              <input type="date" id="cancelled_from_date" class="filter-input">
            </div>
            <div>
              <label class="filter-label">To Date</label>
              <input type="date" id="cancelled_to_date" class="filter-input">
            </div>
            <div>
              <label class="filter-label">Class</label>
              <select id="cancelled_class" class="filter-select">
                <option value="">All Classes</option>
              </select>
            </div>
            <div>
              <label class="filter-label">Stream</label>
              <select id="cancelled_stream" class="filter-select">
                <option value="">All Streams</option>
              </select>
            </div>
            <div class="md:col-span-2 lg:col-span-2">
              <label class="filter-label">Search Student</label>
              <input type="text" id="cancelled_search" placeholder="Search by name or admission number..." class="filter-input">
            </div>
          </div>

          <!-- Cancelled Receipts Table -->
          <div class="overflow-x-auto">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Cancelled Date</th>
                  <th>Receipt No.</th>
                  <th class="text-right">Amount</th>
                  <th>Mode</th>
                  <th>Adm No.</th>
                  <th>Student</th>
                  <th>Class</th>
                  <th>Code</th>
                  <th>Notes</th>
                  <th>Cancelled By</th>
                  <th>Reason</th>
                </tr>
              </thead>
              <tbody id="cancelled_table_body">
                <tr><td colspan="11" class="text-center py-8">
                  <div class="loading-spinner"></div>
                  <span class="ml-2">Loading cancelled receipts...</span>
                </td></tr>
              </tbody>
            </table>
            <div id="cancelled_total" class="text-right mt-4 font-bold text-lg"></div>
          </div>
        </div>
      </div>
    </div>

    <!-- ==================== TAB 3: CLASS LIST ==================== -->
    <div id="tab-classlist" class="tab-content hidden">
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
        <div class="p-6">
          <!-- Filter Section -->
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div>
              <label class="filter-label">Class</label>
              <select id="classlist_class" class="filter-select">
                <option value="">All Classes</option>
              </select>
            </div>
            <div>
              <label class="filter-label">Stream</label>
              <select id="classlist_stream" class="filter-select">
                <option value="">All Streams</option>
              </select>
            </div>
            <div>
              <label class="filter-label">Year</label>
              <select id="classlist_year" class="filter-select">
                <option value="2024">2024</option>
                <option value="2025">2025</option>
                <option value="2026" selected>2026</option>
                <option value="2027">2027</option>
              </select>
            </div>
            <div>
              <label class="filter-label">Gender</label>
              <select id="classlist_gender" class="filter-select">
                <option value="">All</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
              </select>
            </div>
            <div>
              <label class="filter-label">Extra Columns</label>
              <select id="classlist_extra" class="filter-select">
                <option value="">None</option>
                <option value="parent">Parent Name</option>
                <option value="phone">Phone Number</option>
                <option value="dob">Date of Birth</option>
              </select>
            </div>
            <div class="md:col-span-2 lg:col-span-2">
              <label class="filter-label">Search</label>
              <input type="text" id="classlist_search" placeholder="Search by name or admission number..." class="filter-input">
            </div>
          </div>

          <!-- Class List Table -->
          <div class="overflow-x-auto">
            <table class="data-table">
              <thead id="classlist_table_headers">
                <tr>
                  <th>#</th>
                  <th>Admission Number</th>
                  <th>Full Name</th>
                  <th>Gender</th>
                </tr>
              </thead>
              <tbody id="classlist_table_body">
                <tr><td colspan="4" class="text-center py-8">
                  <div class="loading-spinner"></div>
                  <span class="ml-2">Loading class list...</span>
                </td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- ==================== TAB 4: PRE-PAYMENT RECORDS ==================== -->
    <div id="tab-prepayment" class="tab-content hidden">
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
        <div class="p-6">
          <!-- Filter Section -->
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div>
              <label class="filter-label">Class</label>
              <select id="prepayment_class" class="filter-select">
                <option value="">All Classes</option>
              </select>
            </div>
            <div>
              <label class="filter-label">Stream</label>
              <select id="prepayment_stream" class="filter-select">
                <option value="">All Streams</option>
              </select>
            </div>
            <div>
              <label class="filter-label">Year</label>
              <select id="prepayment_year" class="filter-select">
                <option value="2024">2024</option>
                <option value="2025">2025</option>
                <option value="2026" selected>2026</option>
                <option value="2027">2027</option>
              </select>
            </div>
            <div>
              <label class="filter-label">Vote Head</label>
              <select id="prepayment_votehead" class="filter-select">
                <option value="">All Vote Heads</option>
              </select>
            </div>
            <div class="md:col-span-2 lg:col-span-2">
              <label class="filter-label">Search</label>
              <input type="text" id="prepayment_search" placeholder="Search by Name, Admission Number, Vote Head..." class="filter-input">
            </div>
          </div>

          <!-- Pre-Payment Table -->
          <div class="overflow-x-auto">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Adm No.</th>
                  <th>Full Name</th>
                  <th>Class</th>
                  <th>Vote Head</th>
                  <th class="text-right">Amount</th>
                  <th>Distributed</th>
                </tr>
              </thead>
              <tbody id="prepayment_table_body">
                <tr><td colspan="7" class="text-center py-8">
                  <div class="loading-spinner"></div>
                  <span class="ml-2">Loading pre-payment records...</span>
                </td></tr>
              </tbody>
              <tfoot class="bg-gray-50 dark:bg-gray-700">
                <tr class="font-bold">
                  <td colspan="5" class="px-4 py-3 text-right">Total:</td>
                  <td class="px-4 py-3 text-right" id="prepayment_total">KES 0.00</td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- ==================== TAB 5: DAILY SUMMARY ==================== -->
    <div id="tab-dailysummary" class="tab-content hidden">
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
        <div class="p-6">
          <!-- Filter Section -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div>
              <label class="filter-label">From Date</label>
              <input type="date" id="summary_from_date" class="filter-input">
            </div>
            <div>
              <label class="filter-label">To Date</label>
              <input type="date" id="summary_to_date" class="filter-input">
            </div>
          </div>

          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Receipts by Payment Mode -->
            <div class="summary-card">
              <h3><i class="fas fa-credit-card text-indigo-500 mr-2"></i>Receipts by Payment Mode</h3>
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Mode</th>
                    <th class="text-right">Receipts</th>
                    <th class="text-right">Amount</th>
                  </tr>
                </thead>
                <tbody id="receipts_by_mode_body">
                  <tr><td colspan="3" class="text-center py-4">No data</td></tr>
                </tbody>
                <tfoot class="bg-gray-100 dark:bg-gray-800 font-bold">
                  <tr>
                    <td class="px-4 py-2">Total</td>
                    <td class="px-4 py-2 text-right" id="receipts_total_count">0</td>
                    <td class="px-4 py-2 text-right" id="receipts_total_amount">KES 0.00</td>
                  </tr>
                </tfoot>
              </table>
            </div>

            <!-- Payment Vouchers by Payment Mode -->
            <div class="summary-card">
              <h3><i class="fas fa-money-bill-wave text-green-500 mr-2"></i>Payment Vouchers by Mode</h3>
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Mode</th>
                    <th class="text-right">Vouchers</th>
                    <th class="text-right">Amount</th>
                  </tr>
                </thead>
                <tbody id="vouchers_by_mode_body">
                  <tr><td colspan="3" class="text-center py-4">No data</td></tr>
                </tbody>
                <tfoot class="bg-gray-100 dark:bg-gray-800 font-bold">
                  <tr>
                    <td class="px-4 py-2">Total</td>
                    <td class="px-4 py-2 text-right" id="vouchers_total_count">0</td>
                    <td class="px-4 py-2 text-right" id="vouchers_total_amount">KES 0.00</td>
                  </tr>
                </tfoot>
              </table>
            </div>

            <!-- Votehead Summary -->
            <div class="lg:col-span-2 summary-card">
              <h3><i class="fas fa-chart-pie text-purple-500 mr-2"></i>Votehead Summary</h3>
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Votehead</th>
                    <th class="text-right">Credit (Receipts)</th>
                    <th class="text-right">Debit (Vouchers)</th>
                    <th class="text-right">Net</th>
                  </tr>
                </thead>
                <tbody id="votehead_summary_body">
                  <tr><td colspan="4" class="text-center py-4">No data</td></tr>
                </tbody>
                <tfoot class="bg-gray-100 dark:bg-gray-800 font-bold">
                  <tr>
                    <td class="px-4 py-2">Total</td>
                    <td class="px-4 py-2 text-right" id="votehead_credit_total">KES 0.00</td>
                    <td class="px-4 py-2 text-right" id="votehead_debit_total">KES 0.00</td>
                    <td class="px-4 py-2 text-right" id="votehead_net_total">KES 0.00</td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<script>
const schoolId = <?php echo json_encode($school_id); ?>;

// Set default dates
function setDefaultDates() {
  const today = new Date().toISOString().split('T')[0];
  const firstDayOfMonth = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0];
  
  const dateInputs = ['receipts_from_date', 'receipts_to_date', 'cancelled_from_date', 'cancelled_to_date', 'summary_from_date', 'summary_to_date'];
  dateInputs.forEach(id => {
    const el = document.getElementById(id);
    if (el && !el.value) {
      el.value = id.includes('from') ? firstDayOfMonth : today;
    }
  });
}

// Toggle functionality with animation
document.querySelectorAll('.toggle-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const tabId = btn.dataset.tab;
    
    document.querySelectorAll('.toggle-btn').forEach(b => {
      b.classList.remove('active');
    });
    btn.classList.add('active');
    
    document.querySelectorAll('.tab-content').forEach(t => {
      t.style.opacity = '0';
      setTimeout(() => {
        t.classList.add('hidden');
      }, 150);
    });
    
    setTimeout(() => {
      const activeTab = document.getElementById(`tab-${tabId}`);
      activeTab.classList.remove('hidden');
      setTimeout(() => {
        activeTab.style.opacity = '1';
      }, 10);
      activeTab.style.opacity = '0';
      
      if (tabId === 'receipts') loadReceipts();
      else if (tabId === 'cancelled') loadCancelledReceipts();
      else if (tabId === 'classlist') loadClassList();
      else if (tabId === 'prepayment') loadPrePayments();
      else if (tabId === 'dailysummary') loadDailySummary();
    }, 150);
  });
});

// ==================== LOAD RECEIPTS ====================
async function loadReceipts() {
  const fromDate = document.getElementById('receipts_from_date').value;
  const toDate = document.getElementById('receipts_to_date').value;
  const classId = document.getElementById('receipts_class').value;
  const streamId = document.getElementById('receipts_stream').value;
  const year = document.getElementById('receipts_year').value;
  const search = document.getElementById('receipts_search').value;
  
  const tbody = document.getElementById('receipts_table_body');
  tbody.innerHTML = `<tr><td colspan="9" class="text-center py-8"><div class="loading-spinner"></div><span class="ml-2">Loading...</span>NonNull</div></td></tr>`;
  
  try {
    const response = await fetch('/feesystem/api/records/get_receipts.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ school_id: schoolId, from_date: fromDate, to_date: toDate, class_id: classId, stream_id: streamId, year: year, search: search })
    });
    const data = await response.json();
    
    if (data.success) {
      if (data.receipts.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center py-8">No receipts found</td></tr>';
      } else {
        tbody.innerHTML = data.receipts.map(r => `
          <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
            <td class="px-4 py-3"><input type="checkbox" class="receipt-checkbox custom-checkbox" data-id="${r.id}"></td>
            <td class="px-4 py-3">${r.payment_date || '-'}</td>
            <td class="px-4 py-3 font-mono">${escapeHtml(r.receipt_no)}</td>
            <td class="px-4 py-3 text-right font-semibold">KES ${parseFloat(r.amount).toLocaleString()}</td>
            <td class="px-4 py-3"><span class="px-2 py-1 rounded-full text-xs ${getModeColor(r.payment_mode)}">${escapeHtml(r.payment_mode)}</span></td>
            <td class="px-4 py-3 font-mono">${escapeHtml(r.admission_no)}</td>
            <td class="px-4 py-3">${escapeHtml(r.student_name)}</td>
            <td class="px-4 py-3">${escapeHtml(r.class_name)}</td>
            <td class="px-4 py-3 text-gray-500">${escapeHtml(r.notes || '-')}</td>
          </tr>
        `).join('');
      }
    } else {
      tbody.innerHTML = `<tr><td colspan="9" class="text-center py-8 text-red-500">${escapeHtml(data.message)}</td></tr>`;
    }
  } catch (error) {
    console.error('Error loading receipts:', error);
    tbody.innerHTML = '<tr><td colspan="9" class="text-center py-8 text-red-500">Error loading receipts</td></tr>';
  }
}

function getModeColor(mode) {
  const colors = {
    'cash': 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    'mpesa': 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    'bank': 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
    'cheque': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
  };
  return colors[mode?.toLowerCase()] || 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
}

// ==================== LOAD CANCELLED RECEIPTS ====================
async function loadCancelledReceipts() {
  const fromDate = document.getElementById('cancelled_from_date').value;
  const toDate = document.getElementById('cancelled_to_date').value;
  const classId = document.getElementById('cancelled_class').value;
  const streamId = document.getElementById('cancelled_stream').value;
  const search = document.getElementById('cancelled_search').value;
  
  const tbody = document.getElementById('cancelled_table_body');
  tbody.innerHTML = '<tr><td colspan="11" class="text-center py-8"><div class="loading-spinner"></div><span class="ml-2">Loading...</span></td></tr>';
  
  try {
    const response = await fetch('/feesystem/api/records/get_cancelled_receipts.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ school_id: schoolId, from_date: fromDate, to_date: toDate, class_id: classId, stream_id: streamId, search: search })
    });
    const data = await response.json();
    
    if (data.success) {
      let totalAmount = 0;
      
      if (data.receipts.length === 0) {
        tbody.innerHTML = '<tr><td colspan="11" class="text-center py-8">No cancelled receipts found</td></tr>';
      } else {
        tbody.innerHTML = data.receipts.map(r => {
          totalAmount += parseFloat(r.amount) || 0;
          return `
            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
              <td class="px-4 py-3">${r.cancelled_date || '-'}</td>
              <td class="px-4 py-3 font-mono">${escapeHtml(r.receipt_no)}</td>
              <td class="px-4 py-3 text-right font-semibold text-red-600">KES ${parseFloat(r.amount).toLocaleString()}</td>
              <td class="px-4 py-3">${escapeHtml(r.payment_mode)}</td>
              <td class="px-4 py-3 font-mono">${escapeHtml(r.admission_no)}</td>
              <td class="px-4 py-3">${escapeHtml(r.student_name)}</td>
              <td class="px-4 py-3">${escapeHtml(r.class_name)}</td>
              <td class="px-4 py-3 font-mono">${escapeHtml(r.code || '-')}</td>
              <td class="px-4 py-3">${escapeHtml(r.notes || '-')}</td>
              <td class="px-4 py-3">${escapeHtml(r.cancelled_by)}</td>
              <td class="px-4 py-3 text-red-600">${escapeHtml(r.reason)}</td>
            </tr>
          `;
        }).join('');
      }
      document.getElementById('cancelled_total').innerHTML = `Total Cancelled Amount: <span class="text-red-600">KES ${totalAmount.toLocaleString()}</span>`;
    }
  } catch (error) {
    console.error('Error loading cancelled receipts:', error);
  }
}

// ==================== LOAD CLASS LIST ====================
async function loadClassList() {
  const classId = document.getElementById('classlist_class').value;
  const streamId = document.getElementById('classlist_stream').value;
  const year = document.getElementById('classlist_year').value;
  const gender = document.getElementById('classlist_gender').value;
  const extraColumn = document.getElementById('classlist_extra').value;
  const search = document.getElementById('classlist_search').value;
  
  const tbody = document.getElementById('classlist_table_body');
  tbody.innerHTML = '<tr><td colspan="4" class="text-center py-8"><div class="loading-spinner"></div><span class="ml-2">Loading...</span></td></tr>';
  
  try {
    const response = await fetch('/feesystem/api/records/get_class_list.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ school_id: schoolId, class_id: classId, stream_id: streamId, year: year, gender: gender, search: search })
    });
    const data = await response.json();
    
    if (data.success) {
      const thead = document.getElementById('classlist_table_headers');
      let extraHeader = '';
      let extraField = '';
      
      if (extraColumn === 'parent') { extraHeader = '<th>Parent Name</th>'; extraField = 'parent_name'; }
      else if (extraColumn === 'phone') { extraHeader = '<th>Phone Number</th>'; extraField = 'phone'; }
      else if (extraColumn === 'dob') { extraHeader = '<th>Date of Birth</th>'; extraField = 'dob'; }
      
      thead.innerHTML = `
        <tr>
          <th>#</th>
          <th>Admission Number</th>
          <th>Full Name</th>
          <th>Gender</th>
          ${extraHeader}
        </tr>
      `;
      
      if (data.students.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center py-8">No students found</td></tr>';
      } else {
        tbody.innerHTML = data.students.map((s, index) => `
          <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
            <td class="px-4 py-3">${index + 1}</td>
            <td class="px-4 py-3 font-mono">${escapeHtml(s.admission_no)}</td>
            <td class="px-4 py-3 font-medium">${escapeHtml(s.full_name)}</td>
            <td class="px-4 py-3">${escapeHtml(s.gender)}</td>
            ${extraColumn ? `<td class="px-4 py-3">${escapeHtml(s[extraField] || '-')}</td>` : ''}
          </tr>
        `).join('');
      }
    }
  } catch (error) {
    console.error('Error loading class list:', error);
  }
}

// ==================== LOAD PRE-PAYMENTS ====================
async function loadPrePayments() {
  const classId = document.getElementById('prepayment_class').value;
  const streamId = document.getElementById('prepayment_stream').value;
  const year = document.getElementById('prepayment_year').value;
  const voteHeadId = document.getElementById('prepayment_votehead').value;
  const search = document.getElementById('prepayment_search').value;
  
  const tbody = document.getElementById('prepayment_table_body');
  tbody.innerHTML = '<tr><td colspan="7" class="text-center py-8"><div class="loading-spinner"></div><span class="ml-2">Loading...</span></td></tr>';
  
  try {
    const response = await fetch('/feesystem/api/records/get_prepayments.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ school_id: schoolId, class_id: classId, stream_id: streamId, year: year, vote_head_id: voteHeadId, search: search })
    });
    const data = await response.json();
    
    if (data.success) {
      let total = 0;
      
      if (data.records.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-8">No pre-payment records found</td></tr>';
      } else {
        tbody.innerHTML = data.records.map(r => {
          total += parseFloat(r.amount) || 0;
          return `
            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
              <td class="px-4 py-3">${r.payment_date || '-'}</td>
              <td class="px-4 py-3 font-mono">${escapeHtml(r.admission_no)}</td>
              <td class="px-4 py-3">${escapeHtml(r.student_name)}</td>
              <td class="px-4 py-3">${escapeHtml(r.class_name)}</td>
              <td class="px-4 py-3">${escapeHtml(r.vote_head_name)}</td>
              <td class="px-4 py-3 text-right font-semibold">KES ${parseFloat(r.amount).toLocaleString()}</td>
              <td class="px-4 py-3">${r.distributed === '1' ? '<span class="px-2 py-1 rounded-full text-xs bg-green-100 text-green-800">Yes</span>' : '<span class="px-2 py-1 rounded-full text-xs bg-yellow-100 text-yellow-800">No</span>'}</td>
            </tr>
          `;
        }).join('');
      }
      document.getElementById('prepayment_total').innerHTML = `KES ${total.toLocaleString()}`;
    }
  } catch (error) {
    console.error('Error loading pre-payments:', error);
  }
}

// ==================== LOAD DAILY SUMMARY ====================
async function loadDailySummary() {
  const fromDate = document.getElementById('summary_from_date').value;
  const toDate = document.getElementById('summary_to_date').value;
  
  try {
    const response = await fetch('/feesystem/api/records/get_daily_summary.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ school_id: schoolId, from_date: fromDate, to_date: toDate })
    });
    const data = await response.json();
    
    if (data.success) {
      // Receipts by mode
      let receiptsTotalCount = 0, receiptsTotalAmount = 0;
      if (data.receipts_by_mode && data.receipts_by_mode.length > 0) {
        document.getElementById('receipts_by_mode_body').innerHTML = data.receipts_by_mode.map(m => {
          receiptsTotalCount += parseInt(m.count) || 0;
          receiptsTotalAmount += parseFloat(m.total) || 0;
          return `<tr class="border-b"><td class="px-4 py-2">${escapeHtml(m.mode)}</td><td class="px-4 py-2 text-right">${m.count}</td><td class="px-4 py-2 text-right">KES ${parseFloat(m.total).toLocaleString()}</td></tr>`;
        }).join('');
      } else {
        document.getElementById('receipts_by_mode_body').innerHTML = '<tr><td colspan="3" class="text-center py-4">No data</td></tr>';
      }
      document.getElementById('receipts_total_count').innerHTML = receiptsTotalCount;
      document.getElementById('receipts_total_amount').innerHTML = `KES ${receiptsTotalAmount.toLocaleString()}`;
      
      // Vouchers by mode
      let vouchersTotalCount = 0, vouchersTotalAmount = 0;
      if (data.vouchers_by_mode && data.vouchers_by_mode.length > 0) {
        document.getElementById('vouchers_by_mode_body').innerHTML = data.vouchers_by_mode.map(m => {
          vouchersTotalCount += parseInt(m.count) || 0;
          vouchersTotalAmount += parseFloat(m.total) || 0;
          return `<tr class="border-b"><td class="px-4 py-2">${escapeHtml(m.mode)}</td><td class="px-4 py-2 text-right">${m.count}</td><td class="px-4 py-2 text-right">KES ${parseFloat(m.total).toLocaleString()}</td></tr>`;
        }).join('');
      } else {
        document.getElementById('vouchers_by_mode_body').innerHTML = '<tr><td colspan="3" class="text-center py-4">No data</td></tr>';
      }
      document.getElementById('vouchers_total_count').innerHTML = vouchersTotalCount;
      document.getElementById('vouchers_total_amount').innerHTML = `KES ${vouchersTotalAmount.toLocaleString()}`;
      
      // Votehead summary
      let creditTotal = 0, debitTotal = 0;
      if (data.votehead_summary && data.votehead_summary.length > 0) {
        document.getElementById('votehead_summary_body').innerHTML = data.votehead_summary.map(v => {
          creditTotal += parseFloat(v.credit) || 0;
          debitTotal += parseFloat(v.debit) || 0;
          const net = (parseFloat(v.credit) || 0) - (parseFloat(v.debit) || 0);
          return `
            <tr class="border-b">
              <td class="px-4 py-2">${escapeHtml(v.votehead_name)}</td>
              <td class="px-4 py-2 text-right">KES ${parseFloat(v.credit).toLocaleString()}</td>
              <td class="px-4 py-2 text-right">KES ${parseFloat(v.debit).toLocaleString()}</td>
              <td class="px-4 py-2 text-right ${net < 0 ? 'text-red-600' : net > 0 ? 'text-green-600' : ''}">KES ${net.toLocaleString()}</td>
            </tr>
          `;
        }).join('');
      } else {
        document.getElementById('votehead_summary_body').innerHTML = '<tr><td colspan="4" class="text-center py-4">No data</td></tr>';
      }
      document.getElementById('votehead_credit_total').innerHTML = `KES ${creditTotal.toLocaleString()}`;
      document.getElementById('votehead_debit_total').innerHTML = `KES ${debitTotal.toLocaleString()}`;
      document.getElementById('votehead_net_total').innerHTML = `KES ${(creditTotal - debitTotal).toLocaleString()}`;
    }
  } catch (error) {
    console.error('Error loading daily summary:', error);
  }
}

// ==================== LOAD STREAMS BASED ON CLASS ====================
async function loadStreams(classId, targetSelectId) {
  console.log(`Loading streams for class: ${classId} into select: ${targetSelectId}`);
  
  const select = document.getElementById(targetSelectId);
  if (!select) {
    console.error(`Select element not found: ${targetSelectId}`);
    return;
  }
  
  if (!classId) {
    console.log('No class selected, clearing streams');
    select.innerHTML = '<option value="">All Streams</option>';
    return;
  }
  
  select.innerHTML = '<option value="">Loading streams...</option>';
  
  try {
    console.log(`Fetching streams from API for class: ${classId}`);
    const response = await fetch('/feesystem/api/feesystem/get_streams.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ school_id: schoolId, class_id: classId })
    });
    const data = await response.json();
    console.log('Streams API response:', data);
    
    if (data.success && data.streams) {
      if (data.streams.length === 0) {
        console.log('No streams found for this class');
        select.innerHTML = '<option value="">No streams available</option>';
      } else {
        console.log(`Found ${data.streams.length} streams`);
        select.innerHTML = '<option value="">All Streams</option>' + 
          data.streams.map(s => `<option value="${s.id}">${escapeHtml(s.stream_name || s.name)}</option>`).join('');
      }
    } else {
      console.warn('Streams API returned no data:', data);
      select.innerHTML = '<option value="">All Streams</option>';
    }
  } catch (error) {
    console.error('Error loading streams:', error);
    select.innerHTML = '<option value="">All Streams</option>';
  }
}

// ==================== LOAD CLASSES DROPDOWNS ====================
async function loadClasses() {
  try {
    console.log('Loading classes...');
    const response = await fetch('/feesystem/api/feesystem/get_classes.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ school_id: schoolId })
    });
    const data = await response.json();
    console.log('Classes API response:', data);
    
    if (data.success && data.classes) {
      let options = '<option value="">All Classes</option>';
      
      data.classes.forEach(c => {
        const className = c.class_level || c.name || 'Unnamed Class';
        options += `<option value="${c.id}">${escapeHtml(className)}</option>`;
      });
      
      console.log(`Generated ${data.classes.length} class options`);
      
      const classSelectors = ['receipts_class', 'cancelled_class', 'classlist_class', 'prepayment_class'];
      classSelectors.forEach(selector => {
        const element = document.getElementById(selector);
        if (element) {
          const oldValue = element.value;
          element.innerHTML = options;
          if (oldValue && data.classes.some(c => c.id == oldValue)) {
            element.value = oldValue;
          }
          console.log(`Updated ${selector} with ${element.options.length} options`);
        } else {
          console.warn(`Element not found: ${selector}`);
        }
      });
    } else {
      console.warn('No classes data received:', data);
    }
  } catch (error) { 
    console.error('Error loading classes:', error);
  }
}

// ==================== LOAD VOTE HEADS ====================
async function loadVoteHeads() {
  try {
    const response = await fetch('/feesystem/api/feesystem/get_vote_heads.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ school_id: schoolId })
    });
    const data = await response.json();
    if (data.success && data.vote_heads) {
      const options = '<option value="">All Vote Heads</option>' + 
        data.vote_heads.map(v => `<option value="${v.id}">${escapeHtml(v.name)}</option>`).join('');
      const voteHeadSelect = document.getElementById('prepayment_votehead');
      if (voteHeadSelect) voteHeadSelect.innerHTML = options;
    }
  } catch (error) { 
    console.error('Error loading vote heads:', error);
  }
}

// ==================== SETUP EVENT LISTENERS FOR STREAMS ====================
function setupStreamListeners() {
  console.log('Setting up stream listeners...');
  
  // Receipts tab - class change loads streams
  const receiptsClass = document.getElementById('receipts_class');
  if (receiptsClass) {
    receiptsClass.addEventListener('change', (e) => {
      console.log('Receipts class changed to:', e.target.value);
      loadStreams(e.target.value, 'receipts_stream');
    });
  } else {
    console.warn('receipts_class element not found');
  }
  
  // Cancelled tab - class change loads streams
  const cancelledClass = document.getElementById('cancelled_class');
  if (cancelledClass) {
    cancelledClass.addEventListener('change', (e) => {
      console.log('Cancelled class changed to:', e.target.value);
      loadStreams(e.target.value, 'cancelled_stream');
    });
  } else {
    console.warn('cancelled_class element not found');
  }
  
  // Class List tab - class change loads streams
  const classlistClass = document.getElementById('classlist_class');
  if (classlistClass) {
    classlistClass.addEventListener('change', (e) => {
      console.log('Class list class changed to:', e.target.value);
      loadStreams(e.target.value, 'classlist_stream');
    });
  } else {
    console.warn('classlist_class element not found');
  }
  
  // Prepayment tab - class change loads streams
  const prepaymentClass = document.getElementById('prepayment_class');
  if (prepaymentClass) {
    prepaymentClass.addEventListener('change', (e) => {
      console.log('Prepayment class changed to:', e.target.value);
      loadStreams(e.target.value, 'prepayment_stream');
    });
  } else {
    console.warn('prepayment_class element not found');
  }
  
  console.log('Stream listeners setup complete');
}

// Export Receipts to Excel
document.getElementById('export_receipts_btn')?.addEventListener('click', () => {
  const rows = [['Date', 'Receipt No.', 'Amount', 'Mode', 'Adm No.', 'Student', 'Class', 'Notes']];
  document.querySelectorAll('#receipts_table_body tr').forEach(row => {
    if (row.cells && row.cells.length > 1) {
      const rowData = Array.from(row.cells).map(cell => cell.innerText.trim());
      if (rowData.length > 0) rows.push(rowData);
    }
  });
  const ws = XLSX.utils.aoa_to_sheet(rows);
  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, 'Receipts');
  XLSX.writeFile(wb, `receipts_${new Date().toISOString().split('T')[0]}.xlsx`);
});

// Print Receipts
document.getElementById('print_receipts_btn')?.addEventListener('click', () => {
  const printWindow = window.open('', '_blank');
  printWindow.document.write(`
    <html><head><title>Receipts Report</title>
    <style>body{font-family:Arial;padding:20px} table{border-collapse:collapse;width:100%} th,td{border:1px solid #ddd;padding:8px;text-align:left} th{background:#f5f5f5}</style>
    </head><body>
    <h2>Receipts Report</h2>
    <table>${document.querySelector('#receipts_table_body').parentElement.outerHTML}</table>
    </body></html>
  `);
  printWindow.document.close();
  printWindow.print();
});

// Select all checkboxes
document.getElementById('select_all_receipts')?.addEventListener('change', (e) => {
  document.querySelectorAll('.receipt-checkbox').forEach(cb => cb.checked = e.target.checked);
});

function escapeHtml(text) {
  if (!text) return '';
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

// ==================== INITIALIZE ====================
document.addEventListener('DOMContentLoaded', () => {
  console.log('DOM loaded, initializing Records page...');
  setDefaultDates();
  loadClasses();
  loadVoteHeads();
  setupStreamListeners();
  loadReceipts();
});

// Also add event listeners for filter changes that trigger data reload
['receipts_from_date', 'receipts_to_date', 'receipts_class', 'receipts_stream', 'receipts_year', 'receipts_search'].forEach(id => {
  const el = document.getElementById(id);
  if (el) {
    el.addEventListener('change', () => loadReceipts());
    el.addEventListener('input', () => loadReceipts());
  }
});

['cancelled_from_date', 'cancelled_to_date', 'cancelled_class', 'cancelled_stream', 'cancelled_search'].forEach(id => {
  const el = document.getElementById(id);
  if (el) {
    el.addEventListener('change', () => loadCancelledReceipts());
    el.addEventListener('input', () => loadCancelledReceipts());
  }
});

['classlist_class', 'classlist_stream', 'classlist_year', 'classlist_gender', 'classlist_extra', 'classlist_search'].forEach(id => {
  const el = document.getElementById(id);
  if (el) {
    el.addEventListener('change', () => loadClassList());
    el.addEventListener('input', () => loadClassList());
  }
});

['prepayment_class', 'prepayment_stream', 'prepayment_year', 'prepayment_votehead', 'prepayment_search'].forEach(id => {
  const el = document.getElementById(id);
  if (el) {
    el.addEventListener('change', () => loadPrePayments());
    el.addEventListener('input', () => loadPrePayments());
  }
});

['summary_from_date', 'summary_to_date'].forEach(id => {
  const el = document.getElementById(id);
  if (el) {
    el.addEventListener('change', () => loadDailySummary());
  }
});
</script>

<?php include_once('../../includes/footer.php'); ?>