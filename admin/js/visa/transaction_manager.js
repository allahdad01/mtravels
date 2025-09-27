 // Transaction Management
 const transactionManager = {
    // Initialize transaction modal and form handlers
    init: function() {
        this.bindEvents();
        this.setDefaultDateTime();
    },

    // Bind all event listeners
    bindEvents: function() {
        // Remove any existing event handlers first
        $('#visaTransactionForm').off('submit');
        $('#editTransactionForm').off('submit');
        $('#paymentCurrency').off('change');

        // Add new event handlers
        $('#visaTransactionForm').on('submit', this.handleTransactionSubmit);
        $('#editTransactionForm').on('submit', this.handleEditTransactionSubmit);
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

    // Load and display transaction modal
    loadTransactionModal: function(visaId) {
        $.ajax({
            url: 'get_visa_details.php',
            type: 'GET',
            data: { id: visaId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const visa = response.visa;
                    
                    // Update visa application details
                    $('#trans-applicant-name').text(visa.passenger_name || visa.client_name || 'N/A');
                    $('#trans-country').text(visa.country || 'N/A');
                    $('#trans-visa-type').text(visa.visa_type || 'N/A');
                    
                    // Display total amount
                    $('#totalAmount').text(`${visa.currency} ${parseFloat(visa.amount || visa.sold).toFixed(2)}`);
                    
                    // Display exchange rate
                    const exchangeRate = parseFloat(visa.exchange_rate) || 1;
                    $('#exchangeRateDisplay').text(exchangeRate.toFixed(2));
                    
                    // Calculate and display exchanged amount
                    const originalAmount = parseFloat(visa.amount || visa.sold);
                    const exchangedAmount = originalAmount * exchangeRate;
                    
                    // Determine the display currency based on exchange rate
                    let displayCurrency = visa.currency;
                    if (visa.currency === 'USD' && exchangeRate > 1) {
                        displayCurrency = 'AFS';
                    } else if (visa.currency === 'AFS' && exchangeRate < 1) {
                        displayCurrency = 'USD';
                    }
                    
                    $('#exchangedAmount').text(`${displayCurrency} ${exchangedAmount.toFixed(2)}`);
                    
                    // Set visa ID in form and hidden fields
                    $('#transactionVisaId').text(visaId);
                    $('#currency').text(visa.currency);
                    $('#transactionVisaIdInput').val(visaId);
                    $('#visa_id').val(visaId);
                    $('#booking_id').val(visaId); // Ensure all ID fields are set
                    
                    // Load transaction history
                    transactionManager.loadTransactionHistory(visaId);
                    
                    // Show modal
                    $('#transactionModal').modal('show');
                } else {
                    alert('Error fetching visa details: ' + (response.message || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                alert('Error fetching visa details');
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
        const visaId = $('#visa_id').val();
        
        if (!visaId) {
            alert('visa_id_is_missing');
            // Re-enable the submit button
            submitButton.prop('disabled', false);
            submitButton.html('<i class="feather icon-check mr-1"></i> add_transaction');
            return;
        }

        // Ensure visa_id is included in formData
        formData.set('visa_id', visaId);
        
        // Combine date and time into a single datetime value
        const date = formData.get('payment_date');
        const time = formData.get('payment_time') || '00:00:00';
        if (date) {
            formData.set('payment_date', `${date} ${time}`);
        }
        
        $.ajax({
            url: 'add_visa_transaction.php',
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
                        transactionManager.loadTransactionHistory(visaId);
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

   // Load transaction history
   loadTransactionHistory: function(visaId) {
       $.ajax({
           url: 'fetch_visa_transactions.php',
           type: 'GET',
           data: { visa_id: visaId },
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

                   const baseCurrency = $('#currency').text() || 'USD';
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

    // Format date function to handle SQL datetime
    formatDate: function(dateString) {
        if (!dateString) return 'N/A';
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    },

    // Edit transaction function
    editTransaction: function(transactionId, description, amount, createdAt, currency, exchangeRate) {
        // Format the date and time
        const dateTime = new Date(createdAt);
        const formattedDate = dateTime.toISOString().split('T')[0];
        const hours = String(dateTime.getHours()).padStart(2, '0');
        const minutes = String(dateTime.getMinutes()).padStart(2, '0');
        const seconds = String(dateTime.getSeconds()).padStart(2, '0');
        const formattedTime = `${hours}:${minutes}:${seconds}`;

        // Get the visa ID from the transaction modal
        const currentVisaId = $('#transactionVisaIdInput').val();
        console.log('Current visa ID:', currentVisaId);

        // Set the form values
        $('#editTransactionId').val(transactionId);
        $('#editVisaId').val(currentVisaId); // Set the visa_id
        $('#originalAmount').val(amount);
        $('#editPaymentDate').val(formattedDate);
        $('#editPaymentTime').val(formattedTime);
        $('#editPaymentAmount').val(parseFloat(amount).toFixed(2));
        $('#editPaymentDescription').val(description);
        $('#editPaymentCurrency').val(currency);

        // Trigger change event to update exchange rate field visibility
        $('#editPaymentCurrency').trigger('change');

        // Use the exchange rate passed as parameter
        if (exchangeRate && exchangeRate !== 'null' && parseFloat(exchangeRate) > 0) {
            $('#editTransactionExchangeRate').val(exchangeRate);
            $('#editExchangeRateField').show();
        } else {
            $('#editExchangeRateField').hide();
            $('#editTransactionExchangeRate').val('');
        }

        // Remove any existing submit handler
        $(document).off('submit', '#editTransactionForm');

        // Add the submit handler
        $(document).on('submit', '#editTransactionForm', function(e) {
            e.preventDefault();
            
            const submitButton = $(this).find('button[type="submit"]');
            if (submitButton.prop('disabled')) return;
            
            submitButton.prop('disabled', true);
            submitButton.html('<i class="fas fa-spinner fa-spin"></i> processing...');

            const postData = {
                transaction_id: transactionId,
                visa_id: currentVisaId,
                original_amount: amount,
                payment_date: $('#editPaymentDate').val(),
                payment_time: $('#editPaymentTime').val(),
                payment_amount: $('#editPaymentAmount').val(),
                payment_currency: $('#editPaymentCurrency').val(),
                payment_description: $('#editPaymentDescription').val()
            };

            // Add exchange rate if provided
            const exchangeRate = $('#editTransactionExchangeRate').val();
            if (exchangeRate && $('#editExchangeRateField').is(':visible')) {
                postData.payment_exchange_rate = exchangeRate;
            }

            console.log('Sending data to server:', postData);

            $.post('update_visa_payment.php', postData)
                .done(function(response) {
                    if (response.success) {
                        alert('transaction_updated_successfully');
                        $('#editTransactionModal').modal('hide');
                        transactionManager.loadTransactionHistory(currentVisaId);
                    } else {
                        alert('error: ' + (response.message || 'unknown_error'));
                    }
                })
                .fail(function(xhr, status, error) {
                    console.error('AJAX Error:', error);
                    console.error('Response:', xhr.responseText);
                    alert('error_updating_transaction');
                })
                .always(function() {
                    submitButton.prop('disabled', false);
                    submitButton.html('<i class="feather icon-check mr-1"></i> save_changes');
                });
        });

        // Show the modal
        $('#editTransactionModal').modal('show');
    },

    // Delete transaction function
    deleteTransaction: function(transactionId, amount) {
        if (!confirm('are_you_sure_you_want_to_delete_this_transaction')) {
            return;
        }

        const visaId = $('#visa_id').val();

        $.ajax({
            url: 'delete_visa_transaction.php',
            type: 'POST',
            data: {
                transaction_id: transactionId,
                visa_id: visaId,
                amount: amount
            },
            success: function(response) {
                try {
                    const result = typeof response === 'string' ? JSON.parse(response) : response;
                    if (result.success) {
                        transactionManager.loadTransactionHistory(visaId);
                        alert('transaction_deleted_successfully');
                    } else {
                        alert('error_deleting_transaction: ' + (result.message || 'unknown_error'));
                    }
                } catch (e) {
                    console.error('Error parsing response:', e);
                    alert('error_processing_the_request');
                }
            },
            error: function(xhr, status, error) {
                console.error('Delete Error Response:', {
                    status: xhr.status,
                    error: error,
                    response: xhr.responseText
                });
                alert('error_deleting_transaction');
            }
        });
    },
    
    // Refund transaction function
    refundTransaction: function(transactionId, description, amount, currency) {
        if (!confirm('are_you_sure_you_want_to_process_a_refund_for_this_transaction')) {
            return;
        }
        
        const visaId = $('#visa_id').val();
        
        // Prepare refund data
        const now = new Date();
        const today = now.toISOString().split('T')[0];
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        const currentTime = `${hours}:${minutes}:${seconds}`;
        
        // Auto-create refund description
        const refundDescription = `Refund for: ${description}`;
        
        const formData = new FormData();
        formData.append('visa_id', visaId);
        formData.append('payment_date', `${today} ${currentTime}`);
        formData.append('payment_description', refundDescription);
        formData.append('payment_amount', -amount); // Negative amount for refund
        formData.append('currency', currency);
        formData.append('is_refund', 'true');
        formData.append('original_transaction_id', transactionId);
        
        $.ajax({
            url: 'add_visa_transaction.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                try {
                    const result = typeof response === 'string' ? JSON.parse(response) : response;
                    if (result.success) {
                        alert('refund_processed_successfully');
                        transactionManager.loadTransactionHistory(visaId);
                    } else {
                        alert('error_processing_refund: ' + (result.message || 'unknown_error'));
                    }
                } catch (e) {
                    console.error('Error parsing response:', e);
                    alert('error_processing_the_refund_request');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                alert('error_processing_refund');
            }
        });
    }
};

// Initialize transaction manager when document is ready
$(document).ready(function() {
    transactionManager.init();
});

// Function to open transaction modal - global function called from HTML
function openTransactionTab(visaId, soldAmount, currency) {
    transactionManager.loadTransactionModal(visaId);
}