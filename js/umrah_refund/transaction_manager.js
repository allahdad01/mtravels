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
        // USD pairs - show "1 USD = X" format
        'USD-AFS': 'Example: 1 USD = 88 AFS, enter 88',
        'USD-EUR': 'Example: 1 USD = 0.95 EUR, enter 0.95',
        'USD-AED': 'Example: 1 USD = 3.67 AED, enter 3.67',
        'AFS-USD': 'Example: 1 USD = 88 AFS, enter 88',
        'EUR-USD': 'Example: 1 USD = 0.95 EUR, enter 0.95',
        'AED-USD': 'Example: 1 USD = 3.67 AED, enter 3.67',
        
        // EUR pairs - show "1 EUR = X" format
        'EUR-AFS': 'Example: 1 EUR = 92.5 AFS, enter 92.5',
        'AFS-EUR': 'Example: 1 EUR = 92.5 AFS, enter 92.5',
        'EUR-AED': 'Example: 1 EUR = 3.86 AED, enter 3.86',
        'AED-EUR': 'Example: 1 EUR = 3.86 AED, enter 3.86',
        
        // AED pairs - show "1 AED = X" format
        'AED-AFS': 'Example: 1 AED = 23.99 AFS, enter 23.99',
        'AFS-AED': 'Example: 1 AED = 23.99 AFS, enter 23.99'
     };
     const key = `${baseCurrency}-${targetCurrency}`;
     return examples[key] || 'Enter the exchange rate';
 };

 // Transaction Management System
   const transactionManager = {
      // Initialize transaction modal and form handlers
      init: function() {
          this.bindEvents();
          this.setDefaultDateTime();
      },

      // Bind all event listeners
      bindEvents: function() {
          $('#hotelTransactionForm').off('submit').on('submit', this.handleTransactionSubmit);
          $('#paymentCurrency').on('change', this.handleCurrencyChange);
      },
    
    // Set today's date and current time as default
    setDefaultDateTime: function() {
        const now = new Date();
        const today = now.toISOString().split('T')[0];
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        const currentTime = `${hours}:${minutes}:${seconds}`;
        
        $('#paymentDate').val(today);
        $('#paymentTime').val(currentTime);
    },

    // Handle currency change
    handleCurrencyChange: function() {
        const selectedCurrency = $(this).val();
        const refundCurrency = window.refundCurrency || 'USD';
        const exchangeRateField = $('#exchangeRateField');

        // Show/Hide exchange rate field
        if (selectedCurrency && selectedCurrency !== refundCurrency) {
            exchangeRateField.slideDown();
            $('#exchangeRate').attr('required', true);
            
            // Get display names for currencies
            const baseDisplay = getCurrencyDisplay(refundCurrency);
            const targetDisplay = getCurrencyDisplay(selectedCurrency);
            
            // Determine anchor currency (USD, EUR, AED, or AFS)
            let anchorCurrency = refundCurrency;
            const currencies = [selectedCurrency, refundCurrency];
            if (currencies.includes('USD')) {
                anchorCurrency = 'USD';
            } else if (currencies.includes('EUR')) {
                anchorCurrency = 'EUR';
            } else if (currencies.includes('AED')) {
                anchorCurrency = 'AED';
            } else if (currencies.includes('AFS')) {
                anchorCurrency = 'AFS';
            }
            
            const anchorDisplay = getCurrencyDisplay(anchorCurrency);
            const otherDisplay = anchorCurrency === refundCurrency ? targetDisplay : baseDisplay;
            
            // Update label to match example rule: "1 ANCHOR = OTHER"
            const label = `<i class="feather icon-refresh-cw mr-1"></i>${anchorDisplay} to ${otherDisplay} Exchange Rate`;
            const labelElement = exchangeRateField.find('label');
            labelElement.html(label);
            
            // Update helper text to match anchor currency concept
            const example = getExchangeRateExample(baseDisplay, targetDisplay);
            const instructionText = `Enter how many ${otherDisplay} equals 1 ${anchorDisplay}`;
            const helpText = `<small class="form-text text-muted d-block mt-1">
                ${instructionText}
                <span class="d-block mt-1" style="color: #666;">${example}</span>
            </small>`;
            
            // Remove old help text and add new one
            exchangeRateField.find('small').remove();
            exchangeRateField.find('input').after(helpText);
        } else {
            exchangeRateField.slideUp();
            $('#exchangeRate').removeAttr('required').val('');
            // Remove help text
            exchangeRateField.find('small').remove();
        }
    },

    // Load transaction history
    loadTransactionHistory: function(refundId) {
        $.ajax({
            url: '../api/umrah/get_umrah_refund_transactions.php',
            type: 'GET',
            data: { refund_id: refundId },
            dataType: 'json',
            success: function(response) {
                try {
                    const tbody = $('#transactionTableBody');
                    tbody.empty();

                    if (response.success && response.transactions && response.transactions.length > 0) {
                        const transactions = response.transactions;

                        const baseCurrency = window.refundCurrency || 'USD';
                        const totalAmount = parseFloat($('#totalAmount').text()) || 0;

                        // Collect exchange rates from DB transactions
                        let rates = {}; // { EUR: 87, AFS: 70, DARHAM: 18.5 }
                        transactions.forEach(tx => {
                            if (tx.currency !== baseCurrency && tx.exchange_rate) {
                                rates[tx.currency] = parseFloat(tx.exchange_rate);
                            }
                        });

                        // Track currencies present in transactions
                        let hasCurrency = { USD: false, AFS: false, EUR: false, DARHAM: false };

                        // Render transactions table
                        transactions.forEach(tx => {
                            const currency = tx.currency;
                            const amount = parseFloat(tx.amount);
                            const exchangeRate = tx.exchange_rate ? parseFloat(tx.exchange_rate) : null;

                            if (currency in hasCurrency) hasCurrency[currency] = true;

                            const receiptDisplay = tx.receipt || tx.receipt_number || '';

                            tbody.append(`
                                <tr>
                                    <td>${transactionManager.formatDate(tx.created_at)}</td>
                                    <td>${tx.description || ''}</td>
                                    <td>${receiptDisplay || '—'}</td>
                                    <td>${tx.type === 'credit' ? 'Received' : 'Paid'} ${currency} ${amount.toFixed(2)}</td>
                                    <td>${tx.account_name}</td>
                                    <td>${exchangeRate !== null ? exchangeRate : 'N/A'}</td>
                                    <td class="text-center">
                                        <button class="btn btn-primary btn-sm" title="Edit Transaction"
                                            onclick="transactionManager.editTransaction(${tx.id})">
                                            <i class="feather icon-edit"></i>
                                        </button>
                                        <button class="btn btn-info btn-sm mr-1" title="Print Receipt"
                                        onclick="printReceipt(${tx.id})">
                                    <i class="feather icon-printer"></i>
                                </button>
                                        <button class="btn btn-danger btn-sm" title="Delete Transaction"
                                            onclick="transactionManager.deleteTransaction(${tx.id}, ${amount})">
                                            <i class="feather icon-trash-2"></i>
                                        </button>
                                    </td>
                                </tr>
                            `);
                        });

                        // Display exchange rates
                        const exchangeText = Object.entries(rates).map(([cur,val]) => `${cur}: ${val}`).join(', ');
                        $('#exchangeRateDisplay').text(exchangeText || 'No exchange rates found');

                        // Calculate total paid in base currency
                        let totalPaidBase = 0;
                        transactions.forEach(tx => {
                            const amount = parseFloat(tx.amount);
                            const currency = tx.currency;

                            if (currency === baseCurrency) {
                                totalPaidBase += amount;
                            } else if (rates[currency]) {
                                // Convert foreign currency to base currency
                                if (baseCurrency === 'AFS') totalPaidBase += amount * rates[currency];
                                else totalPaidBase += amount / rates[currency];
                            }
                        });

                        const remainingBase = Math.max(0, totalAmount - totalPaidBase);

                        // Display paid and remaining amounts for each currency
                        ['USD','AFS','EUR','DARHAM'].forEach(cur => {
                            if (hasCurrency[cur]) {
                                const paid = transactions.filter(t => t.currency === cur)
                                                         .reduce((a,b) => a + parseFloat(b.amount), 0);
                                $(`#paidAmount${cur==='DARHAM'?'AED':cur}`).text(`${cur==='DARHAM'?'AED':cur} ${paid.toFixed(2)}`);

                                let remaining = 0;
                                if (cur === baseCurrency) {
                                    remaining = remainingBase;
                                } else if (rates[cur]) {
                                    // Convert base currency remaining to foreign
                                    if (baseCurrency === 'AFS') remaining = remainingBase / rates[cur];
                                    else remaining = remainingBase * rates[cur];
                                } else {
                                    remaining = 'N/A';
                                }

                                $(`#remainingAmount${cur==='DARHAM'?'AED':cur}`).text(`${cur==='DARHAM'?'AED':cur} ${typeof remaining==='number'?remaining.toFixed(2):remaining}`);
                            }
                        });

                        // Display exchanged amounts
                        const exchangedAmounts = [];
                        exchangedAmounts.push(`${baseCurrency} ${totalAmount.toFixed(2)}`);
                        Object.keys(rates).forEach(cur => {
                            const val = (baseCurrency === 'AFS') ? totalAmount / rates[cur] : totalAmount * rates[cur];
                            exchangedAmounts.push(`${cur} ${val.toFixed(2)}`);
                        });
                        $('#exchangedAmount').text(exchangedAmounts.join(', '));

                        // Show/hide currency sections
                        $('#usdSection').toggle(hasCurrency.USD);
                        $('#afsSection').toggle(hasCurrency.AFS);
                        $('#eurSection').toggle(hasCurrency.EUR);
                        $('#aedSection').toggle(hasCurrency.DARHAM);

                    } else {
                        tbody.html('<tr><td colspan="7" class="text-center">No transactions found</td></tr>');
                        $('#exchangeRateDisplay').text('No exchange rates found');
                        $('#exchangedAmount').text('No conversions available');
                        $('#usdSection, #afsSection, #eurSection, #aedSection').hide();
                    }

                } catch(e) {
                    console.log(e);
                    $('#transactionTableBody').html('<tr><td colspan="7" class="text-center">error_loading_transactions</td></tr>');
                    $('#exchangeRateDisplay').text('Error loading exchange rates');
                    $('#exchangedAmount').text('Error calculating amounts');
                }
            },
            error: function(xhr, status, error){
                console.log({status, error});
                $('#transactionTableBody').html('<tr><td colspan="7" class="text-center">error_loading_transactions</td></tr>');
                $('#exchangeRateDisplay').text('Error loading exchange rates');
                $('#exchangedAmount').text('Error calculating amounts');
            }
        });
    },

    // Handle transaction form submission
    handleTransactionSubmit: function(e) {
        e.preventDefault();
        
        const submitButton = $(this).find('button[type="submit"]');
        submitButton.prop('disabled', true);
        submitButton.html('<i class="fas fa-spinner fa-spin"></i> processing...');
        
        const formData = new FormData(this);

        // Add date/time if they exist
        if ($('#paymentDate').length > 0 && $('#paymentTime').length > 0) {
            const date = $('#paymentDate').val();
            const time = $('#paymentTime').val() || '00:00:00';
            formData.set('payment_date', `${date} ${time}`);
        }

        // Get the original amount from the total amount display
        const totalAmountText = $('#totalAmount').text();
        const originalAmount = parseFloat(totalAmountText) || 0;

        // Set the original amount
        formData.set('original_amount', originalAmount);

        // Set the booking_id from refund_id for compatibility
        const refundId = $('#refund_id').val();
        formData.set('booking_id', refundId);
        
        $.ajax({
            url: '../api/umrah/add_umrah_refund_transactoin.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                try {
                    const result = typeof response === 'string' ? JSON.parse(response) : response;
                    if (result.success) {
                        alert('transaction_added_successfully');
                        $('#addTransactionForm').collapse('hide');
                        transactionManager.loadTransactionHistory($('#refund_id').val());
                        $('#hotelTransactionForm')[0].reset();
                        transactionManager.setDefaultDateTime();
                    } else {
                        alert('error_adding_transaction: ' + (result.message || 'unknown_error'));
                    }
                } catch (e) {
                    console.log(e);
                    alert('error_processing_the_request');
                } finally {
                    submitButton.prop('disabled', false);
                    submitButton.html('<i class="feather icon-check mr-1"></i>add_transaction');
                }
            },
            error: function(xhr, status, error) {
                console.log({status, error});
                alert('error_adding_transaction');
                submitButton.prop('disabled', false);
                submitButton.html('<i class="feather icon-check mr-1"></i>add_transaction');
            }
        });
    },

    // Edit transaction
    editTransaction: function(transactionId) {
        const refundId = $('#refund_id').val();

        // Fetch transaction details
        $.ajax({
            url: '../api/umrah/get_umrah_refund_transaction.php',
            type: 'GET',
            data: { transaction_id: transactionId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const tx = response.transaction;

                    // Populate form fields
                    $('#editTransactionId').val(tx.id);
                    $('#editRefundId').val(refundId);
                    $('#originalAmount').val(tx.amount);
                    $('#editPaymentAmount').val(tx.amount);
                    $('#editPaymentDescription').val(tx.description);
                    $('#editReceiptNumber').val(tx.receipt || tx.receipt_number || '');
                    $('#editExchangeRate').val(tx.exchange_rate || '');

                    // Show exchange rate field only if currency differs from booking currency and exchange rate exists
                    const bookingCurrency = window.refundCurrency || 'USD';
                    if (tx.exchange_rate && tx.currency && tx.currency !== bookingCurrency) {
                        $('#editExchangeRateField').show();
                    } else {
                        $('#editExchangeRateField').hide();
                    }

                    // Show edit modal
                    $('#editTransactionModal').modal('show');
                } else {
                    alert('Error fetching transaction details: ' + (response.message || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                alert('Error fetching transaction details');
            }
        });
    },
    
    // Delete transaction
    deleteTransaction: function(transactionId, amount) {
        if (!confirm('are_you_sure_you_want_to_delete_this_transaction')) {
            return;
        }

        const refundId = $('#refund_id').val();

        $.ajax({
            url: '../api/umrah/delete_umrah_refund_transactions.php',
            type: 'POST',
            data: {
                transaction_id: transactionId,
                refund_id: refundId,
                amount: amount
            },
            success: function(response) {
                try {
                    const result = typeof response === 'string' ? JSON.parse(response) : response;
                    if (result.success) {
                        alert('transaction_deleted_successfully');
                        transactionManager.loadTransactionHistory(refundId);
                    } else {
                        alert('error_deleting_transaction: ' + (result.message || 'unknown_error'));
                    }
                } catch (e) {
                    console.log(e);
                    alert('error_processing_the_request');
                }
            },
            error: function(xhr, status, error) {
                console.log({status, error});
                alert('error_deleting_transaction');
            }
        });
    },

    // Format date function to handle SQL datetime
    formatDate: function(dateString) {
        if (!dateString) return 'N/A';
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
};
    // Print receipt function
    function printReceipt(transactionId) {
        window.open(`../api/umrah/print_umrah_refund_receipt.php?id=${transactionId}`, '_blank');
    }
// Initialize transaction manager when document is ready
$(document).ready(function() {
    transactionManager.init();

    // Reset exchange rate field when add transaction form is shown
    $('#addTransactionForm').on('shown.bs.collapse', function() {
        $('#exchangeRateField').hide();
        $('#exchangeRate').removeAttr('required').val('');
        // Remove help text
        $('#exchangeRateField').find('small').remove();
        transactionManager.setDefaultDateTime();
    });
    
});

// Add submit handler for the edit form
$(document).on('submit', '#editTransactionForm', function(e) {
    e.preventDefault();
    
    const submitButton = $(this).find('button[type="submit"]');
    submitButton.prop('disabled', true);
    submitButton.html('<i class="fas fa-spinner fa-spin"></i> processing...');
    
    const formData = new FormData(this);
    const refundId = $('#editRefundId').val();
    
    const receiptNumber = $('#editReceiptNumber').val();
    if (receiptNumber) {
        formData.set('receipt_number', receiptNumber);
    }

    // Get the original transaction amount from the hidden field
    const originalAmount = $('#originalAmount').val();
    formData.set('original_amount', originalAmount);
    
    $.ajax({
        url: '../api/umrah/update_refund_umrah_transaction.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            try {
                const result = typeof response === 'string' ? JSON.parse(response) : response;
                if (result.success) {
                    $('#editTransactionModal').modal('hide');
                    transactionManager.loadTransactionHistory(refundId);
                    alert('transaction_updated_successfully');
                } else {
                    alert('error_updating_transaction: ' + (result.message || 'unknown_error'));
                }
            } catch (e) {
                console.log(e);
                alert('error_processing_the_request');
            } finally {
                submitButton.prop('disabled', false);
                submitButton.html('<i class="feather icon-save mr-1"></i>save_changes');
            }
            },
            error: function(xhr, status, error) {
            console.log({status, error});
            alert('error_updating_transaction');
            submitButton.prop('disabled', false);
            submitButton.html('<i class="feather icon-save mr-1"></i>save_changes');
        }
    });
});
