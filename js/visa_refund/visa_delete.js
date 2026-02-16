
// DataTables removed - using server-side PHP filtering instead

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
            // Get CSRF token from meta tag or hidden input
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || 
                             document.querySelector('input[name="csrf_token"]')?.value;
            
            fetch('../api/visa/delete_visa_refund.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ id: refundId, csrf_token: csrfToken })
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

                Swal.fire(
                    'Error!',
                    'An error occurred while deleting the refund.',
                    'error'
                );
            });
        }
    });
}
