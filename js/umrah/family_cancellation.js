// Family Cancellation Modal Functions
var familyMembersData = [];

// Function to open family cancellation modal
function openFamilyCancellationModal(familyId, bookingId = null) {
     // Validate familyId
     if (!familyId || familyId === '' || familyId === 'undefined') {
         showToast('error', 'Please provide a valid family ID.');
         return;
     }
    
    // Set the family ID and booking ID
    $('#familyCancellationFamilyId').val(familyId);
    $('#familyCancellationBookingId').val(bookingId || '');
    
    // Clear previous data
    $('#familyMembersDocuments').html('');
    $('#familyNameDisplay').text('');
    $('#totalMembersDisplay').text('');
    $('#packageTypeDisplay').text('');
    
    // Show the modal first
    $('#familyCancellationDetailsModal').modal('show');
    
    // Load family members data after modal is shown
    setTimeout(function() {
        loadFamilyMembersForCancellation(familyId);
    }, 300);
}

// Function to load family members data
function loadFamilyMembersForCancellation(familyId) {
    // Validate inputs
    if (!familyId) {
        $('#familyMembersDocuments').html('<div class="alert alert-danger">Invalid Family ID</div>');
        return;
    }
    
    // Show loading state
    $('#familyMembersDocuments').html('<div class="text-center p-4"><i class="feather icon-loader spinning"></i> Loading family members...</div>');
    
    // AJAX URL
    var ajaxUrl = '../api/umrah/get_family_members1.php';
    
    // Perform AJAX request
    $.ajax({
        url: ajaxUrl,
        type: 'GET',
        data: { 
            family_id: familyId,
            action: 'get_family_members' 
        },
        dataType: 'json',
        timeout: 30000,
        success: function(response) {
            if (response && response.success && response.data) {
                // Store family members data globally
                window.familyMembersData = response.data.members || [];
                
                // Update family information display
                $('#familyNameDisplay').text(response.data.family_name || 'N/A');
                $('#totalMembersDisplay').text(window.familyMembersData.length);
                $('#packageTypeDisplay').text(response.data.package_type || 'N/A');
                
                // Set the booking ID to the first member's booking ID
                if (window.familyMembersData.length > 0) {
                    var firstMemberBookingId = window.familyMembersData[0].booking_id;
                    $('#familyCancellationBookingId').val(firstMemberBookingId);
                }
                
                // Generate member document sections
                generateFamilyMemberDocumentSections();
            } else {
                var errorMsg = 'Error loading family members: ' + (response.message || 'Invalid response structure');
                $('#familyMembersDocuments').html('<div class="alert alert-danger">' + errorMsg + '</div>');
            }
        },
        error: function(xhr, status, error) {
            var errorMessage = 'Failed to load family members.';
            
            if (xhr.status === 404) {
                errorMessage = 'AJAX endpoint not found. Please check the file path: ' + ajaxUrl;
            } else if (xhr.status === 500) {
                errorMessage = 'Server error occurred. Check server logs.';
            } else if (xhr.status === 403) {
                errorMessage = 'Access denied. Please check your permissions.';
            } else if (status === 'timeout') {
                errorMessage = 'Request timed out. Please try again.';
            } else if (xhr.responseText) {
                try {
                    var errorResponse = JSON.parse(xhr.responseText);
                    errorMessage = errorResponse.message || errorMessage;
                } catch (e) {
                    errorMessage = 'Server returned: ' + xhr.responseText.substring(0, 100) + '...';
                }
            }
            
            $('#familyMembersDocuments').html(
                '<div class="alert alert-danger">' + 
                '<strong>Error:</strong> ' + errorMessage + 
                '<br><small>Status Code: ' + xhr.status + ' | Status: ' + status + 
                '<br>URL: ' + ajaxUrl + '</small>' +
                '</div>'
            );
            
            if (typeof showToast !== 'undefined') {
                showToast('error', errorMessage);
            } else {
                alert(errorMessage);
            }
        }
    });
}

// Function to generate document sections for each family member
function generateFamilyMemberDocumentSections() {
    var sectionsHtml = '';
    
    if (familyMembersData.length === 0) {
        $('#familyMembersDocuments').html('<div class="alert alert-warning">No family members found.</div>');
        return;
    }
    
    familyMembersData.forEach(function(member, index) {
        var template = $('#memberDocumentTemplate').html();
        if (!template) {
            $('#familyMembersDocuments').html('<div class="alert alert-danger">Template not found. Please refresh the page.</div>');
            return;
        }
        
        var memberSection = $(template);
        
        // Update member information
        memberSection.find('.member-name').text(member.name || 'Unknown');
        memberSection.find('.member-passport').text(member.passport_number || 'N/A');
        memberSection.find('.member-booking-id').text(member.booking_id || 'N/A');
        
        // Update input IDs and names for this member
        var memberId = member.booking_id || index;
        
        // Set unique ID for member select checkbox
        memberSection.find('.member-select-checkbox').each(function() {
            var uniqueId = 'member_select_' + memberId;
            
            $(this)
                .attr('id', uniqueId)
                .attr('data-member-id', memberId);
            
            memberSection.find('label').attr('for', uniqueId);
        });
        
        sectionsHtml += memberSection.prop('outerHTML');
    });
    
    $('#familyMembersDocuments').html(sectionsHtml);
}

// Main cancellation form generation handler
$(document).on('click', '#familyGenerateCancellationFormBtn', function() {
    // Validate form
    var form = $('#familyCancellationDetailsForm');
    if (!form[0].checkValidity()) {
        form[0].reportValidity();
        return;
    }
    
    // Check if at least one member is selected
    var hasSelectedMembers = false;
    $('.member-select-checkbox:checked').each(function() {
        hasSelectedMembers = true;
        return false;
    });
    
    if (!hasSelectedMembers) {
        showToast('warning', 'Please select at least one family member with returned documents.');
        return;
        }
        
        // Validate cancellation reason
        var cancellationReason = $('#familyCancellationReason').val().trim();
        if (!cancellationReason) {
        showToast('warning', 'Please provide a detailed reason for the family cancellation.');
        $('#familyCancellationReason').focus();
        return;
        }
    
    // Generate cancellation form directly
    generateFamilyCancellationForm();
});

// Function to generate the actual cancellation form
function generateFamilyCancellationForm() {
    try {
        // Collect all form data
        var familyId = $('#familyCancellationFamilyId').val();
        var bookingId = $('#familyCancellationBookingId').val();
        var cancellationReason = $('#familyCancellationReason').val().trim();
        
        // Validate inputs
        if (!familyId) {
            throw new Error('Family ID is required');
        }
        
        if (!bookingId) {
            throw new Error('Booking ID is required');
        }
        
        if (!cancellationReason) {
            throw new Error('Cancellation reason is required');
        }
        
        // Use window.familyMembersData instead of local variable
        var familyMembersData = window.familyMembersData || [];
        
        // Collect returned items for selected members
        var returnedItems = {};
        
        // Get selected members
        var selectedMembers = [];
        $('.member-select-checkbox:checked').each(function() {
            selectedMembers.push($(this).data('member-id'));
        });
        
        familyMembersData.forEach(function(member) {
            var memberId = member.booking_id;
            var memberPrefix = 'member_' + memberId + '_';
            
            // Document types
            var docTypes = ['passport', 'id_card', 'photos', 'other_docs'];
            
            // Check if this member is selected
            var isSelected = selectedMembers.includes(memberId);
            
            docTypes.forEach(function(docType) {
                // Mark as returned if member is selected, otherwise not returned
                returnedItems[memberPrefix + docType] = isSelected ? '1' : '0';
            });
        });
        
        // Store context for language selection
        window.currentDocumentContext = {
            familyId: familyId,
            bookingId: bookingId,
            type: 'cancellation',
            cancellationReason: cancellationReason,
            returnedItems: returnedItems,
            selectedMembers: selectedMembers
        };
        
        // Close the cancellation modal
        $('#familyCancellationDetailsModal').modal('hide');
        
        // Show language selection modal
        $('#familyLanguageModal').modal('show');
        
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Generation Error',
            text: error.message,
            confirmButtonColor: '#dc3545'
        });
    }
}
