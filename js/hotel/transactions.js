// Global submission flag to prevent multiple submissions
let isHotelTransactionSubmitting = false;

/**
 * Transaction Management Module for Hotel Bookings
 */
const transactionManager = {
    // Currency display mapping
    getCurrencyDisplay: function(currencyCode) {
        const currencyMap = {
            'USD': 'USD',
            'AFS': 'AFS',
            'EUR': 'EUR',
            'DARHAM': 'AED'
        };
        return currencyMap[currencyCode] || currencyCode;
    },

    // Generate dynamic exchange rate example
    getExchangeRateExample: function(baseCurrency, targetCurrency) {
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
        const key = `${targetCurrency}-${baseCurrency}`;
        return examples[key] || 'Enter the exchange rate';
    },

    // Initialize transaction modal and form handlers
    init: function() {
        this.bindEvents();
        this.setDefaultDateTime();
    },

    // Bind all event listeners
    bindEvents: function() {
        // Remove any existing handlers first to prevent duplicates
        $('#hotelTransactionForm').off('submit').on('submit', this.handleTransactionSubmit.bind(this));
        
        // Additional protection: Disable button on click to prevent multiple submissions
        $('#hotelTransactionForm button[type="submit"]').off('click').on('click', function(e) {
            if (isHotelTransactionSubmitting) {

                e.preventDefault();
                e.stopPropagation();
                return false;
            }
        });
        
        $('#editTransactionForm').off('submit').on('submit', (e) => this.handleEditTransactionSubmit.call(this, e));
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
            
            // Get display names for currencies
            const baseDisplay = transactionManager.getCurrencyDisplay(window.bookingCurrency);
            const targetDisplay = transactionManager.getCurrencyDisplay(selectedCurrency);
            
            // Determine anchor currency (USD, EUR, AED, or AFS)
            let anchorCurrency = window.bookingCurrency;
            const currencies = [selectedCurrency, window.bookingCurrency];
            if (currencies.includes('USD')) {
                anchorCurrency = 'USD';
            } else if (currencies.includes('EUR')) {
                anchorCurrency = 'EUR';
            } else if (currencies.includes('AED')) {
                anchorCurrency = 'AED';
            } else if (currencies.includes('AFS')) {
                anchorCurrency = 'AFS';
            }
            
            const anchorDisplay = transactionManager.getCurrencyDisplay(anchorCurrency);
            const otherDisplay = anchorCurrency === window.bookingCurrency ? targetDisplay : baseDisplay;
            
            // Update label to match example rule: "1 ANCHOR = OTHER"
            const label = `<i class="feather icon-refresh-cw mr-1"></i>${anchorDisplay} to ${otherDisplay} Exchange Rate`;
            $('#exchangeRateLabel').html(label);
            
            // Update helper text to match anchor currency concept
            // Always show "1 ANCHOR = X OTHER, enter X"
            $('#exchangeRateBase').text(anchorDisplay);
            $('#exchangeRateTarget').text(otherDisplay);
            
            // Update the instruction text dynamically
            const instructionText = `Enter how many ${otherDisplay} equals 1 ${anchorDisplay}`;
            $('#exchangeRateInstruction').text(instructionText);
            
            // Update example based on currency pair
            const example = transactionManager.getExchangeRateExample(baseDisplay, targetDisplay);
            $('#exchangeRateExample').text(example);
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
            
            // Get display names for currencies
            const baseDisplay = transactionManager.getCurrencyDisplay(window.bookingCurrency);
            const targetDisplay = transactionManager.getCurrencyDisplay(selectedCurrency);
            
            // Determine anchor currency (USD, EUR, AED, or AFS)
            let anchorCurrency = window.bookingCurrency;
            const currencies = [selectedCurrency, window.bookingCurrency];
            if (currencies.includes('USD')) {
                anchorCurrency = 'USD';
            } else if (currencies.includes('EUR')) {
                anchorCurrency = 'EUR';
            } else if (currencies.includes('AED')) {
                anchorCurrency = 'AED';
            } else if (currencies.includes('AFS')) {
                anchorCurrency = 'AFS';
            }
            
            const anchorDisplay = transactionManager.getCurrencyDisplay(anchorCurrency);
            const otherDisplay = anchorCurrency === window.bookingCurrency ? targetDisplay : baseDisplay;
            
            // Update label to match example rule: "1 ANCHOR = OTHER"
            const label = `<i class="feather icon-refresh-cw mr-1"></i>${anchorDisplay} to ${otherDisplay} Exchange Rate`;
            $('#editExchangeRateLabel').html(label);
            
            // Update helper text to match anchor currency concept
            // Always show "1 ANCHOR = X OTHER, enter X"
            $('#editExchangeRateBase').text(anchorDisplay);
            $('#editExchangeRateTarget').text(otherDisplay);
            
            // Update the instruction text dynamically
            const instructionText = `Enter how many ${otherDisplay} equals 1 ${anchorDisplay}`;
            $('#editExchangeRateInstruction').text(instructionText);
            
            // Update example based on currency pair
            const example = transactionManager.getExchangeRateExample(baseDisplay, targetDisplay);
            $('#editExchangeRateExample').text(example);
        } else {
            $('#editExchangeRateField').hide();
            $('#editTransactionExchangeRate').attr('required', false);
            $('#editTransactionExchangeRate').val(''); // Clear value when hidden
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

    // Load and display transaction modal
    loadTransactionModal: function(bookingId) {
        if (!bookingId) {
            const btn = this._activeBtn;
            if (btn) { HotelBtn.clearTimer(btn); HotelBtn.done(btn); delete this._activeBtn; }
            return;
        }

        // Store booking ID in the form
        $('#booking_id').val(bookingId);
        $('#editBookingId').val(bookingId);

        // Reset form fields
        $('#hotelTransactionForm')[0].reset();
        this.setDefaultDateTime();

        // Load booking details and transaction history
        $.ajax({
            url: '../api/hotel/get_hotel_bookings.php',
            type: 'GET',
            data: { id: bookingId },
            dataType: 'json',
            success: function(response) {
                const btn = transactionManager._activeBtn;
                if (btn) { HotelBtn.clearTimer(btn); HotelBtn.done(btn); delete transactionManager._activeBtn; }
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
                const btn = transactionManager._activeBtn;
                if (btn) { HotelBtn.clearTimer(btn); HotelBtn.done(btn); delete transactionManager._activeBtn; }
                showToast('Failed to load booking details', 'error');
            }
        });

        // Show the modal
        $('#transactionsModal').modal('show');
    },

    // Load transaction history
    loadTransactionHistory: function(bookingId) {
        $.ajax({
            url: '../api/hotel/get_hotel_transactions.php',
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
                        const receipt = tx.receipt || tx.receipt_number || '';

                        if (currency in hasCurrency) hasCurrency[currency] = true;

                        tbody.append(`
                            <tr>
                                <td>${transactionManager.formatDate(tx.transaction_date)}</td>
                                <td>${tx.description || ''}</td>
                                <td>${receipt || '\u2014'}</td>
                                <td>${currency} ${amount.toFixed(2)}</td>
                                <td>${exchangeRate || 'N/A'}</td>
                                <td class="text-center">
                                    <button class="btn btn-primary btn-sm" onclick="transactionManager.editTransaction(${tx.id}, '${(tx.description||'').replace(/'/g,"\\'")}', ${amount}, '${currency}', ${tx.exchange_rate || 'null'}, '${receipt.replace(/'/g,"\\'")}')">
                                        <i class="feather icon-edit"></i>
                                    </button>
                                                                    <button class="btn btn-info btn-sm mr-1" title="Print Receipt"
                                        onclick="printReceipt(${tx.id})">
                                    <i class="feather icon-printer"></i>
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

                    $('#transactionTableBody').html('<tr><td colspan="6" class="text-center">error_loading_transactions</td></tr>');
                    $('#exchangeRateDisplay').text('Error loading exchange rates');
                    $('#exchangedAmount').text('Error calculating amounts');
                }
            },
            error: function(xhr, status, error){

                $('#transactionTableBody').html('<tr><td colspan="6" class="text-center">error_loading_transactions</td></tr>');
                $('#exchangeRateDisplay').text('Error loading exchange rates');
                $('#exchangedAmount').text('Error calculating amounts');
            }
        });
    },

    // Handle transaction form submission with double-submit prevention
    handleTransactionSubmit: function(e) {
        e.preventDefault();
        e.stopPropagation();



        // PREVENTION #1: Check if already submitting
        if (isHotelTransactionSubmitting) {

            return false;
        }

        // Set submitting flag immediately
        isHotelTransactionSubmitting = true;


        // PREVENTION #2: Disable submit button immediately
        this.setSubmitButtonState(true, 'Submitting...');

        const form = e.target; // Get form from event target


        // Check if form is valid HTMLFormElement
        if (!(form instanceof HTMLFormElement)) {

            showToast('Error: Invalid form element', 'error');
            isHotelTransactionSubmitting = false;
            this.setSubmitButtonState(false);
            return false;
        }

        const formData = new FormData(form);
        const bookingId = formData.get('booking_id');


        if (!bookingId) {

            showToast('Error: Missing booking ID', 'error');
            isHotelTransactionSubmitting = false;
            this.setSubmitButtonState(false);
            return false;
        }

        // CSRF Token Refresh: Get fresh token from meta tag before submission
        const metaCsrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        if (metaCsrfToken) {
            formData.set('csrf_token', metaCsrfToken);
        }

        // Combine date and time
        const date = formData.get('payment_date');
        const time = formData.get('payment_time') || '00:00:00';
        if (date) {
            formData.set('payment_date', `${date} ${time}`);
        }
        formData.set('receipt_number', ($('#receiptNumber').val() || '').trim());

        const self = this;



        // Set a backup timeout to re-enable the form in case something goes wrong
        const backupTimeout = setTimeout(() => {

            isHotelTransactionSubmitting = false;
            self.setSubmitButtonState(false);
        }, 35000); // 35 seconds (5 seconds after the main timeout)

        $.ajax({
            url: '../api/hotel/add_hotel_transaction.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            timeout: 30000, // 30 second timeout
            success: function(response) {

                clearTimeout(backupTimeout); // Clear backup timeout
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

                    showToast('Error processing the request', 'error');
                }
            },
            error: function(xhr, status, error) {


                clearTimeout(backupTimeout); // Clear backup timeout
                
                // Show appropriate error message
                if (status === 'timeout') {
                    showToast('Request timed out. Please check your connection and try again.', 'error');
                } else {
                    showToast('Error adding transaction. Please try again.', 'error');
                }
            },
            complete: function() {

                // CRITICAL: Always re-enable form in complete callback
                // This runs whether success or error
                isHotelTransactionSubmitting = false;
                self.setSubmitButtonState(false);
            }
        });

        return false;
    },

    // Edit transaction
    editTransaction: function(transactionId, description, amount, currency, exchangeRate, receipt) {
        // Populate the edit form
        $('#editTransactionId').val(transactionId);
        $('#originalAmount').val(amount);
        $('#editPaymentAmount').val(parseFloat(amount).toFixed(2));
        $('#editPaymentDescription').val(description);
        $('#editPaymentCurrency').val(currency);
        $('#editPaymentCurrencyHidden').val(currency);
        $('#editTransactionExchangeRate').val(exchangeRate || '');
        $('#editReceiptNumber').val(receipt || '');

        // Trigger change event to update exchange rate field visibility
        $('#editPaymentCurrency').trigger('change');

        // Show the modal
        $('#editTransactionModal').modal('show');
    },

    // Handle edit transaction form submission
    handleEditTransactionSubmit: function(e) {
        e.preventDefault();

        // Disable submit button to prevent multiple clicks
        const submitBtn = $(e.target).find('button[type="submit"]');
        const originalText = submitBtn.html();
        submitBtn.prop('disabled', true);
        submitBtn.html('<i class="feather icon-loader mr-2 spinner-border spinner-border-sm" role="status" aria-hidden="true"></i>Saving...');

        const form = e.target; // Get form from event target
        const formData = new FormData(form);
        const currentBookingId = $('#editBookingId').val();
        formData.set('booking_id', currentBookingId);

        if (!formData.get('transaction_id') || !formData.get('booking_id')) {
            // Re-enable button on validation error
            submitBtn.prop('disabled', false);
            submitBtn.html(originalText);
            showToast('Error: Missing required information', 'error');
            return;
        }

        // CSRF Token Refresh: Get fresh token from meta tag before submission
        const metaCsrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        if (metaCsrfToken) {
            formData.set('csrf_token', metaCsrfToken);
        }

        // Use hidden currency for submit
        formData.set('payment_currency', $('#editPaymentCurrencyHidden').val() || $('#editPaymentCurrency').val());
        // Add receipt number
        formData.set('receipt_number', ($('#editReceiptNumber').val() || '').trim());

        $.ajax({
            url: '../api/hotel/update_hotel_transaction.php',
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
                        // Re-enable button on error
                        submitBtn.prop('disabled', false);
                        submitBtn.html(originalText);
                        showToast('Error updating transaction: ' + (result.message || 'Unknown error'), 'error');
                    }
                } catch (e) {
                    // Re-enable button on error
                    submitBtn.prop('disabled', false);
                    submitBtn.html(originalText);
                    showToast('Error processing request', 'error');
                }
            },
            error: function(xhr, status, error) {
                // Re-enable button on error
                submitBtn.prop('disabled', false);
                submitBtn.html(originalText);
                showToast('Error updating transaction', 'error');
            }
        });

        // Re-enable submit button after 10 seconds as safety measure
        setTimeout(function() {
            if (submitBtn.prop('disabled')) {
                submitBtn.prop('disabled', false);
                submitBtn.html(originalText);
            }
        }, 10000);
    },

    // Delete transaction
    deleteTransaction: function(transactionId, bookingId, amount) {
        if (!confirm('Are you sure you want to delete this transaction?')) {
            return;
        }

        // Get the delete button that was clicked
        const clickedBtn = $(`button[onclick="transactionManager.deleteTransaction(${transactionId}, ${bookingId}, ${amount})"]`);
        const originalContent = clickedBtn.html();
        
        // Disable button and show loading state
        clickedBtn.prop('disabled', true);
        clickedBtn.html('<i class="feather icon-loader"></i>');

        // Get CSRF token from meta tag or hidden input
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ||
                         document.querySelector('input[name="csrf_token"]')?.value;

        $.ajax({
            url: '../api/hotel/delete_hotel_transaction.php',
            type: 'POST',
            data: {
                transaction_id: transactionId,
                booking_id: bookingId,
                amount: amount,
                csrf_token: csrfToken
            },
            success: function(response) {
                try {
                    const result = typeof response === 'string' ? JSON.parse(response) : response;
                    if (result.success) {
                        transactionManager.loadTransactionHistory(bookingId);
                        showToast('Transaction deleted successfully', 'success');
                    } else {
                        // Re-enable button on error
                        clickedBtn.prop('disabled', false);
                        clickedBtn.html(originalContent);
                        showToast('Error deleting transaction: ' + (result.message || 'Unknown error'), 'error');
                    }
                } catch (e) {
                    // Re-enable button on error
                    clickedBtn.prop('disabled', false);
                    clickedBtn.html(originalContent);
                    showToast('Error processing request', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.log({
                    status: xhr.status,
                    error: error,
                    response: xhr.responseText
                });
                // Re-enable button on error
                clickedBtn.prop('disabled', false);
                clickedBtn.html(originalContent);
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
    // Print receipt function
    function printReceipt(transactionId) {
        window.open(`../api/hotel/print_hotel_receipt.php?id=${transactionId}`, '_blank');
    }
// Initialize transaction manager when document is ready
$(document).ready(function() {
    transactionManager.init();
});

// Global function to manage transactions (called from HTML)
function manageTransactions(bookingId) {
    const btn = HotelBtn.fromOnclick(`manageTransactions(${bookingId})`);
    btn && (transactionManager._activeBtn = btn);
    HotelBtn.loading(btn, '<i class="fas fa-spinner fa-spin"></i>');
    HotelBtn.safetyTimer(btn, 15000);

    transactionManager.loadTransactionModal(bookingId);
}

// Toast notifications are handled by the global showToast function
