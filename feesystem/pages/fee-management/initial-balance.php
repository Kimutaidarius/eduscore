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
$academic_level = $_SESSION['academic_level'] ?? 'primary';

include_once('../../includes/header.php');
include_once('../../includes/sidebar.php');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=yes">
    <title>Initial Balance Management - EduScore</title>
    <style>
        /* Responsive Table Styles */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin-bottom: 1rem;
        }
        
        /* Card-based view for mobile */
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
                background: white;
            }
            
            .data-table tbody td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 0.5rem;
                border-bottom: 1px solid #f3f4f6;
                text-align: right;
            }
            
            .data-table tbody td:last-child {
                border-bottom: none;
            }
            
            .data-table tbody td:before {
                content: attr(data-label);
                font-weight: 600;
                text-align: left;
                margin-right: 1rem;
                color: #6b7280;
                font-size: 0.75rem;
            }
            
            .data-table tfoot tr {
                display: flex;
                flex-wrap: wrap;
                justify-content: space-between;
                padding: 0.75rem;
            }
            
            .data-table tfoot td {
                flex: 1;
                text-align: center;
                padding: 0.5rem;
            }
        }
        
        /* Responsive Filter Grid */
        .filter-grid {
            display: grid;
            gap: 1rem;
        }
        
        @media (min-width: 640px) {
            .filter-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (min-width: 1024px) {
            .filter-grid {
                grid-template-columns: repeat(6, 1fr);
            }
        }
        
        /* Responsive Action Buttons */
        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            justify-content: flex-end;
        }
        
        @media (max-width: 640px) {
            .action-buttons {
                justify-content: stretch;
            }
            
            .action-buttons button {
                flex: 1;
            }
        }
        
        /* Responsive Summary Cards */
        .summary-grid {
            display: grid;
            gap: 1rem;
        }
        
        @media (min-width: 640px) {
            .summary-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (min-width: 1024px) {
            .summary-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }
        
        /* Touch-friendly input sizing */
        @media (max-width: 768px) {
            input, select, button {
                font-size: 16px !important;
            }
            
            .initial-balance-input {
                width: 100% !important;
                min-width: 100px;
            }
        }
        
        /* Improved modal for mobile */
        @media (max-width: 640px) {
            .modal-container {
                width: 95%;
                margin: 1rem;
            }
            
            .modal-container .px-6 {
                padding-left: 1rem;
                padding-right: 1rem;
            }
        }
        
        /* Loading spinner */
        .spinner-small {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #e5e7eb;
            border-top-color: #4f46e5;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Toast for mobile */
        .toast-notification {
            position: fixed;
            bottom: 20px;
            left: 20px;
            right: 20px;
            background: #10b981;
            color: white;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            z-index: 1000;
            transform: translateY(100%);
            opacity: 0;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .toast-notification.show {
            transform: translateY(0);
            opacity: 1;
        }
        
        @media (min-width: 640px) {
            .toast-notification {
                left: auto;
                right: 20px;
                bottom: 20px;
                min-width: 300px;
            }
        }
        
        /* Progress indicator */
        .progress-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            backdrop-filter: blur(4px);
        }
        
        .progress-content {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            text-align: center;
            min-width: 250px;
        }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e5e7eb;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 1rem;
        }
        
        .progress-fill {
            height: 100%;
            background: #4f46e5;
            transition: width 0.3s ease;
        }
    </style>
</head>
<body>
<main class="main-content flex-grow flex flex-col">
  <div class="flex-grow p-3 sm:p-4 md:p-6 overflow-auto">
    
    <!-- Page Header -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4 mb-6">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
        <div>
          <h1 class="text-xl sm:text-2xl font-bold text-gray-800 dark:text-white">
            <i class="fas fa-coins text-indigo-600 mr-2"></i>Initial Balance Management
          </h1>
          <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">Set up student opening balances</p>
        </div>
        <div class="bg-teal-100 dark:bg-teal-900/30 text-teal-700 dark:text-teal-300 px-2 sm:px-3 py-1 rounded-full text-xs sm:text-sm font-medium flex items-center gap-2">
          <i class="fas fa-layer-group"></i>
          <span class="hidden xs:inline"><?php 
            if ($academic_level == 'primary') echo 'Primary (Grades 1-6)';
            elseif ($academic_level == 'junior_secondary') echo 'Junior Secondary (Grades 7-9)';
            else echo 'Senior Secondary (Forms 1-4)';
          ?></span>
          <span class="xs:hidden"><?php 
            if ($academic_level == 'primary') echo 'Primary';
            elseif ($academic_level == 'junior_secondary') echo 'JSS';
            else echo 'SS';
          ?></span>
        </div>
      </div>
    </div>

    <!-- Filter Section -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4 sm:p-6 mb-6">
      <div class="filter-grid">
        <div>
          <label class="block text-xs sm:text-sm font-medium text-gray-700 dark:text-gray-300 mb-1 sm:mb-2">Class *</label>
          <select id="classFilter" class="w-full px-2 sm:px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500 bg-white dark:bg-gray-700 text-sm">
            <option value="">Select Class</option>
          </select>
        </div>
        <div>
          <label class="block text-xs sm:text-sm font-medium text-gray-700 dark:text-gray-300 mb-1 sm:mb-2">Stream</label>
          <select id="streamFilter" class="w-full px-2 sm:px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500 bg-white dark:bg-gray-700 text-sm">
            <option value="">All Streams</option>
          </select>
        </div>
        <div>
          <label class="block text-xs sm:text-sm font-medium text-gray-700 dark:text-gray-300 mb-1 sm:mb-2">Year *</label>
          <select id="yearFilter" class="w-full px-2 sm:px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500 bg-white dark:bg-gray-700 text-sm">
            <option value="2024">2024</option>
            <option value="2025" selected>2025</option>
            <option value="2026">2026</option>
            <option value="2027">2027</option>
          </select>
        </div>
        <div>
          <label class="block text-xs sm:text-sm font-medium text-gray-700 dark:text-gray-300 mb-1 sm:mb-2">Vote Head *</label>
          <select id="voteHeadFilter" class="w-full px-2 sm:px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500 bg-white dark:bg-gray-700 text-sm">
            <option value="">Select Vote Head</option>
          </select>
        </div>
        <div>
          <label class="block text-xs sm:text-sm font-medium text-gray-700 dark:text-gray-300 mb-1 sm:mb-2">Term</label>
          <select id="termFilter" class="w-full px-2 sm:px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500 bg-white dark:bg-gray-700 text-sm">
            <option value="1">Term 1</option>
            <option value="2">Term 2</option>
            <option value="3">Term 3</option>
          </select>
        </div>
        <div>
          <label class="block text-xs sm:text-sm font-medium text-gray-700 dark:text-gray-300 mb-1 sm:mb-2">Recorded At</label>
          <input type="date" id="recordedAt" class="w-full px-2 sm:px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500 bg-white dark:bg-gray-700 text-sm" value="<?php echo date('Y-m-d'); ?>">
        </div>
      </div>
      
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4 mt-4">
        <div>
          <label class="block text-xs sm:text-sm font-medium text-gray-700 dark:text-gray-300 mb-1 sm:mb-2">Bulk Amount (Optional)</label>
          <input type="number" id="bulkAmount" class="w-full px-2 sm:px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500 bg-white dark:bg-gray-700 text-sm" placeholder="Enter amount" step="0.01">
        </div>
        <div>
          <button id="applyBulkBtn" class="w-full px-3 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition text-sm sm:text-base">
            <i class="fas fa-fill-drip mr-1 sm:mr-2"></i>Apply to All
          </button>
        </div>
        <div class="action-buttons">
          <button id="loadStudentsBtn" class="px-3 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition text-sm sm:text-base">
            <i class="fas fa-search mr-1 sm:mr-2"></i>Load
          </button>
          <button id="saveBalancesBtn" class="px-3 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition text-sm sm:text-base">
            <i class="fas fa-save mr-1 sm:mr-2"></i>Save
          </button>
          <button id="importBtn" class="px-3 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition text-sm sm:text-base">
            <i class="fas fa-file-import mr-1 sm:mr-2"></i>Import
          </button>
        </div>
      </div>
    </div>

    <!-- Students Initial Balance Table -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
      <div class="table-responsive">
        <table class="data-table min-w-full divide-y divide-gray-200 dark:divide-gray-700">
          <thead class="bg-gray-50 dark:bg-gray-700">
            <tr>
              <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                <input type="checkbox" id="selectAllCheckbox" class="rounded border-gray-300 w-4 h-4">
              </th>
              <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Admission No</th>
              <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Student Name</th>
              <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Gender</th>
              <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Initial Balance</th>
              <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Previous Balance</th>
              <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Total</th>
            </tr>
          </thead>
          <tbody id="studentsTableBody" class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
            <tr>
              <td colspan="7" class="px-3 sm:px-6 py-8 text-center text-gray-500 text-sm">
                <i class="fas fa-info-circle text-2xl mb-2 block"></i>
                Select class, vote head and click "Load"
              </td>
            </tr>
          </tbody>
          <tfoot class="bg-gray-50 dark:bg-gray-700">
            <tr>
              <td colspan="4" class="px-3 sm:px-6 py-3 text-right font-bold text-gray-700 dark:text-gray-300 text-sm">TOTAL:</td>
              <td class="px-3 sm:px-6 py-3 font-bold text-indigo-600 dark:text-indigo-400 text-sm" id="initialBalanceTotal">KES 0</td>
              <td class="px-3 sm:px-6 py-3 font-bold text-orange-600 dark:text-orange-400 text-sm" id="previousBalanceTotal">KES 0</td>
              <td class="px-3 sm:px-6 py-3 font-bold text-green-600 dark:text-green-400 text-sm" id="grandTotal">KES 0</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

    <!-- Summary Cards -->
    <div class="summary-grid mt-6">
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-3 sm:p-4">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400">Total Students</p>
            <p class="text-xl sm:text-2xl font-bold text-gray-800 dark:text-white" id="totalStudents">0</p>
          </div>
          <i class="fas fa-users text-2xl sm:text-3xl text-indigo-500"></i>
        </div>
      </div>
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-3 sm:p-4">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400">Initial Balance</p>
            <p class="text-xl sm:text-2xl font-bold text-indigo-600" id="summaryInitialTotal">KES 0</p>
          </div>
          <i class="fas fa-chart-line text-2xl sm:text-3xl text-indigo-500"></i>
        </div>
      </div>
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-3 sm:p-4">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400">Previous Balance</p>
            <p class="text-xl sm:text-2xl font-bold text-orange-600" id="summaryPreviousTotal">KES 0</p>
          </div>
          <i class="fas fa-history text-2xl sm:text-3xl text-orange-500"></i>
        </div>
      </div>
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-3 sm:p-4">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400">Overall Total</p>
            <p class="text-xl sm:text-2xl font-bold text-green-600" id="summaryOverallTotal">KES 0</p>
          </div>
          <i class="fas fa-calculator text-2xl sm:text-3xl text-green-500"></i>
        </div>
      </div>
    </div>
  </div>
</main>

<!-- Import Modal -->
<div id="importModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
  <div class="bg-white dark:bg-gray-800 w-full max-w-lg rounded-lg shadow-xl">
    <div class="border-b border-gray-200 dark:border-gray-700 px-4 sm:px-6 py-3 sm:py-4 flex justify-between items-center">
      <h3 class="text-base sm:text-lg font-semibold text-gray-800 dark:text-white">Import Initial Balance</h3>
      <button id="closeImportModal" class="text-gray-400 hover:text-gray-500">
        <i class="fas fa-times text-xl"></i>
      </button>
    </div>
    <div class="px-4 sm:px-6 py-4">
      <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Upload Excel/CSV File</label>
        <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-6 text-center cursor-pointer hover:border-indigo-500 transition" id="uploadArea">
          <i class="fas fa-file-excel text-4xl text-green-500 mb-2"></i>
          <p class="text-sm text-gray-600 dark:text-gray-400">Click or drag & drop file here</p>
          <p class="text-xs text-gray-500 dark:text-gray-500 mt-1">.xls, .xlsx, .csv files only</p>
          <input type="file" id="excelFile" accept=".xls,.xlsx,.csv" class="hidden">
        </div>
      </div>
      <div>
        <a href="#" id="downloadSampleBtn" class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 text-sm">
          <i class="fas fa-download mr-1"></i> Download Sample Template
        </a>
      </div>
    </div>
    <div class="border-t border-gray-200 dark:border-gray-700 px-4 sm:px-6 py-3 sm:py-4 flex justify-end gap-3">
      <button id="cancelImportBtn" class="px-3 py-1.5 sm:px-4 sm:py-2 border border-gray-300 rounded-lg hover:bg-gray-100 text-sm">Cancel</button>
      <button id="processImportBtn" class="px-3 py-1.5 sm:px-4 sm:py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm" disabled>Import</button>
    </div>
  </div>
</div>

<!-- Toast Notification -->
<div id="toastNotification" class="toast-notification">
  <span id="toastMessage"></span>
  <button onclick="hideToast()" class="text-white hover:text-gray-200">&times;</button>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>
const schoolId = <?php echo json_encode($school_id); ?>;
const currentAcademicLevel = <?php echo json_encode($academic_level); ?>;
let studentsData = [];
let voteHeads = [];
let classes = [];
let streams = [];

function showToast(message, isError = false) {
    const toast = document.getElementById('toastNotification');
    const toastMessage = document.getElementById('toastMessage');
    toastMessage.textContent = message;
    toast.style.background = isError ? '#ef4444' : '#10b981';
    toast.classList.add('show');
    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}

function hideToast() {
    document.getElementById('toastNotification').classList.remove('show');
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

async function loadClasses() {
    try {
        const response = await fetch('../../api/feesystem/get_classes.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ school_id: schoolId, academic_level: currentAcademicLevel })
        });
        const data = await response.json();
        if (data.success) {
            classes = data.classes;
            const classSelect = document.getElementById('classFilter');
            classSelect.innerHTML = '<option value="">Select Class</option>' + 
                classes.map(c => `<option value="${c.id}">${escapeHtml(c.class_level)}</option>`).join('');
        }
    } catch (error) {
        console.error('Error loading classes:', error);
        showToast('Failed to load classes', true);
    }
}

document.getElementById('classFilter').addEventListener('change', async () => {
    const classId = document.getElementById('classFilter').value;
    const streamSelect = document.getElementById('streamFilter');
    
    if (!classId) {
        streamSelect.innerHTML = '<option value="">All Streams</option>';
        streams = [];
        return;
    }
    
    try {
        const response = await fetch('../../api/feesystem/get_streams.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ school_id: schoolId, class_id: classId })
        });
        const data = await response.json();
        if (data.success) {
            streams = data.streams;
            streamSelect.innerHTML = '<option value="">All Streams</option>' + 
                streams.map(s => `<option value="${s.id}">${escapeHtml(s.stream_name)}</option>`).join('');
        }
    } catch (error) {
        console.error('Error loading streams:', error);
    }
});

async function loadVoteHeads() {
    try {
        const response = await fetch('../../api/feesystem/get_vote_heads.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ school_id: schoolId, status: 'active', type: 'income' })
        });
        const data = await response.json();
        if (data.success) {
            voteHeads = data.vote_heads;
            const voteHeadSelect = document.getElementById('voteHeadFilter');
            voteHeadSelect.innerHTML = '<option value="">Select Vote Head</option>' + 
                voteHeads.map(vh => `<option value="${vh.id}">${escapeHtml(vh.name)} (${escapeHtml(vh.alias)})</option>`).join('');
        }
    } catch (error) {
        console.error('Error loading vote heads:', error);
        showToast('Failed to load vote heads', true);
    }
}

document.getElementById('loadStudentsBtn').addEventListener('click', async () => {
    const classId = document.getElementById('classFilter').value;
    const streamId = document.getElementById('streamFilter').value;
    const voteHeadId = document.getElementById('voteHeadFilter').value;
    const year = document.getElementById('yearFilter').value;
    const term = document.getElementById('termFilter').value;
    
    if (!classId) {
        Swal.fire('Warning', 'Please select a class', 'warning');
        return;
    }
    
    if (!voteHeadId) {
        Swal.fire('Warning', 'Please select a vote head', 'warning');
        return;
    }
    
    const tbody = document.getElementById('studentsTableBody');
    tbody.innerHTML = '<tr><td colspan="7" class="px-3 sm:px-6 py-8 text-center"><div class="spinner-small mx-auto"></div><p class="mt-2 text-sm">Loading students...</p></div></td></tr>';
    
    try {
        const response = await fetch('../../api/feesystem/get_students_balances.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                school_id: schoolId, 
                class_id: classId, 
                stream_id: streamId || null,
                vote_head_id: voteHeadId,
                year: year,
                term: term
            })
        });
        const data = await response.json();
        
        if (data.success) {
            studentsData = data.students;
            renderStudentsTable();
            updateTotals();
            showToast(`Loaded ${studentsData.length} students`);
        } else {
            tbody.innerHTML = `<tr><td colspan="7" class="px-3 sm:px-6 py-8 text-center text-red-500 text-sm">${escapeHtml(data.message)}</td></tr>`;
        }
    } catch (error) {
        tbody.innerHTML = '<tr><td colspan="7" class="px-3 sm:px-6 py-8 text-center text-red-500 text-sm">Error loading students</td></tr>';
        showToast('Error loading students', true);
    }
});

function renderStudentsTable() {
    const tbody = document.getElementById('studentsTableBody');
    
    if (!studentsData || studentsData.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="px-3 sm:px-6 py-8 text-center text-gray-500 text-sm">No students found</td></tr>';
        document.getElementById('totalStudents').textContent = '0';
        return;
    }
    
    tbody.innerHTML = studentsData.map(student => `
        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
            <td class="px-3 sm:px-6 py-3 sm:py-4" data-label="Select">
                <input type="checkbox" class="student-checkbox rounded border-gray-300" data-id="${student.id}" data-admission="${student.admission_no}">
            </td>
            <td class="px-3 sm:px-6 py-3 sm:py-4 text-sm" data-label="Admission No">${escapeHtml(student.admission_no)}</td>
            <td class="px-3 sm:px-6 py-3 sm:py-4 text-sm font-medium" data-label="Student Name">${escapeHtml(student.full_name)}</td>
            <td class="px-3 sm:px-6 py-3 sm:py-4 text-sm" data-label="Gender">
                <span class="px-2 py-1 text-xs rounded-full ${student.gender === 'Male' ? 'bg-blue-100 text-blue-800' : 'bg-pink-100 text-pink-800'}">
                    ${escapeHtml(student.gender)}
                </span>
            </td>
            <td class="px-3 sm:px-6 py-3 sm:py-4" data-label="Initial Balance">
                <input type="number" class="initial-balance-input w-24 sm:w-32 px-2 py-1 border border-gray-300 dark:border-gray-600 rounded-lg text-sm" 
                       data-id="${student.id}" value="${student.initial_balance || 0}" step="0.01" placeholder="0.00">
            </td>
            <td class="px-3 sm:px-6 py-3 sm:py-4 text-sm text-orange-600 dark:text-orange-400" data-label="Previous Balance">
                KES ${(student.previous_balance || 0).toLocaleString()}
            </td>
            <td class="px-3 sm:px-6 py-3 sm:py-4 text-sm font-semibold text-green-600 dark:text-green-400 total-cell" data-id="${student.id}" data-label="Total">
                KES ${((student.initial_balance || 0) + (student.previous_balance || 0)).toLocaleString()}
            </td>
        </tr>
    `).join('');
    
    document.querySelectorAll('.initial-balance-input').forEach(input => {
        input.addEventListener('input', function() {
            const id = this.dataset.id;
            const initialBalance = parseFloat(this.value) || 0;
            const previousBalance = studentsData.find(s => s.id == id)?.previous_balance || 0;
            const total = initialBalance + previousBalance;
            const totalCell = document.querySelector(`.total-cell[data-id="${id}"]`);
            if (totalCell) totalCell.textContent = `KES ${total.toLocaleString()}`;
            updateTotals();
        });
    });
    
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    if (selectAllCheckbox) {
        selectAllCheckbox.checked = false;
        selectAllCheckbox.onclick = () => {
            document.querySelectorAll('.student-checkbox').forEach(cb => {
                cb.checked = selectAllCheckbox.checked;
            });
        };
    }
    
    document.getElementById('totalStudents').textContent = studentsData.length;
}

function updateTotals() {
    let initialTotal = 0;
    let previousTotal = 0;
    
    studentsData.forEach(student => {
        const initialBalance = parseFloat(document.querySelector(`.initial-balance-input[data-id="${student.id}"]`)?.value) || 0;
        initialTotal += initialBalance;
        previousTotal += student.previous_balance || 0;
    });
    
    const overallTotal = initialTotal + previousTotal;
    
    document.getElementById('initialBalanceTotal').textContent = `KES ${initialTotal.toLocaleString()}`;
    document.getElementById('previousBalanceTotal').textContent = `KES ${previousTotal.toLocaleString()}`;
    document.getElementById('grandTotal').textContent = `KES ${overallTotal.toLocaleString()}`;
    document.getElementById('summaryInitialTotal').textContent = `KES ${initialTotal.toLocaleString()}`;
    document.getElementById('summaryPreviousTotal').textContent = `KES ${previousTotal.toLocaleString()}`;
    document.getElementById('summaryOverallTotal').textContent = `KES ${overallTotal.toLocaleString()}`;
}

document.getElementById('applyBulkBtn').addEventListener('click', () => {
    const bulkAmount = parseFloat(document.getElementById('bulkAmount').value);
    
    if (isNaN(bulkAmount)) {
        Swal.fire('Warning', 'Please enter a valid amount', 'warning');
        return;
    }
    
    const selectedCheckboxes = document.querySelectorAll('.student-checkbox:checked');
    
    if (selectedCheckboxes.length === 0) {
        Swal.fire('Warning', 'Please select at least one student', 'warning');
        return;
    }
    
    selectedCheckboxes.forEach(cb => {
        const studentId = cb.dataset.id;
        const input = document.querySelector(`.initial-balance-input[data-id="${studentId}"]`);
        if (input) {
            input.value = bulkAmount;
            input.dispatchEvent(new Event('input'));
        }
    });
    
    Swal.fire('Success', `Applied KES ${bulkAmount.toLocaleString()} to ${selectedCheckboxes.length} student(s)`, 'success');
});

document.getElementById('saveBalancesBtn').addEventListener('click', async () => {
    const classId = document.getElementById('classFilter').value;
    const voteHeadId = document.getElementById('voteHeadFilter').value;
    const year = document.getElementById('yearFilter').value;
    const term = document.getElementById('termFilter').value;
    const recordedAt = document.getElementById('recordedAt').value;
    
    if (!classId || !voteHeadId) {
        Swal.fire('Warning', 'Please select class and vote head', 'warning');
        return;
    }
    
    const balances = [];
    document.querySelectorAll('.initial-balance-input').forEach(input => {
        const studentId = input.dataset.id;
        const amount = parseFloat(input.value) || 0;
        if (amount !== 0) {
            balances.push({ student_id: studentId, amount: amount });
        }
    });
    
    if (balances.length === 0) {
        Swal.fire('Info', 'No balances to save', 'info');
        return;
    }
    
    const saveBtn = document.getElementById('saveBalancesBtn');
    const originalHtml = saveBtn.innerHTML;
    saveBtn.innerHTML = '<div class="spinner-small mr-2"></div>Saving...';
    saveBtn.disabled = true;
    
    try {
        const response = await fetch('../../api/feesystem/save_initial_balances.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                school_id: schoolId,
                class_id: classId,
                vote_head_id: voteHeadId,
                year: year,
                term: term,
                recorded_at: recordedAt,
                balances: balances
            })
        });
        const data = await response.json();
        
        if (data.success) {
            Swal.fire('Success', 'Initial balances saved successfully!', 'success');
            document.getElementById('loadStudentsBtn').click();
        } else {
            Swal.fire('Error', data.message || 'Failed to save balances', 'error');
        }
    } catch (error) {
        Swal.fire('Error', 'An error occurred while saving', 'error');
    } finally {
        saveBtn.innerHTML = originalHtml;
        saveBtn.disabled = false;
    }
});

// Import functionality
const importModal = document.getElementById('importModal');
let selectedFile = null;

document.getElementById('importBtn')?.addEventListener('click', () => {
    importModal.classList.remove('hidden');
});

function closeImportModal() {
    importModal.classList.add('hidden');
    selectedFile = null;
    document.getElementById('excelFile').value = '';
}

document.getElementById('closeImportModal')?.addEventListener('click', closeImportModal);
document.getElementById('cancelImportBtn')?.addEventListener('click', closeImportModal);

const uploadArea = document.getElementById('uploadArea');
const excelFile = document.getElementById('excelFile');

uploadArea.addEventListener('click', () => excelFile.click());
uploadArea.addEventListener('dragover', (e) => {
    e.preventDefault();
    uploadArea.classList.add('border-indigo-500', 'bg-indigo-50');
});
uploadArea.addEventListener('dragleave', () => {
    uploadArea.classList.remove('border-indigo-500', 'bg-indigo-50');
});
uploadArea.addEventListener('drop', (e) => {
    e.preventDefault();
    uploadArea.classList.remove('border-indigo-500', 'bg-indigo-50');
    const file = e.dataTransfer.files[0];
    if (file) handleFile(file);
});

excelFile.addEventListener('change', (e) => {
    if (e.target.files.length > 0) handleFile(e.target.files[0]);
});

function handleFile(file) {
    selectedFile = file;
    document.getElementById('processImportBtn').disabled = false;
    Swal.fire('Success', `File "${file.name}" selected`, 'success');
}

document.getElementById('processImportBtn').addEventListener('click', async () => {
    if (!selectedFile) {
        Swal.fire('Error', 'Please select a file first', 'error');
        return;
    }
    
    const classId = document.getElementById('classFilter').value;
    const voteHeadId = document.getElementById('voteHeadFilter').value;
    const year = document.getElementById('yearFilter').value;
    const term = document.getElementById('termFilter').value;
    const recordedAt = document.getElementById('recordedAt').value;
    
    if (!classId || !voteHeadId) {
        Swal.fire('Warning', 'Please select class and vote head', 'warning');
        return;
    }
    
    const formData = new FormData();
    formData.append('file', selectedFile);
    formData.append('school_id', schoolId);
    formData.append('class_id', classId);
    formData.append('vote_head_id', voteHeadId);
    formData.append('year', year);
    formData.append('term', term);
    formData.append('recorded_at', recordedAt);
    
    const importBtn = document.getElementById('processImportBtn');
    const originalHtml = importBtn.innerHTML;
    importBtn.innerHTML = '<div class="spinner-small mr-2"></div>Importing...';
    importBtn.disabled = true;
    
    try {
        const response = await fetch('../../api/feesystem/import_initial_balances.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        
        if (data.success) {
            Swal.fire('Success', `Imported ${data.imported_count} balances!`, 'success');
            closeImportModal();
            document.getElementById('loadStudentsBtn').click();
        } else {
            Swal.fire('Error', data.message || 'Import failed', 'error');
        }
    } catch (error) {
        Swal.fire('Error', 'An error occurred during import', 'error');
    } finally {
        importBtn.innerHTML = originalHtml;
        importBtn.disabled = false;
    }
});

document.getElementById('downloadSampleBtn')?.addEventListener('click', (e) => {
    e.preventDefault();
    const sampleData = [
        ['admission_number', 'full_name', 'initial_balance'],
        ['2024001', 'John Doe', '5000'],
        ['2024002', 'Jane Smith', '7500'],
        ['2024003', 'Michael Johnson', '3000']
    ];
    const ws = XLSX.utils.aoa_to_sheet(sampleData);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Initial Balances');
    XLSX.writeFile(wb, `initial_balance_template_${new Date().toISOString().split('T')[0]}.xlsx`);
});

window.addEventListener('storage', function(event) {
    if (event.key === 'academic_level_changed') {
        location.reload();
    }
});

document.addEventListener('DOMContentLoaded', () => {
    loadClasses();
    loadVoteHeads();
});
</script>

<?php include_once('../../includes/footer.php'); ?>
</body>
</html>