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

<main class="main-content flex-grow flex flex-col">
  <header class="bg-white shadow-sm dark:bg-gray-800 dark:border-gray-700">
    <div class="px-4 py-3 flex justify-between items-center">
      <div class="flex items-center">
        <button id="mobile-toggle" class="mr-4 text-gray-500 hover:text-indigo-600 focus:outline-none lg:hidden">
          <i class="fas fa-bars text-xl"></i>
        </button>
        <h1 id="page-title" class="text-xl font-semibold text-gray-800 dark:text-white">Fee Management</h1>
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
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      
      <!-- LEFT COLUMN - Balance Table -->
      <div class="lg:col-span-2">
        <!-- Filter Section -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 mb-6">
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Class</label>
              <select id="classFilter" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500">
                <option value="">Select Class...</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Stream</label>
              <select id="streamFilter" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500">
                <option value="">All Streams</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Term</label>
              <select id="termFilter" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500">
                <option value="1">Term 1</option>
                <option value="2">Term 2</option>
                <option value="3">Term 3</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Year</label>
              <select id="yearFilter" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500">
                <option value="2024">2024</option>
                <option value="2025">2025</option>
                <option value="2026" selected>2026</option>
                <option value="2027">2027</option>
              </select>
            </div>
          </div>
          
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Balance Filter</label>
              <div class="flex flex-wrap gap-3">
                <label class="flex items-center gap-1">
                  <input type="checkbox" id="filterOverdue" value="overdue" class="rounded"> Overdue (>0)
                </label>
                <label class="flex items-center gap-1">
                  <input type="checkbox" id="filterPrepaid" value="prepaid" class="rounded"> Prepaid (&lt;0)
                </label>
                <label class="flex items-center gap-1">
                  <input type="checkbox" id="filterCleared" value="cleared" class="rounded"> Cleared (=0)
                </label>
                <label class="flex items-center gap-1">
                  <input type="checkbox" id="filterAll" value="all" class="rounded" checked> All
                </label>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
              <select id="statusFilter" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500">
                <option value="">All Status</option>
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
              </select>
            </div>
            <div class="flex items-end">
              <button id="fetchBalanceBtn" class="w-full px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                <i class="fas fa-chart-line mr-2"></i>Fetch Balances
              </button>
            </div>
            <div class="flex items-end">
              <button id="exportExcelBtn" class="w-full px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                <i class="fas fa-file-excel mr-2"></i>Export Excel
              </button>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-wrap gap-3 mb-6">
          <button id="generateLettersBtn" class="px-5 py-2.5 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition duration-200 shadow-sm">
            <i class="fas fa-file-alt mr-2"></i>Generate Letters
          </button>
          <button id="generateMessagesBtn" class="px-5 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition duration-200 shadow-sm">
            <i class="fas fa-envelope mr-2"></i>Generate Messages
          </button>
          <button id="messageOnLetterBtn" class="px-5 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition duration-200 shadow-sm">
            <i class="fas fa-pen-alt mr-2"></i>Message on Letter
          </button>
          <button id="smsTemplateBtn" class="px-5 py-2.5 bg-green-600 text-white rounded-lg hover:bg-green-700 transition duration-200 shadow-sm">
            <i class="fas fa-sms mr-2"></i>SMS Template
          </button>
          <button id="printTableBtn" class="px-5 py-2.5 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition duration-200 shadow-sm">
            <i class="fas fa-print mr-2"></i>Print Table
          </button>
        </div>

        <!-- Students Balance Table with Expected, Paid, Balance -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full" id="balancesTable">
              <thead class="bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                <tr>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    <input type="checkbox" id="selectAllCheckbox" class="rounded">
                  </th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Admission No</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Student Name</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Class</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Stream</th>
                  <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Expected (KES)</th>
                  <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Paid (KES)</th>
                  <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Balance (KES)</th>
                </tr>
              </thead>
              <tbody id="balancesTableBody" class="divide-y divide-gray-200 dark:divide-gray-700">
                <tr>
                  <td colspan="8" class="px-6 py-8 text-center text-gray-500">Select class and click "Fetch Balances"</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      
      <!-- RIGHT COLUMN - PDF Preview -->
      <div class="space-y-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden sticky top-4">
          <div class="border-b border-gray-200 dark:border-gray-700 px-6 py-4 bg-gray-50 dark:bg-gray-700">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white">
              <i class="fas fa-file-pdf text-red-500 mr-2"></i>PDF Preview
            </h3>
            <p class="text-xs text-gray-500 mt-1">Preview shows how the demand notes will look</p>
          </div>
          <div id="pdfPreviewContainer" class="bg-gray-100 dark:bg-gray-900" style="height: 600px; overflow-y: auto;">
            <div id="pdfPreview" class="p-4">
              <div class="text-center text-gray-500 py-8">
                <i class="fas fa-eye-slash text-4xl mb-2"></i>
                <p>Select students and click "Generate Letters"</p>
              </div>
            </div>
          </div>
          <div class="border-t border-gray-200 dark:border-gray-700 px-6 py-4 flex justify-end">
            <button id="downloadPdfBtn" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 disabled:opacity-50" disabled>
              <i class="fas fa-download mr-2"></i>Download PDF
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
const schoolId = <?php echo json_encode($school_id); ?>;
let studentsData = [];
let classes = [];
let streams = [];
let selectedStudents = [];

// Load classes
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
      const classSelect = document.getElementById('classFilter');
      classSelect.innerHTML = '<option value="">Select Class...</option>' + 
        classes.map(c => `<option value="${c.id}">${escapeHtml(c.class_level)}</option>`).join('');
    }
  } catch (error) {
    console.error('Error loading classes:', error);
  }
}

// Load streams based on class
document.getElementById('classFilter').addEventListener('change', async () => {
  const classId = document.getElementById('classFilter').value;
  const streamSelect = document.getElementById('streamFilter');
  
  if (!classId) {
    streamSelect.innerHTML = '<option value="">All Streams</option>';
    streams = [];
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
      streams = data.streams;
      streamSelect.innerHTML = '<option value="">All Streams</option>' + 
        streams.map(s => `<option value="${s.id}">${escapeHtml(s.stream_name)}</option>`).join('');
    }
  } catch (error) {
    console.error('Error loading streams:', error);
  }
});

// Fetch balances with Expected, Paid, Balance
document.getElementById('fetchBalanceBtn').addEventListener('click', async () => {
  const classId = document.getElementById('classFilter').value;
  const streamId = document.getElementById('streamFilter').value;
  const term = document.getElementById('termFilter').value;
  const year = document.getElementById('yearFilter').value;
  const status = document.getElementById('statusFilter').value;
  
  if (!classId) {
    Swal.fire('Warning', 'Please select a class first', 'warning');
    return;
  }
  
  const filterOverdue = document.getElementById('filterOverdue').checked;
  const filterPrepaid = document.getElementById('filterPrepaid').checked;
  const filterCleared = document.getElementById('filterCleared').checked;
  const filterAll = document.getElementById('filterAll').checked;
  
  const tbody = document.getElementById('balancesTableBody');
  tbody.innerHTML = '<tr><td colspan="8" class="px-6 py-8 text-center"><i class="fas fa-spinner fa-spin mr-2"></i>Loading balances...</td></tr>';
  
  try {
    const response = await fetch('/feesystem/api/feesystem/get_detailed_fee_balances.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ 
        school_id: schoolId, 
        class_id: classId, 
        stream_id: streamId || null, 
        term: term, 
        year: year,
        status: status || null
      })
    });
    const data = await response.json();
    
    if (data.success) {
      studentsData = data.students;
      
      let filteredData = studentsData;
      if (!filterAll) {
        filteredData = studentsData.filter(student => {
          const balance = student.balance;
          if (filterOverdue && balance > 0) return true;
          if (filterPrepaid && balance < 0) return true;
          if (filterCleared && balance === 0) return true;
          return false;
        });
      }
      
      renderBalancesTable(filteredData);
    } else {
      tbody.innerHTML = `<tr><td colspan="8" class="px-6 py-8 text-center text-red-500">${escapeHtml(data.message)}</td></tr>`;
    }
  } catch (error) {
    tbody.innerHTML = '<tr><td colspan="8" class="px-6 py-8 text-center text-red-500">Error loading balances</td></tr>';
  }
});

function renderBalancesTable(students) {
  const tbody = document.getElementById('balancesTableBody');
  
  if (!students || students.length === 0) {
    tbody.innerHTML = '<tr><td colspan="8" class="px-6 py-8 text-center text-gray-500">No students found</td></tr>';
    document.getElementById('selectAllCheckbox').checked = false;
    return;
  }
  
  tbody.innerHTML = students.map(student => {
    const balanceClass = student.balance > 0 ? 'text-red-600 font-semibold' : (student.balance < 0 ? 'text-green-600' : 'text-gray-600');
    const expectedAmount = student.expected_amount || 0;
    const paidAmount = student.paid_amount || 0;
    
    return `
      <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer" onclick="toggleCheckbox(this, ${student.id})">
        <td class="px-4 py-3">
          <input type="checkbox" class="student-checkbox rounded" data-id="${student.id}" data-admission="${student.admission_no}" onclick="event.stopPropagation()">
        </td>
        <td class="px-4 py-3 text-sm font-mono">${escapeHtml(student.admission_no)}</td>
        <td class="px-4 py-3 text-sm font-medium">${escapeHtml(student.full_name)}</td>
        <td class="px-4 py-3 text-sm">${escapeHtml(student.class_name || 'N/A')}</td>
        <td class="px-4 py-3 text-sm">${escapeHtml(student.stream_name || 'N/A')}</td>
        <td class="px-4 py-3 text-sm text-right">${numberFormat(expectedAmount)}</td>
        <td class="px-4 py-3 text-sm text-right">${numberFormat(paidAmount)}</td>
        <td class="px-4 py-3 text-sm text-right ${balanceClass}">${numberFormat(student.balance)}</td>
      </tr>
    `;
  }).join('');
  
  document.getElementById('selectAllCheckbox').onclick = () => {
    document.querySelectorAll('.student-checkbox').forEach(cb => {
      cb.checked = document.getElementById('selectAllCheckbox').checked;
    });
    updateSelectedStudents();
  };
  
  document.querySelectorAll('.student-checkbox').forEach(cb => {
    cb.onchange = () => updateSelectedStudents();
  });
}

function toggleCheckbox(row, studentId) {
  const cb = row.querySelector('.student-checkbox');
  if (cb) cb.checked = !cb.checked;
  updateSelectedStudents();
}

function updateSelectedStudents() {
  selectedStudents = [];
  document.querySelectorAll('.student-checkbox:checked').forEach(cb => {
    const studentId = parseInt(cb.dataset.id);
    const student = studentsData.find(s => s.id === studentId);
    if (student) selectedStudents.push(student);
  });
}

function numberFormat(amount) {
  if (amount === undefined || amount === null) return 'KES 0.00';
  const formatted = Math.abs(amount).toLocaleString('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  return amount < 0 ? `(KES ${formatted})` : `KES ${formatted}`;
}

// Enhanced Message on Letter Modal with Database Tags
document.getElementById('messageOnLetterBtn').addEventListener('click', () => {
    // Load saved message from localStorage
    const savedMessage = localStorage.getItem('customLetterMessage') || 
        `Dear Parent / Guardian,\n\nThis is to notify you that your outstanding fee balance is KES [balance].\n\nPlease make arrangements to clear the balance soonest possible to avoid wasting students learning time.\n\nThank You.\n\nAccounts Office`;
    
    Swal.fire({
        title: 'Customize Letter Message',
        html: `
            <div class="text-left">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Message Content</label>
                    <textarea id="customMessage" class="w-full h-48 p-3 border rounded-lg font-mono text-sm" placeholder="Enter your message here...">${escapeHtml(savedMessage)}</textarea>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Preview</label>
                    <div id="messagePreview" class="p-3 bg-gray-50 dark:bg-gray-700 rounded-lg text-sm max-h-40 overflow-y-auto"></div>
                </div>
                <div class="border-t pt-3">
                    <p class="text-sm font-semibold text-gray-700 mb-2">Available Database Tags (Click to insert):</p>
                    <div class="flex flex-wrap gap-2 mb-3">
                        <button type="button" class="tag-btn px-2 py-1 bg-indigo-100 text-indigo-700 rounded text-xs hover:bg-indigo-200" data-tag="[student_name]">[student_name]</button>
                        <button type="button" class="tag-btn px-2 py-1 bg-indigo-100 text-indigo-700 rounded text-xs hover:bg-indigo-200" data-tag="[admission_no]">[admission_no]</button>
                        <button type="button" class="tag-btn px-2 py-1 bg-indigo-100 text-indigo-700 rounded text-xs hover:bg-indigo-200" data-tag="[balance]">[balance] - Current Balance</button>
                        <button type="button" class="tag-btn px-2 py-1 bg-indigo-100 text-indigo-700 rounded text-xs hover:bg-indigo-200" data-tag="[next]">[next] - Next Term Fee</button>
                        <button type="button" class="tag-btn px-2 py-1 bg-indigo-100 text-indigo-700 rounded text-xs hover:bg-indigo-200" data-tag="[total]">[total] - Balance + Next Term</button>
                        <button type="button" class="tag-btn px-2 py-1 bg-indigo-100 text-indigo-700 rounded text-xs hover:bg-indigo-200" data-tag="[class]">[class]</button>
                        <button type="button" class="tag-btn px-2 py-1 bg-indigo-100 text-indigo-700 rounded text-xs hover:bg-indigo-200" data-tag="[stream]">[stream]</button>
                        <button type="button" class="tag-btn px-2 py-1 bg-indigo-100 text-indigo-700 rounded text-xs hover:bg-indigo-200" data-tag="[term]">[term]</button>
                        <button type="button" class="tag-btn px-2 py-1 bg-indigo-100 text-indigo-700 rounded text-xs hover:bg-indigo-200" data-tag="[year]">[year]</button>
                        <button type="button" class="tag-btn px-2 py-1 bg-indigo-100 text-indigo-700 rounded text-xs hover:bg-indigo-200" data-tag="[school_name]">[school_name]</button>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">
                        <i class="fas fa-info-circle"></i> 
                        <strong>[balance]</strong> - Current fee balance<br>
                        <strong>[next]</strong> - Next term's expected fee<br>
                        <strong>[total]</strong> - Combined total (current balance + next term fee)
                    </p>
                </div>
            </div>
        `,
        width: '700px',
        showCancelButton: true,
        confirmButtonText: 'Save Message',
        cancelButtonText: 'Cancel',
        didOpen: () => {
            // Add tag insertion functionality
            document.querySelectorAll('.tag-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const textarea = document.getElementById('customMessage');
                    const tag = btn.getAttribute('data-tag');
                    const start = textarea.selectionStart;
                    const end = textarea.selectionEnd;
                    const text = textarea.value;
                    textarea.value = text.substring(0, start) + tag + text.substring(end);
                    textarea.focus();
                    textarea.setSelectionRange(start + tag.length, start + tag.length);
                    updatePreview();
                });
            });
            
            const textarea = document.getElementById('customMessage');
            textarea.addEventListener('input', updatePreview);
            updatePreview();
        },
        preConfirm: () => {
            const message = document.getElementById('customMessage').value;
            if (!message) {
                Swal.showValidationMessage('Please enter a message');
                return false;
            }
            return message;
        }
    }).then((result) => {
        if (result.isConfirmed) {
            // Save to localStorage
            localStorage.setItem('customLetterMessage', result.value);
            
            // Test with a sample student
            if (selectedStudents.length > 0) {
                const sampleStudent = selectedStudents[0];
                const previewMessage = replaceTags(result.value, sampleStudent);
                Swal.fire({
                    title: 'Message Saved!',
                    html: `
                        <p class="mb-3">Your custom message has been saved.</p>
                        <div class="bg-gray-50 p-3 rounded text-left">
                            <p class="text-sm font-semibold mb-2">Preview with ${sampleStudent.full_name}:</p>
                            <p class="text-sm whitespace-pre-line">${escapeHtml(previewMessage)}</p>
                        </div>
                    `,
                    icon: 'success'
                });
            } else {
                Swal.fire('Success', 'Custom message saved successfully!', 'success');
            }
        }
    });
});

function updatePreview() {
    const message = document.getElementById('customMessage').value;
    const preview = document.getElementById('messagePreview');
    if (preview) {
        // Show preview with sample data
        const sampleData = {
            student_name: 'John Doe',
            admission_no: '2024/001',
            balance: '5,000.00',
            next: '15,000.00',
            total: '20,000.00',
            class: 'Form 4',
            stream: 'East',
            term: '1',
            year: '2026',
            school_name: 'SCHOOL NAME'
        };
        let previewText = message;
        for (const [key, value] of Object.entries(sampleData)) {
            previewText = previewText.replace(new RegExp(`\\[${key}\\]`, 'g'), value);
        }
        preview.innerHTML = previewText.replace(/\n/g, '<br>');
    }
}

function replaceTags(message, student) {
    let processed = message;
    const tags = {
        'student_name': student.full_name,
        'admission_no': student.admission_no,
        'balance': numberFormat(student.balance),
        'next': numberFormat(student.next_term_fee || 0),
        'total': numberFormat((student.balance || 0) + (student.next_term_fee || 0)),
        'class': student.class_name || 'N/A',
        'stream': student.stream_name || 'N/A',
        'term': document.getElementById('termFilter').value,
        'year': document.getElementById('yearFilter').value,
        'school_name': document.getElementById('school_name')?.value || 'School'
    };
    
    for (const [key, value] of Object.entries(tags)) {
        processed = processed.replace(new RegExp(`\\[${key}\\]`, 'g'), value);
    }
    return processed;
}

// Generate Messages
document.getElementById('generateMessagesBtn').addEventListener('click', () => {
    if (selectedStudents.length === 0) {
        Swal.fire('Warning', 'Please select at least one student', 'warning');
        return;
    }
    
    const customMessage = localStorage.getItem('customLetterMessage');
    const defaultMessage = `Dear Parent / Guardian,\n\nThis is to notify you that your outstanding fee balance is KES [balance].\n\nPlease make arrangements to clear the balance soonest possible to avoid wasting students learning time.\n\nThank You.`;
    const messageTemplate = customMessage || defaultMessage;
    
    const messages = selectedStudents.map(student => {
        return replaceTags(messageTemplate, student);
    }).join('\n\n' + '='.repeat(50) + '\n\n');
    
    Swal.fire({
        title: 'Generated Messages',
        html: `<textarea class="w-full h-96 p-3 border rounded font-mono text-sm" readonly>${escapeHtml(messages)}</textarea>`,
        width: '700px',
        showConfirmButton: true,
        confirmButtonText: 'Copy to Clipboard',
        showCancelButton: true,
        cancelButtonText: 'Close'
    }).then((result) => {
        if (result.isConfirmed) {
            navigator.clipboard.writeText(messages);
            Swal.fire('Copied!', 'Messages copied to clipboard', 'success');
        }
    });
});

// SMS Template Management
document.getElementById('smsTemplateBtn').addEventListener('click', () => {
    const savedSmsTemplate = localStorage.getItem('smsTemplate') || 'Dear Parent, {student_name} has a fee balance of KES {balance}. Please clear to avoid disruption.';
    
    Swal.fire({
        title: 'SMS Template Manager',
        html: `
            <div class="text-left">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">SMS Template (160 chars max)</label>
                    <textarea id="smsTemplate" class="w-full h-24 p-2 border rounded" maxlength="160">${escapeHtml(savedSmsTemplate)}</textarea>
                    <div class="text-right text-xs text-gray-500 mt-1">
                        <span id="charCount">0</span>/160 characters
                    </div>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Template Name</label>
                    <input type="text" id="templateName" class="w-full p-2 border rounded" placeholder="e.g., Standard Reminder" value="Default Template">
                </div>
                <div class="text-sm text-gray-500">
                    <p><strong>Available placeholders:</strong></p>
                    <div class="flex flex-wrap gap-2 mt-2">
                        <span class="px-2 py-1 bg-gray-100 rounded text-xs">{student_name}</span>
                        <span class="px-2 py-1 bg-gray-100 rounded text-xs">{admission_no}</span>
                        <span class="px-2 py-1 bg-gray-100 rounded text-xs">{balance}</span>
                        <span class="px-2 py-1 bg-gray-100 rounded text-xs">{class}</span>
                    </div>
                </div>
            </div>
        `,
        width: '550px',
        showCancelButton: true,
        confirmButtonText: 'Save Template',
        cancelButtonText: 'Cancel',
        didOpen: () => {
            const textarea = document.getElementById('smsTemplate');
            const charCount = document.getElementById('charCount');
            const updateCount = () => {
                charCount.textContent = textarea.value.length;
            };
            textarea.addEventListener('input', updateCount);
            updateCount();
        },
        preConfirm: () => {
            const template = document.getElementById('smsTemplate').value;
            const name = document.getElementById('templateName').value;
            if (!template) {
                Swal.showValidationMessage('Please enter template content');
                return false;
            }
            if (template.length > 160) {
                Swal.showValidationMessage('SMS template exceeds 160 characters');
                return false;
            }
            return { template, name };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            localStorage.setItem('smsTemplate', result.value.template);
            localStorage.setItem('smsTemplateName', result.value.name);
            Swal.fire('Success', 'SMS template saved successfully!', 'success');
        }
    });
});

// Generate Letters with Custom Message
document.getElementById('generateLettersBtn').addEventListener('click', async () => {
    if (selectedStudents.length === 0) {
        Swal.fire('Warning', 'Please select at least one student', 'warning');
        return;
    }
    
    const year = document.getElementById('yearFilter').value;
    const term = document.getElementById('termFilter').value;
    const customMessage = localStorage.getItem('customLetterMessage');
    
    Swal.fire({
        title: 'Generating PDF...',
        text: 'Please wait',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });
    
    try {
        const response = await fetch('/feesystem/api/feesystem/generate_fee_demand_notes.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                students: selectedStudents.map(s => ({
                    ...s,
                    next_term_fee: s.next_term_fee || 0
                })),
                school_id: schoolId,
                year: year,
                term: term,
                custom_message: customMessage
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            Swal.close();
            document.getElementById('pdfPreview').innerHTML = `<iframe src="${data.pdf_url}" style="width:100%; height:550px; border:none;"></iframe>`;
            document.getElementById('downloadPdfBtn').disabled = false;
            document.getElementById('downloadPdfBtn').onclick = () => {
                window.open(data.pdf_url, '_blank');
            };
        } else {
            throw new Error(data.message);
        }
    } catch (error) {
        Swal.fire('Error', error.message, 'error');
    }
});

// Export to Excel
document.getElementById('exportExcelBtn').addEventListener('click', () => {
    if (!studentsData || studentsData.length === 0) {
        Swal.fire('Warning', 'No data to export', 'warning');
        return;
    }
    
    const exportData = studentsData.map(student => ({
        'Admission Number': student.admission_no,
        'Full Name': student.full_name,
        'Class': student.class_name || 'N/A',
        'Stream': student.stream_name || 'N/A',
        'Expected (KES)': student.expected_amount || 0,
        'Paid (KES)': student.paid_amount || 0,
        'Balance (KES)': student.balance
    }));
    
    const ws = XLSX.utils.json_to_sheet(exportData);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Fee Details');
    XLSX.writeFile(wb, `fee_details_${new Date().toISOString().split('T')[0]}.xlsx`);
});

// Print table
document.getElementById('printTableBtn').addEventListener('click', () => {
    const printWindow = window.open('', '_blank');
    const tableHtml = document.getElementById('balancesTable').cloneNode(true);
    const schoolName = document.querySelector('.font-semibold')?.textContent || 'School';
    
    printWindow.document.write(`
        <html>
            <head>
                <title>Fee Details - ${schoolName}</title>
                <style>
                    body { font-family: Arial, sans-serif; padding: 20px; }
                    h2 { text-align: center; color: #1e3a8a; }
                    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                    th { background-color: #f3f4f6; }
                    .text-right { text-align: right; }
                </style>
            </head>
            <body>
                <h2>${escapeHtml(schoolName)}</h2>
                <h3 style="text-align: center;">Fee Details Report</h3>
                <p>Generated on: ${new Date().toLocaleString()}</p>
                ${tableHtml.outerHTML}
            </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
});

// Balance filter logic
const filterAllCheckbox = document.getElementById('filterAll');
const filterOverdueCheckbox = document.getElementById('filterOverdue');
const filterPrepaidCheckbox = document.getElementById('filterPrepaid');
const filterClearedCheckbox = document.getElementById('filterCleared');

filterAllCheckbox.addEventListener('change', () => {
    if (filterAllCheckbox.checked) {
        filterOverdueCheckbox.checked = false;
        filterPrepaidCheckbox.checked = false;
        filterClearedCheckbox.checked = false;
    }
});

[filterOverdueCheckbox, filterPrepaidCheckbox, filterClearedCheckbox].forEach(cb => {
    cb.addEventListener('change', () => {
        if (cb.checked) filterAllCheckbox.checked = false;
    });
});

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    loadClasses();
});
</script>

<?php include_once('../../includes/footer.php'); ?>