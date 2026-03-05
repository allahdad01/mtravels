/**
 * Umrah Statistics Dashboard Module
 * Loads and displays umrah booking statistics on the dashboard
 */

document.addEventListener('DOMContentLoaded', function() {
    // Load umrah statistics when page loads
    loadUmrahStatistics();
    
    // Refresh every 30 seconds
    setInterval(loadUmrahStatistics, 30000);
});

/**
 * Load umrah statistics from API and update dashboard
 */
function loadUmrahStatistics() {
    const period = 'monthly'; // Default to monthly view
    const currentDate = new Date();
    const filteredDate = currentDate.getFullYear() + '-' + 
                         String(currentDate.getMonth() + 1).padStart(2, '0');
    
    // Fetch umrah details via AJAX
    $.ajax({
        type: 'POST',
        url: '../api/dashboard/get_umrah_details.php',
        data: {
            period: period,
            filtered_date: filteredDate
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                updateUmrahStatistics(response.data);
            } else {
                console.error('Error fetching umrah details:', response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX error fetching umrah details:', error);
        }
    });
    
    // Fetch umrah dues summary
    fetchUmrahDues();
}

/**
 * Update umrah statistics UI elements
 * @param {Array} bookings - Array of umrah booking objects
 */
function updateUmrahStatistics(bookings) {
    if (!bookings || bookings.length === 0) {
        // No bookings found
        $('#umrahTotalBookings').text('0');
        $('#umrahRevenueUSD').text('0.00');
        $('#umrahActivePackages').text('0');
        $('#umrahServices').text('0');
        updateUmrahBookingsTable([]);
        return;
    }
    
    // Calculate statistics
    let totalBookings = bookings.length;
    let ongoingBookings = 0;  // Count of bookings that are in progress (active or pending)
    let totalPassengers = 0;  // Total number of pilgrims/passengers
    
    bookings.forEach(function(booking) {
        // Count bookings that are in progress (not completed/refunded/cancelled)
        const status = (booking.status || '').toLowerCase();
        if (status === 'active' || status === 'pending') {
            ongoingBookings++;
        }
        
        // Count total passengers across all bookings
        totalPassengers++;
    });
    
    // Update UI elements
    $('#umrahTotalBookings').text(totalBookings);
    $('#umrahActivePackages').text(ongoingBookings);  // Number of ongoing (in-progress) bookings
    $('#umrahServices').text(totalPassengers);  // Total pilgrims/passengers
    
    // Update bookings table
    updateUmrahBookingsTable(bookings);
}

/**
 * Update the recent bookings table
 * @param {Array} bookings - Array of umrah booking objects
 */
function updateUmrahBookingsTable(bookings) {
     const tableBody = $('#umrahBookingsTable');
     
     if (!bookings || bookings.length === 0) {
         tableBody.html(`
             <tr>
                 <td colspan="5" class="text-center">
                     <i class="fas fa-inbox mr-2" style="opacity: 0.5;"></i><span style="color: var(--text-muted);">No bookings found</span>
                 </td>
             </tr>
         `);
         return;
     }
     
     let html = '';
     
     // Show only first 5 bookings in table
     bookings.slice(0, 5).forEach(function(booking) {
         const bookingId = booking.id || 'N/A';
         const passengerName = booking.name || 'N/A';
         const packageType = booking.package_type || 'Standard';
         const amount = parseFloat(booking.profit) || 0;
         const currency = booking.currency === 'USD' ? '$' : '؋';
         const createdDate = booking.created_at ? new Date(booking.created_at).toLocaleDateString() : 'N/A';
         
         // Determine status badge color and text
         let statusBadgeClass = 'badge-secondary';
         let statusText = 'Unknown';
         
         if (booking.status) {
             const status = booking.status.toLowerCase();
             switch(status) {
                 case 'active':
                     statusBadgeClass = 'badge-success';
                     statusText = 'Active';
                     break;
                 case 'pending':
                     statusBadgeClass = 'badge-info';
                     statusText = 'Pending';
                     break;
                 case 'refunded':
                     statusBadgeClass = 'badge-warning';
                     statusText = 'Refunded';
                     break;
                 case 'cancelled':
                     statusBadgeClass = 'badge-danger';
                     statusText = 'Cancelled';
                     break;
                 default:
                     statusBadgeClass = 'badge-secondary';
                     statusText = status.charAt(0).toUpperCase() + status.slice(1);
             }
         }
         
         html += `
             <tr>
                 <td><small class="font-weight-bold">#${bookingId}</small></td>
                 <td><small>${packageType}</small></td>
                 <td><small>${passengerName}</small></td>
                 <td><small class="text-success font-weight-bold">${currency}${amount.toFixed(2)}</small></td>
                 <td>
                     <span class="${statusBadgeClass} badge-pill">${statusText}</span>
                 </td>
             </tr>
         `;
     });
     
     tableBody.html(html);
}

/**
 * Fetch and display umrah dues
 */
function fetchUmrahDues() {
    $.ajax({
        type: 'GET',
        url: '../api/dashboard/get_dues_summary.php',
        dataType: 'json',
        success: function(dues) {
            if (dues && typeof dues === 'object') {
                // Update umrah dues display
                let usdDues = dues.umrah_dues_usd || 0;
                let afsDues = dues.umrah_dues_afs || 0;
                
                $('#umrahDuesUSD').text('$' + parseFloat(usdDues).toFixed(2));
                $('#umrahDuesAFS').text('؋' + parseFloat(afsDues).toFixed(2));
            }
        },
        error: function(xhr, status, error) {
            console.error('Error fetching umrah dues:', error);
        }
    });
}
