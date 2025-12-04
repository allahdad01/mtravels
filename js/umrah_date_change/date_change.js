// Load date change requests
function loadDateChangeRequests(status = 'all') {
    $('#loadingIndicator').show();
    $('#noDataMessage').addClass('d-none');

    $.ajax({
        url: '../api/umrah/get_date_change_requests.php',
        type: 'GET',
        data: { status: status },
        success: function(response) {
            $('#loadingIndicator').hide();

            if (response.success && response.requests) {
                renderDateChangeRequests(response.requests);
                updateStatusCounts(response.counts);
            } else {
                $('#noDataMessage').removeClass('d-none');
            }
        },
        error: function() {
            $('#loadingIndicator').hide();
            $('#noDataMessage').removeClass('d-none');
        }
    });
}

// Render requests table
function renderDateChangeRequests(requests) {
    var tbody = $('#dateChangesTableBody');
    tbody.empty();

    if (requests.length === 0) {
        $('#noDataMessage').removeClass('d-none');
        return;
    }

    requests.forEach(function(request) {
        var priceChange = '';
        if (request.price_difference != 0) {
            var changeClass = request.price_difference > 0 ? 'text-danger' : 'text-success';
            var changeSymbol = request.price_difference > 0 ? '+' : '';
            priceChange = `<span class="${changeClass}">${changeSymbol}${request.price_difference} ${request.currency}</span>`;
        } else {
            priceChange = '<span class="text-muted">-</span>';
        }

        var row = `
            <tr>
                <td>#${request.id}</td>
                <td>
                    <strong>${request.passenger_name}</strong><br>
                    <small class="text-muted">Booking #${request.umrah_booking_id}</small>
                </td>
                <td>${request.family_name || 'N/A'}</td>
                <td>
                    <small>
                        <strong>Flight:</strong> ${request.old_flight_date || 'N/A'}<br>
                        <strong>Return:</strong> ${request.old_return_date || 'N/A'}<br>
                        <strong>Duration:</strong> ${request.old_duration || 'N/A'}
                    </small>
                </td>
                <td>
                    <small>
                        <strong>Flight:</strong> ${request.new_flight_date}<br>
                        <strong>Return:</strong> ${request.new_return_date}<br>
                        <strong>Duration:</strong> ${request.new_duration}
                    </small>
                </td>
                <td>${priceChange}</td>
                <td>
                    <span class="badge status-badge status-${request.status}">${request.status}</span>
                </td>
                <td>
                    <small>${new Date(request.created_at).toLocaleDateString()}</small>
                </td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="viewDateChangeDetails(${request.id})">
                        <i class="feather icon-eye"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger ml-1" onclick="deleteDateChangeRequest(${request.id})">
                        <i class="feather icon-trash-2"></i>
                    </button>
                </td>
            </tr>
        `;
        tbody.append(row);
    });
}

// Update status counts
function updateStatusCounts(counts) {
    $('#all-count').text(counts.all || 0);
    $('#pending-count').text(counts.pending || 0);
    $('#approved-count').text(counts.approved || 0);
    $('#completed-count').text(counts.completed || 0);
}

// View date change details
function viewDateChangeDetails(requestId) {
    $.ajax({
        url: '../api/umrah/get_date_change_details.php',
        type: 'GET',
        data: { id: requestId },
        success: function(response) {
            if (response.success) {
                $('#dateChangeDetailsContent').html(response.html);
                $('#actionButtons').html(response.action_buttons);
                $('#dateChangeDetailsModal').modal('show');
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: response.message || 'Failed to load request details',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 4000,
                    timerProgressBar: true
                });
            }
        },
        error: function() {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to load request details',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 4000,
                timerProgressBar: true
            });
        }
    });
}

// Approve date change request
function approveDateChangeRequest(requestId) {
    // Set the request ID in the modal
    $('#penaltyRequestId').val(requestId);

    // Clear previous values
    $('#modal_supplier_penalty').val('');
    $('#modal_service_penalty').val('');
    $('#modal_penalty_remarks').val('');

    // Show the penalty modal
    $('#penaltyModal').modal('show');
}

// Submit penalty approval
function submitPenaltyApproval() {
    const requestId = $('#penaltyRequestId').val();
    const supplierPenalty = parseFloat($('#modal_supplier_penalty').val()) || 0;
    const servicePenalty = parseFloat($('#modal_service_penalty').val()) || 0;
    const penaltyRemarks = $('#modal_penalty_remarks').val().trim();

    const totalPenalty = supplierPenalty + servicePenalty;

    // Show penalty summary as toast and proceed
    Swal.fire({
        icon: 'info',
        title: 'Processing Approval',
        html: `
            <div class="text-left">
                <p><strong>Penalty Summary:</strong></p>
                <ul class="list-unstyled">
                    <li>Supplier Penalty: $${supplierPenalty.toFixed(2)}</li>
                    <li>Service Penalty: $${servicePenalty.toFixed(2)}</li>
                    <li><strong>Total Penalty: $${totalPenalty.toFixed(2)}</strong></li>
                </ul>
                ${penaltyRemarks ? `<p><strong>Remarks:</strong> ${penaltyRemarks}</p>` : ''}
            </div>
        `,
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 2000,
        timerProgressBar: true
    });

    // Hide the penalty modal
    $('#penaltyModal').modal('hide');

    // Proceed with approval
    $.ajax({
        url: '../api/umrah/approve_date_change_request.php',
        type: 'POST',
        data: {
            id: requestId,
            supplier_penalty: supplierPenalty,
            service_penalty: servicePenalty,
            penalty_remarks: penaltyRemarks
        },
        success: function(response) {
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Approved',
                    text: response.message || 'Date change request approved successfully',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true
                });
                $('#dateChangeDetailsModal').modal('hide');
                loadDateChangeRequests();
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Approval Failed',
                    text: response.message || 'Failed to approve request',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 4000,
                    timerProgressBar: true
                });
            }
        },
        error: function() {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'An error occurred while approving the request',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 4000,
                timerProgressBar: true
            });
        }
    });
}

// Reject date change request
function rejectDateChangeRequest(requestId) {
    Swal.fire({
        title: 'Reject Date Change Request',
        input: 'textarea',
        inputLabel: 'Reason for rejection',
        inputPlaceholder: 'Please provide a reason for rejecting this request...',
        inputValidator: (value) => {
            if (!value) {
                return 'Reason is required!';
            }
        },
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Reject Request',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            // Show processing toast
            Swal.fire({
                icon: 'info',
                title: 'Processing Rejection',
                text: 'Rejecting date change request...',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 1500,
                timerProgressBar: true
            });

            $.ajax({
                url: '../api/umrah/reject_date_change_request.php',
                type: 'POST',
                data: {
                    id: requestId,
                    rejection_reason: result.value
                },
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Rejected',
                            text: response.message || 'Date change request rejected',
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 3000,
                            timerProgressBar: true
                        });
                        $('#dateChangeDetailsModal').modal('hide');
                        loadDateChangeRequests();
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Rejection Failed',
                            text: response.message || 'Failed to reject request',
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 4000,
                            timerProgressBar: true
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'An error occurred while rejecting the request',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 4000,
                        timerProgressBar: true
                    });
                }
            });
        }
    });
}

// Process date change (apply the changes)
function processDateChangeRequest(requestId) {
    // Show processing toast and proceed
    Swal.fire({
        icon: 'info',
        title: 'Processing Changes',
        text: 'Applying date changes to booking...',
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 1500,
        timerProgressBar: true
    });

    $.ajax({
        url: '../api/umrah/process_date_change_request.php',
        type: 'POST',
        data: { id: requestId },
        success: function(response) {
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Processed',
                    text: response.message || 'Date changes applied successfully',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true
                });
                $('#dateChangeDetailsModal').modal('hide');
                loadDateChangeRequests();
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Processing Failed',
                    text: response.message || 'Failed to process date changes',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 4000,
                    timerProgressBar: true
                });
            }
        },
        error: function() {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'An error occurred while processing the changes',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 4000,
                timerProgressBar: true
            });
        }
    });
}

// Delete date change request
function deleteDateChangeRequest(requestId) {
    // Show processing toast and proceed
    Swal.fire({
        icon: 'info',
        title: 'Deleting Request',
        text: 'Deleting date change request...',
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 1500,
        timerProgressBar: true
    });

    $.ajax({
        url: '../api/umrah/delete_date_change_request.php',
        type: 'POST',
        data: { id: requestId },
        success: function(response) {
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Deleted',
                    text: response.message || 'Date change request deleted successfully',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true
                });
                loadDateChangeRequests();
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Deletion Failed',
                    text: response.message || 'Failed to delete date change request',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 4000,
                    timerProgressBar: true
                });
            }
        },
        error: function() {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'An error occurred while deleting the request',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 4000,
                timerProgressBar: true
            });
        }
    });
}

// Initialize page
$(document).ready(function() {
    loadDateChangeRequests();

    // Handle tab clicks
    $('#statusTabs a').on('click', function(e) {
        e.preventDefault();
        var status = $(this).attr('href').substring(1); // Remove the # from href
        loadDateChangeRequests(status);
    });

    // Handle Enter key in penalty modal
    $('#penaltyModal input, #penaltyModal textarea').on('keypress', function(e) {
        if (e.which === 13) { // Enter key
            e.preventDefault();
            submitPenaltyApproval();
        }
    });
});