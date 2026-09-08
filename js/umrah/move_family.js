// ============================================================
// Move Family: Transfer a family (and all its members) to another group
// ============================================================

/**
 * Open the move family modal.
 * @param {number} familyId        - the family's family_id
 * @param {string} familyName      - head of family display name
 * @param {number} sourceGroupId   - current group_id
 * @param {string} sourceGroupName - current group name
 */
window.openMoveFamilyModal = function(familyId, familyName, sourceGroupId, sourceGroupName) {
    document.getElementById('moveFamilyId').value = familyId;
    document.getElementById('moveFamilySourceGroupId').value = sourceGroupId;
    document.getElementById('moveFamilyCurrentGroup').textContent = sourceGroupName ? ('#' + sourceGroupName) : ('Group #' + sourceGroupId);
    document.getElementById('moveFamilyName').textContent = familyName || ('Family #' + familyId);
    document.getElementById('moveFamilyInfo').style.display = 'none';
    document.getElementById('moveFamilyConfirmBtn').disabled = true;

    // Reset select
    var groupSelect = document.getElementById('moveFamilyGroupSelect');
    groupSelect.innerHTML = '<option value="">-- Loading groups... --</option>';
    groupSelect.disabled = true;
    document.getElementById('moveFamilyHint').textContent = 'Loading available groups...';

    // Load groups
    fetch('../api/umrah/list_groups.php')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            groupSelect.innerHTML = '<option value="">-- Select Target Group --</option>';

            if (!data.success || !data.groups || data.groups.length === 0) {
                groupSelect.innerHTML = '<option value="">-- No groups available --</option>';
                document.getElementById('moveFamilyHint').textContent = 'No groups found.';
                return;
            }

            var available = 0;
            data.groups.forEach(function(g) {
                // Skip the source group
                if (String(g.group_id) === String(sourceGroupId)) return;

                var opt = document.createElement('option');
                opt.value = g.group_id;
                opt.textContent = (g.group_number ? g.group_number + ' — ' : '') + g.group_name;
                groupSelect.appendChild(opt);
                available++;
            });

            groupSelect.disabled = false;
            document.getElementById('moveFamilyHint').textContent = available + ' groups available. Select the target group.';

            if (available === 0) {
                groupSelect.innerHTML = '<option value="">-- No other groups available --</option>';
                document.getElementById('moveFamilyHint').textContent = 'The family is already the only group.';
            }
        })
        .catch(function() {
            groupSelect.innerHTML = '<option value="">-- Error loading groups --</option>';
            if (typeof showToast === 'function') showToast('error', 'Error loading groups');
        });

    // Show info
    document.getElementById('moveFamilyInfo').innerHTML =
        '<i class="fas fa-info-circle"></i> Moving <strong>' + escapeHtml(familyName) +
        '</strong> and all its members to another group.';
    document.getElementById('moveFamilyInfo').style.display = 'block';

    $('#moveFamilyModal').modal('show');
};

/**
 * Validate selection and enable confirm button.
 */
document.addEventListener('DOMContentLoaded', function() {
    var groupSelect = document.getElementById('moveFamilyGroupSelect');
    if (groupSelect) {
        groupSelect.addEventListener('change', function() {
            var confirmBtn = document.getElementById('moveFamilyConfirmBtn');
            confirmBtn.disabled = !this.value;
        });
    }
});

/**
 * Confirm and execute the move.
 */
window.confirmMoveFamily = function() {
    var familyId = document.getElementById('moveFamilyId').value;
    var targetGroupId = document.getElementById('moveFamilyGroupSelect').value;
    var targetGroupName = document.getElementById('moveFamilyGroupSelect').options[document.getElementById('moveFamilyGroupSelect').selectedIndex].text;

    if (!familyId || !targetGroupId) {
        if (typeof showToast === 'function') showToast('warning', 'Please select a target group');
        return;
    }

    var confirmBtn = document.getElementById('moveFamilyConfirmBtn');
    confirmBtn.disabled = true;
    confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Moving...';

    var formData = new FormData();
    formData.append('family_id', familyId);
    formData.append('target_group_id', targetGroupId);
    formData.append('csrf_token', typeof csrfToken !== 'undefined' ? csrfToken : '');

    fetch('../api/umrah/move_family.php', { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                $('#moveFamilyModal').modal('hide');
                if (typeof showToast === 'function') showToast('success', data.message || 'Family moved successfully');

                // Reload the page after a short delay to update all totals
                setTimeout(function() { window.location.reload(); }, 1200);
            } else {
                if (typeof showToast === 'function') showToast('error', data.message || 'Error moving family');
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = '<i class="fas fa-exchange-alt mr-1"></i>Move Family';
            }
        })
        .catch(function() {
            if (typeof showToast === 'function') showToast('error', 'An error occurred while moving the family');
            confirmBtn.disabled = false;
            confirmBtn.innerHTML = '<i class="fas fa-exchange-alt mr-1"></i>Move Family';
        });
};

/**
 * Helper: escape HTML (reuse from groups.js if available, otherwise local).
 */
if (typeof escapeHtml === 'undefined') {
    window.escapeHtml = function(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    };
}
