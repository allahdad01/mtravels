// Refund Modal Scripts
document.addEventListener('DOMContentLoaded', function() {
    const refundForm = document.getElementById('refundForm');
    const refundTypeInputs = refundForm.querySelectorAll('input[name="refund_type"]');
    const refundAmountGroup = document.getElementById('refundAmountGroup');
    const refundAmount = document.getElementById('refund_amount');
    const maxRefundAmount = document.getElementById('maxRefundAmount');
    const currentRate = document.getElementById('currentRate');

    // Toggle refund amount field
    function toggleRefundAmount() {
        const selectedType = refundForm.querySelector('input[name="refund_type"]:checked').value;
        refundAmountGroup.style.display = selectedType === 'partial' ? 'block' : 'none';
        
        if (selectedType === 'partial') {
            refundAmount.setAttribute('required', '');
        } else {
            refundAmount.removeAttribute('required');
        }
    }

    // Initialize refund modal
    function initRefundModal(amount, profit, currency) {
        // Update display values
        document.getElementById('displayOriginalAmount').textContent = formatCurrency(amount, currency);
        document.getElementById('displayOriginalProfit').textContent = formatCurrency(profit, currency);
        maxRefundAmount.textContent = formatCurrency(amount, currency);
        
        // Set currency symbol
        const currencySymbol = currency === 'USD' ? '$' : 'AFS';
        document.getElementById('refundCurrencySymbol').textContent = currencySymbol;
        
        // Set max refund amount
        refundAmount.max = amount;
        
        // Reset form
        refundForm.reset();
        refundForm.classList.remove('was-validated');
        
    }

    // Validate refund amount
    refundAmount.addEventListener('input', function() {
        const max = parseFloat(this.max);
        const value = parseFloat(this.value);
        
        if (value > max) {
            this.value = max;
        }
    });

    // Form validation
    refundForm.addEventListener('submit', function(event) {
        if (!this.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        this.classList.add('was-validated');
    });

    // Expose functions
    window.toggleRefundAmount = toggleRefundAmount;
    window.initRefundModal = initRefundModal;
});