/**
 * Member Dashboard — Phase 23
 * Opens the operational center for one member: package, financial summary,
 * sold services with fulfillment state, and payment history.
 * Read-only; action buttons deep-link into the existing flows
 * (fulfillment modal, transaction tab).
 */

let currentDashboardBookingId = 0;
let currentDashboardMemberName = '';
let currentDashboardSoldPrice = 0;

function openMemberDashboard(bookingId, memberName) {
    currentDashboardBookingId = bookingId;
    currentDashboardMemberName = memberName || '';

    $('#dashboardServices').empty();
    $('#dashboardPayments').empty();

    showToast('info', 'Loading dashboard...');
    $.ajax({
        url: '../api/umrah/get_member_dashboard.php?booking_id=' + encodeURIComponent(bookingId),
        type: 'GET',
        dataType: 'json'
    }).then(data => {
        if (!data.success) {
            showToast('error', data.message || 'Failed to load dashboard');
            return;
        }
        renderDashboardHeader(data.booking);
        renderDashboardFinancial(data.booking);
        renderDashboardServices(data.services);
        renderDashboardPayments(data.payments, data.booking);
        currentDashboardSoldPrice = parseFloat(data.booking.sold_price || 0);
        $('#memberDashboardModal').modal('show');
    }).catch(err => {
        console.error('Error loading dashboard:', err);
        showToast('error', 'Failed to load dashboard');
    });
}

function escapeHtml(str) {
    return String(str == null ? '' : str).replace(/[&<>"']/g, c => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[c]));
}

function __t(key) {
    if (window.dashboardLabels && window.dashboardLabels[key]) return window.dashboardLabels[key];
    if (window.fulfillmentLabels && window.fulfillmentLabels[key]) return window.fulfillmentLabels[key];
    return key;
}

function fmtMoney(n) {
    const v = parseFloat(n || 0);
    return v.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function bookingStatusBadge(status) {
    const map = {
        'active':    'success',
        'pending':   'warning',
        'refunded':  'secondary',
        'cancelled': 'danger'
    };
    return '<span class="status-pill status-pill-' + (map[status] || 'secondary') + '">' + escapeHtml(status) + '</span>';
}

function statusBadgeClass(status) {
    const done = ['confirmed', 'completed', 'ticketed', 'issued', 'checked_out', 'reserved'];
    const warn = ['pending', 'not_assigned', 'not_applied', 'not_ticketed', 'requested'];
    const info = ['assigned', 'applied', 'processing', 'checked_in', 'changed'];
    if (done.indexOf(status) !== -1) return 'status-pill-success';
    if (warn.indexOf(status) !== -1) return 'status-pill-secondary';
    if (info.indexOf(status) !== -1) return 'status-pill-info';
    if (status === 'cancelled' || status === 'rejected') return 'status-pill-danger';
    return 'status-pill-light';
}

function serviceIcon(type) {
    const t = (type || '').toLowerCase();
    if (t === 'hotel') return 'icon-home';
    if (t === 'ticket' || t === 'flight') return 'icon-plane';
    if (t === 'visa') return 'icon-file-text';
    if (t === 'transport') return 'icon-truck';
    if (t === 'meal') return 'icon-coffee';
    if (t === 'ziyarat') return 'icon-map-pin';
    return 'icon-box';
}

// ---- Header ---------------------------------------------------------------
function renderDashboardHeader(b) {
    const packageHtml = b.package_name
        ? '<span class="fulfillment-chip" style="font-size: 0.85rem;"><i class="feather icon-package mr-1"></i>' + escapeHtml(b.package_name) + (b.package_code ? ' (' + escapeHtml(b.package_code) + ')' : '') + '</span>'
        : '<span class="fulfillment-chip">' + __t('package') + ': —</span>';

    let travelHtml = '';
    if (b.flight_date || b.return_date) {
        travelHtml = '<span class="text-muted ml-2" style="font-size: 0.8rem;">' +
            '<i class="feather icon-plane mr-1"></i>' + escapeHtml(b.flight_date || '—') +
            ' → ' + escapeHtml(b.return_date || '—') + '</span>';
    }

    $('#dashboardMemberInfo').html(
        '<div class="card mb-0" style="border-left: 3px solid #0e7490;">' +
            '<div class="card-body py-3">' +
                '<div class="d-flex flex-wrap align-items-center" style="gap: 10px;">' +
                    '<i class="feather icon-user" style="font-size: 1.6rem; color: #0e7490;"></i>' +
                    '<div>' +
                        '<strong style="font-size: 1.15rem;">' + escapeHtml(b.name || '') + '</strong>' +
                        ' <span class="text-muted">#' + b.booking_id + '</span>' +
                        (b.head_of_family ? '<div class="text-muted" style="font-size: 0.78rem;">' + __t('package') + ': ' + escapeHtml(b.head_of_family) + '</div>' : '') +
                    '</div>' +
                    '<div class="ml-auto d-flex flex-wrap align-items-center" style="gap: 8px;">' +
                        packageHtml +
                        bookingStatusBadge(b.status) +
                    '</div>' +
                '</div>' +
                (travelHtml ? '<div class="mt-2" style="font-size: 0.85rem;">' + travelHtml + '</div>' : '') +
            '</div>' +
        '</div>'
    );
}

// ---- Financial summary ------------------------------------------------------
function renderDashboardFinancial(b) {
    const cur = escapeHtml(b.currency || 'USD');
    const cards = [
        { label: __t('selling_price'), value: fmtMoney(b.sold_price), color: '#0e7490', icon: 'icon-dollar-sign' },
        { label: __t('paid'), value: fmtMoney(b.paid), color: '#16a34a', icon: 'icon-check-circle' },
        { label: __t('due'), value: fmtMoney(b.due), color: '#dc2626', icon: 'icon-alert-circle' },
        { label: __t('profit'), value: fmtMoney(b.profit), color: '#7c3aed', icon: 'icon-trending-up' }
    ];
    $('#dashboardFinancial').html(cards.map(c =>
        '<div class="col-6 col-md-3 mb-2">' +
            '<div class="card text-center" style="border-top: 3px solid ' + c.color + ';">' +
                '<div class="card-body py-3">' +
                    '<div style="font-size: 0.78rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.03em;">' + c.label + '</div>' +
                    '<div style="font-size: 1.25rem; font-weight: 700; color: ' + c.color + ';">' + c.value + ' <small style="font-size: 0.7rem;">' + cur + '</small></div>' +
                '</div>' +
            '</div>' +
        '</div>'
    ).join(''));
}

// ---- Services ----------------------------------------------------------------
function renderDashboardServices(services) {
    $('#dashboardServiceCount').text(services.length);
    const $container = $('#dashboardServices');

    if (!services || services.length === 0) {
        $container.html('<div class="alert alert-light border text-muted">' + __t('not_fulfilled') + '</div>');
        return;
    }

    const rows = services.map(svc => {
        const name = svc.service_name || svc.service_type;
        const sold = fmtMoney(svc.sold_price) + ' ' + escapeHtml(svc.currency || 'USD');
        const qty = parseFloat(svc.quantity || 1);
        const pricing = qty > 1
            ? qty + ' × ' + sold
            : sold + (svc.pricing_unit ? ' / ' + escapeHtml(svc.pricing_unit) : '');

        const status = svc.fulfill_status || svc.sold_status || 'pending';
        const statusBadge = '<span class="status-pill ' + statusBadgeClass(status) + '">' + escapeHtml(status) + '</span>';

        let detailRows = [];
        if (svc.hotel_name) {
            detailRows.push({
                icon: 'icon-home',
                text: escapeHtml(svc.hotel_name) +
                    (svc.room_type_name ? ' · ' + escapeHtml(svc.room_type_name) : '') +
                    (svc.nights ? ' · ' + __t('nights') + ': ' + svc.nights : '') +
                    (svc.check_in ? ' · ' + escapeHtml(svc.check_in) + ' → ' + escapeHtml(svc.check_out || '') : '')
            });
            if (svc.nightly_rate) {
                detailRows.push({ icon: 'icon-dollar-sign', text: __t('nightly_rate') + ': ' + fmtMoney(svc.nightly_rate) });
            }
        }
        if (svc.airline || svc.flight_number || svc.ticket_number) {
            detailRows.push({
                icon: 'icon-plane',
                text: escapeHtml(svc.airline || '') + (svc.flight_number ? ' ' + escapeHtml(svc.flight_number) : '') +
                    (svc.ticket_number ? ' · ' + __t('ticket_number') + ': ' + escapeHtml(svc.ticket_number) : '') +
                    (svc.pnr ? ' · ' + __t('pnr') + ': ' + escapeHtml(svc.pnr) : '')
            });
            if (svc.departure_time || svc.arrival_time) {
                detailRows.push({
                    icon: 'icon-clock',
                    text: (svc.departure_time ? __t('departure') + ': ' + escapeHtml(svc.departure_time) : '') +
                        (svc.departure_time && svc.arrival_time ? ' · ' : '') +
                        (svc.arrival_time ? __t('arrival') + ': ' + escapeHtml(svc.arrival_time) : '')
                });
            }
            if (svc.return_flight_number) {
                detailRows.push({
                    icon: 'icon-corner-up-left',
                    text: __t('return_flight') + ': ' + escapeHtml(svc.return_flight_number) +
                        (svc.return_departure_time ? ' ' + escapeHtml(svc.return_departure_time) : '')
                });
            }
        }
        if (svc.transport_vehicle) {
            detailRows.push({
                icon: 'icon-truck',
                text: __t('vehicle') + ': ' + escapeHtml(svc.transport_vehicle) +
                    (svc.transport_trip_date ? ' · ' + __t('trip_date') + ': ' + escapeHtml(svc.transport_trip_date) : '')
            });
        }
        if (svc.fulfillment_id) {
            const supName = svc.fulfill_supplier_name || svc.supplier_name;
            if (supName) {
                detailRows.push({ icon: 'icon-user', text: __t('supplier') + ': ' + escapeHtml(supName) });
            }
            if (svc.supplier_cost !== null) {
                const cost = fmtMoney(svc.supplier_cost) + ' ' + escapeHtml(svc.supplier_currency || 'USD');
                detailRows.push({ icon: 'icon-dollar-sign', text: __t('supplier_cost') + ': ' + cost });
            }
            if (svc.cost_amount !== null) {
                detailRows.push({ icon: 'icon-dollar-sign', text: __t('cost_in_sale') + ': ' + fmtMoney(svc.cost_amount) });
            }
            if (svc.requested_date) {
                detailRows.push({ icon: 'icon-calendar', text: __t('requested_date') + ': ' + escapeHtml(svc.requested_date) });
            }
            if (svc.planned_date) {
                detailRows.push({ icon: 'icon-calendar', text: __t('planned_date') + ': ' + escapeHtml(svc.planned_date) });
            }
            if (svc.completed_date) {
                detailRows.push({ icon: 'icon-check-circle', text: __t('completed_date') + ': ' + escapeHtml(svc.completed_date) });
            }
        }

        const detailHtml = detailRows.length
            ? '<div class="mt-2 pt-2" style="border-top: 1px dashed #e5e7eb;">' +
                detailRows.map(r =>
                    '<div class="mb-1" style="font-size: 0.82rem; color: #374151;">' +
                        '<i class="feather ' + r.icon + ' mr-2" style="color: #6b7280; font-size: 0.8rem;"></i>' + r.text +
                    '</div>'
                ).join('') +
              '</div>'
            : '';

        return (
            '<div class="card mb-2 dashboard-service-card" style="border-left: 3px solid #0e7490;">' +
                '<div class="card-body py-2 px-3">' +
                    '<div class="d-flex flex-wrap align-items-center" style="gap: 8px;">' +
                        '<i class="feather ' + serviceIcon(svc.service_type) + '" style="color: #0e7490;"></i>' +
                        '<strong style="font-size: 0.95rem;">' + escapeHtml(name) + '</strong>' +
                        (parseInt(svc.is_optional) === 1 ? '<span class="fulfillment-chip fulfillment-chip-optional">' + __t('optional') + '</span>' : '') +
                        '<span class="text-muted" style="font-size: 0.8rem;">' + pricing + '</span>' +
                        '<div class="ml-auto">' + statusBadge + '</div>' +
                    '</div>' +
                    detailHtml +
                '</div>' +
            '</div>'
        );
    });

    $container.html(rows.join(''));
}

// ---- Payments -----------------------------------------------------------------
function renderDashboardPayments(payments, booking) {
    const $container = $('#dashboardPayments');

    if (!payments || payments.length === 0) {
        $container.html('<div class="alert alert-light border text-muted">' + __t('no_payments_yet') + '</div>');
        return;
    }

    const rows = payments.map(p =>
        '<tr>' +
            '<td>' + escapeHtml(p.payment_date) + '</td>' +
            '<td>' + escapeHtml(p.payment_description || '—') + '</td>' +
            '<td>' + escapeHtml(p.transaction_to || '—') + '</td>' +
            '<td>' + escapeHtml(p.receipt || '—') + '</td>' +
            '<td class="text-right" style="font-weight: 600;">' + fmtMoney(p.payment_amount) + ' ' + escapeHtml(p.currency || 'USD') + '</td>' +
        '</tr>'
    ).join('');

    $container.html(
        '<div class="table-responsive" style="max-height: 260px; overflow-y: auto;">' +
            '<table class="table table-sm table-bordered bg-white mb-0">' +
                '<thead class="thead-light">' +
                    '<tr>' +
                        '<th>' + __t('payment_date') + '</th>' +
                        '<th>' + __t('payment_description') + '</th>' +
                        '<th>Account</th>' +
                        '<th>' + __t('receipt') + '</th>' +
                        '<th class="text-right">' + __t('total') + '</th>' +
                    '</tr>' +
                '</thead>' +
                '<tbody>' + rows + '</tbody>' +
            '</table>' +
        '</div>'
    );
}

// ---- Action buttons -------------------------------------------------------------
$(document).on('click', '#btnDashboardFulfill', function() {
    if (currentDashboardBookingId && typeof openFulfillmentModal === 'function') {
        $('#memberDashboardModal').modal('hide');
        openFulfillmentModal(currentDashboardBookingId, currentDashboardMemberName);
    }
});

$(document).on('click', '#btnDashboardPayment', function() {
    if (currentDashboardBookingId && typeof openTransactionTab === 'function') {
        $('#memberDashboardModal').modal('hide');
        openTransactionTab(currentDashboardBookingId, currentDashboardSoldPrice);
    }
});
