 // Delete refund function
 function deleteRefund(refundId) {
    if (!confirm('are_you_sure_you_want_to_delete_this_refund')) {
        return;
    }

    // Show loading state
    const row = $(`a[onclick="deleteRefund(${refundId})"]`).closest('tr');
    row.addClass('loading');

    // Send delete request
    fetch('delete_visa_refund.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ id: refundId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('refund_deleted_successfully');
            // Reload the page to refresh the table
            window.location.reload();
        } else {
            alert('error_deleting_refund: ' + (data.message || 'unknown_error'));
            row.removeClass('loading');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('error_deleting_refund');
        row.removeClass('loading');
    });
}

function printRefundAgreement(refundId) {
    // Open the printable agreement page in a new window
    window.open('print_visa_refund.php?id=' + refundId, '_blank');
}