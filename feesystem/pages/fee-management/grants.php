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
/* Additional styles for grant distributions */
.distribution-item {
    background: #f9fafb;
    border-radius: 0.5rem;
    padding: 0.75rem;
    margin-bottom: 0.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.distribution-item:hover {
    background: #f3f4f6;
}

.distribution-item .remove-distribution {
    color: #ef4444;
    cursor: pointer;
    padding: 0.25rem;
}

.distribution-item .remove-distribution:hover {
    color: #dc2626;
}

.distribution-summary {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 0.5rem;
    padding: 1rem;
    margin-top: 1rem;
}

.distribution-summary.warning {
    background: #fef3c7;
    border-color: #fde68a;
}

.distribution-summary.error {
    background: #fee2e2;
    border-color: #fecaca;
}

/* Main Page Two Column Layout */
.main-content-with-preview {
    display: flex;
    gap: 1.5rem;
    padding: 1rem 1.5rem;
}

.grants-list-container {
    flex: 2;
    min-width: 0;
}

.receipt-preview-container {
    flex: 1;
    min-width: 320px;
    position: sticky;
    top: 1rem;
    align-self: flex-start;
}

/* Receipt Preview Card Styles - Right Side */
.receipt-preview-card {
    background: white;
    border-radius: 0.75rem;
    border: 1px solid #e5e7eb;
    padding: 1rem;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
}

.dark .receipt-preview-card {
    background: #1f2937;
    border-color: #374151;
}

.receipt-preview {
    font-family: 'Courier New', monospace;
    font-size: 11px;
    line-height: 1.4;
    color: #000;
}

.dark .receipt-preview {
    color: #e5e7eb;
}

.receipt-preview .receipt-header {
    text-align: center;
    margin-bottom: 12px;
    border-bottom: 1px dashed #000;
    padding-bottom: 8px;
}

.receipt-preview .receipt-title {
    font-size: 13px;
    font-weight: bold;
    text-align: center;
    margin: 8px 0;
}

.receipt-preview .receipt-line {
    border-top: 1px dashed #000;
    margin: 6px 0;
}

.receipt-preview .receipt-row {
    display: flex;
    justify-content: space-between;
    margin: 3px 0;
}

.receipt-preview .receipt-total {
    font-weight: bold;
    border-top: 1px solid #000;
    margin-top: 6px;
    padding-top: 6px;
}

.receipt-preview .receipt-footer {
    margin-top: 15px;
    text-align: center;
    border-top: 1px dashed #000;
    padding-top: 8px;
}

/* Modal with higher z-index */
#grantModal, #viewGrantModal {
    z-index: 9999 !important;
}

.modal-overlay {
    z-index: 9998 !important;
}

/* SweetAlert2 custom z-index to appear on top of all modals */
.swal2-container {
    z-index: 99999 !important;
}

.swal2-popup {
    z-index: 100000 !important;
}

/* Form group spacing */
.form-group {
    margin-bottom: 1rem;
}

.form-group label {
    display: block;
    font-size: 0.875rem;
    font-weight: 500;
    margin-bottom: 0.5rem;
    color: #374151;
}

.dark .form-group label {
    color: #d1d5db;
}

/* Responsive */
@media (max-width: 1024px) {
    .main-content-with-preview {
        flex-direction: column;
    }
    .receipt-preview-container {
        position: static;
        order: -1;
        margin-bottom: 1.5rem;
    }
}

/* PDF Download Button */
.download-pdf-btn {
    background-color: #dc2626;
    color: white;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    font-size: 0.75rem;
    font-weight: 500;
    cursor: pointer;
    transition: background-color 0.2s;
    width: 100%;
    margin-top: 0.75rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.download-pdf-btn:hover {
    background-color: #b91c1c;
}

.download-pdf-btn i {
    font-size: 0.875rem;
}
</style>

<main class="main-content flex-grow flex flex-col">
  <header class="bg-white shadow-sm dark:bg-gray-800 dark:border-gray-700">
    <div class="px-4 py-3 flex justify-between items-center">
      <div class="flex items-center">
        <button id="mobile-toggle" class="mr-4 text-gray-500 hover:text-indigo-600 focus:outline-none lg:hidden">
          <i class="fas fa-bars text-xl"></i>
        </button>
        <h1 id="page-title" class="text-xl font-semibold text-gray-800 dark:text-white">Grants & Bursaries</h1>
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

  <div class="flex-grow overflow-auto">
    <!-- Two Column Layout -->
    <div class="main-content-with-preview">
      <!-- Left Column: Grants List -->
      <div class="grants-list-container">
        <!-- Summary Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
          <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm p-5 border border-gray-100 dark:border-gray-700">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Total Grants Available</p>
                <p class="text-2xl font-bold text-gray-800 dark:text-white" id="totalGrants">KES 0</p>
              </div>
              <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900/30 rounded-xl flex items-center justify-center">
                <i class="fas fa-hand-holding-heart text-blue-600 dark:text-blue-400 text-xl"></i>
              </div>
            </div>
          </div>
          <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm p-5 border border-gray-100 dark:border-gray-700">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Total Allocated</p>
                <p class="text-2xl font-bold text-gray-800 dark:text-white" id="totalAllocated">KES 0</p>
              </div>
              <div class="w-12 h-12 bg-green-100 dark:bg-green-900/30 rounded-xl flex items-center justify-center">
                <i class="fas fa-check-circle text-green-600 dark:text-green-400 text-xl"></i>
              </div>
            </div>
          </div>
          <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm p-5 border border-gray-100 dark:border-gray-700">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Remaining Balance</p>
                <p class="text-2xl font-bold text-gray-800 dark:text-white" id="remainingBalance">KES 0</p>
              </div>
              <div class="w-12 h-12 bg-yellow-100 dark:bg-yellow-900/30 rounded-xl flex items-center justify-center">
                <i class="fas fa-chart-line text-yellow-600 dark:text-yellow-400 text-xl"></i>
              </div>
            </div>
          </div>
          <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm p-5 border border-gray-100 dark:border-gray-700">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Total Grants</p>
                <p class="text-2xl font-bold text-gray-800 dark:text-white" id="totalGrantsCount">0</p>
              </div>
              <div class="w-12 h-12 bg-purple-100 dark:bg-purple-900/30 rounded-xl flex items-center justify-center">
                <i class="fas fa-ticket-alt text-purple-600 dark:text-purple-400 text-xl"></i>
              </div>
            </div>
          </div>
        </div>

        <!-- Header Section with Add Button -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
          <div>
            <h1 class="text-xl font-semibold text-gray-800 dark:text-white">Grants & Bursaries List</h1>
            <p class="text-gray-500 dark:text-gray-400 text-sm mt-1">Manage grant receipts and allocations</p>
          </div>
          <div class="flex flex-wrap gap-3 mt-3 md:mt-0">
            <button id="addGrantBtn" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition flex items-center gap-2">
              <i class="fas fa-plus"></i> New Grant Receipt
            </button>
          </div>
        </div>

        <!-- Grants Table -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead class="bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Receipt No</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Grant Name</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Source</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Receipt Date</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Total Amount</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                </tr>
              </thead>
              <tbody id="grantsTableBody" class="divide-y divide-gray-200 dark:divide-gray-700">
                <tr><td colspan="7" class="px-6 py-8 text-center text-gray-500">Loading grants...</div></div>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Right Column: Receipt Preview (Sticky) -->
      <div class="receipt-preview-container">
        <div class="receipt-preview-card">
          <h3 class="text-md font-semibold text-gray-700 dark:text-gray-300 mb-3 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center gap-2">
            <i class="fas fa-receipt text-indigo-500"></i> Receipt Preview
          </h3>
          <div class="receipt-preview" id="receiptPreviewContent">
            <div class="receipt-header">
              <div style="font-size: 12px; font-weight: bold;">SCHOOL NAME</div>
              <div style="font-size: 9px;">Select a grant or fill form to see preview</div>
            </div>
          </div>
          <button id="downloadPdfBtn" class="download-pdf-btn">
            <i class="fas fa-file-pdf"></i> Download Receipt as PDF
          </button>
        </div>
      </div>
    </div>
  </div>
</main>

<!-- Create Grant Modal (Without Preview and Without Total Amount field) -->
<div id="grantModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden overflow-y-auto">
  <div class="min-h-screen flex items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-800 w-full max-w-3xl rounded-2xl shadow-xl">
      <div class="border-b border-gray-200 dark:border-gray-700 px-6 py-4 flex justify-between items-center">
        <h3 class="text-lg font-semibold text-gray-800 dark:text-white">Create Grant Receipt</h3>
        <button id="closeGrantModal" class="text-gray-400 hover:text-gray-500">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <div class="px-6 py-4 max-h-[calc(100vh-200px)] overflow-y-auto">
        <form id="grantForm">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="form-group">
              <label>Grant Name *</label>
              <input type="text" id="grantName" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500" placeholder="e.g., Government Capitation Grant">
            </div>
            <div class="form-group">
              <label>Receipt Date *</label>
              <input type="date" id="receiptDate" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500" value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="form-group">
              <label>Source *</label>
              <select id="grantSource" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500">
                <option value="Government">Government</option>
                <option value="Private">Private</option>
                <option value="School">School</option>
                <option value="NGO">NGO</option>
                <option value="Other">Other</option>
              </select>
            </div>
            <div class="form-group">
              <label>Payment Mode *</label>
              <select id="paymentMode" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500">
                <option value="">Select mode...</option>
                <option value="cash">Cash</option>
                <option value="mpesa">M-Pesa</option>
                <option value="bank_transfer">Bank Transfer</option>
                <option value="cheque">Cheque</option>
                <option value="card">Credit/Debit Card</option>
              </select>
            </div>
            <div class="form-group">
              <label>Reference No.</label>
              <input type="text" id="referenceNo" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500" placeholder="Optional...">
            </div>
          </div>

          <!-- Vote Head Distributions Section -->
          <div class="form-group">
            <label>Vote Head Distributions</label>
            <div class="flex gap-2 mb-3 flex-wrap">
              <select id="distributionVoteHead" class="flex-1 min-w-[150px] px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500">
                <option value="">Select vote head...</option>
              </select>
              <input type="number" id="distributionAmount" placeholder="Amount" class="w-32 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500" step="0.01" min="0">
              <button type="button" id="addDistributionBtn" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                <i class="fas fa-plus"></i> Add
              </button>
            </div>
            <div id="distributionsList" class="space-y-2"></div>
            <div id="distributionSummary" class="distribution-summary mt-3 hidden"></div>
          </div>

          <div class="form-group">
            <label>Notes</label>
            <textarea id="grantNotes" rows="3" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500" placeholder="Additional notes..."></textarea>
          </div>
        </form>
      </div>
      <div class="border-t border-gray-200 dark:border-gray-700 px-6 py-4 flex justify-end space-x-3">
        <button id="cancelGrantBtn" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700">Cancel</button>
        <button id="saveGrantBtn" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
          <i class="fas fa-save mr-2"></i>Create Grant
        </button>
      </div>
    </div>
  </div>
</div>

<!-- View Grant Details Modal -->
<div id="viewGrantModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden overflow-y-auto">
  <div class="min-h-screen flex items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-800 w-full max-w-2xl rounded-2xl shadow-xl">
      <div class="border-b border-gray-200 dark:border-gray-700 px-6 py-4 flex justify-between items-center">
        <h3 class="text-lg font-semibold text-gray-800 dark:text-white">Grant Details</h3>
        <button id="closeViewGrantModal" class="text-gray-400 hover:text-gray-500">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <div id="viewGrantContent" class="px-6 py-4 max-h-[calc(100vh-200px)] overflow-y-auto"></div>
      <div class="border-t border-gray-200 dark:border-gray-700 px-6 py-4 flex justify-end space-x-3">
        <button id="printReceiptBtn" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
          <i class="fas fa-print mr-2"></i>Print Receipt
        </button>
        <button id="closeViewGrantModalBtn" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700">Close</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// ============================================================
// HELPER FUNCTIONS
// ============================================================
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function numberToWords(num) {
    if (num === 0) return 'Zero';
    const ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine'];
    const tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
    const teens = ['Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    
    function convert(n) {
        if (n < 10) return ones[n];
        if (n < 20) return teens[n - 10];
        if (n < 100) return tens[Math.floor(n / 10)] + (n % 10 ? ' ' + ones[n % 10] : '');
        if (n < 1000) return ones[Math.floor(n / 100)] + ' Hundred' + (n % 100 ? ' ' + convert(n % 100) : '');
        if (n < 1000000) return convert(Math.floor(n / 1000)) + ' Thousand' + (n % 1000 ? ' ' + convert(n % 1000) : '');
        return convert(Math.floor(n / 1000000)) + ' Million' + (n % 1000000 ? ' ' + convert(n % 1000000) : '');
    }
    return convert(num);
}

function generateReceiptNumber() {
    const date = new Date();
    return `GRT-${date.getFullYear()}${String(date.getMonth()+1).padStart(2,'0')}${String(date.getDate()).padStart(2,'0')}-${Math.floor(Math.random()*1000).toString().padStart(3,'0')}`;
}

// ============================================================
// GLOBAL VARIABLES
// ============================================================
const schoolId = <?php echo json_encode($school_id); ?>;
let grants = [];
let distributions = [];
let voteHeads = [];

let schoolInfo = {
    school_name: '<?php echo addslashes($school_name ?: 'SCHOOL NAME'); ?>',
    school_address: '',
    school_phone: ''
};

// Configure SweetAlert2 to have higher z-index than modals
const SwalConfig = {
    customClass: {
        container: 'swal2-container-top'
    },
    didOpen: () => {
        document.querySelectorAll('.swal2-container').forEach(container => {
            container.style.zIndex = '99999';
        });
    }
};

const originalSwal = Swal.fire;
Swal.fire = function(...args) {
    const options = args[0] || {};
    if (typeof options === 'object') {
        options.customClass = options.customClass || {};
        options.customClass.container = (options.customClass.container || '') + ' swal2-container-top';
        options.didOpen = (originalDidOpen) => {
            if (originalDidOpen) originalDidOpen();
            setTimeout(() => {
                document.querySelectorAll('.swal2-container').forEach(container => {
                    container.style.zIndex = '99999';
                });
            }, 10);
        };
    }
    return originalSwal.call(this, options);
};

// ============================================================
// API FUNCTIONS
// ============================================================
async function loadSchoolInfo() {
    try {
        const response = await fetch('/feesystem/api/feesystem/get_school_info.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        });
        const data = await response.json();
        if (data.success && data.school_info) {
            schoolInfo = data.school_info;
            updateReceiptPreview();
        }
    } catch (error) {
        console.error('Error loading school info:', error);
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
        if (data.success && data.vote_heads) {
            voteHeads = data.vote_heads;
            const select = document.getElementById('distributionVoteHead');
            if (select) {
                select.innerHTML = '<option value="">Select vote head...</option>' + 
                    voteHeads.map(vh => `<option value="${vh.id}" data-name="${escapeHtml(vh.name)}">${escapeHtml(vh.name)} (${escapeHtml(vh.alias)})</option>`).join('');
            }
        }
    } catch (error) {
        console.error('Error loading vote heads:', error);
    }
}

async function loadGrants() {
    try {
        const response = await fetch('/feesystem/api/feesystem/get_grants.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ school_id: schoolId })
        });
        const data = await response.json();
        if (data.success) {
            grants = data.grants;
            renderGrantsTable();
            updateSummaryCards();
        }
    } catch (error) {
        console.error('Error loading grants:', error);
    }
}

function renderGrantsTable() {
    const tbody = document.getElementById('grantsTableBody');
    if (!grants || grants.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="px-6 py-8 text-center text-gray-500">No grants found</div></div>';
        return;
    }
    
    tbody.innerHTML = grants.map(grant => {
        const isActive = grant.status === 'active';
        return `
            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition cursor-pointer" onclick="viewGrant(${grant.id})">
                <td class="px-6 py-4 font-mono text-sm">${escapeHtml(grant.grant_number || 'N/A')}</div>
                <td class="px-6 py-4 font-medium">${escapeHtml(grant.name)}</div>
                <td class="px-6 py-4"><span class="px-2 py-1 text-xs rounded-full bg-gray-100">${escapeHtml(grant.source)}</span></div>
                <td class="px-6 py-4">${grant.receipt_date ? new Date(grant.receipt_date).toLocaleDateString() : '-'}</div>
                <td class="px-6 py-4 font-semibold">KES ${parseFloat(grant.total_amount).toLocaleString()}</div>
                <td class="px-6 py-4">
                    <span class="px-2 py-1 text-xs rounded-full ${isActive ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}">
                        ${isActive ? 'Active' : 'Exhausted'}
                    </span>
                  </div>
                <td class="px-6 py-4">
                    <div class="flex space-x-2">
                        <button onclick="event.stopPropagation(); viewGrant(${grant.id})" class="text-blue-600 hover:text-blue-800" title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button onclick="event.stopPropagation(); deleteGrant(${grant.id})" class="text-red-600 hover:text-red-800" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                  </div>
              </tr>
        `;
    }).join('');
}

function updateSummaryCards() {
    const totalGrants = grants.reduce((sum, g) => sum + parseFloat(g.total_amount), 0);
    const totalAllocated = grants.reduce((sum, g) => sum + parseFloat(g.allocated_amount), 0);
    const remaining = totalGrants - totalAllocated;
    
    document.getElementById('totalGrants').textContent = `KES ${totalGrants.toLocaleString()}`;
    document.getElementById('totalAllocated').textContent = `KES ${totalAllocated.toLocaleString()}`;
    document.getElementById('remainingBalance').textContent = `KES ${remaining.toLocaleString()}`;
    document.getElementById('totalGrantsCount').textContent = grants.length;
}

// ============================================================
// DISTRIBUTION MANAGEMENT
// ============================================================
function addDistribution() {
    const voteHeadSelect = document.getElementById('distributionVoteHead');
    const voteHeadId = voteHeadSelect.value;
    const voteHeadName = voteHeadSelect.options[voteHeadSelect.selectedIndex]?.dataset?.name || '';
    const amount = parseFloat(document.getElementById('distributionAmount').value);
    
    if (!voteHeadId) {
        Swal.fire('Error', 'Please select a vote head', 'error');
        return;
    }
    if (!amount || amount <= 0) {
        Swal.fire('Error', 'Please enter a valid amount', 'error');
        return;
    }
    
    distributions.push({
        vote_head_id: parseInt(voteHeadId),
        vote_head_name: voteHeadName,
        amount: amount
    });
    
    document.getElementById('distributionAmount').value = '';
    voteHeadSelect.value = '';
    renderDistributions();
    updateReceiptPreview();
}

function removeDistribution(index) {
    distributions.splice(index, 1);
    renderDistributions();
    updateReceiptPreview();
}

function renderDistributions() {
    const container = document.getElementById('distributionsList');
    const totalDistribution = distributions.reduce((sum, d) => sum + d.amount, 0);
    
    if (distributions.length === 0) {
        container.innerHTML = '<div class="text-gray-500 text-sm text-center py-4">No distributions added yet</div>';
    } else {
        container.innerHTML = distributions.map((dist, index) => `
            <div class="distribution-item">
                <div>
                    <span class="font-medium">${escapeHtml(dist.vote_head_name)}</span>
                    <span class="text-gray-500 ml-2">KES ${dist.amount.toLocaleString()}</span>
                </div>
                <button type="button" onclick="removeDistribution(${index})" class="remove-distribution">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `).join('');
    }
    
    const summaryDiv = document.getElementById('distributionSummary');
    if (totalDistribution > 0) {
        summaryDiv.classList.remove('hidden');
        summaryDiv.className = 'distribution-summary mt-3';
        summaryDiv.innerHTML = `<div class="flex justify-between items-center">
            <span><i class="fas fa-chart-pie text-indigo-600"></i> Total Distributed: KES ${totalDistribution.toLocaleString()}</span>
        </div>`;
    } else {
        summaryDiv.classList.add('hidden');
    }
}

// ============================================================
// RECEIPT PREVIEW
// ============================================================
function getPreviewHTML() {
    const grantName = document.getElementById('grantName')?.value || 'Select or fill grant details';
    const receiptDate = document.getElementById('receiptDate')?.value || new Date().toISOString().split('T')[0];
    const source = document.getElementById('grantSource')?.value || 'Source';
    const paymentMode = document.getElementById('paymentMode')?.value || 'Payment Mode';
    const referenceNo = document.getElementById('referenceNo')?.value || '-';
    const totalDistribution = distributions.reduce((sum, d) => sum + d.amount, 0);
    const notes = document.getElementById('grantNotes')?.value || '';
    const amountInWords = numberToWords(Math.floor(totalDistribution));
    
    let currentDistributions = distributions;
    if (typeof window.currentViewDistributions !== 'undefined' && window.currentViewDistributions) {
        currentDistributions = window.currentViewDistributions;
    }
    
    return `
        <div class="receipt-preview" id="receiptToPrint">
            <div class="receipt-header">
                <div style="font-size: 13px; font-weight: bold;">${escapeHtml(schoolInfo.school_name || 'SCHOOL NAME')}</div>
                <div style="font-size: 9px;">${escapeHtml(schoolInfo.school_address || '')}</div>
                <div style="font-size: 9px;">Tel: ${escapeHtml(schoolInfo.school_phone || '')}</div>
            </div>
            <div class="receipt-title">OFFICIAL RECEIPT</div>
            <div class="receipt-line"></div>
            <div class="receipt-row"><span>Date:</span><span>${new Date(receiptDate).toLocaleDateString()}</span></div>
            <div class="receipt-row"><span>Receipt No.:</span><span>${generateReceiptNumber()}</span></div>
            <div class="receipt-row"><span>Received From:</span><span>${escapeHtml(source)}</span></div>
            <div class="receipt-line"></div>
            <div class="receipt-row"><strong>Grant: ${escapeHtml(grantName)}</strong></div>
            <div class="receipt-line"></div>
            <div><strong>Amount in words</strong></div>
            <div>${amountInWords || 'Zero'} Shillings Only.</div>
            <div class="receipt-line"></div>
            <div><strong>Particulars</strong></div>
            ${currentDistributions.map(d => `
                <div class="receipt-row"><span>${escapeHtml(d.vote_head_name)}</span><span>KES ${d.amount.toLocaleString()}</span></div>
            `).join('')}
            ${currentDistributions.length === 0 ? '<div class="receipt-row"><span colspan="2">No distributions added</span></div>' : ''}
            <div class="receipt-line"></div>
            <div class="receipt-row receipt-total">
                <span><strong>Total</strong></span>
                <span><strong>KES ${totalDistribution.toLocaleString()}</strong></span>
            </div>
            <div class="receipt-line"></div>
            <div class="receipt-row"><span>Payment Mode:</span><span>${escapeHtml(paymentMode)}</span></div>
            ${referenceNo && referenceNo !== '-' ? `<div class="receipt-row"><span>Reference:</span><span>${escapeHtml(referenceNo)}</span></div>` : ''}
            ${notes ? `<div class="receipt-line"></div><div class="receipt-row"><span>Notes:</span><span>${escapeHtml(notes)}</span></div>` : ''}
            <div class="receipt-footer">
                <div>Authorized Signature: ___________________</div>
            </div>
        </div>
    `;
}

function updateReceiptPreview() {
    const previewHTML = getPreviewHTML();
    const previewContainer = document.getElementById('receiptPreviewContent');
    if (previewContainer) {
        previewContainer.innerHTML = previewHTML;
    }
}

// ============================================================
// PDF DOWNLOAD FUNCTION (Using API Endpoint)
// ============================================================
// ============================================================
// PDF DOWNLOAD FUNCTION (Using API Endpoint)
// ============================================================
async function downloadReceiptAsPDF() {
    const downloadBtn = document.getElementById('downloadPdfBtn');
    const originalText = downloadBtn.innerHTML;
    
    // Check if we're viewing an existing grant (from table click)
    if (window.currentPrintGrant && window.currentPrintGrant.id) {
        downloadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating PDF...';
        downloadBtn.disabled = true;
        
        try {
            const response = await fetch('/feesystem/api/feesystem/generate_grant_receipt_pdf.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ grant_id: window.currentPrintGrant.id })
            });
            const data = await response.json();
            
            if (data.success && data.pdf_url) {
                // Open PDF in new tab
                window.open(data.pdf_url, '_blank');
                await Swal.fire('Success', 'Receipt PDF generated successfully!', 'success');
            } else {
                await Swal.fire('Error', data.message || 'Failed to generate PDF', 'error');
            }
        } catch (error) {
            console.error('PDF generation error:', error);
            await Swal.fire('Error', 'Failed to generate PDF', 'error');
        } finally {
            downloadBtn.innerHTML = originalText;
            downloadBtn.disabled = false;
        }
        return;
    }
    
    // Check if we're creating a new grant (form has data)
    const grantName = document.getElementById('grantName')?.value;
    const receiptDate = document.getElementById('receiptDate')?.value;
    const source = document.getElementById('grantSource')?.value;
    const paymentMode = document.getElementById('paymentMode')?.value;
    
    // Check if form has meaningful data (not empty or placeholder)
    if (grantName && grantName !== 'Select or fill grant details' && receiptDate && source && paymentMode) {
        if (distributions.length === 0) {
            await Swal.fire('Error', 'Please add at least one vote head distribution first', 'error');
            return;
        }
        
        const totalAmount = distributions.reduce((sum, d) => sum + d.amount, 0);
        if (totalAmount === 0) {
            await Swal.fire('Error', 'Please add distributions with valid amounts', 'error');
            return;
        }
        
        downloadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating PDF...';
        downloadBtn.disabled = true;
        
        try {
            const response = await fetch('/feesystem/api/feesystem/generate_grant_receipt_preview.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    name: grantName,
                    receipt_date: receiptDate,
                    source: source,
                    payment_mode: paymentMode,
                    reference_no: document.getElementById('referenceNo').value || '',
                    total_amount: totalAmount,
                    distributions: distributions,
                    notes: document.getElementById('grantNotes').value || ''
                })
            });
            const data = await response.json();
            
            if (data.success && data.pdf_url) {
                window.open(data.pdf_url, '_blank');
                await Swal.fire('Success', 'Preview PDF generated successfully!', 'success');
            } else {
                await Swal.fire('Error', data.message || 'Failed to generate PDF', 'error');
            }
        } catch (error) {
            console.error('PDF generation error:', error);
            await Swal.fire('Error', 'Failed to generate PDF', 'error');
        } finally {
            downloadBtn.innerHTML = originalText;
            downloadBtn.disabled = false;
        }
        return;
    }
    
    // If no grant is selected and no form data, show error
    await Swal.fire('Info', 'Please select a grant from the table or fill the form to generate a receipt', 'info');
}
// ============================================================
// MODAL MANAGEMENT
// ============================================================
const grantModal = document.getElementById('grantModal');

function openGrantModal() {
    distributions = [];
    window.currentPrintGrant = null;  // Reset this when creating new grant
    window.currentViewDistributions = null;  // Also reset this
    document.getElementById('grantForm').reset();
    document.getElementById('receiptDate').value = new Date().toISOString().split('T')[0];
    renderDistributions();
    updateReceiptPreview();
    grantModal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeGrantModal() {
    grantModal.classList.add('hidden');
    document.body.style.overflow = '';
}

async function saveGrant() {
    const grantName = document.getElementById('grantName').value;
    const receiptDate = document.getElementById('receiptDate').value;
    const source = document.getElementById('grantSource').value;
    const paymentMode = document.getElementById('paymentMode').value;
    const referenceNo = document.getElementById('referenceNo').value;
    const notes = document.getElementById('grantNotes').value;
    
    if (!grantName || !receiptDate || !source || !paymentMode) {
        await Swal.fire('Error', 'Please fill all required fields', 'error');
        return;
    }
    
    if (distributions.length === 0) {
        await Swal.fire('Error', 'Please add at least one vote head distribution', 'error');
        return;
    }
    
    const totalAmount = distributions.reduce((sum, d) => sum + d.amount, 0);
    
    const saveBtn = document.getElementById('saveGrantBtn');
    const originalText = saveBtn.innerHTML;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Creating...';
    saveBtn.disabled = true;
    
    try {
        const response = await fetch('/feesystem/api/feesystem/add_grant.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                name: grantName,
                receipt_date: receiptDate,
                source: source,
                payment_mode: paymentMode,
                reference_no: referenceNo,
                total_amount: totalAmount,
                distributions: distributions,
                notes: notes
            })
        });
        const data = await response.json();
        
        if (data.success) {
            await Swal.fire('Success', 'Grant created successfully!', 'success');
            closeGrantModal();
            await loadGrants();
            distributions = [];
            updateReceiptPreview();
        } else {
            await Swal.fire('Error', data.message || 'Failed to create grant', 'error');
        }
    } catch (error) {
        console.error('Error saving grant:', error);
        await Swal.fire('Error', 'An error occurred', 'error');
    } finally {
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
    }
}

async function viewGrant(grantId) {
    try {
        const response = await fetch('/feesystem/api/feesystem/get_grant_details.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ grant_id: grantId })
        });
        const data = await response.json();
        if (data.success) {
            const grant = data.grant;
            // Make sure to set this BEFORE any other operations
            window.currentPrintGrant = grant;
            
            if (grant.distributions && grant.distributions.length > 0) {
                window.currentViewDistributions = grant.distributions.map(d => ({
                    vote_head_name: d.vote_head_name,
                    amount: parseFloat(d.amount)
                }));
            } else {
                window.currentViewDistributions = [];
            }
            
            const tempName = document.getElementById('grantName');
            const tempDate = document.getElementById('receiptDate');
            const tempSource = document.getElementById('grantSource');
            const tempPaymentMode = document.getElementById('paymentMode');
            const tempReference = document.getElementById('referenceNo');
            const tempNotes = document.getElementById('grantNotes');
            
            if (tempName) tempName.value = grant.name;
            if (tempDate) tempDate.value = grant.receipt_date;
            if (tempSource) tempSource.value = grant.source;
            if (tempPaymentMode) tempPaymentMode.value = grant.payment_mode;
            if (tempReference) tempReference.value = grant.reference_no || '';
            if (tempNotes) tempNotes.value = grant.notes || '';
            
            updateReceiptPreview();
            
            document.getElementById('viewGrantContent').innerHTML = `
                <div class="receipt-preview">
                    <div class="receipt-header">
                        <div style="font-size: 13px; font-weight: bold;">${escapeHtml(grant.school_name || schoolInfo.school_name)}</div>
                        <div style="font-size: 9px;">${escapeHtml(grant.school_address || '')}</div>
                    </div>
                    <div class="receipt-title">OFFICIAL RECEIPT</div>
                    <div class="receipt-line"></div>
                    <div class="receipt-row"><span>Date:</span><span>${new Date(grant.receipt_date).toLocaleDateString()}</span></div>
                    <div class="receipt-row"><span>Receipt No.:</span><span>${escapeHtml(grant.grant_number)}</span></div>
                    <div class="receipt-row"><span>Received From:</span><span>${escapeHtml(grant.source)}</span></div>
                    <div class="receipt-line"></div>
                    <div><strong>Amount in words</strong></div>
                    <div>${numberToWords(Math.floor(grant.total_amount))} Shillings Only.</div>
                    <div class="receipt-line"></div>
                    <div><strong>Particulars</strong></div>
                    ${grant.distributions && grant.distributions.map(d => `
                        <div class="receipt-row"><span>${escapeHtml(d.vote_head_name)}</span><span>KES ${parseFloat(d.amount).toLocaleString()}</span></div>
                    `).join('')}
                    <div class="receipt-line"></div>
                    <div class="receipt-row receipt-total">
                        <span><strong>Total</strong></span>
                        <span><strong>KES ${parseFloat(grant.total_amount).toLocaleString()}</strong></span>
                    </div>
                    <div class="receipt-line"></div>
                    <div class="receipt-row"><span>Payment Mode:</span><span>${escapeHtml(grant.payment_mode)}</span></div>
                    ${grant.reference_no ? `<div class="receipt-row"><span>Reference:</span><span>${escapeHtml(grant.reference_no)}</span></div>` : ''}
                    ${grant.notes ? `<div class="receipt-line"></div><div class="receipt-row"><span>Notes:</span><span>${escapeHtml(grant.notes)}</span></div>` : ''}
                    <div class="receipt-footer">
                        <div>Authorized Signature: ___________________</div>
                    </div>
                </div>
            `;
            document.getElementById('viewGrantModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
    } catch (error) {
        console.error('Error viewing grant:', error);
        await Swal.fire('Error', 'Failed to load grant details', 'error');
    }
}

function printReceipt() {
    if (window.currentPrintGrant) {
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <html><head><title>Grant Receipt - ${window.currentPrintGrant.grant_number}</title>
            <style>
                body { font-family: 'Courier New', monospace; font-size: 12px; padding: 20px; }
                .receipt-preview { max-width: 400px; margin: 0 auto; }
                .receipt-header { text-align: center; margin-bottom: 15px; border-bottom: 1px dashed #000; }
                .receipt-title { font-size: 14px; font-weight: bold; text-align: center; margin: 10px 0; }
                .receipt-line { border-top: 1px dashed #000; margin: 8px 0; }
                .receipt-row { display: flex; justify-content: space-between; margin: 4px 0; }
                .receipt-total { font-weight: bold; border-top: 1px solid #000; margin-top: 8px; padding-top: 8px; }
                .receipt-footer { margin-top: 20px; text-align: center; border-top: 1px dashed #000; padding-top: 10px; }
            </style></head>
            <body>${document.getElementById('viewGrantContent').innerHTML}<script>window.print();<\/script></body></html>
        `);
        printWindow.document.close();
    }
}

async function deleteGrant(grantId) {
    const result = await Swal.fire({
        title: 'Are you sure?',
        text: 'This action cannot be undone!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Yes, delete it!'
    });
    if (result.isConfirmed) {
        try {
            const response = await fetch('/feesystem/api/feesystem/delete_grant.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ grant_id: grantId })
            });
            const data = await response.json();
            if (data.success) {
                await Swal.fire('Deleted!', 'Grant has been deleted.', 'success');
                await loadGrants();
                distributions = [];
                updateReceiptPreview();
            } else {
                await Swal.fire('Error', data.message || 'Failed to delete grant', 'error');
            }
        } catch (error) {
            await Swal.fire('Error', 'An error occurred', 'error');
        }
    }
}

// ============================================================
// EVENT LISTENERS
// ============================================================
document.getElementById('addGrantBtn').addEventListener('click', openGrantModal);
document.getElementById('closeGrantModal').addEventListener('click', closeGrantModal);
document.getElementById('cancelGrantBtn').addEventListener('click', closeGrantModal);
document.getElementById('saveGrantBtn').addEventListener('click', saveGrant);
document.getElementById('addDistributionBtn').addEventListener('click', addDistribution);
document.getElementById('downloadPdfBtn').addEventListener('click', downloadReceiptAsPDF);
document.getElementById('closeViewGrantModal').addEventListener('click', () => {
    document.getElementById('viewGrantModal').classList.add('hidden');
    document.body.style.overflow = '';
    if (document.getElementById('grantForm') && document.getElementById('grantForm').style.display !== 'none') {
        updateReceiptPreview();
    } else {
        distributions = [];
        updateReceiptPreview();
    }
});
document.getElementById('closeViewGrantModalBtn').addEventListener('click', () => {
    document.getElementById('viewGrantModal').classList.add('hidden');
    document.body.style.overflow = '';
    if (document.getElementById('grantForm') && document.getElementById('grantForm').style.display !== 'none') {
        updateReceiptPreview();
    } else {
        distributions = [];
        updateReceiptPreview();
    }
});
document.getElementById('printReceiptBtn').addEventListener('click', printReceipt);

const formInputs = ['grantName', 'receiptDate', 'grantSource', 'paymentMode', 'referenceNo', 'grantNotes'];
formInputs.forEach(id => {
    const el = document.getElementById(id);
    if (el) {
        el.addEventListener('input', updateReceiptPreview);
        el.addEventListener('change', updateReceiptPreview);
    }
});

// ============================================================
// INITIALIZE
// ============================================================
document.addEventListener('DOMContentLoaded', async () => {
    await loadSchoolInfo();
    await loadVoteHeads();
    await loadGrants();
    updateReceiptPreview();
});
</script>

<?php include_once('../../includes/footer.php'); ?>