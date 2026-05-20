<?php
session_start();
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('Location: ../../login.php');
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Fee Structure Management - EduScore</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --primary-blue: #1A73E8; --secondary-blue: #1976D2; --dark-blue: #0D47A1;
            --light-blue: #E8F0FE; --success-green: #10b981; --warning-orange: #f59e0b;
            --error-red: #ef4444; --text-dark: #1f2937; --text-light: #6b7280;
            --bg-light: #f9fafb; --bg-white: #ffffff; --border-color: #e5e7eb;
            --shadow-sm: 0 1px 2px 0 rgba(0,0,0,0.05); --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1); --border-radius: 12px;
            --transition: all 0.3s ease;
        }
        body { background: #f3f4f6; font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; line-height: 1.5; overflow-x: hidden; }
        .main-content { flex: 1; padding: 1rem; max-width: 100%; overflow-x: hidden; }
        @media (min-width: 640px) { .main-content { padding: 1.25rem; } }
        @media (min-width: 768px) { .main-content { padding: 1.5rem; } }
        @media (min-width: 1024px) { .main-content { padding: 2rem; } }
        .tab-button { transition: var(--transition); }
        .tab-button.active { border-bottom: 2px solid #4f46e5; color: #4f46e5; }
        .tab-panel { animation: fadeIn 0.3s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
        .data-table td { padding: 0.75rem 1rem; border-bottom: 1px solid #f1f5f9; font-size: 0.875rem; }
        .data-table tr:hover { background: #f8fafc; }
        .form-control { padding: 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.875rem; width: 100%; }
        .form-control:focus { outline: none; border-color: #4f46e5; box-shadow: 0 0 0 2px rgba(79,70,229,0.1); }
        .btn-primary { background: linear-gradient(135deg, #4f46e5, #4338ca); color: white; padding: 0.5rem 1rem; border-radius: 0.5rem; border: none; cursor: pointer; transition: var(--transition); }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: var(--shadow-md); }
        .btn-secondary { background: #f3f4f6; color: #374151; padding: 0.5rem 1rem; border-radius: 0.5rem; border: 1px solid #e5e7eb; cursor: pointer; }
        .term-input, .budget-input { width: 100px; padding: 0.4rem; border: 1px solid #d1d5db; border-radius: 0.375rem; text-align: right; transition: all 0.2s; }
        .term-input.saving, .budget-input.saving { background-color: #fef3c7; border-color: #f59e0b; }
        .term-input.saved, .budget-input.saved { background-color: #d1fae5; border-color: #10b981; }
        .total-cell { font-weight: 600; color: #059669; }
        .spinner { display: inline-block; width: 1rem; height: 1rem; border: 2px solid #e2e8f0; border-top-color: #4f46e5; border-radius: 50%; animation: spin 0.8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; z-index: 1000; opacity: 0; visibility: hidden; transition: var(--transition); padding: 1rem; }
        .modal-overlay.active { opacity: 1; visibility: visible; }
        .modal-container { background: white; border-radius: 1rem; width: 100%; max-width: 550px; max-height: 90vh; overflow-y: auto; transform: scale(0.95); transition: transform 0.3s ease; }
        .modal-overlay.active .modal-container { transform: scale(1); }
        .auto-save-indicator { position: fixed; bottom: 20px; right: 20px; background: #10b981; color: white; padding: 8px 16px; border-radius: 8px; font-size: 0.875rem; display: none; align-items: center; gap: 8px; z-index: 1000; box-shadow: 0 4px 12px rgba(0,0,0,0.1); animation: slideIn 0.3s ease; }
        .auto-save-indicator.show { display: flex; }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        @media (max-width: 768px) { .data-table th, .data-table td { padding: 0.5rem; font-size: 0.75rem; } .term-input, .budget-input { width: 70px !important; font-size: 0.7rem !important; } }
        .checkbox-group { border: 1px solid var(--border-color); border-radius: 0.5rem; padding: 0.75rem; max-height: 200px; overflow-y: auto; }
        .checkbox-item { display: flex; align-items: center; padding: 0.5rem; border-bottom: 1px solid var(--border-color); }
        .checkbox-item:last-child { border-bottom: none; }
        .checkbox-item input { margin-right: 0.75rem; width: 18px; height: 18px; }
        .checkbox-item label { flex: 1; cursor: pointer; font-size: 0.875rem; }
        .checkbox-item:hover { background: var(--bg-light); }
        .academic-level-badge {
            background: #e6f7f5;
            color: #0d9488;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
    </style>
</head>
<body>
    <main class="main-content">
        <div class="bg-white rounded-lg shadow-sm p-4 md:p-6 mb-6">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">
                        <i class="fas fa-coins text-indigo-600 mr-2"></i>Fee Structure Management
                    </h1>
                    <p class="text-gray-500 text-sm mt-1">Manage school fees, budgets, and fee groups</p>
                </div>
                <div class="academic-level-badge">
                    <i class="fas fa-layer-group"></i>
                    <span id="currentAcademicLevel">
                        <?php 
                        if ($academic_level == 'primary') echo 'Primary School (Grades 1-6)';
                        elseif ($academic_level == 'junior_secondary') echo 'Junior Secondary (Grades 7-9)';
                        else echo 'Senior Secondary (Forms 1-4)';
                        ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="flex space-x-2 mb-6 border-b border-gray-200">
            <button id="feeStructureTab" class="tab-button active px-4 md:px-6 py-3 text-sm font-medium text-indigo-600 border-b-2 border-indigo-600"><i class="fas fa-coins mr-2"></i>Fee Structure</button>
            <button id="budgetTab" class="tab-button px-4 md:px-6 py-3 text-sm font-medium text-gray-500 hover:text-gray-700"><i class="fas fa-chart-line mr-2"></i>Budget</button>
            <button id="groupsTab" class="tab-button px-4 md:px-6 py-3 text-sm font-medium text-gray-500 hover:text-gray-700"><i class="fas fa-layer-group mr-2"></i>Groups</button>
        </div>

        <!-- Fee Structure Tab -->
        <div id="feeStructurePanel" class="tab-panel">
            <div class="bg-white rounded-lg shadow-sm p-4 md:p-6 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Academic Year</label><select id="yearFilter" class="form-control"><?php for($y = 2024; $y <= 2030; $y++): ?><option value="<?php echo $y; ?>" <?php echo $y == date('Y') ? 'selected' : ''; ?>><?php echo $y; ?></option><?php endfor; ?></select></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Class</label><select id="classFilter" class="form-control"><option value="">Select Class</option></select></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Clone From</label><select id="cloneFrom" class="form-control"><option value="">Select Class to Clone</option></select></div>
                    <div class="flex items-end"><button id="cloneBtn" class="btn-primary w-full"><i class="fas fa-copy mr-2"></i>Clone</button></div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead><tr><th>Vote Head</th><th>Term 1 (KES)</th><th>Term 2 (KES)</th><th>Term 3 (KES)</th><th>Total (KES)</th></tr></thead>
                        <tbody id="feeTableBody"><tr><td colspan="5" class="text-center py-8"><div class="spinner mx-auto"></div><p class="mt-2">Loading fee structure...</p></div>NonNull</td></tr></tbody>
                        <tfoot class="bg-gray-50 font-bold"><tr><td class="text-right">GRAND TOTAL:NonNull<td id="totalTerm1">KES 0NonNull<td id="totalTerm2">KES 0NonNull<td id="totalTerm3">KES 0NonNull<td id="grandTotal">KES 0NonNull</tr></tfoot>
                    </table>
                </div>
            </div>
            <div class="mt-6 bg-white rounded-lg shadow-sm p-4 md:p-6">
                <h3 class="text-lg font-semibold mb-4"><i class="fas fa-file-pdf text-red-500 mr-2"></i>Fee Structure Preview</h3>
                <div id="pdfPreviewContainer" class="border rounded-lg p-4 min-h-[400px] bg-gray-50 overflow-auto"><div class="text-center text-gray-500 py-8"><i class="fas fa-eye-slash text-4xl mb-2"></i><p>Select class and year to preview</p></div></div>
                <div class="mt-4 flex justify-end gap-3"><button id="printPreviewBtn" class="btn-primary bg-blue-600 hover:bg-blue-700"><i class="fas fa-print mr-2"></i>Print Preview</button><button id="downloadPdfBtn" class="btn-primary bg-red-600 hover:bg-red-700" disabled><i class="fas fa-download mr-2"></i>Download PDF</button></div>
            </div>
        </div>

        <!-- Budget Tab -->
        <div id="budgetPanel" class="tab-panel hidden">
            <div class="bg-white rounded-lg shadow-sm p-4 md:p-6 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Select Class</label><select id="budgetClassFilter" class="form-control"><option value="">Select Class</option></select></div>
                    <div class="flex items-end"><button id="loadBudgetBtn" class="btn-primary w-full"><i class="fas fa-search mr-2"></i>Load Budget</button></div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead><tr><th>Vote Head</th><th><span id="year1Label">2026</span> Budget (KES)</th><th><span id="year2Label">2027</span> Budget (KES)</th><th><span id="year3Label">2028</span> Budget (KES)</th></tr></thead>
                        <tbody id="budgetTableBody"><tr><td colspan="4" class="text-center py-8 text-gray-500">Select a class to load budget</div>NonNull</tr></tbody>
                        <tfoot class="bg-gray-50 font-bold"><tr><td class="text-right">GRAND TOTAL:NonNull<td id="budgetYear1Total">KES 0NonNull<td id="budgetYear2Total">KES 0NonNull<td id="budgetYear3Total">KES 0NonNull</tr></tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- Groups Tab -->
        <div id="groupsPanel" class="tab-panel hidden">
            <div class="bg-white rounded-lg shadow-sm p-4 md:p-6">
                <div class="flex justify-between items-center mb-6"><h3 class="text-lg font-semibold"><i class="fas fa-layer-group text-indigo-500 mr-2"></i>Fee Groups</h3><button id="addGroupBtn" class="btn-primary"><i class="fas fa-plus mr-2"></i>Add Group</button></div>
                <div class="mb-6 p-4 bg-blue-50 rounded-lg">
                    <h4 class="font-semibold mb-3"><i class="fas fa-hand-holding-usd text-blue-600 mr-2"></i>Debit Group to Students</h4>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div><label class="block text-sm font-medium text-gray-700 mb-1">Select Fee Group</label><select id="debitGroupSelect" class="form-control"><option value="">Select Group</option></select></div>
                        <div><label class="block text-sm font-medium text-gray-700 mb-1">Academic Year</label><select id="debitYearSelect" class="form-control"><?php for($y = 2024; $y <= 2030; $y++): ?><option value="<?php echo $y; ?>" <?php echo $y == date('Y') ? 'selected' : ''; ?>><?php echo $y; ?></option><?php endfor; ?></select></div>
                        <div><label class="block text-sm font-medium text-gray-700 mb-1">Term</label><select id="debitTermSelect" class="form-control"><option value="1">Term 1</option><option value="2">Term 2</option><option value="3">Term 3</option></select></div>
                        <div class="flex items-end"><button id="debitGroupBtn" class="btn-primary bg-red-600 hover:bg-red-700 w-full"><i class="fas fa-money-bill-wave mr-2"></i>Debit All Students</button></div>
                    </div>
                </div>
                <div id="groupsList" class="space-y-4"><div class="text-center text-gray-500 py-8"><div class="spinner mx-auto"></div><p>Loading groups...</p></div></div>
            </div>
        </div>

        <!-- Group Modal -->
        <div id="groupModal" class="modal-overlay">
            <div class="modal-container">
                <div class="border-b px-6 py-4 flex justify-between items-center bg-gradient-to-r from-indigo-600 to-indigo-800 text-white rounded-t-lg">
                    <h3 class="text-lg font-semibold" id="groupModalTitle">Add Fee Group</h3>
                    <button id="closeGroupModal" class="text-white hover:text-gray-200"><i class="fas fa-times text-xl"></i></button>
                </div>
                <div class="px-6 py-4">
                    <form id="groupForm">
                        <input type="hidden" id="groupId">
                        <div class="mb-4"><label class="block text-sm font-medium text-gray-700 mb-1">Group Name *</label><input type="text" id="groupName" class="form-control" required></div>
                        <div class="mb-4"><label class="block text-sm font-medium text-gray-700 mb-1">Description</label><textarea id="groupDescription" rows="2" class="form-control"></textarea></div>
                        <div class="mb-4"><label class="block text-sm font-medium text-gray-700 mb-1">Default Amount (KES) <span class="text-xs text-gray-500 ml-1">(Optional - will be used when debiting students)</span></label><input type="number" id="defaultAmount" class="form-control" step="0.01" placeholder="0.00"><p class="text-xs text-gray-500 mt-1">If set, this amount will be used instead of calculating from fee structure</p></div>
                        <div class="mb-4"><label class="block text-sm font-medium text-gray-700 mb-1">Select Vote Heads</label><div id="voteHeadsChecklist" class="checkbox-group"></div></div>
                    </form>
                </div>
                <div class="border-t px-6 py-4 flex justify-end gap-3"><button id="cancelGroupBtn" class="btn-secondary">Cancel</button><button id="saveGroupBtn" class="btn-primary">Save Group</button></div>
            </div>
        </div>

        <div id="autoSaveIndicator" class="auto-save-indicator"><i class="fas fa-save"></i><span>Auto-saving...</span></div>

        <script>
        const schoolId = <?php echo json_encode($school_id); ?>;
        const currentAcademicLevel = <?php echo json_encode($academic_level); ?>;
        let currentFeeData = [], currentBudgetData = [], voteHeads = [], classes = [], saveTimeouts = {};

        function formatCurrency(amount) { return 'KES ' + amount.toLocaleString('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
        function parseNumber(value) { if (!value) return 0; let num = parseFloat(value); return isNaN(num) ? 0 : num; }
        function escapeHtml(text) { if (!text) return ''; const div = document.createElement('div'); div.textContent = text; return div.innerHTML; }
        function showAutoSaveIndicator(message, isError = false) { const indicator = document.getElementById('autoSaveIndicator'); indicator.innerHTML = `<i class="fas fa-${isError ? 'exclamation-triangle' : 'save'}"></i><span>${message}</span>`; indicator.style.background = isError ? '#ef4444' : '#10b981'; indicator.classList.add('show'); setTimeout(() => indicator.classList.remove('show'), 2000); }

        function switchTab(tab) {
            document.querySelectorAll('.tab-button').forEach(btn => { btn.classList.remove('active', 'text-indigo-600', 'border-indigo-600'); btn.classList.add('text-gray-500'); });
            document.getElementById(`${tab}Tab`).classList.add('active', 'text-indigo-600', 'border-indigo-600');
            document.querySelectorAll('.tab-panel').forEach(panel => panel.classList.add('hidden'));
            document.getElementById(`${tab}Panel`).classList.remove('hidden');
            if (tab === 'feeStructure') loadFeeStructures();
            else if (tab === 'budget') loadBudgetData();
            else if (tab === 'groups') loadGroups();
        }

        document.getElementById('feeStructureTab').addEventListener('click', () => switchTab('feeStructure'));
        document.getElementById('budgetTab').addEventListener('click', () => switchTab('budget'));
        document.getElementById('groupsTab').addEventListener('click', () => switchTab('groups'));

        // Load Classes filtered by academic level
        async function loadClasses() {
            try {
                const response = await fetch('../../api/feesystem/get_classes.php', { 
                    method: 'POST', 
                    headers: { 'Content-Type': 'application/json' }, 
                    body: JSON.stringify({ school_id: schoolId, academic_level: currentAcademicLevel }) 
                });
                const data = await response.json();
                if (data.success && data.classes) {
                    classes = data.classes;
                    const options = '<option value="">Select Class</option>' + classes.map(c => `<option value="${c.id}">${escapeHtml(c.class_level)}</option>`).join('');
                    document.getElementById('classFilter').innerHTML = options;
                    document.getElementById('budgetClassFilter').innerHTML = options;
                    document.getElementById('cloneFrom').innerHTML = '<option value="">Select Class to Clone</option>' + classes.map(c => `<option value="${c.id}">${escapeHtml(c.class_level)}</option>`).join('');
                }
            } catch (error) { console.error('Error loading classes:', error); }
        }

        async function loadVoteHeads() {
            try {
                const response = await fetch('../../api/feesystem/get_vote_heads.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ school_id: schoolId, status: 'active' }) });
                const data = await response.json();
                if (data.success && data.vote_heads) {
                    voteHeads = data.vote_heads;
                    document.getElementById('voteHeadsChecklist').innerHTML = voteHeads.map(vh => `<div class="checkbox-item"><input type="checkbox" id="vh_${vh.id}" value="${vh.id}"><label for="vh_${vh.id}">${escapeHtml(vh.name)} <span class="text-gray-400 text-xs">(${escapeHtml(vh.alias)})</span></label></div>`).join('');
                }
            } catch (error) { console.error('Error loading vote heads:', error); }
        }

        async function loadFeeStructures() {
            const classId = document.getElementById('classFilter').value, year = document.getElementById('yearFilter').value;
            if (!classId) { document.getElementById('feeTableBody').innerHTML = '<tr><td colspan="5" class="text-center py-8 text-gray-500">Please select a class</div>NonNull</tr>'; return; }
            document.getElementById('feeTableBody').innerHTML = '<tr><td colspan="5" class="text-center py-8"><div class="spinner mx-auto"></div><p>Loading...</p></div>NonNull</tr>';
            try {
                const response = await fetch('../../api/feesystem/get_fee_structure_data.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ school_id: schoolId, class_id: classId, year: year }) });
                const data = await response.json();
                if (data.success) { currentFeeData = data.data; renderFeeTable(); updatePDFPreview(); }
                else { document.getElementById('feeTableBody').innerHTML = `<tr><td colspan="5" class="text-center py-8 text-red-500">${escapeHtml(data.message)}NonNull</tr>`; }
            } catch (error) { console.error('Error:', error); document.getElementById('feeTableBody').innerHTML = '<tr><td colspan="5" class="text-center py-8 text-red-500">Error loading fee structure</div>NonNull</tr>'; }
        }

        function renderFeeTable() {
            const tbody = document.getElementById('feeTableBody');
            if (!currentFeeData || currentFeeData.length === 0) { tbody.innerHTML = '<tr><td colspan="5" class="text-center py-8 text-gray-500">No vote heads found</div>NonNull</tr>'; updateTotals(); return; }
            tbody.innerHTML = currentFeeData.map(item => `<tr data-id="${item.id}"><td class="font-medium">${escapeHtml(item.vote_head_name)}<br><span class="text-xs text-gray-400">${escapeHtml(item.alias)}</span>NonNull<td><input type="number" class="term-input term1" data-id="${item.id}" data-field="term1" value="${parseNumber(item.term1)}" step="0.01" placeholder="0.00">NonNull<td><input type="number" class="term-input term2" data-id="${item.id}" data-field="term2" value="${parseNumber(item.term2)}" step="0.01" placeholder="0.00">NonNull<td><input type="number" class="term-input term3" data-id="${item.id}" data-field="term3" value="${parseNumber(item.term3)}" step="0.01" placeholder="0.00">NonNull<td class="total-cell" data-total="${item.id}">${formatCurrency(parseNumber(item.term1) + parseNumber(item.term2) + parseNumber(item.term3))}NonNull</tr>`).join('');
            document.querySelectorAll('.term-input').forEach(input => { input.addEventListener('input', function() { const id = parseInt(this.dataset.id), field = this.dataset.field, value = parseNumber(this.value); const item = currentFeeData.find(d => d.id === id); if (item) { item[field] = value; const total = parseNumber(item.term1) + parseNumber(item.term2) + parseNumber(item.term3); const totalCell = document.querySelector(`.total-cell[data-total="${id}"]`); if (totalCell) totalCell.textContent = formatCurrency(total); updateTotals(); } debounceAutoSave(id, field, value); }); });
            updateTotals();
        }

        function updateTotals() { let t1=0,t2=0,t3=0; currentFeeData.forEach(item => { t1 += parseNumber(item.term1); t2 += parseNumber(item.term2); t3 += parseNumber(item.term3); }); document.getElementById('totalTerm1').textContent = formatCurrency(t1); document.getElementById('totalTerm2').textContent = formatCurrency(t2); document.getElementById('totalTerm3').textContent = formatCurrency(t3); document.getElementById('grandTotal').textContent = formatCurrency(t1+t2+t3); }

        async function autoSaveField(itemId, field, value) { const classId = document.getElementById('classFilter').value, year = document.getElementById('yearFilter').value; if (!classId) return; const input = document.querySelector(`.term-input.${field}[data-id="${itemId}"]`); if (input) input.classList.add('saving'); try { const response = await fetch('../../api/feesystem/save_fee_structure_field.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ school_id: schoolId, class_id: classId, year: year, vote_head_id: itemId, field: field, value: value }) }); const result = await response.json(); if (result.success) { if (input) { input.classList.remove('saving'); input.classList.add('saved'); setTimeout(() => input.classList.remove('saved'), 500); } showAutoSaveIndicator('Saved!', false); updatePDFPreview(); } else { if (input) input.classList.remove('saving'); showAutoSaveIndicator('Save failed', true); } } catch (error) { if (input) input.classList.remove('saving'); showAutoSaveIndicator('Save failed', true); } }

        function debounceAutoSave(itemId, field, value, delay = 800) { const key = `${itemId}_${field}`; if (saveTimeouts[key]) clearTimeout(saveTimeouts[key]); saveTimeouts[key] = setTimeout(() => { autoSaveField(itemId, field, value); delete saveTimeouts[key]; }, delay); }

        function updatePDFPreview() { const className = document.getElementById('classFilter').options[document.getElementById('classFilter').selectedIndex]?.text || 'All Classes'; const year = document.getElementById('yearFilter').value; let t1=0,t2=0,t3=0; let html = `<div style="padding:20px"><h2 style="text-align:center">FEE STRUCTURE</h2><p style="text-align:center"><strong>Class:</strong> ${escapeHtml(className)} | <strong>Year:</strong> ${year}</p><table style="width:100%;border-collapse:collapse"><thead><tr style="background:#1e3a8a;color:white"><th style="padding:8px;border:1px solid #ddd">Vote Head</th><th style="padding:8px;border:1px solid #ddd">Term 1</th><th style="padding:8px;border:1px solid #ddd">Term 2</th><th style="padding:8px;border:1px solid #ddd">Term 3</th><th style="padding:8px;border:1px solid #ddd">Total</th></tr></thead><tbody>`; currentFeeData.forEach(item => { const term1 = parseNumber(item.term1), term2 = parseNumber(item.term2), term3 = parseNumber(item.term3); t1+=term1; t2+=term2; t3+=term3; html += `<tr><td style="padding:8px;border:1px solid #ddd">${escapeHtml(item.vote_head_name)}NonNull<td style="padding:8px;border:1px solid #ddd;text-align:right">${term1.toLocaleString()}NonNull<td style="padding:8px;border:1px solid #ddd;text-align:right">${term2.toLocaleString()}NonNull<td style="padding:8px;border:1px solid #ddd;text-align:right">${term3.toLocaleString()}NonNull<td style="padding:8px;border:1px solid #ddd;text-align:right">${(term1+term2+term3).toLocaleString()}NonNull</tr>`; }); html += `<tr style="background:#f3f4f6;font-weight:bold"><td style="padding:8px;border:1px solid #ddd;text-align:right">TOTALNonNull<td style="padding:8px;border:1px solid #ddd;text-align:right">${t1.toLocaleString()}NonNull<td style="padding:8px;border:1px solid #ddd;text-align:right">${t2.toLocaleString()}NonNull<td style="padding:8px;border:1px solid #ddd;text-align:right">${t3.toLocaleString()}NonNull<td style="padding:8px;border:1px solid #ddd;text-align:right">${(t1+t2+t3).toLocaleString()}NonNull</tr></tbody></table><p style="margin-top:20px;text-align:center;font-size:12px">Generated on: ${new Date().toLocaleString()}</p></div>`; document.getElementById('pdfPreviewContainer').innerHTML = html; document.getElementById('downloadPdfBtn').disabled = false; }

        document.getElementById('printPreviewBtn')?.addEventListener('click', () => { const classId = document.getElementById('classFilter').value, year = document.getElementById('yearFilter').value; if (!classId) { Swal.fire('Warning', 'Please select a class first', 'warning'); return; } window.open(`../../api/feesystem/generate_fee_structure_pdf.php?school_id=${schoolId}&class_id=${classId}&year=${year}`, '_blank'); });
        document.getElementById('downloadPdfBtn').addEventListener('click', () => { const classId = document.getElementById('classFilter').value, year = document.getElementById('yearFilter').value; if (!classId) { Swal.fire('Warning', 'Please select a class first', 'warning'); return; } Swal.fire({ title: 'Generating PDF...', allowOutsideClick: false, didOpen: () => Swal.showLoading() }); window.location.href = `../../api/feesystem/generate_fee_structure_pdf.php?school_id=${schoolId}&class_id=${classId}&year=${year}`; setTimeout(() => Swal.close(), 2000); });

        async function loadBudgetData() { const classId = document.getElementById('budgetClassFilter').value; if (!classId) { document.getElementById('budgetTableBody').innerHTML = '<tr><td colspan="4" class="text-center py-8 text-gray-500">Please select a class</div>NonNull</tr>'; return; } document.getElementById('budgetTableBody').innerHTML = '<tr><td colspan="4" class="text-center py-8"><div class="spinner mx-auto"></div><p>Loading budget data...</p></div>NonNull</tr>'; try { const response = await fetch('../../api/feesystem/get_budget_data.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ school_id: schoolId, class_id: classId }) }); const data = await response.json(); if (data.success && data.budget_data) { currentBudgetData = data.budget_data; renderBudgetTable(); } else { document.getElementById('budgetTableBody').innerHTML = `<tr><td colspan="4" class="text-center py-8 text-red-500">${escapeHtml(data.message || 'No budget data found')}</div>NonNull</tr>`; } } catch (error) { console.error('Error loading budget:', error); document.getElementById('budgetTableBody').innerHTML = '<tr><td colspan="4" class="text-center py-8 text-red-500">Error loading budget data</div>NonNull</tr>'; } }

        function renderBudgetTable() { const tbody = document.getElementById('budgetTableBody'); const currentYear = new Date().getFullYear(); document.getElementById('year1Label').textContent = currentYear; document.getElementById('year2Label').textContent = currentYear + 1; document.getElementById('year3Label').textContent = currentYear + 2; if (!currentBudgetData || currentBudgetData.length === 0) { tbody.innerHTML = '<tr><td colspan="4" class="text-center py-8 text-gray-500">No budget data found for this class</div>NonNull</tr>'; updateBudgetTotals(); return; } tbody.innerHTML = currentBudgetData.map(item => `<tr data-id="${item.id}"><td class="font-medium">${escapeHtml(item.vote_head_name)}<br><span class="text-xs text-gray-400">${escapeHtml(item.alias)}</span>NonNull<td><input type="number" class="budget-input budget-year1" data-id="${item.id}" data-year="year1" value="${parseNumber(item.budget_year1)}" step="0.01" placeholder="0.00">NonNull<td><input type="number" class="budget-input budget-year2" data-id="${item.id}" data-year="year2" value="${parseNumber(item.budget_year2)}" step="0.01" placeholder="0.00">NonNull<td><input type="number" class="budget-input budget-year3" data-id="${item.id}" data-year="year3" value="${parseNumber(item.budget_year3)}" step="0.01" placeholder="0.00">NonNull</tr>`).join(''); document.querySelectorAll('.budget-input').forEach(input => { input.addEventListener('input', function() { const id = parseInt(this.dataset.id), year = this.dataset.year, value = parseNumber(this.value); const item = currentBudgetData.find(d => d.id === id); if (item) { if (year === 'year1') item.budget_year1 = value; else if (year === 'year2') item.budget_year2 = value; else if (year === 'year3') item.budget_year3 = value; updateBudgetTotals(); } debounceBudgetSave(id, year, value); }); }); updateBudgetTotals(); }

        function updateBudgetTotals() { let y1=0,y2=0,y3=0; currentBudgetData.forEach(item => { y1 += parseNumber(item.budget_year1); y2 += parseNumber(item.budget_year2); y3 += parseNumber(item.budget_year3); }); document.getElementById('budgetYear1Total').textContent = formatCurrency(y1); document.getElementById('budgetYear2Total').textContent = formatCurrency(y2); document.getElementById('budgetYear3Total').textContent = formatCurrency(y3); }

        async function autoSaveBudgetField(itemId, year, value) { const classId = document.getElementById('budgetClassFilter').value; if (!classId) return; const input = document.querySelector(`.budget-${year}[data-id="${itemId}"]`); if (input) input.classList.add('saving'); try { const response = await fetch('../../api/feesystem/save_budget_field.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ school_id: schoolId, class_id: classId, vote_head_id: itemId, year: year, value: value }) }); const result = await response.json(); if (result.success) { if (input) { input.classList.remove('saving'); input.classList.add('saved'); setTimeout(() => input.classList.remove('saved'), 500); } showAutoSaveIndicator('Budget saved!', false); } else { if (input) input.classList.remove('saving'); showAutoSaveIndicator('Save failed', true); } } catch (error) { if (input) input.classList.remove('saving'); showAutoSaveIndicator('Save failed', true); } }

        function debounceBudgetSave(itemId, year, value, delay = 800) { const key = `budget_${itemId}_${year}`; if (saveTimeouts[key]) clearTimeout(saveTimeouts[key]); saveTimeouts[key] = setTimeout(() => { autoSaveBudgetField(itemId, year, value); delete saveTimeouts[key]; }, delay); }

        document.getElementById('loadBudgetBtn')?.addEventListener('click', loadBudgetData);

        // ========== GROUP FUNCTIONS ==========
        async function loadGroups() {
            try {
                const response = await fetch('../../api/feesystem/get_fee_groups.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ school_id: schoolId }) });
                const data = await response.json();
                if (data.success && data.groups) {
                    const container = document.getElementById('groupsList');
                    const debitSelect = document.getElementById('debitGroupSelect');
                    if (data.groups.length === 0) { container.innerHTML = '<div class="text-center text-gray-500 py-8">No fee groups created yet</div>'; debitSelect.innerHTML = '<option value="">No groups available</option>'; }
                    else {
                        container.innerHTML = data.groups.map(group => `<div class="border rounded-lg p-4 hover:shadow-md transition-shadow"><div class="flex justify-between items-start mb-3"><div class="flex-1"><h4 class="text-lg font-semibold">${escapeHtml(group.name)}</h4><p class="text-sm text-gray-500 mt-1">${escapeHtml(group.description || 'No description')}</p>${group.default_amount > 0 ? `<p class="text-sm text-blue-600 mt-1"><i class="fas fa-money-bill-wave"></i> Default Amount: ${formatCurrency(group.default_amount)}</p>` : ''}</div><div class="flex gap-2 ml-4"><button onclick="viewGroupBalances(${group.id})" class="text-green-600 hover:text-green-800" title="View Balances"><i class="fas fa-chart-line"></i></button><button onclick="editGroup(${group.id})" class="text-blue-600 hover:text-blue-800" title="Edit Group"><i class="fas fa-edit"></i></button><button onclick="deleteGroup(${group.id})" class="text-red-600 hover:text-red-800" title="Delete Group"><i class="fas fa-trash"></i></button></div></div><div class="mt-2"><p class="text-sm text-gray-600"><i class="fas fa-tags mr-1 text-indigo-500"></i> Vote Heads: ${group.vote_heads?.map(vh => vh.name).join(', ') || 'None'}</p><button onclick="debitGroupNow(${group.id})" class="mt-3 text-sm bg-red-100 text-red-700 px-3 py-1 rounded hover:bg-red-200 transition"><i class="fas fa-money-bill-wave mr-1"></i> Debit Students</button></div></div>`).join('');
                        debitSelect.innerHTML = '<option value="">Select Group</option>' + data.groups.map(group => `<option value="${group.id}" data-default-amount="${group.default_amount || 0}">${escapeHtml(group.name)}${group.default_amount > 0 ? ' (Default: ' + formatCurrency(group.default_amount) + ')' : ''}</option>`).join('');
                    }
                } else { console.error('Failed to load groups:', data); document.getElementById('groupsList').innerHTML = '<div class="text-center text-red-500 py-8">Failed to load groups</div>'; }
            } catch (error) { console.error('Error loading groups:', error); document.getElementById('groupsList').innerHTML = '<div class="text-center text-red-500 py-8">Error loading groups: ' + error.message + '</div>'; }
        }

        async function debitGroupNow(groupId) { const year = document.getElementById('debitYearSelect')?.value || new Date().getFullYear(); const term = document.getElementById('debitTermSelect')?.value || 1; const groupSelect = document.getElementById('debitGroupSelect'); const selectedOption = groupSelect?.options[groupSelect.selectedIndex]; const groupName = selectedOption?.text || 'this group'; const defaultAmount = parseFloat(selectedOption?.dataset.defaultAmount || 0); let amountInfo = defaultAmount > 0 ? `<br><br><strong>Note:</strong> Using default amount of ${formatCurrency(defaultAmount)} per student` : `<br><br><strong>Note:</strong> Amount will be calculated from fee structure`; const result = await Swal.fire({ title: 'Confirm Debit', html: `Are you sure you want to debit <strong>ALL students</strong> for group: <strong>${groupName}</strong>?<br>Year: ${year} | Term: ${term}${amountInfo}<br><br>This will add the fee amount to each student's balance.`, icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Yes, Debit All Students!', cancelButtonText: 'Cancel' }); if (result.isConfirmed) { Swal.fire({ title: 'Processing...', text: 'Debiting students, please wait...', allowOutsideClick: false, didOpen: () => Swal.showLoading() }); try { const response = await fetch('../../api/feesystem/debit_students_by_group.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ group_id: groupId, academic_year: year, term: term, school_id: schoolId }) }); const data = await response.json(); if (data.success) { let transactionHtml = '<div class="text-left max-h-96 overflow-y-auto">'; if (data.transactions && data.transactions.length > 0) { transactionHtml += '<table class="min-w-full text-sm"><thead><tr><th>Admission No</th><th>Student Name</th><th>Amount</th></tr></thead><tbody>'; data.transactions.forEach(t => { transactionHtml += `<tr><td class="px-2 py-1">${escapeHtml(t.admission_no)}NonNull<td class="px-2 py-1">${escapeHtml(t.student)}NonNull<td class="px-2 py-1 text-right">${formatCurrency(t.amount)}NonNull</tr>`; }); transactionHtml += '</tbody></table>'; } transactionHtml += '</div>'; Swal.fire({ title: 'Success!', html: `<strong>${data.message}</strong><br><br>${transactionHtml}`, icon: 'success', confirmButtonText: 'OK' }); loadGroups(); } else { Swal.fire('Error', data.message || 'Failed to debit students', 'error'); } } catch (error) { Swal.fire('Error', 'An error occurred while processing: ' + error.message, 'error'); } } }

        async function viewGroupBalances(groupId) { const year = new Date().getFullYear(), term = 1; Swal.fire({ title: 'Student Balances', html: '<div class="text-center"><div class="spinner mx-auto"></div><p>Loading balances...</p></div>', width: '900px', showConfirmButton: false, showCancelButton: true, cancelButtonText: 'Close' }); try { const response = await fetch(`../../api/feesystem/get_student_balances.php?group_id=${groupId}&year=${year}&term=${term}`); if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`); const data = await response.json(); if (data.success && data.students) { if (data.students.length === 0) { Swal.update({ html: '<div class="text-center text-gray-500 py-8">No students found</div>', showConfirmButton: true, confirmButtonText: 'Close' }); return; } let html = `<div class="text-left"><p class="mb-3 font-semibold">Academic Year: ${year} | Term: ${term}</p><div class="overflow-x-auto max-h-96"><table class="min-w-full border-collapse"><thead class="bg-gray-50 sticky top-0"><tr><th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Admission No</th><th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Student Name</th><th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Class</th><th class="px-3 py-2 text-right text-xs font-semibold text-gray-600">Balance (KES)</th></tr></thead><tbody>`; let totalBalance = 0; data.students.forEach(student => { const balance = parseFloat(student.balance || 0); totalBalance += balance; html += `<tr class="border-t hover:bg-gray-50"><td class="px-3 py-2 text-sm">${escapeHtml(student.admission_no || 'N/A')}NonNull<td class="px-3 py-2 text-sm">${escapeHtml(student.student_name || 'N/A')}NonNull<td class="px-3 py-2 text-sm">${escapeHtml(student.class_level || 'N/A')}NonNull<td class="px-3 py-2 text-sm text-right ${balance > 0 ? 'text-red-600 font-semibold' : balance < 0 ? 'text-green-600' : 'text-gray-600'}">${formatCurrency(balance)}NonNull</td>`; }); html += `<tr class="border-t bg-gray-100 font-bold"><td colspan="3" class="px-3 py-2 text-right">TOTAL BALANCE:NonNull<td class="px-3 py-2 text-right ${totalBalance > 0 ? 'text-red-600' : totalBalance < 0 ? 'text-green-600' : 'text-gray-600'}">${formatCurrency(totalBalance)}NonNull</tr></tbody></table></div><p class="text-xs text-gray-500 mt-3 text-center">Showing ${data.students.length} student(s)</p></div>`; Swal.update({ html: html, showConfirmButton: true, confirmButtonText: 'Close', width: '900px' }); } else { Swal.update({ html: `<div class="text-center text-red-500 py-4"><i class="fas fa-exclamation-triangle text-3xl mb-2"></i><p>${escapeHtml(data.message || 'Failed to load balances')}</p></div>`, showConfirmButton: true, confirmButtonText: 'Close' }); } } catch (error) { console.error('Error loading balances:', error); Swal.update({ html: `<div class="text-center text-red-500 py-4"><i class="fas fa-exclamation-triangle text-3xl mb-2"></i><p>Error: ${escapeHtml(error.message)}</p></div>`, showConfirmButton: true, confirmButtonText: 'Close' }); } }

        window.editGroup = async (id) => {
            console.log('Edit group called with ID:', id);
            Swal.fire({ title: 'Loading...', text: 'Fetching group data...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            try {
                const response = await fetch('../../api/feesystem/get_fee_group.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: id, school_id: schoolId }) });
                const data = await response.json();
                Swal.close();
                if (data.success && data.group) {
                    console.log('Group data loaded:', data.group);
                    document.getElementById('groupModalTitle').textContent = 'Edit Fee Group';
                    document.getElementById('groupId').value = data.group.id;
                    document.getElementById('groupName').value = data.group.name;
                    document.getElementById('groupDescription').value = data.group.description || '';
                    document.getElementById('defaultAmount').value = data.group.default_amount || 0;
                    document.querySelectorAll('#voteHeadsChecklist input').forEach(cb => { const voteHeadId = parseInt(cb.value); cb.checked = data.group.vote_head_ids && data.group.vote_head_ids.includes(voteHeadId); });
                    document.getElementById('groupModal').classList.add('active');
                } else { Swal.fire('Error', data.message || 'Failed to load group data', 'error'); }
            } catch (error) { Swal.close(); console.error('Edit group error:', error); Swal.fire('Error', 'Failed to load group data: ' + error.message, 'error'); }
        };

        window.deleteGroup = async (id) => {
            const result = await Swal.fire({ title: 'Are you sure?', text: 'This action cannot be undone!', icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Yes, delete it!' });
            if (result.isConfirmed) {
                Swal.fire({ title: 'Processing...', text: 'Deleting group...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                try {
                    const response = await fetch('../../api/feesystem/delete_fee_group.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: id, school_id: schoolId }) });
                    const data = await response.json();
                    Swal.close();
                    if (data.success) { Swal.fire('Deleted!', 'Group has been deleted.', 'success'); loadGroups(); } 
                    else { Swal.fire('Error', data.message || 'Failed to delete', 'error'); }
                } catch (error) { Swal.fire('Error', 'An error occurred: ' + error.message, 'error'); }
            }
        };

        const groupModal = document.getElementById('groupModal');
        document.getElementById('addGroupBtn').addEventListener('click', () => { 
            document.getElementById('groupModalTitle').textContent = 'Add Fee Group';
            document.getElementById('groupId').value = '';
            document.getElementById('groupName').value = '';
            document.getElementById('groupDescription').value = '';
            document.getElementById('defaultAmount').value = '';
            document.querySelectorAll('#voteHeadsChecklist input').forEach(cb => { cb.checked = false; });
            groupModal.classList.add('active');
        });
        document.getElementById('closeGroupModal').addEventListener('click', () => groupModal.classList.remove('active'));
        document.getElementById('cancelGroupBtn').addEventListener('click', () => groupModal.classList.remove('active'));
        groupModal.addEventListener('click', (e) => { if (e.target === groupModal) groupModal.classList.remove('active'); });

        document.getElementById('saveGroupBtn').addEventListener('click', async () => {
            const id = document.getElementById('groupId').value;
            const name = document.getElementById('groupName').value;
            const description = document.getElementById('groupDescription').value;
            const defaultAmount = document.getElementById('defaultAmount').value;
            const selectedVoteHeads = Array.from(document.querySelectorAll('#voteHeadsChecklist input:checked')).map(cb => cb.value);
            if (!name) { Swal.fire('Error', 'Group name is required', 'error'); return; }
            Swal.fire({ title: 'Processing...', text: id ? 'Updating group...' : 'Creating group...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            const url = id ? '../../api/feesystem/update_fee_group.php' : '../../api/feesystem/create_fee_group.php';
            try {
                const response = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id, school_id: schoolId, name, description, default_amount: defaultAmount || 0, vote_head_ids: selectedVoteHeads }) });
                const data = await response.json();
                Swal.close();
                if (data.success) { Swal.fire('Success', `Group ${id ? 'updated' : 'created'} successfully!`, 'success'); groupModal.classList.remove('active'); loadGroups(); } 
                else { Swal.fire('Error', data.message || 'Operation failed', 'error'); }
            } catch (error) { Swal.close(); Swal.fire('Error', 'An error occurred: ' + error.message, 'error'); }
        });

        document.getElementById('debitGroupBtn')?.addEventListener('click', () => { const groupId = document.getElementById('debitGroupSelect')?.value; if (!groupId) { Swal.fire('Warning', 'Please select a fee group to debit', 'warning'); return; } debitGroupNow(groupId); });

        document.getElementById('cloneBtn').addEventListener('click', async () => { const sourceClassId = document.getElementById('cloneFrom').value, targetClassId = document.getElementById('classFilter').value, year = document.getElementById('yearFilter').value; if (!sourceClassId) { Swal.fire('Warning', 'Please select a class to clone from', 'warning'); return; } if (!targetClassId) { Swal.fire('Warning', 'Please select a target class', 'warning'); return; } const result = await Swal.fire({ title: 'Confirm Clone', text: `Clone fee structure?`, icon: 'question', showCancelButton: true, confirmButtonText: 'Yes, Clone!' }); if (result.isConfirmed) { try { const response = await fetch('../../api/feesystem/clone_fee_structure.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ school_id: schoolId, source_class_id: sourceClassId, target_class_id: targetClassId, year: year }) }); const data = await response.json(); if (data.success) { Swal.fire('Success', 'Fee structure cloned successfully!', 'success'); loadFeeStructures(); } else { Swal.fire('Error', data.message || 'Failed to clone', 'error'); } } catch (error) { Swal.fire('Error', 'An error occurred', 'error'); } } });

        document.getElementById('classFilter').addEventListener('change', () => loadFeeStructures());
        document.getElementById('yearFilter').addEventListener('change', () => loadFeeStructures());

        // Listen for academic level changes from header
        window.addEventListener('storage', function(event) {
            if (event.key === 'academic_level_changed') {
                location.reload();
            }
        });

        document.addEventListener('DOMContentLoaded', () => { loadClasses(); loadVoteHeads(); loadFeeStructures(); loadGroups(); });
        </script>
    </main>
</body>
</html>

<?php include_once('../../includes/footer.php'); ?>