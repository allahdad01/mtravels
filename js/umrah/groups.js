// ============================================================
// Umrah Groups: group CRUD + family-form group select population
// Hierarchy: Groups -> Families -> Members
// ============================================================

(function() {
    let groupCache = null;
    let pendingFamilyGroup = null;

    function getGroups() {
        if (groupCache) return Promise.resolve(groupCache);
        return fetch('../api/umrah/list_groups.php')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                groupCache = (data.success && data.groups) ? data.groups : [];
                if (!data.success) groupCache = [];
                return groupCache;
            })
            .catch(function() { groupCache = []; return groupCache; });
    }

    function fillSelect(select, groups, selected) {
        if (!select) return;
        const keep = select.querySelector('option[value=""]');
        select.innerHTML = keep ? keep.outerHTML : '';
        groups.forEach(function(g) {
            const opt = document.createElement('option');
            opt.value = g.group_id;
            opt.textContent = (g.group_number ? g.group_number + ' — ' : '') + g.group_name;
            if (String(selected) === String(g.group_id)) opt.selected = true;
            select.appendChild(opt);
        });
        if (!selected) {
            const blank = select.querySelector('option[value=""]');
            select.value = blank ? '' : (groups[0] ? groups[0].group_id : '');
        }
    }

    function populateAllSelects() {
        const selects = document.querySelectorAll('select[name="group_id"], #group_id, #editGroup');
        if (!selects.length) return;
        getGroups().then(function(groups) {
            selects.forEach(function(select) {
                fillSelect(select, groups, null);
            });
            // Edit-family modal prefill (set by openEditFamilyModal)
            const prefill = document.getElementById('editFamilyGroupId');
            const editGroup = document.getElementById('editGroup');
            if (prefill && editGroup && prefill.value) {
                fillSelect(editGroup, groups, prefill.value);
            }
            // Create-family modal: preselected current group context
            const createSelect = document.getElementById('group_id');
            if (createSelect) {
                fillSelect(createSelect, groups, pendingFamilyGroup);
            }
        });
    }

    // Called from markup before opening #createFamilyModal (group card + / button)
    window.setPendingFamilyGroup = function(groupId) {
        pendingFamilyGroup = groupId;
        const sel = document.getElementById('group_id');
        if (sel) sel.value = String(groupId);
    };

    document.addEventListener('DOMContentLoaded', function() {
        populateAllSelects();

        // Keep the create-family select in sync when the modal opens
        document.addEventListener('show.bs.modal', function(e) {
            if (e.target && e.target.id === 'createFamilyModal') {
                const sel = document.getElementById('group_id');
                if (sel && pendingFamilyGroup) sel.value = String(pendingFamilyGroup);
            }
        });
    });

    // Flush pending selection so the next open isn't stuck on an old group
    document.addEventListener('hidden.bs.modal', function(e) {
        if (e.target && e.target.id === 'createFamilyModal') pendingFamilyGroup = null;
    });
})();

// ---- Create group ----------------------------------------------------------
window.submitCreateGroupForm = function() {
    const form = document.getElementById('createGroupForm');
    if (!form) return false;
    const btn = form.querySelector('button[type="submit"]');
    btn.disabled = true;
    fetch('../api/umrah/create_group.php', { method: 'POST', body: new FormData(form) })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                showToast('success', data.message || 'Group created successfully');
                setTimeout(function() { window.location.reload(); }, 800);
            } else {
                showToast('error', data.message || 'Error creating group');
                btn.disabled = false;
            }
        })
        .catch(function() {
            showToast('error', 'An error occurred');
            btn.disabled = false;
        });
    return false;
};

// ---- Edit group ------------------------------------------------------------
window.openEditGroupModal = function(groupId, groupNumber, groupName) {
    document.getElementById('editGroupId').value = groupId;
    document.getElementById('editGroupNumber').value = groupNumber;
    document.getElementById('editGroupName').value = groupName;
    $('#editGroupModal').modal('show');
};

window.submitEditGroupForm = function() {
    const form = document.getElementById('editGroupForm');
    if (!form) return false;
    const btn = form.querySelector('button[type="submit"]');
    btn.disabled = true;
    fetch('../api/umrah/update_group.php', { method: 'POST', body: new FormData(form) })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                showToast('success', data.message || 'Group updated successfully');
                setTimeout(function() { window.location.reload(); }, 800);
            } else {
                showToast('error', data.message || 'Error updating group');
                btn.disabled = false;
            }
        })
        .catch(function() {
            showToast('error', 'An error occurred');
            btn.disabled = false;
        });
    return false;
};

// ---- Delete group -----------------------------------------------------------
window.deleteGroup = function(groupId, groupName) {
    if (typeof Swal === 'undefined') {
        if (!confirm('Delete group "' + groupName + '"?')) return;
        proceedDeleteGroup(groupId);
        return;
    }
    Swal.fire({
        title: 'Delete group?',
        html: '<strong>' + groupName + '</strong><br>Families inside it will not be deleted.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Delete',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#dc3545'
    }).then(function(result) {
        if (result.isConfirmed) proceedDeleteGroup(groupId);
    });
};

function proceedDeleteGroup(groupId) {
    const data = new URLSearchParams();
    data.append('group_id', groupId);
    data.append('csrf_token', window.csrfToken || '');
    fetch('../api/umrah/delete_group.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: data.toString()
    })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            showToast(res.success ? 'success' : 'error', res.message || 'Delete failed');
            if (res.success) setTimeout(function() { window.location.reload(); }, 800);
        })
        .catch(function() { showToast('error', 'An error occurred'); });
}