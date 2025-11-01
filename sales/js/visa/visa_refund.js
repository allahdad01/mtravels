// Function to open refund modal
function openRefundModal(visaId, amount, profit, currency) {
    // Fetch visa details including exchange rate
    fetch(`get_visa_details.php?id=${visaId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const visa = data.visa;
                
                // Set values in the refund modal
                document.getElementById('refundVisaId').value = visaId;
                document.getElementById('refundTotalAmount').value = amount;
                document.getElementById('refundProfitAmount').value = profit;
                document.getElementById('refundCurrency').value = currency;
                
                // Display values in readable format
                document.getElementById('refundVisaAmount').value = parseFloat(amount).toFixed(2);
                document.getElementById('refundVisaProfit').value = parseFloat(profit).toFixed(2);
                
                // Set currency labels
                document.getElementById('refundCurrencyLabel').textContent = currency;
                document.getElementById('refundProfitCurrencyLabel').textContent = currency;
                document.getElementById('partialRefundCurrencyLabel').textContent = currency;

                
                // Reset form elements
                document.getElementById('refundReason').value = '';
                document.getElementById('partialRefundAmount').value = '';
                document.getElementById('fullRefund').checked = true;
                document.getElementById('partialRefundAmountGroup').style.display = 'none';
                
                // Show the modal
                $('#refundVisaModal').modal('show');
            } else {
                alert('error_fetching_visa_details: ' + (data.message || 'unknown_error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('error_fetching_visa_details');
        });
}


// Toggle partial refund amount field based on refund type selection
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('input[name="refund_type"]').forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'partial') {
                document.getElementById('partialRefundAmountGroup').style.display = 'block';
            } else {
                document.getElementById('partialRefundAmountGroup').style.display = 'none';
            }
        });
    });

    // Process refund button click handler
    document.getElementById('processRefundBtn').addEventListener('click', function() {
        const refundType = document.querySelector('input[name="refund_type"]:checked').value;
        const visaId = document.getElementById('refundVisaId').value;
        const totalAmount = parseFloat(document.getElementById('refundTotalAmount').value);
        const profitAmount = parseFloat(document.getElementById('refundProfitAmount').value);
        const currency = document.getElementById('refundCurrency').value;
        const reason = document.getElementById('refundReason').value;
        
        // Validate form
        if (!reason.trim()) {
            alert('please_provide_a_reason_for_the_refund');
            return;
        }
        
        
        let refundAmount = 0;
        
        if (refundType === 'full') {
            // Full refund is the total amount
            refundAmount = totalAmount;
        } else {
            // Partial refund - get amount from input
            refundAmount = parseFloat(document.getElementById('partialRefundAmount').value);
            
            // Validate partial refund amount
            if (isNaN(refundAmount) || refundAmount < 0) {
                alert('please_enter_a_valid_refund_amount_greater_than_zero');
                return;
            }
            
            if (refundAmount > totalAmount) {
                alert('refund_amount_cannot_exceed_the_total_visa_amount');
                return;
            }
        }
        
        // Disable the button to prevent double submission
        this.disabled = true;
        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> processing...';
        
        // Prepare form data
        const formData = new FormData();
        formData.append('visa_id', visaId);
        formData.append('refund_type', refundType);
        formData.append('refund_amount', refundAmount);
        formData.append('reason', reason);
        formData.append('currency', currency);
        
        // Send AJAX request to process refund
        fetch('process_visa_refund.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('refund_processed_successfully');
                // Close the current modal
                $('#refundVisaModal').modal('hide');
                // Reload the page to refresh the visa data
                location.reload();
            } else {
                alert('error_processing_refund: ' + data.message);
                // Re-enable the button
                this.disabled = false;
                this.innerHTML = 'process_refund';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('an_error_occurred_while_processing_the_refund');
            // Re-enable the button
            this.disabled = false;
            this.innerHTML = 'process_refund';
        });
    });
});