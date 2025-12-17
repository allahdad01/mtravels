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
    // Ensure familyId is a valid number
    familyId = parseInt(familyId, 10);
    
    if (isNaN(familyId) || familyId <= 0) {
        console.error('Invalid family ID:', familyId);
        showToast('error', 'Please provide a valid family ID.');
        return;
    }
    
    // Show completion details modal
    $('#familyCompletionDetailsModal').modal('show');
    $('#familyCompletionBookingId').val('family_' + familyId);
    
    // Load and display family members
    initializeCompletionMembersForFamily(familyId);
}

function initializeCompletionMembersForFamily(familyId) {
    // Get family members
    $.ajax({
        url: '../api/umrah/get_family_members.php',
        type: 'GET',
        data: { family_id: familyId },
        dataType: 'json',
        success: function(members) {
            let membersHtml = '';
            
            if (members && members.length > 0) {
                members.forEach(function(member, index) {
                    const memberId = member.booking_id;
                    const memberName = member.name || 'Unknown';
                    
                    membersHtml += `
                        <div class="form-check mb-2">
                            <input class="form-check-input completion-member-checkbox" type="checkbox" 
                                   id="completion_member_${memberId}" name="member_ids[]" value="${memberId}">
                            <label class="form-check-label" for="completion_member_${memberId}">
                                ${memberName}
                            </label>
                        </div>
                    `;
                });
            } else {
                membersHtml = '<p class="text-muted">No members found for this family.</p>';
            }
            
            $('#familyCompletionMembersContainer').html(membersHtml);
        },
        error: function(error) {
            console.error('Error loading family members:', error);
            $('#familyCompletionMembersContainer').html('<p class="text-danger">Error loading members</p>');
        }
    });
}

function generateFamilyCancellation(familyId) {
    // Ensure familyId is a valid number
    familyId = parseInt(familyId, 10);
    
    if (isNaN(familyId) || familyId <= 0) {
        console.error('Invalid family ID:', familyId);
        showToast('error', 'Please provide a valid family ID.');
        return;
    }
    
    // Call the modal opening function with the family ID
    openFamilyCancellationModal(familyId);
}

function generateDocumentWithLanguage(language) {
    const context = window.currentDocumentContext;
    if (!context) return;

    const { familyId, type, formData, cancellationReason, returnedItems, bookingId, selectedMembers } = context;
    
    // Close language selection modal
    $('#familyLanguageModal').modal('hide');

    let url = '';
    let documentDetails = {
        status: {}
    };
    
    // Generate the appropriate document based on type
    switch (type) {
        
        case 'agreement':
            url = `../api/umrah/generate_family_agreement.php?family_id=${familyId}&lang=${language}`;
            window.open(url, '_blank');
            break;
        case 'completion':
            // Build URL with member IDs if available
            const params = new URLSearchParams();
            params.append('family_id', familyId);
            params.append('lang', language);
            if (formData && formData.member_ids && Array.isArray(formData.member_ids) && formData.member_ids.length > 0) {
                formData.member_ids.forEach(id => {
                    params.append('member_ids[]', id);
                });
            }
            if (formData && formData.additional_notes) {
                params.append('additional_notes', formData.additional_notes);
            }
            url = `../api/umrah/generate_family_completion.php?${params.toString()}`;
            window.open(url, '_blank');
            break;
        case 'cancellation':
            // Build URL with cancellation details
            const cancelParams = new URLSearchParams();
            cancelParams.append('family_id', familyId);
            cancelParams.append('booking_id', bookingId);
            cancelParams.append('lang', language);
            if (cancellationReason) {
                cancelParams.append('cancellation_reason', cancellationReason);
            }
            if (returnedItems) {
                cancelParams.append('returned_items', JSON.stringify(returnedItems));
            }
            if (selectedMembers && Array.isArray(selectedMembers) && selectedMembers.length > 0) {
                selectedMembers.forEach(id => {
                    cancelParams.append('booking_ids[]', id);
                });
            }
            url = `../api/umrah/generate_family_cancellation.php?${cancelParams.toString()}`;
            window.open(url, '_blank');
            break;
        
    }
}

function initializeDocumentDetailsTableForFamily(familyId) {
    // Get family members first
    $.ajax({
        url: '../api/umrah/get_family_members.php',
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
            
            // Collect selected member IDs
            const selectedMembers = [];
            $('#familyCompletionMembersContainer').find('.completion-member-checkbox:checked').each(function() {
                selectedMembers.push($(this).val());
            });

            // Validate that at least one member is selected
            if (selectedMembers.length === 0) {
                showToast('warning', 'Please select at least one member whose service is complete.');
                return;
            }

            // Collect form data
            const formData = {
                member_ids: selectedMembers,
                additional_notes: $('#familyCompletionAdditionalNotes').val() || ''
            };

            window.currentDocumentContext = {
                familyId: familyId,
                type: 'completion',
                formData: formData,
                memberIds: selectedMembers
            };

            $('#familyCompletionDetailsModal').modal('hide');
            $('#familyLanguageModal').modal('show');
        }
    });

  
}); 

 