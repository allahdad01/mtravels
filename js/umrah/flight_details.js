// Flight Details Modal Handler

// View family flight details - wrapper function
function viewFamilyFlightDetails(familyId, familyName) {
    showFlightDetailsModal(familyId, familyName);
}

// Global function to open flight details modal
function showFlightDetailsModal(familyId, familyName) {
    // Clear previous content
    document.getElementById('flightTicketsContainer').innerHTML = '';
    document.getElementById('flightDetailsLoading').style.display = 'none';
    document.getElementById('flightDetailsError').style.display = 'none';
    document.getElementById('flightDetailsEmpty').style.display = 'none';
    
    // Set family name
    document.getElementById('flightFamilyName').textContent = familyName;
    
    // Show loading state
    document.getElementById('flightDetailsLoading').style.display = 'block';
    
    // Fetch flight details
    fetch(`../api/umrah/get_group_ticket_info.php?family_id=${familyId}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('flightDetailsLoading').style.display = 'none';
            
            if (data.success && data.tickets && data.tickets.length > 0) {
                // Build ticket HTML
                let ticketsHTML = '';
                data.tickets.forEach((ticket, index) => {
                    const flightDate = new Date(ticket.flight_date);
                    const returnDate = new Date(ticket.return_date);
                    const formattedFlightDate = flightDate.toLocaleDateString('en-US', { 
                        weekday: 'short', 
                        year: 'numeric', 
                        month: 'short', 
                        day: 'numeric' 
                    });
                    const formattedReturnDate = returnDate.toLocaleDateString('en-US', { 
                        weekday: 'short', 
                        year: 'numeric', 
                        month: 'short', 
                        day: 'numeric' 
                    });
                    const flightTime = flightDate.toLocaleTimeString('en-US', { 
                        hour: '2-digit', 
                        minute: '2-digit' 
                    });

                    // Build members list HTML
                    let membersListHTML = '';
                    if (data.members && data.members.length > 0) {
                        membersListHTML = `
                            <div style="margin-top: 16px;">
                                <div style="
                                    font-size: 12px;
                                    opacity: 0.8;
                                    text-transform: uppercase;
                                    letter-spacing: 1px;
                                    margin-bottom: 12px;
                                    border-bottom: 1px solid rgba(255, 255, 255, 0.2);
                                    padding-bottom: 8px;
                                ">Passengers on this Flight</div>
                                <div style="display: grid; gap: 8px;">
                        `;
                        
                        data.members.forEach(member => {
                            const memberStatus = member.flight_date ? '✓' : '○';
                            const statusColor = member.flight_date ? '#28a745' : 'rgba(255, 255, 255, 0.5)';
                            
                            membersListHTML += `
                                <div style="
                                    display: flex;
                                    align-items: center;
                                    padding: 8px;
                                    background: rgba(0, 0, 0, 0.1);
                                    border-radius: 4px;
                                    font-size: 13px;
                                ">
                                    <div style="
                                        width: 24px;
                                        height: 24px;
                                        border-radius: 50%;
                                        background: ${statusColor};
                                        display: flex;
                                        align-items: center;
                                        justify-content: center;
                                        margin-right: 8px;
                                        font-size: 14px;
                                        color: white;
                                    ">${memberStatus}</div>
                                    <div style="flex: 1;">
                                        <div style="font-weight: 500;">${member.name}</div>
                                        ${member.flight_date ? `<div style="font-size: 11px; opacity: 0.7;">Flight confirmed</div>` : `<div style="font-size: 11px; opacity: 0.7; color: #ffaa00;">Pending flight assignment</div>`}
                                    </div>
                                </div>
                            `;
                        });
                        
                        membersListHTML += `
                                </div>
                            </div>
                        `;
                    }
                    
                    ticketsHTML += `
                        <div class="flight-ticket">
                            <div class="flight-ticket-header">
                                <div>
                                    <div class="flight-ticket-header-label">Group Ticket ${index + 1}</div>
                                    <div class="flight-ticket-header-value">${ticket.airline_name || 'Airline'}</div>
                                </div>
                                <div style="text-align: right;">
                                    <div class="flight-ticket-header-label">PNR</div>
                                    <div class="flight-ticket-pnr">${ticket.pnr || 'N/A'}</div>
                                </div>
                            </div>

                            <div class="flight-ticket-body">
                                <!-- Flight Journey -->
                                <div class="flight-journey">
                                    <!-- Outbound -->
                                    <div class="flight-journey-point">
                                        <div class="flight-journey-label">Departure</div>
                                        <div class="flight-journey-date">${formattedFlightDate}</div>
                                        <div class="flight-journey-time">${flightTime}</div>
                                    </div>
                                    
                                    <!-- Flight Icon -->
                                    <div class="flight-journey-icon">
                                        <div class="flight-journey-plane">✈️</div>
                                        <div class="flight-journey-type">
                                            ${ticket.flight_type === 'direct' ? 'Direct' : 'Connecting'}
                                        </div>
                                    </div>

                                    <!-- Return -->
                                    <div class="flight-journey-point return">
                                        <div class="flight-journey-label">Return</div>
                                        <div class="flight-journey-date">${formattedReturnDate}</div>
                                        <div class="flight-journey-time">Return Journey</div>
                                    </div>
                                </div>

                                <!-- Flight Details -->
                                <div class="flight-details-section">
                                    <div>
                                        <div class="flight-detail-item-label">Flight Type</div>
                                        <div class="flight-detail-item-value">
                                            ${ticket.flight_type === 'direct' ? 'Direct Flight' : 'Indirect (Connecting) Flight'}
                                        </div>
                                    </div>
                                    <div>
                                        <div class="flight-detail-item-label">Duration</div>
                                        <div class="flight-detail-item-value">${ticket.duration || 'Varies'}</div>
                                    </div>
                                </div>

                                <!-- Passenger Info -->
                                <div class="flight-passenger-info">
                                    <div class="flight-passenger-info-item">
                                        <strong>Status:</strong> <span class="flight-status-active">Active</span>
                                    </div>
                                </div>

                                <!-- Members List -->
                                ${membersListHTML}
                            </div>

                            <!-- Ticket Footer -->
                            <div class="flight-ticket-footer">
                                Group Ticket • Reference: ${ticket.ticket_id}
                            </div>
                        </div>
                    `;
                });
                
                document.getElementById('flightTicketsContainer').innerHTML = ticketsHTML;
            } else if (data.success) {
                // No tickets found
                document.getElementById('flightDetailsEmpty').style.display = 'block';
            } else {
                // API error
                document.getElementById('flightDetailsError').style.display = 'block';
                document.getElementById('flightDetailsErrorMessage').textContent = data.message || 'Failed to load flight details.';
            }
        })
        .catch(error => {
            console.error('Error loading flight details:', error);
            document.getElementById('flightDetailsLoading').style.display = 'none';
            document.getElementById('flightDetailsError').style.display = 'block';
            document.getElementById('flightDetailsErrorMessage').textContent = 'Network error. Please try again.';
        });
    
    // Open modal
    $('#flightDetailsModal').modal('show');
}
