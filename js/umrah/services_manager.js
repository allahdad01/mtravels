/**
 * Service Master controller (Phase 37) — admin/umrah_catalog.php (Services tab)
 * Renders services and edits the service modal. Persists via
 * save_service.php (CSRF + service_manage).
 */

let svData = { services: [] };

function svT(key) {
    return (window.svcLabels && window.svcLabels[key]) || key;
}
function svEsc(str) {
    return String(str == null ? '' : str).replace(/[&<>"']/g, c => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[c]);
}
function svFmt(n) {
    return Number(n || 0).toLocaleString('en-US', { maximumFractionDigits: 2, minimumFractionDigits: 2 });
}
function svAjax(url, data, method) {
    return fetch(url, {
        method: method || 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: method === 'GET' ? undefined : data instanceof FormData ? data : new URLSearchParams(data)
    }).then(r => r.json().then(d => ({ ok: r.ok, d })));
}
function svToast(type, message) {
    Swal.fire({ icon: type, title: message, toast: true, position: 'top-end', showConfirmButton: false, timer: 2200 });
}
function svSaveForm(fd, url) {
    fd.append('csrf_token', window.csrfToken);
    return svAjax(url, fd, 'POST');
}

// ============================================================== DATA
function loadServices() {
    svAjax('../api/umrah/services/get_services.php', {}, 'GET').then(({ ok, d }) => {
        if (!ok || !d.success) {
            document.getElementById('servicesList').innerHTML = '<tr><td colspan="2" class="text-danger text-center py-3">' + svEsc(d.message || 'Failed to load services.') + '</td></tr>';
            return;
        }
        svData = d;
        renderServices();
        const cnt = document.getElementById('tabServicesCount');
        if (cnt) cnt.textContent = svData.services.length;
    }).catch(() => {
        document.getElementById('servicesList').innerHTML = '<tr><td colspan="2" class="text-danger text-center py-3">Network error.</td></tr>';
    });
}

function renderServices() {
    const el = document.getElementById('servicesList');
    if (!svData.services.length) {
        el.innerHTML = '<tr><td colspan="2" class="text-muted text-center py-3">' + svT('no_services') + '</td></tr>';
        return;
    }
    el.innerHTML = svData.services.map(s => {
        return '<tr>' +
            '<td><div style="font-weight:600;">' + svEsc(s.name) + '</div>' +
                '<div class="text-muted" style="font-size:.75rem;">' + svEsc(s.code || '') + '</div></td>' +
            '<td class="text-center" style="white-space:nowrap;">' +
                '<button class="btn btn-xs btn-outline-primary mr-1" onclick="openServiceForm(' + s.id + ')"><i class="feather icon-edit-2"></i></button>' +
                '<button class="btn btn-xs btn-outline-secondary mr-1" onclick="toggleService(' + s.id + ')"><i class="feather icon-toggle-right"></i></button>' +
                '<button class="btn btn-xs btn-outline-danger" onclick="deleteService(' + s.id + ')"><i class="feather icon-trash-2"></i></button>' +
            '</td></tr>';
    }).join('');
}

// ============================================================== SERVICE FORM
function openServiceForm(id) {
    const s = id ? svData.services.find(x => x.id == id) : null;
    $('#sfId').val(s ? s.id : 0);
    $('#sfName').val(s ? s.name : '');
    $('#sfCode').val(s ? s.code || '' : '');
    $('#sfActive').val(s ? s.is_active : 1);
    $('#sfDescription').val(s ? s.description || '' : '');
    $('#serviceModalTitle').text(s ? svT('edit_service') : svT('add_service'));
    $('#serviceModal').modal('show');
}

$(document).on('submit', '#serviceForm', function (e) {
    e.preventDefault();
    const id = $('#sfId').val() || 0;
    if (!$('#sfName').val().trim()) {
        svToast('warning', 'Name is required.');
        return;
    }
    svToast('info', svT('saving') + '...');
    const fd = new FormData(this);
    svSaveForm(fd, '../api/umrah/services/save_service.php').then(({ ok, d }) => {
        if (!ok || !d.success) {
            Swal.close();
            svToast('error', d.message || svT('save_failed'));
            return;
        }
        Swal.close();
        svToast('success', id ? svT('service_updated') : svT('service_created'));
        $('#serviceModal').modal('hide');
        loadServices();
    }).catch(() => { Swal.close(); svToast('error', svT('save_failed')); });
});

function toggleService(id) {
    const s = svData.services.find(x => x.id == id);
    Swal.fire({
        title: svT('toggle_confirm'),
        html: '"' + svEsc(s.name) + '"',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: svT('save'),
        cancelButtonText: 'Cancel'
    }).then(result => {
        if (!result.isConfirmed) return;
        const fd = new FormData();
        fd.append('entity', 'service');
        fd.append('action', 'toggle');
        fd.append('id', id);
        svSaveForm(fd, '../api/umrah/services/save_service.php').then(({ ok, d }) => {
            if (!ok || !d.success) { svToast('error', d.message || svT('save_failed')); return; }
            svToast('success', svT('status_updated'));
            loadServices();
        }).catch(() => svToast('error', svT('save_failed')));
    });
}

function deleteService(id) {
    const s = svData.services.find(x => x.id == id);
    Swal.fire({
        title: svT('delete'),
        text: (s ? '"' + s.name + '" — ' : '') + svT('confirm_delete_svc'),
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: svT('delete'),
        cancelButtonText: 'Cancel'
    }).then(result => {
        if (!result.isConfirmed) return;
        const fd = new FormData();
        fd.append('entity', 'service');
        fd.append('action', 'delete');
        fd.append('id', id);
        svSaveForm(fd, '../api/umrah/services/save_service.php').then(({ ok, d }) => {
            if (!ok || !d.success) {
                svToast(d.message && d.message.indexOf('deactivate') !== -1 ? 'warning' : 'error', d.message || svT('save_failed'));
                return;
            }
            svToast('success', svT('service_deleted'));
            loadServices();
        }).catch(() => svToast('error', svT('save_failed')));
    });
}

// ============================================================== INIT
$(document).ready(function () {
    if (!document.getElementById('btnAddService')) return; // bundle runs on every umrah page
    $('#btnAddService').on('click', function () { openServiceForm(0); });
    loadServices();
});