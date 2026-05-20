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
/* ==================== Toggle Button Styles ==================== */
.main-toggle-btn, .voucher-type-btn {
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

.main-toggle-btn i, .voucher-type-btn i {
    font-size: 1rem;
    transition: transform 0.2s ease;
}

.main-toggle-btn:hover, .voucher-type-btn:hover {
    color: #4F46E5;
    background: rgba(79, 70, 229, 0.05);
}

.main-toggle-btn:hover i, .voucher-type-btn:hover i {
    transform: translateY(-1px);
}

.main-toggle-btn.active, .voucher-type-btn.active {
    color: #4F46E5;
    border-bottom-color: #4F46E5;
    background: linear-gradient(to bottom, rgba(79, 70, 229, 0.05), transparent);
}

.main-toggle-btn.active i, .voucher-type-btn.active i {
    color: #4F46E5;
}

/* Dark mode toggle buttons */
.dark .main-toggle-btn, .dark .voucher-type-btn {
    color: #9CA3AF;
}

.dark .main-toggle-btn:hover, .dark .voucher-type-btn:hover {
    color: #818CF8;
    background: rgba(129, 140, 248, 0.1);
}

.dark .main-toggle-btn.active, .dark .voucher-type-btn.active {
    color: #818CF8;
    border-bottom-color: #818CF8;
    background: linear-gradient(to bottom, rgba(129, 140, 248, 0.1), transparent);
}

/* ==================== Tab Content Transitions ==================== */
.main-tab-content {
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

/* ==================== Form Styles ==================== */
.form-group {
    margin-bottom: 1rem;
}

.form-label {
    display: block;
    font-size: 0.875rem;
    font-weight: 500;
    margin-bottom: 0.5rem;
    color: #374151;
}

.dark .form-label {
    color: #E5E7EB;
}

.form-input, .form-select, .form-textarea {
    width: 100%;
    padding: 0.5rem 0.75rem;
    border: 1px solid #D1D5DB;
    border-radius: 0.5rem;
    background: white;
    transition: all 0.2s;
}

.dark .form-input, .dark .form-select, .dark .form-textarea {
    background: #374151;
    border-color: #4B5563;
    color: #F3F4F6;
}

.form-input:focus, .form-select:focus, .form-textarea:focus {
    outline: none;
    border-color: #4F46E5;
    ring: 2px solid rgba(79, 70, 229, 0.2);
    box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.1);
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

.btn-outline {
    background: transparent;
    border: 1px solid #D1D5DB;
    color: #374151;
}

.btn-outline:hover {
    background: #F9FAFB;
    border-color: #4F46E5;
    color: #4F46E5;
}

.dark .btn-outline {
    border-color: #4B5563;
    color: #E5E7EB;
}

.dark .btn-outline:hover {
    background: #374151;
    border-color: #818CF8;
    color: #818CF8;
}
/* Ensure SweetAlert appears above modals */
.swal2-popup-fixed {
    z-index: 99999 !important;
}

.swal2-container {
    z-index: 99999 !important;
}
/* ==================== Card Styles ==================== */
.card {
    background: white;
    border-radius: 0.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.dark .card {
    background: #1F2937;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
}

.card-header {
    padding: 1rem 1.25rem;
    border-bottom: 1px solid #E5E7EB;
}

.dark .card-header {
    border-bottom-color: #4B5563;
}

.card-body {
    padding: 1.25rem;
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

/* ==================== Status Badges ==================== */
.badge {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.75rem;
    font-size: 0.75rem;
    font-weight: 500;
    border-radius: 9999px;
}

.badge-success {
    background: #D1FAE5;
    color: #065F46;
}

.dark .badge-success {
    background: #064E3B;
    color: #A7F3D0;
}

.badge-warning {
    background: #FEF3C7;
    color: #92400E;
}

.dark .badge-warning {
    background: #78350F;
    color: #FDE68A;
}

.badge-info {
    background: #DBEAFE;
    color: #1E40AF;
}

.dark .badge-info {
    background: #1E3A8A;
    color: #BFDBFE;
}

/* ==================== Modal Styles - HIGH Z-INDEX ==================== */
/* ==================== Modal Styles - HIGH Z-INDEX ==================== */
.modal-overlay {
    position: fixed;
    inset: 0;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 9999;
    align-items: center;
    justify-content: center;
}

.modal-overlay:not(.show) {
    display: none !important;
}

.modal-overlay.show {
    display: flex !important;
}

.modal-container {
    background: white;
    border-radius: 0.5rem;
    width: 100%;
    max-width: 32rem;
    margin: 1rem;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
    z-index: 10000;
}

.dark .modal-container {
    background: #1F2937;
}

.modal-header {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #E5E7EB;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.dark .modal-header {
    border-bottom-color: #4B5563;
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid #E5E7EB;
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
}

.dark .modal-footer {
    border-top-color: #4B5563;
}

/* ==================== Preview Container ==================== */
.preview-container {
    background: #F9FAFB;
    border-radius: 0.5rem;
    padding: 1rem;
    min-height: 400px;
    overflow-y: auto;
}

.dark .preview-container {
    background: #111827;
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
    .main-toggle-btn, .voucher-type-btn {
        padding: 0.5rem 1rem;
        font-size: 0.75rem;
    }
    
    .main-toggle-btn i, .voucher-type-btn i {
        font-size: 0.875rem;
    }
    
    .data-table th, .data-table td {
        padding: 0.5rem 0.75rem;
    }
}

/* ==================== Scrollbar Styling ==================== */
::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

::-webkit-scrollbar-track {
    background: #F1F1F1;
    border-radius: 4px;
}

.dark ::-webkit-scrollbar-track {
    background: #374151;
}

::-webkit-scrollbar-thumb {
    background: #C1C1C1;
    border-radius: 4px;
}

.dark ::-webkit-scrollbar-thumb {
    background: #6B7280;
}

::-webkit-scrollbar-thumb:hover {
    background: #A8A8A8;
}

.dark ::-webkit-scrollbar-thumb:hover {
    background: #9CA3AF;
}
</style>

<main class="main-content flex-grow flex flex-col">
  <header class="bg-white shadow-sm dark:bg-gray-800 dark:border-gray-700">
    <div class="px-4 py-3 flex justify-between items-center">
      <div class="flex items-center">
        <button id="mobile-toggle" class="mr-4 text-gray-500 hover:text-indigo-600 focus:outline-none lg:hidden">
          <i class="fas fa-bars text-xl"></i>
        </button>
        <h1 id="page-title" class="text-xl font-semibold text-gray-800 dark:text-white">Payment Vouchers</h1>
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
    <!-- Main Toggle Buttons -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm mb-6 overflow-x-auto">
      <div class="border-b border-gray-200 dark:border-gray-700">
        <nav class="flex flex-nowrap md:flex-wrap -mb-px">
          <button class="main-toggle-btn active" data-main-tab="vouchers">
            <i class="fas fa-receipt"></i> Payment Vouchers
          </button>
          <button class="main-toggle-btn" data-main-tab="suppliers">
            <i class="fas fa-truck"></i> Suppliers
          </button>
          <button class="main-toggle-btn" data-main-tab="invoices">
            <i class="fas fa-file-invoice"></i> Invoices
          </button>
        </nav>
      </div>
    </div>

    <!-- ==================== TAB 1: PAYMENT VOUCHERS ==================== -->
    <div id="main-tab-vouchers" class="main-tab-content active">
      <!-- Sub Toggle: Simple vs Detailed -->
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm mb-6 overflow-x-auto">
        <div class="border-b border-gray-200 dark:border-gray-700 px-4">
          <nav class="flex -mb-px">
            <button class="voucher-type-btn active" data-type="simple">
              <i class="fas fa-file-alt"></i> Simple Voucher
            </button>
            <button class="voucher-type-btn" data-type="detailed">
              <i class="fas fa-list-alt"></i> Detailed Voucher
            </button>
          </nav>
        </div>
      </div>

      <!-- Three Columns Layout -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column: Payment Voucher Form -->
        <div class="lg:col-span-1">
          <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm sticky top-4">
            <div class="card-header">
              <h2 class="text-lg font-semibold text-gray-800 dark:text-white">
                <i class="fas fa-credit-card text-green-500 mr-2"></i>Payment Voucher
              </h2>
            </div>
            <div class="card-body">
              <form id="voucherForm">
                <!-- Simple Form Fields -->
                <div id="simpleFields">
                  <div class="form-group">
                    <label class="form-label">Supplier (optional)</label>
                    <select id="supplier_id_simple" class="form-select">
                      <option value="">-- No supplier --</option>
                    </select>
                  </div>
                  <div class="form-group">
                    <label class="form-label">Payee Name <span class="text-red-500">*</span></label>
                    <input type="text" id="payee_name_simple" placeholder="Enter payee name..." class="form-input">
                  </div>
                  <div class="form-group">
                    <label class="form-label">ID/PS Number</label>
                    <input type="text" id="id_number_simple" placeholder="Optional..." class="form-input">
                  </div>
                  <div class="form-group">
                    <label class="form-label">Payment Date</label>
                    <input type="date" id="payment_date_simple" class="form-input">
                  </div>
                  <div class="form-group">
                    <label class="form-label">Payment Mode</label>
                    <select id="payment_mode_simple" class="form-select">
                      <option value="">Select mode...</option>
                      <option value="cash">Cash</option>
                      <option value="bank">Bank Transfer</option>
                      <option value="cheque">Cheque</option>
                      <option value="mpesa">M-Pesa</option>
                    </select>
                  </div>
                  <div class="form-group">
                    <label class="form-label">Cheque/Ref No.</label>
                    <input type="text" id="reference_simple" placeholder="e.g., CHQ-001" class="form-input">
                  </div>
                </div>

                <!-- Detailed Form Fields -->
                <div id="detailedFields" class="hidden">
                  <div class="form-group">
                    <label class="form-label">Supplier (optional)</label>
                    <select id="supplier_id_detailed" class="form-select">
                      <option value="">-- No supplier --</option>
                    </select>
                  </div>
                  <div class="form-group">
                    <label class="form-label">Payee Name <span class="text-red-500">*</span></label>
                    <input type="text" id="payee_name_detailed" placeholder="Enter payee name..." class="form-input">
                  </div>
                  <div class="form-group">
                    <label class="form-label">ID/PS Number</label>
                    <input type="text" id="id_number_detailed" placeholder="Optional..." class="form-input">
                  </div>
                  <div class="form-group">
                    <label class="form-label">Payment Date</label>
                    <input type="date" id="payment_date_detailed" class="form-input">
                  </div>
                  <div class="form-group">
                    <label class="form-label">Payment Mode</label>
                    <select id="payment_mode_detailed" class="form-select">
                      <option value="">Select mode...</option>
                      <option value="cash">Cash</option>
                      <option value="bank">Bank Transfer</option>
                      <option value="cheque">Cheque</option>
                      <option value="mpesa">M-Pesa</option>
                    </select>
                  </div>
                  <div class="form-group">
                    <label class="form-label">Cheque/Ref No.</label>
                    <input type="text" id="reference_detailed" placeholder="e.g., CHQ-001" class="form-input">
                  </div>
                  <div class="form-group">
                    <label class="form-label">LPO/LSO Number</label>
                    <input type="text" id="lpo_number" placeholder="Optional..." class="form-input">
                  </div>
                  <div class="form-group">
                    <label class="form-label">LPO Date</label>
                    <input type="date" id="lpo_date" class="form-input">
                  </div>
                  <div class="form-group">
                    <label class="form-label">Delivery Note No.</label>
                    <input type="text" id="delivery_note_no" placeholder="Optional..." class="form-input">
                  </div>
                  <div class="form-group">
                    <label class="form-label">Delivery Note Date</label>
                    <input type="date" id="delivery_note_date" class="form-input">
                  </div>
                </div>

                <!-- Expense Items Section -->
                <div class="form-group">
                  <label class="form-label">Expense Items</label>
                  <button type="button" id="addItemBtn" class="btn btn-outline w-full mb-3">
                    <i class="fas fa-plus mr-1"></i>Add Item
                  </button>
                  <div id="expenseItemsContainer" class="space-y-3">
                    <div class="expense-item border border-gray-200 dark:border-gray-700 rounded-lg p-3">
                      <div class="grid grid-cols-2 gap-2 mb-2">
                        <div>
                          <label class="text-xs text-gray-500">Vote Head</label>
                          <select class="vote_head form-select text-sm">
                            <option value="">Select...</option>
                          </select>
                        </div>
                        <div>
                          <label class="text-xs text-gray-500">Particulars</label>
                          <input type="text" class="particulars form-input text-sm" placeholder="Description...">
                        </div>
                      </div>
                      <div class="grid grid-cols-2 gap-2">
                        <div>
                          <label class="text-xs text-gray-500">Amount</label>
                          <input type="number" class="item_amount form-input text-sm" placeholder="0.00" step="0.01">
                        </div>
                        <div class="flex justify-end items-end">
                          <button type="button" class="removeItemBtn text-red-500 hover:text-red-700 text-sm">
                            <i class="fas fa-trash"></i> Remove
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="text-right mt-2 font-bold text-gray-800 dark:text-white">
                    Total: <span id="expenseTotal">KES 0.00</span>
                  </div>
                </div>

                <div class="form-group">
                  <label class="form-label">Notes</label>
                  <textarea id="voucher_notes" rows="2" placeholder="Additional notes..." class="form-textarea"></textarea>
                </div>

                <div class="flex gap-3">
                  <button type="button" id="clearFormBtn" class="btn btn-outline flex-1">
                    <i class="fas fa-eraser"></i> Clear
                  </button>
                  <button type="button" id="previewBtn" class="btn btn-primary flex-1">
                    <i class="fas fa-eye"></i> Preview
                  </button>
                  <button type="submit" class="btn btn-success flex-1">
                    <i class="fas fa-save"></i> Save
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>

        <!-- Middle Column: Voucher Preview -->
        <div class="lg:col-span-1">
          <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm sticky top-4">
            <div class="card-header">
              <h2 class="text-lg font-semibold text-gray-800 dark:text-white">
                <i class="fas fa-eye text-blue-500 mr-2"></i>Voucher Preview
              </h2>
            </div>
            <div class="card-body">
              <div id="voucherPreview" class="preview-container">
                <div class="text-center text-gray-500 py-8">
                  <i class="fas fa-file-invoice text-5xl mb-3 opacity-50"></i>
                  <p>Fill the form and click Preview</p>
                </div>
              </div>
              <div class="mt-3 flex justify-end">
                <button id="downloadPdfBtn" class="btn btn-danger" disabled>
                  <i class="fas fa-download"></i> Download PDF
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Right Column: Payment Vouchers List -->
        <div class="lg:col-span-1">
          <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
            <div class="card-header">
              <h2 class="text-lg font-semibold text-gray-800 dark:text-white">
                <i class="fas fa-list mr-2 text-purple-500"></i>Payment Vouchers
              </h2>
            </div>
            <div class="card-body p-0 overflow-x-auto">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>PV No.</th>
                    <th>Date</th>
                    <th>Payee</th>
                    <th class="text-right">Amount</th>
                    <th>Mode</th>
                    <th>Status</th>
                    <th class="text-center">Actions</th>
                  </tr>
                </thead>
                <tbody id="vouchersListBody">
                  <tr><td colspan="7" class="text-center py-8">
                    <div class="loading-spinner"></div>
                    <span class="ml-2">Loading vouchers...</span>
                  </td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ==================== TAB 2: SUPPLIERS ==================== -->
    <div id="main-tab-suppliers" class="main-tab-content hidden">
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
        <div class="card-header flex justify-between items-center">
          <h2 class="text-lg font-semibold text-gray-800 dark:text-white">
            <i class="fas fa-truck text-indigo-500 mr-2"></i>Suppliers
          </h2>
          <button id="addSupplierBtn" class="btn btn-primary">
            <i class="fas fa-plus mr-2"></i>Add Supplier
          </button>
        </div>
        <div class="card-body p-0 overflow-x-auto">
          <table class="data-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Phone</th>
                <th>KRA PIN</th>
                <th>Address</th>
                <th class="text-center">Actions</th>
              </tr>
            </thead>
            <tbody id="suppliersListBody">
              <tr><td colspan="5" class="text-center py-8">
                <div class="loading-spinner"></div>
                <span class="ml-2">Loading suppliers...</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ==================== TAB 3: INVOICES ==================== -->
    <div id="main-tab-invoices" class="main-tab-content hidden">
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
        <div class="card-header flex justify-between items-center">
          <h2 class="text-lg font-semibold text-gray-800 dark:text-white">
            <i class="fas fa-file-invoice text-green-500 mr-2"></i>Invoices
          </h2>
          <button id="addInvoiceBtn" class="btn btn-primary">
            <i class="fas fa-plus mr-2"></i>Add Invoice
          </button>
        </div>
        <div class="card-body p-0 overflow-x-auto">
          <table class="data-table">
            <thead>
              <tr>
                <th>Invoice #</th>
                <th>Supplier</th>
                <th>Date</th>
                <th class="text-right">Amount</th>
                <th class="text-right">Balance</th>
                <th>Status</th>
                <th class="text-center">Actions</th>
              </tr>
            </thead>
            <tbody id="invoicesListBody">
              <tr><td colspan="7" class="text-center py-8">
                <div class="loading-spinner"></div>
                <span class="ml-2">Loading invoices...</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</main>

<!-- ==================== MODALS ==================== -->

<!-- Add Supplier Modal -->
<div id="supplierModal" class="modal-overlay" style="display: none;">
  <div class="modal-container">
    <div class="modal-header">
      <h3 class="text-lg font-semibold"><i class="fas fa-truck text-indigo-500 mr-2"></i>Add Supplier</h3>
      <button class="closeModalBtn text-gray-400 hover:text-gray-500 text-2xl leading-none">&times;</button>
    </div>
    <div class="modal-body">
      <form id="supplierForm">
        <div class="form-group">
          <label class="form-label">Name <span class="text-red-500">*</span></label>
          <input type="text" id="supplier_name" required class="form-input">
        </div>
        <div class="form-group">
          <label class="form-label">Phone</label>
          <input type="text" id="supplier_phone" class="form-input">
        </div>
        <div class="form-group">
          <label class="form-label">Address</label>
          <input type="text" id="supplier_address" class="form-input">
        </div>
        <div class="form-group">
          <label class="form-label">KRA PIN</label>
          <input type="text" id="supplier_kra_pin" class="form-input">
        </div>
        <div class="flex justify-end gap-3">
          <button type="button" class="closeModalBtn btn btn-outline">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Supplier</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Add Invoice Modal -->
<div id="invoiceModal" class="modal-overlay" style="display: none;">
  <div class="modal-container max-w-2xl" style="max-height: 90vh; overflow-y: auto;">
    <div class="modal-header">
      <h3 class="text-lg font-semibold"><i class="fas fa-file-invoice text-green-500 mr-2"></i>New Invoice</h3>
      <button class="closeInvoiceModalBtn text-gray-400 hover:text-gray-500 text-2xl leading-none">&times;</button>
    </div>
    <div class="modal-body">
      <form id="invoiceForm">
        <div class="form-group">
          <label class="form-label">Supplier <span class="text-red-500">*</span></label>
          <select id="invoice_supplier_id" required class="form-select">
            <option value="">Select supplier...</option>
          </select>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div class="form-group">
            <label class="form-label">Invoice Number <span class="text-red-500">*</span></label>
            <input type="text" id="invoice_number" required placeholder="e.g. INV-001" class="form-input">
          </div>
          <div class="form-group">
            <label class="form-label">Invoice Date</label>
            <input type="date" id="invoice_date" class="form-input">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Due Date (optional)</label>
          <input type="date" id="invoice_due_date" class="form-input">
        </div>
        
        <div class="form-group">
          <label class="form-label">Items</label>
          <button type="button" id="addInvoiceItemBtn" class="btn btn-outline w-full mb-3">
            <i class="fas fa-plus mr-1"></i>Add Item
          </button>
          <div id="invoiceItemsContainer" class="space-y-3">
            <!-- Initial empty state - will be populated by JavaScript -->
          </div>
          <div class="text-right mt-2 font-bold text-gray-800 dark:text-white">
            Total: <span id="invoiceTotal">KES 0.00</span>
          </div>
        </div>
        
        <div class="form-group">
          <label class="form-label">Notes</label>
          <textarea id="invoice_notes" rows="2" placeholder="Optional..." class="form-textarea"></textarea>
        </div>
        
        <div class="flex justify-end gap-3">
          <button type="button" class="closeInvoiceModalBtn btn btn-outline">Cancel</button>
          <button type="submit" class="btn btn-success">Save Invoice</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<script>
const schoolId = <?php echo json_encode($school_id); ?>;
const userId = <?php echo json_encode($user_id); ?>;
let currentVoucherType = 'simple';
let voteHeads = [];
let currentPreviewHtml = '';

// Set default date
function setDefaultDates() {
  const today = new Date().toISOString().split('T')[0];
  document.querySelectorAll('input[type="date"]').forEach(el => {
    if (!el.value) el.value = today;
  });
}

// ==================== MODAL HANDLERS ====================
function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('show');
        modal.style.display = 'flex';
    }
}

function hideModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('show');
        modal.style.display = 'none';
    }
}

// Close modal when clicking outside
window.addEventListener('click', (e) => {
    const supplierModal = document.getElementById('supplierModal');
    if (supplierModal && e.target === supplierModal) {
        hideModal('supplierModal');
    }
    const invoiceModal = document.getElementById('invoiceModal');
    if (invoiceModal && e.target === invoiceModal) {
        hideModal('invoiceModal');
    }
});

// Override Swal to ensure it appears on top
const originalSwal = Swal;
window.Swal = function(options) {
    if (typeof options === 'object') {
        options.customClass = options.customClass || {};
        options.customClass.popup = (options.customClass.popup || '') + ' swal2-popup-fixed';
    }
    return originalSwal(options);
};
Swal.fire = function() {
    const args = arguments;
    if (args[0] && typeof args[0] === 'object') {
        args[0].customClass = args[0].customClass || {};
        args[0].customClass.popup = (args[0].customClass.popup || '') + ' swal2-popup-fixed';
    }
    return originalSwal.fire.apply(originalSwal, args);
};

// ==================== LOAD DROPDOWNS ====================
async function loadSuppliers() {
  try {
    const response = await fetch('/feesystem/api/payments/get_suppliers.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ school_id: schoolId })
    });
    const result = await response.json();
    const suppliers = result.data?.suppliers || result.suppliers || [];
    if (result.success || result.status === 'success') {
      const options = '<option value="">-- No supplier --</option>' + 
        suppliers.map(s => `<option value="${s.id}">${escapeHtml(s.name)}</option>`).join('');
      document.querySelectorAll('#supplier_id_simple, #supplier_id_detailed, #invoice_supplier_id').forEach(sel => {
        if (sel) sel.innerHTML = options;
      });
    }
  } catch (error) { console.error('Error loading suppliers:', error); }
}

async function loadVoteHeads() {
  try {
    const response = await fetch('/feesystem/api/feesystem/get_vote_heads.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ school_id: schoolId, status: 'active' })
    });
    const result = await response.json();
    const heads = result.data?.vote_heads || result.vote_heads || [];
    if (result.success || result.status === 'success') {
      voteHeads = heads;
      const options = '<option value="">Select...</option>' + 
        heads.map(v => `<option value="${v.id}">${escapeHtml(v.name)}</option>`).join('');
      document.querySelectorAll('.vote_head').forEach(sel => {
        if (sel) sel.innerHTML = options;
      });
    }
  } catch (error) { console.error('Error loading vote heads:', error); }
}

// Show empty preview on page load
function showEmptyPreview() {
  const previewContainer = document.getElementById('voucherPreview');
  if (previewContainer) {
    const emptyHtml = `
      <div style="font-family: Arial, sans-serif; padding: 20px; font-size: 12px; background: white; color: #333; text-align: center;">
        <div style="margin-bottom: 20px; border-bottom: 2px solid #4f46e5; padding-bottom: 10px;">
          <h2 style="margin: 0; color: #1e3a8a;">PAYMENT VOUCHER</h2>
        </div>
        <div style="padding: 40px 20px;">
          <i class="fas fa-file-invoice" style="font-size: 48px; color: #9ca3af; margin-bottom: 15px; display: block;"></i>
          <p style="color: #6b7280;">Fill in the voucher details and click Preview</p>
          <p style="color: #9ca3af; font-size: 11px; margin-top: 10px;">Add expense items to see the preview</p>
        </div>
        <div style="margin-top: 20px; text-align: center; font-size: 9px; color: #999; border-top: 1px solid #e5e7eb; padding-top: 10px;">
          This is a computer-generated payment voucher.
        </div>
      </div>
    `;
    previewContainer.innerHTML = emptyHtml;
    currentPreviewHtml = emptyHtml;
  }
}

// ==================== EXPENSE ITEMS MANAGEMENT ====================
function updateExpenseTotal() {
  let total = 0;
  document.querySelectorAll('.item_amount').forEach(input => {
    total += parseFloat(input.value) || 0;
  });
  const expenseTotalSpan = document.getElementById('expenseTotal');
  if (expenseTotalSpan) expenseTotalSpan.innerHTML = `KES ${total.toLocaleString()}`;
  return total;
}

const addItemBtn = document.getElementById('addItemBtn');
if (addItemBtn) {
  addItemBtn.addEventListener('click', () => {
    const container = document.getElementById('expenseItemsContainer');
    if (!container) return;
    const newItem = document.createElement('div');
    newItem.className = 'expense-item border border-gray-200 dark:border-gray-700 rounded-lg p-3';
    newItem.innerHTML = `
      <div class="grid grid-cols-2 gap-2 mb-2">
        <div>
          <label class="text-xs text-gray-500">Vote Head</label>
          <select class="vote_head form-select text-sm">
            <option value="">Select...</option>
            ${voteHeads.map(v => `<option value="${v.id}">${escapeHtml(v.name)}</option>`).join('')}
          </select>
        </div>
        <div>
          <label class="text-xs text-gray-500">Particulars</label>
          <input type="text" class="particulars form-input text-sm" placeholder="Description...">
        </div>
      </div>
      <div class="grid grid-cols-2 gap-2">
        <div>
          <label class="text-xs text-gray-500">Amount</label>
          <input type="number" class="item_amount form-input text-sm" placeholder="0.00" step="0.01">
        </div>
        <div class="flex justify-end items-end">
          <button type="button" class="removeItemBtn text-red-500 hover:text-red-700 text-sm">
            <i class="fas fa-trash"></i> Remove
          </button>
        </div>
      </div>
    `;
    container.appendChild(newItem);
    
    newItem.querySelector('.item_amount').addEventListener('input', updateExpenseTotal);
    newItem.querySelector('.removeItemBtn').addEventListener('click', () => {
      newItem.remove();
      updateExpenseTotal();
    });
  });
}

document.addEventListener('click', (e) => {
  if (e.target.classList && e.target.classList.contains('removeItemBtn')) {
    const item = e.target.closest('.expense-item');
    if (item) item.remove();
    updateExpenseTotal();
  }
});

// ==================== VOUCHER TYPE TOGGLE ====================
document.querySelectorAll('.voucher-type-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    currentVoucherType = btn.dataset.type;
    document.querySelectorAll('.voucher-type-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    
    const simpleFields = document.getElementById('simpleFields');
    const detailedFields = document.getElementById('detailedFields');
    if (simpleFields && detailedFields) {
      if (currentVoucherType === 'simple') {
        simpleFields.classList.remove('hidden');
        detailedFields.classList.add('hidden');
      } else {
        simpleFields.classList.add('hidden');
        detailedFields.classList.remove('hidden');
      }
    }
  });
});

// ==================== PREVIEW VOUCHER ====================
async function generatePreview() {
  const isSimple = currentVoucherType === 'simple';
  const payeeName = isSimple ? document.getElementById('payee_name_simple')?.value : document.getElementById('payee_name_detailed')?.value;
  const idNumber = isSimple ? document.getElementById('id_number_simple')?.value : document.getElementById('id_number_detailed')?.value;
  const paymentDate = isSimple ? document.getElementById('payment_date_simple')?.value : document.getElementById('payment_date_detailed')?.value;
  const paymentMode = isSimple ? document.getElementById('payment_mode_simple')?.value : document.getElementById('payment_mode_detailed')?.value;
  const reference = isSimple ? document.getElementById('reference_simple')?.value : document.getElementById('reference_detailed')?.value;
  const notes = document.getElementById('voucher_notes')?.value || '';
  
  // Get expense items
  const items = [];
  document.querySelectorAll('.expense-item').forEach(item => {
    const voteHeadSelect = item.querySelector('.vote_head');
    const voteHeadName = voteHeadSelect?.options[voteHeadSelect.selectedIndex]?.text || '';
    const particulars = item.querySelector('.particulars')?.value || '';
    const amount = parseFloat(item.querySelector('.item_amount')?.value) || 0;
    if (amount > 0) {
      items.push({ vote_head_name: voteHeadName, particulars: particulars, amount: amount });
    }
  });
  
  const total = items.reduce((sum, i) => sum + i.amount, 0);
  const schoolName = document.querySelector('.font-semibold')?.textContent || 'School';
  
  // Show loading indicator
  const previewContainer = document.getElementById('voucherPreview');
  if (previewContainer) {
    previewContainer.innerHTML = `
      <div class="flex justify-center items-center py-12">
        <div class="loading-spinner"></div>
        <span class="ml-3 text-gray-500">Generating preview...</span>
      </div>
    `;
  }
  
  // Build preview HTML
  let html = `
    <div style="font-family: Arial, sans-serif; padding: 20px; font-size: 12px; background: white; color: #333;">
      <div style="text-align: center; margin-bottom: 20px; border-bottom: 2px solid #4f46e5; padding-bottom: 10px;">
        <h2 style="margin: 0; color: #1e3a8a;">PAYMENT VOUCHER</h2>
        <p style="margin: 5px 0; font-size: 11px;">${escapeHtml(schoolName)}</p>
      </div>
      
      <div style="margin-bottom: 20px;">
        <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
          <tr><td style="padding: 6px; width: 30%; background: #f3f4f6;"><strong>Voucher No:</strong></td><td style="padding: 6px; border-bottom: 1px solid #e5e7eb;">AUTO-GENERATED</td></tr>
          <tr><td style="padding: 6px; background: #f3f4f6;"><strong>Date:</strong></td><td style="padding: 6px; border-bottom: 1px solid #e5e7eb;">${paymentDate ? new Date(paymentDate).toLocaleDateString() : '-'}</td></tr>
          <tr><td style="padding: 6px; background: #f3f4f6;"><strong>Payee Name:</strong></td><td style="padding: 6px; border-bottom: 1px solid #e5e7eb;">${escapeHtml(payeeName) || '-'}</td></tr>
          ${idNumber ? `<tr><td style="padding: 6px; background: #f3f4f6;"><strong>ID/PS Number:</strong></td><td style="padding: 6px; border-bottom: 1px solid #e5e7eb;">${escapeHtml(idNumber)}</td></tr>` : ''}
          <tr><td style="padding: 6px; background: #f3f4f6;"><strong>Payment Mode:</strong></td><td style="padding: 6px; border-bottom: 1px solid #e5e7eb;">${escapeHtml(paymentMode) || '-'}</td></tr>
          ${reference ? `<tr><td style="padding: 6px; background: #f3f4f6;"><strong>Cheque/Ref No.:</strong></td><td style="padding: 6px; border-bottom: 1px solid #e5e7eb;">${escapeHtml(reference)}</td></tr>` : ''}
        </table>
      </div>
      
      <div style="margin-bottom: 20px;">
        <h3 style="background: #f3f4f6; padding: 8px; margin: 0 0 10px 0; font-size: 12px;">EXPENSE ITEMS</h3>
        <table style="width: 100%; border-collapse: collapse; border: 1px solid #ddd; font-size: 11px;">
          <thead><tr style="background: #e5e7eb;"><th style="padding: 8px; border: 1px solid #ddd; text-align: left;">#</th><th style="padding: 8px; border: 1px solid #ddd; text-align: left;">Vote Head</th><th style="padding: 8px; border: 1px solid #ddd; text-align: left;">Particulars</th><th style="padding: 8px; border: 1px solid #ddd; text-align: right;">Amount (KES)</th></tr></thead>
          <tbody>
            ${items.length > 0 ? items.map((item, idx) => `
              <tr><td style="padding: 8px; border: 1px solid #ddd; text-align: center;">${idx + 1}</td><td style="padding: 8px; border: 1px solid #ddd;">${escapeHtml(item.vote_head_name) || '-'}</td><td style="padding: 8px; border: 1px solid #ddd;">${escapeHtml(item.particulars) || '-'}</td><td style="padding: 8px; border: 1px solid #ddd; text-align: right;">${item.amount.toLocaleString()}</td></tr>
            `).join('') : `
              <tr><td colspan="4" style="padding: 30px; border: 1px solid #ddd; text-align: center; color: #999;"><i class="fas fa-receipt" style="font-size: 24px; margin-bottom: 10px; display: block;"></i>No expense items added yet.<br>Click "Add Item" to add expense items.</td></tr>
            `}
          </tbody>
          <tfoot><tr style="background: #fef2f2;"><td colspan="3" style="padding: 8px; border: 1px solid #ddd; text-align: right;"><strong>Total:</strong></td><td style="padding: 8px; border: 1px solid #ddd; text-align: right;"><strong>KES ${total.toLocaleString()}</strong></td></tr></tfoot>
        </table>
      </div>
      
      ${notes ? `<div style="margin-bottom: 15px;"><strong>Notes:</strong><p style="margin: 5px 0; padding: 8px; background: #f9fafb; border-left: 3px solid #4f46e5;">${escapeHtml(notes)}</p></div>` : ''}
      
      <div style="margin-top: 30px;">
        <div style="display: flex; justify-content: space-between; margin-top: 20px;">
          <div style="text-align: center; width: 30%;"><div style="border-top: 1px solid #000; padding-top: 5px;">Prepared By</div><div style="font-size: 10px; margin-top: 5px;">(Accountant)</div></div>
          <div style="text-align: center; width: 30%;"><div style="border-top: 1px solid #000; padding-top: 5px;">Approved By</div><div style="font-size: 10px; margin-top: 5px;">(Finance Officer)</div></div>
          <div style="text-align: center; width: 30%;"><div style="border-top: 1px solid #000; padding-top: 5px;">Received By</div><div style="font-size: 10px; margin-top: 5px;">${escapeHtml(payeeName) || '(Payee)'}</div></div>
        </div>
      </div>
      
      <div style="margin-top: 20px; text-align: center; font-size: 9px; color: #999; border-top: 1px solid #e5e7eb; padding-top: 10px;">This is a computer-generated payment voucher. Generated on ${new Date().toLocaleString()}</div>
    </div>
  `;
  
  currentPreviewHtml = html;
  if (previewContainer) previewContainer.innerHTML = html;
  const downloadBtn = document.getElementById('downloadPdfBtn');
  if (downloadBtn) downloadBtn.disabled = false;
}

const previewBtn = document.getElementById('previewBtn');
if (previewBtn) {
  previewBtn.addEventListener('click', generatePreview);
}

// ==================== SAVE VOUCHER ====================
const voucherForm = document.getElementById('voucherForm');
if (voucherForm) {
  voucherForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const isSimple = currentVoucherType === 'simple';
    const supplierId = isSimple ? document.getElementById('supplier_id_simple')?.value : document.getElementById('supplier_id_detailed')?.value;
    const payeeName = isSimple ? document.getElementById('payee_name_simple')?.value : document.getElementById('payee_name_detailed')?.value;
    const idNumber = isSimple ? document.getElementById('id_number_simple')?.value : document.getElementById('id_number_detailed')?.value;
    const paymentDate = isSimple ? document.getElementById('payment_date_simple')?.value : document.getElementById('payment_date_detailed')?.value;
    const paymentMode = isSimple ? document.getElementById('payment_mode_simple')?.value : document.getElementById('payment_mode_detailed')?.value;
    const reference = isSimple ? document.getElementById('reference_simple')?.value : document.getElementById('reference_detailed')?.value;
    const notes = document.getElementById('voucher_notes')?.value || '';
    
    if (!payeeName) {
      Swal.fire('Error', 'Payee Name is required', 'error');
      return;
    }
    if (!paymentMode) {
      Swal.fire('Error', 'Payment Mode is required', 'error');
      return;
    }
    
    const items = [];
    let hasItems = false;
    document.querySelectorAll('.expense-item').forEach(item => {
      const voteHeadId = item.querySelector('.vote_head')?.value;
      const particulars = item.querySelector('.particulars')?.value || '';
      const amount = parseFloat(item.querySelector('.item_amount')?.value) || 0;
      if (amount > 0) {
        hasItems = true;
        items.push({ vote_head_id: voteHeadId, particulars, amount });
      }
    });
    
    if (!hasItems) {
      Swal.fire('Error', 'Please add at least one expense item', 'error');
      return;
    }
    
    const voucherData = {
      school_id: schoolId,
      user_id: userId,
      type: currentVoucherType,
      supplier_id: supplierId || null,
      payee_name: payeeName,
      id_number: idNumber || '',
      payment_date: paymentDate,
      payment_mode: paymentMode,
      reference: reference || '',
      notes: notes,
      items: items
    };
    
    if (!isSimple) {
      voucherData.lpo_number = document.getElementById('lpo_number')?.value || '';
      voucherData.lpo_date = document.getElementById('lpo_date')?.value || '';
      voucherData.delivery_note_no = document.getElementById('delivery_note_no')?.value || '';
      voucherData.delivery_note_date = document.getElementById('delivery_note_date')?.value || '';
    }
    
    try {
      const response = await fetch('/feesystem/api/payments/save_voucher.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(voucherData)
      });
      const data = await response.json();
      
      if (data.success || data.status === 'success') {
        Swal.fire('Success', 'Payment voucher saved successfully!', 'success');
        clearForm();
        loadVouchers();
      } else {
        Swal.fire('Error', data.message || 'Failed to save voucher', 'error');
      }
    } catch (error) {
      Swal.fire('Error', 'An error occurred', 'error');
    }
  });
}

function clearForm() {
  document.querySelectorAll('#simpleFields input, #detailedFields input, #simpleFields select, #detailedFields select').forEach(el => {
    if (el.type !== 'submit' && el.tagName !== 'BUTTON') el.value = '';
  });
  const voucherNotes = document.getElementById('voucher_notes');
  if (voucherNotes) voucherNotes.value = '';
  
  const expenseContainer = document.getElementById('expenseItemsContainer');
  if (expenseContainer) {
    expenseContainer.innerHTML = `
      <div class="expense-item border border-gray-200 dark:border-gray-700 rounded-lg p-3">
        <div class="grid grid-cols-2 gap-2 mb-2">
          <div><label class="text-xs text-gray-500">Vote Head</label><select class="vote_head form-select text-sm"><option value="">Select...</option></select></div>
          <div><label class="text-xs text-gray-500">Particulars</label><input type="text" class="particulars form-input text-sm" placeholder="Description..."></div>
        </div>
        <div class="grid grid-cols-2 gap-2">
          <div><label class="text-xs text-gray-500">Amount</label><input type="number" class="item_amount form-input text-sm" placeholder="0.00" step="0.01"></div>
          <div class="flex justify-end items-end"><button type="button" class="removeItemBtn text-red-500 text-sm"><i class="fas fa-trash"></i> Remove</button></div>
        </div>
      </div>
    `;
  }
  setDefaultDates();
  updateExpenseTotal();
  showEmptyPreview();
  const downloadBtn = document.getElementById('downloadPdfBtn');
  if (downloadBtn) downloadBtn.disabled = true;
}

const clearFormBtn = document.getElementById('clearFormBtn');
if (clearFormBtn) clearFormBtn.addEventListener('click', clearForm);

// ==================== LOAD VOUCHERS LIST ====================
async function loadVouchers() {
  try {
    const response = await fetch('/feesystem/api/payments/get_vouchers.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ school_id: schoolId })
    });
    const result = await response.json();
    const vouchers = result.data?.vouchers || result.vouchers || [];
    
    const tbody = document.getElementById('vouchersListBody');
    if (tbody) {
      if (vouchers.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-8">No vouchers found</td></tr>';
      } else {
        tbody.innerHTML = vouchers.map(v => `
          <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
            <td class="px-3 py-2 font-mono">${escapeHtml(v.voucher_no)}</td>
            <td class="px-3 py-2">${v.payment_date || '-'}</td>
            <td class="px-3 py-2">${escapeHtml(v.payee_name)}</td>
            <td class="px-3 py-2 text-right font-semibold">KES ${parseFloat(v.total_amount).toLocaleString()}</td>
            <td class="px-3 py-2"><span class="badge ${v.payment_mode === 'cash' ? 'badge-success' : 'badge-info'}">${escapeHtml(v.payment_mode || '-')}</span></td>
            <td class="px-3 py-2"><span class="badge ${v.status === 'approved' ? 'badge-success' : 'badge-warning'}">${v.status || 'pending'}</span></td>
            <td class="px-3 py-2 text-center">
              <button onclick="viewVoucher(${v.id})" class="text-blue-500 hover:text-blue-700 mr-2" title="View Voucher"><i class="fas fa-eye"></i></button>
              <button onclick="downloadVoucherPDF(${v.id})" class="text-green-500 hover:text-green-700" title="Download PDF"><i class="fas fa-download"></i></button>
            </td>
          </tr>
        `).join('');
      }
    }
  } catch (error) { console.error('Error loading vouchers:', error); }
}

// Download Voucher PDF function
window.downloadVoucherPDF = async (voucherId) => {
  if (!voucherId) {
    Swal.fire('Error', 'Invalid voucher ID', 'error');
    return;
  }
  
  Swal.fire({
    title: 'Generating PDF...',
    text: 'Please wait',
    allowOutsideClick: false,
    didOpen: () => {
      Swal.showLoading();
    }
  });
  
  try {
    const response = await fetch('/feesystem/api/payments/generate_voucher_pdf.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ voucher_id: voucherId })
    });
    const data = await response.json();
    
    Swal.close();
    
    if (data.success) {
      window.open(data.pdf_url, '_blank');
      Swal.fire('Success', 'PDF generated successfully', 'success');
    } else {
      Swal.fire('Error', data.message || 'Failed to generate PDF', 'error');
    }
  } catch (error) {
    Swal.close();
    console.error('Error generating PDF:', error);
    Swal.fire('Error', 'An error occurred while generating PDF', 'error');
  }
};

// View Voucher function
window.viewVoucher = (id) => {
  fetch('/feesystem/api/payments/get_vouchers.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ school_id: schoolId })
  }).then(async (response) => {
    const result = await response.json();
    const vouchers = result.data?.vouchers || result.vouchers || [];
    const voucher = vouchers.find(v => v.id === id);
    
    if (voucher) {
      Swal.fire({
        title: 'Voucher Details',
        html: `
          <div style="text-align: left;">
            <p><strong>Voucher No:</strong> ${escapeHtml(voucher.voucher_no)}</p>
            <p><strong>Date:</strong> ${voucher.payment_date}</p>
            <p><strong>Payee:</strong> ${escapeHtml(voucher.payee_name)}</p>
            <p><strong>Amount:</strong> KES ${parseFloat(voucher.total_amount).toLocaleString()}</p>
            <p><strong>Mode:</strong> ${escapeHtml(voucher.payment_mode)}</p>
            <p><strong>Status:</strong> ${voucher.status}</p>
            ${voucher.reference ? `<p><strong>Reference:</strong> ${escapeHtml(voucher.reference)}</p>` : ''}
            ${voucher.notes ? `<p><strong>Notes:</strong> ${escapeHtml(voucher.notes)}</p>` : ''}
          </div>
        `,
        icon: 'info',
        confirmButtonText: 'Close',
        showCancelButton: true,
        cancelButtonText: 'Download PDF',
        cancelButtonColor: '#10B981'
      }).then((result) => {
        if (result.dismiss === Swal.DismissReason.cancel) {
          downloadVoucherPDF(id);
        }
      });
    } else {
      Swal.fire('Info', 'Voucher details not found', 'info');
    }
  }).catch(error => {
    console.error('Error fetching voucher:', error);
    Swal.fire('Error', 'Failed to load voucher details', 'error');
  });
};

// ==================== SUPPLIERS CRUD ====================
async function loadSuppliersList() {
  try {
    const response = await fetch('/feesystem/api/payments/get_suppliers.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ school_id: schoolId })
    });
    const result = await response.json();
    const suppliers = result.data?.suppliers || result.suppliers || [];
    
    const tbody = document.getElementById('suppliersListBody');
    if (tbody) {
      if (suppliers.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-8">No suppliers found</td></tr>';
      } else {
        tbody.innerHTML = suppliers.map(s => `
          <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
            <td class="px-4 py-3 font-medium">${escapeHtml(s.name)}</td>
            <td class="px-4 py-3">${escapeHtml(s.phone || '-')}</td>
            <td class="px-4 py-3 font-mono">${escapeHtml(s.kra_pin || '-')}</td>
            <td class="px-4 py-3">${escapeHtml(s.address || '-')}</td>
            <td class="px-4 py-3 text-center"><button onclick="deleteSupplier(${s.id})" class="text-red-500 hover:text-red-700"><i class="fas fa-trash"></i></button></td>
          </tr>
        `).join('');
      }
    }
  } catch (error) { console.error('Error loading suppliers:', error); }
}

const addSupplierBtn = document.getElementById('addSupplierBtn');
if (addSupplierBtn) {
  addSupplierBtn.addEventListener('click', () => {
    showModal('supplierModal');
  });
}

document.querySelectorAll('.closeModalBtn').forEach(btn => {
  btn.addEventListener('click', () => {
    hideModal('supplierModal');
  });
});

const supplierForm = document.getElementById('supplierForm');
if (supplierForm) {
  supplierForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const name = document.getElementById('supplier_name')?.value;
    if (!name) { Swal.fire('Error', 'Supplier name is required', 'error'); return; }
    
    try {
      const response = await fetch('/feesystem/api/payments/save_supplier.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          school_id: schoolId,
          name: name,
          phone: document.getElementById('supplier_phone')?.value || '',
          address: document.getElementById('supplier_address')?.value || '',
          kra_pin: document.getElementById('supplier_kra_pin')?.value || ''
        })
      });
      const data = await response.json();
      if (data.success || data.status === 'success') {
        Swal.fire('Success', 'Supplier added successfully', 'success');
        hideModal('supplierModal');
        supplierForm.reset();
        loadSuppliers();
        loadSuppliersList();
      } else {
        Swal.fire('Error', data.message || 'Failed to add supplier', 'error');
      }
    } catch (error) { Swal.fire('Error', 'An error occurred', 'error'); }
  });
}

window.deleteSupplier = async (id) => {
  const result = await Swal.fire({ title: 'Confirm', text: 'Delete this supplier?', icon: 'warning', showCancelButton: true });
  if (result.isConfirmed) {
    try {
      const response = await fetch('/feesystem/api/payments/delete_supplier.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id, school_id: schoolId })
      });
      const data = await response.json();
      if (data.success || data.status === 'success') {
        Swal.fire('Deleted', 'Supplier deleted successfully', 'success');
        loadSuppliers();
        loadSuppliersList();
      } else { Swal.fire('Error', data.message || 'Failed to delete', 'error'); }
    } catch (error) { Swal.fire('Error', 'An error occurred', 'error'); }
  }
};

// ==================== INVOICES ====================
function createInvoiceItem(description = '', qty = 1, price = 0) {
  const container = document.getElementById('invoiceItemsContainer');
  if (!container) return null;
  
  const newItem = document.createElement('div');
  newItem.className = 'invoice-item border border-gray-200 dark:border-gray-700 rounded-lg p-3 mb-3';
  newItem.innerHTML = `
    <div class="mb-2"><label class="text-xs text-gray-500">Description <span class="text-red-500">*</span></label><input type="text" class="item_desc form-input text-sm" placeholder="Enter item description..." value="${escapeHtml(description)}"></div>
    <div class="grid grid-cols-3 gap-2">
      <div><label class="text-xs text-gray-500">Qty <span class="text-red-500">*</span></label><input type="number" class="item_qty form-input text-sm" value="${qty}" step="1" min="1"></div>
      <div><label class="text-xs text-gray-500">Unit Price <span class="text-red-500">*</span></label><input type="number" class="item_price form-input text-sm" step="0.01" value="${price}"></div>
      <div><label class="text-xs text-gray-500">Total</label><input type="text" class="item_total form-input text-sm bg-gray-100 dark:bg-gray-700" readonly value="${(qty * price).toFixed(2)}"></div>
    </div>
    <div class="flex justify-end mt-2"><button type="button" class="removeInvoiceItemBtn text-red-500 hover:text-red-700 text-sm"><i class="fas fa-trash"></i> Remove</button></div>
  `;
  
  const qtyInput = newItem.querySelector('.item_qty');
  const priceInput = newItem.querySelector('.item_price');
  const totalInput = newItem.querySelector('.item_total');
  
  const updateItemTotal = () => {
    const qty = parseFloat(qtyInput.value) || 0;
    const price = parseFloat(priceInput.value) || 0;
    totalInput.value = (qty * price).toFixed(2);
    updateInvoiceTotal();
  };
  
  qtyInput.addEventListener('input', updateItemTotal);
  priceInput.addEventListener('input', updateItemTotal);
  newItem.querySelector('.removeInvoiceItemBtn').addEventListener('click', () => {
    newItem.remove();
    updateInvoiceTotal();
  });
  
  container.appendChild(newItem);
  return newItem;
}

function updateInvoiceTotal() {
  let total = 0;
  document.querySelectorAll('.invoice-item').forEach(item => {
    const totalInput = item.querySelector('.item_total');
    if (totalInput) total += parseFloat(totalInput.value) || 0;
  });
  const invoiceTotalSpan = document.getElementById('invoiceTotal');
  if (invoiceTotalSpan) invoiceTotalSpan.innerHTML = `KES ${total.toLocaleString()}`;
  return total;
}

function validateInvoiceItems() {
  let isValid = true;
  let errorMessage = '';
  document.querySelectorAll('.invoice-item').forEach((item, index) => {
    const description = item.querySelector('.item_desc')?.value?.trim();
    const qty = parseFloat(item.querySelector('.item_qty')?.value) || 0;
    const price = parseFloat(item.querySelector('.item_price')?.value) || 0;
    if (!description) { isValid = false; errorMessage = `Item ${index + 1}: Description is required`; }
    else if (qty <= 0) { isValid = false; errorMessage = `Item ${index + 1}: Quantity must be greater than 0`; }
    else if (price <= 0) { isValid = false; errorMessage = `Item ${index + 1}: Unit price must be greater than 0`; }
  });
  return { isValid, errorMessage };
}

const addInvoiceItemBtn = document.getElementById('addInvoiceItemBtn');
if (addInvoiceItemBtn) {
  addInvoiceItemBtn.addEventListener('click', () => {
    createInvoiceItem('', 1, 0);
  });
}

const addInvoiceBtn = document.getElementById('addInvoiceBtn');
if (addInvoiceBtn) {
  addInvoiceBtn.addEventListener('click', () => {
    const invoiceFormEl = document.getElementById('invoiceForm');
    if (invoiceFormEl) invoiceFormEl.reset();
    const container = document.getElementById('invoiceItemsContainer');
    if (container) container.innerHTML = '';
    createInvoiceItem('', 1, 0);
    updateInvoiceTotal();
    setDefaultDates();
    showModal('invoiceModal');
  });
}

document.querySelectorAll('.closeInvoiceModalBtn').forEach(btn => {
  btn.addEventListener('click', () => {
    hideModal('invoiceModal');
  });
});

const invoiceForm = document.getElementById('invoiceForm');
if (invoiceForm) {
  invoiceForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const supplierId = document.getElementById('invoice_supplier_id')?.value;
    const invoiceNumber = document.getElementById('invoice_number')?.value;
    if (!supplierId || !invoiceNumber) {
      Swal.fire('Error', 'Supplier and Invoice Number are required', 'error');
      return;
    }
    
    const { isValid, errorMessage } = validateInvoiceItems();
    if (!isValid) {
      Swal.fire('Error', errorMessage, 'error');
      return;
    }
    
    const items = [];
    document.querySelectorAll('.invoice-item').forEach(item => {
      const description = item.querySelector('.item_desc')?.value?.trim();
      const qty = parseFloat(item.querySelector('.item_qty')?.value) || 0;
      const price = parseFloat(item.querySelector('.item_price')?.value) || 0;
      if (description && qty > 0 && price > 0) {
        items.push({ description: description, quantity: qty, unit_price: price, total: qty * price });
      }
    });
    
    if (items.length === 0) {
      Swal.fire('Error', 'Please add at least one valid invoice item with description, quantity, and price', 'error');
      return;
    }
    
    try {
      const response = await fetch('/feesystem/api/payments/save_invoice.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          school_id: schoolId,
          supplier_id: supplierId,
          invoice_number: invoiceNumber,
          invoice_date: document.getElementById('invoice_date')?.value || '',
          due_date: document.getElementById('invoice_due_date')?.value || '',
          notes: document.getElementById('invoice_notes')?.value || '',
          items: items
        })
      });
      const data = await response.json();
      if (data.success || data.status === 'success') {
        Swal.fire('Success', 'Invoice saved successfully', 'success');
        hideModal('invoiceModal');
        loadInvoices();
      } else {
        Swal.fire('Error', data.message || 'Failed to save invoice', 'error');
      }
    } catch (error) { 
      console.error('Error saving invoice:', error);
      Swal.fire('Error', 'An error occurred while saving the invoice', 'error'); 
    }
  });
}

async function loadInvoices() {
  try {
    const response = await fetch('/feesystem/api/payments/get_invoices.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ school_id: schoolId })
    });
    const result = await response.json();
    const invoices = result.data?.invoices || result.invoices || [];
    
    const tbody = document.getElementById('invoicesListBody');
    if (tbody) {
      if (invoices.length === 0) {
        tbody.innerHTML = '<td><td colspan="7" class="text-center py-8">No invoices found</td></tr>';
      } else {
        tbody.innerHTML = invoices.map(inv => `
          <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
            <td class="px-4 py-3 font-mono">${escapeHtml(inv.invoice_number)}</td>
            <td class="px-4 py-3">${escapeHtml(inv.supplier_name || '-')}</td>
            <td class="px-4 py-3">${inv.invoice_date || '-'}</td>
            <td class="px-4 py-3 text-right">KES ${parseFloat(inv.total_amount || 0).toLocaleString()}</td>
            <td class="px-4 py-3 text-right font-semibold ${(inv.balance || inv.total_amount) > 0 ? 'text-red-600' : 'text-green-600'}">KES ${parseFloat(inv.balance || inv.total_amount || 0).toLocaleString()}</td>
            <td class="px-4 py-3"><span class="badge ${inv.status === 'paid' ? 'badge-success' : 'badge-warning'}">${inv.status || 'pending'}</span></td>
            <td class="px-4 py-3 text-center"><button onclick="viewInvoice(${inv.id})" class="text-blue-500 hover:text-blue-700"><i class="fas fa-eye"></i></button></td>
          </tr>
        `).join('');
      }
    }
  } catch (error) { console.error('Error loading invoices:', error); }
}

window.viewInvoice = (id) => { Swal.fire('Info', 'View invoice feature coming soon', 'info'); };

// ==================== MAIN TOGGLE ====================
document.querySelectorAll('.main-toggle-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const tabId = btn.dataset.mainTab;
    document.querySelectorAll('.main-toggle-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.main-tab-content').forEach(t => t.classList.add('hidden'));
    btn.classList.add('active');
    const activeTab = document.getElementById(`main-tab-${tabId}`);
    if (activeTab) activeTab.classList.remove('hidden');
    if (tabId === 'suppliers') loadSuppliersList();
    else if (tabId === 'invoices') loadInvoices();
  });
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
  loadSuppliers();
  loadVoteHeads();
  loadVouchers();
  loadSuppliersList();
  loadInvoices();
  updateExpenseTotal();
  showEmptyPreview();
});
</script>

<?php include_once('../../includes/footer.php'); ?>