            // Function to show toast
            function showToast(message, type = 'success') {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: type,
                    title: message,
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true
                });
            }
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
        $('#paymentCurrency').on('change', this.toggleExchangeRateField.bind(this));
        $('#editPaymentCurrency').on('change', this.toggleEditExchangeRateField.bind(this));
    },
        // Toggle exchange rate field based on currency selection
        toggleExchangeRateField: function() {
            const selectedCurrency = $('#paymentCurrency').val();
            const baseCurrency = $('#totalAmount').text().split(' ')[0];
            if (selectedCurrency && baseCurrency && selectedCurrency !== baseCurrency) {
                $('#exchangeRateField').show();
                $('#transactionExchangeRate').attr('required', true);
            } else {
                $('#exchangeRateField').hide();
                $('#transactionExchangeRate').attr('required', false);
                $('#transactionExchangeRate').val(''); // Clear value when hidden
            }
        },

        // Toggle exchange rate field for edit form
        toggleEditExchangeRateField: function() {
            const selectedCurrency = $('#editPaymentCurrency').val();
            const baseCurrency = $('#totalAmount').text().split(' ')[0];
            if (selectedCurrency && baseCurrency && selectedCurrency !== baseCurrency) {
                $('#editExchangeRateField').show();
                $('#editTransactionExchangeRate').attr('required', true);
            } else {
                $('#editExchangeRateField').hide();
                $('#editTransactionExchangeRate').attr('required', false);
                $('#editTransactionExchangeRate').val(''); // Clear value when hidden
            }
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
        const amount = parseFloat($('#paymentAmount').val()) || 0;
        const exchangeRate = parseFloat($('#exchangeRateDisplay').text()) || 1;
        
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
            url: 'get_hotel_refund_transactions.php',
            type: 'GET',
            data: { refund_id: refundId },
            dataType: 'json',
            success: function(response) {
                try {
                    const data = typeof response === 'string' ? JSON.parse(response) : response;
                    const transactions = data.transactions || [];

                    const tbody = $('#transactionTableBody');
                    tbody.empty();

                    if (!Array.isArray(transactions) || transactions.length === 0) {
                        tbody.html('<tr><td colspan="6" class="text-center">No transactions found</td></tr>');
                        $('#exchangeRateDisplay').text('No exchange rates found');
                        $('#exchangedAmount').text('No conversions available');
                        return;
                    }

                    const baseCurrency = $('#totalAmount').text().split(' ')[0] || 'USD';
                    const totalAmount = parseFloat($('#totalAmount').text().split(' ')[1]) || 0;

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
        const originalAmount = parseFloat(totalAmountText.split(' ')[1]) || 0;
        
        // Set the original amount
        formData.set('original_amount', originalAmount);
        
        // Set the booking_id from refund_id for compatibility
        const refundId = $('#refund_id').val();
        formData.set('booking_id', refundId);
        
        $.ajax({
            url: 'add_hotel_refund_transactoin.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                try {
                    const result = typeof response === 'string' ? JSON.parse(response) : response;
                    if (result.success) {
                        showToast('transaction_added_successfully');
                        $('#addTransactionForm').collapse('hide');
                        transactionManager.loadTransactionHistory($('#refund_id').val());
                        $('#hotelTransactionForm')[0].reset();
                        transactionManager.setDefaultDateTime();
                    } else {
                        showToast('error_adding_transaction: ' + (result.message || 'unknown_error'));
                    }
                } catch (e) {
                    console.error('Error parsing response:', e);
                    showToast('error_processing_the_request');
                } finally {
                    submitButton.prop('disabled', false);
                    submitButton.html('<i class="feather icon-check mr-1"></i>add_transaction');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                showToast('error_adding_transaction');
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

        console.log('Current refund ID:', refundId);

        // Create edit transaction modal if it doesn't exist
        if (!$('#editTransactionModal').length) {
            const modalHtml = `
                <div class="modal fade" id="editTransactionModal" tabindex="-1" role="dialog">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header bg-primary text-white">
                                <h5 class="modal-title">
                                    <i class="feather icon-edit mr-2"></i>Edit Transaction
                                </h5>
                                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                            </div>
                            <form id="editTransactionForm">
                                <div class="modal-body">
                                    <input type="hidden" id="editTransactionId" name="transaction_id">
                                    <input type="hidden" id="editRefundId" name="refund_id">
                                    <input type="hidden" id="originalAmount" name="original_amount">

                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="editPaymentDate">
                                                    <i class="feather icon-calendar mr-1"></i>Payment Date
                                                </label>
                                                <input type="date" class="form-control" id="editPaymentDate" name="payment_date" required>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="editPaymentTime">
                                                    <i class="feather icon-clock mr-1"></i>Payment Time
                                                </label>
                                                <input type="time" class="form-control" id="editPaymentTime" name="payment_time" step="1" required>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="editPaymentAmount">
                                                    <i class="feather icon-dollar-sign mr-1"></i>Amount
                                                </label>
                                                <input type="number" class="form-control" id="editPaymentAmount"
                                                       name="payment_amount" step="0.01" min="0.01" required>
                                                <small class="form-text text-muted">
                                                    Changing this amount will update all subsequent balances.
                                                </small>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="editPaymentDescription">
                                                    <i class="feather icon-file-text mr-1"></i>Description
                                                </label>
                                                <textarea class="form-control" id="editPaymentDescription"
                                                          name="payment_description" rows="2" required></textarea>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="editPaymentCurrency">
                                                    <i class="feather icon-dollar-sign mr-1"></i>Currency
                                                </label>
                                                <select class="form-control" id="editPaymentCurrency" name="payment_currency" required disabled>
                                                    <option value="USD">USD</option>
                                                    <option value="AFS">AFS</option>
                                                    <option value="EUR">EUR</option>
                                                    <option value="DARHAM">DARHAM</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group" id="editExchangeRateField" style="display: none;">
                                                <label for="editTransactionExchangeRate">
                                                    <i class="feather icon-refresh-cw mr-1"></i>Exchange Rate
                                                </label>
                                                <input type="number" class="form-control" id="editTransactionExchangeRate"
                                                       name="exchange_rate" step="0.01" placeholder="Enter exchange rate">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                                        <i class="feather icon-x mr-1"></i>Cancel
                                    </button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="feather icon-check mr-1"></i>Save Changes
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            `;
            $('body').append(modalHtml);

            // Bind the change event for the edit currency select
            $('#editPaymentCurrency').on('change', transactionManager.toggleEditExchangeRateField.bind(transactionManager));

            // Add submit handler for the edit form
            $('#editTransactionForm').on('submit', function(e) {
                e.preventDefault();

                // Create FormData from the form
                const formData = new FormData(this);

                // Explicitly set the refund ID again to ensure it's included
                const currentRefundId = $('#refund_id').val();
                formData.set('refund_id', currentRefundId);

                // Ensure transaction_id and refund_id are set
                if (!formData.get('transaction_id')) {
                    alert('Error: Missing transaction ID');
                    return;
                }

                if (!formData.get('refund_id')) {
                    alert('Error: Missing refund ID');
                    return;
                }

                // Combine date and time into a datetime string in MySQL format
                const date = formData.get('payment_date');
                const time = formData.get('payment_time');
                if (date && time) {
                    formData.set('payment_date', `${date} ${time}`);
                }

                // Add exchange rate if provided
                const exchangeRate = $('#editTransactionExchangeRate').val();
                if (exchangeRate && $('#editExchangeRateField').is(':visible')) {
                    formData.set('payment_exchange_rate', exchangeRate);
                }

                // Log the form data for debugging
                console.log('Submitting transaction update with data:');
                for (let pair of formData.entries()) {
                    console.log(pair[0] + ': ' + pair[1]);
                }

                $.ajax({
                    url: 'update_refund_hotel_transaction.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        try {
                            const result = typeof response === 'string' ? JSON.parse(response) : response;
                            if (result.success) {
                                showToast('Transaction updated successfully');
                                $('#editTransactionModal').modal('hide');
                                transactionManager.loadTransactionHistory(currentRefundId);
                            } else {
                                showToast('Error updating transaction: ' + (result.message || 'Unknown error'));
                            }
                        } catch (e) {
                            console.error('Error parsing response:', e);
                            showToast('Error processing the request');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', error);
                        console.error('Response:', xhr.responseText);
                        showToast('Error updating transaction');
                    }
                });
            });
        }

        // Populate the edit form with the current values
        $('#editTransactionId').val(transactionId);
        $('#editRefundId').val(refundId);
        $('#originalAmount').val(amount);
        $('#editPaymentDate').val(formattedDate);
        $('#editPaymentTime').val(formattedTime);
        $('#editPaymentAmount').val(parseFloat(amount).toFixed(2));
        $('#editPaymentDescription').val(description);

        // Set the currency from the transaction
        $('#editPaymentCurrency').val(currency);

        // Show exchange rate field (always shown for edit)
        transactionManager.toggleEditExchangeRateField();

        // Set exchange rate from parameter
        if (exchangeRate && exchangeRate !== 'null') {
            $('#editTransactionExchangeRate').val(exchangeRate);
            $('#editExchangeRateField').show();
        } else {
            $('#editExchangeRateField').hide();
            $('#editTransactionExchangeRate').val('');
        }

        // Log values for debugging
        console.log('Edit Transaction:', {
            transactionId: transactionId,
            refundId: refundId,
            amount: amount,
            date: formattedDate,
            time: formattedTime,
            description: description,
            currency: currency,
            exchangeRate: exchangeRate
        });

        // Show the modal
        $('#editTransactionModal').modal('show');
    },
    
    // Delete transaction
    deleteTransaction: function(transactionId, amount) {
        if (!confirm('are_you_sure_you_want_to_delete_this_transaction')) {
            return;
        }

        const refundId = $('#refund_id').val();

        // Send as form data instead of JSON
        $.ajax({
            url: 'delete_hotel_refund_transactions.php',
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
                        transactionManager.loadTransactionHistory(refundId);
                        showToast('transaction_deleted_successfully');
                    } else {
                        showToast('error_deleting_transaction: ' + (result.message || 'Unknown error'));
                    }
                } catch (e) {
                    console.error('Error parsing response:', e);
                    showToast('error_processing_the_request');
                }
            },
            error: function(xhr, status, error) {
                console.error('Delete Error Response:', {
                    status: xhr.status,
                    error: error,
                    response: xhr.responseText
                });
                showToast('error_deleting_transaction');
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
});
