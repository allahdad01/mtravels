function deleteTicket(id) {
    // Get the button that was clicked
    const clickedBtn = event?.target?.closest('button') || document.activeElement;
    let originalContent = '';
    
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this ticket!",
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
            
            fetch('../api/ticket_date_change/delete_ticket_dc.php', {
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
                    // Success toast
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: data.message || 'Ticket deleted successfully',
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true
                    }).then(() => {
                        location.reload(); // Or refresh table dynamically
                    });
                } else {
                    // Error toast
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'error',
                        title: data.message || 'Error deleting ticket',
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true
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
                    toast: true,
                    position: 'top-end',
                    icon: 'error',
                    title: 'An unexpected error occurred',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true
                });
            });
        }
    });
}

