// Transaction Management
const transactionManager = {
    // Initialize transaction modal and form handlers
    init: function() {
        this.bindEvents();
        this.setDefaultDateTime();
        this.initializeToastContainer();
    },

    // Initialize toast container
    initializeToastContainer: function() {
        if (!$('#toast-container').length) {
            $('body').append(`
                <div id="toast-container" style="position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px;"></div>
            `);
        }
    },

    // Show toast notification
    showToast: function(message, type = 'success', duration = 5000) {
        const toastId = 'toast_' + Date.now();
        const iconClass = type === 'success' ? 'feather icon-check-circle' : 
                         type === 'error' ? 'feather icon-x-circle' : 
                         type === 'warning' ? 'feather icon-alert-triangle' : 
                         'feather icon-info';
        
        const bgClass = type === 'success' ? 'bg-success' : 
                       type === 'error' ? 'bg-danger' : 
                       type === 'warning' ? 'bg-warning' : 
                       'bg-info';

        const toast = `
            <div id="${toastId}" class="toast align-items-center text-white ${bgClass} border-0 mb-2" role="alert" 
                 style="opacity: 0; transform: translateX(100%); transition: all 0.3s ease;">
                <div class="d-flex">
                    <div class="toast-body d-flex align-items-center">
                        <i class="${iconClass} mr-2"></i>
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" 
                            onclick="transactionManager.closeToast('${toastId}')">
                        <i class="feather icon-x"></i>
                    </button>
                </div>
            </div>
        `;

        $('#toast-container').append(toast);
        
        // Animate in
        setTimeout(() => {
            $(`#${toastId}`).css({
                opacity: '1',
                transform: 'translateX(0)'
            });
        }, 100);

        // Auto remove after duration
        if (duration > 0) {
            setTimeout(() => {
                this.closeToast(toastId);
            }, duration);
        }
    },

    // Close toast
    closeToast: function(toastId) {
        $(`#${toastId}`).css({
            opacity: '0',
            transform: 'translateX(100%)'
        });
        
        setTimeout(() => {
            $(`#${toastId}`).remove();
        }, 300);
    },

    // Bind all event listeners
    bindEvents: function() {
        $('#dateChangeTransactionForm').on('submit', this.handleTransactionSubmit);
        $('#editTransactionForm').on('submit', this.handleEditTransactionSubmit);
        $('#paymentCurrency').on('change', this.toggleExchangeRateField.bind(this));
        $('#editPaymentCurrency').on('change', this.toggleEditExchangeRateField.bind(this));
    },

    // Set today's date and current time as default
    setDefaultDateTime: function() {
        const now = new Date();
        const today = now.toISOString().split('T')[0];
        $('#paymentDate').val(today);
        
        // Format time as HH:MM:SS with seconds
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        $('#paymentTime').val(`${hours}:${minutes}:${seconds}`);
    },

    // Reset form fields
    resetForm: function() {
        $('#dateChangeTransactionForm')[0].reset();
        this.setDefaultDateTime();
        // Reset exchange rate field
        $('#exchangeRateField').hide();
        $('#transactionExchangeRate').attr('required', false);
        $('#transactionExchangeRate').val('');
    },

    // Toggle exchange rate field based on currency selection
    toggleExchangeRateField: function() {
        const selectedCurrency = $('#paymentCurrency').val();
        if (selectedCurrency && window.ticketCurrency && selectedCurrency !== window.ticketCurrency) {
            $('#exchangeRateField').show();
            $('#transactionExchangeRate').attr('required', true);
        } else {
            $('#exchangeRateField').hide();
            $('#transactionExchangeRate').attr('required', false);
            $('#transactionExchangeRate').val(''); // Clear value when hidden
        }
    },

    // Toggle exchange rate field for edit modal based on currency selection
    toggleEditExchangeRateField: function() {
        const selectedCurrency = $('#editPaymentCurrency').val();
        if (selectedCurrency && window.ticketCurrency && selectedCurrency !== window.ticketCurrency) {
            $('#editExchangeRateField').show();
            $('#editTransactionExchangeRate').attr('required', true);
        } else {
            $('#editExchangeRateField').hide();
            $('#editTransactionExchangeRate').attr('required', false);
            $('#editTransactionExchangeRate').val(''); // Clear value when hidden
        }
    },

    // Load transaction modal with ticket data
    loadTransactionModal: function(ticketId) {
        if (!ticketId) {
            console.error('no_ticket_id_provided');
            this.showToast('No ticket ID provided', 'error');
            return;
        }
        
        console.log('Loading transactions for ticket ID:', ticketId);
        
        // Store ticket ID in the form
        $('#booking_id').val(ticketId);
        $('#editBookingId').val(ticketId);
        
        // Reset form fields
        this.resetForm();
        
        // Load ticket details and transaction history
        $.ajax({
            url: 'get_date_change_ticket_bookings.php',
            type: 'GET',
            data: { id: ticketId },
            dataType: 'json',
            success: (response) => {
                // Handle the response structure properly
                let ticketData;
                if (response.success && response.ticket) {
                    ticketData = response.ticket;
                } else if (response.passenger_name) {
                    // Direct ticket data
                    ticketData = response;
                } else {
                    this.showToast('Invalid response format', 'error');
                    return;
                }

                // Display ticket details
                $('#trans-passenger-name').text(ticketData.passenger_name || 'N/A');
                $('#trans-pnr').text(ticketData.pnr || 'N/A');
                $('#trans-departure-date').text(ticketData.departure_date || 'N/A');
                
                // Display financial information
                const currency = ticketData.currency || 'USD';
                const soldAmount = parseFloat(ticketData.sold) || 0;
                const exchangeRate = parseFloat(ticketData.exchange_rate) || 1;

                // Store ticket currency for exchange rate logic
                window.ticketCurrency = currency;

                // Display original amount
                $('#totalAmount').text(`${currency} ${soldAmount.toFixed(2)}`);

                // Display exchange rate
                $('#exchangeRateDisplay').text(exchangeRate.toFixed(4));

                // Exchanged amount will be calculated after loading transactions
                // This ensures we use actual exchange rates from transaction data
                $('#exchangedAmount').text('Calculating...');
                
                // Load transaction history - pass the transactions if they exist in response
                if (response.transactions) {
                    this.loadTransactionHistory(ticketId, response.transactions);
                } else {
                    this.loadTransactionHistory(ticketId);
                }
            },
            error: (xhr, status, error) => {
                console.error('Error loading ticket details:', error);
                this.showToast('Failed to load ticket details. Please try again.', 'error');
            }
        });
        
        // Show the modal
        $('#transactionsModal').modal('show');
    },
    
    loadTransactionHistory: function(ticketId) {
        const totalAmountText = $('#totalAmount').text();
        const totalCurrency = totalAmountText.split(' ')[0];
        const totalAmount = parseFloat(totalAmountText.split(' ')[1]) || 0;
    
        $.ajax({
            url: 'get_date_change_ticket_transactions.php',
            type: 'GET',
            data: { ticket_id: ticketId },
            dataType: 'json',
            success: function(transactions) {
                const tbody = $('#transactionTableBody');
                tbody.empty();
    
                let txArray;
                if (Array.isArray(transactions)) {
                    txArray = transactions;
                } else if (transactions.success && transactions.transactions) {
                    txArray = transactions.transactions;
                } else {
                    txArray = [];
                }
    
                if (!Array.isArray(txArray) || txArray.length === 0) {
                    tbody.html('<tr><td colspan="6" class="text-center">no_transactions_found</td></tr>');
                    $('#exchangeRateDisplay').text('No exchange rates found');
                    $('#exchangedAmount').text('No conversions available');
                    return;
                }
    
                // Initialize totals
                let totals = { USD: 0, AFS: 0, EUR: 0, DARHAM: 0 };
                let hasCurrency = { USD: false, AFS: false, EUR: false, DARHAM: false };
                let rates = {}; // DB-provided exchange rates
    
                // Build table rows and gather rates
                txArray.forEach(tx => {
                    const amount = parseFloat(tx.amount);
                    const currency = tx.currency || 'USD';
                    const exchangeRate = tx.exchange_rate ? parseFloat(tx.exchange_rate) : null;
                    hasCurrency[currency] = true;
                    totals[currency] += amount;

                    // Collect exchange rates for display
                    if (exchangeRate && currency !== totalCurrency) {
                        rates[currency] = exchangeRate;
                    }

                    const cleanDescription = (tx.description || '').replace(/ \(Exchange Rate: [0-9.]+\)/, '');
                    tbody.append(`
                        <tr>
                            <td>${transactionManager.formatDate(tx.created_at)}</td>
                            <td>${cleanDescription}</td>
                            <td>${tx.type === 'credit' ? 'Received' : 'Paid'}</td>
                            <td>${currency} ${amount.toFixed(2)}</td>
                            <td>${exchangeRate !== null ? exchangeRate : 'N/A'}</td>
                            <td class="text-center">
                                <button class="btn btn-primary btn-sm mr-1" title="Edit Transaction"
                                    onclick="transactionManager.editTransaction(${tx.id}, '${cleanDescription.replace(/'/g,"\\'")}', ${amount}, '${tx.created_at}')">
                                    <i class="feather icon-edit"></i>
                                </button>
                                <button class="btn btn-danger btn-sm" title="Delete Transaction"
                                    onclick="transactionManager.deleteTransaction(${tx.id})">
                                    <i class="feather icon-trash-2"></i>
                                </button>
                            </td>
                        </tr>
                    `);
                });
    
                // Display exchange rates
                const exchangeText = Object.entries(rates).map(([cur, val]) => `${cur}: ${val}`).join(', ');
                $('#exchangeRateDisplay').text(exchangeText || 'No exchange rates found');
    
                // Calculate remaining amounts dynamically
                const convertToBase = (amount, currency) => {
                    if (currency === totalCurrency) return amount;
                    if (!rates[currency]) return 0;
                    // Convert foreign to base currency
                    if (totalCurrency === 'USD') return amount / rates[currency];
                    if (totalCurrency === 'AFS') return amount * rates[currency];
                    if (totalCurrency === 'EUR') return amount / rates[currency];
                    if (totalCurrency === 'DARHAM') return amount / rates[currency];
                    return amount;
                };
    
                const totalPaidInBase = Object.keys(totals).reduce((sum, cur) => sum + convertToBase(totals[cur], cur), 0);
                const remainingBase = Math.max(0, totalAmount - totalPaidInBase);
    
                // Update paid and remaining for each currency
                ['USD','AFS','EUR','DARHAM'].forEach(cur => {
                    if (hasCurrency[cur]) {
                        $(`#paidAmount${cur==='DARHAM'?'AED':cur}`).text(`${cur==='DARHAM'?'AED':cur} ${totals[cur].toFixed(2)}`);
    
                        let remaining = remainingBase;
                        if (cur !== totalCurrency && rates[cur]) {
                            if (totalCurrency === 'USD') remaining *= rates[cur];
                            if (totalCurrency === 'AFS') remaining /= rates[cur];
                            if (totalCurrency === 'EUR') remaining *= rates[cur];
                            if (totalCurrency === 'DARHAM') remaining *= rates[cur];
                        } else if (cur !== totalCurrency && !rates[cur]) {
                            remaining = 'N/A';
                        }
                        $(`#remainingAmount${cur==='DARHAM'?'AED':cur}`).text(`${cur==='DARHAM'?'AED':cur} ${typeof remaining==='number'?remaining.toFixed(2):remaining}`);
                    }
                });
    
                // Display exchanged amounts
                let exchangedAmounts = [`${totalCurrency} ${totalAmount.toFixed(2)}`];
                Object.keys(rates).forEach(cur => {
                    let val = totalAmount;
                    if (totalCurrency === 'USD') val *= rates[cur];
                    else if (totalCurrency === 'AFS') val /= rates[cur];
                    else if (totalCurrency === 'EUR') val *= rates[cur];
                    else if (totalCurrency === 'DARHAM') val *= rates[cur];
                    exchangedAmounts.push(`${cur} ${val.toFixed(2)}`);
                });
                $('#exchangedAmount').text(exchangedAmounts.join(', '));
    
                // Show/hide currency sections
                $('#usdSection').toggle(hasCurrency.USD);
                $('#afsSection').toggle(hasCurrency.AFS);
                $('#eurSection').toggle(hasCurrency.EUR);
                $('#aedSection').toggle(hasCurrency.DARHAM);
            },
            error: function(xhr, status, error) {
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
        
        const form = $(this);
        const formData = new FormData(form[0]);
        const ticketId = formData.get('booking_id');
        
        // Combine date and time into a single datetime value
        const date = formData.get('payment_date');
        const time = formData.get('payment_time') || '00:00:00';
        if (date) {
            formData.set('payment_date', `${date} ${time}`);
        }

        // Add exchange rate if field is visible
        if ($('#exchangeRateField').is(':visible')) {
            const exchangeRate = $('#transactionExchangeRate').val();
            if (exchangeRate) {
                formData.set('exchange_rate', exchangeRate);
            }
        }
        
        $.ajax({
            url: 'add_date_change_ticket_payment.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                try {
                    const result = typeof response === 'string' ? JSON.parse(response) : response;
                    
                    if (result.success) {
                        // Reset form and collapse it
                        transactionManager.resetForm();
                        $('#addTransactionForm').collapse('hide');

                        // Reload transaction history
                        transactionManager.loadTransactionHistory(ticketId);

                        // Show success toast
                        transactionManager.showToast('Transaction added successfully!', 'success');
                    } else {
                        transactionManager.showToast('Error: ' + (result.message || 'Failed to add transaction'), 'error');
                        console.error('Server response:', result);
                    }
                } catch (e) {
                    console.error('Error parsing response:', e);
                    console.error('Raw response:', response);
                    transactionManager.showToast('Error processing the request', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                console.error('Response:', xhr.responseText);
                transactionManager.showToast('Error adding transaction. Please try again.', 'error');
            }
        });
    },
    
    // Edit transaction
    editTransaction: function(transactionId) {
        console.log('Loading transaction for edit:', transactionId);

        $.ajax({
            url: 'get_date_change_transaction.php',
            type: 'GET',
            data: { id: transactionId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const tx = response.transaction;
                    console.log('Transaction data loaded:', tx);

                    // Parse the datetime string
                    const txDate = new Date(tx.transaction_date);
                    const formattedDate = txDate.toISOString().split('T')[0];

                    // Format time as HH:MM:SS
                    const hours = String(txDate.getHours()).padStart(2, '0');
                    const minutes = String(txDate.getMinutes()).padStart(2, '0');
                    const seconds = String(txDate.getSeconds()).padStart(2, '0');
                    const formattedTime = `${hours}:${minutes}:${seconds}`;

                    // Populate form fields
                    $('#editTransactionId').val(tx.id);
                    $('#editBookingId').val(tx.reference_id);
                    $('#originalAmount').val(tx.amount);
                    $('#editPaymentDate').val(formattedDate);
                    $('#editPaymentTime').val(formattedTime);
                    $('#editPaymentAmount').val(tx.amount);
                    $('#editPaymentCurrency').val(tx.currency || 'USD');
                    $('#editPaymentDescription').val(tx.description);

                    // Handle exchange rate - use transaction's exchange_rate field first, then fallback to description
                    if (tx.exchange_rate && parseFloat(tx.exchange_rate) > 0) {
                        // Use exchange rate from transaction data
                        $('#editTransactionExchangeRate').val(tx.exchange_rate);

                        // Show exchange rate field if currency differs from ticket currency
                        if (tx.currency && window.ticketCurrency && tx.currency !== window.ticketCurrency) {
                            $('#editExchangeRateField').show();
                            $('#editTransactionExchangeRate').attr('required', true);
                        }
                    } else {
                        // Fallback to parsing from description
                        const description = tx.description || '';
                        const exchangeRateMatch = description.match(/\(Exchange Rate: ([0-9.]+)\)/);
                        if (exchangeRateMatch) {
                            const exchangeRate = parseFloat(exchangeRateMatch[1]);
                            $('#editTransactionExchangeRate').val(exchangeRate);

                            // Show exchange rate field if currency differs from ticket currency
                            if (tx.currency && window.ticketCurrency && tx.currency !== window.ticketCurrency) {
                                $('#editExchangeRateField').show();
                                $('#editTransactionExchangeRate').attr('required', true);
                            }
                        } else {
                            // Hide exchange rate field if no exchange rate found
                            $('#editExchangeRateField').hide();
                            $('#editTransactionExchangeRate').attr('required', false);
                            $('#editTransactionExchangeRate').val('');
                        }
                    }

                    // Show the modal
                    $('#editTransactionModal').modal('show');
                } else {
                    transactionManager.showToast('Error: ' + (response.message || 'Failed to load transaction details'), 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading transaction details:', error);
                console.error('Response:', xhr.responseText);
                transactionManager.showToast('Failed to load transaction details. Please try again.', 'error');
            }
        });
    },
    
    // Handle edit transaction form submission
    handleEditTransactionSubmit: function(e) {
        e.preventDefault();

        const form = $(this);
        const formData = new FormData(form[0]);
        const ticketId = formData.get('booking_id');

        // Combine date and time into a single datetime value
        const date = formData.get('payment_date');
        const time = formData.get('payment_time') || '00:00:00';
        if (date) {
            formData.set('payment_date', `${date} ${time}`);
        }

        // Add exchange rate if field is visible
        if ($('#editExchangeRateField').is(':visible')) {
            const exchangeRate = $('#editTransactionExchangeRate').val();
            if (exchangeRate) {
                formData.set('exchange_rate', exchangeRate);
            }
        }

        $.ajax({
            url: 'update_date_change_transaction.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                try {
                    const result = typeof response === 'string' ? JSON.parse(response) : response;

                    if (result.success) {
                        // Close the modal
                        $('#editTransactionModal').modal('hide');

                        // Reload transaction history
                        transactionManager.loadTransactionHistory(ticketId);

                        // Show success toast
                        transactionManager.showToast('Transaction updated successfully!', 'success');
                    } else {
                        transactionManager.showToast('Error: ' + (result.message || 'Failed to update transaction'), 'error');
                        console.error('Server response:', result);
                    }
                } catch (e) {
                    console.error('Error parsing response:', e);
                    console.error('Raw response:', response);
                    transactionManager.showToast('Error processing the request', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                console.error('Response:', xhr.responseText);
                transactionManager.showToast('Error updating transaction. Please try again.', 'error');
            }
        });
    },
    
    // Delete transaction
    deleteTransaction: function(transactionId) {
        // Show custom confirmation toast instead of alert
        this.showConfirmationToast('Are you sure you want to delete this transaction?', () => {
            const ticketId = $('#booking_id').val();
            const transactionRow = $(`button[onclick="transactionManager.deleteTransaction(${transactionId})"]`).closest('tr');
            const amountText = transactionRow.find('td:nth-child(4)').text().trim();
            const amount = parseFloat(amountText.split(' ')[1]);

            $.ajax({
                url: 'delete_date_change_ticket_transaction.php',
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
                            transactionManager.showToast('Transaction deleted successfully!', 'success');
                        } else {
                            transactionManager.showToast('Error: ' + (result.message || 'Unknown error'), 'error');
                        }
                    } catch (e) {
                        console.error('Error parsing response:', e);
                        transactionManager.showToast('Error processing the request', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Delete Error Response:', {
                        status: xhr.status,
                        error: error,
                        response: xhr.responseText
                    });
                    transactionManager.showToast('Error deleting transaction', 'error');
                }
            });
        });
    },

    // Show confirmation toast
    showConfirmationToast: function(message, onConfirm, onCancel = null) {
        const toastId = 'confirm_toast_' + Date.now();

        const toast = `
            <div id="${toastId}" class="toast align-items-center text-white bg-warning border-0 mb-2" role="alert"
                 style="opacity: 0; transform: translateX(100%); transition: all 0.3s ease;">
                <div class="d-flex flex-column p-3">
                    <div class="mb-3">
                        <i class="feather icon-alert-triangle mr-2"></i>
                        ${message}
                    </div>
                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-light btn-sm mr-2"
                                onclick="transactionManager.closeToast('${toastId}'); ${onCancel ? 'onCancel()' : ''}">
                            Cancel
                        </button>
                        <button type="button" class="btn btn-danger btn-sm"
                                onclick="transactionManager.closeToast('${toastId}'); onConfirm()">
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        `;

        $('#toast-container').append(toast);

        // Make onConfirm available globally for the onclick
        window.onConfirm = onConfirm;
        if (onCancel) window.onCancel = onCancel;

        // Animate in
        setTimeout(() => {
            $(`#${toastId}`).css({
                opacity: '1',
                transform: 'translateX(0)'
            });
        }, 100);
    },

    // Format date function
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

// Global function to manage transactions (called from HTML)
function manageTransactions(ticketId) {
    transactionManager.loadTransactionModal(ticketId);
}