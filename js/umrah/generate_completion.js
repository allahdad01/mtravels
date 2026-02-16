(function() {
    // Simple sprintf implementation
    function sprintf(format, ...args) {
        return format.replace(/%s/g, () => {
            return args.shift() || '';
        });
    }

    // Simple date formatting function to mimic PHP's date('d/m/Y')
    function formatDate(date = new Date()) {
        return date.toLocaleDateString('en-GB', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        }).replace(/\//g, '/');
    }

    // Modify the generateCompletionForm function to show the details modal first
    function generateCompletionForm(bookingId) {
        // Set the booking ID in the hidden input
        $('#completionBookingId').val(bookingId);
        
        // Show the completion details modal
        $('#completionDetailsModal').modal('show');
    }

    // Expose the function globally so it can be called from onclick events
    window.generateCompletionForm = generateCompletionForm;

    // Handle completion form generation
    $(document).ready(function() {
        $('#generateCompletionFormBtn').off('click').on('click', function() {
            const form = $('#completionDetailsForm');
            
            // Validate the form
            if (!form[0].checkValidity()) {
                form[0].reportValidity();
                return;
            }
            
            // Close the modal
            $('#completionDetailsModal').modal('hide');
            
            // Collect form data
            const formData = new FormData(form[0]);
            const bookingId = $('#completionBookingId').val();
            
            // Collect form data
            const params = new URLSearchParams();

            // Add additional notes
            const additionalNotes = $('#completionAdditionalNotes').val();
            if (additionalNotes) {
                params.append('additional_notes', additionalNotes);
            }
            
            // Open language selection dialog
            Swal.fire({
                title: 'Select Language',
                text: 'Please select the language for the document',
                showDenyButton: true,
                showCancelButton: true,
                confirmButtonText: 'English',
                denyButtonText: 'Dari (دری)',
                cancelButtonText: 'Pashto (پښتو)'
            }).then((result) => {
                let lang = 'en';
                if (result.isDenied) {
                    lang = 'fa';
                } else if (result.dismiss === Swal.DismissReason.cancel) {
                    lang = 'ps';
                }
                
                // Build URL with parameters
                const url = `../api/umrah/generate_umrah_completion.php?booking_id=${bookingId}&lang=${lang}&${params.toString()}`;

                // Directly open in new window, similar to family agreements
                window.open(url, '_blank');
            });
        });
    });
})();
