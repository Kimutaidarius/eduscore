<?php
// Get current page to highlight active menu
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

// Helper function to check if a menu item is active
function isActive($pageName, $currentPage, $currentDir) {
    return ($currentPage == $pageName) ? 'border-l-4 border-indigo-500 bg-indigo-800' : 'border-l-4 border-transparent';
}

function isDropdownActive($pages, $currentPage) {
    return in_array($currentPage, $pages) ? 'bg-indigo-800' : '';
}

// Absolute base path that always includes feesystem folder
$baseUrl = '/feesystem/';
?>
<aside id="sidebar" class="sidebar bg-indigo-900 text-white w-64 min-h-screen flex flex-col z-10 transform transition-all duration-300 ease-in-out">
  <!-- Logo Section -->
  <div class="p-4 flex items-center justify-between">
    <div class="flex items-center space-x-2">
      <svg class="h-8 w-8 text-indigo-300" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M5 7H19V19H5V7Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M19 7L12 3L5 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M9 12H15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M9 16H15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      <span class="text-xl font-bold">EduScore</span>
    </div>
    <button id="sidebar-toggle" class="lg:hidden text-white focus:outline-none">
      <i class="fas fa-times"></i>
    </button>
  </div>
  
  <!-- Navigation Links -->
  <nav class="flex-grow py-4 overflow-y-auto">
    <ul class="space-y-1">
      <!-- Dashboard -->
      <li>
        <a href="<?php echo $baseUrl; ?>dashboard.php" class="flex items-center px-4 py-3 hover:bg-indigo-800 transition-colors duration-200 text-indigo-100 hover:text-white <?php echo isActive('dashboard.php', $current_page, $current_dir); ?>">
          <i class="fas fa-chart-pie w-6"></i>
          <span class="ml-2">Dashboard</span>
        </a>
      </li>
      
      <!-- Registration Dropdown -->
      <?php
      $registrationPages = ['classes.php', 'students.php', 'student-list.php', 'teachers.php', 'subjects.php', 'roles.php', 'vote-heads.php', 'staff.php', 'users.php'];
      $isRegistrationActive = in_array($current_page, $registrationPages);
      ?>
      <li class="relative">
        <a href="javascript:void(0)" onclick="toggleSubmenu('registration-submenu')" class="flex items-center px-4 py-3 hover:bg-indigo-800 transition-colors duration-200 text-indigo-100 hover:text-white border-l-4 border-transparent <?php echo $isRegistrationActive ? 'bg-indigo-800' : ''; ?>">
          <i class="fas fa-address-card w-6"></i>
          <span class="ml-2 flex-grow">Registration</span>
          <i class="fas fa-chevron-down text-xs"></i>
        </a>
        <ul id="registration-submenu" class="<?php echo $isRegistrationActive ? '' : 'hidden'; ?> bg-indigo-800 rounded-md mt-1 mx-2">
          <li><a href="<?php echo $baseUrl; ?>pages/registration/classes.php" class="block px-8 py-2 text-sm hover:bg-indigo-700 rounded <?php echo ($current_page == 'classes.php') ? 'bg-indigo-700' : ''; ?>">Classes</a></li>
          <li><a href="<?php echo $baseUrl; ?>pages/registration/students.php" class="block px-8 py-2 text-sm hover:bg-indigo-700 rounded <?php echo ($current_page == 'students.php') ? 'bg-indigo-700' : ''; ?>">Students</a></li>
          <li><a href="<?php echo $baseUrl; ?>pages/registration/roles.php" class="block px-8 py-2 text-sm hover:bg-indigo-700 rounded <?php echo ($current_page == 'roles.php') ? 'bg-indigo-700' : ''; ?>">Roles</a></li>
          <li><a href="<?php echo $baseUrl; ?>pages/registration/vote-heads.php" class="block px-8 py-2 text-sm hover:bg-indigo-700 rounded <?php echo ($current_page == 'vote-heads.php') ? 'bg-indigo-700' : ''; ?>">Vote Heads</a></li>
          <li><a href="<?php echo $baseUrl; ?>pages/registration/staff.php" class="block px-8 py-2 text-sm hover:bg-indigo-700 rounded <?php echo ($current_page == 'staff.php') ? 'bg-indigo-700' : ''; ?>">Staff</a></li>
        </ul>
      </li>
      
      <!-- Fee Management Dropdown -->
      <?php
      $feePages = ['fee-structure.php', 'initial-balance.php', 'invoices.php', 'fee-collection.php', 'grants.php', 'other-income.php', 'fee-balances.php', 'records.php'];
      $isFeeActive = in_array($current_page, $feePages);
      ?>
      <li class="relative">
        <a href="javascript:void(0)" onclick="toggleSubmenu('fee-submenu')" class="flex items-center px-4 py-3 hover:bg-indigo-800 transition-colors duration-200 text-indigo-100 hover:text-white border-l-4 border-transparent <?php echo $isFeeActive ? 'bg-indigo-800' : ''; ?>">
          <i class="fas fa-coins w-6"></i>
          <span class="ml-2 flex-grow">Fee Management</span>
          <i class="fas fa-chevron-down text-xs"></i>
        </a>
        <ul id="fee-submenu" class="<?php echo $isFeeActive ? '' : 'hidden'; ?> bg-indigo-800 rounded-md mt-1 mx-2">
          <li><a href="<?php echo $baseUrl; ?>pages/fee-management/fee-structure.php" class="block px-8 py-2 text-sm hover:bg-indigo-700 rounded <?php echo ($current_page == 'fee-structure.php') ? 'bg-indigo-700' : ''; ?>">Fee Structure</a></li>
          <li><a href="<?php echo $baseUrl; ?>pages/fee-management/initial-balance.php" class="block px-8 py-2 text-sm hover:bg-indigo-700 rounded <?php echo ($current_page == 'initial-balance.php') ? 'bg-indigo-700' : ''; ?>">Initial Balance</a></li>
          <li><a href="<?php echo $baseUrl; ?>pages/fee-management/invoices.php" class="block px-8 py-2 text-sm hover:bg-indigo-700 rounded <?php echo ($current_page == 'invoices.php') ? 'bg-indigo-700' : ''; ?>">Invoices</a></li>
          <li><a href="<?php echo $baseUrl; ?>pages/fee-management/fee-collection.php" class="block px-8 py-2 text-sm hover:bg-indigo-700 rounded <?php echo ($current_page == 'fee-collection.php') ? 'bg-indigo-700' : ''; ?>">Fee Collection</a></li>
          <li><a href="<?php echo $baseUrl; ?>pages/fee-management/grants.php" class="block px-8 py-2 text-sm hover:bg-indigo-700 rounded <?php echo ($current_page == 'grants.php') ? 'bg-indigo-700' : ''; ?>">Grants</a></li>
          <li><a href="<?php echo $baseUrl; ?>pages/fee-management/other-income.php" class="block px-8 py-2 text-sm hover:bg-indigo-700 rounded <?php echo ($current_page == 'other-income.php') ? 'bg-indigo-700' : ''; ?>">Other Income</a></li>
          <li><a href="<?php echo $baseUrl; ?>pages/fee-management/fee-balances.php" class="block px-8 py-2 text-sm hover:bg-indigo-700 rounded <?php echo ($current_page == 'fee-balances.php') ? 'bg-indigo-700' : ''; ?>">Fee Balances</a></li>
          <li><a href="<?php echo $baseUrl; ?>pages/fee-management/records.php" class="block px-8 py-2 text-sm hover:bg-indigo-700 rounded <?php echo ($current_page == 'records.php') ? 'bg-indigo-700' : ''; ?>">Records</a></li>
        </ul>
      </li>
      
      <!-- Payments Dropdown (Payment Vouchers, Payroll, Cash & Bank, Stores) -->
      <?php
      $paymentsPages = ['payment-vouchers.php', 'payroll.php', 'cash-bank.php', 'stores.php'];
      $isPaymentsActive = in_array($current_page, $paymentsPages);
      ?>
      <li class="relative">
        <a href="javascript:void(0)" onclick="toggleSubmenu('payments-submenu')" class="flex items-center px-4 py-3 hover:bg-indigo-800 transition-colors duration-200 text-indigo-100 hover:text-white border-l-4 border-transparent <?php echo $isPaymentsActive ? 'bg-indigo-800' : ''; ?>">
          <i class="fas fa-receipt w-6"></i>
          <span class="ml-2 flex-grow">Payments</span>
          <i class="fas fa-chevron-down text-xs"></i>
        </a>
        <ul id="payments-submenu" class="<?php echo $isPaymentsActive ? '' : 'hidden'; ?> bg-indigo-800 rounded-md mt-1 mx-2">
          <li><a href="<?php echo $baseUrl; ?>pages/payments/payment-vouchers.php" class="block px-8 py-2 text-sm hover:bg-indigo-700 rounded <?php echo ($current_page == 'payment-vouchers.php') ? 'bg-indigo-700' : ''; ?>">Payment Vouchers</a></li>
          <li><a href="<?php echo $baseUrl; ?>pages/payments/payroll.php" class="block px-8 py-2 text-sm hover:bg-indigo-700 rounded <?php echo ($current_page == 'payroll.php') ? 'bg-indigo-700' : ''; ?>">Payroll</a></li>
          <li><a href="<?php echo $baseUrl; ?>pages/payments/cash-bank.php" class="block px-8 py-2 text-sm hover:bg-indigo-700 rounded <?php echo ($current_page == 'cash-bank.php') ? 'bg-indigo-700' : ''; ?>">Cash & Bank</a></li>
          <li><a href="<?php echo $baseUrl; ?>pages/payments/stores.php" class="block px-8 py-2 text-sm hover:bg-indigo-700 rounded <?php echo ($current_page == 'stores.php') ? 'bg-indigo-700' : ''; ?>">Stores</a></li>
        </ul>
      </li>
      
      <!-- Pocket Money -->
      <li>
        <a href="<?php echo $baseUrl; ?>pages/pocket-money/index.php" class="flex items-center px-4 py-3 hover:bg-indigo-800 transition-colors duration-200 text-indigo-100 hover:text-white border-l-4 border-transparent <?php echo ($current_page == 'index.php' && $current_dir == 'pocket-money') ? 'border-l-4 border-indigo-500 bg-indigo-800' : 'border-l-4 border-transparent'; ?>">
          <i class="fas fa-wallet w-6"></i>
          <span class="ml-2">Pocket Money</span>
        </a>
      </li>
      
      <!-- Messaging -->
      <li>
        <a href="<?php echo $baseUrl; ?>pages/messaging/index.php" class="flex items-center px-4 py-3 hover:bg-indigo-800 transition-colors duration-200 text-indigo-100 hover:text-white border-l-4 border-transparent <?php echo ($current_page == 'index.php' && $current_dir == 'messaging') ? 'border-l-4 border-indigo-500 bg-indigo-800' : 'border-l-4 border-transparent'; ?>">
          <i class="fas fa-envelope w-6"></i>
          <span class="ml-2">Messaging</span>
        </a>
      </li>
      
      <!-- Reports -->
      <li>
        <a href="<?php echo $baseUrl; ?>pages/reports/index.php" class="flex items-center px-4 py-3 hover:bg-indigo-800 transition-colors duration-200 text-indigo-100 hover:text-white border-l-4 border-transparent <?php echo ($current_page == 'index.php' && $current_dir == 'reports') ? 'border-l-4 border-indigo-500 bg-indigo-800' : 'border-l-4 border-transparent'; ?>">
          <i class="fas fa-chart-line w-6"></i>
          <span class="ml-2">Reports</span>
        </a>
      </li>
     
      <!-- Administration - Now as a single page with tabs for Billing & Subscriptions -->
      <li>
        <a href="<?php echo $baseUrl; ?>pages/administration/administration.php" class="flex items-center px-4 py-3 hover:bg-indigo-800 transition-colors duration-200 text-indigo-100 hover:text-white <?php echo isActive('administration.php', $current_page, $current_dir); ?>">
          <i class="fas fa-building w-6"></i>
          <span class="ml-2">Administration</span>
        </a>
      </li>
        <!-- Billing & Subscription -->
<li>
  <a href="<?php echo $baseUrl; ?>pages/billing/billing.php" class="flex items-center px-4 py-3 hover:bg-indigo-800 transition-colors duration-200 text-indigo-100 hover:text-white <?php echo isActive('billing.php', $current_page, $current_dir); ?>">
    <i class="fas fa-credit-card w-6"></i>
    <span class="ml-2">Billing & Subscription</span>
  </a>
</li>
    </ul>
  </nav>
  
  <!-- School Info Section -->
  <div class="p-4 bg-indigo-800 text-indigo-200 text-sm">
    <p class="font-semibold"><?php echo htmlspecialchars($_SESSION['school_name'] ?? 'School'); ?></p>
    <p id="current-term-display">Loading...</p>
  </div>
</aside>

<script>
function toggleSubmenu(id) {
  const submenu = document.getElementById(id);
  if (submenu) {
    submenu.classList.toggle('hidden');
  }
}

// Close all submenus when clicking outside (optional)
document.addEventListener('click', function(event) {
  const isClickInsideSidebar = event.target.closest('#sidebar');
  if (!isClickInsideSidebar) {
    const allSubmenus = document.querySelectorAll('#sidebar ul[id$="-submenu"]');
    allSubmenus.forEach(submenu => {
      if (!submenu.classList.contains('hidden')) {
        // Don't auto-close on mobile when sidebar is collapsed
        const sidebar = document.getElementById('sidebar');
        if (sidebar && !sidebar.classList.contains('sidebar-collapsed')) {
          submenu.classList.add('hidden');
        }
      }
    });
  }
});
</script>