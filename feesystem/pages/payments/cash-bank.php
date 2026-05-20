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
/* Custom Styles for Cash & Bank */
.balance-card {
    transition: all 0.3s ease;
}
.balance-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
}
.transaction-table tr:hover {
    background-color: rgba(79, 70, 229, 0.05);
}
.filter-select, .filter-input {
    transition: all 0.2s ease;
}
.filter-select:focus, .filter-input:focus {
    border-color: #4f46e5;
    ring: 2px solid #4f46e5;
}

/* ===== MODAL Z-INDEX FIX ===== */
/* Ensure modals appear above header and all content */
#depositModal,
#withdrawalModal,
.modal {
    z-index: 9999 !important;
}

/* Modal backdrop */
#depositModal.fixed.inset-0,
#withdrawalModal.fixed.inset-0,
.modal-backdrop {
    z-index: 9998 !important;
}

/* Modal content containers */
#depositModal > div,
#withdrawalModal > div,
.modal > div {
    z-index: 10000 !important;
    position: relative;
}

/* Ensure header stays below modals */
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
        <h1 id="page-title" class="text-xl font-semibold text-gray-800 dark:text-white">Cash & Bank Management</h1>
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
    <!-- Description -->
    <div class="mb-6">
      <p class="text-gray-600 dark:text-gray-400">Track petty cash, bank balances, deposits, and withdrawals</p>
    </div>

    <!-- Date Range Filter -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4 mb-6">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">From</label>
          <input type="date" id="from_date" class="filter-input w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">To</label>
          <input type="date" id="to_date" class="filter-input w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Bank Account</label>
          <select id="bank_account_filter" class="filter-select w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
            <option value="">All Banks</option>
          </select>
        </div>
        <div class="flex items-end">
          <button id="refreshDataBtn" class="w-full px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
            <i class="fas fa-sync-alt mr-2"></i>Refresh
          </button>
        </div>
      </div>
    </div>

    <!-- Petty Cash Balance Card -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
      <div class="balance-card bg-gradient-to-r from-green-500 to-green-600 rounded-lg shadow-lg p-5 text-white">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-green-100 text-sm">Petty Cash Balance</p>
            <p class="text-3xl font-bold mt-1" id="pettyCashBalance">KES 0.00</p>
          </div>
          <i class="fas fa-money-bill-wave text-4xl opacity-50"></i>
        </div>
      </div>
      
      <div class="balance-card bg-gradient-to-r from-blue-500 to-blue-600 rounded-lg shadow-lg p-5 text-white">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-blue-100 text-sm">Bank Balance</p>
            <p class="text-3xl font-bold mt-1" id="bankBalance">KES 0.00</p>
          </div>
          <i class="fas fa-university text-4xl opacity-50"></i>
        </div>
      </div>
      
      <div class="balance-card bg-gradient-to-r from-purple-500 to-purple-600 rounded-lg shadow-lg p-5 text-white">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-purple-100 text-sm">Total Deposits (Period)</p>
            <p class="text-3xl font-bold mt-1" id="totalDeposits">KES 0.00</p>
          </div>
          <i class="fas fa-arrow-down text-4xl opacity-50"></i>
        </div>
      </div>
      
      <div class="balance-card bg-gradient-to-r from-red-500 to-red-600 rounded-lg shadow-lg p-5 text-white">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-red-100 text-sm">Total Withdrawals (Period)</p>
            <p class="text-3xl font-bold mt-1" id="totalWithdrawals">KES 0.00</p>
          </div>
          <i class="fas fa-arrow-up text-4xl opacity-50"></i>
        </div>
      </div>
    </div>

    <!-- Two Column Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
      <!-- Left Column: Cash Collections & Payments -->
      <div class="space-y-6">
        <!-- Cash Collections -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
          <div class="bg-green-50 dark:bg-green-900/20 px-4 py-3 border-b border-green-200 dark:border-green-800">
            <h3 class="font-semibold text-green-700 dark:text-green-400">
              <i class="fas fa-hand-holding-usd mr-2"></i>Cash Collections
            </h3>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                  <th class="px-4 py-2 text-left">Date</th>
                  <th class="px-4 py-2 text-left">Rcpt #</th>
                  <th class="px-4 py-2 text-right">Amount</th>
                  <th class="px-4 py-2 text-center">Deposited</th>
                </tr>
              </thead>
              <tbody id="cashCollectionsBody">
                <tr><td colspan="4" class="text-center py-8 text-gray-500">No cash collections</td></tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Other Collections (Bank/Cheque) -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
          <div class="bg-blue-50 dark:bg-blue-900/20 px-4 py-3 border-b border-blue-200 dark:border-blue-800">
            <h3 class="font-semibold text-blue-700 dark:text-blue-400">
              <i class="fas fa-credit-card mr-2"></i>Other Collections (Bank/Cheque)
            </h3>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                  <th class="px-4 py-2 text-left">Date</th>
                  <th class="px-4 py-2 text-left">Rcpt #</th>
                  <th class="px-4 py-2 text-right">Amount</th>
                  <th class="px-4 py-2 text-center">Deposited</th>
                </tr>
              </thead>
              <tbody id="otherCollectionsBody">
                <tr><td colspan="4" class="text-center py-8 text-gray-500">No other collections</td></tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Cash Payments -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
          <div class="bg-red-50 dark:bg-red-900/20 px-4 py-3 border-b border-red-200 dark:border-red-800">
            <h3 class="font-semibold text-red-700 dark:text-red-400">
              <i class="fas fa-money-bill-wave mr-2"></i>Cash Payments
            </h3>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                  <th class="px-4 py-2 text-left">Date</th>
                  <th class="px-4 py-2 text-left">PV #</th>
                  <th class="px-4 py-2 text-left">Payee</th>
                  <th class="px-4 py-2 text-right">Amount</th>
                </tr>
              </thead>
              <tbody id="cashPaymentsBody">
                <tr><td colspan="4" class="text-center py-8 text-gray-500">No cash payments</td></tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Other Payments (Bank/Cheque) -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
          <div class="bg-orange-50 dark:bg-orange-900/20 px-4 py-3 border-b border-orange-200 dark:border-orange-800">
            <h3 class="font-semibold text-orange-700 dark:text-orange-400">
              <i class="fas fa-exchange-alt mr-2"></i>Other Payments (Bank/Cheque)
            </h3>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                  <th class="px-4 py-2 text-left">Date</th>
                  <th class="px-4 py-2 text-left">PV #</th>
                  <th class="px-4 py-2 text-left">Payee</th>
                  <th class="px-4 py-2 text-right">Amount</th>
                </tr>
              </thead>
              <tbody id="otherPaymentsBody">
                <tr><td colspan="4" class="text-center py-8 text-gray-500">No other payments</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Right Column: Deposit/Withdrawal & Transaction History -->
      <div class="space-y-6">
        <!-- Deposit and Withdrawal Buttons -->
        <div class="grid grid-cols-2 gap-4">
          <button id="depositBtn" class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center justify-center">
            <i class="fas fa-plus-circle mr-2"></i>Deposit
          </button>
          <button id="withdrawalBtn" class="px-6 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 transition flex items-center justify-center">
            <i class="fas fa-minus-circle mr-2"></i>Withdrawal
          </button>
        </div>

        <!-- Transaction History -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
          <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 border-b">
            <h3 class="font-semibold text-gray-800 dark:text-white">
              <i class="fas fa-history mr-2 text-indigo-500"></i>Transaction History
            </h3>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-sm transaction-table">
              <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                  <th class="px-4 py-2 text-left">Date</th>
                  <th class="px-4 py-2 text-left">Type</th>
                  <th class="px-4 py-2 text-right">Amount</th>
                  <th class="px-4 py-2 text-left">Description</th>
                  <th class="px-4 py-2 text-left">Reference</th>
                  <th class="px-4 py-2 text-center">Actions</th>
                </tr>
              </thead>
              <tbody id="transactionHistoryBody">
                <tr><td colspan="6" class="text-center py-8 text-gray-500">Select a bank account to view transactions</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>

<!-- Deposit Modal -->
<div id="depositModal" class="fixed inset-0 bg-black bg-opacity-50 z-30 hidden flex items-center justify-center">
  <div class="bg-white dark:bg-gray-800 w-full max-w-md rounded-lg shadow-xl mx-4">
    <div class="border-b border-gray-200 dark:border-gray-700 px-6 py-4 flex justify-between items-center">
      <h3 class="text-lg font-semibold text-gray-800 dark:text-white">
        <i class="fas fa-plus-circle text-green-500 mr-2"></i>Make Deposit
      </h3>
      <button class="closeDepositModal text-gray-400 hover:text-gray-500">&times;</button>
    </div>
    <div class="px-6 py-4">
      <form id="depositForm">
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Bank Account <span class="text-red-500">*</span></label>
          <select id="deposit_bank_account" required class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
            <option value="">Select bank account...</option>
          </select>
        </div>
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Amount <span class="text-red-500">*</span></label>
          <input type="number" id="deposit_amount" required step="0.01" min="0.01" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
        </div>
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Deposit Date</label>
          <input type="date" id="deposit_date" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
        </div>
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Reference Number</label>
          <input type="text" id="deposit_reference" placeholder="e.g., DEP-001, Cheque No." class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
        </div>
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
          <textarea id="deposit_description" rows="2" placeholder="Additional notes..." class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600"></textarea>
        </div>
        <div class="flex justify-end gap-3">
          <button type="button" class="closeDepositModal px-4 py-2 border rounded-lg">Cancel</button>
          <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">Confirm Deposit</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Withdrawal Modal -->
<div id="withdrawalModal" class="fixed inset-0 bg-black bg-opacity-50 z-30 hidden flex items-center justify-center">
  <div class="bg-white dark:bg-gray-800 w-full max-w-md rounded-lg shadow-xl mx-4">
    <div class="border-b border-gray-200 dark:border-gray-700 px-6 py-4 flex justify-between items-center">
      <h3 class="text-lg font-semibold text-gray-800 dark:text-white">
        <i class="fas fa-minus-circle text-red-500 mr-2"></i>Make Withdrawal
      </h3>
      <button class="closeWithdrawalModal text-gray-400 hover:text-gray-500">&times;</button>
    </div>
    <div class="px-6 py-4">
      <form id="withdrawalForm">
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Bank Account <span class="text-red-500">*</span></label>
          <select id="withdrawal_bank_account" required class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
            <option value="">Select bank account...</option>
          </select>
        </div>
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Amount <span class="text-red-500">*</span></label>
          <input type="number" id="withdrawal_amount" required step="0.01" min="0.01" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
        </div>
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Withdrawal Date</label>
          <input type="date" id="withdrawal_date" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
        </div>
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Reference Number</label>
          <input type="text" id="withdrawal_reference" placeholder="e.g., WTH-001, Cheque No." class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
        </div>
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
          <textarea id="withdrawal_description" rows="2" placeholder="Purpose of withdrawal..." class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600"></textarea>
        </div>
        <div class="flex justify-end gap-3">
          <button type="button" class="closeWithdrawalModal px-4 py-2 border rounded-lg">Cancel</button>
          <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">Confirm Withdrawal</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
const schoolId = <?php echo json_encode($school_id); ?>;
const userId = <?php echo json_encode($user_id); ?>;

// Set default dates (first day to last day of current month)
function setDefaultDates() {
    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
    
    document.getElementById('from_date').value = formatDate(firstDay);
    document.getElementById('to_date').value = formatDate(lastDay);
    document.getElementById('deposit_date').value = formatDate(today);
    document.getElementById('withdrawal_date').value = formatDate(today);
}

function formatDate(date) {
    return date.toISOString().split('T')[0];
}

// ==================== LOAD BANK ACCOUNTS ====================
// ==================== LOAD BANK ACCOUNTS ====================
async function loadBankAccounts() {
    try {
        const response = await fetch('/feesystem/api/cashbank/get_bank_accounts.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ school_id: schoolId })
        });
        const data = await response.json();
        
        if (data.success) {
            let accounts = [];
            
            // Handle different response formats
            if (data.accounts) {
                // Format 1: Direct accounts array
                accounts = data.accounts;
            } else if (data.banks) {
                // Format 2: Banks with nested accounts
                data.banks.forEach(bank => {
                    if (bank.accounts && bank.accounts.length > 0) {
                        bank.accounts.forEach(acc => {
                            accounts.push({
                                id: acc.account_id,
                                bank_name: bank.bank_name,
                                account_name: acc.account_name,
                                account_number: acc.account_number,
                                current_balance: acc.current_balance
                            });
                        });
                    }
                });
            }
            
            // Build filter dropdown (shows banks for filtering)
            let filterOptions = '<option value="">All Banks</option>';
            
            // If we have accounts, use them for the filter
            if (accounts.length > 0) {
                // Get unique banks from accounts
                const uniqueBanks = {};
                accounts.forEach(acc => {
                    if (!uniqueBanks[acc.bank_name]) {
                        uniqueBanks[acc.bank_name] = acc.bank_name;
                        filterOptions += `<option value="${acc.id}">${escapeHtml(acc.bank_name)} - ${escapeHtml(acc.account_name)}</option>`;
                    }
                });
            } else if (data.banks) {
                // If no accounts but we have banks, show banks in filter
                data.banks.forEach(bank => {
                    filterOptions += `<option value="${bank.bank_id}">${escapeHtml(bank.bank_name)}</option>`;
                });
            }
            
            document.getElementById('bank_account_filter').innerHTML = filterOptions;
            
            // Build deposit/withdrawal dropdowns (only show accounts)
            let accountOptions = '<option value="">Select bank account...</option>';
            if (accounts.length > 0) {
                accounts.forEach(acc => {
                    accountOptions += `<option value="${acc.id}">${escapeHtml(acc.bank_name)} - ${escapeHtml(acc.account_name)} (${escapeHtml(acc.account_number || '')})</option>`;
                });
            } else {
                accountOptions += '<option value="" disabled>No bank accounts found. Please add bank accounts first.</option>';
            }
            
            document.getElementById('deposit_bank_account').innerHTML = accountOptions;
            document.getElementById('withdrawal_bank_account').innerHTML = accountOptions;
            
            // Show warning if no accounts
            if (accounts.length === 0) {
                console.warn('No bank accounts found for this school');
                // Optional: Show a notification
                // Swal.fire('Info', 'Please add bank accounts before making deposits or withdrawals', 'info');
            }
        }
    } catch (error) { 
        console.error('Error loading bank accounts:', error);
        // Set default options on error
        const defaultOptions = '<option value="">Select bank account...</option><option value="" disabled>Error loading accounts</option>';
        document.getElementById('deposit_bank_account').innerHTML = defaultOptions;
        document.getElementById('withdrawal_bank_account').innerHTML = defaultOptions;
    }
}
// ==================== LOAD ALL DATA ====================
async function loadAllData() {
    const fromDate = document.getElementById('from_date').value;
    const toDate = document.getElementById('to_date').value;
    const bankAccountId = document.getElementById('bank_account_filter').value;
    
    await loadPettyCashBalance();
    await loadBankBalance(bankAccountId);
    await loadCashCollections(fromDate, toDate);
    await loadOtherCollections(fromDate, toDate);
    await loadCashPayments(fromDate, toDate);
    await loadOtherPayments(fromDate, toDate);
    await loadTransactionHistory(fromDate, toDate, bankAccountId);
    await loadDepositWithdrawalTotals(fromDate, toDate);
}

async function loadPettyCashBalance() {
    try {
        const response = await fetch('/feesystem/api/cashbank/get_petty_cash_balance.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ school_id: schoolId })
        });
        const data = await response.json();
        if (data.success) {
            document.getElementById('pettyCashBalance').innerHTML = `KES ${parseFloat(data.balance).toLocaleString()}`;
        }
    } catch (error) { console.error('Error loading petty cash:', error); }
}

async function loadBankBalance(bankAccountId) {
    try {
        const response = await fetch('/feesystem/api/cashbank/get_bank_balance.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ school_id: schoolId, bank_account_id: bankAccountId || null })
        });
        const data = await response.json();
        if (data.success) {
            document.getElementById('bankBalance').innerHTML = `KES ${parseFloat(data.balance).toLocaleString()}`;
        }
    } catch (error) { console.error('Error loading bank balance:', error); }
}

async function loadCashCollections(fromDate, toDate) {
    try {
        const response = await fetch('/feesystem/api/cashbank/get_cash_collections.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ school_id: schoolId, from_date: fromDate, to_date: toDate })
        });
        const data = await response.json();
        const tbody = document.getElementById('cashCollectionsBody');
        if (data.success && data.collections.length > 0) {
            tbody.innerHTML = data.collections.map(c => `
                <tr class="border-b dark:border-gray-700">
                    <td class="px-4 py-2">${c.payment_date || '-'}</td>
                    <td class="px-4 py-2">${escapeHtml(c.receipt_no)}</td>
                    <td class="px-4 py-2 text-right">KES ${parseFloat(c.amount).toLocaleString()}</td>
                    <td class="px-4 py-2 text-center">
                        ${c.deposited ? '<span class="text-green-600"><i class="fas fa-check-circle"></i> Yes</span>' : '<span class="text-yellow-600"><i class="fas fa-clock"></i> No</span>'}
                    </td>
                </tr>
            `).join('');
        } else {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center py-8 text-gray-500">No cash collections</td></tr>';
        }
    } catch (error) { console.error('Error loading cash collections:', error); }
}

async function loadOtherCollections(fromDate, toDate) {
    try {
        const response = await fetch('/feesystem/api/cashbank/get_other_collections.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ school_id: schoolId, from_date: fromDate, to_date: toDate })
        });
        const data = await response.json();
        const tbody = document.getElementById('otherCollectionsBody');
        if (data.success && data.collections.length > 0) {
            tbody.innerHTML = data.collections.map(c => `
                <tr class="border-b dark:border-gray-700">
                    <td class="px-4 py-2">${c.payment_date || '-'}</td>
                    <td class="px-4 py-2">${escapeHtml(c.receipt_no)}</td>
                    <td class="px-4 py-2 text-right">KES ${parseFloat(c.amount).toLocaleString()}</td>
                    <td class="px-4 py-2 text-center">
                        ${c.deposited ? '<span class="text-green-600"><i class="fas fa-check-circle"></i> Yes</span>' : '<span class="text-yellow-600"><i class="fas fa-clock"></i> No</span>'}
                    </td>
                </tr>
            `).join('');
        } else {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center py-8 text-gray-500">No other collections</td></tr>';
        }
    } catch (error) { console.error('Error loading other collections:', error); }
}

async function loadCashPayments(fromDate, toDate) {
    try {
        const response = await fetch('/feesystem/api/cashbank/get_cash_payments.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ school_id: schoolId, from_date: fromDate, to_date: toDate })
        });
        const data = await response.json();
        const tbody = document.getElementById('cashPaymentsBody');
        if (data.success && data.payments.length > 0) {
            tbody.innerHTML = data.payments.map(p => `
                <tr class="border-b dark:border-gray-700">
                    <td class="px-4 py-2">${p.payment_date || '-'}</td>
                    <td class="px-4 py-2">${escapeHtml(p.voucher_no)}</td>
                    <td class="px-4 py-2">${escapeHtml(p.payee_name)}</td>
                    <td class="px-4 py-2 text-right">KES ${parseFloat(p.amount).toLocaleString()}</td>
                </tr>
            `).join('');
        } else {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center py-8 text-gray-500">No cash payments</td></tr>';
        }
    } catch (error) { console.error('Error loading cash payments:', error); }
}

async function loadOtherPayments(fromDate, toDate) {
    try {
        const response = await fetch('/feesystem/api/cashbank/get_other_payments.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ school_id: schoolId, from_date: fromDate, to_date: toDate })
        });
        const data = await response.json();
        const tbody = document.getElementById('otherPaymentsBody');
        if (data.success && data.payments.length > 0) {
            tbody.innerHTML = data.payments.map(p => `
                <tr class="border-b dark:border-gray-700">
                    <td class="px-4 py-2">${p.payment_date || '-'}</td>
                    <td class="px-4 py-2">${escapeHtml(p.voucher_no)}</td>
                    <td class="px-4 py-2">${escapeHtml(p.payee_name)}</td>
                    <td class="px-4 py-2 text-right">KES ${parseFloat(p.amount).toLocaleString()}</td>
                </tr>
            `).join('');
        } else {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center py-8 text-gray-500">No other payments</td></tr>';
        }
    } catch (error) { console.error('Error loading other payments:', error); }
}

async function loadTransactionHistory(fromDate, toDate, bankAccountId) {
    try {
        const response = await fetch('/feesystem/api/cashbank/get_transaction_history.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ school_id: schoolId, from_date: fromDate, to_date: toDate, bank_account_id: bankAccountId || null })
        });
        const data = await response.json();
        const tbody = document.getElementById('transactionHistoryBody');
        if (data.success && data.transactions.length > 0) {
            tbody.innerHTML = data.transactions.map(t => `
                <tr class="border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700">
                    <td class="px-4 py-2">${t.transaction_date}</td>
                    <td class="px-4 py-2">
                        <span class="px-2 py-1 rounded-full text-xs ${t.type === 'deposit' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
                            ${t.type === 'deposit' ? 'Deposit' : 'Withdrawal'}
                        </span>
                    </td>
                    <td class="px-4 py-2 text-right ${t.type === 'deposit' ? 'text-green-600' : 'text-red-600'}">
                        ${t.type === 'deposit' ? '+' : '-'} KES ${parseFloat(t.amount).toLocaleString()}
                    </td>
                    <td class="px-4 py-2">${escapeHtml(t.description || '-')}</td>
                    <td class="px-4 py-2">${escapeHtml(t.reference || '-')}</td>
                    <td class="px-4 py-2 text-center">
                        <button onclick="viewReceipt(${t.id})" class="text-blue-500 hover:text-blue-700"><i class="fas fa-eye"></i></button>
                    </td>
                </tr>
            `).join('');
        } else {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-8 text-gray-500">No transactions found</td></tr>';
        }
    } catch (error) { console.error('Error loading transaction history:', error); }
}

async function loadDepositWithdrawalTotals(fromDate, toDate) {
    try {
        const response = await fetch('/feesystem/api/cashbank/get_deposit_withdrawal_totals.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ school_id: schoolId, from_date: fromDate, to_date: toDate })
        });
        const data = await response.json();
        if (data.success) {
            document.getElementById('totalDeposits').innerHTML = `KES ${parseFloat(data.total_deposits).toLocaleString()}`;
            document.getElementById('totalWithdrawals').innerHTML = `KES ${parseFloat(data.total_withdrawals).toLocaleString()}`;
        }
    } catch (error) { console.error('Error loading totals:', error); }
}

// ==================== DEPOSIT ====================
document.getElementById('depositBtn').addEventListener('click', () => {
    document.getElementById('depositModal').classList.remove('hidden');
});

document.querySelectorAll('.closeDepositModal').forEach(btn => {
    btn.addEventListener('click', () => document.getElementById('depositModal').classList.add('hidden'));
});

document.getElementById('depositForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const bankAccountId = document.getElementById('deposit_bank_account').value;
    const amount = document.getElementById('deposit_amount').value;
    const depositDate = document.getElementById('deposit_date').value;
    const reference = document.getElementById('deposit_reference').value;
    const description = document.getElementById('deposit_description').value;
    
    if (!bankAccountId) {
        Swal.fire('Error', 'Please select a bank account', 'error');
        return;
    }
    if (!amount || amount <= 0) {
        Swal.fire('Error', 'Please enter a valid amount', 'error');
        return;
    }
    
    try {
        const response = await fetch('/feesystem/api/cashbank/make_deposit.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                school_id: schoolId,
                user_id: userId,
                bank_account_id: bankAccountId,
                amount: amount,
                transaction_date: depositDate,
                reference: reference,
                description: description
            })
        });
        const data = await response.json();
        if (data.success) {
            Swal.fire('Success', 'Deposit recorded successfully!', 'success');
            document.getElementById('depositModal').classList.add('hidden');
            document.getElementById('depositForm').reset();
            loadAllData();
        } else {
            Swal.fire('Error', data.message || 'Failed to record deposit', 'error');
        }
    } catch (error) { Swal.fire('Error', 'An error occurred', 'error'); }
});

// ==================== WITHDRAWAL ====================
document.getElementById('withdrawalBtn').addEventListener('click', () => {
    document.getElementById('withdrawalModal').classList.remove('hidden');
});

document.querySelectorAll('.closeWithdrawalModal').forEach(btn => {
    btn.addEventListener('click', () => document.getElementById('withdrawalModal').classList.add('hidden'));
});

document.getElementById('withdrawalForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const bankAccountId = document.getElementById('withdrawal_bank_account').value;
    const amount = document.getElementById('withdrawal_amount').value;
    const withdrawalDate = document.getElementById('withdrawal_date').value;
    const reference = document.getElementById('withdrawal_reference').value;
    const description = document.getElementById('withdrawal_description').value;
    
    if (!bankAccountId) {
        Swal.fire('Error', 'Please select a bank account', 'error');
        return;
    }
    if (!amount || amount <= 0) {
        Swal.fire('Error', 'Please enter a valid amount', 'error');
        return;
    }
    
    try {
        const response = await fetch('/feesystem/api/cashbank/make_withdrawal.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                school_id: schoolId,
                user_id: userId,
                bank_account_id: bankAccountId,
                amount: amount,
                transaction_date: withdrawalDate,
                reference: reference,
                description: description
            })
        });
        const data = await response.json();
        if (data.success) {
            Swal.fire('Success', 'Withdrawal recorded successfully!', 'success');
            document.getElementById('withdrawalModal').classList.add('hidden');
            document.getElementById('withdrawalForm').reset();
            loadAllData();
        } else {
            Swal.fire('Error', data.message || 'Failed to record withdrawal', 'error');
        }
    } catch (error) { Swal.fire('Error', 'An error occurred', 'error'); }
});

// ==================== REFRESH ====================
document.getElementById('refreshDataBtn').addEventListener('click', () => {
    loadAllData();
});

// Event listeners for date changes
document.getElementById('from_date').addEventListener('change', loadAllData);
document.getElementById('to_date').addEventListener('change', loadAllData);
document.getElementById('bank_account_filter').addEventListener('change', loadAllData);

window.viewReceipt = (transactionId) => {
    // Fetch receipt details
    fetch('/feesystem/api/cashbank/get_receipt_details.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ transaction_id: transactionId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.receipt) {
            let receiptHtml = `
                <div class="p-4">
                    <div class="text-center mb-4">
                        <h3 class="font-bold">RECEIPT</h3>
                        <p>Receipt No: ${data.receipt.reference || 'N/A'}</p>
                        <p>Date: ${data.receipt.transaction_date}</p>
                    </div>
                    <div class="border-t border-b py-2 my-2">
                        <p><strong>Type:</strong> ${data.receipt.type.toUpperCase()}</p>
                        <p><strong>Amount:</strong> KES ${parseFloat(data.receipt.amount).toLocaleString()}</p>
                        <p><strong>Bank Account:</strong> ${data.receipt.bank_name || 'N/A'}</p>
                        <p><strong>Description:</strong> ${data.receipt.description || 'N/A'}</p>
                    </div>
                    <div class="text-center mt-4">
                        <button onclick="window.print()" class="px-4 py-2 bg-indigo-600 text-white rounded">Print</button>
                    </div>
                </div>
            `;
            Swal.fire({
                title: 'Transaction Receipt',
                html: receiptHtml,
                width: '500px',
                showConfirmButton: true
            });
        } else {
            Swal.fire('Info', 'Receipt details not available', 'info');
        }
    })
    .catch(error => {
        console.error('Error fetching receipt:', error);
        Swal.fire('Info', 'Receipt viewing feature coming soon', 'info');
    });
};

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    setDefaultDates();
    loadBankAccounts();
    loadAllData();
});
</script>

<?php include_once('../../includes/footer.php'); ?>