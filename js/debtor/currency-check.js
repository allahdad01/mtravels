// Function to check currency and show/hide exchange rate field for debtors
function checkCurrency(selectElement, debtorCurrency, debtorId) {
    const selectedCurrency = selectElement.value;
    const exchangeRateDiv = document.getElementById('exchangeRateDiv' + debtorId);
    const baseSpan = document.getElementById('selectedCurrency' + debtorId);
    const targetSpan = document.getElementById('debtorCurrency' + debtorId);
    const exchangeRateInput = document.getElementById('exchangeRate' + debtorId);
    const helpText = document.getElementById('exchangeRateHelp' + debtorId);

    if (selectedCurrency !== debtorCurrency) {
        exchangeRateDiv.style.display = 'block';
        exchangeRateInput.required = true;

        // Show rate in the most natural direction
        if (debtorCurrency === 'AFS') {
            // 1 [payment] = ? AFS → multiply
            baseSpan.textContent = selectedCurrency;
            targetSpan.textContent = 'AFS';
            helpText.textContent = 'Enter the rate for 1 ' + selectedCurrency + ' = ? AFS';
        } else if (selectedCurrency === 'AFS') {
            // 1 [debtor] = ? AFS → divide
            baseSpan.textContent = debtorCurrency;
            targetSpan.textContent = 'AFS';
            helpText.textContent = 'Enter the rate for 1 ' + debtorCurrency + ' = ? AFS';
        } else {
            // 1 [debtor] = ? [payment] → divide
            baseSpan.textContent = debtorCurrency;
            targetSpan.textContent = selectedCurrency;
            helpText.textContent = 'Enter the rate for 1 ' + debtorCurrency + ' = ? ' + selectedCurrency;
        }
    } else {
        exchangeRateDiv.style.display = 'none';
        exchangeRateInput.required = false;
        exchangeRateInput.value = '';
    }
}
