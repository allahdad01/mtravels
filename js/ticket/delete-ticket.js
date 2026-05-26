function deleteTicket(id, btnElement) {
    if (confirm('Are you sure you want to delete this ticket?')) {
        // Get the button that was clicked if not passed directly
        const clickedBtn = btnElement || event?.target?.closest('button') || document.activeElement;
        let originalContent = '';
        
        // Store original content and show loading state if button found
        if (clickedBtn && clickedBtn.tagName === 'BUTTON') {
            originalContent = clickedBtn.innerHTML;
            clickedBtn.disabled = true;
            clickedBtn.innerHTML = '<i class="feather icon-loader"></i>';
        }
        
        // Get CSRF token from meta tag or hidden input
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ||
                         document.querySelector('input[name="csrf_token"]')?.value;
        
        fetch('../api/ticket/delete_ticket.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, csrf_token: csrfToken }),
        })
        .then(response => response.json())
        .then(data => {
            if (data.success || data.status === 'success') {
                showToast('Ticket deleted successfully', 'success');
                setTimeout(() => {
                    refreshTicketTable();
                }, 1000);
            } else {
                showToast(data.message || 'Error deleting ticket', 'error');
            }
        })
        .catch(error => {
            showToast('An error occurred while deleting the ticket', 'error');
        })
        .finally(() => {
            // Restore button state if button was found
            if (clickedBtn && clickedBtn.tagName === 'BUTTON' && originalContent) {
                clickedBtn.disabled = false;
                clickedBtn.innerHTML = originalContent;
            }
        });
    }
}
