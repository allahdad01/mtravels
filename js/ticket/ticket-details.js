// Function to populate and display modal details
$(document).on('click', '.view-details', function() {
    var ticketData = $(this).data('ticket');


    if (!ticketData || !ticketData.ticket || !ticketData.ticket.id) {
        showToast('Ticket data or ID is missing!', 'error');
        return;
    }

    // Attach ticket data to the modal
    $('#detailsModal').data('ticket', ticketData); // Attach full ticket data
    $('#detailsModal').data('ticket-id', ticketData.ticket.id); // Attach ticket ID

    if (ticketData) {
        // Populate fields...
        $('#detailsModal #passenger-name').text(ticketData.ticket.passenger_name || 'N/A');
        $('#detailsModal #pnr').text(ticketData.ticket.pnr || 'N/A');
        $('#detailsModal #supplier-name').text(ticketData.ticket.supplier_name || 'N/A');
        $('#detailsModal #sold-to').text(ticketData.ticket.sold_to || 'N/A');
        $('#detailsModal #paid-to').text(ticketData.ticket.paid_to || 'N/A');
        $('#detailsModal #created-by').text(ticketData.ticket.created_by_name || 'N/A');
        $('#detailsModal #sold-price').text(ticketData.ticket.sold || 'N/A');
        $('#detailsModal #base-price').text(ticketData.ticket.price || 'N/A');
        $('#detailsModal #discount').text(ticketData.ticket.discount || 'N/A');
        $('#detailsModal #profit').text(ticketData.ticket.profit || 'N/A');
        $('#detailsModal #payment-amount').text(ticketData.ticket.paymentAmount || 'N/A');
        $('#detailsModal #currency').text(ticketData.ticket.currency || 'N/A');
        $('#detailsModal #exchangeRate').text(ticketData.ticket.exchangeRate || 'N/A');
        $('#detailsModal #marketExchangeRate').text(ticketData.ticket.marketExchangeRate || 'N/A');
        $('#detailsModal #phone').text(ticketData.ticket.phone || 'N/A');
        $('#detailsModal #gender').text(ticketData.ticket.gender || 'N/A');
        $('#detailsModal #description').text(ticketData.ticket.description || 'N/A');

        // Populate flight legs (multi-leg itineraries)
        var legs = [];
        if (ticketData.ticket.flight_legs) {
            try { legs = JSON.parse(ticketData.ticket.flight_legs); } catch (e) { legs = []; }
        }
        if (!legs.length) {
            legs = [{
                origin: ticketData.ticket.origin,
                destination: ticketData.ticket.destination,
                airline: ticketData.ticket.airline,
                date: ticketData.ticket.departure_date,
                time: ticketData.ticket.departure_time
            }];
        }
        var routeCities = legs.map(function (l) { return l.origin; }).filter(Boolean);
        var lastDest = legs.length ? legs[legs.length - 1].destination : '';
        if (lastDest) routeCities.push(lastDest);
        $('#detailsModal #flight-legs-route').text(routeCities.join(' \u2192 ') || 'N/A');
        var legsList = $('#detailsModal #flight-legs-list');
        legsList.empty();
        legs.forEach(function (leg, i) {
            var route = [leg.origin, leg.destination].filter(Boolean).join(' \u2192 ') || 'N/A';
            var dep = [leg.date, leg.time].filter(Boolean).join(' @ ');
            var arr = [leg.arrival_date, leg.arrival_time].filter(Boolean).join(' @ ');
            var meta = [];
            if (leg.flight_number) meta.push('FN ' + leg.flight_number);
            if (leg.duration) meta.push('Duration: ' + leg.duration);
            if (leg.stopover) meta.push('Stopover: ' + leg.stopover);
            legsList.append(
                '<div class="d-flex justify-content-between align-items-start mb-2 pb-2" style="border-bottom: 1px dashed #e3e6eb;">' +
                '    <div><strong>Leg ' + (i + 1) + ':</strong> ' + route +
                (dep ? ' <small class="text-muted">Dep: ' + dep + '</small>' : '') +
                (arr ? ' <small class="text-muted">Arr: ' + arr + '</small>' : '') +
                (meta.length ? ' <small class="text-muted">(' + meta.join(' | ') + ')</small>' : '') + '</div>' +
                '    <span class="badge badge-light">' + (leg.airline || 'N/A') + '</span>' +
                '</div>'
            );
        });
        
        // Populate return flight segments (round trip)
        var returnLegsCard = $('#detailsModal #returnFlightSegmentsCard');
        var returnLegsList = $('#detailsModal #return-flight-legs-list');
        returnLegsList.empty();
        var returnLegs = [];
        if (ticketData.ticket.return_flight_legs) {
            try { returnLegs = JSON.parse(ticketData.ticket.return_flight_legs); } catch (e) { returnLegs = []; }
        }
        if (returnLegs.length) {
            returnLegsCard.show();
            returnLegs.forEach(function (leg, i) {
                var route = [leg.origin, leg.destination].filter(Boolean).join(' \u2192 ') || 'N/A';
                var dep = [leg.date, leg.time].filter(Boolean).join(' @ ');
                var arr = [leg.arrival_date, leg.arrival_time].filter(Boolean).join(' @ ');
                var meta = [];
                if (leg.flight_number) meta.push('FN ' + leg.flight_number);
                if (leg.duration) meta.push('Duration: ' + leg.duration);
                if (leg.stopover) meta.push('Stopover: ' + leg.stopover);
                returnLegsList.append(
                    '<div class="d-flex justify-content-between align-items-start mb-2 pb-2" style="border-bottom: 1px dashed #e3e6eb;">' +
                    '    <div><strong>Leg ' + (i + 1) + ':</strong> ' + route +
                    (dep ? ' <small class="text-muted">Dep: ' + dep + '</small>' : '') +
                    (arr ? ' <small class="text-muted">Arr: ' + arr + '</small>' : '') +
                    (meta.length ? ' <small class="text-muted">(' + meta.join(' | ') + ')</small>' : '') + '</div>' +
                    '    <span class="badge badge-light">' + (leg.airline || 'N/A') + '</span>' +
                    '</div>'
                );
            });
        } else {
            returnLegsCard.hide();
        }

        // Disable date change, weight, and refund buttons for refunded tickets
        var isRefunded = ticketData.ticket.status === 'Refunded';
        $('#dateChangeBtn, #addWeightBtn, #refundBtn').prop('disabled', isRefunded);
        if (isRefunded) {
            $('#dateChangeBtn, #addWeightBtn, #refundBtn').addClass('disabled').attr('title', 'Not available for refunded tickets');
        } else {
            $('#dateChangeBtn, #addWeightBtn, #refundBtn').removeClass('disabled').attr('title', '');
        }
        
        // Handle refund data...
        if (ticketData.refund_data) {
            $('#detailsModal #refund-supplier-penalty').text(ticketData.refund_data.supplier_penalty || 'N/A');
            $('#detailsModal #refund-service-penalty').text(ticketData.refund_data.service_penalty || 'N/A');
            $('#detailsModal #refund-to-passenger').text(ticketData.refund_data.refund_to_passenger || 'N/A');
            $('#detailsModal #refund-status').text(ticketData.refund_data.status || 'N/A');
            $('#detailsModal #refund-remarks').text(ticketData.refund_data.remarks || 'N/A');
        }

        // Handle date change data...
        if (ticketData.date_change_data) {
            $('#detailsModal #date-change-departure-date').text(ticketData.date_change_data.departure_date || 'N/A');
            $('#detailsModal #date-change-currency').text(ticketData.date_change_data.currency || 'N/A');
            $('#detailsModal #date-change-supplier-penalty').text(ticketData.date_change_data.supplier_penalty || 'N/A');
            $('#detailsModal #date-change-service-penalty').text(ticketData.date_change_data.service_penalty || 'N/A');
            $('#detailsModal #date-change-status').text(ticketData.date_change_data.status || 'N/A');
            $('#detailsModal #date-change-remarks').text(ticketData.date_change_data.remarks || 'N/A');
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

        if (ticketData.ticket.status === 'Refunded') {
            showToast('Cannot add date change to a refunded ticket.', 'error');
            return;
        }

        const ticketId = ticketData.ticket.id; // Extract the ticket ID

        // Pass the ticketId dynamically to the Date Change modal fields
        $('#dateChangeTicketId').val(ticketId);  // Set ticketId in the hidden field for the date change form

        // Populate fields (fetch dynamically or mock data)
        $('#dateChangeSold').val($('#detailsModal #sold-price').text());
        $('#dateChangeBase').val($('#detailsModal #base-price').text());
        $('#dateChangeDescription').val($('#detailsModal #description').text());
        $('#dateChangeDepartureDate').val('');  // Empty the departure date for the user to enter
        $('#dateChangeReturnDate').val('');  // Empty the return date for the user to enter

        // Reset date type selection
        $('#changeDepartureOnly').prop('checked', true);
        
        // Show/hide date type selection and return date field based on trip type
        if (ticketData.ticket.trip_type === 'round_trip') {
            $('#dateTypeSelectionGroup').show();
            $('#departureGroup').show();
            $('#returnDateGroup').hide();
            updateDateChangeFields();
        } else {
            $('#dateTypeSelectionGroup').hide();
            $('#departureGroup').show();
            $('#returnDateGroup').hide();
            $('#dateChangeDepartureDate').prop('required', true);
            $('#dateChangeReturnDate').prop('required', false);
        }

        $('#dateChangeModal').modal('show');
    });

    // Handle date type selection changes
    $(document).on('change', 'input[name="dateType"]', function() {
        updateDateChangeFields();
    });

    function updateDateChangeFields() {
        const dateType = $('input[name="dateType"]:checked').val();
        
        if (dateType === 'departure') {
            $('#departureGroup').show();
            $('#returnDateGroup').hide();
            $('#dateChangeDepartureDate').prop('required', true);
            $('#dateChangeReturnDate').prop('required', false);
        } else if (dateType === 'return') {
            $('#departureGroup').hide();
            $('#returnDateGroup').show();
            $('#dateChangeDepartureDate').prop('required', false);
            $('#dateChangeReturnDate').prop('required', true);
        } else if (dateType === 'both') {
            $('#departureGroup').show();
            $('#returnDateGroup').show();
            $('#dateChangeDepartureDate').prop('required', true);
            $('#dateChangeReturnDate').prop('required', true);
        }
    }

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
            url: '../api/ticket/getClientType.php',
            data: { ticketId: ticketId }, // Send only the ticket ID
            success: function (response) {
                const data = JSON.parse(response);

                if (data.status === 'success') {
                    // Populate refund form fields
                    $('#refundSold').val($('#detailsModal #sold-price').text());
                    $('#refundBase').val($('#detailsModal #base-price').text());
                    $('#refundDescription').val($('#detailsModal #description').text());

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
        
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.html();
        
        // Disable button and show loading state
        submitBtn.prop('disabled', true);
        submitBtn.html('<i class="feather icon-loader mr-2"></i>Processing...');
        
        const dateType = $('input[name="dateType"]:checked').val();
        const departureDateVal = $('#dateChangeDepartureDate').val();
        const returnDateVal = $('#dateChangeReturnDate').val();
        
        // Validate that at least one date is provided
        if (dateType === 'departure' && !departureDateVal) {
            showToast('Please enter the new departure date', 'error');
            submitBtn.prop('disabled', false);
            submitBtn.html(originalText);
            return;
        }
        if (dateType === 'return' && !returnDateVal) {
            showToast('Please enter the new return date', 'error');
            submitBtn.prop('disabled', false);
            submitBtn.html(originalText);
            return;
        }
        if (dateType === 'both' && (!departureDateVal || !returnDateVal)) {
            showToast('Please enter both departure and return dates', 'error');
            submitBtn.prop('disabled', false);
            submitBtn.html(originalText);
            return;
        }
        
        const formData = $(this).serialize();

        $.ajax({
            url: '../api/ticket/insert_ticket_record_dc.php',
            method: 'POST',
            data: formData,
            success: function (response) {
                if ($.trim(response) === 'success') { // Trim whitespace
                    showToast('Date change recorded successfully', 'success');
                    $('#dateChangeModal').modal('hide');
                    setTimeout(() => {
                        refreshTicketTable();
                    }, 1000);
                } else {
                    showToast('Error recording date change: ' + response, 'error');
                }
            },
            error: function () {
                showToast('An error occurred', 'error');
            },
            complete: function () {
                // Re-enable button and restore original text
                submitBtn.prop('disabled', false);
                submitBtn.html(originalText);
            }
        });
    });
    
    // Submit Refund Form
    $('#refundForm').submit(function (e) {
        e.preventDefault();
        
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.html();
        
        // Disable button and show loading state
        submitBtn.prop('disabled', true);
        submitBtn.html('<i class="feather icon-loader mr-2"></i>Processing...');
        
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
            url: '../api/ticket/insert_ticket_record.php',
            method: 'POST',
            data: formData,
            success: function (response) {
                if ($.trim(response) === 'success') {
                    showToast('Refund recorded successfully', 'success');
                    $('#refundModal').modal('hide');
                    setTimeout(() => {
                        refreshTicketTable();
                    }, 1000);
                } else {
                    showToast('Error recording refund', 'error');
                }
            },
            error: function () {
                showToast('An error occurred', 'error');
            },
            complete: function () {
                // Re-enable button and restore original text
                submitBtn.prop('disabled', false);
                submitBtn.html(originalText);
            }
        });
    });
}); 
