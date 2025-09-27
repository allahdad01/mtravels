function openTransactionTab(umrahId, soldAmount) {
    // Make sure soldAmount is a number
    soldAmount = parseFloat(soldAmount) || 0;

    // Set the basic info (without currency yet)
    document.getElementById('transactionUmrahId').textContent = umrahId;
    document.getElementById('totalAmount').textContent = `USD ${soldAmount.toFixed(2)}`; // Default to USD
    document.getElementById('transactionUmrahIdInput').value = umrahId;

    // Fetch Umrah details for the transaction modal
    $.ajax({
        url: 'get_umrah_details.php',
        type: 'GET',
        data: { id: umrahId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const umrah = response.umrah;

                // Store booking currency globally for exchange rate field logic
                window.bookingCurrency = umrah.currency || 'USD';

                // Update total amount with correct currency
                document.getElementById('totalAmount').textContent = `${umrah.currency || 'USD'} ${soldAmount.toFixed(2)}`;

                // Update umrah details
                $('#trans-guest-name').text(umrah.name || 'N/A');
                $('#trans-package-name').text(umrah.family_name || 'N/A');

                // FIXED: Handle exchange rates properly - this section seems problematic
                // The exchange rate display should come from transaction data, not umrah details
                // Commenting out the potentially incorrect logic:
                /*
                const exchangeRate = parseFloat(umrah.exchange_rate) || 1;
                $('#exchangeRateDisplay').text(exchangeRate.toFixed(2));

                const originalAmount = parseFloat(soldAmount);
                const exchangedAmount = originalAmount * exchangeRate;

                let displayCurrency = 'USD';
                if (exchangeRate > 1) {
                    displayCurrency = 'AFS';
                }

                $('#exchangedAmount').text(`${displayCurrency} ${exchangedAmount.toFixed(2)}`);
                */

                // Now fetch transactions after we have the details
                loadTransactionHistory(umrahId);

                // Show modal
                $('#transactionModal').modal('show');
            } else {
                alert('error_loading_umrah_details: ' + (response.message || 'unknown_error'));
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            console.error('Response:', xhr.responseText);
            alert('error_loading_umrah_details');
        }
    });
}

function loadTransactionHistory(umrahId) {
    $.ajax({
        url: 'fetch_umrah_transactions.php',
        type: 'GET',
        data: { umrah_id: umrahId },
        dataType: 'json',
        success: function(response) {
            let transactions = typeof response === 'string' ? JSON.parse(response) : response;
            // Map transactions to the expected format
            transactions = transactions.map(transaction => ({
                id: transaction.id,
                created_at: transaction.payment_date + ' ' + transaction.payment_time,
                description: transaction.payment_description || '',
                type: 'debit',
                currency: transaction.payment_currency || 'USD',
                amount: transaction.payment_amount || 0,
                exchange_rate: transaction.exchange_rate
            }));
            
            try {
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

                // FIXED: Collect exchange rates from DB transactions with currency mapping
                let rates = {};
                transactions.forEach(tx => {
                    // Map DAR to DARHAM for consistency
                    const mappedCurrency = tx.currency === 'DAR' ? 'DARHAM' : tx.currency;
                    if (mappedCurrency !== baseCurrency && tx.exchange_rate) {
                        rates[mappedCurrency] = parseFloat(tx.exchange_rate);
                    }
                });

                // Track currencies present in transactions
                let hasCurrency = { USD: false, AFS: false, EUR: false, DARHAM: false };

                // Render transactions table
                transactions.forEach(tx => {
                    const currency = tx.currency;
                    const amount = parseFloat(tx.amount);
                    const exchangeRate = tx.exchange_rate ? parseFloat(tx.exchange_rate) : null;

                    // FIXED: Map DAR to DARHAM for consistency
                    const mappedCurrency = currency === 'DAR' ? 'DARHAM' : currency;
                    if (mappedCurrency in hasCurrency) hasCurrency[mappedCurrency] = true;

                    tbody.append(`
                        <tr>
                            <td>${tx.created_at}</td>
                            <td>${tx.description || ''}</td>
                            <td>${tx.type === 'credit' ? 'Received' : 'Paid'}</td>
                            <td>${currency} ${amount.toFixed(2)}</td>
                            <td>${exchangeRate || 'N/A'}</td>
                            <td class="text-center">
                                <button class="btn btn-primary btn-sm" onclick="editTransaction(${tx.id})">
                                    <i class="feather icon-edit"></i>
                                </button>
                                <button class="btn btn-danger btn-sm" onclick="deleteTransaction(${tx.id})">
                                    <i class="feather icon-trash-2"></i>
                                </button>
                            </td>
                        </tr>
                    `);
                });

                // Display exchange rates
                const exchangeText = Object.entries(rates).map(([cur,val]) => `${cur}: ${val}`).join(', ');
                $('#exchangeRateDisplay').text(exchangeText || 'No exchange rates found');

                // Calculate total paid in base currency (using the WORKING logic from tickets)
                let totalPaidBase = 0;
                transactions.forEach(tx => {
                    const amount = parseFloat(tx.amount);
                    // Map DAR to DARHAM for consistency
                    const currency = tx.currency === 'DAR' ? 'DARHAM' : tx.currency;

                    if (currency === baseCurrency) {
                        totalPaidBase += amount;
                    } else if (rates[currency]) {
                        // Convert foreign currency to base currency (SAME AS TICKET CODE)
                        if (baseCurrency === 'AFS') totalPaidBase += amount * rates[currency];
                        else totalPaidBase += amount / rates[currency];
                    }
                });

                const remainingBase = Math.max(0, totalAmount - totalPaidBase);

                // Display paid and remaining amounts for each currency (SAME AS TICKET CODE)
                ['USD','AFS','EUR','DARHAM'].forEach(cur => {
                    if (hasCurrency[cur]) {
                        // FIXED: Filter transactions with currency mapping
                        const paid = transactions.filter(t => {
                            const mappedCurrency = t.currency === 'DAR' ? 'DARHAM' : t.currency;
                            return mappedCurrency === cur;
                        }).reduce((a,b) => a + parseFloat(b.amount), 0);
                        
                        $(`#paidAmount${cur==='DARHAM'?'AED':cur}`).text(`${cur==='DARHAM'?'AED':cur} ${paid.toFixed(2)}`);

                        let remaining = 0;
                        if (cur === baseCurrency) {
                            remaining = remainingBase;
                        } else if (rates[cur]) {
                            // Convert base currency remaining to foreign (SAME AS TICKET CODE)
                            if (baseCurrency === 'AFS') remaining = remainingBase / rates[cur];
                            else remaining = remainingBase * rates[cur];
                        } else {
                            remaining = 'N/A';
                        }

                        $(`#remainingAmount${cur==='DARHAM'?'AED':cur}`).text(`${cur==='DARHAM'?'AED':cur} ${typeof remaining==='number'?remaining.toFixed(2):remaining}`);
                    }
                });

                // Display exchanged amounts (SAME AS TICKET CODE)
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
}

function deleteTransaction(transactionId) {
    if (!confirm('are_you_sure_you_want_to_delete_this_transaction')) {
        return;
    }
    
    const deleteBtn = event.target.closest('button');
    const originalHtml = deleteBtn.innerHTML;
    deleteBtn.disabled = true;
    deleteBtn.innerHTML = '<i class="feather icon-loader"></i>';
    
    // Get umrah ID from the modal
    const umrahId = document.getElementById('transactionUmrahId').textContent;
    
    // Send delete transaction request with JSON format
    fetch('delete_umrah_transaction.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ 
            transaction_id: transactionId,
            umrah_id: umrahId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('transaction_deleted_successfully');
            const soldAmount = parseFloat(document.getElementById('totalAmount').textContent.replace(/[^\d.]/g, ''));
            loadTransactionHistory(umrahId);
        } else {
            alert('error_deleting_transaction: ' + (data.message || 'unknown_error'));
            deleteBtn.disabled = false;
            deleteBtn.innerHTML = originalHtml;
        }
    })
    .catch(error => {
        console.error('Error deleting transaction:', error);
        alert('an_error_occurred_while_deleting_the_transaction');
        deleteBtn.disabled = false;
        deleteBtn.innerHTML = originalHtml;
    });
}


$(document).ready(function() {
    // Form submission handler
    $('#umrahTransactionForm').off('submit').on('submit', function(e) {
        e.preventDefault();

        const submitBtn = $(this).find('button[type="submit"]');
        const originalHtml = submitBtn.html();
        submitBtn.prop('disabled', true);
        submitBtn.html('<i class="feather icon-loader"></i> adding...');

        const formData = new FormData(this);
        const umrahId = $('#transactionUmrahIdInput').val();

        // Ensure payment_currency is included if missing
        if (!formData.has('payment_currency')) {
            formData.append('payment_currency', $('#paymentCurrency').val() || 'USD');
        }

        // Get exchange rate and append to description if currencies differ
        const currency = formData.get('payment_currency') || $('#paymentCurrency').val() || 'USD';
        const bookingCurrency = window.bookingCurrency || 'USD';
        const exchangeRate = parseFloat(formData.get('exchange_rate') || $('#transactionExchangeRate').val() || 1);

        if (currency !== bookingCurrency && exchangeRate > 0) {
            let description = formData.get('payment_description') || '';
            if (description && !description.includes('Exchange Rate:')) {
                description += ` (Exchange Rate: ${exchangeRate.toFixed(2)})`;
                formData.set('payment_description', description);
            } else if (!description) {
                formData.set('payment_description', `(Exchange Rate: ${exchangeRate.toFixed(2)})`);
            }
        }

        $.ajax({
            url: 'add_umrah_transaction.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                submitBtn.prop('disabled', false);
                submitBtn.html(originalHtml);

                try {
                    const result = typeof response === 'string' ? JSON.parse(response) : response;
                    if (result.success) {
                        alert('transaction_added_successfully');

                        // Refresh transaction list
                        const soldAmount = parseFloat($('#totalAmount').text().replace(/[^\d.]/g, '')) || 0;
                        loadTransactionHistory(umrahId);

                        // Reset form
                        $('#umrahTransactionForm')[0].reset();
                        $('#paymentCurrency').val('');
                        $('#transaction_to').val('Internal Account');
                        $('#receiptNumberField').hide();

                    } else {
                        alert('error: ' + (result.message || 'failed_to_add_transaction'));
                    }
                } catch (e) {
                    console.error('Error processing response:', e, response);
                    alert('error_processing_the_request');
                }
            },
            error: function(xhr, status, error) {
                submitBtn.prop('disabled', false);
                submitBtn.html(originalHtml);

                console.error('AJAX Error:', error);
                console.error('Response:', xhr.responseText);
                alert('error_adding_transaction');
            }
        });
    });
    

    // Show/Hide receipt number field
    $('#transaction_to').on('change', function() {
        if ($(this).val() === 'Bank') {
            $('#receiptNumberField').slideDown();
        } else {
            $('#receiptNumberField').slideUp();
        }
    });

// Show/Hide exchange rate field based on currency difference
$('#paymentCurrency').on('change', function() {
    const selectedCurrency = $(this).val();
    const bookingCurrency = window.bookingCurrency || 'USD';
    const exchangeRateField = $('#transactionExchangeRate').closest('.form-group');

    if (selectedCurrency && selectedCurrency !== bookingCurrency) {
        // Show exchange rate field and make it required
        exchangeRateField.slideDown();
        $('#transactionExchangeRate').attr('required', true);
        // Add visual indicator
        if (!exchangeRateField.find('.text-warning').length) {
            exchangeRateField.find('label').after('<small class="text-warning d-block">Exchange rate required for currency conversion</small>');
        }
    } else {
        // Hide exchange rate field and remove required
        exchangeRateField.slideUp();
        $('#transactionExchangeRate').removeAttr('required').val('');
        // Remove visual indicator
        exchangeRateField.find('.text-warning').remove();
    }
});

    // Set today's date by default
    const today = new Date().toISOString().split('T')[0];
    $('#paymentDate').val(today);

    // Always reset the add transaction button and form fields when the form is fully shown
    $('#addTransactionForm').on('shown.bs.collapse', function() {
        var submitBtn = $('#umrahTransactionForm').find('button[type="submit"]');
        submitBtn.prop('disabled', false);
        submitBtn.html('<i class="feather icon-check mr-1"></i>' + (typeof add_transaction_label !== 'undefined' ? add_transaction_label : 'Add Transaction'));
        $('#umrahTransactionForm')[0].reset();
        $('#paymentCurrency').val(''); // Clear currency selection
        $('#transaction_to').val('Internal Account');
        $('#receiptNumberField').hide();

        // Reset exchange rate field
        const exchangeRateField = $('#transactionExchangeRate').closest('.form-group');
        exchangeRateField.hide();
        $('#transactionExchangeRate').removeAttr('required').val('');
        exchangeRateField.find('.text-warning').remove();

        // Set today's date
        const today = new Date().toISOString().split('T')[0];
        $('#paymentDate').val(today);

        // Apply the exchange rate field logic based on the currency
        $('#paymentCurrency').trigger('change');
    
        const soldAmount = parseFloat($('#totalAmount').text().replace(/[^\d.]/g, '')) || 0;
        const umrahId = $('#transactionUmrahIdInput').val();
        if (umrahId) {
            loadTransactionHistory(umrahId);
        }
    });
});

// Function to edit transaction
function editTransaction(transactionId) {
    console.log('Editing transaction:', transactionId);
    
    // Fetch the transaction details
    fetch(`fetch_umrah_transactions.php?transaction_id=${transactionId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.text();
        })
        .then(rawText => {
            console.log('Raw response for edit:', rawText);
            
            try {
                return JSON.parse(rawText);
            } catch (e) {
                console.error('JSON parsing error:', e);
                throw new Error('Invalid JSON response from server');
            }
        })
        .then(data => {
            if (!data.success || !data.transaction) {
                throw new Error(data.message || 'Failed to fetch transaction details');
            }
            
            const transaction = data.transaction;
            console.log('Transaction to edit:', transaction);
            
            // Format date and time for form fields
            const paymentDate = transaction.payment_date_only || transaction.payment_date || '';
            const paymentTime = transaction.payment_time || '';
            const paymentAmount = parseFloat(transaction.payment_amount || transaction.amount || 0).toFixed(2);
            const paymentCurrency = transaction.payment_currency || transaction.currency || 'USD';
            const paymentDescription = transaction.payment_description || transaction.description || '';
            const transactionTo = transaction.transaction_to || 'Internal Account';
            const umrahId = transaction.umrah_booking_id || transaction.umrah_id || $('#transactionUmrahId').text();
            
            // Populate edit form fields
            $('#editTransactionId').val(transactionId);
            $('#editUmrahId').val(umrahId);
            $('#originalAmount').val(paymentAmount);
            $('#editPaymentDate').val(paymentDate);
            $('#editPaymentTime').val(paymentTime);
            $('#editPaymentAmount').val(paymentAmount);
            $('#editPaymentCurrency').val(paymentCurrency);
            $('#editPaymentDescription').val(paymentDescription);
            $('#editTransactionTo').val(transactionTo);

            // Populate exchange rate field
            const exchangeRate = parseFloat(transaction.exchange_rate) || 1;
            $('#editExchangeRate').val(exchangeRate.toFixed(2));

            // Show/hide exchange rate field based on currency difference
            const bookingCurrency = window.bookingCurrency || 'USD';
            const exchangeRateField = $('#editExchangeRate').closest('.form-group');

            if (paymentCurrency && paymentCurrency !== bookingCurrency) {
                exchangeRateField.show();
                $('#editExchangeRate').attr('required', true);
            } else {
                exchangeRateField.hide();
                $('#editExchangeRate').removeAttr('required');
            }

            // Show the edit modal
            $('#editTransactionModal').modal('show');

            // Trigger currency change event to show/hide exchange rate field
            $('#editPaymentCurrency').trigger('change');
        })
        .catch(error => {
            console.error('Error fetching transaction details:', error);
            alert('error_fetching_transaction_details: ' + error.message);
        });
}

// Function to fetch transactions (alias for loadTransactionHistory)
function fetchTransactions(umrahId) {
    loadTransactionHistory(umrahId);
}

// Function to update transaction table with edit buttons
function updateTransactionTableRows() {
    // Find all delete buttons in the transaction table
    const deleteButtons = $('#transactionTableBody button[onclick^="deleteTransaction"]');

    // For each delete button, add an edit button before it
    deleteButtons.each(function() {
        const deleteBtn = $(this);
        const row = deleteBtn.closest('tr');
        const transactionId = deleteBtn.attr('onclick').match(/deleteTransaction\((\d+)\)/)[1];

        // Check if edit button already exists
        if (row.find('.edit-transaction-btn').length === 0) {
            // Create edit button
            const editBtn = $(`
                <button class="btn btn-primary btn-sm mr-1 edit-transaction-btn" title="<?= __('edit_transaction') ?>"
                        onclick="editTransaction(${transactionId})">
                    <i class="feather icon-edit"></i>
                </button>
            `);

            // Insert edit button before delete button
            deleteBtn.before(editBtn);
        }
    });
}

// Find the existing transaction form and add time field after the date field
$(document).ready(function() {
    // Add time field after date field in the transaction form
    const dateField = $('#transactionForm').find('input[name="payment_date"]');
    if (dateField.length) {
        dateField.parent().after(`
            <div class="form-group">
                <label for="paymentTime"><?= __('payment_time') ?></label>
                <input type="time" class="form-control" id="paymentTime" name="payment_time" required>
            </div>
        `);
        
        // Set current time as default
        const now = new Date();
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        $('#paymentTime').val(`${hours}:${minutes}`);
    }
    
    // Add event handler for edit transaction form submission
    $('#editTransactionForm').off('submit').on('submit', function(e) {
        e.preventDefault();
        
        const submitBtn = $(this).find('button[type="submit"]');
        const originalHtml = submitBtn.html();
        submitBtn.prop('disabled', true);
        submitBtn.html('<i class="feather icon-loader"></i> saving...');
        
        const formData = new FormData(this);
        
        // Make sure umrah_id is set correctly
        if (!formData.get('umrah_id') || formData.get('umrah_id') === '') {
            const umrahId = $('#transactionUmrahId').text();
            formData.set('umrah_id', umrahId);
        }
        
        // Log the form data for debugging
        console.log('Form data for update:');
        for (let pair of formData.entries()) {
            console.log(pair[0], pair[1]);
        }
        
        $.ajax({
            url: 'update_umrah_payment.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                submitBtn.prop('disabled', false);
                submitBtn.html(originalHtml);
                
                try {
                    const result = typeof response === 'string' ? JSON.parse(response) : response;
                    console.log('Update response:', result);
                    
                    if (result.success) {
                        alert('transaction_updated_successfully');
                        $('#editTransactionModal').modal('hide');
                        
                        // Refresh transaction list
                        const umrahId = $('#transactionUmrahId').text();
                        const soldAmount = parseFloat($('#totalAmount').text().replace(/[^\d.]/g, '')) || 0;
                        loadTransactionHistory(umrahId);
                    } else {
                        alert('error_updating_transaction: ' + (result.message || 'unknown_error'));
                    }
                } catch (e) {
                    console.error('Error processing response:', e, response);
                    alert('error_processing_the_server_response');
                }
            },
            error: function(xhr, status, error) {
                submitBtn.prop('disabled', false);
                submitBtn.html(originalHtml);
                
                console.error('AJAX Error:', error);
                console.error('Response:', xhr.responseText);
                alert('error_updating_transaction');
            }
        });
    });
    
    // Add edit button to transaction rows
    updateTransactionTableRows();

    // Show/Hide exchange rate field in edit form based on currency difference
    $('#editPaymentCurrency').on('change', function() {
        const selectedCurrency = $(this).val();
        const bookingCurrency = window.bookingCurrency || 'USD';
        const exchangeRateField = $('#editExchangeRate').closest('.form-group');

        if (selectedCurrency && selectedCurrency !== bookingCurrency) {
            exchangeRateField.slideDown();
            $('#editExchangeRate').attr('required', true);
        } else {
            exchangeRateField.slideUp();
            $('#editExchangeRate').removeAttr('required');
        }
    });
});