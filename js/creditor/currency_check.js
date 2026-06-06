// Function to check currency and show/hide exchange rate field for creditors
function checkCreditorCurrency(selectElement, creditorCurrency, creditorId) {
    const selectedCurrency = selectElement.value;
    const exchangeRateDiv = document.getElementById('exchangeRateDiv_' + creditorId);
    const baseSpan = document.getElementById('selectedCreditorCurrency_' + creditorId);
    const targetSpan = document.getElementById('creditorCurrency_' + creditorId);
    const exchangeRateInput = document.getElementById('exchangeRate_' + creditorId);
    const helpText = document.getElementById('exchangeRateHelp_' + creditorId);

    if (selectedCurrency !== creditorCurrency) {
        exchangeRateDiv.style.display = 'block';
        exchangeRateInput.required = true;
        
        // Show rate in the most natural direction
        if (creditorCurrency === 'AFS') {
            // 1 [payment] = ? AFS → multiply
            baseSpan.textContent = selectedCurrency;
            targetSpan.textContent = 'AFS';
            helpText.textContent = 'Enter the rate for 1 ' + selectedCurrency + ' = ? AFS';
        } else if (selectedCurrency === 'AFS') {
            // 1 [creditor] = ? AFS → divide
            baseSpan.textContent = creditorCurrency;
            targetSpan.textContent = 'AFS';
            helpText.textContent = 'Enter the rate for 1 ' + creditorCurrency + ' = ? AFS';
        } else {
            // 1 [creditor] = ? [payment] → divide
            baseSpan.textContent = creditorCurrency;
            targetSpan.textContent = selectedCurrency;
            helpText.textContent = 'Enter the rate for 1 ' + creditorCurrency + ' = ? ' + selectedCurrency;
        }
    } else {
        exchangeRateDiv.style.display = 'none';
        exchangeRateInput.required = false;
        exchangeRateInput.value = '';
    }
}
