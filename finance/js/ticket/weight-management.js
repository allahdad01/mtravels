$(document).ready(function() {
    // Function to handle opening the weight modal
    function openWeightModal(ticketId) {
        // Get passenger name and PNR from the details modal
        const passengerName = $('#passenger-name').text();
        const pnr = $('#pnr').text();

        // Set the values in the weight modal
        $('#weight-ticket-id').val(ticketId);
        $('#weight-passenger-name').text(passengerName);
        $('#weight-pnr').text(pnr);

        // Show the modal
        $('#addWeightModal').modal('show');
    }

    // Handle Add Weight button click
    $('#addWeightBtn').on('click', function() {
        const ticketId = $('#detailsModal').data('ticket-id');
        if (!ticketId) {
            showToast('Ticket ID is missing', 'error');
            return;
        }
        openWeightModal(ticketId);
    });

    // Calculate profit automatically
    $('#base-weight-price, #sold-weight-price').on('input', function() {
        const basePrice = parseFloat($('#base-weight-price').val()) || 0;
        const soldPrice = parseFloat($('#sold-weight-price').val()) || 0;
        const profit = soldPrice - basePrice;
        $('#weight-profit').val(profit.toFixed(2));
    });

    // Handle form submission
    $('#addWeightForm').on('submit', function(e) {
        e.preventDefault();

        const formData = {
            ticket_id: $('#weight-ticket-id').val(),
            weight: $('#weight').val(),
            base_price: $('#base-weight-price').val(),
            sold_price: $('#sold-weight-price').val(),
            profit: $('#weight-profit').val(),
            remarks: $('#weight-remarks').val()
        };

        // Send AJAX request to save weight
        $.ajax({
            url: 'ajax/save_weight.php',
            type: 'POST',
            data: formData,
            success: function(response) {
                try {
                    const result = JSON.parse(response);
                    if (result.success) {
                        // Show success message
                        showToast(result.message, 'success');
                        // Close modal and reset form
                        $('#addWeightModal').modal('hide');
                        $('#addWeightForm')[0].reset();
                    } else {
                        throw new Error(result.message);
                    }
                } catch (error) {
                    showToast(error.message, 'error');
                }
            },
            error: function() {
                showToast('Something went wrong', 'error');
            }
        });
    });
}); 