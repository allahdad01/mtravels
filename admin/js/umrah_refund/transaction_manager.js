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
        const amount = parseFloat($('#paymentAmount').val()) || 0;
        const exchangeRate = parseFloat($('#exchangeRateDisplay').text()) || 1;

        // Show/Hide exchange rate field
        if (selectedCurrency && selectedCurrency !== refundCurrency) {
            $('#exchangeRateField').slideDown();
            $('#exchangeRate').attr('required', true);
        } else {
            $('#exchangeRateField').slideUp();
            $('#exchangeRate').removeAttr('required').val('');
        }

        if (selectedCurrency === 'AFS' && $('#paymentAmount').data('usd-amount')) {
            // Convert USD to AFS
            const afsAmount = amount * exchangeRate;
            $('#paymentAmount').val(afsAmount.toFixed(2));
        } else if (selectedCurrency === 'USD' && $('#paymentAmount').data('afs-amount')) {
            // Convert AFS to USD
            const usdAmount = amount / exchangeRate;
            $('#paymentAmount').val(usdAmount.toFixed(2));
        }
    },

    // Load transaction history
    loadTransactionHistory: function(refundId) {
        $.ajax({
            url: 'get_umrah_refund_transactions.php',
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

                            tbody.append(`
                                <tr>
                                    <td>${transactionManager.formatDate(tx.created_at)}</td>
                                    <td>${tx.description || ''}</td>
                                    <td>${tx.type === 'credit' ? 'Received' : 'Paid'}</td>
                                    <td>${currency} ${amount.toFixed(2)}</td>
                                    <td>${tx.account_name}</td>
                                    <td>${exchangeRate || 'N/A'}</td>
                                    <td class="text-center">
                                        <button class="btn btn-primary btn-sm" onclick="transactionManager.editTransaction(${tx.id}, '${(tx.description||'').replace(/'/g,"\\'")}', ${amount}, '${tx.created_at}', '${currency}', ${tx.exchange_rate || 'null'})">
                                            <i class="feather icon-edit"></i>
                                        </button>
                                        <button class="btn btn-danger btn-sm" onclick="transactionManager.deleteTransaction(${tx.id}, ${amount})">
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
                        tbody.html('<tr><td colspan="6" class="text-center">No transactions found</td></tr>');
                        $('#exchangeRateDisplay').text('No exchange rates found');
                        $('#exchangedAmount').text('No conversions available');
                        $('#usdSection, #afsSection, #eurSection, #aedSection').hide();
                    }

                } catch(e) {
                    console.error('Error parsing transactions:', e);
                    $('#transactionTableBody').html('<tr><td colspan="6" class="text-center">error_loading_transactions</td></tr>');
                    $('#exchangeRateDisplay').text('Error loading exchange rates');
                    $('#exchangedAmount').text('Error calculating amounts');
                }
            },
            error: function(xhr, status, error){
                console.error('Error loading transactions:', error);
                $('#transactionTableBody').html('<tr><td colspan="6" class="text-center">error_loading_transactions</td></tr>');
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
            url: 'add_umrah_refund_transactoin.php',
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
                    console.error('Error parsing response:', e);
                    alert('error_processing_the_request');
                } finally {
                    submitButton.prop('disabled', false);
                    submitButton.html('<i class="feather icon-check mr-1"></i>add_transaction');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                alert('error_adding_transaction');
                submitButton.prop('disabled', false);
                submitButton.html('<i class="feather icon-check mr-1"></i>add_transaction');
            }
        });
    },

    // Edit transaction
    editTransaction: function(transactionId, description, amount, createdAt, currency, exchangeRate) {
        // Parse the datetime string
        const dateTime = new Date(createdAt);
        
        // Format date for input field (YYYY-MM-DD)
        const formattedDate = dateTime.toISOString().split('T')[0];
        
        // Format time for input field (HH:MM:SS)
        const hours = String(dateTime.getHours()).padStart(2, '0');
        const minutes = String(dateTime.getMinutes()).padStart(2, '0');
        const seconds = String(dateTime.getSeconds()).padStart(2, '0');
        const formattedTime = `${hours}:${minutes}:${seconds}`;
        
        // Get the current refund ID from the refund_id field
        const refundId = $('#refund_id').val();
        
        // Populate the edit form with the current values
        $('#editTransactionId').val(transactionId);
        $('#editRefundId').val(refundId);
        $('#editOriginalAmount').val(amount);
        $('#editPaymentDate').val(formattedDate);
        $('#editPaymentTime').val(formattedTime);
        $('#editPaymentAmount').val(parseFloat(amount).toFixed(2));
        $('#editPaymentDescription').val(description);

        // Set the currency from the transaction
        $('#editPaymentCurrency').val(currency);

        // Show exchange rate field (always shown for edit)
        $('#editExchangeRateField').show();
        $('#editExchangeRate').attr('required', true);

        // Set exchange rate from parameter
        if (exchangeRate && exchangeRate !== 'null') {
            $('#editExchangeRate').val(exchangeRate);
        } else {
            $('#editExchangeRate').val('');
        }
        
        // Show the modal
        $('#editTransactionModal').modal('show');
    },
    
    // Delete transaction
    deleteTransaction: function(transactionId, amount) {
        if (!confirm('are_you_sure_you_want_to_delete_this_transaction')) {
            return;
        }

        const refundId = $('#refund_id').val();

        $.ajax({
            url: 'delete_umrah_refund_transactions.php',
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
                    console.error('Error parsing response:', e);
                    alert('error_processing_the_request');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
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

// Initialize transaction manager when document is ready
$(document).ready(function() {
    transactionManager.init();

    // Reset exchange rate field when add transaction form is shown
    $('#addTransactionForm').on('shown.bs.collapse', function() {
        $('#exchangeRateField').hide();
        $('#exchangeRate').removeAttr('required').val('');
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
    
    // Combine date and time
    const date = formData.get('payment_date');
    const time = formData.get('payment_time') || '00:00:00';
    if (date) {
        formData.set('payment_date', `${date} ${time}`);
    }

    // Get the original transaction amount from the hidden field
    const originalAmount = $('#editOriginalAmount').val();
    formData.set('original_amount', originalAmount);
    
    $.ajax({
        url: 'update_refund_umrah_transaction.php',
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
                console.error('Error parsing response:', e);
                alert('error_processing_the_request');
            } finally {
                submitButton.prop('disabled', false);
                submitButton.html('<i class="feather icon-save mr-1"></i>save_changes');
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            alert('error_updating_transaction');
            submitButton.prop('disabled', false);
            submitButton.html('<i class="feather icon-save mr-1"></i>save_changes');
        }
    });
});