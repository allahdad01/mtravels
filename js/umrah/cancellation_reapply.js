// Cancellation/Re-Apply Management Functions

// Function to open cancellation/re-apply modal
function openCancellationReapplyModal(bookingId, basePrice, soldPrice, currentProfit, currency, status) {
    // Default to 'active' status if not provided
    status = status || 'active';
    
    // Set hidden fields
    jQuery('#cr_booking_id').val(bookingId);
    jQuery('#cr_base_price').val(basePrice);
    jQuery('#cr_sold_price').val(soldPrice);
    jQuery('#cr_current_profit').val(currentProfit);

    // Display values in the modal
    jQuery('#displayBasePrice').text(currency + ' ' + parseFloat(basePrice).toFixed(2));
    jQuery('#displaySoldPrice').text(currency + ' ' + parseFloat(soldPrice).toFixed(2));
    jQuery('#displayCurrentProfit').text(currency + ' ' + parseFloat(currentProfit).toFixed(2));
    jQuery('#displayNewProfit').text(currency + ' ' + parseFloat(0).toFixed(2));

    // Reset form
    jQuery('#cancellationReapplyForm')[0].reset();
    jQuery('#selectedActionDisplay').hide();
    jQuery('#cr_reason').val('');

    // Re-set the hidden fields after form reset
    jQuery('#cr_booking_id').val(bookingId);
    jQuery('#cr_base_price').val(basePrice);
    jQuery('#cr_sold_price').val(soldPrice);
    jQuery('#cr_current_profit').val(currentProfit);

    // Dynamic UI based on status
    console.log('Booking status:', status); // Debug log
    
    if (status === 'cancelled') {
        // Show re-apply option
        jQuery('#modalTitle').text('Re-apply Booking');
        jQuery('#cancelActionCard').hide();
        jQuery('#reapplyActionCard').show();
        // Auto-select reapply action
        jQuery('#cr_action').val('reapply');
    } else {
        // Show cancel option
        jQuery('#modalTitle').text('Cancel Booking');
        jQuery('#cancelActionCard').show();
        jQuery('#reapplyActionCard').hide();
        // Auto-select cancel action
        jQuery('#cr_action').val('cancel');
    }

    // Calculate and display new profit (will be updated when action is selected)
    jQuery('#displayNewProfit').text(currency + ' 0.00');

    // Show modal
    jQuery('#cancellationReapplyModal').modal('show');
}

// Function to select action - must be global
function selectAction(action) {
    // Set the action
    jQuery('#cr_action').val(action);

    // Calculate new profit
    const basePrice = parseFloat(jQuery('#cr_base_price').val());
    const soldPrice = parseFloat(jQuery('#cr_sold_price').val());
    const newProfit = action === 'cancel' ? 0 : (soldPrice - basePrice);
    const currency = 'USD'; // You can make this dynamic based on the booking

    // Update display
    const actionText = action === 'cancel' ? 'Cancel Booking (Set Profit to 0)' : 'Re-apply Booking (Recalculate Profit)';
    jQuery('#actionText').text(actionText);
    jQuery('#selectedActionDisplay').show();
    jQuery('#displayNewProfit').text(currency + ' ' + newProfit.toFixed(2));

    // Add visual feedback
    const feedbackDiv = jQuery('<div class="alert alert-success mt-2" id="actionFeedback">Action selected: ' + action + '<br>New profit: ' + currency + ' ' + newProfit.toFixed(2) + '</div>');
    jQuery('#selectedActionDisplay').after(feedbackDiv);

    // Remove feedback after 3 seconds
    setTimeout(function() {
        feedbackDiv.fadeOut(function() { jQuery(this).remove(); });
    }, 3000);
}

// Debug function to test AJAX directly
function testAjax() {
    console.log('Testing AJAX...');
    jQuery.ajax({
        url: 'test_ajax.php',
        type: 'POST',
        data: {
            test: 'hello world',
            timestamp: new Date().toISOString()
        },
        success: function(response) {
            console.log('Test AJAX success:', response);
            alert('Test AJAX worked! Check console for response.');
        },
        error: function(xhr, status, error) {
            console.error('Test AJAX error:', error, status, xhr.responseText);
            alert('Test AJAX failed! Check console for details.');
        }
    });
}

// Handle cancellation/re-apply processing
jQuery(document).ready(function($) {
    
    // Process button click
    $(document).on('click', '#processCancellationReapplyBtn', function(e) {
        e.preventDefault();

        // Get form data
        const bookingId = $('#cr_booking_id').val();
        const action = $('#cr_action').val();
        const basePrice = parseFloat($('#cr_base_price').val());
        const soldPrice = parseFloat($('#cr_sold_price').val());
        const currentProfit = parseFloat($('#cr_current_profit').val());
        const reason = $('#cr_reason').val();

        // Validate required fields
        if (!bookingId || !action || !reason) {
            Swal.fire({
                icon: 'error',
                title: 'Missing Information',
                text: 'Please select an action and provide a reason'
            });
            return;
        }
        
        // Show loading state
        const btn = $(this);
        const originalText = btn.html();
        btn.prop('disabled', true).html('<i class="feather icon-refresh-cw spinner"></i> Processing...');
        
        console.log('Sending AJAX request to process_cancellation_reapply.php');
        
        // Send AJAX request
        $.ajax({
            url: 'process_cancellation_reapply.php',
            type: 'POST',
            data: {
                booking_id: bookingId,
                action: action,
                base_price: basePrice,
                sold_price: soldPrice,
                current_profit: currentProfit,
                reason: reason
            },
            success: function(response) {
                try {
                    // Try to parse the response if it's a string
                    const result = typeof response === 'string' ? JSON.parse(response) : response;

                    // Check for success
                    if (result && (result.status === 'success' || result.success === true || result.success === 'true')) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success',
                            text: result.message || 'Action processed successfully',
                            confirmButtonText: 'OK'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                $('#cancellationReapplyModal').modal('hide');
                                location.reload();
                            }
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: result.message || 'Failed to process action'
                        });
                    }
                } catch (e) {
                    console.error('Error parsing response:', e);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        html: 'Error processing the request: ' + e.message
                    });
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                console.error('Status:', status);
                console.error('Response:', xhr.responseText);
                
                alert('AJAX Error: ' + error + '\nStatus: ' + status + '\nResponse: ' + xhr.responseText);
            },
            complete: function() {
                // Reset button state
                btn.prop('disabled', false).html(originalText);
                console.log('AJAX request completed');
            }
        });
    });
    
    // Test button for AJAX (if needed for debugging)
    $(document).on('click', '#testAjaxBtn', function() {
        testAjax();
    });
});

// Make functions available globally
if (typeof window !== 'undefined') {
    window.openCancellationReapplyModal = openCancellationReapplyModal;
    window.selectAction = selectAction;
    window.testAjax = testAjax;
    window.toggleAllMembers = toggleAllMembers;
    window.bulkCancelSelected = bulkCancelSelected;
    window.bulkReapplySelected = bulkReapplySelected;
}

// Bulk Operations Functions

// Toggle all member checkboxes
function toggleAllMembers() {
    const selectAllCheckbox = document.getElementById('selectAllMembers');
    const memberCheckboxes = document.querySelectorAll('.member-checkbox');
    
    memberCheckboxes.forEach(checkbox => {
        checkbox.checked = selectAllCheckbox.checked;
    });
    
    updateBulkButtonStates();
}

// Get selected member checkboxes
function getSelectedMembers() {
    return Array.from(document.querySelectorAll('.member-checkbox:checked'));
}

// Update bulk button states
function updateBulkButtonStates() {
    const selectedMembers = getSelectedMembers();
    const cancelBtn = document.querySelector('[onclick="bulkCancelSelected()"]');
    const reapplyBtn = document.querySelector('[onclick="bulkReapplySelected()"]');
    
    if (cancelBtn && reapplyBtn) {
        if (selectedMembers.length > 0) {
            cancelBtn.disabled = false;
            reapplyBtn.disabled = false;
        } else {
            cancelBtn.disabled = true;
            reapplyBtn.disabled = true;
        }
    }
}

// Get member data from checkbox attributes
function getMemberData(checkbox) {
    return {
        booking_id: checkbox.dataset.bookingId,
        base_price: parseFloat(checkbox.dataset.basePrice),
        sold_price: parseFloat(checkbox.dataset.soldPrice),
        current_profit: parseFloat(checkbox.dataset.currentProfit),
        status: checkbox.dataset.status,
        currency: checkbox.dataset.currency
    };
}

// Bulk cancel selected members
function bulkCancelSelected() {
    const selectedMembers = getSelectedMembers();
    
    if (selectedMembers.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'No Selection',
            text: 'Please select at least one member to cancel.'
        });
        return;
    }
    
    // Check if any selected members are already cancelled
    const alreadyCancelled = selectedMembers.filter(cb =>
        getMemberData(cb).status === 'cancelled'
    );
    
    if (alreadyCancelled.length > 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Invalid Selection',
            text: 'Some selected members are already cancelled. Please select only active members.'
        });
        return;
    }
    
    // Get sample member data for display
    const sampleData = getMemberData(selectedMembers[0]);
    
    Swal.fire({
        title: `Cancel ${selectedMembers.length} Member${selectedMembers.length > 1 ? 's' : ''}?`,
        text: `This will set profit to 0 for all selected members. This action cannot be undone.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#f39c12',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, Cancel Them',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            processBulkAction(selectedMembers, 'cancel', 'cancelled');
        }
    });
}

// Bulk reapply selected members
function bulkReapplySelected() {
    const selectedMembers = getSelectedMembers();
    
    if (selectedMembers.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'No Selection',
            text: 'Please select at least one member to re-apply.'
        });
        return;
    }
    
    // Check if any selected members are not cancelled
    const notCancelled = selectedMembers.filter(cb =>
        getMemberData(cb).status !== 'cancelled'
    );
    
    if (notCancelled.length > 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Invalid Selection',
            text: 'Some selected members are already active. Please select only cancelled members.'
        });
        return;
    }
    
    Swal.fire({
        title: `Re-apply ${selectedMembers.length} Member${selectedMembers.length > 1 ? 's' : ''}?`,
        text: `This will recalculate profit (sold - base) for all selected cancelled members.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#27ae60',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, Re-apply Them',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            processBulkAction(selectedMembers, 'reapply', 'active');
        }
    });
}

// Process bulk action
function processBulkAction(selectedMembers, action, newStatus) {
    // Prepare data for bulk processing
    const bulkData = selectedMembers.map(checkbox => getMemberData(checkbox));
    
    // Show loading state
    Swal.fire({
        title: 'Processing...',
        text: `Processing ${selectedMembers.length} member${selectedMembers.length > 1 ? 's' : ''}. Please wait.`,
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    // Send bulk AJAX request
    jQuery.ajax({
        url: 'process_bulk_cancellation_reapply.php',
        type: 'POST',
        data: {
            action: action,
            bookings: JSON.stringify(bulkData),
            new_status: newStatus,
            reason: `Bulk ${action} - processed ${selectedMembers.length} member${selectedMembers.length > 1 ? 's' : ''}`
        },
        success: function(response) {
            try {
                const result = typeof response === 'string' ? JSON.parse(response) : response;
                
                if (result.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: result.message || `Successfully ${action}ed ${selectedMembers.length} member${selectedMembers.length > 1 ? 's' : ''}`,
                        confirmButtonText: 'OK'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: result.message || `Failed to process bulk ${action}`
                    });
                }
            } catch (e) {
                console.error('Error parsing response:', e);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    html: 'Error processing the request: ' + e.message
                });
            }
        },
        error: function(xhr, status, error) {
            console.error('Bulk AJAX Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: `Error processing bulk ${action}: ` + error
            });
        }
    });
}

// Update button states when checkboxes change
jQuery(document).on('change', '.member-checkbox', function() {
    updateBulkButtonStates();
});

// Initialize on page load
jQuery(document).ready(function($) {
    updateBulkButtonStates();
});