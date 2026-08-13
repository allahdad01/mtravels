/**
 * Hotel Management — Phase 24-25
 * admin/umrah_hotels.php controller: overview stats + occupancy,
 * hotel/room-type/room CRUD, contracts with inventory + rates,
 * and the room × date occupancy calendar (A/R/O/B).
 */

let hdData = null;                 // last dashboard payload
let hdDateState = { from: null, to: null };
let hdRoomTypeOptions = [];        // [{id,name,hotel_id}]
let hdHotelOptions = [];           // [{id,name}]
let hdCalendarData = null;         // last calendar payload

function hdEsc(str) {
    return String(str == null ? '' : str).replace(/[&<>"']/g, c => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[c]);
}

function hdT(key) {
    return (window.hotelLabels && window.hotelLabels[key]) || key;
}

function hdMoney(n, cur) {
    return parseFloat(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + hdEsc(cur || 'USD');
}

function hdAjax(url, params, method) {
    method = method || 'GET';
    return $.ajax({
        url: url,
        type: method,
        dataType: 'json',
        data: params,
        headers: { 'X-CSRF-Token': window.csrfToken || '' }
    });
}

// ============================================================== LOAD
function loadHotelDashboard() {
    $('#overviewStats').html('<div class="col-12"><div class="text-muted py-3">' + hdT('loading') + '...</div></div>');
    hdAjax('../api/umrah/hotels/get_hotel_dashboard.php').then(data => {
        if (!data.success) {
            showToast('error', data.message || 'Failed to load');
            return;
        }
        hdData = data;
        hdHotelOptions = data.hotels.map(h => ({ id: h.id, name: h.name }));
        hdRoomTypeOptions = data.room_types.map(r => ({ id: r.id, name: r.name }));
        renderOverviewStats(data);
        renderOverviewOccupancy(data.occupancy);
        renderOverviewStays(data.recent_stays);
        renderHotelsTable(data.hotels);
        renderRoomsTable(data.rooms, data.hotels);
        renderRoomTypesTable(data.room_types);
        renderContractsTable(data.contracts);
        fillHotelFilter('roomsHotelFilter', true);
        fillHotelFilter('calendarHotelFilter', false);
        if (!hdDateState.from) {
            const d = new Date();
            d.setDate(d.getDate() - 3);
            $('#calendarFrom').val(toDateInput(d));
            const d2 = new Date();
            d2.setDate(d2.getDate() + 27);
            $('#calendarTo').val(toDateInput(d2));
            hdDateState = { from: $('#calendarFrom').val(), to: $('#calendarTo').val() };
        }
    }).catch(() => showToast('error', hdT('load_failed')));
}

function toDateInput(d) {
    const p = n => String(n).padStart(2, '0');
    return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate());
}

function fillHotelFilter(selId, withAll) {
    const $sel = $('#' + selId);
    let html = withAll ? '<option value="">' + hdT('all_hotels') + '</option>' : '<option value="">' + hdT('select_hotel') + '</option>';
    html += hdHotelOptions.map(h => '<option value="' + h.id + '">' + hdEsc(h.name) + '</option>').join('');
    $sel.html(html);
}

// ============================================================== OVERVIEW
function renderOverviewStats(d) {
    const s = d.stats || {};
    const occ = d.occupancy || [];
    const reservedToday = occ.reduce((a, o) => a + parseInt(o.reserved || 0, 10), 0);
    const availableToday = occ.reduce((a, o) => a + parseInt(o.available || 0, 10), 0);
    const stats = [
        { label: hdT('total_hotels'), value: s.total_hotels, icon: 'icon-home', color: '#0e7490' },
        { label: hdT('total_rooms'), value: s.total_rooms, icon: 'icon-grid', color: '#7c3aed' },
        { label: hdT('active_contracts'), value: s.active_contracts, icon: 'icon-file-text', color: '#16a34a' },
        { label: hdT('occupied_today'), value: s.stays_today, icon: 'icon-users', color: '#dc2626' },
        { label: hdT('reserved_today'), value: reservedToday, icon: 'icon-calendar', color: '#f59e0b' },
        { label: hdT('available_today'), value: availableToday, icon: 'icon-check-circle', color: '#2563eb' }
    ];
    $('#overviewStats').html(stats.map(s =>
        '<div class="col-6 col-md-4 col-lg-2 mb-2">' +
            '<div class="card text-center" style="border-top: 3px solid ' + s.color + ';">' +
                '<div class="card-body py-3">' +
                    '<i class="feather ' + s.icon + '" style="color: ' + s.color + ';"></i>' +
                    '<div style="font-size: 1.4rem; font-weight: 700; color: ' + s.color + ';">' + s.value + '</div>' +
                    '<div style="font-size: 0.75rem; color: #6b7280;">' + s.label + '</div>' +
                '</div>' +
            '</div>' +
        '</div>'
    ).join(''));
}

function renderOverviewOccupancy(occupancy) {
    const $c = $('#overviewOccupancy');
    if (!occupancy || occupancy.length === 0) {
        $c.html('<div class="text-muted py-3 text-center">' + hdT('no_occupancy_data') + '</div>');
        return;
    }
    const header = '<tr><th>' + hdT('room_type') + '</th><th class="text-center">' + hdT('available') + '</th><th class="text-center">' + hdT('reserved') + '</th><th class="text-center">' + hdT('occupied') + '</th><th class="text-center">' + hdT('total') + '</th></tr>';
    const rows = occupancy.map(o =>
        '<tr>' +
            '<td><strong>' + hdEsc(o.room_type_name) + '</strong> <span class="text-muted" style="font-size: 0.78rem;">· ' + hdEsc(o.hotel_name || '') + '</span></td>' +
            '<td class="text-center"><span class="uh-badge uh-badge--green">' + o.available + '</span></td>' +
            '<td class="text-center"><span class="uh-badge uh-badge--blue">' + o.reserved + '</span></td>' +
            '<td class="text-center"><span class="uh-badge uh-badge--red">' + o.occupied + '</span></td>' +
            '<td class="text-center"><strong>' + o.total + '</strong></td>' +
        '</tr>'
    ).join('');
    $c.html('<div class="table-responsive"><table class="table table-sm table-bordered mb-0"><thead class="thead-light">' + header + '</thead><tbody>' + rows + '</tbody></table></div>');
}

function renderOverviewStays(stays) {
    const $c = $('#overviewStays');
    if (!stays || stays.length === 0) {
        $c.html('<div class="text-muted py-3 text-center">' + hdT('no_stays') + '</div>');
        return;
    }
    const rows = stays.map(s =>
        '<tr>' +
            '<td>' + hdEsc(s.member_name || '—') + ' <span class="text-muted">#' + s.booking_id + '</span></td>' +
            '<td>' + hdEsc(s.hotel_name || '—') + '</td>' +
            '<td>' + hdEsc(s.room_number || '—') + '</td>' +
            '<td>' + hdEsc(s.check_in || '—') + ' → ' + hdEsc(s.check_out || '—') + '</td>' +
            '<td><span class="uh-badge ' + (s.fulfill_status === 'checked_out' ? 'uh-badge--slate' : 'uh-badge--blue') + '">' + hdEsc(s.fulfill_status) + '</span></td>' +
        '</tr>'
    ).join('');
    $c.html('<div class="table-responsive" style="max-height: 320px; overflow-y: auto;"><table class="table table-sm table-bordered mb-0"><thead class="thead-light"><tr><th>' + hdT('member') + '</th><th>' + hdT('hotel') + '</th><th>' + hdT('room') + '</th><th>' + hdT('stay_period') + '</th><th>' + hdT('status') + '</th></tr></thead><tbody>' + rows + '</tbody></table></div>');
}

// ============================================================== HOTELS TABLE
function renderHotelsTable(hotels) {
    const $w = $('#hotelsTableWrap');
    if (!hotels || hotels.length === 0) {
        $w.html('<div class="text-muted py-4 text-center">' + hdT('no_hotels') + '</div>');
        return;
    }
    const rows = hotels.map(h =>
        '<tr>' +
            '<td><strong>' + hdEsc(h.name) + '</strong>' + (h.saudi_name ? ' <span class="text-muted" style="font-size: 0.8rem;">' + hdEsc(h.saudi_name) + '</span>' : '') + '</td>' +
            '<td>' + hdEsc(h.city || '—') + '</td>' +
            '<td>' + (h.star_rating ? '<span class="uh-badge uh-badge--amber">' + h.star_rating + ' ★</span>' : '—') + '</td>' +
            '<td>' + hdEsc(h.supplier_name || '—') + '</td>' +
            '<td>' + h.room_type_count + ' / ' + h.room_count + '</td>' +
            '<td>' + (h.status === 'active' ? '<span class="uh-badge uh-badge--green">' + hdT('active') + '</span>' : '<span class="uh-badge uh-badge--slate">' + hdT('inactive') + '</span>') + '</td>' +
            '<td class="text-right">' + hdRowActions('hotel', h) + '</td>' +
        '</tr>'
    ).join('');
    $w.html(
        '<div class="table-responsive">' +
            '<table class="table table-sm table-bordered table-hover mb-0">' +
                '<thead class="thead-light"><tr>' +
                    '<th>' + hdT('name') + '</th><th>' + hdT('city') + '</th><th>' + hdT('star_rating') + '</th><th>' + hdT('supplier') + '</th><th>' + hdT('room_types') + ' / ' + hdT('rooms') + '</th><th>' + hdT('status') + '</th><th></th>' +
                '</tr></thead>' +
                '<tbody>' + rows + '</tbody>' +
            '</table>' +
        '</div>'
    );
}

function hdRowActions(kind, row) {
    if (window.canManageHotels === false) return '';
    const edit = '<button class="btn btn-xs btn-outline-primary mr-1" onclick="' + (kind === 'hotel' ? 'openHotelForm' : kind === 'room_type' ? 'openRoomTypeForm' : 'openRoomForm') + '(' + row.id + ')"><i class="feather icon-edit-2"></i></button>';
    const toggle = '<button class="btn btn-xs btn-outline-warning mr-1" title="' + hdT('toggle') + '" onclick="toggleMaster(\'' + kind + '\',' + row.id + ')"><i class="feather icon-toggle-right"></i></button>';
    const del = '<button class="btn btn-xs btn-outline-danger" title="' + hdT('delete') + '" onclick="deleteMaster(\'' + kind + '\',' + row.id + ')"><i class="feather icon-trash-2"></i></button>';
    return edit + toggle + del;
}

// ============================================================== ROOM TYPES TABLE (global)
function renderRoomTypesTable(roomTypes) {
    const $w = $('#roomTypesTableWrap');
    if (!roomTypes || roomTypes.length === 0) {
        $w.html('<div class="text-muted py-4 text-center">' + hdT('no_room_types') + '</div>');
        return;
    }
    const rows = roomTypes.map(r =>
        '<tr>' +
            '<td><strong>' + hdEsc(r.name) + '</strong></td>' +
            '<td>' + (r.max_occupancy || '—') + '</td>' +
            '<td>' + hdEsc(r.bed_type || '—') + '</td>' +
            '<td>' + (r.room_count || 0) + '</td>' +
            '<td>' + (r.status === 'active' ? '<span class="uh-badge uh-badge--green">' + hdT('active') + '</span>' : '<span class="uh-badge uh-badge--slate">' + hdT('inactive') + '</span>') + '</td>' +
            '<td class="text-right">' + hdRowActions('room_type', r) + '</td>' +
        '</tr>'
    ).join('');
    $w.html(
        '<div class="table-responsive">' +
            '<table class="table table-sm table-bordered table-hover mb-0">' +
                '<thead class="thead-light"><tr>' +
                    '<th>' + hdT('room_type_name') + '</th><th>' + hdT('max_occupancy') + '</th><th>' + hdT('bed_type') + '</th><th>' + hdT('rooms') + '</th><th>' + hdT('status') + '</th><th></th>' +
                '</tr></thead>' +
                '<tbody>' + rows + '</tbody>' +
            '</table>' +
        '</div>'
    );
}

// ============================================================== ROOMS TABLE (per-hotel)
function renderRoomsTable(rooms, hotels) {
    const $w = $('#roomsTableWrap');
    const filter = $('#roomsHotelFilter').val();
    const hotelName = id => {
        const h = (hotels || []).find(x => x.id == id);
        return h ? h.name : '';
    };
    const rtName = id => {
        const r = (hdData.room_types || []).find(x => x.id == id);
        return r ? r.name : '';
    };

    const rows = (rooms || []).filter(r => !filter || r.hotel_id == filter).map(r =>
        '<tr>' +
            '<td><i class="feather icon-grid mr-1" style="color:#6b7280;"></i><span class="text-muted">' + hdT('room') + '</span></td>' +
            '<td><strong>' + hdEsc(r.room_number) + '</strong>' + (r.floor ? ' <span class="text-muted">· F' + hdEsc(r.floor) + '</span>' : '') + '</td>' +
            '<td>' + hdEsc(rtName(r.room_type_id)) + '</td>' +
            '<td>' + hdEsc(hotelName(r.hotel_id)) + '</td>' +
            '<td>' + (r.status === 'maintenance' ? '<span class="uh-badge uh-badge--amber">' + hdT('maintenance') + '</span>' : r.status === 'active' ? '<span class="uh-badge uh-badge--green">' + hdT('active') + '</span>' : '<span class="uh-badge uh-badge--light">' + hdT('inactive') + '</span>') + '</td>' +
            '<td class="text-right">' + hdRowActions('room', r) + '</td>' +
        '</tr>'
    ).join('');

    if (!rows) {
        $w.html('<div class="text-muted py-4 text-center">' + hdT('no_rooms') + '</div>');
        return;
    }
    $w.html(
        '<div class="table-responsive">' +
            '<table class="table table-sm table-bordered table-hover mb-0">' +
                '<thead class="thead-light"><tr>' +
                    '<th></th><th>' + hdT('room_number') + '</th><th>' + hdT('room_type') + '</th><th>' + hdT('hotel') + '</th><th>' + hdT('status') + '</th><th></th>' +
                '</tr></thead>' +
                '<tbody>' + rows + '</tbody>' +
            '</table>' +
        '</div>'
    );
}

$(document).on('change', '#roomsHotelFilter', () => renderRoomsTable(hdData.rooms, hdData.hotels));

// ============================================================== CONTRACTS TABLE
function renderContractsTable(contracts) {
    const $w = $('#contractsTableWrap');
    if (!contracts || contracts.length === 0) {
        $w.html('<div class="text-muted py-4 text-center">' + hdT('no_contracts') + '</div>');
        return;
    }
    const rows = contracts.map(c => {
        const typeLabel = c.contract_type === 'per_trip'
            ? '<span class="uh-badge uh-badge--slate" title="' + hdT('contract_type_per_trip') + '">' + hdT('per_trip') + '</span>'
            : '<span class="uh-badge uh-badge--blue" title="' + hdT('contract_type_period') + '">' + hdT('period') + '</span>';
        const amountTxt = c.contract_type === 'per_trip' && c.contract_amount
            ? '<div class="small text-muted">' + hdEsc(c.contract_currency || 'USD') + ' ' + Number(c.contract_amount).toLocaleString() + '</div>'
            : '';
        return '<tr>' +
            '<td><strong>' + hdEsc(c.contract_number || '—') + '</strong>' + typeLabel + amountTxt + '</td>' +
            '<td>' + hdEsc(c.hotel_names || '—') + '</td>' +
            '<td>' + hdEsc(c.supplier_name || '—') + '</td>' +
            '<td>' + hdEsc(c.scope || '—') + '</td>' +
            '<td>' + hdEsc(c.valid_from || '—') + ' → ' + hdEsc(c.valid_to || '—') + '</td>' +
            '<td>' + (c.contract_type === 'per_trip' ? '—' : (c.rate_count || 0)) + '</td>' +
            '<td>' + (c.status === 'active' ? '<span class="uh-badge uh-badge--green">' + hdT('active') + '</span>' : c.status === 'expired' ? '<span class="uh-badge uh-badge--amber">' + hdT('expired') + '</span>' : '<span class="uh-badge uh-badge--slate">' + hdT('inactive') + '</span>') + '</td>' +
            '<td class="text-right">' + (window.canManageHotels === false ? '' :
                '<button class="btn btn-xs btn-outline-primary mr-1" onclick="openContractForm(' + c.id + ')"><i class="feather icon-edit-2"></i></button>' +
                '<button class="btn btn-xs btn-outline-danger" onclick="deleteContract(' + c.id + ')"><i class="feather icon-trash-2"></i></button>'
            ) + '</td>' +
        '</tr>';
    }).join('');
    $w.html(
        '<div class="table-responsive">' +
            '<table class="table table-sm table-bordered table-hover mb-0">' +
                '<thead class="thead-light"><tr>' +
                    '<th>' + hdT('contract_number') + '</th><th>' + hdT('hotel') + '</th><th>' + hdT('supplier') + '</th><th>' + hdT('scope') + '</th><th>' + hdT('validity') + '</th><th>' + hdT('rates') + '</th><th>' + hdT('status') + '</th><th></th>' +
                '</tr></thead>' +
                '<tbody>' + rows + '</tbody>' +
            '</table>' +
        '</div>'
    );
}

// ============================================================== HOTEL / ROOM TYPE / ROOM FORMS
function syncHotelFormRequired() {
    $('#hotelForm [required]').prop('disabled', function() {
        return $(this).closest('div[data-entity]').hasClass('d-none');
    });
}
function openHotelForm(id) {
    $('#hotelForm').find('#hotelFormHotelSection').removeClass('d-none');
    $('#hotelForm').find('#hotelFormRoomTypeSection').addClass('d-none');
    $('#hotelForm').find('#hotelFormRoomSection').addClass('d-none');
    syncHotelFormRequired();
    $('#hotelFormTitleText').text(hdT(id ? 'edit_hotel' : 'add_hotel'));
    $('#hfEntity').val('hotel');
    $('#hfId').val(id || 0);

    $('#hfHotelId').val(id || 0);
    const h = (hdData.hotels || []).find(x => x.id == id);
    $('#hfName').val(h ? h.name : '');
    $('#hfSaudiName').val(h ? h.saudi_name || '' : '');
    $('#hfCity').val(h ? (['Makkah', 'Madinah'].indexOf(h.city) !== -1 ? h.city : 'Other') : '');
    $('#hfLocation').val(h ? h.location || '' : '');
    $('#hfStar').val(h ? h.star_rating || '' : '');
    $('#hfContact').val(h ? h.contact || '' : '');
    $('#hfAddress').val(h ? h.address || '' : '');
    $('#hfNotes').val(h ? h.notes || '' : '');
    $('#hfHotelStatus').val(h ? h.status || 'active' : 'active');

    $('#hfSupplier').html('<option value="">' + hdT('none') + '</option>' + (hdData.suppliers || []).map(s =>
        '<option value="' + s.id + '">' + hdEsc(s.name) + '</option>'
    ).join(''));
    $('#hfSupplier').val(h ? h.supplier_id || '' : '');

    $('#hotelFormModal').modal('show');
}

function openRoomTypeForm(id) {
    $('#hotelForm').find('#hotelFormHotelSection').addClass('d-none');
    $('#hotelForm').find('#hotelFormRoomTypeSection').removeClass('d-none');
    $('#hotelForm').find('#hotelFormRoomSection').addClass('d-none');
    syncHotelFormRequired();
    $('#hotelFormTitleText').text(hdT(id ? 'edit_room_type' : 'add_room_type'));
    $('#hfEntity').val('room_type');
    $('#hfId').val(id || 0);

    $('#hfRoomTypeId').val(id || 0);
    const rt = (hdData.room_types || []).find(x => x.id == id);
    $('#hfRtName').val(rt ? rt.name : '');
    $('#hfRtOccupancy').val(rt ? rt.max_occupancy : '');
    $('#hfRtBed').val(rt ? rt.bed_type || '' : '');
    $('#hfRtDescription').val(rt ? rt.description || '' : '');
    $('#hfRtStatus').val(rt ? rt.status || 'active' : 'active');

    $('#hotelFormModal').modal('show');
}

function openRoomForm(id) {
    $('#hotelForm').find('#hotelFormHotelSection').addClass('d-none');
    $('#hotelForm').find('#hotelFormRoomTypeSection').addClass('d-none');
    $('#hotelForm').find('#hotelFormRoomSection').removeClass('d-none');
    syncHotelFormRequired();
    $('#hotelFormTitleText').text(hdT(id ? 'edit_room' : 'add_room'));
    $('#hfEntity').val('room');
    $('#hfId').val(id || 0);

    $('#hfRoomId').val(id || 0);
    const room = (hdData.rooms || []).find(x => x.id == id);
    $('#hfRoomHotel').html('<option value="">' + hdT('select_hotel') + '</option>' + hdHotelOptions.map(h => '<option value="' + h.id + '">' + hdEsc(h.name) + '</option>').join(''));
    $('#hfRoomHotel').val(room ? room.hotel_id : '');
    refreshRoomTypeOptions(room ? room.hotel_id : '', room ? room.room_type_id : '');
    $('#hfRoomNumber').val(room ? room.room_number : '');
    $('#hfRoomFloor').val(room ? room.floor || '' : '');
    $('#hfRoomNotes').val(room ? room.notes || '' : '');
    $('#hfRoomStatus').val(room ? room.status || 'active' : 'active');

    $('#hotelFormModal').modal('show');
}

function refreshRoomTypeOptions(hotelId, selectedId) {
    $('#hfRoomType').html('<option value="">' + hdT('select_room_type') + '</option>' + hdRoomTypeOptions.map(r =>
        '<option value="' + r.id + '">' + hdEsc(r.name) + '</option>'
    ).join(''));
    $('#hfRoomType').val(selectedId || '');
}

$(document).on('change', '#hfRoomHotel', function() { refreshRoomTypeOptions($(this).val(), ''); });

$(document).on('submit', '#hotelForm', function(e) {
    e.preventDefault();
    const entity = $('#hotelForm [name="entity"]').val();
    const data = $(this).serialize();
    showToast('info', hdT('saving') + '...');
    hdAjax('../api/umrah/hotels/save_hotel.php', data, 'POST').then(res => {
        if (!res.success) {
            showToast('error', res.message || 'Save failed');
            return;
        }
        showToast('success', res.message || hdT('saved'));
        $('#hotelFormModal').modal('hide');
        loadHotelDashboard();
    }).catch(() => showToast('error', hdT('save_failed')));
});

function toggleMaster(entity, id) {
    showToast('info', hdT('saving') + '...');
    hdAjax('../api/umrah/hotels/save_hotel.php', { action: 'toggle', entity: entity, id: id }, 'POST').then(res => {
        if (!res.success) { showToast('error', res.message || 'Failed'); return; }
        showToast('success', res.message || hdT('saved'));
        loadHotelDashboard();
    }).catch(() => showToast('error', hdT('save_failed')));
}

function deleteMaster(entity, id) {
    if (!confirm(hdT('confirm_delete'))) return;
    showToast('info', hdT('deleting') + '...');
    hdAjax('../api/umrah/hotels/save_hotel.php', { action: 'delete', entity: entity, id: id }, 'POST').then(res => {
        if (!res.success) { showToast('error', res.message || 'Failed'); return; }
        showToast('success', res.message || hdT('deleted'));
        loadHotelDashboard();
    }).catch(() => showToast('error', hdT('save_failed')));
}

// ============================================================== CONTRACT FORM
function openContractForm(id) {
    const c = (hdData.contracts || []).find(x => x.id == id);

    $('#cfContractId').val(id || 0);
    $('#cfHotels').html((hdData.hotels || []).map(h =>
        '<option value="' + h.id + '">' + hdEsc(h.name) + (h.city ? ' (' + hdEsc(h.city) + ')' : '') + '</option>'
    ).join(''));
    $('#cfHotels').val(c ? (c.hotel_ids || []).map(String) : []);
    $('#cfNumber').val(c ? c.contract_number || '' : '');
    $('#cfSupplier').html('<option value="">' + hdT('none') + '</option>' + (hdData.suppliers || []).map(s =>
        '<option value="' + s.id + '" data-currency="' + hdEsc(s.currency || 'USD') + '">' + hdEsc(s.name) + '</option>'
    ).join(''));
    $('#cfSupplier').val(c ? c.supplier_id || '' : '');
    $('#cfScope').val(c ? c.scope || 'specific_rooms' : 'specific_rooms');
    $('#cfType').val(c ? c.contract_type || 'period' : 'period');
    $('#cfAmount').val(c ? c.contract_amount ?? '' : '');
    $('#cfAmountCurrency').val(c ? c.contract_currency || 'USD' : 'USD');
    $('#cfValidFrom').val(c ? c.valid_from || '' : '');
    $('#cfValidTo').val(c ? c.valid_to || '' : '');
    $('#cfStatus').val(c ? c.status || 'active' : 'active');
    $('#cfPaymentTerms').val(c ? c.payment_terms || '' : '');
    $('#cfNotes').val(c ? c.notes || '' : '');
    toggleContractType();

    // rates table
    renderRatesEditor(c ? c.rates : null);

    // contract hotels -> refresh the hotel select in every rate row
    $(document).off('change.contractHotels').on('change.contractHotels', '#cfHotels', function() {
        $('#cfRatesTable tbody tr').each(function() {
            const $sel = $(this).find('.rate-hotel');
            if (!$sel.length) return;
            const keep = $sel.val();
            $sel.html(contractHotelOptions(keep));
        });
    });

    // currency follows the selected supplier (change event only, not on prefill)
    $(document).off('change.contractSupplier').on('change.contractSupplier', '#cfSupplier', function() {
        if (!$(this).val()) return;
        const cur = ($(this).find(':selected').data('currency') || 'USD');
        $('#cfRatesTable tbody tr .rate-cost-currency').val(cur);
        $('#cfAmountCurrency').val(cur);
    });

    $(document).off('change.contractType').on('change.contractType', '#cfType', toggleContractType);

    $('#contractFormModal').modal('show');
}

function toggleContractType() {
    const perTrip = $('#cfType').val() === 'per_trip';
    $('#cfAmountWrap').toggle(perTrip);
    $('#cfRatesSection').toggle(!perTrip);
    $('#cfTypeHelp').text(perTrip ? hdT('contract_type_per_trip_help') : hdT('contract_type_period_help'));
}

function contractHotelOptions(selectedId) {
    const ids = $('#cfHotels').val() || [];
    const pool = ids.length ? (hdData.hotels || []).filter(h => ids.indexOf(String(h.id)) !== -1) : (hdData.hotels || []);
    return '<option value="">' + hdT('select_hotel') + '</option>' + pool.map(h =>
        '<option value="' + h.id + '"' + (selectedId && String(h.id) === String(selectedId) ? ' selected' : '') + '>' + hdEsc(h.name) + (h.city ? ' (' + hdEsc(h.city) + ')' : '') + '</option>'
    ).join('');
}

function renderRatesEditor(rates) {
    const rows = (rates || []).map((r, i) => rateRowHtml(i, r));
    const html =
        '<div class="table-responsive" style="max-height: 260px; overflow-y: auto;">' +
            '<table class="table table-sm table-bordered bg-white mb-2" id="cfRatesTable">' +
                '<thead class="thead-light"><tr>' +
                    '<th style="min-width:140px;">' + hdT('hotel') + '</th><th>' + hdT('room_type') + '</th><th style="width:150px;">' + hdT('cost_price') + '</th><th style="width:110px;">' + hdT('currency') + '</th><th style="width:40px;"></th>' +
                '</tr></thead>' +
                '<tbody>' + (rows.join('') || '<tr><td colspan="5" class="text-muted text-center">' + hdT('no_rates') + '</td></tr>') + '</tbody>' +
            '</table>' +
        '</div>' +
        '<button type="button" class="btn btn-sm btn-outline-primary" onclick="addContractRateRow()"><i class="feather icon-plus mr-1"></i>' + hdT('add_rate') + '</button>';
    $('#cfRatesWrap').html(html);
}

function rateRowHtml(i, r) {
    const rtOptions = '<option value="">' + hdT('select_room_type') + '</option>' + hdRoomTypeOptions.map(rt =>
        '<option value="' + rt.id + '"' + (r && String(rt.id) === String(r.room_type_id) ? ' selected' : '') + '>' + hdEsc(rt.name) + '</option>'
    ).join('');
    return (
        '<tr>' +
            '<td><select class="form-control form-control-sm rate-hotel">' + contractHotelOptions(r ? r.hotel_id : '') + '</select></td>' +
            '<td><select class="form-control form-control-sm rate-rt">' + rtOptions + '</select></td>' +
            '<td><input type="number" step="0.01" min="0" class="form-control form-control-sm rate-cost" value="' + (r ? r.cost_price : '') + '"></td>' +
            '<td><input type="text" class="form-control form-control-sm rate-cost-currency" value="' + hdEsc(r ? (r.cost_currency || 'USD') : 'USD') + '"></td>' +
            '<td><button type="button" class="btn btn-xs btn-outline-danger" onclick="$(this).closest(\'tr\').remove()"><i class="feather icon-x"></i></button></td>' +
        '</tr>'
    );
}

function addContractRateRow() {
    const $tbody = $('#cfRatesTable tbody');
    if ($tbody.find('tr').length === 1 && $tbody.find('tr td').length === 5 && !$tbody.find('tr .rate-rt').length) {
        $tbody.empty();
    }
    $tbody.append(rateRowHtml($tbody.find('tr').length, null));
    const cur = $('#cfSupplier option:selected').data('currency');
    if (cur) $tbody.find('tr:last .rate-cost-currency').val(cur);
}

$(document).on('submit', '#contractForm', function(e) {
    e.preventDefault();
    const rates = [];
    $('#cfRatesTable tbody tr').each(function() {
        const $tr = $(this);
        if (!$tr.find('.rate-rt').length) return;
        const hotel = $tr.find('.rate-hotel').val();
        const rt = $tr.find('.rate-rt').val();
        const cost = $tr.find('.rate-cost').val();
        if (!hotel || (!rt && !cost)) return;
        rates.push({
            hotel_id: hotel,
            room_type_id: rt,
            cost_price: cost || null,
            cost_currency: $tr.find('.rate-cost-currency').val() || 'USD'
        });
    });
    showToast('info', hdT('saving') + '...');
    hdAjax('../api/umrah/hotels/save_contract.php', {
        action: 'save',
        id: $('#cfContractId').val(),
        hotel_ids: $('#cfHotels').val() || [],
        contract_number: $('#cfNumber').val(),
        supplier_id: $('#cfSupplier').val(),
        scope: $('#cfScope').val(),
        contract_type: $('#cfType').val(),
        contract_amount: $('#cfAmount').val(),
        contract_currency: $('#cfAmountCurrency').val(),
        valid_from: $('#cfValidFrom').val(),
        valid_to: $('#cfValidTo').val(),
        status: $('#cfStatus').val(),
        payment_terms: $('#cfPaymentTerms').val(),
        notes: $('#cfNotes').val(),
        rates: rates
    }, 'POST').then(res => {
        if (!res.success) {
            showToast('error', res.message || 'Save failed');
            return;
        }
        showToast('success', res.message || hdT('saved'));
        $('#contractFormModal').modal('hide');
        loadHotelDashboard();
    }).catch(() => showToast('error', hdT('save_failed')));
});

function deleteContract(id) {
    if (!confirm(hdT('confirm_delete'))) return;
    showToast('info', hdT('deleting') + '...');
    hdAjax('../api/umrah/hotels/save_contract.php', { action: 'delete', id: id }, 'POST').then(res => {
        if (!res.success) { showToast('error', res.message || 'Failed'); return; }
        showToast('success', res.message || hdT('deleted'));
        loadHotelDashboard();
    }).catch(() => showToast('error', hdT('save_failed')));
}

// ============================================================== CALENDAR
function loadCalendar() {
    const hotelId = $('#calendarHotelFilter').val();
    if (!hotelId) {
        $('#calendarWrap').html('<div class="text-muted py-4 text-center">' + hdT('select_hotel') + '</div>');
        return;
    }
    const rt = $('#calendarRoomTypeFilter').val();
    let from = $('#calendarFrom').val();
    let to = $('#calendarTo').val();
    if (!from && !to) return;
    if (from && to && from > to) {
        showToast('error', hdT('invalid_date_range'));
        return;
    }
    hdDateState = { from: from, to: to };
    showToast('info', hdT('loading') + '...');
    hdAjax('../api/umrah/hotels/get_hotel_calendar.php', {
        hotel_id: hotelId,
        room_type_id: rt || '',
        from: from,
        to: to
    }).then(res => {
        if (!res.success) {
            showToast('error', res.message || 'Failed');
            return;
        }
        hdCalendarData = res;
        renderCalendar(res);
        refreshRoomTypeFilter();
    }).catch(() => showToast('error', hdT('load_failed')));
}

function refreshRoomTypeFilter() {
    const hotelId = $('#calendarHotelFilter').val();
    const usedIds = {};
    (hdData.rooms || []).forEach(r => {
        if (String(r.hotel_id) === String(hotelId)) usedIds[r.room_type_id] = true;
    });
    const html = '<option value="">' + hdT('all_room_types') + '</option>' +
        hdRoomTypeOptions.filter(r => usedIds[r.id]).map(r =>
            '<option value="' + r.id + '">' + hdEsc(r.name) + '</option>'
        ).join('');
    $('#calendarRoomTypeFilter').html(html);
}

function renderCalendar(res) {
    const $w = $('#calendarWrap');
    const rooms = res.rooms || [];
    const grid = res.grid || {};
    const dates = res.days || [];
    const legend = {
A: '<span class="uh-badge uh-badge--green">A</span>',
            R: '<span class="uh-badge uh-badge--blue">R</span>',
            O: '<span class="uh-badge uh-badge--red">O</span>',
            B: '<span class="uh-badge uh-badge--slate">B</span>'
    };

    if (!rooms.length || !dates.length) {
        $w.html('<div class="text-muted py-4 text-center">' + hdT('no_rooms') + '</div>');
        return;
    }

    // column header per date
    const colHeader = dates.map(d => {
        const dd = new Date(d);
        const wd = ['S', 'M', 'T', 'W', 'T', 'F', 'S'][dd.getDay()];
        return '<th class="text-center" style="min-width: 88px;">' + wd + '<br><strong>' + d.slice(5) + '</strong></th>';
    }).join('');

    const rowHtml = rooms.map(r => {
        const rtName = (hdData.room_types || []).find(x => x.id == r.room_type_id);
        const cells = dates.map(d => {
            const cell = grid[r.id] ? grid[r.id][d] : null;
            const s = cell ? cell.state : 'A';
            const st = legend[s] || legend.A;
            const occ = (s === 'O' || s === 'R');
            const fam = occ && cell.family ? cell.family : (occ && cell.member ? cell.member : '');
            let extra = '';
            if (occ) {
                extra =
                    '<div class="uh-cell-name" title="' + hdEsc(fam) + '">' + hdEsc(fam || '—') + '</div>' +
                    '<div class="uh-cell-bar uh-cell-bar--full"><div class="uh-cell-bar-fill" style="width:100%;"></div></div>';
            }
            const title = hdEsc(r.room_number) + ' ' + hdT('status') + ': ' + s + (fam ? ' — ' + hdEsc(fam) : '') + (occ && cell.check_in ? ' (' + hdEsc(cell.check_in) + ' → ' + hdEsc(cell.check_out) + ')' : '');
            return '<td class="text-center ' + (occ ? 'p-0 uh-cell-occ' : 'px-1') + '" title="' + title + '">' + st + (occ ? '<div class="uh-cell-fill">' + extra + '</div>' : '') + '</td>';
        }).join('');
        return '<tr><td style="min-width: 130px;"><strong>' + hdEsc(r.room_number) + '</strong><br><span class="text-muted" style="font-size: 0.75rem;">' + hdEsc(rtName ? rtName.name : '') + '</span></td>' + cells + '</tr>';
    }).join('');

    $w.html(
        '<div class="table-responsive">' +
            '<table class="table table-sm table-bordered mb-0 text-nowrap">' +
                '<thead class="thead-light"><tr><th style="min-width: 130px;">' + hdT('room') + '</th>' + colHeader + '</tr></thead>' +
                '<tbody>' + rowHtml + '</tbody>' +
            '</table>' +
        '</div>'
    );
}

$(document).on('change', '#calendarHotelFilter', refreshRoomTypeFilter);
$(document).on('click', '#btnLoadCalendar', loadCalendar);
$(document).on('click', '#btnRefreshHotels', loadHotelDashboard);

// ============================================================== INIT
$(document).ready(() => {
    if ($('#pane-overview').length) loadHotelDashboard();
});
