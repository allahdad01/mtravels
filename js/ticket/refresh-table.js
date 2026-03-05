// Function to refresh the ticket table without page reload
function refreshTicketTable(page = 1) {
    const ticketTable = document.getElementById('ticketTable');
    if (!ticketTable) return;

    fetch(`../api/ticket/fetch_tickets.php?page=${page}`)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                // Clear existing rows
                ticketTable.innerHTML = '';

                // Re-render rows
                let counter = 1;
                data.tickets.forEach(ticketData => {
                    const ticket = ticketData.ticket;
                    const card = createTicketCard(ticket, ticketData, counter++);
                    ticketTable.appendChild(card);
                });

                // Reinitialize any event listeners if needed
                attachTicketRowListeners();
            }
        })
        .catch(error => {
            console.error('Error refreshing table:', error);
        });
}

// Function to create a ticket card element
function createTicketCard(ticket, ticketData, counter) {
    const card = document.createElement('div');
    card.className = 'ticket-card';
    
    // Determine payment status
    let paymentStatus = 'neutral';
    let totalPaidInBase = 0.0;
    
    // Check if client is agency
    if (ticket.client_type === 'agency') {
        // Calculate payment status using transaction data
        const baseCurrency = ticket.currency;
        const soldAmount = parseFloat(ticket.sold);
        
        // Get transactions from ticketData
        const transactions = ticketData.transactions || [];
        
        if (transactions.length > 0) {
            transactions.forEach(trans => {
                const amount = parseFloat(trans.amount);
                const transCurrency = trans.currency;
                const transExchangeRate = parseFloat(trans.exchange_rate) || 1.0;
                
                let convertedAmount = 0.0;
                
                if (transCurrency === baseCurrency) {
                    convertedAmount = amount;
                } else {
                    if (baseCurrency === 'AFS') {
                        convertedAmount = amount * transExchangeRate;
                    } else {
                        convertedAmount = amount / transExchangeRate;
                    }
                }
                
                totalPaidInBase += convertedAmount;
            });
        }
        
        // Determine status
        if (totalPaidInBase <= 0) {
            paymentStatus = 'unpaid';
        } else if (totalPaidInBase < soldAmount) {
            paymentStatus = 'partial';
        } else {
            paymentStatus = 'paid';
        }
    }
    
    // Build refund, date change, weight info
    let additionalInfo = '';
    
    if (ticketData.refund_data) {
        additionalInfo += `
            <div class="ticket-card-detail-item" style="color: #ff6b6b;">
                <span class="ticket-card-detail-label">Refunded:</span>
                <span>${escapeHtml(ticketData.refund_data.currency)} ${parseFloat(ticketData.refund_data.refund_to_passenger).toFixed(2)}</span>
            </div>
        `;
    }
    
    if (ticketData.date_change_data) {
        additionalInfo += `
            <div class="ticket-card-detail-item" style="color: #ffc107;">
                <span class="ticket-card-detail-label">Date Change:</span>
                <span>${escapeHtml(ticketData.date_change_data.currency)} ${(parseFloat(ticketData.date_change_data.supplier_penalty) + parseFloat(ticketData.date_change_data.service_penalty)).toFixed(2)}</span>
            </div>
        `;
    }
    
    if (ticketData.ticket.weight_count > 0) {
        additionalInfo += `
            <div class="ticket-card-detail-item">
                <span class="ticket-card-detail-label">Weight:</span>
                <span>${ticketData.ticket.weight_count} items, ${parseFloat(ticketData.ticket.total_weight).toFixed(2)} kg</span>
            </div>
        `;
    }
    
    let returnInfo = '';
    if (ticket.trip_type === 'round_trip') {
        returnInfo = `
            <div class="ticket-card-detail-item">
                <span class="ticket-card-detail-label">Return:</span>
                <span>${escapeHtml(ticket.return_date)}${ticket.return_departure_time ? ` @ ${escapeHtml(ticket.return_departure_time)}` : ''}</span>
            </div>
        `;
    }
    
    card.innerHTML = `
        <div class="ticket-card-main status-${paymentStatus}">
            <div class="ticket-card-left">
                <div class="ticket-card-header">
                    <div class="ticket-card-status-dots">
                        <div class="ticket-card-dot primary"></div>
                        <div class="ticket-card-dot"></div>
                        <div class="ticket-card-dot"></div>
                        <div class="ticket-card-dot"></div>
                    </div>
                </div>
                <div>
                    <div class="ticket-card-title">
                        TICKET
                        <span></span>
                    </div>
                    <div class="ticket-card-id">${escapeHtml(ticket.pnr)}</div>
                </div>
                <div class="ticket-card-details">
                    <div class="ticket-card-detail-item">
                        <span class="ticket-card-detail-label">Sold To:</span>
                        <span>${escapeHtml(ticket.sold_to)}</span>
                    </div>
                    <div class="ticket-card-detail-item">
                        <span class="ticket-card-detail-label">Passenger:</span>
                        <span>${escapeHtml(ticket.title)} ${escapeHtml(ticket.passenger_name)}</span>
                    </div>
                    <div class="ticket-card-detail-item">
                        <span class="ticket-card-detail-label">Route:</span>
                        <span>${escapeHtml(ticket.origin)} → ${escapeHtml(ticket.destination)}</span>
                    </div>
                    <div class="ticket-card-detail-item">
                        <span class="ticket-card-detail-label">Airline:</span>
                        <span>${escapeHtml(ticket.airline)}</span>
                    </div>
                    <div class="ticket-card-detail-item">
                        <span class="ticket-card-detail-label">Issue Date:</span>
                        <span>${escapeHtml(ticket.issue_date)}</span>
                    </div>
                    <div class="ticket-card-detail-item">
                        <span class="ticket-card-detail-label">Departure:</span>
                        <span>${escapeHtml(ticket.departure_date)}${ticket.departure_time ? ` @ ${escapeHtml(ticket.departure_time)}` : ''}</span>
                    </div>
                    ${returnInfo}
                    ${additionalInfo}
                </div>
            </div>
            <div class="ticket-card-right">
                <div class="ticket-card-price-box">${parseFloat(ticket.sold).toFixed(2)}</div>
                <div class="ticket-card-price-meta">
                    <div class="ticket-card-meta-dot"></div>
                    <div class="ticket-card-meta-dot"></div>
                    <div class="ticket-card-meta-dot"></div>
                    <span>${escapeHtml(ticket.currency)}</span>
                </div>
            </div>
        </div>
        <div class="ticket-card-stub">
            <div class="ticket-card-actions" style="display: flex; flex-direction: column; gap: 6px;">
                <button class="ticket-card-action-btn view-details" data-ticket='${JSON.stringify(ticketData).replace(/'/g, "&apos;")}' title="View Details">
                    <i class="feather icon-eye"></i>
                </button>
                <button class="ticket-card-action-btn" onclick="editTicket(${ticket.id})" title="Edit">
                    <i class="feather icon-edit-2"></i>
                </button>
                ${ticket.client_type === 'agency' ? `
                    <button class="ticket-card-action-btn" onclick="manageTransactions(${ticket.id})" title="Manage Transactions">
                        <i class="fas fa-dollar-sign"></i>
                    </button>
                ` : ''}
                <button class="ticket-card-action-btn" onclick="deleteTicket(${ticket.id})" title="Delete">
                    <i class="feather icon-trash-2"></i>
                </button>
            </div>
        </div>
    `;
    
    return card;
}

// Helper function to escape HTML
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text ? text.replace(/[&<>"']/g, m => map[m]) : '';
}

// Function to reattach event listeners to new rows
function attachTicketRowListeners() {
    // Reattach view details listeners
    document.querySelectorAll('.view-details').forEach(btn => {
        btn.addEventListener('click', function() {
            const ticketData = JSON.parse(this.getAttribute('data-ticket'));
            // Trigger modal or whatever you use to show details
            if (typeof showTicketDetails === 'function') {
                showTicketDetails(ticketData);
            }
        });
    });
}
