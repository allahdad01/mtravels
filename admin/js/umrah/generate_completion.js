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

    // Define the document types and items to be returned
    const completionItems = [
        { key: 'passport', label: 'Passport', defaultNote: '' },
        { key: 'id_card', label: 'ID Card', defaultNote: '' },
        { key: 'other_docs', label: 'Other Documents', defaultNote: '' }
    ];

    // Modify the generateCompletionForm function to show the details modal first
    function generateCompletionForm(bookingId) {
        // Set the booking ID in the hidden input
        $('#completionBookingId').val(bookingId);
        
        // Clear and populate the completion details table
        const tableBody = $('#completionDetailsTableBody');
        tableBody.empty();

        completionItems.forEach(item => {
            const row = `
                <tr>
                    <td>${item.label}</td>
                    <td class="text-center">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input"
                                   id="completion_${item.key}" name="returned_items[${item.key}]" value="1">
                            <label class="custom-control-label" for="completion_${item.key}"></label>
                        </div>
                    </td>
                </tr>
            `;
            tableBody.append(row);
        });

        // Fetch existing booking data to pre-fill some fields
        fetch(`get_umrah_member.php?booking_id=${bookingId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.member) {
                    const member = data.member;
                    // Pre-fill some fields based on member data
                    if (member.id_type === 'Original') {
                        $('#completion_passport').prop('checked', true).trigger('change');
                        $('#condition_passport').val('good');
                    }
                    // Add any other pre-fill logic here
                }
            })
            .catch(error => {
                console.error('Error fetching member data:', error);
            });

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
            
            // Collect form data for each item
            const params = new URLSearchParams();
            
            completionItems.forEach(item => {
                const isReturned = $(`#completion_${item.key}`).is(':checked');
                // Always include the item, with 1 for checked and 0 for unchecked
                params.append(`returned_items[${item.key}]`, isReturned ? '1' : '0');
            });

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
                const url = `generate_umrah_completion.php?booking_id=${bookingId}&lang=${lang}&${params.toString()}`;
                
                Swal.fire({
                    title: 'Generating Completion Form',
                    text: 'Please wait...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                fetch(url, {
                    method: 'GET',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success',
                            text: data.message,
                            confirmButtonText: 'View Document',
                            showCancelButton: true,
                            cancelButtonText: 'Close'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                window.open('../' + data.file_url, '_blank');
                            }
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message || 'Failed to generate document'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'An error occurred'
                    });
                });
            });
        });
    });
})();