/**
 * Approve Umrah Booking - Transaction Processing
 * Handles approval of member bookings with supplier and client transactions
 */

function approveMemberBooking(bookingId, memberName) {
    Swal.fire({
        title: 'Approve Booking',
        text: `Are you sure you want to approve the booking for ${memberName}? This will create supplier and client transactions.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, Approve',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            processApproveBooking(bookingId, memberName);
        }
    });
}

function processApproveBooking(bookingId, memberName) {
    const formData = new FormData();
    formData.append('booking_id', bookingId);
    formData.append('csrf_token', window.csrfToken);

    fetch('../api/umrah/approve_umrah_booking.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        // Check if response is ok
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.text();
    })
    .then(text => {
        // Try to parse JSON
        try {
            const data = JSON.parse(text);
            if (data.success) {
                showToast('success', `Booking for ${memberName} approved successfully!`);
                
                // Reload the page after a short delay to refresh the member list
                setTimeout(() => {
                    location.reload();
                }, 3000);
            } else {
                showToast('error', data.error || data.message || 'Failed to approve booking');
                console.error('Error details:', data);
            }
        } catch (e) {
            console.error('Failed to parse response:', text);
            console.error('Parse error:', e);
            showToast('error', 'Invalid response from server');
        }
    })
    .catch(error => {
        showToast('error', 'An error occurred while approving the booking');
        console.error('Error:', error);
    });
}

/**
 * Display toast notification
 * @param {string} type - 'success', 'error', 'warning', 'info'
 * @param {string} message - The message to display
 */
function showToast(type, message) {
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true
    });
    
    Toast.fire({
        icon: type,
        title: message
    });
}
