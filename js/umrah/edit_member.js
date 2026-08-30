let editServiceRowCounter = 0;
let editGrandSoldEdited = false;

const EDIT_SERVICE_TYPE_LABELS = {
    'all': 'All Services',
    'ticket': 'Ticket',
    'visa': 'Visa',
    'hotel': 'Hotel',
    'transport': 'Transport',
    'ticket+visa': 'Ticket + Visa',
    'ticket+hotel': 'Ticket + Hotel',
    'ticket+transport': 'Ticket + Transport',
    'visa+services': 'Visa + Services',
    'visa+hotel': 'Visa + Hotel',
    'visa+transport': 'Visa + Transport',
    'hotel+transport': 'Hotel + Transport',
    'umrah': 'Umrah',
    'package': 'Package'
};

function addEditServiceRow(service = {}, $target) {
    editServiceRowCounter++;
    const rowId = 'editServiceRow_' + editServiceRowCounter;
    const i = editServiceRowCounter;
    const $parent = $target || $('.edit-services-grid-body');

    const serviceType = service.service_type || '';
    const serviceTypeLabel = EDIT_SERVICE_TYPE_LABELS[serviceType] || serviceType || 'Service';
    const quantity = parseInt(service.quantity) || 1;
    const pricingUnit = service.pricing_unit || '';
    const unitText = quantity > 1 ? `${quantity} × ${pricingUnit || 'unit'}` : (pricingUnit || 'unit');
    const isOptional = service.is_optional ? parseInt(service.is_optional) : 0;

    const hidden = `
        <input type="hidden" name="edit_services[${i}][service_type]" value="${escapeHtml(serviceType)}">
        <input type="hidden" name="edit_services[${i}][supplier_id]" value="${escapeHtml(service.supplier_id || '')}">
        <input type="hidden" name="edit_services[${i}][base_price]" value="${escapeHtml(service.base_price || 0)}">
        <input type="hidden" name="edit_services[${i}][sold_price]" value="${escapeHtml(service.sold_price || 0)}">
        <input type="hidden" name="edit_services[${i}][profit]" value="${escapeHtml(service.profit || 0)}">
        <input type="hidden" name="edit_services[${i}][currency]" value="${escapeHtml(service.currency || '')}">
        <input type="hidden" name="edit_services[${i}][service_id]" value="${escapeHtml(service.service_id || '')}">
        <input type="hidden" name="edit_services[${i}][pricing_unit]" value="${escapeHtml(service.pricing_unit || '')}">
        <input type="hidden" name="edit_services[${i}][quantity]" value="${quantity}">
        <input type="hidden" name="edit_services[${i}][is_optional]" value="${isOptional}">
        <input type="hidden" name="edit_services[${i}][hotel_id]" value="${escapeHtml(service.hotel_id || '')}">
        <input type="hidden" name="edit_services[${i}][room_type_id]" value="${escapeHtml(service.room_type_id || '')}">
    `;

    const action = isOptional
        ? `<button type="button" class="btn btn-sm btn-outline-danger" style="padding:1px 8px; font-size:0.75rem; border-radius:999px;" onclick="removeEditServiceRow('${rowId}')"><i class="feather icon-x"></i></button>`
        : `<i class="feather icon-lock" style="width:0.8rem; height:0.8rem; color:#c2410c;"></i>`;

    const rowHtml = `
        <div id="${rowId}" class="edit-service-row-collapsed" style="display:inline-flex; align-items:center; gap:6px; background:#fff; border:1px solid #e5e7eb; border-radius:999px; padding:4px 12px;">
            ${action}
            <strong style="font-size:0.8rem; color:#1f2937;">${escapeHtml(serviceTypeLabel)}</strong>
            <span style="color:#6b7280; font-size:0.75rem;">${escapeHtml(unitText)}</span>
            ${hidden}
        </div>
    `;

    $parent.append(rowHtml);
    updateEditTotals();
}

function removeEditServiceRow(rowId) {
    const $row = $('#' + rowId);
    $row.remove();
    const $container = $row.closest('.edit-service-row-collapsed');
    if ($container.length && $container.find('[id^="editServiceRow_"]').length === 0 && $container.find('.edit-empty-line').length === 0) {
        $container.remove();
    }
    updateEditTotals();
}

function addEditEmptyServiceRow($target) {
    const rowId = 'editRow_Empty';
    const line = `
        <div id="${rowId}" class="edit-empty-line" style="flex:1 1 100%; color:#6b7280; font-size:0.8rem; padding:2px 4px;">
            No package services assigned yet — supplier &amp; pricing are set at fulfillment.
        </div>
    `;
    ($target || $('.edit-services-grid-body')).append(line);
}

function getEditSaleCurrency() { return ($('#editSaleCurrency').val() || 'USD').toUpperCase(); }

function getEditExchangeRate() { const r = parseFloat($('#editExchangeRate').val()); return (r && r > 0) ? r : 1; }

function updateEditTotals() {
    let totalBase = 0, totalSold = 0;
    const discount = parseFloat($('#editDiscount').val()) || 0;
    const sale = getEditSaleCurrency();
    const rate = getEditExchangeRate();
    $('.edit-services-grid-body .edit-service-row-collapsed').each(function() {
        const base = parseFloat($(this).find('input[name$="[base_price]"]').val()) || 0;
        const sold = parseFloat($(this).find('input[name$="[sold_price]"]').val()) || 0;
        const cur = ($(this).find('input[name$="[currency]"]').val() || '').trim().toUpperCase();
        const baseInSale = (!cur || cur === sale) ? base : base / rate;
        totalBase += baseInSale;
        totalSold += sold;
    });

    if (!editGrandSoldEdited) {
        $('#editTotalSoldPrice').val(totalSold.toFixed(2));
    }
    totalSold = parseFloat($('#editTotalSoldPrice').val()) || 0;
    const discountedSold = totalSold - discount;
    const finalProfit = discountedSold - totalBase;

    $('#editTotalBasePrice').val(totalBase.toFixed(2));
    $('#editTotalProfit').val(finalProfit.toFixed(2));

    let hidden = $('#editTotalBasePriceHidden');
    if (hidden.length === 0) {
        hidden = $('<input type="hidden" id="editTotalBasePriceHidden" name="total_base_price">');
        $('#editMemberForm').append(hidden);
    }
    hidden.val(totalBase.toFixed(2));

    $('#editTotalProfitHidden').length === 0
        ? $('<input type="hidden" id="editTotalProfitHidden" name="total_profit">').appendTo('#editMemberForm').val(finalProfit.toFixed(2))
        : $('#editTotalProfitHidden').val(finalProfit.toFixed(2));

    const paid = parseFloat($('#editPaidAmount')?.val() || 0);
    $('#editDue').val((discountedSold - paid).toFixed(2));
}

// Event bindings
$(document).on('input', '#editTotalSoldPrice, #editDiscount, #editExchangeRate', function() {
    if ($(this).attr('id') === 'editTotalSoldPrice') { editGrandSoldEdited = true; }
    updateEditTotals();
});
$(document).on('change', '#editSaleCurrency', updateEditTotals);

$(document).on('submit', '#editMemberForm', function(event) {
    event.preventDefault();

    const submitBtn = event.target.querySelector('button[type="submit"]');
    const originalHtml = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="feather icon-loader"></i> Updating...';

    let formData = new FormData(event.target);

    fetch("../api/umrah/update_umrah_member.php", {
        method: "POST",
        body: formData,
    })
    .then(response => response.json())
    .then(data => {

        if (data.success) {
            showToast('success', data.message || 'Umrah member updated successfully');
            setTimeout(() => location.reload(), 800);
        } else {
            showToast('error', data.message || 'Failed to update member');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalHtml;
        }
    })
    .catch(error => {

        showToast('error', 'An error occurred');
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalHtml;
    });
});

function openEditMemberModal(bookingId) {

    showToast('info', 'Loading... Please wait');

    fetch(`../api/umrah/get_member_details.php?booking_id=${bookingId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.member) {
                const member = data.member;

                document.getElementById('editBookingId').value = member.booking_id;
                document.getElementById('editSoldTo').value = member.sold_to;
                document.getElementById('editPaidTo').value = member.paid_to;
                document.getElementById('editEntry_date').value = member.entry_date;
                document.getElementById('editName').value = member.name;
                document.getElementById('editDob').value = member.dob;
                document.getElementById('editGender').value = member.gender;
                document.getElementById('editFather_name').value = member.fname;
                document.getElementById('editPassport_number').value = member.passport_number;
                document.getElementById('editPassport_expiry').value = member.passport_expiry;
                document.getElementById('editId_type').value = member.id_type;
                document.getElementById('editFlight_date').value = member.flight_date;
                document.getElementById('editReturn_date').value = member.return_date;
                document.getElementById('editDuration').value = member.duration;
                document.getElementById('editRoom_type').value = member.room_type;
                document.getElementById('editDiscount').value = member.discount || 0;
                document.getElementById('editRemarks').value = member.remarks || '';
                document.getElementById('editSaleCurrency').value = member.currency || 'USD';
                document.getElementById('editExchangeRate').value = (member.exchange_rate && member.exchange_rate > 0) ? member.exchange_rate : 1;
                document.getElementById('editPassengerType').value = member.passenger_type || 'adult';
                document.getElementById('editSoldPrice').value = (parseFloat(member.sold_price) || 0).toFixed(2);

                $('.edit-services-grid-body').empty();
                editServiceRowCounter = 0;
                editGrandSoldEdited = true;
                $('#editTotalSoldPrice').val((parseFloat(member.sold_price) || 0).toFixed(2));

                const addRows = () => {
                    const $container = $('<div class="edit-service-row-collapsed" style="display:flex; flex-wrap:wrap; align-items:center; gap:8px; background:#f8fafc; border:1px dashed #dee2e6; border-radius:8px; padding:10px 14px;"></div>');
                    $('.edit-services-grid-body').append($container);
                    if (member.services && member.services.length > 0) {
                        member.services.forEach(service => {
                            addEditServiceRow(service, $container);
                        });
                        $('#editEmptyServicesNote').addClass('d-none');
                    } else {
                        addEditEmptyServiceRow($container);
                        $('#editEmptyServicesNote').removeClass('d-none');
                    }
                    updateEditTotals();
                };

                const loadData = () => {
                    if (suppliersData.length === 0) {
                        loadSuppliers().then(addRows);
                    } else {
                        addRows();
                    }
                };

                loadData();

                if (member.has_refund) {
                    document.getElementById('editSoldTo').disabled = true;
                } else {
                    document.getElementById('editSoldTo').disabled = false;
                }

                $('#editMemberModal').modal('show');
            } else {
                showToast('error', data.message || 'Failed to load member details');
            }
        })
        .catch(error => {
            showToast('error', 'Failed to load member details');
        });
}