document.getElementById('addVisaForm').addEventListener('submit', function (event) {
    event.preventDefault(); // Prevent default form submission
    const formData = new FormData(this); // Collect form data
    
    // Disable the submit button
    const submitButton = this.querySelector('button[type="submit"]');
    submitButton.disabled = true;
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> processing...';

    fetch('add_visa.php', {
    method: 'POST',
    body: formData
})
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            alert(data.message);
            location.reload();
        } else {
            alert('Error: ' + data.message);
            // Re-enable the button if there's an error
            submitButton.disabled = false;
            submitButton.innerHTML = 'add_visa';
        }
    })
    .catch(error => {
        console.error('Error:', error);
            alert('an_unexpected_error_occurred');
        // Re-enable the button if there's an error
        submitButton.disabled = false;
        submitButton.innerHTML = 'add_visa';
    });

});