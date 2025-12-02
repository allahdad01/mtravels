// View transaction function
function viewTransaction(transactionId) {
    // Implement view transaction functionality
    alert('View transaction functionality to be implemented');
}

// Process refund transaction function
function processRefundTransaction(refundId) {
    // Show loading state
    $('#refundTransactionModal .modal-content').addClass('loading');
    
    // Fetch refund details
    $.ajax({
        url: 'get_umrah_refund_details.php',
        type: 'GET',
        data: { id: refundId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const refund = response.refund;

                // Store refund currency globally for exchange rate field logic
                window.refundCurrency = refund.currency || 'USD';

                // Set form values
                $('#refund_id').val(refundId);
                $('#transactionBookingId').text(refund.booking_id);
                $('#refundType').text(refund.refund_type === 'full' ? 'full_refund' : 'partial_refund');
                $('#refundReason').text(refund.reason || 'N/A');
                $('#refundGuest').text(refund.name || 'N/A');
                $('#refundUmrah').text(refund.package_type || 'N/A');
                
                // Calculate exchanged amount
                const amount = parseFloat(refund.refund_amount);
                const exchangeRate = parseFloat(refund.exchange_rate || 1);
                const exchangedAmount = refund.currency === 'USD' ? 
                    amount * exchangeRate : 
                    amount / exchangeRate;
                
                // Update total amount with currency
                const totalAmountElement = $('#totalAmount');
                totalAmountElement.text(parseFloat(amount).toFixed(2));
                totalAmountElement.closest('.financial-summary-value').html(`${refund.currency} ${totalAmountElement.text()}`);

                $('#exchangeRateDisplay').text(parseFloat(exchangeRate).toFixed(5));
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
                const description = `Refund payment for Umrah Booking #${refund.booking_id} - ${refund.name}`;
                $('#paymentDescription').val(description);
                
                // Load transaction history
                transactionManager.loadTransactionHistory(refundId);
                
                // Remove loading state and show modal
                $('#refundTransactionModal .modal-content').removeClass('loading');
                $('#refundTransactionModal').modal('show');
            } else {
                alert('Error fetching refund details: ' + (response.message || 'Unknown error'));
                $('#refundTransactionModal .modal-content').removeClass('loading');
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            alert('Error fetching refund details');
            $('#refundTransactionModal .modal-content').removeClass('loading');
        }
    });
}

function printRefundAgreement(refundId) {
    // Open the printable agreement page in a new window
    window.open('print_umrah_refund.php?id=' + refundId, '_blank');
}


    // Enhanced delete refund with confirmation
    function deleteRefund(refundId) {
        Swal.fire({
            title: 'are_you_sure',
            text: 'you_cannot_revert_this_action',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'yes_delete_it',
            cancelButtonText: 'cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                // Actual delete logic
                fetch('delete_umrah_refund.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ id: refundId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire(
                            'deleted',
                            'refund_deleted_successfully',
                            'success'
                        ).then(() => location.reload());
                    } else {
                        Swal.fire(
                            'error',
                            data.message || 'failed_to_delete_refund',
                            'error'
                        );
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire(
                        'error',
                        'network_error_occurred',
                        'error'
                    );
                });
            }
        });
    }