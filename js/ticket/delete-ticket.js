function deleteTicket(id) {
    if (confirm('Are you sure you want to delete this ticket?')) {
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
        });
    }
} 
