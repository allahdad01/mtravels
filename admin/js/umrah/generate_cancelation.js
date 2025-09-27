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

    // Function to generate cancellation form
    function generateCancellationForm(bookingId) {
        // Set the booking ID in the hidden input
        $('#cancellationBookingId').val(bookingId);
        
        // Reset form
        $('#cancellationDetailsForm')[0].reset();
        
        // Fetch booking data to pre-fill document return section
        fetch(`get_umrah_member.php?booking_id=${bookingId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.member) {
                    const member = data.member;
                    
                    // Pre-check passport return and set condition based on ID type
                    $('#return_passport').prop('checked', true);
                    $('#condition_passport').val(member.id_type === 'Original' ? 'good' : 'fair');
                    
                    // If photos were provided, pre-check photos return
                    if (member.photos_provided) {
                        $('#return_photos').prop('checked', true);
                        $('#condition_photos').val('good');
                    }
                    
                    // Enable/disable condition selects based on checkboxes
                    updateDocumentReturnFields();
                }
            })
            .catch(error => {
                console.error('Error fetching member data:', error);
            });
        
        // Show the modal
        $('#cancellationDetailsModal').modal('show');
    }

    // Expose the function globally so it can be called from onclick events
    window.generateCancellationForm = generateCancellationForm;

    // Function to update document return fields based on checkbox status
    function updateDocumentReturnFields() {
        // For each document type
        ['passport', 'id_card', 'photos', 'other_docs'].forEach(docType => {
            const isChecked = $(`#return_${docType}`).is(':checked');
            $(`#condition_${docType}`).prop('disabled', !isChecked);
            $(`input[name="item_notes[${docType}]"]`).prop('disabled', !isChecked);
            
            if (!isChecked) {
                $(`#condition_${docType}`).val('');
                $(`input[name="item_notes[${docType}]"]`).val('');
            }
        });
    }

    // Add event listeners for document return checkboxes
    $(document).on('change', '[id^="return_"]', function() {
        updateDocumentReturnFields();
    });

    // Handle cancellation form generation
    $(document).ready(function() {
        $('#generateCancellationFormBtn').off('click').on('click', function() {
            const form = $('#cancellationDetailsForm');
            
            // Validate the form
            if (!form[0].checkValidity()) {
                form[0].reportValidity();
                return;
            }
            
            // Close the modal
            $('#cancellationDetailsModal').modal('hide');
            
            // Collect form data
            const formData = new FormData(form[0]);
            const bookingId = $('#cancellationBookingId').val();
            
            // Convert form data to URL parameters
            const params = new URLSearchParams();
            for (let pair of formData.entries()) {
                if (typeof pair[1] === 'string') {
                    params.append(pair[0], pair[1]);
                } else {
                    params.append(pair[0], JSON.stringify(pair[1]));
                }
            }
            
            // Open language selection dialog
            Swal.fire({
                title: 'Select Language',
                text: 'Please select the language for the document',
                showDenyButton: true,
                showCancelButton: true,
                confirmButtonText: 'English',
                denyButtonText: 'Dari (دری)',
                cancelButtonText: 'Pashto (پښتو)',
                customClass: {
                    confirmButton: 'btn btn-primary mx-1',
                    denyButton: 'btn btn-info mx-1',
                    cancelButton: 'btn btn-success mx-1',
                },
                buttonsStyling: false,
                reverseButtons: true
            }).then((result) => {
                let lang = 'en';
                if (result.isDenied) {
                    lang = 'fa';
                } else if (result.dismiss === Swal.DismissReason.cancel) {
                    lang = 'ps';
                }
                
                // Build URL with parameters
                const url = `generate_umrah_cancellation.php?booking_id=${bookingId}&lang=${lang}&${params.toString()}`;
                
                Swal.fire({
                    title: 'Generating Cancellation Form',
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