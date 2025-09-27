// Function to populate and display modal details
$(document).on('click', '.view-details', function() {
    var ticketData = $(this).data('ticket');

    console.log(ticketData);  // Log ticket data for debugging
     if (!ticketData || !ticketData.ticket || !ticketData.ticket.id) {
        alert('Ticket data or ID is missing!');
        return;
    }

    // Attach ticket data to the modal
    $('#detailsModal').data('ticket', ticketData); // Attach full ticket data
    $('#detailsModal').data('ticket-id', ticketData.ticket.id); // Attach ticket ID


    if (ticketData) {
        // Supplier, sold to, and paid to names should now be populated correctly
        $('#passenger-name').text(ticketData.ticket.passenger_name || 'N/A');
        $('#pnr').text(ticketData.ticket.pnr || 'N/A');
        $('#supplier-name').text(ticketData.ticket.supplier_name || 'N/A');
        $('#sold-to').text(ticketData.ticket.sold_to || 'N/A');
        $('#paid-to').text(ticketData.ticket.paid_to || 'N/A');
        
        // Populate other fields...
        $('#sold-price').text(ticketData.ticket.sold || 'N/A');
        $('#base-price').text(ticketData.ticket.price || 'N/A');
        $('#profit').text(ticketData.ticket.profit || 'N/A');
        $('#paymentAmount').text(ticketData.ticket.paymentAmount || 'N/A');
        $('#currency').text(ticketData.ticket.currency || 'N/A');
        $('#payment-currency').text(ticketData.ticket.paymentCurrency || 'N/A');
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
        alert('Ticket data not available!');
    }
});