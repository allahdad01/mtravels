// Search functionality
function searchTickets(searchType) {
    const searchValue = searchType === 'pnr' 
        ? document.getElementById('searchPNR').value.trim()
        : document.getElementById('searchName').value.trim();
    
    if (!searchValue) {
        alert(searchType === 'pnr' 
            ? 'Please enter PNR' 
            : 'Please enter passenger name');
        return;
    }

    fetch('ajax/search_tickets.php?' + searchType + '=' + encodeURIComponent(searchValue))
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displaySearchResults(data.tickets);
            } else {
                alert(data.message || 'No tickets found');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error searching for tickets');
        });
}

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
                        Select
                    </button>
                </td>
            `;
            tbody.appendChild(row);
        });
        resultsDiv.style.display = 'block';
    } else {
        resultsDiv.style.display = 'none';
        alert('No tickets found');
    }
}

// Initialize search functionality
document.addEventListener('DOMContentLoaded', function() {
    // Add search input event listeners if needed
    const searchPNR = document.getElementById('searchPNR');
    const searchName = document.getElementById('searchName');
    
    if (searchPNR) {
        searchPNR.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchTickets('pnr');
            }
        });
    }
    
    if (searchName) {
        searchName.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchTickets('passenger');
            }
        });
    }
}); 