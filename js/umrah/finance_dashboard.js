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

function renderGroupProfitDetail(data) {
    var $table = $('#memberProfitTable');
    var members = data.members || [];
    if (!members.length) {
        $table.html('<div class="text-muted py-4 text-center">' + fnT('no_data') + '</div>');
        return;
    }

    // Check if multi-group (more than one distinct group)
    var groupIds = {};
    members.forEach(function (m) { if (m.group_id) groupIds[m.group_id] = true; });
    var isMultiGroup = Object.keys(groupIds).length > 1;

    // Build group → client → family hierarchy
    var byGroup = {};
    members.forEach(function (m) {
        var groupKey = isMultiGroup ? (m.group_id || '_single') : '_single';
        var groupLabel = m.group_name || m.group_number || '—';
        var clientKey = m.client_name || '—';
        var familyKey = m.head_of_family || '—';
        if (!byGroup[groupKey]) byGroup[groupKey] = { label: groupLabel, clients: {} };
        if (!byGroup[groupKey].clients[clientKey]) byGroup[groupKey].clients[clientKey] = {};
        if (!byGroup[groupKey].clients[clientKey][familyKey]) byGroup[groupKey].clients[clientKey][familyKey] = [];
        byGroup[groupKey].clients[clientKey][familyKey].push(m);
    });

    var html = '<div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead class="thead-light"><tr>' +
        '<th class="text-center" style="width:4%;">#</th>' +
        '<th style="width:13%;">' + fnT('name') + '</th>' +
        '<th style="width:11%;">' + fnT('col_fname') + '</th>' +
        '<th style="width:10%;">' + fnT('col_passport') + '</th>' +
        '<th style="width:22%;">' + fnT('service_type') + '</th>' +
        '<th class="text-center" style="width:10%;">' + fnT('cost') + '</th>' +
        '<th class="text-center" style="width:10%;">' + fnT('total_selling') + '</th>' +
        '<th class="text-center" style="width:10%;">' + fnT('gross_profit') + '</th>' +
        '</tr></thead><tbody>';

    var i = 0;
    var grandCost = 0, grandSold = 0, grandProfit = 0;
    var totalRegular = 0, totalExtra = 0;

    Object.keys(byGroup).sort().forEach(function (groupKey) {
        var groupData = byGroup[groupKey];
        var groupName = groupData.label;
        var clients = groupData.clients;

        // Group header (only when multi-group)
        if (isMultiGroup) {
            html += '<tr><td colspan="8" style="background:#374151; color:#fff; font-weight:700; border-top:2px solid #111827;">' +
                '<i class="feather icon-layers mr-1"></i>' + fnT('group_name') + ': ' + fnEsc(groupName) + '</td></tr>';
        }

        var groupCost = 0, groupSold = 0, groupProfit = 0;

        Object.keys(clients).sort().forEach(function (clientName) {
            var families = clients[clientName];

            // Client header
            html += '<tr><td colspan="8" style="background:#dbeafe; font-weight:700; border-top:2px solid #3b82f6;">' +
                fnT('client') + ': ' + fnEsc(clientName) + '</td></tr>';

            Object.keys(families).sort().forEach(function (familyName) {
                var fmembers = families[familyName];
                var famCost = 0, famSold = 0, famProfit = 0;
                var famRegular = 0, famExtra = 0;

                fmembers.forEach(function (m) {
                    famCost += m.cost_total || 0;
                    famSold += m.sold_total || 0;
                    famProfit += m.profit || 0;
                    if (m.is_extra_bed || m.is_extra_transport) { famExtra++; } else { famRegular++; }
                });

                // Family header
                var extraLabel = famExtra > 0 ? ' + ' + famExtra + ' extra' : '';
                html += '<tr><td colspan="8" style="background:#f3f4f6; font-weight:600; border-top:1px solid #9ca3af;">' +
                    fnT('family') + ': ' + fnEsc(familyName) + ' (' + famRegular + ' ' + fnT('member') + extraLabel + ')</td></tr>';

                // Member rows
                fmembers.forEach(function (m) {
                    i++;
                    var extraTag = (m.is_extra_bed || m.is_extra_transport) ? ' <span style="color:#d97706;font-size:9px;font-weight:600;">(extra)</span>' : '';
                    var profitCls = (m.profit || 0) >= 0 ? 'text-success' : 'text-danger';

                    html += '<tr>' +
                        '<td class="text-center">' + i + '</td>' +
                        '<td>' + fnEsc(m.name) + extraTag + '</td>' +
                        '<td>' + fnEsc(m.fname) + '</td>' +
                        '<td>' + fnEsc(m.passport_number) + '</td>' +
                        '<td>';

                    if (m.services && m.services.length) {
                        m.services.forEach(function (s) {
                            html += '<span style="display:block;">' + fnEsc(s.label) + ' &mdash; <b>' + fnMoney(s.cost, 'USD') + '</b></span>';
                        });
                    } else {
                        html += '<span style="color:#6b7280;">&mdash;</span>';
                    }

                    html += '</td>' +
                        '<td class="text-center">' + fnMoney(m.cost_total, 'USD') + '</td>' +
                        '<td class="text-center">' + fnMoney(m.sold_total, 'USD') + '</td>' +
                        '<td class="text-center font-weight-bold ' + profitCls + '">' + fnMoney(m.profit, 'USD') + '</td>' +
                        '</tr>';
                });

                // Family subtotal
                var famProfitCls = famProfit >= 0 ? 'text-success' : 'text-danger';
                html += '<tr style="background:#fef3c7;font-weight:600;border-top:1px solid #d97706;">' +
                    '<td colspan="5" style="padding-left:20px;">' + fnEsc(familyName) + ' &mdash; Subtotal</td>' +
                    '<td class="text-center">' + fnMoney(famCost, 'USD') + '</td>' +
                    '<td class="text-center">' + fnMoney(famSold, 'USD') + '</td>' +
                    '<td class="text-center ' + famProfitCls + '">' + fnMoney(famProfit, 'USD') + '</td>' +
                    '</tr>';

                groupCost += famCost; groupSold += famSold; groupProfit += famProfit;
                totalRegular += famRegular; totalExtra += famExtra;
            });

            // Client subtotal (only when multi-group)
            if (isMultiGroup) {
                var clientProfitCls = groupProfit >= 0 ? 'text-success' : 'text-danger';
            }
        });

        // Group subtotal (only when multi-group)
        if (isMultiGroup) {
            var grpProfitCls = groupProfit >= 0 ? 'text-success' : 'text-danger';
            html += '<tr style="background:#374151; color:#fff; font-weight:700; border-top:2px solid #111827;">' +
                '<td colspan="5">' + fnT('group_name') + ': ' + fnEsc(groupName) + ' &mdash; Subtotal</td>' +
                '<td class="text-center">' + fnMoney(groupCost, 'USD') + '</td>' +
                '<td class="text-center">' + fnMoney(groupSold, 'USD') + '</td>' +
                '<td class="text-center ' + grpProfitCls + '">' + fnMoney(groupProfit, 'USD') + '</td>' +
                '</tr>';
            grandCost += groupCost; grandSold += groupSold; grandProfit += groupProfit;
        } else {
            grandCost += groupCost; grandSold += groupSold; grandProfit += groupProfit;
        }
    });

    // Grand total
    var memberLabel = totalRegular + ' ' + fnT('member') + (totalExtra > 0 ? ' + ' + totalExtra + ' extra' : '');
    var grandProfitCls = grandProfit >= 0 ? 'text-success' : 'text-danger';
    html += '<tr style="background:#e5e7eb;font-weight:700;border-top:2px solid #374151;">' +
        '<td colspan="5">' + fnT('grand_total') + ' (' + memberLabel + ')</td>' +
        '<td class="text-center">' + fnMoney(grandCost, 'USD') + '</td>' +
        '<td class="text-center">' + fnMoney(grandSold, 'USD') + '</td>' +
        '<td class="text-center ' + grandProfitCls + '">' + fnMoney(grandProfit, 'USD') + '</td>' +
        '</tr>';

    html += '</tbody></table></div>';
    $table.html(html);
}

function loadProfitGroups() {
    var dateFrom = $('#profitDateFrom').val();
    var dateTo = $('#profitDateTo').val();
    var params = { report: 'service_groups' };
    if (dateFrom) params.date_from = dateFrom;
    if (dateTo) params.date_to = dateTo;

    $.ajax({
        url: '../api/umrah/get_finance_report.php',
        type: 'GET',
        dataType: 'json',
        data: params,
        headers: { 'X-CSRF-Token': window.csrfToken || '' }
    }).then(function (resp) {
        if (!resp.success || !resp.data) return;
        if (typeof window.populateGroupDropdown === 'function') {
            window.populateGroupDropdown(resp.data);
        } else {
            var $sel = $('#profitGroupSelect');
            var html = '';
            resp.data.forEach(function (g) {
                html += '<option value="' + g.group_id + '">#' + fnEsc(g.group_number) + ' — ' + fnEsc(g.group_name) + '</option>';
            });
            $sel.html(html);
        }
    });
}

function loadGroupProfitDetail() {
    var dateFrom = $('#profitDateFrom').val();
    var dateTo = $('#profitDateTo').val();
    var groupIds = (typeof window.getSelectedGroupIds === 'function') ? window.getSelectedGroupIds() : [];
    if (!groupIds.length) {
        var groupVal = $('#profitGroupSelect').val() || '';
        groupIds = Array.isArray(groupVal) ? groupVal : (groupVal && groupVal !== 'all' ? [groupVal] : []);
    }

    $('#memberProfitTable').html('<div class="text-muted py-4 text-center">' + fnT('loading') + '...</div>');

    var params = { report: 'group_profit_detail' };
    if (groupIds.length) params.group_ids = groupIds.join(',');
    if (dateFrom) params.date_from = dateFrom;
    if (dateTo) params.date_to = dateTo;

    $.ajax({
        url: '../api/umrah/get_finance_report.php',
        type: 'GET',
        dataType: 'json',
        data: params,
        headers: { 'X-CSRF-Token': window.csrfToken || '' }
    }).then(function (resp) {
        if (!resp.success || !resp.data) {
            showToast('error', fnT('load_failed'));
            return;
        }
        window.profitDetailData = { group_ids: groupIds.length ? groupIds : (resp.data._all_group_ids || []), date_from: dateFrom, date_to: dateTo };
        renderGroupProfitDetail(resp.data);
    }).catch(function () {
        showToast('error', fnT('load_failed'));
    });
}

function openProfitPrint() {
    var d = window.profitDetailData || {};
    var lang = $('#profitLangSelect').val() || 'en';
    var url = '../api/umrah/profit_report_template.php?scope=group&language=' + encodeURIComponent(lang);
    if (d.group_ids && d.group_ids.length === 1) {
        url += '&id=' + d.group_ids[0];
    }
    if (d.date_from) url += '&date_from=' + encodeURIComponent(d.date_from);
    if (d.date_to) url += '&date_to=' + encodeURIComponent(d.date_to);
    if (d.group_ids && d.group_ids.length > 1) url += '&group_ids=' + encodeURIComponent(d.group_ids.join(','));
    window.open(url, '_blank');
}

function openProfitExcel() {
    var d = window.profitDetailData || {};
    var lang = $('#profitLangSelect').val() || 'en';
    var url = '../api/umrah/profit_report_excel.php?scope=group&language=' + encodeURIComponent(lang);
    if (d.group_ids && d.group_ids.length === 1) {
        url += '&id=' + d.group_ids[0];
    }
    if (d.date_from) url += '&date_from=' + encodeURIComponent(d.date_from);
    if (d.date_to) url += '&date_to=' + encodeURIComponent(d.date_to);
    if (d.group_ids && d.group_ids.length > 1) url += '&group_ids=' + encodeURIComponent(d.group_ids.join(','));
    window.open(url, '_blank');
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

function renderServiceReportStats(data) {
    fnStatCards('serviceReportStats', [
        { label: fnT('total_members'), value: data.total_members || 0 },
        { label: fnT('total_cost'), value: fnMoney(data.cost_total, 'USD') },
        { label: fnT('service_type'), value: data.service_count || 0 },
    ]);
}

function renderServiceReportTable(data) {
    var $table = $('#serviceReportTable');
    var members = data.members || [];
    if (!members.length) {
        $table.html('<div class="text-muted py-4 text-center">' + fnT('no_data') + '</div>');
        return;
    }

    // Group by client → family (like profit report)
    var byClient = {};
    members.forEach(function (m) {
        var clientKey = m.client_name || '—';
        var familyKey = m.head_of_family || '—';
        if (!byClient[clientKey]) byClient[clientKey] = {};
        if (!byClient[clientKey][familyKey]) byClient[clientKey][familyKey] = [];
        byClient[clientKey][familyKey].push(m);
    });

    var html = '<div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead class="thead-light"><tr>' +
        '<th class="text-center" style="width:4%;">#</th>' +
        '<th style="width:14%;">' + fnT('name') + '</th>' +
        '<th style="width:12%;">' + fnT('col_fname') + '</th>' +
        '<th style="width:11%;">' + fnT('col_passport') + '</th>' +
        '<th style="width:24%;">' + fnT('service_type') + '</th>' +
        '<th class="text-center" style="width:15%;">' + fnT('cost') + ' (USD)</th>' +
        '</tr></thead><tbody>';

    var i = 0;
    var grandCost = 0;
    var totalRegular = 0;
    var totalExtra = 0;

    Object.keys(byClient).sort().forEach(function (clientName) {
        var families = byClient[clientName];

        // Client header
        html += '<tr class="client-header"><td colspan="6" style="background:#dbeafe; font-weight:700; border-top:2px solid #3b82f6;">' +
            fnT('client') + ': ' + fnEsc(clientName) + '</td></tr>';

        Object.keys(families).sort().forEach(function (familyName) {
            var fmembers = families[familyName];
            var famCost = 0;
            var famRegular = 0;
            var famExtra = 0;

            fmembers.forEach(function (m) {
                famCost += m.cost_total || 0;
                if (m.is_extra_bed || m.is_extra_transport) { famExtra++; } else { famRegular++; }
            });

            // Family header
            var extraLabel = famExtra > 0 ? ' + ' + famExtra + ' extra' : '';
            html += '<tr class="family-header"><td colspan="6" style="background:#f3f4f6; font-weight:600; border-top:1px solid #9ca3af;">' +
                fnT('family') + ': ' + fnEsc(familyName) + ' (' + famRegular + ' ' + fnT('member') + extraLabel + ')</td></tr>';

            // Member rows
            fmembers.forEach(function (m) {
                i++;
                var extraTag = (m.is_extra_bed || m.is_extra_transport) ? ' <span style="color:#d97706;font-size:9px;font-weight:600;">(extra)</span>' : '';
                html += '<tr>' +
                    '<td class="text-center">' + i + '</td>' +
                    '<td>' + fnEsc(m.name) + extraTag + '</td>' +
                    '<td>' + fnEsc(m.fname) + '</td>' +
                    '<td>' + fnEsc(m.passport_number) + '</td>' +
                    '<td>';

                if (m.services && m.services.length) {
                    m.services.forEach(function (s) {
                        html += '<span style="display:block;">' + fnEsc(s.label) + ' &mdash; <b>' + fnMoney(s.cost, 'USD') + '</b></span>';
                    });
                } else {
                    html += '<span style="color:#6b7280;">&mdash;</span>';
                }

                html += '</td>' +
                    '<td class="text-center font-weight-bold">' + fnMoney(m.cost_total, 'USD') + '</td>' +
                    '</tr>';
            });

            // Family subtotal
            html += '<tr style="background:#fef3c7;font-weight:600;border-top:1px solid #d97706;">' +
                '<td colspan="5" style="padding-left:20px;">' + fnEsc(familyName) + ' &mdash; Subtotal</td>' +
                '<td class="text-center">' + fnMoney(famCost, 'USD') + '</td>' +
                '</tr>';

            grandCost += famCost;
            totalRegular += famRegular;
            totalExtra += famExtra;
        });
    });

    // Grand total
    var memberLabel = totalRegular + ' ' + fnT('member') + (totalExtra > 0 ? ' + ' + totalExtra + ' extra' : '');
    html += '<tr style="background:#e5e7eb;font-weight:700;border-top:2px solid #374151;">' +
        '<td colspan="5">' + fnT('grand_total') + ' (' + memberLabel + ')</td>' +
        '<td class="text-center">' + fnMoney(grandCost, 'USD') + '</td>' +
        '</tr>';

    html += '</tbody></table></div>';
    $table.html(html);
}

function loadServiceReport() {
    var dateFrom = $('#svcDateFrom').val();
    var dateTo = $('#svcDateTo').val();

    if (!dateFrom || !dateTo) {
        showToast('warning', 'Please select date range');
        return;
    }

    // Collect selected service types from badge buttons
    var serviceTypes = [];
    $('.svc-type-badge.active').each(function () {
        serviceTypes.push($(this).data('svc'));
    });

    var groupId = $('#svcGroupFilter').val() || '';

    $('#serviceReportTable').html('<div class="text-muted py-4 text-center">' + fnT('loading') + '...</div>');
    $('#serviceReportStats').html('');

    var params = { report: 'service_detail', date_from: dateFrom, date_to: dateTo };
    if (serviceTypes.length) {
        params.service_types = serviceTypes.join(',');
    }
    if (groupId) {
        params.group_id = groupId;
    }

    $.ajax({
        url: '../api/umrah/get_finance_report.php',
        type: 'GET',
        dataType: 'json',
        data: params,
        headers: { 'X-CSRF-Token': window.csrfToken || '' }
    }).then(function (resp) {
        if (!resp.success || !resp.data) {
            showToast('error', fnT('load_failed'));
            return;
        }
        serviceReportData = resp.data;
        serviceReportData.service_types = serviceTypes;
        renderServiceReportStats(resp.data);
        renderServiceReportTable(resp.data);
        $('#btnExportServiceExcel').prop('disabled', false);
        $('#btnPrintServiceReport').prop('disabled', false);
    }).catch(function () {
        showToast('error', fnT('load_failed'));
    });
}

function loadServiceGroups() {
    var dateFrom = $('#svcDateFrom').val();
    var dateTo = $('#svcDateTo').val();
    if (!dateFrom || !dateTo) return;

    var $sel = $('#svcGroupFilter');
    var prevVal = $sel.val();
    $sel.html('<option value="">' + fnT('loading') + '...</option>');

    $.ajax({
        url: '../api/umrah/get_finance_report.php',
        type: 'GET',
        dataType: 'json',
        data: { report: 'service_groups', date_from: dateFrom, date_to: dateTo },
        headers: { 'X-CSRF-Token': window.csrfToken || '' }
    }).then(function (resp) {
        if (!resp.success || !resp.data) {
            $sel.html('<option value="">' + fnT('no_data') + '</option>');
            return;
        }
        var html = '<option value="">' + fnT('all') + '</option>';
        resp.data.forEach(function (g) {
            var label = '#' + g.group_number + ' — ' + g.group_name;
            html += '<option value="' + g.group_id + '">' + fnEsc(label) + '</option>';
        });
        $sel.html(html);
        if (prevVal) $sel.val(prevVal);
    }).catch(function () {
        $sel.html('<option value="">' + fnT('load_failed') + '</option>');
    });
}

function openServiceReportPrint() {
    if (!serviceReportData) return;
    var url = '../api/umrah/service_report_template.php?date_from=' + encodeURIComponent(serviceReportData.date_from) +
        '&date_to=' + encodeURIComponent(serviceReportData.date_to) +
        '&language=en';
    if (serviceReportData.service_types && serviceReportData.service_types.length) {
        url += '&service_types=' + encodeURIComponent(serviceReportData.service_types.join(','));
    }
    window.open(url, '_blank');
}

function exportServiceReportExcel() {
    if (!serviceReportData) return;
    var url = '../api/umrah/service_report_excel.php?date_from=' + encodeURIComponent(serviceReportData.date_from) +
        '&date_to=' + encodeURIComponent(serviceReportData.date_to) +
        '&language=en';
    if (serviceReportData.service_types && serviceReportData.service_types.length) {
        url += '&service_types=' + encodeURIComponent(serviceReportData.service_types.join(','));
    }
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

    // Group Profitability handlers
    $('#btnLoadProfitDetail').on('click', loadGroupProfitDetail);
    $('#btnProfitPrint').on('click', openProfitPrint);
    $('#btnProfitExcel').on('click', openProfitExcel);

    // Reload groups when date range changes
    var profitDateTimer = null;
    $('#profitDateFrom, #profitDateTo').on('change', function () {
        clearTimeout(profitDateTimer);
        profitDateTimer = setTimeout(function () { loadProfitGroups(); }, 300);
    });

    // Set default dates for profit tab
    var now2 = new Date();
    var firstOfMonth2 = new Date(now2.getFullYear(), now2.getMonth(), 1);
    var fmt2 = function (d) { return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); };
    $('#profitDateTo').val(fmt2(now2));
    $('#profitDateFrom').val(fmt2(firstOfMonth2));

    loadProfitGroups();

    // Load groups when dates change
    var dateLoadTimer = null;
    function onSvcDateChange() {
        clearTimeout(dateLoadTimer);
        dateLoadTimer = setTimeout(function () {
            loadServiceGroups();
        }, 300);
    }
    $('#svcDateFrom, #svcDateTo').on('change', onSvcDateChange);

    // Toggle badge buttons
    $('#svcTypeBadges').on('click', '.svc-type-badge', function (e) {
        e.preventDefault();
        $(this).toggleClass('active');
    });

    // Set default dates: first of current month to today
    var now = new Date();
    var firstOfMonth = new Date(now.getFullYear(), now.getMonth(), 1);
    var fmt = function (d) { return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); };
    $('#svcDateTo').val(fmt(now));
    $('#svcDateFrom').val(fmt(firstOfMonth));

    // Load groups on init
    loadServiceGroups();
});
