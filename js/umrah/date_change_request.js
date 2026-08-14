// Date Change Modal Functions — single-step direct change
function openDateChangeModal(bookingId, passengerName, currentFlightDate, currentReturnDate, currentDuration, currentPrice, currency) {
    // Set modal data from the row (fallback while the authoritative dates load)
    document.getElementById('dateChangeBookingId').value = bookingId;
    document.getElementById('currentPassengerName').textContent = passengerName || '';
    document.getElementById('currentFlightDate').textContent = currentFlightDate || 'Not set';
    document.getElementById('currentReturnDate').textContent = currentReturnDate || 'Not set';
    document.getElementById('currentDuration').textContent = currentDuration || 'Not set';

    // Set current values as defaults for new fields
    document.getElementById('newFlightDate').value = currentFlightDate || '';
    document.getElementById('newReturnDate').value = currentReturnDate || '';
    document.getElementById('newDuration').value = currentDuration || '';

    // Reset penalties and reason
    document.getElementById('supplierPenalty').value = 0;
    document.getElementById('servicePenalty').value = 0;
    document.getElementById('changeReason').value = '';

    // Set currency display
    var currencyText = currency || 'USD';
    document.getElementById('penaltyCurrency').textContent = currencyText;
    document.getElementById('totalPenaltyCurrency').textContent = currencyText;
    updatePenaltyTotal();

    // Reset form validation state
    document.getElementById('dateChangeForm').classList.remove('was-validated');

    // Show modal
    $('#dateChangeModal').modal('show');

    // Fetch the authoritative current dates (booking row may be empty when the
    // flight service is fulfilled — dates then live on the flight fulfillment)
    $.ajax({
        url: '../api/umrah/get_booking_current_dates.php',
        type: 'GET',
        data: { booking_id: bookingId },
        success: function(response) {
            if (response.success) {
                var prevFlight = document.getElementById('newFlightDate').value;
                var prevReturn = document.getElementById('newReturnDate').value;
                var prevDuration = document.getElementById('newDuration').value;

                document.getElementById('currentFlightDate').textContent = response.flight_date || 'Not set';
                document.getElementById('currentReturnDate').textContent = response.return_date || 'Not set';
                document.getElementById('currentDuration').textContent = response.duration || 'Not set';

                // Only prefill the new-date fields while the user has not edited them
                if (prevFlight === (currentFlightDate || '')) {
                    document.getElementById('newFlightDate').value = response.flight_date || '';
                }
                if (prevReturn === (currentReturnDate || '')) {
                    document.getElementById('newReturnDate').value = response.return_date || '';
                }
                if (prevDuration === (currentDuration || '')) {
                    document.getElementById('newDuration').value = response.duration || '';
                }

                var currencyText = response.currency || 'USD';
                document.getElementById('penaltyCurrency').textContent = currencyText;
                document.getElementById('totalPenaltyCurrency').textContent = currencyText;
            }
        }
    });
}

// Live penalty total
function updatePenaltyTotal() {
    var supplier = parseFloat(document.getElementById('supplierPenalty').value) || 0;
    var service = parseFloat(document.getElementById('servicePenalty').value) || 0;
    document.getElementById('totalPenaltyDisplay').textContent = (supplier + service).toFixed(2);
}

// Auto-calculate duration when dates change
function recalculateDuration() {
    var flightDate = new Date(document.getElementById('newFlightDate').value);
    var returnDate = new Date(document.getElementById('newReturnDate').value);

    if (flightDate && returnDate && !isNaN(flightDate.getTime()) && !isNaN(returnDate.getTime()) && returnDate > flightDate) {
        var daysDiff = Math.ceil((returnDate.getTime() - flightDate.getTime()) / (1000 * 3600 * 24));
        document.getElementById('newDuration').value = daysDiff + ' Days';
    } else {
        document.getElementById('newDuration').value = '';
    }
}

$(document).on('change input', '#supplierPenalty, #servicePenalty', function() {
    updatePenaltyTotal();
});

$(document).on('change', '#newFlightDate, #newReturnDate', function() {
    recalculateDuration();
});

// Submit Date Change — applies directly (single step)
$(document).on('click', '#submitDateChangeRequest', function() {
    var form = document.getElementById('dateChangeForm');

    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    if (!document.getElementById('changeReason').value.trim()) {
        showToast('warning', 'Please provide a reason for the date change.');
        return;
    }

    // Show loading state
    var submitBtn = $(this);
    var originalHtml = submitBtn.html();
    submitBtn.html('<i class="feather icon-loader spinning"></i> Applying...').prop('disabled', true);

    $.ajax({
        url: '../api/umrah/submit_date_change_request.php',
        type: 'POST',
        data: new FormData(form),
        processData: false,
        contentType: false,
        success: function(response) {
            if (response.success) {
                showToast('success', response.message || 'Date change applied successfully.');
                setTimeout(function() {
                    $('#dateChangeModal').modal('hide');
                    location.reload();
                }, 1200);
            } else {
                showToast('error', response.message || 'Failed to apply date change.');
            }
        },
        error: function() {
            showToast('error', 'An error occurred while applying the date change. Please try again.');
        },
        complete: function() {
            submitBtn.html(originalHtml).prop('disabled', false);
        }
    });
});

// Revert (delete) a date change — restores the booking's previous dates,
// prices and balances, and removes the record from history
function revertDateChange(dateChangeId, bookingId) {
    Swal.fire({
        title: 'Are you sure?',
        text: 'This will restore the booking to its previous dates, revert prices and balances, and remove the change from history.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, revert it!'
    }).then((result) => {
        if (!result.isConfirmed) return;

        fetch('../api/umrah/delete_date_change_request.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id=' + encodeURIComponent(dateChangeId)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('success', data.message || 'Date change reverted.');
                loadDateChangeHistory(bookingId);
            } else {
                showToast('error', data.message || 'Failed to revert date change.');
            }
        })
        .catch(() => {
            showToast('error', 'An error occurred while reverting the date change. Please try again.');
        });
    });
}

// Load date change history for a booking
function loadDateChangeHistory(bookingId) {
    $.ajax({
        url: '../api/umrah/get_booking_date_changes.php',
        type: 'GET',
        data: { booking_id: bookingId },
        success: function(response) {
            if (response.success && response.history && response.history.length > 0) {
                let historyHtml = '<div class="table-responsive"><table class="table table-sm table-striped">';
                historyHtml += '<thead><tr><th>Date</th><th>Changes</th><th>Status</th><th>Penalty</th><th>Actions</th></tr></thead><tbody>';

                response.history.forEach(function(item) {
                    const date = new Date(item.created_at).toLocaleDateString();
                    const changes = [];

                    if (item.old_flight_date !== item.new_flight_date) {
                        changes.push(`Flight: ${item.old_flight_date || 'N/A'} → ${item.new_flight_date}`);
                    }
                    if (item.old_return_date !== item.new_return_date) {
                        changes.push(`Return: ${item.old_return_date || 'N/A'} → ${item.new_return_date}`);
                    }
                    if (item.old_duration !== item.new_duration) {
                        changes.push(`Duration: ${item.old_duration || 'N/A'} → ${item.new_duration}`);
                    }

                    const changesText = changes.length > 0 ? changes.join('<br>') : 'Price change only';
                    const penaltyText = item.total_penalty > 0 ? `$${item.total_penalty}` : '-';

                    let statusBadge = '';
                    switch(item.status) {
                        case 'Pending': statusBadge = '<span class="badge-warning">Pending</span>'; break;
                        case 'Approved': statusBadge = '<span class="badge-info">Approved</span>'; break;
                        case 'Rejected': statusBadge = '<span class="badge-danger">Rejected</span>'; break;
                        case 'Completed': statusBadge = '<span class="badge-success">Completed</span>'; break;
                    }

                    historyHtml += `<tr>
                        <td>${date}</td>
                        <td>${changesText}</td>
                        <td>${statusBadge}</td>
                        <td>${penaltyText}</td>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline-danger" title="Revert this date change"
                                    onclick="revertDateChange(${item.id}, ${bookingId})">
                                <i class="feather icon-rotate-ccw mr-1"></i>Revert
                            </button>
                        </td>
                    </tr>`;
                });

                historyHtml += '</tbody></table></div>';
                $('#dateChangeHistoryContent').html(historyHtml);
                $('#dateChangeHistorySection').show();
            } else {
                $('#dateChangeHistorySection').hide();
            }
        },
        error: function() {
            $('#dateChangeHistorySection').hide();
        }
    });
}