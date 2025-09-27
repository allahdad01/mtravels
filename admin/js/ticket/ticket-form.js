document.getElementById('bookTicketForm').addEventListener('submit', function (event) {
    event.preventDefault(); // Prevent default form submission
    const formData = new FormData(this); // Collect form data

    fetch('save_ticket.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json()) // Parse JSON response
    .then(data => {
        if (data.status === 'success') { // Check for status
            showToast(data.message, 'success'); // Show success toast
            location.reload(); // Reload page
        } else {
            // Use translated error message from PHP
            showToast(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error); // Log error
        // Use translated error message from PHP
        showToast('An unexpected error occurred', 'error');
    });
}); 