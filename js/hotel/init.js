// Hotel Module Initialization
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Select2 dropdowns
    if (typeof $.fn.select2 !== 'undefined') {
        $('#supplier').select2({
            theme: 'bootstrap-5',
            width: '100%',
            dropdownParent: $('#supplier').closest('.modal-body'),
            placeholder: 'Select Supplier',
            allowClear: true
        });
        
        $('#soldTo').select2({
            theme: 'bootstrap-5',
            width: '100%',
            dropdownParent: $('#soldTo').closest('.modal-body'),
            placeholder: 'Select Client',
            allowClear: true
        });

        // Initialize Select2 for client dropdown in invoice modal
        $('#clientForInvoice1').select2({
            dropdownParent: $('#multiTicketInvoiceModal'),
            placeholder: "Search and select client...",
            allowClear: true
        });
    }

    // Initialize tooltips
    if (typeof $.fn.tooltip !== 'undefined') {
        $('[data-toggle="tooltip"]').tooltip();
    }

    // Initialize datepickers
    if (typeof $.fn.datepicker !== 'undefined') {
        $('.datepicker').datepicker({
            format: 'yyyy-mm-dd',
            autoclose: true
        });
    }

    // Set default date for issue_date
    const issueDateInput = document.getElementById('issue_date');
    if (issueDateInput) {
        issueDateInput.value = new Date().toISOString().split('T')[0];
    }

    // Add form submission handler for edit form
    const editBookingForm = document.getElementById('editBookingForm');
    if (editBookingForm) {
        editBookingForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitEditForm();
        });
    }

    // Add event listeners for amount calculations in edit form
    const baseAmountInput = document.getElementById('editBookingForm').querySelector('#base_amount');
    const soldAmountInput = document.getElementById('editBookingForm').querySelector('#sold_amount');
    if (baseAmountInput && soldAmountInput) {
        [baseAmountInput, soldAmountInput].forEach(input => {
            input.addEventListener('input', function() {
                const baseAmount = parseFloat(baseAmountInput.value) || 0;
                const soldAmount = parseFloat(soldAmountInput.value) || 0;
                document.getElementById('editBookingForm').querySelector('#profit').value = 
                    (soldAmount - baseAmount).toFixed(2);
            });
        });
    }

    // Initialize floating action button for multi-ticket invoice
    const launchMultiTicketBtn = document.getElementById('launchMultiTicketInvoice');
    if (launchMultiTicketBtn) {
        launchMultiTicketBtn.addEventListener('click', function() {
            loadTicketsForInvoice();
            $('#multiTicketInvoiceModal').modal('show');
        });
    }

    // Handle "Select All" checkbox in invoice modal
    const selectAllTickets = document.getElementById('selectAllTickets');
    if (selectAllTickets) {
        selectAllTickets.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('#ticketsForInvoiceBody input[type="checkbox"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateInvoiceTotal();
        });
    }
}); 