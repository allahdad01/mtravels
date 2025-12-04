
$(document).ready(function() {
    $('#refundsTable').DataTable({
        responsive: true,
        pageLength: 25,
        lengthChange: true,
        searching: true,
        ordering: true,
        paging: false,  // Disable DataTables pagination
        columns: [
            { width: '5%' },   // ID
            { width: '20%' },  // Visa Details
            { width: '15%' },  // Refund Info
            { width: '10%' },  // Amount
            { width: '15%' },  // Date
            { width: '15%' }   // Actions
        ]
    });
});

function deleteRefund(refundId) {
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('../api/visa/delete_visa_refund.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ id: refundId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire(
                        'Deleted!',
                        data.message,
                        'success'
                    ).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire(
                        'Error!',
                        data.message,
                        'error'
                    );
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire(
                    'Error!',
                    'An error occurred while deleting the refund.',
                    'error'
                );
            });
        }
    });
}