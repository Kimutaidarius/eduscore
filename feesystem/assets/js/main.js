// API Base URL
const API_BASE = '../api/feesystem/';
const schoolId = window.schoolId || null;
const teacherId = window.teacherId || null;

// Initialize theme from localStorage
function initTheme() {
  const savedTheme = localStorage.getItem('eduScoreTheme') || 'light';
  document.body.className = savedTheme;
  
  const sunIcon = document.querySelector('#theme-toggle .fa-sun');
  const moonIcon = document.querySelector('#theme-toggle .fa-moon');
  if (sunIcon && moonIcon) {
    if (savedTheme === 'dark') {
      sunIcon.classList.add('hidden');
      moonIcon.classList.remove('hidden');
    } else {
      sunIcon.classList.remove('hidden');
      moonIcon.classList.add('hidden');
    }
  }
}

// Toggle theme between light and dark
function toggleTheme() {
  if (document.body.classList.contains('dark')) {
    document.body.classList.remove('dark');
    document.body.classList.add('light');
    localStorage.setItem('eduScoreTheme', 'light');
    
    const sunIcon = document.querySelector('#theme-toggle .fa-sun');
    const moonIcon = document.querySelector('#theme-toggle .fa-moon');
    if (sunIcon && moonIcon) {
      sunIcon.classList.remove('hidden');
      moonIcon.classList.add('hidden');
    }
  } else {
    document.body.classList.remove('light');
    document.body.classList.add('dark');
    localStorage.setItem('eduScoreTheme', 'dark');
    
    const sunIcon = document.querySelector('#theme-toggle .fa-sun');
    const moonIcon = document.querySelector('#theme-toggle .fa-moon');
    if (sunIcon && moonIcon) {
      sunIcon.classList.add('hidden');
      moonIcon.classList.remove('hidden');
    }
  }
}

// Toggle sidebar visibility on mobile
function toggleSidebar() {
  const sidebar = document.getElementById('sidebar');
  if (sidebar) {
    sidebar.classList.toggle('sidebar-collapsed');
  }
}

// Show/hide user dropdown menu
function toggleUserDropdown() {
  const dropdown = document.getElementById('user-dropdown');
  if (dropdown) {
    dropdown.classList.toggle('hidden');
  }
}

// Load current term display
async function loadCurrentTerm() {
  try {
    const response = await fetch(`${API_BASE}get_current_term.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ school_id: schoolId })
    });
    const data = await response.json();
    
    if (data.success) {
      const termDisplay = document.getElementById('current-term-display');
      if (termDisplay) {
        termDisplay.textContent = `${data.term_name} - ${data.academic_year}`;
      }
    }
  } catch (error) {
    console.error('Error loading current term:', error);
  }
}

// Modal handling
function openModal(modalId, content) {
  const backdrop = document.getElementById('modal-backdrop');
  if (!backdrop) return;
  
  // Clear previous modal content
  backdrop.innerHTML = '';
  
  // Create modal container
  const modal = document.createElement('div');
  modal.id = modalId;
  modal.className = 'bg-white dark:bg-gray-800 w-full max-w-lg rounded-lg shadow-xl modal modal-enter';
  
  // Set content if provided
  if (content) {
    modal.innerHTML = content;
  }
  
  backdrop.appendChild(modal);
  backdrop.classList.remove('hidden');
  
  setTimeout(() => {
    modal.classList.remove('modal-enter');
    modal.classList.add('modal-visible');
  }, 10);
}

function closeModal() {
  const backdrop = document.getElementById('modal-backdrop');
  const visibleModal = backdrop?.querySelector('.modal');
  
  if (visibleModal) {
    visibleModal.classList.remove('modal-visible');
    visibleModal.classList.add('modal-enter');
    
    setTimeout(() => {
      if (backdrop) {
        backdrop.innerHTML = '';
        backdrop.classList.add('hidden');
      }
    }, 300);
  } else if (backdrop) {
    backdrop.innerHTML = '';
    backdrop.classList.add('hidden');
  }
}

// Helper functions
function capitalize(str) {
  return str.charAt(0).toUpperCase() + str.slice(1);
}

function escapeHtml(text) {
  if (!text) return '';
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

function formatNumber(num) {
  return parseFloat(num).toLocaleString();
}

function formatCurrency(amount) {
  return `KSh ${formatNumber(amount)}`;
}

function formatDate(dateString) {
  if (!dateString) return '';
  const date = new Date(dateString);
  return date.toLocaleDateString();
}

// Show toast notification
function showToast(message, type = 'success') {
  const toast = document.createElement('div');
  toast.className = `fixed bottom-4 right-4 px-6 py-3 rounded-lg shadow-lg text-white z-50 ${
    type === 'success' ? 'bg-green-500' : type === 'error' ? 'bg-red-500' : 'bg-blue-500'
  }`;
  toast.innerHTML = message;
  document.body.appendChild(toast);
  
  setTimeout(() => {
    toast.remove();
  }, 3000);
}

// Show loading spinner
function showLoading(containerId) {
  const container = document.getElementById(containerId);
  if (container) {
    container.innerHTML = '<div class="flex justify-center items-center py-8"><div class="loader"></div></div>';
  }
}

// DataTable initialization
function initDataTable(tableId, options = {}) {
  if ($.fn.DataTable) {
    $(`#${tableId}`).DataTable({
      paging: true,
      searching: true,
      ordering: true,
      responsive: true,
      language: {
        search: "Search:",
        lengthMenu: "Show _MENU_ entries",
        info: "Showing _START_ to _END_ of _TOTAL_ entries",
        paginate: {
          first: "First",
          last: "Last",
          next: "Next",
          previous: "Previous"
        }
      },
      ...options
    });
  }
}

// Document Ready
document.addEventListener('DOMContentLoaded', function() {
  // Initialize theme
  initTheme();
  
  // Theme toggle button
  const themeToggle = document.getElementById('theme-toggle');
  if (themeToggle) {
    themeToggle.addEventListener('click', toggleTheme);
  }
  
  // Mobile sidebar toggle
  const mobileToggle = document.getElementById('mobile-toggle');
  const sidebarToggle = document.getElementById('sidebar-toggle');
  if (mobileToggle) mobileToggle.addEventListener('click', toggleSidebar);
  if (sidebarToggle) sidebarToggle.addEventListener('click', toggleSidebar);
  
  // User dropdown toggle
  const userMenuButton = document.getElementById('user-menu-button');
  if (userMenuButton) {
    userMenuButton.addEventListener('click', toggleUserDropdown);
  }
  
  // Close modal when clicking on backdrop
  const backdrop = document.getElementById('modal-backdrop');
  if (backdrop) {
    backdrop.addEventListener('click', function(e) {
      if (e.target === this) {
        closeModal();
      }
    });
  }
  
  // Close dropdowns when clicking outside
  document.addEventListener('click', function(event) {
    const userMenuContainer = document.getElementById('user-menu-container');
    const userDropdown = document.getElementById('user-dropdown');
    
    if (userMenuContainer && userDropdown && !userMenuContainer.contains(event.target) && !userDropdown.classList.contains('hidden')) {
      userDropdown.classList.add('hidden');
    }
  });
  
  // Load current term
  loadCurrentTerm();
});