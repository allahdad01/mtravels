// Function to open edit member modal and populate with data
function openEditMemberModal(bookingId) {
    // Show loading state
    $('#editMemberModal').modal('show');
    
    // Store the original form HTML before replacing it
    if (!window.editMemberFormHTML) {
        window.editMemberFormHTML = $('#editMemberForm').html();
    }
    
    // Show loading indicator
    $('#editMemberForm').html('<div class="text-center py-4"><i class="feather icon-loader fa-spin fa-2x"></i><p class="mt-2">loading...</p></div>');
    
    // Fetch member data
    fetch(`get_umrah_member.php?booking_id=${bookingId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            
            if (data.success) {
                // Restore the original form HTML
                $('#editMemberForm').html(window.editMemberFormHTML);
                
                const member = data.member;
                
                // Function to ensure field is populated
                const setFieldValue = (fieldId, value, type = 'text') => {
                    const field = $(`#${fieldId}`);
                    if (field.length) {
                        // Special handling for id_type field
                        if (fieldId === 'editIdType' && value === 'Orignal') {
                            value = 'Original'; // Fix the typo
                        }
                        
                        // Special handling for date fields
                        if (type === 'date' && value) {
                            // Ensure date is in YYYY-MM-DD format
                            const dateValue = value.split(' ')[0]; // Remove any time component
                            field.val(dateValue);
                        }
                        // Special handling for number fields
                        else if (type === 'number' && value) {
                            // Remove any commas and convert to number
                            const numValue = value.toString().replace(/,/g, '');
                            field.val(numValue);
                        }
                        // Default handling for text fields
                        else {
                            field.val(value);
                        }
                        
                        // Verify the value was set
                        const actualValue = field.val();
                        if (actualValue !== value && type !== 'date' && type !== 'number') {
                        }
                    } else {
                        console.error(`Field ${fieldId} not found in the form`);
                    }
                };
                
                // First set all basic fields
                setFieldValue('editBookingId', member.booking_id);
                setFieldValue('editFamilyIdMember', member.family_id);
                setFieldValue('editSupplier', member.supplier);
                setFieldValue('editSoldTo', member.sold_to);
                setFieldValue('editPaidTo', member.paid_to);
                setFieldValue('editEntryDate', member.entry_date, 'date');
                setFieldValue('editName', member.name);
                setFieldValue('editDob', member.dob, 'date');
                setFieldValue('editPassportNumber', member.passport_number);
                setFieldValue('editFlightDate', member.flight_date, 'date');
                setFieldValue('editReturnDate', member.return_date, 'date');
                setFieldValue('editDuration', member.duration);
                setFieldValue('editRoomType', member.room_type);
                setFieldValue('editSupplierCurrency', member.currency);
                setFieldValue('editPrice', member.price, 'number');
                setFieldValue('editSoldPrice', member.sold_price, 'number');
                setFieldValue('editDiscount', member.discount, 'number');
                setFieldValue('editProfit', member.profit, 'number');
                setFieldValue('editReceivedBankPayment', member.received_bank_payment, 'number');
                setFieldValue('editBankReceiptNumber', member.bank_receipt_number);
                setFieldValue('editPaid', member.paid, 'number');
                setFieldValue('editDue', member.due, 'number');
                setFieldValue('editRemarks', member.remarks);
                setFieldValue('editRelation', member.relation);
                setFieldValue('editGName', member.gfname);
                setFieldValue('editFatherName', member.fname);
                
                // Set problematic fields with a slight delay to ensure DOM is ready
                setTimeout(() => {
                    // Set passport expiry (date field)
                    setFieldValue('editThePassportExpiry', member.passport_expiry, 'date');
                    
                    // Set ID type
                    setFieldValue('editIdType', member.id_type);
                    
                    // Set exchange rate (number field)
                    setFieldValue('editTotalExchangeRate', member.exchange_rate, 'number');
                    
                    // Set gender
                    setFieldValue('editGender', member.gender);
                    
                }, 100);
                
                // Calculate financials
                updateEditFormCalculations();
                
                // Re-attach event handlers
                $('#editPrice, #editSoldPrice, #editDiscount, #editPaid').off('input').on('input', updateEditFormCalculations);
                
                // Re-attach supplier change handler
                $('#editSupplier').off('change').on('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    const currencyMatch = selectedOption.text.match(/\((.*?)\s*(USD|AFS)\)/);
                    if (currencyMatch) {
                        $('#editSupplierCurrency').val(currencyMatch[2]);
                    } else {
                        $('#editSupplierCurrency').val('');
                    }
                });
                
                // Re-attach form submission handler
                $('#editMemberForm').off('submit').on('submit', function(e) {
                    e.preventDefault();
                    const submitBtn = $(this).find('button[type="submit"]');
                    const originalHtml = submitBtn.html();
                    submitBtn.prop('disabled', true);
                    submitBtn.html('<i class="feather icon-loader"></i> updating...');
                    
                    const formData = new FormData(this);
                    
                    // Debug log form data before submission
                    const formDataObj = {};
                    formData.forEach((value, key) => {
                        formDataObj[key] = value;
                    });
                    
                    fetch('update_umrah_member.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('member_updated_successfully');
                            $('#editMemberModal').modal('hide');
                            location.reload();
                        } else {
                            alert('error_updating_member: ' + data.message);
                            submitBtn.prop('disabled', false);
                            submitBtn.html(originalHtml);
                        }
                    })
                    .catch(error => {
                        alert('an_error_occurred_while_updating_the_member');
                        submitBtn.prop('disabled', false);
                        submitBtn.html(originalHtml);
                    });
                });
            } else {
                alert('error_loading_member_data: ' + data.message);
                $('#editMemberModal').modal('hide');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('an_error_occurred_while_loading_member_data: ' + error.message);
            $('#editMemberModal').modal('hide');
        });
}

// Function to update calculations in the edit form
function updateEditFormCalculations() {
    let price = parseFloat($('#editPrice').val()) || 0;
    let discount = parseFloat($('#editDiscount').val()) || 0;
    let soldPrice = parseFloat($('#editSoldPrice').val()) || 0;
    let paid = parseFloat($('#editPaid').val()) || 0;

    let profit = soldPrice - price - discount;
    let due = soldPrice - paid;

    $('#editProfit').val(profit.toFixed(3));
    $('#editDue').val(due.toFixed(3));
}

// Remove any global form submission handler that might conflict
$(document).ready(function() {
    // Other document ready handlers...
    
    // Make sure we don't have duplicate handlers
    $('#editMemberForm').off('submit');
});