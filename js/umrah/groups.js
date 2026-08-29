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
    resetEditFamiliesPanel();
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
        html: '<strong>' + groupName + '</strong><br>This will permanently delete all pending families and members inside this group. Only groups with all pending members can be deleted.',
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

// ============================================================
// Inline Family & Member editing within Edit Group modal
// ============================================================

window.toggleEditFamiliesPanel = function() {
    const panel = document.getElementById('editFamiliesPanel');
    const toggle = document.getElementById('editFamiliesToggle');
    if (!panel || !toggle) return;

    if (toggle.checked) {
        panel.style.display = 'block';
        loadFamiliesForGroup();
    } else {
        panel.style.display = 'none';
        resetEditFamiliesPanel();
    }
};

function resetEditFamiliesPanel() {
    const container = document.getElementById('familyListContainer');
    const memberSection = document.getElementById('memberEditSection');
    const memberContainer = document.getElementById('memberEditContainer');
    const selectionSection = document.getElementById('familySelectionSection');
    const toggle = document.getElementById('editFamiliesToggle');
    const loadBtn = document.getElementById('loadMembersBtn');

    if (container) container.innerHTML = '<div class="text-center text-muted py-3" id="familyListLoading"><i class="fas fa-spinner fa-spin"></i> Loading families...</div>';
    if (memberSection) memberSection.style.display = 'none';
    if (memberContainer) memberContainer.innerHTML = '';
    if (selectionSection) selectionSection.style.display = 'block';
    if (toggle) toggle.checked = false;
    if (loadBtn) loadBtn.disabled = true;
}

function loadFamiliesForGroup() {
    const groupId = document.getElementById('editGroupId').value;
    const container = document.getElementById('familyListContainer');
    if (!groupId || !container) return;

    container.innerHTML = '<div class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin"></i> Loading families...</div>';

    fetch('../api/umrah/get_families_by_group.php?group_id=' + groupId)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success || !data.families || data.families.length === 0) {
                container.innerHTML = '<div class="text-center text-muted py-3">No families found in this group</div>';
                return;
            }
            renderFamilyList(data.families);
        })
        .catch(function() {
            container.innerHTML = '<div class="text-center text-danger py-3">Error loading families</div>';
        });
}

function renderFamilyList(families) {
    const container = document.getElementById('familyListContainer');
    if (!container) return;

    let html = '';
    families.forEach(function(f) {
        const price = parseFloat(f.total_price) || 0;
        const paid = parseFloat(f.total_paid) || 0;
        const due = parseFloat(f.total_due) || 0;
        html += '<div class="family-select-card" data-family-id="' + f.family_id + '" onclick="toggleFamilySelection(this, ' + f.family_id + ')">';
        html += '<input type="checkbox" class="family-checkbox" data-family-id="' + f.family_id + '" onclick="event.stopPropagation(); toggleFamilySelection(this.closest(\'.family-select-card\'), ' + f.family_id + ')">';
        html += '<div class="family-info">';
        html += '<div class="family-name">' + escapeHtml(f.head_of_family || 'Unknown') + '</div>';
        html += '<div class="family-meta">';
        html += '<span><i class="fas fa-users mr-1"></i>' + (f.actual_member_count || 0) + ' members</span>';
        html += ' &middot; <span><i class="fas fa-dollar-sign mr-1"></i>' + price.toFixed(2) + '</span>';
        if (f.tazmin) html += ' &middot; <span class="ug-badge ug-badge-' + (f.tazmin === 'Done' ? 'success' : 'warning') + '">' + f.tazmin + '</span>';
        if (f.visa_status) html += ' <span class="ug-badge ug-badge-info">' + f.visa_status + '</span>';
        html += '</div>';
        html += '</div>';
        html += '</div>';
    });
    container.innerHTML = html;
}

window.toggleFamilySelection = function(card, familyId) {
    const checkbox = card.querySelector('.family-checkbox');
    if (checkbox) {
        checkbox.checked = !checkbox.checked;
    }
    if (checkbox.checked) {
        card.classList.add('selected');
    } else {
        card.classList.remove('selected');
    }
    updateLoadButton();
};

window.toggleAllFamilies = function(selectAll) {
    const checkboxes = document.querySelectorAll('.family-checkbox');
    checkboxes.forEach(function(cb) {
        cb.checked = selectAll;
        const card = cb.closest('.family-select-card');
        if (card) {
            if (selectAll) card.classList.add('selected');
            else card.classList.remove('selected');
        }
    });
    updateLoadButton();
};

function updateLoadButton() {
    const selected = document.querySelectorAll('.family-checkbox:checked');
    const btn = document.getElementById('loadMembersBtn');
    const countEl = document.getElementById('selectedFamilyCount');
    if (btn) btn.disabled = selected.length === 0;
    if (countEl) countEl.textContent = '(' + selected.length + ')';
}

window.loadSelectedFamilyMembers = function() {
    const selected = document.querySelectorAll('.family-checkbox:checked');
    if (selected.length === 0) {
        showToast('warning', 'Please select at least one family');
        return;
    }

    const familyIds = [];
    selected.forEach(function(cb) {
        familyIds.push(cb.dataset.familyId);
    });

    const selectionSection = document.getElementById('familySelectionSection');
    const memberSection = document.getElementById('memberEditSection');
    const memberContainer = document.getElementById('memberEditContainer');

    memberContainer.innerHTML = '<div class="text-center py-3"><i class="fas fa-spinner fa-spin"></i> Loading members...</div>';
    selectionSection.style.display = 'none';
    memberSection.style.display = 'block';

    fetch('../api/umrah/get_members_for_edit.php?family_ids=' + familyIds.join(','))
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success || !data.families) {
                memberContainer.innerHTML = '<div class="text-center text-danger py-3">Error loading data</div>';
                return;
            }
            renderFamilyAndMemberEditors(data.families);
        })
        .catch(function() {
            memberContainer.innerHTML = '<div class="text-center text-danger py-3">Error loading data</div>';
        });
};

window.backToFamilySelection = function() {
    document.getElementById('familySelectionSection').style.display = 'block';
    document.getElementById('memberEditSection').style.display = 'none';
    document.getElementById('memberEditContainer').innerHTML = '';
};

function renderFamilyAndMemberEditors(families) {
    const container = document.getElementById('memberEditContainer');
    if (!container) return;

    let html = '';
    families.forEach(function(fam) {
        // Family edit section
        html += '<div class="family-edit-section">';
        html += '<div class="section-title"><i class="fas fa-home mr-1"></i> ' + escapeHtml(fam.head_of_family || 'Family') + '</div>';
        html += '<div class="row">';
        html += '<div class="col-md-3"><div class="form-group"><label>Head of Family</label>';
        html += '<input type="text" class="form-control form-control-sm family-field" data-family-id="' + fam.family_id + '" data-field="head_of_family" value="' + escapeAttr(fam.head_of_family || '') + '"></div></div>';
        html += '<div class="col-md-3"><div class="form-group"><label>Contact</label>';
        html += '<input type="text" class="form-control form-control-sm family-field" data-family-id="' + fam.family_id + '" data-field="contact" value="' + escapeAttr(fam.contact || '') + '"></div></div>';
        html += '<div class="col-md-3"><div class="form-group"><label>Address</label>';
        html += '<input type="text" class="form-control form-control-sm family-field" data-family-id="' + fam.family_id + '" data-field="address" value="' + escapeAttr(fam.address || '') + '"></div></div>';
        html += '<div class="col-md-3"><div class="form-group"><label>Tazmin</label>';
        html += '<select class="form-control form-control-sm family-field" data-family-id="' + fam.family_id + '" data-field="tazmin">';
        html += '<option value="Done"' + (fam.tazmin === 'Done' ? ' selected' : '') + '>Done</option>';
        html += '<option value="Not Done"' + (fam.tazmin === 'Not Done' ? ' selected' : '') + '>Not Done</option>';
        html += '</select></div></div>';
        html += '</div></div>';

        // Members
        if (fam.members && fam.members.length > 0) {
            fam.members.forEach(function(mem) {
                html += renderMemberEditCard(mem);
            });
        } else {
            html += '<div class="alert alert-info">No members in this family</div>';
        }
    });
    container.innerHTML = html;
}

function renderMemberEditCard(mem) {
    let html = '<div class="member-edit-card" data-booking-id="' + mem.booking_id + '">';
    html += '<div class="card-header-edit">';
    html += '<span><i class="fas fa-user mr-1"></i> ' + escapeHtml(mem.name || 'Member') + (mem.name === mem.head_of_family ? ' <span class="ug-badge ug-badge-primary">Head</span>' : '') + '</span>';
    html += '<span class="save-indicator"><i class="fas fa-check"></i> Saved</span>';
    html += '</div>';
    html += '<div class="row">';

    html += '<div class="col-md-4"><div class="form-group"><label>Name</label>';
    html += '<input type="text" class="form-control form-control-sm member-field" data-booking-id="' + mem.booking_id + '" data-field="name" value="' + escapeAttr(mem.name || '') + '"></div></div>';

    html += '<div class="col-md-4"><div class="form-group"><label>Father Name</label>';
    html += '<input type="text" class="form-control form-control-sm member-field" data-booking-id="' + mem.booking_id + '" data-field="fname" value="' + escapeAttr(mem.fname || '') + '"></div></div>';

    html += '<div class="col-md-4"><div class="form-group"><label>Passport</label>';
    html += '<input type="text" class="form-control form-control-sm member-field" data-booking-id="' + mem.booking_id + '" data-field="passport_number" value="' + escapeAttr(mem.passport_number || '') + '"></div></div>';

    html += '<div class="col-md-4"><div class="form-group"><label>DOB</label>';
    html += '<input type="date" class="form-control form-control-sm member-field" data-booking-id="' + mem.booking_id + '" data-field="dob" value="' + escapeAttr(mem.dob || '') + '"></div></div>';

    html += '<div class="col-md-4"><div class="form-group"><label>Room Type</label>';
    html += '<select class="form-control form-control-sm member-field" data-booking-id="' + mem.booking_id + '" data-field="room_type">';
    html += '<option value="">Select</option>';
    ['1 Bed','2 Beds','3 Beds','4 Beds','5 Beds','6 Beds','Shared','Special Room','No Room'].forEach(function(opt) {
        html += '<option value="' + opt + '"' + (mem.room_type === opt ? ' selected' : '') + '>' + opt + '</option>';
    });
    html += '</select></div></div>';

    html += '<div class="col-md-4"><div class="form-group"><label>Duration</label>';
    html += '<select class="form-control form-control-sm member-field" data-booking-id="' + mem.booking_id + '" data-field="duration">';
    html += '<option value="">Select</option>';
    ['5 Days','6 Days','7 Days','8 Days','9 Days','10 Days','11 Days','12 Days','13 Days','14 Days','15 Days','16 Days','17 Days','18 Days','19 Days','20 Days','21 Days','22 Days','23 Days','24 Days','25 Days','26 Days','27 Days','28 Days','29 Days','30 Days','45 Days','60 Days','90 Days'].forEach(function(opt) {
        html += '<option value="' + opt + '"' + (mem.duration === opt ? ' selected' : '') + '>' + opt + '</option>';
    });
    html += '</select></div></div>';

    html += '<div class="col-md-4"><div class="form-group"><label>Sold Price (' + escapeHtml(mem.currency || 'USD') + ')</label>';
    html += '<input type="number" step="0.001" class="form-control form-control-sm member-field" data-booking-id="' + mem.booking_id + '" data-field="sold_price" value="' + escapeAttr(mem.sold_price || '0') + '"></div></div>';

    html += '</div>';
    html += '<div class="text-right mt-1"><button type="button" class="btn btn-sm btn-outline-primary" onclick="saveMemberEdit(' + mem.booking_id + ')"><i class="fas fa-save mr-1"></i> Save</button></div>';
    html += '</div>';
    return html;
}

window.saveMemberEdit = function(bookingId) {
    const card = document.querySelector('.member-edit-card[data-booking-id="' + bookingId + '"]');
    if (!card) return;

    const formData = new FormData();
    formData.append('booking_id', bookingId);

    const fields = card.querySelectorAll('.member-field');
    fields.forEach(function(field) {
        formData.append(field.dataset.field, field.value);
    });

    const btn = card.querySelector('button[onclick*="saveMemberEdit"]');
    const indicator = card.querySelector('.save-indicator');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>'; }

    fetch('../api/umrah/update_member_quick.php', { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-save mr-1"></i> Save'; }
            if (data.success) {
                if (indicator) { indicator.style.display = 'inline'; setTimeout(function() { indicator.style.display = 'none'; }, 2000); }
            } else {
                showToast('error', data.message || 'Error saving');
            }
        })
        .catch(function() {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-save mr-1"></i> Save'; }
            showToast('error', 'Error saving member');
        });
};

window.saveAllEdits = function() {
    // Save all families
    const familyFields = document.querySelectorAll('.family-field');
    const familyIds = new Set();
    familyFields.forEach(function(f) { familyIds.add(f.dataset.familyId); });

    let pending = familyIds.size;
    if (pending === 0) { saveAllMembers(); return; }

    familyIds.forEach(function(familyId) {
        const form = new FormData();
        form.append('family_id', familyId);
        const fields = document.querySelectorAll('.family-field[data-family-id="' + familyId + '"]');
        fields.forEach(function(f) { form.append(f.dataset.field, f.value); });

        fetch('../api/umrah/update_family_quick.php', { method: 'POST', body: form })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                pending--;
                if (pending === 0) saveAllMembers();
            })
            .catch(function() {
                pending--;
                if (pending === 0) saveAllMembers();
            });
    });
};

function saveAllMembers() {
    const memberCards = document.querySelectorAll('.member-edit-card');
    let success = 0;
    let failed = 0;
    const total = memberCards.length;

    if (total === 0) {
        showToast('success', 'All changes saved');
        setTimeout(function() { window.location.reload(); }, 800);
        return;
    }

    const cardsArray = Array.from(memberCards);
    let index = 0;

    function processNext() {
        if (index >= cardsArray.length) {
            const msg = success + ' saved' + (failed ? ', ' + failed + ' failed' : '');
            showToast(failed ? 'warning' : 'success', msg);
            setTimeout(function() { window.location.reload(); }, 1000);
            return;
        }

        const card = cardsArray[index];
        index++;
        const bookingId = card.dataset.bookingId;
        const formData = new FormData();
        formData.append('booking_id', bookingId);

        card.querySelectorAll('.member-field').forEach(function(f) {
            formData.append(f.dataset.field, f.value);
        });

        fetch('../api/umrah/update_member_quick.php', { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) success++;
                else failed++;
                processNext();
            })
            .catch(function() {
                failed++;
                processNext();
            });
    }

    processNext();
}

// Reset panel when modal opens/closes
document.addEventListener('shown.bs.modal', function(e) {
    if (e.target && e.target.id === 'editGroupModal') {
        resetEditFamiliesPanel();
    }
});
document.addEventListener('hidden.bs.modal', function(e) {
    if (e.target && e.target.id === 'editGroupModal') {
        resetEditFamiliesPanel();
    }
});

function escapeHtml(str) {
    if (!str) return '';
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
}

function escapeAttr(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}