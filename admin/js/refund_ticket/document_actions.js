// Print refund agreement
function printRefundAgreement(ticketId) {
    if (!ticketId) {
        alert(window.translations.ticket_id_is_missing);
        return;
    }
    
    // Fetch the agreement and print it
    fetch(`print_ticket_refund_agreement.php?id=${ticketId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Open the generated PDF in a new window
                const fileUrl = '../' + data.data.file_url;
                window.open(fileUrl, '_blank');
            } else {
                alert(window.translations.error + ': ' + (data.message || window.translations.failed_to_generate_agreement));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert(window.translations.error_generating_agreement);
        });
}

// Delete ticket
function deleteTicket(id) {
    if (confirm(window.translations.are_you_sure_you_want_to_delete_this_ticket)) {
        fetch('delete_ticket_rf.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id }),
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert(window.translations.error + ': ' + data.message);
            }
        })
        .catch(error => console.error('Error deleting Ticket:', error));
    }
}

// Generate invoice click handler
$(document).on('click', '.generate-invoice', function () {
    const ticketId = $(this).data('ticket-id');
    if (!ticketId) {
        alert(window.translations.ticket_id_is_missing);
        return;
    }
    window.location.href = `rt_generateInvoice.php?ticketId=${ticketId}`;
}); 