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

    // Define the document types and their default notes
    const documentTypes = [
        { key: 'passport', label: 'passport', defaultNote: '' },
        { key: 'id_card', label: 'id_card', defaultNote: '' },
        { key: 'photos', label: 'photos', defaultNote: 'photo_notes' },
        { key: 'vaccination_cert', label: 'vaccination_cert', defaultNote: '' },
        { key: 'marriage_cert', label: 'marriage_cert', defaultNote: 'not_applicable' },
        { key: 'birth_cert', label: 'birth_cert', defaultNote: 'not_applicable' },
        { key: 'visa_form', label: 'visa_form', defaultNote: sprintf('signed_on %s', formatDate()) },
        { key: 'mahram_declaration', label: 'mahram_declaration', defaultNote: 'required_if_female' } // This note should be conditional later
    ];

    // Modify the generateDocumentReceipt function to show the details modal first
    function generateDocumentReceipt(bookingId) {
        // Set the booking ID in the hidden input
        $('#documentReceiptBookingId').val(bookingId);
        
        const tableBody = $('#documentDetailsTableBody');
        tableBody.empty(); // Clear previous rows

        documentTypes.forEach(doc => {
            const row = `
                <tr>
                    <td>${doc.label}</td>
                    <td class="text-center">
                        <div class="custom-control custom-radio custom-control-inline">
                            <input type="radio" id="doc_${doc.key}_original" name="document_status[${doc.key}]" value="original" class="custom-control-input">
                            <label class="custom-control-label" for="doc_${doc.key}_original"></label>
                        </div>
                    </td>
                    <td class="text-center">
                        <div class="custom-control custom-radio custom-control-inline">
                            <input type="radio" id="doc_${doc.key}_copy" name="document_status[${doc.key}]" value="copy" class="custom-control-input">
                            <label class="custom-control-label" for="doc_${doc.key}_copy"></label>
                        </div>
                    </td>
                    <td>
                        <input type="text" class="form-control form-control-sm" name="document_notes[${doc.key}]" value="${doc.defaultNote}">
                    </td>
                </tr>
            `;
            tableBody.append(row);
        });

        // Fetch existing booking data to pre-fill passport status and mahram note
        fetch(`get_umrah_member.php?booking_id=${bookingId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.member) {
                    const member = data.member;
                    // Pre-fill passport original/copy
                    if (member.id_type === 'Original') {
                        $(`#doc_passport_original`).prop('checked', true);
                    } else if (member.id_type === 'Copy') {
                        $(`#doc_passport_copy`).prop('checked', true);
                    }
                    // Update passport notes with expiry date
                    if (member.passport_expiry) {
                        $(`input[name='document_notes[passport]']`).val(`valid_until ${new Date(member.passport_expiry).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}`);
                    }

                    // Update Mahram Declaration note if female
                    if (member.gender === 'Female') {
                        $(`input[name='document_notes[mahram_declaration]']`).val('required');
                    } else {
                        $(`input[name='document_notes[mahram_declaration]']`).val('not_applicable');
                    }
                } else {
                    console.warn('Could not fetch member data for pre-filling document receipt modal.');
                }
            })
            .catch(error => {
                console.error('Error fetching member data for document receipt modal:', error);
            });

        // Show the details modal
        $('#documentReceiptDetailsModal').modal('show');
    }

    // Bind the generate button event
    $(document).ready(function() {
        $('#generateDocumentReceiptBtn').on('click', function() {
            const form = $('#documentReceiptDetailsForm');
            
            // Validate the form
            if (form[0].checkValidity() === false) {
                form[0].reportValidity();
                return;
            }
            
            // Close the modal
            $('#documentReceiptDetailsModal').modal('hide');
            
            // Collect form data
            const bookingId = $('#documentReceiptBookingId').val();
            const additionalNotes = $('#receiptAdditionalNotes').val();
            
            const documentStatus = {};
            $('input[name^="document_status["]:checked').each(function() {
                const key = $(this).attr('name').match(/\[(.*?)\]/)[1];
                documentStatus[key] = $(this).val();
            });

            const documentNotes = {};
            $('input[name^="document_notes["]').each(function() {
                const key = $(this).attr('name').match(/\[(.*?)\]/)[1];
                documentNotes[key] = $(this).val();
            });

            // Open language modal to select language, then pass all details
            Swal.fire({
                title: 'select_language',
                text: 'please_select_the_language_for_the_document',
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
                reverseButtons: true // To make order English, Dari, Pashto
            }).then((result) => {
                let lang = 'en'; // Default language
                if (result.isConfirmed) {
                    lang = 'en';
                } else if (result.isDenied) {
                    lang = 'fa';
                } else if (result.dismiss === Swal.DismissReason.cancel) {
                    lang = 'ps';
                } else {
                    return; // User closed modal without selection
                }

                const url = `generate_umrah_document_receipt.php?booking_id=${bookingId}&lang=${lang}&additional_notes=${encodeURIComponent(additionalNotes)}`;
                const formTitle = 'generating_document_receipt';

                // Add document details to the URL parameters
                // For simplicity, serialize them as JSON and encode
                const documentDetailsParam = encodeURIComponent(JSON.stringify({
                    status: documentStatus,
                    notes: documentNotes
                }));

                const finalUrl = `${url}&document_details=${documentDetailsParam}`;

                Swal.fire({
                    title: formTitle,
                    text: 'please_wait...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                fetch(finalUrl, {
                    method: 'GET',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'success',
                            text: data.message,
                            confirmButtonText: 'view_document',
                            showCancelButton: true,
                            cancelButtonText: 'close'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                window.open('../' + data.file_url, '_blank');
                            }
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'error',
                            text: data.message || 'failed_to_generate_document'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'error',
                        text: 'an_error_occurred'
                    });
                });
            });
        });
    });

    // Expose the function globally so it can be called from HTML onclick events
    window.generateDocumentReceipt = generateDocumentReceipt;
})();