    // Fetch Main Accounts and Populate Dropdown for Withdrawal
    function loadWithdrawMainAccounts() {
        fetch('fetch_main_accounts.php')
            .then(response => response.json())
            .then(data => {
                const mainAccountSelect = document.getElementById('withdrawMainAccount');
                mainAccountSelect.innerHTML = ''; // Clear existing options
                data.forEach(account => {
                    const option = document.createElement('option');
                    option.value = account.id;
                    option.textContent = `${account.name}`;
                    mainAccountSelect.appendChild(option);
                });
            })
            .catch(error => {
                console.error('error_fetching_main_accounts:', error);
                alert('error_fetching_main_accounts');
            });
    }

    function setupWithdrawModal(supplierId, supplierName, supplierCurrency) {
        // Set supplier details in the modal
        document.getElementById('withdrawSupplierId').value = supplierId;
        document.getElementById('withdrawSupplierName').value = supplierName;
        document.getElementById('withdrawSupplierCurrency').value = supplierCurrency;
        
        // Default payment currency to supplier currency
        const paymentCurrencySelect = document.getElementById('withdrawPaymentCurrency');
        paymentCurrencySelect.value = supplierCurrency;
        
        // Load main accounts
        loadWithdrawMainAccounts();
        
        // Toggle exchange rate visibility
        toggleWithdrawExchangeRateVisibility();
        
        // Show the modal
        $('#withdrawSupplierModal').modal('show');
    }

    // Toggle Exchange Rate Visibility
    function toggleWithdrawExchangeRateVisibility() {
        const supplierCurrency = document.getElementById('withdrawSupplierCurrency').value;
        const paymentCurrency = document.getElementById('withdrawPaymentCurrency').value;
        const group = document.getElementById('withdrawExchangeRateGroup');
        const label = document.getElementById('withdrawExchangeRateLabel');
        const hint = document.getElementById('withdrawExchangeHint');
        const exchangeInput = document.getElementById('withdrawExchangeRate');
        
        const needsRate = supplierCurrency !== paymentCurrency;
        group.classList.toggle('d-none', !needsRate);
        exchangeInput.required = needsRate;
        
        // We always expect USD → AFS rate
        label.textContent = 'exchange_rate_usd_to_afs';
    }

    // Add event listener for payment currency change
    document.getElementById('withdrawPaymentCurrency').addEventListener('change', toggleWithdrawExchangeRateVisibility);

    // Handle withdrawal form submission
    document.getElementById('withdrawSupplierForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const supplierId = document.getElementById('withdrawSupplierId').value;
        const mainAccountId = document.getElementById('withdrawMainAccount').value;
        const paymentCurrency = document.getElementById('withdrawPaymentCurrency').value;
        const amount = parseFloat(document.getElementById('withdrawAmount').value);
        const remarks = document.getElementById('withdrawRemarks').value.trim();
        const receiptNumber = document.getElementById('withdrawReceiptNumber').value.trim();
        
        // Basic validation
        if (!mainAccountId) {
            alert("please_select_main_account");
            return;
        }
        
        if (isNaN(amount) || amount <= 0) {
            alert("please_enter_a_valid_amount");
            return;
        }
        
        // Prepare withdrawal data
        const withdrawalData = {
            supplier_id: supplierId,
            main_account_id: mainAccountId,
            payment_currency: paymentCurrency,
            amount: amount,
            remarks: remarks,
            receipt_number: receiptNumber
        };
        
        // Add exchange rate if needed
        if (paymentCurrency !== document.getElementById('withdrawSupplierCurrency').value) {
            const exchangeRate = parseFloat(document.getElementById('withdrawExchangeRate').value);
            if (isNaN(exchangeRate) || exchangeRate <= 0) {
                alert("please_enter_a_valid_exchange_rate");
                return;
            }
            withdrawalData.exchange_rate = exchangeRate;
        }
        
        // Send withdrawal request
        fetch('withdraw_fund.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(withdrawalData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('supplier_account_withdrawal_successful');
                location.reload(); // Refresh page to reflect changes
            } else {
                alert('error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('error_withdrawing_funds:', error);
            alert('error_withdrawing_funds');
        });
    });