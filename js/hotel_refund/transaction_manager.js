            // Function to show toast with fallback to alert
            function showToast(message, type = 'success') {
                // Fallback to standard alert if Swal is not available
                if (typeof Swal === 'undefined') {
                    alert(message);
                    return;
                }
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

// Global submission flag to prevent multiple submissions
let isHotelRefundTransactionSubmitting = false;

// Transaction Management System
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
        'AFS-AED': 'Example: 1 AED = 23.99 AFS, enter 23.99',
        
        // SAR pairs - show "1 ANCHOR = X SAR" format
        'USD-SAR': 'Example: 1 USD = 3.75 SAR, enter 3.75',
        'SAR-USD': 'Example: 1 USD = 3.75 SAR, enter 3.75',
        'EUR-SAR': 'Example: 1 EUR = 4.07 SAR, enter 4.07',
        'SAR-EUR': 'Example: 1 EUR = 4.07 SAR, enter 4.07',
        'AED-SAR': 'Example: 1 AED = 1.02 SAR, enter 1.02',
        'SAR-AED': 'Example: 1 AED = 1.02 SAR, enter 1.02',
        'AFS-SAR': 'Example: 1 AFS = 18.67 SAR, enter 18.67',
        'SAR-AFS': 'Example: 1 AFS = 18.67 SAR, enter 18.67'
        };
        const key = `${baseCurrency}-${targetCurrency}`;
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
             if (isHotelRefundTransactionSubmitting) {
                 console.log('Form already submitting, preventing duplicate submission');
                 e.preventDefault();
                 e.stopPropagation();
                 return false;
             }
         });
        
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
                
                // Get display names for currencies
                const baseDisplay = transactionManager.getCurrencyDisplay(baseCurrency);
                const targetDisplay = transactionManager.getCurrencyDisplay(selectedCurrency);
                
                // Determine anchor currency (USD, EUR, AED, or AFS)
                let anchorCurrency = baseCurrency;
                const currencies = [selectedCurrency, baseCurrency];
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
                const otherDisplay = anchorCurrency === baseCurrency ? targetDisplay : baseDisplay;
                
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
            const baseCurrency = $('#totalAmount').text().split(' ')[0];
            if (selectedCurrency && baseCurrency && selectedCurrency !== baseCurrency) {
                $('#editExchangeRateField').show();
                $('#editTransactionExchangeRate').attr('required', true);
                
                // Get display names for currencies
                const baseDisplay = transactionManager.getCurrencyDisplay(baseCurrency);
                const targetDisplay = transactionManager.getCurrencyDisplay(selectedCurrency);
                
                // Determine anchor currency (USD, EUR, AED, or AFS)
                let anchorCurrency = baseCurrency;
                const currencies = [selectedCurrency, baseCurrency];
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
                const otherDisplay = anchorCurrency === baseCurrency ? targetDisplay : baseDisplay;
                
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
            url: '../api/hotel/get_hotel_refund_transactions.php',
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
                        tbody.html('<tr><td colspan="7" class="text-center">No transactions found</td></tr>');
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
                    let hasCurrency = { USD: false, AFS: false, EUR: false, DARHAM: false, SAR: false };

                    // Render transactions table
                    transactions.forEach(tx => {
                        const currency = tx.currency;
                        const amount = parseFloat(tx.amount);
                        const exchangeRate = tx.exchange_rate ? parseFloat(tx.exchange_rate) : null;
                        const receipt = tx.receipt || tx.receipt_number || '';

                        if (currency in hasCurrency) hasCurrency[currency] = true;

                        tbody.append(`
                            <tr>
                                <td>${transactionManager.formatDate(tx.created_at)}</td>
                                <td>${tx.description || ''}</td>
                                <td>${receipt || '\u2014'}</td>
                                <td>${tx.type === 'credit' ? 'Received' : 'Paid'}</td>
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
                    ['USD','AFS','EUR','DARHAM','SAR'].forEach(cur => {
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
                    $('#sarSection').toggle(hasCurrency.SAR);

                } catch(e) {

                    $('#transactionTableBody').html('<tr><td colspan="7" class="text-center">error_loading_transactions</td></tr>');
                    $('#exchangeRateDisplay').text('Error loading exchange rates');
                    $('#exchangedAmount').text('Error calculating amounts');
                }
            },
            error: function(xhr, status, error){

                $('#transactionTableBody').html('<tr><td colspan="7" class="text-center">error_loading_transactions</td></tr>');
                $('#exchangeRateDisplay').text('Error loading exchange rates');
                $('#exchangedAmount').text('Error calculating amounts');
            }
        });
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
            $submitBtn.html(`<i class="fas fa-spinner fa-spin"></i> ${text || 'Processing...'}`);
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
        if (isHotelRefundTransactionSubmitting) {

            return false;
        }

        // Set submitting flag immediately
        isHotelRefundTransactionSubmitting = true;


        // PREVENTION #2: Disable submit button immediately
        this.setSubmitButtonState(true, 'Processing...');
        
        const form = e.target; // Get form from event target
        const formData = new FormData(form);
        
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
        formData.set('receipt_number', ($('#receiptNumber').val() || '').trim());
        
        // Validate required fields
        if (!refundId) {

            showToast('error: Missing refund ID');
            isHotelRefundTransactionSubmitting = false;
            this.setSubmitButtonState(false);
            return false;
        }

        const self = this;



        // Set a backup timeout to re-enable the form in case something goes wrong
        const backupTimeout = setTimeout(() => {

            isHotelRefundTransactionSubmitting = false;
            self.setSubmitButtonState(false);
        }, 35000); // 35 seconds (5 seconds after the main timeout)
        
        $.ajax({
            url: '../api/hotel/add_hotel_refund_transaction.php',
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
                        showToast('transaction_added_successfully');
                        $('#addTransactionForm').collapse('hide');
                        transactionManager.loadTransactionHistory($('#refund_id').val());
                        $('#hotelTransactionForm')[0].reset();
                        transactionManager.setDefaultDateTime();
                    } else {
                        // Re-enable submit button on business logic error
                        showToast('error_adding_transaction: ' + (result.message || 'unknown_error'));
                    }
                } catch (e) {

                    // Re-enable submit button on parsing error
                    showToast('error_processing_the_request');
                }
            },
            error: function(xhr, status, error) {


                clearTimeout(backupTimeout); // Clear backup timeout
                showToast('error_adding_transaction');
            },
            complete: function() {

                // CRITICAL: Always re-enable form in complete callback
                // This runs whether success or error
                isHotelRefundTransactionSubmitting = false;
                self.setSubmitButtonState(false);
            }
        });

        return false;
    },

    // Edit transaction
    editTransaction: function(transactionId, description, amount, currency, exchangeRate, receipt) {
        // Get the current refund ID from the refund_id field
        const refundId = $('#refund_id').val();

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
                                        <div class="col-md-6">
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
                                                    <option value="SAR">SAR</option>
                                                </select>
                                                <input type="hidden" id="editPaymentCurrencyHidden" name="payment_currency_actual">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="editPaymentDescription">
                                            <i class="feather icon-file-text mr-1"></i>Description
                                        </label>
                                        <textarea class="form-control" id="editPaymentDescription"
                                                  name="payment_description" rows="2" required></textarea>
                                    </div>

                                    <div class="form-group">
                                        <label for="editReceiptNumber">
                                            <i class="feather icon-hash mr-1"></i>Receipt Number
                                        </label>
                                        <input type="text" class="form-control" id="editReceiptNumber"
                                               name="receipt_number" placeholder="Enter receipt number">
                                    </div>

                                    <div class="form-group" id="editExchangeRateField" style="display: none;">
                                        <label for="editTransactionExchangeRate">
                                            <i class="feather icon-refresh-cw mr-1"></i>Exchange Rate
                                        </label>
                                        <input type="number" class="form-control" id="editTransactionExchangeRate"
                                               name="exchange_rate" step="0.01" placeholder="Enter exchange rate">
                                        <small class="form-text text-muted d-block mt-1">
                                            Enter how many <span id="editExchangeRateTarget"></span> equals 1 <span id="editExchangeRateBase"></span>
                                            <span id="editExchangeRateExample" class="d-block mt-1" style="color: #666;"></span>
                                        </small>
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
        }

        // Bind the change event for the edit currency select (rebind in case modal was recreated)
        $('#editPaymentCurrency').off('change').on('change', transactionManager.toggleEditExchangeRateField.bind(transactionManager));

        // Add submit handler for the edit form using event delegation
        $(document).off('submit', '#editTransactionForm').on('submit', '#editTransactionForm', function(e) {
            e.preventDefault();

            // Disable submit button to prevent multiple clicks
            const submitBtn = $(this).find('button[type="submit"]');
            const originalText = submitBtn.html();
            submitBtn.prop('disabled', true);
            submitBtn.html('<i class="feather icon-refresh-cw mr-2 spinner-border spinner-border-sm" role="status" aria-hidden="true"></i>Saving...');

            // Create FormData from the form
            const formData = new FormData(this);

            // Explicitly set the refund ID again to ensure it's included
            const currentRefundId = $('#refund_id').val();
            formData.set('refund_id', currentRefundId);

            // Ensure transaction_id and refund_id are set
            if (!formData.get('transaction_id')) {
                submitBtn.prop('disabled', false);
                submitBtn.html(originalText);
                alert('Error: Missing transaction ID');
                return;
            }

            if (!formData.get('refund_id')) {
                submitBtn.prop('disabled', false);
                submitBtn.html(originalText);
                alert('Error: Missing refund ID');
                return;
            }

            // Use hidden currency for submit
            formData.set('payment_currency', $('#editPaymentCurrencyHidden').val() || $('#editPaymentCurrency').val());
            // Add receipt number
            formData.set('receipt_number', ($('#editReceiptNumber').val() || '').trim());

            $.ajax({
                url: '../api/hotel/update_refund_hotel_transaction.php',
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
                            submitBtn.prop('disabled', false);
                            submitBtn.html(originalText);
                            showToast('Error updating transaction: ' + (result.message || 'Unknown error'));
                        }
                    } catch (e) {
                        submitBtn.prop('disabled', false);
                        submitBtn.html(originalText);
                        showToast('Error processing the request');
                    }
                },
                error: function(xhr, status, error) {
                    submitBtn.prop('disabled', false);
                    submitBtn.html(originalText);
                    showToast('Error updating transaction');
                }
            });

            // Re-enable submit button after 10 seconds as safety measure
            setTimeout(function() {
                if (submitBtn.prop('disabled')) {
                    submitBtn.prop('disabled', false);
                    submitBtn.html(originalText);
                }
            }, 10000);
        });

        // Populate the edit form with the current values
        $('#editTransactionId').val(transactionId);
        $('#editRefundId').val(refundId);
        $('#originalAmount').val(amount);
        $('#editPaymentAmount').val(parseFloat(amount).toFixed(2));
        $('#editPaymentDescription').val(description);
        $('#editPaymentCurrency').val(currency);
        $('#editPaymentCurrencyHidden').val(currency);
        $('#editTransactionExchangeRate').val(exchangeRate || '');
        $('#editReceiptNumber').val(receipt || '');

        // Show exchange rate field
        transactionManager.toggleEditExchangeRateField();
        if (exchangeRate && exchangeRate !== 'null') {
            $('#editExchangeRateField').show();
        } else {
            $('#editExchangeRateField').hide();
        }

        // Show the modal
        $('#editTransactionModal').modal('show');
    },
    
    // Delete transaction
    deleteTransaction: function(transactionId, amount) {
        if (!confirm('Are you sure you want to delete this transaction?')) {
            return;
        }

        const refundId = $('#refund_id').val();

        // Get the delete button that was clicked
        const clickedBtn = $(`button[onclick="transactionManager.deleteTransaction(${transactionId}, ${amount})"]`);
        const originalContent = clickedBtn.html();
        
        // Disable button and show loading state
        clickedBtn.prop('disabled', true);
        clickedBtn.html('<i class="feather icon-loader"></i>');

        // Get CSRF token from meta tag or hidden input
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ||
                         document.querySelector('input[name="csrf_token"]')?.value;

        // Send as form data instead of JSON
        $.ajax({
            url: '../api/hotel/delete_hotel_refund_transactions.php',
            type: 'POST',
            data: {
                transaction_id: transactionId,
                refund_id: refundId,
                amount: amount,
                csrf_token: csrfToken
            },
            success: function(response) {
                try {
                    const result = typeof response === 'string' ? JSON.parse(response) : response;
                    if (result.success) {
                        transactionManager.loadTransactionHistory(refundId);
                        showToast('transaction_deleted_successfully');
                    } else {
                        // Re-enable button on error
                        clickedBtn.prop('disabled', false);
                        clickedBtn.html(originalContent);
                        showToast('error_deleting_transaction: ' + (result.message || 'Unknown error'));
                    }
                } catch (e) {
                    // Re-enable button on error
                    clickedBtn.prop('disabled', false);
                    clickedBtn.html(originalContent);
                    showToast('error_processing_the_request');
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
    // Print receipt function
    function printReceipt(transactionId) {
        window.open(`../api/hotel/print_hotel_refund_receipt.php?id=${transactionId}`, '_blank');
    }
// Initialize transaction manager when document is ready
$(document).ready(function() {
    transactionManager.init();
});

// Expose to global scope so inline onclick handlers in the rendered rows work
// (when embedded via new Function they are not global lexical bindings).
window.transactionManager = transactionManager;
window.printReceipt = printReceipt;
