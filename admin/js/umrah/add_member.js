var suppliersData = [];

function loadSuppliers() {
    return $.getJSON('ajax/get_suppliers.php').then(data => {
        suppliersData = data.success ? data.suppliers : [];
        console.log('Suppliers loaded:', suppliersData.length);
    }).catch(() => { suppliersData = []; });
}

let serviceRowCounter = 0;

function addServiceRow(serviceType = '', supplierId = '', basePrice = 0, soldPrice = 0) {
    serviceRowCounter++;
    const rowId = 'serviceRow_' + serviceRowCounter;

    const suppliersOptions = suppliersData.map(s => `<option value="${s.id}" data-currency="${s.currency}">${s.name}</option>`).join('');

    const rowHtml = `
        <tr id="${rowId}">
            <td>
                <select class="form-control service-type" name="services[${serviceRowCounter}][service_type]" required>
                    <option value="">Select Service Type</option>
                    <option value="all" ${serviceType==='all'?'selected':''}>All Services</option>
                    <option value="ticket" ${serviceType==='ticket'?'selected':''}>Ticket</option>
                    <option value="visa" ${serviceType==='visa'?'selected':''}>Visa</option>
                    <option value="hotel" ${serviceType==='hotel'?'selected':''}>Hotel</option>
                    <option value="transport" ${serviceType==='transport'?'selected':''}>Transport</option>
                </select>
            </td>
            <td>
                <select class="form-control service-supplier" name="services[${serviceRowCounter}][supplier_id]" required>
                    <option value="">Select Supplier</option>
                    ${suppliersOptions}
                </select>
            </td>
            <td><input type="text" class="form-control service-currency" name="services[${serviceRowCounter}][currency]" readonly></td>
            <td><input type="number" class="form-control service-base-price" name="services[${serviceRowCounter}][base_price]" value="${basePrice}" min="0" step="0.01" required></td>
            <td><input type="number" class="form-control service-sold-price" name="services[${serviceRowCounter}][sold_price]" value="${soldPrice}" min="0" step="0.01" required></td>
            <td><input type="number" class="form-control service-profit" name="services[${serviceRowCounter}][profit]" readonly></td>
            <td>
                <button type="button" class="btn btn-sm btn-danger" onclick="removeServiceRow('${rowId}')">
                    <i class="feather icon-trash-2"></i>
                </button>
            </td>
        </tr>
    `;

    $('#servicesTableBody').append(rowHtml);
    if(supplierId) $(`#${rowId} .service-supplier`).val(supplierId).trigger('change');
    updateTotals();
}

function removeServiceRow(rowId) { $('#' + rowId).remove(); updateTotals(); }

function updateTotals() {
    let totalBase=0, totalSold=0, totalProfit=0;
    const discount = parseFloat($('#discount').val()) || 0;
    $('#servicesTableBody tr').each(function() {
        const base = parseFloat($(this).find('.service-base-price').val()) || 0;
        const sold = parseFloat($(this).find('.service-sold-price').val()) || 0;
        const profit = sold - base;
        $(this).find('.service-profit').val(profit.toFixed(2));
        totalBase += base; totalSold += sold; totalProfit += profit;
    });
    const discountedSold = totalSold - discount;
    $('#totalBasePrice').val(totalBase.toFixed(2));
    $('#totalSoldPrice').val(discountedSold.toFixed(2));
    $('#totalProfit').val((discountedSold - totalBase).toFixed(2));
}

// Event bindings
$(document).on('click', '#addServiceBtn', () => addServiceRow());
$(document).on('change', '.service-supplier', function() {
    const currency = $(this).find('option:selected').data('currency') || '';
    $(this).closest('tr').find('.service-currency').val(currency);
});
$(document).on('input', '.service-base-price, .service-sold-price, #discount', updateTotals);

// Ensure at least one service row when modal opens
$('#umrahModal').on('shown.bs.modal', function() {
    if ($('#servicesTableBody tr').length === 0) {
        loadSuppliers().then(() => addServiceRow());
    }
});