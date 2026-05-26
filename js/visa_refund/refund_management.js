 // Delete refund function
 function deleteRefund(refundId) {
    if (!confirm('are_you_sure_you_want_to_delete_this_refund')) {
        return;
    }

    // Get the delete button/link that was clicked
    const clickedBtn = $(`a[onclick="deleteRefund(${refundId})"]`);
    const originalContent = clickedBtn.html();
    
    // Disable button and show loading state
    clickedBtn.prop('disabled', true);
    clickedBtn.html('<i class="feather icon-loader"></i>');
    
    // Show loading state on row
    const row = clickedBtn.closest('tr');
    row.addClass('loading');

    // Get CSRF token from meta tag or hidden input
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ||
                     document.querySelector('input[name="csrf_token"]')?.value;

    // Send delete request
    fetch('../api/visa/delete_visa_refund.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ id: refundId, csrf_token: csrfToken })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('refund_deleted_successfully');
            // Reload the page to refresh the table
            window.location.reload();
        } else {
            // Re-enable button on error
            clickedBtn.prop('disabled', false);
            clickedBtn.html(originalContent);
            alert('error_deleting_refund: ' + (data.message || 'unknown_error'));
            row.removeClass('loading');
        }
    })
    .catch(error => {
        // Re-enable button on error
        clickedBtn.prop('disabled', false);
        clickedBtn.html(originalContent);
        alert('error_deleting_refund');
        row.removeClass('loading');
    });
}

function printRefundAgreement(refundId) {
    // Open the printable agreement page in a new window
    window.open('../api/visa/print_visa_refund.php?id=' + refundId, '_blank');
}
