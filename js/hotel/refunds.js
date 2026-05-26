/**
 * Refund Management Module
 */
// Toast notifications are handled by the global showToast function
// Function to open refund modal
function openRefundModal(bookingId, amount, profit, currency) {
    // Set hidden fields
    $('#refund_booking_id').val(bookingId);
    $('#refund_original_amount').val(amount);
    $('#refund_original_profit').val(profit);
    $('#refund_currency').val(currency);
    
    // Display values in the modal
    $('#displayOriginalAmount').text(currency + ' ' + amount.toFixed(2));
    $('#displayOriginalProfit').text(currency + ' ' + profit.toFixed(2));
    
    // Set default exchange rate
    $('#exchange_rate').val('89.5000').prop('readonly', false);
    
    // Reset form
    $('#refundForm')[0].reset();
    $('#refundAmountGroup').hide();
    
    // Show modal
    $('#refundModal').modal('show');
}

// Function to toggle refund amount field
function toggleRefundAmount() {
    const refundType = $('#refund_type').val();
    const amountGroup = $('#refundAmountGroup');
    const amountInput = $('#refund_amount');
    
    if (refundType === 'partial') {
        amountGroup.show();
        amountInput.prop('required', true);
        const maxAmount = parseFloat($('#refund_original_amount').val());
        amountInput.attr('max', maxAmount);
    } else {
        amountGroup.hide();
        amountInput.prop('required', false);
    }
}

// Initialize when document is ready
$(document).ready(function() {
    // Handle refund form submission
    $('#refundForm').on('submit', function(e) {
        e.preventDefault();
        
        // Disable submit button to prevent multiple clicks
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.html();
        submitBtn.prop('disabled', true);
        submitBtn.html('<i class="feather icon-loader mr-2 spinner-border spinner-border-sm" role="status" aria-hidden="true"></i>Processing...');
        
        const formData = new FormData(this);
        const refundType = formData.get('refund_type');
        const exchangeRate = parseFloat($('#exchange_rate').val());
        const originalAmount = parseFloat($('#refund_original_amount').val());
        
        // Validate refund amount for partial refunds
        if (refundType === 'partial') {
            const refundAmount = parseFloat(formData.get('refund_amount'));
            if (!refundAmount || refundAmount < 0 || refundAmount > originalAmount) {
                // Re-enable button on validation error
                submitBtn.prop('disabled', false);
                submitBtn.html(originalText);
                showToast('Please enter a valid refund amount between 0 and ' + originalAmount);
                return;
            }
        }
        
        // Send AJAX request
        $.ajax({
            url: '../api/hotel/process_hotel_refund.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                try {
                    const result = typeof response === 'string' ? JSON.parse(response) : response;
                    if (result.success) {
                        showToast('Refund processed successfully');
                        $('#refundModal').modal('hide');
                        location.reload(); // Reload to show updated data
                    } else {
                        // Re-enable button on error
                        submitBtn.prop('disabled', false);
                        submitBtn.html(originalText);
                        showToast('Error: ' + (result.message || 'Failed to process refund'));
                    }
                } catch (e) {
                    // Re-enable button on error
                    submitBtn.prop('disabled', false);
                    submitBtn.html(originalText);
                    showToast('Error processing the refund request');
                }
            },
            error: function(xhr, status, error) {
                // Re-enable button on error
                submitBtn.prop('disabled', false);
                submitBtn.html(originalText);
                showToast('Error processing refund');
            }
        });
    });
});

// Export functions for global access
window.openRefundModal = openRefundModal;
window.toggleRefundAmount = toggleRefundAmount; 
