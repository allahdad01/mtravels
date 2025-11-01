// Function to populate and display modal details
$(document).on('click', '.view-details', function() {
    var ticketData = $(this).data('ticket');

    console.log(ticketData);  // Log ticket data for debugging
    if (!ticketData || !ticketData.ticket || !ticketData.ticket.id) {
        showToast('Ticket data or ID is missing!', 'error');
        return;
    }

    // Attach ticket data to the modal
    $('#detailsModal').data('ticket', ticketData); // Attach full ticket data
    $('#detailsModal').data('ticket-id', ticketData.ticket.id); // Attach ticket ID

    if (ticketData) {
        // Populate fields...
        $('#passenger-name').text(ticketData.ticket.passenger_name || 'N/A');
        $('#pnr').text(ticketData.ticket.pnr || 'N/A');
        $('#supplier-name').text(ticketData.ticket.supplier_name || 'N/A');
        $('#sold-to').text(ticketData.ticket.sold_to || 'N/A');
        $('#paid-to').text(ticketData.ticket.paid_to || 'N/A');
        $('#created-by').text(ticketData.ticket.created_by_name || 'N/A');
        $('#sold-price').text(ticketData.ticket.sold || 'N/A');
        $('#base-price').text(ticketData.ticket.price || 'N/A');
        $('#discount').text(ticketData.ticket.discount || 'N/A');
        $('#profit').text(ticketData.ticket.profit || 'N/A');
        $('#payment-amount').text(ticketData.ticket.paymentAmount || 'N/A');
        $('#currency').text(ticketData.ticket.currency || 'N/A');
        $('#exchangeRate').text(ticketData.ticket.exchangeRate || 'N/A');
        $('#marketExchangeRate').text(ticketData.ticket.marketExchangeRate || 'N/A');
        $('#phone').text(ticketData.ticket.phone || 'N/A');
        $('#gender').text(ticketData.ticket.gender || 'N/A');
        $('#description').text(ticketData.ticket.description || 'N/A');
        
        // Handle refund data...
        if (ticketData.refund_data) {
            $('#refund-supplier-penalty').text(ticketData.refund_data.supplier_penalty || 'N/A');
            $('#refund-service-penalty').text(ticketData.refund_data.service_penalty || 'N/A');
            $('#refund-to-passenger').text(ticketData.refund_data.refund_to_passenger || 'N/A');
            $('#refund-status').text(ticketData.refund_data.status || 'N/A');
            $('#refund-remarks').text(ticketData.refund_data.remarks || 'N/A');
        }

        // Handle date change data...
        if (ticketData.date_change_data) {
            $('#date-change-departure-date').text(ticketData.date_change_data.departure_date || 'N/A');
            $('#date-change-currency').text(ticketData.date_change_data.currency || 'N/A');
            $('#date-change-supplier-penalty').text(ticketData.date_change_data.supplier_penalty || 'N/A');
            $('#date-change-service-penalty').text(ticketData.date_change_data.service_penalty || 'N/A');
            $('#date-change-status').text(ticketData.date_change_data.status || 'N/A');
            $('#date-change-remarks').text(ticketData.date_change_data.remarks || 'N/A');
        }

        $('#detailsModal').modal('show');  // Show the modal with details
    } else {
        showToast('Ticket data not available', 'error');
    }
});

// Date change function
$(document).ready(function () {
    // Open Date Change Modal
    $('#dateChangeBtn').click(function () {
        const ticketData = $('#detailsModal').data('ticket'); // Get ticket data
        if (!ticketData || !ticketData.ticket || !ticketData.ticket.id) {
            showToast('Ticket data or ID is missing!', 'error');
            return;
        }

        const ticketId = ticketData.ticket.id; // Extract the ticket ID

        // Pass the ticketId dynamically to the Date Change modal fields
        $('#dateChangeTicketId').val(ticketId);  // Set ticketId in the hidden field for the date change form

        // Populate fields (fetch dynamically or mock data)
        $('#dateChangeSold').val($('#sold-price').text());
        $('#dateChangeBase').val($('#base-price').text());
        $('#dateChangeDescription').val($('#description').text());
        $('#dateChangeDepartureDate').val('');  // Empty the departure date for the user to enter

        $('#dateChangeModal').modal('show');
    });

    // Open Refund Modal
    $('#refundBtn').click(function () {
        const ticketData = $('#detailsModal').data('ticket'); // Get ticket data
        if (!ticketData || !ticketData.ticket || !ticketData.ticket.id) {
            showToast('Ticket data or ID is missing!', 'error');
            return;
        }

        const ticketId = ticketData.ticket.id; // Extract the ticket ID
        
        $('#refundTicketId').val(ticketId); // Set the hidden field for the refund form

        // Fetch client type and handle the refund modal
        $.ajax({
            type: 'POST',
            url: 'getClientType.php',
            data: { ticketId: ticketId }, // Send only the ticket ID
            success: function (response) {
                const data = JSON.parse(response);

                if (data.status === 'success') {
                    // Populate refund form fields
                    $('#refundSold').val($('#sold-price').text());
                    $('#refundBase').val($('#base-price').text());
                    $('#refundDescription').val($('#description').text());

                    // Helper function to calculate refund amount based on selected method
                    function calculateRefundAmount() {
                        const calculationMethod = $('input[name="calculationMethod"]:checked').val();
                        const basePrice = parseFloat($('#refundBase').val()) || 0;
                        const soldPrice = parseFloat($('#refundSold').val()) || 0;
                        const supplierPenalty = parseFloat($('#supplierRefundPenalty').val()) || 0;
                        const servicePenalty = parseFloat($('#serviceRefundPenalty').val()) || 0;
                        
                        let amount = 0;
                        
                        if (calculationMethod === 'base') {
                            amount = basePrice - supplierPenalty - servicePenalty;
                        } else { // 'sold'
                            amount = soldPrice - supplierPenalty - servicePenalty;
                        }
                        
                        // Ensure refundAmount is non-negative
                        return Math.max(0, amount);
                    }

                    // On change of penalties or calculation method, update the refund calculation
                    $('#supplierRefundPenalty, #serviceRefundPenalty, input[name="calculationMethod"]').on('input change', function() {
                        const refundAmount = calculateRefundAmount();
                        $('#refundAmount').val(refundAmount.toFixed(2));
                        
                        console.log('Calculation method changed. Updated refund amount:', refundAmount.toFixed(2));
                    });

                    // Show the modal
                    $('#refundModal').modal('show');
                } else {
                    showToast('Error: ' + data.message, 'error'); // If there was an error fetching client type
                }
            },
            error: function () {
                showToast('Error fetching client type', 'error'); // AJAX error
            }
        });
    });
    
    // Submit Date Change Form
    $('#dateChangeForm').submit(function (e) {
        e.preventDefault();
        const formData = $(this).serialize();

        $.ajax({
            url: 'insert_ticket_record_dc.php',
            method: 'POST',
            data: formData,
            success: function (response) {
                console.log('Server Response:', response); // Log response for debugging
                if ($.trim(response) === 'success') { // Trim whitespace
                    showToast('Date change recorded successfully', 'success');
                    $('#dateChangeModal').modal('hide');
                } else {
                    showToast('Error recording date change: ' + response, 'error');
                }
            },
            error: function () {
                showToast('An error occurred', 'error');
            },
        });
    });
    
    // Submit Refund Form
    $('#refundForm').submit(function (e) {
        e.preventDefault();
        
        // Get the selected calculation method and add it to the form data
        const calculationMethod = $('input[name="calculationMethod"]:checked').val();
        
        // Add hidden field dynamically to ensure it's included
        if (!$('#calculationMethodHidden').length) {
            $(this).append('<input type="hidden" id="calculationMethodHidden" name="calculationMethod" value="' + calculationMethod + '">');
        } else {
            $('#calculationMethodHidden').val(calculationMethod);
        }
        
        const formData = $(this).serialize();

        $.ajax({
            url: 'insert_ticket_record.php',
            method: 'POST',
            data: formData,
            success: function (response) {
                console.log('Server Response:', response); // Log response for debugging
                if ($.trim(response) === 'success') {
                    showToast('Refund recorded successfully', 'success');
                    $('#refundModal').modal('hide');
                } else {
                    showToast('Error recording refund', 'error');
                }
            },
            error: function () {
                showToast('An error occurred', 'error');
            },
        });
    });
}); 