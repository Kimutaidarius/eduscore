<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Check if user has finance access
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'finance') {
    header('Location: login.php?error=access_denied');
    exit;
}

require_once('includes/config.php');

$school_id = $_SESSION['school_id'];
$school_name = $_SESSION['school_name'] ?? '';

// Pass school data to JavaScript
$school_id_js = json_encode($school_id);
$teacher_id_js = json_encode($_SESSION['teacher_id'] ?? 0);

include_once('includes/header.php');
include_once('includes/sidebar.php');
?>

<!-- Main Content -->
<main class="main-content flex-grow flex flex-col">
  <!-- Top Navigation Bar -->
  <header class="bg-white shadow-sm dark:bg-gray-800 dark:border-gray-700">
    <div class="px-4 py-3 flex justify-between items-center">
      <div class="flex items-center">
        <button id="mobile-toggle" class="mr-4 text-gray-500 hover:text-indigo-600 focus:outline-none lg:hidden">
          <i class="fas fa-bars text-xl"></i>
        </button>
        <h1 id="page-title" class="text-xl font-semibold text-gray-800 dark:text-white">Fee Management Dashboard</h1>
      </div>
      
      <div class="flex items-center space-x-4">
        <div class="hidden md:block relative">
          <input type="text" placeholder="Search..." class="pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200">
          <span class="absolute left-3 top-2.5 text-gray-400">
            <i class="fas fa-search"></i>
          </span>
        </div>
        
        <button id="theme-toggle" class="p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 focus:outline-none">
          <i class="fas fa-sun text-yellow-500 dark:hidden"></i>
          <i class="fas fa-moon text-blue-300 hidden dark:block"></i>
        </button>
        
        <button class="p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 focus:outline-none relative">
          <i class="fas fa-bell text-gray-600 dark:text-gray-300"></i>
          <span class="absolute top-0 right-0 h-2 w-2 bg-red-500 rounded-full"></span>
        </button>
        
        <div class="relative" id="user-menu-container">
          <button id="user-menu-button" class="flex items-center focus:outline-none">
            <img src="https://cdn.pixabay.com/photo/2015/10/05/22/37/blank-profile-picture-973460_960_720.png" alt="User Avatar" class="w-8 h-8 rounded-full mr-2">
            <span class="hidden md:block text-sm font-medium text-gray-700 dark:text-gray-200"><?php echo htmlspecialchars($_SESSION['email'] ?? 'User'); ?></span>
            <i class="fas fa-chevron-down text-xs ml-2 text-gray-500"></i>
          </button>
          
          <div id="user-dropdown" class="absolute right-0 mt-2 w-48 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg py-1 z-20 hidden">
            <a href="pages/profile.php" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
              <i class="fas fa-user-circle mr-2"></i> My Profile
            </a>
            <a href="pages/settings.php" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
              <i class="fas fa-cog mr-2"></i> Account Settings
            </a>
            <div class="border-t border-gray-200 dark:border-gray-700 my-1"></div>
            <a href="logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-gray-100 dark:hover:bg-gray-700">
              <i class="fas fa-sign-out-alt mr-2"></i> Logout
            </a>
          </div>
        </div>
      </div>
    </div>
  </header>

  <!-- Page Content -->
  <div class="flex-grow p-4 md:p-6 overflow-auto">
    <!-- Dashboard Content -->
    <div class="space-y-6">
      <!-- Summary Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 card border-l-4 border-green-500">
          <div class="flex justify-between items-center">
            <div>
              <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Fees Collected</p>
              <h3 class="text-2xl font-bold text-gray-800 dark:text-white mt-1" id="totalCollected">KSh 0</h3>
              <p class="text-sm text-green-600 dark:text-green-400 mt-2">
                <i class="fas fa-arrow-up mr-1"></i> All time total
              </p>
            </div>
            <div class="bg-green-100 dark:bg-green-900/30 p-3 rounded-full">
              <i class="fas fa-money-bill-wave text-green-600 dark:text-green-400 text-xl"></i>
            </div>
          </div>
        </div>
        
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 card border-l-4 border-yellow-500">
          <div class="flex justify-between items-center">
            <div>
              <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Pending Balances</p>
              <h3 class="text-2xl font-bold text-gray-800 dark:text-white mt-1" id="pendingBalance">KSh 0</h3>
              <p class="text-sm text-yellow-600 dark:text-yellow-400 mt-2">
                <i class="fas fa-clock mr-1"></i> Outstanding fees
              </p>
            </div>
            <div class="bg-yellow-100 dark:bg-yellow-900/30 p-3 rounded-full">
              <i class="fas fa-hourglass-half text-yellow-600 dark:text-yellow-400 text-xl"></i>
            </div>
          </div>
        </div>
        
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 card border-l-4 border-red-500">
          <div class="flex justify-between items-center">
            <div>
              <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Overdue Accounts</p>
              <h3 class="text-2xl font-bold text-gray-800 dark:text-white mt-1" id="overdueCount">0</h3>
              <p class="text-sm text-red-600 dark:text-red-400 mt-2">
                <i class="fas fa-exclamation-triangle mr-1"></i> Past due dates
              </p>
            </div>
            <div class="bg-red-100 dark:bg-red-900/30 p-3 rounded-full">
              <i class="fas fa-calendar-times text-red-600 dark:text-red-400 text-xl"></i>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Charts Section -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
          <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white">Fee Collection Trends</h3>
            <div>
              <select id="collection-year-select" class="text-sm border border-gray-300 dark:border-gray-600 rounded px-3 py-1 bg-white dark:bg-gray-700 text-gray-800 dark:text-white">
                <option value="2025">2025</option>
                <option value="2024">2024</option>
              </select>
            </div>
          </div>
          <div class="h-72">
            <canvas id="feeCollectionChart"></canvas>
          </div>
        </div>
        
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
          <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white">Payment Methods</h3>
          </div>
          <div class="h-72">
            <canvas id="paymentMethodChart"></canvas>
          </div>
        </div>
      </div>
      
      <!-- Recent Activities -->
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-lg font-semibold text-gray-800 dark:text-white">Recent Activities</h3>
          <a href="pages/records/payments.php" class="text-indigo-600 dark:text-indigo-400 text-sm hover:underline">View All</a>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full">
            <thead class="bg-gray-50 dark:bg-gray-700">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Student</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Activity</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Amount</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
              </tr>
            </thead>
            <tbody id="recent-activities-tbody" class="divide-y divide-gray-200 dark:divide-gray-600">
              <tr>
                <td colspan="4" class="px-6 py-4 text-center text-gray-500">
                  <div class="flex justify-center items-center">
                    <i class="fas fa-spinner fa-spin mr-2"></i> Loading activities...
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Pass PHP variables to JavaScript
window.schoolId = <?php echo $school_id_js; ?>;
window.teacherId = <?php echo $teacher_id_js; ?>;

// Dashboard specific functions
let feeChart = null;
let paymentChart = null;

async function loadDashboardData() {
  try {
    const response = await fetch('/feesystem/api/feesystem/get_dashboard_data.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ school_id: schoolId })
    });
    const data = await response.json();
    
    if (data.success) {
      // Update summary cards
      const totalCollectedEl = document.getElementById('totalCollected');
      const pendingBalanceEl = document.getElementById('pendingBalance');
      const overdueCountEl = document.getElementById('overdueCount');
      
      if (totalCollectedEl) totalCollectedEl.textContent = `KSh ${data.total_collected.toLocaleString()}`;
      if (pendingBalanceEl) pendingBalanceEl.textContent = `KSh ${data.pending_balance.toLocaleString()}`;
      if (overdueCountEl) overdueCountEl.textContent = data.overdue_count;
      
      updateRecentActivities(data.recent_activities);
      initCharts(data);
    } else {
      console.error('Error loading dashboard data:', data.message);
      showToast('Error loading dashboard data', 'error');
    }
  } catch (error) {
    console.error('Error loading dashboard data:', error);
    showToast('Failed to load dashboard data', 'error');
  }
}

function updateRecentActivities(activities) {
  const tbody = document.getElementById('recent-activities-tbody');
  if (!tbody) return;
  
  if (!activities || activities.length === 0) {
    tbody.innerHTML = '<tr><td colspan="4" class="px-6 py-4 text-center text-gray-500">No recent activities</td></tr>';
    return;
  }
  
  tbody.innerHTML = activities.map(activity => `
    <tr>
      <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-800 dark:text-white">${escapeHtml(activity.student_name || 'N/A')}</td>
      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">${escapeHtml(activity.activity)}</td>
      <td class="px-6 py-4 whitespace-nowrap text-sm ${parseFloat(activity.amount) > 0 ? 'text-green-600 dark:text-green-400' : 'text-blue-600 dark:text-blue-400'}">
        ${parseFloat(activity.amount) > 0 ? '+' : ''} KSh ${Math.abs(parseFloat(activity.amount)).toLocaleString()}
      </td>
      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">${escapeHtml(activity.date || activity.payment_date || 'N/A')}</td>
    </tr>
  `).join('');
}

function initCharts(data) {
  // Fee Collection Trends Chart
  const feeCtx = document.getElementById('feeCollectionChart');
  if (feeCtx) {
    // Destroy existing chart if it exists
    if (feeChart) feeChart.destroy();
    
    feeChart = new Chart(feeCtx, {
      type: 'bar',
      data: {
        labels: ['Term 1', 'Term 2', 'Term 3'],
        datasets: [{
          label: 'Expected (KES)',
          data: data.collection_trends?.expected || [0, 0, 0],
          backgroundColor: 'rgba(99, 102, 241, 0.3)',
          borderColor: 'rgba(99, 102, 241, 1)',
          borderWidth: 1
        }, {
          label: 'Collected (KES)',
          data: data.collection_trends?.collected || [0, 0, 0],
          backgroundColor: 'rgba(16, 185, 129, 0.3)',
          borderColor: 'rgba(16, 185, 129, 1)',
          borderWidth: 1
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              callback: function(value) {
                return 'KSh ' + (value / 1000).toFixed(0) + 'K';
              }
            }
          }
        },
        plugins: {
          tooltip: {
            callbacks: {
              label: function(context) {
                return context.dataset.label + ': KSh ' + context.raw.toLocaleString();
              }
            }
          }
        }
      }
    });
  }
  
  // Payment Methods Chart
  const methodsCtx = document.getElementById('paymentMethodChart');
  if (methodsCtx && data.payment_methods && Object.keys(data.payment_methods).length > 0) {
    // Destroy existing chart if it exists
    if (paymentChart) paymentChart.destroy();
    
    const labels = Object.keys(data.payment_methods).map(method => 
      method.charAt(0).toUpperCase() + method.slice(1)
    );
    const values = Object.values(data.payment_methods);
    
    paymentChart = new Chart(methodsCtx, {
      type: 'doughnut',
      data: {
        labels: labels,
        datasets: [{
          data: values,
          backgroundColor: [
            'rgba(99, 102, 241, 0.8)',
            'rgba(16, 185, 129, 0.8)',
            'rgba(245, 158, 11, 0.8)',
            'rgba(6, 182, 212, 0.8)',
            'rgba(236, 72, 153, 0.8)'
          ],
          borderWidth: 1
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'bottom'
          },
          tooltip: {
            callbacks: {
              label: function(context) {
                const label = context.label || '';
                const value = context.raw || 0;
                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                const percentage = ((value / total) * 100).toFixed(1);
                return `${label}: KSh ${value.toLocaleString()} (${percentage}%)`;
              }
            }
          }
        }
      }
    });
  } else if (methodsCtx) {
    // Show a message when no data is available
    methodsCtx.parentElement.innerHTML = `
      <div class="flex items-center justify-center h-full">
        <div class="text-center text-gray-500">
          <i class="fas fa-chart-pie text-4xl mb-2 opacity-50"></i>
          <p>No payment data available</p>
        </div>
      </div>
    `;
  }
}

// Year selector change handler
document.getElementById('collection-year-select')?.addEventListener('change', function() {
  const year = this.value;
  // You can extend this to load data for different years
  showToast(`Loading data for ${year}...`, 'info');
  loadDashboardData();
});

function showToast(message, type = 'info') {
  // Create a temporary toast container if it doesn't exist
  let container = document.querySelector('.toast-container');
  if (!container) {
    container = document.createElement('div');
    container.className = 'fixed top-20 right-4 z-50 space-y-2';
    document.body.appendChild(container);
  }
  
  const toast = document.createElement('div');
  toast.className = `bg-white dark:bg-gray-800 rounded-lg shadow-lg p-4 min-w-[300px] transform transition-all duration-300 translate-x-full opacity-0`;
  toast.innerHTML = `
    <div class="flex items-center gap-3">
      <div class="flex-shrink-0">
        <i class="fas ${type === 'success' ? 'fa-check-circle text-green-500' : type === 'error' ? 'fa-exclamation-circle text-red-500' : 'fa-info-circle text-blue-500'}"></i>
      </div>
      <div class="flex-1">
        <p class="text-sm text-gray-700 dark:text-gray-300">${escapeHtml(message)}</p>
      </div>
    </div>
  `;
  
  container.appendChild(toast);
  
  // Animate in
  setTimeout(() => {
    toast.classList.remove('translate-x-full', 'opacity-0');
    toast.classList.add('translate-x-0', 'opacity-100');
  }, 10);
  
  // Animate out and remove
  setTimeout(() => {
    toast.classList.remove('translate-x-0', 'opacity-100');
    toast.classList.add('translate-x-full', 'opacity-0');
    setTimeout(() => toast.remove(), 300);
  }, 5000);
}

function escapeHtml(text) {
  if (!text) return '';
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

// Load dashboard data on page load
document.addEventListener('DOMContentLoaded', function() {
  loadDashboardData();
});
</script>

<?php include_once('includes/footer.php'); ?>