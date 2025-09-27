/**
 * Invoice Management Module
 */
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Select2 for client dropdown
    if (typeof $.fn.select2 !== 'undefined') {
        $('#clientForInvoice1').select2({
            dropdownParent: $('#multiTicketInvoiceModal'),
            placeholder: "Search and select client...",
            allowClear: true
        });
    }
    
    // Launch multi-ticket invoice modal
    document.getElementById('launchMultiTicketInvoice').addEventListener('click', function() {
        loadTicketsForInvoice();
        $('#multiTicketInvoiceModal').modal('show');
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
            showToast('Please select at least one ticket for the invoice');
            return;
        }
        
        const clientId = document.getElementById('clientForInvoice').value;
        if (!clientId) {
            showToast('Please select a client for the invoice');
            return;
        }
        
        const invoiceData = {
            comment: document.getElementById('invoiceComment').value,
            currency: document.getElementById('invoiceCurrency').value,
            clientName: document.getElementById('clientForInvoice').value,
            tickets: selectedTickets
        };
        
        // Send the data to a new page to generate the invoice
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'generate_multi_hotel_invoice.php';
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
});

// Load tickets for the invoice table
function loadTicketsForInvoice() {
    fetch('fetch_hotels_for_invoice.php')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                populateTicketTable(data.tickets);
            } else {
                console.error('Error loading tickets:', data.message);
                showToast('Failed to load tickets');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('An error occurred while loading tickets');
        });
}

// Populate the ticket selection table
function populateTicketTable(tickets) {
    const tableBody = document.getElementById('ticketsForInvoiceBody');
    tableBody.innerHTML = '';
    
    tickets.forEach((ticket, index) => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>
                <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input ticket-checkbox" 
                        id="ticket-${ticket.id}" 
                        data-ticket-id="${ticket.id}"
                        data-amount="${ticket.sold_amount}"
                        onchange="updateInvoiceTotal()">
                    <label class="custom-control-label" for="ticket-${ticket.id}"></label>
                </div>
            </td>
            <td>${ticket.guest_name}</td>
            <td>${ticket.order_id}</td>
            <td>${ticket.check_in_date}</td>
            <td>${ticket.check_out_date}</td>
            <td>${ticket.accommodation_details}</td>
            <td class="text-right">${parseFloat(ticket.sold_amount).toFixed(2)}</td>
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
};

// Global toast notification function
function showToast(message, type = 'success') {
    // Use SweetAlert2 if available, otherwise fallback to alert
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: type,
            title: message,
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });
    } else {
        alert(message);
    }
}