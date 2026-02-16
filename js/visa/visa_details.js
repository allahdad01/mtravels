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

        $('#detailsModal').data('visa-id', visa.id); // Storing visa id in the modal itself

        // Show/hide approve button based on status
        const approveBtn = document.getElementById('approveVisaBtn');
        if (visa.status === 'Pending') {
            approveBtn.style.display = 'inline-block';
        } else {
            approveBtn.style.display = 'none';
        }

        // Show the modal
        $('#detailsModal').modal('show');
    });
});

// Approve Visa Button Handler
document.getElementById('approveVisaBtn')?.addEventListener('click', function () {
    const visaId = $('#detailsModal').data('visa-id');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || 
                     document.querySelector('input[name="csrf_token"]')?.value;

    if (!confirm('Are you sure you want to approve this visa application? This will process all transactions.')) {
        return;
    }

    const formData = new FormData();
    formData.append('visa_id', visaId);
    formData.append('csrf_token', csrfToken);

    fetch('../api/visa/approve_visa.php', {
        method: 'POST',
        body: formData,
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Visa approved successfully! Transactions processed.', 'success');
            $('#detailsModal').modal('hide');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('Error: ' + (data.error || data.message), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('An error occurred while approving visa', 'error');
    });
});

function deleteVisa(id) {
    if (confirm('Are you sure you want to delete this Visa?')) {
        // Get CSRF token from meta tag or hidden input
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || 
                         document.querySelector('input[name="csrf_token"]')?.value;
        
        fetch('../api/visa/delete_visa.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, csrf_token: csrfToken }),
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

    }
}
