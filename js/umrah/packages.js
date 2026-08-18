/**
 * Package Management controller (Phase 36) — admin/umrah_catalog.php (Packages tab)
 * Renders package cards with price-engine totals, manages the package modal
 * and its service-line editor, persists via save_package.php (CSRF-gated).
 */

let pkData = { packages: [], services: [], categories: [], hotels: [], room_types: [] };
let pkLineCounter = 0;

function pkT(key) {
    return (window.pkLabels && window.pkLabels[key]) || key;
}
function pkEsc(str) {
    return String(str == null ? '' : str).replace(/[&<>"']/g, c => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[c]);
}

function buildSvcOptions(currentId) {
    const optgroups = {};
    let html = '<option value=""' + (currentId ? '' : ' selected') + '>' + pkEsc(pkT('select_service')) + '</option>';
    (pkData.services || []).forEach(s => {
        const g = s.category_name || pkT('all_services');
        (optgroups[g] = optgroups[g] || []).push(
            '<option value="' + s.id + '"' + (currentId == s.id ? ' selected' : '') +
            ' data-cat="' + pkEsc(s.category_name || '') + '">' +
            serviceOptionText(s) + '</option>');
    });
    html += Object.keys(optgroups).map(g => '<optgroup label="' + pkEsc(g) + '">' + optgroups[g].join('') + '</optgroup>').join('');
    return html;
}

function buildHotelOptions(currentId) {
    const hotels = pkData.hotels || [];
    return '<option value=""' + (currentId ? '' : ' selected') + '>—</option>' +
        hotels.map(h => '<option value="' + h.id + '"' + (currentId == h.id ? ' selected' : '') + '>' + pkEsc(h.name) + '</option>').join('');
}

function buildRtOptions(currentHotelId, currentId) {
    // Room types are global — not filtered by hotel.
    const rts = pkData.room_types || [];
    return '<option value=""' + (currentId ? '' : ' selected') + '>—</option>' +
        rts.map(r => '<option value="' + r.id + '"' + (currentId == r.id ? ' selected' : '') + '>' + pkEsc(r.name) + '</option>').join('');
}
function pkAjax(url, data, method) {
    return fetch(url, {
        method: method || 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: method === 'GET' ? undefined : data instanceof FormData ? data : new URLSearchParams(data)
    }).then(r => r.json().then(d => ({ ok: r.ok, d })));
}
function pkToast(type, message) {
    Swal.fire({ icon: type, title: message, toast: true, position: 'top-end', showConfirmButton: false, timer: 2200 });
}

// ============================================================== DATA
function loadPackages() {
    pkAjax('../api/umrah/packages/get_packages.php', {}, 'GET').then(({ ok, d }) => {
        if (!ok || !d.success) {
            document.getElementById('packagesGrid').innerHTML = '<div class="col-12 text-danger py-4 text-center">' + pkEsc(d.message || 'Failed to load packages.') + '</div>';
            return;
        }
        pkData = d;
        renderPackages();
        refreshLineRows();
        const cnt = document.getElementById('tabPackagesCount');
        if (cnt) cnt.textContent = pkData.packages.length;
    }).catch(() => {
        document.getElementById('packagesGrid').innerHTML = '<div class="col-12 text-danger py-4 text-center">Network error.</div>';
    });
}

function renderPackages() {
    const grid = document.getElementById('packagesGrid');
    if (!pkData.packages.length) {
        grid.innerHTML = '<div class="col-12"><div class="text-muted py-4 text-center">' + pkT('no_packages') + '</div></div>';
        return;
    }
    grid.innerHTML = pkData.packages.map(p => {
        const lines = (p.lines || []).map(l =>
            '<tr>' +
            '<td>' + pkEsc(l.service_name) + '</td>' +
            '</tr>').join('') || '<tr><td class="text-muted text-center">' + pkT('no_lines') + '</td></tr>';
        return '<div class="col-md-6 mb-4">' +
        '<div class="pkg-card">' +
            '<div class="pkg-card-head">' +
                '<div>' +
                    '<div style="font-weight:700;font-size:.95rem;">' + pkEsc(p.name) + '</div>' +
                    '<div class="pkg-card-code">' + pkEsc(p.code || '') + '</div>' +
                '</div>' +
                '<div class="text-right">' +
                    '<span class="uh-badge ' + (p.status === 'active' ? 'uh-badge--green' : 'uh-badge--slate') + '">' + pkT(p.status) + '</span>' +
                    '<div style="margin-top:8px;">' +
                        '<button class="btn btn-xs btn-outline-primary mr-1" onclick="openPackageForm(' + p.id + ')"><i class="feather icon-edit-2"></i></button>' +
                        '<button class="btn btn-xs btn-outline-secondary mr-1" onclick="togglePackage(' + p.id + ')"><i class="feather icon-toggle-right"></i></button>' +
                        '<button class="btn btn-xs btn-outline-danger" onclick="deletePackage(' + p.id + ')"><i class="feather icon-trash-2"></i></button>' +
                    '</div>' +
                '</div>' +
            '</div>' +
            '<div class="px-3 pb-1" style="font-size:.8rem;">' +
                '<span class="text-muted">' + (p.lines || []).length + ' ' + pkT('services_in_package') + '</span>' +
            '</div>' +
            '<div class="card-body pt-2 px-3 pb-3"><table class="table table-sm table-hover table-bordered mb-0" style="font-size:.82rem;">' +
                '<thead><tr><th>' + pkT('service') + '</th></tr></thead>' +
                '<tbody>' + lines + '</tbody>' +
            '</table></div>' +
        '</div></div>';
    }).join('');
}

// ============================================================== PACKAGE FORM
function openPackageForm(id) {
    const p = pkData.packages.find(x => x.id == id);
    $('#pfId').val(id || 0);
    $('#pfName').val(p ? p.name : '');
    $('#pfCode').val(p ? p.code : '');
    $('#pfStatus').val(p ? p.status : 'active');
    $('#pfDescription').val(p ? p.description || '' : '');
    $('#pkgModalTitle').text(p ? pkT('edit_package') : pkT('add_package'));
    $('#linesEditor').empty();
    pkLineCounter = 0;
    (p ? p.lines : []).forEach(l => {
        addLineRow({ id: l.id, service_id: l.service_id, hotel_id: l.hotel_id, room_type_id: l.room_type_id, quantity: l.quantity, is_required: l.is_required });
    });
    $('#packageModal').modal('show');
}

function serviceOptionText(s) {
    const cat = s.category_name ? ' (' + pkEsc(s.category_name) + ')' : '';
    return pkEsc(s.name) + cat;
}

// Hotel/room pickers only apply to hotel services; hide and clear them
// for everything else (ticket, visa, transport, ziyarat…).
function syncVenue(row) {
    const sel = row.querySelector('.pk-svc');
    const cat = ((sel.selectedOptions[0] || {}).dataset.cat || '').toLowerCase().replace(/[^a-z0-9]/g, '');
    const show = cat === 'hotel';
    row.querySelector('.pk-venue').style.display = show ? '' : 'none';
    if (!show) {
        row.querySelector('.pk-hotel').value = '';
        row.querySelector('.pk-rt').innerHTML = buildRtOptions('', '');
    }
}

function addLineRow(pre = {}) {
    pkLineCounter++;
    const rowId = 'pkLine_' + pkLineCounter;
    const svcHtml = buildSvcOptions(pre.service_id || '');
    const hotelOpts = buildHotelOptions(pre.hotel_id || '');
    const rtOpts = buildRtOptions(pre.hotel_id || '', pre.room_type_id || '');

    const div = document.createElement('div');
    div.className = 'pkg-line-row uh-line-card';
    div.id = rowId;
    div.innerHTML =
        '<div class="uh-line-top">' +
            '<select class="form-control form-control-sm pk-svc">' + svcHtml + '</select>' +
            '<button type="button" class="btn btn-sm btn-outline-danger uh-line-del pk-del" title="' + pkT('delete') + '"><i class="feather icon-x"></i></button>' +
        '</div>' +
        '<div class="row mt-2 pk-venue">' +
            '<div class="col-6"><select class="form-control form-control-sm pk-hotel">' + hotelOpts + '</select></div>' +
            '<div class="col-6"><select class="form-control form-control-sm pk-rt">' + rtOpts + '</select></div>' +
        '</div>';
    if (pre.id) {
        div.setAttribute('data-line-id', pre.id);
    }
    document.getElementById('linesEditor').appendChild(div);
    bindLineRow(div);
    syncVenue(div);
}

function bindLineRow(row) {
    row.querySelector('.pk-svc').addEventListener('change', () => { syncVenue(row); });
    row.querySelector('.pk-hotel').addEventListener('change', e => {
        row.querySelector('.pk-rt').innerHTML = buildRtOptions(e.target.value || '', '');
    });
    row.querySelector('.pk-del').addEventListener('click', () => { row.remove(); });
}

// Rebuild open line rows after async data arrives (covers rows added before loadPackages resolved)
function refreshLineRows() {
    document.querySelectorAll('#linesEditor .pkg-line-row').forEach(r => {
        const svc = r.querySelector('.pk-svc');
        const hotel = r.querySelector('.pk-hotel');
        const rt = r.querySelector('.pk-rt');
        const curSvc = svc.value, curHotel = hotel.value, curRt = rt.value;
        svc.innerHTML = buildSvcOptions(curSvc);
        hotel.innerHTML = buildHotelOptions(curHotel);
        rt.innerHTML = buildRtOptions(curHotel, curRt);
        syncVenue(r);
    });
}

function collectLines() {
    const lines = [];
    document.querySelectorAll('#linesEditor .pkg-line-row').forEach(r => {
        const sel = r.querySelector('.pk-svc');
        if (!sel.value) return;
        lines.push({
            line_id: r.getAttribute('data-line-id') || '',
            service_id: sel.value,
            hotel_id: r.querySelector('.pk-hotel').value || '',
            room_type_id: r.querySelector('.pk-rt').value || '',
            quantity: 1,
            is_required: 1,
        });
    });
    return lines;
}

function saveLines(packageId) {
    const lines = collectLines();
    const calls = lines.map(l => {
        const fd = new FormData();
        fd.append('entity', 'line');
        fd.append('action', 'save');
        fd.append('csrf_token', window.csrfToken);
        fd.append('package_id', packageId);
        fd.append('id', l.line_id);
        fd.append('service_id', l.service_id);
        fd.append('hotel_id', l.hotel_id);
        fd.append('room_type_id', l.room_type_id);
        fd.append('quantity', l.quantity);
        fd.append('is_required', l.is_required);
        return pkAjax('../api/umrah/packages/save_package.php', fd, 'POST');
    });
    // drop lines removed from an existing package
    const existing = new Set((pkData.packages.find(p => p.id == packageId) || { lines: [] }).lines.map(l => l.id));
    const kept = new Set(lines.filter(l => l.line_id).map(l => parseInt(l.line_id, 10)));
    const removed = [...existing].filter(id => !kept.has(id));
    removed.forEach(id => {
        const fd = new FormData();
        fd.append('entity', 'line');
        fd.append('action', 'delete');
        fd.append('csrf_token', window.csrfToken);
        fd.append('id', id);
        calls.push(pkAjax('../api/umrah/packages/save_package.php', fd, 'POST'));
    });
    return Promise.all(calls);
}

// ============================================================== SUBMIT
$(document).on('submit', '#packageForm', function (e) {
    e.preventDefault();
    const id = $('#pfId').val() || 0;
    const fd = new FormData(this);
    fd.append('csrf_token', window.csrfToken);
    if (!fd.get('name') || !fd.get('code')) {
        pkToast('warning', 'Name and code are required.');
        return;
    }
    pkToast('info', pkT('saving') + '...');
    pkAjax('../api/umrah/packages/save_package.php', fd, 'POST').then(({ ok, d }) => {
        if (!ok || !d.success) {
            Swal.close();
            pkToast('error', d.message || pkT('save_failed'));
            return;
        }
        const pkgId = d.id;
        saveLines(pkgId).then(results => {
            const bad = results.find(r => !r.ok || !r.d.success);
            Swal.close();
            pkToast(bad ? 'warning' : 'success', bad ? (bad.d.message || pkT('save_failed')) : (id ? pkT('package_updated') : pkT('package_created')));
            $('#packageModal').modal('hide');
            loadPackages();
        });
    }).catch(() => { Swal.close(); pkToast('error', pkT('save_failed')); });
});

function togglePackage(id) {
    pkToast('info', pkT('saving') + '...');
    const fd = new FormData();
    fd.append('entity', 'package');
    fd.append('action', 'toggle');
    fd.append('csrf_token', window.csrfToken);
    fd.append('id', id);
    pkAjax('../api/umrah/packages/save_package.php', fd, 'POST').then(({ ok, d }) => {
        Swal.close();
        if (!ok || !d.success) { pkToast('error', d.message || pkT('save_failed')); return; }
        pkToast('success', d.message);
        loadPackages();
    }).catch(() => { Swal.close(); pkToast('error', pkT('save_failed')); });
}

function deletePackage(id) {
    const p = pkData.packages.find(x => x.id == id);
    Swal.fire({
        title: pkT('delete'),
        text: (p ? '"' + p.name + '" — ' : '') + pkT('confirm_delete_pkg'),
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: pkT('delete'),
        cancelButtonText: 'Cancel'
    }).then(result => {
        if (!result.isConfirmed) return;
        const fd = new FormData();
        fd.append('entity', 'package');
        fd.append('action', 'delete');
        fd.append('csrf_token', window.csrfToken);
        fd.append('id', id);
        pkAjax('../api/umrah/packages/save_package.php', fd, 'POST').then(({ ok, d }) => {
            if (!ok || !d.success) {
                pkToast(d.message && d.message.indexOf('bookings') !== -1 ? 'warning' : 'error', d.message || pkT('delete_pkg_blocked'));
                if (d.message && d.message.indexOf('bookings') !== -1) { loadPackages(); }
                return;
            }
            pkToast('success', d.message);
            loadPackages();
        }).catch(() => pkToast('error', pkT('save_failed')));
    });
}

// ============================================================== INIT
// Keep the package editor's service list in sync when services are
// added/edited/toggled/deleted on the Services tab (services_manager.js).
$(document).on('uhServicesChanged', function (e) {
    pkAjax('../api/umrah/services/get_services.php', {}, 'GET').then(({ ok, d }) => {
        if (ok && d.success) {
            pkData.services = d.services;
            refreshLineRows();
        }
    });
});

$(document).ready(function () {
    if (!document.getElementById('btnAddPackage')) return; // bundle runs on every umrah page
    $('#btnAddPackage').on('click', function () { openPackageForm(0); });
    $('#btnAddLine').on('click', function () { addLineRow(); });
    loadPackages();
});