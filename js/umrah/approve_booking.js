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
    Swal.fire({
        title: 'Approving Booking...',
        html: `Processing <strong>${memberName}</strong>. This creates supplier and client transactions, please wait...`,
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => Swal.showLoading()
    });

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
                Swal.close();
                showToast('success', `Booking for ${memberName} approved successfully!`);
                
                // Reload the page after a short delay to refresh the member list
                setTimeout(() => {
                    location.reload();
                }, 3000);
            } else {
                Swal.close();
                showToast('error', data.error || data.message || 'Failed to approve booking');
                console.error('Error details:', data);
            }
        } catch (e) {
            Swal.close();
            console.error('Failed to parse response:', text);
            console.error('Parse error:', e);
            showToast('error', 'Invalid response from server');
        }
    })
    .catch(error => {
        Swal.close();
        showToast('error', 'An error occurred while approving the booking');
        console.error('Error:', error);
    });
}

/**
 * Bulk approve selected member bookings (pending members only).
 * Processes each booking sequentially, showing live progress.
 */
function bulkApproveSelected() {
    const selectedMembers = getSelectedMembers();
    
    if (selectedMembers.length === 0) {
        showToast('warning', 'Please select at least one member to approve.');
        return;
    }
    
    const pendingMembers = selectedMembers.filter(cb => (cb.dataset.status || '') === 'pending');
    
    if (pendingMembers.length === 0) {
        showToast('warning', 'Selected members are not pending approval.');
        return;
    }
    
    if (pendingMembers.length < selectedMembers.length) {
        showToast('info', `Approving ${pendingMembers.length} of ${selectedMembers.length} selected member(s). Others are not pending.`);
    }
    
    const names = pendingMembers.map(cb => {
        const card = cb.closest('.member-card');
        const nameEl = card ? card.querySelector('.member-name') : null;
        return nameEl ? nameEl.textContent.trim() : 'Member';
    });
    
    Swal.fire({
        title: 'Approve Bookings',
        html: `Are you sure you want to approve <strong>${pendingMembers.length}</strong> booking(s)?<br>This will create supplier and client transactions for each.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, Approve All',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            processBulkApprove(pendingMembers, names);
        }
    });
}

function processBulkApprove(pendingMembers, names) {
    const total = pendingMembers.length;
    let index = 0;
    let approvedCount = 0;
    let failedNames = [];
    
    const progressHtml = (memberName, current) =>
        `Approving <strong>${current}</strong> of ${total}: ${memberName}<br>` +
        `<div class="progress mt-2" style="height: 8px;">` +
        `<div class="progress-bar progress-bar-striped progress-bar-animated" style="width: ${(current / total) * 100}%; background-color: #28a745;"></div>` +
        `</div>`;
    
    Swal.fire({
        title: 'Approving Bookings...',
        html: progressHtml(names[0] || '...', 1),
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false
    });
    
    function approveNext() {
        if (index >= total) {
            if (failedNames.length > 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Approval Completed with Errors',
                    html: `<strong>${approvedCount}</strong> of ${total} approved.<br>Failed: ${failedNames.join(', ')}`,
                    confirmButtonText: 'OK'
                }).then(() => location.reload());
            } else {
                Swal.fire({
                    icon: 'success',
                    title: 'Approval Complete',
                    html: `<strong>${approvedCount}</strong> of ${total} booking(s) approved successfully.`,
                    confirmButtonText: 'OK'
                }).then(() => location.reload());
            }
            return;
        }
        
        const checkbox = pendingMembers[index];
        const bookingId = checkbox.dataset.bookingId;
        const name = names[index] || 'Member';
        
        const formData = new FormData();
        formData.append('booking_id', bookingId);
        formData.append('csrf_token', window.csrfToken);
        
        fetch('../api/umrah/approve_umrah_booking.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(text => {
            try {
                const data = JSON.parse(text);
                if (data.success) {
                    approvedCount++;
                } else {
                    failedNames.push(name);
                    console.error(`Approve failed for ${name}:`, data.error || data.message);
                }
            } catch (e) {
                failedNames.push(name);
                console.error(`Approve parse error for ${name}:`, text, e);
            }
            index++;
            Swal.update({
                html: progressHtml(index < total ? names[index] : name, index)
            });
            approveNext();
        })
        .catch(error => {
            failedNames.push(name);
            console.error(`Approve error for ${name}:`, error);
            index++;
            Swal.update({
                html: progressHtml(index < total ? names[index] : name, index)
            });
            approveNext();
        });
    }
    
    approveNext();
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
