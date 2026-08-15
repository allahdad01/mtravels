/**
 * Finance, Payables & Reports — Phases 26-28
 * admin/umrah_finance.php controller: member profitability, service
 * profitability, supplier payables (from fulfilled services), hotel
 * report and outstanding payments.
 */

let fnLabels = {};

function fnT(key) {
    return (window.financeLabels && window.financeLabels[key]) || key;
}

function fnEsc(str) {
    return String(str == null ? '' : str).replace(/[&<>"']/g, c => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[c]);
}

function fnMoney(n, cur) {
    return parseFloat(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + fnEsc(cur || 'USD');
}

function fnAjax(params) {
    return $.ajax({
        url: '../api/umrah/get_finance_report.php',
        type: 'GET',
        dataType: 'json',
        data: params,
        headers: { 'X-CSRF-Token': window.csrfToken || '' }
    });
}

// ============================================================== STAT CARDS
function fnStatCards(container, cards) {
    const colors = ['#2563eb', '#16a34a', '#dc2626', '#d97706', '#7c3aed', '#0e7490'];
    let html = '';
    cards.forEach((c, i) => {
        html += '<div class="col-xl-2 col-md-4 col-sm-6 mb-2">' +
            '<div class="card mb-0">' +
            '<div class="card-body py-3">' +
            '<div class="text-muted" style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.5px;">' + fnEsc(c.label) + '</div>' +
            '<div style="font-size:1.15rem;font-weight:700;color:' + colors[i % colors.length] + ';">' + c.value + '</div>' +
            '</div></div></div>';
    });
    $('#' + container).html(html);
}

// ============================================================== FINANCE TAB
function renderFinanceStats(totals) {
    fnStatCards('financeStats', [
        { label: fnT('total_selling'), value: fnMoney(totals.selling_usd, 'USD') },
        { label: fnT('total_cost'), value: fnMoney(totals.cost_usd, 'USD') },
        { label: fnT('gross_profit'), value: fnMoney(totals.profit_usd, 'USD') },
        { label: fnT('margin'), value: (totals.margin || 0) + '%' },
        { label: fnT('total_paid'), value: fnMoney(totals.paid_usd, 'USD') },
        { label: fnT('total_due'), value: fnMoney(totals.due_usd, 'USD') },
    ]);
}

function renderMemberProfit(rows) {
    if (!rows.length) {
        $('#memberProfitTable').html('<div class="text-muted py-4 text-center">' + fnT('no_data') + '</div>');
        return;
    }
    const th = ['member', 'flight_date', 'currency', 'selling', 'cost', 'profit', 'margin', 'paid', 'due'];
    let html = '<div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead class="thead-light"><tr>';
    th.forEach(k => html += '<th>' + fnT(k) + '</th>');
    html += '</tr></thead><tbody>';
    rows.forEach(r => {
        const profitCls = r.profit_usd >= 0 ? 'text-success' : 'text-danger';
        const dueCls = r.due > 0 ? 'text-danger' : '';
        html += '<tr>' +
            '<td><div class="font-weight-bold">' + fnEsc(r.name) + '</div>' +
            '<div class="text-muted" style="font-size:0.75rem;">#' + r.booking_id + ' · ' + fnEsc(r.passport_number || '-') + '</div></td>' +
            '<td>' + fnEsc(r.flight_date || '-') + '</td>' +
            '<td>' + fnEsc(r.currency || '-') + '</td>' +
            '<td>' + fnMoney(r.selling, r.currency) + '</td>' +
            '<td>' + fnMoney(r.cost_usd, 'USD') + '</td>' +
            '<td class="' + profitCls + ' font-weight-bold">' + fnMoney(r.profit_usd, 'USD') + '</td>' +
            '<td>' + (r.margin || 0) + '%</td>' +
            '<td>' + fnMoney(r.paid, r.currency) + '</td>' +
            '<td class="' + dueCls + '">' + fnMoney(r.due, r.currency) + '</td>' +
            '</tr>';
    });
    html += '</tbody></table></div>';
    $('#memberProfitTable').html(html);
}

function renderServiceProfit(rows) {
    if (!rows.length) {
        $('#serviceProfitTable').html('<div class="text-muted py-4 text-center">' + fnT('no_data') + '</div>');
        return;
    }
    let html = '<div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead class="thead-light"><tr>' +
        '<th>' + fnT('service_type') + '</th><th>' + fnT('services_count') + '</th><th>' + fnT('cost') + '</th>' +
        '</tr></thead><tbody>';
    rows.forEach(r => {
        html += '<tr>' +
            '<td class="font-weight-bold">' + fnEsc(r.service_type) + '</td>' +
            '<td>' + r.count + '</td>' +
            '<td class="font-weight-bold">' + fnMoney(r.cost_usd, 'USD') + '</td>' +
            '</tr>';
    });
    html += '</tbody></table></div>';
    $('#serviceProfitTable').html(html);
}

// ============================================================== PAYABLES TAB
function renderPayablesStats(totals) {
    fnStatCards('payablesStats', [
        { label: fnT('suppliers'), value: totals.suppliers || 0 },
        { label: fnT('fulfilled_services'), value: totals.services_count || 0 },
        { label: fnT('total_payable'), value: fnMoney(totals.total_payable, 'USD') },
    ]);
}

function renderSupplierPayables(rows) {
    if (!rows.length) {
        $('#supplierPayableTable').html('<div class="text-muted py-4 text-center">' + fnT('no_payables') + '</div>');
        return;
    }
    const types = ['flight', 'hotel', 'visa', 'transport', 'meal', 'ziyarat'];
    let html = '<div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead class="thead-light"><tr>' +
        '<th>' + fnT('supplier') + '</th><th>' + fnT('currency') + '</th><th>' + fnT('services_count') + '</th>';
    types.forEach(t => html += '<th>' + fnT('pay_' + t) + '</th>');
    html += '<th class="text-right">' + fnT('total_payable') + '</th>' +
        '<th class="text-right">' + fnT('paid') + '</th>' +
        '<th class="text-right">' + fnT('balance') + '</th></tr></thead><tbody>';
    rows.forEach(r => {
        const cur = r.currency || 'USD';
        const bal = parseFloat(r.balance_ccy) || 0;
        html += '<tr>' +
            '<td class="font-weight-bold">' + fnEsc(r.supplier_name) + '</td>' +
            '<td>' + fnEsc(cur) + '</td>' +
            '<td>' + r.services_count + '</td>';
        types.forEach(t => html += '<td>' + fnMoney(r[t + '_cost'], 'USD') + '</td>');
        html += '<td class="text-right font-weight-bold text-danger">' + fnMoney(r.total_payable, 'USD') + '</td>' +
            '<td class="text-right">' + fnMoney(r.paid_ccy, cur) + '</td>' +
            '<td class="text-right font-weight-bold ' + (bal > 0 ? 'text-danger' : 'text-success') + '">' + fnMoney(r.balance_ccy, cur) + '</td>' +
            '</tr>';
    });
    html += '</tbody></table></div>';
    $('#supplierPayableTable').html(html);
}

// ============================================================== REPORTS TAB
function renderHotelReport(rows) {
    if (!rows.length) {
        $('#hotelReportTable').html('<div class="text-muted py-4 text-center">' + fnT('no_data') + '</div>');
        return;
    }
    let html = '<div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead class="thead-light"><tr>' +
        '<th>' + fnT('hotel') + '</th><th>' + fnT('city') + '</th><th>' + fnT('total_rooms') + '</th>' +
        '<th>' + fnT('reservations') + '</th><th>' + fnT('occupied_today') + '</th><th>' + fnT('occupancy') + '</th>' +
        '<th>' + fnT('contracts') + '</th><th>' + fnT('inventory_rooms') + '</th><th>' + fnT('utilization') + '</th>' +
        '</tr></thead><tbody>';
    rows.forEach(r => {
        html += '<tr>' +
            '<td class="font-weight-bold">' + fnEsc(r.hotel_name) + '</td>' +
            '<td>' + fnEsc(r.city || '-') + '</td>' +
            '<td>' + r.rooms + '</td>' +
            '<td>' + r.reservations + '</td>' +
            '<td>' + r.occupied_today + '</td>' +
            '<td>' + (r.occupancy_pct || 0) + '%</td>' +
            '<td>' + r.contracts + '</td>' +
            '<td>' + r.inventory_rooms + '</td>' +
            '<td>' + (r.utilization_pct || 0) + '%</td>' +
            '</tr>';
    });
    html += '</tbody></table></div>';
    $('#hotelReportTable').html(html);
}

function renderOutstanding(rows, totals) {
    if (!rows.length) {
        $('#outstandingTable').html('<div class="text-muted py-4 text-center">' + fnT('no_outstanding') + '</div>');
        return;
    }
    let html = '<div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead class="thead-light"><tr>' +
        '<th>' + fnT('member') + '</th><th>' + fnT('flight_date') + '</th><th>' + fnT('currency') + '</th>' +
        '<th>' + fnT('total') + '</th><th>' + fnT('paid') + '</th><th>' + fnT('due') + '</th>' +
        '</tr></thead><tbody>';
    rows.forEach(r => {
        html += '<tr>' +
            '<td><div class="font-weight-bold">' + fnEsc(r.name) + '</div>' +
            '<div class="text-muted" style="font-size:0.75rem;">#' + r.booking_id + '</div></td>' +
            '<td>' + fnEsc(r.flight_date || '-') + '</td>' +
            '<td>' + fnEsc(r.currency || '-') + '</td>' +
            '<td>' + fnMoney(r.total, r.currency) + '</td>' +
            '<td class="text-success">' + fnMoney(r.paid, r.currency) + '</td>' +
            '<td class="text-danger font-weight-bold">' + fnMoney(r.due, r.currency) + '</td>' +
            '</tr>';
    });
    html += '</tbody></table></div>';
    $('#outstandingTable').html(html);
}

// ============================================================== LOAD
function loadFinanceDashboard() {
    $('#memberProfitTable').html('<div class="text-muted py-4 text-center">' + fnT('loading') + '...</div>');
    Promise.all([
        fnAjax({ report: 'members' }),
        fnAjax({ report: 'services' }),
        fnAjax({ report: 'suppliers' }),
        fnAjax({ report: 'hotels' }),
        fnAjax({ report: 'outstanding' }),
    ]).then(([members, services, suppliers, hotels, outstanding]) => {
        if (!members.success || !services.success || !suppliers.success || !hotels.success || !outstanding.success) {
            showToast('error', fnT('load_failed'));
            return;
        }
        renderFinanceStats(members.totals);
        renderMemberProfit(members.rows);
        renderServiceProfit(services.rows);

        const payTotals = suppliers.totals || {};
        payTotals.suppliers = suppliers.rows.length;
        renderPayablesStats(payTotals);
        renderSupplierPayables(suppliers.rows);

        renderHotelReport(hotels.rows);
        renderOutstanding(outstanding.rows, outstanding.totals);
    }).catch(() => showToast('error', fnT('load_failed')));
}

$(function () {
    $('#btnRefreshFinance').on('click', loadFinanceDashboard);
    loadFinanceDashboard();
});
