/**
 * Fulfillment modal logic — Phase 19-21
 * Opens per-member, lists sold services, assigns suppliers, records the
 * actual procurement cost (frozen once confirmed) and drives statuses.
 */

let fulfillmentData = null;
let currentFulfillmentBookingId = 0;
let currentFulfillmentCurrency = 'USD';
let currentFulfillmentFamilyId = 0;
let currentFulfillmentMode = 'member'; // 'member' | 'family' | 'group'
let currentFulfillmentGroupId = 0;
let currentFulfillmentGroupName = '';

function openFulfillmentModal(bookingId, memberName) {
    openFulfillmentFor({ booking_id: bookingId }, memberName || ('#' + bookingId), 'member');
}

function openFamilyFulfillmentModal(familyId, familyName) {
    openFulfillmentFor({ family_id: familyId }, familyName || ('Family #' + familyId), 'family');
}

function openGroupFulfillmentModal(groupId, groupName) {
    openFulfillmentFor({ group_id: groupId }, groupName || ('Group #' + groupId), 'group');
}

function openFulfillmentFor(params, entityName, mode) {
    currentFulfillmentBookingId = 0;
    currentFulfillmentFamilyId = 0;
    currentFulfillmentGroupId = 0;
    currentFulfillmentGroupName = '';
    currentFulfillmentMode = mode;
    $('#fulfillmentServicesContainer').empty();
    $('#fulfillmentEmptyState').addClass('d-none');

    const modeChip = mode === 'family'
        ? '<span class="fulfillment-chip ml-2">' + 'Family fulfillment' + '</span>'
        : (mode === 'group' ? '<span class="fulfillment-chip ml-2">' + 'Group fulfillment' + '</span>' : '');
    $('#fulfillmentMemberInfo').html(
        '<div class="card mb-0" style="border-left: 3px solid #0e7490;">' +
            '<div class="card-body py-2">' +
                '<strong>' + (entityName ? escapeHtml(entityName) : '') + '</strong>' +
                ' <span class="text-muted">' + (mode === 'family' ? '#' + params.family_id : (mode === 'group' ? '#' + params.group_id : '#' + params.booking_id)) + '</span>' +
                modeChip +
                '<div id="fulfillmentSummary" class="mt-1 small text-muted"></div>' +
            '</div>' +
        '</div>'
    );

    showToast('info', 'Loading services...');
    $.ajax({
        url: '../api/umrah/get_fulfillments.php?' + $.param(params),
        type: 'GET',
        dataType: 'json'
    }).then(data => {
        if (!data.success) {
            showToast('error', data.message || 'Failed to load services');
            return;
        }
        fulfillmentData = data;
        currentFulfillmentCurrency = ((data.booking && data.booking.currency) || 'USD').toString().trim().toUpperCase();
        currentFulfillmentBookingId = (data.booking && data.booking.booking_id) ? parseInt(data.booking.booking_id, 10) : 0;
        currentFulfillmentFamilyId = (data.booking && data.booking.family_id) ? parseInt(data.booking.family_id, 10) : 0;
        currentFulfillmentGroupId = data.group_id ? parseInt(data.group_id, 10) : 0;
        currentFulfillmentGroupName = data.group_name || '';
        renderFulfillmentServices(data);
        updateFulfillmentSummary();
        $('#fulfillmentModal').modal('show');
    }).catch(err => {
        console.error('Error loading fulfillments:', err);
        showToast('error', 'Failed to load services');
    });
}

function escapeHtml(str) {
    return String(str == null ? '' : str).replace(/[&<>"']/g, c => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[c]));
}

// One hotel stay block: hotel + room type + room + dates + rate. `st` is a stay
// from service.hotel_stays (or null for a fresh blank block).
function roomsOfHotel(hotelId) {
    if (!hotelId) return [];
    return (fulfillmentData.rooms || []).filter(r => String(r.hotel_id) === String(hotelId));
}

function roomTypeOptionsFor(hotelId) {
    if (!hotelId) return (fulfillmentData.room_types || []);
    const ids = {};
    roomsOfHotel(hotelId).forEach(r => { ids[r.room_type_id] = 1; });
    if (!Object.keys(ids).length) return (fulfillmentData.room_types || []);
    return (fulfillmentData.room_types || []).filter(rt => ids[rt.id]);
}

// Max occupancy of a room's room type (null when unset). Shared room types
// are treated as 4-bed rooms regardless of their stored occupancy —
// same-gender members are packed up to four per shared room.
function roomMaxOccupancy(roomId) {
    const room = (fulfillmentData.rooms || []).find(r => String(r.id) === String(roomId));
    if (!room) return null;
    const rt = (fulfillmentData.room_types || []).find(t => String(t.id) === String(room.room_type_id));
    if (rt && isSharedRoomType(rt.name)) return 4;
    const occ = rt ? parseInt(rt.max_occupancy, 10) : 0;
    return occ > 0 ? occ : null;
}

// Name of a room's room type ('' when unknown).
function roomTypeNameOf(roomId) {
    const room = (fulfillmentData.rooms || []).find(r => String(r.id) === String(roomId));
    if (!room) return '';
    const rt = (fulfillmentData.room_types || []).find(t => String(t.id) === String(room.room_type_id));
    return rt ? String(rt.name || '') : '';
}

// Family of the members a stay block assigns, from the card's breakdown
// (0 when unknown). Used to keep private room types family-exclusive.
function familyIdOfStay($st) {
    if (!$st || !$st.length) return 0;
    const ids = $st.closest('.f-hotel-group').data('member-ids');
    if (!Array.isArray(ids) || !ids.length) return 0;
    const bd = $st.closest('.fulfillment-service-card').data('breakdown') || [];
    for (let i = 0; i < bd.length; i++) {
        if (ids.indexOf(bd[i].booking_id) !== -1) return parseInt(bd[i].family_id, 10) || 0;
    }
    return 0;
}

// Whether the members a stay block assigns are booked on a Shared room type
// (their stored room type, NOT the physical room's type — a shared family
// can sit on a 4-bed room and still share it with other shared families).
// Unknown blocks default to private.
function isSharedStay($st) {
    if (!$st || !$st.length) return false;
    const ids = $st.closest('.f-hotel-group').data('member-ids');
    if (!Array.isArray(ids) || !ids.length) return false;
    const bd = $st.closest('.fulfillment-service-card').data('breakdown') || [];
    for (let i = 0; i < bd.length; i++) {
        if (ids.indexOf(bd[i].booking_id) !== -1) return isSharedRoomType(bd[i].room_type);
    }
    return false;
}

// A room is selectable only while it still has free beds for the stay's
// dates — full rooms must not be picked by other families/members. The
// block's own current pick stays listed even when the occupancy data shows
// it full (the server re-checks capacity on save anyway).
function roomIsUnavailable(roomId, stayEl, curRoom) {
    if (String(roomId) === String(curRoom || '')) return false;
    const max = roomMaxOccupancy(roomId);
    if (max === null || max === undefined) return false;
    const occ = roomOccupancy(roomId, stayEl);
    return occ.used >= max + occ.extraBeds;
}

// How many members are already on the room for the stay's dates, and how
// many extra beds (rollaway cots) are active there. Counts distinct owners
// (member bookings from the loaded scope data, plus other stay blocks in
// this modal picking the same room), excluding the stay's own assignment.
// Each checked "Extra bed" on an overlapping stay block adds one cot — the
// room's effective capacity is max_occupancy + extra beds (4-person room
// with one extra bed can host 5 members). The member's own stays never
// count in member view — the capacity guard lives server-side for the rest.
function roomOccupancy(roomId, stayEl) {
    const result = { used: 0, extraBeds: 0 };
    if (!roomId) return result;
    const owners = {};
    const extras = {};
    const selfFid = stayEl ? String(stayEl.data('fulfillment-id') || '') : '';
    const cin = stayEl ? stayEl.find('.f-check-in').val() : '';
    const cout = stayEl ? stayEl.find('.f-check-out').val() : '';
    // Shared members share their room with other families; private members
    // (1/2/3/4 Beds …) rent the whole room for their family. The rule keys on
    // the MEMBERS' booked room type — a shared family seated on a 4-bed room
    // still leaves its free beds selectable by other shared families.
    const selfFam = stayEl ? familyIdOfStay(stayEl) : 0;
    const selfShared = stayEl ? isSharedStay(stayEl) : false;
    // fulfillment_id|room of every assignment already counted from the saved
    // scope data — the DOM scan below skips those blocks, so a saved assignment
    // counts exactly once (the scroll only adds fresh / re-assigned blocks).
    const svcCounted = {};
    const usedKey = (key, fid, cIn, cOut, rId, extra) => {
        if (String(rId || '') !== String(roomId)) return;
        if (selfFid && fid && String(fid) === selfFid) return;
        if (cin && cout && cIn && cOut) {
            if (String(cOut) <= String(cin) || String(cIn) >= String(cout)) return;
        }
        if (fid) svcCounted[String(fid) + '|' + String(roomId)] = 1;
        owners[key] = 1;
        if (extra) extras[key] = 1;
    };
    const data = fulfillmentData || {};
    const memberView = data.scope === 'member';
    (data.services || []).forEach(svc => {
        const svcKey = 'svc' + (svc.booking_service_id || '');
        // When a per-member breakdown exists it already carries every member's
        // stays — using service.hotel_stays too would double-count the rep.
        const hasBreakdown = Array.isArray(svc.member_breakdown) && svc.member_breakdown.length > 0;
        if (!memberView && !hasBreakdown) {
            (Array.isArray(svc.hotel_stays) ? svc.hotel_stays : []).forEach(st =>
                usedKey(svcKey, st.fulfillment_id, st.check_in, st.check_out, st.room_id, st.extra_bed));
        }
        (Array.isArray(svc.member_breakdown) ? svc.member_breakdown : []).forEach(m =>
            (Array.isArray(m.stays) ? m.stays : []).forEach(st => {
                usedKey('m' + (m.booking_id || svcKey), st.fulfillment_id, st.check_in, st.check_out, st.room_id, st.extra_bed);
                // Cross-family cohabitation is allowed only when BOTH sides
                // are Shared-type. A private member's saved assignment makes
                // the whole room unavailable to others — a shared member's
                // saved assignment only counts its own beds.
                const mFam = parseInt(m.family_id, 10) || 0;
                if (selfFam && mFam && mFam !== selfFam) {
                    const mShared = isSharedRoomType(m.room_type);
                    if (!mShared || !selfShared) {
                        const capX = roomMaxOccupancy(roomId);
                        if (capX !== null && capX !== undefined && String(st.room_id || '') === String(roomId)) {
                            owners['m' + (m.booking_id || svcKey)] = capX;
                        }
                    }
                }
            }));
    });
    if (!memberView) {
        $('.fulfillment-stay').each(function() {
            if (stayEl && this === stayEl[0]) return;
            const $other = $(this);
            if (String($other.find('.f-room').val() || '') !== String(roomId)) return;
            const oFid = $other.data('fulfillment-id');
            if (oFid && svcCounted[String(oFid) + '|' + String(roomId)]) return;
            const oIn = $other.find('.f-check-in').val(), oOut = $other.find('.f-check-out').val();
            if (cin && cout && oIn && oOut) {
                if (String(oOut) <= String(cin) || String(oIn) >= String(cout)) return;
            }
            const $ocard = $other.closest('.fulfillment-service-card');
            const $ogroup = $other.closest('.f-hotel-group');
            // One stay block may cover several members (a merged same-duration
            // card / a whole scope) — count the members it assigns.
            let n = 1;
            if ($ogroup.length) {
                const gkey = $ogroup.data('gkey');
                const ids = $ogroup.data('member-ids');
                const gmode = $ocard.data('hotel-split') || 'duration';
                n = (Array.isArray(ids) && ids.length > 1)
                    ? ids.length
                    : (($ocard.data('breakdown') || []).filter(m => hotelMemberGroupKey(m, gmode) === gkey).length || 1);
            } else {
                n = ($ocard.data('breakdown') || []).length || 1;
            }
            // Cross-family cohabitation is allowed only when the members on BOTH
            // sides are Shared-type: a shared family's block adds only its
            // own beds (2 shared males on a 4-bed room leave 2 beds for
            // another family's shared males); a private family's block marks
            // the room fully occupied for everyone else, and a private block
            // never joins a room other families sit on. Shared rooms keep
            // their per-member counts either way.
            const myFam = familyIdOfStay(stayEl);
            const oFam = familyIdOfStay($other);
            const capN = roomMaxOccupancy(roomId);
            if (capN !== null && capN !== undefined && myFam > 0 && oFam > 0 && myFam !== oFam) {
                const mShared = isSharedStay($other);
                if (!mShared || !selfShared) n = capN;
            }
            const k = 'modal' + $ocard.data('booking-service-id') + ':' + String($ogroup.data('gkey') || '');
            owners[k] = (owners[k] || 0) + n;
            // The extra bed is a property of the room block (one checkbox per
            // block), so it adds one cot regardless of how many members the
            // block assigns — the server keeps the flag on one member only.
            if ($other.find('.f-extra-bed').is(':checked')) extras[k] = 1;
        });
    }
    result.used = Object.values(owners).reduce((a, b) => a + b, 0);
    result.extraBeds = Object.values(extras).reduce((a, b) => a + b, 0);
    return result;
}

// Room dropdown label: "200 · Floor 2 (2/4+1)" — number, floor, occupancy.
// The capacity shown adds the room's active extra beds (4-person room with
// one rollaway = 5).
function roomOptionLabel(r, used, max, extraBeds) {
    let label = escapeHtml(r.room_number);
    if (r.floor !== null && r.floor !== undefined && String(r.floor) !== '') {
        label += ' · ' + __t('floor') + ' ' + escapeHtml(r.floor);
    }
    const cap = max !== null && max !== undefined ? max : roomMaxOccupancy(r.id);
    const beds = parseInt(extraBeds, 10) || 0;
    if (cap) label += ' (' + (used || 0) + '/' + cap + (beds > 0 ? '+' + beds : '') + ')';
    return label;
}

function fulfillmentStayHtml(st, i, data) {
    const fid = st ? (st.fulfillment_id || '') : '';
    const stHotel = st ? (st.hotel_id || '') : '';
    const hotelOptions = `<option value="" data-supplier="">—</option>` + data.hotels.map(h =>
        `<option value="${h.id}" data-supplier="${h.supplier_id || ''}" ${st && String(st.hotel_id) === String(h.id) ? 'selected' : ''}>${escapeHtml(h.name)}${h.city ? ' (' + escapeHtml(h.city) + ')' : ''}</option>`
    ).join('');
    const roomTypeOptions = `<option value="">—</option>` + roomTypeOptionsFor(stHotel).map(rt =>
        `<option value="${rt.id}" ${st && String(st.room_type_id) === String(rt.id) ? 'selected' : ''}>${escapeHtml(rt.name)}</option>`
    ).join('');
    const roomOptions = `<option value="">—</option>` + roomsOfHotel(stHotel).map(r =>
        `<option value="${r.id}" ${st && String(st.room_id) === String(r.id) ? 'selected' : ''}>${roomOptionLabel(r, null, null)}</option>`
    ).join('');
    const nights = st && st.nights != null ? st.nights : '';
    const rate = st && st.nightly_rate != null ? st.nightly_rate : '';
    return `
    <div class="fulfillment-stay mb-2 p-2 rounded" style="border:1px solid #dee9f0;background:#f8fafc;" data-fulfillment-id="${fid}">
        <div class="row">
            <div class="form-group col-md-4 mb-1">
                <label style="font-size:.8rem;">${__t('hotel')}</label>
                <select class="form-control form-control-sm f-hotel">${hotelOptions}</select>
            </div>
            <div class="form-group col-md-4 mb-1">
                <label style="font-size:.8rem;">${__t('room_type')}</label>
                <select class="form-control form-control-sm f-room-type">${roomTypeOptions}</select>
            </div>
            <div class="form-group col-md-4 mb-1">
                <label style="font-size:.8rem;">Room</label>
                <select class="form-control form-control-sm f-room">${roomOptions}</select>
            </div>
        </div>
        <div class="row">
            <div class="form-group col-md-4 mb-1">
                <label style="font-size:.8rem;">${__t('check_in')}</label>
                <input type="date" class="form-control form-control-sm f-check-in" value="${st && st.check_in ? st.check_in : ''}">
            </div>
            <div class="form-group col-md-4 mb-1">
                <label style="font-size:.8rem;">${__t('check_out')}</label>
                <input type="date" class="form-control form-control-sm f-check-out" value="${st && st.check_out ? st.check_out : ''}">
            </div>
            <div class="form-group col-md-4 mb-1">
                <label style="font-size:.8rem;">${__t('nights')}</label>
                <input type="number" class="form-control form-control-sm f-nights" min="0" value="${nights}">
            </div>
        </div>
        <div class="row">
            <div class="form-group col-md-4 mb-0">
                <label style="font-size:.8rem;">${__t('nightly_rate')}</label>
                <input type="number" class="form-control form-control-sm f-nightly-rate" min="0" step="0.01" value="${rate}">
            </div>
            <div class="col-md-4 mb-0 mt-4">
                <label class="mb-0" style="font-size:.8rem;font-weight:500;color:#475569;">
                    <input type="checkbox" class="form-check-input f-extra-bed mr-1" style="margin-left:0;" ${st && st.extra_bed ? 'checked' : ''}>
                    ${__t('extra_bed')}
                </label>
            </div>
            <div class="col-md-4 mb-0 text-right">
                <button type="button" class="btn btn-sm btn-light btn-remove-stay mt-4 px-2" title="${__t('remove_stay')}"><i class="feather icon-x"></i></button>
            </div>
        </div>
    </div>`;
}

// Cost snapshot is frozen once the fulfillment leaves the setup phase
// (same status whitelist as save_fulfillment.php).
function isFulfillmentFrozen(status) {
    if (!status) return false;
    var open = ['pending', 'requested', 'assigned', 'not_assigned', 'reserved', 'not_applied', 'applied', 'processing', 'confirmed', 'ticketed', 'issued', 'not_ticketed'];
    return open.indexOf(status) === -1;
}

// Booking-level profit preview: sold - discount - sum of recorded costs.
// Family/group views use the scope-level aggregates returned by the API.
function updateFulfillmentSummary() {
    if (!fulfillmentData || !fulfillmentData.booking) return;
    const aggregate = fulfillmentData.scope === 'family' || fulfillmentData.scope === 'group';
    const sold = parseFloat(fulfillmentData.booking.sold_price) || 0;
    const discount = parseFloat(fulfillmentData.booking.discount) || 0;
    let costs;
    if (aggregate && fulfillmentData.booking.cost_total != null) {
        costs = parseFloat(fulfillmentData.booking.cost_total) || 0;
    } else {
        costs = 0;
        $('.fulfillment-service-card').each(function() {
            costs += parseFloat($(this).data('cost')) || 0;
        });
    }
    const profit = sold - discount - costs;
    let prefix = '';
    if (aggregate) {
        const fams = parseInt(fulfillmentData.booking.families_count) || 1;
        const mems = parseInt(fulfillmentData.booking.members_count) || 1;
        prefix = '<b>' + fams + '</b> famil' + (fams === 1 ? 'y' : 'ies') + ' &nbsp;·&nbsp; <b>' + mems + '</b> member' + (mems === 1 ? '' : 's') + ' &nbsp;·&nbsp; ';
    }
    $('#fulfillmentSummary').html(
        prefix +
        'Sold: <b>' + sold.toFixed(2) + '</b> ' + escapeHtml(currentFulfillmentCurrency) +
        ' &nbsp;·&nbsp; Discount: <b>' + discount.toFixed(2) + '</b>' +
        ' &nbsp;·&nbsp; Costs: <b>' + costs.toFixed(2) + '</b>' +
        ' &nbsp;·&nbsp; Profit: <b class="' + (profit >= 0 ? 'text-success' : 'text-danger') + '">' + profit.toFixed(2) + '</b>'
    );
}

function serviceGroupFor(service) {
    const cat = (service.category_name || '').toLowerCase();
    if (cat === 'hotel') return 'hotel';
    if (cat === 'flight') return 'flight';
    if (cat === 'visa') return 'visa';
    if (cat === 'transport') return 'transport';
    if (cat === 'meal') return 'meal';
    if (cat === 'ziyarat') return 'ziyarat';
    const t = (service.service_type || '').toLowerCase();
    if (t === 'ticket') return 'flight';
    if (t === 'hotel') return 'hotel';
    if (t === 'visa') return 'visa';
    if (t === 'transport') return 'transport';
    if (t === 'meal') return 'meal';
    return 'ziyarat';
}

// Normalize the package duration stored on a member: "15 days", "15 Days"
// and 15 all key the SAME duration group; values without a number (empty /
// unspecified) land in the 'unspecified' group.
function normalizedDurationKey(d) {
    const s = String(d == null ? '' : d).trim();
    const num = (s.match(/\d+/) || [null])[0];
    return num !== null ? num : 'unspecified';
}

// Group the covered members of an aggregate line by package duration.
// Members without a duration land in an 'unspecified' group (sorted last).
function groupMembersByDuration(breakdown) {
    const map = new Map();
    (Array.isArray(breakdown) ? breakdown : []).forEach(m => {
        const k = normalizedDurationKey(m.duration);
        if (!map.has(k)) { map.set(k, []); }
        map.get(k).push(m);
    });
    return new Map([...map.entries()].sort((a, b) => {
        if (a[0] === 'unspecified') return 1;
        if (b[0] === 'unspecified') return -1;
        return parseInt(a[0], 10) - parseInt(b[0], 10);
    }));
}

function groupDurationLabel(dur) {
    if (dur === 'unspecified') return __t('return_unspecified_duration');
    return __t('return_for_duration').replace('{duration}', dur);
}

// Aggregate flight card when covered members have DIFFERENT package
// durations (e.g. 15 vs 21 days). Shared by default (Same Departure / Same
// PNR checked); unchecking reveals per-duration-group fields. The return
// journey is always split per duration group.
function groupedFlightExtraHtml(service, durGroups, fType) {
    const splitDT = (v) => {
        if (!v) return { d: '', t: '' };
        const p = String(v).replace('T', ' ').split(' ');
        return { d: p[0] || '', t: p[1] ? p[1].slice(0, 5) : '' };
    };
    const sdep = splitDT(service.departure_time), sarr = splitDT(service.arrival_time);
    const firstWithData = (members) => {
        const hit = (members || []).find(m => m.pnr || m.return_flight_number || m.return_departure_time || m.departure_time);
        return hit || (members || [])[0] || {};
    };

    const groupBlocks = [...durGroups.entries()].map(([dur, members]) => {
        const g = firstWithData(members);
        const dep = splitDT(g.departure_time), arr = splitDT(g.arrival_time);
        const rdep = splitDT(g.return_departure_time), rarr = splitDT(g.return_arrival_time);
        return `
            <div class="card mb-2 f-flight-group" data-dur="${dur}">
                <div class="card-header bg-light py-1">
                    <h6 class="mb-0" style="font-size:0.85rem;color:#334155;">
                        <i class="feather icon-users mr-2"></i>${members.map(m => escapeHtml(m.name || ('#' + m.booking_id))).join(', ')}
                        <span class="flight-duration-chip ml-2">${dur === 'unspecified' ? '—' : dur + ' days'}</span>
                        ${g && g.booking_service_id && g.fulfillment_id
                            ? `<button type="button" class="btn btn-xs btn-outline-primary ml-2 btn-print-ticket" data-booking-service-id="${g.booking_service_id}" title="${__t('print_ticket')}"><i class="feather icon-printer mr-1"></i>${__t('print_ticket')}</button>`
                            : ''}
                    </h6>
                </div>
                <div class="card-body py-2">
                    <div class="f-g-pnr-row" style="display:none;">
                        <div class="form-group col-md-4 px-0 mb-1">
                            <label class="small mb-1 text-muted">PNR</label>
                            <input type="text" class="form-control form-control-sm f-g-pnr" value="${escapeHtml(g.pnr || '')}">
                        </div>
                    </div>
                    <div class="f-g-dep-wrap" style="display:none;">
                        <div class="mb-1" style="font-size:0.85rem;font-weight:600;color:#334155;">
                            <i class="feather icon-corner-up-right mr-1" style="color:#0e7490;"></i>${__t('outbound_journey')} — <span class="font-weight-bold">${dur === 'unspecified' ? '—' : dur + ' days'}</span>
                        </div>
                        <div class="row f-g-dep-row">
                            <div class="form-group col-md-4 mb-2">
                                <label class="small mb-1 text-muted">${__t('departure_city')}</label>
                                <input type="text" class="form-control form-control-sm f-g-dep-city" value="${escapeHtml(g.departure_city || service.departure_city || 'Kabul')}">
                            </div>
                            <div class="form-group col-md-4 mb-2">
                                <label class="small mb-1 text-muted">${__t('arrival_city')}</label>
                                <input type="text" class="form-control form-control-sm f-g-arr-city" value="${escapeHtml(g.arrival_city || service.arrival_city || 'Jeddah')}">
                            </div>
                            <div class="form-group col-md-4 mb-2">
                                <label class="small mb-1 text-muted">${__t('outbound_flight_number')}</label>
                                <input type="text" class="form-control form-control-sm f-g-flight-no" value="${escapeHtml(g.flight_number || service.flight_number || 'RQ993')}">
                            </div>
                            <div class="form-group col-md-3 mb-2">
                                <label class="small mb-1 text-muted">${__t('departure_date')}</label>
                                <input type="date" class="form-control form-control-sm f-g-dep-date" value="${escapeHtml(dep.d)}">
                            </div>
                            <div class="form-group col-md-3 mb-2">
                                <label class="small mb-1 text-muted">${__t('departure_time')}</label>
                                <input type="time" class="form-control form-control-sm f-g-dep-time" value="${escapeHtml(dep.t)}">
                            </div>
                            <div class="form-group col-md-3 mb-2">
                                <label class="small mb-1 text-muted">${__t('arrival_date')}</label>
                                <input type="date" class="form-control form-control-sm f-g-arr-date" value="${escapeHtml(arr.d)}">
                            </div>
                            <div class="form-group col-md-3 mb-2">
                                <label class="small mb-1 text-muted">${__t('arrival_time')}</label>
                                <input type="time" class="form-control form-control-sm f-g-arr-time" value="${escapeHtml(arr.t)}">
                            </div>
                        </div>
                    </div>
                    <div class="mt-1 mb-1" style="font-size:0.85rem;font-weight:600;color:#334155;">
                        <i class="feather icon-corner-up-left mr-1" style="color:#0e7490;"></i>${__t('return_journey')} — <span class="font-weight-bold">${dur === 'unspecified' ? '—' : dur + ' days'}</span>
                    </div>
                    <div class="row">
                        <div class="form-group col-md-4 mb-2">
                            <label class="small mb-1 text-muted">${__t('return_flight_number')}</label>
                            <input type="text" class="form-control form-control-sm f-g-return-flight" value="${escapeHtml(g.return_flight_number || service.return_flight_number || '')}">
                        </div>
                        <div class="form-group col-md-4 mb-2">
                            <label class="small mb-1 text-muted">${__t('departure_date')}</label>
                            <input type="date" class="form-control form-control-sm f-g-ret-dep-date" value="${escapeHtml(rdep.d)}">
                        </div>
                        <div class="form-group col-md-4 mb-2">
                            <label class="small mb-1 text-muted">${__t('departure_time')}</label>
                            <input type="time" class="form-control form-control-sm f-g-ret-dep-time" value="${escapeHtml(rdep.t)}">
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-md-6 mb-2">
                            <label class="small mb-1 text-muted">${__t('arrival_date')}</label>
                            <input type="date" class="form-control form-control-sm f-g-ret-arr-date" value="${escapeHtml(rarr.d)}">
                        </div>
                        <div class="form-group col-md-6 mb-2">
                            <label class="small mb-1 text-muted">${__t('arrival_time')}</label>
                            <input type="time" class="form-control form-control-sm f-g-ret-arr-time" value="${escapeHtml(rarr.t)}">
                        </div>
                    </div>
                </div>
            </div>`;
    }).join('');

    return `
        <div class="mt-2 fulfillment-type-fields">
            <div class="row">
                <div class="form-group col-md-4">
                    <label>${__t('ticket_number')}</label>
                    <input type="text" class="form-control form-control-sm f-ticket" value="${escapeHtml(service.ticket_number || '')}">
                </div>
                <div class="form-group col-md-4">
                    <label>${__t('airline')}</label>
                    <input type="text" class="form-control form-control-sm f-airline" value="${escapeHtml(service.airline || '')}">
                </div>
                <div class="form-group col-md-4">
                    <label>${__t('flight_type')}</label>
                    <input type="text" class="form-control form-control-sm" value="${fType === 'indirect' ? __t('connecting_flight') : __t('direct_flight')}" readonly>
                </div>
            </div>
            <div class="row mb-2">
                <div class="col-md-6">
                    <div class="form-check mb-1">
                        <input class="form-check-input f-same-dep" type="checkbox" id="sd-${service.booking_service_id}" checked>
                        <label class="form-check-label small" for="sd-${service.booking_service_id}">${__t('same_departure')}</label>
                    </div>
                    <div class="form-check mb-1">
                        <input class="form-check-input f-same-pnr" type="checkbox" id="sp-${service.booking_service_id}" checked>
                        <label class="form-check-label small" for="sp-${service.booking_service_id}">${__t('same_pnr')}</label>
                    </div>
                </div>
                <div class="col-md-6 text-muted small" style="font-size:0.8rem;">
                    ${__t('same_flight_hint')}
                </div>
            </div>
            <div class="f-same-dep-fields">
                <div class="row">
                    <div class="form-group col-md-6">
                        <label>${__t('departure_city')}</label>
                        <input type="text" class="form-control form-control-sm f-dep-city" value="${escapeHtml(service.departure_city || 'Kabul')}">
                    </div>
                    <div class="form-group col-md-6">
                        <label>${__t('arrival_city')}</label>
                        <input type="text" class="form-control form-control-sm f-arr-city" value="${escapeHtml(service.arrival_city || 'Jeddah')}">
                    </div>
                </div>
                <div class="row">
                    <div class="form-group col-md-6">
                        <label>${__t('outbound_flight_number')}</label>
                        <input type="text" class="form-control form-control-sm f-flight-no" value="${escapeHtml(service.flight_number || 'RQ993')}">
                    </div>
                    <div class="form-group col-md-6">
                        <label class="small">${__t('departure_date')}</label>
                        <input type="date" class="form-control form-control-sm f-dep-date" value="${escapeHtml(sdep.d)}">
                    </div>
                </div>
                <div class="row">
                    <div class="form-group col-md-4">
                        <label class="small">${__t('departure_time')}</label>
                        <input type="time" class="form-control form-control-sm f-dep-time" value="${escapeHtml(sdep.t)}">
                    </div>
                    <div class="form-group col-md-4">
                        <label class="small">${__t('arrival_date')}</label>
                        <input type="date" class="form-control form-control-sm f-arr-date" value="${escapeHtml(sarr.d)}">
                    </div>
                    <div class="form-group col-md-4">
                        <label class="small">${__t('arrival_time')}</label>
                        <input type="time" class="form-control form-control-sm f-arr-time" value="${escapeHtml(sarr.t)}">
                    </div>
                </div>
            </div>
            <div class="f-same-pnr-fields mb-2" style="display:none;">
                <div class="form-group col-md-4 px-0 mb-1">
                    <label>PNR</label>
                    <input type="text" class="form-control form-control-sm f-pnr" value="${escapeHtml(service.pnr || '')}">
                </div>
            </div>
            <div class="f-group-blocks" style="margin-top:6px;">
                ${groupBlocks}
            </div>
        </div>`;
}

// —— Hotel room split --------------------------------------------------------
// Family/group booking: the room assignment can be split per family, per
// member, per package-duration or per gender. Every grouped mode renders ONE
// stay card per member (keys stay string-safe, 'm42', and map 1:1 to
// hotel_group_members / hotel_groups on save) so check-in / check-out are
// shown separately even for members of the same family; gender mode
// additionally auto-assigns Shared-room types to the nearest same-gender
// rooms (see autoAssignGenderRooms).
function hotelMemberGroupKey(m, mode) {
    if (mode === 'duration') return m.duration ? String(m.duration) : 'unspecified';
    if (mode === 'gender') return m.gender ? String(m.gender) : 'unspecified';
    if (mode === 'family') {
        // Members without a family (standalone) become their own one-person
        // group so they are still assignable.
        return m.family_id ? 'fam' + m.family_id : (m.booking_id ? 'fam' + m.booking_id : null);
    }
    if (mode === 'member') return m.booking_id ? 'm' + m.booking_id : null;
    return null;
}

// Shared-room detection: hotels pair same-gender members in "Shared" room
// types — NOT the bed-count types (1 / 2 / 3 / 4 beds), which are private
// rooms booked per member.
function isSharedRoomType(rt) {
    const s = String(rt || '').trim().toLowerCase();
    if (!s) return false;
    if (/\d+\s*beds?/.test(s) || /beds?\s*\d/.test(s)) return false;
    return s.indexOf('share') !== -1;
}

function hotelMemberRank(m) {
    const s = String(m.gender || '').toLowerCase();
    const g = s === 'male' ? 0 : (s === 'female' ? 1 : 2);
    const durKey = normalizedDurationKey(m.duration);
    const d = durKey === 'unspecified' ? 9999 : parseInt(durKey, 10);
    return {
        gender: g,
        duration: isNaN(d) ? 9999 : d,
        family: parseInt(m.family_id, 10) || 0,
        name: String(m.name || '').toLowerCase()
    };
}

// Deterministic order for the per-member hotel cards ("per family and per
// member for the duration"): the split identity (duration / family / gender)
// sorts first so families of the same duration or gender stay adjacent,
// then the rest, then the name.
function sortHotelMembers(breakdown, mode) {
    return (Array.isArray(breakdown) ? breakdown : [])
        .filter(m => m && m.booking_id)
        .sort((a, b) => {
            const A = hotelMemberRank(a), B = hotelMemberRank(b);
            let k = 0;
            if (mode === 'gender') {
                k = A.gender - B.gender;
                if (k === 0) k = A.duration - B.duration;
                if (k === 0) k = A.family - B.family;
            } else if (mode === 'duration') {
                k = A.duration - B.duration;
                if (k === 0) k = A.gender - B.gender;
                if (k === 0) k = A.family - B.family;
            } else {
                // family / member — family first, males next to males
                k = A.family - B.family;
                if (k === 0) k = A.gender - B.gender;
                if (k === 0) k = A.duration - B.duration;
            }
            if (k === 0) k = A.name.localeCompare(B.name) || (a.booking_id - b.booking_id);
            return k;
        });
}

function hotelMemberChips(m, data) {
    const famName = m.family_id ? ((data.families || {})[String(m.family_id)] || '') : '';
    const fam = famName
        ? `<span class="fulfillment-chip ml-2"><i class="feather icon-home mr-1"></i>${escapeHtml(famName)} family</span>`
        : `<span class="fulfillment-chip ml-2">Family #${m.family_id || 0}</span>`;
    const durKey = normalizedDurationKey(m.duration);
    const dur = `<span class="flight-duration-chip ml-2">${durKey === 'unspecified' ? '—' : durKey + ' days'}</span>`;
    const isF = String(m.gender || '').toLowerCase() === 'female';
    const gender = `<span class="fulfillment-chip fulfillment-chip-optional ml-2"><i class="feather ${isF ? 'icon-user-check' : 'icon-user'} mr-1"></i>${escapeHtml(m.gender || 'Unspecified')}</span>`;
    return gender + dur + fam;
}

// Numeric part of a room number ("101" vs "A-12") for nearest-room ordering.
function roomNumberSort(r) {
    const m = String(r.room_number || '').match(/\d+/);
    return m ? parseInt(m[0], 10) : 0;
}

function hotelSplitBodyHtml(service, data, mode) {
    if (mode === 'same' || !mode) {
        const stays = (Array.isArray(service.hotel_stays) && service.hotel_stays.length) ? service.hotel_stays : [null];
        return `<div class="fulfillment-stays">
            ${stays.map((st, i) => fulfillmentStayHtml(st, i, data)).join('')}
        </div>
        <button type="button" class="btn btn-sm btn-outline-primary btn-add-stay">
            <i class="feather icon-plus mr-1"></i>${__t('add_stay')}
        </button>`;
    }
    // Grouped modes: members keep their own stay blocks — even relatives of
    // the same family — so check-in / check-out (and their rooms) can differ
    // per person. Per family groups the family, then members of the SAME
    // package duration together; per duration groups the duration, then
    // families together; per gender merges Shared room types into one card
    // per gender (flat per-member cards under Per member). Same-family
    // members sharing duration + room type merge into ONE shared block — a
    // single room pick assigns them all; whether gender splits the cards
    // depends on the type: private types (3 Beds …) group regardless of
    // gender (family rents the room), Shared types group by gender (females
    // with females, males with males, auto-packed into nearest rooms). A
    // member on a different duration / room type keeps their own fields.
    // Each card maps to its member(s) on save (data-member-ids = its hotel
    // group).
    const members = sortHotelMembers(service.member_breakdown, mode);
    const hasShared = (service.member_breakdown || []).some(m => isSharedRoomType(m.room_type));
    let groupBlocks = '';
    if (mode === 'member') {
        groupBlocks = members.map(m => hotelMemberCardHtml(m, data)).join('');
    } else if (mode === 'gender') {
        // Shared room types: merge into one card per gender (males together,
        // females together), then the auto packer fills nearest same-gender
        // rooms.
        groupBlocks = hotelGroupedMemberCardsHtml(members, data);
    } else {
        groupBlocks = hotelSubgroups(members, mode)
            .map(sub => hotelSubgroupCardHtml(sub, mode, data))
            .join('');
    }
    return `
    <div class="f-hotel-groups">
        ${groupBlocks}
    </div>
    ${mode === 'duration' ? `<div class="mb-2 text-muted small" style="font-size:0.8rem;">${__t('grouped_hotel_hint')}</div>` : ''}
    ${hasShared ? `<div class="mb-2 text-muted small" style="font-size:0.8rem;">${__t('gender_split_hint')}</div>` : ''}`;
}

// One member's own stay card: their name + context chips + their hotel
// stays. This card IS the member's room group on save.
function hotelMemberCardHtml(m, data) {
    const stays = (Array.isArray(m.stays) && m.stays.length) ? m.stays : [null];
    const stayBlocks = stays.map((st, i) => fulfillmentStayHtml(st, i, data)).join('');
    const isF = String(m.gender || '').toLowerCase() === 'female';
    const icon = isF ? 'icon-user-check' : 'icon-user';
    const color = isF ? '#be185d' : '#0e7490';
    return `
    <div class="card mb-2 f-hotel-group" data-gkey="m${m.booking_id}" data-member-id="${m.booking_id}" data-member-ids="${JSON.stringify([m.booking_id])}">
        <div class="card-header bg-light py-1">
            <h6 class="mb-0" style="font-size:0.85rem;color:#334155;">
                <i class="feather ${icon} mr-2" style="color:${color};"></i>${escapeHtml(m.name || ('#' + m.booking_id))}
                ${hotelMemberChips(m, data)}
            </h6>
        </div>
        <div class="card-body py-2">
            <div class="fulfillment-stays">
                ${stayBlocks}
            </div>
            <button type="button" class="btn btn-sm btn-outline-primary btn-add-stay mt-1">
                <i class="feather icon-plus mr-1"></i>${__t('add_stay')}
            </button>
        </div>
    </div>`;
}

function durSortKey(key) { return key === 'unspecified' ? 9999 : (isNaN(parseInt(key, 10)) ? 9999 : parseInt(key, 10)); }

// ---- Same-room grouping ------------------------------------------------------
// Members of the same family on the SAME package duration and room type are
// merged into ONE shared stay card — a single Room dropdown whose pick
// assigns the whole group to that room on save. How gender factors in
// depends on the room type:
//   - Private types (3 Beds, 2 Beds …): gender is IRRELEVANT — the family
//     rents the whole room for themselves, so males + females group together.
//   - Shared types ("Shared" …): gender MATTERS — the group key includes the
//     gender, so males get one card and females another, and the auto
//     packer assigns them to the nearest same-gender rooms (never mixed).
// A member on a different duration / room type naturally keeps their own
// card with their own fields. Room types compare case-insensitively and
// members without a stored room type still group together (the operator
// picks the type once in the shared block). Only members whose saved stays
// are identical (or all fresh) merge — conflicting saved assignments stay
// per-member, so nothing already recorded is ever overwritten blindly.

// Stay assignments (hotel/room/dates/rate) normalized to one comparable key.
function hotelStaysKey(m) {
    return JSON.stringify(((Array.isArray(m.stays) ? m.stays : []) || []).map(st => [
        String(st.hotel_id || ''), String(st.room_id || ''), String(st.room_type_id || ''),
        st.extra_bed ? 1 : 0, String(st.check_in || ''), String(st.check_out || ''),
        st.nights == null ? '' : String(st.nights), st.nightly_rate == null ? '' : String(st.nightly_rate)
    ].join('|')));
}

function hotelStaysCompatible(members) {
    const first = hotelStaysKey(members[0]);
    return members.every(m => hotelStaysKey(m) === first);
}

// Renders the member cards of one family/duration subgroup: members that
// merge (same duration + room type + compatible stays + gender where the
// type is Shared) become one shared card; everyone else keeps their own
// per-member card.
function hotelGroupedMemberCardsHtml(members, data) {
    const sharedByKey = new Map();   // duration|room_type|gender? -> member list
    const memberToKey = new Map();   // booking_id -> merge key
    (Array.isArray(members) ? members : []).forEach(m => {
        if (!m.booking_id) return;
        const dur = normalizedDurationKey(m.duration);
        const rt = String(m.room_type || '').trim().toLowerCase();
        // For Shared room types the gender separates the cards (females with
        // females, males with males); for private types the whole family
        // groups together regardless of gender.
        const genKey = isSharedRoomType(rt) ? String(m.gender || '').trim().toLowerCase() : '';
        const k = dur + '||' + rt + '||' + genKey;
        if (!sharedByKey.has(k)) sharedByKey.set(k, []);
        sharedByKey.get(k).push(m);
        memberToKey.set(String(m.booking_id), k);
    });
    const keep = {};
    sharedByKey.forEach((group, k) => {
        if (group.length > 1 && hotelStaysCompatible(group)) {
            keep[k] = group;
        } else {
            group.forEach(m => memberToKey.delete(String(m.booking_id)));
        }
    });
    const emitted = {};
    let html = '';
    members.forEach(m => {
        const k = (m.booking_id != null) ? memberToKey.get(String(m.booking_id)) : undefined;
        if (k && keep[k]) {
            if (!emitted[k]) {
                emitted[k] = 1;
                html += hotelSharedCardHtml(keep[k], data);
            }
            // already covered by the shared card above — skip the member
        } else {
            html += hotelMemberCardHtml(m, data);
        }
    });
    return html;
}

// One stay block shared by several members: the header lists everyone the
// pick applies to, and data-member-ids is the WHOLE group on save — so
// choosing room 200 assigns all of them. Outside cards count the whole
// group against the room's occupancy via member-ids.
function hotelSharedCardHtml(members, data) {
    const durKey = normalizedDurationKey(members[0].duration);
    const src = members.find(m => Array.isArray(m.stays) && m.stays.length) || members[0];
    const stays = (Array.isArray(src.stays) && src.stays.length) ? src.stays : [null];
    const stayBlocks = stays.map((st, i) => fulfillmentStayHtml(st, i, data)).join('');
    const ids = members.map(m => m.booking_id);
    const names = members.map(m => escapeHtml(m.name || ('#' + m.booking_id))).join(', ');
    const durChip = `<span class="flight-duration-chip ml-2">${durKey === 'unspecified' ? '—' : durKey + ' days'}</span>`;
    const rt = String(members[0].room_type || '').trim();
    const shared = isSharedRoomType(rt);
    const rtChip = rt
        ? `<span class="fulfillment-chip ml-2"><i class="feather icon-bed mr-1"></i>${escapeHtml(rt)}</span>`
        : '';
    // Gender chip only on Shared cards — there the group is one gender by
    // construction; on private cards gender is irrelevant.
    const g = shared ? String(members[0].gender || '').trim().toLowerCase() : '';
    const genderChip = g
        ? `<span class="fulfillment-chip fulfillment-chip-optional ml-2"><i class="feather ${g === 'female' ? 'icon-user-check' : 'icon-user'} mr-1"></i>${escapeHtml(members[0].gender)}</span>`
        : '';
    return `
    <div class="card mb-2 f-hotel-group" data-gkey="s${ids[0]}" data-shared="1" data-member-ids="${JSON.stringify(ids)}">
        <div class="card-header bg-light py-1">
            <h6 class="mb-0" style="font-size:0.85rem;color:#334155;">
                <i class="feather icon-users mr-2" style="color:#0e7490;"></i>${names}
                ${genderChip}${rtChip}${durChip}
                <span class="fulfillment-chip ml-2"><i class="feather icon-bed mr-1"></i>${members.length} member${members.length === 1 ? '' : 's'} · one room</span>
            </h6>
        </div>
        <div class="card-body py-2">
            <div class="fulfillment-stays">${stayBlocks}</div>
            <div class="mb-1 text-muted small" style="font-size:0.8rem;">
                <i class="feather icon-info mr-1" style="color:#0e7490;"></i>The room you pick here is assigned to all ${members.length} member${members.length === 1 ? '' : 's'} together — switch to "Per member" to give each one a separate room.
            </div>
        </div>
    </div>`;
}

// Group cards for family / duration modes: members of the same family stay
// under one family card with their same-duration relatives ("Per family"
// groups the members with the same package duration together; "Per duration"
// mirrors that — duration card, then family). The subgroups are only visual
// containers — the .f-hotel-group member cards inside do the saving.
function hotelSubgroups(members, mode) {
    const subs = new Map();
    (Array.isArray(members) ? members : []).forEach(m => {
        const fam = parseInt(m.family_id, 10) || 0;
        const durKey = normalizedDurationKey(m.duration);
        const p = (mode === 'family' ? 'fam' + fam : durKey);
        const s = (mode === 'family' ? durKey : 'fam' + fam);
        const k = p + '|' + s;
        if (!subs.has(k)) {
            subs.set(k, { fam: fam, durKey: durKey, members: [] });
        }
        subs.get(k).members.push(m);
    });
    const arr = [...subs.values()];
    arr.sort((a, b) => {
        let k = mode === 'family'
            ? (a.fam - b.fam) || (durSortKey(a.durKey) - durSortKey(b.durKey))
            : (durSortKey(a.durKey) - durSortKey(b.durKey)) || (a.fam - b.fam);
        return k;
    });
    arr.forEach(sub => {
        sub.members.sort((x, y) =>
            (hotelMemberRank(x).gender - hotelMemberRank(y).gender) ||
            String(x.name || '').localeCompare(String(y.name || '')) ||
            (x.booking_id - y.booking_id));
    });
    return arr;
}

function hotelSubgroupCardHtml(sub, mode, data) {
    const famName = sub.fam ? ((data.families || {})[String(sub.fam)] || '') : '';
    const famChip = famName
        ? `<span class="fulfillment-chip ml-2"><i class="feather icon-home mr-1"></i>${escapeHtml(famName)} family</span>`
        : `<span class="fulfillment-chip ml-2">Family #${sub.fam || 0}</span>`;
    const durChip = `<span class="flight-duration-chip ml-2">${sub.durKey === 'unspecified' ? '—' : sub.durKey + ' days'}</span>`;
    const names = sub.members.map(m => escapeHtml(m.name || ('#' + m.booking_id))).join(', ');
    const memberCards = hotelGroupedMemberCardsHtml(sub.members, data);
    return `
    <div class="card mb-2 f-hotel-subgroup">
        <div class="card-header bg-light py-1">
            <h6 class="mb-0" style="font-size:0.85rem;color:#334155;">
                <i class="feather icon-users mr-2" style="color:#0e7490;"></i>
                ${mode === 'family' ? famChip + durChip : durChip + famChip}
                <span class="flight-duration-chip ml-2">${sub.members.length} member${sub.members.length === 1 ? '' : 's'}</span>
                <span class="ml-1 text-muted" style="font-weight:400;">· ${names}</span>
            </h6>
        </div>
        <div class="card-body py-2">
            ${memberCards}
        </div>
    </div>`;
}

// Pure gender packer: assign each unit (gender + member count) to rooms in
// order (floor, room number), males first, then females, then others. A room
// holds members up to its capacity; when a unit needs more than a room's
// free space it moves to the next room, so a merged gender card (size = its
// member count) always lands on a room that fits the WHOLE group. Rooms are
// never mixed across genders: the first room the next gender could not fully
// close within one gender's row is skipped for the next gender. Returns a
// Map of unit-index -> room id (units without a spot stay unassigned).
function packGenderRooms(units, rooms, occupancyOf) {
    const inRoom = {};
    const result = new Map();
    const cap = (r) => occupancyOf(r) || 1;
    let ptr = 0;
    ['male', 'female', 'other'].forEach(gk => {
        units.forEach((u, i) => {
            const isOther = u.gender !== 'male' && u.gender !== 'female';
            if (gk === 'other' ? !isOther : u.gender !== gk) return;
            const need = u.size || 1;
            while (ptr < rooms.length && (inRoom[rooms[ptr].id] || 0) + need > cap(rooms[ptr])) ptr++;
            if (ptr >= rooms.length) return;
            inRoom[rooms[ptr].id] = (inRoom[rooms[ptr].id] || 0) + need;
            result.set(i, rooms[ptr].id);
        });
        // Close the row: a room the previous gender only partially filled
        // must NOT be shared with the next gender — the next gender starts
        // on the following room (nearest available, never mixed).
        if (ptr < rooms.length && (inRoom[rooms[ptr].id] || 0) > 0) ptr++;
    });
    return result;
}

// Gender split + Shared room type: auto-assign every SHARED member's stay
// block to the nearest same-gender room — one card per gender (merged) or
// per shared member (flat), both land here. Males pack into the earliest
// rooms, females into the rooms right after (and others after them),
// regardless of family, so e.g. 2 families × 2 males + 2 females end up
// with one male room and one female room side by side. A merged gender card
// counts as its whole member group — the room it lands on must fit ALL of
// them. Only blocks WITHOUT a room yet are placed — saved assignments are
// never overwritten. Blocks with no hotel selected are skipped; picking a
// hotel later runs this again.
function autoAssignGenderRooms($card) {
    if ($card.data('group') !== 'hotel') return;
    if (($card.data('hotel-split') || 'same') === 'same') return;
    const bd = $card.data('breakdown') || [];
    if (!bd.length || !bd.some(m => isSharedRoomType(m.room_type))) return;
    const membersById = {};
    bd.forEach(m => { membersById[m.booking_id] = m; });

    const blocksByHotel = {};
    $card.find('.fulfillment-stay').each(function() {
        const $st = $(this);
        if ($st.find('.f-room').val()) return; // already assigned
        const hotelId = $st.find('.f-hotel').val();
        if (!hotelId) return;
        const $gc = $st.closest('.f-hotel-group');
        const ids = $gc.data('member-ids');
        const groupMembers = (Array.isArray(ids) && ids.length)
            ? ids.map(id => membersById[id]).filter(Boolean)
            : [];
        if (groupMembers.length) {
            // Merged gender card — every member shares the same Shared room
            // type and (by construction) the same gender; the chosen room
            // must fit the whole group. Private merged cards are skipped.
            if (!groupMembers.every(m => isSharedRoomType(m.room_type))) return;
            const gender = String(groupMembers[0].gender || '').toLowerCase();
            (blocksByHotel[hotelId] = blocksByHotel[hotelId] || []).push({ $st: $st, gender: gender, size: groupMembers.length });
        } else {
            const mrec = membersById[$gc.data('member-id')] || {};
            if (!isSharedRoomType(mrec.room_type)) return; // private rooms stay manual
            const gender = String(mrec.gender || '').toLowerCase();
            (blocksByHotel[hotelId] = blocksByHotel[hotelId] || []).push({ $st: $st, gender: gender, size: 1 });
        }
    });

    Object.keys(blocksByHotel).forEach(hotelId => {
        const units = blocksByHotel[hotelId];
        if (!units.length) return;
        const preferredRt = units[0].$st.find('.f-room-type').val() || '';
        // Shared members pack ONLY into Shared-type rooms — private rooms
        // (1/2/3/4 Beds) are reserved for the families that rent them and
        // must never be grabbed by the gender packer.
        const rooms = roomsOfHotel(hotelId)
            .filter(r => isSharedRoomType(roomTypeNameOf(r.id)))
            .filter(r => !preferredRt || String(r.room_type_id) === preferredRt)
            .sort((a, b) =>
                String(a.floor || '').localeCompare(String(b.floor || ''), undefined, { numeric: true }) ||
                roomNumberSort(a) - roomNumberSort(b));
        if (!rooms.length) return;
        const packed = packGenderRooms(units, rooms, (r) => roomMaxOccupancy(r.id));
        packed.forEach((rid, uIdx) => { if (rid !== undefined) units[uIdx].$st.data('auto-room', rid); });
    });

    // Switch each block's room type to its assigned room's type, then apply.
    let rtChanged = false;
    $card.find('.fulfillment-stay').each(function() {
        const rid = $(this).data('auto-room');
        if (rid === undefined || rid === null) return;
        const room = (fulfillmentData.rooms || []).find(r => String(r.id) === String(rid));
        if (!room) return;
        const rtId = String(room.room_type_id || '');
        const $rt = $(this).find('.f-room-type');
        if (rtId && $rt.find('option[value="' + rtId + '"]').length && $rt.val() !== rtId) {
            $rt.val(rtId);
            rtChanged = true;
        }
    });
    if (rtChanged) syncFulfillmentRoomOptions($card);
    $card.find('.fulfillment-stay').each(function() {
        const rid = $(this).data('auto-room');
        if (rid === undefined || rid === null) return;
        const $rm = $(this).find('.f-room');
        if ($rm.find('option[value="' + rid + '"]').length) {
            $rm.val(rid);
        }
        $(this).removeData('auto-room');
    });
    syncFulfillmentRoomOptions($card);
}

// Private room types ("3 Beds", "2 Beds" …) are rented per family: a family
// whose stay blocks carry such a type fills ONE room of that bed count —
// the nearest free room of the same type — and that room is never offered
// to other families or members afterwards (the occupancy guard above marks
// it fully occupied for them). Blocks in different duration groups of the
// same family still share that one room. Rooms the operator already picked
// (or that were saved before) are never overwritten. Shared room types are
// left to the gender packer (autoAssignGenderRooms).
function autoAssignBedRooms($card) {
    if ($card.data('group') !== 'hotel') return;
    if (($card.data('hotel-split') || 'same') === 'same') return;
    const bd = $card.data('breakdown') || [];
    if (!bd.length) return;
    const membersById = {};
    bd.forEach(m => { membersById[m.booking_id] = m; });

    // Blocks still missing a room, grouped per hotel into family units. A
    // unit is the whole family (its stay blocks may be split across several
    // cards) and needs one room of the family's bed-count type.
    const unitsByHotel = {};
    $card.find('.fulfillment-stay').each(function() {
        const $st = $(this);
        if ($st.find('.f-room').val()) return; // already assigned
        const hotelId = $st.find('.f-hotel').val();
        if (!hotelId) return;
        const rtVal = $st.find('.f-room-type').val() || '';
        const rtName = rtVal ? String($st.find('.f-room-type option:selected').text() || '').trim() : '';
        const $gc = $st.closest('.f-hotel-group');
        const ids = $gc.data('member-ids');
        const first = (Array.isArray(ids) && ids.length) ? membersById[ids[0]] : null;
        const desired = rtName || String((first && first.room_type) || '').trim();
        if (!desired || isSharedRoomType(desired)) return; // Shared -> gender packer
        const fam = familyIdOfStay($st);
        const size = (Array.isArray(ids) && ids.length) ? ids.length : 1;
        const key = String(desired).toLowerCase() + '|' + fam;
        (unitsByHotel[hotelId] = unitsByHotel[hotelId] || {})[key] =
            unitsByHotel[hotelId][key] || { fam: fam, rt: desired, size: 0, blocks: [] };
        const u = unitsByHotel[hotelId][key];
        u.size += size;
        u.blocks.push($st);
    });

    Object.keys(unitsByHotel).forEach(hotelId => {
        // Biggest families first, so a 3-member family gets the 3 Beds room
        // before a single member grabs it.
        const units = Object.values(unitsByHotel[hotelId])
            .sort((a, b) => (b.size - a.size) || String(a.rt).localeCompare(String(b.rt)));
        const roomsByType = {};
        roomsOfHotel(hotelId).forEach(r => {
            const t = roomTypeNameOf(r.id).toLowerCase().trim();
            (roomsByType[t] = roomsByType[t] || []).push(r);
        });
        Object.keys(roomsByType).forEach(t =>
            roomsByType[t].sort((a, b) =>
                String(a.floor || '').localeCompare(String(b.floor || ''), undefined, { numeric: true }) ||
                roomNumberSort(a) - roomNumberSort(b)));
        const taken = {};
        units.forEach(u => {
            const probe = u.blocks[0];
            const cands = (roomsByType[String(u.rt).toLowerCase().trim()] || []).filter(r => {
                if (taken[r.id]) return false;
                const max = roomMaxOccupancy(r.id);
                if (max === null || max === undefined) return false;
                if (u.size > max) return false; // family needs a bigger room
                return roomOccupancy(r.id, probe).used === 0;
            });
            if (!cands.length) return; // no free room -> reported as remaining
            taken[cands[0].id] = 1;
            u.blocks.forEach($st => $st.data('auto-room', cands[0].id));
        });
    });

    // Switch each block's room type to its assigned room's type, then apply.
    let rtChanged = false;
    $card.find('.fulfillment-stay').each(function() {
        const rid = $(this).data('auto-room');
        if (rid === undefined || rid === null) return;
        const room = (fulfillmentData.rooms || []).find(r => String(r.id) === String(rid));
        if (!room) return;
        const rtId = String(room.room_type_id || '');
        const $rt = $(this).find('.f-room-type');
        if (rtId && $rt.find('option[value="' + rtId + '"]').length && $rt.val() !== rtId) {
            $rt.val(rtId);
            rtChanged = true;
        }
    });
    if (rtChanged) syncFulfillmentRoomOptions($card);
    $card.find('.fulfillment-stay').each(function() {
        const rid = $(this).data('auto-room');
        if (rid === undefined || rid === null) return;
        const $rm = $(this).find('.f-room');
        if ($rm.find('option[value="' + rid + '"]').length) {
            $rm.val(rid);
        }
        $(this).removeData('auto-room');
    });
    syncFulfillmentRoomOptions($card);
}

// Both auto packers + the coverage message, in one call (used on render and
// on every hotel / room-type / split change).
function autoAssignRooms($card) {
    autoAssignGenderRooms($card);
    autoAssignBedRooms($card);
    updateRoomNeedMessage($card);
}

// Coverage status of the hotel card: how many families/members got a room
// vs how many still need one. When rooms fall short, the card shows a
// warning with the exact counts ("X families and Y members assigned · X
// families and Y members remaining — please add more rooms"). Blocks
// without a hotel selected are ignored (nothing to assign yet).
function updateRoomNeedMessage($card) {
    const $el = $card.find('.f-room-need');
    if (!$el.length) return;
    const split = $card.data('hotel-split') || 'same';
    if ($card.data('group') !== 'hotel' || split === 'same') { $el.html(''); return; }
    const bd = $card.data('breakdown') || [];
    if (!bd.length) { $el.html(''); return; }
    const membersById = {};
    bd.forEach(m => { membersById[m.booking_id] = m; });

    const seen = {}, roomed = {};
    $card.find('.fulfillment-stay').each(function() {
        const $st = $(this);
        if (!$st.find('.f-hotel').val()) return;
        const ids = $st.closest('.f-hotel-group').data('member-ids');
        if (!Array.isArray(ids) || !ids.length) return;
        const hasRoom = !!$st.find('.f-room').val();
        ids.forEach(id => {
            seen[String(id)] = 1;
            if (hasRoom) roomed[String(id)] = 1;
        });
    });
    const remainingIds = Object.keys(seen).filter(id => !roomed[id]);
    if (!remainingIds.length) { $el.html(''); return; }
    const famsOf = (ids) => {
        const f = {};
        ids.forEach(id => {
            const fam = parseInt((membersById[id] || {}).family_id, 10) || 0;
            if (fam) f[fam] = 1;
        });
        return Object.keys(f).length;
    };
    const famWord = (n) => n + ' famil' + (n === 1 ? 'y' : 'ies');
    const memWord = (n) => n + ' member' + (n === 1 ? '' : 's');
    const famAssigned = famsOf(Object.keys(roomed));
    const memAssigned = Object.keys(roomed).length;
    const famRemaining = famsOf(remainingIds);
    const memRemaining = remainingIds.length;
    $el.html('<div class="alert alert-warning py-1 px-2 mt-2 mb-0" style="font-size:0.8rem;">' +
        '<i class="feather icon-alert-triangle mr-1"></i>' +
        '<b>' + famWord(famAssigned) + ' and ' + memWord(memAssigned) + ' assigned</b> · ' +
        '<b class="text-danger">' + famWord(famRemaining) + ' and ' + memWord(memRemaining) + ' remaining</b>' +
        ' — ' + __t('add_more_rooms') + '</div>');
}

// Aggregate hotel card wrapper: "Hotel Stays" header + the room-split
// selector. Only Per family / Per member are manual picks. Per duration
// (members on different packages get their own fields) and Per gender
// (Shared room types auto-assign same-gender members to the nearest rooms,
// males and females always apart) are decided automatically and shown as
// info tags — both can apply at once when a duration split AND shared
// rooms coexist. The selector never offers them.
function hotelSplitHtml(service, data, aggregate, splitMode) {
    const autoTag = autoSplitTagsHtml(service);
    const selector = aggregate ? `
    <label class="small mb-0 ml-auto" style="font-weight:500;color:#64748b;">${__t('assign_rooms')}:
        <select class="form-control form-control-sm d-inline-block ml-1 f-hotel-split" style="width:auto;">
            <option value="family" ${splitMode !== 'member' ? 'selected' : ''}>${__t('room_split_family')}</option>
            <option value="member" ${splitMode === 'member' ? 'selected' : ''}>${__t('room_split_member')}</option>
        </select>
        ${autoTag}
    </label>` : '';
    return `
    <div class="mt-2">
        <div class="mb-1 d-flex align-items-center flex-wrap" style="font-size:0.85rem;font-weight:600;color:#334155;">
            <i class="feather icon-bed mr-1" style="color:#0e7490;"></i>${__t('hotel_stays')}
            ${selector}
        </div>
        <div class="f-hotel-split-body">${hotelSplitBodyHtml(service, data, splitMode)}</div>
        <div class="f-contract-hint"></div>
        <div class="f-room-need"></div>
    </div>`;
}

// Auto-split info tags: "Per duration (auto)" when the covered members run
// on different package durations (normalized — "15 days" = "15" = 15), and
// "Per gender (auto)" when any member is booked on a Shared room type.
// Both tags can appear together.
function autoSplitTagsHtml(service) {
    const bd = Array.isArray(service.member_breakdown) ? service.member_breakdown : [];
    let html = '';
    if (bd.length && groupMembersByDuration(bd).size > 1) {
        html += `<span class="f-split-auto-tag text-muted small ml-2" style="font-size:0.8rem;"><i class="feather icon-zap mr-1" style="color:#0e7490;"></i>${__t('room_split_duration')} <span class="text-muted">(auto)</span></span>`;
    }
    if (bd.some(m => isSharedRoomType(m.room_type))) {
        html += `<span class="f-split-auto-tag text-muted small ml-2" style="font-size:0.8rem;"><i class="feather icon-zap mr-1" style="color:#0e7490;"></i>${__t('room_split_gender')} <span class="text-muted">(auto)</span></span>`;
    }
    return html;
}

// Room split default for aggregate hotel cards — the "top rank":
//   1. Per-duration when the covered members travel on different packages
//      (cards ordered by duration, then family, then gender).
//   2. Per-gender when EVERY member is booked on a Shared room type (hotels
//      pair males with males, females with females) — with automatic
//      same-gender nearest-room assignment.
//   3. Per-family otherwise (each member keeps their own check-in/check-out
//      even inside the same family).
// The operator can switch modes anytime.
function autoHotelSplitMode(service) {
    const bd = Array.isArray(service.member_breakdown) ? service.member_breakdown : [];
    if (!bd.length) return 'same';
    const shared = bd.every(m => isSharedRoomType(m.room_type));
    return shared ? 'gender' : 'family';
}

function renderFulfillmentServices(data) {
    const $container = $('#fulfillmentServicesContainer');
    $container.empty();

    // Optional BRN procurement cost — rendered even when no services exist.
    renderBrnCard(data);

    if (!data.services || data.services.length === 0) {
        $('#fulfillmentEmptyState').removeClass('d-none');
        return;
    }

    data.services.forEach(service => {
        const group = serviceGroupFor(service);
        const suppliersOptions = data.suppliers.map(s =>
            `<option value="${s.id}" data-currency="${escapeHtml(s.currency || 'USD')}" ${String(service.supplier_id) === String(s.id) ? 'selected' : ''}>${escapeHtml(s.name)}</option>`
        ).join('');

        const name = service.service_name || service.service_type || 'Service';
        const qty = parseFloat(service.quantity) || 1;
        const costUsd = parseFloat(service.cost_amount) || 0;
        const soldStatus = service.fulfill_status || service.sold_status || 'pending';

        let extra = '';
        let splitMode = 'same';
        if (group === 'hotel') {
            const aggregateHotel = currentFulfillmentMode !== 'member' && !!service.is_aggregate;
            const durGroups = aggregateHotel ? groupMembersByDuration(service.member_breakdown || []) : null;
            // Aggregate cards default to per-duration blocks when the covered
            // members travel on different packages; shared-room bookings
            // default to per-gender blocks with nearest-room auto-assignment;
            // otherwise per-family (per-member check-in/check-out).
            splitMode = aggregateHotel && durGroups !== null && durGroups.size > 1 ? 'duration' : (aggregateHotel ? autoHotelSplitMode(service) : 'same');
            extra = hotelSplitHtml(service, data, aggregateHotel, splitMode);
        } else if (group === 'flight') {
            // Rich flight details (mirrors the group ticket form): direct
            // (outbound + return with dates/times) or connecting (two legs
            // each way with live stopover duration). Direct data lives in the
            // flat fulfillment columns; connecting legs in service.flight_legs.
            const fType = service.flight_type || 'direct';
            const legs = Array.isArray(service.flight_legs) ? service.flight_legs : [];
            const fresh = !service.fulfillment_id;
            const legOf = (label, dCity, aCity, fNo) => {
                const l = legs.find(x => x && x.label === label) || {};
                return {
                    dep_city: l.dep_city || (fresh ? dCity : ''),
                    arr_city: l.arr_city || (fresh ? aCity : ''),
                    flight_no: l.flight_no || (fresh ? fNo : ''),
                    dep_date: l.dep_date || '', dep_time: l.dep_time || '',
                    arr_date: l.arr_date || '', arr_time: l.arr_time || ''
                };
            };
            const o1 = legOf('outbound_1', 'Kabul', 'Dubai', 'FZ341');
            const o2 = legOf('outbound_2', 'Dubai', 'Jeddah', 'FZ415');
            const r1 = legOf('return_1', 'Jeddah', 'Dubai', 'FZ416');
            const r2 = legOf('return_2', 'Dubai', 'Kabul', 'FZ342');
            const splitDT = (v) => {
                if (!v) return { d: '', t: '' };
                const p = String(v).replace('T', ' ').split(' ');
                return { d: p[0] || '', t: p[1] ? p[1].slice(0, 5) : '' };
            };
            const dep = splitDT(service.departure_time), arr = splitDT(service.arrival_time);
            const rdep = splitDT(service.return_departure_time), rarr = splitDT(service.return_arrival_time);

            // Aggregate (family/group) view: when the covered members have
            // DIFFERENT package durations (e.g. 15 vs 21 days), the card shows
            // per-duration-group flight details instead of one shared set.
            const aggregateFlight = currentFulfillmentMode !== 'member' && !!service.is_aggregate;
            const durGroups = aggregateFlight ? groupMembersByDuration(service.member_breakdown) : null;
            const groupedMode = aggregateFlight && durGroups !== null && durGroups.size > 1;

            const legCard = (title, icon, leg, arrLabel, stopId) => `
                <div class="card mb-2 fulfillment-leg">
                    <div class="card-header bg-light py-1">
                        <h6 class="mb-0" style="font-size:0.85rem;color:#334155;"><i class="feather ${icon} mr-2"></i>${title}</h6>
                    </div>
                    <div class="card-body py-2">
                        <div class="row">
                            <div class="form-group col-md-4 mb-2">
                                <label class="small mb-1 text-muted">${__t('departure_city')}</label>
                                <input type="text" class="form-control form-control-sm f-leg-dep-city" value="${escapeHtml(leg.dep_city)}">
                            </div>
                            <div class="form-group col-md-4 mb-2">
                                <label class="small mb-1 text-muted">${__t(arrLabel)}</label>
                                <input type="text" class="form-control form-control-sm f-leg-arr-city" value="${escapeHtml(leg.arr_city)}">
                            </div>
                            <div class="form-group col-md-4 mb-2">
                                <label class="small mb-1 text-muted">${__t('flight_number')}</label>
                                <input type="text" class="form-control form-control-sm f-leg-flight-no" value="${escapeHtml(leg.flight_no)}">
                            </div>
                        </div>
                        <div class="row">
                            <div class="form-group col-md-3 mb-2">
                                <label class="small mb-1 text-muted">${__t('departure_date')}</label>
                                <input type="date" class="form-control form-control-sm f-leg-dep-date" value="${escapeHtml(leg.dep_date)}">
                            </div>
                            <div class="form-group col-md-3 mb-2">
                                <label class="small mb-1 text-muted">${__t('departure_time')}</label>
                                <input type="time" class="form-control form-control-sm f-leg-dep-time" value="${escapeHtml(leg.dep_time)}">
                            </div>
                            <div class="form-group col-md-3 mb-2">
                                <label class="small mb-1 text-muted">${__t('arrival_date')}</label>
                                <input type="date" class="form-control form-control-sm f-leg-arr-date" value="${escapeHtml(leg.arr_date)}">
                            </div>
                            <div class="form-group col-md-3 mb-2">
                                <label class="small mb-1 text-muted">${__t('arrival_time')}</label>
                                <input type="time" class="form-control form-control-sm f-leg-arr-time" value="${escapeHtml(leg.arr_time)}">
                            </div>
                        </div>
                        ${stopId ? `<div class="alert alert-warning py-1 px-2 mb-0" style="font-size:0.8rem;">
                            <i class="feather icon-clock mr-1"></i><strong>${__t('stopover_duration')}:</strong> <span class="f-stopover-span">${__t('calculating')}...</span>
                        </div>` : ''}
                    </div>
                </div>`;

            if (groupedMode) {
                extra = groupedFlightExtraHtml(service, durGroups, fType);
            } else {
            extra = `
            <div class="mt-2 fulfillment-type-fields">
                <div class="row">
                    <div class="form-group col-md-4">
                        <label>${__t('ticket_number')}</label>
                        <input type="text" class="form-control form-control-sm f-ticket" value="${escapeHtml(service.ticket_number || '')}">
                    </div>
                    <div class="form-group col-md-4">
                        <label>PNR</label>
                        <input type="text" class="form-control form-control-sm f-pnr" value="${escapeHtml(service.pnr || '')}">
                    </div>
                    <div class="form-group col-md-4">
                        <label>${__t('airline')}</label>
                        <input type="text" class="form-control form-control-sm f-airline" value="${escapeHtml(service.airline || '')}">
                    </div>
                </div>
                <div class="row mb-2">
                    <div class="col-md-12">
                        <label class="form-label small mb-1 text-muted">${__t('flight_type')}</label>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input f-flight-type" type="radio" name="f-flight-type-${service.booking_service_id}" value="direct" ${fType !== 'indirect' ? 'checked' : ''}>
                            <label class="form-check-label">${__t('direct_flight')}</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input f-flight-type" type="radio" name="f-flight-type-${service.booking_service_id}" value="indirect" ${fType === 'indirect' ? 'checked' : ''}>
                            <label class="form-check-label">${__t('connecting_flight')}</label>
                        </div>
                    </div>
                </div>
                <div class="f-flight-direct" ${fType === 'indirect' ? 'style="display:none;"' : ''}>
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label>${__t('departure_city')}</label>
                            <input type="text" class="form-control form-control-sm f-dep-city" value="${escapeHtml(service.departure_city || (fresh ? 'Kabul' : ''))}">
                        </div>
                        <div class="form-group col-md-6">
                            <label>${__t('arrival_city')}</label>
                            <input type="text" class="form-control form-control-sm f-arr-city" value="${escapeHtml(service.arrival_city || (fresh ? 'Jeddah' : ''))}">
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label>${__t('outbound_flight_number')}</label>
                            <input type="text" class="form-control form-control-sm f-flight-no" value="${escapeHtml(service.flight_number || (fresh ? 'RQ993' : ''))}">
                        </div>
                        <div class="form-group col-md-6">
                            <label>${__t('return_flight_number')}</label>
                            <input type="text" class="form-control form-control-sm f-return-flight" value="${escapeHtml(service.return_flight_number || (fresh ? 'RQ994' : ''))}">
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-md-3">
                            <label class="small">${__t('departure_date')}</label>
                            <input type="date" class="form-control form-control-sm f-dep-date" value="${escapeHtml(dep.d)}">
                        </div>
                        <div class="form-group col-md-3">
                            <label class="small">${__t('departure_time')}</label>
                            <input type="time" class="form-control form-control-sm f-dep-time" value="${escapeHtml(dep.t)}">
                        </div>
                        <div class="form-group col-md-3">
                            <label class="small">${__t('arrival_date')}</label>
                            <input type="date" class="form-control form-control-sm f-arr-date" value="${escapeHtml(arr.d)}">
                        </div>
                        <div class="form-group col-md-3">
                            <label class="small">${__t('arrival_time')}</label>
                            <input type="time" class="form-control form-control-sm f-arr-time" value="${escapeHtml(arr.t)}">
                        </div>
                    </div>
                    <hr>
                    <h6 class="text-primary mb-2" style="font-size:0.9rem;"><i class="feather icon-corner-up-left mr-2"></i>${__t('return_journey')}</h6>
                    <div class="row">
                        <div class="form-group col-md-3">
                            <label class="small">${__t('departure_date')}</label>
                            <input type="date" class="form-control form-control-sm f-return-dep-date" value="${escapeHtml(rdep.d)}">
                        </div>
                        <div class="form-group col-md-3">
                            <label class="small">${__t('departure_time')}</label>
                            <input type="time" class="form-control form-control-sm f-return-dep-time" value="${escapeHtml(rdep.t)}">
                        </div>
                        <div class="form-group col-md-3">
                            <label class="small">${__t('arrival_date')}</label>
                            <input type="date" class="form-control form-control-sm f-return-arr-date" value="${escapeHtml(rarr.d)}">
                        </div>
                        <div class="form-group col-md-3">
                            <label class="small">${__t('arrival_time')}</label>
                            <input type="time" class="form-control form-control-sm f-return-arr-time" value="${escapeHtml(rarr.t)}">
                        </div>
                    </div>
                </div>
                <div class="f-flight-indirect" ${fType === 'indirect' ? '' : 'style="display:none;"'}>
                    <h6 class="text-primary mb-2" style="font-size:0.9rem;"><i class="feather icon-arrow-right mr-2"></i>${__t('outbound_journey')}</h6>
                    ${legCard(__t('first_leg'), 'icon-arrow-right', o1, 'stopover_city', true)}
                    ${legCard(__t('second_leg'), 'icon-arrow-right', o2, 'final_destination', false)}
                    <hr>
                    <h6 class="text-success mb-2" style="font-size:0.9rem;"><i class="feather icon-corner-up-left mr-2"></i>${__t('return_journey')}</h6>
                    ${legCard(__t('return_first_leg'), 'icon-arrow-left', r1, 'stopover_city', true)}
                    ${legCard(__t('return_second_leg'), 'icon-arrow-left', r2, 'final_destination', false)}
                </div>
                <div class="mt-2">
                    <button type="button" class="btn btn-sm btn-outline-primary btn-print-ticket" ${service.fulfillment_id ? '' : 'disabled'}>
                        <i class="feather icon-printer mr-1"></i>${__t('print_ticket')}
                    </button>
                </div>
            </div>`;
            }
        } else if (group === 'transport') {
            extra = `
            <div class="row mt-2 fulfillment-type-fields">
                <div class="form-group col-md-4">
                    <label>${__t('vehicle')}</label>
                    <input type="text" class="form-control form-control-sm f-vehicle" value="${escapeHtml(service.transport_vehicle || '')}" placeholder="${__t('vehicle_placeholder')}">
                </div>
                <div class="form-group col-md-3">
                    <label>${__t('trip_date')}</label>
                    <input type="date" class="form-control form-control-sm f-trip-date" value="${escapeHtml(service.transport_trip_date || '')}">
                </div>
            </div>
            <div class="f-contract-hint"></div>`;
        }

        const isFrozen = isFulfillmentFrozen(service.fulfill_status || '');

        let coverageChip = '';
        if (currentFulfillmentMode !== 'member' && service.is_aggregate) {
            const famLabel = service.families_applicable === 1 ? 'family' : 'families';
            const memLabel = service.members_applicable === 1 ? 'member' : 'members';
            coverageChip = `<span class="fulfillment-chip fulfillment-chip-optional ml-1" title="${service.coverage_skipped ? 'Skipped: ' + escapeHtml(JSON.stringify(service.skip_breakdown || {})) : 'Applies to every member with this service'}" style="cursor:default;">
                covers ${service.families_applicable} ${famLabel} · ${service.members_applicable} ${memLabel}
                ${service.coverage_skipped ? ' <span style="opacity:.75;">· ' + service.coverage_skipped + ' skipped</span>' : ''}
            </span>`;
        }

        const card = `
        <div class="card mb-3 fulfillment-service-card" data-booking-service-id="${service.booking_service_id}" data-service-id="${service.service_id || ''}" data-qty="${qty}" data-group="${group}" data-frozen="${isFrozen ? 1 : 0}" data-cost="${service.cost_amount !== null && service.cost_amount !== undefined ? service.cost_amount : ''}" data-orig-rate="${service.exchange_rate !== null && service.exchange_rate !== undefined ? service.exchange_rate : ''}" data-planned="${service.planned_date || ''}" data-completed="${service.completed_date || ''}">
            <div class="card-header bg-light d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <strong>${escapeHtml(name)}</strong>
                    <span class="fulfillment-chip ml-2">${qty} × ${escapeHtml(service.pricing_unit || '')} ${service.is_optional ? '<span class="fulfillment-chip fulfillment-chip-optional ml-1">optional</span>' : ''}</span>
                    ${coverageChip}
                    <span class="ml-2 text-muted" style="font-size: 0.85rem;">
                        Cost: <b class="f-card-cost">${costUsd.toFixed(2)}</b> <span class="f-card-cost-cur">${escapeHtml(currentFulfillmentCurrency)}</span>
                    </span>
                </div>
                <span class="fulfillment-status f-status-badge">${escapeHtml(soldStatus)}</span>
            </div>
            <div class="card-body pt-3">
                <div class="row">
                    <div class="form-group col-md-12">
                        <label>${__t('supplier')}</label>
                        <select class="form-control form-control-sm f-supplier">
                            <option value="">— ${__t('select_supplier')} —</option>
                            ${suppliersOptions}
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="form-group col-md-3">
                        <label>Currency</label>
                        <input type="text" class="form-control form-control-sm f-currency" data-orig="${escapeHtml(service.supplier_currency || '')}" value="${escapeHtml(service.supplier_currency || 'USD')}" ${isFrozen ? 'readonly' : ''}>
                    </div>
                    <div class="form-group col-md-3">
                        <label>Cost</label>
                        <input type="number" class="form-control form-control-sm f-cost" min="0" step="0.01" value="${service.supplier_cost !== null ? service.supplier_cost : ''}" ${isFrozen ? 'readonly' : ''}>
                    </div>
                    <div class="form-group col-md-3 f-rate-field" style="display: none;">
                        <label>Rate</label>
                        <input type="number" class="form-control form-control-sm f-rate" min="0" step="0.0001" value="${service.exchange_rate !== null ? service.exchange_rate : ''}" ${isFrozen ? 'readonly' : ''}>
                    </div>
                    <div class="form-group col-md-3">
                        <label>Cost (${currentFulfillmentCurrency})</label>
                        <input type="number" class="form-control form-control-sm f-cost-usd" readonly value="${service.cost_amount !== null ? service.cost_amount : ''}">
                    </div>
                </div>
                ${extra}
                <div class="row">
                    <div class="form-group col-md-12">
                        <label>${__t('notes')}</label>
                        <input type="text" class="form-control form-control-sm f-notes" value="${escapeHtml(service.notes || '')}">
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center flex-wrap mt-2">
                    ${currentFulfillmentMode === 'family' ? `
                    <label class="mb-0" style="font-size:0.85rem;">Apply to:
                        <select class="form-control form-control-sm d-inline-block ml-1 f-scope" style="width:auto;">
                            <option value="family">All family members</option>
                            <option value="group">Entire group</option>
                        </select>
                    </label>` : (currentFulfillmentMode === 'group' ? `
                    <span class="mb-0" style="font-size:0.85rem;">
                        <i class="feather icon-users mr-1" style="color:#0e7490;"></i>Applying to <b>entire group</b>${currentFulfillmentGroupName ? ': ' + escapeHtml(currentFulfillmentGroupName) : ''}
                        <input type="hidden" class="f-scope" value="group">
                    </span>` : '<span></span>')}
                    <div>
                        <button type="button" class="btn btn-sm btn-primary btn-save-fulfillment">
                            <i class="feather icon-save mr-1"></i>${__t('save')}
                        </button>
                    </div>
                </div>
            </div>
        </div>`;

        $container.append(card);

        // Contract pricing runs first so contract rates win over the package's
        // suggested supplier cost — the suggestion never overwrites a filled cost.
        const $card = $container.children().last();
        $card.data('breakdown', service.member_breakdown || []);
        $card.data('stays', service.hotel_stays || []);
        $card.data('hotel-split', group === 'hotel' ? (splitMode || 'same') : 'same');
        syncFulfillmentHotelOptions($card);
        applyContractPricing($card);
        applyTransportContractPricing($card);
        if ($card.find('.f-supplier').val()) {
            applySuggestion($card);
        }
        syncFulfillmentRateField($card);
        calcFulfillmentStopover($card);
    });

    bindFulfillmentEvents();

    // Room dropdowns are built while cards are being appended one by one, so
    // re-sync once everything is in the DOM to include occupancy from every
    // stay block of the modal (member counts update live from here on).
    $container.find('.fulfillment-service-card').each(function() {
        syncFulfillmentRoomOptions($(this));
        autoAssignRooms($(this));
    });
}

// Optional BRN (Booking Reference Number) procurement cost. Member mode: one
// record for the member's booking; family/group mode: the shared cost is bulk
// applied to every covered member (one umrah_brn_costs row per booking) via
// the f-scope selector. Reuses the standard card cost fields so the currency /
// rate helpers and the profit summary work unchanged (data-cost feeds
// updateFulfillmentSummary).
function renderBrnCard(data) {
    if (currentFulfillmentMode !== 'member' && !currentFulfillmentFamilyId) return;
    const brnRows = Array.isArray(data.brn_costs) ? data.brn_costs : [];
    const totalCost = brnRows.reduce((a, r) => a + (parseFloat(r.cost_amount) || 0), 0);
    const pre = brnRows[0] || {};
    const suppliersOptions = data.suppliers.map(s =>
        `<option value="${s.id}" data-currency="${escapeHtml(s.currency || 'USD')}" ${String(pre.supplier_id) === String(s.id) ? 'selected' : ''}>${escapeHtml(s.name)}</option>`
    ).join('');
    const scopeSelector = currentFulfillmentMode === 'family' ? `
        <label class="mb-0" style="font-size:0.85rem;">${__t('apply_to')}
            <select class="form-control form-control-sm d-inline-block ml-1 f-scope" style="width:auto;">
                <option value="family">${__t('all_family_members')}</option>
                <option value="group">${__t('entire_group')}</option>
            </select>
        </label>` : (currentFulfillmentMode === 'group' ? `
        <span class="mb-0" style="font-size:0.85rem;">
            <i class="feather icon-users mr-1" style="color:#0e7490;"></i>${__t('applying_to')} <b>${__t('entire_group')}</b>${currentFulfillmentGroupName ? ': ' + escapeHtml(currentFulfillmentGroupName) : ''}
            <input type="hidden" class="f-scope" value="group">
        </span>` : '<span></span>');
    const html = `
    <div class="card mb-3 fulfillment-service-card fulfillment-brn-card" data-group="brn" data-cost="${totalCost}">
        <div class="card-header bg-light d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <strong>BRN <span class="text-muted">(${__t('booking_reference_number')})</span></strong>
                <span class="fulfillment-chip fulfillment-chip-optional ml-2">optional</span>
                <span class="ml-2 text-muted" style="font-size: 0.85rem;">
                    Cost: <b class="f-card-cost">${totalCost.toFixed(2)}</b> <span class="f-card-cost-cur">${escapeHtml(currentFulfillmentCurrency)}</span>
                </span>
            </div>
            <button type="button" class="btn btn-sm btn-light" data-toggle="collapse" data-target="#fulfillmentBrnCollapse" aria-expanded="false" aria-controls="fulfillmentBrnCollapse" title="${__t('show_hide_brn_fields')}">
                <i class="feather icon-chevron-down"></i>
            </button>
        </div>
        <div class="collapse" id="fulfillmentBrnCollapse">
        <div class="card-body pt-3">
            <div class="row">
                <div class="form-group col-md-12">
                    <label>${__t('supplier')}</label>
                    <select class="form-control form-control-sm f-supplier">
                        <option value="">— ${__t('select_supplier')} —</option>
                        ${suppliersOptions}
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="form-group col-md-3">
                    <label>Currency</label>
                    <input type="text" class="form-control form-control-sm f-currency" value="${escapeHtml(pre.supplier_currency || 'USD')}">
                </div>
                <div class="form-group col-md-3">
                    <label>Cost</label>
                    <input type="number" class="form-control form-control-sm f-cost" min="0" step="0.01" value="${pre.supplier_cost !== null && pre.supplier_cost !== undefined ? pre.supplier_cost : ''}">
                    <div class="small text-muted mt-1" style="font-size:0.75rem;">${__t('enter_rate_per_member')}</div>
                </div>
                <div class="form-group col-md-3 f-rate-field" style="display: none;">
                    <label>Rate</label>
                    <input type="number" class="form-control form-control-sm f-rate" min="0" step="0.0001" value="${pre.exchange_rate !== null && pre.exchange_rate !== undefined ? pre.exchange_rate : ''}">
                </div>
                <div class="form-group col-md-3">
                    <label>Cost (${escapeHtml(currentFulfillmentCurrency)})</label>
                    <input type="number" class="form-control form-control-sm f-cost-usd" readonly value="${totalCost ? totalCost : ''}">
                </div>
            </div>
            <div class="row">
                <div class="form-group col-md-12">
                    <label>${__t('notes')}</label>
                    <input type="text" class="form-control form-control-sm f-notes" value="${escapeHtml(pre.notes || '')}">
                </div>
            </div>
            <div class="d-flex justify-content-between align-items-center flex-wrap mt-2">
                ${scopeSelector}
                <div>
                    <button type="button" class="btn btn-sm btn-primary btn-save-brn">
                        <i class="feather icon-save mr-1"></i>${__t('save')}
                    </button>
                </div>
            </div>
            <div class="small text-muted mt-2" style="font-size:0.8rem;">
                <i class="feather icon-info mr-1" style="color:#0e7490;"></i>${__t('remove_brn_hint')}
            </div>
        </div>
        </div>
    </div>`;
    $('#fulfillmentServicesContainer').append(html);
    const $card = $('#fulfillmentServicesContainer').children().last();
    $card.data('cost', totalCost);
    if ($card.find('.f-supplier').val()) {
        applySuggestion($card);
    }
    syncFulfillmentRateField($card);
}

function applySuggestion($card) {
    const serviceId = $card.data('service-id');
    const supplierId = $card.find('.f-supplier').val();
    // A cost filled by contract auto-pricing is authoritative — the suggestion
    // only fills empty cost/currency fields.
    const hasCost = $card.find('.f-cost').val() !== '';
    if (!supplierId) {
        $card.find('.f-currency').val('');
        updateFulfillmentCostUsd($card);
        syncFulfillmentRateField($card);
        return;
    }
    const supCurrency = ($card.find('.f-supplier option:selected').data('currency') || '').toString().toUpperCase();
    if (supCurrency && !hasCost) $card.find('.f-currency').val(supCurrency);
    if (!serviceId) {
        updateFulfillmentCostUsd($card);
        syncFulfillmentRateField($card);
        return;
    }
    updateFulfillmentCostUsd($card);
    syncFulfillmentRateField($card);
}

function syncFulfillmentRateField($card) {
    const $field = $card.find('.f-rate-field');
    const hasSupplier = !!$card.find('.f-supplier').val() || !!$card.find('.f-currency').attr('data-orig');
    const cur = ($card.find('.f-currency').val() || '').trim().toUpperCase();
    const differs = hasSupplier && !!cur && cur !== currentFulfillmentCurrency;
    const keepRate = differs || !!$card.data('orig-rate') || $card.data('frozen') == 1;
    if (!keepRate) $card.find('.f-rate').val('');
    $field.toggle(keepRate);
}

function updateFulfillmentCostUsd($card) {
    const cur = ($card.find('.f-currency').val() || currentFulfillmentCurrency).trim().toUpperCase();
    const cost = parseFloat($card.find('.f-cost').val()) || 0;
    if (!cur || cur === currentFulfillmentCurrency) {
        $card.find('.f-cost-usd').val(cost.toFixed(2));
        return;
    }
    const rate = parseFloat($card.find('.f-rate').val()) || 0;
    $card.find('.f-cost-usd').val(rate > 0 ? (cost / rate).toFixed(2) : '');
}

// Hotel dropdowns (one per hotel stay) show only hotels belonging to the
// selected supplier (hotels are managed with a supplier in hotel management).
function syncFulfillmentHotelOptions($card) {
    const supplierId = $card.find('.f-supplier').val();
    $card.find('.f-hotel').each(function() {
        const $hotel = $(this);
        const current = $hotel.val();
        let clear = false;
        $hotel.find('option').each(function() {
            const matches = !supplierId || !$(this).val() || String($(this).data('supplier')) === String(supplierId);
            $(this).toggle(matches);
            if ($(this).val() === current && !matches) clear = true;
        });
        if (clear) $hotel.val('');
    });
    syncFulfillmentRoomOptions($card);
}

function syncFulfillmentRoomOptions($card) {
    // Room types are scoped to the SELECTED hotel — only types that actually
    // have rooms created for that hotel are offered (fallback: all types when
    // the hotel has no rooms configured yet). The Room dropdown lists that
    // hotel's rooms, narrowed by the chosen room type.
    $card.find('.fulfillment-stay').each(function() {
        const $st = $(this);
        const hotelId = $st.find('.f-hotel').val() || '';
        const curRt = $st.find('.f-room-type').val();
        const curRoom = $st.find('.f-room').val();

        const rtOptions = `<option value="">—</option>` + roomTypeOptionsFor(hotelId).map(rt =>
            `<option value="${rt.id}">${escapeHtml(rt.name)}</option>`).join('');
        $st.find('.f-room-type').html(rtOptions);
        if (curRt && $st.find('.f-room-type option[value="' + curRt + '"]').length) {
            $st.find('.f-room-type').val(curRt);
        }

        const rt = $st.find('.f-room-type').val() || '';
        // Full rooms are filtered out so they cannot be picked by other
        // families/members sharing the modal (the block's own selection is
        // kept). Labels still show the live used/capacity counts.
        $st.find('.f-room').html(`<option value="">—</option>` + roomsOfHotel(hotelId)
            .filter(r => !rt || String(r.room_type_id) === String(rt))
            .filter(r => !roomIsUnavailable(r.id, $st, curRoom))
            .map(r => {
                const max = roomMaxOccupancy(r.id);
                const occ = roomOccupancy(r.id, $st);
                return `<option value="${r.id}">${roomOptionLabel(r, occ.used, max, occ.extraBeds)}</option>`;
            }).join(''));
        if (curRoom && $st.find('.f-room option[value="' + curRoom + '"]').length) {
            $st.find('.f-room').val(curRoom);
        }
    });
}

// Auto-pricing from hotel contracts. A contract covering every selected
// hotel applies either nightly rates (period) or splits the contracted
// amount across the trip's members (per_trip). Runs on open and when
// hotels/room types/nights change; manual edits to cost fields stop
// further overwrites (data-auto-cost flag).
function applyContractPricing($card) {
    if ($card.data('group') !== 'hotel') return;
    const stays = [];
    $card.find('.fulfillment-stay').each(function() {
        const hotel = $(this).find('.f-hotel').val();
        if (!hotel) return;
        stays.push({
            hotel: String(hotel),
            rt: $(this).find('.f-room-type').val() || '',
            nights: parseFloat($(this).find('.f-nights').val()) || 0,
            $el: $(this)
        });
    });
    const $hint = $card.find('.f-contract-hint');
    if (!stays.length) { if ($hint) $hint.html(''); return; }

    const hotelIds = stays.map(s => s.hotel);
    const candidates = (fulfillmentData.contracts || []).filter(c =>
        hotelIds.every(h => (c.hotels || []).map(String).indexOf(h) !== -1)
    );
    if (!candidates.length) { if ($hint) $hint.html(''); return; }
    candidates.sort((a, b) => String(b.valid_from || '').localeCompare(String(a.valid_from || '')));
    const contract = candidates[0];

    const typeLabel = contract.contract_type === 'per_trip' ? __t('per_trip') : __t('contract_type_period');
    $hint.html('<div class="small text-muted mt-1"><i class="feather icon-file-text mr-1" style="color:#0e7490;"></i>' +
        __t('contract') + ': <b>' + escapeHtml(contract.contract_number || '—') + '</b> · ' + escapeHtml(typeLabel) + '</div>');

    const $cost = $card.find('.f-cost');
    const $currency = $card.find('.f-currency');
    const existing = $cost.val() ? parseFloat($cost.val()) : null;
    const autoMode = $card.data('auto-cost') === 1 || existing === null;
    const qty = parseFloat($card.data('qty')) || 1;

    if (contract.contract_type === 'per_trip') {
        if (autoMode && contract.per_member_cost !== null && contract.per_member_cost !== undefined) {
            $currency.val(contract.contract_currency || 'USD');
            $cost.val((Number(contract.per_member_cost) * qty).toFixed(2));
            $card.data('auto-cost', 1);
        }
    } else {
        let total = 0;
        let currency = '';
        let foundRate = false;
        stays.forEach(s => {
            const exact = (contract.rates || []).find(r =>
                String(r.hotel_id) === s.hotel && String(r.room_type_id || '') === s.rt);
            const rate = exact || (contract.rates || []).find(r => String(r.hotel_id) === s.hotel);
            if (!rate || rate.cost_price === null || rate.cost_price === undefined) return;
            const price = parseFloat(rate.cost_price);
            if (isNaN(price)) return;
            foundRate = true;
            if (autoMode && (!s.$el.find('.f-nightly-rate').val() || $card.data('auto-cost') === 1)) {
                s.$el.find('.f-nightly-rate').val(price);
            }
            total += s.nights * price;
            currency = rate.cost_currency || currency;
        });
        if (foundRate && autoMode) {
            $currency.val(currency || 'USD');
            if (stays.every(s => s.nights > 0)) $cost.val((total * qty).toFixed(2));
            $card.data('auto-cost', 1);
        }
    }
    updateFulfillmentCostUsd($card);
    syncFulfillmentRateField($card);
}

// Auto-pricing from transport contracts. Transport contracts are
// amount-based: the contracted amount is divided among the trip's active
// members, and the contract is matched by the selected supplier.
function applyTransportContractPricing($card) {
    if ($card.data('group') !== 'transport') return;
    const supplierId = $card.find('.f-supplier').val();
    const $hint = $card.find('.f-contract-hint');
    if (!supplierId) { if ($hint) $hint.html(''); return; }
    const candidates = (fulfillmentData.transport_contracts || []).filter(c =>
        String(c.supplier_id || '') === String(supplierId));
    if (!candidates.length) { if ($hint) $hint.html(''); return; }
    candidates.sort((a, b) => String(b.valid_from || '').localeCompare(String(a.valid_from || '')));
    const contract = candidates[0];

    const typeLabel = contract.contract_type === 'per_trip' ? __t('per_trip') : __t('contract_type_period');
    $hint.html('<div class="small text-muted mt-1"><i class="feather icon-file-text mr-1" style="color:#0e7490;"></i>' +
        __t('contract') + ': <b>' + escapeHtml(contract.contract_number || '—') + '</b> · ' + escapeHtml(typeLabel) +
        (contract.member_count ? ' · <b>' + contract.member_count + '</b> ' + __t('trip_members') : '') + '</div>');

    const $cost = $card.find('.f-cost');
    const $currency = $card.find('.f-currency');
    const existing = $cost.val() ? parseFloat($cost.val()) : null;
    const autoMode = $card.data('auto-cost') === 1 || existing === null;
    if (autoMode && contract.per_member_cost !== null && contract.per_member_cost !== undefined) {
        const qty = parseFloat($card.data('qty')) || 1;
        $currency.val(contract.contract_currency || 'USD');
        $cost.val((Number(contract.per_member_cost) * qty).toFixed(2));
        $card.data('auto-cost', 1);
    }
    updateFulfillmentCostUsd($card);
    syncFulfillmentRateField($card);
}

// Live stopover duration: leg N arrival -> leg N+1 departure, per journey
// (first leg card carries the <span>, the next .fulfillment-leg is its pair).
function calcFulfillmentStopover($card) {
    $card.find('.fulfillment-leg').each(function() {
        const $span = $(this).find('.f-stopover-span');
        if (!$span.length) return;
        const $next = $(this).next('.fulfillment-leg');
        if (!$next.length) return;
        const aD = $(this).find('.f-leg-arr-date').val();
        const aT = $(this).find('.f-leg-arr-time').val();
        const dD = $next.find('.f-leg-dep-date').val();
        const dT = $next.find('.f-leg-dep-time').val();
        if (!(aD && aT && dD && dT)) return;
        const diffMs = new Date(dD + 'T' + dT) - new Date(aD + 'T' + aT);
        if (isNaN(diffMs)) return;
        const h = Math.max(0, Math.floor(diffMs / 3600000));
        const m = Math.max(0, Math.floor((diffMs % 3600000) / 60000));
        $span.text(h + 'h ' + m + 'm');
    });
}

function bindFulfillmentEvents() {
    $(document).off('change.fulfillment', '.f-flight-type').on('change.fulfillment', '.f-flight-type', function() {
        const $card = $(this).closest('.fulfillment-service-card');
        const indirect = $(this).val() === 'indirect';
        $card.find('.f-flight-direct').toggle(!indirect);
        $card.find('.f-flight-indirect').toggle(indirect);
    });

    // Live stopover duration between leg1 arrival and leg2 departure
    $(document).off('input.fulfillment', '.f-leg-dep-date, .f-leg-dep-time, .f-leg-arr-date, .f-leg-arr-time').on('input.fulfillment', '.f-leg-dep-date, .f-leg-dep-time, .f-leg-arr-date, .f-leg-arr-time', function() {
        calcFulfillmentStopover($(this).closest('.fulfillment-service-card'));
    });

    $(document).off('click.fulfillment', '.btn-print-ticket').on('click.fulfillment', '.btn-print-ticket', function() {
        const $btn = $(this);
        const $card = $btn.closest('.fulfillment-service-card');
        const serviceId = $btn.data('booking-service-id') || $card.data('booking-service-id');
        if (serviceId) {
            window.open('../api/umrah/generate_fulfillment_ticket.php?booking_service_id=' + serviceId, '_blank');
        }
    });

    // Aggregate flight card with differing durations: Same Departure /
    // Same PNR checkboxes reveal per-duration-group fields.
    $(document).off('change.fulfillment', '.f-same-dep').on('change.fulfillment', '.f-same-dep', function() {
        const $card = $(this).closest('.fulfillment-service-card');
        const shared = $(this).is(':checked');
        $card.find('.f-same-dep-fields').toggle(shared);
        $card.find('.f-flight-group .f-g-dep-wrap').toggle(!shared);
    });

    $(document).off('change.fulfillment', '.f-same-pnr').on('change.fulfillment', '.f-same-pnr', function() {
        const $card = $(this).closest('.fulfillment-service-card');
        const shared = $(this).is(':checked');
        $card.find('.f-same-pnr-fields').toggle(shared);
        $card.find('.f-flight-group .f-g-pnr-row').toggle(!shared);
    });

    $(document).off('change.fulfillment', '.f-supplier').on('change.fulfillment', '.f-supplier', function() {
        const $card = $(this).closest('.fulfillment-service-card');
        syncFulfillmentHotelOptions($card);
        applyContractPricing($card);
        applyTransportContractPricing($card);
        applySuggestion($card);
    });

    $(document).off('input.fulfillment', '.f-cost, .f-rate, .f-currency').on('input.fulfillment', '.f-cost, .f-rate, .f-currency', function() {
        const $card = $(this).closest('.fulfillment-service-card');
        $card.data('auto-cost', 0);
        updateFulfillmentCostUsd($card);
        syncFulfillmentRateField($card);
    });

    $(document).off('input.fulfillment', '.f-nightly-rate').on('input.fulfillment', '.f-nightly-rate', function() {
        $(this).closest('.fulfillment-service-card').data('auto-cost', 0);
    });

    $(document).off('change.fulfillment', '.f-hotel').on('change.fulfillment', '.f-hotel', function() {
        const $card = $(this).closest('.fulfillment-service-card');
        syncFulfillmentHotelOptions($card);
        applyContractPricing($card);
        autoAssignRooms($card);
    });

    $(document).off('change.fulfillment', '.f-room-type, .f-nights').on('change.fulfillment', '.f-room-type, .f-nights', function() {
        const $card = $(this).closest('.fulfillment-service-card');
        syncFulfillmentRoomOptions($card);
        applyContractPricing($card);
        autoAssignRooms($card);
    });

    // Room occupancy counts depend on the stay dates, the room chosen and
    // the extra-bed toggles on the room's other stay blocks — refresh the
    // room dropdowns whenever any of them changes so the used/max labels
    // stay in sync and full rooms drop out of the other lists.
    $(document).off('change.fulfillment', '.f-check-in, .f-check-out, .f-room, .f-extra-bed').on('change.fulfillment', '.f-check-in, .f-check-out, .f-room, .f-extra-bed', function() {
        const $card = $(this).closest('.fulfillment-service-card');
        syncFulfillmentRoomOptions($card);
        updateRoomNeedMessage($card);
    });

    // Room split selector (aggregate hotel cards): swapping the split mode
    // re-renders the stay blocks — per family or per member (duration /
    // gender splits are always automatic and never offered here). The auto
    // info tags disappear once a manual split is picked.
    $(document).off('change.fulfillment', '.f-hotel-split').on('change.fulfillment', '.f-hotel-split', function() {
        const mode = $(this).val();
        const $card = $(this).closest('.fulfillment-service-card');
        $card.data('hotel-split', mode);
        $card.find('.f-split-auto-tag').remove();
        $card.find('.f-hotel-split-body').html(hotelSplitBodyHtml({
            member_breakdown: $card.data('breakdown') || [],
            hotel_stays: $card.data('stays') || []
        }, fulfillmentData, mode));
        syncFulfillmentHotelOptions($card);
        applyContractPricing($card);
        autoAssignRooms($card);
    });

    $(document).off('click.fulfillment', '.btn-add-stay').on('click.fulfillment', '.btn-add-stay', function() {
        const $card = $(this).closest('.fulfillment-service-card');
        $(this).closest('.card-body').find('.fulfillment-stays').append(fulfillmentStayHtml(null, 0, fulfillmentData));
        syncFulfillmentHotelOptions($card);
        applyContractPricing($card);
    });

    $(document).off('click.fulfillment', '.btn-remove-stay').on('click.fulfillment', '.btn-remove-stay', function() {
        const $card = $(this).closest('.fulfillment-service-card');
        $(this).closest('.fulfillment-stay').remove();
        applyContractPricing($card);
    });

    $(document).off('click.fulfillment', '.btn-save-fulfillment').on('click.fulfillment', '.btn-save-fulfillment', function() {
        saveFulfillment($(this).closest('.fulfillment-service-card'));
    });

    $(document).off('click.fulfillment', '.btn-save-brn').on('click.fulfillment', '.btn-save-brn', function() {
        saveBrn($(this).closest('.fulfillment-service-card'));
    });
}

// Optional BRN (Booking Reference Number) procurement cost — saved via
// save_brn.php. Member mode posts the booking; family/group mode posts the
// family (+ group scope) so the server applies the shared cost to every
// covered member. Empty supplier + cost = record removal.
function saveBrn($card) {
    const btn = $card.find('.btn-save-brn');
    const originalHtml = btn.html();
    const isMulti = currentFulfillmentMode !== 'member';
    let scope = 'family';
    if (currentFulfillmentMode === 'family') {
        scope = $card.find('.f-scope').val() || 'family';
    } else if (currentFulfillmentMode === 'group') {
        scope = 'group';
    }

    const formData = new FormData();
    formData.append('csrf_token', window.csrfToken || '');
    formData.append('booking_id', currentFulfillmentBookingId || '');
    formData.append('supplier_id', $card.find('.f-supplier').val() || '');
    formData.append('supplier_currency', $card.find('.f-currency').val() || '');
    formData.append('supplier_cost', $card.find('.f-cost').val() || '');
    formData.append('exchange_rate', $card.find('.f-rate').val() || '');
    formData.append('notes', $card.find('.f-notes').val() || '');
    if (isMulti) {
        formData.append('family_id', currentFulfillmentFamilyId);
        if (scope === 'group') {
            formData.append('scope', 'group');
        }
    }

    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Saving...');

    $.ajax({
        url: '../api/umrah/save_brn.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json'
    }).then(data => {
        btn.prop('disabled', false).html(originalHtml);
        if (data.success) {
            showToast('success', data.message || (data.removed ? 'BRN removed' : 'BRN saved'));
            if (data.errors && data.errors.length) {
                console.warn('BRN save errors:', data.errors);
                showToast('error', (data.errors[0].member || 'Member') + ': ' + data.errors[0].message);
            }
            const newCost = parseFloat($card.find('.f-cost-usd').val()) || 0;
            $card.data('cost', newCost);
            $card.find('.f-card-cost').text(newCost.toFixed(2));
            updateFulfillmentSummary();
        } else {
            showToast('error', data.message || 'Failed to save BRN');
        }
    }).catch(err => {
        console.error('Error saving BRN:', err);
        btn.prop('disabled', false).html(originalHtml);
        showToast('error', 'An error occurred while saving BRN');
    });
}

function saveFulfillment($card) {
    const bookingServiceId = $card.data('booking-service-id');
    const btn = $card.find('.btn-save-fulfillment');
    const originalHtml = btn.html();
    const isMulti = currentFulfillmentMode !== 'member';
    let scope = 'family';
    if (currentFulfillmentMode === 'family') {
        scope = $card.find('.f-scope').val() || 'family';
    } else if (currentFulfillmentMode === 'group') {
        scope = 'group';
    }
    if (isMulti && !currentFulfillmentFamilyId) {
        showToast('error', 'Booking has no family — multi-member fulfillment needs a family.');
        return;
    }

    // Auto status — derived from what was entered, never picked by the user:
    //   supplier + cost filled      -> active status per type
    //   otherwise                   -> pre-work status per type
    const cat = $card.data('group') || 'ziyarat';
    const hasSupplier = !!$card.find('.f-supplier').val();
    const hasCost = $card.find('.f-cost').val() !== '';
    let autoStatus;
    if (hasSupplier && hasCost) {
        autoStatus = cat === 'visa' ? 'issued' : (cat === 'flight' ? 'ticketed' : 'confirmed');
    } else {
        autoStatus = cat === 'visa' ? 'not_applied' : (cat === 'flight' ? 'not_ticketed' : (cat === 'hotel' ? 'not_assigned' : 'pending'));
    }

    const formData = new FormData();
    formData.append('csrf_token', window.csrfToken || '');
    formData.append('booking_service_id', bookingServiceId);
    if (isMulti) {
        formData.append('family_id', currentFulfillmentFamilyId);
        if (scope === 'group') {
            formData.append('scope', 'group');
        }
    }
    formData.append('supplier_id', $card.find('.f-supplier').val() || '');
    formData.append('status', autoStatus);
    formData.append('supplier_currency', $card.find('.f-currency').val() || '');
    formData.append('supplier_cost', $card.find('.f-cost').val() || '');
    formData.append('exchange_rate', $card.find('.f-rate').val() || '');
    formData.append('notes', $card.find('.f-notes').val() || '');
    formData.append('planned_date', $card.data('planned') || '');
    formData.append('completed_date', $card.data('completed') || '');

    if ($card.data('group') === 'hotel') {
        const collectStays = ($scope) => {
            const arr = [];
            $scope.find('.fulfillment-stay').each(function() {
                const $st = $(this);
                arr.push({
                    fulfillment_id: $st.data('fulfillment-id') || '',
                    hotel_id: $st.find('.f-hotel').val() || '',
                    room_type_id: $st.find('.f-room-type').val() || '',
                    room_id: $st.find('.f-room').val() || '',
                    extra_bed: $st.find('.f-extra-bed').is(':checked') ? 1 : 0,
                    check_in: $st.find('.f-check-in').val() || '',
                    check_out: $st.find('.f-check-out').val() || '',
                    nights: $st.find('.f-nights').val() || '',
                    nightly_rate: $st.find('.f-nightly-rate').val() || ''
                });
            });
            return arr;
        };
        if ($card.find('.f-hotel-group').length) {
            // Aggregate hotel card split into group blocks — each card maps to
            // its member(s) via data-member-ids (a merged same-duration card
            // carries the whole group), so every member's own check-in /
            // check-out / room save to THEIR fulfillment only, even when
            // several members share one room.
            const hGMembers = {};
            const hGroups = {};
            $card.find('.f-hotel-group').each(function() {
                const gkey = String($(this).data('gkey') || '');
                hGroups[gkey] = collectStays($(this));
                const ids = $(this).data('member-ids');
                if (Array.isArray(ids) && ids.length) {
                    hGMembers[gkey] = ids.map(Number);
                }
            });
            formData.append('hotel_mode', 'grouped');
            formData.append('hotel_group_members', JSON.stringify(hGMembers));
            formData.append('hotel_groups', JSON.stringify(hGroups));
            const firstStays = collectStays($card.find('.f-hotel-group').first());
            formData.append('hotel_stays', JSON.stringify(firstStays));
            formData.append('hotel_id', $card.find('.f-hotel').first().val() || '');
            formData.append('room_type_id', $card.find('.f-room-type').first().val() || '');
            formData.append('room_id', $card.find('.f-room').first().val() || '');
            formData.append('check_in', $card.find('.f-check-in').first().val() || '');
            formData.append('check_out', $card.find('.f-check-out').first().val() || '');
            formData.append('nights', $card.find('.f-nights').first().val() || '');
            formData.append('nightly_rate', $card.find('.f-nightly-rate').first().val() || '');
            formData.append('extra_bed', $card.find('.f-extra-bed').first().is(':checked') ? 1 : 0);
        } else {
            const stays = collectStays($card);
            formData.append('hotel_stays', JSON.stringify(stays));
            formData.append('hotel_id', $card.find('.f-hotel').first().val() || '');
            formData.append('room_type_id', $card.find('.f-room-type').first().val() || '');
            formData.append('room_id', $card.find('.f-room').first().val() || '');
            formData.append('check_in', $card.find('.f-check-in').first().val() || '');
            formData.append('check_out', $card.find('.f-check-out').first().val() || '');
            formData.append('nights', $card.find('.f-nights').first().val() || '');
            formData.append('nightly_rate', $card.find('.f-nightly-rate').first().val() || '');
            formData.append('extra_bed', $card.find('.f-extra-bed').first().is(':checked') ? 1 : 0);
        }
    } else if ($card.data('group') === 'flight') {
        if ($card.find('.f-flight-group').length && $card.find('.f-same-dep').length) {
            // Aggregate flight card with DIFFERENT member durations — shared
            // fields + per-duration-group overrides posted as JSON maps.
            const sameDep = $card.find('.f-same-dep').is(':checked');
            const samePnr = $card.find('.f-same-pnr').is(':checked');
            const ddtGrouped = (d, t) => d && t ? d + ' ' + t : '';
            formData.append('ticket_number', $card.find('.f-ticket').val() || '');
            formData.append('airline', $card.find('.f-airline').val() || '');
            formData.append('flight_type', 'direct');
            formData.append('flight_legs', '');
            formData.append('flight_mode', 'grouped');
            formData.append('same_dep', sameDep ? 1 : 0);
            formData.append('same_pnr', samePnr ? 1 : 0);
            formData.append('pnr', $card.find('.f-pnr').val() || '');
            formData.append('departure_city', $card.find('.f-dep-city').val() || '');
            formData.append('arrival_city', $card.find('.f-arr-city').val() || '');
            formData.append('flight_number', $card.find('.f-flight-no').val() || '');
            formData.append('departure_time', ddtGrouped($card.find('.f-dep-date').val(), $card.find('.f-dep-time').val()));
            formData.append('arrival_time', ddtGrouped($card.find('.f-arr-date').val(), $card.find('.f-arr-time').val()));
            const bd = $card.data('breakdown') || [];
            const gMembers = {};
            bd.forEach(m => {
                const d = normalizedDurationKey(m.duration);
                (gMembers[d] = gMembers[d] || []).push(m.booking_id);
            });
            const groups = {};
            $card.find('.f-flight-group').each(function() {
                const dur = $(this).data('dur');
                const g = {};
                if (!samePnr) { g.pnr = $(this).find('.f-g-pnr').val() || ''; }
                if (!sameDep) {
                    g.departure_city = $(this).find('.f-g-dep-city').val() || '';
                    g.arrival_city = $(this).find('.f-g-arr-city').val() || '';
                    g.flight_number = $(this).find('.f-g-flight-no').val() || '';
                    g.departure_time = ddtGrouped($(this).find('.f-g-dep-date').val(), $(this).find('.f-g-dep-time').val());
                    g.arrival_time = ddtGrouped($(this).find('.f-g-arr-date').val(), $(this).find('.f-g-arr-time').val());
                }
                g.return_flight_number = $(this).find('.f-g-return-flight').val() || '';
                g.return_departure_time = ddtGrouped($(this).find('.f-g-ret-dep-date').val(), $(this).find('.f-g-ret-dep-time').val());
                g.return_arrival_time = ddtGrouped($(this).find('.f-g-ret-arr-date').val(), $(this).find('.f-g-ret-arr-time').val());
                groups[dur] = g;
            });
            formData.append('flight_group_members', JSON.stringify(gMembers));
            formData.append('flight_groups', JSON.stringify(groups));
        } else {
            formData.append('ticket_number', $card.find('.f-ticket').val() || '');
            formData.append('pnr', $card.find('.f-pnr').val() || '');
            formData.append('airline', $card.find('.f-airline').val() || '');
            const fType = $card.find('.f-flight-type:checked').val() || 'direct';
            formData.append('flight_type', fType);
            if (fType === 'direct') {
                formData.append('flight_legs', '');
                formData.append('departure_city', $card.find('.f-dep-city').val() || '');
                formData.append('arrival_city', $card.find('.f-arr-city').val() || '');
                formData.append('flight_number', $card.find('.f-flight-no').val() || '');
                formData.append('return_flight_number', $card.find('.f-return-flight').val() || '');
                const ddt = (d, t) => d && t ? d + ' ' + t : '';
                formData.append('departure_time', ddt($card.find('.f-dep-date').val(), $card.find('.f-dep-time').val()));
                formData.append('arrival_time', ddt($card.find('.f-arr-date').val(), $card.find('.f-arr-time').val()));
                formData.append('return_departure_time', ddt($card.find('.f-return-dep-date').val(), $card.find('.f-return-dep-time').val()));
                formData.append('return_arrival_time', ddt($card.find('.f-return-arr-date').val(), $card.find('.f-return-arr-time').val()));
            } else {
                const legs = [];
                const labels = ['outbound_1', 'outbound_2', 'return_1', 'return_2'];
                $card.find('.fulfillment-leg').each(function(i) {
                    const $leg = $(this);
                    legs.push({
                        label: labels[i] || ('leg_' + i),
                        dep_city: $leg.find('.f-leg-dep-city').val() || '',
                        arr_city: $leg.find('.f-leg-arr-city').val() || '',
                        flight_no: $leg.find('.f-leg-flight-no').val() || '',
                        dep_date: $leg.find('.f-leg-dep-date').val() || '',
                        dep_time: $leg.find('.f-leg-dep-time').val() || '',
                        arr_date: $leg.find('.f-leg-arr-date').val() || '',
                        arr_time: $leg.find('.f-leg-arr-time').val() || ''
                    });
                });
                formData.append('flight_legs', JSON.stringify(legs));
            }
        }
    } else if ($card.data('group') === 'transport') {
        formData.append('transport_vehicle', $card.find('.f-vehicle').val() || '');
        formData.append('transport_trip_date', $card.find('.f-trip-date').val() || '');
    }

    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Saving...');

    $.ajax({
        url: isMulti ? '../api/umrah/save_multi_fulfillment.php' : '../api/umrah/save_fulfillment.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json'
    }).then(data => {
        btn.prop('disabled', false).html(originalHtml);
        if (data.success) {
            if (data.fulfillment_ids && data.fulfillment_ids.length) {
                $card.find('.fulfillment-stay').each(function(i) {
                    if (data.fulfillment_ids[i]) {
                        $(this).attr('data-fulfillment-id', data.fulfillment_ids[i]);
                    }
                });
            }
            if (isMulti) {
                if (data.applied) {
                    showToast('success', data.message || 'Fulfillment applied');
                } else {
                    showToast('error', 'Nothing applied — no matching service lines in the ' + (scope === 'group' ? 'group' : 'family'));
                }
                if (data.errors && data.errors.length) {
                    console.warn('Multi-fulfillment errors:', data.errors);
                    showToast('error', (data.errors[0].member || 'Member') + ': ' + data.errors[0].message);
                }
            } else {
                showToast('success', data.message || 'Fulfillment saved');
            }
            const savedStatus = data.status || autoStatus;
            $card.find('.f-status-badge').text(savedStatus).attr('class', 'fulfillment-status f-status-badge');
            const newCost = parseFloat($card.find('.f-cost-usd').val()) || 0;
            $card.data('cost', newCost);
            if (!isMulti && data.cost_amount !== null && data.cost_amount !== undefined) {
                $card.find('.f-cost-usd').val(Number(data.cost_amount).toFixed(2));
            }
            $card.find('.f-card-cost').text((parseFloat($card.find('.f-cost-usd').val()) || 0).toFixed(2));
            updateFulfillmentSummary();
            if (!isMulti) {
                const frozen = isFulfillmentFrozen(savedStatus);
                if (frozen) {
                    $card.find('.f-cost, .f-rate, .f-currency').prop('readonly', true);
                    $card.data('frozen', 1);
                    showToast('info', 'Cost snapshot frozen — further changes to cost are blocked');
                }
            }
        } else {
            showToast('error', data.message || 'Failed to save fulfillment');
        }
    }).catch(err => {
        console.error('Error saving fulfillment:', err);
        btn.prop('disabled', false).html(originalHtml);
        showToast('error', 'An error occurred while saving');
    });
}

function __t(key) {
    if (window.fulfillmentLabels && window.fulfillmentLabels[key]) return window.fulfillmentLabels[key];
    if (window.dashboardLabels && window.dashboardLabels[key]) return window.dashboardLabels[key];
    return key;
}
