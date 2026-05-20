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
$current_year = date('Y');

include_once('../../includes/header.php');
include_once('../../includes/sidebar.php');
?>

<style>
/* ============================================================
   MODERN SEARCH SECTION STYLES
   ============================================================ */
.search-section {
    background: white;
    border-radius: 1rem;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
    margin-bottom: 1.5rem;
    overflow: hidden;
}

.dark .search-section {
    background: #1f2937;
}

.search-header {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid #e5e7eb;
    background: #f9fafb;
}

.dark .search-header {
    background: #111827;
    border-bottom-color: #374151;
}

.search-header h2 {
    font-size: 1.125rem;
    font-weight: 600;
    color: #111827;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.dark .search-header h2 {
    color: #f9fafb;
}

.search-header h2 i {
    color: #6366f1;
}

.search-body {
    padding: 1.5rem;
}

/* Modern Search Input */
.modern-search {
    position: relative;
    width: 100%;
}

.modern-search-input {
    width: 100%;
    padding: 0.875rem 1rem 0.875rem 3rem;
    font-size: 0.95rem;
    border: 2px solid #e5e7eb;
    border-radius: 0.75rem;
    background: white;
    color: #111827;
    transition: all 0.2s ease;
}

.dark .modern-search-input {
    background: #374151;
    border-color: #4b5563;
    color: #f9fafb;
}

.modern-search-input:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.modern-search-icon {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: #9ca3af;
    font-size: 1.1rem;
}

.modern-search-spinner {
    position: absolute;
    right: 1rem;
    top: 50%;
    transform: translateY(-50%);
    width: 20px;
    height: 20px;
    border: 2px solid #e5e7eb;
    border-top-color: #6366f1;
    border-radius: 50%;
    animation: spin 0.6s linear infinite;
}

/* Search Results Dropdown */
.search-results-dropdown {
    position: absolute;
    top: calc(100% + 0.5rem);
    left: 0;
    right: 0;
    background: white;
    border-radius: 0.75rem;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.02);
    border: 1px solid #e5e7eb;
    max-height: 320px;
    overflow-y: auto;
    z-index: 50;
    display: none;
}

.dark .search-results-dropdown {
    background: #1f2937;
    border-color: #374151;
}

.search-result-item {
    padding: 0.875rem 1rem;
    cursor: pointer;
    transition: all 0.15s ease;
    border-bottom: 1px solid #f3f4f6;
}

.dark .search-result-item {
    border-bottom-color: #374151;
}

.search-result-item:hover {
    background-color: #f3f4f6;
}

.dark .search-result-item:hover {
    background-color: #374151;
}

.search-result-item:last-child {
    border-bottom: none;
}

.search-result-name {
    font-weight: 600;
    color: #111827;
    margin-bottom: 0.25rem;
}

.dark .search-result-name {
    color: #f9fafb;
}

.search-result-details {
    font-size: 0.75rem;
    color: #6b7280;
}

.dark .search-result-details {
    color: #9ca3af;
}

.search-result-details i {
    margin-right: 0.25rem;
    width: 14px;
}

.search-empty {
    padding: 2rem;
    text-align: center;
    color: #6b7280;
}

/* Student List Styles */
.student-list-section {
    margin-top: 1rem;
}

.student-list-title {
    font-size: 0.875rem;
    font-weight: 500;
    color: #6b7280;
    margin-bottom: 0.75rem;
    padding: 0 0.25rem;
}

.student-list-container {
    max-height: 400px;
    overflow-y: auto;
    border-radius: 0.75rem;
}

.student-list-item {
    padding: 0.875rem 1rem;
    cursor: pointer;
    transition: all 0.15s ease;
    border-radius: 0.5rem;
    margin-bottom: 0.25rem;
}

.student-list-item:hover {
    background-color: #f3f4f6;
}

.dark .student-list-item:hover {
    background-color: #374151;
}

.student-list-item.selected {
    background-color: #e0e7ff;
}

.dark .student-list-item.selected {
    background-color: #3730a3;
}

.student-list-name {
    font-weight: 600;
    color: #111827;
    margin-bottom: 0.25rem;
}

.dark .student-list-name {
    color: #f9fafb;
}

.student-list-details {
    font-size: 0.75rem;
    color: #6b7280;
    display: flex;
    gap: 1rem;
}

.dark .student-list-details {
    color: #9ca3af;
}

.student-list-details span {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

/* Loading State */
.loading-students {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem;
    color: #6b7280;
}

.spinner {
    width: 32px;
    height: 32px;
    border: 3px solid #e5e7eb;
    border-top-color: #6366f1;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    margin-bottom: 1rem;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* PDF Preview Styles */
.pdf-preview {
    font-family: 'Times New Roman', Arial, sans-serif;
    line-height: 1.4;
    color: #000000;
    background: white;
}

.pdf-preview .header {
    text-align: center;
    margin-bottom: 20px;
    border-bottom: 1px solid #000;
    padding-bottom: 10px;
}

.pdf-preview .school-name {
    font-size: 18px;
    font-weight: bold;
}

.pdf-preview .school-address,
.pdf-preview .school-contact {
    font-size: 10px;
    margin: 2px 0;
}

.pdf-preview .title {
    font-size: 14px;
    font-weight: bold;
    text-align: center;
    margin: 15px 0;
}

.pdf-preview .subtitle {
    font-size: 11px;
    text-align: center;
    margin-bottom: 15px;
}

.pdf-preview table {
    width: 100%;
    border-collapse: collapse;
    margin: 10px 0;
}

.pdf-preview th, .pdf-preview td {
    border: 1px solid #000;
    padding: 8px;
    text-align: left;
    font-size: 10px;
}

.pdf-preview th {
    background-color: #f0f0f0;
    font-weight: bold;
}

.pdf-preview .text-right {
    text-align: right;
}

.pdf-preview .total-row {
    font-weight: bold;
    background-color: #f5f5f5;
}

.pdf-preview .footer {
    margin-top: 30px;
    text-align: center;
    font-size: 9px;
    border-top: 1px solid #000;
    padding-top: 10px;
}

/* Other Cards */
.info-card {
    background: white;
    border-radius: 1rem;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
    padding: 1.5rem;
}

.dark .info-card {
    background: #1f2937;
}

.card-header {
    font-size: 1.125rem;
    font-weight: 600;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.dark .card-header {
    border-bottom-color: #374151;
}

/* Payment Form */
.payment-form .form-group {
    margin-bottom: 1rem;
}

.payment-form label {
    display: block;
    font-size: 0.875rem;
    font-weight: 500;
    margin-bottom: 0.5rem;
    color: #374151;
}

.dark .payment-form label {
    color: #d1d5db;
}

.payment-form input,
.payment-form select {
    width: 100%;
    padding: 0.625rem 0.875rem;
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    transition: all 0.2s;
}

.dark .payment-form input,
.dark .payment-form select {
    background: #374151;
    border-color: #4b5563;
    color: #f9fafb;
}

.payment-form input:focus,
.payment-form select:focus {
    outline: none;
    border-color: #6366f1;
    ring: 2px solid rgba(99, 102, 241, 0.2);
}

.btn-primary {
    background: #10b981;
    color: white;
    padding: 0.625rem 1rem;
    border-radius: 0.5rem;
    font-weight: 500;
    width: 100%;
    transition: background 0.2s;
}

.btn-primary:hover:not(:disabled) {
    background: #059669;
}

.btn-primary:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Financial Summary Cards */
.summary-card {
    background: #f9fafb;
    border-radius: 0.75rem;
    padding: 1rem;
    text-align: center;
}

.dark .summary-card {
    background: #111827;
}

.summary-card .label {
    font-size: 0.75rem;
    color: #6b7280;
    margin-bottom: 0.5rem;
}

.summary-card .value {
    font-size: 1.25rem;
    font-weight: 700;
}

.summary-card.debits .value { color: #dc2626; }
.summary-card.credits .value { color: #10b981; }
.summary-card.balance .value { color: #3b82f6; }

/* Action Buttons */
.action-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid #e5e7eb;
}

.dark .action-buttons {
    border-top-color: #374151;
}

.btn-action {
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    font-weight: 500;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-yellow {
    background: #eab308;
    color: white;
}

.btn-yellow:hover {
    background: #ca8a04;
}

.btn-red {
    background: #ef4444;
    color: white;
}

.btn-red:hover {
    background: #dc2626;
}

/* Modal Styles - ENSURE HIDDEN BY DEFAULT */
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 100;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-overlay.hidden {
    display: none !important;
}

.modal-content {
    background: white;
    border-radius: 1rem;
    max-width: 28rem;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
}

.dark .modal-content {
    background: #1f2937;
}

.modal-header {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.dark .modal-header {
    border-bottom-color: #374151;
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid #e5e7eb;
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
}

.dark .modal-footer {
    border-top-color: #374151;
}
</style>

<main class="main-content flex-grow flex flex-col">
  <header class="bg-white shadow-sm dark:bg-gray-800 dark:border-gray-700">
    <div class="px-4 py-3 flex justify-between items-center">
      <div class="flex items-center">
        <button id="mobile-toggle" class="mr-4 text-gray-500 hover:text-indigo-600 focus:outline-none lg:hidden">
          <i class="fas fa-bars text-xl"></i>
        </button>
        <h1 id="page-title" class="text-xl font-semibold text-gray-800 dark:text-white">Fee Collection</h1>
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
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Left Column - Student Selector -->
      <div class="lg:col-span-1">
        <div class="search-section">
          <div class="search-header">
            <h2>
              <i class="fas fa-users"></i>
              Select Student
            </h2>
          </div>
          <div class="search-body">
            <div class="modern-search">
              <i class="fas fa-search modern-search-icon"></i>
              <input type="text" 
                     id="studentSearchInput" 
                     class="modern-search-input" 
                     placeholder="Search by name or admission number..."
                     autocomplete="off">
              <div id="searchSpinner" class="modern-search-spinner hidden"></div>
              <div id="searchResults" class="search-results-dropdown"></div>
            </div>
            
            <div class="student-list-section">
              <div class="student-list-title">
                <i class="fas fa-list mr-1"></i> All Students
              </div>
              <div id="studentListContainer" class="student-list-container">
                <div class="loading-students">
                  <div class="spinner"></div>
                  <p>Loading students...</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Right Column - Student Details and Payment -->
      <div class="lg:col-span-2 space-y-6">
        <div class="info-card">
          <div class="card-header">
            <i class="fas fa-file-invoice text-indigo-500"></i>
            Fee Statement
            <div class="flex-1"></div>
            <select id="statementYear" class="px-2 py-1 border border-gray-300 dark:border-gray-600 rounded text-sm bg-white dark:bg-gray-700">
              <option value="2024">2024</option>
              <option value="2025">2025</option>
              <option value="2026">2026</option>
              <option value="2027">2027</option>
            </select>
            <button id="downloadStatementBtn" class="px-3 py-1 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm ml-2">
              <i class="fas fa-file-pdf mr-1"></i>PDF
            </button>
          </div>
          <div id="statementPreview" class="border border-gray-200 dark:border-gray-700 rounded-lg bg-gray-50 dark:bg-gray-900 p-4 overflow-auto max-h-[450px]">
            <div class="pdf-preview">
              <div class="header">
                <div class="school-name" id="schoolName"><?php echo htmlspecialchars($school_name ?: 'SCHOOL NAME'); ?></div>
                <div class="school-address" id="schoolAddress">Loading...</div>
                <div class="school-contact" id="schoolContact">Loading...</div>
              </div>
              <div class="title">FEE STATEMENT</div>
              <div class="subtitle" id="statementSubtitle">Select a student to view statement</div>
              
              <table>
                <thead>
                  <tr>
                    <th>Date</th>
                    <th>Receipt No</th>
                    <th>Transaction</th>
                    <th class="text-right">Debit (KES)</th>
                    <th class="text-right">Credit (KES)</th>
                    <th class="text-right">Balance (KES)</th>
                  </tr>
                </thead>
                <tbody id="statementTableBody">
                  <tr><td colspan="6" class="text-center">No transactions found</td></tr>
                </tbody>
                <tfoot id="statementTableFooter" style="display: none;">
                  <tr class="total-row">
                    <td colspan="3" class="text-right"><strong>TOTAL</strong></td>
                    <td class="text-right"><strong id="totalDebitAmount">0.00</strong></td>
                    <td class="text-right"><strong id="totalCreditAmount">0.00</strong></td>
                    <td class="text-right"><strong id="totalBalanceAmount">0.00</strong></td>
                  </tr>
                </tfoot>
               </table>
              
              <div class="footer">
                <p>Generated on: <?php echo date('Y-m-d H:i:s'); ?></p>
                <p>This is an official fee statement. Please contact the finance office for any queries.</p>
              </div>
            </div>
          </div>
        </div>

        <div id="studentDetailsCard" class="info-card hidden">
          <div class="card-header">
            <i class="fas fa-user-graduate text-indigo-500"></i>
            Student Information
          </div>
          <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
            <div>
              <p class="text-sm text-gray-500">Full Name</p>
              <p class="font-medium" id="studentFullName">-</p>
            </div>
            <div>
              <p class="text-sm text-gray-500">Class</p>
              <p class="font-medium" id="studentClass">-</p>
            </div>
            <div>
              <p class="text-sm text-gray-500">Admission Number</p>
              <p class="font-medium" id="studentAdmission">-</p>
            </div>
            <div>
              <p class="text-sm text-gray-500">Gender</p>
              <p class="font-medium" id="studentGender">-</p>
            </div>
            <div>
              <p class="text-sm text-gray-500">Date Registered</p>
              <p class="font-medium" id="studentRegDate">-</p>
            </div>
            <div>
              <p class="text-sm text-gray-500">Financial Year</p>
              <p class="font-medium" id="displayYear"><?php echo $current_year; ?></p>
            </div>
          </div>
        </div>

        <div id="financialSummaryCard" class="info-card hidden">
          <div class="card-header">
            <i class="fas fa-chart-line text-green-500"></i>
            Financial Summary
          </div>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="summary-card debits">
              <div class="label">Total Debits</div>
              <div class="value" id="totalDebits">KES 0</div>
            </div>
            <div class="summary-card credits">
              <div class="label">Total Credits</div>
              <div class="value" id="totalCredits">KES 0</div>
            </div>
            <div class="summary-card balance">
              <div class="label">Account Balance</div>
              <div class="value" id="accountBalance">KES 0</div>
            </div>
          </div>
          
          <div class="action-buttons">
            <button id="otherDebitsBtn" class="btn-action btn-yellow">
              <i class="fas fa-plus-circle"></i> Other Debits
            </button>
            <button id="waiveFeesBtn" class="btn-action btn-red">
              <i class="fas fa-hand-peace"></i> Waive Fees
            </button>
          </div>
        </div>

        <div class="info-card">
          <div class="card-header">
            <i class="fas fa-credit-card text-green-500"></i>
            Process Payment
          </div>
          <form id="paymentForm" class="payment-form">
            <div class="form-group">
              <label>Amount (KES)</label>
              <input type="number" id="paymentAmount" required step="0.01" min="0" value="5000">
            </div>
            <div class="form-group">
              <label>Payment Date</label>
              <input type="date" id="paymentDate" value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="form-group">
              <label>Receipt No.</label>
              <input type="text" id="receiptNo" readonly class="bg-gray-100 dark:bg-gray-700">
            </div>
            <div class="form-group">
              <label>Payment Mode</label>
              <select id="paymentMode">
                <option value="">Choose payment mode...</option>
                <option value="cash">Cash</option>
                <option value="mpesa">M-Pesa</option>
                <option value="bank">Bank Transfer</option>
                <option value="cheque">Cheque</option>
                <option value="card">Credit/Debit Card</option>
              </select>
            </div>
            <div class="form-group">
              <label>Payment Code</label>
              <input type="text" id="paymentCode" placeholder="Transaction ID / M-Pesa Code">
            </div>
            <button type="submit" id="processPaymentBtn" class="btn-primary" disabled>
              <i class="fas fa-check-circle mr-2"></i> Process Payment
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</main>

<!-- Other Debits Modal - HIDDEN BY DEFAULT -->
<div id="otherDebitsModal" class="modal-overlay hidden" style="display: none !important;">
  <div class="modal-content">
    <div class="modal-header">
      <h3 class="text-lg font-semibold">
        <i class="fas fa-plus-circle text-yellow-600 mr-2"></i>Add Other Debit
      </h3>
      <button type="button" id="closeOtherDebitsModal" class="text-gray-400 hover:text-gray-500">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <div class="modal-body">
      <form id="otherDebitsForm">
        <div class="form-group mb-4">
          <label>Vote Head</label>
          <select id="otherDebitVoteHead" required>
            <option value="">Select Vote Head</option>
          </select>
        </div>
        <div class="form-group mb-4">
          <label>Amount (KES)</label>
          <input type="number" id="otherDebitAmount" required step="0.01" min="0">
        </div>
        <div class="form-group mb-4">
          <label>Description</label>
          <textarea id="otherDebitDescription" rows="2" placeholder="Reason for debit..."></textarea>
        </div>
        <div class="form-group mb-4">
          <label>Term</label>
          <select id="otherDebitTerm">
            <option value="1">Term 1</option>
            <option value="2">Term 2</option>
            <option value="3">Term 3</option>
          </select>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button type="button" id="cancelOtherDebitsBtn" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700">Cancel</button>
      <button type="button" id="saveOtherDebitsBtn" class="px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700">Add Debit</button>
    </div>
  </div>
</div>

<!-- Waive Fees Modal - HIDDEN BY DEFAULT -->
<div id="waiveFeesModal" class="modal-overlay hidden" style="display: none !important;">
  <div class="modal-content">
    <div class="modal-header">
      <h3 class="text-lg font-semibold">
        <i class="fas fa-hand-peace text-red-600 mr-2"></i>Waive Fees
      </h3>
      <button type="button" id="closeWaiveFeesModal" class="text-gray-400 hover:text-gray-500">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <div class="modal-body">
      <form id="waiveFeesForm">
        <div class="form-group mb-4">
          <label>Select Fee to Waive</label>
          <select id="waiveFeeItem" required>
            <option value="">Select Fee Item</option>
          </select>
        </div>
        <div class="form-group mb-4">
          <label>Amount to Waive (KES)</label>
          <input type="number" id="waiveAmount" required step="0.01" min="0">
        </div>
        <div class="form-group mb-4">
          <label>Reason for Waiver</label>
          <textarea id="waiveReason" rows="2" placeholder="Reason for waiving fees..."></textarea>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button type="button" id="cancelWaiveFeesBtn" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700">Cancel</button>
      <button type="button" id="confirmWaiveFeesBtn" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">Confirm Waiver</button>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Immediately close any modals that might be visible (runs before DOMContentLoaded)
(function() {
    if (document.getElementById('otherDebitsModal')) {
        document.getElementById('otherDebitsModal').style.display = 'none';
        document.getElementById('otherDebitsModal').classList.add('hidden');
    }
    if (document.getElementById('waiveFeesModal')) {
        document.getElementById('waiveFeesModal').style.display = 'none';
        document.getElementById('waiveFeesModal').classList.add('hidden');
    }
})();

document.addEventListener('DOMContentLoaded', function() {
    // Ensure modals are closed on page load
    const otherDebitsModal = document.getElementById('otherDebitsModal');
    const waiveFeesModal = document.getElementById('waiveFeesModal');
    
    if (otherDebitsModal) {
        otherDebitsModal.style.display = 'none';
        otherDebitsModal.classList.add('hidden');
    }
    if (waiveFeesModal) {
        waiveFeesModal.style.display = 'none';
        waiveFeesModal.classList.add('hidden');
    }
    
    // Initialize the application
    initializeApp();
});

function initializeApp() {
    // Close modal function
    window.closeAllModals = function() {
        const otherDebitsModal = document.getElementById('otherDebitsModal');
        const waiveFeesModal = document.getElementById('waiveFeesModal');
        
        if (otherDebitsModal) {
            otherDebitsModal.style.display = 'none';
            otherDebitsModal.classList.add('hidden');
        }
        if (waiveFeesModal) {
            waiveFeesModal.style.display = 'none';
            waiveFeesModal.classList.add('hidden');
        }
    };
    
    // Open Other Debits Modal
    window.openOtherDebitsModal = function() {
        if (!currentStudent) {
            Swal.fire('Warning', 'Please select a student first', 'warning');
            return;
        }
        closeAllModals();
        const modal = document.getElementById('otherDebitsModal');
        if (modal) {
            modal.style.display = 'flex';
            modal.classList.remove('hidden');
            // Reset form
            document.getElementById('otherDebitAmount').value = '';
            document.getElementById('otherDebitDescription').value = '';
            if (document.getElementById('otherDebitVoteHead')) {
                document.getElementById('otherDebitVoteHead').value = '';
            }
            if (document.getElementById('otherDebitTerm')) {
                document.getElementById('otherDebitTerm').value = '1';
            }
        }
    };
    
    // Open Waive Fees Modal
    window.openWaiveFeesModal = async function() {
        if (!currentStudent) {
            Swal.fire('Warning', 'Please select a student first', 'warning');
            return;
        }
        closeAllModals();
        
        try {
            const response = await fetch('/feesystem/api/feesystem/get_outstanding_fees.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ school_id: schoolId, student_id: currentStudent.id })
            });
            const data = await response.json();
            
            if (data.success && data.fees.length > 0) {
                const feeSelect = document.getElementById('waiveFeeItem');
                feeSelect.innerHTML = '<option value="">Select Fee Item</option>' + 
                    data.fees.map(fee => `<option value="${fee.id}" data-amount="${fee.balance}">${escapeHtml(fee.description || fee.vote_head_name)} - Balance: KES ${parseFloat(fee.balance).toLocaleString()}</option>`).join('');
                
                feeSelect.onchange = function() {
                    const selected = feeSelect.options[feeSelect.selectedIndex];
                    const maxAmount = selected.dataset.amount || 0;
                    const waiveAmountInput = document.getElementById('waiveAmount');
                    if (waiveAmountInput) {
                        waiveAmountInput.max = maxAmount;
                        waiveAmountInput.placeholder = `Max: KES ${parseFloat(maxAmount).toLocaleString()}`;
                    }
                };
                
                const modal = document.getElementById('waiveFeesModal');
                if (modal) {
                    modal.style.display = 'flex';
                    modal.classList.remove('hidden');
                    // Reset form
                    document.getElementById('waiveAmount').value = '';
                    document.getElementById('waiveReason').value = '';
                }
            } else {
                Swal.fire('Info', 'No outstanding fees to waive', 'info');
            }
        } catch (error) {
            Swal.fire('Error', 'Failed to load fee items', 'error');
        }
    };
    
    const schoolId = <?php echo json_encode($school_id); ?>;
    const currentYear = <?php echo $current_year; ?>;
    let currentStudent = null;
    let allStudents = [];
    let studentDebits = [];
    let studentPayments = [];
    let voteHeads = [];
    let searchTimeout = null;
    
    let schoolInfo = {
        school_name: '<?php echo addslashes($school_name ?: 'SCHOOL NAME'); ?>',
        school_address: 'P.O BOX 000 - CITY',
        school_phone: '0000000000',
        school_email: 'school@email.com'
    };
    
    // Set up event listeners
    document.getElementById('otherDebitsBtn').addEventListener('click', openOtherDebitsModal);
    document.getElementById('waiveFeesBtn').addEventListener('click', openWaiveFeesModal);
    
    document.getElementById('closeOtherDebitsModal').addEventListener('click', closeAllModals);
    document.getElementById('cancelOtherDebitsBtn').addEventListener('click', closeAllModals);
    document.getElementById('closeWaiveFeesModal').addEventListener('click', closeAllModals);
    document.getElementById('cancelWaiveFeesBtn').addEventListener('click', closeAllModals);
    
    // Close modals on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeAllModals();
        }
    });
    
    // Load school info
    async function loadSchoolInfo() {
        try {
            const response = await fetch('/feesystem/api/feesystem/get_school_info.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({})
            });
            const data = await response.json();
            if (data.success && data.school_info) {
                schoolInfo = data.school_info;
                updateSchoolInfoDisplay();
            }
        } catch (error) {
            console.error('Error loading school info:', error);
        }
    }
    
    function updateSchoolInfoDisplay() {
        document.getElementById('schoolName').textContent = schoolInfo.school_name || 'SCHOOL NAME';
        document.getElementById('schoolAddress').textContent = schoolInfo.school_address || 'P.O BOX 000 - CITY';
        let contact = "";
        if (schoolInfo.school_phone) contact += "Phone: " + schoolInfo.school_phone;
        if (schoolInfo.school_email) {
            if (contact) contact += " | ";
            contact += "Email: " + schoolInfo.school_email;
        }
        document.getElementById('schoolContact').textContent = contact || "Phone: 0000000000 | Email: school@email.com";
    }
    
    // Load all students
    async function loadAllStudents() {
        const container = document.getElementById('studentListContainer');
        container.innerHTML = '<div class="loading-students"><div class="spinner"></div><p>Loading students...</p></div>';
        try {
            const response = await fetch('/feesystem/api/feesystem/get_all_students.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ school_id: schoolId })
            });
            const data = await response.json();
            if (data.success && data.students) {
                allStudents = data.students;
                renderStudentList(allStudents);
            } else {
                container.innerHTML = '<div class="loading-students"><p>No students found</p></div>';
            }
        } catch (error) {
            console.error('Error loading students:', error);
            container.innerHTML = '<div class="loading-students"><p>Error loading students</p></div>';
        }
    }
    
    function renderStudentList(students) {
        const container = document.getElementById('studentListContainer');
        if (!students || students.length === 0) {
            container.innerHTML = '<div class="loading-students"><p>No students found</p></div>';
            return;
        }
        container.innerHTML = students.map(student => `
            <div class="student-list-item" data-id="${student.id}" data-admission="${student.admission_no}" data-name="${escapeHtml(student.full_name)}">
                <div class="student-list-name">${escapeHtml(student.full_name)}</div>
                <div class="student-list-details">
                    <span><i class="fas fa-id-card"></i> ${escapeHtml(student.admission_no)}</span>
                    <span><i class="fas fa-graduation-cap"></i> ${escapeHtml(student.class_name || 'No Class')}</span>
                </div>
            </div>
        `).join('');
        document.querySelectorAll('.student-list-item').forEach(item => {
            item.addEventListener('click', () => {
                document.querySelectorAll('.student-list-item').forEach(i => i.classList.remove('selected'));
                item.classList.add('selected');
                searchStudentByAdmission(item.dataset.admission);
            });
        });
    }
    
    // Search functionality
    const searchInput = document.getElementById('studentSearchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.trim().toLowerCase();
            const resultsDiv = document.getElementById('searchResults');
            const spinner = document.getElementById('searchSpinner');
            if (searchTimeout) clearTimeout(searchTimeout);
            if (searchTerm.length >= 2) {
                spinner.classList.remove('hidden');
                searchTimeout = setTimeout(() => {
                    const filtered = allStudents.filter(student => 
                        student.full_name.toLowerCase().includes(searchTerm) || 
                        student.admission_no.toLowerCase().includes(searchTerm)
                    );
                    if (filtered.length > 0) {
                        resultsDiv.innerHTML = filtered.map(s => `
                            <div class="search-result-item" data-admission="${s.admission_no}" data-name="${escapeHtml(s.full_name)}">
                                <div class="search-result-name">${escapeHtml(s.full_name)}</div>
                                <div class="search-result-details">
                                    <i class="fas fa-id-card"></i> ${escapeHtml(s.admission_no)}
                                    <i class="fas fa-graduation-cap ml-2"></i> ${escapeHtml(s.class_name || 'No Class')}
                                </div>
                            </div>
                        `).join('');
                        resultsDiv.style.display = 'block';
                    } else {
                        resultsDiv.innerHTML = '<div class="search-empty">No students found</div>';
                        resultsDiv.style.display = 'block';
                    }
                    spinner.classList.add('hidden');
                }, 300);
            } else if (searchTerm.length === 0) {
                resultsDiv.style.display = 'none';
            }
        });
    }
    
    const searchResults = document.getElementById('searchResults');
    if (searchResults) {
        searchResults.addEventListener('click', (e) => {
            const item = e.target.closest('.search-result-item');
            if (item) {
                const admissionNo = item.dataset.admission;
                const name = item.dataset.name;
                document.getElementById('studentSearchInput').value = name;
                document.getElementById('searchResults').style.display = 'none';
                searchStudentByAdmission(admissionNo);
                document.querySelectorAll('.student-list-item').forEach(el => {
                    if (el.dataset.admission === admissionNo) {
                        el.classList.add('selected');
                    } else {
                        el.classList.remove('selected');
                    }
                });
            }
        });
    }
    
    document.addEventListener('click', (e) => {
        if (!e.target.closest('#studentSearchInput') && !e.target.closest('#searchResults')) {
            const resultsDiv = document.getElementById('searchResults');
            if (resultsDiv) resultsDiv.style.display = 'none';
        }
    });
    
    async function searchStudentByAdmission(admissionNo) {
        if (!admissionNo || admissionNo.length < 2) {
            clearStudentData();
            return;
        }
        try {
            const response = await fetch('/feesystem/api/feesystem/get_student_by_admission.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ admission_no: admissionNo })
            });
            const data = await response.json();
            if (data.success && data.student) {
                currentStudent = data.student;
                await loadStudentFinancials(currentStudent.id);
                displayStudentDetails(currentStudent);
                generateReceiptNumber();
                enablePaymentButton(true);
            } else {
                clearStudentData();
                Swal.fire('Not Found', 'Student not found', 'warning');
            }
        } catch (error) {
            console.error('Error searching student:', error);
            Swal.fire('Error', 'Failed to search student', 'error');
        }
    }
    
    async function loadVoteHeads() {
        try {
            const response = await fetch('/feesystem/api/feesystem/get_vote_heads.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ school_id: schoolId, status: 'active' })
            });
            const data = await response.json();
            if (data.success) {
                voteHeads = data.vote_heads;
                const options = '<option value="">Select Vote Head</option>' + 
                    voteHeads.map(vh => `<option value="${vh.id}">${escapeHtml(vh.name)} (${escapeHtml(vh.alias)})</option>`).join('');
                document.getElementById('otherDebitVoteHead').innerHTML = options;
            }
        } catch (error) {
            console.error('Error loading vote heads:', error);
        }
    }
    
    async function loadStudentFinancials(studentId) {
        const year = document.getElementById('statementYear').value;
        try {
            const response = await fetch('/feesystem/api/feesystem/get_student_financials.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ school_id: schoolId, student_id: studentId, year: year })
            });
            const data = await response.json();
            if (data.success) {
                studentDebits = data.debits || [];
                studentPayments = data.payments || [];
                updateFinancialSummary();
                updateStatementPreview();
            }
        } catch (error) {
            console.error('Error loading financials:', error);
        }
    }
    
    function displayStudentDetails(student) {
        document.getElementById('studentDetailsCard').classList.remove('hidden');
        document.getElementById('financialSummaryCard').classList.remove('hidden');
        document.getElementById('studentFullName').textContent = `${student.first_name || ''} ${student.middle_name || ''} ${student.last_name || ''}`.trim();
        document.getElementById('studentClass').textContent = student.class_name || '-';
        document.getElementById('studentAdmission').textContent = student.admission_no;
        document.getElementById('studentGender').textContent = student.gender || '-';
        document.getElementById('studentRegDate').textContent = student.admission_date || '-';
        document.getElementById('displayYear').textContent = document.getElementById('statementYear').value;
        document.getElementById('statementSubtitle').innerHTML = `${student.first_name || ''} ${student.last_name || ''} (${student.admission_no}) - ${document.getElementById('statementYear').value}`;
    }
    
    function updateFinancialSummary() {
        let totalDebits = 0;
        let totalCredits = 0;
        studentDebits.forEach(debit => { totalDebits += parseFloat(debit.amount) || 0; });
        studentPayments.forEach(payment => { totalCredits += parseFloat(payment.amount) || 0; });
        const balance = totalDebits - totalCredits;
        document.getElementById('totalDebits').textContent = `KES ${totalDebits.toLocaleString()}`;
        document.getElementById('totalCredits').textContent = `KES ${totalCredits.toLocaleString()}`;
        document.getElementById('accountBalance').textContent = `KES ${balance.toLocaleString()}`;
        const balanceEl = document.getElementById('accountBalance');
        balanceEl.classList.remove('text-red-600', 'text-green-600', 'text-gray-600', 'text-blue-600');
        if (balance < 0) balanceEl.classList.add('text-red-600');
        else if (balance > 0) balanceEl.classList.add('text-green-600');
        else balanceEl.classList.add('text-gray-600');
    }
    
    function updateStatementPreview() {
        const tbody = document.getElementById('statementTableBody');
        const tfoot = document.getElementById('statementTableFooter');
        let transactions = [];
        studentDebits.forEach(debit => {
            transactions.push({
                date: debit.created_at?.split(' ')[0] || new Date().toISOString().split('T')[0],
                receipt_no: '-',
                transaction: debit.description || debit.vote_head_name || 'Debit Charge',
                debit: parseFloat(debit.amount) || 0,
                credit: 0
            });
        });
        studentPayments.forEach(payment => {
            const receiptNo = payment.receipt_no || `RCP-${payment.id}`;
            transactions.push({
                date: payment.payment_date || payment.created_at?.split(' ')[0] || new Date().toISOString().split('T')[0],
                receipt_no: receiptNo,
                transaction: payment.description || `Payment`,
                debit: 0,
                credit: parseFloat(payment.amount) || 0
            });
        });
        transactions.sort((a, b) => new Date(a.date) - new Date(b.date));
        if (transactions.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center">No transactions found</div></div>';
            tfoot.style.display = 'none';
            return;
        }
        let runningBalance = 0;
        let totalDebit = 0;
        let totalCredit = 0;
        tbody.innerHTML = transactions.map(trans => {
            runningBalance += trans.debit - trans.credit;
            totalDebit += trans.debit;
            totalCredit += trans.credit;
            return `
                <tr>
                    <td class="px-3 py-2">${trans.date}</div>
                    <td class="px-3 py-2">${escapeHtml(trans.receipt_no)}</div>
                    <td class="px-3 py-2">${escapeHtml(trans.transaction)}</div>
                    <td class="px-3 py-2 text-right">${trans.debit > 0 ? trans.debit.toLocaleString() : '-'}</div>
                    <td class="px-3 py-2 text-right">${trans.credit > 0 ? trans.credit.toLocaleString() : '-'}</div>
                    <td class="px-3 py-2 text-right">${runningBalance.toLocaleString()}</div>
                 </div>
            `;
        }).join('');
        tfoot.style.display = 'table-footer-group';
        document.getElementById('totalDebitAmount').textContent = totalDebit.toLocaleString();
        document.getElementById('totalCreditAmount').textContent = totalCredit.toLocaleString();
        document.getElementById('totalBalanceAmount').textContent = (totalDebit - totalCredit).toLocaleString();
    }
    
    function generateReceiptNumber() {
        const date = new Date();
        const receiptNo = `RCP-${date.getFullYear()}${String(date.getMonth()+1).padStart(2,'0')}${String(date.getDate()).padStart(2,'0')}-${Math.floor(Math.random()*10000).toString().padStart(4,'0')}`;
        document.getElementById('receiptNo').value = receiptNo;
    }
    
    function enablePaymentButton(enabled) {
        const processBtn = document.getElementById('processPaymentBtn');
        if (processBtn) processBtn.disabled = !enabled;
    }
    
    function clearStudentData() {
        currentStudent = null;
        studentDebits = [];
        studentPayments = [];
        document.getElementById('studentDetailsCard').classList.add('hidden');
        document.getElementById('financialSummaryCard').classList.add('hidden');
        document.getElementById('statementSubtitle').innerHTML = 'Select a student to view statement';
        document.getElementById('statementTableBody').innerHTML = '<tr><td colspan="6" class="text-center">No transactions found</div></div>';
        document.getElementById('statementTableFooter').style.display = 'none';
        enablePaymentButton(false);
        document.getElementById('studentSearchInput').value = '';
        document.querySelectorAll('.student-list-item').forEach(i => i.classList.remove('selected'));
    }
    
    // Process Payment
    document.getElementById('paymentForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!currentStudent) {
            Swal.fire('Error', 'No student selected', 'error');
            return;
        }
        const amount = document.getElementById('paymentAmount').value;
        const paymentDate = document.getElementById('paymentDate').value;
        const receiptNo = document.getElementById('receiptNo').value;
        const paymentMode = document.getElementById('paymentMode').value;
        const paymentCode = document.getElementById('paymentCode').value;
        const year = document.getElementById('statementYear').value;
        if (!amount || amount <= 0) {
            Swal.fire('Error', 'Please enter a valid amount', 'error');
            return;
        }
        if (!paymentMode) {
            Swal.fire('Error', 'Please select payment mode', 'error');
            return;
        }
        const saveBtn = document.getElementById('processPaymentBtn');
        const originalText = saveBtn.innerHTML;
        saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
        saveBtn.disabled = true;
        try {
            const response = await fetch('/feesystem/api/feesystem/process_payment.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    school_id: schoolId,
                    student_id: currentStudent.id,
                    amount: amount,
                    payment_date: paymentDate,
                    receipt_no: receiptNo,
                    payment_mode: paymentMode,
                    payment_code: paymentCode,
                    year: year
                })
            });
            const data = await response.json();
            if (data.success) {
                Swal.fire('Success', 'Payment processed successfully!', 'success');
                await loadStudentFinancials(currentStudent.id);
                generateReceiptNumber();
                document.getElementById('paymentAmount').value = '5000';
                document.getElementById('paymentCode').value = '';
            } else {
                Swal.fire('Error', data.message || 'Failed to process payment', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            Swal.fire('Error', 'An error occurred', 'error');
        } finally {
            saveBtn.innerHTML = originalText;
            saveBtn.disabled = false;
        }
    });
    
    // Download Statement
    document.getElementById('downloadStatementBtn').addEventListener('click', async () => {
        if (!currentStudent) {
            Swal.fire('Warning', 'No student selected', 'warning');
            return;
        }
        const year = document.getElementById('statementYear').value;
        const btn = document.getElementById('downloadStatementBtn');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Generating...';
        btn.disabled = true;
        try {
            const response = await fetch('/feesystem/api/feesystem/generate_fee_statement_pdf.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    student_id: currentStudent.id,
                    year: year,
                    school_info: schoolInfo
                })
            });
            const data = await response.json();
            if (data.success && data.pdf_url) {
                window.open(data.pdf_url, '_blank');
                Swal.fire('Success', 'PDF generated successfully!', 'success');
            } else {
                Swal.fire('Error', data.message || 'Failed to generate PDF', 'error');
            }
        } catch (error) {
            console.error('Error generating PDF:', error);
            Swal.fire('Error', 'Failed to generate PDF', 'error');
        } finally {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    });
    
    // Save Other Debits
    document.getElementById('saveOtherDebitsBtn').addEventListener('click', async () => {
        const voteHeadId = document.getElementById('otherDebitVoteHead').value;
        const amount = document.getElementById('otherDebitAmount').value;
        const description = document.getElementById('otherDebitDescription').value;
        const term = document.getElementById('otherDebitTerm').value;
        const year = document.getElementById('statementYear').value;
        if (!voteHeadId) {
            Swal.fire('Error', 'Please select a vote head', 'error');
            return;
        }
        if (!amount || amount <= 0) {
            Swal.fire('Error', 'Please enter a valid amount', 'error');
            return;
        }
        const saveBtn = document.getElementById('saveOtherDebitsBtn');
        const originalText = saveBtn.innerHTML;
        saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
        saveBtn.disabled = true;
        try {
            const response = await fetch('/feesystem/api/feesystem/add_debit.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    school_id: schoolId,
                    student_id: currentStudent.id,
                    vote_head_id: voteHeadId,
                    amount: amount,
                    description: description,
                    term: term,
                    year: year
                })
            });
            const data = await response.json();
            if (data.success) {
                Swal.fire('Success', 'Debit added successfully!', 'success');
                closeAllModals();
                await loadStudentFinancials(currentStudent.id);
                document.getElementById('otherDebitAmount').value = '';
                document.getElementById('otherDebitDescription').value = '';
            } else {
                Swal.fire('Error', data.message || 'Failed to add debit', 'error');
            }
        } catch (error) {
            Swal.fire('Error', 'An error occurred', 'error');
        } finally {
            saveBtn.innerHTML = originalText;
            saveBtn.disabled = false;
        }
    });
    
    // Confirm Waive Fees
    document.getElementById('confirmWaiveFeesBtn').addEventListener('click', async () => {
        const feeItemId = document.getElementById('waiveFeeItem').value;
        const amount = document.getElementById('waiveAmount').value;
        const reason = document.getElementById('waiveReason').value;
        if (!feeItemId) {
            Swal.fire('Error', 'Please select a fee item', 'error');
            return;
        }
        if (!amount || amount <= 0) {
            Swal.fire('Error', 'Please enter a valid amount', 'error');
            return;
        }
        const confirmBtn = document.getElementById('confirmWaiveFeesBtn');
        const originalText = confirmBtn.innerHTML;
        confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
        confirmBtn.disabled = true;
        try {
            const response = await fetch('/feesystem/api/feesystem/waive_fees.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    school_id: schoolId,
                    student_id: currentStudent.id,
                    fee_item_id: feeItemId,
                    amount: amount,
                    reason: reason
                })
            });
            const data = await response.json();
            if (data.success) {
                Swal.fire('Success', 'Fees waived successfully!', 'success');
                closeAllModals();
                await loadStudentFinancials(currentStudent.id);
                document.getElementById('waiveAmount').value = '';
                document.getElementById('waiveReason').value = '';
            } else {
                Swal.fire('Error', data.message || 'Failed to waive fees', 'error');
            }
        } catch (error) {
            Swal.fire('Error', 'An error occurred', 'error');
        } finally {
            confirmBtn.innerHTML = originalText;
            confirmBtn.disabled = false;
        }
    });
    
    // Refresh financials when year changes
    document.getElementById('statementYear').addEventListener('change', () => {
        document.getElementById('displayYear').textContent = document.getElementById('statementYear').value;
        if (currentStudent) {
            loadStudentFinancials(currentStudent.id);
        }
    });
    
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Start loading data
    loadSchoolInfo();
    document.getElementById('statementYear').value = currentYear;
    document.getElementById('displayYear').textContent = currentYear;
    loadVoteHeads();
    loadAllStudents();
    generateReceiptNumber();
}
</script>

<?php include_once('../../includes/footer.php'); ?>