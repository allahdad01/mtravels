            // Transaction Manager for Additional Payments
            const transactionManager = {
                formatDate: function(dateString) {
                    const date = new Date(dateString);
                    return date.toLocaleDateString('en-US', {
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                },
    
                loadTransactionHistory: function(paymentId) {
                    const soldAmount = parseFloat($('#totalAmount').text().split(' ')[1]);
    
                    $.ajax({
                        url: '../api/additional_payment/get_transactions.php',
                        type: 'GET',
                        data: { payment_id: paymentId },
                        dataType: 'json',
                        success: function(response) {
                            try {
                                const transactions = response;
    
                                // Check if transactions is an array
                                if (!Array.isArray(transactions)) {

                                    $('#transactionsTableBody').html('<tr><td colspan="6" class="text-center">Error loading transactions</td></tr>');
                                    return;
                                }
    
                                const tbody = $('#transactionsTableBody');
                                tbody.empty();
    
                                let hasUSDTransactions = false;
                                let hasAFSTransactions = false;
                                let hasEURTransactions = false;
                                let hasDARHAMTransactions = false;
    
                                // Store exchange rates for calculations (will be updated from transactions)
                                let usdToAfsRate = 70; // Default AFS rate from user's data
                                let usdToEurRate = 0.9; // Default EUR rate from user's data
                                let usdToDarhamRate = 3.61; // Default DARHAM rate from user's data
    
                                // Collect exchange rates from transactions for display
                                let exchangeRatesDisplay = [];
    
                                transactions.forEach(transaction => {
                                    // Check which currencies have transactions
                                    switch (transaction.currency) {
                                        case 'USD':
                                            hasUSDTransactions = true;
                                            break;
                                        case 'AFS':
                                            hasAFSTransactions = true;
                                            break;
                                        case 'EUR':
                                            hasEURTransactions = true;
                                            break;
                                        case 'DARHAM':
                                            hasDARHAMTransactions = true;
                                            break;
                                    }

                                    // Ensure description is a string
                                    const description = String(transaction.description || '');

                                    // Use exchange_rate field directly from transaction
                                    let transactionExchangeRate = transaction.exchange_rate ? parseFloat(transaction.exchange_rate) : null;
                                    let exchangeRateDisplay = transactionExchangeRate ? transactionExchangeRate.toString() : 'N/A';

                                    if (transactionExchangeRate) {
                                        // Update exchange rates for calculations if this transaction has a rate
                                        if (transaction.currency === 'AFS') {
                                            usdToAfsRate = transactionExchangeRate;

                                            // Add to display list if not already present
                                            if (!exchangeRatesDisplay.find(rate => rate.currency === 'AFS' && rate.value === transactionExchangeRate)) {
                                                exchangeRatesDisplay.push({ currency: 'AFS', value: transactionExchangeRate });
                                            }
                                        } else if (transaction.currency === 'EUR') {
                                            usdToEurRate = transactionExchangeRate;

                                            // Add to display list if not already present
                                            if (!exchangeRatesDisplay.find(rate => rate.currency === 'EUR' && rate.value === transactionExchangeRate)) {
                                                exchangeRatesDisplay.push({ currency: 'EUR', value: transactionExchangeRate });
                                            }
                                        } else if (transaction.currency === 'DARHAM') {
                                            usdToDarhamRate = transactionExchangeRate;

                                            // Add to display list if not already present
                                            if (!exchangeRatesDisplay.find(rate => rate.currency === 'DARHAM' && rate.value === transactionExchangeRate)) {
                                                exchangeRatesDisplay.push({ currency: 'DARHAM', value: transactionExchangeRate });
                                            }
                                        } else if (transaction.currency === 'USD') {
                                            usdToUsdRate = transactionExchangeRate;

                                            // Add to display list if not already present
                                            if (!exchangeRatesDisplay.find(rate => rate.currency === 'USD' && rate.value === transactionExchangeRate)) {
                                                exchangeRatesDisplay.push({ currency: 'USD', value: transactionExchangeRate });
                                            }
                                        }

                                    }
    
                                    const row = `
                                        <tr>
                                            <td>${transactionManager.formatDate(transaction.created_at)}</td>
                                            <td>${description}</td>
                                            <td>${transaction.type === 'credit' ? 'Received' : 'Paid'}</td>
                                            <td>${parseFloat(transaction.amount).toFixed(2)} ${transaction.currency}</td>
                                            <td>${exchangeRateDisplay}</td>
                                            <td class="text-center">
                                                <button class="btn btn-primary btn-sm mr-1" title="Edit Transaction"
                                                        onclick="transactionManager.editTransaction(${transaction.id}, '${description.replace(/'/g, "\\'")}', ${transaction.amount}, '${transaction.created_at}', '${transaction.currency}', ${transaction.exchange_rate || 'null'})">
                                                    <i class="feather icon-edit"></i>
                                                </button>
                                                                                <button class="btn btn-info btn-sm mr-1" title="Print Receipt"
                                        onclick="printReceipt(${transaction.id})">
                                    <i class="feather icon-printer"></i>
                                </button>
                                                <button class="btn btn-danger btn-sm" title="Delete Transaction"
                                                        onclick="transactionManager.deleteTransaction(${transaction.id}, ${transaction.amount})">
                                                    <i class="feather icon-trash-2"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    `;
                                    tbody.append(row);
                                });
    
                                // Update exchange rate display in info card
                                if (exchangeRatesDisplay.length > 0) {
                                    const displayText = exchangeRatesDisplay
                                        .map(rate => `${rate.currency}: ${rate.value}`)
                                        .join(', ');
                                    $('#exchangeRateDisplay').text(displayText);
                                } else {
                                    $('#exchangeRateDisplay').text('No exchange rates found');
                                }
    
                                // Get the total amount and its currency
                                const totalAmountText = $('#totalAmount').text();
                                const totalCurrency = totalAmountText.split(' ')[0];
                                const totalAmount = parseFloat(totalAmountText.split(' ')[1]) || 0;
    
                                // Calculate and display exchanged amounts for each currency
                                let exchangedAmounts = [];
                                let exchangedAmountMap = {}; // Store exchanged amounts for later use

                                exchangeRatesDisplay.forEach(rate => {
                                    let convertedAmount = totalAmount;
                                    if (totalCurrency === 'USD') {
                                        if (rate.currency === 'AFS') {
                                            convertedAmount = totalAmount * rate.value;
                                        } else if (rate.currency === 'EUR') {
                                            convertedAmount = totalAmount * rate.value;
                                        } else if (rate.currency === 'DARHAM') {
                                            convertedAmount = totalAmount * rate.value;
                                        }
                                    } else if (totalCurrency === 'AFS') {
                                        if (rate.currency === 'USD') {
                                            convertedAmount = totalAmount / rate.value;
                                        } else if (rate.currency === 'EUR') {
                                            convertedAmount = (totalAmount / usdToAfsRate) * rate.value;
                                        } else if (rate.currency === 'DARHAM') {
                                            convertedAmount = (totalAmount / usdToAfsRate) * rate.value;
                                        } else if (rate.currency === 'USD') {
                                            convertedAmount = (totalAmount / usdToAfsRate) * rate.value;
                                        }
                                    } else if (totalCurrency === 'EUR') {
                                        if (rate.currency === 'USD') {
                                            convertedAmount = totalAmount / rate.value;
                                        } else if (rate.currency === 'AFS') {
                                            convertedAmount = (totalAmount / usdToEurRate) * rate.value;
                                        } else if (rate.currency === 'DARHAM') {
                                            convertedAmount = (totalAmount / usdToEurRate) * rate.value;
                                        }
                                    } else if (totalCurrency === 'DARHAM') {
                                        if (rate.currency === 'USD') {
                                            convertedAmount = totalAmount / rate.value;
                                        } else if (rate.currency === 'AFS') {
                                            convertedAmount = (totalAmount / usdToDarhamRate) * rate.value;
                                        } else if (rate.currency === 'EUR') {
                                            convertedAmount = (totalAmount / usdToDarhamRate) * rate.value;
                                        }
                                    }
                                    exchangedAmounts.push(`${rate.currency} ${convertedAmount.toFixed(2)}`);
                                    exchangedAmountMap[rate.currency] = convertedAmount;
                                });

                                if (exchangedAmounts.length > 0) {
                                    $('#exchangedAmount').text(exchangedAmounts.join(', '));
                                } else {
                                    $('#exchangedAmount').text('No conversions available');
                                }
    
                                // Exchange rates are now calculated from transaction data above
                                // usdToAfsRate, usdToEurRate, usdToDarhamRate are set based on actual transaction exchange rates
    
                                // Show/hide currency sections based on transaction existence
                                $('#usdSection').toggle(hasUSDTransactions);
                                $('#afsSection').toggle(hasAFSTransactions);
                                $('#eurSection').toggle(hasEURTransactions);
                                $('#aedSection').toggle(hasDARHAMTransactions);
    
                                // Calculate totals and remaining amounts using transaction-specific exchange rates
                                let totalPaidInBaseCurrency = 0;



                                // Sum up all payments converted to the payment's base currency
                                transactions.forEach(transaction => {
                                    const amount = parseFloat(transaction.amount);
                                    let transactionExchangeRate = transaction.exchange_rate ? parseFloat(transaction.exchange_rate) : null;


                                    // Convert transaction amount to base currency
                                    let convertedAmount = amount;
                                    if (transaction.currency !== totalCurrency) {
                                        // Use transaction-specific exchange rate if available, otherwise use default rates
                                        let exchangeRateToUse = transactionExchangeRate;

                                        if (!exchangeRateToUse) {
                                            // Use default exchange rates when transaction doesn't have a rate
                                            if (totalCurrency === 'USD') {
                                                if (transaction.currency === 'AFS') exchangeRateToUse = usdToAfsRate;
                                                else if (transaction.currency === 'EUR') exchangeRateToUse = usdToEurRate;
                                                else if (transaction.currency === 'DARHAM') exchangeRateToUse = usdToDarhamRate;
                                            } else if (totalCurrency === 'AFS') {
                                                if (transaction.currency === 'USD') exchangeRateToUse = 1 / usdToAfsRate;
                                                else if (transaction.currency === 'EUR') exchangeRateToUse = usdToEurRate / usdToAfsRate;
                                                else if (transaction.currency === 'DARHAM') exchangeRateToUse = usdToDarhamRate / usdToAfsRate;
                                            } else if (totalCurrency === 'EUR') {
                                                if (transaction.currency === 'USD') exchangeRateToUse = 1 / usdToEurRate;
                                                else if (transaction.currency === 'AFS') exchangeRateToUse = usdToAfsRate / usdToEurRate;
                                                else if (transaction.currency === 'DARHAM') exchangeRateToUse = usdToDarhamRate / usdToEurRate;
                                            }
                                        }

                                        if (exchangeRateToUse) {
                                            if (totalCurrency === 'USD') {
                                                if (transaction.currency === 'AFS') {
                                                    convertedAmount = amount / exchangeRateToUse;

                                                } else if (transaction.currency === 'EUR') {
                                                    convertedAmount = amount / exchangeRateToUse;

                                                } else if (transaction.currency === 'DARHAM') {
                                                    convertedAmount = amount / exchangeRateToUse;

                                                }
                                            } else if (totalCurrency === 'AFS') {
                                                if (transaction.currency === 'USD') {
                                                    convertedAmount = amount * exchangeRateToUse;

                                                } else if (transaction.currency === 'EUR') {
                                                    convertedAmount = amount * exchangeRateToUse;

                                                } else if (transaction.currency === 'DARHAM') {
                                                    convertedAmount = amount * exchangeRateToUse;

                                                }
                                            } else if (totalCurrency === 'EUR') {
                                                if (transaction.currency === 'USD') {
                                                    convertedAmount = amount * exchangeRateToUse;

                                                } else if (transaction.currency === 'AFS') {
                                                    convertedAmount = amount * exchangeRateToUse;

                                                } else if (transaction.currency === 'DARHAM') {
                                                    convertedAmount = amount * exchangeRateToUse;

                                                }
                                            }
                                        } else {

                                        }
                                    } else {

                                    }

                                    totalPaidInBaseCurrency += convertedAmount;

                                });

                                const remainingAmount = Math.max(0, totalAmount - totalPaidInBaseCurrency);

    
                                // Display amounts in each currency section
                                if (hasUSDTransactions) {
                                    const usdPaid = transactions
                                        .filter(t => t.currency === 'USD')
                                        .reduce((sum, t) => sum + parseFloat(t.amount), 0);
                                    $('#paidAmountUSD').text(`USD ${usdPaid.toFixed(2)}`);

                                    let usdRemaining = remainingAmount;
                                    if (totalCurrency === 'AFS') {
                                        usdRemaining = remainingAmount / usdToAfsRate;
                                    } else if (totalCurrency === 'EUR') {
                                        usdRemaining = remainingAmount / usdToEurRate;
                                    } else if (totalCurrency === 'DARHAM') {
                                        usdRemaining = remainingAmount / usdToDarhamRate;
                                    }
                                    $('#remainingAmountUSD').text(`USD ${usdRemaining.toFixed(2)}`);
                                }

                                if (hasAFSTransactions) {
                                    const afsPaid = transactions
                                        .filter(t => t.currency === 'AFS')
                                        .reduce((sum, t) => sum + parseFloat(t.amount), 0);
                                    $('#paidAmountAFS').text(`AFS ${afsPaid.toFixed(2)}`);

                                    let afsRemaining = remainingAmount;
                                    if (totalCurrency === 'USD') {
                                        afsRemaining = remainingAmount * usdToAfsRate;
                                    } else if (totalCurrency === 'EUR') {
                                        afsRemaining = (remainingAmount / usdToEurRate) * usdToAfsRate;
                                    } else if (totalCurrency === 'DARHAM') {
                                        afsRemaining = (remainingAmount / usdToDarhamRate) * usdToAfsRate;
                                    }
                                    $('#remainingAmountAFS').text(`AFS ${afsRemaining.toFixed(2)}`);
                                }

                                if (hasEURTransactions) {
                                    const eurPaid = transactions
                                        .filter(t => t.currency === 'EUR')
                                        .reduce((sum, t) => sum + parseFloat(t.amount), 0);
                                    $('#paidAmountEUR').text(`EUR ${eurPaid.toFixed(2)}`);

                                    let eurRemaining = remainingAmount;
                                    if (totalCurrency === 'USD') {
                                        eurRemaining = remainingAmount * usdToEurRate;
                                    } else if (totalCurrency === 'AFS') {
                                        eurRemaining = (remainingAmount / usdToAfsRate) * usdToEurRate;
                                    } else if (totalCurrency === 'DARHAM') {
                                        eurRemaining = (remainingAmount / usdToDarhamRate) * usdToEurRate;
                                    }
                                    $('#remainingAmountEUR').text(`EUR ${eurRemaining.toFixed(2)}`);
                                }

                                if (hasDARHAMTransactions) {
                                    const darhamPaid = transactions
                                        .filter(t => t.currency === 'DARHAM')
                                        .reduce((sum, t) => sum + parseFloat(t.amount), 0);
                                    $('#paidAmountAED').text(`AED ${darhamPaid.toFixed(2)}`);

                                    let darhamRemaining = remainingAmount;
                                    if (totalCurrency === 'USD') {
                                        darhamRemaining = remainingAmount * usdToDarhamRate;
                                    } else if (totalCurrency === 'AFS') {
                                        darhamRemaining = (remainingAmount / usdToAfsRate) * usdToDarhamRate;
                                    } else if (totalCurrency === 'EUR') {
                                        darhamRemaining = (remainingAmount / usdToEurRate) * usdToDarhamRate;
                                    }
                                    $('#remainingAmountAED').text(`AED ${darhamRemaining.toFixed(2)}`);
                                }
    
                            } catch (e) {

                                $('#transactionsTableBody').html(
                                    '<tr><td colspan="6" class="text-center">error_loading_transactions</td></tr>'
                                );
                                $('#exchangeRateDisplay').text('Error loading exchange rates');
                                $('#exchangedAmount').text('Error calculating amounts');
                            }
                        },
                        error: function(xhr, status, error) {

                            $('#transactionsTableBody').html(
                                '<tr><td colspan="6" class="text-center">error_loading_transactions</td></tr>'
                            );
                            $('#exchangeRateDisplay').text('Error loading exchange rates');
                            $('#exchangedAmount').text('Error calculating amounts');
                        }
                    });
                },
    
                editTransaction: function(id, description, amount, created_at, currency, exchange_rate) {
                    // Populate edit modal with transaction data
                    $('#edit_transaction_id').val(id);
                    $('#edit_transaction_payment_id').val($('#transaction_payment_id').val());
                    $('#edit_original_payment_currency').val($('#original_payment_currency').val());
                    $('#edit_payment_amount').val(amount);
                    $('#edit_transaction_currency').val(currency);
                    $('#edit_payment_description').val(description);
                    $('#edit_receipt').val(''); // You may need to fetch this separately

                    // Parse and set date/time
                    const txDate = new Date(created_at);
                    const formattedDate = txDate.toISOString().split('T')[0];
                    const hours = String(txDate.getHours()).padStart(2, '0');
                    const minutes = String(txDate.getMinutes()).padStart(2, '0');
                    const seconds = String(txDate.getSeconds()).padStart(2, '0');
                    const formattedTime = `${hours}:${minutes}:${seconds}`;

                    $('#edit_payment_date').val(formattedDate);
                    $('#edit_payment_time').val(formattedTime);

                    // Handle exchange rate - use the direct field from database
                    if (exchange_rate && exchange_rate !== 'null') {
                        $('#edit_exchange_rate').val(exchange_rate);
                        $('#edit_exchange_rate_group').show();
                    } else {
                        $('#edit_exchange_rate').val('');
                        $('#edit_exchange_rate_group').hide();
                    }

                    // Show modal
                    $('#editTransactionModal').modal('show');
                },
    
                deleteTransaction: function(id, amount) {
                    if (confirm('Are you sure you want to delete this transaction?')) {
                        const paymentId = $('#transaction_payment_id').val();

                        // Get CSRF token from meta tag or hidden input
                        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || 
                                         document.querySelector('input[name="csrf_token"]')?.value;
                        
                        $.ajax({
                            url: '../api/additional_payment/delete_additional_payment_transaction.php',
                            type: 'POST',
                            data: {
                                transaction_id: id,
                                payment_id: paymentId,
                                csrf_token: csrfToken
                            },
                            success: function(response) {
                                try {
                                    const result = typeof response === 'object' ? response : JSON.parse(response);
                                    if (result.success) {
                                        alert('Transaction deleted successfully');
                                        // Reload transactions
                                        transactionManager.loadTransactionHistory(paymentId);
                                    } else {
                                        alert('Error: ' + (result.message || 'Unknown error occurred'));
                                    }
                                } catch (e) {

                                    alert('Error: Invalid response from server');
                                }
                            },
                            error: function(xhr, status, error) {

                                alert('Error deleting transaction');
                            }
                        });
                    }
                }
            };

            function printReceipt(transactionId) {
                window.open('../api/additional_payment/print_additional_receipt.php?transaction_id=' + transactionId, '_blank');
            }

            $(document).ready(function() {
                $('#supplier_id').select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    dropdownParent: $('#supplier_id').closest('.modal-body'),
                    placeholder: 'Select supplier',
                    allowClear: true
                });
                $('#client_id').select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    dropdownParent: $('#client_id').closest('.modal-body'),
                    placeholder: 'Select client',
                    allowClear: true
                });
            });
