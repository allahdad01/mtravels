// Family Document Generation Functions

function generateFamilyAgreement(familyId) {
    // Show language selection modal
    $('#familyLanguageModal').modal('show');
    
    // Store the family ID and document type
    window.currentDocumentContext = {
        familyId: familyId,
        type: 'agreement'
    };
}

function generateFamilyReceipt(familyId) {
    // Show document receipt details modal
    $('#familyDocumentReceiptDetailsModal').modal('show');
    $('#familyDocumentReceiptBookingId').val('family_' + familyId);
    
    // Initialize document details table for family
    initializeDocumentDetailsTableForFamily(familyId);
}

function generateFamilyCompletion(familyId) {
    // Show completion details modal
    $('#familyCompletionDetailsModal').modal('show');
    $('#familyCompletionBookingId').val('family_' + familyId);
    
    // Initialize completion details table for family
    initializeCompletionDetailsTableForFamily();
}

function generateFamilyCancellation(familyId) {
    // Ensure familyId is a valid number
    familyId = parseInt(familyId, 10);
    
    if (isNaN(familyId) || familyId <= 0) {
        console.error('Invalid family ID:', familyId);
        Swal.fire({
            icon: 'error',
            title: 'Invalid Family ID',
            text: 'Please provide a valid family ID.'
        });
        return;
    }
    
    // Call the modal opening function with the family ID
    openFamilyCancellationModal(familyId);
}

function generateDocumentWithLanguage(language) {
    const context = window.currentDocumentContext;
    if (!context) return;

    const { familyId, type, formData } = context;
    
    // Close language selection modal
    $('#familyLanguageModal').modal('hide');

    let url = '';
    let documentDetails = {
        status: {}
    };
    
    // Generate the appropriate document based on type
    switch (type) {
        
        case 'agreement':
            url = `generate_family_agreement.php?family_id=${familyId}&lang=${language}`;
            window.open(url, '_blank');
            break;
        case 'completion':
            url = `generate_family_completion.php?family_id=${familyId}&lang=${language}`;
            window.open(url, '_blank');
            break;
        
    }
}

function initializeDocumentDetailsTableForFamily(familyId) {
    // Get family members first
    $.ajax({
        url: 'ajax/get_family_members.php',
        type: 'GET',
        data: { family_id: familyId },
        dataType: 'json', // Explicitly set dataType to json
        success: function(members) {
            // Generate table HTML
            let tableHtml = '';

            // Member documents section
            if (members && members.length > 0) {
                members.forEach(member => {
                    tableHtml += `
                        <tr class="table-info">
                            <td colspan="3"><strong>Member: ${member.name || 'Unknown'}</strong></td>
                        </tr>
                        <tr class="member-documents" data-booking-id="${member.booking_id}">
                            <td>Passport (${member.passport_number || 'No Passport'})</td>
                            <td class="text-center">
                                <div class="custom-control custom-radio custom-control-inline">
                                    <input type="radio" id="passport_original_${member.booking_id}" name="passport_status_${member.booking_id}" value="original" class="custom-control-input">
                                    <label class="custom-control-label" for="passport_original_${member.booking_id}">Original</label>
                                </div>
                            </td>
                            <td class="text-center">
                                <div class="custom-control custom-radio custom-control-inline">
                                    <input type="radio" id="passport_copy_${member.booking_id}" name="passport_status_${member.booking_id}" value="copy" class="custom-control-input">
                                    <label class="custom-control-label" for="passport_copy_${member.booking_id}">Copy</label>
                                </div>
                            </td>
                        </tr>
                        <tr class="member-documents" data-booking-id="${member.booking_id}">
                            <td>Tazkira/ID Card</td>
                            <td class="text-center">
                                <div class="custom-control custom-radio custom-control-inline">
                                    <input type="radio" id="id_card_original_${member.booking_id}" name="id_card_status_${member.booking_id}" value="original" class="custom-control-input">
                                    <label class="custom-control-label" for="id_card_original_${member.booking_id}">Original</label>
                                </div>
                            </td>
                            <td class="text-center">
                                <div class="custom-control custom-radio custom-control-inline">
                                    <input type="radio" id="id_card_copy_${member.booking_id}" name="id_card_status_${member.booking_id}" value="copy" class="custom-control-input">
                                    <label class="custom-control-label" for="id_card_copy_${member.booking_id}">Copy</label>
                                </div>
                            </td>
                        </tr>
                        <tr class="member-documents" data-booking-id="${member.booking_id}">
                            <td>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <div class="input-group-text">
                                            <input type="checkbox" id="other_doc_check_${member.booking_id}" name="other_doc_check_${member.booking_id}">
                                        </div>
                                    </div>
                                    <input type="text" class="form-control" id="other_doc_name_${member.booking_id}" name="other_doc_name_${member.booking_id}" placeholder="Other Document Name">
                                </div>
                            </td>
                            <td class="text-center">
                                <div class="custom-control custom-radio custom-control-inline">
                                    <input type="radio" id="other_doc_original_${member.booking_id}" name="other_doc_status_${member.booking_id}" value="original" class="custom-control-input" disabled>
                                    <label class="custom-control-label" for="other_doc_original_${member.booking_id}">Original</label>
                                </div>
                            </td>
                            <td class="text-center">
                                <div class="custom-control custom-radio custom-control-inline">
                                    <input type="radio" id="other_doc_copy_${member.booking_id}" name="other_doc_status_${member.booking_id}" value="copy" class="custom-control-input" disabled>
                                    <label class="custom-control-label" for="other_doc_copy_${member.booking_id}">Copy</label>
                                </div>
                            </td>
                        </tr>
                    `;
                });
            } else {
                tableHtml = `
                    <tr>
                        <td colspan="3" class="text-center text-warning">
                            No family members found
                        </td>
                    </tr>
                `;
            }

            $('#familyDocumentDetailsTableBody').html(tableHtml);

            // Add event listener for other document checkbox
            $('input[id^="other_doc_check_"]').change(function() {
                const bookingId = $(this).closest('tr').data('booking-id');
                const otherDocNameInput = $(`#other_doc_name_${bookingId}`);
                const otherDocOriginalRadio = $(`#other_doc_original_${bookingId}`);
                const otherDocCopyRadio = $(`#other_doc_copy_${bookingId}`);

                if ($(this).is(':checked')) {
                    otherDocNameInput.prop('required', true);
                    otherDocOriginalRadio.prop('disabled', false);
                    otherDocCopyRadio.prop('disabled', false);
                } else {
                    otherDocNameInput.prop('required', false).val('');
                    otherDocOriginalRadio.prop('disabled', true).prop('checked', false);
                    otherDocCopyRadio.prop('disabled', true).prop('checked', false);
                }
            });
        },
        error: function(xhr, status, error) {
            console.error('Error fetching family members:', error);
            
            // Try to parse error response
            let errorMessage = 'Error loading family members';
            try {
                const responseText = xhr.responseText;
                errorMessage += `: ${responseText}`;
            } catch (e) {
                console.error('Could not parse error response', e);
            }

            $('#familyDocumentDetailsTableBody').html(`
                <tr>
                    <td colspan="3" class="text-center text-danger">
                        ${errorMessage}
                    </td>
                </tr>
            `);
        }
    });
}

function initializeCompletionDetailsTableForFamily() {
    const items = [
        { id: 'passports', name: 'Passports' },
        { id: 'photos', name: 'Photos' },
        { id: 'other_items', name: 'Other Items' }
    ];

    let tableHtml = '';
    items.forEach(item => {
        tableHtml += `
            <tr>
                <td>${item.name}</td>
                <td class="text-center">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="returned_${item.id}" name="returned_${item.id}">
                        <label class="custom-control-label" for="returned_${item.id}"></label>
                    </div>
                </td>
            </tr>
        `;
    });

    $('#familyCompletionDetailsTableBody').html(tableHtml);
}

// Event handlers for form submissions
$(document).ready(function() {
    $('#familyGenerateDocumentReceiptBtn').click(function() {
        const bookingId = $('#familyDocumentReceiptBookingId').val();
        if (bookingId.startsWith('family_')) {
            const familyId = bookingId.replace('family_', '');
            
            // Show language selection modal
            window.currentDocumentContext = {
                familyId: familyId,
                type: 'receipt'
            };

            // Close receipt modal and show language modal
            $('#familyDocumentReceiptDetailsModal').modal('hide');
            $('#familyLanguageModal').modal('show');
        }
    });

    $('#familyGenerateCompletionFormBtn').click(function() {
        const bookingId = $('#familyCompletionBookingId').val();
        if (bookingId.startsWith('family_')) {
            const familyId = bookingId.replace('family_', '');
            
            // Collect form data
            const formData = {};
            const form = $('#familyCompletionDetailsForm');

            form.find('input[type="checkbox"]').each(function() {
                const input = $(this);
                const name = input.attr('name');
                if (name) {
                    formData[name] = input.is(':checked') ? '1' : '0';
                }
            });

            window.currentDocumentContext = {
                familyId: familyId,
                type: 'completion',
                formData: formData
            };

            $('#familyCompletionDetailsModal').modal('hide');
            $('#familyLanguageModal').modal('show');
        }
    });

  
}); 

 