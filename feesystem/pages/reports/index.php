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
/* Base Responsive Styles */
* {
    box-sizing: border-box;
}

/* Toggle Button Styles - Mobile First */
.main-toggle-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.5rem 0.75rem;
    font-size: 0.75rem;
    font-weight: 500;
    color: #6b7280;
    background: transparent;
    border: none;
    border-bottom: 2px solid transparent;
    transition: all 0.2s ease;
    cursor: pointer;
    white-space: nowrap;
}

.main-toggle-btn i {
    margin-right: 0.375rem;
    font-size: 0.875rem;
}

@media (min-width: 640px) {
    .main-toggle-btn {
        padding: 0.75rem 1.25rem;
        font-size: 0.875rem;
    }
    .main-toggle-btn i {
        margin-right: 0.5rem;
        font-size: 1rem;
    }
}

@media (min-width: 1024px) {
    .main-toggle-btn {
        padding: 0.75rem 1.5rem;
    }
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

/* Tab Navigation - Horizontal Scroll on Mobile */
.tab-nav-wrapper {
    position: relative;
    overflow-x: auto;
    overflow-y: hidden;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
}

.tab-nav-wrapper::-webkit-scrollbar {
    height: 3px;
}

.tab-nav-wrapper::-webkit-scrollbar-track {
    background: #e5e7eb;
    border-radius: 10px;
}

.tab-nav-wrapper::-webkit-scrollbar-thumb {
    background: #4f46e5;
    border-radius: 10px;
}

.tab-nav {
    display: inline-flex;
    min-width: max-content;
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

/* Summary Cards - Responsive Grid */
.summary-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem;
    margin-bottom: 1.5rem;
}

@media (min-width: 640px) {
    .summary-grid {
        gap: 1rem;
        margin-bottom: 1.75rem;
    }
}

@media (min-width: 768px) {
    .summary-grid {
        grid-template-columns: repeat(4, 1fr);
        gap: 1rem;
        margin-bottom: 2rem;
    }
}

.summary-card {
    background: #f9fafb;
    border-radius: 0.5rem;
    padding: 0.75rem;
    text-align: center;
    transition: all 0.2s ease;
}

@media (min-width: 640px) {
    .summary-card {
        padding: 1rem;
    }
}

.summary-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.summary-card p:first-child {
    font-size: 0.7rem;
    color: #6b7280;
    margin-bottom: 0.25rem;
}

@media (min-width: 640px) {
    .summary-card p:first-child {
        font-size: 0.75rem;
        margin-bottom: 0.5rem;
    }
}

.summary-card .amount {
    font-size: 1rem;
    font-weight: bold;
}

@media (min-width: 640px) {
    .summary-card .amount {
        font-size: 1.25rem;
    }
}

@media (min-width: 1024px) {
    .summary-card .amount {
        font-size: 1.5rem;
    }
}

/* Responsive Tables */
.table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    margin: 0 -1rem;
    padding: 0 1rem;
}

@media (min-width: 640px) {
    .table-responsive {
        margin: 0;
        padding: 0;
    }
}

.report-table {
    width: 100%;
    min-width: 640px;
    font-size: 0.75rem;
    border-collapse: collapse;
}

@media (min-width: 640px) {
    .report-table {
        min-width: auto;
        font-size: 0.875rem;
    }
}

.report-table th {
    background-color: #f8fafc;
    padding: 0.5rem 0.75rem;
    text-align: left;
    font-weight: 600;
    border-bottom: 2px solid #e2e8f0;
    white-space: nowrap;
}

@media (min-width: 640px) {
    .report-table th {
        padding: 0.75rem 1rem;
        white-space: normal;
    }
}

.report-table td {
    padding: 0.5rem 0.75rem;
    border-bottom: 1px solid #e2e8f0;
}

@media (min-width: 640px) {
    .report-table td {
        padding: 0.75rem 1rem;
    }
}

.report-table tr:hover {
    background-color: rgba(79, 70, 229, 0.05);
}

/* Responsive Filter Form */
.filter-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 0.75rem;
}

@media (min-width: 640px) {
    .filter-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
    }
}

@media (min-width: 1024px) {
    .filter-grid {
        grid-template-columns: repeat(4, 1fr);
        gap: 1rem;
    }
}

.filter-input, .filter-select {
    width: 100%;
    padding: 0.5rem 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 0.5rem;
    transition: all 0.2s ease;
    font-size: 0.875rem;
}

@media (min-width: 640px) {
    .filter-input, .filter-select {
        padding: 0.5rem 0.75rem;
    }
}

@media (min-width: 1024px) {
    .filter-input, .filter-select {
        padding: 0.5rem 0.75rem;
    }
}

.filter-input:focus, .filter-select:focus {
    border-color: #4f46e5;
    outline: none;
    ring: 2px solid #4f46e5;
}

/* Button Group */
.button-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

@media (min-width: 640px) {
    .button-group {
        flex-direction: row;
        gap: 0.5rem;
    }
}

.action-btn {
    flex: 1;
    padding: 0.5rem 0.75rem;
    border-radius: 0.5rem;
    font-size: 0.75rem;
    font-weight: 500;
    transition: all 0.2s ease;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.375rem;
}

@media (min-width: 640px) {
    .action-btn {
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
    }
}

.action-btn i {
    font-size: 0.75rem;
}

@media (min-width: 640px) {
    .action-btn i {
        font-size: 0.875rem;
    }
}

.btn-indigo {
    background-color: #4f46e5;
    color: white;
}

.btn-indigo:hover {
    background-color: #4338ca;
}

.btn-gray {
    background-color: #6b7280;
    color: white;
}

.btn-gray:hover {
    background-color: #4b5563;
}

.btn-green {
    background-color: #10b981;
    color: white;
}

.btn-green:hover {
    background-color: #059669;
}

/* Dark Mode Adjustments */
.dark .summary-card {
    background-color: #1f2937;
}

.dark .summary-card p:first-child {
    color: #9ca3af;
}

.dark .report-table th {
    background-color: #1f2937;
    border-bottom-color: #374151;
}

.dark .report-table td {
    border-bottom-color: #374151;
}

/* Loading State */
.loading-spinner {
    display: inline-block;
    width: 1.5rem;
    height: 1.5rem;
    border: 2px solid #e5e7eb;
    border-top-color: #4f46e5;
    border-radius: 50%;
    animation: spin 0.6s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Print Styles */
@media print {
    .sidebar, header, .main-toggle-btn, .action-buttons, .no-print,
    .tab-nav-wrapper, .filter-grid, .button-group {
        display: none !important;
    }
    .main-content {
        margin: 0 !important;
        padding: 0 !important;
    }
    .report-table {
        min-width: auto;
    }
    .summary-card {
        break-inside: avoid;
    }
}

/* Status Badge */
.status-badge {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    border-radius: 9999px;
    font-size: 0.7rem;
    font-weight: 500;
}

@media (min-width: 640px) {
    .status-badge {
        padding: 0.25rem 0.75rem;
        font-size: 0.75rem;
    }
}

/* Filter Row on Mobile */
.filter-row {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

@media (min-width: 768px) {
    .filter-row {
        flex-direction: row;
        align-items: flex-end;
        gap: 1rem;
    }
    .filter-row > div {
        flex: 1;
    }
}
</style>

<main class="main-content flex-grow flex flex-col">
  <header class="bg-white shadow-sm dark:bg-gray-800 dark:border-gray-700">
    <div class="px-3 py-2 sm:px-4 sm:py-3 flex justify-between items-center">
      <div class="flex items-center">
        <button id="mobile-toggle" class="mr-3 text-gray-500 hover:text-indigo-600 focus:outline-none lg:hidden">
          <i class="fas fa-bars text-lg sm:text-xl"></i>
        </button>
        <h1 id="page-title" class="text-lg sm:text-xl font-semibold text-gray-800 dark:text-white">Financial Reports</h1>
      </div>
      
      <div class="flex items-center space-x-2 sm:space-x-4">
        <button id="theme-toggle" class="p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 focus:outline-none">
          <i class="fas fa-sun text-yellow-500 dark:hidden"></i>
          <i class="fas fa-moon text-blue-300 hidden dark:block"></i>
        </button>
        
        <div class="relative" id="user-menu-container">
          <button id="user-menu-button" class="flex items-center focus:outline-none">
            <img src="https://cdn.pixabay.com/photo/2015/10/05/22/37/blank-profile-picture-973460_960_720.png" alt="User Avatar" class="w-7 h-7 sm:w-8 sm:h-8 rounded-full mr-1 sm:mr-2">
            <span class="hidden sm:block text-sm font-medium text-gray-700 dark:text-gray-200"><?php echo htmlspecialchars($_SESSION['email'] ?? 'User'); ?></span>
            <i class="fas fa-chevron-down text-xs ml-1 sm:ml-2 text-gray-500"></i>
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

  <div class="flex-grow p-3 sm:p-4 md:p-6 overflow-auto">
    <!-- Report Toggle Buttons - Horizontal Scroll on Mobile -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm mb-4 sm:mb-6">
      <div class="border-b border-gray-200 dark:border-gray-700">
        <div class="tab-nav-wrapper">
          <nav class="tab-nav" id="reportTabNav">
            <button class="main-toggle-btn active" data-report-tab="cashbook">
              <i class="fas fa-book"></i><span class="hidden sm:inline">Cashbook</span><span class="sm:hidden">Cash</span>
            </button>
            <button class="main-toggle-btn" data-report-tab="feeregister">
              <i class="fas fa-file-invoice-dollar"></i><span class="hidden sm:inline">Fee Register</span><span class="sm:hidden">Fees</span>
            </button>
            <button class="main-toggle-btn" data-report-tab="ledger">
              <i class="fas fa-list-ul"></i><span class="hidden sm:inline">Ledger</span><span class="sm:hidden">Ledger</span>
            </button>
            <button class="main-toggle-btn" data-report-tab="expenditure">
              <i class="fas fa-chart-line"></i><span class="hidden sm:inline">Expenditure</span><span class="sm:hidden">Expense</span>
            </button>
            <button class="main-toggle-btn" data-report-tab="trialbalance">
              <i class="fas fa-balance-scale"></i><span class="hidden sm:inline">Trial Balance</span><span class="sm:hidden">TB</span>
            </button>
          </nav>
        </div>
      </div>
    </div>

    <!-- Date Range Filter (Common for all reports) -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-3 sm:p-4 mb-4 sm:mb-6">
      <div class="filter-grid">
        <div>
          <label class="block text-xs sm:text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">From Date</label>
          <input type="date" id="from_date" class="filter-input dark:bg-gray-700 dark:border-gray-600">
        </div>
        <div>
          <label class="block text-xs sm:text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">To Date</label>
          <input type="date" id="to_date" class="filter-input dark:bg-gray-700 dark:border-gray-600">
        </div>
        <div>
          <label class="block text-xs sm:text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Additional Filter</label>
          <select id="additional_filter" class="filter-select dark:bg-gray-700 dark:border-gray-600">
            <option value="">All</option>
            <option value="cash">Cash Only</option>
            <option value="bank">Bank Only</option>
            <option value="mpesa">M-Pesa Only</option>
          </select>
        </div>
        <div class="button-group">
          <button id="refreshReportBtn" class="action-btn btn-indigo">
            <i class="fas fa-sync-alt"></i> Generate
          </button>
          <button id="printReportBtn" class="action-btn btn-gray">
            <i class="fas fa-print"></i> Print
          </button>
          <button id="exportReportBtn" class="action-btn btn-green">
            <i class="fas fa-file-excel"></i> Export
          </button>
        </div>
      </div>
    </div>

    <!-- ==================== TAB 1: CASHBOOK ==================== -->
    <div id="report-tab-cashbook" class="main-tab-content active">
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
        <div class="bg-gray-50 dark:bg-gray-700 px-3 py-2 sm:px-4 sm:py-3 border-b">
          <h3 class="font-semibold text-gray-800 dark:text-white text-sm sm:text-base">
            <i class="fas fa-book text-indigo-500 mr-2"></i>Cashbook Report
          </h3>
          <p class="text-xs text-gray-500 mt-1">From <span id="cashbook_from_date">-</span> to <span id="cashbook_to_date">-</span></p>
        </div>
        <div class="p-3 sm:p-4">
          <!-- Summary Cards -->
          <div class="summary-grid">
            <div class="summary-card">
              <p class="text-gray-600">Total Receipts</p>
              <p class="amount text-green-600" id="cashbook_total_receipts">KES 0</p>
            </div>
            <div class="summary-card">
              <p class="text-gray-600">Total Payments</p>
              <p class="amount text-red-600" id="cashbook_total_payments">KES 0</p>
            </div>
            <div class="summary-card">
              <p class="text-gray-600">Net Cash Flow</p>
              <p class="amount text-blue-600" id="cashbook_net_flow">KES 0</p>
            </div>
            <div class="summary-card">
              <p class="text-gray-600">Opening Balance</p>
              <p class="amount text-purple-600" id="cashbook_opening_balance">KES 0</p>
            </div>
          </div>

          <div class="table-responsive">
            <table class="report-table">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Transaction ID</th>
                  <th>Description</th>
                  <th>Receipts (KES)</th>
                  <th>Payments (KES)</th>
                  <th>Balance (KES)</th>
                </tr>
              </thead>
              <tbody id="cashbook_table_body">
                <tr><td colspan="6" class="text-center py-6 text-gray-500">Select date range and click Generate</td></tr>
              </tbody>
              <tfoot class="bg-gray-50 font-bold">
                <tr>
                  <td colspan="3" class="text-right">Totals:</td>
                  <td id="cashbook_receipts_total">KES 0</td>
                  <td id="cashbook_payments_total">KES 0</td>
                  <td id="cashbook_balance_total">KES 0</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- ==================== TAB 2: FEE REGISTER ==================== -->
    <div id="report-tab-feeregister" class="main-tab-content">
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
        <div class="bg-gray-50 dark:bg-gray-700 px-3 py-2 sm:px-4 sm:py-3 border-b">
          <h3 class="font-semibold text-gray-800 dark:text-white text-sm sm:text-base">
            <i class="fas fa-file-invoice-dollar text-green-500 mr-2"></i>Fee Register
          </h3>
          <p class="text-xs text-gray-500 mt-1">From <span id="feeregister_from_date">-</span> to <span id="feeregister_to_date">-</span></p>
        </div>
        <div class="p-3 sm:p-4">
          <div class="summary-grid">
            <div class="summary-card">
              <p class="text-gray-600">Total Expected</p>
              <p class="amount text-green-600" id="feeregister_expected">KES 0</p>
            </div>
            <div class="summary-card">
              <p class="text-gray-600">Total Collected</p>
              <p class="amount text-blue-600" id="feeregister_collected">KES 0</p>
            </div>
            <div class="summary-card">
              <p class="text-gray-600">Outstanding</p>
              <p class="amount text-red-600" id="feeregister_outstanding">KES 0</p>
            </div>
            <div class="summary-card">
              <p class="text-gray-600">Collection Rate</p>
              <p class="amount text-purple-600" id="feeregister_rate">0%</p>
            </div>
          </div>

          <div class="filter-row mb-4">
            <div>
              <select id="fee_class_filter" class="filter-select w-full dark:bg-gray-700 dark:border-gray-600">
                <option value="">All Classes</option>
              </select>
            </div>
            <div>
              <select id="fee_stream_filter" class="filter-select w-full dark:bg-gray-700 dark:border-gray-600">
                <option value="">All Streams</option>
              </select>
            </div>
          </div>

          <div class="table-responsive">
            <table class="report-table">
              <thead>
                <tr>
                  <th>Adm No.</th>
                  <th>Student Name</th>
                  <th>Class</th>
                  <th>Expected (KES)</th>
                  <th>Paid (KES)</th>
                  <th>Balance (KES)</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody id="feeregister_table_body">
                <tr><td colspan="7" class="text-center py-6 text-gray-500">Select date range and click Generate</td></tr>
              </tbody>
              <tfoot class="bg-gray-50 font-bold">
                <tr>
                  <td colspan="3" class="text-right">Totals:</td>
                  <td id="feeregister_expected_total">KES 0</td>
                  <td id="feeregister_paid_total">KES 0</td>
                  <td id="feeregister_balance_total">KES 0</td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- ==================== TAB 3: LEDGER ==================== -->
    <div id="report-tab-ledger" class="main-tab-content">
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
        <div class="bg-gray-50 dark:bg-gray-700 px-3 py-2 sm:px-4 sm:py-3 border-b">
          <h3 class="font-semibold text-gray-800 dark:text-white text-sm sm:text-base">
            <i class="fas fa-list-ul text-purple-500 mr-2"></i>General Ledger
          </h3>
          <p class="text-xs text-gray-500 mt-1">From <span id="ledger_from_date">-</span> to <span id="ledger_to_date">-</span></p>
        </div>
        <div class="p-3 sm:p-4">
          <div class="mb-4">
            <select id="ledger_account_filter" class="filter-select w-full dark:bg-gray-700 dark:border-gray-600">
              <option value="">All Accounts</option>
              <option value="fee_collections">Fee Collections</option>
              <option value="payment_vouchers">Payment Vouchers</option>
              <option value="payroll">Payroll</option>
              <option value="bank_transactions">Bank Transactions</option>
              <option value="pocket_money">Pocket Money</option>
            </select>
          </div>

          <div class="table-responsive">
            <table class="report-table">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Transaction ID</th>
                  <th>Account</th>
                  <th>Description</th>
                  <th>Debit (KES)</th>
                  <th>Credit (KES)</th>
                </tr>
              </thead>
              <tbody id="ledger_table_body">
                <tr><td colspan="6" class="text-center py-6 text-gray-500">Select date range and click Generate</td></tr>
              </tbody>
              <tfoot class="bg-gray-50 font-bold">
                <tr>
                  <td colspan="4" class="text-right">Totals:</td>
                  <td id="ledger_debit_total">KES 0</td>
                  <td id="ledger_credit_total">KES 0</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- ==================== TAB 4: EXPENDITURE ==================== -->
    <div id="report-tab-expenditure" class="main-tab-content">
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
        <div class="bg-gray-50 dark:bg-gray-700 px-3 py-2 sm:px-4 sm:py-3 border-b">
          <h3 class="font-semibold text-gray-800 dark:text-white text-sm sm:text-base">
            <i class="fas fa-chart-line text-orange-500 mr-2"></i>Expenditure Report
          </h3>
          <p class="text-xs text-gray-500 mt-1">From <span id="expenditure_from_date">-</span> to <span id="expenditure_to_date">-</span></p>
        </div>
        <div class="p-3 sm:p-4">
          <div class="summary-grid">
            <div class="summary-card">
              <p class="text-gray-600">Total Expenditure</p>
              <p class="amount text-red-600" id="expenditure_total">KES 0</p>
            </div>
            <div class="summary-card">
              <p class="text-gray-600">Average Daily Spend</p>
              <p class="amount text-blue-600" id="expenditure_avg">KES 0</p>
            </div>
            <div class="summary-card">
              <p class="text-gray-600">Largest Payment</p>
              <p class="amount text-green-600" id="expenditure_largest">KES 0</p>
            </div>
          </div>

          <div class="table-responsive">
            <table class="report-table">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Voucher No.</th>
                  <th>Payee</th>
                  <th>Vote Head</th>
                  <th>Description</th>
                  <th>Amount (KES)</th>
                  <th>Mode</th>
                </tr>
              </thead>
              <tbody id="expenditure_table_body">
                <tr><td colspan="7" class="text-center py-6 text-gray-500">Select date range and click Generate</td></tr>
              </tbody>
              <tfoot class="bg-gray-50 font-bold">
                <tr>
                  <td colspan="5" class="text-right">Total Expenditure:</td>
                  <td id="expenditure_total_amount">KES 0</td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- ==================== TAB 5: TRIAL BALANCE ==================== -->
    <div id="report-tab-trialbalance" class="main-tab-content">
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
        <div class="bg-gray-50 dark:bg-gray-700 px-3 py-2 sm:px-4 sm:py-3 border-b">
          <h3 class="font-semibold text-gray-800 dark:text-white text-sm sm:text-base">
            <i class="fas fa-balance-scale text-teal-500 mr-2"></i>Trial Balance
          </h3>
          <p class="text-xs text-gray-500 mt-1">As at <span id="trialbalance_date">-</span></p>
        </div>
        <div class="p-3 sm:p-4">
          <div class="summary-grid">
            <div class="summary-card">
              <p class="text-gray-600">Total Debits</p>
              <p class="amount text-green-600" id="trialbalance_debits">KES 0</p>
            </div>
            <div class="summary-card">
              <p class="text-gray-600">Total Credits</p>
              <p class="amount text-red-600" id="trialbalance_credits">KES 0</p>
            </div>
          </div>

          <div class="table-responsive">
            <table class="report-table">
              <thead>
                <tr>
                  <th>Account Code</th>
                  <th>Account Name</th>
                  <th>Debit (KES)</th>
                  <th>Credit (KES)</th>
                </tr>
              </thead>
              <tbody id="trialbalance_table_body">
                <tr><td colspan="4" class="text-center py-6 text-gray-500">Select date and click Generate</td></tr>
              </tbody>
              <tfoot class="bg-gray-50 font-bold">
                <tr>
                  <td colspan="2" class="text-right">Totals:</td>
                  <td id="trialbalance_debits_total">KES 0</td>
                  <td id="trialbalance_credits_total">KES 0</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>

<script>
const schoolId = <?php echo json_encode($school_id); ?>;
const userId = <?php echo json_encode($user_id); ?>;
let currentReportTab = 'cashbook';

// Set default dates
function setDefaultDates() {
    const today = new Date();
    const firstDayOfYear = new Date(today.getFullYear(), 0, 1);
    document.getElementById('from_date').value = formatDate(firstDayOfYear);
    document.getElementById('to_date').value = formatDate(today);
}

function formatDate(date) {
    return date.toISOString().split('T')[0];
}

function formatNumber(num) {
    if (num === undefined || num === null) return '0';
    return parseFloat(num).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatCurrency(amount) {
    return `KES ${formatNumber(amount)}`;
}

// ==================== LOAD CLASSES AND STREAMS ====================
async function loadClasses() {
    try {
        const response = await fetch('/feesystem/api/feesystem/get_classes.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ school_id: schoolId })
        });
        const data = await response.json();
        if (data.success) {
            const options = '<option value="">All Classes</option>' + data.classes.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
            document.getElementById('fee_class_filter').innerHTML = options;
        }
    } catch (error) { console.error('Error loading classes:', error); }
}

// ==================== CASHBOOK REPORT ====================
async function loadCashbookReport() {
    const fromDate = document.getElementById('from_date').value;
    const toDate = document.getElementById('to_date').value;
    const filter = document.getElementById('additional_filter').value;
    
    document.getElementById('cashbook_from_date').innerText = fromDate || '-';
    document.getElementById('cashbook_to_date').innerText = toDate || '-';
    
    try {
        const response = await fetch('/feesystem/api/reports/get_cashbook.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ school_id: schoolId, from_date: fromDate, to_date: toDate, filter: filter })
        });
        const data = await response.json();
        const tbody = document.getElementById('cashbook_table_body');
        
        if (data.success && data.transactions && data.transactions.length > 0) {
            let runningBalance = data.opening_balance || 0;
            tbody.innerHTML = data.transactions.map(t => {
                const isReceipt = t.type === 'receipt' || t.type === 'deposit';
                const amount = parseFloat(t.amount);
                if (isReceipt) runningBalance += amount;
                else runningBalance -= amount;
                
                const dateObj = new Date(t.transaction_date);
                const formattedDate = dateObj.toLocaleDateString('en-KE');
                
                return `
                    <tr>
                        <td>${formattedDate}</td>
                        <td>${escapeHtml(t.transaction_no)}</td>
                        <td>${escapeHtml(t.description)}</td>
                        <td class="${isReceipt ? 'text-green-600' : ''}">${isReceipt ? formatCurrency(amount) : '-'}</td>
                        <td class="${!isReceipt ? 'text-red-600' : ''}">${!isReceipt ? formatCurrency(amount) : '-'}</td>
                        <td>${formatCurrency(runningBalance)}</td>
                    </tr>
                `;
            }).join('');
            
            document.getElementById('cashbook_receipts_total').innerHTML = formatCurrency(data.total_receipts);
            document.getElementById('cashbook_payments_total').innerHTML = formatCurrency(data.total_payments);
            document.getElementById('cashbook_balance_total').innerHTML = formatCurrency(data.total_receipts - data.total_payments);
            document.getElementById('cashbook_total_receipts').innerHTML = formatCurrency(data.total_receipts);
            document.getElementById('cashbook_total_payments').innerHTML = formatCurrency(data.total_payments);
            document.getElementById('cashbook_net_flow').innerHTML = formatCurrency(data.total_receipts - data.total_payments);
            document.getElementById('cashbook_opening_balance').innerHTML = formatCurrency(data.opening_balance || 0);
        } else {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-6 text-gray-500">No transactions found for the selected period</td></tr>';
        }
    } catch (error) { console.error('Error loading cashbook:', error); }
}

// ==================== FEE REGISTER REPORT ====================
async function loadFeeRegisterReport() {
    const fromDate = document.getElementById('from_date').value;
    const toDate = document.getElementById('to_date').value;
    const classId = document.getElementById('fee_class_filter').value;
    const streamId = document.getElementById('fee_stream_filter').value;
    
    document.getElementById('feeregister_from_date').innerText = fromDate || '-';
    document.getElementById('feeregister_to_date').innerText = toDate || '-';
    
    try {
        const response = await fetch('/feesystem/api/reports/get_fee_register.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ school_id: schoolId, from_date: fromDate, to_date: toDate, class_id: classId, stream_id: streamId })
        });
        const data = await response.json();
        const tbody = document.getElementById('feeregister_table_body');
        
        if (data.success && data.students && data.students.length > 0) {
            let totalExpected = 0, totalPaid = 0, totalBalance = 0;
            tbody.innerHTML = data.students.map(s => {
                totalExpected += s.expected_fees;
                totalPaid += s.paid_amount;
                totalBalance += s.balance;
                let statusClass = s.balance <= 0 ? 'text-green-600' : (s.balance > 1000 ? 'text-red-600' : 'text-yellow-600');
                let statusText = s.balance <= 0 ? 'Paid' : (s.balance > 1000 ? 'Overdue' : 'Partially Paid');
                return `
                    <tr>
                        <td>${escapeHtml(s.admission_no)}</td>
                        <td>${escapeHtml(s.student_name)}</td>
                        <td>${escapeHtml(s.class_name)}</td>
                        <td>${formatCurrency(s.expected_fees)}</td>
                        <td>${formatCurrency(s.paid_amount)}</td>
                        <td class="${statusClass}">${formatCurrency(s.balance)}</td>
                        <td><span class="status-badge ${statusClass} bg-opacity-10">${statusText}</span></td>
                    </tr>
                `;
            }).join('');
            
            document.getElementById('feeregister_expected_total').innerHTML = formatCurrency(totalExpected);
            document.getElementById('feeregister_paid_total').innerHTML = formatCurrency(totalPaid);
            document.getElementById('feeregister_balance_total').innerHTML = formatCurrency(totalBalance);
            document.getElementById('feeregister_expected').innerHTML = formatCurrency(totalExpected);
            document.getElementById('feeregister_collected').innerHTML = formatCurrency(totalPaid);
            document.getElementById('feeregister_outstanding').innerHTML = formatCurrency(totalBalance);
            const rate = totalExpected > 0 ? ((totalPaid / totalExpected) * 100).toFixed(1) : 0;
            document.getElementById('feeregister_rate').innerHTML = `${rate}%`;
        } else {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-6 text-gray-500">No students found</td></tr>';
        }
    } catch (error) { console.error('Error loading fee register:', error); }
}

// ==================== LEDGER REPORT ====================
async function loadLedgerReport() {
    const fromDate = document.getElementById('from_date').value;
    const toDate = document.getElementById('to_date').value;
    const account = document.getElementById('ledger_account_filter').value;
    
    document.getElementById('ledger_from_date').innerText = fromDate || '-';
    document.getElementById('ledger_to_date').innerText = toDate || '-';
    
    try {
        const response = await fetch('/feesystem/api/reports/get_ledger.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ school_id: schoolId, from_date: fromDate, to_date: toDate, account: account })
        });
        const data = await response.json();
        const tbody = document.getElementById('ledger_table_body');
        
        if (data.success && data.entries && data.entries.length > 0) {
            tbody.innerHTML = data.entries.map(e => {
                const dateObj = new Date(e.transaction_date);
                const formattedDate = dateObj.toLocaleDateString('en-KE');
                
                return `
                    <tr class="border-b hover:bg-gray-50">
                        <td class="px-3 py-2 sm:px-4">${formattedDate}</td>
                        <td class="px-3 py-2 sm:px-4">${escapeHtml(e.transaction_no || '-')}</td>
                        <td class="px-3 py-2 sm:px-4">${escapeHtml(e.account_name)}</td>
                        <td class="px-3 py-2 sm:px-4">${escapeHtml(e.description || '-')}</td>
                        <td class="px-3 py-2 sm:px-4 text-right text-green-600">${e.debit > 0 ? formatCurrency(e.debit) : '-'}</td>
                        <td class="px-3 py-2 sm:px-4 text-right text-red-600">${e.credit > 0 ? formatCurrency(e.credit) : '-'}</td>
                    </tr>
                `;
            }).join('');
            
            document.getElementById('ledger_debit_total').innerHTML = formatCurrency(data.total_debit);
            document.getElementById('ledger_credit_total').innerHTML = formatCurrency(data.total_credit);
        } else {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-6 text-gray-500">No ledger entries found for the selected period</td></tr>';
            document.getElementById('ledger_debit_total').innerHTML = formatCurrency(0);
            document.getElementById('ledger_credit_total').innerHTML = formatCurrency(0);
        }
    } catch (error) { 
        console.error('Error loading ledger:', error);
        document.getElementById('ledger_table_body').innerHTML = '<tr><td colspan="6" class="text-center py-6 text-red-500">Error loading ledger data</td></tr>';
    }
}

// ==================== EXPENDITURE REPORT ====================
async function loadExpenditureReport() {
    const fromDate = document.getElementById('from_date').value;
    const toDate = document.getElementById('to_date').value;
    
    document.getElementById('expenditure_from_date').innerText = fromDate || '-';
    document.getElementById('expenditure_to_date').innerText = toDate || '-';
    
    try {
        const response = await fetch('/feesystem/api/reports/get_expenditure.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ school_id: schoolId, from_date: fromDate, to_date: toDate })
        });
        const data = await response.json();
        const tbody = document.getElementById('expenditure_table_body');
        
        if (data.success && data.expenditures && data.expenditures.length > 0) {
            let total = 0, largest = 0;
            tbody.innerHTML = data.expenditures.map(e => {
                total += e.amount;
                if (e.amount > largest) largest = e.amount;
                return `
                    <tr>
                        <td>${e.payment_date}</td>
                        <td>${escapeHtml(e.voucher_no)}</td>
                        <td>${escapeHtml(e.payee_name)}</td>
                        <td>${escapeHtml(e.vote_head_name)}</td>
                        <td>${escapeHtml(e.description || '-')}</td>
                        <td class="text-red-600">${formatCurrency(e.amount)}</td>
                        <td>${escapeHtml(e.payment_mode)}</td>
                    </tr>
                `;
            }).join('');
            
            const avgSpend = total / (data.expenditures.length || 1);
            document.getElementById('expenditure_total_amount').innerHTML = formatCurrency(total);
            document.getElementById('expenditure_total').innerHTML = formatCurrency(total);
            document.getElementById('expenditure_avg').innerHTML = formatCurrency(avgSpend);
            document.getElementById('expenditure_largest').innerHTML = formatCurrency(largest);
        } else {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-6 text-gray-500">No expenditure records found</td></tr>';
        }
    } catch (error) { console.error('Error loading expenditure:', error); }
}

// ==================== TRIAL BALANCE REPORT ====================
async function loadTrialBalance() {
    const asAtDate = document.getElementById('to_date').value;
    
    document.getElementById('trialbalance_date').innerText = asAtDate || '-';
    
    try {
        const response = await fetch('/feesystem/api/reports/get_trial_balance.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ school_id: schoolId, as_at_date: asAtDate })
        });
        const data = await response.json();
        const tbody = document.getElementById('trialbalance_table_body');
        
        if (data.success && data.accounts && data.accounts.length > 0) {
            let totalDebits = data.total_debits || 0;
            let totalCredits = data.total_credits || 0;
            
            tbody.innerHTML = data.accounts.map(a => {
                return `
                    <tr class="border-b hover:bg-gray-50">
                        <td class="px-3 py-2 sm:px-4">${escapeHtml(a.account_code)}</td>
                        <td class="px-3 py-2 sm:px-4">${escapeHtml(a.account_name)}</td>
                        <td class="px-3 py-2 sm:px-4 text-right text-green-600">${a.debit_balance > 0 ? formatCurrency(a.debit_balance) : '-'}</td>
                        <td class="px-3 py-2 sm:px-4 text-right text-red-600">${a.credit_balance > 0 ? formatCurrency(a.credit_balance) : '-'}</td>
                    </tr>
                `;
            }).join('');
            
            document.getElementById('trialbalance_debits_total').innerHTML = formatCurrency(totalDebits);
            document.getElementById('trialbalance_credits_total').innerHTML = formatCurrency(totalCredits);
            document.getElementById('trialbalance_debits').innerHTML = formatCurrency(totalDebits);
            document.getElementById('trialbalance_credits').innerHTML = formatCurrency(totalCredits);
        } else {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center py-6 text-gray-500">No trial balance data found</td></tr>';
            document.getElementById('trialbalance_debits_total').innerHTML = formatCurrency(0);
            document.getElementById('trialbalance_credits_total').innerHTML = formatCurrency(0);
        }
    } catch (error) { 
        console.error('Error loading trial balance:', error);
        document.getElementById('trialbalance_table_body').innerHTML = '<tr><td colspan="4" class="text-center py-6 text-red-500">Error loading trial balance data</td></tr>';
    }
}

// ==================== LOAD ACTIVE REPORT ====================
function loadActiveReport() {
    if (currentReportTab === 'cashbook') loadCashbookReport();
    else if (currentReportTab === 'feeregister') loadFeeRegisterReport();
    else if (currentReportTab === 'ledger') loadLedgerReport();
    else if (currentReportTab === 'expenditure') loadExpenditureReport();
    else if (currentReportTab === 'trialbalance') loadTrialBalance();
}

// ==================== PRINT REPORT ====================
function printReport() {
    const reportContent = document.querySelector('.main-tab-content.active').cloneNode(true);
    const printWindow = window.open('', '_blank');
    const schoolName = document.querySelector('.font-semibold')?.textContent || 'School';
    
    printWindow.document.write(`
        <html>
        <head>
            <title>${currentReportTab.toUpperCase()} Report - ${schoolName}</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; }
                table { border-collapse: collapse; width: 100%; margin-top: 20px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
                .header { text-align: center; margin-bottom: 20px; }
                .summary-grid { display: flex; gap: 20px; margin-bottom: 20px; flex-wrap: wrap; }
                .summary-card { padding: 10px; border: 1px solid #ddd; border-radius: 5px; text-align: center; flex: 1; min-width: 150px; }
                @media print { .no-print { display: none; } }
            </style>
        </head>
        <body>
            <div class="header">
                <h2>${escapeHtml(schoolName)}</h2>
                <h3>${currentReportTab.toUpperCase()} Report</h3>
                <p>Generated on: ${new Date().toLocaleString()}</p>
            </div>
            ${reportContent.innerHTML}
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}

// ==================== EXPORT TO EXCEL ====================
function exportToExcel() {
    const table = document.querySelector('.main-tab-content.active table');
    const ws = XLSX.utils.table_to_sheet(table);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, `${currentReportTab.toUpperCase()}_Report`);
    XLSX.writeFile(wb, `${currentReportTab}_report_${new Date().toISOString().split('T')[0]}.xlsx`);
}

// ==================== MAIN TOGGLE FUNCTIONALITY ====================
document.querySelectorAll('.main-toggle-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        const tabId = this.dataset.reportTab;
        currentReportTab = tabId;
        
        document.querySelectorAll('.main-toggle-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.main-tab-content').forEach(t => t.classList.remove('active'));
        
        this.classList.add('active');
        document.getElementById(`report-tab-${tabId}`).classList.add('active');
        
        loadActiveReport();
    });
});

// Event listeners
document.getElementById('refreshReportBtn')?.addEventListener('click', loadActiveReport);
document.getElementById('printReportBtn')?.addEventListener('click', printReport);
document.getElementById('exportReportBtn')?.addEventListener('click', exportToExcel);
document.getElementById('fee_class_filter')?.addEventListener('change', loadFeeRegisterReport);
document.getElementById('ledger_account_filter')?.addEventListener('change', loadLedgerReport);
document.getElementById('from_date')?.addEventListener('change', loadActiveReport);
document.getElementById('to_date')?.addEventListener('change', loadActiveReport);
document.getElementById('additional_filter')?.addEventListener('change', () => {
    if (currentReportTab === 'cashbook') loadCashbookReport();
});

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    setDefaultDates();
    loadClasses();
    loadActiveReport();
});
</script>

<?php include_once('../../includes/footer.php'); ?>