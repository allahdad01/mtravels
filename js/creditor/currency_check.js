// Function to check currency and show/hide exchange rate field for creditors
function checkCreditorCurrency(selectElement, creditorCurrency, creditorId) {
    const selectedCurrency = selectElement.value;
    const exchangeRateDiv = document.getElementById('exchangeRateDiv_' + creditorId);
    const selectedCurrencySpan = document.getElementById('selectedCreditorCurrency_' + creditorId);
    const creditorCurrencySpan = document.getElementById('creditorCurrency_' + creditorId);
    const exchangeRateInput = document.getElementById('exchangeRate_' + creditorId);

    if (selectedCurrency !== creditorCurrency) {
        // Show exchange rate field
        exchangeRateDiv.style.display = 'block';
        selectedCurrencySpan.textContent = selectedCurrency;
        creditorCurrencySpan.textContent = creditorCurrency;
        exchangeRateInput.required = true;
    } else {
        // Hide exchange rate field
        exchangeRateDiv.style.display = 'none';
        exchangeRateInput.required = false;
        exchangeRateInput.value = '';
    }
}
