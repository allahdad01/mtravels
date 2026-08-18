document.getElementById('bookTicketForm').addEventListener('submit', function (event) {
    event.preventDefault(); // Prevent default form submission
    const formData = new FormData(this); // Collect form data

    // Multi-leg flights: append legs JSON and use the last leg as final destination
    if (typeof window.collectFlightLegs === 'function') {
        const legsJson = window.collectFlightLegs();
        if (legsJson) {
            formData.append('flight_legs', legsJson);
            const finalDestination = window.getFlightLegsFinalDestination();
            if (finalDestination) formData.set('destination', finalDestination);
        }
    }

    // Round trip: append return flight segments JSON and derive return fields
    const tripType = formData.get('tripType');
    if (tripType === 'round_trip' && typeof window.collectReturnFlightLegs === 'function') {
        const returnLegsJson = window.collectReturnFlightLegs();
        if (returnLegsJson) {
            formData.append('return_flight_legs', returnLegsJson);
            const returnFinalDestination = window.getReturnFlightLegsFinalDestination();
            if (returnFinalDestination) formData.append('returnDestination', returnFinalDestination);
        } else {
            showToast('Please add at least one return flight segment for a round trip.', 'error');
            return;
        }
    }

    const submitBtn = this.querySelector('button[type="submit"]'); // Get submit button
    const bookTicketForm = this; // Reference to form
    
    // Disable button and show processing state
    submitBtn.disabled = true;
    const originalButtonText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="feather icon-loader mr-2"></i>Processing...';

    fetch('../api/ticket/save_ticket.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json()) // Parse JSON response
    .then(data => {
        if (data.status === 'success') { // Check for status
            showToast(data.message, 'success'); // Show success toast

            // Close modal after a short delay to show the toast
            setTimeout(() => {
                $('#bookTicketModal').modal('hide');
                // Reload the page to show the newly added ticket
                window.location.reload();
            }, 1500); // Wait for toast to be visible
        } else {
            // Use translated error message from PHP
            showToast(data.message, 'error');
            // Re-enable button on error
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalButtonText;
        }
    })
    .catch(error => {
        // Use translated error message from PHP
        showToast('An unexpected error occurred', 'error');
        // Re-enable button on error
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalButtonText;
    });
});

// Fully reset the book ticket modal: native inputs plus plugin UI,
// dynamic passenger rows, round-trip toggle state and PDF status text.
function resetBookTicketForm() {
    const form = document.getElementById('bookTicketForm');
    if (!form) return;

    // 1. Native reset clears input/select/textarea values
    form.reset();

    // 2. Rebuild passenger rows from the (now default) counts
    $('.passenger-count').trigger('change');

    // 3. Re-sync bootstrap-select UI with the reset values
    $('#bookTicketModal .selectpicker').selectpicker('refresh');

    // 4. Hide round-trip return segments left visible from a previous booking
    const returnFlightSegmentsGroup = document.getElementById('returnFlightSegmentsGroup');
    if (returnFlightSegmentsGroup) returnFlightSegmentsGroup.style.display = 'none';
    const returnFlightStops = document.getElementById('returnFlightStops');
    const returnFlightRoutePreview = document.getElementById('returnFlightRoutePreview');
    if (returnFlightStops) { returnFlightStops.textContent = ''; returnFlightStops.style.display = 'none'; }
    if (returnFlightRoutePreview) returnFlightRoutePreview.textContent = '';

    // 5. Clear supplier-derived currency
    const currInput = document.getElementById('curr');
    if (currInput) currInput.value = '';

    // 6. Remove any stale PDF extraction status messages
    document.querySelectorAll('.pdf-status').forEach(el => el.remove());
}

// Reset whenever the modal is fully closed (after booking or user close)
$('#bookTicketModal').on('hidden.bs.modal', function() {
    resetBookTicketForm();
}); 
