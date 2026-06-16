// Wrapper function to initialize modal
function setupFundingModal(supplierId, supplierName, supplierCurrency) {
    // Get the form elements with null checks
    const supplierIdInput = document.getElementById('supplierId');
    const supplierNameInput = document.getElementById('supplierName');
    const supplierCurrencyInput = document.getElementById('supplierCurrency');
    const supplierNameDisplay = document.getElementById('supplierNameDisplay');
    const supplierCurrencyDisplay = document.getElementById('supplierCurrencyDisplay');
    const currencySymbol = document.getElementById('currencySymbol');
    
    // Set form values if elements exist
    if (supplierIdInput) supplierIdInput.value = supplierId;
    if (supplierNameInput) supplierNameInput.value = supplierName;
    if (supplierCurrencyInput) supplierCurrencyInput.value = supplierCurrency;
    
    // Set display values
    if (supplierNameDisplay) supplierNameDisplay.textContent = supplierName;
    
    // Set currency display and symbol
    if (supplierCurrencyDisplay) {
        supplierCurrencyDisplay.textContent = `Currency: ${supplierCurrency}`;
    }
    
    if (currencySymbol) {
        if (supplierCurrency === 'USD') {
            currencySymbol.textContent = '$';
        } else if (supplierCurrency === 'AFS') {
            currencySymbol.textContent = '؋';
        } else if (supplierCurrency === 'EUR') {
            currencySymbol.textContent = '€';
        } else {
            currencySymbol.textContent = supplierCurrency;
        }
    }
    
    // Load accounts
    loadMainAccounts();
    
    // Show modal using jQuery
    try {
        $('#fundSupplierModal').modal('show');
    } catch (error) {

    }
}

// Fetch Main Accounts and Populate Dropdown
function loadMainAccounts() {
    fetch('../api/accounts/fetch_main_accounts.php')
        .then(response => response.json())
        .then(data => {
            const mainAccountSelect = document.getElementById('mainAccount');
            mainAccountSelect.innerHTML = ''; // Clear existing options
            data.forEach(account => {
                const option = document.createElement('option');
                option.value = account.id;
                option.textContent = `${account.name}`;
                mainAccountSelect.appendChild(option);
            });
        })
        .catch(error => {

        });
}

// Show Modal with Preloaded Data
function showFundSupplierModal(supplierId, supplierName, supplierCurrency) {
    // Get the form elements with null checks
    const supplierIdInput = document.getElementById('supplierId');
    const supplierNameInput = document.getElementById('supplierName');
    const supplierCurrencyInput = document.getElementById('supplierCurrency');
    const supplierNameDisplay = document.getElementById('supplierNameDisplay');
    const supplierCurrencyDisplay = document.getElementById('supplierCurrencyDisplay');
    const currencySymbol = document.getElementById('currencySymbol');
    
    // Set form values if elements exist
    if (supplierIdInput) supplierIdInput.value = supplierId;
    if (supplierNameInput) supplierNameInput.value = supplierName;
    if (supplierCurrencyInput) supplierCurrencyInput.value = supplierCurrency;
    
    // Set display values
    if (supplierNameDisplay) supplierNameDisplay.textContent = supplierName;
    
    // Set currency display and symbol
    if (supplierCurrencyDisplay) {
        supplierCurrencyDisplay.textContent = `Currency: ${supplierCurrency}`;
    }
    
    if (currencySymbol) {
        if (supplierCurrency === 'USD') {
            currencySymbol.textContent = '$';
        } else if (supplierCurrency === 'AFS') {
            currencySymbol.textContent = '؋';
        } else if (supplierCurrency === 'EUR') {
            currencySymbol.textContent = '€';
        } else {
            currencySymbol.textContent = supplierCurrency;
        }
    }
    
    // Load accounts
    loadMainAccounts();
    
    // Show modal using jQuery
    $('#fundSupplierModal').modal('show');
}

// Helpers for exchange rate UI
function toggleExchangeRateVisibility() {
    const supplierCurrency = document.getElementById('supplierCurrency').value;
    const paymentCurrency = document.getElementById('paymentCurrency').value;
    const group = document.getElementById('exchangeRateGroup');
    const label = document.getElementById('exchangeRateLabel');
    const hint = document.getElementById('exchangeHint');
    const exchangeInput = document.getElementById('exchangeRate');
    const needsRate = supplierCurrency !== paymentCurrency;
    group.classList.toggle('d-none', !needsRate);
    exchangeInput.required = needsRate;
    // We always expect USD → AFS rate
    label.textContent = 'Exchange rate (USD → AFS)';
    hint.textContent = 'Provide USD → AFS rate only when payment currency differs from supplier currency.';
}

function updateAmountPlaceholder() {
    const paymentCurrency = document.getElementById('paymentCurrency').value;
    const amount = document.getElementById('fundAmount');
    amount.placeholder = `Enter amount in ${paymentCurrency}`;
}

document.getElementById('paymentCurrency').addEventListener('change', () => {
    toggleExchangeRateVisibility();
    updateAmountPlaceholder();
});

// Load main accounts for client dropdowns
function loadMainAccountsForClients() {
    fetch('../api/accounts/fetch_main_accounts.php')
        .then(response => response.json())
        .then(data => {
            // For each client, populate their main account dropdown
            document.querySelectorAll('[id^="clientMainAccount-"]').forEach(select => {
                // Clear existing options except the first placeholder
                select.innerHTML = '<option value="">Select main account</option>';
                
                // Add options for each main account
                data.forEach(account => {
                    const option = document.createElement('option');
                    option.value = account.id;
                    option.textContent = `${account.name} - (USD: ${account.usd_balance}, AFS: ${account.afs_balance})`;
                    select.appendChild(option);
                });
            });
        })
        .catch(error => {

        });
}

function generateOverallReport() {
    const format = prompt("Choose a format: Excel, Word, PDF").toLowerCase();

    if (['excel', 'word', 'pdf'].includes(format)) {
        window.open(`generate_report.php?format=${format}`, '_blank');
    } else {
        showWarningToast("Invalid format. Please choose Excel, Word, PDF.");
    }
}

// Handler for form submission
document.addEventListener('DOMContentLoaded', function() {
    // Form submission for funding supplier accounts
    const fundSupplierForm = document.getElementById('fundSupplierForm');
    if (fundSupplierForm) {
        fundSupplierForm.addEventListener('submit', function (e) {
            e.preventDefault();
            
            const submitBtn = fundSupplierForm.querySelector('button[type="submit"]') || fundSupplierForm.querySelector('button');
            const originalHtml = submitBtn ? submitBtn.innerHTML : '';
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Funding...';
            }

            const formData = new FormData(e.target);
            // Add CSRF token to formData
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            formData.append('csrf_token', csrfToken);
            
            fetch('../api/accounts/fund_supplier.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccessToast('Supplier account funded successfully!');
                    setTimeout(() => {
                    location.reload();
                    }, 1000);
                } else {
                    showErrorToast('Error: ' + data.message);
                }
            })
            .catch(error => {

                showErrorToast('An unexpected error occurred while funding the supplier.');
            })
            .finally(() => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalHtml;
                }
            });
        });
    }

    // Attach event listener for fund button click
    document.querySelectorAll(".fund-account-btn").forEach(button => {
        button.addEventListener("click", function () {
            const accountId = this.dataset.accountId;
            const currency = document.getElementById(`currency-${accountId}`).value;
            const amount = parseFloat(document.getElementById(`amount-${accountId}`).value);

            if (isNaN(amount) || amount <= 0) {
                showWarningToast("Please enter a valid amount.");
                return;
            }

            // Show the remarks modal for custom remarks
            $('#remarksModal').modal('show');

            // Store the account details temporarily
            $('#submit-remarks-btn').off('click').on('click', function () {
                const $btn = $(this);
                const originalHtml = $btn.html();
                $btn.prop('disabled', true);
                $btn.html('<i class="fas fa-spinner fa-spin mr-1"></i>Submitting...');

                const userRemarks = document.getElementById('user-remarks').value.trim();
                const receiptNumber = document.getElementById('modalReceiptNumber').value.trim();
                if (!userRemarks) {
                    $btn.prop('disabled', false);
                    $btn.html(originalHtml);
                    showWarningToast("Please add your remarks.");
                    return;
                }

                if (!receiptNumber) {
                    $btn.prop('disabled', false);
                    $btn.html(originalHtml);
                    showWarningToast("Please enter a receipt number.");
                    return;
                }

                // Get CSRF token from meta tag
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

                // Send the data to the backend
                fetch("../api/accounts/fund_main_account.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                    },
                    body: JSON.stringify({
                        accountId: accountId,
                        currency: currency,
                        amount: amount,
                        userRemarks: userRemarks, // Pass the custom remarks
                        receipt: receiptNumber,
                        csrf_token: csrfToken,
                    }),
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showSuccessToast("Main account funded successfully!");
                        setTimeout(() => {
                        location.reload();
                        }, 1000);
                    } else {
                        showErrorToast("Error: " + data.message);
                    }
                })
                .catch(error => {
                    showErrorToast("Error: " + error.message);
                })
                .finally(() => {
                    $btn.prop('disabled', false);
                    $btn.html(originalHtml);
                });

                // Close the modal
                $('#remarksModal').modal('hide');
            });
        });
    });

    // Transfer balance between accounts
    const transferBtn = document.getElementById('transferBtn');
    if (transferBtn) {
        const transferForm = document.getElementById('transferForm');
        
        transferBtn.addEventListener('click', function() {
            const originalHtml = transferBtn.innerHTML;
            transferBtn.disabled = true;
            transferBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Transferring...';

            const formData = new FormData(transferForm);
            const data = Object.fromEntries(formData.entries());
            
            // Validate form
            if (!data.fromAccount || !data.fromCurrency || !data.toAccount || !data.toCurrency || !data.amount || !data.exchangeRate) {
                transferBtn.disabled = false;
                transferBtn.innerHTML = originalHtml;
                showWarningToast('Please fill in all required fields');
                return;
            }
            
            if (data.fromAccount === data.toAccount && data.fromCurrency === data.toCurrency) {
                transferBtn.disabled = false;
                transferBtn.innerHTML = originalHtml;
                showWarningToast('Cannot transfer to the same account and currency');
                return;
            }
            
            // Get CSRF token from meta tag
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            // Send transfer request
            fetch('../api/accounts/transfer_balance.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({...data, csrf_token: csrfToken})
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccessToast('Transfer successful');
                    setTimeout(() => {
                    location.reload();
                    }, 1000);
                } else {
                    showErrorToast('Transfer failed: ' + data.message);
                }
            })
            .catch(error => {

                showErrorToast('An error occurred during the transfer');
            })
            .finally(() => {
                transferBtn.disabled = false;
                transferBtn.innerHTML = originalHtml;
            });
        });
    }

    // --- Transfer balance dynamic helper ---
    const transferDividePairs = ['AFS->AED', 'AFS->EUR', 'AFS->USD', 'AED->EUR', 'AED->USD', 'EUR->USD'];
    const transferSampleRates = {
        'AFS->AED': 19.75, 'AED->AFS': 19.75,
        'AFS->EUR': 78.8, 'EUR->AFS': 78.8,
        'AFS->USD': 72.5, 'USD->AFS': 72.5,
        'AED->EUR': 3.99, 'EUR->AED': 3.99,
        'AED->USD': 3.67, 'USD->AED': 3.67,
        'EUR->USD': 1.09, 'USD->EUR': 0.92,
    };
    function updateTransferHelper() {
        const fromCur = document.getElementById('fromCurrency').value;
        const toCur = document.getElementById('toCurrency').value;
        const badge = document.getElementById('transferFormulaBadge');
        const help = document.getElementById('transferRateHelp');
        const preview = document.getElementById('transferConvertedPreview');
        const previewText = document.getElementById('transferConvertedText');
        if (!fromCur || !toCur || fromCur === toCur) {
            badge.textContent = '×';
            help.textContent = '';
            preview.style.display = 'none';
            return;
        }
        const pairKey = fromCur + '->' + toCur;
        const isDivide = transferDividePairs.includes(pairKey);
        badge.textContent = isDivide ? '÷' : '×';
        const rate = transferSampleRates[pairKey];
        if (rate) {
            help.textContent = isDivide
                ? 'e.g. 1 ' + toCur + ' = ' + rate.toFixed(2) + ' ' + fromCur + ' → enter ' + rate.toFixed(2)
                : 'e.g. 1 ' + fromCur + ' = ' + rate.toFixed(2) + ' ' + toCur + ' → enter ' + rate.toFixed(2);
        } else {
            help.textContent = 'Enter the exchange rate';
        }
        const amount = parseFloat(document.getElementById('amount').value) || 0;
        const exchangeRate = parseFloat(document.getElementById('exchangeRate').value) || 0;
        if (amount > 0 && exchangeRate > 0) {
            const converted = isDivide ? amount / exchangeRate : amount * exchangeRate;
            previewText.textContent = converted.toFixed(2) + ' ' + toCur;
            preview.style.display = 'block';
        } else {
            preview.style.display = 'none';
        }
    }
    document.getElementById('fromCurrency').addEventListener('change', updateTransferHelper);
    document.getElementById('toCurrency').addEventListener('change', updateTransferHelper);
    document.getElementById('amount').addEventListener('input', updateTransferHelper);
    document.getElementById('exchangeRate').addEventListener('input', updateTransferHelper);
    $('#transferModal').on('shown.bs.modal', updateTransferHelper);

    // Fund client accounts
    document.addEventListener('DOMContentLoaded', () => {
        // Fetch and populate main accounts for all client dropdowns
        loadMainAccountsForClients();
        
        // Add event listener to all Fund This Client buttons
        document.querySelectorAll(".fund-client-btn").forEach(button => {
            button.addEventListener("click", function () {
                const clientId = this.dataset.clientId;
                
                // Get the selected currency value from the dropdown
                const currency = document.getElementById(`clientCurrency-${clientId}`).value;
                
                // Get the amount input value for the current client
                const amountInput = document.getElementById(`clientAmount-${clientId}`);
                const amount = parseFloat(amountInput.value);

                // Get the selected main account
                const mainAccountSelect = document.getElementById(`clientMainAccount-${clientId}`);
                const mainAccountId = mainAccountSelect.value;
                
                if (!mainAccountId) {
                    showWarningToast("Please select a main account.");
                    return;
                }
                
                // Validate the amount input
                if (isNaN(amount) || amount <= 0) {
                    showWarningToast("Please enter a valid amount greater than zero.");
                    return;
                }

                // For USD payments, ask if there's an additional AFS portion
                if (currency === 'USD') {
                    const hasAFSPortion = confirm("Do you want to add an AFS portion to this payment?");
                    
                    if (hasAFSPortion) {
                        const afsEquivalentUSD = prompt("Enter the USD amount you want to pay in AFS:");
                        if (afsEquivalentUSD && !isNaN(afsEquivalentUSD)) {
                            const exchangeRate = prompt(`Please enter today's exchange rate (USD to AFS) to convert ${afsEquivalentUSD} USD:`);
                            
                            if (exchangeRate && !isNaN(exchangeRate)) {
                                const afsAmount = parseFloat(afsEquivalentUSD) * parseFloat(exchangeRate);
                                
                                // Show confirmation with both amounts
                                const confirmMessage = `Please confirm the payment:\n` +
                                                     `- ${amount} USD direct payment\n` +
                                                     `- ${afsEquivalentUSD} USD equivalent in AFS (${afsAmount} AFS)\n` +
                                                     `Total USD value: ${amount + parseFloat(afsEquivalentUSD)} USD`;
                                
                                if (confirm(confirmMessage)) {
                                    showRemarksModal(clientId, mainAccountId, amount, currency, true, afsAmount, parseFloat(afsEquivalentUSD));
                                }
                                return;
                            } else {
                                showWarningToast("Invalid exchange rate. Please try again.");
                                return;
                            }
                        } else {
                            showWarningToast("Invalid AFS equivalent amount. Please try again.");
                            return;
                        }
                    }
                }

                // If no AFS portion or not USD, proceed normally
                showRemarksModal(clientId, mainAccountId, amount, currency, false, 0, 0);
            });
        });
    });
});

// Function to show remarks modal and handle the submission for client funding
function showRemarksModal(clientId, mainAccountId, amount, currency, hasAfsPortion, afsAmount, afsEquivalentUsd) {
    $('#remarksModal').modal('show');

    // Set up the event listener to process the funding when remarks are entered
    $('#submit-remarks-btn').off('click').on('click', function () {
        const $btn = $(this);
        const originalHtml = $btn.html();
        $btn.prop('disabled', true);
        $btn.html('<i class="fas fa-spinner fa-spin mr-1"></i>Submitting...');

        const userRemarks = document.getElementById('user-remarks').value.trim();
        const receiptNumber = document.getElementById('modalReceiptNumber').value.trim();

        if (!userRemarks) {
            $btn.prop('disabled', false);
            $btn.html(originalHtml);
            showWarningToast("Please add your remarks.");
            return;
        }

        if (!receiptNumber) {
            $btn.prop('disabled', false);
            $btn.html(originalHtml);
            showWarningToast("Please enter a receipt number.");
            return;
        }

        // Get CSRF token from meta tag
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        // Send the funding request to the server via the API
         fetch("../api/accounts/fundClient.php", {
             method: "POST",
             headers: {
                 "Content-Type": "application/json",
             },
             body: JSON.stringify({
                 clientId: clientId,
                 currency: currency,
                 amount: amount,
                 userRemarks: userRemarks,
                 receipt: receiptNumber,
                 mainAccountId: mainAccountId,
                 hasAfsPortion: hasAfsPortion,
                 afsAmount: afsAmount,
                 afsEquivalentUsd: afsEquivalentUsd,
                 csrf_token: csrfToken
             }),
         })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showSuccessToast("Client account funded successfully!");
                setTimeout(() => {
                location.reload();
                }, 1000);
            } else {
                showErrorToast("Error: " + data.message);
            }
        })
        .catch(error => {
            showErrorToast("Error: " + error.message);
        })
        .finally(() => {
            $btn.prop('disabled', false);
            $btn.html(originalHtml);
        });

        // Close the modal after submitting
        $('#remarksModal').modal('hide');
    });
} 
