<?php
// This is a modal template - include it in the main page
?>
<!-- Create/Edit Role Modal -->
<div class="modal-overlay" id="roleFormModal">
    <div class="modal" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fas fa-plus-circle"></i>
                <span id="roleModalTitle">Create New Role</span>
            </h3>
            <button class="close-modal" id="closeRoleModal">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="roleForm">
            <div class="modal-body">
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label required">Role Name</label>
                    <input type="text" class="form-control" id="roleName" name="role_name" required 
                           placeholder="e.g., Head Teacher, ICT Teacher">
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" id="roleDescription" name="description" rows="3" 
                              placeholder="Describe the responsibilities of this role..."></textarea>
                </div>
                <input type="hidden" id="roleId" name="role_id" value="0">
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-btn modal-btn-secondary" id="cancelRoleBtn">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="modal-btn modal-btn-primary">
                    <i class="fas fa-save"></i> Save Role
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Role form handling
const roleFormModal = document.getElementById('roleFormModal');
const roleForm = document.getElementById('roleForm');
const roleModalTitle = document.getElementById('roleModalTitle');
const roleIdInput = document.getElementById('roleId');
const roleNameInput = document.getElementById('roleName');
const roleDescInput = document.getElementById('roleDescription');

function openCreateRoleModal() {
    roleModalTitle.textContent = 'Create New Role';
    roleIdInput.value = '0';
    roleNameInput.value = '';
    roleDescInput.value = '';
    roleFormModal.classList.add('active');
}

function openEditRoleModal(roleId, roleName, roleDesc) {
    roleModalTitle.textContent = 'Edit Role';
    roleIdInput.value = roleId;
    roleNameInput.value = roleName;
    roleDescInput.value = roleDesc || '';
    roleFormModal.classList.add('active');
}

function closeRoleModal() {
    roleFormModal.classList.remove('active');
}

document.getElementById('closeRoleModal').addEventListener('click', closeRoleModal);
document.getElementById('cancelRoleBtn').addEventListener('click', closeRoleModal);

roleForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const roleId = roleIdInput.value;
    const roleName = roleNameInput.value.trim();
    const description = roleDescInput.value.trim();
    
    if (!roleName) {
        showToast('Role name is required', 'warning');
        return;
    }
    
    const action = roleId === '0' ? 'create_role' : 'update_role';
    const url = `ajax/roles.php?action=${action}`;
    
    const submitBtn = roleForm.querySelector('button[type="submit"]');
    const originalHtml = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    submitBtn.disabled = true;
    
    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ role_id: roleId, role_name: roleName, description: description })
        });
        const result = await response.json();
        
        if (result.success) {
            showToast(result.message, 'success');
            closeRoleModal();
            fetchRoles(); // Refresh roles list
        } else {
            showToast(result.message, 'error');
        }
    } catch (error) {
        showToast('Failed to save role', 'error');
    } finally {
        submitBtn.innerHTML = originalHtml;
        submitBtn.disabled = false;
    }
});
</script>