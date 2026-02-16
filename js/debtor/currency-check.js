// Function to check currency and show/hide exchange rate field
function checkCurrency(selectElement, debtorCurrency, debtorId) {
    const selectedCurrency = selectElement.value;
    const exchangeRateDiv = document.getElementById('exchangeRateDiv' + debtorId);
    const selectedCurrencySpan = document.getElementById('selectedCurrency' + debtorId);
    const debtorCurrencySpan = document.getElementById('debtorCurrency' + debtorId);
    const exchangeRateInput = document.getElementById('exchangeRate' + debtorId);

    if (selectedCurrency !== debtorCurrency) {
        // Show exchange rate field
        exchangeRateDiv.style.display = 'block';
        selectedCurrencySpan.textContent = selectedCurrency;
        debtorCurrencySpan.textContent = debtorCurrency;
        exchangeRateInput.required = true;
    } else {
        // Hide exchange rate field
        exchangeRateDiv.style.display = 'none';
        exchangeRateInput.required = false;
        exchangeRateInput.value = '';
    }
}
