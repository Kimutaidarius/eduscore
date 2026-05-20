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
$user_email = $_SESSION['email'] ?? '';

include_once('../../includes/header.php');
include_once('../../includes/sidebar.php');
?>

<style>
.settings-section { transition: all 0.3s ease; }
.settings-section:hover { transform: translateY(-2px); box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1); }
.form-input, .form-select { transition: all 0.2s ease; }
.form-input:focus, .form-select:focus { border-color: #4f46e5; outline: none; ring: 2px solid #4f46e5; }
.toggle-switch { position: relative; display: inline-block; width: 50px; height: 24px; }
.toggle-switch input { opacity: 0; width: 0; height: 0; }
.toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: 0.3s; border-radius: 34px; }
.toggle-slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: 0.3s; border-radius: 50%; }
input:checked + .toggle-slider { background-color: #4f46e5; }
input:checked + .toggle-slider:before { transform: translateX(26px); }
.table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }
.loading { display: inline-block; width: 20px; height: 20px; border: 2px solid #f3f3f3; border-top: 2px solid #4f46e5; border-radius: 50%; animation: spin 1s linear infinite; }
@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
@media print { .sidebar, header, .no-print { display: none !important; } .main-content { margin: 0 !important; padding: 0 !important; } }
</style>

<main class="main-content flex-grow flex flex-col">
  <header class="bg-white shadow-sm dark:bg-gray-800 dark:border-gray-700">
    <div class="px-4 py-3 flex justify-between items-center">
      <div class="flex items-center">
        <button id="mobile-toggle" class="mr-4 text-gray-500 hover:text-indigo-600 focus:outline-none lg:hidden">
          <i class="fas fa-bars text-xl"></i>
        </button>
        <h1 id="page-title" class="text-xl font-semibold text-gray-800 dark:text-white">Administration</h1>
      </div>
      <div class="flex items-center space-x-4">
        <button id="theme-toggle" class="p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700">
          <i class="fas fa-sun text-yellow-500 dark:hidden"></i>
          <i class="fas fa-moon text-blue-300 hidden dark:block"></i>
        </button>
        <div class="relative" id="user-menu-container">
          <button id="user-menu-button" class="flex items-center focus:outline-none">
            <img src="https://cdn.pixabay.com/photo/2015/10/05/22/37/blank-profile-picture-973460_960_720.png" alt="User Avatar" class="w-8 h-8 rounded-full mr-2">
            <span class="hidden md:block text-sm font-medium text-gray-700 dark:text-gray-200"><?php echo htmlspecialchars($_SESSION['email'] ?? 'User'); ?></span>
            <i class="fas fa-chevron-down text-xs ml-2 text-gray-500"></i>
          </button>
          <div id="user-dropdown" class="absolute right-0 mt-2 w-48 bg-white dark:bg-gray-800 border rounded-lg shadow-lg py-1 z-20 hidden">
            <a href="../../profile.php" class="block px-4 py-2 text-sm hover:bg-gray-100"><i class="fas fa-user-circle mr-2"></i> My Profile</a>
            <div class="border-t my-1"></div>
            <a href="../../logout.php" class="block px-4 py-2 text-sm text-red-600"><i class="fas fa-sign-out-alt mr-2"></i> Logout</a>
          </div>
        </div>
      </div>
    </div>
  </header>

  <div class="flex-grow p-4 md:p-6 overflow-auto">
    <!-- SCHOOL ACCOUNTS SECTION -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm mb-6 overflow-hidden">
      <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 border-b">
        <h3 class="font-semibold text-gray-800 dark:text-white">
          <i class="fas fa-building text-indigo-500 mr-2"></i>School Accounts
        </h3>
      </div>
      <div class="p-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <form id="addAccountForm" class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Account Name</label>
                <input type="text" id="account_name" class="form-input w-full px-3 py-2 border rounded-lg dark:bg-gray-700" placeholder="e.g., Main Account, School Fund" required>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Abbreviation</label>
                <input type="text" id="account_abbr" class="form-input w-full px-3 py-2 border rounded-lg dark:bg-gray-700" placeholder="e.g., M, SF" required>
              </div>
              <button type="submit" class="w-full px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                <i class="fas fa-plus mr-2"></i>Save Account
              </button>
            </form>
          </div>
          <div>
            <div class="table-responsive">
              <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700">
                  <tr><th class="px-3 py-2 text-left">Name</th><th class="px-3 py-2 text-left">Abbr</th><th class="px-3 py-2">Actions</th></tr>
                </thead>
                <tbody id="accountsList">
                  <tr><td colspan="3" class="text-center py-4"><div class="loading"></div> Loading...</td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- PAYMENT MODES SECTION -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm mb-6 overflow-hidden">
      <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 border-b">
        <h3 class="font-semibold text-gray-800 dark:text-white">
          <i class="fas fa-credit-card text-green-500 mr-2"></i>Payment Modes
        </h3>
      </div>
      <div class="p-4">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div>
            <form id="addPaymentModeForm" class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Account</label>
                <select id="payment_account" class="form-select w-full px-3 py-2 border rounded-lg dark:bg-gray-700">
                  <option value="">Select account...</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Payment Mode</label>
                <select id="payment_mode_type" class="form-select w-full px-3 py-2 border rounded-lg dark:bg-gray-700">
                  <option value="M-Pesa">M-Pesa</option>
                  <option value="Cash">Cash</option>
                  <option value="Bank Transfer">Bank Transfer</option>
                  <option value="Cheque">Cheque</option>
                </select>
              </div>
              <button type="submit" class="w-full px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                <i class="fas fa-save mr-2"></i>Save Payment Mode
              </button>
            </form>
          </div>
          <div>
            <div class="table-responsive">
              <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700">
                  <tr><th class="px-3 py-2">Payment Mode</th><th class="px-3 py-2">Linked Account</th><th class="px-3 py-2">Actions</th></tr>
                </thead>
                <tbody id="paymentModesList">
                  <tr><td colspan="3" class="text-center py-4"><div class="loading"></div> Loading...</td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- BANK ACCOUNTS SECTION -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm mb-6 overflow-hidden">
      <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 border-b">
        <h3 class="font-semibold text-gray-800 dark:text-white">
          <i class="fas fa-university text-blue-500 mr-2"></i>Bank Accounts
        </h3>
      </div>
      <div class="p-4">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div>
            <form id="addBankAccountForm" class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Account</label>
                <select id="bank_account_select" class="form-select w-full px-3 py-2 border rounded-lg dark:bg-gray-700">
                  <option value="">Select account...</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Bank Name</label>
                <input type="text" id="bank_name" class="form-input w-full px-3 py-2 border rounded-lg dark:bg-gray-700" placeholder="e.g., Cooperative Bank Nyeri Branch">
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Account Number</label>
                <input type="text" id="bank_account_number" class="form-input w-full px-3 py-2 border rounded-lg dark:bg-gray-700" placeholder="e.g., 12587665487">
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Branch</label>
                <input type="text" id="bank_branch" class="form-input w-full px-3 py-2 border rounded-lg dark:bg-gray-700" placeholder="Branch name">
              </div>
              <button type="submit" class="w-full px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                <i class="fas fa-save mr-2"></i>Save Bank Account
              </button>
            </form>
          </div>
          <div>
            <div class="table-responsive">
              <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700">
                  <tr><th class="px-3 py-2">Bank Name</th><th class="px-3 py-2">Account Number</th><th class="px-3 py-2">Actions</th></tr>
                </thead>
                <tbody id="bankAccountsList">
                  <tr><td colspan="3" class="text-center py-4"><div class="loading"></div> Loading...</td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- IPSAS MODE SECTION -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm mb-6 overflow-hidden">
      <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 border-b">
        <h3 class="font-semibold text-gray-800 dark:text-white">
          <i class="fas fa-chart-pie text-purple-500 mr-2"></i>IPSAS Mode
        </h3>
      </div>
      <div class="p-4">
        <div class="space-y-4">
          <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
            <div>
              <span class="font-medium">Financial year runs from July 1st to June 30th next year</span>
              <p class="text-sm text-gray-500" id="financial_year_display">Financial year: Loading...</p>
            </div>
            <label class="toggle-switch"><input type="checkbox" id="financial_year_toggle"><span class="toggle-slider"></span></label>
          </div>
          <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
            <div>
              <span class="font-medium">Arrears Rolled Up</span>
              <p class="text-sm text-gray-500">Indicates if the Arrears of the current financial year are rolled up</p>
            </div>
            <label class="toggle-switch"><input type="checkbox" id="arrears_toggle"><span class="toggle-slider"></span></label>
          </div>
          <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
            <div>
              <span class="font-medium">Prepayments distributed</span>
              <p class="text-sm text-gray-500">Indicates if prepayments of the preceding year are distributed into current financial year</p>
            </div>
            <label class="toggle-switch"><input type="checkbox" id="prepayments_toggle"><span class="toggle-slider"></span></label>
          </div>
        </div>
        <button id="saveIpsasBtn" class="mt-4 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
          <i class="fas fa-save mr-2"></i>Save IPSAS Settings
        </button>
      </div>
    </div>

    <!-- RECEIPT & TEMPLATE SETTINGS -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm mb-6 overflow-hidden">
      <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 border-b">
        <h3 class="font-semibold text-gray-800 dark:text-white">
          <i class="fas fa-print text-orange-500 mr-2"></i>Receipt & Template Settings
        </h3>
      </div>
      <div class="p-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Receipt Template</label>
            <select id="receipt_template" class="form-select w-full px-3 py-2 border rounded-lg dark:bg-gray-700">
              <option value="standard">Standard Template</option>
              <option value="detailed">Detailed Template</option>
              <option value="simple">Simple Template</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Payment Voucher Template</label>
            <select id="voucher_template" class="form-select w-full px-3 py-2 border rounded-lg dark:bg-gray-700">
              <option value="standard">Standard Template</option>
              <option value="detailed">Detailed Template</option>
              <option value="simple">Simple Template</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Receipt Copies to Print</label>
            <select id="receipt_copies" class="form-select w-full px-3 py-2 border rounded-lg dark:bg-gray-700">
              <option value="1">1 Copy</option>
              <option value="2">2 Copies</option>
              <option value="3">3 Copies</option>
            </select>
          </div>
        </div>
        <button id="saveTemplateBtn" class="mt-4 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
          <i class="fas fa-save mr-2"></i>Save Template Settings
        </button>
      </div>
    </div>

    <!-- STORES SETTINGS -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
      <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 border-b">
        <h3 class="font-semibold text-gray-800 dark:text-white">
          <i class="fas fa-warehouse text-yellow-500 mr-2"></i>Stores
        </h3>
      </div>
      <div class="p-4">
        <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg mb-4">
          <div><span class="font-medium">Enable multi-store</span><p class="text-sm text-gray-500">Manage multiple stores for stock tracking</p></div>
          <label class="toggle-switch"><input type="checkbox" id="multi_store_toggle"><span class="toggle-slider"></span></label>
        </div>
        <div id="storesList" class="mt-4"></div>
        <button id="addStoreBtn" class="mt-3 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition hidden">
          <i class="fas fa-plus mr-2"></i>Add New Store
        </button>
        <p class="text-sm text-gray-500 mt-2" id="storeHelpText">Multi-store is disabled. All stock movements use a single General Stores. Enable multi-store above to manage separate stores.</p>
      </div>
    </div>
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const schoolId = <?php echo json_encode($school_id); ?>;

// API Helper function
async function apiCall(action, data = {}) {
    data.action = action;
    const response = await fetch('/feesystem/api/admin/save_settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    });
    return await response.json();
}

// Load all data on page load
async function loadAllData() {
    await loadAccounts();
    await loadPaymentModes();
    await loadBankAccounts();
    await loadIpsasSettings();
    await loadTemplateSettings();
    await loadMultiStoreStatus();
}

// Load School Accounts
async function loadAccounts() {
    const result = await apiCall('get_accounts');
    const tbody = document.getElementById('accountsList');
    const accountSelects = document.querySelectorAll('#payment_account, #bank_account_select');
    
    if (result.success && result.accounts.length > 0) {
        tbody.innerHTML = result.accounts.map(acc => `
            <tr class="border-b">
                <td class="px-3 py-2">${escapeHtml(acc.account_name)}</td>
                <td class="px-3 py-2">${escapeHtml(acc.abbreviation)}</td>
                <td class="px-3 py-2 text-center">
                    <button class="text-red-500" onclick="deleteAccount(${acc.id})"><i class="fas fa-trash"></i></button>
                </td>
            </tr>
        `).join('');
        
        // Populate account selects
        const options = '<option value="">Select account...</option>' + result.accounts.map(acc => `<option value="${acc.id}">${escapeHtml(acc.account_name)} (${escapeHtml(acc.abbreviation)})</option>`).join('');
        accountSelects.forEach(select => { if(select) select.innerHTML = options; });
    } else {
        tbody.innerHTML = '<tr><td colspan="3" class="text-center py-4 text-gray-500">No accounts found</td></tr>';
    }
}

// Delete Account
async function deleteAccount(id) {
    const result = await Swal.fire({
        title: 'Delete Account?',
        text: 'This action cannot be undone!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Yes, delete'
    });
    if (result.isConfirmed) {
        const res = await apiCall('delete_account', { account_id: id });
        if (res.success) {
            Swal.fire('Deleted!', res.message, 'success');
            loadAccounts();
        } else {
            Swal.fire('Error!', res.message, 'error');
        }
    }
}

// Load Payment Modes
async function loadPaymentModes() {
    const result = await apiCall('get_payment_modes');
    const tbody = document.getElementById('paymentModesList');
    
    if (result.success && result.payment_modes.length > 0) {
        tbody.innerHTML = result.payment_modes.map(mode => `
            <tr class="border-b">
                <td class="px-3 py-2">${escapeHtml(mode.mode_name)}</td>
                <td class="px-3 py-2">${escapeHtml(mode.linked_account || '-')}</td>
                <td class="px-3 py-2 text-center">
                    <button class="text-red-500" onclick="deletePaymentMode(${mode.id})"><i class="fas fa-trash"></i></button>
                </td>
            </tr>
        `).join('');
    } else {
        tbody.innerHTML = '<tr><td colspan="3" class="text-center py-4 text-gray-500">No payment modes found</td></tr>';
    }
}

// Delete Payment Mode
async function deletePaymentMode(id) {
    const result = await Swal.fire({ title: 'Delete?', text: 'This action cannot be undone!', icon: 'warning', showCancelButton: true });
    if (result.isConfirmed) {
        const res = await apiCall('delete_payment_mode', { mode_id: id });
        if (res.success) { Swal.fire('Deleted!', res.message, 'success'); loadPaymentModes(); }
        else Swal.fire('Error!', res.message, 'error');
    }
}

// Load Bank Accounts
async function loadBankAccounts() {
    const result = await apiCall('get_bank_accounts');
    const tbody = document.getElementById('bankAccountsList');
    
    if (result.success && result.bank_accounts.length > 0) {
        tbody.innerHTML = result.bank_accounts.map(bank => `
            <tr class="border-b">
                <td class="px-3 py-2">${escapeHtml(bank.bank_name)}</td>
                <td class="px-3 py-2">${escapeHtml(bank.account_number)}</td>
                <td class="px-3 py-2 text-center">
                    <button class="text-red-500" onclick="deleteBankAccount(${bank.id})"><i class="fas fa-trash"></i></button>
                </td>
            </table>
        `).join('');
    } else {
        tbody.innerHTML = '<tr><td colspan="3" class="text-center py-4 text-gray-500">No bank accounts found</td></tr>';
    }
}

// Delete Bank Account
async function deleteBankAccount(id) {
    const result = await Swal.fire({ title: 'Delete?', text: 'This action cannot be undone!', icon: 'warning', showCancelButton: true });
    if (result.isConfirmed) {
        const res = await apiCall('delete_bank_account', { bank_id: id });
        if (res.success) { Swal.fire('Deleted!', res.message, 'success'); loadBankAccounts(); }
        else Swal.fire('Error!', res.message, 'error');
    }
}

// Load IPSAS Settings
async function loadIpsasSettings() {
    const result = await apiCall('get_ipsas');
    if (result.success && result.settings) {
        document.getElementById('financial_year_toggle').checked = result.settings.financial_year_start ? true : false;
        document.getElementById('arrears_toggle').checked = result.settings.arrears_rolled_up == 1;
        document.getElementById('prepayments_toggle').checked = result.settings.prepayments_distributed == 1;
        
        if (result.settings.financial_year_start) {
            const start = new Date(result.settings.financial_year_start);
            const end = new Date(result.settings.financial_year_end);
            document.getElementById('financial_year_display').innerText = `Financial year: ${start.toLocaleDateString()} - ${end.toLocaleDateString()}`;
        }
    }
}

// Load Template Settings
async function loadTemplateSettings() {
    const result = await apiCall('get_templates');
    if (result.success && result.settings) {
        document.getElementById('receipt_template').value = result.settings.receipt_template || 'standard';
        document.getElementById('voucher_template').value = result.settings.voucher_template || 'standard';
        document.getElementById('receipt_copies').value = result.settings.receipt_copies || 2;
    }
}

// Load Multi-store Status
async function loadMultiStoreStatus() {
    const result = await apiCall('get_multi_store_status');
    if (result.success) {
        const enabled = result.enabled == 1;
        document.getElementById('multi_store_toggle').checked = enabled;
        document.getElementById('addStoreBtn').classList.toggle('hidden', !enabled);
        document.getElementById('storeHelpText').innerHTML = enabled ? 'Multi-store is enabled. You can now manage multiple stores.' : 'Multi-store is disabled. All stock movements use a single General Stores. Enable multi-store above to manage separate stores.';
        
        if (enabled) {
            await loadStores();
        } else {
            document.getElementById('storesList').innerHTML = '<div class="border rounded-lg p-3 mb-2 flex justify-between items-center"><span><i class="fas fa-store mr-2 text-indigo-500"></i> General Stores</span><span class="text-sm text-gray-500">Default Store</span></div>';
        }
    }
}

// Load Stores
async function loadStores() {
    const result = await apiCall('get_stores');
    const storesList = document.getElementById('storesList');
    if (result.success && result.stores.length > 0) {
        storesList.innerHTML = result.stores.map(store => `
            <div class="border rounded-lg p-3 mb-2 flex justify-between items-center">
                <span><i class="fas fa-store mr-2 text-indigo-500"></i> ${escapeHtml(store.store_name)}</span>
                <span class="text-sm text-gray-500">${store.is_default ? 'Default' : ''}</span>
            </div>
        `).join('');
    } else {
        storesList.innerHTML = '<div class="border rounded-lg p-3 mb-2 flex justify-between items-center"><span><i class="fas fa-store mr-2 text-indigo-500"></i> General Stores</span><span class="text-sm text-gray-500">Default Store</span></div>';
    }
}

// Add Account Form
document.getElementById('addAccountForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const account_name = document.getElementById('account_name').value;
    const abbreviation = document.getElementById('account_abbr').value;
    
    if(account_name && abbreviation) {
        const result = await apiCall('add_account', { account_name, abbreviation });
        if (result.success) {
            Swal.fire('Success', result.message, 'success');
            document.getElementById('account_name').value = '';
            document.getElementById('account_abbr').value = '';
            loadAccounts();
        } else {
            Swal.fire('Error', result.message, 'error');
        }
    }
});

// Add Payment Mode Form
document.getElementById('addPaymentModeForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const account_id = document.getElementById('payment_account').value;
    const mode_name = document.getElementById('payment_mode_type').value;
    
    const result = await apiCall('add_payment_mode', { account_id: account_id || null, mode_name });
    if (result.success) {
        Swal.fire('Success', result.message, 'success');
        loadPaymentModes();
    } else {
        Swal.fire('Error', result.message, 'error');
    }
});

// Add Bank Account Form
document.getElementById('addBankAccountForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const account_id = document.getElementById('bank_account_select').value;
    const bank_name = document.getElementById('bank_name').value;
    const account_number = document.getElementById('bank_account_number').value;
    const branch = document.getElementById('bank_branch').value;
    
    if(bank_name && account_number) {
        const result = await apiCall('add_bank_account', { account_id: account_id || null, bank_name, account_number, branch });
        if (result.success) {
            Swal.fire('Success', result.message, 'success');
            document.getElementById('bank_name').value = '';
            document.getElementById('bank_account_number').value = '';
            document.getElementById('bank_branch').value = '';
            loadBankAccounts();
        } else {
            Swal.fire('Error', result.message, 'error');
        }
    }
});

// Save IPSAS Settings
document.getElementById('saveIpsasBtn')?.addEventListener('click', async () => {
    const today = new Date();
    const financial_year_start = document.getElementById('financial_year_toggle').checked ? new Date(today.getFullYear(), 6, 1) : null;
    const financial_year_end = financial_year_start ? new Date(today.getFullYear() + 1, 5, 30) : null;
    
    const result = await apiCall('save_ipsas', {
        financial_year_start: financial_year_start ? financial_year_start.toISOString().split('T')[0] : null,
        financial_year_end: financial_year_end ? financial_year_end.toISOString().split('T')[0] : null,
        arrears_rolled_up: document.getElementById('arrears_toggle').checked,
        prepayments_distributed: document.getElementById('prepayments_toggle').checked
    });
    if (result.success) Swal.fire('Success', result.message, 'success');
    else Swal.fire('Error', result.message, 'error');
});

// Save Template Settings
document.getElementById('saveTemplateBtn')?.addEventListener('click', async () => {
    const result = await apiCall('save_templates', {
        receipt_template: document.getElementById('receipt_template').value,
        voucher_template: document.getElementById('voucher_template').value,
        receipt_copies: document.getElementById('receipt_copies').value
    });
    if (result.success) Swal.fire('Success', result.message, 'success');
    else Swal.fire('Error', result.message, 'error');
});

// Multi-store Toggle
document.getElementById('multi_store_toggle')?.addEventListener('change', async (e) => {
    const result = await apiCall('toggle_multi_store', { enabled: e.target.checked });
    if (result.success) {
        await loadMultiStoreStatus();
        Swal.fire('Success', result.message, 'success');
    }
});

// Add Store Button
document.getElementById('addStoreBtn')?.addEventListener('click', async () => {
    const { value: storeName } = await Swal.fire({
        title: 'Add New Store',
        input: 'text',
        inputPlaceholder: 'Store Name (e.g., Science Lab, Kitchen, Library)',
        showCancelButton: true
    });
    if (storeName) {
        const result = await apiCall('add_store', { store_name: storeName });
        if (result.success) {
            Swal.fire('Success', result.message, 'success');
            loadStores();
        } else {
            Swal.fire('Error', result.message, 'error');
        }
    }
});

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    loadAllData();
});

// User dropdown and theme toggle (existing code remains)
document.getElementById('user-menu-button')?.addEventListener('click', () => {
    document.getElementById('user-dropdown')?.classList.toggle('hidden');
});
document.addEventListener('click', (e) => {
    if(!document.getElementById('user-menu-container')?.contains(e.target)) {
        document.getElementById('user-dropdown')?.classList.add('hidden');
    }
});
document.getElementById('theme-toggle')?.addEventListener('click', () => {
    document.documentElement.classList.toggle('dark');
    localStorage.setItem('theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
});
if(localStorage.getItem('theme') === 'dark') document.documentElement.classList.add('dark');
</script>

<?php include_once('../../includes/footer.php'); ?>