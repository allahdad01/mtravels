$(document).ready(function() {
    // Set issue date to today by default
    const today = new Date().toISOString().split('T')[0];
    const issueDate = document.getElementById('issueDate');
    if (issueDate && !issueDate.value) {
        issueDate.value = today;
    }
    
    // Function to calculate totals
    function calculateTotals() {
        let totalBase = 0;
        let totalSold = 0;
        let totalDiscount = 0; // Initialize total discount to 0
        let totalProfit = 0;

        // Sum up all passenger amounts
        $('.passenger-info').each(function() {
            const base = parseFloat($(this).find('.base-amount').val()) || 0;
            const sold = parseFloat($(this).find('.sold-amount').val()) || 0;
            const passengerDiscount = parseFloat($(this).find('.discount-amount').val()) || 0;
            const passengerProfit = sold - base - passengerDiscount;

            // Update individual passenger profit
            $(this).find('.profit-amount').val(passengerProfit.toFixed(2));

            // Add to totals
            totalBase += base;
            totalSold += sold;
            totalDiscount += passengerDiscount; // Sum up passenger discounts
        });

        // Calculate total profit
        totalProfit = totalSold - totalBase - totalDiscount;

        // Update total fields
        $('#base').val(totalBase.toFixed(2));
        $('#sold').val(totalSold.toFixed(2));
        $('#discount').val(totalDiscount.toFixed(2)); // Update total discount field
        $('#pro').val(totalProfit.toFixed(2));
    }

    // Function to create passenger form fields
    function createPassengerFields(type, index, count) {
        let titles = type === 'infant' ? ['Infant'] :
                    type === 'child' ? ['Child'] :
                    ['Mr', 'Mrs', 'Ms'];
        
        let html = `
            <div class="passenger-info ${type}-passenger" data-passenger="${index}">
                <h6 class="border-bottom pb-2 mb-3">${type.charAt(0).toUpperCase() + type.slice(1)} Passenger ${count}</h6>
                <div class="form-row mb-3">
                    <div class="form-group col-md-2 mb-0">
                        <label for="title_${index}">Title</label>
                        <select class="form-control" id="title_${index}" name="passengers[${index}][title]" required>
                            ${titles.map(title => `<option value="${title}">${title}</option>`).join('')}
                        </select>
                    </div>
                    <div class="form-group col-md-2 mb-0">
                        <label for="gender_${index}">Gender</label>
                        <select class="form-control" id="gender_${index}" name="passengers[${index}][gender]" required>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                    <div class="form-group col-md-8 mb-0">
                         <label for="passengerName_${index}">Passenger Name</label>
                         <input type="text" class="form-control" id="passengerName_${index}" name="passengers[${index}][name]" required>
                     </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-3 mb-0">
                        <label for="base_${index}">
                            <i class="feather icon-dollar-sign mr-1"></i>Base Amount
                        </label>
                        <input type="number" class="form-control base-amount" id="base_${index}" name="passengers[${index}][base]" step="any" required>
                    </div>
                    <div class="form-group col-md-3 mb-0">
                        <label for="sold_${index}">
                            <i class="feather icon-dollar-sign mr-1"></i>Sold Amount
                        </label>
                        <input type="number" class="form-control sold-amount" id="sold_${index}" name="passengers[${index}][sold]" step="any" required>
                    </div>
                    <div class="form-group col-md-3 mb-0">
                        <label for="discount_${index}">
                            <i class="feather icon-minus-circle mr-1"></i>Discount
                        </label>
                        <input type="number" class="form-control discount-amount" id="discount_${index}" name="passengers[${index}][discount]" value="0" step="any">
                    </div>
                    <div class="form-group col-md-3 mb-0">
                        <label for="profit_${index}">
                            <i class="feather icon-plus-circle mr-1"></i>Profit
                        </label>
                        <input type="number" class="form-control profit-amount" id="profit_${index}" name="passengers[${index}][profit]" step="any" readonly>
                    </div>
                </div>
            </div>
        `;
        return html;
    }

    // Function to update passenger fields
    function updatePassengerFields() {
        let adultCount = parseInt($('#adultCount').val()) || 0;
        let childCount = parseInt($('#childCount').val()) || 0;
        let infantCount = parseInt($('#infantCount').val()) || 0;
        
        let container = $('#passengersContainer');
        container.empty();
        
        let index = 1;
        
        // Add adult passengers
        for(let i = 0; i < adultCount; i++) {
            container.append(createPassengerFields('adult', index, i + 1));
            if (i < adultCount - 1) {
                container.append('<hr>');
            }
            index++;
        }
        
        // Add child passengers
        if (childCount > 0 && adultCount > 0) {
            container.append('<hr>');
        }
        for(let i = 0; i < childCount; i++) {
            container.append(createPassengerFields('child', index, i + 1));
            if (i < childCount - 1) {
                container.append('<hr>');
            }
            index++;
        }
        
        // Add infant passengers
        if (infantCount > 0 && (adultCount > 0 || childCount > 0)) {
            container.append('<hr>');
        }
        for(let i = 0; i < infantCount; i++) {
            container.append(createPassengerFields('infant', index, i + 1));
            if (i < infantCount - 1) {
                container.append('<hr>');
            }
            index++;
        }

        // Add event listeners for calculation
        $('.base-amount, .sold-amount, .discount-amount').on('input', calculateTotals);
    }

    // Event listeners
    $('.passenger-count').change(updatePassengerFields);
    
    // Initial setup
    updatePassengerFields();
});

// Handle book ticket form submission to prevent double-clicking
$('#bookTicketForm').on('submit', function(e) {
    const submitBtn = $(this).find('button[type="submit"]');
    
    // Disable the button immediately to prevent multiple clicks
    submitBtn.prop('disabled', true);
    
    // Change button text to show processing state
    const originalText = submitBtn.html();
    submitBtn.html('<i class="feather icon-refresh-cw mr-2 spinner-border spinner-border-sm" role="status" aria-hidden="true"></i>processing...');
    
    // If there's an error or the form submission fails, re-enable the button
    // This will be handled by the AJAX error callback or form validation
    setTimeout(function() {
        if (submitBtn.prop('disabled')) {
            submitBtn.prop('disabled', false);
            submitBtn.html(originalText);
        }
    }, 5000); // 5 second timeout as safety measure
});

// Re-enable button if there's an error in form submission
$(document).ajaxError(function(event, xhr, settings) {
    if (settings.url && settings.url.includes('../api/ticket/save_ticket.php')) {
        const submitBtn = $('#bookTicketForm button[type="submit"]');
        submitBtn.prop('disabled', false);
        submitBtn.html('<i class="feather icon-check mr-2"></i>Book');
    }
});

// Re-enable button if form validation fails
$('#bookTicketForm').on('invalid', function() {
    const submitBtn = $(this).find('button[type="submit"]');
    submitBtn.prop('disabled', false);
    submitBtn.html('<i class="feather icon-check mr-2"></i>Book');
});