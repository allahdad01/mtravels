document.getElementById('bookTicketForm').addEventListener('submit', function (event) {
    event.preventDefault(); // Prevent default form submission
    const formData = new FormData(this); // Collect form data
    const submitBtn = this.querySelector('button[type="submit"]'); // Get submit button
    const bookTicketForm = this; // Reference to form
    
    // Disable button and show processing state
    submitBtn.disabled = true;
    const originalButtonText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="feather icon-loader mr-2"></i>Processing...';

    fetch('../api/ticket/save_ticket.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json()) // Parse JSON response
    .then(data => {
        if (data.status === 'success') { // Check for status
            showToast(data.message, 'success'); // Show success toast
            
            // Reset form
            bookTicketForm.reset();
            
            // Close modal after a short delay to show the toast
            setTimeout(() => {
                $('#bookTicketModal').modal('hide');
                // Refresh the table
                refreshTicketTable();
                // Re-enable button for next booking
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalButtonText;
            }, 1500); // Wait for toast to be visible
        } else {
            // Use translated error message from PHP
            showToast(data.message, 'error');
            // Re-enable button on error
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalButtonText;
        }
    })
    .catch(error => {
        // Use translated error message from PHP
        showToast('An unexpected error occurred', 'error');
        // Re-enable button on error
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalButtonText;
    });
}); 
