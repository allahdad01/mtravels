var suppliersData = [];

function loadSuppliers() {
    return $.ajax({
        url: '../api/umrah/get_suppliers.php',
        type: 'GET',
        dataType: 'json'
    }).then(data => {
        // The API returns { suppliers: [...], main_account: {...} }
        suppliersData = data.suppliers || [];

        if (suppliersData.length === 0) {

        }
    }).catch(error => { 

        suppliersData = [];
    });
}

let serviceRowCounter = 0;

function addServiceRow(serviceType = '', supplierId = '', basePrice = 0, soldPrice = 0) {
    serviceRowCounter++;
    const rowId = 'serviceRow_' + serviceRowCounter;

    const suppliersOptions = suppliersData.map(s => `<option value="${s.id}" data-currency="${s.currency}">${s.name}</option>`).join('');

    const rowHtml = `
        <div id="${rowId}" class="service-row-grid">
            <div class="grid-column-1">
                <div class="form-group">
                    <label>Service Type</label>
                    <select class="form-control service-type" name="services[${serviceRowCounter}][service_type]" required>
                        <option value="">Select Service Type</option>
                        <option value="all" ${serviceType==='all'?'selected':''}>All Services</option>
                        <option value="ticket" ${serviceType==='ticket'?'selected':''}>Ticket</option>
                        <option value="visa" ${serviceType==='visa'?'selected':''}>Visa</option>
                        <option value="hotel" ${serviceType==='hotel'?'selected':''}>Hotel</option>
                        <option value="transport" ${serviceType==='transport'?'selected':''}>Transport</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Supplier</label>
                    <select class="form-control service-supplier" name="services[${serviceRowCounter}][supplier_id]" required>
                        <option value="">Select Supplier</option>
                        ${suppliersOptions}
                    </select>
                </div>
                <div class="form-group">
                    <label>Currency</label>
                    <input type="text" class="form-control service-currency" name="services[${serviceRowCounter}][currency]" readonly>
                </div>
            </div>
            <div class="grid-column-2">
                <div class="form-group">
                    <label>Base Price</label>
                    <input type="number" class="form-control service-base-price" name="services[${serviceRowCounter}][base_price]" value="${basePrice}" min="0" step="0.01" required>
                </div>
                <div class="form-group">
                    <label>Sold Price</label>
                    <input type="number" class="form-control service-sold-price" name="services[${serviceRowCounter}][sold_price]" value="${soldPrice}" min="0" step="0.01" required>
                </div>
                <div class="form-group">
                    <label>Profit</label>
                    <input type="number" class="form-control service-profit" name="services[${serviceRowCounter}][profit]" readonly>
                </div>
            </div>
            <div class="grid-column-3">
                
                <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="button" class="btn btn-sm btn-danger btn-block" onclick="removeServiceRow('${rowId}')">
                        <i class="feather icon-trash-2"></i> Remove
                    </button>
                </div>
            </div>
        </div>
    `;

    $('.services-grid-body').append(rowHtml);
    if(supplierId) $(`#${rowId} .service-supplier`).val(supplierId).trigger('change');
    updateTotals();
}

function removeServiceRow(rowId) { $('#' + rowId).remove(); updateTotals(); }

function updateTotals() {
    let totalBase=0, totalSold=0, totalProfit=0;
    const discount = parseFloat($('#discount').val()) || 0;
    $('.services-grid-body .service-row-grid').each(function() {
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
    $(this).closest('.service-row-grid').find('.service-currency').val(currency);
});
$(document).on('input', '.service-base-price, .service-sold-price, #discount', updateTotals);

// Ensure at least one service row when modal opens
$('#umrahModal').on('shown.bs.modal', function() {
    if ($('.services-grid-body .service-row-grid').length === 0) {
        loadSuppliers().then(() => {
            addServiceRow();
            if (suppliersData.length === 0) {
                // Show warning if no suppliers available

            }
        }).catch(error => {

            addServiceRow(); // Still add row even if suppliers fail to load
        });
    }
});
