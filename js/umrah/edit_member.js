let editServiceRowCounter = 0;

function addEditServiceRow(serviceType = '', supplierId = '', basePrice = 0, soldPrice = 0, serviceId = null) {
    editServiceRowCounter++;
    const rowId = 'editServiceRow_' + editServiceRowCounter;

    const suppliersOptions = suppliersData.map(s => `<option value="${s.id}" data-currency="${s.currency}">${s.name}</option>`).join('');

    const rowHtml = `
        <div id="${rowId}" class="edit-service-row-grid" data-service-id="${serviceId || ''}">
            <div class="edit-grid-column-1">
                <div class="form-group">
                    <label>Service Type</label>
                    <select class="form-control edit-service-type" name="edit_services[${editServiceRowCounter}][service_type]" required>
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
                    <select class="form-control edit-service-supplier" name="edit_services[${editServiceRowCounter}][supplier_id]" required>
                        <option value="">Select Supplier</option>
                        ${suppliersOptions}
                    </select>
                </div>
                <div class="form-group">
                    <label>Currency</label>
                    <input type="text" class="form-control edit-service-currency" name="edit_services[${editServiceRowCounter}][currency]" readonly>
                </div>
            </div>
            <div class="edit-grid-column-2">
                <div class="form-group">
                    <label>Base Price</label>
                    <input type="number" class="form-control edit-service-base-price" name="edit_services[${editServiceRowCounter}][base_price]" value="${basePrice}" min="0" step="0.01" required>
                </div>
                <div class="form-group">
                    <label>Sold Price</label>
                    <input type="number" class="form-control edit-service-sold-price" name="edit_services[${editServiceRowCounter}][sold_price]" value="${soldPrice}" min="0" step="0.01" required>
                </div>
                <div class="form-group">
                    <label>Profit</label>
                    <input type="number" class="form-control edit-service-profit" name="edit_services[${editServiceRowCounter}][profit]" readonly>
                </div>
            </div>
            <div class="edit-grid-column-3">
                
                <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="button" class="btn btn-sm btn-danger btn-block" onclick="removeEditServiceRow('${rowId}')">
                        <i class="feather icon-trash-2"></i> Remove
                    </button>
                </div>
            </div>
        </div>
    `;

    $('.edit-services-grid-body').append(rowHtml);
    if(supplierId) $(`#${rowId} .edit-service-supplier`).val(supplierId).trigger('change');
    updateEditTotals();
}

function removeEditServiceRow(rowId) { $('#' + rowId).remove(); updateEditTotals(); }

function updateEditTotals() {
    let totalBase=0, totalSold=0, totalProfit=0;
    const discount = parseFloat($('#editDiscount').val()) || 0;
    $('.edit-services-grid-body .edit-service-row-grid').each(function() {
        const base = parseFloat($(this).find('.edit-service-base-price').val()) || 0;
        const sold = parseFloat($(this).find('.edit-service-sold-price').val()) || 0;
        const profit = sold - base;
        $(this).find('.edit-service-profit').val(profit.toFixed(2));
        totalBase += base; totalSold += sold; totalProfit += profit;
    });
    const discountedSold = totalSold - discount;
    const finalProfit = discountedSold - totalBase;
    
    // Update visible totals
    $('#editTotalBasePrice').val(totalBase.toFixed(2));
    $('#editTotalSoldPrice').val(discountedSold.toFixed(2));
    $('#editTotalProfit').val(finalProfit.toFixed(2));

    // Update hidden fields to ensure they are sent in the form
    if ($('#editTotalBasePriceHidden').length === 0) {
        $('<input>').attr({
            type: 'hidden',
            id: 'editTotalBasePriceHidden',
            name: 'total_base_price',
            value: totalBase.toFixed(2)
        }).appendTo('#editMemberForm');
    } else {
        $('#editTotalBasePriceHidden').val(totalBase.toFixed(2));
    }

    if ($('#editTotalSoldPriceHidden').length === 0) {
        $('<input>').attr({
            type: 'hidden',
            id: 'editTotalSoldPriceHidden',
            name: 'total_sold_price',
            value: discountedSold.toFixed(2)
        }).appendTo('#editMemberForm');
    } else {
        $('#editTotalSoldPriceHidden').val(discountedSold.toFixed(2));
    }

    if ($('#editTotalProfitHidden').length === 0) {
        $('<input>').attr({
            type: 'hidden',
            id: 'editTotalProfitHidden',
            name: 'total_profit',
            value: finalProfit.toFixed(2)
        }).appendTo('#editMemberForm');
    } else {
        $('#editTotalProfitHidden').val(finalProfit.toFixed(2));
    }

    // Update due amount if needed
    const paid = parseFloat($('#editPaidAmount')?.val() || 0);
    const due = discountedSold - paid;
    $('#editDue').val(due.toFixed(2));
}


// Event bindings
$(document).on('click', '#editAddServiceBtn', () => addEditServiceRow());
$(document).on('change', '.edit-service-supplier', function() {
    const currency = $(this).find('option:selected').data('currency') || '';
    $(this).closest('.edit-service-row-grid').find('.edit-service-currency').val(currency);
});
$(document).on('input', '.edit-service-base-price, .edit-service-sold-price, #editDiscount', updateEditTotals);

$(document).on('submit', '#editMemberForm', function(event) {
    event.preventDefault();
    console.log("Edit form submitted!");

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
        console.log("Server Response:", data);
        if (data.success) {
            alert("Umrah member updated successfully");
            location.reload();
        } else {
            alert("error: " + (data.message || "Failed to update member"));
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalHtml;
        }
    })
    .catch(error => {
        console.error("Error:", error);
        alert("An error occurred");
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalHtml;
    });
});

// Ensure at least one service row when modal opens for new entries (not for editing existing data)
// This is handled by openEditMemberModal function for editing existing members

function openEditMemberModal(bookingId) {
    console.log('Opening edit modal for booking:', bookingId);

    // Show loading state
    Swal.fire({
        title: 'Loading',
        text: 'Please wait',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    // Fetch member details
    fetch(`../api/umrah/get_member_details.php?booking_id=${bookingId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.member) {
                const member = data.member;

                // Populate form fields
                document.getElementById('editBookingId').value = member.booking_id;
                document.getElementById('editSoldTo').value = member.sold_to;
                document.getElementById('editPaidTo').value = member.paid_to;
                document.getElementById('editEntry_date').value = member.entry_date;
                document.getElementById('editName').value = member.name;
                document.getElementById('editDob').value = member.dob;
                document.getElementById('editGender').value = member.gender;
                document.getElementById('editFather_name').value = member.fname;
                document.getElementById('editG_name').value = member.gfname;
                document.getElementById('editRelation').value = member.relation;
                document.getElementById('editPassport_number').value = member.passport_number;
                document.getElementById('editPassport_expiry').value = member.passport_expiry;
                document.getElementById('editId_type').value = member.id_type;
                document.getElementById('editFlight_date').value = member.flight_date;
                document.getElementById('editReturn_date').value = member.return_date;
                document.getElementById('editDuration').value = member.duration;
                document.getElementById('editRoom_type').value = member.room_type;
                document.getElementById('editDiscount').value = member.discount || 0;
                document.getElementById('editRemarks').value = member.remarks || '';

                // Clear existing services
                $('.edit-services-grid-body').empty();

                // Ensure suppliers are loaded before adding rows
                const addRows = () => {
                    if (member.services && member.services.length > 0) {
                        member.services.forEach(service => {
                            addEditServiceRow(service.service_type, service.supplier_id, service.base_price, service.sold_price, service.service_id);
                        });
                    } else {
                        // Add one empty row if no services
                        addEditServiceRow();
                    }
                    
                    // Update totals after all services are loaded
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

                // Close loading and show modal
                Swal.close();
                $('#editMemberModal').modal('show');
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message || 'Failed to load member details'
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to load member details'
            });
        });
}