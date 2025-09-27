document.addEventListener('DOMContentLoaded', function() {
    // Launch multi-ticket invoice modal
    $('#launchMultiTicketInvoice').on('click', function() {
        loadTicketsForInvoice();
        $('#multiTicketInvoiceModal').modal({
            backdrop: 'static',
            keyboard: false
        });
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
            showToast('Please select at least one ticket for the invoice', 'warning');
            return;
        }
        
        const clientName = document.getElementById('clientForInvoice').value;
        if (!clientName) {
            showToast('Please enter a client name for the invoice', 'warning');
            return;
        }
        
        const invoiceData = {
            comment: document.getElementById('invoiceComment').value,
            currency: document.getElementById('invoiceCurrency').value,
            clientName: clientName,
            tickets: selectedTickets
        };
        
        // Show loading state
        showToast('Generating invoice, please wait...', 'info');
        
        // Send the data to generate the invoice
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'generate_multi_ticket_invoice.php';
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
        
        // Show success message
        showToast('The combined invoice has been generated successfully', 'success');
    });
    
    // Load tickets for the invoice table
    function loadTicketsForInvoice() {
        // Show loading state
        showToast('Loading tickets, please wait...', 'info');
        
        fetch('fetch_tickets_for_invoice.php')
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    populateTicketTable(data.tickets);
                } else {
                    throw new Error(data.message || 'Failed to load tickets');
                }
            })
            .catch(error => {
                showToast(error.message || 'Failed to load tickets', 'error');
            });
    }

    // Add client filter event listener
    document.getElementById('clientFilter').addEventListener('change', function() {
        const selectedClient = this.value;
        const rows = document.querySelectorAll('#ticketsForInvoiceBody tr');
        
        rows.forEach(row => {
            const clientCell = row.querySelector('td[data-client]');
            if (selectedClient === '' || clientCell.getAttribute('data-client') === selectedClient) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
        
        updateInvoiceTotal();
    });
    
    // Populate the ticket selection table
    function populateTicketTable(tickets) {
        const tableBody = document.getElementById('ticketsForInvoiceBody');
        tableBody.innerHTML = '';
        
        tickets.forEach((ticket, index) => {
            // Format the sector information
            let sector = `${ticket.origin} to ${ticket.destination}`;
            if (ticket.trip_type === 'round_trip' && ticket.return_destination) {
                    sector += `<br><small>Return: ${ticket.return_destination}</small>`;
            }
            
            // Format the flight information
            let flight = ticket.airline;
            
            // Format the date information
            let date = formatDate(ticket.departure_date);
            if (ticket.trip_type === 'round_trip' && ticket.return_date) {
                    date += `<br><small>Return: ${formatDate(ticket.return_date)}</small>`;
            }
            
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input ticket-checkbox" 
                            id="ticket-${ticket.id}" 
                            data-ticket-id="${ticket.id}"
                            data-amount="${ticket.sold}"
                            onchange="updateInvoiceTotal()">
                        <label class="custom-control-label" for="ticket-${ticket.id}"></label>
                    </div>
                </td>
                <td data-client="${ticket.sold_to_name}">${ticket.sold_to_name}</td>
                <td>${ticket.passenger_name}</td>
                <td>${ticket.pnr}</td>
                <td>${sector}</td>
                <td>${flight}</td>
                <td>${date}</td>
                <td class="text-right">${parseFloat(ticket.sold).toFixed(2)}</td>
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
            tickets.push(checkbox.dataset.ticketId);
        });
        
        return tickets;
    }
    
    // Update the invoice total
    window.updateInvoiceTotal = function() {
        const checkboxes = document.querySelectorAll('#ticketsForInvoiceBody input[type="checkbox"]:checked');
        let total = 0;
        
        checkboxes.forEach(checkbox => {
            total += parseFloat(checkbox.dataset.amount) || 0;
        });
        
        document.getElementById('invoiceTotal').textContent = total.toFixed(2);
    }
    
    // Helper function to show alerts
    function showToast(text, icon) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: icon,
                title: text,
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                didOpen: (toast) => {
                    toast.addEventListener('mouseenter', Swal.stopTimer)
                         .addEventListener('mouseleave', Swal.resumeTimer)
                }
            });
        } else {
            alert(text);
        }
    }
}); 