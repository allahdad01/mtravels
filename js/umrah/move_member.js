// ============================================================
// Move Member: Transfer a member between families
// ============================================================

/**
 * Open the move member modal.
 * @param {number} bookingId     - the member's booking_id
 * @param {string} memberName    - display name
 * @param {number} sourceFamilyId - current family_id
 * @param {string} sourceFamilyName - current family head of family
 */
window.openMoveMemberModal = function(bookingId, memberName, sourceFamilyId, sourceFamilyName) {
    document.getElementById('moveMemberBookingId').value = bookingId;
    document.getElementById('moveMemberSourceFamilyId').value = sourceFamilyId;
    document.getElementById('moveMemberCurrentFamily').textContent = sourceFamilyName || ('Family #' + sourceFamilyId);
    document.getElementById('moveMemberInfo').style.display = 'none';
    document.getElementById('moveMemberConfirmBtn').disabled = true;

    // Reset selects
    var groupSelect = document.getElementById('moveMemberGroupSelect');
    var familySelect = document.getElementById('moveMemberFamilySelect');
    groupSelect.innerHTML = '<option value="">-- Select Group --</option>';
    familySelect.innerHTML = '<option value="">-- Select a group first --</option>';
    familySelect.disabled = true;
    document.getElementById('moveMemberFamilyHint').textContent = 'Select a group to see available families.';

    // Load groups
    fetch('../api/umrah/list_groups.php')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success && data.groups) {
                data.groups.forEach(function(g) {
                    var opt = document.createElement('option');
                    opt.value = g.group_id;
                    opt.textContent = (g.group_number ? g.group_number + ' - ' : '') + g.group_name;
                    groupSelect.appendChild(opt);
                });
            }
        })
        .catch(function() {
            showToast('error', 'Error loading groups');
        });

    // Show info
    document.getElementById('moveMemberInfo').innerHTML =
        '<i class="fas fa-info-circle"></i> Moving <strong>' + escapeHtml(memberName) +
        '</strong> to another family. Only active/pending members can be moved.';
    document.getElementById('moveMemberInfo').style.display = 'block';

    $('#moveMemberModal').modal('show');
};

/**
 * Load families for the selected group (called when group select changes).
 */
window.moveMemberLoadFamilies = function() {
    var groupId = document.getElementById('moveMemberGroupSelect').value;
    var familySelect = document.getElementById('moveMemberFamilySelect');
    var confirmBtn = document.getElementById('moveMemberConfirmBtn');
    var hint = document.getElementById('moveMemberFamilyHint');
    var sourceFamilyId = document.getElementById('moveMemberSourceFamilyId').value;

    familySelect.innerHTML = '<option value="">-- Loading families... --</option>';
    familySelect.disabled = true;
    confirmBtn.disabled = true;

    if (!groupId) {
        familySelect.innerHTML = '<option value="">-- Select a group first --</option>';
        hint.textContent = 'Select a group to see available families.';
        return;
    }

    fetch('../api/umrah/get_families_by_group.php?group_id=' + groupId)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            familySelect.innerHTML = '<option value="">-- Select Target Family --</option>';

            if (!data.success || !data.families || data.families.length === 0) {
                familySelect.innerHTML = '<option value="">-- No families in this group --</option>';
                hint.textContent = 'No families found in this group.';
                return;
            }

            var available = 0;
            data.families.forEach(function(f) {
                // Skip the source family (same family)
                if (String(f.family_id) === String(sourceFamilyId)) return;

                var opt = document.createElement('option');
                opt.value = f.family_id;
                var count = f.actual_member_count || 0;
                opt.textContent = f.head_of_family + ' (' + count + ' members)';
                familySelect.appendChild(opt);
                available++;
            });

            familySelect.disabled = false;
            hint.textContent = available + ' families available. Select the target family.';

            if (available === 0) {
                familySelect.innerHTML = '<option value="">-- No other families in this group --</option>';
                hint.textContent = 'The member is already the only family in this group.';
            }
        })
        .catch(function() {
            familySelect.innerHTML = '<option value="">-- Error loading families --</option>';
            showToast('error', 'Error loading families');
        });
};

/**
 * Validate selection and enable confirm button.
 */
document.addEventListener('DOMContentLoaded', function() {
    var familySelect = document.getElementById('moveMemberFamilySelect');
    if (familySelect) {
        familySelect.addEventListener('change', function() {
            var confirmBtn = document.getElementById('moveMemberConfirmBtn');
            confirmBtn.disabled = !this.value;
        });
    }
});

/**
 * Confirm and execute the move.
 */
window.confirmMoveMember = function() {
    var bookingId = document.getElementById('moveMemberBookingId').value;
    var targetFamilyId = document.getElementById('moveMemberFamilySelect').value;
    var targetFamilyName = document.getElementById('moveMemberFamilySelect').options[document.getElementById('moveMemberFamilySelect').selectedIndex].text;

    if (!bookingId || !targetFamilyId) {
        showToast('warning', 'Please select a target family');
        return;
    }

    var confirmBtn = document.getElementById('moveMemberConfirmBtn');
    confirmBtn.disabled = true;
    confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Moving...';

    var formData = new FormData();
    formData.append('booking_id', bookingId);
    formData.append('target_family_id', targetFamilyId);
    formData.append('csrf_token', typeof csrfToken !== 'undefined' ? csrfToken : '');

    fetch('../api/umrah/move_member.php', { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                $('#moveMemberModal').modal('hide');
                showToast('success', data.message || 'Member moved successfully');

                // Reload the current family's member list
                if (typeof loadFamilyMembers === 'function' && data.source_family_id) {
                    loadFamilyMembers(data.source_family_id);
                }

                // Reload the page after a short delay to update all totals
                setTimeout(function() { window.location.reload(); }, 1200);
            } else {
                showToast('error', data.message || 'Error moving member');
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = '<i class="fas fa-exchange-alt mr-1"></i>Move Member';
            }
        })
        .catch(function() {
            showToast('error', 'An error occurred while moving the member');
            confirmBtn.disabled = false;
            confirmBtn.innerHTML = '<i class="fas fa-exchange-alt mr-1"></i>Move Member';
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
