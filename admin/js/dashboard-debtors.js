document.addEventListener('DOMContentLoaded', function() {
    // Load all dues data
    loadDuesData();

    // Add click handlers for due cards
    document.querySelectorAll('.due-card').forEach(card => {
        card.addEventListener('click', function() {
            const dueType = this.dataset.type;
            loadDebtorsList(dueType);
        });
    });
});

function loadDuesData() {
    fetch('get_dues_summary.php')
        .then(response => response.json())
        .then(data => {
            // Update USD dues
            document.getElementById('ticketDuesUSD').textContent = formatCurrency(data.ticket_dues_usd, 'USD');
            document.getElementById('ticketReserveDuesUSD').textContent = formatCurrency(data.ticket_reserve_dues_usd, 'USD');
            document.getElementById('weightDuesUSD').textContent = formatCurrency(data.ticket_weights_dues_usd, 'USD');
            document.getElementById('refundedDuesUSD').textContent = formatCurrency(data.refunded_dues_usd, 'USD');
            document.getElementById('dateChangeDuesUSD').textContent = formatCurrency(data.datechange_dues_usd, 'USD');
            document.getElementById('umrahDuesUSD').textContent = formatCurrency(data.umrah_dues_usd, 'USD');
            document.getElementById('visaDuesUSD').textContent = formatCurrency(data.visa_dues_usd, 'USD');
            document.getElementById('hotelDuesUSD').textContent = formatCurrency(data.hotel_dues_usd, 'USD');
            document.getElementById('addpaymentDuesUSD').textContent = formatCurrency(data.addpayment_dues_usd, 'USD');

            // Update AFS dues
            document.getElementById('ticketDuesAFS').textContent = formatCurrency(data.ticket_dues_afs, 'AFS');
            document.getElementById('ticketReserveDuesAFS').textContent = formatCurrency(data.ticket_reserve_dues_afs, 'AFS');
            document.getElementById('weightDuesAFS').textContent = formatCurrency(data.ticket_weights_dues_afs, 'AFS');
            document.getElementById('refundedDuesAFS').textContent = formatCurrency(data.refunded_dues_afs, 'AFS');
            document.getElementById('dateChangeDuesAFS').textContent = formatCurrency(data.datechange_dues_afs, 'AFS');
            document.getElementById('umrahDuesAFS').textContent = formatCurrency(data.umrah_dues_afs, 'AFS');
            document.getElementById('visaDuesAFS').textContent = formatCurrency(data.visa_dues_afs, 'AFS');
            document.getElementById('hotelDuesAFS').textContent = formatCurrency(data.hotel_dues_afs, 'AFS');
            document.getElementById('addpaymentDuesAFS').textContent = formatCurrency(data.addpayment_dues_afs, 'AFS');
        })
        .catch(error => console.error('Error loading dues:', error));
}

function loadDebtorsList(type) {
    fetch(`get_debtors.php?type=${type}`)
        .then(response => response.json())
        .then(data => {
            const tableBody = document.getElementById('debtorsTableBody');
            
            // Check if the element exists before trying to modify it
            if (!tableBody) {
                console.error('Element with ID "debtorsTableBody" not found');
                return;
            }
            
            tableBody.innerHTML = '';
            
            data.forEach(debtor => {
                const row = `
                    <tr>
                        <td>${debtor.name}</td>
                        <td>${debtor.pnr}</td>
                        <td>${debtor.phone}</td>
                        <td class="text-danger">
                            ${debtor.currency === 'USD' ? 
                              formatCurrency(debtor.amount_due, 'USD') : 
                              formatCurrency(debtor.amount_due, 'AFS')}
                        </td>
                        <td>${formatDate(debtor.date)}</td>
                    </tr>
                `;
                tableBody.insertAdjacentHTML('beforeend', row);
            });

            // Update modal title and show it
            const modalTitle = document.getElementById('debtorsModalTitle');
            if (modalTitle) {
                modalTitle.textContent = `${type.charAt(0).toUpperCase() + type.slice(1)} Debtors`;
            }
            
            // Check if Bootstrap modal is available
            if (typeof $ !== 'undefined' && $.fn.modal) {
                $('#debtorsModal').modal('show');
            } else {
                console.error('Bootstrap modal not available');
            }
        })
        .catch(error => console.error('Error loading debtors:', error));
}

function formatCurrency(amount, currency) {
    if (amount === null || amount === undefined || isNaN(amount)) {
        amount = 0;
    }
    
    const formatter = new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency,
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
    
    return formatter.format(amount);
}

function formatDate(dateString) {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
} 