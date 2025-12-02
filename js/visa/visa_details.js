document.querySelectorAll('.view-details').forEach(button => {
    button.addEventListener('click', function () {
        // Parse the ticket data from the data-ticket attribute
        const visa = JSON.parse(this.getAttribute('data-visa'));

        // Populate modal fields
        document.getElementById('paid-to').textContent = visa.paid_name;
        document.getElementById('country').textContent = visa.country;
        document.getElementById('visa-type').textContent = visa.visa_type;
        document.getElementById('created-by').textContent = visa.created_by;
        document.getElementById('receive-date').textContent = visa.receive_date;
        document.getElementById('applied-date').textContent = visa.applied_date;
        document.getElementById('issued-date').textContent = visa.issued_date;
        document.getElementById('sold-price').textContent = visa.sold;
        document.getElementById('base-price').textContent = visa.base;
        document.getElementById('profit').textContent = visa.profit;
        document.getElementById('currency').textContent = visa.currency;
        document.getElementById('phone').textContent = visa.phone;
        document.getElementById('gender').textContent = visa.gender;
        document.getElementById('description').textContent = visa.remarks;

        $('#detailsModal').data('visa-id', visa.id); // Storing ticketId in the modal itself

        // Show the modal
        $('#detailsModal').modal('show');
    });
});

      function deleteVisa(id) {
            if (confirm('Are you sure you want to delete this Visa?')) {
                fetch('delete_visa.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id }),
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Visa deleted successfully!');
                        location.reload(); // Refresh table
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => console.error('Error deleting Visa:', error));
            }
        }