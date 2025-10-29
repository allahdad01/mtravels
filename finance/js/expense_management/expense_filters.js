// Function to format date as YYYY-MM-DD
function formatDateISO(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0'); // Add leading zero if needed
    const day = String(date.getDate()).padStart(2, '0'); // Add leading zero if needed
    return `${year}-${month}-${day}`;
}

// Wait for document and jQuery to be ready
$(document).ready(function() {
    // Check if we have filter parameters in the URL
    const urlParams = new URLSearchParams(window.location.search);
    const urlStartDate = urlParams.get('startDate');
    const urlEndDate = urlParams.get('endDate');
    
    if (urlStartDate && urlEndDate) {
        // If we have filter dates in URL, use those
        $('#filterStartDate').val(urlStartDate);
        $('#filterEndDate').val(urlEndDate);
    } else {
        // Otherwise set default date range to current month
        const currentDate = new Date();
        const firstDayOfMonth = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
        const lastDayOfMonth = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 0);
        
        // Format dates properly for inputs
        $('#filterStartDate').val(formatDateISO(firstDayOfMonth));
        $('#filterEndDate').val(formatDateISO(lastDayOfMonth));
    }
    
    // If filter is active, show a reset button at the top
    if (urlStartDate && urlEndDate) {
        // Add a visible indicator that a filter is active
        $('.card-header h5').append(' <span class="badge badge-primary">Filtered</span>');
    }
    
    // Expense Filter Section Toggle
    $('#toggleExpenseFilter').on('click', function() {
        $('#expenseFilterBody').slideToggle();
        const icon = $(this).find('i');
        if (icon.hasClass('icon-chevron-down')) {
            icon.removeClass('icon-chevron-down').addClass('icon-chevron-up');
        } else {
            icon.removeClass('icon-chevron-up').addClass('icon-chevron-down');
        }
    });
    
    // Expense Filter Form Submission
    $('#expenseFilterForm').on('submit', function(e) {
        e.preventDefault();
        
        // Get filter dates
        const startDate = $('#filterStartDate').val();
        const endDate = $('#filterEndDate').val();
        
        if (startDate && endDate) {
            // Reload the page with date parameters to fetch filtered data from server
            window.location.href = window.location.pathname + '?startDate=' + startDate + '&endDate=' + endDate;
        } else {
            alert('Please select both start and end dates');
        }
    });
    
    // Quick date range selection
    $('#filterQuickDate').on('change', function() {
        const range = $(this).val();
        const today = new Date();
        let startDate, endDate;

        switch(range) {
            case 'today':
                startDate = new Date(today.getFullYear(), today.getMonth(), today.getDate());
                // Set endDate to tomorrow
                endDate = new Date(today.getFullYear(), today.getMonth(), today.getDate() + 1);
                break;
            case 'yesterday':
                startDate = new Date(today.getFullYear(), today.getMonth(), today.getDate() - 1);
                endDate = new Date(today.getFullYear(), today.getMonth(), today.getDate() - 1);
                break;
            case 'week':
                // Get first day of week (Sunday)
                startDate = new Date(today.getFullYear(), today.getMonth(), today.getDate());
                startDate.setDate(startDate.getDate() - startDate.getDay());
                endDate = new Date(today.getFullYear(), today.getMonth(), today.getDate());
                break;
            case 'month':
                // First day of current month
                startDate = new Date(today.getFullYear(), today.getMonth(), 1);
                // Last day of current month
                endDate = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                break;
            case 'last_month':
                // First day of last month
                startDate = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                // Last day of last month
                endDate = new Date(today.getFullYear(), today.getMonth(), 0);
                break;
            case 'year':
                // First day of current year
                startDate = new Date(today.getFullYear(), 0, 1);
                // Current day
                endDate = new Date(today.getFullYear(), today.getMonth(), today.getDate());
                break;
            default:
                // Keep current values
                return;
        }
        
        // Format dates as YYYY-MM-DD
        $('#filterStartDate').val(formatDateISO(startDate));
        $('#filterEndDate').val(formatDateISO(endDate));
    });
    
    // Reset Date Filter - go back to current month view
    $('#resetExpenseFilter').on('click', function() {
        // If we have URL parameters, reload without them to show default view
        if (window.location.search) {
            window.location.href = window.location.pathname;
        } else {
            // Just reset the form fields
            $('#expenseFilterForm')[0].reset();
            
            // Set default date range to current month
            const currentDate = new Date();
            const firstDayOfMonth = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
            const lastDayOfMonth = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 0);
            
            $('#filterStartDate').val(formatDateISO(firstDayOfMonth));
            $('#filterEndDate').val(formatDateISO(lastDayOfMonth));
        }
    });
});