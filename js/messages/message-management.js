/**
 * Message Management JS
 * Handles all interactions for the messaging system
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTables with custom options
    const messagesTable = $('#messagesTable').DataTable({
        responsive: true,
        language: {
            search: searchText + ":",
            lengthMenu: showText + " _MENU_ " + entriesText,
            info: showingText + " _START_ " + toText + " _END_ " + ofText + " _TOTAL_ " + totalEntriesText,
            infoFiltered: "(" + filteredFromText + " _MAX_ " + totalEntriesText + ")",
            paginate: {
                first: firstText,
                last: lastText,
                next: nextText,
                previous: previousText
            }
        },
        columnDefs: [
            { targets: 'no-sort', orderable: false }
        ],
        order: [[0, 'desc']], // Sort by date (first column) descending
        pageLength: 10,
        dom: '<"top"lf>rt<"bottom"ip>',
        drawCallback: function() {
            // Reinitialize tooltips after table redraw
            $('[data-toggle="tooltip"]').tooltip();
        }
    });
    
    // Initialize Select2 for better dropdowns
    $('.select2').select2({
        placeholder: selectRecipientText,
        width: '100%',
        allowClear: true
    });
    
    // Toggle recipient select based on recipient type
    window.toggleRecipientSelect = function() {
        const recipientType = document.getElementById('recipient_type').value;
        const recipientSelectGroup = document.getElementById('recipient_select_group');
        
        if (recipientType === 'individual') {
            recipientSelectGroup.style.display = 'block';
            
            // Trigger select2 to recalculate size after showing
            setTimeout(() => {
                $('.select2').trigger('resize.select2');
            }, 10);
        } else {
            recipientSelectGroup.style.display = 'none';
        }
    };
    
    // Toggle recipient select in edit modal
    window.toggleEditRecipientSelect = function() {
        const recipientType = document.getElementById('edit_recipient_type').value;
        const recipientSelectGroup = document.getElementById('edit_recipient_select_group');
        
        if (recipientType === 'individual') {
            recipientSelectGroup.style.display = 'block';
            
            // Trigger select2 to recalculate size after showing
            setTimeout(() => {
                $('.select2').trigger('resize.select2');
            }, 10);
        } else {
            recipientSelectGroup.style.display = 'none';
        }
    };
    
    // View message modal handler
    $(document).on('click', '.view-message', function() {
        const button = $(this);
        const modal = $('#viewMessageModal');
        
        // Get data from button attributes
        const id = button.data('id');
        const subject = button.data('subject');
        const message = button.data('message');
        const sender = button.data('sender');
        const recipient = button.data('recipient');
        const date = button.data('date');
        const readStatus = button.data('read-status');
        
        // Set data in modal
        modal.find('#messageSubject').text(subject);
        modal.find('#messageSender').text(sender);
        modal.find('#messageRecipient').text(recipient);
        modal.find('#messageDate').text(date);
        
        // Format message with line breaks
        modal.find('#messageBody').html(message.replace(/\n/g, '<br>'));
        
        // Set status badge
        if (readStatus === 'read') {
            modal.find('#messageStatus').html('<span class="badge badge-success"><i class="feather icon-check mr-1"></i> ' + readText + '</span>');
        } else {
            modal.find('#messageStatus').html('<span class="badge badge-warning"><i class="feather icon-clock mr-1"></i> ' + unreadText + '</span>');
        }
        
        // Show modal with fade animation
        modal.modal('show');
        
        // Add animation class to modal dialog
        setTimeout(() => {
            modal.find('.modal-dialog').addClass('animated fadeInUp faster');
        }, 10);
    });
    
    // Edit message modal handler
    $(document).on('click', '.edit-message', function() {
        const button = $(this);
        const modal = $('#editMessageModal');
        
        // Get data from button attributes
        const id = button.data('id');
        const subject = button.data('subject');
        const message = button.data('message');
        const recipientType = button.data('recipient-type');
        const recipientId = button.data('recipient-id');
        
        // Set data in modal form
        modal.find('#edit_message_id').val(id);
        modal.find('#edit_subject').val(subject);
        modal.find('#edit_message').val(message);
        modal.find('#edit_recipient_type').val(recipientType);
        
        // Handle recipient type selection
        if (recipientType === 'individual' && recipientId) {
            modal.find('#edit_recipient_select_group').show();
            modal.find('#edit_recipient_id').val(recipientId).trigger('change');
        } else {
            modal.find('#edit_recipient_select_group').hide();
        }
        
        // Show modal with fade animation
        modal.modal('show');
        
        // Add animation class to modal dialog
        setTimeout(() => {
            modal.find('.modal-dialog').addClass('animated fadeInUp faster');
        }, 10);
    });
    
    // Delete message modal handler
    $(document).on('click', '.delete-message', function() {
        const button = $(this);
        const modal = $('#deleteMessageModal');
        
        // Get message ID from button attribute
        const id = button.data('id');
        
        // Set message ID in modal form
        modal.find('#delete_message_id').val(id);
        
        // Show modal with fade animation
        modal.modal('show');
        
        // Add animation class to modal dialog
        setTimeout(() => {
            modal.find('.modal-dialog').addClass('animated fadeInUp faster');
        }, 10);
    });
    
    // Show toast notifications if present
    if (successMessage && !window.toastsDisplayed) {
        showToast('success', successMessage);
        window.toastsDisplayed = true;
    }
    
    if (errorMessage && !window.toastsDisplayed) {
        showToast('error', errorMessage);
        window.toastsDisplayed = true;
    }
    
    // Function to show toast notifications
    function showToast(type, message) {
        const toastContainer = document.querySelector('.toast-container');
        const toastElement = document.createElement('div');
        
        toastElement.className = `toast toast-${type}`;
        
        // Set toast content based on type
        let title = type === 'success' ? 'Success' : 'Error';
        let icon = type === 'success' ? 'check-circle' : 'alert-circle';
        
        toastElement.innerHTML = `
            <div class="toast-title">
                <i class="feather icon-${icon} mr-2"></i>
                ${title}
            </div>
            <div class="toast-message">${message}</div>
        `;
        
        // Add toast to container
        toastContainer.appendChild(toastElement);
        
        // Remove toast after animation completes
        setTimeout(() => {
            toastElement.remove();
        }, 4000);
    }
    
    // Add visual feedback to form fields
    document.querySelectorAll('.form-control').forEach(input => {
        input.addEventListener('focus', () => {
            input.closest('.form-group').classList.add('is-focused');
        });
        
        input.addEventListener('blur', () => {
            input.closest('.form-group').classList.remove('is-focused');
        });
    });
    
    // Enhance form submission with visual feedback
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            // Add spinner to submit button
            const submitBtn = this.querySelector('button[type="submit"]');
            
            if (submitBtn) {
                const originalContent = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm mr-2" role="status" aria-hidden="true"></span> Loading...';
                
                // Restore button after submission (for error cases)
                setTimeout(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalContent;
                }, 2000);
            }
        });
    });
}); 