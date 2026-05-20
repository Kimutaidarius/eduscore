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
.stats-card {
    transition: all 0.3s ease;
}
.stats-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
}
.transaction-table tr:hover {
    background-color: rgba(79, 70, 229, 0.05);
}
.filter-input, .filter-select {
    transition: all 0.2s ease;
}
.filter-input:focus, .filter-select:focus {
    border-color: #4f46e5;
    ring: 2px solid #4f46e5;
}
.balance-positive {
    color: #10b981;
}
.balance-negative {
    color: #ef4444;
}
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 99999;
}
.loading-spinner {
    width: 50px;
    height: 50px;
    border: 3px solid #f3f3f3;
    border-top: 3px solid #4f46e5;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>

<main class="main-content flex-grow flex flex-col">
  <header class="bg-white shadow-sm dark:bg-gray-800 dark:border-gray-700">
    <div class="px-4 py-3 flex justify-between items-center">
      <div class="flex items-center">
        <button id="mobile-toggle" class="mr-4 text-gray-500 hover:text-indigo-600 focus:outline-none lg:hidden">
          <i class="fas fa-bars text-xl"></i>
        </button>
        <h1 class="text-xl font-semibold text-gray-800 dark:text-white">Pocket Money Management</h1>
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
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
      <div class="stats-card bg-gradient-to-r from-blue-500 to-blue-600 rounded-lg shadow-lg p-5 text-white">
        <div class="flex items-center justify-between">
          <div><p class="text-blue-100 text-sm">Total Students</p><p class="text-3xl font-bold mt-1" id="totalStudents">0</p></div>
          <i class="fas fa-users text-4xl opacity-50"></i>
        </div>
      </div>
      <div class="stats-card bg-gradient-to-r from-green-500 to-green-600 rounded-lg shadow-lg p-5 text-white">
        <div class="flex items-center justify-between">
          <div><p class="text-green-100 text-sm">Total Deposits</p><p class="text-3xl font-bold mt-1" id="totalDeposits">KES 0.00</p></div>
          <i class="fas fa-arrow-down text-4xl opacity-50"></i>
        </div>
      </div>
      <div class="stats-card bg-gradient-to-r from-red-500 to-red-600 rounded-lg shadow-lg p-5 text-white">
        <div class="flex items-center justify-between">
          <div><p class="text-red-100 text-sm">Total Withdrawals</p><p class="text-3xl font-bold mt-1" id="totalWithdrawals">KES 0.00</p></div>
          <i class="fas fa-arrow-up text-4xl opacity-50"></i>
        </div>
      </div>
      <div class="stats-card bg-gradient-to-r from-purple-500 to-purple-600 rounded-lg shadow-lg p-5 text-white">
        <div class="flex items-center justify-between">
          <div><p class="text-purple-100 text-sm">Outstanding Balance</p><p class="text-3xl font-bold mt-1" id="outstandingBalance">KES 0.00</p></div>
          <i class="fas fa-wallet text-4xl opacity-50"></i>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4 mb-6">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Class</label><select id="class_filter" class="filter-select w-full px-3 py-2 border rounded-lg dark:bg-gray-700"><option value="">All Classes</option></select></div>
        <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Student</label><input type="text" id="student_search" placeholder="Search by name or admission..." class="filter-input w-full px-3 py-2 border rounded-lg dark:bg-gray-700"></div>
        <div class="flex items-end"><button id="refreshDataBtn" class="w-full px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition"><i class="fas fa-sync-alt mr-2"></i>Refresh</button></div>
      </div>
    </div>

    <!-- Student Selection -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4 mb-6">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Select Student</label><select id="student_id" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700"><option value="">Choose a student...</option></select></div>
        <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Current Balance</label><div class="text-2xl font-bold" id="currentBalance">KES 0.00</div></div>
        <div class="flex gap-3 items-end"><button id="depositBtn" class="flex-1 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition"><i class="fas fa-plus-circle mr-2"></i>Deposit</button><button id="withdrawBtn" class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition"><i class="fas fa-minus-circle mr-2"></i>Withdraw</button></div>
      </div>
    </div>

    <!-- Transaction History -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
      <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 border-b"><h3 class="font-semibold text-gray-800 dark:text-white"><i class="fas fa-history mr-2 text-indigo-500"></i>Transaction History</h3></div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm transaction-table">
          <thead class="bg-gray-50 dark:bg-gray-700">
            <tr><th class="px-4 py-3 text-left">Date</th><th class="px-4 py-3 text-left">Transaction ID</th><th class="px-4 py-3 text-left">Student</th><th class="px-4 py-3 text-left">Type</th><th class="px-4 py-3 text-right">Amount</th><th class="px-4 py-3 text-left">Description</th><th class="px-4 py-3 text-left">Reference</th><th class="px-4 py-3 text-left">Processed By</th><th class="px-4 py-3 text-center">Actions</th></tr>
          </thead>
          <tbody id="transactionHistoryBody"><tr><td colspan="9" class="text-center py-8 text-gray-500">Loading transactions...</td></tr></tbody>
        </table>
      </div>
    </div>
  </div>
</main>

<!-- Modals -->
<div id="depositModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center" style="z-index: 9999;"><div class="bg-white dark:bg-gray-800 w-full max-w-md rounded-lg shadow-xl mx-4" style="z-index: 10000;"><div class="border-b border-gray-200 dark:border-gray-700 px-6 py-4 flex justify-between items-center"><h3 class="text-lg font-semibold"><i class="fas fa-plus-circle text-green-500 mr-2"></i>Deposit Pocket Money</h3><button class="closeDepositModal text-gray-400 hover:text-gray-500 text-2xl">&times;</button></div><div class="px-6 py-4"><form id="depositForm"><div class="mb-4"><label class="block text-sm font-medium mb-1">Student</label><input type="text" id="deposit_student_name" readonly class="w-full px-3 py-2 border rounded-lg bg-gray-100 dark:bg-gray-600"></div><div class="mb-4"><label class="block text-sm font-medium mb-1">Amount <span class="text-red-500">*</span></label><input type="number" id="deposit_amount" required step="0.01" min="0.01" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700"></div><div class="mb-4"><label class="block text-sm font-medium mb-1">Transaction Date</label><input type="date" id="deposit_date" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700"></div><div class="mb-4"><label class="block text-sm font-medium mb-1">Reference</label><input type="text" id="deposit_reference" placeholder="e.g., DEP-001" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700"></div><div class="mb-4"><label class="block text-sm font-medium mb-1">Description</label><textarea id="deposit_description" rows="2" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700"></textarea></div><div class="flex justify-end gap-3"><button type="button" class="closeDepositModal px-4 py-2 border rounded-lg">Cancel</button><button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">Confirm Deposit</button></div></form></div></div></div>

<div id="withdrawalModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center" style="z-index: 9999;"><div class="bg-white dark:bg-gray-800 w-full max-w-md rounded-lg shadow-xl mx-4" style="z-index: 10000;"><div class="border-b border-gray-200 dark:border-gray-700 px-6 py-4 flex justify-between items-center"><h3 class="text-lg font-semibold"><i class="fas fa-minus-circle text-red-500 mr-2"></i>Withdraw Pocket Money</h3><button class="closeWithdrawalModal text-gray-400 hover:text-gray-500 text-2xl">&times;</button></div><div class="px-6 py-4"><form id="withdrawalForm"><div class="mb-4"><label class="block text-sm font-medium mb-1">Student</label><input type="text" id="withdrawal_student_name" readonly class="w-full px-3 py-2 border rounded-lg bg-gray-100 dark:bg-gray-600"></div><div class="mb-4"><label class="block text-sm font-medium mb-1">Current Balance</label><div id="withdrawal_current_balance" class="text-xl font-bold text-green-600">KES 0.00</div></div><div class="mb-4"><label class="block text-sm font-medium mb-1">Amount <span class="text-red-500">*</span></label><input type="number" id="withdrawal_amount" required step="0.01" min="0.01" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700"></div><div class="mb-4"><label class="block text-sm font-medium mb-1">Transaction Date</label><input type="date" id="withdrawal_date" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700"></div><div class="mb-4"><label class="block text-sm font-medium mb-1">Reference</label><input type="text" id="withdrawal_reference" placeholder="e.g., WTH-001" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700"></div><div class="mb-4"><label class="block text-sm font-medium mb-1">Description</label><textarea id="withdrawal_description" rows="2" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700"></textarea></div><div class="flex justify-end gap-3"><button type="button" class="closeWithdrawalModal px-4 py-2 border rounded-lg">Cancel</button><button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">Confirm Withdrawal</button></div></form></div></div></div>

<div id="viewTransactionModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center overflow-y-auto" style="z-index: 9999;"><div class="bg-white dark:bg-gray-800 w-full max-w-2xl rounded-lg shadow-xl mx-4" style="z-index: 10000;"><div class="border-b border-gray-200 dark:border-gray-700 px-6 py-4 flex justify-between items-center"><h3 class="text-lg font-semibold"><i class="fas fa-receipt text-blue-500 mr-2"></i>Transaction Details</h3><button class="closeViewModal text-gray-400 hover:text-gray-500 text-2xl">&times;</button></div><div class="px-6 py-4" id="transactionDetailsContent"><div class="text-center py-8">Loading...</div></div></div></div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const schoolId = <?php echo json_encode($school_id); ?>;
const userId = <?php echo json_encode($user_id); ?>;
let currentStudentBalance = 0;

function escapeHtml(text) { if (!text) return ''; const div = document.createElement('div'); div.textContent = text; return div.innerHTML; }
function lockBodyScroll() { document.body.style.overflow = 'hidden'; document.body.classList.add('modal-open'); }
function unlockBodyScroll() { document.body.style.overflow = ''; document.body.classList.remove('modal-open'); }

// Show/hide loading
function showLoading() { document.getElementById('loadingOverlay')?.remove(); const div = document.createElement('div'); div.id = 'loadingOverlay'; div.className = 'loading-overlay'; div.innerHTML = '<div class="loading-spinner"></div>'; document.body.appendChild(div); }
function hideLoading() { document.getElementById('loadingOverlay')?.remove(); }

// Load all data via AJAX
async function loadAllData() {
    showLoading();
    try {
        await Promise.all([loadClasses(), loadStudents(), loadStats(), loadTransactionHistory()]);
    } catch (error) { console.error('Error loading data:', error); }
    finally { hideLoading(); }
}

async function loadClasses() {
    try {
        const response = await fetch('/feesystem/api/feesystem/get_classes.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({}) });
        const data = await response.json();
        if (data.success && data.classes) {
            let options = '<option value="">All Classes</option>';
            data.classes.forEach(cls => { const className = cls.stream ? `${cls.class_level} - ${cls.stream}` : cls.class_level; options += `<option value="${cls.id}">${escapeHtml(className)}</option>`; });
            document.getElementById('class_filter').innerHTML = options;
        }
    } catch (error) { console.error('Error loading classes:', error); }
}

async function loadStudents() {
    const classId = document.getElementById('class_filter').value;
    const search = document.getElementById('student_search').value;
    try {
        const response = await fetch('/feesystem/api/feesystem/get_students.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ school_id: schoolId, class_id: classId, search: search }) });
        const data = await response.json();
        if (data.success && data.students) {
            let options = '<option value="">Choose a student...</option>';
            data.students.forEach(s => { const studentName = s.full_name || `${s.FirstName} ${s.LastName || ''}`; options += `<option value="${s.id}">${escapeHtml(studentName)} (${escapeHtml(s.admission_no || s.AdmNo)}) - ${escapeHtml(s.class_name)}</option>`; });
            document.getElementById('student_id').innerHTML = options;
            document.getElementById('totalStudents').innerText = data.students.length;
        }
    } catch (error) { console.error('Error loading students:', error); }
}

async function loadStats() {
    const classId = document.getElementById('class_filter').value;
    try {
        const response = await fetch('/feesystem/api/pocketmoney/get_stats.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ school_id: schoolId, class_id: classId }) });
        const data = await response.json();
        if (data.success) {
            document.getElementById('totalDeposits').innerHTML = `KES ${parseFloat(data.total_deposits || 0).toLocaleString()}`;
            document.getElementById('totalWithdrawals').innerHTML = `KES ${parseFloat(data.total_withdrawals || 0).toLocaleString()}`;
            document.getElementById('outstandingBalance').innerHTML = `KES ${parseFloat(data.outstanding_balance || 0).toLocaleString()}`;
        }
    } catch (error) { console.error('Error loading stats:', error); }
}

async function loadStudentBalance(studentId) {
    if (!studentId) return;
    try {
        const response = await fetch('/feesystem/api/pocketmoney/get_balance.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ school_id: schoolId, student_id: studentId }) });
        const data = await response.json();
        if (data.success) {
            currentStudentBalance = data.balance;
            const balanceEl = document.getElementById('currentBalance');
            balanceEl.innerHTML = `KES ${parseFloat(data.balance).toLocaleString()}`;
            balanceEl.className = `text-2xl font-bold ${data.balance < 0 ? 'text-red-600' : 'text-green-600'}`;
        }
    } catch (error) { console.error('Error loading balance:', error); }
}

async function loadTransactionHistory() {
    const studentId = document.getElementById('student_id').value;
    try {
        const response = await fetch('/feesystem/api/pocketmoney/get_transactions.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ school_id: schoolId, student_id: studentId || '' }) });
        const data = await response.json();
        const tbody = document.getElementById('transactionHistoryBody');
        if (data.success && data.transactions && data.transactions.length > 0) {
            tbody.innerHTML = data.transactions.map(t => `<tr class="border-b dark:border-gray-700 hover:bg-gray-50">
                <td class="px-4 py-3">${t.transaction_date}</td>
                <td class="px-4 py-3">${escapeHtml(t.transaction_no)}</td>
                <td class="px-4 py-3">${escapeHtml(t.student_name)} (${escapeHtml(t.admission_no)})</td>
                <td class="px-4 py-3"><span class="px-2 py-1 rounded-full text-xs ${t.type === 'deposit' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">${t.type === 'deposit' ? 'Deposit' : 'Withdrawal'}</span></td>
                <td class="px-4 py-3 text-right ${t.type === 'deposit' ? 'text-green-600' : 'text-red-600'}">${t.type === 'deposit' ? '+' : '-'} KES ${parseFloat(t.amount).toLocaleString()}</td>
                <td class="px-4 py-3">${escapeHtml(t.description || '-')}</td>
                <td class="px-4 py-3">${escapeHtml(t.reference || '-')}</td>
                <td class="px-4 py-3">${escapeHtml(t.processed_by_name)}</td>
                <td class="px-4 py-3 text-center"><button onclick="viewTransaction(${t.id})" class="text-blue-500 hover:text-blue-700 mr-2"><i class="fas fa-eye"></i></button><button onclick="printReceipt(${t.id})" class="text-gray-500 hover:text-gray-700"><i class="fas fa-print"></i></button></td>
            </tr>`).join('');
        } else { tbody.innerHTML = `<tr><td colspan="9" class="text-center py-8 text-gray-500">No transactions found</td></tr>`; }
    } catch (error) { console.error('Error loading transactions:', error); tbody.innerHTML = `<tr><td colspan="9" class="text-center py-8 text-red-500">Error loading transactions</td></tr>`; }
}

// View transaction with running balance
window.viewTransaction = async (id) => {
    showLoading();
    try {
        const response = await fetch('/feesystem/api/pocketmoney/get_transaction.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: id, school_id: schoolId }) });
        const data = await response.json();
        if (data.success) {
            const t = data.transaction;
            const allTxnsResponse = await fetch('/feesystem/api/pocketmoney/get_transactions.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ school_id: schoolId, student_id: t.student_id }) });
            const allTxnsData = await allTxnsResponse.json();
            let runningBalance = 0;
            if (allTxnsData.success && allTxnsData.transactions) {
                for (let txn of allTxnsData.transactions) {
                    if (txn.type === 'deposit') runningBalance += parseFloat(txn.amount);
                    else runningBalance -= parseFloat(txn.amount);
                    if (txn.id == id) break;
                }
            }
            document.getElementById('transactionDetailsContent').innerHTML = `<div class="space-y-4"><div class="grid grid-cols-2 gap-4 p-3 bg-gray-50 dark:bg-gray-700 rounded">
                <div><strong>Transaction No:</strong> ${escapeHtml(t.transaction_no)}</div><div><strong>Date:</strong> ${t.transaction_date}</div>
                <div><strong>Student:</strong> ${escapeHtml(t.student_name)}</div><div><strong>Admission No:</strong> ${escapeHtml(t.admission_no)}</div>
                <div><strong>Type:</strong> <span class="px-2 py-1 rounded-full text-xs ${t.type === 'deposit' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">${t.type === 'deposit' ? 'Deposit' : 'Withdrawal'}</span></div>
                <div><strong>Amount:</strong> <span class="font-bold ${t.type === 'deposit' ? 'text-green-600' : 'text-red-600'}">${t.type === 'deposit' ? '+' : '-'} KES ${parseFloat(t.amount).toLocaleString()}</span></div>
                <div><strong>Reference:</strong> ${escapeHtml(t.reference || '-')}</div><div><strong>Processed By:</strong> ${escapeHtml(t.processed_by_name || 'System')}</div>
                <div class="col-span-2"><strong>Description:</strong><p class="mt-1">${escapeHtml(t.description || '-')}</p></div>
                <div class="col-span-2"><strong>Balance After:</strong> KES ${runningBalance.toLocaleString(undefined, {minimumFractionDigits: 2})}</div>
            </div></div>`;
            document.getElementById('viewTransactionModal').classList.remove('hidden');
            lockBodyScroll();
        } else { Swal.fire('Error', 'Failed to load transaction details', 'error'); }
    } catch (error) { Swal.fire('Error', 'Failed to load transaction details', 'error'); }
    finally { hideLoading(); }
};

window.printReceipt = async (id) => {
    try {
        const response = await fetch('/feesystem/api/pocketmoney/get_transaction.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: id, school_id: schoolId }) });
        const data = await response.json();
        if (data.success) {
            const t = data.transaction;
            const allTxnsResponse = await fetch('/feesystem/api/pocketmoney/get_transactions.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ school_id: schoolId, student_id: t.student_id }) });
            const allTxnsData = await allTxnsResponse.json();
            let runningBalance = 0;
            if (allTxnsData.success && allTxnsData.transactions) {
                for (let txn of allTxnsData.transactions) {
                    if (txn.type === 'deposit') runningBalance += parseFloat(txn.amount);
                    else runningBalance -= parseFloat(txn.amount);
                    if (txn.id == id) break;
                }
            }
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`<!DOCTYPE html><html><head><title>Pocket Money Receipt - ${t.transaction_no}</title><style>body{font-family:'Courier New',monospace;padding:20px}@media print{body{margin:0;padding:0}.no-print{display:none}}</style></head><body>
                <div style="text-align:center;border-bottom:2px solid #000;padding-bottom:10px;margin-bottom:20px;"><h2>POCKET MONEY RECEIPT</h2></div>
                <p><strong>Receipt No:</strong> ${escapeHtml(t.transaction_no)}</p><p><strong>Date:</strong> ${t.transaction_date}</p>
                <p><strong>Student:</strong> ${escapeHtml(t.student_name)}</p><p><strong>Admission No:</strong> ${escapeHtml(t.admission_no)}</p>
                <p><strong>Transaction Type:</strong> ${t.type === 'deposit' ? 'DEPOSIT' : 'WITHDRAWAL'}</p>
                <p><strong>Amount:</strong> KES ${parseFloat(t.amount).toLocaleString()}</p>
                ${t.reference ? `<p><strong>Reference:</strong> ${escapeHtml(t.reference)}</p>` : ''}
                ${t.description ? `<p><strong>Description:</strong> ${escapeHtml(t.description)}</p>` : ''}
                <p><strong>Balance After:</strong> KES ${runningBalance.toLocaleString(undefined, {minimumFractionDigits: 2})}</p>
                <div class="no-print" style="text-align:center;margin-top:20px;"><button onclick="window.print()">Print</button> <button onclick="window.close()">Close</button></div>
            </body></html>`);
            printWindow.document.close();
        } else { Swal.fire('Error', 'Failed to load transaction details', 'error'); }
    } catch (error) { Swal.fire('Error', 'Failed to print receipt', 'error'); }
};

// Deposit
document.getElementById('depositBtn').addEventListener('click', () => {
    const studentId = document.getElementById('student_id').value;
    if (!studentId) { Swal.fire('Warning', 'Please select a student first', 'warning'); return; }
    const studentName = document.getElementById('student_id').options[document.getElementById('student_id').selectedIndex]?.text || '';
    document.getElementById('deposit_student_name').value = studentName;
    document.getElementById('deposit_amount').value = '';
    document.getElementById('deposit_reference').value = '';
    document.getElementById('deposit_description').value = '';
    document.getElementById('deposit_date').value = new Date().toISOString().split('T')[0];
    document.getElementById('depositModal').classList.remove('hidden');
    lockBodyScroll();
});

document.getElementById('depositForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const studentId = document.getElementById('student_id').value;
    const amount = document.getElementById('deposit_amount').value;
    const depositDate = document.getElementById('deposit_date').value;
    const reference = document.getElementById('deposit_reference').value;
    const description = document.getElementById('deposit_description').value;
    if (!studentId || !amount || amount <= 0) { Swal.fire('Error', 'Please select a student and enter a valid amount', 'error'); return; }
    showLoading();
    try {
        const response = await fetch('/feesystem/api/pocketmoney/make_deposit.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ school_id: schoolId, user_id: userId, student_id: studentId, amount: amount, transaction_date: depositDate, reference: reference, description: description }) });
        const data = await response.json();
        if (data.success) {
            Swal.fire('Success', 'Deposit recorded successfully!', 'success');
            document.getElementById('depositModal').classList.add('hidden');
            unlockBodyScroll();
            await Promise.all([loadStudentBalance(studentId), loadTransactionHistory(), loadStats()]);
        } else { Swal.fire('Error', data.message || 'Failed to record deposit', 'error'); }
    } catch (error) { Swal.fire('Error', 'An error occurred', 'error'); }
    finally { hideLoading(); }
});

// Withdrawal
document.getElementById('withdrawBtn').addEventListener('click', () => {
    const studentId = document.getElementById('student_id').value;
    if (!studentId) { Swal.fire('Warning', 'Please select a student first', 'warning'); return; }
    const studentName = document.getElementById('student_id').options[document.getElementById('student_id').selectedIndex]?.text || '';
    document.getElementById('withdrawal_student_name').value = studentName;
    document.getElementById('withdrawal_current_balance').innerHTML = `KES ${currentStudentBalance.toLocaleString()}`;
    document.getElementById('withdrawal_amount').value = '';
    document.getElementById('withdrawal_reference').value = '';
    document.getElementById('withdrawal_description').value = '';
    document.getElementById('withdrawal_date').value = new Date().toISOString().split('T')[0];
    document.getElementById('withdrawalModal').classList.remove('hidden');
    lockBodyScroll();
});

document.getElementById('withdrawalForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const studentId = document.getElementById('student_id').value;
    const amount = document.getElementById('withdrawal_amount').value;
    const withdrawalDate = document.getElementById('withdrawal_date').value;
    const reference = document.getElementById('withdrawal_reference').value;
    const description = document.getElementById('withdrawal_description').value;
    if (!studentId || !amount || amount <= 0) { Swal.fire('Error', 'Please select a student and enter a valid amount', 'error'); return; }
    if (parseFloat(amount) > currentStudentBalance) { Swal.fire('Error', 'Insufficient balance!', 'error'); return; }
    showLoading();
    try {
        const response = await fetch('/feesystem/api/pocketmoney/make_withdrawal.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ school_id: schoolId, user_id: userId, student_id: studentId, amount: amount, transaction_date: withdrawalDate, reference: reference, description: description }) });
        const data = await response.json();
        if (data.success) {
            Swal.fire('Success', 'Withdrawal recorded successfully!', 'success');
            document.getElementById('withdrawalModal').classList.add('hidden');
            unlockBodyScroll();
            await Promise.all([loadStudentBalance(studentId), loadTransactionHistory(), loadStats()]);
        } else { Swal.fire('Error', data.message || 'Failed to record withdrawal', 'error'); }
    } catch (error) { Swal.fire('Error', 'An error occurred', 'error'); }
    finally { hideLoading(); }
});

// Close modals
document.querySelectorAll('.closeDepositModal, .closeWithdrawalModal, .closeViewModal').forEach(btn => btn.addEventListener('click', () => { document.getElementById('depositModal')?.classList.add('hidden'); document.getElementById('withdrawalModal')?.classList.add('hidden'); document.getElementById('viewTransactionModal')?.classList.add('hidden'); unlockBodyScroll(); }));
window.addEventListener('click', (e) => { if (e.target === document.getElementById('depositModal')) document.getElementById('depositModal').classList.add('hidden'); if (e.target === document.getElementById('withdrawalModal')) document.getElementById('withdrawalModal').classList.add('hidden'); if (e.target === document.getElementById('viewTransactionModal')) document.getElementById('viewTransactionModal').classList.add('hidden'); unlockBodyScroll(); });
document.addEventListener('keydown', (e) => { if (e.key === 'Escape') { document.getElementById('depositModal')?.classList.add('hidden'); document.getElementById('withdrawalModal')?.classList.add('hidden'); document.getElementById('viewTransactionModal')?.classList.add('hidden'); unlockBodyScroll(); } });

// Event listeners
document.getElementById('refreshDataBtn').addEventListener('click', () => loadAllData());
document.getElementById('class_filter').addEventListener('change', () => { loadStudents(); loadStats(); loadTransactionHistory(); });
document.getElementById('student_search').addEventListener('input', () => loadStudents());
document.getElementById('student_id').addEventListener('change', (e) => { if (e.target.value) { loadStudentBalance(e.target.value); loadTransactionHistory(); } else { document.getElementById('currentBalance').innerHTML = 'KES 0.00'; loadTransactionHistory(); } });

// Initialize
document.addEventListener('DOMContentLoaded', () => loadAllData());
</script>

<?php include_once('../../includes/footer.php'); ?>