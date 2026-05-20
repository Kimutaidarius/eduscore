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
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-overlay.hidden {
    display: none;
}

.modal-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    z-index: 10000;
    max-height: 90vh;
    overflow-y: auto;
}

.dark .modal-container {
    background: #1f2937;
}

.modal-overlay .modal-container {
    position: relative;
    margin: 20px;
}

.delete-invoice-btn:hover {
    background-color: #dc2626 !important;
    transform: scale(1.05);
    transition: all 0.2s ease;
}

.simple-debit-form {
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
}

.dark .simple-debit-form {
    background: #1f2937;
    border-color: #374151;
}
</style>

<main class="main-content flex-grow flex flex-col">
  <header class="bg-white shadow-sm dark:bg-gray-800 dark:border-gray-700">
    <div class="px-4 py-3 flex justify-between items-center">
      <div class="flex items-center">
        <button id="mobile-toggle" class="mr-4 text-gray-500 hover:text-indigo-600 focus:outline-none lg:hidden">
          <i class="fas fa-bars text-xl"></i>
        </button>
        <h1 id="page-title" class="text-xl font-semibold text-gray-800 dark:text-white">Invoice Management</h1>
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
    <div class="flex space-x-2 mb-6 border-b border-gray-200 dark:border-gray-700">
      <button id="debitsTab" class="tab-button active px-6 py-3 text-sm font-medium text-indigo-600 border-b-2 border-indigo-600 dark:text-indigo-400 dark:border-indigo-400">
        <i class="fas fa-arrow-down mr-2"></i>Debits
      </button>
      <button id="invoicesTab" class="tab-button px-6 py-3 text-sm font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
        <i class="fas fa-file-invoice mr-2"></i>Invoices
      </button>
      <button id="clientsTab" class="tab-button px-6 py-3 text-sm font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
        <i class="fas fa-users mr-2"></i>Clients
      </button>
    </div>

    <!-- Debits Tab -->
    <div id="debitsPanel" class="tab-panel">
      <!-- Filter Section -->
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Class</label>
            <select id="debitClassFilter" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500 bg-white dark:bg-gray-700">
              <option value="">All Classes</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Stream</label>
            <select id="debitStreamFilter" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500 bg-white dark:bg-gray-700">
              <option value="">All Streams</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Term</label>
            <select id="debitTermFilter" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500 bg-white dark:bg-gray-700">
              <option value="1">Term 1</option>
              <option value="2">Term 2</option>
              <option value="3">Term 3</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Year</label>
            <select id="debitYearFilter" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500 bg-white dark:bg-gray-700">
              <option value="2024">2024</option>
              <option value="2025">2025</option>
              <option value="2026">2026</option>
              <option value="2027">2027</option>
            </select>
          </div>
          <div class="flex items-end">
            <button id="addDebitBtn" class="w-full px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500">
              <i class="fas fa-plus mr-2"></i>Add Debit
            </button>
          </div>
        </div>
      </div>

      <!-- Bulk Debit Form -->
      <div id="bulkDebitForm" class="simple-debit-form hidden">
        <h4 class="text-md font-semibold text-gray-800 dark:text-white mb-4">
          <i class="fas fa-layer-group text-green-600 mr-2"></i>Bulk Debit for Selected Class
        </h4>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Vote Head *</label>
            <select id="bulkVoteHeadId" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500">
              <option value="">Select Vote Head</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Amount (KES) *</label>
            <input type="number" id="bulkAmount" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500" step="0.01" min="0" placeholder="Enter amount">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Description</label>
            <input type="text" id="bulkDescription" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500" placeholder="Optional description">
          </div>
        </div>
        <div class="mt-4 flex justify-end space-x-3">
          <button id="cancelBulkDebitBtn" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700">
            Cancel
          </button>
          <button id="confirmBulkDebitBtn" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
            <i class="fas fa-save mr-2"></i>Add Debit to All Students
          </button>
        </div>
      </div>

      <!-- Debits Table -->
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Admission Number</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Full Name</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Gender</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Term</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Amount (KES)</th>
              </tr>
            </thead>
            <tbody id="debitsTableBody" class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
              <tr>
                <td colspan="5" class="px-6 py-4 text-center text-gray-500">Select filters to view students and add debits</div>
              </tr>
            </tbody>
            <tfoot class="bg-gray-50 dark:bg-gray-700">
              <tr>
                <td colspan="4" class="px-6 py-3 text-right font-bold text-gray-700 dark:text-gray-300">TOTAL:</div>
                <td class="px-6 py-3 font-bold text-green-600 dark:text-green-400" id="debitsTotal">KES 0</div>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>

    <!-- Invoices Tab -->
    <div id="invoicesPanel" class="tab-panel hidden">
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Filter Invoices</label>
            <select id="invoiceStatusFilter" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500">
              <option value="all">All Invoices</option>
              <option value="paid">Paid</option>
              <option value="unpaid">Unpaid</option>
              <option value="overdue">Overdue</option>
            </select>
          </div>
          <div class="flex items-end">
            <button id="createInvoiceBtn" class="w-full px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <i class="fas fa-plus mr-2"></i>Create Invoice
            </button>
          </div>
        </div>
      </div>

      <div id="invoicesList" class="space-y-4">
        <div class="text-center text-gray-500 py-8">No invoices found</div>
      </div>
    </div>

    <!-- Clients Tab -->
    <div id="clientsPanel" class="tab-panel hidden">
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Search by Name</label>
            <input type="text" id="searchClientName" placeholder="Enter name..." class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">ID Number</label>
            <input type="text" id="searchClientId" placeholder="ID Number..." class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Contact</label>
            <input type="text" id="searchClientContact" placeholder="Phone number..." class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500">
          </div>
          <div class="flex items-end">
            <button id="searchClientBtn" class="w-full px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
              <i class="fas fa-search mr-2"></i>Search
            </button>
          </div>
          <div class="flex items-end">
            <button id="addClientBtn" class="w-full px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
              <i class="fas fa-user-plus mr-2"></i>Add Client
            </button>
          </div>
        </div>
      </div>

      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Name</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">ID Number</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Contact</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Phone</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Email</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody id="clientsTableBody" class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
              <tr><td colspan="6" class="px-6 py-4 text-center text-gray-500">Loading clients...</div></div>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</main>

<!-- Create Invoice Modal -->
<div id="invoiceModal" class="modal-overlay hidden">
  <div class="modal-container w-full max-w-4xl rounded-lg shadow-xl mx-4 my-8">
    <div class="border-b border-gray-200 dark:border-gray-700 px-6 py-4 flex justify-between items-center sticky top-0 bg-white dark:bg-gray-800 rounded-t-lg">
      <h3 class="text-lg font-semibold text-gray-800 dark:text-white">
        <i class="fas fa-file-invoice text-indigo-600 mr-2"></i>Create Invoice
      </h3>
      <button type="button" class="close-modal-btn text-gray-400 hover:text-gray-500 dark:hover:text-gray-300" data-modal="invoiceModal">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <div class="px-6 py-4 max-h-[calc(100vh-200px)] overflow-y-auto">
      <form id="invoiceForm">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Client *</label>
            <select id="clientId" name="client_id" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500">
              <option value="">Select client...</option>
            </select>
            <p class="text-red-500 text-xs mt-1 hidden" id="clientIdError">The invoice client id field is required.</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Invoice Date</label>
            <input type="date" id="invoiceDate" name="invoice_date" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500" value="<?php echo date('Y-m-d'); ?>">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Due Date</label>
            <input type="date" id="dueDate" name="due_date" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500" value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>">
          </div>
        </div>

        <div class="mb-6">
          <div class="flex justify-between items-center mb-4">
            <h4 class="text-md font-semibold text-gray-800 dark:text-white">Invoice Items</h4>
            <button type="button" id="addItemBtn" class="px-3 py-1 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm">
              <i class="fas fa-plus mr-1"></i>Add Item
            </button>
          </div>
          <div id="invoiceItemsContainer" class="space-y-3">
            <div class="invoice-item border border-gray-200 dark:border-gray-700 rounded-lg p-4">
              <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Vote Head *</label>
                  <select name="items[0][vote_head_id]" class="vote-head-select w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500" required>
                    <option value="">Select...</option>
                  </select>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description *</label>
                  <input type="text" name="items[0][description]" placeholder="Description" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500" required>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Qty</label>
                  <input type="number" name="items[0][qty]" value="1" class="item-qty w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500" step="1" min="1">
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Unit Price</label>
                  <input type="number" name="items[0][unit_price]" value="0" class="item-price w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500" step="0.01" min="0">
                </div>
              </div>
              <div class="mt-3 flex justify-end">
                <button type="button" class="remove-item-btn text-red-600 hover:text-red-800 text-sm">
                  <i class="fas fa-trash mr-1"></i>Remove
                </button>
              </div>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Notes</label>
            <textarea name="notes" rows="3" placeholder="Additional notes..." class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500"></textarea>
          </div>
          <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
            <div class="flex justify-between mb-2">
              <span class="text-gray-600 dark:text-gray-300">Subtotal:</span>
              <span class="font-semibold" id="subtotal">KES 0.00</span>
            </div>
            <div class="flex justify-between mb-2">
              <span class="text-gray-600 dark:text-gray-300">Tax Amount:</span>
              <span class="font-semibold" id="taxAmount">KES 0.00</span>
            </div>
            <div class="flex justify-between pt-2 border-t border-gray-300 dark:border-gray-600">
              <span class="text-lg font-bold text-gray-800 dark:text-white">Total:</span>
              <span class="text-lg font-bold text-indigo-600" id="totalAmount">KES 0.00</span>
            </div>
          </div>
        </div>
      </form>
    </div>
    <div class="border-t border-gray-200 dark:border-gray-700 px-6 py-4 flex justify-end space-x-3 sticky bottom-0 bg-white dark:bg-gray-800 rounded-b-lg">
      <button type="button" class="cancel-modal-btn px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700" data-modal="invoiceModal">
        Cancel
      </button>
      <button id="saveInvoiceBtn" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
        <i class="fas fa-save mr-2"></i>Create Invoice
      </button>
    </div>
  </div>
</div>

<!-- Add Client Modal -->
<div id="addClientModal" class="modal-overlay hidden">
  <div class="modal-container w-full max-w-2xl rounded-lg shadow-xl mx-4">
    <div class="border-b border-gray-200 dark:border-gray-700 px-6 py-4 flex justify-between items-center">
      <h3 class="text-lg font-semibold text-gray-800 dark:text-white">
        <i class="fas fa-user-plus text-green-600 mr-2"></i>Add New Client
      </h3>
      <button type="button" class="close-modal-btn text-gray-400 hover:text-gray-500 dark:hover:text-gray-300" data-modal="addClientModal">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <div class="px-6 py-4">
      <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Enter the client details below.</p>
      <form id="addClientForm">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Client Name *</label>
            <input type="text" id="clientName" name="name" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500" placeholder="Enter client name">
          </div>
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">ID/Registration Number</label>
            <input type="text" id="clientIdNumber" name="id_number" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500" placeholder="Enter ID number">
          </div>
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Contact Person</label>
            <input type="text" id="clientContact" name="contact" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500" placeholder="Enter contact person">
          </div>
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Phone *</label>
            <input type="tel" id="clientPhone" name="phone" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500" placeholder="Enter phone number">
          </div>
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Email Address</label>
            <input type="email" id="clientEmail" name="email" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500" placeholder="Enter email address">
          </div>
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">PIN Number</label>
            <input type="text" id="clientPin" name="pin" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500" placeholder="Enter PIN number">
          </div>
          <div class="mb-4 md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Address</label>
            <textarea id="clientAddress" name="address" rows="2" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500" placeholder="Enter address"></textarea>
          </div>
        </div>
      </form>
    </div>
    <div class="border-t border-gray-200 dark:border-gray-700 px-6 py-4 flex justify-end space-x-3">
      <button type="button" class="cancel-modal-btn px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700" data-modal="addClientModal">
        Cancel
      </button>
      <button id="saveClientBtn" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
        <i class="fas fa-save mr-2"></i>Create Client
      </button>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const schoolId = <?php echo json_encode($school_id); ?>;
let voteHeads = [];
let classes = [];
let streams = [];
let clients = [];
let currentDebits = [];

// ============================================================
// MODAL MANAGEMENT
// ============================================================
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
    }
}

function closeAllModals() {
    const modals = ['invoiceModal', 'addClientModal'];
    modals.forEach(modalId => {
        const modal = document.getElementById(modalId);
        if (modal) modal.classList.add('hidden');
    });
    document.body.style.overflow = '';
}

function setupModalButtons() {
    document.querySelectorAll('.close-modal-btn, .cancel-modal-btn').forEach(btn => {
        btn.removeEventListener('click', handleModalClose);
        btn.addEventListener('click', handleModalClose);
    });
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.removeEventListener('click', handleOverlayClick);
        overlay.addEventListener('click', handleOverlayClick);
    });
}

function handleModalClose(e) {
    const modalId = e.currentTarget.getAttribute('data-modal');
    if (modalId) closeModal(modalId);
}

function handleOverlayClick(e) {
    if (e.target === e.currentTarget) {
        e.currentTarget.classList.add('hidden');
        document.body.style.overflow = '';
    }
}

// ============================================================
// BULK DEBIT FUNCTIONALITY
// ============================================================
function showBulkDebitForm() {
    const classId = document.getElementById('debitClassFilter').value;
    if (!classId) {
        Swal.fire('Warning', 'Please select a class first', 'warning');
        return;
    }
    // Make sure vote heads are loaded
    if (voteHeads.length === 0) {
        Swal.fire('Info', 'Loading vote heads...', 'info');
        loadVoteHeads().then(() => {
            document.getElementById('bulkDebitForm').classList.remove('hidden');
        });
    } else {
        document.getElementById('bulkDebitForm').classList.remove('hidden');
    }
}

function hideBulkDebitForm() {
    document.getElementById('bulkDebitForm').classList.add('hidden');
    document.getElementById('bulkVoteHeadId').value = '';
    document.getElementById('bulkAmount').value = '';
    document.getElementById('bulkDescription').value = '';
}

async function addBulkDebit() {
    const classId = document.getElementById('debitClassFilter').value;
    const streamId = document.getElementById('debitStreamFilter').value;
    const term = document.getElementById('debitTermFilter').value;
    const year = document.getElementById('debitYearFilter').value;
    const voteHeadId = document.getElementById('bulkVoteHeadId').value;
    const amount = document.getElementById('bulkAmount').value;
    const description = document.getElementById('bulkDescription').value;
    
    console.log('Sending data:', { classId, streamId, term, year, voteHeadId, amount, description }); // Debug log
    
    if (!classId) {
        Swal.fire('Warning', 'Please select a class first', 'warning');
        return;
    }
    if (!voteHeadId) {
        Swal.fire('Error', 'Please select a vote head', 'error');
        return;
    }
    if (!amount || amount <= 0) {
        Swal.fire('Error', 'Please enter a valid amount', 'error');
        return;
    }
    
    const confirmResult = await Swal.fire({
        title: 'Confirm Bulk Debit',
        html: `You are about to add debit of <strong>KES ${parseFloat(amount).toLocaleString()}</strong> to <strong>ALL students</strong> in the selected class/stream for <strong>Term ${term}, ${year}</strong>.<br><br>This action cannot be undone easily.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Yes, add debit!',
        cancelButtonText: 'Cancel'
    });
    
    if (!confirmResult.isConfirmed) return;
    
    const saveBtn = document.getElementById('confirmBulkDebitBtn');
    const originalText = saveBtn.innerHTML;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
    saveBtn.disabled = true;
    
    try {
        const response = await fetch('/feesystem/api/feesystem/add_bulk_debit.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                school_id: schoolId,
                class_id: parseInt(classId),
                stream_id: streamId ? parseInt(streamId) : null,
                term: parseInt(term),
                year: parseInt(year),
                vote_head_id: parseInt(voteHeadId),
                amount: parseFloat(amount),
                description: description
            })
        });
        const data = await response.json();
        
        if (data.success) {
            Swal.fire('Success', `Debit added to ${data.affected_count} student(s) successfully!`, 'success');
            hideBulkDebitForm();
            loadDebits();
        } else {
            Swal.fire('Error', data.message || 'Failed to add debit', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        Swal.fire('Error', 'An error occurred while adding debit', 'error');
    } finally {
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
    }
}

// ============================================================
// INVOICE ITEM MANAGEMENT
// ============================================================
function addInvoiceItem(voteHeadOptions, index) {
    const container = document.getElementById('invoiceItemsContainer');
    const itemDiv = document.createElement('div');
    itemDiv.className = 'invoice-item border border-gray-200 dark:border-gray-700 rounded-lg p-4';
    itemDiv.innerHTML = `
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Vote Head *</label>
                <select name="items[${index}][vote_head_id]" class="vote-head-select w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500" required>
                    ${voteHeadOptions}
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description *</label>
                <input type="text" name="items[${index}][description]" placeholder="Description" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Qty</label>
                <input type="number" name="items[${index}][qty]" value="1" class="item-qty w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500" step="1" min="1">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Unit Price</label>
                <input type="number" name="items[${index}][unit_price]" value="0" class="item-price w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500" step="0.01" min="0">
            </div>
        </div>
        <div class="mt-3 flex justify-end">
            <button type="button" class="remove-item-btn text-red-600 hover:text-red-800 text-sm">
                <i class="fas fa-trash mr-1"></i>Remove
            </button>
        </div>
    `;
    
    const qtyInput = itemDiv.querySelector('.item-qty');
    const priceInput = itemDiv.querySelector('.item-price');
    const removeBtn = itemDiv.querySelector('.remove-item-btn');
    
    if (qtyInput) qtyInput.addEventListener('input', calculateInvoiceTotal);
    if (priceInput) priceInput.addEventListener('input', calculateInvoiceTotal);
    if (removeBtn) {
        removeBtn.addEventListener('click', function() {
            if (document.querySelectorAll('.invoice-item').length > 1) {
                itemDiv.remove();
                calculateInvoiceTotal();
            } else {
                Swal.fire('Warning', 'You need at least one invoice item', 'warning');
            }
        });
    }
    
    container.appendChild(itemDiv);
}

function resetInvoiceForm() {
    document.getElementById('clientId').value = '';
    const today = new Date().toISOString().split('T')[0];
    const futureDate = new Date();
    futureDate.setDate(futureDate.getDate() + 30);
    document.getElementById('invoiceDate').value = today;
    document.getElementById('dueDate').value = futureDate.toISOString().split('T')[0];
    document.querySelector('textarea[name="notes"]').value = '';
    
    const container = document.getElementById('invoiceItemsContainer');
    container.innerHTML = '';
    
    const voteHeadOptions = '<option value="">Select Vote Head</option>' + 
        voteHeads.map(vh => `<option value="${vh.id}">${escapeHtml(vh.name)} (${escapeHtml(vh.alias)})</option>`).join('');
    
    addInvoiceItem(voteHeadOptions, 0);
    
    document.getElementById('subtotal').textContent = 'KES 0.00';
    document.getElementById('taxAmount').textContent = 'KES 0.00';
    document.getElementById('totalAmount').textContent = 'KES 0.00';
}

function calculateInvoiceTotal() {
    let subtotal = 0;
    document.querySelectorAll('.invoice-item').forEach(item => {
        const qty = parseFloat(item.querySelector('.item-qty')?.value) || 0;
        const price = parseFloat(item.querySelector('.item-price')?.value) || 0;
        subtotal += qty * price;
    });
    
    const taxAmount = subtotal * 0.16;
    const total = subtotal + taxAmount;
    
    document.getElementById('subtotal').textContent = `KES ${subtotal.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
    document.getElementById('taxAmount').textContent = `KES ${taxAmount.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
    document.getElementById('totalAmount').textContent = `KES ${total.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
}

// ============================================================
// RENDER FUNCTIONS
// ============================================================
function renderInvoices(invoicesList) {
    const container = document.getElementById('invoicesList');
    
    if (!invoicesList || invoicesList.length === 0) {
        container.innerHTML = '<div class="text-center text-gray-500 py-8">No invoices found</div>';
        return;
    }
    
    container.innerHTML = invoicesList.map(inv => {
        const totalAmount = inv.total_amount || inv.total || 0;
        const formattedTotal = parseFloat(totalAmount).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
        const displayStatus = (inv.status || 'UNPAID').toLowerCase();
        
        return `
            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:shadow-md transition" id="invoice-${inv.id}">
                <div class="flex justify-between items-start">
                    <div>
                        <h4 class="text-lg font-semibold text-gray-800 dark:text-white">Invoice #${escapeHtml(inv.invoice_number)}</h4>
                        <p class="text-sm text-gray-500">Client: ${escapeHtml(inv.client_name)}</p>
                        <p class="text-sm text-gray-500">Date: ${inv.invoice_date || ''} | Due: ${inv.due_date || ''}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-xl font-bold text-indigo-600">KES ${formattedTotal}</p>
                        <span class="px-2 py-1 text-xs rounded-full ${getStatusClass(displayStatus)}">${displayStatus}</span>
                    </div>
                </div>
                <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700 flex justify-between items-center">
                    <div>
                        <button onclick="viewInvoice(${inv.id})" class="text-blue-600 hover:text-blue-800 mr-3">
                            <i class="fas fa-eye mr-1"></i>View
                        </button>
                        <button onclick="downloadInvoice(${inv.id})" class="text-green-600 hover:text-green-800">
                            <i class="fas fa-download mr-1"></i>Download PDF
                        </button>
                    </div>
                    <button onclick="deleteInvoice(${inv.id}, '${escapeHtml(inv.invoice_number)}')" 
                            class="delete-invoice-btn px-3 py-1 bg-red-500 text-white rounded-lg hover:bg-red-600 transition text-sm">
                        <i class="fas fa-trash mr-1"></i>Delete
                    </button>
                </div>
            </div>
        `;
    }).join('');
}

function renderDebitsTable(debits) {
    const tbody = document.getElementById('debitsTableBody');
    
    if (!debits || debits.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">No students found for selected filters</div></div>';
        document.getElementById('debitsTotal').textContent = 'KES 0';
        return;
    }
    
    let total = 0;
    tbody.innerHTML = debits.map(debit => {
        total += parseFloat(debit.amount);
        return `
          <tr>
            <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">${escapeHtml(debit.admission_no)}</div>
            <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">${escapeHtml(debit.full_name)}</div>
            <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">${escapeHtml(debit.gender)}</div>
            <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">Term ${debit.term}</div>
            <td class="px-6 py-4 text-sm font-semibold text-red-600">KES ${parseFloat(debit.amount).toLocaleString()}</div>
           </div>
        `;
    }).join('');
    
    document.getElementById('debitsTotal').textContent = `KES ${total.toLocaleString()}`;
}

function renderClientsTable(clientsList) {
    const tbody = document.getElementById('clientsTableBody');
    if (!clientsList || clientsList.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-4 text-center text-gray-500">No clients found</div></div>';
        return;
    }
    tbody.innerHTML = clientsList.map(client => `
        <tr>
            <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">${escapeHtml(client.name)}</div>
            <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">${escapeHtml(client.id_number || '-')}</div>
            <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">${escapeHtml(client.contact || '-')}</div>
            <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">${escapeHtml(client.phone || '-')}</div>
            <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">${escapeHtml(client.email || '-')}</div>
            <td class="px-6 py-4 text-sm">
                <button onclick="viewClientInvoices(${client.id})" class="text-indigo-600 hover:text-indigo-800 mr-2"><i class="fas fa-file-invoice"></i></button>
                <button onclick="editClient(${client.id})" class="text-blue-600 hover:text-blue-800 mr-2"><i class="fas fa-edit"></i></button>
             </div>
         </div>
    `).join('');
}

function getStatusClass(status) {
    const s = (status || '').toLowerCase();
    switch(s) {
        case 'paid': return 'bg-green-100 text-green-800';
        case 'overdue': return 'bg-red-100 text-red-800';
        case 'cancelled': return 'bg-gray-100 text-gray-800';
        default: return 'bg-yellow-100 text-yellow-800';
    }
}

// ============================================================
// TAB SWITCHING
// ============================================================
document.getElementById('debitsTab').addEventListener('click', () => switchTab('debits'));
document.getElementById('invoicesTab').addEventListener('click', () => switchTab('invoices'));
document.getElementById('clientsTab').addEventListener('click', () => switchTab('clients'));

function switchTab(tab) {
    document.querySelectorAll('.tab-button').forEach(btn => {
        btn.classList.remove('active', 'border-indigo-600', 'text-indigo-600');
        btn.classList.add('text-gray-500');
    });
    const activeBtn = document.getElementById(`${tab}Tab`);
    activeBtn.classList.add('active', 'border-indigo-600', 'text-indigo-600');
    activeBtn.classList.remove('text-gray-500');
    
    document.querySelectorAll('.tab-panel').forEach(panel => panel.classList.add('hidden'));
    document.getElementById(`${tab}Panel`).classList.remove('hidden');
    
    if (tab === 'debits') loadDebits();
    else if (tab === 'invoices') loadInvoices();
    else if (tab === 'clients') loadClients();
}

// ============================================================
// LOAD FUNCTIONS
// ============================================================
async function loadClasses() {
    try {
        const response = await fetch('/feesystem/api/feesystem/get_classes.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ school_id: schoolId })
        });
        const data = await response.json();
        if (data.success) {
            classes = data.classes;
            document.getElementById('debitClassFilter').innerHTML = '<option value="">All Classes</option>' + 
                classes.map(c => `<option value="${c.id}">${escapeHtml(c.class_level)}</option>`).join('');
        }
    } catch (error) {
        console.error('Error loading classes:', error);
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
            
            document.getElementById('bulkVoteHeadId').innerHTML = options;
            document.querySelectorAll('.vote-head-select').forEach(select => {
                select.innerHTML = options;
            });
        }
    } catch (error) {
        console.error('Error loading vote heads:', error);
    }
}

async function loadClients() {
    try {
        const response = await fetch('/feesystem/api/feesystem/get_clients.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ school_id: schoolId })
        });
        const data = await response.json();
        if (data.success) {
            clients = data.clients;
            renderClientsTable(clients);
        }
    } catch (error) {
        console.error('Error loading clients:', error);
    }
}

async function loadClientsForInvoice() {
    try {
        const response = await fetch('/feesystem/api/feesystem/get_clients.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ school_id: schoolId })
        });
        const data = await response.json();
        if (data.success) {
            clients = data.clients;
            const clientSelect = document.getElementById('clientId');
            clientSelect.innerHTML = '<option value="">Select client...</option>' + 
                clients.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
        }
    } catch (error) {
        console.error('Error loading clients:', error);
    }
}

async function loadInvoices() {
    const status = document.getElementById('invoiceStatusFilter').value;
    try {
        const response = await fetch('/feesystem/api/feesystem/get_invoices.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ school_id: schoolId, status: status })
        });
        const data = await response.json();
        if (data.success) {
            renderInvoices(data.invoices);
        }
    } catch (error) {
        console.error('Error loading invoices:', error);
    }
}

async function loadDebits() {
    const classId = document.getElementById('debitClassFilter').value;
    if (!classId) {
        document.getElementById('debitsTableBody').innerHTML = '<tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">Select a class to view students</div></div>';
        document.getElementById('debitsTotal').textContent = 'KES 0';
        return;
    }
    
    const streamId = document.getElementById('debitStreamFilter').value;
    const term = document.getElementById('debitTermFilter').value;
    const year = document.getElementById('debitYearFilter').value;
    
    try {
        const response = await fetch('/feesystem/api/feesystem/get_students_with_debits.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ school_id: schoolId, class_id: classId, stream_id: streamId || null, term: term, year: year })
        });
        const data = await response.json();
        if (data.success) {
            currentDebits = data.debits || [];
            renderDebitsTable(currentDebits);
        } else {
            console.error('Error loading debits:', data.message);
        }
    } catch (error) {
        console.error('Error loading debits:', error);
    }
}

// ============================================================
// DELETE INVOICE FUNCTION
// ============================================================
window.deleteInvoice = async (invoiceId, invoiceNumber) => {
    const result = await Swal.fire({
        title: 'Are you sure?',
        html: `You are about to delete invoice <strong>${escapeHtml(invoiceNumber)}</strong>.<br><br>This action cannot be undone and will also delete all invoice items.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
    });
    
    if (result.isConfirmed) {
        try {
            const response = await fetch('/feesystem/api/feesystem/delete_invoice.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    invoice_id: invoiceId,
                    school_id: schoolId
                })
            });
            const data = await response.json();
            
            if (data.success) {
                Swal.fire('Deleted!', 'Invoice has been deleted successfully.', 'success');
                loadInvoices();
            } else {
                Swal.fire('Error!', data.message || 'Failed to delete invoice.', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            Swal.fire('Error!', 'An error occurred while deleting the invoice.', 'error');
        }
    }
};

// ============================================================
// EVENT LISTENERS
// ============================================================
document.getElementById('createInvoiceBtn').addEventListener('click', async () => {
    await loadClientsForInvoice();
    resetInvoiceForm();
    openModal('invoiceModal');
});

document.getElementById('addItemBtn').addEventListener('click', () => {
    const options = '<option value="">Select Vote Head</option>' + 
        voteHeads.map(vh => `<option value="${vh.id}">${escapeHtml(vh.name)} (${escapeHtml(vh.alias)})</option>`).join('');
    const currentCount = document.querySelectorAll('.invoice-item').length;
    addInvoiceItem(options, currentCount);
});

document.getElementById('saveInvoiceBtn').addEventListener('click', async () => {
    const clientId = document.getElementById('clientId').value;
    const invoiceDate = document.getElementById('invoiceDate').value;
    const dueDate = document.getElementById('dueDate').value;
    const notes = document.querySelector('textarea[name="notes"]').value;
    
    if (!clientId) {
        document.getElementById('clientIdError').classList.remove('hidden');
        Swal.fire('Error', 'Please select a client', 'error');
        return;
    }
    document.getElementById('clientIdError').classList.add('hidden');
    
    const items = [];
    let hasValidItem = false;
    
    document.querySelectorAll('.invoice-item').forEach((item) => {
        const voteHeadId = item.querySelector('.vote-head-select').value;
        const description = item.querySelector('input[name*="description"]').value;
        const qty = parseFloat(item.querySelector('.item-qty').value) || 0;
        const unitPrice = parseFloat(item.querySelector('.item-price').value) || 0;
        
        if (voteHeadId && description && qty > 0 && unitPrice > 0) {
            items.push({ vote_head_id: voteHeadId, description, qty, unit_price: unitPrice });
            hasValidItem = true;
        }
    });
    
    if (!hasValidItem) {
        Swal.fire('Error', 'Please add at least one valid invoice item (Vote Head, Description, Quantity > 0, and Unit Price > 0)', 'error');
        return;
    }
    
    let subtotal = 0;
    items.forEach(item => { subtotal += item.qty * item.unit_price; });
    const taxAmount = subtotal * 0.16;
    const total = subtotal + taxAmount;
    
    const saveBtn = document.getElementById('saveInvoiceBtn');
    const originalText = saveBtn.innerHTML;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Creating...';
    saveBtn.disabled = true;
    
    try {
        const response = await fetch('/feesystem/api/feesystem/create_invoice.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                school_id: schoolId,
                client_id: parseInt(clientId),
                invoice_date: invoiceDate,
                due_date: dueDate,
                notes: notes,
                subtotal: subtotal,
                tax_amount: taxAmount,
                total: total,
                items: items
            })
        });
        const data = await response.json();
        
        if (data.success) {
            Swal.fire('Success', 'Invoice created successfully!', 'success');
            closeModal('invoiceModal');
            loadInvoices();
        } else {
            Swal.fire('Error', data.message || 'Failed to create invoice', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        Swal.fire('Error', 'An error occurred while creating the invoice', 'error');
    } finally {
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
    }
});

// Debit button handlers
document.getElementById('addDebitBtn').addEventListener('click', showBulkDebitForm);
document.getElementById('cancelBulkDebitBtn').addEventListener('click', hideBulkDebitForm);
document.getElementById('confirmBulkDebitBtn').addEventListener('click', addBulkDebit);

document.getElementById('addClientBtn').addEventListener('click', () => openModal('addClientModal'));

document.getElementById('saveClientBtn').addEventListener('click', async () => {
    const name = document.getElementById('clientName').value;
    const phone = document.getElementById('clientPhone').value;
    
    if (!name) { Swal.fire('Error', 'Please enter client name', 'error'); return; }
    if (!phone) { Swal.fire('Error', 'Please enter phone number', 'error'); return; }
    
    try {
        const response = await fetch('/feesystem/api/feesystem/add_client.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                school_id: schoolId,
                name: name,
                id_number: document.getElementById('clientIdNumber').value,
                contact: document.getElementById('clientContact').value,
                phone: phone,
                email: document.getElementById('clientEmail').value,
                address: document.getElementById('clientAddress').value
            })
        });
        const data = await response.json();
        if (data.success) {
            Swal.fire('Success', 'Client added successfully!', 'success');
            closeModal('addClientModal');
            loadClients();
            document.getElementById('addClientForm').reset();
        } else {
            Swal.fire('Error', data.message || 'Failed to add client', 'error');
        }
    } catch (error) {
        Swal.fire('Error', 'An error occurred', 'error');
    }
});

document.getElementById('searchClientBtn').addEventListener('click', async () => {
    try {
        const response = await fetch('/feesystem/api/feesystem/search_clients.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                school_id: schoolId,
                name: document.getElementById('searchClientName').value,
                id_number: document.getElementById('searchClientId').value,
                contact: document.getElementById('searchClientContact').value
            })
        });
        const data = await response.json();
        if (data.success) renderClientsTable(data.clients);
    } catch (error) {
        console.error('Error searching clients:', error);
    }
});

// Filter listeners
document.getElementById('debitClassFilter').addEventListener('change', () => {
    hideBulkDebitForm();
    loadDebits();
});
document.getElementById('debitStreamFilter').addEventListener('change', () => {
    hideBulkDebitForm();
    loadDebits();
});
document.getElementById('debitTermFilter').addEventListener('change', () => loadDebits());
document.getElementById('debitYearFilter').addEventListener('change', () => loadDebits());
document.getElementById('invoiceStatusFilter').addEventListener('change', () => loadInvoices());

// Stream loading on class change
document.getElementById('debitClassFilter').addEventListener('change', async () => {
    const classId = document.getElementById('debitClassFilter').value;
    const streamSelect = document.getElementById('debitStreamFilter');
    if (!classId) {
        streamSelect.innerHTML = '<option value="">All Streams</option>';
        return;
    }
    try {
        const response = await fetch('/feesystem/api/feesystem/get_streams.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ school_id: schoolId, class_id: classId })
        });
        const data = await response.json();
        if (data.success) {
            streamSelect.innerHTML = '<option value="">All Streams</option>' + 
                data.streams.map(s => `<option value="${s.id}">${escapeHtml(s.stream_name)}</option>`).join('');
        }
    } catch (error) {
        console.error('Error loading streams:', error);
    }
});

// ============================================================
// HELPER FUNCTIONS
// ============================================================
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Global functions
window.viewInvoice = async (invoiceId) => {
    try {
        const response = await fetch('/feesystem/api/feesystem/get_invoice_details.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ invoice_id: invoiceId })
        });
        const data = await response.json();
        if (data.success) {
            let itemsHtml = '';
            data.items.forEach(item => {
                itemsHtml += `<div class="flex justify-between text-sm py-1">
                    <span>${escapeHtml(item.description)}</span>
                    <span>${item.quantity} x KES ${parseFloat(item.unit_price).toLocaleString()} = KES ${parseFloat(item.total).toLocaleString()}</span>
                </div>`;
            });
            Swal.fire({
                title: `Invoice #${data.invoice.invoice_number}`,
                html: `<div class="text-left">
                    <p><strong>Client:</strong> ${escapeHtml(data.invoice.client_name)}</p>
                    <p><strong>Date:</strong> ${data.invoice.invoice_date}</p>
                    <p><strong>Due Date:</strong> ${data.invoice.due_date}</p>
                    <p><strong>Status:</strong> <span class="px-2 py-1 text-xs rounded-full ${getStatusClass(data.invoice.status)}">${data.invoice.status}</span></p>
                    <div class="border-t border-gray-200 my-3"></div>
                    <p><strong>Items:</strong></p>
                    ${itemsHtml}
                    <div class="border-t border-gray-200 my-3"></div>
                    <p><strong>Subtotal:</strong> KES ${parseFloat(data.invoice.subtotal).toLocaleString()}</p>
                    <p><strong>Tax (16%):</strong> KES ${parseFloat(data.invoice.tax_amount).toLocaleString()}</p>
                    <p><strong>Total:</strong> KES ${parseFloat(data.invoice.total).toLocaleString()}</p>
                    ${data.invoice.notes ? `<p><strong>Notes:</strong> ${escapeHtml(data.invoice.notes)}</p>` : ''}
                </div>`,
                width: '600px',
                confirmButtonText: 'Close'
            });
        }
    } catch (error) {
        Swal.fire('Error', 'An error occurred', 'error');
    }
};

window.downloadInvoice = async (invoiceId) => {
    try {
        const response = await fetch('/feesystem/api/feesystem/download_invoice_pdf.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ invoice_id: invoiceId })
        });
        const data = await response.json();
        if (data.success && data.pdf_url) window.open(data.pdf_url, '_blank');
        else Swal.fire('Error', 'Could not generate PDF', 'error');
    } catch (error) {
        Swal.fire('Error', 'An error occurred', 'error');
    }
};

window.viewClientInvoices = async (clientId) => {
    try {
        const response = await fetch('/feesystem/api/feesystem/get_invoices.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ school_id: schoolId, client_id: clientId })
        });
        const data = await response.json();
        if (data.success && data.invoices.length > 0) {
            let invoicesHtml = '';
            data.invoices.forEach(inv => {
                invoicesHtml += `<div class="border-b border-gray-200 py-2">
                    <p><strong>#${inv.invoice_number}</strong> - KES ${parseFloat(inv.total).toLocaleString()} - 
                    <span class="px-2 py-1 text-xs rounded-full ${getStatusClass(inv.status)}">${inv.status}</span></p>
                    <p class="text-sm text-gray-500">Due: ${inv.due_date}</p>
                </div>`;
            });
            Swal.fire({ title: 'Client Invoices', html: `<div class="max-h-96 overflow-y-auto">${invoicesHtml}</div>`, width: '500px', confirmButtonText: 'Close' });
        } else {
            Swal.fire('Info', 'No invoices found for this client', 'info');
        }
    } catch (error) {
        Swal.fire('Error', 'An error occurred', 'error');
    }
};

window.editClient = async (clientId) => {
    const client = clients.find(c => c.id === clientId);
    if (!client) { Swal.fire('Error', 'Client not found', 'error'); return; }
    Swal.fire({
        title: 'Edit Client',
        html: `
            <input id="edit-name" class="swal2-input" placeholder="Client Name" value="${escapeHtml(client.name)}">
            <input id="edit-id-number" class="swal2-input" placeholder="ID Number" value="${escapeHtml(client.id_number || '')}">
            <input id="edit-contact" class="swal2-input" placeholder="Contact Person" value="${escapeHtml(client.contact || '')}">
            <input id="edit-phone" class="swal2-input" placeholder="Phone" value="${escapeHtml(client.phone || '')}">
            <input id="edit-email" class="swal2-input" placeholder="Email" value="${escapeHtml(client.email || '')}">
            <textarea id="edit-address" class="swal2-textarea" placeholder="Address">${escapeHtml(client.address || '')}</textarea>
        `,
        focusConfirm: false,
        preConfirm: async () => {
            const name = document.getElementById('edit-name').value;
            const phone = document.getElementById('edit-phone').value;
            if (!name) { Swal.showValidationMessage('Client name is required'); return false; }
            if (!phone) { Swal.showValidationMessage('Phone number is required'); return false; }
            try {
                const response = await fetch('/feesystem/api/feesystem/update_client.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id: clientId, school_id: schoolId, name: name,
                        id_number: document.getElementById('edit-id-number').value,
                        contact: document.getElementById('edit-contact').value,
                        phone: phone, email: document.getElementById('edit-email').value,
                        address: document.getElementById('edit-address').value
                    })
                });
                const data = await response.json();
                if (data.success) { Swal.fire('Success', 'Client updated successfully!', 'success'); loadClients(); }
                else { Swal.fire('Error', data.message || 'Failed to update client', 'error'); }
            } catch (error) { Swal.fire('Error', 'An error occurred', 'error'); }
        },
        showCancelButton: true, confirmButtonText: 'Update', cancelButtonText: 'Cancel'
    });
};

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    closeAllModals();
    setupModalButtons();
    loadClasses();
    loadVoteHeads();
    loadInvoices();
    loadClients();
    document.body.style.overflow = '';
});
</script>

<?php include_once('../../includes/footer.php'); ?>