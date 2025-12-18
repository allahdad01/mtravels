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
            if (data.success) {
                showToast('Ticket deleted successfully', 'success');
                location.reload();
            } else {
                showToast(data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error deleting ticket:', error);
            showToast('An error occurred while deleting the ticket', 'error');
        });
    }
} 