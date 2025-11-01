document.addEventListener('DOMContentLoaded', function() {
    // Initialize Select2 for client dropdowns
    if (typeof $.fn.select2 !== 'undefined') {
        $('#clientFilter, #clientForInvoice').select2({
            dropdownParent: $('#multiTicketInvoiceModal'),
            placeholder: "<?= __('search_and_select_client') ?>...",
            allowClear: true
        });
    }
    
    // Launch multi-ticket invoice modal
    document.getElementById('launchMultiTicketInvoice').addEventListener('click', function() {
        loadTicketsForInvoice();
        $('#multiTicketInvoiceModal').modal('show');
    });

    // Handle client filter change
    document.getElementById('clientFilter').addEventListener('change', function() {
        loadTicketsForInvoice();
    });
    
    // Handle "Select All" checkbox
    document.getElementById('selectAllTickets').addEventListener('change', function() {
        const isChecked = this.checked;
        const checkboxes = document.querySelectorAll('#ticketsForInvoiceBody input[type="checkbox"]');
        
        checkboxes.forEach(checkbox => {
            checkbox.checked = isChecked;
        });
        
        updateInvoiceTotal();
    });
    
    // Generate the combined invoice
    document.getElementById('generateCombinedInvoice').addEventListener('click', function() {
        const selectedTickets = getSelectedTickets();
        
        if (selectedTickets.length === 0) {
            alert('please_select_at_least_one_ticket_for_the_invoice');
            return;
        }
        
        const clientId = document.getElementById('clientForInvoice').value;
        if (!clientId) {
            alert('please_select_a_client_for_the_invoice');
            return;
        }
        
        const invoiceData = {
            includeCharges: document.getElementById('includeCharges').checked,
            comment: document.getElementById('invoiceComment').value,
            currency: document.getElementById('invoiceCurrency').value,
            clientId: clientId,
            tickets: selectedTickets
        };
        
        // Send the data to a new page to generate the invoice
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'generate_multi_ticket_refund_invoice.php';
        form.target = '_blank';
        
        const hiddenField = document.createElement('input');
        hiddenField.type = 'hidden';
        hiddenField.name = 'invoiceData';
        hiddenField.value = JSON.stringify(invoiceData);
        
        form.appendChild(hiddenField);
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
        
        // Close the modal after generating the invoice
        $('#multiTicketInvoiceModal').modal('hide');
    });
    
    // Load tickets for the invoice table
    function loadTicketsForInvoice() {
        const clientFilter = document.getElementById('clientFilter').value;
        
        fetch('fetch_ticket_refund_for_invoice.php?client_id=' + clientFilter)
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    populateTicketTable(data.tickets);
                } else {
                    console.error('Error loading tickets:', data.message);
                    alert('failed_to_load_tickets');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('an_error_occurred_while_loading_tickets');
            });
    }
    
    // Populate the ticket selection table
    function populateTicketTable(tickets) {
        const tableBody = document.getElementById('ticketsForInvoiceBody');
        tableBody.innerHTML = '';
        
        tickets.forEach((ticket, index) => {
            // Format the sector information
            let sector = `${ticket.origin} to ${ticket.destination}`;
            if (ticket.trip_type === 'round_trip' && ticket.return_destination) {
                sector += `<br><small><?= __('return') ?>: ${ticket.return_destination}</small>`;
            }
            
            // Format the flight information
            let flight = ticket.airline;
            
            // Format the date information
            let date = formatDate(ticket.departure_date);
            if (ticket.trip_type === 'round_trip' && ticket.return_date) {
                    date += `<br><small><?= __('return') ?>: ${formatDate(ticket.return_date)}</small>`;
            }
            
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input ticket-checkbox" 
                            id="ticket-${ticket.id}" 
                            data-ticket-id="${ticket.id}"
                            data-refund-amount="${ticket.refund_to_passenger}"
                            data-charges="${ticket.charges}"
                            onchange="updateInvoiceTotal()">
                        <label class="custom-control-label" for="ticket-${ticket.id}"></label>
                    </div>
                </td>
                <td>${ticket.passenger_name}</td>
                <td>${ticket.pnr}</td>
                <td>${sector}</td>
                <td>${flight}</td>
                <td>${date}</td>
                <td class="text-right">${parseFloat(ticket.charges).toFixed(2)}</td>
                <td class="text-right">${parseFloat(ticket.refund_to_passenger).toFixed(2)}</td>
            `;
            
            tableBody.appendChild(row);
        });
        
        // Clear the total
        updateInvoiceTotal();
    }
    
    // Format date for display
    function formatDate(dateString) {
        if (!dateString) return 'N/A';
        
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    }
    
    // Get all selected tickets
    function getSelectedTickets() {
        const checkboxes = document.querySelectorAll('#ticketsForInvoiceBody input[type="checkbox"]:checked');
        const tickets = [];
        
        checkboxes.forEach(checkbox => {
            tickets.push({
                id: checkbox.dataset.ticketId,
                refundAmount: parseFloat(checkbox.dataset.refundAmount),
                charges: parseFloat(checkbox.dataset.charges)
            });
        });
        
        return tickets;
    }
    
    // Update the invoice total
    window.updateInvoiceTotal = function() {
        const checkboxes = document.querySelectorAll('#ticketsForInvoiceBody input[type="checkbox"]:checked');
        const includeCharges = document.getElementById('includeCharges').checked;
        let total = 0;
        
        checkboxes.forEach(checkbox => {
            const refundAmount = parseFloat(checkbox.dataset.refundAmount) || 0;
            const charges = parseFloat(checkbox.dataset.charges) || 0;
            total += includeCharges ? (refundAmount + charges) : refundAmount;
        });
        
        document.getElementById('invoiceTotal').textContent = total.toFixed(2);
    }

    // Add event listener for charges toggle
    document.getElementById('includeCharges').addEventListener('change', updateInvoiceTotal);
});