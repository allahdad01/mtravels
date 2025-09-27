// Dashboard Dues JS - Updates dues cards with dynamic percentages
document.addEventListener('DOMContentLoaded', function() {
    // Fetch dues data from the server
    fetch('get_dues_summary.php')
        .then(response => response.json())
        .then(data => {
            
            // Update the dues amounts
            updateDuesAmounts(data);
        })
        .catch(error => {
            console.error('Error fetching dues data:', error);
        });
});

/**
 * Update the dues amounts in the dashboard cards
 * @param {Object} data - The dues data from the server
 */
function updateDuesAmounts(data) {
    // Format USD amounts
    if (document.getElementById('ticketDuesUSD')) {
        document.getElementById('ticketDuesUSD').textContent = '$' + formatNumber(data.ticket_reserve_dues_usd);
    }
    if (document.getElementById('ticketReserveDuesUSD')) {
        document.getElementById('ticketReserveDuesUSD').textContent = '$' + formatNumber(data.ticket_reserve_dues_usd);
    }
    if (document.getElementById('weightDuesUSD')) {
        document.getElementById('weightDuesUSD').textContent = '$' + formatNumber(data.ticket_weights_dues_usd);
    }
    if (document.getElementById('dateChangeDuesUSD')) {
        document.getElementById('dateChangeDuesUSD').textContent = '$' + formatNumber(data.datechange_dues_usd);
    }
    if (document.getElementById('refundedDuesUSD')) {
        document.getElementById('refundedDuesUSD').textContent = '$' + formatNumber(data.refunded_ticket_dues_usd);
    }
    if (document.getElementById('umrahDuesUSD')) {
        document.getElementById('umrahDuesUSD').textContent = '$' + formatNumber(data.umrah_dues_usd);
    }
    if (document.getElementById('visaDuesUSD')) {
        document.getElementById('visaDuesUSD').textContent = '$' + formatNumber(data.visa_dues_usd);
    }
    if (document.getElementById('hotelDuesUSD')) {
        document.getElementById('hotelDuesUSD').textContent = '$' + formatNumber(data.hotel_dues_usd);
    }
    if (document.getElementById('addpaymentDuesUSD')) {
        document.getElementById('addpaymentDuesUSD').textContent = '$' + formatNumber(data.addpayment_dues_usd);
    }

    // Format AFS amounts
    if (document.getElementById('ticketDuesAFS')) {
        document.getElementById('ticketDuesAFS').textContent = '؋' + formatNumber(data.ticket_reserve_dues_afs);
    }
    if (document.getElementById('ticketReserveDuesAFS')) {
        document.getElementById('ticketReserveDuesAFS').textContent = '؋' + formatNumber(data.ticket_reserve_dues_afs);
    }
    if (document.getElementById('weightDuesAFS')) {
        document.getElementById('weightDuesAFS').textContent = '؋' + formatNumber(data.ticket_weights_dues_afs);
    }
    if (document.getElementById('dateChangeDuesAFS')) {
        document.getElementById('dateChangeDuesAFS').textContent = '؋' + formatNumber(data.datechange_dues_afs);
    }
    if (document.getElementById('refundedDuesAFS')) {
        document.getElementById('refundedDuesAFS').textContent = '؋' + formatNumber(data.refunded_ticket_dues_afs);
    }
    if (document.getElementById('umrahDuesAFS')) {
        document.getElementById('umrahDuesAFS').textContent = '؋' + formatNumber(data.umrah_dues_afs);
    }
    if (document.getElementById('visaDuesAFS')) {
        document.getElementById('visaDuesAFS').textContent = '؋' + formatNumber(data.visa_dues_afs);
    }
    if (document.getElementById('hotelDuesAFS')) {
        document.getElementById('hotelDuesAFS').textContent = '؋' + formatNumber(data.hotel_dues_afs);
    }
    if (document.getElementById('addpaymentDuesAFS')) {
        document.getElementById('addpaymentDuesAFS').textContent = '؋' + formatNumber(data.addpayment_dues_afs);
    }
}


/**
 * Format a number with thousands separators and two decimal places
 * @param {number} number - The number to format
 * @returns {string} The formatted number
 */
function formatNumber(number) {
    return parseFloat(number).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
} 