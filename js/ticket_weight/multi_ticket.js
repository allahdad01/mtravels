
$(document).ready(function() {
    // DataTables removed - using server-side PHP filtering
    
    // Handle select all checkbox
    $('#selectAllWeights').on('change', function() {
        $('.weight-checkbox').prop('checked', $(this).prop('checked'));
        updateRowHighlighting();
        updateGenerateInvoiceButton();
    });

    // Handle individual checkbox changes
    $(document).on('change', '.weight-checkbox', function() {
        updateRowHighlighting();
        updateGenerateInvoiceButton();
    });

    // Function to update row highlighting
    function updateRowHighlighting() {
        $('.weight-checkbox').each(function() {
            const row = $(this).closest('tr');
            if ($(this).prop('checked')) {
                row.addClass('selected');
            } else {
                row.removeClass('selected');
            }
        });
    }

    // Function to update generate invoice button visibility
    function updateGenerateInvoiceButton() {
        const checkedBoxes = $('.weight-checkbox:checked');
        if (checkedBoxes.length > 0) {
            $('#generateInvoiceBtn').show();
        } else {
            $('#generateInvoiceBtn').hide();
        }
    }

    // Handle generate invoice button click
    $('#generateInvoiceBtn').on('click', function() {
        const selectedWeights = $('.weight-checkbox:checked').map(function() {
            return $(this).val();
        }).get();

        if (selectedWeights.length === 0) {
            showToast('Please select at least one weight.', 'warning');
            return;
        }

        // Show modal for invoice details
        showInvoiceModal(selectedWeights);
    });

    // Function to show invoice modal
    function showInvoiceModal(selectedWeights) {
        Swal.fire({
            title: 'Generate Invoice',
            html: `
                <div class="form-group text-left">
                    <label for="invoiceCurrency">Currency:</label>
                    <select id="invoiceCurrency" class="form-control">
                        <option value="USD">USD</option>
                        <option value="AFS">AFS</option>
                        <option value="EUR">EUR</option>
                        <option value="DARHAM">DARHAM</option>
                    </select>
                </div>
                <div class="form-group text-left">
                    <label for="clientName">Client Name:</label>
                    <input type="text" id="clientName" class="form-control" placeholder="Enter client name">
                </div>
                <div class="form-group text-left">
                    <label for="invoiceComments">Comments:</label>
                    <textarea id="invoiceComments" class="form-control" rows="3" placeholder="Optional comments"></textarea>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Generate Invoice',
            cancelButtonText: 'Cancel',
            preConfirm: () => {
                const currency = $('#invoiceCurrency').val();
                const clientName = $('#clientName').val().trim();
                const comments = $('#invoiceComments').val().trim();

                if (!clientName) {
                    Swal.showValidationMessage('Please enter a client name');
                    return false;
                }

                return {
                    currency: currency,
                    clientName: clientName,
                    comments: comments
                };
            }
        }).then((result) => {
            if (result.isConfirmed) {
                generateInvoice(selectedWeights, result.value);
            }
        });
    }

    // Function to generate invoice
    function generateInvoice(selectedWeights, invoiceData) {
        // Create form data
        const formData = new FormData();
        formData.append('invoiceData', JSON.stringify({
            tickets: selectedWeights,
            currency: invoiceData.currency,
            clientName: invoiceData.clientName,
            comment: invoiceData.comments
        }));

        // Create a temporary form to submit the data
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '../api/ticket_weight/generate_multi_ticket_weight_invoice.php';
        form.target = '_blank';

        // Add the invoice data as a hidden input
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'invoiceData';
        input.value = JSON.stringify({
            tickets: selectedWeights,
            currency: invoiceData.currency,
            clientName: invoiceData.clientName,
            comment: invoiceData.comments
        });
        form.appendChild(input);

        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);

        // Uncheck all checkboxes after generating invoice
        $('.weight-checkbox').prop('checked', false);
        $('#selectAllWeights').prop('checked', false);
        updateRowHighlighting();
        updateGenerateInvoiceButton();

        showToast('Invoice generated successfully!', 'success');
    }

    // Handle floating action button click
    $('#launchMultiWeightInvoice').on('click', function() {
        loadWeightsForInvoice();
        $('#multiWeightInvoiceModal').modal('show');
    });

    // Function to load weights for invoice selection
    function loadWeightsForInvoice() {
        $.ajax({
            url: '../api/ticket_weight/fetch_weights_for_invoice.php',
            type: 'GET',
            success: function(response) {
                try {
                    const result = JSON.parse(response);
                    if (result.success) {
                        displayWeightsForInvoice(result.weights);
                    } else {
                        showToast(result.message || 'Failed to load weights', 'error');
                    }
                } catch (e) {
                    showToast('Error loading weights', 'error');
                }
            },
            error: function() {
                showToast('Error loading weights', 'error');
            }
        });
    }

    // Function to display weights in the modal
    function displayWeightsForInvoice(weights) {
        const tbody = $('#weightsForInvoiceBody');
        tbody.empty();
        let total = 0;

        weights.forEach(weight => {
            const row = `
                <tr>
                    <td>
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input weight-invoice-checkbox"
                                   id="weight_${weight.id}" value="${weight.id}">
                            <label class="custom-control-label" for="weight_${weight.id}"></label>
                        </div>
                    </td>
                    <td>${weight.sold_to_name || '-'}</td>
                    <td>${weight.passenger_name}</td>
                    <td>${weight.pnr}</td>
                    <td>${weight.weight} kg</td>
                    <td>${weight.currency} ${parseFloat(weight.sold_price).toFixed(2)}</td>
                </tr>
            `;
            tbody.append(row);
            total += parseFloat(weight.sold_price);
        });

        $('#weightInvoiceTotal').text(total.toFixed(2));

        // Handle select all in modal
        $('#selectAllWeightsModal').on('change', function() {
            $('.weight-invoice-checkbox').prop('checked', $(this).prop('checked'));
            updateModalTotal();
        });

        // Handle individual checkbox changes
        $(document).on('change', '.weight-invoice-checkbox', function() {
            updateModalTotal();
        });
    }

    // Function to update modal total
    function updateModalTotal() {
        let total = 0;
        $('.weight-invoice-checkbox:checked').each(function() {
            const weightId = $(this).val();
            // Find the corresponding weight data and add to total
            // This would need to be enhanced to get the actual amount
        });
        $('#weightInvoiceTotal').text(total.toFixed(2));
    }

    // Handle generate combined weight invoice
    $('#generateCombinedWeightInvoice').on('click', function() {
        const selectedWeights = $('.weight-invoice-checkbox:checked').map(function() {
            return $(this).val();
        }).get();

        if (selectedWeights.length === 0) {
            showToast('Please select at least one weight.', 'warning');
            return;
        }

        const clientName = $('#clientForWeightInvoice').val().trim();
        if (!clientName) {
            showToast('Please enter a client name.', 'warning');
            return;
        }

        const currency = $('#weightInvoiceCurrency').val();
        const comments = $('#weightInvoiceComment').val().trim();

        // Create form data
        const formData = new FormData();
        formData.append('invoiceData', JSON.stringify({
            tickets: selectedWeights,
            currency: currency,
            clientName: clientName,
            comment: comments
        }));

        // Create a temporary form to submit the data
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '../api/ticket_weight/generate_multi_ticket_weight_invoice.php';
        form.target = '_blank';

        // Add the invoice data as a hidden input
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'invoiceData';
        input.value = JSON.stringify({
            tickets: selectedWeights,
            currency: currency,
            clientName: clientName,
            comment: comments
        });
        form.appendChild(input);

        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);

        // Close modal and show success message
        $('#multiWeightInvoiceModal').modal('hide');
        showToast('Weight invoice generated successfully!', 'success');
    });

    // Handle client filter
    $('#clientFilterWeight').on('change', function() {
        const clientName = $(this).val();
        if (clientName) {
            loadWeightsForInvoice(clientName);
        } else {
            loadWeightsForInvoice();
        }
    });

    

    // Search by PNR
    $('#searchPNRBtn').on('click', function() {
        const pnr = $('#searchPNR').val().trim();
        if (pnr) {
            searchTickets({ pnr: pnr });
        }
    });

    // Search by Passenger Name
    $('#searchPassengerBtn').on('click', function() {
        const passengerName = $('#searchPassenger').val().trim();
        if (passengerName) {
            searchTickets({ passenger: passengerName });
        }
    });

    // Function to search tickets
    function searchTickets(params) {
        $.ajax({
            url: '../api/ticket_weight/search_tickets.php',
            type: 'GET',
            data: params,
            success: function(response) {
                try {

                    // Determine if response is already an object or needs parsing
                    const result = typeof response === 'string' ? JSON.parse(response) : response;

                    if (result.success) {
                        displaySearchResults(result.tickets);
                    } else {
                        alert(result.message || 'No tickets found');
                    }
                } catch (e) {
                    alert('Error processing request');
                }
            },
            error: function() {
                alert('Error searching tickets');
            }
        });
    }

    // Function to display search results
    function displaySearchResults(tickets) {
        const tbody = $('#searchResultsTable tbody');
        tbody.empty();

        tickets.forEach(ticket => {
            const row = `
                <tr>
                    <td>${ticket.passenger_name}</td>
                    <td>${ticket.pnr}</td>
                    <td>
                        ${ticket.airline}<br>
                        <small>${ticket.origin} - ${ticket.destination}</small>
                    </td>
                    <td>${ticket.departure_date}</td>
                    <td>
                        <button type="button" class="btn btn-sm btn-primary select-ticket" data-ticket-id="${ticket.id}">
                            Select
                        </button>
                    </td>
                </tr>
            `;
            tbody.append(row);
        });
        $('#searchResultsContainer').show();
    }

    // Handle ticket selection
    $(document).on('click', '.select-ticket', function() {
        const ticketId = $(this).data('ticket-id');
        $('#selectedTicketId').val(ticketId);
        $('#weightDetailsContainer').show();
        $('#saveTransactionBtn').show();
    });

    // Calculate profit automatically
    $('#basePrice, #soldPrice').on('input', function() {
        const basePrice = parseFloat($('#basePrice').val()) || 0;
        const soldPrice = parseFloat($('#soldPrice').val()) || 0;
        const profit = soldPrice - basePrice;
        $('#profit').val(profit.toFixed(2));
    });

    // Handle form submission
    $('#addTransactionForm').on('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        $.ajax({
            url: '../api/ticket/save_weight.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                try {
                    const result = JSON.parse(response);
                    if (result.success) {
                        showToast('Weight saved successfully', 'success');
                        location.reload();
                    } else {
                        showToast(result.message || 'Failed to save weight', 'error');
                    }
                } catch (e) {
                    showToast('Error processing request', 'error');
                }
            },
            error: function() {
                showToast('Error saving weight', 'error');
            }
        });
    });

});
