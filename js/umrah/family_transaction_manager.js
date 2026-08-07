// Note: getCurrencyDisplay and getExchangeRateExample are defined in transaction_manager.js
// to avoid duplicate declaration errors

function familyTransactionNeedsReceipt(transactionTo) {
    return transactionTo === 'Bank' || transactionTo === 'Internal Account';
}

function openFamilyTransactionModal(familyId, familyName, packageName, totalMembers) {
    // Set family info
    document.getElementById('familyTransactionFamilyId').value = familyId;
    document.getElementById('familyTransactionHead').textContent = familyName;
    document.getElementById('familyTransactionPackage').textContent = packageName;
    document.getElementById('familyTransactionMembers').textContent = totalMembers;

    // Reset member currencies array
    window.familyMemberCurrencies = [];

    // Collapse the add-transaction form if it was left open
    $('#familyTransactionForm').collapse('hide');

    // Load family members and financial summary
    loadFamilyTransactionData(familyId);

    // Show modal
    $('#familyTransactionModal').modal('show');
}

function loadFamilyTransactionData(familyId) {
    $.ajax({
        url: '../api/umrah/get_family_transaction_data.php',
        type: 'GET',
        data: { family_id: familyId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const data = response.data;

                // Store client type for transaction_to logic (regular clients default to Bank)
                window.currentClientType = data.client_type || '';

                // Determine majority supplier type across members
                var supCounts = {};
                (data.members || []).forEach(function(m) {
                    var st = m.supplier_type || '';
                    supCounts[st] = (supCounts[st] || 0) + 1;
                });
                var majoritySup = Object.keys(supCounts).reduce(function(a, b) {
                    return supCounts[a] > supCounts[b] ? a : b;
                }, '');
                window.familyMajoritySupplier = majoritySup;

                // Majority route-to-main-account flag (Nusuk-style suppliers)
                var routeCount = (data.members || []).filter(function(m) {
                    return parseInt(m.route_payment_to_main_account || 0, 10) === 1;
                }).length;
                window.familyRouteToMainMajority = routeCount >= (data.members || []).length / 2;

                // Update financial summary
                $('#familyTotalPrice').text(data.total_price || '0.00');
                $('#familyTotalPaid').text(data.total_paid || '0.00');
                $('#familyTotalDue').text(data.total_due || '0.00');

                // Load members table
                loadFamilyMembersTransactionTable(data.members);

                // Load member payment inputs
                loadFamilyMemberPaymentInputs(data.members);
            } else {
                alert('Error loading family data: ' + (response.message || 'Unknown error'));
            }
        },
        error: function(xhr, status, error) {

            alert('Error loading family transaction data');
        }
    });
}

function loadFamilyMembersTransactionTable(members) {
    const tbody = $('#familyMembersTransactionTable');
    tbody.empty();

    if (!members || members.length === 0) {
        tbody.html('<tr><td colspan="5" class="text-center">No members found</td></tr>');
        return;
    }

    // Determine majority supplier_type and sold_to
    var supplierCounts = {};
    var clientCounts = {};
    members.forEach(function(m) {
        var st = m.supplier_type || '';
        supplierCounts[st] = (supplierCounts[st] || 0) + 1;
        var ct = String(m.sold_to || '');
        clientCounts[ct] = (clientCounts[ct] || 0) + 1;
    });
    var majoritySupplier = Object.keys(supplierCounts).reduce(function(a, b) {
        return supplierCounts[a] > supplierCounts[b] ? a : b;
    }, '');
    var majorityClient = Object.keys(clientCounts).reduce(function(a, b) {
        return clientCounts[a] > clientCounts[b] ? a : b;
    }, '');

    members.forEach(function(member) {
        var isDiff = member.supplier_type !== majoritySupplier || String(member.sold_to) !== String(majorityClient);
        var warningIcon = isDiff ? '<i class="feather icon-alert-triangle text-warning mr-1" title="Supplier or client differs from majority"></i>' : '';
        var rowClass = isDiff ? ' class="table-warning"' : '';
        var helperText = isDiff ? '<br><small class="text-warning"><i class="feather icon-alert-triangle"></i> Supplier or client differs from others</small>' : '';

        var row = '<tr' + rowClass + '>' +
            '<td>' + warningIcon + '<strong>' + member.name + '</strong>' +
                '<br><small class="text-muted">ID: ' + member.booking_id + '</small>' +
                helperText +
            '</td>' +
            '<td>' + (member.sold_price || '0.00') + '</td>' +
            '<td class="text-success">' + (member.paid || '0.00') + '</td>' +
            '<td class="text-danger">' + (member.due || '0.00') + '</td>' +
            '<td>' +
                '<button class="btn btn-sm btn-outline-primary" onclick="openTransactionTab(' + member.booking_id + ', ' + (member.sold_price || '0').replace(/,/g, '') + ')">' +
                    '<i class="feather icon-credit-card"></i> View' +
                '</button>' +
            '</td>' +
        '</tr>';
        tbody.append(row);
    });
}

function loadFamilyMemberPaymentInputs(members) {
     const container = $('#familyMemberPayments');
     container.empty();

     // Reset member currencies array
     window.familyMemberCurrencies = [];

     if (!members || members.length === 0) {
         container.html('<div class="col-12"><p class="text-muted">No members available for payment</p></div>');
         return;
     }

     // Determine majority supplier_type and sold_to
     var supplierCounts = {};
     var clientCounts = {};
     members.forEach(function(m) {
         var st = m.supplier_type || '';
         supplierCounts[st] = (supplierCounts[st] || 0) + 1;
         var ct = String(m.sold_to || '');
         clientCounts[ct] = (clientCounts[ct] || 0) + 1;
     });
     var majoritySupplier = Object.keys(supplierCounts).reduce(function(a, b) {
         return supplierCounts[a] > supplierCounts[b] ? a : b;
     }, '');
     var majorityClient = Object.keys(clientCounts).reduce(function(a, b) {
         return clientCounts[a] > clientCounts[b] ? a : b;
     }, '');

     members.forEach(function(member) {
         // Store member currency for exchange rate logic
         window.familyMemberCurrencies.push(member.currency);

         var isDiff = member.supplier_type !== majoritySupplier || String(member.sold_to) !== String(majorityClient);
         var cardClass = isDiff ? 'card border-warning' : 'card border-light';
         var warningBadge = isDiff ? '<div class="mt-1"><span class="badge badge-warning"><i class="feather icon-alert-triangle"></i> Supplier or client differs from others</span></div>' : '';

         const needsReceipt = familyTransactionNeedsReceipt($('#familyTransactionTo').val());
         
         let receiptInput = '';
         if (needsReceipt) {
             receiptInput = `
                 <div class="form-group mb-2">
                     <label class="small text-primary">
                         <i class="feather icon-file-text"></i> Receipt Number
                     </label>
                     <input type="text" class="form-control form-control-sm member-receipt-input"
                            name="member_payments[${member.booking_id}][receipt_number]"
                            id="receipt_${member.booking_id}"
                            placeholder="Enter receipt #"
                            data-booking-id="${member.booking_id}">
                 </div>
             `;
         }
         
         const memberInput = `
             <div class="col-md-6 mb-3">
                 <div class="${cardClass}">
                     <div class="card-body p-3">
                         <div class="d-flex justify-content-between align-items-center mb-2">
                             <strong>${member.name}</strong>
                             <small class="text-muted">Due: ${member.due || '0.00'}</small>
                         </div>
                         ${warningBadge}
                         <div class="form-group mb-2">
                             <label class="small">Payment Amount</label>
                             <input type="number" class="form-control form-control-sm"
                                    name="member_payments[${member.booking_id}][amount]"
                                    id="payment_${member.booking_id}"
                                    step="0.01" min="0"
                                    placeholder="0.00">
                             <input type="hidden" name="member_payments[${member.booking_id}][booking_id]" value="${member.booking_id}">
                         </div>
                         ${receiptInput}
                     </div>
                 </div>
             </div>
         `;
         container.append(memberInput);
     });
 }

$(document).ready(function() {
    // Form submission handler
    $('#familyTransactionFormData').off('submit').on('submit', function(e) {
        e.preventDefault();

        const submitBtn = $(this).find('button[type="submit"]');
        const originalHtml = submitBtn.html();
        submitBtn.prop('disabled', true);
        submitBtn.html('<i class="feather icon-loader"></i> Processing...');

        const formData = new FormData(this);

        // Ensure transaction_to is included if missing (the select may be disabled)
        if (!formData.has('transaction_to')) {
            formData.append('transaction_to', $('#familyTransactionTo').val() || 'Internal Account');
        }

        // Collect member payments
         const memberPayments = [];
         const requiresReceipt = familyTransactionNeedsReceipt($('#familyTransactionTo').val());
         
         // Track booking IDs that have amounts
         const bookingIdsWithAmounts = new Set();
         
         $('input[name^="member_payments"]').each(function() {
             const name = $(this).attr('name');
             const value = $(this).val();

             if (name.includes('[amount]') && value && parseFloat(value) > 0) {
                 const bookingId = name.match(/\[(\d+)\]/)[1];
                 bookingIdsWithAmounts.add(bookingId);
                 memberPayments.push({
                     booking_id: bookingId,
                     amount: parseFloat(value)
                 });
             }
         });

         if (memberPayments.length === 0) {
             alert('Please enter payment amounts for at least one member');
             submitBtn.prop('disabled', false);
             submitBtn.html(originalHtml);
             return;
         }
         
         // Validate that each paid member has a receipt number when required
         if (requiresReceipt) {
             let missingReceipts = [];
             bookingIdsWithAmounts.forEach(bookingId => {
                 const receiptValue = $(`#receipt_${bookingId}`).val();
                 if (!receiptValue || receiptValue.trim() === '') {
                     missingReceipts.push(bookingId);
                 } else {
                     // Add receipt to member payment
                     const memberPayment = memberPayments.find(m => m.booking_id == bookingId);
                     if (memberPayment) {
                         memberPayment.receipt_number = receiptValue.trim();
                     }
                 }
             });
             
             if (missingReceipts.length > 0) {
                 alert('Bank and internal account transactions require a receipt number for each member.\nPlease enter receipt numbers for all members.');
                 submitBtn.prop('disabled', false);
                 submitBtn.html(originalHtml);
                 return;
             }
         }

        // Add member payments to form data
        formData.append('member_payments', JSON.stringify(memberPayments));

        // Get exchange rate if needed
        const currency = formData.get('payment_currency');
        const bookingCurrency = 'USD'; // Default, could be made dynamic
        const exchangeRate = parseFloat(formData.get('exchange_rate') || 1);

        if (currency && currency !== bookingCurrency && exchangeRate > 0) {
            let description = formData.get('payment_description') || '';
            if (description && !description.includes('Exchange Rate:')) {
                description += ` (Exchange Rate: ${exchangeRate.toFixed(2)})`;
                formData.set('payment_description', description);
            } else if (!description) {
                formData.set('payment_description', `(Exchange Rate: ${exchangeRate.toFixed(2)})`);
            }
        }

        $.ajax({
            url: '../api/umrah/add_family_umrah_transactions.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                submitBtn.prop('disabled', false);
                submitBtn.html(originalHtml);

                try {
                    const result = typeof response === 'string' ? JSON.parse(response) : response;
                    if (result.success) {
                        alert(`Family transaction processed successfully! ${result.processed_members} member(s) paid total of ${result.total_amount}`);

                        // Refresh the page to show updated data
                        location.reload();

                        // Reset form
                        $('#familyTransactionFormData')[0].reset();
                        $('#familyTransactionForm').collapse('hide');
                        $('#familyReceiptNumberField').hide();

                    } else {
                        alert('Error: ' + (result.message || 'Failed to process family transaction'));
                    }
                } catch (e) {

                    alert('Error processing the server response');
                }
            },
            error: function(xhr, status, error) {
                submitBtn.prop('disabled', false);
                submitBtn.html(originalHtml);



                alert('Error processing family transaction');
            }
        });
    });

    // Show/Hide receipt number field and reload member inputs with receipts
     $('#familyTransactionTo').on('change', function() {
         const transactionTo = $(this).val();
         const needsReceipt = familyTransactionNeedsReceipt(transactionTo);
         
         if (needsReceipt) {
             $('#familyReceiptNumberField').slideDown();
             $('#bankReceiptAlert').slideDown();
             // Reload member inputs to show receipt fields
             const familyId = $('#familyTransactionFamilyId').val();
             loadFamilyTransactionData(familyId);
         } else {
             $('#familyReceiptNumberField').slideUp();
             $('#bankReceiptAlert').slideUp();
             // Reload member inputs to hide receipt fields
             const familyId = $('#familyTransactionFamilyId').val();
             loadFamilyTransactionData(familyId);
         }
     });

    // Show/Hide exchange rate field based on currency difference
    $('#familyPaymentCurrency').on('change', function() {
        const selectedCurrency = $(this).val();
        const exchangeRateField = $('#familyExchangeRateField');

        // Check if selected currency differs from any member currency
        const needsExchangeRate = selectedCurrency && window.familyMemberCurrencies &&
            window.familyMemberCurrencies.some(memberCurrency => memberCurrency !== selectedCurrency);

        if (needsExchangeRate) {
            exchangeRateField.slideDown();
            $('#familyExchangeRate').attr('required', true);
            
            // Get display names for currencies - use first different currency for display
            const baseCurrency = window.familyMemberCurrencies[0] || 'USD';
            const baseDisplay = getCurrencyDisplay(baseCurrency);
            const targetDisplay = getCurrencyDisplay(selectedCurrency);
            
            // Update label with proper exchange rate direction
            const label = `<i class="feather icon-refresh-cw mr-1"></i>${baseDisplay} to ${targetDisplay} Exchange Rate`;
            const labelElement = exchangeRateField.find('label');
            labelElement.html(label);
            
            // Update helper text
            const example = getExchangeRateExample(baseDisplay, targetDisplay);
            const helpText = `<small class="form-text text-muted d-block mt-1">
                Enter how many ${targetDisplay} equals 1 ${baseDisplay}
                <span class="d-block mt-1" style="color: #666;">${example}</span>
            </small>`;
            
            // Remove old help text and add new one
            exchangeRateField.find('small').remove();
            exchangeRateField.find('input').after(helpText);
        } else {
            exchangeRateField.slideUp();
            $('#familyExchangeRate').removeAttr('required').val('');
            // Remove help text
            exchangeRateField.find('small').remove();
        }
    });

    // Set today's date by default
    const today = new Date().toISOString().split('T')[0];
    $('#familyPaymentDate').val(today);

    // Reset form when transaction form is shown
    $('#familyTransactionForm').on('shown.bs.collapse', function() {
        const submitBtn = $('#familyTransactionFormData').find('button[type="submit"]');
        submitBtn.prop('disabled', false);
        submitBtn.html('<i class="feather icon-check mr-1"></i>Add Transactions');

        $('#familyTransactionFormData')[0].reset();
        $('#familyPaymentCurrency').val('');
        if (window.currentClientType === 'regular') {
            $('#familyTransactionTo').val('Bank').prop('disabled', true).trigger('change');
        } else {
            $('#familyTransactionTo').val('Internal Account').prop('disabled', false).trigger('change');
        }
        $('#familyReceiptNumberField').show();
        $('#bankReceiptAlert').show();

        // Reset exchange rate field
        const exchangeRateField = $('#familyExchangeRateField');
        exchangeRateField.hide();
        $('#familyExchangeRate').removeAttr('required').val('');
        exchangeRateField.find('.text-warning').remove();

        // Set today's date
        const today = new Date().toISOString().split('T')[0];
        $('#familyPaymentDate').val(today);

        // Trigger currency change event
        $('#familyPaymentCurrency').trigger('change');
    });
});
