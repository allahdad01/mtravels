    // Transaction Management System
    const transactionManager = {
        // Initialize transaction modal and form handlers
        init: function() {
            this.bindEvents();
            this.setDefaultDateTime();
        },

        // Bind all event listeners
        bindEvents: function() {
            $('#visaTransactionForm').off('submit').on('submit', this.handleTransactionSubmit);
            $('#paymentCurrency').on('change', this.toggleExchangeRateField.bind(this));
            $('#editPaymentCurrency').on('change', this.toggleEditExchangeRateField.bind(this));
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

        // Load transaction history
        loadTransactionHistory: function(refundId) {
            $.ajax({
                url: 'get_visa_refund_transactions.php',
                type: 'GET',
                data: { refund_id: refundId },
                dataType: 'json',
                success: function(response) {
                    try {
                        const transactions = typeof response === 'string' ? JSON.parse(response) : response;
                        const tbody = $('#transactionTableBody');
                        tbody.empty();

                        const transactionList = transactions.transactions || transactions;

                        if (!Array.isArray(transactionList) || transactionList.length === 0) {
                            tbody.html('<tr><td colspan="6" class="text-center">No transactions found</td></tr>');
                            $('#exchangeRateDisplay').text('No exchange rates found');
                            $('#exchangedAmount').text('No conversions available');
                            return;
                        }

                        const baseCurrency = $('#totalAmount').text().split(' ')[0];
                        const totalAmount = parseFloat($('#totalAmount').text().split(' ')[1]) || 0;

                        // Collect exchange rates from DB transactions
                        let rates = {}; // { EUR: 87, AFS: 70, DARHAM: 18.5 }
                        transactionList.forEach(tx => {
                            if (tx.currency !== baseCurrency && tx.exchange_rate) {
                                rates[tx.currency] = parseFloat(tx.exchange_rate);
                            }
                        });

                        // Track currencies present in transactions
                        let hasCurrency = { USD: false, AFS: false, EUR: false, DARHAM: false };

                        // Render transactions table
                        transactionList.forEach(tx => {
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
                                        <button class="btn btn-danger btn-sm" onclick="transactionManager.deleteTransaction(${tx.id})">
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
                        transactionList.forEach(tx => {
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
                                const paid = transactionList.filter(t => t.currency === cur)
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
            e.stopPropagation();  // Prevent event bubbling

            // Get the submit button
            const submitButton = $(this).find('button[type="submit"]');

            // If the form is already being submitted, return
            if (submitButton.prop('disabled')) {
                return;
            }

            // Disable the submit button
            submitButton.prop('disabled', true);
            submitButton.html('<i class="fas fa-spinner fa-spin"></i> processing...');

            const formData = new FormData(this);
            const refundId = $('#refund_id').val();

            if (!refundId) {
                alert('refund_id_is_missing');
                // Re-enable the submit button
                submitButton.prop('disabled', false);
                submitButton.html('<i class="feather icon-check mr-1"></i> add_transaction');
                return;
            }

            // Ensure refund_id is included in formData
            formData.set('refund_id', refundId);

            // Combine date and time into a single datetime value
            const date = formData.get('payment_date');
            const time = formData.get('payment_time') || '00:00:00';
            if (date) {
                formData.set('payment_date', `${date} ${time}`);
            }

            // Get the original amount from the total amount display
            const totalAmountText = $('#totalAmount').text();
            const originalAmount = parseFloat(totalAmountText.split(' ')[1]) || 0;

            // Set the original amount
            formData.set('original_amount', originalAmount);

            // Add exchange rate if provided
            const exchangeRate = $('#transactionExchangeRate').val();
            if (exchangeRate && $('#exchangeRateField').is(':visible')) {
                formData.set('exchange_rate', exchangeRate);
            }

            $.ajax({
                url: 'process_visa_refund_transaction.php',
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
                            $('#visaTransactionForm')[0].reset();
                            transactionManager.setDefaultDateTime();
                            // Reset exchange rate field
                            $('#exchangeRateField').hide();
                            $('#transactionExchangeRate').attr('required', false);
                            $('#transactionExchangeRate').val('');
                            transactionManager.loadTransactionHistory(refundId);
                        } else {
                            alert('Error adding transaction: ' + (result.message || 'Unknown error'));
                        }
                    } catch (e) {
                        console.error('Error parsing response:', e);
                        alert('error_processing_the_request');
                    } finally {
                        // Re-enable the submit button
                        submitButton.prop('disabled', false);
                        submitButton.html('<i class="feather icon-check mr-1"></i> add_transaction');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error);
                    alert('error_adding_transaction');
                    // Re-enable the submit button
                    submitButton.prop('disabled', false);
                    submitButton.html('<i class="feather icon-check mr-1"></i> add_transaction');
                }
            });
        },

        // Edit transaction
        editTransaction: function(transactionId) {
            const refundId = $('#refund_id').val();
            
            // Fetch transaction details
            $.ajax({
                url: 'get_visa_transaction.php',
                type: 'GET',
                data: { transaction_id: transactionId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        const tx = response.transaction;
                        
                        // Parse the datetime string
                        const txDate = new Date(tx.created_at);
                        const formattedDate = txDate.toISOString().split('T')[0];
                        
                        // Format time as HH:MM:SS
                        const hours = String(txDate.getHours()).padStart(2, '0');
                        const minutes = String(txDate.getMinutes()).padStart(2, '0');
                        const seconds = String(txDate.getSeconds()).padStart(2, '0');
                        const formattedTime = `${hours}:${minutes}:${seconds}`;
                        
                        // Store the original transaction amount
                        const originalAmount = Math.abs(parseFloat(tx.amount));
                        
                        // Populate form fields
                        $('#editTransactionId').val(tx.id);
                        $('#editRefundId').val(refundId);
                        $('#editOriginalAmount').val(originalAmount);  // Set original transaction amount
                        $('#originalAmount').val(originalAmount);      // Set backup of original amount
                        $('#editPaymentDate').val(formattedDate);
                        $('#editPaymentTime').val(formattedTime);
                        $('#editPaymentAmount').val(Math.abs(tx.amount));  // Use absolute value of current amount
                        $('#editPaymentCurrency').val(tx.currency || 'USD');  // Set currency
                        $('#editPaymentDescription').val(tx.description);

                        // Set exchange rate if available
                        if (tx.exchange_rate && parseFloat(tx.exchange_rate) > 0) {
                            $('#editTransactionExchangeRate').val(tx.exchange_rate);
                            $('#editExchangeRateField').show();
                        } else {
                            $('#editExchangeRateField').hide();
                            $('#editTransactionExchangeRate').val('');
                        }

                        // Trigger currency change to update exchange rate field visibility
                        $('#editPaymentCurrency').trigger('change');
                        
                        // Show edit modal
                        $('#editTransactionModal').modal('show');
                    } else {
                        alert('error_fetching_transaction_details: ' + (response.message || 'unknown_error'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error);
                    alert('error_fetching_transaction_details');
                }
            });
        },
        
        // Delete transaction
        deleteTransaction: function(transactionId) {
            if (!confirm('are_you_sure_you_want_to_delete_this_transaction')) {
                return;
            }
            
            const refundId = $('#refund_id').val();
            
            $.ajax({
                url: 'delete_visa_refund_transaction.php',
                type: 'POST',
                data: {
                    transaction_id: transactionId,
                    refund_id: refundId
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

     // Function to process refund transaction
     function processRefundTransaction(refundId) {
        // Show loading state
        $('#refundTransactionModal .modal-content').addClass('loading');
        
        // Fetch refund details
        $.ajax({
            url: 'get_refund_details.php',
            type: 'GET',
            data: { id: refundId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const refund = response.refund;
                    
                    // Set form values
                    $('#refund_id').val(refundId);
                    $('#refundType').text(refund.refund_type === 'full' ? 'full_refund' : 'partial_refund');
                    $('#refundReason').text(refund.reason || 'N/A');
                    $('#refundApplicant').text(refund.applicant_name || 'N/A');
                    $('#refundPassport').text(refund.passport_number || 'N/A');
                    $('#totalAmount').text(`${refund.currency} ${parseFloat(refund.refund_amount).toFixed(2)}`);
                    $('#exchangeRateDisplay').text(parseFloat(refund.exchange_rate || 1).toFixed(4));
                    
                    // Calculate exchanged amount
                    const amount = parseFloat(refund.refund_amount);
                    const exchangeRate = parseFloat(refund.exchange_rate || 1);
                    const exchangedAmount = refund.currency === 'USD' ? 
                        amount * exchangeRate : 
                        amount / exchangeRate;
                    
                    $('#exchangedAmount').text(
                        `${refund.currency === 'USD' ? 'AFS' : 'USD'} ${exchangedAmount.toFixed(2)}`
                    );
                    
                    // Store original amounts for currency conversion
                    $('#paymentAmount')
                        .data('usd-amount', refund.currency === 'USD' ? amount : exchangedAmount)
                        .data('afs-amount', refund.currency === 'USD' ? exchangedAmount : amount);
                    
                    // Generate default description
                    const description = `Refund payment for Visa Application #${refund.visa_id} - ${refund.applicant_name}`;
                    $('#paymentDescription').val(description);
                    
                    // Load transaction history
                    transactionManager.loadTransactionHistory(refundId);
                    
                    // Remove loading state and show modal
                    $('#refundTransactionModal .modal-content').removeClass('loading');
                    $('#refundTransactionModal').modal('show');
                } else {
                    alert('error_fetching_refund_details: ' + (response.message || 'unknown_error'));
                    $('#refundTransactionModal .modal-content').removeClass('loading');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                alert('error_fetching_refund_details');
                $('#refundTransactionModal .modal-content').removeClass('loading');
            }
        });
    }

    // Initialize transaction manager when document is ready
    $(document).ready(function() {
        transactionManager.init();
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

        // Add exchange rate if provided
        const exchangeRate = $('#editTransactionExchangeRate').val();
        if (exchangeRate && $('#editExchangeRateField').is(':visible')) {
            formData.set('exchange_rate', exchangeRate);
        }
        
        $.ajax({
            url: 'update_visa_transaction.php',
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
                    submitButton.html('<i class="feather icon-save mr-1"></i> save_changes');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                alert('error_updating_transaction');
                submitButton.prop('disabled', false);
                submitButton.html('<i class="feather icon-save mr-1"></i> save_changes');
            }
        });
    });