// Transaction Management
const transactionManager = {
    // Initialize transaction modal and form handlers
    init: function() {
        this.bindEvents();
        this.setDefaultDateTime();
    },
    
    // Show toast notification
    showToast: function(message, type) {
        if (typeof Swal !== 'undefined') {
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                didOpen: (toast) => {
                    toast.addEventListener('mouseenter', Swal.stopTimer)
                    toast.addEventListener('mouseleave', Swal.resumeTimer)
                }
            });
            
            Toast.fire({
                icon: type,
                title: message
            });
        } else {
            // Fallback to alert if SweetAlert2 is not available
            alert(message);
        }
    },

    // Bind all event listeners
    bindEvents: function() {
        $('#hotelTransactionForm').on('submit', this.handleTransactionSubmit);
        $('#editTransactionForm').on('submit', this.handleEditTransactionSubmit);
        $('#paymentCurrency').on('change', this.toggleExchangeRateField.bind(this));
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

    loadTransactionModal: function(ticketId) {
        if (!ticketId) {
            console.error('No ticket ID provided');
            return;
        }
    
        $.ajax({
            url: 'get_refund_ticket_bookings.php',
            type: 'GET',
            data: { id: ticketId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const booking = response.booking;
    
                    $('#trans-guest-name').text(`${booking.title} ${booking.passenger_name}`);
                    $('#trans-order-id').text(booking.pnr);
    
                    const currency = booking.currency || 'USD';
                    const refundAmount = parseFloat(booking.refund_to_passenger) || 0;

                    $('#totalAmount').text(`${currency} ${refundAmount.toFixed(2)}`);
                    $('#exchangeRateDisplay').text('Loading...');
                    $('#exchangedAmount').text('Loading...');

                    // Store ticket currency for exchange rate logic
                    window.ticketCurrency = currency;

                    $('#booking_id').val(ticketId);
    
                    // Load refund transaction history with multi-currency support
                    transactionManager.loadRefundTransactionHistory(ticketId);
    
                    $('#transactionsModal').modal('show');
                } else {
                    alert('Error fetching booking details: ' + (response.message || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                alert('Error fetching booking details');
            }
        });
    },
    

 loadRefundTransactionHistory: function(ticketId) {
    const totalAmountText = $('#totalAmount').text();
    const totalCurrency = totalAmountText.split(' ')[0];
    const totalAmount = parseFloat(totalAmountText.split(' ')[1]) || 0;

    $.ajax({
        url: 'get_refund_ticket_transactions.php',
        type: 'GET',
        data: { ticket_id: ticketId },
        dataType: 'json',
        success: function(transactions) {
            const tbody = $('#transactionTableBody');
            tbody.empty();

            if (!Array.isArray(transactions) || transactions.length === 0) {
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
            transactions.forEach(tx => {
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



    // Edit transaction function
    editTransaction: function(transactionId) {
        const ticketId = $('#booking_id').val();
        
        // Fetch transaction details
        $.ajax({
            url: 'get_refund_transaction_details.php',
            type: 'GET',
            data: { transaction_id: transactionId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const tx = response.transaction;
                    
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
                    $('#editTicketId').val(ticketId);
                    $('#originalAmount').val(tx.amount);
                    $('#editPaymentDate').val(formattedDate);
                    $('#editPaymentTime').val(formattedTime);
                    $('#editPaymentAmount').val(tx.amount);
                    $('#editPaymentDescription').val(tx.description);
                    $('#editExchangeRate').val(tx.exchange_rate || '');

                    // Show exchange rate field if there's an exchange rate
                    if (tx.exchange_rate) {
                        $('#editExchangeRateField').show();
                    } else {
                        $('#editExchangeRateField').hide();
                    }
                    
                    // Show edit modal
                    $('#editTransactionModal').modal('show');
                } else {
                    transactionManager.showToast('Error fetching transaction details: ' + (response.message || 'Unknown error'), 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                transactionManager.showToast('Error fetching transaction details', 'error');
            }
        });
    },

    // Handle edit transaction form submission
    handleEditTransactionSubmit: function(e) {
        e.preventDefault();
        
        const form = $(this);
        const formData = new FormData(form[0]);
        const ticketId = formData.get('ticket_id');
        
        // Combine date and time into a single datetime value
        const date = formData.get('payment_date');
        const time = formData.get('payment_time') || '00:00:00';
        if (date) {
            formData.set('payment_date', `${date} ${time}`);
        }
        
       $.ajax({
    url: 'update_refund_transaction.php',
    type: 'POST',
    data: formData,
    processData: false,
    contentType: false,
    dataType: 'json', // ✅ Expect JSON response
    success: function(result) {
    if (result.success === true) {
        $('#editTransactionModal').modal('hide');
        transactionManager.loadRefundTransactionHistory(ticketId); // ✅ correct
        transactionManager.showToast('Transaction updated successfully', 'success');

        // Reset exchange rate field
        $('#editExchangeRate').val('');
        $('#editExchangeRateField').hide();
    } else {
        transactionManager.showToast('Error updating transaction: ' + (result.message || 'Unknown error'), 'error');
    }
},
    error: function(xhr, status, error) {
        console.error('AJAX Error:', error);
        transactionManager.showToast('Error updating transaction', 'error');
    }
});

    },

    // Handle transaction form submission (add new transaction)
    handleTransactionSubmit: function(e) {
        e.preventDefault();

        const form = $(this);
        const formData = new FormData(form[0]);
        const ticketId = $('#booking_id').val();

        // Combine date and time into a single datetime value
        const date = formData.get('payment_date');
        const time = formData.get('payment_time') || '00:00:00';
        if (date) {
            formData.set('payment_date', `${date} ${time}`);
        }

        $.ajax({
            url: 'add_refund_ticket_payment.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(result) {
                if (result.success === true) {
                    // Reset form
                    form[0].reset();
                    // Set default date/time again
                    transactionManager.setDefaultDateTime();
                    // Reload transaction history
                    transactionManager.loadRefundTransactionHistory(ticketId);
                    transactionManager.showToast('Transaction added successfully', 'success');
                } else {
                    transactionManager.showToast('Error adding transaction: ' + (result.message || 'Unknown error'), 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                transactionManager.showToast('Error adding transaction', 'error');
            }
        });
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
    },

    // Delete transaction function
    deleteTransaction: function(transactionId) {
        const ticketId = $('#booking_id').val();
        const transactionRow = $(`button[onclick="transactionManager.deleteTransaction(${transactionId})"]`).closest('tr');
        const amountText = transactionRow.find('td:nth-child(4)').text().trim();
        const amount = parseFloat(amountText.split(' ')[1]);
        
        // Use SweetAlert2 for confirmation if available
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Are you sure?',
                text: 'You are about to delete this transaction. This action cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    this.performDeleteTransaction(transactionId, ticketId, amount);
                }
            });
        } else {
            // Fallback to standard confirm
            if (confirm('Are you sure you want to delete this transaction?')) {
                this.performDeleteTransaction(transactionId, ticketId, amount);
            }
        }
    },
    
    // Perform the actual transaction deletion
    performDeleteTransaction: function(transactionId, ticketId, amount) {

       $.ajax({
    url: 'delete_refund_ticket_transaction.php',
    type: 'POST',
    data: {
        transaction_id: transactionId,
        ticket_id: ticketId,
        amount: amount
    },
    dataType: 'json', // 👈 ensures automatic JSON parsing
    success: function(result) {
        if (result.success) {
            transactionManager.loadRefundTransactionHistory(ticketId);
            transactionManager.showToast('Transaction deleted successfully', 'success');
        } else {
            transactionManager.showToast('Error deleting transaction: ' + (result.message || 'Unknown error'), 'error');
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

    },

    // View receipt function
    viewReceipt: function(receipt) {
        if (receipt) {
            window.open(`../uploads/receipts/${receipt}`, '_blank');
        }
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

// Add click handler for the update transaction button
$(document).ready(function() {
    $('#updateTransactionBtn').on('click', function() {
        // Manually trigger the form submission
        $('#editTransactionForm').submit();
    });
});
