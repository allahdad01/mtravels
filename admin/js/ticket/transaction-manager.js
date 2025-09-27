// Transaction Management
const transactionManager = {
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

        // Format time as HH:MM:SS
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
            $('#transactionExchangeRate').val(''); // Clear value when hidden
        }
    },

    // Toggle exchange rate field for edit form
    toggleEditExchangeRateField: function() {
        // Always show exchange rate field for edit form
        $('#editExchangeRateField').show();
        $('#editTransactionExchangeRate').attr('required', true);
    },

     // Load and display transaction modal
loadTransactionModal: function(ticketId) {
    $.ajax({
        url: 'get_ticket_bookings.php',
        type: 'GET',
        data: { id: ticketId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const booking = response.booking;

                // Set guest name and PNR
                $('#trans-guest-name').text(`${booking.title} ${booking.passenger_name}`);
                $('#trans-order-id').text(booking.pnr);

                // Total sold amount (in booking.currency)
                const originalAmount = parseFloat(booking.sold);
                $('#totalAmount').text(`${booking.currency} ${originalAmount.toFixed(2)}`);

                // Exchange rate will be set from transaction data
                $('#exchangeRateDisplay').text('Loading...');

                // Exchanged amount will be calculated from transaction data
                $('#exchangedAmount').text('Loading...');

                // Set booking ID in the form
                $('#booking_id').val(ticketId);

                // Store ticket currency for exchange rate logic
                window.ticketCurrency = booking.currency;

                // Load previous transaction history
                transactionManager.loadTransactionHistory(ticketId);

                // Show modal
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
        url: 'get_ticket_transactions.php',
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





    // Update format date function to handle SQL datetime
    formatDate: function(dateString) {
        if (!dateString) return 'N/A';
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    },

    // Add edit transaction function
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
        
        // Get the current ticket ID from the booking_id field
        const ticketId = $('#booking_id').val();
        
        console.log('Current ticket ID:', ticketId); // Debug log
        
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
                
                // Explicitly set the ticket ID again to ensure it's included
                const currentTicketId = $('#booking_id').val();
                formData.set('ticket_id', currentTicketId);
                
                // Ensure transaction_id and ticket_id are set
                if (!formData.get('transaction_id')) {
                    alert('Error: Missing transaction ID');
                    return;
                }
                
                if (!formData.get('ticket_id')) {
                    alert('Error: Missing ticket ID');
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
                    url: 'update_ticket_payment.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
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
                            alert('Error processing the request');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', error);
                        console.error('Response:', xhr.responseText);
                        alert('Error updating transaction');
                    }
                });
            });
        }
        
        // Populate the edit form with the current values
        $('#editTransactionId').val(transactionId);
        $('#editTicketId').val(ticketId);
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
            ticketId: ticketId,
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

    // Update delete transaction function to match your endpoint
    deleteTransaction: function(transactionId, amount) {
        if (!confirm('Are you sure you want to delete this transaction?')) {
            return;
        }

        const ticketId = $('#booking_id').val();

        // Send as form data instead of JSON
        $.ajax({
            url: 'delete_ticket_payment.php',
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
    },

    // Handle transaction form submission
    handleTransactionSubmit: function(e) {
        e.preventDefault(); // Prevent default form submission
        e.stopPropagation(); // Stop event from bubbling up

        // Gather form data manually to ensure all fields are captured
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
                return;
            }
        }

        // Combine date and time
        const paymentDateTime = `${formData.payment_date} ${formData.payment_time}`;

        // Send AJAX request to add transaction
        const ajaxData = {
            booking_id: formData.booking_id,
            payment_date: paymentDateTime,
            payment_amount: formData.payment_amount,
            payment_currency: formData.payment_currency,
            payment_description: formData.payment_description
        };

        // Add exchange rate if provided
        if (formData.payment_exchange_rate) {
            ajaxData.payment_exchange_rate = formData.payment_exchange_rate;
        }

        $.ajax({
            url: 'add_ticket_payment.php', // Use correct endpoint for ticket reservations
            type: 'POST',
            data: ajaxData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Close the transaction form
                    $('#addTransactionForm').collapse('hide');
                    
                    // Reload transaction history
                    transactionManager.loadTransactionHistory(formData.booking_id);
                    
                    // Show success message
                    alert('Transaction added successfully');
                    
                    // Reset the form
                    $('#hotelTransactionForm')[0].reset();
                    transactionManager.setDefaultDateTime();

                    // Reset exchange rate field
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
                alert('Error adding transaction');
            }
        });

        return false; // Ensure form is not submitted
    }
};

// Initialize transaction manager when document is ready
$(document).ready(function() {
    transactionManager.init();
});

// Global function to manage transactions (called from HTML)
function manageTransactions(ticketId) {
    transactionManager.loadTransactionModal(ticketId);
} 
