// Function to format date as YYYY-MM-DD
    function formatDateISO(date) {
        // Check if date is undefined or null
        if (!date) {

            return '';
        }
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0'); // Add leading zero if needed
        const day = String(date.getDate()).padStart(2, '0'); // Add leading zero if needed
        return `${year}-${month}-${day}`;
    }
    
    // Set default date range (current month)
    const today = new Date();
    // First day of current month (always the 1st)
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    // Last day of current month
    const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
    
    // Format dates properly
    $('#startDate').val(formatDateISO(firstDay));
    $('#endDate').val(formatDateISO(lastDay));
    
    // Debug function to validate date format
    function validateDateRange() {
        const startDate = $('#startDate').val();
        const endDate = $('#endDate').val();
        
        // Check if the start date is the first day of the month
        const startDateObj = new Date(startDate);
        const isFirstDay = startDateObj.getDate() === 1;
        
        return isFirstDay;
    }
    
    // Validate initial date range
    validateDateRange();

    // Date range form submission
    $('#dateRangeForm').on('submit', function(e) {
        e.preventDefault();
        // Validate date range before loading data
        validateDateRange();
        loadFinancialData();
    });

    // Quick date range buttons
    $('.btn-group .btn').click(function() {
        const range = $(this).data('range');
        const today = new Date();
        let startDate, endDate;

        switch(range) {
            case 'today':
                startDate = new Date(today.getFullYear(), today.getMonth(), today.getDate());
                // Set endDate to tomorrow
                endDate = new Date(today.getFullYear(), today.getMonth(), today.getDate() + 1);
                break;
            case 'week':
                // Get first day of week (Sunday)
                startDate = new Date(today.getFullYear(), today.getMonth(), today.getDate());
                startDate.setDate(startDate.getDate() - startDate.getDay());
                endDate = new Date(startDate.getFullYear(), startDate.getMonth(), startDate.getDate());
                endDate.setDate(endDate.getDate() + 6);
                break;
            case 'month':
                // First day of current month - always the 1st
                startDate = new Date(today.getFullYear(), today.getMonth(), 1);
                // Last day of current month
                endDate = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                break;
            case 'quarter':
                const quarter = Math.floor(today.getMonth() / 3);
                // First day of current quarter
                startDate = new Date(today.getFullYear(), quarter * 3, 1);
                // Last day of current quarter
                endDate = new Date(today.getFullYear(), (quarter + 1) * 3, 0);
                break;
            case 'year':
                // First day of current year
                startDate = new Date(today.getFullYear(), 0, 1);
                // Last day of current year
                endDate = new Date(today.getFullYear(), 11, 31);
                break;
        }

        // Use our custom formatting function
        $('#startDate').val(formatDateISO(startDate));
        $('#endDate').val(formatDateISO(endDate));
        
        // Make the current selection button active
        $('.btn-group .btn').removeClass('active');
        $(this).addClass('active');
        
        // Submit the form to update data
        $('#dateRangeForm').submit();
    });

    // Highlight active range button
    function updateActiveButton() {
        $('.btn-group .btn').removeClass('active');
        // Add logic to determine which button should be active based on current date range
    }

    // Update active button when date inputs change
    $('#startDate, #endDate').change(updateActiveButton);
    
   

    // Reset filter
    $('#resetFilter').click(function() {
        // Reset to current month from 1st day to last day
        const today = new Date();
        // First day of current month (always the 1st)
        const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
        // Last day of current month
        const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
        
        // Use our custom formatting function
        $('#startDate').val(formatDateISO(firstDay));
        $('#endDate').val(formatDateISO(lastDay));
        
        // Clear any active button state
        $('.btn-group .btn').removeClass('active');
        
        // Load data with these date settings
        loadFinancialData();
    });

    // Initial load
    loadFinancialData();

    // Make sure category headers expand when clicked
    $(document).on('click', '.category-card-header', function() {
        const $card = $(this).closest('.category-card');
        const $list = $card.find('.expense-list');
        const $icon = $card.find('.expand-icon');
        $list.slideToggle();
        $icon.toggleClass('icon-chevron-down icon-chevron-up');
    });
    
    // Attach click handler to the comprehensive export button
    $('#exportComprehensiveReport').click(function() {
        exportComprehensiveReport();
    });
