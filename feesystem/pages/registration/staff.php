<?php
session_start();
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('Location: ../../login.php');
    exit;
}

require_once('../../includes/config.php');

$school_id = $_SESSION['school_id'];
$school_name = $_SESSION['school_name'] ?? '';

include_once('../../includes/header.php');
include_once('../../includes/sidebar.php');
?>

<style>
.btn-warning {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
}

.btn-warning:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

/* Responsive Styles - Only adds responsiveness without affecting header */
@media (max-width: 768px) {
    .form-grid-responsive {
        grid-template-columns: 1fr !important;
    }
    
    .table-header-responsive {
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 1rem !important;
    }
    
    .filter-group-responsive {
        flex-direction: column !important;
        width: 100% !important;
    }
    
    .filter-group-responsive > * {
        width: 100% !important;
    }
    
    .search-box-responsive input {
        width: 100% !important;
    }
    
    .filter-select-responsive {
        width: 100% !important;
    }
    
    .action-buttons-responsive {
        flex-wrap: wrap !important;
        justify-content: center !important;
    }
    
    .pagination-container-responsive {
        flex-direction: column !important;
        text-align: center !important;
        gap: 1rem !important;
    }
    
    .pagination-buttons-responsive {
        justify-content: center !important;
    }
    
    .form-actions-responsive {
        flex-direction: column !important;
    }
    
    .form-actions-responsive button {
        width: 100% !important;
    }
    
    .modal-content-responsive {
        width: 95% !important;
        margin: 1rem !important;
    }
    
    .staff-table th, .staff-table td {
        padding: 0.5rem !important;
        font-size: 0.75rem !important;
    }
}

@media (max-width: 640px) {
    .staff-table th, .staff-table td {
        padding: 0.4rem !important;
        font-size: 0.7rem !important;
    }
    
    .btn-sm-responsive {
        padding: 0.3rem 0.6rem !important;
        font-size: 0.7rem !important;
    }
}

@media (max-width: 480px) {
    .staff-table th, .staff-table td {
        padding: 0.3rem !important;
        font-size: 0.65rem !important;
    }
    
    .hide-on-mobile {
        display: none !important;
    }
}

/* Touch-friendly adjustments */
@media (hover: none) and (pointer: coarse) {
    button, .action-btn, .pagination-btn {
        min-height: 44px !important;
    }
    
    .action-btn {
        min-width: 44px !important;
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
        <h1 id="page-title" class="text-xl font-semibold text-gray-800 dark:text-white">Staff Management</h1>
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
            <a href="../profile.php" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
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
    <!-- Add Staff Form -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4 md:p-6 mb-6">
      <h2 class="text-lg md:text-xl font-semibold text-gray-800 dark:text-white mb-4">Add New Staff Member</h2>
      
      <form id="staffForm" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 form-grid-responsive">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Department *</label>
            <div class="flex space-x-2">
              <select id="department" name="department" required 
                      class="flex-1 px-3 md:px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white dark:bg-gray-700 text-gray-800 dark:text-white text-sm md:text-base">
                <option value="">Select Department</option>
              </select>
              <button type="button" id="addDepartmentBtn" class="px-3 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 focus:outline-none" title="Add New Department">
                <i class="fas fa-plus"></i>
              </button>
            </div>
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Staff Number *</label>
            <input type="text" id="staffNumber" name="staffNumber" required 
                   class="w-full px-3 md:px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white dark:bg-gray-700 text-gray-800 dark:text-white text-sm md:text-base"
                   placeholder="e.g., STF-2024-001">
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Title *</label>
            <select id="title" name="title" required 
                    class="w-full px-3 md:px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white dark:bg-gray-700 text-gray-800 dark:text-white text-sm md:text-base">
              <option value="">Select Title</option>
              <option value="Mr.">Mr.</option>
              <option value="Mrs.">Mrs.</option>
              <option value="Ms.">Ms.</option>
              <option value="Dr.">Dr.</option>
              <option value="Prof.">Prof.</option>
              <option value="Rev.">Rev.</option>
              <option value="Eng.">Eng.</option>
              <option value="Hon.">Hon.</option>
            </select>
          </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 form-grid-responsive">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">First Name *</label>
            <input type="text" id="firstName" name="firstName" required 
                   class="w-full px-3 md:px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white dark:bg-gray-700 text-gray-800 dark:text-white text-sm md:text-base"
                   placeholder="First Name">
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Middle Name</label>
            <input type="text" id="middleName" name="middleName" 
                   class="w-full px-3 md:px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white dark:bg-gray-700 text-gray-800 dark:text-white text-sm md:text-base"
                   placeholder="Middle Name">
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Last Name *</label>
            <input type="text" id="lastName" name="lastName" required 
                   class="w-full px-3 md:px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white dark:bg-gray-700 text-gray-800 dark:text-white text-sm md:text-base"
                   placeholder="Last Name">
          </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 form-grid-responsive">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Gender *</label>
            <select id="gender" name="gender" required 
                    class="w-full px-3 md:px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white dark:bg-gray-700 text-gray-800 dark:text-white text-sm md:text-base">
              <option value="">Select Gender</option>
              <option value="Male">Male</option>
              <option value="Female">Female</option>
              <option value="Other">Other</option>
            </select>
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Phone Number *</label>
            <input type="tel" id="phoneNumber" name="phoneNumber" required 
                   class="w-full px-3 md:px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white dark:bg-gray-700 text-gray-800 dark:text-white text-sm md:text-base"
                   placeholder="e.g., 0712345678">
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">ID Number *</label>
            <input type="text" id="idNumber" name="idNumber" required 
                   class="w-full px-3 md:px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white dark:bg-gray-700 text-gray-800 dark:text-white text-sm md:text-base"
                   placeholder="National ID Number">
          </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 form-grid-responsive">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Bank *</label>
            <div class="flex space-x-2">
              <select id="bank" name="bank" required 
                      class="flex-1 px-3 md:px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white dark:bg-gray-700 text-gray-800 dark:text-white text-sm md:text-base">
                <option value="">Select Bank</option>
                <option value="M-Pesa">M-Pesa</option>
                <option value="KCB Bank">KCB Bank</option>
                <option value="Equity Bank">Equity Bank</option>
                <option value="Cooperative Bank">Cooperative Bank</option>
                <option value="Absa Bank">Absa Bank</option>
                <option value="Stanbic Bank">Stanbic Bank</option>
                <option value="Standard Chartered">Standard Chartered</option>
                <option value="NCBA Bank">NCBA Bank</option>
                <option value="DTB Bank">DTB Bank</option>
                <option value="I&M Bank">I&M Bank</option>
                <option value="Family Bank">Family Bank</option>
                <option value="Diamond Trust Bank">Diamond Trust Bank</option>
                <option value="National Bank">National Bank</option>
                <option value="Postbank">Postbank</option>
                <option value="Other">Other</option>
              </select>
              <button type="button" id="addBankBtn" class="px-3 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 focus:outline-none" title="Add New Bank">
                <i class="fas fa-plus"></i>
              </button>
            </div>
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Account Number *</label>
            <input type="text" id="accountNumber" name="accountNumber" required 
                   class="w-full px-3 md:px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white dark:bg-gray-700 text-gray-800 dark:text-white text-sm md:text-base"
                   placeholder="Bank Account Number or M-Pesa Number">
          </div>
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Signature Upload</label>
          <div class="flex flex-wrap items-center gap-3">
            <input type="file" id="signature" name="signature" accept="image/*" 
                   class="flex-1 px-3 md:px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white dark:bg-gray-700 text-gray-800 dark:text-white text-sm md:text-base file:mr-2 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
            <div id="signaturePreview" class="hidden w-12 h-12 md:w-16 md:h-16 border rounded-lg overflow-hidden bg-gray-100 dark:bg-gray-700">
              <img id="signatureImage" class="w-full h-full object-contain" alt="Signature Preview">
            </div>
            <button type="button" id="clearSignature" class="hidden px-3 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 focus:outline-none">
              <i class="fas fa-times"></i>
            </button>
          </div>
          <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Accepted formats: JPG, PNG, GIF (Max 2MB)</p>
        </div>
        
        <div class="flex flex-col sm:flex-row justify-end space-y-2 sm:space-y-0 sm:space-x-3 form-actions-responsive">
          <button type="reset" class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none">
            <i class="fas fa-undo mr-2"></i> Reset
          </button>
          <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 glow-button">
            <i class="fas fa-save mr-2"></i> Save Staff
          </button>
        </div>
      </form>
    </div>
    
    <!-- Staff List Table -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4 md:p-6">
      <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-4 gap-3 table-header-responsive">
        <h2 class="text-lg md:text-xl font-semibold text-gray-800 dark:text-white">Staff List</h2>
        <div class="flex flex-col sm:flex-row gap-2 filter-group-responsive">
          <div class="relative">
            <input type="text" id="searchStaff" placeholder="Search staff..." 
                   class="pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 w-full sm:w-64 search-box-responsive">
            <span class="absolute left-3 top-2.5 text-gray-400">
              <i class="fas fa-search"></i>
            </span>
          </div>
          <select id="filterDepartment" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 filter-select-responsive">
            <option value="">All Departments</option>
          </select>
          <button id="exportStaffBtn" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 glow-button btn-sm-responsive">
            <i class="fas fa-file-excel mr-2"></i> Export
          </button>
          <button id="toggleSensitiveDataBtn" class="px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-offset-2 btn-sm-responsive">
            <i class="fas fa-eye"></i> Show Sensitive Data
          </button>
        </div>
      </div>
      
      <div class="overflow-x-auto">
        <table class="min-w-full bg-white dark:bg-gray-800 staff-table">
          <thead class="bg-gray-50 dark:bg-gray-700">
            <tr>
              <th class="px-3 md:px-4 py-2 md:py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Staff No.</th>
              <th class="px-3 md:px-4 py-2 md:py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Title</th>
              <th class="px-3 md:px-4 py-2 md:py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Full Name</th>
              <th class="px-3 md:px-4 py-2 md:py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider hide-on-mobile">Gender</th>
              <th class="px-3 md:px-4 py-2 md:py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider hide-on-mobile">ID No.</th>
              <th class="px-3 md:px-4 py-2 md:py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider hide-on-mobile">Bank</th>
              <th class="px-3 md:px-4 py-2 md:py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">A/C No.</th>
              <th class="px-3 md:px-4 py-2 md:py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider hide-on-mobile">Department</th>
              <th class="px-3 md:px-4 py-2 md:py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Phone</th>
              <th class="px-3 md:px-4 py-2 md:py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody id="staffTbody" class="divide-y divide-gray-200 dark:divide-gray-700">
            <tr>
              <td colspan="10" class="px-6 py-4 text-center text-gray-500">Loading staff...</td>
            </tr>
          </tbody>
        </table>
      </div>
      
      <!-- Pagination -->
      <div class="mt-4 flex flex-col md:flex-row justify-between items-center gap-3 pagination-container-responsive">
        <div class="text-sm text-gray-500 dark:text-gray-400">
          Showing <span id="staffStart">0</span> to <span id="staffEnd">0</span> of <span id="staffTotal">0</span> staff members
        </div>
        <div class="flex space-x-2 pagination-buttons-responsive" id="staffPagination"></div>
      </div>
    </div>
  </div>
</main>

<!-- Modals remain the same as before -->
<!-- Add Department Modal -->
<div id="departmentModal" class="fixed inset-0 bg-black bg-opacity-50 z-30 hidden flex items-center justify-center">
  <div class="bg-white dark:bg-gray-800 w-full max-w-md rounded-lg shadow-xl mx-4 modal-content-responsive">
    <div class="border-b border-gray-200 dark:border-gray-700 px-4 md:px-6 py-4 flex justify-between items-center">
      <h3 class="text-base md:text-lg font-semibold text-gray-800 dark:text-white">Add New Department</h3>
      <button id="closeDepartmentModal" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 focus:outline-none">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <div class="px-4 md:px-6 py-4">
      <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Department Name *</label>
        <input type="text" id="newDepartmentName" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white dark:bg-gray-700 text-gray-800 dark:text-white" placeholder="e.g., ICT Department">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Description</label>
        <textarea id="newDepartmentDesc" rows="2" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white dark:bg-gray-700 text-gray-800 dark:text-white" placeholder="Department description (optional)"></textarea>
      </div>
    </div>
    <div class="border-t border-gray-200 dark:border-gray-700 px-4 md:px-6 py-4 flex justify-end space-x-3">
      <button id="cancelDepartmentBtn" class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none">
        Cancel
      </button>
      <button id="saveDepartmentBtn" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
        <i class="fas fa-save mr-2"></i> Save Department
      </button>
    </div>
  </div>
</div>

<!-- Add Bank Modal -->
<div id="bankModal" class="fixed inset-0 bg-black bg-opacity-50 z-30 hidden flex items-center justify-center">
  <div class="bg-white dark:bg-gray-800 w-full max-w-md rounded-lg shadow-xl mx-4 modal-content-responsive">
    <div class="border-b border-gray-200 dark:border-gray-700 px-4 md:px-6 py-4 flex justify-between items-center">
      <h3 class="text-base md:text-lg font-semibold text-gray-800 dark:text-white">Add New Bank</h3>
      <button id="closeBankModal" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 focus:outline-none">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <div class="px-4 md:px-6 py-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Bank Name *</label>
        <input type="text" id="newBankName" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white dark:bg-gray-700 text-gray-800 dark:text-white" placeholder="e.g., ABC Bank">
      </div>
    </div>
    <div class="border-t border-gray-200 dark:border-gray-700 px-4 md:px-6 py-4 flex justify-end space-x-3">
      <button id="cancelBankBtn" class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none">
        Cancel
      </button>
      <button id="saveBankBtn" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
        <i class="fas fa-save mr-2"></i> Save Bank
      </button>
    </div>
  </div>
</div>

<!-- Edit Staff Modal -->
<div id="editStaffModal" class="fixed inset-0 bg-black bg-opacity-50 z-30 hidden flex items-center justify-center overflow-y-auto">
  <div class="bg-white dark:bg-gray-800 w-full max-w-4xl rounded-lg shadow-xl mx-4 my-8 modal-content-responsive">
    <div class="border-b border-gray-200 dark:border-gray-700 px-4 md:px-6 py-4 flex justify-between items-center">
      <h3 class="text-base md:text-lg font-semibold text-gray-800 dark:text-white">Edit Staff Member</h3>
      <button id="closeEditModal" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 focus:outline-none">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <div class="px-4 md:px-6 py-4 max-h-96 overflow-y-auto">
      <form id="editStaffForm" class="space-y-4">
        <input type="hidden" id="editStaffId">
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 form-grid-responsive">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Department *</label>
            <select id="editDepartment" required class="w-full px-3 md:px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white dark:bg-gray-700 text-gray-800 dark:text-white text-sm md:text-base">
              <option value="">Select Department</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Staff Number *</label>
            <input type="text" id="editStaffNumber" required class="w-full px-3 md:px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white dark:bg-gray-700 text-gray-800 dark:text-white text-sm md:text-base">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Title *</label>
            <select id="editTitle" required class="w-full px-3 md:px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white dark:bg-gray-700 text-gray-800 dark:text-white text-sm md:text-base">
              <option value="">Select Title</option>
              <option value="Mr.">Mr.</option>
              <option value="Mrs.">Mrs.</option>
              <option value="Ms.">Ms.</option>
              <option value="Dr.">Dr.</option>
              <option value="Prof.">Prof.</option>
              <option value="Rev.">Rev.</option>
              <option value="Eng.">Eng.</option>
              <option value="Hon.">Hon.</option>
            </select>
          </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 form-grid-responsive">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">First Name *</label>
            <input type="text" id="editFirstName" required class="w-full px-3 md:px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white dark:bg-gray-700 text-gray-800 dark:text-white text-sm md:text-base">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Middle Name</label>
            <input type="text" id="editMiddleName" class="w-full px-3 md:px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white dark:bg-gray-700 text-gray-800 dark:text-white text-sm md:text-base">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Last Name *</label>
            <input type="text" id="editLastName" required class="w-full px-3 md:px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white dark:bg-gray-700 text-gray-800 dark:text-white text-sm md:text-base">
          </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 form-grid-responsive">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Gender *</label>
            <select id="editGender" required class="w-full px-3 md:px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white dark:bg-gray-700 text-gray-800 dark:text-white text-sm md:text-base">
              <option value="">Select Gender</option>
              <option value="Male">Male</option>
              <option value="Female">Female</option>
              <option value="Other">Other</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Phone Number *</label>
            <input type="tel" id="editPhoneNumber" required class="w-full px-3 md:px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white dark:bg-gray-700 text-gray-800 dark:text-white text-sm md:text-base">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">ID Number *</label>
            <input type="text" id="editIdNumber" required class="w-full px-3 md:px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white dark:bg-gray-700 text-gray-800 dark:text-white text-sm md:text-base">
          </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 form-grid-responsive">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Bank *</label>
            <select id="editBank" required class="w-full px-3 md:px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white dark:bg-gray-700 text-gray-800 dark:text-white text-sm md:text-base">
              <option value="">Select Bank</option>
              <option value="M-Pesa">M-Pesa</option>
              <option value="KCB Bank">KCB Bank</option>
              <option value="Equity Bank">Equity Bank</option>
              <option value="Cooperative Bank">Cooperative Bank</option>
              <option value="Absa Bank">Absa Bank</option>
              <option value="Stanbic Bank">Stanbic Bank</option>
              <option value="Standard Chartered">Standard Chartered</option>
              <option value="NCBA Bank">NCBA Bank</option>
              <option value="DTB Bank">DTB Bank</option>
              <option value="I&M Bank">I&M Bank</option>
              <option value="Family Bank">Family Bank</option>
              <option value="Diamond Trust Bank">Diamond Trust Bank</option>
              <option value="National Bank">National Bank</option>
              <option value="Postbank">Postbank</option>
              <option value="Other">Other</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Account Number *</label>
            <input type="text" id="editAccountNumber" required class="w-full px-3 md:px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white dark:bg-gray-700 text-gray-800 dark:text-white text-sm md:text-base">
          </div>
        </div>
      </form>
    </div>
    <div class="border-t border-gray-200 dark:border-gray-700 px-4 md:px-6 py-4 flex justify-end space-x-3">
      <button id="cancelEditBtn" class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none">
        Cancel
      </button>
      <button id="saveEditBtn" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
        <i class="fas fa-save mr-2"></i> Update Staff
      </button>
    </div>
  </div>
</div>

<script>
const schoolId = <?php echo json_encode($school_id); ?>;
let currentPage = 1;
let itemsPerPage = 10;
let totalStaff = 0;
let staffData = [];
let showSensitiveData = false;

// Signature upload handling
document.getElementById('signature').addEventListener('change', function(e) {
  const file = e.target.files[0];
  if (file) {
    const reader = new FileReader();
    reader.onload = function(event) {
      const preview = document.getElementById('signaturePreview');
      const img = document.getElementById('signatureImage');
      const clearBtn = document.getElementById('clearSignature');
      img.src = event.target.result;
      preview.classList.remove('hidden');
      clearBtn.classList.remove('hidden');
    };
    reader.readAsDataURL(file);
  }
});

document.getElementById('clearSignature').addEventListener('click', function() {
  document.getElementById('signature').value = '';
  document.getElementById('signaturePreview').classList.add('hidden');
  document.getElementById('clearSignature').classList.add('hidden');
  document.getElementById('signatureImage').src = '';
});

// Toggle sensitive data
function toggleSensitiveData() {
    showSensitiveData = !showSensitiveData;
    const toggleBtn = document.getElementById('toggleSensitiveDataBtn');
    if (showSensitiveData) {
        toggleBtn.innerHTML = '<i class="fas fa-eye-slash"></i> Hide Sensitive Data';
        toggleBtn.classList.remove('btn-outline');
        toggleBtn.classList.add('btn-warning');
    } else {
        toggleBtn.innerHTML = '<i class="fas fa-eye"></i> Show Sensitive Data';
        toggleBtn.classList.remove('btn-warning');
        toggleBtn.classList.add('btn-outline');
    }
    loadStaffWithMasking();
}

async function loadStaffWithMasking() {
    try {
        const search = document.getElementById('searchStaff')?.value || '';
        const departmentFilter = document.getElementById('filterDepartment')?.value || '';
        
        const response = await fetch('../../api/feesystem/get_staff.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                school_id: schoolId,
                search: search,
                department_id: departmentFilter,
                show_unmasked: showSensitiveData
            })
        });
        const data = await response.json();
        
        if (data.success) {
            staffData = data.staff || [];
            totalStaff = staffData.length;
            renderStaffTable();
            updatePagination();
        } else {
            document.getElementById('staffTbody').innerHTML = `
                <tr><td colspan="10" class="px-6 py-4 text-center text-red-500">${escapeHtml(data.message)}</td></tr>
            `;
        }
    } catch (error) {
        console.error('Error loading staff:', error);
        document.getElementById('staffTbody').innerHTML = `
            <tr><td colspan="10" class="px-6 py-4 text-center text-red-500">Error loading staff</td></tr>
        `;
    }
}

function loadStaff() {
    loadStaffWithMasking();
}

document.getElementById('toggleSensitiveDataBtn')?.addEventListener('click', toggleSensitiveData);

// Load departments
async function loadDepartments() {
  try {
    const response = await fetch('../../api/feesystem/get_departments.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ school_id: schoolId })
    });
    const data = await response.json();
    
    if (data.success && data.departments) {
      const select = document.getElementById('department');
      const filterSelect = document.getElementById('filterDepartment');
      const editSelect = document.getElementById('editDepartment');
      
      const options = '<option value="">Select Department</option>' + 
        data.departments.map(dept => `<option value="${dept.id}">${escapeHtml(dept.name)}</option>`).join('');
      
      select.innerHTML = options;
      filterSelect.innerHTML = '<option value="">All Departments</option>' + 
        data.departments.map(dept => `<option value="${dept.id}">${escapeHtml(dept.name)}</option>`).join('');
      editSelect.innerHTML = options;
    }
  } catch (error) {
    console.error('Error loading departments:', error);
  }
}

// Load banks
async function loadBanks() {
  try {
    const response = await fetch('../../api/feesystem/get_banks.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ school_id: schoolId })
    });
    const data = await response.json();
    
    if (data.success && data.banks) {
      const select = document.getElementById('bank');
      const editSelect = document.getElementById('editBank');
      
      const options = '<option value="">Select Bank</option>' + 
        data.banks.map(bank => `<option value="${escapeHtml(bank.name)}">${escapeHtml(bank.name)}</option>`).join('');
      
      select.innerHTML = options;
      editSelect.innerHTML = options;
    }
  } catch (error) {
    console.error('Error loading banks:', error);
  }
}

function renderStaffTable() {
    const tbody = document.getElementById('staffTbody');
    const start = (currentPage - 1) * itemsPerPage;
    const end = start + itemsPerPage;
    const pageData = staffData.slice(start, end);
    
    if (pageData.length === 0) {
        tbody.innerHTML = '<tr><td colspan="10" class="px-6 py-4 text-center text-gray-500">No staff found</td></tr>';
        return;
    }
    
    tbody.innerHTML = pageData.map(staff => `
        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
            <td class="px-3 md:px-4 py-2 md:py-3 whitespace-nowrap text-xs md:text-sm font-medium text-gray-800 dark:text-white">${escapeHtml(staff.staff_number)}</td>
            <td class="px-3 md:px-4 py-2 md:py-3 whitespace-nowrap text-xs md:text-sm text-gray-600 dark:text-gray-300">${escapeHtml(staff.title)}</td>
            <td class="px-3 md:px-4 py-2 md:py-3 whitespace-nowrap text-xs md:text-sm text-gray-600 dark:text-gray-300">${escapeHtml(staff.full_name)}</td>
            <td class="px-3 md:px-4 py-2 md:py-3 whitespace-nowrap text-xs md:text-sm text-gray-600 dark:text-gray-300 hide-on-mobile">${escapeHtml(staff.gender)}</td>
            <td class="px-3 md:px-4 py-2 md:py-3 whitespace-nowrap text-xs md:text-sm text-gray-600 dark:text-gray-300 hide-on-mobile" title="${staff.is_masked ? 'Masked - Click Show Data to view full ID' : ''}">
                ${escapeHtml(staff.id_number)}
            </td>
            <td class="px-3 md:px-4 py-2 md:py-3 whitespace-nowrap text-xs md:text-sm text-gray-600 dark:text-gray-300 hide-on-mobile">${escapeHtml(staff.bank)}</td>
            <td class="px-3 md:px-4 py-2 md:py-3 whitespace-nowrap text-xs md:text-sm text-gray-600 dark:text-gray-300" title="${staff.is_masked ? 'Masked - Click Show Data to view full account' : ''}">
                ${escapeHtml(staff.account_number)}
            </td>
            <td class="px-3 md:px-4 py-2 md:py-3 whitespace-nowrap text-xs md:text-sm text-gray-600 dark:text-gray-300 hide-on-mobile">${escapeHtml(staff.department_name)}</td>
            <td class="px-3 md:px-4 py-2 md:py-3 whitespace-nowrap text-xs md:text-sm text-gray-600 dark:text-gray-300" title="${staff.is_masked ? 'Masked - Click Show Data to view full phone' : ''}">
                ${escapeHtml(staff.phone_number)}
            </td>
            <td class="px-3 md:px-4 py-2 md:py-3 whitespace-nowrap text-sm">
                <div class="flex space-x-2 action-buttons-responsive">
                    <button onclick="editStaff(${staff.id})" class="p-1 text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300" title="Edit">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button onclick="deleteStaff(${staff.id})" class="p-1 text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300" title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
    
    document.getElementById('staffStart').textContent = staffData.length > 0 ? start + 1 : 0;
    document.getElementById('staffEnd').textContent = Math.min(end, totalStaff);
    document.getElementById('staffTotal').textContent = totalStaff;
}

function updatePagination() {
  const container = document.getElementById('staffPagination');
  const totalPages = Math.ceil(totalStaff / itemsPerPage);
  
  if (totalPages <= 1) {
    container.innerHTML = '';
    return;
  }
  
  let buttons = '';
  
  if (currentPage > 1) {
    buttons += `<button onclick="goToPage(${currentPage - 1})" class="px-2 md:px-3 py-1 border border-gray-300 dark:border-gray-600 rounded text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 text-xs md:text-sm">Previous</button>`;
  }
  
  for (let i = 1; i <= totalPages; i++) {
    if (i === currentPage) {
      buttons += `<button class="px-2 md:px-3 py-1 bg-indigo-600 text-white rounded hover:bg-indigo-700 text-xs md:text-sm">${i}</button>`;
    } else if (Math.abs(i - currentPage) <= 2 || i === 1 || i === totalPages) {
      buttons += `<button onclick="goToPage(${i})" class="px-2 md:px-3 py-1 border border-gray-300 dark:border-gray-600 rounded text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 text-xs md:text-sm">${i}</button>`;
    } else if (Math.abs(i - currentPage) === 3) {
      buttons += `<span class="px-2 md:px-3 py-1 text-gray-500">...</span>`;
    }
  }
  
  if (currentPage < totalPages) {
    buttons += `<button onclick="goToPage(${currentPage + 1})" class="px-2 md:px-3 py-1 border border-gray-300 dark:border-gray-600 rounded text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 text-xs md:text-sm">Next</button>`;
  }
  
  container.innerHTML = buttons;
}

function goToPage(page) {
  currentPage = page;
  renderStaffTable();
  updatePagination();
}

// Save staff
document.getElementById('staffForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  
  const formData = new FormData();
  formData.append('school_id', schoolId);
  formData.append('department_id', document.getElementById('department').value);
  formData.append('staff_number', document.getElementById('staffNumber').value);
  formData.append('title', document.getElementById('title').value);
  formData.append('first_name', document.getElementById('firstName').value);
  formData.append('middle_name', document.getElementById('middleName').value);
  formData.append('last_name', document.getElementById('lastName').value);
  formData.append('gender', document.getElementById('gender').value);
  formData.append('phone_number', document.getElementById('phoneNumber').value);
  formData.append('id_number', document.getElementById('idNumber').value);
  formData.append('bank', document.getElementById('bank').value);
  formData.append('account_number', document.getElementById('accountNumber').value);
  
  const signatureFile = document.getElementById('signature').files[0];
  if (signatureFile) {
    formData.append('signature', signatureFile);
  }
  
  const submitBtn = e.target.querySelector('button[type="submit"]');
  const originalText = submitBtn.innerHTML;
  submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Saving...';
  submitBtn.disabled = true;
  
  try {
    const response = await fetch('../../api/feesystem/save_staff.php', {
      method: 'POST',
      body: formData
    });
    const data = await response.json();
    
    if (data.success) {
      Swal.fire('Success', 'Staff member saved successfully', 'success');
      document.getElementById('staffForm').reset();
      document.getElementById('signaturePreview').classList.add('hidden');
      document.getElementById('clearSignature').classList.add('hidden');
      loadStaff();
    } else {
      Swal.fire('Error', data.message || 'Failed to save staff member', 'error');
    }
  } catch (error) {
    console.error('Error saving staff:', error);
    Swal.fire('Error', 'An error occurred while saving staff member', 'error');
  } finally {
    submitBtn.innerHTML = originalText;
    submitBtn.disabled = false;
  }
});

// Edit staff
window.editStaff = async (id) => {
  try {
    const response = await fetch('../../api/feesystem/get_staff.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: id, school_id: schoolId })
    });
    const data = await response.json();
    
    if (data.success && data.staff) {
      const staff = data.staff;
      document.getElementById('editStaffId').value = staff.id;
      document.getElementById('editDepartment').value = staff.department_id;
      document.getElementById('editStaffNumber').value = staff.staff_number;
      document.getElementById('editTitle').value = staff.title;
      document.getElementById('editFirstName').value = staff.first_name;
      document.getElementById('editMiddleName').value = staff.middle_name || '';
      document.getElementById('editLastName').value = staff.last_name;
      document.getElementById('editGender').value = staff.gender;
      document.getElementById('editPhoneNumber').value = staff.phone_number;
      document.getElementById('editIdNumber').value = staff.id_number;
      document.getElementById('editBank').value = staff.bank;
      document.getElementById('editAccountNumber').value = staff.account_number;
      
      document.getElementById('editStaffModal').classList.remove('hidden');
    } else {
      Swal.fire('Error', 'Failed to load staff data', 'error');
    }
  } catch (error) {
    console.error('Error loading staff:', error);
    Swal.fire('Error', 'An error occurred while loading staff data', 'error');
  }
};

// Save edit
document.getElementById('saveEditBtn').addEventListener('click', async () => {
  const formData = {
    id: parseInt(document.getElementById('editStaffId').value),
    school_id: schoolId,
    department_id: document.getElementById('editDepartment').value,
    staff_number: document.getElementById('editStaffNumber').value,
    title: document.getElementById('editTitle').value,
    first_name: document.getElementById('editFirstName').value,
    middle_name: document.getElementById('editMiddleName').value,
    last_name: document.getElementById('editLastName').value,
    gender: document.getElementById('editGender').value,
    phone_number: document.getElementById('editPhoneNumber').value,
    id_number: document.getElementById('editIdNumber').value,
    bank: document.getElementById('editBank').value,
    account_number: document.getElementById('editAccountNumber').value
  };
  
  const saveBtn = document.getElementById('saveEditBtn');
  const originalText = saveBtn.innerHTML;
  saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Updating...';
  saveBtn.disabled = true;
  
  try {
    const response = await fetch('../../api/feesystem/update_staff.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(formData)
    });
    const data = await response.json();
    
    if (data.success) {
      Swal.fire('Success', 'Staff member updated successfully', 'success');
      document.getElementById('editStaffModal').classList.add('hidden');
      loadStaff();
    } else {
      Swal.fire('Error', data.message || 'Failed to update staff member', 'error');
    }
  } catch (error) {
    console.error('Error updating staff:', error);
    Swal.fire('Error', 'An error occurred while updating staff member', 'error');
  } finally {
    saveBtn.innerHTML = originalText;
    saveBtn.disabled = false;
  }
});

// Delete staff
window.deleteStaff = async (id) => {
  const result = await Swal.fire({
    title: 'Are you sure?',
    text: 'You won\'t be able to revert this!',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Yes, delete it!'
  });
  
  if (result.isConfirmed) {
    try {
      const response = await fetch('../../api/feesystem/delete_staff.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id, school_id: schoolId })
      });
      const data = await response.json();
      
      if (data.success) {
        Swal.fire('Deleted!', 'Staff member has been deleted.', 'success');
        loadStaff();
      } else {
        Swal.fire('Error', data.message || 'Failed to delete staff member', 'error');
      }
    } catch (error) {
      console.error('Error deleting staff:', error);
      Swal.fire('Error', 'An error occurred while deleting staff member', 'error');
    }
  }
};

// Export staff to Excel
document.getElementById('exportStaffBtn').addEventListener('click', () => {
  window.location.href = `../../api/feesystem/export_staff.php?school_id=${schoolId}`;
});

// Department Modal
document.getElementById('addDepartmentBtn').addEventListener('click', () => {
  document.getElementById('departmentModal').classList.remove('hidden');
});

document.getElementById('closeDepartmentModal').addEventListener('click', () => {
  document.getElementById('departmentModal').classList.add('hidden');
});

document.getElementById('cancelDepartmentBtn').addEventListener('click', () => {
  document.getElementById('departmentModal').classList.add('hidden');
});

document.getElementById('saveDepartmentBtn').addEventListener('click', async () => {
  const name = document.getElementById('newDepartmentName').value.trim();
  const description = document.getElementById('newDepartmentDesc').value.trim();
  
  if (!name) {
    Swal.fire('Error', 'Please enter department name', 'error');
    return;
  }
  
  try {
    const response = await fetch('../../api/feesystem/save_department.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ school_id: schoolId, name: name, description: description })
    });
    const data = await response.json();
    
    if (data.success) {
      Swal.fire('Success', 'Department added successfully', 'success');
      document.getElementById('departmentModal').classList.add('hidden');
      document.getElementById('newDepartmentName').value = '';
      document.getElementById('newDepartmentDesc').value = '';
      loadDepartments();
    } else {
      Swal.fire('Error', data.message || 'Failed to add department', 'error');
    }
  } catch (error) {
    console.error('Error adding department:', error);
    Swal.fire('Error', 'An error occurred while adding department', 'error');
  }
});

// Bank Modal
document.getElementById('addBankBtn').addEventListener('click', () => {
  document.getElementById('bankModal').classList.remove('hidden');
});

document.getElementById('closeBankModal').addEventListener('click', () => {
  document.getElementById('bankModal').classList.add('hidden');
});

document.getElementById('cancelBankBtn').addEventListener('click', () => {
  document.getElementById('bankModal').classList.add('hidden');
});

document.getElementById('saveBankBtn').addEventListener('click', async () => {
  const name = document.getElementById('newBankName').value.trim();
  
  if (!name) {
    Swal.fire('Error', 'Please enter bank name', 'error');
    return;
  }
  
  try {
    const response = await fetch('../../api/feesystem/save_bank.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ school_id: schoolId, name: name })
    });
    const data = await response.json();
    
    if (data.success) {
      Swal.fire('Success', 'Bank added successfully', 'success');
      document.getElementById('bankModal').classList.add('hidden');
      document.getElementById('newBankName').value = '';
      loadBanks();
    } else {
      Swal.fire('Error', data.message || 'Failed to add bank', 'error');
    }
  } catch (error) {
    console.error('Error adding bank:', error);
    Swal.fire('Error', 'An error occurred while adding bank', 'error');
  }
});

// Close modals
document.getElementById('closeEditModal').addEventListener('click', () => {
  document.getElementById('editStaffModal').classList.add('hidden');
});
document.getElementById('cancelEditBtn').addEventListener('click', () => {
  document.getElementById('editStaffModal').classList.add('hidden');
});

// Filter events
document.getElementById('searchStaff')?.addEventListener('input', () => {
  currentPage = 1;
  loadStaff();
});
document.getElementById('filterDepartment')?.addEventListener('change', () => {
  currentPage = 1;
  loadStaff();
});

// Helper function
function escapeHtml(text) {
  if (!text) return '';
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
  loadDepartments();
  loadBanks();
  loadStaff();
});
</script>

<?php include_once('../../includes/footer.php'); ?>