// Date Change Modal Functions
function openDateChangeModal(bookingId, passengerName, currentFlightDate, currentReturnDate, currentDuration, currentPrice, currency) {
    console.log('Opening date change modal for booking:', bookingId);

    // Set modal data
    document.getElementById('dateChangeBookingId').value = bookingId;
    document.getElementById('currentPassengerName').textContent = passengerName;
    document.getElementById('currentFlightDate').textContent = currentFlightDate || 'Not set';
    document.getElementById('currentReturnDate').textContent = currentReturnDate || 'Not set';
    document.getElementById('currentDuration').textContent = currentDuration || 'Not set';

    // Set current values as defaults for new fields
    document.getElementById('newFlightDate').value = currentFlightDate || '';
    document.getElementById('newReturnDate').value = currentReturnDate || '';
    document.getElementById('newDuration').value = currentDuration || '';
    document.getElementById('newPrice').value = currentPrice || '';

    // Reset form
    document.getElementById('dateChangeForm').reset();
    document.getElementById('dateChangeConfirmation').checked = false;

    // Show modal
    $('#dateChangeModal').modal('show');
}

// Submit Date Change Request
$(document).on('click', '#submitDateChangeRequest', function() {
    console.log('Submit date change request clicked');

    // Validate form
    var form = document.getElementById('dateChangeForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    // Check confirmation
    if (!document.getElementById('dateChangeConfirmation').checked) {
        showToast('warning', 'Please confirm that the date change request details are correct.');
        return;
    }

    // Show loading state
    var submitBtn = $(this);
    var originalHtml = submitBtn.html();
    submitBtn.html('<i class="feather icon-loader spinning"></i> Submitting...').prop('disabled', true);

    // Collect form data
    var formData = new FormData(form);

    // Submit request
    $.ajax({
        url: '../api/umrah/submit_date_change_request.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            console.log('Date change request response:', response);

            if (response.success) {
                 showToast('success', response.message || 'Date change request has been submitted successfully.');
                 setTimeout(() => {
                     $('#dateChangeModal').modal('hide');
                     // Refresh the page to show updated data
                     location.reload();
                 }, 1500);
             } else {
                 showToast('error', response.message || 'Failed to submit date change request.');
             }
        },
        error: function(xhr, status, error) {
            console.error('Date change request error:', error);
            showToast('error', 'An error occurred while submitting the request. Please try again.');
        },
        complete: function() {
            // Reset button state
            submitBtn.html(originalHtml).prop('disabled', false);
        }
    });
});

// Auto-calculate duration when dates change
$(document).on('change', '#newFlightDate, #newReturnDate', function() {
    var flightDate = new Date($('#newFlightDate').val());
    var returnDate = new Date($('#newReturnDate').val());

    if (flightDate && returnDate && returnDate > flightDate) {
        var timeDiff = returnDate.getTime() - flightDate.getTime();
        var daysDiff = Math.ceil(timeDiff / (1000 * 3600 * 24));

        // Set duration based on days difference
        var durationSelect = $('#newDuration');
        var durationValue = daysDiff + ' Days';

        // Check if the calculated duration exists in options
        if (durationSelect.find('option[value="' + durationValue + '"]').length > 0) {
            durationSelect.val(durationValue);
        }
    }
});

// Load date change history for a booking
function loadDateChangeHistory(bookingId) {
    $.ajax({
        url: '../api/umrah/get_booking_date_changes.php',
        type: 'GET',
        data: { booking_id: bookingId },
        success: function(response) {
            if (response.success && response.history && response.history.length > 0) {
                let historyHtml = '<div class="table-responsive"><table class="table table-sm table-striped">';
                historyHtml += '<thead><tr><th>Date</th><th>Changes</th><th>Status</th><th>Penalty</th></tr></thead><tbody>';

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