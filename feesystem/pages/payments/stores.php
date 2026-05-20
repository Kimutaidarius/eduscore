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

/* Card Styles */
.stats-card {
    transition: all 0.3s ease;
}
.stats-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
}

/* Table Styles */
.data-table tr:hover {
    background-color: rgba(79, 70, 229, 0.05);
}

.filter-input, .filter-select {
    transition: all 0.2s ease;
}
.filter-input:focus, .filter-select:focus {
    border-color: #4f46e5;
    ring: 2px solid #4f46e5;
}

/* Modal Z-INDEX FIX */
#itemModal, #viewIssuanceModal, #depositModal, #withdrawalModal, .modal {
    z-index: 9999 !important;
}
#itemModal.fixed.inset-0, #viewIssuanceModal.fixed.inset-0, .modal-backdrop {
    z-index: 9998 !important;
}
#itemModal > div, #viewIssuanceModal > div, .modal > div {
    z-index: 10000 !important;
    position: relative;
}
header, .main-header, .bg-white.shadow-sm, header.bg-white {
    z-index: 100 !important;
    position: relative;
}
.modal-open {
    overflow: hidden;
}
.swal2-container {
    z-index: 99999 !important;
}
.dark #itemModal .bg-white, .dark #viewIssuanceModal .bg-white {
    background-color: #1f2937 !important;
    color: #f3f4f6 !important;
}
.dark .modal input, .dark .modal select, .dark .modal textarea {
    background-color: #374151 !important;
    border-color: #4b5563 !important;
    color: #f3f4f6 !important;
}

/* Loading spinner */
.loading-spinner {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 2px solid #f3f3f3;
    border-top: 2px solid #4f46e5;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Responsive tables */
@media (max-width: 768px) {
    .data-table thead {
        display: none;
    }
    .data-table tbody tr {
        display: block;
        margin-bottom: 1rem;
        border: 1px solid #e5e7eb;
        border-radius: 0.5rem;
        padding: 0.75rem;
    }
    .data-table tbody td {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.5rem;
        border-bottom: 1px solid #e5e7eb;
    }
    .data-table tbody td:before {
        content: attr(data-label);
        font-weight: 600;
        margin-right: 1rem;
    }
    .data-table tbody td:last-child {
        border-bottom: none;
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
        <h1 id="page-title" class="text-xl font-semibold text-gray-800 dark:text-white">Stores Management</h1>
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
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm mb-6">
      <div class="border-b border-gray-200 dark:border-gray-700">
        <nav class="flex flex-wrap" id="mainTabNav">
          <button class="main-toggle-btn active" data-main-tab="issuances">
            <i class="fas fa-sign-out-alt mr-2"></i>Issuances
          </button>
          <button class="main-toggle-btn" data-main-tab="items">
            <i class="fas fa-boxes mr-2"></i>Store Items
          </button>
          <button class="main-toggle-btn" data-main-tab="lpos">
            <i class="fas fa-file-alt mr-2"></i>LPOs
          </button>
          <button class="main-toggle-btn" data-main-tab="grns">
            <i class="fas fa-truck-loading mr-2"></i>GRNs
          </button>
        </nav>
      </div>
    </div>

    <!-- ==================== TAB 1: ISSUANCES ==================== -->
    <div id="main-tab-issuances" class="main-tab-content active">
      <!-- Stats Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <div class="stats-card bg-gradient-to-r from-blue-500 to-blue-600 rounded-lg shadow-lg p-5 text-white">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-blue-100 text-sm">Total Issuances</p>
              <p class="text-3xl font-bold mt-1" id="totalIssuances">0</p>
            </div>
            <i class="fas fa-sign-out-alt text-4xl opacity-50"></i>
          </div>
        </div>
        <div class="stats-card bg-gradient-to-r from-green-500 to-green-600 rounded-lg shadow-lg p-5 text-white">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-green-100 text-sm">Total Quantity Issued</p>
              <p class="text-3xl font-bold mt-1" id="totalQuantityIssued">0</p>
            </div>
            <i class="fas fa-cubes text-4xl opacity-50"></i>
          </div>
        </div>
        <div class="stats-card bg-gradient-to-r from-purple-500 to-purple-600 rounded-lg shadow-lg p-5 text-white">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-purple-100 text-sm">Total Value (KES)</p>
              <p class="text-3xl font-bold mt-1" id="totalIssuanceValue">KES 0.00</p>
            </div>
            <i class="fas fa-chart-line text-4xl opacity-50"></i>
          </div>
        </div>
        <div class="stats-card bg-gradient-to-r from-orange-500 to-orange-600 rounded-lg shadow-lg p-5 text-white">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-orange-100 text-sm">Departments</p>
              <p class="text-3xl font-bold mt-1" id="departmentsCount">0</p>
            </div>
            <i class="fas fa-building text-4xl opacity-50"></i>
          </div>
        </div>
      </div>

      <!-- Issuance Form and List -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Left Column: New Issuance Form -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-5">
          <h2 class="text-lg font-semibold text-gray-800 dark:text-white mb-4 border-b pb-2">
            <i class="fas fa-plus-circle text-green-500 mr-2"></i>New Issuance
          </h2>
          <form id="issuanceForm">
            <div class="grid grid-cols-2 gap-4 mb-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Issue Date <span class="text-red-500">*</span></label>
                <input type="date" id="issue_date" required class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Issue No.</label>
                <input type="text" id="issue_no" readonly class="w-full px-3 py-2 border rounded-lg bg-gray-100 dark:bg-gray-600">
              </div>
            </div>
            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Department <span class="text-red-500">*</span></label>
              <select id="department_id" required class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
                <option value="">Loading departments...</option>
              </select>
            </div>
            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Requested By</label>
              <input type="text" id="requested_by" placeholder="Staff name" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
            </div>
            
            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Items</label>
              <button type="button" id="addIssuanceItemBtn" class="mb-3 px-3 py-1 bg-indigo-100 text-indigo-700 rounded-lg text-sm hover:bg-indigo-200">
                <i class="fas fa-plus mr-1"></i>Add Item
              </button>
              <div id="issuanceItemsContainer" class="space-y-3"></div>
            </div>
            
            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Remarks</label>
              <textarea id="issuance_remarks" rows="2" placeholder="Additional notes..." class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600"></textarea>
            </div>
            
            <button type="submit" class="w-full px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
              <i class="fas fa-save mr-2"></i>Process Issuance
            </button>
          </form>
        </div>

        <!-- Right Column: Issuances List -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-5">
          <h2 class="text-lg font-semibold text-gray-800 dark:text-white mb-4 border-b pb-2">
            <i class="fas fa-list mr-2 text-purple-500"></i>Recent Issuances
          </h2>
          <div class="overflow-x-auto">
            <table class="w-full text-sm data-table">
              <thead class="bg-gray-50 dark:bg-gray-700">
                <tr><th class="px-3 py-2 text-left">Issue No.</th><th class="px-3 py-2 text-left">Date</th><th class="px-3 py-2 text-left">Department</th><th class="px-3 py-2 text-right">Items</th><th class="px-3 py-2 text-right">Total Qty</th><th class="px-3 py-2 text-center">Actions</th></tr>
              </thead>
              <tbody id="issuancesListBody"><tr><td colspan="6" class="text-center py-8 text-gray-500">Loading issuances...</td></tr></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- ==================== TAB 2: STORE ITEMS ==================== -->
    <div id="main-tab-items" class="main-tab-content">
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
          <h2 class="text-xl font-semibold text-gray-800 dark:text-white">
            <i class="fas fa-boxes text-indigo-500 mr-2"></i>Store Items Inventory
          </h2>
          <button id="addItemBtn" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
            <i class="fas fa-plus mr-2"></i>Add Item
          </button>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
          <div><label class="block text-sm font-medium mb-1">Search</label><input type="text" id="item_search" placeholder="Search by name or code..." class="filter-input w-full px-3 py-2 border rounded-lg dark:bg-gray-700"></div>
          <div><label class="block text-sm font-medium mb-1">Category</label><select id="item_category_filter" class="filter-select w-full px-3 py-2 border rounded-lg dark:bg-gray-700"><option value="">All Categories</option></select></div>
          <div><label class="block text-sm font-medium mb-1">Stock Status</label><select id="stock_status_filter" class="filter-select w-full px-3 py-2 border rounded-lg dark:bg-gray-700"><option value="">All</option><option value="low">Low Stock</option><option value="out">Out of Stock</option><option value="in">In Stock</option></select></div>
        </div>
        
        <div class="overflow-x-auto">
          <table class="w-full text-sm data-table">
            <thead class="bg-gray-50 dark:bg-gray-700">
              <tr><th class="px-4 py-3">Item Code</th><th class="px-4 py-3">Item Name</th><th class="px-4 py-3">Category</th><th class="px-4 py-3">Unit</th><th class="px-4 py-3 text-right">Stock</th><th class="px-4 py-3 text-right">Unit Price</th><th class="px-4 py-3 text-right">Reorder Level</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Actions</th></tr>
            </thead>
            <tbody id="itemsListBody"><tr><td colspan="9" class="text-center py-8 text-gray-500">Loading items...</td></tr></tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ==================== TAB 3: LPOs ==================== -->
    <div id="main-tab-lpos" class="main-tab-content">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-5">
          <h2 class="text-lg font-semibold mb-4 border-b pb-2"><i class="fas fa-file-alt text-blue-500 mr-2"></i>Create LPO</h2>
          <form id="lpoForm">
            <div class="grid grid-cols-2 gap-4 mb-4">
              <div><label class="block text-sm font-medium mb-1">LPO Date <span class="text-red-500">*</span></label><input type="date" id="lpo_date" required class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700"></div>
              <div><label class="block text-sm font-medium mb-1">LPO No.</label><input type="text" id="lpo_no" readonly class="w-full px-3 py-2 border rounded-lg bg-gray-100 dark:bg-gray-600"></div>
            </div>
            <div class="mb-4"><label class="block text-sm font-medium mb-1">Supplier <span class="text-red-500">*</span></label><select id="lpo_supplier_id" required class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700"><option value="">Loading suppliers...</option></select></div>
            <div class="mb-4"><label class="block text-sm font-medium mb-1">Delivery Date</label><input type="date" id="lpo_delivery_date" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700"></div>
            
            <div class="mb-4">
              <label class="block text-sm font-medium mb-2">Items</label>
              <button type="button" id="addLpoItemBtn" class="mb-3 px-3 py-1 bg-indigo-100 text-indigo-700 rounded-lg text-sm hover:bg-indigo-200"><i class="fas fa-plus mr-1"></i>Add Item</button>
              <div id="lpoItemsContainer" class="space-y-3"></div>
              <div class="text-right mt-2 font-bold">Total: <span id="lpoTotal">KES 0.00</span></div>
            </div>
            
            <div class="mb-4"><label class="block text-sm font-medium mb-1">Notes</label><textarea id="lpo_notes" rows="2" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700"></textarea></div>
            <button type="submit" class="w-full px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition"><i class="fas fa-save mr-2"></i>Create LPO</button>
          </form>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-5">
          <h2 class="text-lg font-semibold mb-4 border-b pb-2"><i class="fas fa-list mr-2 text-purple-500"></i>LPOs List</h2>
          <div class="overflow-x-auto">
            <table class="w-full text-sm data-table">
              <thead class="bg-gray-50 dark:bg-gray-700"><tr><th class="px-3 py-2 text-left">LPO No.</th><th class="px-3 py-2 text-left">Date</th><th class="px-3 py-2 text-left">Supplier</th><th class="px-3 py-2 text-right">Amount</th><th class="px-3 py-2 text-left">Status</th><th class="px-3 py-2 text-center">Actions</th></tr></thead>
              <tbody id="lposListBody"><tr><td colspan="6" class="text-center py-8 text-gray-500">Loading LPOs...</td></tr></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- ==================== TAB 4: GRNs ==================== -->
    <div id="main-tab-grns" class="main-tab-content">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-5">
          <h2 class="text-lg font-semibold mb-4 border-b pb-2"><i class="fas fa-truck-loading text-orange-500 mr-2"></i>Create GRN</h2>
          <form id="grnForm">
            <div class="grid grid-cols-2 gap-4 mb-4">
              <div><label class="block text-sm font-medium mb-1">GRN Date <span class="text-red-500">*</span></label><input type="date" id="grn_date" required class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700"></div>
              <div><label class="block text-sm font-medium mb-1">GRN No.</label><input type="text" id="grn_no" readonly class="w-full px-3 py-2 border rounded-lg bg-gray-100 dark:bg-gray-600"></div>
            </div>
            <div class="mb-4"><label class="block text-sm font-medium mb-1">LPO Reference <span class="text-red-500">*</span></label><select id="grn_lpo_id" required class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700"><option value="">Select LPO...</option></select></div>
            <div class="mb-4"><label class="block text-sm font-medium mb-1">Delivery Note No.</label><input type="text" id="grn_delivery_note" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700"></div>
            <div class="mb-4"><label class="block text-sm font-medium mb-1">Received By</label><input type="text" id="grn_received_by" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700"></div>
            <div class="mb-4"><label class="block text-sm font-medium mb-2">Items Received</label><div id="grnItemsContainer" class="space-y-3"><div class="text-center text-gray-500 py-4">Select an LPO to load items</div></div></div>
            <div class="mb-4"><label class="block text-sm font-medium mb-1">Notes</label><textarea id="grn_notes" rows="2" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700"></textarea></div>
            <button type="submit" class="w-full px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition"><i class="fas fa-save mr-2"></i>Receive Goods</button>
          </form>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-5">
          <h2 class="text-lg font-semibold mb-4 border-b pb-2"><i class="fas fa-list mr-2 text-purple-500"></i>GRNs List</h2>
          <div class="overflow-x-auto">
            <table class="w-full text-sm data-table">
              <thead class="bg-gray-50 dark:bg-gray-700"><tr><th class="px-3 py-2 text-left">GRN No.</th><th class="px-3 py-2 text-left">Date</th><th class="px-3 py-2 text-left">LPO No.</th><th class="px-3 py-2 text-left">Supplier</th><th class="px-3 py-2 text-center">Actions</th></tr></thead>
              <tbody id="grnsListBody"><tr><td colspan="5" class="text-center py-8 text-gray-500">Loading GRNs...</td></tr></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>

<!-- Modals -->
<div id="itemModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center overflow-y-auto" style="z-index: 9999;">
  <div class="bg-white dark:bg-gray-800 w-full max-w-md rounded-lg shadow-xl mx-4" style="z-index: 10000; position: relative;">
    <div class="border-b border-gray-200 dark:border-gray-700 px-6 py-4 flex justify-between items-center">
      <h3 class="text-lg font-semibold"><i class="fas fa-box text-indigo-500 mr-2"></i><span id="itemModalTitle">Add Store Item</span></h3>
      <button class="closeItemModal text-gray-400 hover:text-gray-500 text-2xl leading-none">&times;</button>
    </div>
    <div class="px-6 py-4">
      <form id="itemForm">
        <input type="hidden" id="item_edit_id">
        <div class="mb-4"><label class="block text-sm font-medium mb-1">Item Code <span class="text-red-500">*</span></label><input type="text" id="item_code" required class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700"></div>
        <div class="mb-4"><label class="block text-sm font-medium mb-1">Item Name <span class="text-red-500">*</span></label><input type="text" id="item_name" required class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700"></div>
        <div class="mb-4"><label class="block text-sm font-medium mb-1">Category</label><select id="item_category" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700"><option value="">Select category...</option></select></div>
        <div class="mb-4"><label class="block text-sm font-medium mb-1">Unit of Measure</label><select id="item_unit" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700"><option value="pcs">Pieces (pcs)</option><option value="kg">Kilograms (kg)</option><option value="litres">Litres (L)</option><option value="boxes">Boxes</option><option value="reams">Reams</option></select></div>
        <div class="mb-4"><label class="block text-sm font-medium mb-1">Unit Price (KES)</label><input type="number" id="item_unit_price" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700" step="0.01"></div>
        <div class="mb-4"><label class="block text-sm font-medium mb-1">Initial Stock</label><input type="number" id="item_initial_stock" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700" step="1" min="0"></div>
        <div class="mb-4"><label class="block text-sm font-medium mb-1">Reorder Level</label><input type="number" id="item_reorder_level" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700" step="1" min="0" value="10"></div>
        <div class="flex justify-end gap-3"><button type="button" class="closeItemModal px-4 py-2 border rounded-lg">Cancel</button><button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">Save Item</button></div>
      </form>
    </div>
  </div>
</div>

<div id="viewIssuanceModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center overflow-y-auto" style="z-index: 9999;">
  <div class="bg-white dark:bg-gray-800 w-full max-w-2xl rounded-lg shadow-xl mx-4" style="z-index: 10000; position: relative;">
    <div class="border-b border-gray-200 dark:border-gray-700 px-6 py-4 flex justify-between items-center">
      <h3 class="text-lg font-semibold"><i class="fas fa-sign-out-alt text-blue-500 mr-2"></i>Issuance Details</h3>
      <button class="closeViewModal text-gray-400 hover:text-gray-500 text-2xl leading-none">&times;</button>
    </div>
    <div class="px-6 py-4" id="issuanceDetailsContent"></div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const schoolId = <?php echo json_encode($school_id); ?>;
const userId = <?php echo json_encode($user_id); ?>;

// Tab switching
document.querySelectorAll('.main-toggle-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        const tabId = this.dataset.mainTab;
        document.querySelectorAll('.main-toggle-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.main-tab-content').forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        document.getElementById(`main-tab-${tabId}`).classList.add('active');
        
        // Refresh data when tab changes
        if (tabId === 'items') loadItems();
        else if (tabId === 'lpos') loadLPOs();
        else if (tabId === 'grns') loadGRNs();
        else if (tabId === 'issuances') loadIssuances();
    });
});

// Helper functions
function lockBodyScroll() { document.body.style.overflow = 'hidden'; document.body.classList.add('modal-open'); }
function unlockBodyScroll() { document.body.style.overflow = ''; document.body.classList.remove('modal-open'); }
function escapeHtml(text) { if (!text) return ''; const div = document.createElement('div'); div.textContent = text; return div.innerHTML; }

// Generate reference number
function generateRefNumber(prefix) {
    const date = new Date();
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const random = Math.floor(Math.random() * 10000).toString().padStart(4, '0');
    return `${prefix}-${year}${month}-${random}`;
}

// ==================== LOAD CATEGORIES ====================
async function loadCategories() {
    try {
        const response = await fetch('/feesystem/api/stores/get_categories.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ school_id: schoolId })
        });
        const data = await response.json();
        if (data.success && data.categories) {
            let options = '<option value="">Select category...</option>';
            data.categories.forEach(cat => { options += `<option value="${cat.id}">${escapeHtml(cat.name)}</option>`; });
            document.getElementById('item_category').innerHTML = options;
            document.getElementById('item_category_filter').innerHTML = '<option value="">All Categories</option>' + data.categories.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
        }
    } catch (error) { console.error('Error loading categories:', error); }
}

// ==================== LOAD DEPARTMENTS ====================
async function loadDepartments() {
    try {
        const response = await fetch('/feesystem/api/stores/get_departments.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ school_id: schoolId })
        });
        const data = await response.json();
        if (data.success && data.departments) {
            let options = '<option value="">Select department...</option>';
            data.departments.forEach(dept => { options += `<option value="${dept.id}">${escapeHtml(dept.name)}</option>`; });
            document.getElementById('department_id').innerHTML = options;
            document.getElementById('departmentsCount').innerText = data.departments.length;
        }
    } catch (error) { console.error('Error loading departments:', error); }
}

// ==================== LOAD SUPPLIERS ====================
async function loadSuppliers() {
    try {
        const response = await fetch('/feesystem/api/stores/get_suppliers.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ school_id: schoolId })
        });
        const data = await response.json();
        if (data.success && data.suppliers) {
            let options = '<option value="">Select supplier...</option>';
            data.suppliers.forEach(sup => { options += `<option value="${sup.id}">${escapeHtml(sup.name)}</option>`; });
            document.getElementById('lpo_supplier_id').innerHTML = options;
        }
    } catch (error) { console.error('Error loading suppliers:', error); }
}

// ==================== LOAD ITEMS ====================
async function loadItems() {
    const search = document.getElementById('item_search')?.value || '';
    const categoryId = document.getElementById('item_category_filter')?.value || '';
    const stockStatus = document.getElementById('stock_status_filter')?.value || '';
    
    try {
        const response = await fetch('/feesystem/api/stores/get_items.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ school_id: schoolId, search, category_id: categoryId, stock_status: stockStatus })
        });
        const data = await response.json();
        const tbody = document.getElementById('itemsListBody');
        if (data.success && data.items && data.items.length > 0) {
            tbody.innerHTML = data.items.map(item => {
                let statusClass = '', statusText = '';
                if (item.current_stock <= 0) { statusClass = 'bg-red-100 text-red-800'; statusText = 'Out of Stock'; }
                else if (item.current_stock <= item.reorder_level) { statusClass = 'bg-yellow-100 text-yellow-800'; statusText = 'Low Stock'; }
                else { statusClass = 'bg-green-100 text-green-800'; statusText = 'In Stock'; }
                return `<tr class="border-b dark:border-gray-700">
                    <td class="px-4 py-3" data-label="Item Code">${escapeHtml(item.item_code)}</td>
                    <td class="px-4 py-3" data-label="Item Name">${escapeHtml(item.item_name)}</td>
                    <td class="px-4 py-3" data-label="Category">${escapeHtml(item.category_name || '-')}</td>
                    <td class="px-4 py-3" data-label="Unit">${escapeHtml(item.unit_of_measure)}</td>
                    <td class="px-4 py-3 text-right" data-label="Stock">${parseFloat(item.current_stock).toLocaleString()}</td>
                    <td class="px-4 py-3 text-right" data-label="Unit Price">KES ${parseFloat(item.unit_price).toLocaleString()}</td>
                    <td class="px-4 py-3 text-right" data-label="Reorder Level">${parseFloat(item.reorder_level).toLocaleString()}</td>
                    <td class="px-4 py-3" data-label="Status"><span class="px-2 py-1 rounded-full text-xs ${statusClass}">${statusText}</span></td>
                    <td class="px-4 py-3" data-label="Actions"><button onclick="editItem(${item.id})" class="text-blue-500 mr-2"><i class="fas fa-edit"></i></button><button onclick="deleteItem(${item.id})" class="text-red-500"><i class="fas fa-trash"></i></button></td>
                </tr>`;
            }).join('');
        } else { tbody.innerHTML = '<tr><td colspan="9" class="text-center py-8 text-gray-500">No items found</td></tr>'; }
    } catch (error) { console.error('Error loading items:', error); document.getElementById('itemsListBody').innerHTML = '<tr><td colspan="9" class="text-center py-8 text-gray-500">Error loading items</td></tr>'; }
}

// ==================== SAVE ITEM ====================
document.getElementById('addItemBtn').addEventListener('click', () => {
    document.getElementById('itemModalTitle').innerText = 'Add Store Item';
    document.getElementById('itemForm').reset();
    document.getElementById('item_edit_id').value = '';
    document.getElementById('item_code').value = generateRefNumber('ITM');
    document.getElementById('itemModal').classList.remove('hidden');
    lockBodyScroll();
});
document.querySelectorAll('.closeItemModal').forEach(btn => btn.addEventListener('click', () => { document.getElementById('itemModal').classList.add('hidden'); unlockBodyScroll(); }));
document.getElementById('itemModal').addEventListener('click', (e) => { if (e.target === document.getElementById('itemModal')) { document.getElementById('itemModal').classList.add('hidden'); unlockBodyScroll(); } });

document.getElementById('itemForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = { school_id: schoolId, user_id: userId, item_id: document.getElementById('item_edit_id').value, item_code: document.getElementById('item_code').value, item_name: document.getElementById('item_name').value, category_id: document.getElementById('item_category').value, unit_of_measure: document.getElementById('item_unit').value, unit_price: document.getElementById('item_unit_price').value, initial_stock: document.getElementById('item_initial_stock').value, reorder_level: document.getElementById('item_reorder_level').value };
    try {
        const response = await fetch('/feesystem/api/stores/save_item.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(formData) });
        const data = await response.json();
        if (data.success) { Swal.fire('Success', data.message, 'success'); document.getElementById('itemModal').classList.add('hidden'); unlockBodyScroll(); loadItems(); }
        else { Swal.fire('Error', data.message, 'error'); }
    } catch (error) { Swal.fire('Error', 'An error occurred', 'error'); }
});

// ==================== EDIT ITEM ====================
window.editItem = async (id) => {
    try {
        const response = await fetch('/feesystem/api/stores/get_item.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ school_id: schoolId, item_id: id })
        });
        const data = await response.json();
        
        if (data.success && data.item) {
            document.getElementById('itemModalTitle').innerText = 'Edit Store Item';
            document.getElementById('item_edit_id').value = data.item.id;
            document.getElementById('item_code').value = data.item.item_code;
            document.getElementById('item_name').value = data.item.item_name;
            document.getElementById('item_category').value = data.item.category_id || '';
            document.getElementById('item_unit').value = data.item.unit_of_measure;
            document.getElementById('item_unit_price').value = data.item.unit_price;
            document.getElementById('item_initial_stock').value = data.item.current_stock;
            document.getElementById('item_reorder_level').value = data.item.reorder_level;
            document.getElementById('itemModal').classList.remove('hidden');
            lockBodyScroll();
        } else {
            Swal.fire('Error', 'Failed to load item details', 'error');
        }
    } catch (error) {
        console.error('Error loading item:', error);
        Swal.fire('Error', 'An error occurred', 'error');
    }
};

// ==================== DELETE ITEM ====================
window.deleteItem = async (id) => {
    const result = await Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!'
    });
    
    if (result.isConfirmed) {
        try {
            const response = await fetch('/feesystem/api/stores/delete_item.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ school_id: schoolId, item_id: id })
            });
            const data = await response.json();
            
            if (data.success) {
                Swal.fire('Deleted!', data.message, 'success');
                loadItems();
            } else {
                Swal.fire('Error', data.message, 'error');
            }
        } catch (error) {
            Swal.fire('Error', 'An error occurred', 'error');
        }
    }
};

// ==================== DELETE LPO ====================
// Updated DELETE LPO function
window.deleteLPO = async (id) => {
    const result = await Swal.fire({
        title: 'Delete LPO?',
        text: "This action will permanently delete this LPO and cannot be undone!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete permanently!'
    });
    
    if (result.isConfirmed) {
        try {
            const response = await fetch('/feesystem/api/stores/delete_lpo.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ school_id: schoolId, lpo_id: id })
            });
            const data = await response.json();
            
            if (data.success) {
                Swal.fire('Deleted!', data.message, 'success');
                loadLPOs();
                // Refresh the next number in the form if it's the current one
                await getNextLPONumber();
            } else {
                Swal.fire('Error', data.message, 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            Swal.fire('Error', 'An error occurred while deleting', 'error');
        }
    }
};

// ==================== DELETE GRN ====================
window.deleteGRN = async (id) => {
    const result = await Swal.fire({
        title: 'Delete GRN?',
        text: "This will reverse stock quantities!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!'
    });
    
    if (result.isConfirmed) {
        try {
            const response = await fetch('/feesystem/api/stores/delete_grn.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ school_id: schoolId, grn_id: id, user_id: userId })
            });
            const data = await response.json();
            
            if (data.success) {
                Swal.fire('Deleted!', data.message, 'success');
                loadGRNs();
                loadLPOs();
                loadItems();
            } else {
                Swal.fire('Error', data.message, 'error');
            }
        } catch (error) {
            Swal.fire('Error', 'An error occurred', 'error');
        }
    }
};


// ==================== LOAD ISSUANCES ====================
// ==================== LOAD ISSUANCES ====================
async function loadIssuances() {
    try {
        const response = await fetch('/feesystem/api/stores/get_issuances.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ school_id: schoolId })
        });
        const data = await response.json();
        const tbody = document.getElementById('issuancesListBody');
        if (data.success && data.issuances && data.issuances.length > 0) {
            tbody.innerHTML = data.issuances.map(iss => {
                const statusColors = {
                    'issued': 'bg-green-100 text-green-800',
                    'pending': 'bg-yellow-100 text-yellow-800',
                    'approved': 'bg-blue-100 text-blue-800',
                    'cancelled': 'bg-red-100 text-red-800'
                };
                const statusColor = statusColors[iss.status] || 'bg-gray-100 text-gray-800';
                
                return `<tr class="border-b dark:border-gray-700">
                    <td class="px-3 py-2" data-label="Issue No.">${escapeHtml(iss.issuance_number)}</td>
                    <td class="px-3 py-2" data-label="Date">${iss.issuance_date}</td>
                    <td class="px-3 py-2" data-label="Department">${escapeHtml(iss.department_name || '-')}</td>
                    <td class="px-3 py-2 text-right" data-label="Items">${iss.item_count || 0}</td>
                    <td class="px-3 py-2 text-right" data-label="Total Qty">${parseFloat(iss.total_quantity || 0).toLocaleString()}</td>
                    <td class="px-3 py-2 text-center" data-label="Actions">
                        <button onclick="viewIssuance(${iss.id})" class="text-blue-500 hover:text-blue-700 mr-2" title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button onclick="printIssuance(${iss.id})" class="text-green-500 hover:text-green-700" title="Print">
                            <i class="fas fa-print"></i>
                        </button>
                    </td>
                </tr>`;
            }).join('');
            
            // Update summary stats
            if (data.summary) {
                document.getElementById('totalIssuances').innerText = data.summary.total_issuances || 0;
                document.getElementById('totalQuantityIssued').innerText = parseFloat(data.summary.total_quantity || 0).toLocaleString();
                document.getElementById('totalIssuanceValue').innerHTML = `KES ${parseFloat(data.summary.total_value || 0).toLocaleString()}`;
            }
        } else { 
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-8 text-gray-500">No issuances found</td></tr>';
        }
    } catch (error) { 
        console.error('Error loading issuances:', error);
        document.getElementById('issuancesListBody').innerHTML = '<tr><td colspan="6" class="text-center py-8 text-gray-500">Error loading issuances</td></tr>';
    }
}
    // ==================== PRINT ISSUANCE ====================
window.printIssuance = async (id) => {
    try {
        const response = await fetch('/feesystem/api/stores/get_issuances.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ school_id: schoolId, issuance_id: id })
        });
        const data = await response.json();
        
        if (data.success && data.issuance) {
            const issuance = data.issuance;
            const items = issuance.items || [];
            
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Issuance Note - ${issuance.issuance_number}</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 40px; }
                        .header { text-align: center; margin-bottom: 30px; }
                        .title { font-size: 24px; font-weight: bold; }
                        .details { margin-bottom: 20px; }
                        .details table { width: 100%; }
                        .details td { padding: 5px; }
                        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                        th { background-color: #f2f2f2; }
                        .text-right { text-align: right; }
                        .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; }
                        @media print {
                            body { margin: 0; }
                            .no-print { display: none; }
                        }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <div class="title">ISSUANCE NOTE</div>
                        <p>${issuance.issuance_number}</p>
                    </div>
                    
                    <div class="details">
                        <table>
                            <tr><td width="30%"><strong>Issue Date:</strong></td><td>${issuance.issuance_date}</td></tr>
                            <tr><td><strong>Department:</strong></td><td>${escapeHtml(issuance.department_name || 'N/A')}</td></tr>
                            <tr><td><strong>Requested By:</strong></td><td>${escapeHtml(issuance.requested_by || 'N/A')}</td></tr>
                            <tr><td><strong>Status:</strong></td><td>${issuance.status.toUpperCase()}</td></tr>
                            <tr><td><strong>Remarks:</strong></td><td>${escapeHtml(issuance.remarks || 'No remarks')}</td></tr>
                        </table>
                    </div>
                    
                    <table>
                        <thead>
                            <tr><th>Item Code</th><th>Item Name</th><th class="text-right">Quantity</th><th class="text-right">Unit Price</th><th class="text-right">Total</th></tr>
                        </thead>
                        <tbody>
                            ${items.map(item => `
                                <tr>
                                    <td>${escapeHtml(item.item_code)}</td>
                                    <td>${escapeHtml(item.item_name)}</td>
                                    <td class="text-right">${parseFloat(item.quantity).toLocaleString()} ${escapeHtml(item.unit_of_measure)}</td>
                                    <td class="text-right">KES ${parseFloat(item.unit_price).toLocaleString()}</td>
                                    <td class="text-right">KES ${parseFloat(item.total).toLocaleString()}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                        <tfoot>
                            <tr><td colspan="4" class="text-right"><strong>Total:</strong></td><td class="text-right"><strong>KES ${parseFloat(issuance.total_value || 0).toLocaleString()}</strong></td></tr>
                        </tfoot>
                    </table>
                    
                    <div class="footer">
                        <p>Generated on ${new Date().toLocaleString()}</p>
                    </div>
                    
                    <div class="no-print" style="text-align: center; margin-top: 20px;">
                        <button onclick="window.print()" style="padding: 10px 20px;">Print</button>
                        <button onclick="window.close()" style="padding: 10px 20px;">Close</button>
                    </div>
                </body>
                </html>
            `);
            printWindow.document.close();
        } else {
            Swal.fire('Error', 'Failed to load issuance details for printing', 'error');
        }
    } catch (error) {
        console.error('Error printing issuance:', error);
        Swal.fire('Error', 'An error occurred while printing', 'error');
    }
};
// ==================== LOAD LPOS ====================
async function loadLPOs() {
    try {
        const response = await fetch('/feesystem/api/stores/get_lpos.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ school_id: schoolId })
        });
        const data = await response.json();
        const tbody = document.getElementById('lposListBody');
        if (data.success && data.lpos && data.lpos.length > 0) {
            tbody.innerHTML = data.lpos.map(lpo => {
                let statusClass = lpo.status === 'completed' ? 'bg-green-100 text-green-800' : (lpo.status === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800');
                return `<tr class="border-b dark:border-gray-700">
                    <td class="px-3 py-2" data-label="LPO No.">${escapeHtml(lpo.lpo_number)}</td>
                    <td class="px-3 py-2" data-label="Date">${lpo.lpo_date}</td>
                    <td class="px-3 py-2" data-label="Supplier">${escapeHtml(lpo.supplier_name)}</td>
                    <td class="px-3 py-2 text-right" data-label="Amount">KES ${parseFloat(lpo.total_amount).toLocaleString()}</td>
                    <td class="px-3 py-2" data-label="Status"><span class="px-2 py-1 rounded-full text-xs ${statusClass}">${lpo.status}</span></td>
                    
<td class="px-3 py-2 text-center" data-label="Actions">
    <button onclick="viewLPO(${lpo.id})" class="text-blue-500 hover:text-blue-700 mr-2" title="View"><i class="fas fa-eye"></i></button>
    <button onclick="deleteLPO(${lpo.id})" class="text-red-500 hover:text-red-700" title="Cancel"><i class="fas fa-trash"></i></button>
</td>
                </tr>`;
            }).join('');
        } else { tbody.innerHTML = '<tr><td colspan="6" class="text-center py-8 text-gray-500">No LPOs found</td></tr>'; }
        
        // Update LPO dropdown for GRN
        let lpoOptions = '<option value="">Select LPO...</option>';
        if (data.success && data.lpos) { data.lpos.forEach(lpo => { lpoOptions += `<option value="${lpo.id}">${escapeHtml(lpo.lpo_number)} - ${escapeHtml(lpo.supplier_name)}</option>`; }); }
        document.getElementById('grn_lpo_id').innerHTML = lpoOptions;
    } catch (error) { console.error('Error loading LPOs:', error); }
}

// ==================== LOAD GRNS ====================
async function loadGRNs() {
    try {
        const response = await fetch('/feesystem/api/stores/get_grns.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ school_id: schoolId })
        });
        const data = await response.json();
        const tbody = document.getElementById('grnsListBody');
        if (data.success && data.grns && data.grns.length > 0) {
            tbody.innerHTML = data.grns.map(grn => `<tr class="border-b dark:border-gray-700">
                <td class="px-3 py-2" data-label="GRN No.">${escapeHtml(grn.grn_number)}</td>
                <td class="px-3 py-2" data-label="Date">${grn.grn_date}</td>
                <td class="px-3 py-2" data-label="LPO No.">${escapeHtml(grn.lpo_number)}</td>
                <td class="px-3 py-2" data-label="Supplier">${escapeHtml(grn.supplier_name)}</td>
                // In loadGRNs function, update the actions column:
<td class="px-3 py-2 text-center" data-label="Actions">
    <button onclick="viewGRN(${grn.id})" class="text-blue-500 hover:text-blue-700 mr-2" title="View"><i class="fas fa-eye"></i></button>
    <button onclick="deleteGRN(${grn.id})" class="text-red-500 hover:text-red-700" title="Delete"><i class="fas fa-trash"></i></button>
</td>
            </tr>`).join('');
        } else { tbody.innerHTML = '<tr><td colspan="5" class="text-center py-8 text-gray-500">No GRNs found</td></tr>'; }
    } catch (error) { console.error('Error loading GRNs:', error); }
}

// ==================== SAVE ISSUANCE ====================
document.getElementById('addIssuanceItemBtn').addEventListener('click', () => {
    const container = document.getElementById('issuanceItemsContainer');
    const index = container.children.length;
    const newItem = document.createElement('div');
    newItem.className = 'issuance-item border border-gray-200 dark:border-gray-700 rounded-lg p-3 mt-2';
    newItem.innerHTML = `<div class="grid grid-cols-2 gap-2 mb-2"><div><label class="text-xs text-gray-500">Store Item</label><select class="item_id w-full px-2 py-1 text-sm border rounded dark:bg-gray-700"><option value="">Select item...</option></select></div><div><label class="text-xs text-gray-500">Quantity</label><input type="number" class="item_quantity w-full px-2 py-1 text-sm border rounded" placeholder="Qty" step="1" min="1"></div></div><div class="flex justify-end"><button type="button" class="removeIssuanceItemBtn text-red-500 text-sm"><i class="fas fa-trash"></i> Remove</button></div>`;
    container.appendChild(newItem);
    newItem.querySelector('.removeIssuanceItemBtn').addEventListener('click', () => newItem.remove());
    loadItemsForSelect(newItem.querySelector('.item_id'));
});
async function loadItemsForSelect(selectElement) {
    try {
        const response = await fetch('/feesystem/api/stores/get_items.php', { 
            method: 'POST', 
            headers: { 'Content-Type': 'application/json' }, 
            body: JSON.stringify({ school_id: schoolId }) 
        });
        const data = await response.json();
        if (data.success && data.items) {
            let options = '<option value="">Select item...</option>';
            data.items.forEach(item => { 
                options += `<option value="${item.id}" data-price="${item.unit_price}" data-stock="${item.current_stock}">${escapeHtml(item.item_name)} (Stock: ${parseFloat(item.current_stock).toLocaleString()} ${escapeHtml(item.unit_of_measure)})</option>`;
            });
            selectElement.innerHTML = options;
        }
    } catch (error) { 
        console.error('Error loading items for select:', error);
        selectElement.innerHTML = '<option value="">Error loading items</option>';
    }
}

// ==================== SAVE ISSUANCE ====================
document.getElementById('issuanceForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    // Show loading
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
    submitBtn.disabled = true;
    
    const items = [];
    document.querySelectorAll('.issuance-item').forEach(item => {
        const itemId = item.querySelector('.item_id').value;
        const quantity = item.querySelector('.item_quantity').value;
        if (itemId && quantity && parseFloat(quantity) > 0) { 
            items.push({ item_id: itemId, quantity: parseFloat(quantity) }); 
        }
    });
    
    if (items.length === 0) { 
        Swal.fire('Error', 'Please add at least one item', 'error');
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
        return; 
    }
    
    const formData = { 
        school_id: schoolId, 
        user_id: userId, 
        issuance_date: document.getElementById('issue_date').value, 
        department_id: document.getElementById('department_id').value, 
        requested_by: document.getElementById('requested_by').value, 
        items: items, 
        remarks: document.getElementById('issuance_remarks').value 
    };
    
    try {
        const response = await fetch('/feesystem/api/stores/save_issuance.php', { 
            method: 'POST', 
            headers: { 'Content-Type': 'application/json' }, 
            body: JSON.stringify(formData) 
        });
        const data = await response.json();
        
        if (data.success) { 
            Swal.fire('Success', data.message, 'success');
            
            // Update the issuance number display with the generated number
            document.getElementById('issue_no').value = data.issuance_number;
            
            // Reset form but keep the generated number
            document.getElementById('requested_by').value = '';
            document.getElementById('issuance_remarks').value = '';
            document.getElementById('issuanceItemsContainer').innerHTML = '';
            
            // Add a new empty item row
            addEmptyIssuanceItem();
            
            // Refresh the issuances list
            loadIssuances();
            
            // Refresh items to update stock levels
            loadItems();
        } else { 
            Swal.fire('Error', data.message, 'error'); 
        }
    } catch (error) { 
        console.error('Error:', error);
        Swal.fire('Error', 'An error occurred', 'error'); 
    } finally {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }
});

// Function to add empty issuance item row
function addEmptyIssuanceItem() {
    const container = document.getElementById('issuanceItemsContainer');
    const newItem = document.createElement('div');
    newItem.className = 'issuance-item border border-gray-200 dark:border-gray-700 rounded-lg p-3';
    newItem.innerHTML = `
        <div class="grid grid-cols-2 gap-2 mb-2">
            <div>
                <label class="text-xs text-gray-500">Store Item</label>
                <select class="item_id w-full px-2 py-1 text-sm border rounded dark:bg-gray-700">
                    <option value="">Select item...</option>
                </select>
            </div>
            <div>
                <label class="text-xs text-gray-500">Quantity</label>
                <input type="number" class="item_quantity w-full px-2 py-1 text-sm border rounded" placeholder="Qty" step="1" min="1">
            </div>
        </div>
        <div class="flex justify-end">
            <button type="button" class="removeIssuanceItemBtn text-red-500 text-sm"><i class="fas fa-trash"></i> Remove</button>
        </div>
    `;
    container.appendChild(newItem);
    
    // Load items into the select
    loadItemsForSelect(newItem.querySelector('.item_id'));
    
    // Add remove functionality
    newItem.querySelector('.removeIssuanceItemBtn').addEventListener('click', () => newItem.remove());
}

// Update the add button to use the function
document.getElementById('addIssuanceItemBtn').addEventListener('click', () => {
    addEmptyIssuanceItem();
});

// Initialize with one empty item row
function initIssuanceItems() {
    const container = document.getElementById('issuanceItemsContainer');
    container.innerHTML = ''; // Clear any existing
    addEmptyIssuanceItem();
}

// Call this in DOMContentLoaded
// Add this line in your DOMContentLoaded:
// initIssuanceItems();

// ==================== SAVE LPO ====================
document.getElementById('addLpoItemBtn').addEventListener('click', () => {
    const container = document.getElementById('lpoItemsContainer');
    const newItem = document.createElement('div');
    newItem.className = 'lpo-item border border-gray-200 dark:border-gray-700 rounded-lg p-3 mt-2';
    newItem.innerHTML = `<div class="grid grid-cols-3 gap-2 mb-2"><div><label class="text-xs text-gray-500">Store Item</label><select class="lpo_item_id w-full px-2 py-1 text-sm border rounded dark:bg-gray-700"><option value="">Select item...</option></select></div><div><label class="text-xs text-gray-500">Quantity</label><input type="number" class="lpo_item_qty w-full px-2 py-1 text-sm border rounded" placeholder="Qty" step="1" min="1"></div><div><label class="text-xs text-gray-500">Unit Price</label><input type="number" class="lpo_item_price w-full px-2 py-1 text-sm border rounded" placeholder="Price" step="0.01"></div></div><div class="flex justify-end"><button type="button" class="removeLpoItemBtn text-red-500 text-sm"><i class="fas fa-trash"></i> Remove</button></div>`;
    container.appendChild(newItem);
    newItem.querySelector('.lpo_item_qty').addEventListener('input', calculateLpoTotal);
    newItem.querySelector('.lpo_item_price').addEventListener('input', calculateLpoTotal);
    newItem.querySelector('.removeLpoItemBtn').addEventListener('click', () => { newItem.remove(); calculateLpoTotal(); });
    loadItemsForSelectLPO(newItem.querySelector('.lpo_item_id'));
});
async function loadItemsForSelectLPO(selectElement) {
    try {
        const response = await fetch('/feesystem/api/stores/get_items.php', { 
            method: 'POST', 
            headers: { 'Content-Type': 'application/json' }, 
            body: JSON.stringify({ school_id: schoolId }) 
        });
        const data = await response.json();
        if (data.success && data.items) {
            let options = '<option value="">Select item...</option>';
            data.items.forEach(item => { 
                options += `<option value="${item.id}" data-price="${item.unit_price}" data-stock="${item.current_stock}">${escapeHtml(item.item_name)} - ${escapeHtml(item.unit_of_measure)}</option>`;
            });
            selectElement.innerHTML = options;
            
            // Add change event listener to auto-fill price
            selectElement.addEventListener('change', function() { 
                const selectedOption = this.options[this.selectedIndex];
                const price = selectedOption?.dataset?.price; 
                if (price) { 
                    const priceInput = this.closest('.lpo-item')?.querySelector('.lpo_item_price');
                    if (priceInput) {
                        priceInput.value = parseFloat(price).toFixed(2);
                        calculateLpoTotal();
                    }
                } 
            });
        }
    } catch (error) { 
        console.error('Error loading items for LPO:', error);
        selectElement.innerHTML = '<option value="">Error loading items</option>';
    }
}
    async function loadAllItemsForSelect(selectElement, showStock = false) {
    try {
        const response = await fetch('/feesystem/api/stores/get_items.php', { 
            method: 'POST', 
            headers: { 'Content-Type': 'application/json' }, 
            body: JSON.stringify({ school_id: schoolId }) 
        });
        const data = await response.json();
        if (data.success && data.items) {
            let options = '<option value="">Select item...</option>';
            data.items.forEach(item => { 
                if (showStock) {
                    options += `<option value="${item.id}" data-price="${item.unit_price}" data-stock="${item.current_stock}">${escapeHtml(item.item_name)} (${parseFloat(item.current_stock).toLocaleString()} ${escapeHtml(item.unit_of_measure)} in stock)</option>`;
                } else {
                    options += `<option value="${item.id}" data-price="${item.unit_price}">${escapeHtml(item.item_name)} - ${escapeHtml(item.unit_of_measure)}</option>`;
                }
            });
            selectElement.innerHTML = options;
        }
    } catch (error) { 
        console.error('Error loading items:', error);
        selectElement.innerHTML = '<option value="">Error loading items</option>';
    }
}
function calculateLpoTotal() { let total = 0; document.querySelectorAll('.lpo-item').forEach(item => { const qty = parseFloat(item.querySelector('.lpo_item_qty')?.value) || 0; const price = parseFloat(item.querySelector('.lpo_item_price')?.value) || 0; total += qty * price; }); document.getElementById('lpoTotal').innerHTML = `KES ${total.toLocaleString()}`; }

// Updated SAVE LPO with proper form reset
document.getElementById('lpoForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    // Show loading
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Creating...';
    submitBtn.disabled = true;
    
    const items = [];
    document.querySelectorAll('.lpo-item').forEach(item => {
        const itemId = item.querySelector('.lpo_item_id').value;
        const quantity = item.querySelector('.lpo_item_qty').value;
        const price = item.querySelector('.lpo_item_price').value;
        if (itemId && quantity && price) { 
            items.push({ 
                item_id: itemId, 
                quantity: parseFloat(quantity), 
                unit_price: parseFloat(price) 
            }); 
        }
    });
    
    if (items.length === 0) { 
        Swal.fire('Error', 'Please add at least one item', 'error');
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
        return; 
    }
    
    const formData = { 
        school_id: schoolId, 
        user_id: userId, 
        supplier_id: document.getElementById('lpo_supplier_id').value, 
        lpo_date: document.getElementById('lpo_date').value, 
        delivery_date: document.getElementById('lpo_delivery_date').value, 
        items: items, 
        notes: document.getElementById('lpo_notes').value 
    };
    
    try {
        const response = await fetch('/feesystem/api/stores/save_lpo.php', { 
            method: 'POST', 
            headers: { 'Content-Type': 'application/json' }, 
            body: JSON.stringify(formData) 
        });
        const data = await response.json();
        
        if (data.success) { 
            Swal.fire('Success', data.message, 'success');
            
            // Clear the items container
            document.getElementById('lpoItemsContainer').innerHTML = '';
            
            // Add one empty item row
            addEmptyLpoItem();
            
            // Reset other form fields (keep date as today)
            document.getElementById('lpo_delivery_date').value = '';
            document.getElementById('lpo_notes').value = '';
            
            // Reset supplier dropdown to default
            document.getElementById('lpo_supplier_id').value = '';
            
            // Generate and set the next LPO number
            await getNextLPONumber();
            
            // Reset total display
            document.getElementById('lpoTotal').innerHTML = 'KES 0.00';
            
            // Refresh the LPOs list
            loadLPOs();
        } else { 
            Swal.fire('Error', data.message, 'error'); 
        }
    } catch (error) { 
        console.error('Error:', error);
        Swal.fire('Error', 'An error occurred', 'error'); 
    } finally {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }
});
// ==================== SAVE GRN ====================
document.getElementById('grn_lpo_id').addEventListener('change', async (e) => {
    if (e.target.value) {
        const lpoId = e.target.value;
        try {
            const response = await fetch('/feesystem/api/stores/get_lpo_items.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ school_id: schoolId, lpo_id: lpoId }) });
            const data = await response.json();
            if (data.success && data.items) {
                let html = '';
                data.items.forEach(item => { html += `<div class="border rounded-lg p-3"><div class="flex justify-between items-center"><div><strong>${escapeHtml(item.item_name)}</strong><br><span class="text-sm text-gray-500">Ordered: ${item.quantity}</span><input type="hidden" class="lpo_item_id" value="${item.id}"></div><div><label class="text-sm">Received Qty:</label><input type="number" class="received_qty w-24 px-2 py-1 border rounded ml-2" value="${item.quantity}" min="0" max="${item.quantity}" step="1"></div></div></div>`; });
                document.getElementById('grnItemsContainer').innerHTML = html;
            }
        } catch (error) { console.error('Error loading LPO items:', error); }
    } else { document.getElementById('grnItemsContainer').innerHTML = '<div class="text-center text-gray-500 py-4">Select an LPO to load items</div>'; }
});

document.getElementById('grnForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const items = [];
    document.querySelectorAll('#grnItemsContainer .border').forEach(container => {
        const lpoItemId = container.querySelector('.lpo_item_id')?.value;
        const receivedQty = container.querySelector('.received_qty')?.value;
        if (lpoItemId && receivedQty && parseFloat(receivedQty) > 0) { items.push({ lpo_item_id: lpoItemId, received_quantity: parseFloat(receivedQty) }); }
    });
    if (items.length === 0) { Swal.fire('Error', 'Please add at least one received item', 'error'); return; }
    const formData = { school_id: schoolId, user_id: userId, lpo_id: document.getElementById('grn_lpo_id').value, grn_date: document.getElementById('grn_date').value, delivery_note: document.getElementById('grn_delivery_note').value, received_by: document.getElementById('grn_received_by').value, items: items, notes: document.getElementById('grn_notes').value };
    try {
        const response = await fetch('/feesystem/api/stores/save_grn.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(formData) });
        const data = await response.json();
        if (data.success) { Swal.fire('Success', data.message, 'success'); document.getElementById('grnForm').reset(); document.getElementById('grn_no').value = generateRefNumber('GRN'); document.getElementById('grnItemsContainer').innerHTML = '<div class="text-center text-gray-500 py-4">Select an LPO to load items</div>'; loadGRNs(); loadLPOs(); loadItems(); }
        else { Swal.fire('Error', data.message, 'error'); }
    } catch (error) { Swal.fire('Error', 'An error occurred', 'error'); }
});

// ==================== VIEW FUNCTIONS ====================
// ==================== VIEW ISSUANCE DETAILS ====================
window.viewIssuance = async (id) => {
    // Show loading state
    document.getElementById('issuanceDetailsContent').innerHTML = `
        <div class="text-center py-8">
            <div class="loading-spinner mx-auto mb-4"></div>
            <p class="text-gray-500">Loading issuance details...</p>
        </div>
    `;
    document.getElementById('viewIssuanceModal').classList.remove('hidden');
    lockBodyScroll();
    
    try {
        const response = await fetch('/feesystem/api/stores/get_issuances.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ school_id: schoolId, issuance_id: id })
        });
        const data = await response.json();
        
        if (data.success && data.issuance) {
            const issuance = data.issuance;
            const items = issuance.items || [];
            
            let itemsHtml = '';
            if (items.length > 0) {
                itemsHtml = `
                    <table class="w-full text-sm">
                        <thead class="bg-gray-100 dark:bg-gray-700">
                            <tr>
                                <th class="px-3 py-2 text-left">Item Code</th>
                                <th class="px-3 py-2 text-left">Item Name</th>
                                <th class="px-3 py-2 text-right">Quantity</th>
                                <th class="px-3 py-2 text-right">Unit Price</th>
                                <th class="px-3 py-2 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${items.map(item => `
                                <tr class="border-b dark:border-gray-700">
                                    <td class="px-3 py-2">${escapeHtml(item.item_code)}</td>
                                    <td class="px-3 py-2">${escapeHtml(item.item_name)}</td>
                                    <td class="px-3 py-2 text-right">${parseFloat(item.quantity).toLocaleString()} ${escapeHtml(item.unit_of_measure)}</td>
                                    <td class="px-3 py-2 text-right">KES ${parseFloat(item.unit_price).toLocaleString()}</td>
                                    <td class="px-3 py-2 text-right">KES ${parseFloat(item.total).toLocaleString()}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                        <tfoot class="bg-gray-50 dark:bg-gray-700 font-bold">
                            <tr>
                                <td colspan="4" class="px-3 py-2 text-right">Total:</td>
                                <td class="px-3 py-2 text-right">KES ${parseFloat(issuance.total_value || 0).toLocaleString()}</td>
                            </tr>
                        </tfoot>
                    </table>
                `;
            } else {
                itemsHtml = '<div class="text-center py-4 text-gray-500">No items found</div>';
            }
            
            const statusColors = {
                'issued': 'bg-green-100 text-green-800',
                'pending': 'bg-yellow-100 text-yellow-800',
                'approved': 'bg-blue-100 text-blue-800',
                'cancelled': 'bg-red-100 text-red-800'
            };
            const statusColor = statusColors[issuance.status] || 'bg-gray-100 text-gray-800';
            
            const html = `
                <div class="space-y-4">
                    <!-- Header Information -->
                    <div class="grid grid-cols-2 gap-4 pb-4 border-b dark:border-gray-700">
                        <div>
                            <p class="text-sm text-gray-500">Issuance Number</p>
                            <p class="font-semibold">${escapeHtml(issuance.issuance_number)}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Issue Date</p>
                            <p class="font-semibold">${issuance.issuance_date}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Department</p>
                            <p class="font-semibold">${escapeHtml(issuance.department_name || 'N/A')}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Status</p>
                            <p><span class="px-2 py-1 rounded-full text-xs ${statusColor}">${issuance.status.toUpperCase()}</span></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Requested By</p>
                            <p class="font-semibold">${escapeHtml(issuance.requested_by || 'N/A')}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Created By</p>
                            <p class="font-semibold">${escapeHtml(issuance.created_by_name || 'System')}</p>
                        </div>
                        <div class="col-span-2">
                            <p class="text-sm text-gray-500">Remarks</p>
                            <p class="text-gray-700 dark:text-gray-300">${escapeHtml(issuance.remarks) || 'No remarks'}</p>
                        </div>
                    </div>
                    
                    <!-- Items Section -->
                    <div>
                        <h4 class="font-semibold mb-2">Items Issued</h4>
                        <div class="overflow-x-auto">
                            ${itemsHtml}
                        </div>
                    </div>
                    
                    <!-- Summary Section -->
                    <div class="pt-4 border-t dark:border-gray-700">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-gray-500">Total Items</p>
                                <p class="font-semibold text-lg">${items.length}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Total Quantity</p>
                                <p class="font-semibold text-lg">${parseFloat(issuance.total_quantity || 0).toLocaleString()}</p>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('issuanceDetailsContent').innerHTML = html;
        } else {
            document.getElementById('issuanceDetailsContent').innerHTML = `
                <div class="text-center py-8 text-red-500">
                    <i class="fas fa-exclamation-circle text-4xl mb-2"></i>
                    <p>Failed to load issuance details</p>
                    <p class="text-sm">${data.message || 'Please try again'}</p>
                </div>
            `;
        }
    } catch (error) {
        console.error('Error loading issuance details:', error);
        document.getElementById('issuanceDetailsContent').innerHTML = `
            <div class="text-center py-8 text-red-500">
                <i class="fas fa-exclamation-circle text-4xl mb-2"></i>
                <p>Error loading issuance details</p>
                <p class="text-sm">Please try again</p>
            </div>
        `;
    }
};

document.querySelectorAll('.closeViewModal').forEach(btn => btn.addEventListener('click', () => { document.getElementById('viewIssuanceModal').classList.add('hidden'); unlockBodyScroll(); }));
document.getElementById('viewIssuanceModal').addEventListener('click', (e) => { if (e.target === document.getElementById('viewIssuanceModal')) { document.getElementById('viewIssuanceModal').classList.add('hidden'); unlockBodyScroll(); } });
document.addEventListener('keydown', (e) => { if (e.key === 'Escape') { if (!document.getElementById('itemModal').classList.contains('hidden')) { document.getElementById('itemModal').classList.add('hidden'); unlockBodyScroll(); } if (!document.getElementById('viewIssuanceModal').classList.contains('hidden')) { document.getElementById('viewIssuanceModal').classList.add('hidden'); unlockBodyScroll(); } } });
// ==================== GET NEXT ISSUANCE NUMBER ====================
async function getNextIssuanceNumber() {
    try {
        const response = await fetch('/feesystem/api/stores/get_next_issuance_number.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ school_id: schoolId })
        });
        const data = await response.json();
        if (data.success) {
            document.getElementById('issue_no').value = data.issuance_number;
        } else {
            // Fallback to a temporary number
            const year = new Date().getFullYear();
            document.getElementById('issue_no').value = `ISS-${year}-001`;
        }
    } catch (error) {
        console.error('Error getting next issuance number:', error);
        const year = new Date().getFullYear();
        document.getElementById('issue_no').value = `ISS-${year}-001`;
    }
}

// ==================== GET NEXT LPO NUMBER ====================
async function getNextLPONumber() {
    try {
        const response = await fetch('/feesystem/api/stores/get_next_lpo_number.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ school_id: schoolId })
        });
        const data = await response.json();
        if (data.success) {
            document.getElementById('lpo_no').value = data.lpo_number;
        } else {
            const year = new Date().getFullYear();
            document.getElementById('lpo_no').value = `LPO-${year}-001`;
        }
    } catch (error) {
        console.error('Error getting next LPO number:', error);
        const year = new Date().getFullYear();
        document.getElementById('lpo_no').value = `LPO-${year}-001`;
    }
}

// ==================== GET NEXT GRN NUMBER ====================
async function getNextGRNNumber() {
    try {
        const response = await fetch('/feesystem/api/stores/get_next_grn_number.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ school_id: schoolId })
        });
        const data = await response.json();
        if (data.success) {
            document.getElementById('grn_no').value = data.grn_number;
        } else {
            const year = new Date().getFullYear();
            document.getElementById('grn_no').value = `GRN-${year}-001`;
        }
    } catch (error) {
        console.error('Error getting next GRN number:', error);
        const year = new Date().getFullYear();
        document.getElementById('grn_no').value = `GRN-${year}-001`;
    }
}

// ==================== GET NEXT ITEM CODE ====================
async function getNextItemCode() {
    try {
        const response = await fetch('/feesystem/api/stores/get_next_item_code.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ school_id: schoolId })
        });
        const data = await response.json();
        if (data.success) {
            document.getElementById('item_code').value = data.item_code;
        } else {
            const year = new Date().getFullYear();
            document.getElementById('item_code').value = `ITM-${year}-001`;
        }
    } catch (error) {
        console.error('Error getting next item code:', error);
        const year = new Date().getFullYear();
        document.getElementById('item_code').value = `ITM-${year}-001`;
    }
}
// ==================== INITIALIZE ====================
document.addEventListener('DOMContentLoaded', async () => {
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('issue_date').value = today;
    document.getElementById('lpo_date').value = today;
    document.getElementById('grn_date').value = today;
    
    // Get next numbers from server
    await Promise.all([
        getNextIssuanceNumber(),
        getNextLPONumber(),
        getNextGRNNumber(),
        getNextItemCode()
    ]);
    
    loadCategories();
    loadDepartments();
    loadSuppliers();
    loadItems();
    loadIssuances();
    loadLPOs();
    loadGRNs();
    
    // Initialize issuance items
    initIssuanceItems();
    
    // Initialize LPO items with one empty row
    const lpoContainer = document.getElementById('lpoItemsContainer');
    if (lpoContainer.children.length === 0) {
        addEmptyLpoItem();
    }
    
    // Filter event listeners
    document.getElementById('item_search')?.addEventListener('input', () => loadItems());
    document.getElementById('item_category_filter')?.addEventListener('change', () => loadItems());
    document.getElementById('stock_status_filter')?.addEventListener('change', () => loadItems());
});

function addEmptyLpoItem() {
    const container = document.getElementById('lpoItemsContainer');
    const newItem = document.createElement('div');
    newItem.className = 'lpo-item border border-gray-200 dark:border-gray-700 rounded-lg p-3 mt-2';
    newItem.innerHTML = `
        <div class="grid grid-cols-3 gap-2 mb-2">
            <div>
                <label class="text-xs text-gray-500">Store Item</label>
                <select class="lpo_item_id w-full px-2 py-1 text-sm border rounded dark:bg-gray-700">
                    <option value="">Select item...</option>
                </select>
            </div>
            <div>
                <label class="text-xs text-gray-500">Quantity</label>
                <input type="number" class="lpo_item_qty w-full px-2 py-1 text-sm border rounded" placeholder="Qty" step="1" min="1">
            </div>
            <div>
                <label class="text-xs text-gray-500">Unit Price</label>
                <input type="number" class="lpo_item_price w-full px-2 py-1 text-sm border rounded" placeholder="Price" step="0.01">
            </div>
        </div>
        <div class="flex justify-end">
            <button type="button" class="removeLpoItemBtn text-red-500 text-sm">
                <i class="fas fa-trash"></i> Remove
            </button>
        </div>
    `;
    container.appendChild(newItem);
    
    loadItemsForSelectLPO(newItem.querySelector('.lpo_item_id'));
    
    const qtyInput = newItem.querySelector('.lpo_item_qty');
    const priceInput = newItem.querySelector('.lpo_item_price');
    
    qtyInput.addEventListener('input', calculateLpoTotal);
    priceInput.addEventListener('input', calculateLpoTotal);
    
    newItem.querySelector('.removeLpoItemBtn').addEventListener('click', () => { 
        newItem.remove(); 
        calculateLpoTotal(); 
    });
}
</script>

<?php include_once('../../includes/footer.php'); ?>