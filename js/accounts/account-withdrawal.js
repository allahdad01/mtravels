function loadWithdrawMainAccounts() {
    fetch('../api/accounts/fetch_main_accounts.php')
        .then(response => response.json())
        .then(data => {
            const mainAccountSelect = document.getElementById('withdrawMainAccount');
            mainAccountSelect.innerHTML = '<option value="">Select main account</option>';
            data.forEach(account => {
                const option = document.createElement('option');
                option.value = account.id;
                option.textContent = account.name;
                mainAccountSelect.appendChild(option);
            });
        })
        .catch(error => {
            showErrorToast('Error fetching main accounts');
        });
}

function setupWithdrawModal(supplierId, supplierName, supplierCurrency) {
    document.getElementById('withdrawSupplierId').value = supplierId;
    document.getElementById('withdrawSupplierName').value = supplierName;
    document.getElementById('withdrawSupplierCurrency').value = supplierCurrency;
    const paymentCurrencySelect = document.getElementById('withdrawPaymentCurrency');
    paymentCurrencySelect.value = supplierCurrency;
    loadWithdrawMainAccounts();
    toggleWithdrawExchangeRateVisibility();
    $('#withdrawSupplierModal').modal('show');
}

function toggleWithdrawExchangeRateVisibility() {
    const supplierCurrency = document.getElementById('withdrawSupplierCurrency').value;
    const paymentCurrency = document.getElementById('withdrawPaymentCurrency').value;
    const group = document.getElementById('withdrawExchangeRateGroup');
    const label = document.getElementById('withdrawExchangeRateLabel');
    const hint = document.getElementById('withdrawExchangeHint');
    const exchangeInput = document.getElementById('withdrawExchangeRate');
    const badge = document.getElementById('withdrawFormulaBadge');
    const needsRate = supplierCurrency !== paymentCurrency;
    group.classList.toggle('d-none', !needsRate);
    if (exchangeInput) exchangeInput.required = needsRate;
    if (!needsRate) return;
    const norm = c => c === 'DARHAM' ? 'AED' : c;
    const normFrom = norm(paymentCurrency);
    const normTo = norm(supplierCurrency);
    const dividePairs = ['AFS->AED', 'AFS->EUR', 'AFS->USD', 'AED->EUR', 'AED->USD', 'EUR->USD'];
    const isDivide = dividePairs.includes(normFrom + '->' + normTo);
    if (badge) badge.textContent = isDivide ? '÷' : '×';
    label.textContent = 'Exchange rate (' + paymentCurrency + ' → ' + supplierCurrency + ')';
    const sampleRates = {
        'AFS->AED': 19.75, 'AED->AFS': 19.75,
        'AFS->EUR': 78.8, 'EUR->AFS': 78.8,
        'AFS->USD': 72.5, 'USD->AFS': 72.5,
        'AED->EUR': 3.99, 'EUR->AED': 3.99,
        'AED->USD': 3.67, 'USD->AED': 3.67,
        'EUR->USD': 1.09, 'USD->EUR': 0.92,
    };
    const rate = sampleRates[normFrom + '->' + normTo];
    if (rate) {
        hint.textContent = isDivide
            ? 'e.g. 1 ' + supplierCurrency + ' = ' + rate.toFixed(2) + ' ' + paymentCurrency + ' → enter ' + rate.toFixed(2)
            : 'e.g. 1 ' + paymentCurrency + ' = ' + rate.toFixed(2) + ' ' + supplierCurrency + ' → enter ' + rate.toFixed(2);
    } else {
        hint.textContent = 'Enter the exchange rate';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const paymentCurrencyEl = document.getElementById('withdrawPaymentCurrency');
    if (paymentCurrencyEl) {
        paymentCurrencyEl.addEventListener('change', toggleWithdrawExchangeRateVisibility);
    }

    // Client withdraw button handler
    document.querySelectorAll('.client-withdraw-btn').forEach(button => {
        button.addEventListener('click', function() {
            const clientId = this.dataset.clientId;
            const clientName = this.dataset.clientName;
            const usdBalance = parseFloat(this.dataset.usdBalance);
            const afsBalance = parseFloat(this.dataset.afsBalance);
            window.openClientWithdrawModal(clientId, clientName, usdBalance, afsBalance);
        });
    });

    const form = document.getElementById('withdrawSupplierForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = document.getElementById('withdrawSupplierBtn');
            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Withdrawing...';

            const supplierId = document.getElementById('withdrawSupplierId').value;
            const mainAccountId = document.getElementById('withdrawMainAccount').value;
            const paymentCurrency = document.getElementById('withdrawPaymentCurrency').value;
            const amount = parseFloat(document.getElementById('withdrawAmount').value);
            const remarks = document.getElementById('withdrawRemarks').value.trim();
            const receiptNumber = document.getElementById('withdrawReceiptNumber').value.trim();

            if (!mainAccountId) {
                btn.disabled = false; btn.innerHTML = originalHtml;
                showWarningToast('Please select a main account');
                return;
            }
            if (isNaN(amount) || amount <= 0) {
                btn.disabled = false; btn.innerHTML = originalHtml;
                showWarningToast('Please enter a valid amount');
                return;
            }

            const withdrawalData = {
                supplier_id: supplierId,
                main_account_id: mainAccountId,
                payment_currency: paymentCurrency,
                amount: amount,
                remarks: remarks,
                receipt_number: receiptNumber
            };

            if (paymentCurrency !== document.getElementById('withdrawSupplierCurrency').value) {
                const exchangeRate = parseFloat(document.getElementById('withdrawExchangeRate').value);
                if (isNaN(exchangeRate) || exchangeRate <= 0) {
                    btn.disabled = false; btn.innerHTML = originalHtml;
                    showWarningToast('Please enter a valid exchange rate');
                    return;
                }
                withdrawalData.exchange_rate = exchangeRate;
            }

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            withdrawalData.csrf_token = csrfToken;

            fetch('../api/accounts/withdraw_fund.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(withdrawalData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccessToast('Withdrawal successful');
                    location.reload();
                } else {
                    showErrorToast('Error: ' + data.message);
                }
            })
            .catch(error => {
                showErrorToast('Error withdrawing funds');
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            });
        });
    }
});
