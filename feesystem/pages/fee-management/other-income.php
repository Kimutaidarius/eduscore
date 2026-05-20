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
$user_name = $_SESSION['full_name'] ?? $_SESSION['email'] ?? 'Finance Officer';

include_once('../../includes/header.php');
include_once('../../includes/sidebar.php');

// Get the last receipt number from database to generate sequential number
function getNextReceiptNumber($pdo, $school_id) {
    $prefix = 'RCP';
    $year = date('Y');
    
    // Get the highest receipt number for this school and year
    $sql = "SELECT receipt_number FROM other_income_receipts 
            WHERE school_id = ? AND receipt_number LIKE ? 
            ORDER BY id DESC LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $pattern = "$prefix-$year%";
    $stmt->execute([$school_id, $pattern]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row) {
        // Extract the sequential number from last receipt
        $parts = explode('-', $row['receipt_number']);
        $last_num = intval(end($parts));
        $next_num = $last_num + 1;
    } else {
        $next_num = 1;
    }
    
    return "$prefix-$year-" . str_pad($next_num, 4, '0', STR_PAD_LEFT);
}

// Get database connection
$database = Database::getInstance();
$pdo = $database->getConnection();
$next_receipt_number = getNextReceiptNumber($pdo, $school_id);
?>

<main class="main-content flex-grow flex flex-col">
  <header class="bg-white shadow-sm dark:bg-gray-800 dark:border-gray-700">
    <div class="px-4 py-3 flex justify-between items-center">
      <div class="flex items-center">
        <button id="mobile-toggle" class="mr-4 text-gray-500 hover:text-indigo-600 focus:outline-none lg:hidden">
          <i class="fas fa-bars text-xl"></i>
        </button>
        <h1 id="page-title" class="text-xl font-semibold text-gray-800 dark:text-white">Other Income</h1>
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
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      
      <!-- LEFT COLUMN - Receipt Form -->
      <div class="space-y-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
          <div class="border-b border-gray-200 dark:border-gray-700 px-6 py-4 bg-gradient-to-r from-green-50 to-emerald-50 dark:from-gray-700 dark:to-gray-700">
            <div class="flex justify-between items-center">
              <div>
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white">
                  <i class="fas fa-receipt text-green-600 mr-2"></i>Other Income Receipt
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Create receipts for non-student income</p>
              </div>
              <div class="text-right bg-white dark:bg-gray-800 px-4 py-2 rounded-lg shadow-sm">
                <span class="text-xs text-gray-500">Receipt Number</span>
                <div class="font-mono font-bold text-green-600 text-lg" id="receiptNumberDisplay"><?php echo $next_receipt_number; ?></div>
              </div>
            </div>
          </div>
          
          <div class="p-6">
            <form id="receiptForm">
              <!-- Payer Information -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Payer Name *</label>
                  <input type="text" id="payerName" name="payer_name" 
                         placeholder="Enter payer name" 
                         class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                  <input type="hidden" id="payerId" name="payer_id">
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Payer Type</label>
                  <select id="payerType" name="payer_type" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    <option value="client">Client</option>
                    <option value="student">Student</option>
                    <option value="staff">Staff</option>
                    <option value="other">Other</option>
                  </select>
                </div>
              </div>
              
              <!-- Payment Details -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Payment Mode</label>
                  <select id="paymentMode" name="payment_mode" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-green-500">
                    <option value="">Select mode...</option>
                    <option value="cash">Cash</option>
                    <option value="mpesa">M-Pesa</option>
                    <option value="bank">Bank Transfer</option>
                    <option value="cheque">Cheque</option>
                    <option value="card">Credit/Debit Card</option>
                  </select>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Payment Code</label>
                  <input type="text" id="paymentCode" name="payment_code" placeholder="e.g., Cheque No., M-Pesa Code" 
                         class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-green-500">
                </div>
              </div>
              
              <!-- Payment Date -->
              <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Payment Date</label>
                <input type="date" id="paymentDate" name="payment_date" 
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-green-500" 
                       value="<?php echo date('Y-m-d'); ?>">
              </div>
              
              <!-- Line Items Section -->
              <div class="mb-4">
                <div class="flex justify-between items-center mb-3">
                  <h3 class="text-md font-semibold text-gray-800 dark:text-white">
                    <i class="fas fa-list-ul text-gray-500 mr-2"></i>Line Items
                  </h3>
                  <button type="button" id="addItemBtn" class="px-3 py-1.5 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm transition-colors">
                    <i class="fas fa-plus mr-1"></i>Add Item
                  </button>
                </div>
                
                <div id="receiptItemsContainer" class="space-y-3 max-h-80 overflow-y-auto">
                  <div class="receipt-item bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3 border border-gray-200 dark:border-gray-600">
                    <div class="grid grid-cols-12 gap-2">
                      <div class="col-span-12 md:col-span-4">
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Vote Head</label>
                        <select name="items[0][vote_head_id]" class="vote-head-select w-full px-2 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-green-500">
                          <option value="">Select...</option>
                        </select>
                      </div>
                      <div class="col-span-12 md:col-span-5">
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Particular</label>
                        <input type="text" name="items[0][description]" placeholder="Description..." 
                               class="w-full px-2 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-green-500">
                      </div>
                      <div class="col-span-8 md:col-span-2">
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Amount</label>
                        <input type="number" name="items[0][amount]" value="0" class="item-amount w-full px-2 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-green-500" step="0.01" min="0">
                      </div>
                      <div class="col-span-4 md:col-span-1 flex items-end justify-end">
                        <button type="button" class="remove-item-btn text-red-500 hover:text-red-700 text-sm p-1.5">
                          <i class="fas fa-trash-alt"></i>
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              
              <!-- Total Display -->
              <div class="bg-gray-100 dark:bg-gray-700 rounded-lg p-4 mb-4">
                <div class="flex justify-between items-center">
                  <span class="text-gray-600 dark:text-gray-300 font-medium">Total Amount:</span>
                  <span class="text-2xl font-bold text-green-600" id="totalAmount">KES 0.00</span>
                </div>
              </div>
              
              <!-- Notes -->
              <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notes</label>
                <textarea id="notes" name="notes" rows="2" placeholder="Additional notes..." 
                          class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-green-500"></textarea>
              </div>
              
              <!-- Form Actions -->
              <div class="flex justify-end space-x-3 pt-2 border-t border-gray-200 dark:border-gray-700">
                <button type="reset" id="cancelBtn" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                  <i class="fas fa-times mr-2"></i>Clear
                </button>
                <button type="submit" id="saveReceiptBtn" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                  <i class="fas fa-save mr-2"></i>Save Receipt
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
      
      <!-- RIGHT COLUMN - Receipt Preview -->
      <div class="space-y-6">
        <!-- PDF Receipt Preview -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden sticky top-4">
          <div class="border-b border-gray-200 dark:border-gray-700 px-6 py-3 bg-gray-50 dark:bg-gray-700">
            <h3 class="text-md font-semibold text-gray-800 dark:text-white">
              <i class="fas fa-file-pdf text-red-500 mr-2"></i>Receipt Preview
            </h3>
          </div>
          
          <!-- PDF Preview Container -->
          <div id="pdfPreviewContainer" class="bg-gray-100 dark:bg-gray-900">
            <div id="receiptPreview" class="min-h-[500px]"></div>
          </div>
          
          <div class="border-t border-gray-200 dark:border-gray-700 px-6 py-3 flex justify-between items-center">
            <span class="text-xs text-gray-400" id="previewStatus">Ready</span>
            <button id="downloadReceiptBtn" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
              <i class="fas fa-download mr-2"></i>Download PDF
            </button>
          </div>
        </div>
        
        <!-- Receipt History Table -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
          <div class="border-b border-gray-200 dark:border-gray-700 px-6 py-3 bg-gray-50 dark:bg-gray-700">
            <h3 class="text-md font-semibold text-gray-800 dark:text-white">
              <i class="fas fa-history text-blue-500 mr-2"></i>Receipt History
            </h3>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="bg-gray-100 dark:bg-gray-700">
                <tr>
                  <th class="px-4 py-3 text-left">Receipt #</th>
                  <th class="px-4 py-3 text-left">Date</th>
                  <th class="px-4 py-3 text-left">Payer</th>
                  <th class="px-4 py-3 text-right">Amount</th>
                  <th class="px-4 py-3 text-left">Payment Mode</th>
                  <th class="px-4 py-3 text-left">Issued By</th>
                  <th class="px-4 py-3 text-center">Actions</th>
                </tr>
              </thead>
              <tbody id="receiptHistoryBody">
                <tr>
                  <td colspan="7" class="text-center text-gray-500 py-8">No receipts found</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const schoolId = <?php echo json_encode($school_id); ?>;
const currentUser = <?php echo json_encode($user_name); ?>;
let voteHeads = [];
let receipts = [];
let itemIndex = 1;
let currentReceiptId = null;

// Load vote heads
async function loadVoteHeads() {
  try {
    const response = await fetch('/feesystem/api/feesystem/get_vote_heads.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ school_id: schoolId, status: 'active', type: 'income' })
    });
    const data = await response.json();
    if (data.success) {
      voteHeads = data.vote_heads;
      populateVoteHeadSelects();
    }
  } catch (error) {
    console.error('Error loading vote heads:', error);
  }
}

function populateVoteHeadSelects() {
  const options = '<option value="">Select...</option>' + 
    voteHeads.map(vh => `<option value="${vh.id}">${escapeHtml(vh.name)} (${escapeHtml(vh.alias)})</option>`).join('');
  
  document.querySelectorAll('.vote-head-select').forEach(select => {
    select.innerHTML = options;
  });
}

// Add receipt item
document.getElementById('addItemBtn').addEventListener('click', () => {
  const container = document.getElementById('receiptItemsContainer');
  const newItem = document.createElement('div');
  newItem.className = 'receipt-item bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3 border border-gray-200 dark:border-gray-600';
  newItem.innerHTML = `
    <div class="grid grid-cols-12 gap-2">
      <div class="col-span-12 md:col-span-4">
        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Vote Head</label>
        <select name="items[${itemIndex}][vote_head_id]" class="vote-head-select w-full px-2 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-green-500">
          <option value="">Select...</option>
        </select>
      </div>
      <div class="col-span-12 md:col-span-5">
        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Particular</label>
        <input type="text" name="items[${itemIndex}][description]" placeholder="Description..." 
               class="w-full px-2 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-green-500">
      </div>
      <div class="col-span-8 md:col-span-2">
        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Amount</label>
        <input type="number" name="items[${itemIndex}][amount]" value="0" class="item-amount w-full px-2 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-green-500" step="0.01" min="0">
      </div>
      <div class="col-span-4 md:col-span-1 flex items-end justify-end">
        <button type="button" class="remove-item-btn text-red-500 hover:text-red-700 text-sm p-1.5">
          <i class="fas fa-trash-alt"></i>
        </button>
      </div>
    </div>
  `;
  container.appendChild(newItem);
  
  const voteHeadSelect = newItem.querySelector('.vote-head-select');
  voteHeadSelect.innerHTML = '<option value="">Select...</option>' + 
    voteHeads.map(vh => `<option value="${vh.id}">${escapeHtml(vh.name)} (${escapeHtml(vh.alias)})</option>`).join('');
  
  newItem.querySelector('.item-amount').addEventListener('input', () => {
    calculateTotal();
    generateReceiptPreview();
  });
  newItem.querySelector('.remove-item-btn').addEventListener('click', function() {
    newItem.remove();
    calculateTotal();
    generateReceiptPreview();
  });
  newItem.querySelector('input[name*="description"]').addEventListener('input', () => generateReceiptPreview());
  voteHeadSelect.addEventListener('change', () => generateReceiptPreview());
  
  itemIndex++;
  generateReceiptPreview();
});

function calculateTotal() {
  let subtotal = 0;
  document.querySelectorAll('.item-amount').forEach(input => {
    subtotal += parseFloat(input.value) || 0;
  });
  
  document.getElementById('totalAmount').textContent = `KES ${subtotal.toLocaleString()}`;
  return subtotal;
}

// Generate receipt preview (without vote head column)
function generateReceiptPreview() {
  const payerName = document.getElementById('payerName').value || '_________________________';
  const payerType = document.getElementById('payerType').value;
  const payerTypeText = document.getElementById('payerType').options[document.getElementById('payerType').selectedIndex]?.text || 'Not specified';
  const paymentDate = document.getElementById('paymentDate').value || new Date().toISOString().split('T')[0];
  const paymentMode = document.getElementById('paymentMode').value;
  const paymentModeText = document.getElementById('paymentMode').options[document.getElementById('paymentMode').selectedIndex]?.text || 'Not specified';
  const paymentCode = document.getElementById('paymentCode').value || 'N/A';
  const notes = document.getElementById('notes').value || '';
  const receiptNumber = document.getElementById('receiptNumberDisplay').textContent;
  
  const items = [];
  document.querySelectorAll('.receipt-item').forEach((item) => {
    const voteHeadSelect = item.querySelector('.vote-head-select');
    const voteHeadId = voteHeadSelect.value;
    const description = item.querySelector('input[name*="description"]').value;
    const amount = parseFloat(item.querySelector('.item-amount').value) || 0;
    
    if (voteHeadId && description && amount > 0) {
      items.push({ 
        description: description, 
        amount: amount 
      });
    }
  });
  
  const total = calculateTotal();
  
  // Build preview HTML (without Vote Head column)
  let itemsHtml = '';
  if (items.length === 0) {
    itemsHtml = `
      <tr>
        <td colspan="2" style="padding: 30px; text-align: center; color: #999;">No items added yet</td>
      </tr>
    `;
  } else {
    items.forEach(item => {
      itemsHtml += `
        <tr>
          <td style="padding: 8px; border: 1px solid #ddd;">${escapeHtml(item.description)}</td>
          <td style="padding: 8px; border: 1px solid #ddd; text-align: right;">${formatNumber(item.amount)}</td>
        </tr>
      `;
    });
  }
  
  const html = `
    <div style="font-family: 'Helvetica', Arial, sans-serif; padding: 20px; max-width: 100%; margin: 0 auto; background: white;">
      <!-- Header -->
      <div style="text-align: center; margin-bottom: 25px;">
        <h2 style="color: #1e3a8a; margin-bottom: 5px; font-size: 20px;">OFFICIAL RECEIPT</h2>
        <div style="border-top: 2px solid #1e3a8a; width: 80px; margin: 5px auto;"></div>
        <p style="color: #666; font-size: 11px; margin-top: 5px;">${escapeHtml(document.querySelector('.font-semibold')?.textContent || 'School Name')}</p>
        <hr style="margin: 10px 0; border: none; border-top: 1px dashed #ccc;">
        <table style="width: 100%; margin-top: 10px;">
          <tr>
            <td style="text-align: left;"><strong>Receipt No:</strong> ${receiptNumber}</td>
            <td style="text-align: right;"><strong>Date:</strong> ${paymentDate}</td>
          </tr>
        </table>
      </div>
      
      <!-- Payer Details -->
      <div style="margin-bottom: 20px; background: #f9fafb; padding: 12px; border-radius: 5px;">
        <table style="width: 100%; border-collapse: collapse;">
          <tr>
            <td style="padding: 4px;"><strong>Received From:</strong></td>
            <td style="padding: 4px;">${escapeHtml(payerName)}</td>
            <td style="padding: 4px;"><strong>Payer Type:</strong></td>
            <td style="padding: 4px;">${escapeHtml(payerTypeText)}</td>
          </tr>
          <tr>
            <td style="padding: 4px;"><strong>Payment Mode:</strong></td>
            <td style="padding: 4px;">${escapeHtml(paymentModeText)}</td>
            <td style="padding: 4px;"><strong>Transaction ID:</strong></td>
            <td style="padding: 4px;">${escapeHtml(paymentCode)}</td>
          </tr>
        </table>
      </div>
      
      <!-- Items Table (without Vote Head) -->
      <table style="width: 100%; border-collapse: collapse; border: 1px solid #ddd; margin-bottom: 15px;">
        <thead>
          <tr style="background-color: #f3f4f6;">
            <th style="padding: 10px; border: 1px solid #ddd; text-align: left; font-size: 12px;">Particulars</th>
            <th style="padding: 10px; border: 1px solid #ddd; text-align: right; font-size: 12px;">Amount (KES)</th>
          </tr>
        </thead>
        <tbody>
          ${itemsHtml}
        </tbody>
        <tfoot>
          <tr style="background-color: #fef2f2;">
            <td style="padding: 8px; border: 1px solid #ddd; text-align: right;"><strong>TOTAL:</strong></td>
            <td style="padding: 8px; border: 1px solid #ddd; text-align: right;"><strong>${formatNumber(total)}</strong></td>
          </tr>
        </tfoot>
      </table>
      
      ${notes ? `
      <div style="margin-top: 15px; padding: 10px; background-color: #f0fdf4; border-left: 3px solid #10b981; border-radius: 4px;">
        <p style="margin: 0; font-size: 11px;"><strong>Notes:</strong> ${escapeHtml(notes)}</p>
      </div>
      ` : ''}
      
      <!-- Amount in words -->
      <div style="margin-top: 15px; padding: 8px; background-color: #f8fafc; border-radius: 4px;">
        <p style="margin: 0; font-size: 11px;"><strong>Amount in words:</strong> ${numberToWords(Math.floor(total))} Shillings Only.</p>
      </div>
      
      <!-- Signatures -->
      <div style="margin-top: 30px;">
        <table style="width: 100%;">
          <tr>
            <td style="text-align: center; width: 50%;">
              <div style="border-top: 1px solid #000; width: 80%; margin: 0 auto; padding-top: 5px;">
                <span style="font-size: 10px;">Finance Officer</span>
              </div>
            </td>
            <td style="text-align: center; width: 50%;">
              <div style="border-top: 1px solid #000; width: 80%; margin: 0 auto; padding-top: 5px;">
                <span style="font-size: 10px;">Principal / Head of School</span>
              </div>
            </td>
          </tr>
        </table>
      </div>
      
      <!-- Footer -->
      <div style="margin-top: 25px; text-align: center; font-size: 10px; color: #666; border-top: 1px solid #eee; padding-top: 10px;">
        <p>This is a computer generated receipt - No signature required</p>
        <p>Generated on: ${new Date().toLocaleString()}</p>
      </div>
    </div>
  `;
  
  document.getElementById('receiptPreview').innerHTML = html;
  document.getElementById('previewStatus').textContent = 'Ready';
}

function formatNumber(num) {
  return num.toLocaleString('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
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

// Save receipt to API
document.getElementById('receiptForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  
  const payerType = document.getElementById('payerType').value;
  const payerName = document.getElementById('payerName').value;
  const paymentDate = document.getElementById('paymentDate').value;
  const paymentMode = document.getElementById('paymentMode').value;
  const paymentCode = document.getElementById('paymentCode').value;
  const notes = document.getElementById('notes').value;
  const receiptNumber = document.getElementById('receiptNumberDisplay').textContent;
  
  if (!payerName) {
    Swal.fire('Error', 'Please enter payer name', 'error');
    return;
  }
  
  if (!paymentMode) {
    Swal.fire('Error', 'Please select payment mode', 'error');
    return;
  }
  
  const items = [];
  let hasItems = false;
  
  document.querySelectorAll('.receipt-item').forEach(item => {
    const voteHeadId = item.querySelector('.vote-head-select').value;
    const description = item.querySelector('input[name*="description"]').value;
    const amount = parseFloat(item.querySelector('.item-amount').value) || 0;
    
    if (voteHeadId && description && amount > 0) {
      hasItems = true;
      items.push({ vote_head_id: parseInt(voteHeadId), description, amount });
    }
  });
  
  if (!hasItems) {
    Swal.fire('Error', 'Please add at least one receipt item', 'error');
    return;
  }
  
  const total = parseFloat(document.getElementById('totalAmount').textContent.replace('KES ', '').replace(/,/g, '')) || 0;
  
  Swal.fire({
    title: 'Saving...',
    text: 'Please wait while we save your receipt',
    allowOutsideClick: false,
    didOpen: () => {
      Swal.showLoading();
    }
  });
  
  try {
    const response = await fetch('/feesystem/api/feesystem/save_other_income.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        receipt_number: receiptNumber,
        payer_type: payerType,
        payer_id: null,
        payer_name: payerName,
        payment_date: paymentDate,
        payment_mode: paymentMode,
        payment_code: paymentCode || null,
        items: items,
        subtotal: total,
        tax_amount: 0,
        total_amount: total,
        notes: notes || null,
        created_by_name: currentUser
      })
    });
    
    const data = await response.json();
    
    if (data.success) {
      Swal.fire({
        title: 'Success!',
        text: 'Receipt saved successfully',
        icon: 'success',
        confirmButtonText: 'OK'
      }).then(() => {
        const newReceipt = {
          id: data.data.receipt_id,
          receipt_number: receiptNumber,
          payer_name: payerName,
          payer_type: payerType,
          payment_date: paymentDate,
          payment_mode: paymentMode,
          payment_code: paymentCode,
          total: total,
          items: items,
          notes: notes,
          issued_by: currentUser,
          created_at: new Date().toISOString()
        };
        
        receipts.unshift(newReceipt);
        renderReceiptHistory();
        
        // Update receipt number display for next receipt
        const nextNum = parseInt(receiptNumber.split('-').pop()) + 1;
        const newReceiptNumber = receiptNumber.replace(/\d{4}$/, String(nextNum).padStart(4, '0'));
        document.getElementById('receiptNumberDisplay').textContent = newReceiptNumber;
        
        // Display the saved receipt in preview
        displayReceiptInPreview(newReceipt);
        
        // Reset form but keep the new receipt number
        resetFormKeepNumber(newReceiptNumber);
      });
    } else {
      throw new Error(data.message || 'Failed to save receipt');
    }
  } catch (error) {
    console.error('Error:', error);
    Swal.fire('Error', error.message || 'Failed to save receipt', 'error');
  }
});

function resetFormKeepNumber(newReceiptNumber) {
  // Clear items but keep one empty item
  document.getElementById('receiptItemsContainer').innerHTML = `
    <div class="receipt-item bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3 border border-gray-200 dark:border-gray-600">
      <div class="grid grid-cols-12 gap-2">
        <div class="col-span-12 md:col-span-4">
          <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Vote Head</label>
          <select name="items[0][vote_head_id]" class="vote-head-select w-full px-2 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-green-500">
            <option value="">Select...</option>
          </select>
        </div>
        <div class="col-span-12 md:col-span-5">
          <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Particular</label>
          <input type="text" name="items[0][description]" placeholder="Description..." 
                 class="w-full px-2 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-green-500">
        </div>
        <div class="col-span-8 md:col-span-2">
          <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Amount</label>
          <input type="number" name="items[0][amount]" value="0" class="item-amount w-full px-2 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-green-500" step="0.01" min="0">
        </div>
        <div class="col-span-4 md:col-span-1 flex items-end justify-end">
          <button type="button" class="remove-item-btn text-red-500 hover:text-red-700 text-sm p-1.5">
            <i class="fas fa-trash-alt"></i>
          </button>
        </div>
      </div>
    </div>
  `;
  
  document.getElementById('payerName').value = '';
  document.getElementById('paymentDate').value = new Date().toISOString().split('T')[0];
  document.getElementById('paymentMode').value = '';
  document.getElementById('paymentCode').value = '';
  document.getElementById('notes').value = '';
  document.getElementById('totalAmount').textContent = 'KES 0.00';
  document.getElementById('receiptNumberDisplay').textContent = newReceiptNumber;
  
  populateVoteHeadSelects();
  
  const firstItemAmount = document.querySelector('.item-amount');
  const firstItemDesc = document.querySelector('input[name*="description"]');
  const firstItemSelect = document.querySelector('.vote-head-select');
  
  if (firstItemAmount) {
    firstItemAmount.addEventListener('input', () => {
      calculateTotal();
      generateReceiptPreview();
    });
  }
  if (firstItemDesc) {
    firstItemDesc.addEventListener('input', () => generateReceiptPreview());
  }
  if (firstItemSelect) {
    firstItemSelect.addEventListener('change', () => generateReceiptPreview());
  }
  
  itemIndex = 1;
  generateReceiptPreview();
}

// Display a receipt in the preview area
function displayReceiptInPreview(receipt) {
  let itemsHtml = '';
  if (receipt.items && receipt.items.length > 0) {
    receipt.items.forEach(item => {
      itemsHtml += `
        <tr>
          <td style="padding: 8px; border: 1px solid #ddd;">${escapeHtml(item.description)}</td>
          <td style="padding: 8px; border: 1px solid #ddd; text-align: right;">${formatNumber(item.amount)}</td>
        </tr>
      `;
    });
  } else {
    itemsHtml = '<tr><td colspan="2" style="padding: 30px; text-align: center;">No items</td</tr>';
  }
  
  const html = `
    <div style="font-family: 'Helvetica', Arial, sans-serif; padding: 20px; max-width: 100%; background: white;">
      <div style="text-align: center; margin-bottom: 25px;">
        <h2 style="color: #1e3a8a; margin-bottom: 5px; font-size: 20px;">OFFICIAL RECEIPT</h2>
        <div style="border-top: 2px solid #1e3a8a; width: 80px; margin: 5px auto;"></div>
        <hr style="margin: 10px 0; border: none; border-top: 1px dashed #ccc;">
        <table style="width: 100%; margin-top: 10px;">
          <tr><td style="text-align: left;"><strong>Receipt No:</strong> ${escapeHtml(receipt.receipt_number)}</td>
           <td style="text-align: right;"><strong>Date:</strong> ${receipt.payment_date}</td>
          </tr>
        </table>
      </div>
      
      <div style="margin-bottom: 20px; background: #f9fafb; padding: 12px; border-radius: 5px;">
        <table style="width: 100%;">
          <tr><td><strong>Received From:</strong> ${escapeHtml(receipt.payer_name)}</td>
           <td><strong>Payment Mode:</strong> ${escapeHtml(receipt.payment_mode)}</td>
          </tr>
          ${receipt.payment_code ? `<tr><td><strong>Transaction ID:</strong> ${escapeHtml(receipt.payment_code)}</td><td></td></tr>` : ''}
        </table>
      </div>
      
      <table style="width: 100%; border-collapse: collapse; border: 1px solid #ddd;">
        <thead><tr style="background-color: #f3f4f6;"><th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Particulars</th><th style="padding: 10px; border: 1px solid #ddd; text-align: right;">Amount</th></tr></thead>
        <tbody>${itemsHtml}</tbody>
        <tfoot><tr style="background-color: #fef2f2;"><td style="padding: 8px; text-align: right;"><strong>TOTAL:</strong></td><td style="padding: 8px; text-align: right;"><strong>${formatNumber(receipt.total)}</strong></td></tr></tfoot>
      </table>
      
      ${receipt.notes ? `<div style="margin-top: 15px; padding: 10px; background-color: #f0fdf4; border-left: 3px solid #10b981;"><strong>Notes:</strong> ${escapeHtml(receipt.notes)}</div>` : ''}
      
      <div style="margin-top: 15px; padding: 8px; background-color: #f8fafc; border-radius: 4px;">
        <p style="margin: 0; font-size: 11px;"><strong>Amount in words:</strong> ${numberToWords(Math.floor(receipt.total))} Shillings Only.</p>
      </div>
      
      <div style="margin-top: 30px;">
        <table style="width: 100%;">
          <tr><td style="text-align: center;"><div style="border-top: 1px solid #000; width: 80%; margin: 0 auto; padding-top: 5px;">Finance Officer</div></td>
           <td style="text-align: center;"><div style="border-top: 1px solid #000; width: 80%; margin: 0 auto; padding-top: 5px;">Principal / Head of School</div></td>
          </tr>
        </table>
      </div>
      
      <div style="margin-top: 25px; text-align: center; font-size: 10px; color: #666; border-top: 1px solid #eee; padding-top: 10px;">
        <p>Generated on: ${new Date().toLocaleString()}</p>
      </div>
    </div>
  `;
  
  document.getElementById('receiptPreview').innerHTML = html;
  document.getElementById('previewStatus').textContent = 'Viewing Saved Receipt';
  
  document.getElementById('downloadReceiptBtn').onclick = () => {
    generateReceiptPDF(receipt);
  };
}

function resetForm() {
  document.getElementById('receiptForm').reset();
  document.getElementById('receiptItemsContainer').innerHTML = `
    <div class="receipt-item bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3 border border-gray-200 dark:border-gray-600">
      <div class="grid grid-cols-12 gap-2">
        <div class="col-span-12 md:col-span-4">
          <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Vote Head</label>
          <select name="items[0][vote_head_id]" class="vote-head-select w-full px-2 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-green-500">
            <option value="">Select...</option>
          </select>
        </div>
        <div class="col-span-12 md:col-span-5">
          <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Particular</label>
          <input type="text" name="items[0][description]" placeholder="Description..." 
                 class="w-full px-2 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-green-500">
        </div>
        <div class="col-span-8 md:col-span-2">
          <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Amount</label>
          <input type="number" name="items[0][amount]" value="0" class="item-amount w-full px-2 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-green-500" step="0.01" min="0">
        </div>
        <div class="col-span-4 md:col-span-1 flex items-end justify-end">
          <button type="button" class="remove-item-btn text-red-500 hover:text-red-700 text-sm p-1.5">
            <i class="fas fa-trash-alt"></i>
          </button>
        </div>
      </div>
    </div>
  `;
  
  document.getElementById('payerName').value = '';
  document.getElementById('paymentDate').value = new Date().toISOString().split('T')[0];
  document.getElementById('paymentMode').value = '';
  document.getElementById('paymentCode').value = '';
  document.getElementById('notes').value = '';
  document.getElementById('totalAmount').textContent = 'KES 0.00';
  
  populateVoteHeadSelects();
  
  const firstItemAmount = document.querySelector('.item-amount');
  const firstItemDesc = document.querySelector('input[name*="description"]');
  const firstItemSelect = document.querySelector('.vote-head-select');
  
  if (firstItemAmount) {
    firstItemAmount.addEventListener('input', () => {
      calculateTotal();
      generateReceiptPreview();
    });
  }
  if (firstItemDesc) {
    firstItemDesc.addEventListener('input', () => generateReceiptPreview());
  }
  if (firstItemSelect) {
    firstItemSelect.addEventListener('change', () => generateReceiptPreview());
  }
  
  itemIndex = 1;
  generateReceiptPreview();
  
  document.getElementById('downloadReceiptBtn').onclick = defaultDownloadHandler;
}

// Default download handler for current form data
const defaultDownloadHandler = async () => {
  const receiptNumber = document.getElementById('receiptNumberDisplay').textContent;
  const payerName = document.getElementById('payerName').value || '_________________________';
  const payerType = document.getElementById('payerType').value;
  const paymentDate = document.getElementById('paymentDate').value;
  const paymentMode = document.getElementById('paymentMode').value;
  const paymentCode = document.getElementById('paymentCode').value;
  const notes = document.getElementById('notes').value;
  
  const items = [];
  document.querySelectorAll('.receipt-item').forEach((item) => {
    const voteHeadId = item.querySelector('.vote-head-select').value;
    const description = item.querySelector('input[name*="description"]').value;
    const amount = parseFloat(item.querySelector('.item-amount').value) || 0;
    
    if (voteHeadId && description && amount > 0) {
      items.push({ 
        vote_head_id: parseInt(voteHeadId),
        description: description, 
        amount: amount 
      });
    }
  });
  
  const total = parseFloat(document.getElementById('totalAmount').textContent.replace('KES ', '').replace(/,/g, '')) || 0;
  
  Swal.fire({
    title: 'Generating PDF...',
    text: 'Please wait while we create your receipt',
    allowOutsideClick: false,
    didOpen: () => {
      Swal.showLoading();
    }
  });
  
  try {
    const response = await fetch('/feesystem/api/feesystem/generate_other_income_pdf.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        receipt_number: receiptNumber,
        payer_name: payerName,
        payer_type: payerType,
        payment_date: paymentDate,
        payment_mode: paymentMode,
        payment_code: paymentCode || '',
        notes: notes || '',
        total_amount: total,
        items: items
      })
    });
    
    const data = await response.json();
    
    if (data.success) {
      Swal.close();
      window.open(data.pdf_url, '_blank');
    } else {
      throw new Error(data.message || 'Failed to generate PDF');
    }
  } catch (error) {
    console.error('Error:', error);
    Swal.fire('Error', error.message || 'Failed to generate PDF', 'error');
  }
};

// Load receipts from API
async function loadReceiptsFromAPI() {
    try {
        const response = await fetch('/feesystem/api/feesystem/get_other_income_receipts.php?limit=100');
        const data = await response.json();
        
        if (data.success && data.data) {
            receipts = data.data.map(receipt => ({
                id: receipt.id,
                receipt_number: receipt.receipt_number,
                payer_name: receipt.payer_name,
                payer_type: receipt.payer_type,
                payment_date: receipt.payment_date,
                payment_mode: receipt.payment_mode,
                payment_code: receipt.payment_code,
                total: receipt.total_amount,
                items: receipt.items || [],
                notes: receipt.notes,
                issued_by: receipt.issued_by,
                created_at: receipt.created_at
            }));
            renderReceiptHistory();
        }
    } catch (error) {
        console.error('Error loading receipts:', error);
        // Fallback to localStorage if API fails
        loadReceiptsFromLocal();
    }
}

// Load receipts from localStorage as fallback
function loadReceiptsFromLocal() {
    const localReceipts = localStorage.getItem('other_income_receipts');
    if (localReceipts) {
        receipts = JSON.parse(localReceipts);
        renderReceiptHistory();
    }
}

// Save receipt to localStorage as backup
function saveReceiptToLocal(receiptData) {
    let localReceipts = JSON.parse(localStorage.getItem('other_income_receipts') || '[]');
    localReceipts.unshift(receiptData);
    // Keep only last 100 receipts
    localReceipts = localReceipts.slice(0, 100);
    localStorage.setItem('other_income_receipts', JSON.stringify(localReceipts));
}

function renderReceiptHistory() {
    const tbody = document.getElementById('receiptHistoryBody');
    
    if (!receipts || receipts.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-gray-500 py-8">No receipts found</td></tr>';
        return;
    }
    
    tbody.innerHTML = receipts.map(receipt => `
        <tr class="border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer" onclick="viewReceipt(${receipt.id})">
            <td class="px-4 py-3 font-mono text-sm">${escapeHtml(receipt.receipt_number)}</td>
            <td class="px-4 py-3">${receipt.payment_date}</td>
            <td class="px-4 py-3">${escapeHtml(receipt.payer_name)}</td>
            <td class="px-4 py-3 text-right font-semibold text-green-600">KES ${receipt.total.toLocaleString()}</td>
            <td class="px-4 py-3">${escapeHtml(receipt.payment_mode)}</td>
            <td class="px-4 py-3">${escapeHtml(receipt.issued_by || currentUser)}</td>
            <td class="px-4 py-3 text-center">
                <button onclick="event.stopPropagation(); downloadReceipt(${receipt.id})" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300" title="Download PDF">
                    <i class="fas fa-download"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

// Update the save receipt function to also save to API and refresh list
document.getElementById('receiptForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    // ... existing validation code ...
    
    try {
        const response = await fetch('/feesystem/api/feesystem/save_other_income.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                receipt_number: receiptNumber,
                payer_type: payerType,
                payer_id: null,
                payer_name: payerName,
                payment_date: paymentDate,
                payment_mode: paymentMode,
                payment_code: paymentCode || null,
                items: items,
                subtotal: total,
                tax_amount: 0,
                total_amount: total,
                notes: notes || null,
                created_by_name: currentUser
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Also save to localStorage as backup
            const newReceipt = {
                id: data.data.receipt_id,
                receipt_number: receiptNumber,
                payer_name: payerName,
                payer_type: payerType,
                payment_date: paymentDate,
                payment_mode: paymentMode,
                payment_code: paymentCode,
                total: total,
                items: items,
                notes: notes,
                issued_by: currentUser,
                created_at: new Date().toISOString()
            };
            
            saveReceiptToLocal(newReceipt);
            
            // Reload receipts from API to refresh the table
            await loadReceiptsFromAPI();
            
            Swal.fire({
                title: 'Success!',
                text: 'Receipt saved successfully',
                icon: 'success',
                confirmButtonText: 'OK'
            }).then(() => {
                // Update receipt number display for next receipt
                const nextNum = parseInt(receiptNumber.split('-').pop()) + 1;
                const newReceiptNumber = receiptNumber.replace(/\d{4}$/, String(nextNum).padStart(4, '0'));
                document.getElementById('receiptNumberDisplay').textContent = newReceiptNumber;
                
                // Display the saved receipt in preview
                displayReceiptInPreview(newReceipt);
                
                // Reset form but keep the new receipt number
                resetFormKeepNumber(newReceiptNumber);
            });
        } else {
            throw new Error(data.message || 'Failed to save receipt');
        }
    } catch (error) {
        console.error('Error:', error);
        
        // Fallback: Save locally if API fails
        const fallbackReceipt = {
            id: Date.now(),
            receipt_number: receiptNumber,
            payer_name: payerName,
            payer_type: payerType,
            payment_date: paymentDate,
            payment_mode: paymentMode,
            payment_code: paymentCode,
            total: total,
            items: items,
            notes: notes,
            issued_by: currentUser,
            created_at: new Date().toISOString()
        };
        
        saveReceiptToLocal(fallbackReceipt);
        receipts.unshift(fallbackReceipt);
        renderReceiptHistory();
        
        Swal.fire({
            title: 'Warning',
            text: 'Receipt saved locally only. Server connection issue.',
            icon: 'warning',
            confirmButtonText: 'OK'
        });
        
        // Still update the form
        const nextNum = parseInt(receiptNumber.split('-').pop()) + 1;
        const newReceiptNumber = receiptNumber.replace(/\d{4}$/, String(nextNum).padStart(4, '0'));
        document.getElementById('receiptNumberDisplay').textContent = newReceiptNumber;
        displayReceiptInPreview(fallbackReceipt);
        resetFormKeepNumber(newReceiptNumber);
    }
});

// Initialize - load receipts from API on page load
document.addEventListener('DOMContentLoaded', () => {
    loadVoteHeads();
    loadReceiptsFromAPI(); // Load receipts from backend API
    
    document.getElementById('downloadReceiptBtn').onclick = defaultDownloadHandler;
    
    const initialAmount = document.querySelector('.item-amount');
    const initialDesc = document.querySelector('input[name*="description"]');
    const initialSelect = document.querySelector('.vote-head-select');
    
    if (initialAmount) {
        initialAmount.addEventListener('input', () => {
            calculateTotal();
            generateReceiptPreview();
        });
    }
    if (initialDesc) {
        initialDesc.addEventListener('input', () => generateReceiptPreview());
    }
    if (initialSelect) {
        initialSelect.addEventListener('change', () => generateReceiptPreview());
    }
    
    generateReceiptPreview();
});

function downloadReceipt(receiptId) {
  const receipt = receipts.find(r => r.id === receiptId);
  if (receipt) {
    generateReceiptPDF(receipt);
  }
}

function viewReceipt(receiptId) {
  const receipt = receipts.find(r => r.id === receiptId);
  if (receipt) {
    displayReceiptInPreview(receipt);
  }
}

// Generate PDF using the API
async function generateReceiptPDF(receipt) {
  Swal.fire({
    title: 'Generating PDF...',
    text: 'Please wait while we create your receipt',
    allowOutsideClick: false,
    didOpen: () => {
      Swal.showLoading();
    }
  });
  
  try {
    const response = await fetch('/feesystem/api/feesystem/generate_other_income_pdf.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        receipt_number: receipt.receipt_number,
        payer_name: receipt.payer_name,
        payer_type: receipt.payer_type,
        payment_date: receipt.payment_date,
        payment_mode: receipt.payment_mode,
        payment_code: receipt.payment_code || '',
        notes: receipt.notes || '',
        total_amount: receipt.total,
        items: receipt.items || []
      })
    });
    
    const data = await response.json();
    
    if (data.success) {
      Swal.close();
      window.open(data.pdf_url, '_blank');
    } else {
      throw new Error(data.message || 'Failed to generate PDF');
    }
  } catch (error) {
    console.error('Error:', error);
    Swal.fire('Error', error.message || 'Failed to generate PDF', 'error');
  }
}

// Clear form
document.getElementById('cancelBtn').addEventListener('click', () => {
  resetForm();
});

// Input event listeners for preview
document.getElementById('payerName').addEventListener('input', () => generateReceiptPreview());
document.getElementById('payerType').addEventListener('change', () => generateReceiptPreview());
document.getElementById('paymentDate').addEventListener('change', () => generateReceiptPreview());
document.getElementById('paymentMode').addEventListener('change', () => generateReceiptPreview());
document.getElementById('paymentCode').addEventListener('input', () => generateReceiptPreview());
document.getElementById('notes').addEventListener('input', () => generateReceiptPreview());

function escapeHtml(text) {
  if (!text) return '';
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
  loadVoteHeads();
  
  document.getElementById('downloadReceiptBtn').onclick = defaultDownloadHandler;
  
  const initialAmount = document.querySelector('.item-amount');
  const initialDesc = document.querySelector('input[name*="description"]');
  const initialSelect = document.querySelector('.vote-head-select');
  
  if (initialAmount) {
    initialAmount.addEventListener('input', () => {
      calculateTotal();
      generateReceiptPreview();
    });
  }
  if (initialDesc) {
    initialDesc.addEventListener('input', () => generateReceiptPreview());
  }
  if (initialSelect) {
    initialSelect.addEventListener('change', () => generateReceiptPreview());
  }
  
  renderReceiptHistory();
  generateReceiptPreview();
});
</script>

<?php include_once('../../includes/footer.php'); ?>