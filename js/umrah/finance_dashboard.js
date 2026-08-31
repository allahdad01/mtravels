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
    let html = '<div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead class="thead-light"><tr>' +
        '<th>#</th><th>' + fnT('group_name') + '</th><th>' + fnT('member') + 's</th>' +
        '<th>' + fnT('total_selling') + '</th><th>' + fnT('total_cost') + '</th>' +
        '<th>' + fnT('gross_profit') + '</th><th>' + fnT('margin') + '</th>' +
        '<th>' + fnT('total_paid') + '</th><th>' + fnT('total_due') + '</th>' +
        '</tr></thead><tbody>';
    rows.forEach(function (r, i) {
        const profitCls = r.profit_usd >= 0 ? 'text-success' : 'text-danger';
        const dueCls = r.due_usd > 0 ? 'text-danger' : '';
        html += '<tr>' +
            '<td>' + (i + 1) + '</td>' +
            '<td><div class="font-weight-bold">#' + fnEsc(r.group_number) + ' — ' + fnEsc(r.group_name) + '</div></td>' +
            '<td>' + r.member_count + '</td>' +
            '<td>' + fnMoney(r.selling_usd, 'USD') + '</td>' +
            '<td>' + fnMoney(r.cost_usd, 'USD') + '</td>' +
            '<td class="' + profitCls + ' font-weight-bold">' + fnMoney(r.profit_usd, 'USD') + '</td>' +
            '<td>' + (r.margin || 0) + '%</td>' +
            '<td>' + fnMoney(r.paid_usd, 'USD') + '</td>' +
            '<td class="' + dueCls + '">' + fnMoney(r.due_usd, 'USD') + '</td>' +
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
        '<th style="width:30px;"></th>' +
        '<th>' + fnT('supplier') + '</th><th>' + fnT('currency') + '</th><th>' + fnT('services_count') + '</th>';
    types.forEach(t => html += '<th>' + fnT('pay_' + t) + '</th>');
    html += '<th class="text-right">' + fnT('total_payable') + '</th>' +
        '<th class="text-right">' + fnT('paid') + '</th>' +
        '<th class="text-right">' + fnT('balance') + '</th>' +
        '</tr></thead><tbody>';
    rows.forEach(function (r, idx) {
        const cur = r.currency || 'USD';
        const bal = parseFloat(r.balance_ccy) || 0;
        // Summary row
        html += '<tr class="supplier-summary-row" data-idx="' + idx + '" style="cursor:pointer;">' +
            '<td><i class="feather icon-chevron-right supplier-expand-icon" style="transition:transform 0.2s;"></i></td>' +
            '<td class="font-weight-bold">' + fnEsc(r.supplier_name) + '</td>' +
            '<td>' + fnEsc(cur) + '</td>' +
            '<td>' + r.services_count + '</td>';
        types.forEach(t => html += '<td>' + fnMoney(r[t + '_cost'], 'USD') + '</td>');
        html += '<td class="text-right font-weight-bold text-danger">' + fnMoney(r.total_payable, 'USD') + '</td>' +
            '<td class="text-right">' + fnMoney(r.paid_ccy, cur) + '</td>' +
            '<td class="text-right font-weight-bold ' + (bal > 0 ? 'text-danger' : 'text-success') + '">' + fnMoney(bal, cur) + '</td>' +
            '</tr>';
        // Hidden fulfillment detail rows
        if (r.fulfillments && r.fulfillments.length) {
            html += '<tr class="supplier-detail-row" data-idx="' + idx + '" style="display:none;">' +
                '<td colspan="' + (4 + types.length) + '" style="padding:0;">' +
                '<div style="padding:6px 12px 6px 36px;">' +
                '<table class="table table-xs mb-0" style="font-size:0.8rem;">' +
                '<thead><tr style="background:#f9fafb;">' +
                '<th>' + fnT('service_type') + '</th>' +
                '<th>' + fnT('member') + '</th>' +
                '<th class="text-right">' + fnT('total_payable') + '</th>' +
                '<th class="text-right">' + fnT('paid') + '</th>' +
                '<th class="text-right">' + fnT('balance') + '</th>' +
                '</tr></thead><tbody>';
            r.fulfillments.forEach(function (f) {
                const fBal = parseFloat(f.balance) || 0;
                const fBalCls = fBal > 0.01 ? 'text-danger' : (fBal < -0.01 ? 'text-success' : '');
                html += '<tr>' +
                    '<td>' + fnEsc(f.type) + '</td>' +
                    '<td>' + fnEsc(f.member_name || '-') + '</td>' +
                    '<td class="text-right">' + fnMoney(f.payable, cur) + '</td>' +
                    '<td class="text-right text-success">' + fnMoney(f.paid, cur) + '</td>' +
                    '<td class="text-right font-weight-bold ' + fBalCls + '">' + fnMoney(fBal, cur) + '</td>' +
                    '</tr>';
            });
            html += '</tbody></table></div></td></tr>';
        }
    });
    html += '</tbody></table></div>';
    $('#supplierPayableTable').html(html);

    // Click handler to expand/collapse fulfillment details
    $('#supplierPayableTable').off('click', '.supplier-summary-row').on('click', '.supplier-summary-row', function () {
        const idx = $(this).data('idx');
        const detailRow = $('#supplierPayableTable .supplier-detail-row[data-idx="' + idx + '"]');
        const icon = $(this).find('.supplier-expand-icon');
        if (detailRow.is(':visible')) {
            detailRow.hide();
            icon.css('transform', 'rotate(0deg)');
        } else {
            detailRow.show();
            icon.css('transform', 'rotate(90deg)');
        }
    });
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

// ===================================================== SERVICE REPORT TAB
let serviceReportData = null;

function renderServiceReportStats(totals) {
    fnStatCards('serviceReportStats', [
        { label: fnT('total_members'), value: totals.members || 0 },
        { label: fnT('total_cost'), value: fnMoney(totals.cost, 'USD') },
        { label: fnT('total_selling'), value: fnMoney(totals.sold, 'USD') },
        { label: fnT('gross_profit'), value: fnMoney(totals.profit, 'USD') },
    ]);
}

function renderServiceReportTable(data) {
    const groupBy = data.group_by;
    const $table = $('#serviceReportTable');

    if (groupBy === 'service') {
        if (!data.services || !data.services.length) {
            $table.html('<div class="text-muted py-4 text-center">' + fnT('no_data') + '</div>');
            return;
        }
        let html = '<div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead class="thead-light"><tr>' +
            '<th>#</th><th>' + fnT('service_type') + '</th><th>' + fnT('member') + '</th><th>' + fnT('cost') + '</th>' +
            '</tr></thead><tbody>';
        let totalCost = 0;
        data.services.forEach(function (r, i) {
            totalCost += parseFloat(r.total_cost || 0);
            html += '<tr>' +
                '<td>' + (i + 1) + '</td>' +
                '<td class="font-weight-bold">' + fnEsc(r.service_name || r.service_type) + '</td>' +
                '<td>' + (r.member_count || 0) + '</td>' +
                '<td class="font-weight-bold">' + fnMoney(r.total_cost, 'USD') + '</td>' +
                '</tr>';
        });
        html += '<tr style="background:#e5e7eb;font-weight:700;">' +
            '<td colspan="2">' + fnT('grand_total') + '</td>' +
            '<td>' + (data.summary.total_members || 0) + '</td>' +
            '<td>' + fnMoney(totalCost, 'USD') + '</td>' +
            '</tr>';
        html += '</tbody></table></div>';
        $table.html(html);

    } else if (groupBy === 'group') {
        if (!data.details || !data.details.length) {
            $table.html('<div class="text-muted py-4 text-center">' + fnT('no_data') + '</div>');
            return;
        }
        let html = '<div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead class="thead-light"><tr>' +
            '<th>#</th><th>' + fnT('group_name') + '</th><th>' + fnT('service_type') + '</th><th>' + fnT('member') + '</th><th>' + fnT('cost') + '</th>' +
            '</tr></thead><tbody>';
        let ri = 0;
        data.details.forEach(function (grp) {
            ri++;
            html += '<tr style="background:#dbeafe;font-weight:700;">' +
                '<td>' + ri + '</td>' +
                '<td colspan="4">#' + fnEsc(grp.group_number) + ' — ' + fnEsc(grp.group_name) + ' (' + grp.member_count + ' ' + fnT('member') + ')</td>' +
                '</tr>';
            if (grp.services) {
                Object.values(grp.services).forEach(function (svc) {
                    html += '<tr>' +
                        '<td></td>' +
                        '<td style="padding-left:20px;">— ' + fnEsc(svc.service_name || svc.service_type) + '</td>' +
                        '<td></td>' +
                        '<td>' + (svc.member_count || 0) + '</td>' +
                        '<td class="font-weight-bold">' + fnMoney(svc.total_cost, 'USD') + '</td>' +
                        '</tr>';
                });
            }
            html += '<tr style="background:#fef3c7;font-weight:600;">' +
                '<td></td>' +
                '<td style="padding-left:20px;">Subtotal</td>' +
                '<td></td>' +
                '<td>' + grp.member_count + '</td>' +
                '<td>' + fnMoney(grp.total_cost, 'USD') + '</td>' +
                '</tr>';
        });
        html += '<tr style="background:#e5e7eb;font-weight:700;">' +
            '<td colspan="3">' + fnT('grand_total') + '</td>' +
            '<td>' + (data.summary.total_members || 0) + '</td>' +
            '<td>' + fnMoney(data.summary.total_cost, 'USD') + '</td>' +
            '</tr>';
        html += '</tbody></table></div>';
        $table.html(html);

    } else if (groupBy === 'family') {
        if (!data.details || !data.details.length) {
            $table.html('<div class="text-muted py-4 text-center">' + fnT('no_data') + '</div>');
            return;
        }
        let html = '<div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead class="thead-light"><tr>' +
            '<th>#</th><th>' + fnT('family') + '</th><th>' + fnT('group_name') + '</th><th>' + fnT('service_type') + '</th><th>' + fnT('member') + '</th><th>' + fnT('cost') + '</th>' +
            '</tr></thead><tbody>';
        let ri = 0;
        data.details.forEach(function (fam) {
            ri++;
            html += '<tr style="background:#f3f4f6;font-weight:600;">' +
                '<td>' + ri + '</td>' +
                '<td colspan="5">' + fnEsc(fam.head_of_family) + ' — ' + fnEsc(fam.group_name || '') + ' (' + fam.member_count + ' ' + fnT('member') + ')</td>' +
                '</tr>';
            if (fam.services) {
                Object.values(fam.services).forEach(function (svc) {
                    html += '<tr>' +
                        '<td></td>' +
                        '<td style="padding-left:20px;">— ' + fnEsc(svc.service_name || svc.service_type) + '</td>' +
                        '<td></td>' +
                        '<td></td>' +
                        '<td>' + (svc.member_count || 0) + '</td>' +
                        '<td class="font-weight-bold">' + fnMoney(svc.total_cost, 'USD') + '</td>' +
                        '</tr>';
                });
            }
            html += '<tr style="background:#fef3c7;font-weight:600;">' +
                '<td></td>' +
                '<td style="padding-left:20px;">Subtotal</td>' +
                '<td></td>' +
                '<td></td>' +
                '<td>' + fam.member_count + '</td>' +
                '<td>' + fnMoney(fam.total_cost, 'USD') + '</td>' +
                '</tr>';
        });
        html += '<tr style="background:#e5e7eb;font-weight:700;">' +
            '<td colspan="4">' + fnT('grand_total') + '</td>' +
            '<td>' + (data.summary.total_members || 0) + '</td>' +
            '<td>' + fnMoney(data.summary.total_cost, 'USD') + '</td>' +
            '</tr>';
        html += '</tbody></table></div>';
        $table.html(html);
    }
}

function loadServiceReport() {
    var dateFrom = $('#svcDateFrom').val();
    var dateTo = $('#svcDateTo').val();
    var groupBy = $('#svcGroupBy').val();

    if (!dateFrom || !dateTo) {
        showToast('warning', 'Please select date range');
        return;
    }

    $('#serviceReportTable').html('<div class="text-muted py-4 text-center">' + fnT('loading') + '...</div>');
    $('#serviceReportStats').html('');

    $.ajax({
        url: '../api/umrah/get_finance_report.php',
        type: 'GET',
        dataType: 'json',
        data: { report: 'service_detail', date_from: dateFrom, date_to: dateTo, group_by: groupBy },
        headers: { 'X-CSRF-Token': window.csrfToken || '' }
    }).then(function (resp) {
        if (!resp.success || !resp.data) {
            showToast('error', fnT('load_failed'));
            return;
        }
        serviceReportData = resp.data;
        renderServiceReportStats(resp.data.totals || resp.data.summary);
        renderServiceReportTable(resp.data);
        $('#btnExportServiceExcel').prop('disabled', false);
        $('#btnPrintServiceReport').prop('disabled', false);
    }).catch(function () {
        showToast('error', fnT('load_failed'));
    });
}

function openServiceReportPrint() {
    if (!serviceReportData) return;
    var url = '../api/umrah/service_report_template.php?date_from=' + encodeURIComponent(serviceReportData.date_from) +
        '&date_to=' + encodeURIComponent(serviceReportData.date_to) +
        '&group_by=' + encodeURIComponent(serviceReportData.group_by) +
        '&language=en';
    window.open(url, '_blank');
}

function exportServiceReportExcel() {
    if (!serviceReportData) return;
    var url = '../api/umrah/service_report_excel.php?date_from=' + encodeURIComponent(serviceReportData.date_from) +
        '&date_to=' + encodeURIComponent(serviceReportData.date_to) +
        '&group_by=' + encodeURIComponent(serviceReportData.group_by) +
        '&language=en';
    window.open(url, '_blank');
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
    // Finance dashboard requires umrah.finance_view (set server-side).
    if (!window.UMRAH_CAN_FINANCE) return;
    $('#btnRefreshFinance').on('click', loadFinanceDashboard);
    loadFinanceDashboard();

    // Service Report tab handlers
    $('#btnLoadServiceReport').on('click', loadServiceReport);
    $('#btnPrintServiceReport').on('click', openServiceReportPrint);
    $('#btnExportServiceExcel').on('click', exportServiceReportExcel);

    // Set default dates: first of current month to today
    var now = new Date();
    var firstOfMonth = new Date(now.getFullYear(), now.getMonth(), 1);
    var fmt = function (d) { return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); };
    $('#svcDateTo').val(fmt(now));
    $('#svcDateFrom').val(fmt(firstOfMonth));
});
