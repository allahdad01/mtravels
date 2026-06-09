
function printRefundAgreement(refundId) {
    window.open('../api/visa/print_visa_refund.php?id=' + refundId, '_blank');
}

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
            fetch('../api/visa/delete_visa_refund.php', {
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
                Swal.fire(
                    'error',
                    'network_error_occurred',
                    'error'
                );
            });
        }
    });
}
