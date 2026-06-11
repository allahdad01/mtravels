// Function to check currency and show/hide exchange rate field for creditors
function checkCreditorCurrency(selectElement, creditorCurrency, creditorId) {
    const selectedCurrency = selectElement.value;
    const exchangeRateDiv = document.getElementById('exchangeRateDiv_' + creditorId);
    const baseSpan = document.getElementById('selectedCreditorCurrency_' + creditorId);
    const targetSpan = document.getElementById('creditorCurrency_' + creditorId);
    const exchangeRateInput = document.getElementById('exchangeRate_' + creditorId);
    const helpText = document.getElementById('exchangeRateHelp_' + creditorId);

    const sampleRates = {
        'USD->AFS': 72.5, 'AFS->USD': 72.5,
        'USD->EUR': 0.92, 'EUR->USD': 1.09,
        'USD->DARHAM': 3.67, 'DARHAM->USD': 3.67,
        'AFS->EUR': 78.8, 'EUR->AFS': 78.8,
        'AFS->DARHAM': 19.75, 'DARHAM->AFS': 19.75,
        'EUR->DARHAM': 3.99, 'DARHAM->EUR': 3.99,
    };

    if (selectedCurrency !== creditorCurrency) {
        exchangeRateDiv.style.display = 'block';
        exchangeRateInput.required = true;

        let base, target;
        if (creditorCurrency === 'AFS') {
            base = selectedCurrency;
            target = 'AFS';
        } else if (selectedCurrency === 'AFS') {
            base = creditorCurrency;
            target = 'AFS';
        } else {
            base = creditorCurrency;
            target = selectedCurrency;
        }
        baseSpan.textContent = base;
        targetSpan.textContent = target;
        const rate = sampleRates[base + '->' + target];
        helpText.textContent = rate
            ? 'e.g. 1 ' + base + ' = ' + rate + ' ' + target + ' → enter ' + rate
            : 'Enter the rate for 1 ' + base + ' = ? ' + target;
    } else {
        exchangeRateDiv.style.display = 'none';
        exchangeRateInput.required = false;
        exchangeRateInput.value = '';
    }
}
