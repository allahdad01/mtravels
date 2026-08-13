/**
 * Transport Management — Phase 24+
 * admin/umrah_transport.php controller: transport contracts with the same
 * pricing scheme as hotel contracts (period | per_trip), amount-based —
 * the contracted amount is divided among the trip's members at fulfillment.
 */

let tcData = null; // last dashboard payload

function tcEsc(str) {
    return String(str == null ? '' : str).replace(/[&<>"']/g, c => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[c]);
}

function tcT(key) {
    return (window.transportLabels && window.transportLabels[key]) || key;
}

function tcAjax(url, params, method) {
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
function loadTransportDashboard() {
    $('#overviewStats').html('<div class="col-12"><div class="text-muted py-3">' + tcT('loading') + '...</div></div>');
    tcAjax('../api/umrah/transport/get_transport_dashboard.php').then(data => {
        if (!data.success) {
            showToast('error', data.message || 'Failed to load');
            return;
        }
        tcData = data;
        renderTransportStats(data.stats);
        renderTransportContractsTable(data.contracts);
    }).catch(() => showToast('error', tcT('load_failed')));
}

// ============================================================== OVERVIEW
function renderTransportStats(stats) {
    const s = stats || {};
    const cards = [
        { label: tcT('active_contracts'), value: s.contracts, icon: 'icon-file-text', color: '#16a34a' },
        { label: tcT('total_contracts'), value: s.total_contracts, icon: 'icon-layers', color: '#0e7490' },
        { label: tcT('total_amount'), value: tcMoney(s.contract_amount), icon: 'icon-dollar-sign', color: '#7c3aed' }
    ];
    $('#overviewStats').html(cards.map(c =>
        '<div class="col-6 col-md-4 mb-2">' +
            '<div class="card text-center" style="border-top: 3px solid ' + c.color + ';">' +
                '<div class="card-body py-3">' +
                    '<i class="feather ' + c.icon + '" style="color: ' + c.color + ';"></i>' +
                    '<div style="font-size: 1.4rem; font-weight: 700; color: ' + c.color + ';">' + c.value + '</div>' +
                    '<div style="font-size: 0.75rem; color: #6b7280;">' + c.label + '</div>' +
                '</div>' +
            '</div>' +
        '</div>'
    ).join(''));
}

// ============================================================== CONTRACTS TABLE
function renderTransportContractsTable(contracts) {
    const $w = $('#contractsTableWrap');
    if (!contracts || contracts.length === 0) {
        $w.html('<div class="text-muted py-4 text-center">' + tcT('no_contracts') + '</div>');
        return;
    }
    const rows = contracts.map(c => {
        const typeLabel = c.contract_type === 'per_trip'
            ? '<span class="uh-badge uh-badge--slate" title="' + tcT('contract_type_per_trip') + '">' + tcT('per_trip') + '</span>'
            : '<span class="uh-badge uh-badge--blue" title="' + tcT('contract_type_period') + '">' + tcT('period') + '</span>';
        return '<tr>' +
            '<td><strong>' + tcEsc(c.contract_number || '—') + '</strong>' + typeLabel + '</td>' +
            '<td>' + tcEsc(c.supplier_name || '—') + '</td>' +
            '<td><strong>' + tcMoney(c.contract_amount, c.contract_currency) + '</strong></td>' +
            '<td>' + tcEsc(c.valid_from || '—') + ' → ' + tcEsc(c.valid_to || '—') + '</td>' +
            '<td>' + (c.status === 'active' ? '<span class="uh-badge uh-badge--green">' + tcT('active') + '</span>' : c.status === 'expired' ? '<span class="uh-badge uh-badge--amber">' + tcT('expired') + '</span>' : '<span class="uh-badge uh-badge--slate">' + tcT('inactive') + '</span>') + '</td>' +
            '<td class="text-right">' + (window.canManageTransport === false ? '' :
                '<button class="btn btn-xs btn-outline-primary mr-1" onclick="openTransportContractForm(' + c.id + ')"><i class="feather icon-edit-2"></i></button>' +
                '<button class="btn btn-xs btn-outline-danger" onclick="deleteTransportContract(' + c.id + ')"><i class="feather icon-trash-2"></i></button>'
            ) + '</td>' +
        '</tr>';
    }).join('');
    $w.html(
        '<div class="table-responsive">' +
            '<table class="table table-sm table-bordered table-hover mb-0">' +
                '<thead class="thead-light"><tr>' +
                    '<th>' + tcT('contract_number') + '</th><th>' + tcT('supplier') + '</th><th>' + tcT('contract_amount') + '</th><th>' + tcT('validity') + '</th><th>' + tcT('status') + '</th><th></th>' +
                '</tr></thead>' +
                '<tbody>' + rows + '</tbody>' +
            '</table>' +
        '</div>'
    );
}

function tcMoney(n, cur) {
    return parseFloat(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + tcEsc(cur || 'USD');
}

// ============================================================== CONTRACT FORM
function openTransportContractForm(id) {
    const c = (tcData.contracts || []).find(x => x.id == id);

    $('#tcContractId').val(id || 0);
    $('#tcNumber').val(c ? c.contract_number || '' : '');
    $('#tcSupplier').html('<option value="">' + tcT('none') + '</option>' + (tcData.suppliers || []).map(s =>
        '<option value="' + s.id + '" data-currency="' + tcEsc(s.currency || 'USD') + '">' + tcEsc(s.name) + '</option>'
    ).join(''));
    $('#tcSupplier').val(c ? c.supplier_id || '' : '');
    $('#tcType').val(c ? c.contract_type || 'per_trip' : 'per_trip');
    $('#tcAmount').val(c ? c.contract_amount ?? '' : '');
    $('#tcCurrency').val(c ? c.contract_currency || 'USD' : 'USD');
    $('#tcValidFrom').val(c ? c.valid_from || '' : '');
    $('#tcValidTo').val(c ? c.valid_to || '' : '');
    $('#tcStatus').val(c ? c.status || 'active' : 'active');
    $('#tcPaymentTerms').val(c ? c.payment_terms || '' : '');
    $('#tcNotes').val(c ? c.notes || '' : '');
    toggleTransportType();

    // currency follows the selected supplier (change event only, not on prefill)
    $(document).off('change.transportSupplier').on('change.transportSupplier', '#tcSupplier', function() {
        if (!$(this).val()) return;
        const cur = ($(this).find(':selected').data('currency') || 'USD');
        $('#tcCurrency').val(cur);
    });

    $(document).off('change.transportType').on('change.transportType', '#tcType', toggleTransportType);

    $('#transportContractModal').modal('show');
}

function toggleTransportType() {
    const perTrip = $('#tcType').val() === 'per_trip';
    $('#tcTypeHelp').text(perTrip ? tcT('contract_type_per_trip_help') : tcT('contract_type_period_help'));
}

$(document).on('submit', '#transportContractForm', function(e) {
    e.preventDefault();
    showToast('info', tcT('saving') + '...');
    tcAjax('../api/umrah/transport/save_transport_contract.php', {
        action: 'save',
        id: $('#tcContractId').val(),
        contract_number: $('#tcNumber').val(),
        supplier_id: $('#tcSupplier').val(),
        contract_type: $('#tcType').val(),
        contract_amount: $('#tcAmount').val(),
        contract_currency: $('#tcCurrency').val(),
        valid_from: $('#tcValidFrom').val(),
        valid_to: $('#tcValidTo').val(),
        status: $('#tcStatus').val(),
        payment_terms: $('#tcPaymentTerms').val(),
        notes: $('#tcNotes').val()
    }, 'POST').then(res => {
        if (!res.success) {
            showToast('error', res.message || 'Save failed');
            return;
        }
        showToast('success', res.message || tcT('saved'));
        $('#transportContractModal').modal('hide');
        loadTransportDashboard();
    }).catch(() => showToast('error', tcT('save_failed')));
});

function deleteTransportContract(id) {
    if (!confirm(tcT('confirm_delete'))) return;
    showToast('info', tcT('deleting') + '...');
    tcAjax('../api/umrah/transport/save_transport_contract.php', { action: 'delete', id: id }, 'POST').then(res => {
        if (!res.success) { showToast('error', res.message || 'Failed'); return; }
        showToast('success', res.message || tcT('deleted'));
        loadTransportDashboard();
    }).catch(() => showToast('error', tcT('save_failed')));
}

// ============================================================== INIT
$(function() {
    if (!$('#btnRefreshTransport').length) return;
    loadTransportDashboard();
    $('#btnRefreshTransport').on('click', loadTransportDashboard);
});
