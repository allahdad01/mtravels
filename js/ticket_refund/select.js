// Search for tickets by PNR or passenger name
function searchTickets(searchType) {
    const searchValue = searchType === 'pnr' 
        ? document.getElementById('searchPNR').value.trim()
        : document.getElementById('searchName').value.trim();
    
    if (!searchValue) {
        alert(searchType === 'pnr' 
            ? 'please_enter_pnr' 
            : 'please_enter_passenger_name');
        return;
    }

    fetch('ajax/search_tickets.php?' + searchType + '=' + encodeURIComponent(searchValue))
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displaySearchResults(data.tickets);
            } else {
                alert(data.message || 'no_tickets_found');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('error_searching_for_tickets');
        });
}

// Display search results
function displaySearchResults(tickets) {
    const resultsDiv = document.getElementById('searchResults');
    const tbody = document.getElementById('searchResultsBody');
    tbody.innerHTML = '';

    if (tickets && tickets.length > 0) {
        tickets.forEach(ticket => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>
                    <div class="d-flex align-items-center">
                        <div class="avatar bg-light-primary">
                            <span>${ticket.passenger_name.charAt(0).toUpperCase()}</span>
                        </div>
                        <div class="ml-2">
                            <h6 class="mb-0">${ticket.title} ${ticket.passenger_name}</h6>
                            <small class="text-muted">${ticket.phone || 'N/A'}</small>
                        </div>
                    </div>
                </td>
                <td>${ticket.pnr}</td>
                <td>
                    <div class="d-flex flex-column">
                        <span class="font-weight-bold">${ticket.airline}</span>
                        <small>${ticket.origin} - ${ticket.destination}</small>
                    </div>
                </td>
                <td>${new Date(ticket.departure_date).toLocaleDateString()}</td>
                <td>
                    <button type="button" class="btn btn-sm btn-primary" 
                            onclick='selectTicket(${JSON.stringify(ticket)})'>
                        <?= __('select') ?>
                    </button>
                </td>
            `;
            tbody.appendChild(row);
        });
        resultsDiv.style.display = 'block';
    } else {
        resultsDiv.style.display = 'none';
        alert('no_tickets_found');
    }
}

// Select ticket for refund
function selectTicket(ticket) {
    console.log('Selecting ticket:', ticket);
    
    // Validate ticket data
    if (!ticket || !ticket.id || !ticket.price || !ticket.sold) {
        console.error('Invalid ticket data:', ticket);
        return;
    }
    
    // Set ticket details
    document.getElementById('ticketId').value = ticket.id;
    document.getElementById('base').value = parseFloat(ticket.price).toFixed(2);
    document.getElementById('sold').value = parseFloat(ticket.sold).toFixed(2);
    
    // Set currency display in the UI
    const currency = ticket.currency || 'USD';
    document.getElementById('baseCurrency').textContent = currency;
    document.getElementById('soldCurrency').textContent = currency;
    document.getElementById('penaltyCurrency').textContent = currency;
    document.getElementById('penaltyCurrency2').textContent = currency;
    document.getElementById('totalPenaltyCurrency').textContent = currency;
    document.getElementById('refundCurrency').textContent = currency;
    
    // Reset form fields
    document.getElementById('supplier_penalty').value = '0';
    document.getElementById('service_penalty').value = '0';
    document.getElementById('description').value = '';
    document.getElementById('calculationMethod').value = 'sold';
    
    // Show the refund form
    const refundForm = document.getElementById('refundTicketForm');
    if (refundForm) {
        refundForm.style.display = 'block';
        
        // Add event listeners for all form inputs
        document.getElementById('supplier_penalty').onchange = calculateRefund;
        document.getElementById('supplier_penalty').oninput = calculateRefund;
        document.getElementById('service_penalty').onchange = calculateRefund;
        document.getElementById('service_penalty').oninput = calculateRefund;
        document.getElementById('calculationMethod').onchange = calculateRefund;
        
        // Calculate initial refund
        calculateRefund();
    } else {
        console.error('Refund form not found');
    }
}

// Calculate refund amount
function calculateRefund() {
    const base = parseFloat(document.getElementById('base').value) || 0;
    const sold = parseFloat(document.getElementById('sold').value) || 0;
    const supplierPenalty = parseFloat(document.getElementById('supplier_penalty').value) || 0;
    const servicePenalty = parseFloat(document.getElementById('service_penalty').value) || 0;
    const calculationMethod = document.getElementById('calculationMethod').value;
    
    // Calculate total penalty
    const totalPenalty = supplierPenalty + servicePenalty;
    
    // Update total penalty field
    const totalPenaltyField = document.getElementById('totalPenalty');
    if (totalPenaltyField) {
        totalPenaltyField.value = totalPenalty.toFixed(2);
    }

    // Calculate refund amount based on selected method
    let refundAmount;
    let calculationText;
    
    if (calculationMethod === 'sold') {
        refundAmount = sold - totalPenalty;
        calculationText = `Sold Amount (${sold.toFixed(2)}) - Total Penalty (${totalPenalty.toFixed(2)})`;
    } else { // base
        refundAmount = base - totalPenalty;
        calculationText = `Base Amount (${base.toFixed(2)}) - Total Penalty (${totalPenalty.toFixed(2)})`;
    }

    // Update the refund amount field
    const refundField = document.getElementById('refundPassengerAmount');
    if (refundField) {
        refundField.value = refundAmount > 0 ? refundAmount.toFixed(2) : '0.00';
    }

    // Update the calculation info text
    const infoText = document.getElementById('refundCalculationInfo');
    const currency = document.getElementById('refundCurrency').textContent || 'USD';
    if (infoText) {
        infoText.textContent = `${calculationText} = ${refundAmount > 0 ? refundAmount.toFixed(2) : '0.00'} ${currency}`;
    }
}

 // Show toast notification
    function showToast(message, type) { 

        if (typeof Swal !== 'undefined') {
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                didOpen: (toast) => {
                    toast.addEventListener('mouseenter', Swal.stopTimer)
                    toast.addEventListener('mouseleave', Swal.resumeTimer)
                }
            });
            
            Toast.fire({
                icon: type,
                title: message
            });
        } else {
            // Fallback to alert if SweetAlert2 is not available
            alert(message);
        }
    }
// Save refund ticket
function saveRefundTicket() {
    
    const form = document.getElementById('refundTicketForm');
    const formData = new FormData(form);

    if (!formData.get('ticketId')) {
        showToast('Please select a ticket first', 'error');
        return;
    }

    // Validate penalties and refund amount
    const supplierPenalty = parseFloat(document.getElementById('supplier_penalty').value) || 0;
    const servicePenalty = parseFloat(document.getElementById('service_penalty').value) || 0;
    const refundAmount = parseFloat(document.getElementById('refundPassengerAmount').value) || 0;

    if (supplierPenalty <= 0 && servicePenalty <= 0) {
        if (!confirm('No penalties entered. Continue?')) return;
    }

    if (refundAmount <= 0) {
        showToast('Refund amount must be greater than zero', 'error');
        return;
    }

    // Add refund amount to form data
    formData.append('refund_amount', refundAmount);

    fetch('insert_ticket_record.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text()) // treat response as text
    .then(data => {
        if (data.trim() === 'success') {
            showToast('Refund ticket saved successfully', 'success');

            // Reload the page or reload the table
            // Option 1: Reload full page
            // location.reload();

            // Option 2: Reload the refund table via AJAX
            location.reload();


            // Reset the form
            form.reset();
            document.getElementById('supplier_penalty').value = 0;
            document.getElementById('service_penalty').value = 0;
            document.getElementById('refundPassengerAmount').value = 0;
        } else {
            showToast(data || 'Error saving refund ticket', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error saving refund ticket', 'error');
    });
}


// Function to set up refund form listeners
function setupRefundFormListeners() {
    // Add event listeners for penalty inputs
    document.getElementById('supplier_penalty').addEventListener('input', calculateRefund);
    document.getElementById('service_penalty').addEventListener('input', calculateRefund);
    
    // Add event listener for calculation method change
    document.getElementById('calculationMethod').addEventListener('change', calculateRefund);
    
    // Set default values and calculate initial refund
    document.getElementById('supplier_penalty').value = '0';
    document.getElementById('service_penalty').value = '0';
    document.getElementById('calculationMethod').value = 'sold';
    
    // Calculate initial refund
    calculateRefund();
}

// Initialize when document is ready
document.addEventListener('DOMContentLoaded', function() {
    // Set up modal event listeners
    $('#addRefundTicketModal').on('shown.bs.modal', function() {
        setupRefundFormListeners();
    });
});