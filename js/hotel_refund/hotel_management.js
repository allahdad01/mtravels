// Function to show toast
function showToast(message, type = 'success') {
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: type,
        title: message,
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true
    });
}
// Delete refund function
function deleteRefund(refundId) {
    if (!confirm('are_you_sure_you_want_to_delete_this_refund')) {
        return;
    }

    // Show loading state
    const row = $(`a[onclick="deleteRefund(${refundId})"]`).closest('tr');
    row.addClass('loading');

    // Send delete request
    fetch('delete_hotel_refund.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ id: refundId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('refund_deleted_successfully');
            // Reload the page to refresh the table
            window.location.reload();
        } else {
            showToast('error_deleting_refund: ' + (data.message || 'unknown_error'));
            row.removeClass('loading');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('error_deleting_refund');
        row.removeClass('loading');
    });
}

    // View transaction function
    function viewTransaction(transactionId) {
        // Implement view transaction functionality
        showToast('view_transaction_functionality_to_be_implemented');
    }

    // Process refund transaction function
    function processRefundTransaction(refundId) {
        // Show loading state
        $('#refundTransactionModal .modal-content').addClass('loading');
        
        // Fetch refund details
        $.ajax({
            url: 'get_hotel_refund_details.php',
            type: 'GET',
            data: { id: refundId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const refund = response.refund;
                    
                    // Set form values
                    $('#refund_id').val(refundId);
                    $('#transactionBookingId').text(refund.booking_id);
                    $('#refundType').text(refund.refund_type === 'full' ? 'Full Refund' : 'Partial Refund');
                    $('#refundReason').text(refund.reason || 'N/A');
                    $('#refundGuest').text(refund.title + ' ' + refund.first_name + ' ' + refund.last_name || 'N/A');
                    $('#refundHotel').text(refund.accommodation_details || 'N/A');
                    $('#totalAmount').text(`${refund.currency} ${parseFloat(refund.refund_amount).toFixed(2)}`);
                    $('#exchangeRateDisplay').text(parseFloat(refund.exchange_rate || 1).toFixed(4));
                    
                    // Calculate exchanged amount
                    const amount = parseFloat(refund.refund_amount);
                    const exchangeRate = parseFloat(refund.exchange_rate || 1);
                    const exchangedAmount = refund.currency === 'USD' ? 
                        amount * exchangeRate : 
                        amount / exchangeRate;
                    
                    $('#exchangedAmount').text(
                        `${refund.currency === 'USD' ? 'AFS' : 'USD'} ${exchangedAmount.toFixed(2)}`
                    );
                    
                    // Store original amounts for currency conversion
                    $('#paymentAmount')
                        .data('usd-amount', refund.currency === 'USD' ? amount : exchangedAmount)
                        .data('afs-amount', refund.currency === 'USD' ? exchangedAmount : amount)
                        .val(amount.toFixed(2));
                    
                    // Set default currency
                    $('#paymentCurrency').val(refund.currency);
                    
                    // Generate default description
                    const description = `Refund payment for Hotel Booking #${refund.booking_id} - ${refund.title} ${refund.first_name} ${refund.last_name}`;
                    $('#paymentDescription').val(description);
                    
                    // Load transaction history
                    transactionManager.loadTransactionHistory(refundId);
                    
                    // Remove loading state and show modal
                    $('#refundTransactionModal .modal-content').removeClass('loading');
                    $('#refundTransactionModal').modal('show');
                } else {
                    showToast('error_fetching_refund_details: ' + (response.message || 'unknown_error'));
                    $('#refundTransactionModal .modal-content').removeClass('loading');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                showToast('error_fetching_refund_details');
                $('#refundTransactionModal .modal-content').removeClass('loading');
            }
        });
    }

    // Add this new function for printing refund agreement
    function printRefundAgreement(refundId) {
        // Open the printable agreement page in a new window
        window.open('generate_refund_agreement.php?refund_id=' + refundId, '_blank');
    }