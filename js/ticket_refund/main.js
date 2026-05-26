function printRefundAgreement(ticketId) {
    if (!ticketId) {
        alert('Ticket id is missing');
        return;
    }

    // Open the printable agreement page in a new window
    window.open('../api/ticket_refund/print_ticket_refund_agreement.php?id=' + ticketId, '_blank');
}

function deleteTicket(id) {
    // Get the button that was clicked
    const clickedBtn = event?.target?.closest('button') || document.activeElement;
    let originalContent = '';
    
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
            // Store original content and show loading state if button found
            if (clickedBtn && clickedBtn.tagName === 'BUTTON') {
                originalContent = clickedBtn.innerHTML;
                clickedBtn.disabled = true;
                clickedBtn.innerHTML = '<i class="feather icon-loader"></i>';
            }
            
            // Make the fetch request
            fetch('../api/ticket_refund/delete_ticket_rf.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id }),
            })
            .then(response => response.json())
            .then(data => {
                // Restore button state if button was found
                if (clickedBtn && clickedBtn.tagName === 'BUTTON' && originalContent) {
                    clickedBtn.disabled = false;
                    clickedBtn.innerHTML = originalContent;
                }
                
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
                // Restore button state if button was found
                if (clickedBtn && clickedBtn.tagName === 'BUTTON' && originalContent) {
                    clickedBtn.disabled = false;
                    clickedBtn.innerHTML = originalContent;
                }
                
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
