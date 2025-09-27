/**
 * Refund Management Module
 */
            // Function to show toast
            function showToast(message, type = 'success') {
                // Use SweetAlert2 if available, otherwise fallback to alert
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: type,
                        title: message,
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true
                    });
                } else {
                    alert(message);
                }
            }
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
        
        const formData = new FormData(this);
        const refundType = formData.get('refund_type');
        const exchangeRate = parseFloat($('#exchange_rate').val());
        const originalAmount = parseFloat($('#refund_original_amount').val());
        
        // Validate refund amount for partial refunds
        if (refundType === 'partial') {
            const refundAmount = parseFloat(formData.get('refund_amount'));
            if (!refundAmount || refundAmount < 0 || refundAmount > originalAmount) {
                showToast('Please enter a valid refund amount between 0 and ' + originalAmount);
                return;
            }
        }
        
        // Send AJAX request
        $.ajax({
            url: 'process_hotel_refund.php',
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
                        showToast('Error: ' + (result.message || 'Failed to process refund'));
                    }
                } catch (e) {
                    console.error('Error parsing response:', e);
                    showToast('Error processing the refund request');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                showToast('Error processing refund');
            }
        });
    });
});

// Export functions for global access
window.openRefundModal = openRefundModal;
window.toggleRefundAmount = toggleRefundAmount; 