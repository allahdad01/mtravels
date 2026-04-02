// Currency display mapping
const getCurrencyDisplay = function(currencyCode) {
    const currencyMap = {
        'USD': 'USD',
        'AFS': 'AFS',
        'EUR': 'EUR',
        'DARHAM': 'AED'
    };
    return currencyMap[currencyCode] || currencyCode;
};

// Generate dynamic exchange rate example
const getExchangeRateExample = function(baseCurrency, targetCurrency) {
    const examples = {
        'USD-AFS': 'Example: If 1 USD = 88 AFS, enter 88',
        'USD-EUR': 'Example: If 1 USD = 0.95 EUR, enter 0.95',
        'USD-AED': 'Example: If 1 USD = 3.67 AED, enter 3.67',
        'AFS-USD': 'Example: If 1 AFS = 0.0114 USD, enter 0.0114',
        'AFS-EUR': 'Example: If 1 AFS = 0.0108 EUR, enter 0.0108',
        'AFS-AED': 'Example: If 1 AFS = 0.0417 AED, enter 0.0417',
        'EUR-USD': 'Example: If 1 EUR = 1.05 USD, enter 1.05',
        'EUR-AFS': 'Example: If 1 EUR = 92.5 AFS, enter 92.5',
        'EUR-AED': 'Example: If 1 EUR = 3.86 AED, enter 3.86',
        'AED-USD': 'Example: If 1 AED = 0.27 USD, enter 0.27',
        'AED-AFS': 'Example: If 1 AED = 23.99 AFS, enter 23.99',
        'AED-EUR': 'Example: If 1 AED = 0.26 EUR, enter 0.26'
    };
    const key = `${baseCurrency}-${targetCurrency}`;
    return examples[key] || 'Enter the exchange rate';
};

function openFamilyTransactionModal(familyId, familyName, packageName, totalMembers) {
    // Set family info
    document.getElementById('familyTransactionFamilyId').value = familyId;
    document.getElementById('familyTransactionHead').textContent = familyName;
    document.getElementById('familyTransactionPackage').textContent = packageName;
    document.getElementById('familyTransactionMembers').textContent = totalMembers;

    // Reset member currencies array
    window.familyMemberCurrencies = [];

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

    members.forEach(member => {
        const row = `
            <tr>
                <td>
                    <div><strong>${member.name}</strong></div>
                    <small class="text-muted">ID: ${member.booking_id}</small>
                </td>
                <td>${member.sold_price || '0.00'}</td>
                <td class="text-success">${member.paid || '0.00'}</td>
                <td class="text-danger">${member.due || '0.00'}</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="openTransactionTab(${member.booking_id}, ${member.sold_price})">
                        <i class="feather icon-credit-card"></i> View
                    </button>
                </td>
            </tr>
        `;
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

     members.forEach(member => {
         // Store member currency for exchange rate logic
         window.familyMemberCurrencies.push(member.currency);
         
         // Check if we're in bank transaction mode
         const isBank = $('#familyTransactionTo').val() === 'Bank';
         
         let receiptInput = '';
         if (isBank) {
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
                 <div class="card border-light">
                     <div class="card-body p-3">
                         <div class="d-flex justify-content-between align-items-center mb-2">
                             <strong>${member.name}</strong>
                             <small class="text-muted">Due: ${member.due || '0.00'}</small>
                         </div>
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

        // Collect member payments
         const memberPayments = [];
         const isBank = $('#familyTransactionTo').val() === 'Bank';
         
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
         
         // For bank transactions, validate that each member has a receipt number
         if (isBank) {
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
                 alert('Bank transactions require a receipt number for each member.\nPlease enter receipt numbers for all members.');
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
         const isBankTransaction = $(this).val() === 'Bank';
         
         if (isBankTransaction) {
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
        $('#familyTransactionTo').val('Internal Account');
        $('#familyReceiptNumberField').hide();

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
