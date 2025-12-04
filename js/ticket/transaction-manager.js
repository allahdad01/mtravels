// Transaction Management with Double-Submit Prevention
const transactionManager = {
    isSubmitting: false, // Flag to track submission state

    // Initialize transaction modal and form handlers
    init: function() {
        this.bindEvents();
        this.setDefaultDateTime();
    },

    // Bind all event listeners
    bindEvents: function() {
        $('#hotelTransactionForm').on('submit', this.handleTransactionSubmit.bind(this));
        $('#transaction_to').on('change', this.toggleReceiptField);
        $('#paymentCurrency').on('change', this.toggleExchangeRateField.bind(this));
        $('#editPaymentCurrency').on('change', this.toggleEditExchangeRateField.bind(this));
    },

    // Set today's date and current time as default
    setDefaultDateTime: function() {
        const now = new Date();
        const today = now.toISOString().split('T')[0];
        $('#paymentDate').val(today);

        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        $('#paymentTime').val(`${hours}:${minutes}:${seconds}`);
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
            $('#transactionExchangeRate').val('');
        }
    },

    // Toggle exchange rate field for edit form
    toggleEditExchangeRateField: function() {
        const selectedCurrency = $('#editPaymentCurrency').val();
        const baseCurrency = window.ticketCurrency;
        if (selectedCurrency && baseCurrency && selectedCurrency !== baseCurrency) {
            $('#editExchangeRateField').show();
            $('#editTransactionExchangeRate').attr('required', true);
        } else {
            $('#editExchangeRateField').hide();
            $('#editTransactionExchangeRate').attr('required', false);
            $('#editTransactionExchangeRate').val('');
        }
    },

    // Disable/Enable submit button helper
    setSubmitButtonState: function(disabled, text) {
        const $submitBtn = $('#hotelTransactionForm button[type="submit"]');
        $submitBtn.prop('disabled', disabled);
        
        if (disabled) {
            // Store original text if not already stored
            if (!$submitBtn.data('original-text')) {
                $submitBtn.data('original-text', $submitBtn.html());
            }
            $submitBtn.html(`<i class="feather icon-loader spin"></i> ${text || 'Processing...'}`);
        } else {
            // Restore original text
            const originalText = $submitBtn.data('original-text') || 'Submit';
            $submitBtn.html(originalText);
        }
    },

    // Handle transaction form submission with double-submit prevention
    handleTransactionSubmit: function(e) {
        e.preventDefault();
        e.stopPropagation();

        // PREVENTION #1: Check if already submitting
        if (this.isSubmitting) {
            console.log('Form submission already in progress, ignoring duplicate request');
            return false;
        }

        // Set submitting flag
        this.isSubmitting = true;

        // PREVENTION #2: Disable submit button
        this.setSubmitButtonState(true, 'Submitting...');

        // Gather form data
        const formData = {
            booking_id: $('#booking_id').val(),
            payment_date: $('#paymentDate').val(),
            payment_time: $('#paymentTime').val(),
            payment_amount: $('#paymentAmount').val(),
            payment_currency: $('#paymentCurrency').val(),
            payment_description: $('#paymentDescription').val()
        };

        // Add exchange rate if field is visible
        if ($('#exchangeRateField').is(':visible')) {
            formData.payment_exchange_rate = $('#transactionExchangeRate').val();
        }

        // Validate form data
        const requiredFields = ['booking_id', 'payment_date', 'payment_time', 'payment_amount', 'payment_currency', 'payment_description'];
        for (let field of requiredFields) {
            if (!formData[field]) {
                alert(`Please fill in the ${field.replace('_', ' ')} field`);
                // Re-enable form on validation error
                this.isSubmitting = false;
                this.setSubmitButtonState(false);
                return false;
            }
        }

        // Combine date and time
        const paymentDateTime = `${formData.payment_date} ${formData.payment_time}`;

        // Prepare AJAX data
        const ajaxData = {
            booking_id: formData.booking_id,
            payment_date: paymentDateTime,
            payment_amount: formData.payment_amount,
            payment_currency: formData.payment_currency,
            payment_description: formData.payment_description
        };

        if (formData.payment_exchange_rate) {
            ajaxData.payment_exchange_rate = formData.payment_exchange_rate;
        }

        // PREVENTION #3: Add timestamp to make request unique
        ajaxData.submission_timestamp = Date.now();

        const self = this;

        $.ajax({
            url: '../api/ticket/add_ticket_payment.php',
            type: 'POST',
            data: ajaxData,
            dataType: 'json',
            timeout: 30000, // 30 second timeout
            success: function(response) {
                if (response.success) {
                    $('#addTransactionForm').collapse('hide');
                    self.loadTransactionHistory(formData.booking_id);
                    alert('Transaction added successfully');
                    $('#hotelTransactionForm')[0].reset();
                    self.setDefaultDateTime();
                    $('#exchangeRateField').hide();
                    $('#transactionExchangeRate').attr('required', false);
                    $('#transactionExchangeRate').val('');
                } else {
                    alert('Error adding transaction: ' + (response.message || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                console.error('Response:', xhr.responseText);
                
                // Show appropriate error message
                if (status === 'timeout') {
                    alert('Request timed out. Please check your connection and try again.');
                } else {
                    alert('Error adding transaction. Please try again.');
                }
            },
            complete: function() {
                // CRITICAL: Always re-enable form in complete callback
                // This runs whether success or error
                self.isSubmitting = false;
                self.setSubmitButtonState(false);
            }
        });

        return false;
    },

    // Rest of your existing methods...
    loadTransactionModal: function(ticketId) {
        $.ajax({
            url: '../api/ticket/get_ticket_bookings.php',
            type: 'GET',
            data: { id: ticketId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const booking = response.booking;
                    $('#trans-guest-name').text(`${booking.title} ${booking.passenger_name}`);
                    $('#trans-order-id').text(booking.pnr);
                    const originalAmount = parseFloat(booking.sold);
                    $('#totalAmount').text(`${booking.currency} ${originalAmount.toFixed(2)}`);
                    $('#exchangeRateDisplay').text('Loading...');
                    $('#exchangedAmount').text('Loading...');
                    $('#booking_id').val(ticketId);
                    window.ticketCurrency = booking.currency;
                    transactionManager.loadTransactionHistory(ticketId);
                    $('#transactionsModal').modal('show');
                } else {
                    alert('error_fetching_booking_details: ' + (response.message || 'unknown_error'));
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                alert('error_fetching_booking_details');
            }
        });
    },

    loadTransactionHistory: function(ticketId) {
        $.ajax({
            url: '../api/ticket/get_ticket_transactions.php',
            type: 'GET',
            data: { ticket_id: ticketId },
            dataType: 'json',
            success: function(transactions) {
                try {
                    const tbody = $('#transactionTableBody');
                    tbody.empty();

                    if (!Array.isArray(transactions) || transactions.length === 0) {
                        tbody.html('<tr><td colspan="6" class="text-center">No transactions found</td></tr>');
                        $('#exchangeRateDisplay').text('No exchange rates found');
                        $('#exchangedAmount').text('No conversions available');
                        return;
                    }

                    const baseCurrency = window.ticketCurrency || 'USD';
                    const totalAmount = parseFloat($('#totalAmount').text().split(' ')[1]) || 0;

                    let rates = {};
                    transactions.forEach(tx => {
                        if (tx.currency !== baseCurrency && tx.exchange_rate) {
                            rates[tx.currency] = parseFloat(tx.exchange_rate);
                        }
                    });

                    let hasCurrency = { USD: false, AFS: false, EUR: false, DARHAM: false };

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
                                    <button class="btn btn-info btn-sm mr-1" title="Print Receipt"
                                            onclick="printReceipt(${tx.id})">
                                        <i class="feather icon-printer"></i>
                                    </button>
                                    <button class="btn btn-danger btn-sm" onclick="transactionManager.deleteTransaction(${tx.id}, ${amount})">
                                        <i class="feather icon-trash-2"></i>
                                    </button>
                                </td>
                            </tr>
                        `);
                    });

                    const exchangeText = Object.entries(rates).map(([cur,val]) => `${cur}: ${val}`).join(', ');
                    $('#exchangeRateDisplay').text(exchangeText || 'No exchange rates found');

                    let totalPaidBase = 0;
                    transactions.forEach(tx => {
                        const amount = parseFloat(tx.amount);
                        const currency = tx.currency;

                        if (currency === baseCurrency) {
                            totalPaidBase += amount;
                        } else if (rates[currency]) {
                            if (baseCurrency === 'AFS') totalPaidBase += amount * rates[currency];
                            else totalPaidBase += amount / rates[currency];
                        }
                    });

                    const remainingBase = Math.max(0, totalAmount - totalPaidBase);

                    ['USD','AFS','EUR','DARHAM'].forEach(cur => {
                        if (hasCurrency[cur]) {
                            const paid = transactions.filter(t => t.currency === cur)
                                                     .reduce((a,b) => a + parseFloat(b.amount), 0);
                            $(`#paidAmount${cur==='DARHAM'?'AED':cur}`).text(`${cur==='DARHAM'?'AED':cur} ${paid.toFixed(2)}`);

                            let remaining = 0;
                            if (cur === baseCurrency) {
                                remaining = remainingBase;
                            } else if (rates[cur]) {
                                if (baseCurrency === 'AFS') remaining = remainingBase / rates[cur];
                                else remaining = remainingBase * rates[cur];
                            } else {
                                remaining = 'N/A';
                            }

                            $(`#remainingAmount${cur==='DARHAM'?'AED':cur}`).text(`${cur==='DARHAM'?'AED':cur} ${typeof remaining==='number'?remaining.toFixed(2):remaining}`);
                        }
                    });

                    const exchangedAmounts = [];
                    exchangedAmounts.push(`${baseCurrency} ${totalAmount.toFixed(2)}`);
                    Object.keys(rates).forEach(cur => {
                        const val = (baseCurrency === 'AFS') ? totalAmount / rates[cur] : totalAmount * rates[cur];
                        exchangedAmounts.push(`${cur} ${val.toFixed(2)}`);
                    });
                    $('#exchangedAmount').text(exchangedAmounts.join(', '));

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

    formatDate: function(dateString) {
        if (!dateString) return 'N/A';
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    },

    editTransaction: function(transactionId, description, amount, createdAt, currency, exchangeRate) {
        const dateTime = new Date(createdAt);
        const formattedDate = dateTime.toISOString().split('T')[0];
        const hours = String(dateTime.getHours()).padStart(2, '0');
        const minutes = String(dateTime.getMinutes()).padStart(2, '0');
        const seconds = String(dateTime.getSeconds()).padStart(2, '0');
        const formattedTime = `${hours}:${minutes}:${seconds}`;
        const ticketId = $('#booking_id').val();

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
                                    <input type="hidden" id="editTicketId" name="ticket_id">
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
                                                <select class="form-control" id="editPaymentCurrency" name="payment_currency" required>
                                                    <option value="USD">USD</option>
                                                    <option value="AFS">AFS</option>
                                                    <option value="EUR">EUR</option>
                                                    <option value="DARHAM">DARHAM</option>
                                                </select>
                                                <input type="hidden" id="editPaymentCurrencyHidden" name="payment_currency_actual">
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

            $('#editPaymentCurrency').on('change', transactionManager.toggleEditExchangeRateField.bind(transactionManager));

            // Handle edit form submission with double-submit prevention
            let isEditSubmitting = false;
            
            $('#editTransactionForm').on('submit', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                // Prevent double submission
                if (isEditSubmitting) {
                    console.log('Edit form submission already in progress');
                    return false;
                }
                
                isEditSubmitting = true;
                const $submitBtn = $('#editTransactionForm button[type="submit"]');
                const originalBtnHtml = $submitBtn.html();
                $submitBtn.prop('disabled', true).html('<i class="feather icon-loader spin"></i> Saving...');
                
                const formData = new FormData(this);
                const currentTicketId = $('#booking_id').val();
                formData.set('ticket_id', currentTicketId);
                
                // Use the hidden currency field value since the select is disabled
                const actualCurrency = $('#editPaymentCurrencyHidden').val();
                if (actualCurrency) {
                    formData.set('payment_currency', actualCurrency);
                }
                
                // Log form data for debugging
                console.log('Edit Form Data:');
                for (let pair of formData.entries()) {
                    console.log(pair[0] + ': ' + pair[1]);
                }
                
                if (!formData.get('transaction_id')) {
                    alert('Error: Missing transaction ID');
                    isEditSubmitting = false;
                    $submitBtn.prop('disabled', false).html(originalBtnHtml);
                    return false;
                }
                
                if (!formData.get('ticket_id')) {
                    alert('Error: Missing ticket ID');
                    isEditSubmitting = false;
                    $submitBtn.prop('disabled', false).html(originalBtnHtml);
                    return false;
                }
                
                // Remove required attribute from hidden exchange rate field before validation
                if ($('#editExchangeRateField').is(':hidden')) {
                    $('#editTransactionExchangeRate').removeAttr('required');
                }
                
                const date = formData.get('payment_date');
                const time = formData.get('payment_time');
                if (date && time) {
                    formData.set('payment_date', `${date} ${time}`);
                    formData.delete('payment_time'); // Remove separate time field
                }

                const exchangeRate = $('#editTransactionExchangeRate').val();
                if (exchangeRate && $('#editExchangeRateField').is(':visible')) {
                    formData.set('payment_exchange_rate', exchangeRate);
                }
                
                $.ajax({
                    url: '../api/ticket/update_ticket_payment.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    timeout: 30000,
                    success: function(response) {
                        console.log('Edit response:', response);
                        try {
                            const result = typeof response === 'string' ? JSON.parse(response) : response;
                            if (result.success) {
                                alert('Transaction updated successfully');
                                $('#editTransactionModal').modal('hide');
                                transactionManager.loadTransactionHistory(currentTicketId);
                            } else {
                                alert('Error updating transaction: ' + (result.message || 'Unknown error'));
                            }
                        } catch (e) {
                            console.error('Error parsing response:', e);
                            console.error('Raw response:', response);
                            alert('Error processing the request');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', error);
                        console.error('Status:', status);
                        console.error('Response:', xhr.responseText);
                        
                        if (status === 'timeout') {
                            alert('Request timed out. Please try again.');
                        } else {
                            alert('Error updating transaction. Check console for details.');
                        }
                    },
                    complete: function() {
                        isEditSubmitting = false;
                        $submitBtn.prop('disabled', false).html(originalBtnHtml);
                    }
                });
                
                return false;
            });
        }
        
        $('#editTransactionId').val(transactionId);
        $('#editTicketId').val(ticketId);
        $('#originalAmount').val(amount);
        $('#editPaymentDate').val(formattedDate);
        $('#editPaymentTime').val(formattedTime);
        $('#editPaymentAmount').val(parseFloat(amount).toFixed(2));
        $('#editPaymentDescription').val(description);
        $('#editPaymentCurrency').val(currency);

        transactionManager.toggleEditExchangeRateField();

        if (exchangeRate && exchangeRate !== 'null') {
            $('#editTransactionExchangeRate').val(exchangeRate);
            $('#editExchangeRateField').show();
        } else {
            $('#editExchangeRateField').hide();
            $('#editTransactionExchangeRate').val('');
        }
        
        $('#editTransactionModal').modal('show');
    },

    deleteTransaction: function(transactionId, amount) {
        if (!confirm('Are you sure you want to delete this transaction?')) {
            return;
        }

        const ticketId = $('#booking_id').val();

        $.ajax({
            url: '../api/ticket/delete_ticket_payment.php',
            type: 'POST',
            data: {
                transaction_id: transactionId,
                ticket_id: ticketId,
                amount: amount
            },
            success: function(response) {
                try {
                    const result = typeof response === 'string' ? JSON.parse(response) : response;
                    if (result.success) {
                        transactionManager.loadTransactionHistory(ticketId);
                        showToast('Transaction deleted successfully', 'success');
                    } else {
                        showToast('Error deleting transaction: ' + (result.message || 'Unknown error'), 'error');
                    }
                } catch (e) {
                    console.error('Error parsing response:', e);
                    showToast('Error processing the request', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Delete Error Response:', {
                    status: xhr.status,
                    error: error,
                    response: xhr.responseText
                });
                showToast('Error deleting transaction', 'error');
            }
        });
    }
};

function printReceipt(transactionId) {
    window.open(`../api/ticket/print_receipt.php?id=${transactionId}`, '_blank');
}

$(document).ready(function() {
    transactionManager.init();
});

function manageTransactions(ticketId) {
    transactionManager.loadTransactionModal(ticketId);
}