function printRefundAgreement(ticketId) {
    if (!ticketId) {
        alert('Ticket id is missing');
        return;
    }

    // Open the printable agreement page in a new window
    window.open('../api/ticket_refund/print_ticket_refund_agreement.php?id=' + ticketId, '_blank');
}

function deleteTicket(id) {
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            // Make the fetch request
            fetch('../api/ticket_refund/delete_ticket_rf.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id }),
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: data.message || 'Ticket deleted successfully'
                    }).then(() => {
                        location.reload(); // Or call a table reload function instead of full reload
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Error deleting ticket'
                    });
                }
            })
            .catch(error => {

                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'An unexpected error occurred'
                });
            });
        }
    });
}
// Search functionality
$(document).ready(function() {
    $("#ticketSearch").on("keyup", function() {
        var value = $(this).val().toLowerCase();
        $("table tbody tr").filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
        });
    });
});
