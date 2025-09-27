/**
 * Transaction Management Module for Hotel Bookings
 */
const transactionManager = {
    // Initialize transaction modal and form handlers
    init: function() {
        this.bindEvents();
        this.setDefaultDateTime();
    },

    // Bind all event listeners
    bindEvents: function() {
        $('#hotelTransactionForm').on('submit', (e) => this.handleTransactionSubmit.call(this, e));
        $('#editTransactionForm').on('submit', (e) => this.handleEditTransactionSubmit.call(this, e));
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
        if (selectedCurrency && window.bookingCurrency && selectedCurrency !== window.bookingCurrency) {
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
        if (selectedCurrency && window.bookingCurrency && selectedCurrency !== window.bookingCurrency) {
            $('#editExchangeRateField').show();
            $('#editTransactionExchangeRate').attr('required', true);
        } else {
            $('#editExchangeRateField').hide();
            $('#editTransactionExchangeRate').attr('required', false);
            $('#editTransactionExchangeRate').val(''); // Clear value when hidden
        }
    },

    // Load and display transaction modal
    loadTransactionModal: function(bookingId) {
        if (!bookingId) {
            console.error('No booking ID provided');
            return;
        }

        console.log('Loading transactions for booking ID:', bookingId);

        // Store booking ID in the form
        $('#booking_id').val(bookingId);
        $('#editBookingId').val(bookingId);

        // Reset form fields
        $('#hotelTransactionForm')[0].reset();
        this.setDefaultDateTime();

        // Load booking details and transaction history
        $.ajax({
            url: 'get_hotel_bookings.php',
            type: 'GET',
            data: { id: bookingId },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.bookings && response.bookings.length > 0) {
                    const booking = response.bookings[0];

                    // Display booking details
                    $('#trans-guest-name').text(`${booking.title || ''} ${booking.first_name || ''} ${booking.last_name || ''}`.trim());
                    $('#trans-order-id').text(booking.order_id || 'N/A');

                    // Display financial information
                    const currency = booking.currency || 'USD';
                    const soldAmount = parseFloat(booking.sold_amount) || 0;
                    const exchangeRate = parseFloat(booking.exchange_rate) || 1;

                    // Display original amount
                    $('#totalAmount').text(`${currency} ${soldAmount.toFixed(2)}`);

                    // If booking has exchange rate, use it as fallback
                    if (exchangeRate > 1) {
                        $('#exchangeRateDisplay').text(`Booking Rate: ${exchangeRate.toFixed(4)}`);
                    } else {
                        $('#exchangeRateDisplay').text('Loading...');
                    }

                    // Exchanged amount will be calculated from transaction data
                    $('#exchangedAmount').text('Loading...');

                    // Store booking currency for exchange rate logic
                    window.bookingCurrency = currency;

                    // Load transaction history
                    transactionManager.loadTransactionHistory(bookingId);
                } else {
                    // Handle error response
                    const errorMessage = response.message || 'Failed to load booking details';
                    showToast(errorMessage, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading booking details:', error);
                showToast('Failed to load booking details', 'error');
            }
        });

        // Show the modal
        $('#transactionsModal').modal('show');
    },

    // Load transaction history
    loadTransactionHistory: function(bookingId) {
        $.ajax({
            url: 'get_hotel_transactions.php',
            type: 'GET',
            data: { booking_id: bookingId },
            dataType: 'json',
            success: function(response) {
                try {
                    const transactions = response.transactions || [];
                    const tbody = $('#transactionTableBody');
                    tbody.empty();

                    if (!Array.isArray(transactions) || transactions.length === 0) {
                        tbody.html('<tr><td colspan="6" class="text-center">No transactions found</td></tr>');
                        $('#exchangeRateDisplay').text('No exchange rates found');
                        $('#exchangedAmount').text('No conversions available');
                        return;
                    }

                    const baseCurrency = window.bookingCurrency || 'USD';
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
                                <td>${transactionManager.formatDate(tx.transaction_date)}</td>
                                <td>${tx.description || ''}</td>
                                <td>${tx.type === 'credit' ? 'Received' : 'Paid'}</td>
                                <td>${currency} ${amount.toFixed(2)}</td>
                                <td>${exchangeRate || 'N/A'}</td>
                                <td class="text-center">
                                    <button class="btn btn-primary btn-sm" onclick="transactionManager.editTransaction(${tx.id}, '${(tx.description||'').replace(/'/g,"\\'")}', ${amount}, '${tx.transaction_date}', '${currency}', ${tx.exchange_rate || 'null'})">
                                        <i class="feather icon-edit"></i>
                                    </button>
                                    <button class="btn btn-danger btn-sm" onclick="transactionManager.deleteTransaction(${tx.id}, ${bookingId}, ${amount})">
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

        const form = e.target; // Get form from event target

        // Check if form is valid HTMLFormElement
        if (!(form instanceof HTMLFormElement)) {
            console.error('Form is not a valid HTMLFormElement:', form);
            showToast('Error: Invalid form element', 'error');
            return;
        }

        const formData = new FormData(form);
        const bookingId = formData.get('booking_id');

        if (!bookingId) {
            console.error('No booking ID in form');
            showToast('Error: Missing booking ID', 'error');
            return;
        }

        // Combine date and time
        const date = formData.get('payment_date');
        const time = formData.get('payment_time') || '00:00:00';
        if (date) {
            formData.set('payment_date', `${date} ${time}`);
        }

        $.ajax({
            url: 'add_hotel_transaction.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                try {
                    const result = typeof response === 'string' ? JSON.parse(response) : response;

                    if (result.success) {
                        form.reset();
                        transactionManager.setDefaultDateTime();
                        $('#addTransactionForm').collapse('hide');
                        transactionManager.loadTransactionHistory(bookingId);
                        showToast('Transaction added successfully', 'success');
                    } else {
                        showToast('Error adding transaction: ' + (result.message || 'Unknown error'), 'error');
                    }
                } catch (e) {
                    console.error('Error parsing response:', e);
                    showToast('Error processing the request', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                showToast('Error adding transaction', 'error');
            }
        });
    },

    // Edit transaction
    editTransaction: function(transactionId, description, amount, transactionDate, currency, exchangeRate) {
        const dateTime = new Date(transactionDate);
        const formattedDate = dateTime.toISOString().split('T')[0];

        // Format time as HH:MM:SS
        const hours = String(dateTime.getHours()).padStart(2, '0');
        const minutes = String(dateTime.getMinutes()).padStart(2, '0');
        const seconds = String(dateTime.getSeconds()).padStart(2, '0');
        const formattedTime = `${hours}:${minutes}:${seconds}`;

        // Populate the edit form
        $('#editTransactionId').val(transactionId);
        $('#originalAmount').val(amount);
        $('#editPaymentDate').val(formattedDate);
        $('#editPaymentTime').val(formattedTime);
        $('#editPaymentAmount').val(parseFloat(amount).toFixed(2));
        $('#editPaymentDescription').val(description);
        $('#editPaymentCurrency').val(currency);
        $('#editTransactionExchangeRate').val(exchangeRate || '');

        // Trigger change event to update exchange rate field visibility
        $('#editPaymentCurrency').trigger('change');

        // Show the modal
        $('#editTransactionModal').modal('show');
    },

    // Handle edit transaction form submission
    handleEditTransactionSubmit: function(e) {
        e.preventDefault();

        const form = e.target; // Get form from event target
        const formData = new FormData(form);
        const currentBookingId = $('#editBookingId').val();
        formData.set('booking_id', currentBookingId);

        if (!formData.get('transaction_id') || !formData.get('booking_id')) {
            showToast('Error: Missing required information', 'error');
            return;
        }

        // Combine date and time
        const date = formData.get('payment_date');
        const time = formData.get('payment_time');
        if (date && time) {
            formData.set('payment_date', `${date} ${time}`);
        }

        $.ajax({
            url: 'update_hotel_transaction.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                try {
                    const result = typeof response === 'string' ? JSON.parse(response) : response;
                    if (result.success) {
                        showToast('Transaction updated successfully', 'success');
                        $('#editTransactionModal').modal('hide');
                        transactionManager.loadTransactionHistory(currentBookingId);
                    } else {
                        showToast('Error updating transaction: ' + (result.message || 'Unknown error'), 'error');
                    }
                } catch (e) {
                    console.error('Error parsing response:', e);
                    showToast('Error processing request', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                showToast('Error updating transaction', 'error');
            }
        });
    },

    // Delete transaction
    deleteTransaction: function(transactionId, bookingId, amount) {
        if (!confirm('Are you sure you want to delete this transaction?')) {
            return;
        }

        $.ajax({
            url: 'delete_hotel_transaction.php',
            type: 'POST',
            data: {
                transaction_id: transactionId,
                booking_id: bookingId,
                amount: amount
            },
            success: function(response) {
                try {
                    const result = typeof response === 'string' ? JSON.parse(response) : response;
                    if (result.success) {
                        transactionManager.loadTransactionHistory(bookingId);
                        showToast('Transaction deleted successfully', 'success');
                    } else {
                        showToast('Error deleting transaction: ' + (result.message || 'Unknown error'), 'error');
                    }
                } catch (e) {
                    console.error('Error parsing response:', e);
                    showToast('Error processing request', 'error');
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

    // Format date function to handle SQL datetime
    formatDate: function(dateString) {
        if (!dateString) return 'N/A';
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    }
};

// Initialize transaction manager when document is ready
$(document).ready(function() {
    transactionManager.init();
});

// Global function to manage transactions (called from HTML)
function manageTransactions(bookingId) {
    transactionManager.loadTransactionModal(bookingId);
}

// Toast notification function
function showToast(message, type = 'success') {
    // Use SweetAlert2 if available, otherwise fallback to alert
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: type,
            title: message,
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });
    } else {
        alert(message);
    }
}