$(document).on('click', '.generate-invoice', function(e) {
    e.preventDefault();
    const ticketId = $(this).data('ticket-id');
    if (!ticketId) {
        alert('Ticket ID is missing');
        return;
    }
    window.location.href = `generateInvoice.php?ticketId=${ticketId}`;
}); 