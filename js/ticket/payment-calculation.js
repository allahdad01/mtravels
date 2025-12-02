// Set supplier currency when supplier changes
document.getElementById('supplier').addEventListener('change', function() {
    // This function is already handled by the existing get_supplier_currency.php call
    // Additionally update payment calculation when supplier or currency changes
    setTimeout(() => {
        const paymentCurrency = document.getElementById('paymentCurrency').value;
        const supplierCurrency = document.getElementById('curr').value;
        
        if (paymentCurrency === supplierCurrency) {
            document.getElementById('paymentAmount').value = document.getElementById('sold').value;
        } else {
            // Clear payment amount to require recalculation
            document.getElementById('paymentAmount').value = '';
        }
    }, 500); // Small timeout to wait for the supplier currency to be set
});

// Update payment amount when sold amount changes
document.getElementById('sold').addEventListener('input', function() {
    const paymentCurrency = document.getElementById('paymentCurrency').value;
    const supplierCurrency = document.getElementById('curr').value;
    
    if (paymentCurrency === supplierCurrency) {
        document.getElementById('paymentAmount').value = this.value;
    } else {
        // If currencies differ, don't auto-update but indicate recalculation is needed
        const currentPaymentAmount = document.getElementById('paymentAmount').value;
        if (currentPaymentAmount) {
            // Trigger calculation if there was already a value
            document.getElementById('calculatePayment').click();
        }
    }
}); 