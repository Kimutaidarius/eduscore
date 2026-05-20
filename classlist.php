<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include('includes/config.php');
require_once 'includes/session_timeout.php'; 
require_once 'includes/ajax-handler.php';

if (empty($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(403);
    header('Location: login.php');
    exit;
}

// Enhanced security: Validate session variables
if (
    empty($_SESSION['authenticated']) ||
    empty($_SESSION['school_id']) ||
    empty($_SESSION['teacher_id'])
) {
    session_destroy();
    header("Location: login.php");
    exit;
}

$loggedInUserId = $_SESSION['id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EduScore - Modern School Management System</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="icon" type="image/png" href="images/logo.png" />
<link rel="apple-touch-icon" href="images/logo.png">

<!-- CRITICAL CSS FIX - Add these styles BEFORE including header.php -->
<style>
/* ======================
   CRITICAL MOBILE FIXES
====================== */

/* Ensure proper stacking context for mobile */
body {
    margin: 0;
    padding: 0;
    overflow-x: hidden;
    min-height: 100vh;
    position: relative;
}

/* Main wrapper for proper layout */
.main-wrapper {
    display: flex;
    min-height: 100vh;
    position: relative;
    background: #f8fafc;
}

/* Content wrapper adjustment */
.content-wrapper {
    flex: 1;
    transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    margin-left: 240px; /* Default sidebar width */
    width: calc(100% - 240px);
    min-height: 100vh;
    background: #f8fafc;
    position: relative;
}

/* When sidebar is collapsed on desktop */
.sidebar.collapsed ~ .content-wrapper {
    margin-left: 70px;
    width: calc(100% - 70px);
}

/* Mobile adjustments */
@media (max-width: 992px) {
    .content-wrapper {
        margin-left: 0 !important;
        width: 100% !important;
        padding-top: 60px; /* Mobile header height */
    }
    
    /* Container fluid adjustments */
    .container-fluid {
        width: 100%;
        padding-right: 15px;
        padding-left: 15px;
        margin-right: auto;
        margin-left: auto;
    }
    
    /* Page title adjustments */
    .page-title-div {
        margin-top: 10px;
        margin-bottom: 20px;
    }
    
    .title {
        font-size: 1.5rem;
        margin-bottom: 15px;
    }
}

/* Ensure content doesn't get hidden behind header */
.container-fluid {
    padding-top: 20px;
    padding-bottom: 20px;
}

/* Fix for z-index conflicts */
.header, .mobile-header {
    z-index: 1000 !important;
}

.sidebar {
    z-index: 900 !important;
}

.sidebar-overlay {
    z-index: 899 !important;
}

/* Glassmorphic Futuristic Select Inputs */
.futuristic-selects {
  display: flex;
  gap: 20px;
  margin-bottom: 20px;
}

.futuristic-selects label {
  display: block;
  margin-bottom: 5px;
  font-weight: 600;
  color: #333;
}

.futuristic-selects select {
  width: 200px;
  padding: 10px 14px;
  border-radius: 14px;
  border: 1px solid rgba(255,255,255,0.3);
  background: rgba(255,255,255,0.5);
  backdrop-filter: blur(8px);
  color: #333;
  font-weight: 500;
  box-shadow: 0 4px 20px rgba(0,0,0,0.05);
  transition: all 0.3s ease;
}

.futuristic-selects select:focus {
  outline: none;
  border-color: #64b5f6;
  box-shadow: 0 0 10px #64b5f6, 0 4px 20px rgba(0,0,0,0.05);
}

/* Glassmorphic Futuristic Buttons */
.futuristic-buttons {
  display: flex;
  gap: 15px;
  margin-bottom: 20px;
}

.futuristic-buttons button {
  padding: 10px 22px;
  border: none;
  border-radius: 14px;
  font-weight: 600;
  color: #fff;
  cursor: pointer;
  background: rgba(79,158,255,0.7);
  backdrop-filter: blur(8px);
  box-shadow: 0 4px 15px rgba(79,158,255,0.4), 0 6px 20px rgba(0,0,0,0.1);
  position: relative;
  overflow: hidden;
  transition: all 0.3s ease;
}

.futuristic-buttons button::before {
  content: '';
  position: absolute;
  top: -50%;
  left: -50%;
  width: 200%;
  height: 200%;
  background: radial-gradient(circle at center, rgba(255,255,255,0.4), transparent 70%);
  transform: scale(0);
  transition: transform 0.5s ease;
  border-radius: 50%;
}

.futuristic-buttons button:hover::before {
  transform: scale(1);
}

.futuristic-buttons button:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(79,158,255,0.5), 0 10px 25px rgba(0,0,0,0.1);
}

.futuristic-buttons button i {
  margin-right: 8px;
}

/* Glassmorphic Futuristic Table */
.futuristic-table-container {
  overflow-x: auto;
  border-radius: 18px;
  background: rgba(255,255,255,0.6);
  backdrop-filter: blur(12px);
  padding: 20px;
  box-shadow: 0 8px 30px rgba(0,0,0,0.05);
  transition: all 0.3s ease;
}

.futuristic-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0 12px;
  color: #333;
  font-weight: 500;
}

.futuristic-table th, 
.futuristic-table td {
  padding: 14px 16px;
  text-align: left;
}

.futuristic-table thead th {
  background: rgba(100,181,246,0.2);
  color: #333;
  text-transform: uppercase;
  font-size: 0.85em;
  letter-spacing: 0.5px;
  border-bottom: 2px solid #64b5f6;
  border-radius: 10px;
}

.futuristic-table tbody tr {
  background: rgba(255,255,255,0.4);
  border-radius: 14px;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.futuristic-table tbody tr:hover {
  transform: translateY(-3px);
  box-shadow: 0 10px 25px rgba(100,181,246,0.2), 0 10px 20px rgba(0,0,0,0.05);
}

.futuristic-table td {
  border-bottom: none;
}

/* Status Labels with Neon Glow */
.status-active {
  background: rgba(132,239,152,0.2);
  color: #28a745;
  padding: 5px 12px;
  border-radius: 12px;
  font-weight: 600;
  font-size: 0.85em;
  box-shadow: 0 0 8px #28a74550, 0 0 15px #28a74530;
}

.status-inactive {
  background: rgba(255,135,135,0.2);
  color: #dc3545;
  padding: 5px 12px;
  border-radius: 12px;
  font-weight: 600;
  font-size: 0.85em;
  box-shadow: 0 0 8px #dc354550, 0 0 15px #dc354530;
}

/* Glassmorphic Pagination */
.pagination {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 15px;
}

.pagination button {
  padding: 6px 14px;
  border-radius: 12px;
  border: none;
  background: rgba(255,255,255,0.5);
  backdrop-filter: blur(6px);
  color: #333;
  cursor: pointer;
  font-weight: 600;
  box-shadow: 0 4px 12px rgba(0,0,0,0.05);
  transition: all 0.3s ease;
}

.pagination button.active {
  background: rgba(79,158,255,0.6);
  color: #fff;
  box-shadow: 0 0 15px #64b5f6, 0 4px 12px rgba(0,0,0,0.05);
}

.pagination button:hover:not(:disabled) {
  background: rgba(100,181,246,0.6);
  color: #fff;
  box-shadow: 0 0 12px #64b5f6, 0 4px 12px rgba(0,0,0,0.05);
}

.pagination button:disabled {
  background: rgba(240,243,247,0.5);
  cursor: not-allowed;
}

/* Responsive tweaks */
@media (max-width: 768px) {
  .futuristic-selects {
    flex-direction: column;
    gap: 15px;
  }
  .futuristic-buttons {
    flex-direction: column;
  }
  
  .futuristic-table th,
  .futuristic-table td {
    padding: 10px 12px;
    font-size: 0.9em;
  }
  
  .pagination {
    justify-content: center;
  }
}

@media (max-width: 576px) {
  .futuristic-selects select {
    width: 100%;
  }
  
  .futuristic-buttons button {
    width: 100%;
    justify-content: center;
  }
}
</style>

</head>
<body>
    <?php 
    // Include the trial banner if not activated
    if (!isset($school)) {
        // Fetch school data for the banner
        $stmt = $db->prepare("SELECT * FROM tblschoolinfo WHERE id = :school_id");
        $stmt->bindParam(":school_id", $_SESSION['school_id'], PDO::PARAM_INT);
        $stmt->execute();
        $school = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    include 'trial_banner.php'; 
    ?>
    
    <div class="main-wrapper">
        <?php include('includes/leftbar.php'); ?>

        <div class="content-wrapper">
            <?php include('includes/topbar.php'); ?>
            
            <div class="container-fluid">
                <div class="row page-title-div">
                    <div class="col-md-6">
                        <h2 class="title">Class List</h2>
                    </div>
                </div>
                
                <div class="select-buttons futuristic-selects">
                    <div>
                        <label for="selectClass">Class</label>
                        <select id="selectClass">
                            <option value="">Select</option>
                        </select>
                    </div>
                    <div>
                        <label for="selectStream">Stream</label>
                        <select id="selectStream" disabled>
                            <option value="">Select</option>
                        </select>
                    </div>
                </div>

                <div class="export-print-buttons futuristic-buttons">
                    <button class="export-btn"><i class="fas fa-download"></i> Export</button>
                    <button class="print-btn"><i class="fas fa-print"></i> Print</button>
                </div>

                <div class="table-responsive futuristic-table-container">
                    <table id="studentsTable" class="display futuristic-table">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Name</th>
                                <th>Gender</th>
                                <th>Admn No.</th>
                                <th>Nemis</th>
                                <th>Class</th>
                                <th>Stream</th>
                                <th>Status</th>
                                <th>Guardians</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Rows dynamically populated -->
                        </tbody>
                    </table>
                </div>

                <div class="table-footer">
                    <div id="pagination" class="pagination"></div>
                </div>
            </div>
        </div>
    </div>

<!-- CRITICAL JAVASCRIPT FIXES -->
<script>
document.addEventListener("DOMContentLoaded", () => {
    // Initialize sidebar state based on window width
    function initializeSidebarState() {
        const sidebar = document.querySelector('.sidebar');
        const contentWrapper = document.querySelector('.content-wrapper');
        const isMobile = window.innerWidth <= 992;
        
        if (isMobile) {
            // On mobile, ensure content wrapper has no left margin
            if (contentWrapper) {
                contentWrapper.style.marginLeft = '0';
                contentWrapper.style.width = '100%';
            }
            // Ensure sidebar is initially hidden on mobile
            if (sidebar) {
                sidebar.classList.remove('show');
                sidebar.classList.remove('collapsed');
            }
        } else {
            // On desktop, restore saved sidebar state
            const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            if (sidebar) {
                if (isCollapsed) {
                    sidebar.classList.add('collapsed');
                } else {
                    sidebar.classList.remove('collapsed');
                }
            }
        }
    }
    
    // Call on load and on resize
    initializeSidebarState();
    window.addEventListener('resize', initializeSidebarState);
    
    // Fix for sidebar toggle issue
    // Ensure sidebar toggle functions are accessible globally
    window.toggleSidebar = function() {
        const sidebar = document.querySelector('.sidebar');
        const sidebarOverlay = document.querySelector('.sidebar-overlay');
        const isMobile = window.innerWidth <= 992;
        
        if (!sidebar || !sidebarOverlay) {
            console.error('Sidebar elements not found');
            return;
        }
        
        if (isMobile) {
            // Mobile behavior
            const isShowing = sidebar.classList.contains('show');
            
            if (isShowing) {
                // Close sidebar
                sidebar.classList.remove('show');
                sidebarOverlay.classList.remove('show');
                document.body.style.overflow = '';
            } else {
                // Open sidebar
                sidebar.classList.add('show');
                sidebarOverlay.classList.add('show');
                document.body.style.overflow = 'hidden';
            }
        } else {
            // Desktop behavior - toggle collapsed state
            sidebar.classList.toggle('collapsed');
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
            
            // Update content wrapper margin
            const contentWrapper = document.querySelector('.content-wrapper');
            if (contentWrapper) {
                if (sidebar.classList.contains('collapsed')) {
                    contentWrapper.style.marginLeft = '70px';
                    contentWrapper.style.width = 'calc(100% - 70px)';
                } else {
                    contentWrapper.style.marginLeft = '240px';
                    contentWrapper.style.width = 'calc(100% - 240px)';
                }
            }
        }
    };
    
    // Listen for toggle events from header
    window.addEventListener('toggleSidebarRequest', function() {
        window.toggleSidebar();
    });
    
    // Close sidebar when clicking on overlay
    const sidebarOverlay = document.querySelector('.sidebar-overlay');
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function() {
            window.toggleSidebar();
        });
    }
    
    // Your existing JavaScript code for class list functionality...
    const selectClass = document.getElementById("selectClass");
    const selectStream = document.getElementById("selectStream");
    const studentsTableBody = document.querySelector("#studentsTable tbody");
    const paginationContainer = document.getElementById("pagination");

    let currentPage = 1;
    const rowsPerPage = 10;
    let totalRows = 0;
    let lastClassId = "";
    let lastStreamId = "";

    // --- Fetch classes ---
    async function fetchClasses() {
        selectClass.disabled = true;
        selectClass.innerHTML = '<option>Loading...</option>';
        try {
            const res = await fetch("api_handlers/getClasses.php");
            const data = await res.json();

            selectClass.innerHTML = '<option value="">Select</option>';
            if (data.status === "success" && Array.isArray(data.data.classes)) {
                data.data.classes.forEach(cls => {
                    const opt = document.createElement("option");
                    opt.value = cls.id;
                    opt.textContent = cls.class_level;
                    selectClass.appendChild(opt);
                });
            }
            selectClass.disabled = false;
            selectStream.innerHTML = '<option value="">Select</option>';
            selectStream.disabled = true;
        } catch (err) {
            console.error("Error fetching classes:", err);
            selectClass.innerHTML = '<option value="">Error loading classes</option>';
        }
    }

    // --- Fetch streams for a class ---
    async function fetchStreams(classId) {
        selectStream.disabled = true;
        selectStream.innerHTML = '<option>Loading...</option>';

        if (!classId) {
            selectStream.innerHTML = '<option value="">Select</option>';
            return;
        }

        try {
            const res = await fetch(`api_handlers/getStreamsByClass.php?class_id=${encodeURIComponent(classId)}`);
            const data = await res.json();

            selectStream.innerHTML = '<option value="">Select</option>';

            const streams = Array.isArray(data.streams)
                ? data.streams
                : Array.isArray(data.data?.streams)
                ? data.data.streams
                : [];

            if (streams.length > 0) {
                streams.forEach(stream => {
                    const opt = document.createElement("option");
                    opt.value = stream.id;
                    opt.textContent = stream.name || stream.stream_name || `Stream ${stream.id}`;
                    selectStream.appendChild(opt);
                });
                selectStream.disabled = false;
            } else {
                selectStream.innerHTML = '<option value="">No streams found</option>';
                selectStream.disabled = true;
            }
        } catch (err) {
            console.error("Error fetching streams:", err);
            selectStream.innerHTML = '<option value="">Error loading streams</option>';
            selectStream.disabled = true;
        }
    }

    // --- Render table ---
    function renderTable(rows, offset) {
        studentsTableBody.innerHTML = "";
        if (!rows || rows.length === 0) {
            studentsTableBody.innerHTML = `<tr><td colspan="10" style="text-align:center;">No students found</td></tr>`;
            return;
        }
        rows.forEach((student, idx) => {
            const isActive = student.Status?.toLowerCase() === "active";
            const statusClass = isActive ? "status-active" : "status-inactive";
            const tr = document.createElement("tr");
            tr.innerHTML = `
                <td>${offset + idx + 1}</td>
                <td>${student.Name || ""}</td>
                <td>${student.Gender || ""}</td>
                <td>${student.AdmNo || ""}</td>
                <td>${student.Nemis || ""}</td>
                <td>${student.ClassName || ""}</td>
                <td>${student.StreamName || ""}</td>
                <td><span class="${statusClass}">${student.Status || ""}</span></td>
                <td>${student.GuardiansContact || ""}</td>
            `;
            studentsTableBody.appendChild(tr);
        });
    }

    // --- Render pagination ---
    function renderPagination() {
        paginationContainer.innerHTML = "";
        const totalPages = Math.ceil(totalRows / rowsPerPage);
        if (totalPages <= 1) return;

        // Previous
        const prevBtn = document.createElement("button");
        prevBtn.textContent = "<";
        prevBtn.disabled = currentPage === 1;
        prevBtn.onclick = () => goToPage(currentPage - 1);
        paginationContainer.appendChild(prevBtn);

        // Page numbers
        for (let i = 1; i <= totalPages; i++) {
            const btn = document.createElement("button");
            btn.textContent = i;
            if (i === currentPage) btn.classList.add("active");
            btn.onclick = () => goToPage(i);
            paginationContainer.appendChild(btn);
        }

        // Next
        const nextBtn = document.createElement("button");
        nextBtn.textContent = ">";
        nextBtn.disabled = currentPage === totalPages;
        nextBtn.onclick = () => goToPage(currentPage + 1);
        paginationContainer.appendChild(nextBtn);
    }

    function goToPage(page) {
        currentPage = page;
        fetchStudents(lastClassId, lastStreamId, currentPage);
    }

    // --- Fetch students ---
    async function fetchStudents(classId, streamId = "", page = 1) {
        if (!classId) {
            studentsTableBody.innerHTML = '<tr><td colspan="10" style="text-align:center;">Please select a class</td></tr>';
            paginationContainer.innerHTML = "";
            return;
        }

        lastClassId = classId;
        lastStreamId = streamId || "";
        studentsTableBody.innerHTML = '<tr><td colspan="10" style="text-align:center;">Loading...</td></tr>';

        try {
            const start = (page - 1) * rowsPerPage;
            let url = `api_handlers/getStudents.php?class_id=${encodeURIComponent(classId)}&start=${start}&length=${rowsPerPage}`;
            if (streamId) url += `&stream_id=${encodeURIComponent(streamId)}`;

            const res = await fetch(url);
            if (!res.ok) throw new Error(`HTTP error ${res.status}`);
            const data = await res.json();

            totalRows = Number(data.recordsFiltered ?? data.recordsTotal ?? (Array.isArray(data.data) ? data.data.length : 0));
            const rows = Array.isArray(data.data) ? data.data : [];
            renderTable(rows, start);
            renderPagination();
        } catch (err) {
            console.error("Error fetching students:", err);
            studentsTableBody.innerHTML = '<tr><td colspan="10" style="text-align:center;">Error fetching students</td></tr>';
            paginationContainer.innerHTML = "";
        }
    }

    // --- Events ---
    selectClass.addEventListener("change", async () => {
        await fetchStreams(selectClass.value);
        currentPage = 1;
        fetchStudents(selectClass.value, selectStream.value || "", currentPage);
    });

    selectStream.addEventListener("change", () => {
        currentPage = 1;
        fetchStudents(selectClass.value, selectStream.value || "", currentPage);
    });

    // --- Initialize ---
    fetchClasses();
    
    // Export and Print button functionality
    document.querySelector('.export-btn').addEventListener('click', function() {
        alert('Export functionality would go here');
    });
    
    document.querySelector('.print-btn').addEventListener('click', function() {
        window.print();
    });
});
</script>

</body>
</html>